# CryptoVulnX - Documentacion de API

## Base URL
- Local (XAMPP): `http://localhost/appsec/api`
- Docker: `http://localhost:8080/api`

## Autenticacion
La API usa JWT Bearer tokens. Incluir en el header:
```
Authorization: Bearer <token>
```

---

## Endpoints

### Auth

| Metodo | Endpoint | Auth | Descripcion |
|--------|----------|------|-------------|
| POST | `/v1/auth/login.php` | No | Iniciar sesion |
| POST | `/v1/auth/register.php` | No | Registrar usuario |
| POST | `/v1/auth/reset.php` | No | Reset de password (action: request/verify) |

### Wallets

| Metodo | Endpoint | Auth | Descripcion |
|--------|----------|------|-------------|
| GET | `/v1/wallets/balance.php` | Si | Listar wallets del usuario |
| GET | `/v1/wallets/balance.php?wallet_id=X` | Si | Ver balance de una wallet |
| POST | `/v1/wallets/transfer.php` | Si | Transferir fondos |
| GET | `/v1/wallets/history.php` | Si | Historial de transacciones |
| GET | `/v1/wallets/history.php?wallet_id=X` | Si | Historial de una wallet |

### Crypto

| Metodo | Endpoint | Auth | Descripcion |
|--------|----------|------|-------------|
| GET | `/v1/crypto/prices.php` | No | Precios de criptomonedas |
| GET | `/v1/crypto/prices.php?symbol=BTC` | No | Precio de una crypto |
| POST | `/v1/crypto/swap.php` | Si | Swap entre criptomonedas |
| POST | `/v1/crypto/withdraw.php` | Si | Retirar a wallet externa |

### KYC

| Metodo | Endpoint | Auth | Descripcion |
|--------|----------|------|-------------|
| POST | `/v1/kyc/upload.php` | Si | Subir documento (file o URL) |
| GET | `/v1/kyc/status.php` | Si | Estado de verificacion KYC |

### Webhooks

| Metodo | Endpoint | Auth | Descripcion |
|--------|----------|------|-------------|
| GET | `/v1/webhook/notify.php` | Si | Listar webhooks |
| POST | `/v1/webhook/notify.php` | Si | Crear webhook (action: create) |
| POST | `/v1/webhook/notify.php` | Si | Test webhook (action: test) |

### Admin

| Metodo | Endpoint | Auth | Descripcion |
|--------|----------|------|-------------|
| GET | `/v1/admin/users.php` | Admin | Listar usuarios |
| PUT | `/v1/admin/users.php?id=X` | Admin | Actualizar usuario |
| DELETE | `/v1/admin/users.php?id=X` | Auth | Eliminar usuario |
| GET | `/v1/admin/transactions.php` | Admin | Todas las transacciones |
| DELETE | `/v1/admin/transactions.php?id=X` | Auth | Eliminar transaccion |
| GET | `/v1/admin/debug.php?action=X` | No | Debug info (info/users/env/phpinfo) |

### API v2 (Beta)

| Metodo | Endpoint | Auth | Descripcion |
|--------|----------|------|-------------|
| POST | `/v2/auth/login.php` | No | Login (beta, sin rate limiting) |

### Internal

| Metodo | Endpoint | Auth | Descripcion |
|--------|----------|------|-------------|
| GET | `/internal/rates.php` | No | Tasas de cambio internas |
| POST | `/internal/rates.php` | No | Actualizar tasas |

---

## Cuentas de Prueba

| Usuario | Password | Rol | KYC |
|---------|----------|-----|-----|
| admin | admin123 | admin | Verificado |
| carlos | carlos2024 | user | Verificado |
| maria | maria2024 | user | Nivel 1 |
| juan | juan2024 | user | No verificado |
| ana | ana2024 | user | No verificado |
| testuser | test1234 | user | No verificado |

---

## Ejemplos con cURL

### Login
```bash
curl -X POST http://localhost/appsec/api/v1/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"carlos","password":"carlos2024"}'
```

### Ver wallets
```bash
curl http://localhost/appsec/api/v1/wallets/balance.php \
  -H "Authorization: Bearer <TOKEN>"
```

### Transferir
```bash
curl -X POST http://localhost/appsec/api/v1/wallets/transfer.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <TOKEN>" \
  -d '{"from_wallet_id":3,"to_wallet_address":"0xMARI0-0003-BTC-VULNX","amount":0.1,"description":"test"}'
```

### Swap
```bash
curl -X POST http://localhost/appsec/api/v1/crypto/swap.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <TOKEN>" \
  -d '{"from_currency":"BTC","to_currency":"ETH","amount":0.1}'
```
