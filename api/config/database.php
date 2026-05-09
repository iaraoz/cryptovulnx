<?php
// CryptoVulnX - Configuración de base de datos
// VULN (API8): Credenciales hardcodeadas, debug habilitado

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'cryptovulnx');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DEBUG_MODE', getenv('DEBUG') ?: true);
define('APP_ENV', getenv('APP_ENV') ?: 'development');

function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

    if ($conn->connect_error) {
        if (DEBUG_MODE) {
            // VULN (API8): Expone detalles de conexión en error
            die(json_encode([
                'error' => 'Database connection failed',
                'details' => $conn->connect_error,
                'host' => DB_HOST,
                'port' => DB_PORT,
                'database' => DB_NAME,
                'user' => DB_USER
            ]));
        }
        die(json_encode(['error' => 'Internal server error']));
    }

    $conn->set_charset("utf8mb4");
    return $conn;
}
