<?php
// CryptoVulnX - KYC Status endpoint
// VULN (API1): BOLA - puede ver KYC de otros usuarios

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$user = requireAuth();
$conn = getDBConnection();

$user_id = $_GET['user_id'] ?? $user['user_id'];

// VULN (API1): BOLA - acepta user_id como parámetro sin verificar que sea el usuario autenticado
$result = $conn->query("SELECT k.*, u.username, u.email, u.full_name, u.phone, u.address
                        FROM kyc_documents k
                        JOIN users u ON k.user_id = u.id
                        WHERE k.user_id = $user_id
                        ORDER BY k.created_at DESC");

$documents = [];
while ($row = $result->fetch_assoc()) {
    $documents[] = [
        'id' => (int)$row['id'],
        'document_type' => $row['document_type'],
        'file_path' => $row['file_path'],       // VULN (API3): Expone ruta
        'file_url' => $row['file_url'],
        'status' => $row['status'],
        'review_notes' => $row['review_notes'],
        'created_at' => $row['created_at'],
        'user_info' => [                         // VULN (API3): Expone PII
            'username' => $row['username'],
            'email' => $row['email'],
            'full_name' => $row['full_name'],
            'phone' => $row['phone'],
            'address' => $row['address']
        ]
    ];
}

jsonResponse([
    'success' => true,
    'user_id' => (int)$user_id,
    'kyc_documents' => $documents
]);
