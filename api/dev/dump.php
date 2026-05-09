<?php
// CryptoVulnX - API dev/dump (sandbox)
// VULN (API9): Sandbox de dev expuesto en docroot publico
// Permite ejecutar consultas SELECT arbitrarias

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

setCORSHeaders();
header("X-API-Environment: development-sandbox");

$conn = getDBConnection();

// VULN: query arbitraria del cliente
$query = $_GET['q'] ?? "SELECT table_name FROM information_schema.tables WHERE table_schema='cryptovulnx'";

$result = $conn->query($query);

if (!$result) {
    jsonResponse([
        'error' => 'Query failed',
        'mysql_error' => $conn->error,
        'query' => $query,
        'hint' => 'Ej: ?q=SELECT * FROM internal_config'
    ], 500);
}

$rows = [];
if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}

jsonResponse([
    'success' => true,
    'query' => $query,
    'row_count' => count($rows),
    'rows' => $rows,
    'flag' => 'FLAG-INVENT-07: dev_dump_permite_query_arbitraria'
]);
