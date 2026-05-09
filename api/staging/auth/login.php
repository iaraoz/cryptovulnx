<?php
// CryptoVulnX - API staging/auth/login (mirror de produccion sin rate limit)
// VULN (API9)(API4): Mirror de prod sin rate limit, apunta a la misma DB

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/jwt.php';
require_once __DIR__ . '/../../config/helpers.php';

setCORSHeaders();
header("X-API-Environment: staging");
header("X-API-Warning: Mirror de produccion - usar para load testing");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Metodo no permitido'], 405);
}

$data = getRequestBody();
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

$conn = getDBConnection();

// VULN: SQL injection identica a v1, sin rate limit, sin lockout
$sql = "SELECT id, username, role FROM users WHERE username = '$username' AND password_plain = '$password'";
$result = $conn->query($sql);

if (!$result || $result->num_rows === 0) {
    jsonResponse([
        'error' => 'Invalid credentials',
        'environment' => 'staging',
        'rate_limit' => 'disabled',
        'flag' => 'FLAG-INVENT-08: staging_apunta_a_misma_db_sin_rate_limit'
    ], 401);
}

$user = $result->fetch_assoc();
$token = generateJWT([
    'user_id' => (int)$user['id'],
    'username' => $user['username'],
    'role' => $user['role'],
    'env' => 'staging'
]);

jsonResponse([
    'success' => true,
    'token' => $token,
    'environment' => 'staging'
]);
