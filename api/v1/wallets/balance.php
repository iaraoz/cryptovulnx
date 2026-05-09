<?php
// CryptoVulnX - Wallet Balance endpoint
// VULN (API1): BOLA - acceso a balance de cualquier wallet sin verificar ownership
// VULN (API3): Mass exposure via magic params (?debug, ?include, ?fields)
// VULN (API5): impersonacion via ?as_user_id=N

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/magic.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
emitMagicSignalsHeader();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$user = requireAuth();
$conn = getDBConnection();

// VULN: ?as_user_id=N permite impersonar a otro user
$impersonate = getImpersonationUserId();
if ($impersonate) {
    $user['user_id'] = $impersonate;
    if (isDebugRequested()) {
        header("X-Impersonation: user_id=$impersonate (legacy as_user_id param)");
    }
}

$wallet_id = $_GET['wallet_id'] ?? null;

if ($wallet_id) {
    // VULN (API1): BOLA - No verifica ownership
    $sql = "SELECT w.*, u.username, u.email, u.full_name, u.password_plain, u.role, u.kyc_verified
            FROM wallets w
            JOIN users u ON w.user_id = u.id
            WHERE w.id = '$wallet_id'";

    $result = $conn->query($sql);

    if (!$result || $result->num_rows === 0) {
        jsonResponse(['error' => 'Wallet no encontrada'], 404);
    }

    $wallet = $result->fetch_assoc();

    // Respuesta basica (publica)
    $response = [
        'success' => true,
        'wallet' => [
            'id' => (int)$wallet['id'],
            'wallet_address' => $wallet['wallet_address'],
            'currency' => $wallet['currency'],
            'balance' => (float)$wallet['balance'],
            'is_active' => (bool)$wallet['is_active'],
            'created_at' => $wallet['created_at']
        ]
    ];

    // VULN (API3): ?include=private_key,referral_code,*
    $include = getIncludeFields();
    if (in_array('*', $include) || in_array('private_key', $include)) {
        $response['wallet']['private_key'] = $wallet['private_key'];
    }
    if (in_array('*', $include) || in_array('owner', $include)) {
        $response['wallet']['owner'] = [
            'user_id' => (int)$wallet['user_id'],
            'username' => $wallet['username'],
            'email' => $wallet['email'],
            'full_name' => $wallet['full_name']
        ];
    }
    if (in_array('*', $include)) {
        // Dump entero
        $response['wallet']['_raw'] = $wallet;
    }

    // VULN (API3): ?fields=* dump completo del row
    if (wantsAllFields()) {
        $response['wallet']['_dump'] = $wallet;
    }

    // VULN (API8): ?debug=1 expone query SQL y conexion
    if (isDebugRequested()) {
        $response['_debug'] = [
            'query' => $sql,
            'rows' => $result->num_rows,
            'mysql_thread_id' => $conn->thread_id,
            'flag' => 'FLAG-PARAM-01: query_param_debug_expone_sql'
        ];
    }

    jsonResponse($response);
} else {
    $userId = $user['user_id'];
    $result = $conn->query("SELECT * FROM wallets WHERE user_id = $userId");

    $wallets = [];
    while ($row = $result->fetch_assoc()) {
        $w = [
            'id' => (int)$row['id'],
            'wallet_address' => $row['wallet_address'],
            'currency' => $row['currency'],
            'balance' => (float)$row['balance'],
            'is_active' => (bool)$row['is_active']
        ];

        $include = getIncludeFields();
        if (in_array('*', $include) || in_array('private_key', $include)) {
            $w['private_key'] = $row['private_key'];
        }
        if (wantsAllFields()) {
            $w['_dump'] = $row;
        }

        $wallets[] = $w;
    }

    jsonResponse([
        'success' => true,
        'user_id' => $userId,
        'wallets' => $wallets
    ]);
}
