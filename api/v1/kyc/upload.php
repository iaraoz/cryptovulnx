<?php
// CryptoVulnX - KYC Document Upload endpoint
// VULN (API7): SSRF via URL fetch, (API4): Sin límite de tamaño, sin validación de tipo

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$user = requireAuth();
$conn = getDBConnection();
$userId = $user['user_id'];

// Soporta dos métodos: file upload directo o URL
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($contentType, 'application/json') !== false) {
    // Método URL - VULN (API7): SSRF
    $data = getRequestBody();
    $document_type = $data['document_type'] ?? 'national_id';
    $file_url = $data['file_url'] ?? '';

    if (empty($file_url)) {
        jsonResponse(['error' => 'file_url es requerido'], 400);
    }

    // VULN (API7): SSRF - No valida la URL, permite acceder a recursos internos
    // Un atacante puede usar:
    //   http://169.254.169.254/latest/meta-data/ (AWS metadata)
    //   http://localhost:3306/ (servicios internos)
    //   file:///etc/passwd (archivos locales)
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $file_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWREDIRECTS => true,    // VULN: Sigue redirects
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_SSL_VERIFYPEER => false,    // VULN: Sin verificación SSL
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HEADER => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        jsonResponse([
            'error' => 'No se pudo descargar el archivo',
            'curl_error' => $error,
            'url_attempted' => $file_url  // VULN (API8): Expone la URL
        ], 400);
    }

    // VULN (API4): Sin límite de tamaño del archivo descargado
    $filename = 'kyc_' . $userId . '_' . time() . '_' . basename(parse_url($file_url, PHP_URL_PATH));
    $uploadDir = __DIR__ . '/../../../uploads/kyc/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // VULN: Path traversal - basename no se aplica correctamente si la URL tiene ..
    $filePath = $uploadDir . $filename;
    file_put_contents($filePath, $response);

    $conn->query("INSERT INTO kyc_documents (user_id, document_type, file_path, file_url, status)
                  VALUES ($userId, '$document_type', '$filePath', '$file_url', 'pending')");

    // VULN (API8): Expone información del servidor en la respuesta
    jsonResponse([
        'success' => true,
        'message' => 'Documento subido exitosamente',
        'document' => [
            'id' => $conn->insert_id,
            'document_type' => $document_type,
            'file_path' => $filePath,           // VULN: Expone ruta del servidor
            'source_url' => $file_url,
            'effective_url' => $effectiveUrl,   // VULN: Expone URL efectiva (después de redirects)
            'http_code' => $httpCode,
            'content_type' => $contentType,
            'file_size' => strlen($response),
            'status' => 'pending'
        ]
    ], 201);

} else {
    // Método file upload directo
    if (!isset($_FILES['document'])) {
        jsonResponse(['error' => 'Envía un archivo como "document" o usa JSON con "file_url"'], 400);
    }

    $file = $_FILES['document'];
    $document_type = $_POST['document_type'] ?? 'national_id';

    // VULN (API4): Sin validación de tipo de archivo - acepta cualquier extensión
    // VULN (API4): Sin límite de tamaño
    $uploadDir = __DIR__ . '/../../../uploads/kyc/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // VULN: Usa el nombre original del archivo - path traversal posible
    $filename = $file['name'];
    $filePath = $uploadDir . $filename;

    move_uploaded_file($file['tmp_name'], $filePath);

    $conn->query("INSERT INTO kyc_documents (user_id, document_type, file_path, status)
                  VALUES ($userId, '$document_type', '$filePath', 'pending')");

    jsonResponse([
        'success' => true,
        'message' => 'Documento subido exitosamente',
        'document' => [
            'id' => $conn->insert_id,
            'filename' => $filename,
            'file_path' => $filePath,
            'document_type' => $document_type,
            'status' => 'pending'
        ]
    ], 201);
}
