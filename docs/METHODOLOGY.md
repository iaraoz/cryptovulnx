# CryptoVulnX - Metodologia Maestra (Documento del Instructor)

> **NOTA AL ALUMNO**: Este documento es la **referencia maestra**. Si lo abriste antes de hacer las fases del lab, te estas spoileando. La forma correcta es:
> 1. Levantar el lab (`docker compose up -d`)
> 2. Ir al `/playbook.php` y seguir la guia gamificada
> 3. Resolver cada fase con el LAB correspondiente (LAB11..LAB14 + LAB01..LAB10)
> 4. Volver a este doc al final, como cierre y consolidacion

---

## Indice

1. [Vision general](#vision-general)
2. [Las 5 fases del pentest de API](#las-5-fases-del-pentest-de-api)
3. [Fase 1 - RECON](#fase-1---recon)
4. [Fase 2 - API INVENTORY](#fase-2---api-inventory)
5. [Fase 3 - ENDPOINT FUZZING](#fase-3---endpoint-fuzzing)
6. [Fase 4 - PARAMETER FUZZING](#fase-4---parameter-fuzzing)
7. [Fase 5 - EXPLOITATION](#fase-5---exploitation)
8. [Encadenamiento](#encadenamiento)
9. [Mapa de flags](#mapa-de-flags)
10. [Nota del instructor](#nota-del-instructor)

---

## Vision general

El pentest de un API moderno **no es** ejecutar `sqlmap` contra `/api/v1/login`. Es un **proceso ordenado** de descubrimiento y explotacion donde cada fase **alimenta** la siguiente:

```
   ┌──────────┐    ┌───────────┐    ┌─────────────┐    ┌──────────────┐    ┌──────────────┐
   │ FASE 1   │───▶│ FASE 2    │───▶│ FASE 3      │───▶│ FASE 4       │───▶│ FASE 5       │
   │ RECON    │    │ INVENTORY │    │ ENDPOINT    │    │ PARAMETER    │    │ EXPLOITATION │
   └──────────┘    └───────────┘    └─────────────┘    └──────────────┘    └──────────────┘
   archivos        versiones        rutas dentro       params/headers      OWASP API
   estaticos       (v1,v2,internal) de cada version    /cookies ocultos    Top 10
```

El error frecuente del alumno principiante es **saltar directo a la fase 5** ("vamos a probar SQLi en login"). El pentest profesional gasta **70% del tiempo en fases 1-4** y solo el 30% en explotacion. Razon: si la enumeracion fue completa, la explotacion es trivial.

---

## Las 5 fases del pentest de API

| # | Nombre | Pregunta que responde | Output |
|---|---|---|---|
| 1 | RECON | Que archivos publicos hay? | Lista de archivos sensibles + creds + arquitectura interna |
| 2 | API INVENTORY | Que APIs existen? | Lista de versiones (v1, v2, v3, internal, test, dev, staging) y sus specs |
| 3 | ENDPOINT FUZZING | Que rutas tiene cada API? | Lista de endpoints individuales: `/admin/exec`, `/internal/health`, etc. |
| 4 | PARAMETER FUZZING | Que params/headers/cookies aceptan? | Lista de magic flags: `?debug`, `X-Admin-Token`, `Cookie internal_user=admin`, `body.role` |
| 5 | EXPLOITATION | Como se rompe el negocio? | Chain de OWASP API Top 10 que cumple objetivos del atacante |

---

## Fase 1 - RECON

### Objetivo
Descubrir TODO lo que un GET sin auth puede traer del servidor.

### Vector tipico
- `/robots.txt`, `/sitemap.xml`, `/.well-known/*`
- `.git/`, `.svn/`, `.hg/` directorios completos
- `.env`, `.env.bak`, `.env.old`, `.env.local`, `.env.production`
- `composer.json`, `composer.lock`, `package.json`, `yarn.lock`, `requirements.txt`
- `phpinfo.php`, `info.php`, `test.php`, `debug.php`
- `notes.txt`, `TODO.md`, `README.local`
- `backup.sql`, `backup.zip`, `backup/`, `dumps/`
- `adminer.php`, `phpmyadmin/`, `pma/`, `dbadmin/`
- `.DS_Store`, `.idea/`, `.vscode/`

### Wordlists recomendados
- `SecLists/Discovery/Web-Content/raft-medium-files.txt`
- `SecLists/Discovery/Web-Content/quickhits.txt`
- `SecLists/Discovery/Web-Content/Common-PHP-Filenames.txt`
- `assetnote/wordlists/data/automated/exposures.txt`

### Comando base
```bash
ffuf -w raft-medium-files.txt \
     -u $TARGET/FUZZ \
     -mc 200 -fs 0 -t 50 \
     -e .bak,.old,.swp,.sql,.json,.lock,~ \
     -of csv -o recon.csv
```

### Hallazgos en CryptoVulnX
Documentados en `labs/LAB11-RECON.md`. Mapeo a OWASP API: API8 (Misconfiguration).

### Que NO hacer
- Saltar a `?id=1' OR 1=1`. La fase 5 viene despues.
- Ignorar archivos `.bak`, `.old`. Ahi viven los secretos rotados.
- Ignorar `.git/` "porque es dificil". Con `git-dumper` recuperas el codigo.

---

## Fase 2 - API INVENTORY

### Objetivo
Construir el inventario completo de **versiones y namespaces** del API.

### Vector tipico
- Cambios de version: `/api/v1/`, `/api/v2/`, `/api/v3/`, `/api/v4/`, `/api/beta/`
- Ambientes paralelos: `/api/staging/`, `/api/dev/`, `/api/test/`, `/api/qa/`, `/api/sandbox/`
- Microservicios "internos" expuestos: `/api/internal/`, `/api/private/`, `/api/admin/`
- Specs OpenAPI/Swagger: `/api/swagger.json`, `/swagger-ui/`, `/openapi.yaml`, `/api-docs`
- Colecciones Postman: `/postman_collection.json`, exportadas en S3 publicos
- `Server`, `X-Powered-By`, `X-Api-Version` en headers de respuesta
- Erros que filtran rutas internas (`X-Original-URL`, redirects)

### Wordlists recomendados
- `SecLists/Discovery/Web-Content/api/api-endpoints.txt`
- `SecLists/Discovery/Web-Content/api/objects.txt`
- `assetnote/wordlists/data/automated/swagger.txt`
- `wordlists/cryptovulnx-api-versions.txt` (incluido)

### Comando base
```bash
# Brute de versiones
ffuf -w cryptovulnx-api-versions.txt \
     -u $TARGET/api/FUZZ/auth/login.php \
     -mc 200,401,405 -t 30

# Buscar swagger
ffuf -w swagger-paths.txt \
     -u $TARGET/FUZZ \
     -mc 200 -fs 0
```

### Hallazgos en CryptoVulnX
Documentados en `labs/LAB12-API-INVENTORY-FUZZING.md`. Mapeo a OWASP: API9 (Inventory).

### Tip clave
- Comparar respuestas de **el mismo** endpoint en **diferentes** versiones. La "v2 beta" suele tener menos protecciones que v1, y v3 todavia menos.
- Los specs internos suelen tener campos custom: `x-internal-servers`, `x-undocumented-endpoints`, `x-magic-headers`, `x-mass-assignable`. Buscarlos.

---

## Fase 3 - ENDPOINT FUZZING

### Objetivo
Dentro de cada version conocida, descubrir **endpoints individuales** que no estan en la spec publica.

### Vector tipico
- `/api/v1/admin/*` - paneles admin
- `/api/v1/internal/*` - microservicios internos
- `/api/v1/users/*` - export, dump, csv
- `/api/v1/.git-rev`, `/api/v1/health`, `/api/v1/version` - metadata
- HTTP verb fuzzing: probar todos los metodos en cada endpoint
- Extension fuzzing: `.php`, `.json`, `.csv`, `.xml`, `.yaml`

### Wordlists recomendados
- `SecLists/Discovery/Web-Content/api/api-endpoints.txt`
- `SecLists/Discovery/Web-Content/api/objects.txt`
- `SecLists/Discovery/Web-Content/api/actions.txt`
- `wordlists/cryptovulnx-endpoints.txt` (incluido)

### Comando base
```bash
# Brute de endpoints bajo /api/v1/admin/
feroxbuster -u $TARGET/api/v1/admin/ \
            -w api-endpoints.txt \
            -x php,json,csv \
            -t 30 -d 2

# Verb fuzzing
for V in GET POST PUT DELETE PATCH OPTIONS; do
    curl -sX $V -o /dev/null -w "$V %{http_code}\n" $TARGET/api/v1/admin/exec
done
```

### Hallazgos en CryptoVulnX
Documentados en `labs/LAB13-ENDPOINT-FUZZING.md`.

### Tip clave
- En esta fase aparecen los endpoints "RCE friendly" (`exec.php`, `eval.php`), los "LFI friendly" (`logs.php?file=`), y los "dump friendly" (`backup.php`, `export.csv`).
- Combinarlos con magic flags de Fase 4 para saltearse auth.

---

## Fase 4 - PARAMETER FUZZING

### Objetivo
Descubrir **query params, body params, headers y cookies** no documentados que cambian el comportamiento del endpoint.

### Tipos
| Tipo | Tools | Wordlist |
|---|---|---|
| Query (GET) | `arjun --get` | `burp-parameter-names.txt`, `cryptovulnx-magic-params.txt` |
| Body JSON (POST) | `arjun -m POST -d '{}'` | idem |
| Headers | Burp `Param Miner`, `ffuf -H "FUZZ: 1"` | `burp-parameter-names.txt` |
| Cookies | `ffuf -b "FUZZ=1"` | idem |

### Categorias de magic params en CryptoVulnX

#### 4.1 Query params
- `?debug=1` → modo debug
- `?include=*` o `?include=private_key,owner` → over-exposure
- `?fields=*` → dump de columnas
- `?as_user_id=N` → impersonacion
- `?bypass_rate_limit=1` → skip rate limit
- `?role=admin` (en filtros) → SQLi adicional

#### 4.2 Headers
- `X-Debug-Token: 1` → debug mode
- `X-Bypass-RateLimit: 1` → skip rate limit
- `X-Forwarded-For: 127.0.0.1` → bypass de allowlist + internal mode
- `X-Real-IP: 10.0.0.5` → idem
- `X-Original-URL: /api/v1/admin/users` → URL rewriting
- `X-Api-Version: internal` → routing oculto
- `X-Service-Token: internal_svc_token_2024_q1` → internal mode
- `X-Admin-Token: admin_dev_bypass_v1` → bypass requireAdmin

#### 4.3 Body params (POST JSON)
- `role`, `kyc_verified`, `kyc_level`, `referral_bonus`, `balance` (mass assignment en register)
- `fee_override`, `skip_validation`, `as_user_id` (transfer)
- `rate_override`, `bypass_kyc`, `fee_override` (swap)

#### 4.4 Cookies
- `debug_mode=1`
- `internal_user=admin`
- `feature_flags=admin_panel,debug`

### Comando base
```bash
# Query params
arjun -u $TARGET/api/v1/wallets/balance.php?wallet_id=1 \
      -H "Authorization: Bearer $TOK" \
      -w cryptovulnx-magic-params.txt

# Body params
arjun -u $TARGET/api/v1/wallets/transfer.php \
      -m POST -d '{"from_wallet_id":1,"to_wallet_address":"x","amount":1}' \
      --headers "Authorization: Bearer $TOK,Content-Type: application/json"

# Headers (con respuesta reflejando)
ffuf -w burp-parameter-names.txt \
     -H "FUZZ: 1" \
     -u $TARGET/api/v1/wallets/balance.php?wallet_id=1 \
     -H "Authorization: Bearer $TOK" \
     -mr "X-Magic-Signals|_debug" -t 30
```

### Tip clave (importante para el instructor)
El header de respuesta `X-Magic-Signals: N` indica cuantos magic flags interpreto el server. Es una **pista pedagogica** - en pentest real no existe, pero aqui sirve para que el alumno se de cuenta cuando un flag funciono.

### Hallazgos en CryptoVulnX
Documentados en `labs/LAB14-PARAMETER-FUZZING.md`.

---

## Fase 5 - EXPLOITATION

### Objetivo
Combinar todo lo descubierto en fases 1-4 para ejecutar **chains de OWASP API Top 10**.

### Mapeo OWASP -> LAB existente
| OWASP API:2023 | LAB | Activado por hallazgos de... |
|---|---|---|
| API1 BOLA | LAB01 | Fase 3 (`/wallets/balance?wallet_id=N`), Fase 4 (`?as_user_id=N`) |
| API2 Auth | LAB02 | Fase 1 (JWT_SECRET), Fase 2 (alg=none en v3) |
| API3 BOPLA / Mass Assign | LAB03 | Fase 4 (body params), Fase 1 (openapi spec con campos mass-assignable) |
| API4 Resource | LAB04 | Fase 4 (?bypass_rate_limit, X-Forwarded-For) |
| API5 BFLA | LAB05 | Fase 4 (X-Admin-Token, Cookie internal_user=admin) |
| API6 Business | LAB06 | Fase 4 (rate_override, bypass_kyc, fee_override) |
| API7 SSRF | LAB07 | Fase 4 (X-Forwarded-Host) |
| API8 Misconfig | LAB08 | Fase 1 (todos los archivos), test.php |
| API9 Inventory | LAB09 | Fase 2 (todas las versiones) |
| API10 Unsafe | LAB10 | Fase 3 (/api/internal/rates POST) |

### Chain ejemplo (objetivo: convertirse en admin con $999M)
1. **Fase 1**: leer `notes.txt` y `backup.sql` -> `JWT_SECRET=crypto123`, `admin/admin123`
2. **Fase 2**: leer `/api/v1/openapi.json` -> lista de endpoints internos
3. **Fase 3**: identificar `/api/v1/admin/exec.php` (RCE con X-Admin-Token)
4. **Fase 4**: probar `X-Admin-Token: admin_dev_bypass_v1` -> 200
5. **Fase 5 chain**:
   - Login con `admin/admin123` (LAB02)
   - O directamente: forjar JWT con `crypto123` y role=admin
   - Manipular precio BTC con `/api/internal/rates` POST a 1.0 (LAB10)
   - Swap USDT->BTC, restaurar precio, swap BTC->USDT (LAB06)
   - O: register con `role=admin` mass assignment + balance=999000000 (LAB03)

---

## Encadenamiento

| De Fase | A Fase | Que se transmite |
|---|---|---|
| 1 → 2 | RECON → INVENTORY | Pista en `security.txt`, lista de endpoints en `notes.txt`, `TODO.md` |
| 1 → 4 | RECON → PARAMS | Lista de headers/cookies/params magicos en `notes.txt` |
| 1 → 5 | RECON → EXPLOIT | Credenciales (`admin/admin123`), `JWT_SECRET=crypto123`, hashes para crackear |
| 2 → 3 | INVENTORY → ENDPOINTS | `x-undocumented-endpoints` del openapi interno |
| 2 → 4 | INVENTORY → PARAMS | `x-magic-headers`, `x-magic-cookies`, `x-mass-assignable` del openapi |
| 3 → 4 | ENDPOINTS → PARAMS | header `X-Magic-Signals: N` confirma que params funcionan |
| 4 → 5 | PARAMS → EXPLOIT | bypass de auth (X-Admin-Token), bypass de rate limit, mass assignment activo |

---

## Mapa de flags

```
FLAG-RECON-01     .env.bak (creds rotadas)
FLAG-RECON-02     notes.txt (apunta a swagger)
FLAG-RECON-03     TODO.md  (apunta a logs.php)
FLAG-RECON-04     .git/COMMIT_EDITMSG
FLAG-RECON-05     backup.sql (admin/admin123)
FLAG-RECON-06     backup/db_*.sql (arquitectura)
FLAG-RECON-07     adminer.php (creds DB en HTML comment)
FLAG-RECON-08     .well-known/security.txt

FLAG-INVENT-01    swagger.json/x-internal-servers
FLAG-INVENT-02    openapi.json (todos endpoints + magic headers)
FLAG-INVENT-03    postman_collection.json (tokens hardcoded)
FLAG-INVENT-04    /api/v3/auth/login.php alg=none
FLAG-INVENT-05    /api/v3/admin/exec.php RCE
FLAG-INVENT-06    /api/test/users.php dump
FLAG-INVENT-07    /api/dev/dump.php query arbitraria
FLAG-INVENT-08    /api/staging mirror sin rate limit

FLAG-ENDPOINT-01  /api/v1/admin/backup.php
FLAG-ENDPOINT-02  /api/v1/admin/logs.php LFI
FLAG-ENDPOINT-03  /api/v1/admin/exec.php RCE
FLAG-ENDPOINT-04  /api/v1/internal/health
FLAG-ENDPOINT-05  /api/v1/wallets/private-keys.php
FLAG-ENDPOINT-06  /api/v1/users/export.csv
FLAG-ENDPOINT-07  /api/v1/.git-rev

FLAG-PARAM-01     ?debug=1 expone SQL
FLAG-PARAM-02     rate_limit bypass
FLAG-PARAM-03     ?fields=* dump
FLAG-PARAM-04     body params en transfer
FLAG-PARAM-05     body params en swap

FLAG-PLAYBOOK-XX  por desbloquear cada fase del playbook
FLAG-PLAYBOOK-FINAL  por completar metodologia (5 flags entregados)
```

---

## Nota del instructor

### Para correr el lab en clase
1. Subir el repo a un GitLab/GitHub privado del curso.
2. Cada alumno: `docker compose up -d` (puerto 8080).
3. Asignar las fases secuencialmente (1 dia por fase, o 1 fase por sesion).
4. Pedir entrega de los flags por fase como evidencia de progreso.
5. Para cierre de modulo: pedir `X-Flags-Collected: <csv>` contra `/playbook?phase=final` -> screenshot del response como entregable.

### Tiempos estimados
| Fase | Tiempo alumno (sin pistas) | Tiempo alumno (con LAB##.md) |
|---|---|---|
| 1 RECON | 1.5 hs | 30 min |
| 2 INVENTORY | 2 hs | 45 min |
| 3 ENDPOINTS | 2 hs | 45 min |
| 4 PARAMETERS | 3 hs | 1 hs |
| 5 EXPLOITATION | 4 hs | 1.5 hs |
| **Total** | **~12 hs** | **~4.5 hs** |

### Que enfasizar
- Que la **velocidad** del pentest profesional viene de la **metodologia ordenada**, no de tools magicas.
- Que el **70% de los hallazgos criticos** vienen de fases 1-4 (recon + enumeration), no de la fase 5.
- Que el `X-Magic-Signals` es una concesion pedagogica - en prod los servers NUNCA filtran metadata de su comportamiento.
- Que la diferencia entre v1, v2, v3 enseña que las **versiones beta heredan datos** pero **no heredan controles**.
