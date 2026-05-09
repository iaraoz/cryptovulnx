<?php
// CryptoVulnX - Password Reset endpoint
// VULN (API2): PIN de 4 dígitos brute-forceable, sin rate limiting, sin expiración

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/jwt.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$data = getRequestBody();
$action = $data['action'] ?? 'request';

$conn = getDBConnection();

if ($action === 'request') {
    // Solicitar reset - genera PIN
    $email = $data['email'] ?? '';

    if (empty($email)) {
        jsonResponse(['error' => 'Email es requerido'], 400);
    }

    // VULN (API2): Enumera si el email existe
    $result = $conn->query("SELECT id, username FROM users WHERE email = '$email'");

    if ($result->num_rows === 0) {
        jsonResponse(['error' => "No se encontró cuenta con el email: $email"], 404);
    }

    $user = $result->fetch_assoc();

    // VULN (API2): PIN de solo 4 dígitos, fácilmente brute-forceable (10,000 combinaciones)
    $pin = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

    // Invalidar PINs anteriores
    $conn->query("UPDATE password_resets SET used = 1 WHERE user_id = " . $user['id']);

    // VULN (API2): Sin expiración del PIN
    $conn->query("INSERT INTO password_resets (user_id, pin) VALUES (" . $user['id'] . ", '$pin')");

    logAudit($conn, $user['id'], 'password_reset_request', '/api/v1/auth/reset');

    // VULN (API8): En modo debug, expone el PIN directamente
    $response = [
        'success' => true,
        'message' => 'Se ha enviado un PIN de recuperación a tu email'
    ];

    if (DEBUG_MODE) {
        $response['debug_pin'] = $pin;
        $response['debug_user'] = $user['username'];
        $response['hint'] = 'El PIN tiene 4 dígitos (0000-9999)';
    }

    jsonResponse($response);

} elseif ($action === 'verify') {
    // Verificar PIN y cambiar contraseña
    $email = $data['email'] ?? '';
    $pin = $data['pin'] ?? '';
    $new_password = $data['new_password'] ?? '';

    if (empty($email) || empty($pin) || empty($new_password)) {
        jsonResponse(['error' => 'email, pin y new_password son requeridos'], 400);
    }

    // VULN (API2): Sin rate limiting en verificación de PIN
    // VULN (API2): Sin expiración del PIN
    $sql = "SELECT pr.id, pr.user_id, u.username
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.id
            WHERE u.email = '$email' AND pr.pin = '$pin' AND pr.used = 0
            ORDER BY pr.created_at DESC LIMIT 1";

    $result = $conn->query($sql);

    if ($result->num_rows === 0) {
        jsonResponse(['error' => 'PIN inválido o ya utilizado'], 400);
    }

    $reset = $result->fetch_assoc();

    // VULN (API2): Almacena password en texto plano
    $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
    $conn->query("UPDATE users SET password_plain = '$new_password', password_hash = '$password_hash' WHERE id = " . $reset['user_id']);
    $conn->query("UPDATE password_resets SET used = 1 WHERE id = " . $reset['id']);

    logAudit($conn, $reset['user_id'], 'password_reset_complete', '/api/v1/auth/reset');

    jsonResponse([
        'success' => true,
        'message' => 'Contraseña actualizada exitosamente',
        'username' => $reset['username']
    ]);

} else {
    jsonResponse(['error' => 'Acción no válida. Usa: request o verify'], 400);
}
