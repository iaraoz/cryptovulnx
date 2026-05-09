<?php
// VULN (API8): Endpoint de "test" que los devs usaron para validar conexion DB
// Muestra credenciales y resultado de query

require_once __DIR__ . '/api/config/database.php';

header('Content-Type: text/plain');

echo "=== CryptoVulnX - Connection Test ===\n";
echo "PHP version: " . phpversion() . "\n";
echo "DB_HOST:     " . DB_HOST . "\n";
echo "DB_PORT:     " . DB_PORT . "\n";
echo "DB_NAME:     " . DB_NAME . "\n";
echo "DB_USER:     " . DB_USER . "\n";
echo "DB_PASS:     " . DB_PASS . "\n";
echo "JWT_SECRET:  " . JWT_SECRET . "\n";
echo "DEBUG:       " . (DEBUG_MODE ? 'true' : 'false') . "\n";
echo "APP_ENV:     " . APP_ENV . "\n\n";

$conn = getDBConnection();
echo "MySQL server: " . $conn->server_info . "\n";
echo "Hostname:     " . gethostname() . "\n\n";

$result = $conn->query("SELECT COUNT(*) as total FROM users");
if ($result) {
    $row = $result->fetch_assoc();
    echo "Total users in DB: " . $row['total'] . "\n";
}

echo "\n=== END ===\n";
