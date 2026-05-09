<?php
// CryptoVulnX - Configuración JWT
// VULN (API2): Secret débil y hardcodeado, sin expiración real

define('JWT_SECRET', getenv('JWT_SECRET') ?: 'crypto123');
define('JWT_ALGORITHM', 'HS256');

function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

function generateJWT($payload) {
    $header = json_encode(['typ' => 'JWT', 'alg' => JWT_ALGORITHM]);

    // VULN (API2): No se establece expiración por defecto
    $payload['iat'] = time();
    // No exp claim - tokens never expire

    $headerEncoded = base64UrlEncode($header);
    $payloadEncoded = base64UrlEncode(json_encode($payload));

    $signature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", JWT_SECRET, true);
    $signatureEncoded = base64UrlEncode($signature);

    return "$headerEncoded.$payloadEncoded.$signatureEncoded";
}

function verifyJWT($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;

    list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;

    // VULN (API2): Acepta algoritmo 'none'
    $header = json_decode(base64UrlDecode($headerEncoded), true);
    if ($header['alg'] === 'none') {
        return json_decode(base64UrlDecode($payloadEncoded), true);
    }

    $expectedSignature = base64UrlEncode(
        hash_hmac('sha256', "$headerEncoded.$payloadEncoded", JWT_SECRET, true)
    );

    if ($signatureEncoded !== $expectedSignature) return false;

    $payload = json_decode(base64UrlDecode($payloadEncoded), true);

    // VULN (API2): No valida expiración aunque exista el claim
    return $payload;
}
