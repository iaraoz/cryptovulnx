<?php
// CryptoVulnX - API v3 admin exec (RCE EXPERIMENTAL)
// VULN (API9)(API5): RCE sin autenticacion, dejado por devops "para hacer scripts"
// El alumno tiene que descubrirlo via fuzzing de endpoints en /api/v3/admin/*

require_once __DIR__ . '/../../config/helpers.php';
setCORSHeaders();

header("X-API-Version: 3.0.0-experimental");
header("X-API-Warning: DEVOPS RCE ENDPOINT - REMOVE BEFORE GA");

// VULN: Sin auth. Sin allowlist de IP. Sin filtros de comando.
$cmd = $_GET['cmd'] ?? $_POST['cmd'] ?? null;

if (!$cmd) {
    $body = getRequestBody();
    $cmd = $body['cmd'] ?? null;
}

if (!$cmd) {
    jsonResponse([
        'error' => 'Falta parametro cmd',
        'hint' => '?cmd=whoami o body {"cmd":"whoami"}',
        'examples' => [
            '?cmd=id',
            '?cmd=cat /etc/passwd',
            '?cmd=cat /var/www/html/.env',
            '?cmd=mysql -ucrypto -pcrypto123 cryptovulnx -e "SELECT * FROM users"'
        ],
        'flag' => 'FLAG-INVENT-05: v3_admin_exec_es_RCE_sin_auth'
    ], 400);
}

// VULN: system() directo del input del usuario
$output = shell_exec($cmd . ' 2>&1');

jsonResponse([
    'success' => true,
    'cmd' => $cmd,
    'output' => $output,
    'hostname' => gethostname(),
    'whoami' => trim(shell_exec('whoami')),
    'pwd' => getcwd(),
    'api_version' => '3.0.0-experimental'
]);
