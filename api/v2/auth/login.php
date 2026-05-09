<?php
// CryptoVulnX - API v2 Login (Beta)
// VULN (API9): Versión beta expuesta sin rate limiting ni protecciones
// VULN (API9): Misma funcionalidad que v1 pero sin controles de seguridad

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/jwt.php';
require_once __DIR__ . '/../../config/helpers.php';

setCORSHeaders();

// VULN (API9): Header que indica versión beta
header("X-API-Version: 2.0.0-beta");
header("X-API-Warning: This is a beta endpoint. Do not use in production.");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$data = getRequestBody();
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

if (empty($username) || empty($password)) {
    jsonResponse(['error' => 'Username y password son requeridos'], 400);
}

$conn = getDBConnection();

// VULN (API9): Misma SQL injection que v1, pero sin rate limiting
// VULN (API9): Sin logging/auditoría
// VULN (API9): Sin bloqueo de cuenta
$sql = "SELECT id, username, email, password_plain, role, kyc_verified, full_name, phone, address
        FROM users
        WHERE username = '$username' AND password_plain = '$password'";

$result = $conn->query($sql);

if (!$result || $result->num_rows === 0) {
    // VULN (API9): Mensaje de error más verboso que v1
    jsonResponse([
        'error' => 'Credenciales inválidas',
        'debug' => [
            'query_executed' => $sql,
            'rows_found' => $result ? $result->num_rows : 0,
            'server_time' => date('Y-m-d H:i:s'),
            'api_version' => '2.0.0-beta'
        ]
    ], 401);
}

$user = $result->fetch_assoc();

$token = generateJWT([
    'user_id' => (int)$user['id'],
    'username' => $user['username'],
    'email' => $user['email'],
    'role' => $user['role'],
    'kyc_verified' => (bool)$user['kyc_verified'],
    'api_version' => 'v2-beta'
]);

// VULN (API9)(API3): Respuesta v2 expone MÁS datos que v1
jsonResponse([
    'success' => true,
    'message' => 'Login exitoso (API v2 Beta)',
    'token' => $token,
    'user' => [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => $user['role'],
        'full_name' => $user['full_name'],
        'phone' => $user['phone'],
        'address' => $user['address'],
        'kyc_verified' => (bool)$user['kyc_verified'],
        'password_hash_algorithm' => 'bcrypt'    // VULN: Info innecesaria
    ],
    'api_info' => [
        'version' => '2.0.0-beta',
        'rate_limit' => 'none',                  // VULN: Confirma ausencia de rate limit
        'deprecation_notice' => 'This endpoint is under development'
    ]
]);
