<?php
// CryptoVulnX - admin/backup
// VULN (API5)(API8): Descarga backup de DB sin auth solida
// Solo requiere conocer el endpoint

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/magic.php';

// VULN: Auth opcional - cualquier "internal" o admin token sirve
if (!hasAdminBypass() && !isInternalRequest()) {
    // Si no es interno, igual permite con un token simple en query
    if (($_GET['token'] ?? '') !== 'backup_static_2024') {
        jsonResponse([
            'error' => 'Unauthorized',
            'hint' => 'Necesitas X-Admin-Token, Cookie internal_user=admin, IP interna, o ?token=backup_static_2024'
        ], 401);
    }
}

setCORSHeaders();
header("Content-Type: application/sql; charset=utf-8");
header("Content-Disposition: attachment; filename=cryptovulnx_backup_" . date('Y-m-d_His') . ".sql");

$conn = getDBConnection();

echo "-- CryptoVulnX backup generado on-demand\n";
echo "-- Hostname: " . gethostname() . "\n";
echo "-- Timestamp: " . date('c') . "\n";
echo "-- DB: " . DB_NAME . "\n\n";
echo "-- FLAG-ENDPOINT-01: backup_php_dumpea_DB_completa\n\n";

$tables = [];
$res = $conn->query("SHOW TABLES");
while ($row = $res->fetch_array()) { $tables[] = $row[0]; }

foreach ($tables as $table) {
    echo "-- ----------------------------\n";
    echo "-- Table: $table\n";
    echo "-- ----------------------------\n";

    $createRes = $conn->query("SHOW CREATE TABLE `$table`");
    if ($createRes) {
        $createRow = $createRes->fetch_array();
        echo $createRow[1] . ";\n\n";
    }

    $rows = $conn->query("SELECT * FROM `$table`");
    if ($rows) {
        while ($row = $rows->fetch_assoc()) {
            $cols = '`' . implode('`,`', array_keys($row)) . '`';
            $vals = array_map(function($v) use ($conn) {
                if ($v === null) return 'NULL';
                return "'" . $conn->real_escape_string($v) . "'";
            }, array_values($row));
            echo "INSERT INTO `$table` ($cols) VALUES (" . implode(',', $vals) . ");\n";
        }
        echo "\n";
    }
}
