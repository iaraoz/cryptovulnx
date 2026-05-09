<?php
// CryptoVulnX - admin/logs
// VULN (API5): Sin auth solida + LFI clasica via ?file=

require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/magic.php';

setCORSHeaders();
emitMagicSignalsHeader();

// VULN: Permite acceder con magic flags
if (!hasAdminBypass() && !isInternalRequest()) {
    jsonResponse([
        'error' => 'Unauthorized',
        'hint' => 'X-Admin-Token: admin_dev_bypass_v1, Cookie internal_user=admin, o IP interna'
    ], 401);
}

$file = $_GET['file'] ?? 'app.log';
$lines = (int)($_GET['lines'] ?? 100);

// VULN (API8): LFI - sin filtro de path traversal
$baseDir = '/var/log/cryptovulnx/';
$path = $baseDir . $file;

// Si el archivo no existe en /var/log/cryptovulnx/, intenta absoluto
if (!file_exists($path)) {
    $path = $file;
}

if (!file_exists($path)) {
    // Logs simulados si no hay archivos reales
    $simulated = [
        'app.log' => "[2026-04-15 03:01:22] login user=carlos ip=10.0.1.10 status=200\n[2026-04-15 03:02:11] login user=admin ip=10.0.1.10 status=200\n[2026-04-15 03:05:45] swap user=admin from=BTC to=USDT amount=10 rate=67000\n",
        'error.log' => "[2026-04-15 02:50:00] PHP Notice: undefined index 'role' in /var/www/html/api/v1/auth/register.php on line 24\n[2026-04-15 03:00:00] PHP Warning: hash_equals expects string, null given in jwt.php\n",
        'access.log' => "10.0.1.10 - - [15/Apr/2026:03:01:22] \"POST /api/v1/auth/login.php HTTP/1.1\" 200\n10.0.1.50 - admin [15/Apr/2026:03:02:00] \"POST /api/v1/admin/exec.php?cmd=ls HTTP/1.1\" 200\n",
        'audit.log' => "FLAG-ENDPOINT-02: logs_php_LFI_via_file_param\n"
    ];

    if (isset($simulated[basename($file)])) {
        header('Content-Type: text/plain');
        echo $simulated[basename($file)];
        exit;
    }

    jsonResponse(['error' => "File '$file' not found", 'tried' => [$baseDir . $file, $file]], 404);
}

// VULN: Lee cualquier archivo del sistema si tienes permisos
header('Content-Type: text/plain');
$content = file_get_contents($path);
$linesArr = explode("\n", $content);
$tail = array_slice($linesArr, -$lines);
echo implode("\n", $tail);
