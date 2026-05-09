<?php
// CryptoVulnX - Crypto Prices endpoint
// Endpoint público para consultar precios

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/magic.php';

setCORSHeaders();
emitMagicSignalsHeader();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$conn = getDBConnection();

$symbol = $_GET['symbol'] ?? '';

if (!empty($symbol)) {
    // VULN: SQL Injection en parámetro symbol
    $sql = "SELECT * FROM crypto_prices WHERE symbol = '$symbol'";
    $result = $conn->query($sql);

    if (!$result) {
        if (DEBUG_MODE) {
            jsonResponse(['error' => 'Error en consulta', 'sql_error' => $conn->error, 'query' => $sql], 500);
        }
        jsonResponse(['error' => 'Error interno'], 500);
    }

    if ($result->num_rows === 0) {
        jsonResponse(['error' => "Criptomoneda '$symbol' no encontrada"], 404);
    }

    $price = $result->fetch_assoc();
    jsonResponse([
        'success' => true,
        'price' => [
            'symbol' => $price['symbol'],
            'price_usd' => (float)$price['price_usd'],
            'change_24h' => (float)$price['change_24h'],
            'volume_24h' => (float)$price['volume_24h'],
            'updated_at' => $price['updated_at']
        ]
    ]);
} else {
    $result = $conn->query("SELECT * FROM crypto_prices ORDER BY price_usd DESC");
    $prices = [];
    while ($row = $result->fetch_assoc()) {
        if (wantsAllFields()) {
            // VULN (API3): ?fields=* dump completo de todas las columnas
            $prices[] = $row;
        } else {
            $prices[] = [
                'symbol' => $row['symbol'],
                'price_usd' => (float)$row['price_usd'],
                'change_24h' => (float)$row['change_24h'],
                'volume_24h' => (float)$row['volume_24h'],
                'updated_at' => $row['updated_at']
            ];
        }
    }
    $resp = ['success' => true, 'prices' => $prices];
    if (wantsAllFields()) {
        $resp['flag'] = 'FLAG-PARAM-03: query_param_fields_dump_de_columnas';
    }
    jsonResponse($resp);
}
