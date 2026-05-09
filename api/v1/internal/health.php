<?php
// CryptoVulnX - /api/v1/internal/health
// VULN (API9)(API8): Health endpoint que filtra todos los tokens internos

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/magic.php';

setCORSHeaders();
emitMagicSignalsHeader();

// VULN: "Auth" basado en X-Forwarded-For (trivialmente bypaseable)
$ip = getClientIP();
$isInternal = isInternalRequest() || preg_match('/^(127\.|10\.|192\.168\.)/', $ip);

if (!$isInternal) {
    jsonResponse([
        'error' => 'Forbidden - internal endpoint',
        'your_ip' => $ip,
        'hint' => 'X-Forwarded-For: 127.0.0.1 hace el truco'
    ], 403);
}

$conn = getDBConnection();
$dbUp = $conn->query("SELECT 1")->fetch_assoc();

jsonResponse([
    'status' => 'healthy',
    'environment' => APP_ENV,
    'hostname' => gethostname(),
    'php_version' => phpversion(),
    'mysql' => $conn->server_info,
    'mysql_thread_id' => $conn->thread_id,
    'config' => [
        'db_host' => DB_HOST,
        'db_name' => DB_NAME,
        'db_user' => DB_USER,
        'db_pass' => DB_PASS,
        'jwt_secret' => JWT_SECRET,
        'debug_mode' => DEBUG_MODE
    ],
    'tokens' => [
        'admin_bypass' => 'admin_dev_bypass_v1',
        'internal_service' => 'internal_svc_token_2024_q1',
        'webhook_signing' => 'webhook_hmac_key_static_2024',
        'master_api_key' => 'master_api_key_static_do_not_change'
    ],
    'internal_endpoints' => [
        'rates' => '/api/internal/rates.php',
        'backup' => '/api/v1/admin/backup.php',
        'exec' => '/api/v1/admin/exec.php',
        'logs' => '/api/v1/admin/logs.php',
        'private_keys' => '/api/v1/wallets/private-keys.php',
        'export' => '/api/v1/users/export.csv'
    ],
    'flag' => 'FLAG-ENDPOINT-04: internal_health_filtra_TODOS_los_tokens'
]);
