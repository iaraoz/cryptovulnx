# Wordlists - CryptoVulnX

## Externos (descargar antes de empezar)

### SecLists (obligatorio)
```bash
# Descarga completa (~600 MB)
git clone --depth 1 https://github.com/danielmiessler/SecLists.git

# Solo lo esencial para este lab:
SecLists/Discovery/Web-Content/raft-medium-files.txt
SecLists/Discovery/Web-Content/raft-medium-directories.txt
SecLists/Discovery/Web-Content/quickhits.txt
SecLists/Discovery/Web-Content/Common-PHP-Filenames.txt
SecLists/Discovery/Web-Content/api/api-endpoints.txt
SecLists/Discovery/Web-Content/api/objects.txt
SecLists/Discovery/Web-Content/api/actions.txt
SecLists/Discovery/Web-Content/burp-parameter-names.txt
SecLists/Passwords/Common-Credentials/10k-most-common.txt
```

### Assetnote
```bash
# https://wordlists.assetnote.io/
wget https://wordlists-cdn.assetnote.io/data/automated/exposures.txt
wget https://wordlists-cdn.assetnote.io/data/automated/swagger.txt
wget https://wordlists-cdn.assetnote.io/data/automated/parameters.txt
wget https://wordlists-cdn.assetnote.io/data/automated/php.txt
```

---

## Custom (incluidos en el repo)

| Archivo | Para que fase | Tamaño | Hits del lab | Uso |
|---|---|---|---|---|
| `wordlists/cryptovulnx-api-versions.txt` | Fase 2 | ~290 entries | 7 (~2.5%) | Brute de versiones de API |
| `wordlists/cryptovulnx-endpoints.txt` | Fase 3 | ~690 entries | ~25 (~3.5%) | Brute de paths bajo cada version |
| `wordlists/cryptovulnx-magic-params.txt` | Fase 4 | ~720 entries | ~30 (~4%) | Brute de magic params/headers/cookies |

Los wordlists custom son **dirigidos al dominio** del lab (crypto, wallet, KYC, swap) con un **ratio de hits realista (~3-4%)** para que `ffuf` muestre el comportamiento normal de un fuzzing profesional: la mayoria de respuestas son 404 (descartadas), y los hits aparecen en medio del ruido.

**Por que no son mas cortas**: una wordlist de 50 lineas completa en milisegundos y no enseña nada. Una de 700 entries demora 5-15 segundos en local con concurrencia 30, suficiente para ver:
- La barra de progreso de `ffuf`
- El filtrado por status code (`-mc 200,401,403`)
- El descarte de respuestas con tamaño identico (`-fs 0`, `-fc 404`)
- Los hits resaltados en verde

**Por que no son mucho mas largas**: 5000 entries demoraria minutos contra un lab local, y la pedagogia se pierde. En pentest real se usan wordlists de 50k-500k entries y el fuzzing demora horas, pero en este lab buscamos enseñar la mecanica.

En pentest real, conviene siempre construir un wordlist custom basado en:
1. El idioma de la app (es/en/pt)
2. El dominio (banking, ecommerce, gaming, crypto)
3. Los nombres de funciones/clases vistos en el codigo recuperado de Fase 1

---

## Como se ve `ffuf` corriendo cada wordlist

### Fase 2 - Brute de versiones de API

```bash
ffuf -w wordlists/cryptovulnx-api-versions.txt \
     -u http://localhost:8080/api/FUZZ/auth/login.php \
     -mc 200,401,405 \
     -t 30
```

Output esperado (~290 lineas, ~5 segundos):

```
        /'___\  /'___\           /'___\
       /\ \__/ /\ \__/  __  __  /\ \__/
       \ \ ,__\\ \ ,__\/\ \/\ \ \ \ ,__\
        \ \ \_/ \ \ \_/\ \ \_\ \ \ \ \_/
         \ \_\   \ \_\  \ \____/  \ \_\
          \/_/    \/_/   \/___/    \/_/

       v2.1.0
________________________________________________

 :: Method           : GET
 :: URL              : http://localhost:8080/api/FUZZ/auth/login.php
 :: Wordlist         : FUZZ: cryptovulnx-api-versions.txt
 :: Follow redirects : false
 :: Calibration      : false
 :: Timeout          : 10
 :: Threads          : 30
 :: Matcher          : Response status: 200,401,405

[Status: 405, Size: 245, Words: 3, Lines: 1]      | URL | http://...api/v1/auth/login.php
[Status: 405, Size: 287, Words: 3, Lines: 1]      | URL | http://...api/v2/auth/login.php
[Status: 405, Size: 412, Words: 5, Lines: 1]      | URL | http://...api/v3/auth/login.php
[Status: 405, Size: 298, Words: 3, Lines: 1]      | URL | http://...api/staging/auth/login.php

:: Progress: [288/288] :: Job [1/1] :: 60 req/sec :: Duration: [0:00:05] :: Errors: 0 ::
```

4 hits visibles (`v1`, `v2`, `v3`, `staging`). Los demas estan en `/api/test/`, `/api/dev/`, `/api/internal/` pero no tienen `auth/login.php` - el alumno los encuentra fuzzeando otros paths.

### Fase 3 - Brute de endpoints

```bash
ffuf -w wordlists/cryptovulnx-endpoints.txt \
     -u http://localhost:8080/api/v1/admin/FUZZ.php \
     -mc 200,401,403,500 \
     -fs 0 \
     -t 30
```

Output esperado (~690 lineas, ~10 segundos):

```
[Status: 200, Size: 1234, Words: 50, Lines: 30]  | URL | .../api/v1/admin/users.php
[Status: 200, Size: 5421, Words: 180, Lines: 95] | URL | .../api/v1/admin/debug.php
[Status: 401, Size: 87, Words: 4, Lines: 1]      | URL | .../api/v1/admin/backup.php
[Status: 401, Size: 96, Words: 5, Lines: 1]      | URL | .../api/v1/admin/exec.php
[Status: 401, Size: 92, Words: 5, Lines: 1]      | URL | .../api/v1/admin/logs.php
[Status: 200, Size: 654, Words: 22, Lines: 1]    | URL | .../api/v1/admin/transactions.php

:: Progress: [688/688] :: Job [1/1] :: 70 req/sec :: Duration: [0:00:10] :: Errors: 0 ::
```

6 hits visibles. Los `401` son endpoints que existen pero requieren un magic header (eso es Fase 4). Esa es la pista para combinar fases.

### Fase 4 - Param mining con arjun

```bash
arjun -u "http://localhost:8080/api/v1/wallets/balance.php?wallet_id=1" \
      -H "Authorization: Bearer $TOK" \
      -w wordlists/cryptovulnx-magic-params.txt \
      --get
```

Output esperado (~720 entries, ~30 segundos por la calibracion estadistica de arjun):

```
[~] Analysing the content of the webpage.
[~] Analysing behaviour for a non-existent parameter.
[~] Logging request count and response length.
[~] Reflections: 0
[~] Response Code: 200
[~] Content Length: 432

[~] Heuristic scanner found 5 valid parameters.
[~] Wordlist: cryptovulnx-magic-params.txt (720 lines)
[~] Total requests: 720
[~] Threads: 5
[~] Concurrent requests: 25

[+] Parameter detected: debug
[+] Parameter detected: include
[+] Parameter detected: fields
[+] Parameter detected: as_user_id
[+] Parameter detected: bypass_rate_limit

[~] Final stable parameters: ['debug', 'include', 'fields', 'as_user_id', 'bypass_rate_limit']
```

5 hits del lab encontrados de las ~720 candidatas. El resto da response identica al baseline y arjun los descarta correctamente.

---

## Mapeo fase -> wordlist

```
FASE 1 RECON
├── raft-medium-files.txt
├── quickhits.txt
├── Common-PHP-Filenames.txt
├── exposures.txt
└── extensions: .bak, .old, .swp, .sql, .json, .lock, ~

FASE 2 INVENTORY
├── cryptovulnx-api-versions.txt   (custom)
├── api-endpoints.txt
├── swagger.txt
└── extensions: .json, .yaml

FASE 3 ENDPOINTS
├── cryptovulnx-endpoints.txt      (custom)
├── api-endpoints.txt
├── api/objects.txt
├── api/actions.txt
├── raft-medium-words.txt
└── extensions: .php, .json, .csv, .xml

FASE 4 PARAMETERS
├── cryptovulnx-magic-params.txt   (custom)
├── burp-parameter-names.txt
├── parameters.txt
└── headers: usar Param Miner default
```

---

## Construir tu propio wordlist custom

```bash
# 1. Recuperar codigo via .git/
git-dumper $TARGET/.git/ ./recovered/

# 2. Extraer identificadores (function names, var names, db columns, urls)
cd recovered/
grep -rohE '[a-z_]+_[a-z_]+' --include="*.php" | sort -u > /tmp/identifiers.txt
grep -rohE '[$_GET|\$_POST|\$_REQUEST|\$_COOKIE]\[.[a-z_]+.\]' --include="*.php" | sort -u >> /tmp/identifiers.txt

# 3. Extraer rutas
grep -rohE '/api/v[0-9]+/[a-z/_]+' --include="*.php" | sort -u > /tmp/paths.txt

# 4. Combinar con magic params estandar
cat cryptovulnx-magic-params.txt /tmp/identifiers.txt | sort -u > custom-final.txt

# 5. Usar contra el target
ffuf -w custom-final.txt -u $TARGET/FUZZ -mc all
```
