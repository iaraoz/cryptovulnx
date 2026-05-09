<?php
// CryptoVulnX - users/export.csv (servido por export.php gracias a la regla rewrite)
// VULN (API1)(API3): Export CSV completo de usuarios sin auth

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/magic.php';

// VULN: Sin auth si pasas un magic flag o IP interna
if (!hasAdminBypass() && !isInternalRequest()) {
    if (($_GET['key'] ?? '') !== 'export_2024') {
        setCORSHeaders();
        jsonResponse([
            'error' => 'Unauthorized',
            'hint' => '?key=export_2024 o X-Admin-Token o IP interna'
        ], 401);
    }
}

header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=users_export_" . date('Y-m-d') . ".csv");

$conn = getDBConnection();
$result = $conn->query("SELECT id, username, email, password_plain, password_hash, role, kyc_verified, full_name, phone, address, referral_code, referral_bonus, created_at FROM users");

$out = fopen('php://output', 'w');
$first = true;
while ($row = $result->fetch_assoc()) {
    if ($first) {
        fputcsv($out, array_keys($row));
        $first = false;
    }
    fputcsv($out, $row);
}
fputcsv($out, ['# FLAG-ENDPOINT-06', 'export_csv_dump_de_usuarios']);
fclose($out);
