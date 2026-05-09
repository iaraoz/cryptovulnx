<?php
// CryptoVulnX - Webhook Management endpoint
// VULN (API7): SSRF via webhook URL, (API1): BOLA

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();

$user = requireAuth();
$conn = getDBConnection();
$userId = $user['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Listar webhooks
    $targetUser = $_GET['user_id'] ?? $userId;

    // VULN (API1): BOLA - puede ver webhooks de otros usuarios
    $result = $conn->query("SELECT * FROM webhooks WHERE user_id = $targetUser");

    $webhooks = [];
    while ($row = $result->fetch_assoc()) {
        $webhooks[] = [
            'id' => (int)$row['id'],
            'url' => $row['url'],
            'event_type' => $row['event_type'],
            'secret' => $row['secret'],         // VULN (API3): Expone secret
            'is_active' => (bool)$row['is_active'],
            'last_triggered' => $row['last_triggered']
        ];
    }

    jsonResponse(['success' => true, 'webhooks' => $webhooks]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getRequestBody();
    $action = $data['action'] ?? 'create';

    if ($action === 'create') {
        $url = $data['url'] ?? '';
        $event_type = $data['event_type'] ?? 'transaction';
        $secret = $data['secret'] ?? bin2hex(random_bytes(16));

        if (empty($url)) {
            jsonResponse(['error' => 'url es requerido'], 400);
        }

        // VULN (API7): No valida la URL - permite SSRF
        $conn->query("INSERT INTO webhooks (user_id, url, event_type, secret)
                      VALUES ($userId, '$url', '$event_type', '$secret')");

        jsonResponse([
            'success' => true,
            'message' => 'Webhook creado',
            'webhook' => [
                'id' => $conn->insert_id,
                'url' => $url,
                'event_type' => $event_type,
                'secret' => $secret
            ]
        ], 201);

    } elseif ($action === 'test') {
        // Probar webhook - VULN (API7): SSRF
        $webhook_id = $data['webhook_id'] ?? null;

        if (!$webhook_id) {
            jsonResponse(['error' => 'webhook_id es requerido'], 400);
        }

        // VULN (API1): BOLA - no verifica ownership
        $webhook = $conn->query("SELECT * FROM webhooks WHERE id = $webhook_id")->fetch_assoc();

        if (!$webhook) {
            jsonResponse(['error' => 'Webhook no encontrado'], 404);
        }

        // VULN (API7): SSRF - hace request a la URL del webhook sin validación
        $testPayload = json_encode([
            'event' => 'test',
            'timestamp' => time(),
            'message' => 'Test webhook from CryptoVulnX'
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $webhook['url'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $testPayload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWREDIRECTS => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Webhook-Secret: ' . $webhook['secret']
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $error = curl_error($ch);
        curl_close($ch);

        $conn->query("UPDATE webhooks SET last_triggered = NOW() WHERE id = $webhook_id");

        // VULN (API8): Expone respuesta completa del servidor interno
        jsonResponse([
            'success' => true,
            'message' => 'Webhook test enviado',
            'result' => [
                'http_code' => $httpCode,
                'response_body' => $response,       // VULN: Expone respuesta del servidor interno
                'effective_url' => $effectiveUrl,
                'error' => $error
            ]
        ]);
    }

} else {
    jsonResponse(['error' => 'Método no permitido'], 405);
}
