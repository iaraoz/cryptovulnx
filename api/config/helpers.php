<?php
// CryptoVulnX - Helper functions

// VULN (API8): CORS permisivo
function setCORSHeaders() {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Api-Key, X-Debug-Mode");
    header("Access-Control-Expose-Headers: X-Debug-Info, X-Server-Version");
    header("Content-Type: application/json; charset=UTF-8");

    // VULN (API8): Headers de debug expuestos
    header("X-Server-Version: CryptoVulnX/1.0.0-beta");
    header("X-Powered-By: PHP/" . phpversion());

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function getRequestBody() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?: [];
}

function getAuthToken() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return $matches[1];
    }
    return null;
}

function generateTxHash() {
    return 'TX-' . bin2hex(random_bytes(8)) . '-' . time();
}

function generateWalletAddress($prefix, $userId, $currency) {
    // VULN: Dirección de wallet predecible
    return "0x" . strtoupper($prefix) . str_pad($userId, 4, '0', STR_PAD_LEFT) . "-" . $currency . "-VULNX";
}

function getClientIP() {
    // VULN (API8): Confía en headers del cliente para IP
    return $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['HTTP_X_REAL_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '127.0.0.1';
}

function logAudit($conn, $userId, $action, $endpoint) {
    $ip = getClientIP();
    $requestData = json_encode(getRequestBody());
    $responseCode = http_response_code();

    // VULN: SQL Injection en audit log
    $sql = "INSERT INTO audit_log (user_id, action, endpoint, ip_address, request_data, response_code)
            VALUES ('$userId', '$action', '$endpoint', '$ip', '$requestData', '$responseCode')";
    $conn->query($sql);
}
