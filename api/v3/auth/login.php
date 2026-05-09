<?php
// CryptoVulnX - API v3 Login (EXPERIMENTAL - DEVOPS ONLY)
// VULN (API9): Version experimental aun mas rota que v2
// VULN (API2): Acepta JWT con alg=none directamente desde header Authorization

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/jwt.php';
require_once __DIR__ . '/../../config/helpers.php';

setCORSHeaders();

header("X-API-Version: 3.0.0-experimental");
header("X-API-Warning: DEVOPS USE ONLY - NOT FOR PRODUCTION");

// VULN (API2): Si viene Authorization: Bearer <token> con alg=none, lo acepta
// y permite "login" sin password
$existingToken = getAuthToken();
if ($existingToken) {
    $parts = explode('.', $existingToken);
    if (count($parts) === 3) {
        $headerDecoded = json_decode(base64UrlDecode($parts[0]), true);
        $payloadDecoded = json_decode(base64UrlDecode($parts[1]), true);

        // VULN (API2): Acepta alg=none directo
        if (isset($headerDecoded['alg']) && strtolower($headerDecoded['alg']) === 'none') {
            jsonResponse([
                'success' => true,
                'message' => 'Login via JWT alg=none aceptado',
                'token' => $existingToken,
                'user' => $payloadDecoded,
                'api_version' => '3.0.0-experimental'
            ]);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse([
        'error' => 'Metodo no permitido',
        'hint' => 'POST con username/password, o GET con Authorization: Bearer <jwt-alg-none>',
        'api_version' => '3.0.0-experimental'
    ], 405);
}

$data = getRequestBody();
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

$conn = getDBConnection();

// VULN (API2)(API9): SQL injection sin filtros + sin rate limit + dump verbose
$sql = "SELECT * FROM users WHERE username = '$username' AND password_plain = '$password'";
$result = $conn->query($sql);

if (!$result || $result->num_rows === 0) {
    jsonResponse([
        'error' => 'Credenciales invalidas',
        'debug' => [
            'query' => $sql,
            'mysql_error' => $conn->error,
            'api_version' => '3.0.0-experimental',
            'note' => 'tip: probar con Authorization: Bearer <jwt con alg=none>'
        ]
    ], 401);
}

$user = $result->fetch_assoc();

// VULN (API2): Genera tokens "estandar" pero acepta cualquiera con alg=none
$token = generateJWT([
    'user_id' => (int)$user['id'],
    'username' => $user['username'],
    'role' => $user['role'],
    'api_version' => 'v3-experimental'
]);

// VULN (API3): Devuelve TODO el row, incluyendo password_hash y password_plain
jsonResponse([
    'success' => true,
    'message' => 'Login v3 OK',
    'token' => $token,
    'user_full_record' => $user,
    'api_version' => '3.0.0-experimental',
    'flag' => 'FLAG-INVENT-04: v3_acepta_alg_none_y_devuelve_password_plain'
]);
