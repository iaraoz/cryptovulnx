<?php
// CryptoVulnX - API test/users (QA fixture)
// VULN (API9)(API1): Endpoint de testing dejado en prod, dump completo sin auth

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

setCORSHeaders();
header("X-API-Environment: testing");

$conn = getDBConnection();

// VULN: dump completo de usuarios incluyendo password_plain, password_hash, role
$result = $conn->query("SELECT * FROM users ORDER BY id ASC");
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// VULN: tambien dumpea wallets con private_key
$walletsRes = $conn->query("SELECT * FROM wallets");
$wallets = [];
while ($row = $walletsRes->fetch_assoc()) {
    $wallets[] = $row;
}

jsonResponse([
    'success' => true,
    'environment' => 'testing',
    'note' => 'QA fixture - DO NOT EXPOSE',
    'users' => $users,
    'wallets' => $wallets,
    'flag' => 'FLAG-INVENT-06: test_users_dumpea_todo_sin_auth'
]);
