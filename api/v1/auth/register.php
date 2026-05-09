<?php
// CryptoVulnX - Register endpoint
// VULN (API3): Mass assignment - acepta role, kyc_verified del cliente

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/jwt.php';
require_once __DIR__ . '/../../config/helpers.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$data = getRequestBody();

$username = $data['username'] ?? '';
$email = $data['email'] ?? '';
$password = $data['password'] ?? '';
$full_name = $data['full_name'] ?? '';
$phone = $data['phone'] ?? '';

// VULN (API3): Mass Assignment - estos campos no deberían ser aceptados del cliente
$role = $data['role'] ?? 'user';
$kyc_verified = $data['kyc_verified'] ?? 0;
$kyc_level = $data['kyc_level'] ?? 0;
$referral_bonus = $data['referral_bonus'] ?? 0;
$referred_by = $data['referred_by'] ?? '';

if (empty($username) || empty($email) || empty($password)) {
    jsonResponse(['error' => 'username, email y password son requeridos'], 400);
}

// VULN (API2): Sin validación de fortaleza de contraseña
// VULN (API2): Almacena password en texto plano
$password_hash = password_hash($password, PASSWORD_BCRYPT);

$referral_code = 'REF-' . strtoupper(substr($username, 0, 4)) . rand(10, 99);

$conn = getDBConnection();

// VULN (API3): Inserta TODOS los campos recibidos del cliente, incluyendo role y kyc_verified
$sql = "INSERT INTO users (username, email, password_plain, password_hash, role, kyc_verified, kyc_level, referral_code, referred_by, referral_bonus, full_name, phone)
        VALUES ('$username', '$email', '$password', '$password_hash', '$role', '$kyc_verified', '$kyc_level', '$referral_code', '$referred_by', '$referral_bonus', '$full_name', '$phone')";

if (!$conn->query($sql)) {
    if (DEBUG_MODE) {
        jsonResponse([
            'error' => 'Error al registrar usuario',
            'sql_error' => $conn->error,
            'query' => $sql
        ], 500);
    }
    jsonResponse(['error' => 'Error al registrar usuario. Es posible que el username o email ya existan.'], 400);
}

$userId = $conn->insert_id;

// Crear wallet BTC por defecto
$walletAddress = generateWalletAddress(substr($username, 0, 5), $userId, 'BTC');
$privateKey = 'pk_' . $username . '_btc_' . md5($username . time());

$conn->query("INSERT INTO wallets (user_id, wallet_address, currency, balance, private_key)
              VALUES ($userId, '$walletAddress', 'BTC', 0.01000000, '$privateKey')");

// Crear wallet ETH
$ethAddress = generateWalletAddress(substr($username, 0, 5), $userId, 'ETH');
$ethPrivateKey = 'pk_' . $username . '_eth_' . md5($username . 'eth' . time());

$conn->query("INSERT INTO wallets (user_id, wallet_address, currency, balance, private_key)
              VALUES ($userId, '$ethAddress', 'ETH', 0.50000000, '$ethPrivateKey')");

// Bonus por referido
if (!empty($referred_by)) {
    $refQuery = $conn->query("SELECT id FROM users WHERE referral_code = '$referred_by'");
    if ($refQuery && $refQuery->num_rows > 0) {
        $referrer = $refQuery->fetch_assoc();
        // VULN (API6): Sin límite de bonus por referidos
        $conn->query("UPDATE users SET referral_bonus = referral_bonus + 0.001 WHERE id = " . $referrer['id']);
    }
}

$token = generateJWT([
    'user_id' => $userId,
    'username' => $username,
    'email' => $email,
    'role' => $role,
    'kyc_verified' => (bool)$kyc_verified
]);

logAudit($conn, $userId, 'register', '/api/v1/auth/register');

// VULN (API3): Respuesta expone campos sensibles
jsonResponse([
    'success' => true,
    'message' => 'Usuario registrado exitosamente',
    'token' => $token,
    'user' => [
        'id' => $userId,
        'username' => $username,
        'email' => $email,
        'role' => $role,
        'kyc_verified' => (bool)$kyc_verified,
        'kyc_level' => (int)$kyc_level,
        'referral_code' => $referral_code,
        'referral_bonus' => (float)$referral_bonus,
        'wallet_btc' => $walletAddress,
        'wallet_eth' => $ethAddress,
        'private_key_btc' => $privateKey,
        'private_key_eth' => $ethPrivateKey
    ]
], 201);
