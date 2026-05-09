<?php
// CryptoVulnX - Transfer endpoint
// VULN (API1): BOLA, (API4): Sin rate limit, (API6): Sin protección contra automatización

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/magic.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
emitMagicSignalsHeader();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$user = requireAuth();
$data = getRequestBody();
$conn = getDBConnection();

$from_wallet_id = $data['from_wallet_id'] ?? null;
$to_wallet_address = $data['to_wallet_address'] ?? '';
$amount = $data['amount'] ?? 0;
$description = $data['description'] ?? '';

// VULN (API3): Body params ocultos no documentados
// fee_override, skip_validation, as_user_id - todos detectables con arjun --post
$fee_override = isset($data['fee_override']) ? (float)$data['fee_override'] : null;
$skip_validation = !empty($data['skip_validation']);
$as_user_id = isset($data['as_user_id']) ? (int)$data['as_user_id'] : null;
if ($as_user_id) {
    $user['user_id'] = $as_user_id;
    header("X-Impersonation-Body: as_user_id=$as_user_id");
}

if (!$from_wallet_id || empty($to_wallet_address) || !$amount) {
    jsonResponse(['error' => 'from_wallet_id, to_wallet_address y amount son requeridos'], 400);
}

// VULN (API1): BOLA - No verifica que from_wallet_id pertenezca al usuario autenticado
// Un atacante puede transferir desde la wallet de otro usuario
$fromWallet = $conn->query("SELECT * FROM wallets WHERE id = $from_wallet_id");

if (!$fromWallet || $fromWallet->num_rows === 0) {
    jsonResponse(['error' => 'Wallet de origen no encontrada'], 404);
}

$from = $fromWallet->fetch_assoc();

// VULN (API6): Sin límite de monto por transacción
// VULN: Acepta montos negativos - permite invertir transferencias
$amount = (float)$amount;

if (!$skip_validation && $from['balance'] < abs($amount)) {
    jsonResponse(['error' => 'Saldo insuficiente', 'balance' => (float)$from['balance']], 400);
}
if ($skip_validation) {
    header("X-Validation-Skipped: true (body param skip_validation=1)");
}

// Buscar wallet destino
$toWallet = $conn->query("SELECT * FROM wallets WHERE wallet_address = '$to_wallet_address'");

if (!$toWallet || $toWallet->num_rows === 0) {
    jsonResponse(['error' => 'Wallet de destino no encontrada'], 404);
}

$to = $toWallet->fetch_assoc();

if ($from['currency'] !== $to['currency']) {
    jsonResponse(['error' => 'Las wallets deben tener la misma moneda. Usa /api/v1/crypto/swap para conversiones'], 400);
}

// VULN (Race Condition): No usa transacciones ni locks
// Ejecutar transferencia sin BEGIN TRANSACTION
$conn->query("UPDATE wallets SET balance = balance - $amount WHERE id = " . $from['id']);
$conn->query("UPDATE wallets SET balance = balance + $amount WHERE id = " . $to['id']);

$txHash = generateTxHash();
$ip = getClientIP();

// VULN: SQL Injection en description
$sql = "INSERT INTO transactions (tx_hash, from_wallet_id, to_wallet_id, amount, currency, tx_type, status, description, ip_address)
        VALUES ('$txHash', " . $from['id'] . ", " . $to['id'] . ", $amount, '" . $from['currency'] . "', 'transfer', 'completed', '$description', '$ip')";

$conn->query($sql);

logAudit($conn, $user['user_id'], 'transfer', '/api/v1/wallets/transfer');

$resp = [
    'success' => true,
    'message' => 'Transferencia completada',
    'transaction' => [
        'tx_hash' => $txHash,
        'from_wallet' => $from['wallet_address'],
        'to_wallet' => $to['wallet_address'],
        'amount' => $amount,
        'currency' => $from['currency'],
        'status' => 'completed',
        'new_balance' => (float)$from['balance'] - $amount,
        'fee_applied' => $fee_override !== null ? $fee_override : 0.0
    ]
];

if ($skip_validation || $as_user_id || $fee_override !== null) {
    $resp['_magic_body_params_used'] = [
        'skip_validation' => $skip_validation,
        'as_user_id' => $as_user_id,
        'fee_override' => $fee_override,
        'flag' => 'FLAG-PARAM-04: body_params_ocultos_en_transfer'
    ];
}

jsonResponse($resp);
