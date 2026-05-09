<?php
// CryptoVulnX - wallets/private-keys
// VULN (API1)(API3): Dump de private keys de TODAS las wallets

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/magic.php';

setCORSHeaders();
emitMagicSignalsHeader();

// VULN: "Auth" superficial
if (!hasAdminBypass() && !isInternalRequest()) {
    jsonResponse([
        'error' => 'Unauthorized',
        'hint' => 'Necesitas X-Admin-Token o Cookie internal_user=admin'
    ], 401);
}

$conn = getDBConnection();
$result = $conn->query("SELECT w.id, w.user_id, w.wallet_address, w.currency, w.balance, w.private_key,
                              u.username, u.email
                       FROM wallets w
                       JOIN users u ON w.user_id = u.id
                       ORDER BY w.balance DESC");

$wallets = [];
while ($row = $result->fetch_assoc()) {
    $wallets[] = $row;
}

jsonResponse([
    'success' => true,
    'count' => count($wallets),
    'wallets' => $wallets,
    'flag' => 'FLAG-ENDPOINT-05: private_keys_dump_completo'
]);
