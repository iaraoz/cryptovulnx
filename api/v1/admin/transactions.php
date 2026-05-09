<?php
// CryptoVulnX - Admin Transactions endpoint
// VULN (API5): BFLA, (API4): Sin paginación

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // VULN (API5): Verifica admin solo por JWT claim
    $user = requireAdmin();

    $sql = "SELECT t.*, fw.wallet_address as from_address, fw.user_id as from_user_id,
                   tw.wallet_address as to_address, tw.user_id as to_user_id
            FROM transactions t
            LEFT JOIN wallets fw ON t.from_wallet_id = fw.id
            LEFT JOIN wallets tw ON t.to_wallet_id = tw.id
            ORDER BY t.created_at DESC";

    // VULN (API4): Sin paginación - devuelve todas las transacciones
    $result = $conn->query($sql);

    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }

    jsonResponse([
        'success' => true,
        'total' => count($transactions),
        'transactions' => $transactions
    ]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // VULN (API5): Solo requiere auth básica para DELETE, no admin
    $user = requireAuth();

    $txId = $_GET['id'] ?? null;
    if (!$txId) {
        jsonResponse(['error' => 'id de transacción requerido'], 400);
    }

    $conn->query("DELETE FROM transactions WHERE id = $txId");

    jsonResponse([
        'success' => true,
        'message' => "Transacción $txId eliminada"
    ]);

} else {
    jsonResponse(['error' => 'Método no permitido'], 405);
}
