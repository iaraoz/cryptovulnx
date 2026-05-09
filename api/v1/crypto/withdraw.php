<?php
// CryptoVulnX - Crypto Withdrawal endpoint
// VULN (API1): BOLA, (API5): Sin verificación KYC real

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$user = requireAuth();
$data = getRequestBody();
$conn = getDBConnection();

$wallet_id = $data['wallet_id'] ?? null;
$amount = (float)($data['amount'] ?? 0);
$external_address = $data['external_address'] ?? '';

if (!$wallet_id || $amount <= 0 || empty($external_address)) {
    jsonResponse(['error' => 'wallet_id, amount y external_address son requeridos'], 400);
}

// VULN (API5): Solo verifica kyc_verified del JWT claim, no de la DB
// Un atacante puede fabricar un JWT con kyc_verified: true
if (!$user['kyc_verified']) {
    jsonResponse(['error' => 'Debes completar la verificación KYC para retirar fondos'], 403);
}

// VULN (API1): BOLA - No verifica que wallet_id pertenezca al usuario
$wallet = $conn->query("SELECT * FROM wallets WHERE id = $wallet_id");

if (!$wallet || $wallet->num_rows === 0) {
    jsonResponse(['error' => 'Wallet no encontrada'], 404);
}

$w = $wallet->fetch_assoc();

// VULN: Montos negativos permitidos
if ($w['balance'] < abs($amount)) {
    jsonResponse(['error' => 'Saldo insuficiente'], 400);
}

// VULN (Race Condition)
$conn->query("UPDATE wallets SET balance = balance - $amount WHERE id = $wallet_id");

$txHash = generateTxHash();
$ip = getClientIP();

$conn->query("INSERT INTO transactions (tx_hash, from_wallet_id, amount, currency, tx_type, status, description, ip_address)
              VALUES ('$txHash', $wallet_id, $amount, '" . $w['currency'] . "', 'withdrawal', 'pending', 'Retiro a: $external_address', '$ip')");

logAudit($conn, $user['user_id'], 'withdrawal', '/api/v1/crypto/withdraw');

jsonResponse([
    'success' => true,
    'message' => 'Retiro procesado. Estado: pendiente de confirmación.',
    'withdrawal' => [
        'tx_hash' => $txHash,
        'wallet_id' => (int)$wallet_id,
        'amount' => $amount,
        'currency' => $w['currency'],
        'external_address' => $external_address,
        'status' => 'pending',
        'estimated_time' => '10-30 minutos'
    ]
]);
