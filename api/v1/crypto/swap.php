<?php
// CryptoVulnX - Crypto Swap endpoint
// VULN (API3): Rate manipulable desde cliente, (API6): Sin cooldown/límite de swaps

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

$from_currency = $data['from_currency'] ?? '';
$to_currency = $data['to_currency'] ?? '';
$amount = (float)($data['amount'] ?? 0);

// VULN (API3): Mass Assignment - el cliente puede enviar su propia tasa de cambio
$custom_rate = $data['rate'] ?? $data['rate_override'] ?? null;

// VULN (API3): bypass_kyc, fee_override - body params ocultos
$bypass_kyc = !empty($data['bypass_kyc']);
$fee_override = isset($data['fee_override']) ? (float)$data['fee_override'] : null;

if (empty($from_currency) || empty($to_currency) || $amount <= 0) {
    jsonResponse(['error' => 'from_currency, to_currency y amount (>0) son requeridos'], 400);
}

$userId = $user['user_id'];

// Verificar wallet de origen
$fromWallet = $conn->query("SELECT * FROM wallets WHERE user_id = $userId AND currency = '$from_currency'");
if (!$fromWallet || $fromWallet->num_rows === 0) {
    jsonResponse(['error' => "No tienes wallet de $from_currency"], 404);
}
$from = $fromWallet->fetch_assoc();

if ($from['balance'] < $amount) {
    jsonResponse(['error' => 'Saldo insuficiente', 'balance' => (float)$from['balance']], 400);
}

// Obtener o usar rate
if ($custom_rate !== null) {
    // VULN (API3): Acepta rate del cliente sin validación
    // Un atacante puede enviar rate: 999999 para obtener una cantidad masiva
    $rate = (float)$custom_rate;
} else {
    // Obtener precios de DB
    $fromPrice = $conn->query("SELECT price_usd FROM crypto_prices WHERE symbol = '$from_currency'")->fetch_assoc();
    $toPrice = $conn->query("SELECT price_usd FROM crypto_prices WHERE symbol = '$to_currency'")->fetch_assoc();

    if (!$fromPrice || !$toPrice) {
        jsonResponse(['error' => 'No se encontró precio para una de las monedas'], 404);
    }

    $rate = $fromPrice['price_usd'] / $toPrice['price_usd'];
}

$result_amount = $amount * $rate;

// Verificar/crear wallet destino
$toWallet = $conn->query("SELECT * FROM wallets WHERE user_id = $userId AND currency = '$to_currency'");
if (!$toWallet || $toWallet->num_rows === 0) {
    // Auto-crear wallet
    $walletAddr = generateWalletAddress(substr($user['username'], 0, 5), $userId, $to_currency);
    $pk = 'pk_' . $user['username'] . '_' . strtolower($to_currency) . '_' . md5(time());
    $conn->query("INSERT INTO wallets (user_id, wallet_address, currency, balance, private_key)
                  VALUES ($userId, '$walletAddr', '$to_currency', 0, '$pk')");
    $toWalletId = $conn->insert_id;
} else {
    $to = $toWallet->fetch_assoc();
    $toWalletId = $to['id'];
}

// VULN (Race Condition): Sin transacción atómica
// VULN (API6): Sin cooldown entre swaps - permite arbitraje automatizado
$conn->query("UPDATE wallets SET balance = balance - $amount WHERE id = " . $from['id']);
$conn->query("UPDATE wallets SET balance = balance + $result_amount WHERE id = $toWalletId");

// Registrar swap
$conn->query("INSERT INTO swaps (user_id, from_currency, to_currency, amount, rate_applied, result_amount, status)
              VALUES ($userId, '$from_currency', '$to_currency', $amount, $rate, $result_amount, 'completed')");

logAudit($conn, $userId, 'swap', '/api/v1/crypto/swap');

$resp = [
    'success' => true,
    'message' => 'Swap completado exitosamente',
    'swap' => [
        'from_currency' => $from_currency,
        'to_currency' => $to_currency,
        'amount' => $amount,
        'rate_applied' => $rate,
        'result_amount' => $result_amount,
        'custom_rate_used' => $custom_rate !== null,
        'bypass_kyc' => $bypass_kyc,
        'fee_override' => $fee_override
    ]
];

if ($bypass_kyc || $fee_override !== null || $custom_rate !== null) {
    $resp['_magic_body_params_used'] = [
        'rate_override' => $custom_rate,
        'bypass_kyc' => $bypass_kyc,
        'fee_override' => $fee_override,
        'flag' => 'FLAG-PARAM-05: body_params_ocultos_en_swap'
    ];
}

jsonResponse($resp);
