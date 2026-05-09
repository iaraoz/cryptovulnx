<?php
// CryptoVulnX - Internal Rates API
// VULN (API9): Endpoint interno expuesto públicamente sin autenticación
// VULN (API10): Unsafe consumption - este endpoint simula una API externa de tasas

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

setCORSHeaders();

// VULN (API9): Sin autenticación en endpoint interno
// VULN (API8): Header expone que es un servicio interno
header("X-Internal-Service: rates-engine-v1");
header("X-Service-Network: internal");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $conn = getDBConnection();

    $from = $_GET['from'] ?? '';
    $to = $_GET['to'] ?? '';

    if (!empty($from) && !empty($to)) {
        // VULN: SQL Injection
        $fromResult = $conn->query("SELECT price_usd FROM crypto_prices WHERE symbol = '$from'");
        $toResult = $conn->query("SELECT price_usd FROM crypto_prices WHERE symbol = '$to'");

        if (!$fromResult || !$toResult || $fromResult->num_rows === 0 || $toResult->num_rows === 0) {
            jsonResponse(['error' => 'Par no encontrado'], 404);
        }

        $fromPrice = (float)$fromResult->fetch_assoc()['price_usd'];
        $toPrice = (float)$toResult->fetch_assoc()['price_usd'];

        jsonResponse([
            'success' => true,
            'pair' => "$from/$to",
            'rate' => $fromPrice / $toPrice,
            'from_price_usd' => $fromPrice,
            'to_price_usd' => $toPrice,
            'source' => 'internal-rates-engine',
            'timestamp' => time(),
            // VULN (API9): Expone información de infraestructura
            'service_info' => [
                'hostname' => gethostname(),
                'db_host' => DB_HOST,
                'environment' => APP_ENV
            ]
        ]);
    } else {
        // Devolver todas las tasas
        $result = $conn->query("SELECT * FROM crypto_prices ORDER BY symbol ASC");
        $rates = [];
        while ($row = $result->fetch_assoc()) {
            $rates[$row['symbol']] = [
                'price_usd' => (float)$row['price_usd'],
                'change_24h' => (float)$row['change_24h'],
                'volume_24h' => (float)$row['volume_24h']
            ];
        }
        jsonResponse([
            'success' => true,
            'rates' => $rates,
            'source' => 'internal-rates-engine',
            'service_info' => [
                'hostname' => gethostname(),
                'db_host' => DB_HOST,
                'environment' => APP_ENV
            ]
        ]);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // VULN (API10): Permite actualizar precios sin autenticación
    // Simula consumo inseguro de API externa
    $data = getRequestBody();
    $conn = getDBConnection();

    if (isset($data['rates']) && is_array($data['rates'])) {
        foreach ($data['rates'] as $symbol => $price) {
            // VULN (API10): No valida datos de "API externa"
            // VULN: SQL Injection
            $conn->query("UPDATE crypto_prices SET price_usd = '$price', updated_at = NOW() WHERE symbol = '$symbol'");
        }
        jsonResponse([
            'success' => true,
            'message' => 'Rates actualizados',
            'updated_symbols' => array_keys($data['rates'])
        ]);
    }

    jsonResponse(['error' => 'Formato inválido. Envía {"rates": {"BTC": 70000}}'], 400);

} else {
    jsonResponse(['error' => 'Método no permitido'], 405);
}
