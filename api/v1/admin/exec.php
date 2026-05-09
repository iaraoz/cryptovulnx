<?php
// CryptoVulnX - admin/exec
// VULN (API5): RCE con auth "debil" via X-Admin-Token estatico

require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/magic.php';

setCORSHeaders();

// Auth "debil": basta con tener el token estatico
if (!hasAdminBypass()) {
    jsonResponse([
        'error' => 'Unauthorized',
        'hint' => 'Header X-Admin-Token: admin_dev_bypass_v1, o Cookie internal_user=admin'
    ], 401);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonResponse([
        'success' => true,
        'message' => 'admin/exec endpoint - send POST {"cmd":"<command>"}',
        'auth_status' => 'authenticated_via_static_token',
        'examples' => [
            '{"cmd": "whoami"}',
            '{"cmd": "id"}',
            '{"cmd": "cat /var/www/html/.env"}',
            '{"cmd": "ls -la /var/www/html/uploads/kyc/"}',
            '{"cmd": "mysql -u root -p\\"\\" cryptovulnx -e \\"SELECT * FROM users\\""}'
        ],
        'flag' => 'FLAG-ENDPOINT-03: admin_exec_RCE_via_static_token'
    ]);
}

$body = getRequestBody();
$cmd = $body['cmd'] ?? $_POST['cmd'] ?? null;

if (!$cmd) {
    jsonResponse(['error' => 'Falta cmd en body'], 400);
}

$output = shell_exec($cmd . ' 2>&1');

jsonResponse([
    'success' => true,
    'cmd' => $cmd,
    'output' => $output,
    'whoami' => trim(shell_exec('whoami')),
    'hostname' => gethostname()
]);
