<?php
// CryptoVulnX - Login endpoint
// VULN (API2): SQL Injection, sin rate limiting, password en texto plano

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/jwt.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/magic.php';

setCORSHeaders();
emitMagicSignalsHeader();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$data = getRequestBody();
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

if (empty($username) || empty($password)) {
    jsonResponse(['error' => 'Username y password son requeridos'], 400);
}

// VULN (API4): "Rate limit" simulado por sesion (5 intentos por IP).
// Bypass via ?bypass_rate_limit=1, header X-Bypass-RateLimit:1, o IP interna (XFF).
session_start();
$attemptsKey = 'login_attempts_' . md5(getClientIP());
$_SESSION[$attemptsKey] = ($_SESSION[$attemptsKey] ?? 0) + 1;

if (!wantsRateLimitBypass() && !isInternalRequest()) {
    if ($_SESSION[$attemptsKey] > 5) {
        jsonResponse([
            'error' => 'Rate limit excedido. Espera un momento.',
            'attempts' => $_SESSION[$attemptsKey],
            'hint_for_pentesters' => 'Hay query params y headers ocultos para saltear esto. Mira /api/v1/openapi.json',
            'flag' => 'FLAG-PARAM-02: rate_limit_se_bypassa_con_query_o_header_o_xff'
        ], 429);
    }
} else {
    header("X-RateLimit-Bypassed: true");
}

$conn = getDBConnection();

// VULN (API2): SQL Injection - concatenación directa de input del usuario
$sql = "SELECT id, username, email, password_plain, role, kyc_verified, full_name
        FROM users
        WHERE username = '$username' AND password_plain = '$password'";

$result = $conn->query($sql);

if (!$result) {
    // VULN (API8): Expone error SQL completo
    if (DEBUG_MODE) {
        jsonResponse([
            'error' => 'Error en la consulta',
            'sql_error' => $conn->error,
            'query' => $sql
        ], 500);
    }
    jsonResponse(['error' => 'Error interno del servidor'], 500);
}

if ($result->num_rows === 0) {
    // VULN (API2): Enumera usuarios - mensaje diferente si el usuario existe
    $checkUser = $conn->query("SELECT id FROM users WHERE username = '$username'");
    if ($checkUser && $checkUser->num_rows > 0) {
        jsonResponse(['error' => 'Contraseña incorrecta para el usuario: ' . $username], 401);
    }
    jsonResponse(['error' => 'Usuario no encontrado'], 401);
}

$user = $result->fetch_assoc();

// Reset rate limit on success
$_SESSION[$attemptsKey] = 0;

// Generar JWT
$token = generateJWT([
    'user_id' => (int)$user['id'],
    'username' => $user['username'],
    'email' => $user['email'],
    'role' => $user['role'],
    'kyc_verified' => (bool)$user['kyc_verified']
]);

logAudit($conn, $user['id'], 'login', '/api/v1/auth/login');

$response = [
    'success' => true,
    'message' => 'Login exitoso',
    'token' => $token,
    'user' => [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => $user['role'],
        'full_name' => $user['full_name'],
        'kyc_verified' => (bool)$user['kyc_verified']
    ]
];

if (isDebugRequested()) {
    $response['_debug'] = [
        'query' => $sql,
        'jwt_secret_used' => JWT_SECRET,
        'attempts_used' => $_SESSION[$attemptsKey],
        'client_ip' => getClientIP()
    ];
}

jsonResponse($response);
