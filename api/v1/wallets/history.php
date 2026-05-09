<?php
// CryptoVulnX - Transaction History endpoint
// VULN (API1): BOLA, (API4): Sin paginación/límite, SQL Injection

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$user = requireAuth();
$conn = getDBConnection();

$wallet_id = $_GET['wallet_id'] ?? null;
$status = $_GET['status'] ?? '';
$currency = $_GET['currency'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';

// VULN (API4): Sin paginación - devuelve TODAS las transacciones
// VULN (API1): BOLA - No verifica ownership de wallet_id

$sql = "SELECT t.*, fw.wallet_address as from_address, tw.wallet_address as to_address
        FROM transactions t
        LEFT JOIN wallets fw ON t.from_wallet_id = fw.id
        LEFT JOIN wallets tw ON t.to_wallet_id = tw.id
        WHERE 1=1";

if ($wallet_id) {
    // VULN (API1): Cualquier usuario puede ver transacciones de cualquier wallet
    $sql .= " AND (t.from_wallet_id = '$wallet_id' OR t.to_wallet_id = '$wallet_id')";
}

// VULN: SQL Injection via parámetro status
if (!empty($status)) {
    $sql .= " AND t.status = '$status'";
}

if (!empty($currency)) {
    $sql .= " AND t.currency = '$currency'";
}

// VULN: SQL Injection via parámetro sort - ORDER BY injection
$sql .= " ORDER BY $sort $order";

$result = $conn->query($sql);

if (!$result) {
    if (DEBUG_MODE) {
        jsonResponse([
            'error' => 'Error en consulta',
            'sql_error' => $conn->error,
            'query' => $sql
        ], 500);
    }
    jsonResponse(['error' => 'Error interno'], 500);
}

$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = [
        'id' => (int)$row['id'],
        'tx_hash' => $row['tx_hash'],
        'from_address' => $row['from_address'],
        'to_address' => $row['to_address'],
        'amount' => (float)$row['amount'],
        'currency' => $row['currency'],
        'tx_type' => $row['tx_type'],
        'status' => $row['status'],
        'description' => $row['description'],
        'ip_address' => $row['ip_address'], // VULN (API3): Expone IP
        'created_at' => $row['created_at']
    ];
}

// VULN (API4): Sin limit, expone count total
jsonResponse([
    'success' => true,
    'total_count' => count($transactions),
    'transactions' => $transactions
]);
