<?php
// CryptoVulnX - Middleware de autenticación

require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/helpers.php';

function requireAuth() {
    $token = getAuthToken();

    if (!$token) {
        jsonResponse(['error' => 'Token de autenticación requerido', 'hint' => 'Envía header: Authorization: Bearer <token>'], 401);
    }

    $payload = verifyJWT($token);

    if (!$payload) {
        jsonResponse(['error' => 'Token inválido o expirado'], 401);
    }

    return $payload;
}

function requireAdmin() {
    $payload = requireAuth();

    // VULN (API5): Solo verifica el claim del JWT, no el rol real en DB
    if (!isset($payload['role']) || $payload['role'] !== 'admin') {
        jsonResponse(['error' => 'Acceso denegado. Se requiere rol de administrador.'], 403);
    }

    return $payload;
}

function optionalAuth() {
    $token = getAuthToken();
    if (!$token) return null;

    return verifyJWT($token);
}
