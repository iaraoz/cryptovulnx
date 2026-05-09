<?php
// CryptoVulnX - Admin Users endpoint
// VULN (API5): BFLA - verificación de rol solo por JWT claim, no por DB

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/magic.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
emitMagicSignalsHeader();

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // VULN (API5): Si el cliente trae bypass de admin (header X-Admin-Token o cookie internal_user=admin)
    // o viene de IP interna, salteamos requireAdmin y permitimos acceso
    if (!hasAdminBypass() && !isInternalRequest()) {
        $user = requireAdmin();
    } else {
        header("X-Admin-Bypass: true");
    }

    $search = $_GET['search'] ?? '';
    $roleFilter = $_GET['role'] ?? '';
    $sql = "SELECT id, username, email, password_plain, role, kyc_verified, kyc_level,
                   referral_code, referral_bonus, full_name, phone, address, created_at
            FROM users";

    $where = [];
    if (!empty($search)) {
        // VULN: SQL Injection
        $where[] = "(username LIKE '%$search%' OR email LIKE '%$search%' OR full_name LIKE '%$search%')";
    }
    if (!empty($roleFilter)) {
        // VULN: SQL Injection en role
        $where[] = "role = '$roleFilter'";
    }
    if ($where) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }

    $sql .= " ORDER BY id ASC";

    $result = $conn->query($sql);

    if (!$result) {
        if (DEBUG_MODE) {
            jsonResponse(['error' => 'Error en consulta', 'sql_error' => $conn->error, 'query' => $sql], 500);
        }
        jsonResponse(['error' => 'Error interno'], 500);
    }

    $users = [];
    while ($row = $result->fetch_assoc()) {
        // VULN (API3): Expone password en texto plano y todos los datos
        $users[] = $row;
    }

    jsonResponse([
        'success' => true,
        'total_users' => count($users),
        'users' => $users
    ]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $user = requireAdmin();
    $data = getRequestBody();
    $targetUserId = $_GET['id'] ?? $data['id'] ?? null;

    if (!$targetUserId) {
        jsonResponse(['error' => 'id del usuario es requerido'], 400);
    }

    // VULN (API3): Mass assignment - actualiza cualquier campo enviado
    $updates = [];
    $allowedFields = ['username', 'email', 'role', 'kyc_verified', 'kyc_level', 'full_name', 'phone', 'address', 'password_plain', 'referral_bonus'];

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = '" . $data[$field] . "'";
        }
    }

    if (empty($updates)) {
        jsonResponse(['error' => 'No se proporcionaron campos para actualizar'], 400);
    }

    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = $targetUserId";
    $conn->query($sql);

    jsonResponse([
        'success' => true,
        'message' => 'Usuario actualizado',
        'updated_fields' => array_keys(array_intersect_key($data, array_flip($allowedFields)))
    ]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // VULN (API5): DELETE no requiere autenticación de admin via método HTTP
    // Solo verifica auth básica, no admin
    $user = requireAuth();

    $targetUserId = $_GET['id'] ?? null;
    if (!$targetUserId) {
        jsonResponse(['error' => 'id del usuario es requerido'], 400);
    }

    $conn->query("DELETE FROM users WHERE id = $targetUserId");

    jsonResponse([
        'success' => true,
        'message' => "Usuario $targetUserId eliminado"
    ]);

} else {
    jsonResponse(['error' => 'Método no permitido'], 405);
}
