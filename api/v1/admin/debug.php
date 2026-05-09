<?php
// CryptoVulnX - Debug endpoint
// VULN (API8): Endpoint de debug expuesto, (API9): Sin autenticación

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

setCORSHeaders();

// VULN (API8): Sin autenticación en endpoint de debug
// VULN (API8): Expone toda la configuración del servidor

$conn = getDBConnection();

$action = $_GET['action'] ?? 'info';

if ($action === 'info') {
    jsonResponse([
        'success' => true,
        'server_info' => [
            'php_version' => phpversion(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown',
            'server_port' => $_SERVER['SERVER_PORT'] ?? 'unknown',
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ],
        'database' => [
            'host' => DB_HOST,
            'port' => DB_PORT,
            'name' => DB_NAME,
            'user' => DB_USER,
            'password' => DB_PASS,    // VULN (API8): Expone password de DB
            'server_info' => $conn->server_info
        ],
        'jwt_config' => [
            'secret' => JWT_SECRET,    // VULN (API8): Expone JWT secret
            'algorithm' => JWT_ALGORITHM
        ],
        'environment' => [
            'debug_mode' => DEBUG_MODE,
            'app_env' => APP_ENV
        ],
        'loaded_extensions' => get_loaded_extensions()
    ]);

} elseif ($action === 'users') {
    // VULN (API8): Dump completo de usuarios sin autenticación
    $result = $conn->query("SELECT * FROM users");
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    jsonResponse(['success' => true, 'users' => $users]);

} elseif ($action === 'env') {
    // VULN (API8): Expone variables de entorno
    jsonResponse([
        'success' => true,
        'environment_variables' => getenv()
    ]);

} elseif ($action === 'phpinfo') {
    // VULN (API8): phpinfo completo
    phpinfo();
    exit;

} else {
    jsonResponse([
        'error' => 'Acción no válida',
        'available_actions' => ['info', 'users', 'env', 'phpinfo'],
        'hint' => 'Usa ?action=info para ver la configuración del servidor'
    ], 400);
}
