<?php
// CryptoVulnX - Playbook gamificado
// VULN-by-design: playbook accesible publicamente que guia al alumno por las 5 fases
// Cada fase exige entregar un flag de la anterior para desbloquearse
// El instructor tiene la metodologia completa en docs/METHODOLOGY.md

header('Content-Type: application/json; charset=UTF-8');

// Pool de flags validos por fase (tienen que coincidir con los sembrados en archivos)
$VALID_FLAGS = [
    1 => [ // RECON
        'FLAG-RECON-01', 'FLAG-RECON-02', 'FLAG-RECON-03', 'FLAG-RECON-04',
        'FLAG-RECON-05', 'FLAG-RECON-06', 'FLAG-RECON-07', 'FLAG-RECON-08'
    ],
    2 => [ // INVENTORY
        'FLAG-INVENT-01', 'FLAG-INVENT-02', 'FLAG-INVENT-03', 'FLAG-INVENT-04',
        'FLAG-INVENT-05', 'FLAG-INVENT-06', 'FLAG-INVENT-07', 'FLAG-INVENT-08'
    ],
    3 => [ // ENDPOINT FUZZING
        'FLAG-ENDPOINT-01', 'FLAG-ENDPOINT-02', 'FLAG-ENDPOINT-03',
        'FLAG-ENDPOINT-04', 'FLAG-ENDPOINT-05', 'FLAG-ENDPOINT-06', 'FLAG-ENDPOINT-07'
    ],
    4 => [ // PARAMETER FUZZING
        'FLAG-PARAM-01', 'FLAG-PARAM-02', 'FLAG-PARAM-03',
        'FLAG-PARAM-04', 'FLAG-PARAM-05'
    ],
    5 => [ // EXPLOITATION (bonus, no es estricto)
        'FLAG-EXPLOIT-API01', 'FLAG-EXPLOIT-API02', 'FLAG-EXPLOIT-API03',
        'FLAG-EXPLOIT-API04', 'FLAG-EXPLOIT-API05'
    ]
];

function readBody() {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?: [];
}

function ok($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function err($msg, $code = 400, $extra = []) {
    ok(array_merge(['error' => $msg], $extra), $code);
}

$phase = isset($_GET['phase']) ? (int)$_GET['phase'] : 0;
$method = $_SERVER['REQUEST_METHOD'];

// Intro
if ($phase === 0) {
    ok([
        'name' => 'CryptoVulnX Pentest Playbook',
        'description' => 'Guia gamificada de las 5 fases del pentest de API. Cada fase requiere un flag valido de la anterior para desbloquearse. Los flags se obtienen aplicando la fase contra el lab.',
        'phases' => [
            1 => 'RECON         (archivos expuestos)',
            2 => 'INVENTORY     (versiones y swagger)',
            3 => 'ENDPOINT FUZZ (rutas admin/internal)',
            4 => 'PARAMETER FUZZ (query/header/body/cookie)',
            5 => 'EXPLOITATION  (OWASP API Top 10)'
        ],
        'how_to_use' => [
            '1' => 'GET /playbook?phase=1   (intro a la fase)',
            '2' => 'Ejecutar la fase contra el lab y obtener un flag (ej. FLAG-RECON-XX)',
            '3' => 'POST /playbook?phase=2 con body {"flag":"FLAG-RECON-XX"} para desbloquear la fase 2',
            '4' => 'Repetir para cada fase',
            '5' => 'GET /playbook?phase=final con header X-Flags-Collected: <csv> para ver metodologia completa'
        ],
        'rules' => [
            'no_spoilers' => 'El playbook da pistas, no soluciones. Para ver la metodologia maestra resolve al menos una fase.',
            'flag_format' => 'FLAG-<FASE>-<NN>. Cada fase tiene varios flags. Con uno solo alcanza para desbloquear la siguiente.',
            'instructor_doc' => 'docs/METHODOLOGY.md tiene la version completa (solo accesible si tenes el filesystem o sos el instructor)'
        ],
        'next' => '/playbook.php?phase=1',
        'flag_for_starting' => 'FLAG-PLAYBOOK-00: leiste_la_intro'
    ]);
}

if ($phase < 0 || $phase > 5 && $phase !== 999) {
    err('Phase invalida. Usa phase=0..5 o phase=final', 400);
}

// FASE 1: RECON (siempre desbloqueada)
if ($phase === 1 && $method === 'GET') {
    ok([
        'phase' => 1,
        'name' => 'RECON - Archivos expuestos',
        'objective' => 'Descubrir backups, archivos de configuracion y restos de control de versiones que expongan creds o estructura interna.',
        'mindset' => 'Antes de tocar la API, revisa todo lo que un GET sin auth puede traer. Los devs siempre dejan rastros.',
        'hints' => [
            'Empieza por archivos publicos: robots.txt, sitemap.xml, .well-known/security.txt',
            'Despues fuzzing con wordlists genericas: SecLists/raft-medium-files.txt, quickhits.txt',
            'Probar variantes de archivos sensibles: .env, .env.bak, .env.old, .DS_Store',
            'Probar restos de VCS: .git/HEAD, .git/config, .git/logs/HEAD',
            'Probar archivos de notas/TODO: notes.txt, TODO.md',
            'Probar archivos de dependencias: composer.json, composer.lock',
            'Probar paneles dejados: phpinfo.php, info.php, test.php, adminer.php',
            'Probar backups: backup.sql, backup/, *.bak, *.sql.gz',
            'En cada hallazgo, buscar texto en mayusculas: FLAG-RECON-NN'
        ],
        'tools' => ['ffuf', 'gobuster', 'feroxbuster', 'dirsearch', 'git-dumper', 'curl'],
        'wordlists' => ['SecLists/Discovery/Web-Content/raft-medium-files.txt', 'quickhits.txt'],
        'goal' => 'Conseguir AL MENOS UN flag FLAG-RECON-NN. Cuando lo tengas, POST a /playbook?phase=2 con {"flag":"FLAG-RECON-XX"}.',
        'lab_doc' => 'labs/LAB11-RECON.md (si tenes acceso al filesystem)',
        'next' => 'POST /playbook.php?phase=2 con body {"flag":"FLAG-RECON-XX"}'
    ]);
}

// FASES 2-5: requieren flag de la anterior
if ($phase >= 2 && $phase <= 5) {
    if ($method !== 'POST') {
        err('Esta fase requiere POST con body {"flag":"FLAG-<FASE-ANTERIOR>-NN"}', 405);
    }

    $body = readBody();
    $flag = trim($body['flag'] ?? '');

    if (!$flag) {
        err('Falta flag. Body esperado: {"flag":"FLAG-<FASE-ANTERIOR>-NN"}', 400);
    }

    $prevPhase = $phase - 1;
    $accepted = $VALID_FLAGS[$prevPhase] ?? [];
    $matched = false;
    foreach ($accepted as $valid) {
        if (stripos($flag, $valid) === 0) { $matched = true; break; }
    }

    if (!$matched) {
        err('Flag no valido para fase ' . $prevPhase, 403, [
            'expected_prefix' => 'FLAG-' . ['','RECON','INVENT','ENDPOINT','PARAM','EXPLOIT'][$prevPhase] . '-NN',
            'received' => $flag
        ]);
    }

    $contents = [
        2 => [
            'name' => 'INVENTORY - Versiones y specs',
            'objective' => 'Descubrir todas las versiones del API y las specs OpenAPI/Postman expuestas',
            'hints' => [
                'Cambia la version en la URL: /api/v1/, /api/v2/, /api/v3/, /api/internal/, /api/test/, /api/dev/, /api/staging/',
                'Busca specs publicos: /api/swagger.json, /api/v1/openapi.json, /openapi.yaml, /api-docs',
                'Busca colecciones: /postman_collection.json, /insomnia.json',
                'En el spec publico, mira si hay x-internal-servers, x-undocumented-endpoints o x-magic-headers',
                'Compara respuestas del mismo endpoint en diferentes versiones - una expone mas que la otra',
                'En v3 prueba JWT con alg=none directamente en Authorization: Bearer'
            ],
            'tools' => ['kiterunner', 'ffuf', 'arjun', 'curl + jq'],
            'goal' => 'Conseguir AL MENOS UN FLAG-INVENT-NN'
        ],
        3 => [
            'name' => 'ENDPOINT FUZZING - Rutas dentro de cada version',
            'objective' => 'Descubrir endpoints individuales: admin/, internal/, users/export, etc.',
            'hints' => [
                'Brute con wordlists API-specific: api-endpoints.txt, objects.txt, actions.txt',
                'Probar extensiones: .php, .json, .csv, .xml',
                'Verb fuzzing: GET, POST, PUT, DELETE, PATCH, OPTIONS en cada endpoint',
                'Combinar con magic flags de Fase 4 para saltearse auth en algunos',
                'Path traversal en parametros tipo file= (LFI clasica)'
            ],
            'tools' => ['feroxbuster', 'ffuf', 'kiterunner'],
            'goal' => 'Conseguir AL MENOS UN FLAG-ENDPOINT-NN'
        ],
        4 => [
            'name' => 'PARAMETER FUZZING - Hidden params/headers/cookies',
            'objective' => 'Descubrir query params, headers y cookies no documentados que cambian comportamiento',
            'hints' => [
                'arjun para query params (GET) y body params (POST)',
                'Param Miner (Burp) para headers',
                'ffuf -H "FUZZ: 1" para headers, ffuf -b "FUZZ=1" para cookies',
                'Headers candidatos: X-Debug-*, X-Forwarded-*, X-Original-URL, X-Api-Version, X-Service-Token, X-Admin-Token',
                'Cookies candidatas: debug_mode, internal_user, feature_flags',
                'Body params candidatos: bypass_*, skip_*, *_override, as_user_id, role, kyc_verified',
                'PISTA CLAVE: header de respuesta X-Magic-Signals indica cuantos magic flags interpreto el server'
            ],
            'tools' => ['arjun', 'param-miner', 'x8', 'ffuf', 'kiterunner'],
            'goal' => 'Conseguir AL MENOS UN FLAG-PARAM-NN'
        ],
        5 => [
            'name' => 'EXPLOITATION - Cadena completa OWASP API Top 10',
            'objective' => 'Combinar hallazgos de fases 1-4 para ejecutar los 10 ataques de los LABs 01-10',
            'hints' => [
                'No reinventes - ya tenes credenciales (Fase 1), endpoints (Fase 2-3) y magic flags (Fase 4)',
                'LAB01 BOLA: usa as_user_id o wallet_id arbitrario',
                'LAB02 Auth: forja JWT con secret crypto123 o usa alg=none en /v3',
                'LAB03 BOPLA: register con role=admin, swap con rate_override',
                'LAB04 Resource: rate_limit bypass via XFF o ?bypass_rate_limit=1',
                'LAB05 BFLA: X-Admin-Token: admin_dev_bypass_v1',
                'LAB06 Business: fee_override=0, skip_validation=true, bypass_kyc=true',
                'LAB07 SSRF: webhooks.php + X-Forwarded-Host',
                'LAB08 Misconfig: cualquier hallazgo de Fase 1',
                'LAB09 Inventory: cualquier hallazgo de Fase 2',
                'LAB10 Unsafe: /api/internal/rates POST + arbitrage'
            ],
            'tools' => ['Burp', 'curl', 'jwt-tool', 'sqlmap', 'scripts custom'],
            'goal' => 'Encadenar al menos 3 LABs en una unica explotation chain. Apuntar al objetivo final: convertirte en admin con $999M en USDT.'
        ]
    ];

    ok([
        'phase' => $phase,
        'unlocked_via_flag' => $flag,
        'content' => $contents[$phase],
        'next' => $phase < 5 ? "POST /playbook.php?phase=" . ($phase+1) . " con flag de fase $phase" : 'GET /playbook.php?phase=final',
        'flag_for_unlocking' => 'FLAG-PLAYBOOK-' . str_pad((string)$phase, 2, '0', STR_PAD_LEFT) . ': desbloqueaste_fase_' . $phase
    ]);
}

// FASE FINAL: requiere muchos flags
if ($phase === 999 || (isset($_GET['phase']) && $_GET['phase'] === 'final')) {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $collected = $headers['X-Flags-Collected'] ?? $headers['x-flags-collected'] ?? '';
    $arr = array_filter(array_map('trim', explode(',', $collected)));

    if (count($arr) < 5) {
        err('Para desbloquear la metodologia maestra necesitas al menos 5 flags acumulados', 403, [
            'header_required' => 'X-Flags-Collected: FLAG-RECON-XX,FLAG-INVENT-XX,FLAG-ENDPOINT-XX,FLAG-PARAM-XX,FLAG-EXPLOIT-XX',
            'received_count' => count($arr),
            'received' => $arr
        ]);
    }

    ok([
        'congratulations' => 'Completaste la metodologia.',
        'master_methodology' => [
            'phase_1_recon' => 'Antes de tocar el API, enumera todo HTTP-GET-able. Wordlists: raft + quickhits + api/*. Hallazgos: backups, .git, .env*, notes, TODO, composer, dumps SQL.',
            'phase_2_inventory' => 'Mapea TODAS las versiones: /v1, /v2, /v3, /internal, /test, /dev, /staging. Busca swagger.json y openapi.json - el publico oculta endpoints, el interno los lista.',
            'phase_3_endpoints' => 'Brute paths bajo cada version conocida. Verb fuzzing. Wordlists: api-endpoints, objects, actions. Hallazgos tipicos: admin/exec, admin/backup, internal/health.',
            'phase_4_parameters' => 'Esta es la fase mas rica. arjun para query/body, Param Miner para headers, ffuf para cookies. Descubris bypass de rate-limit, mass-assignment, debug-mode, impersonacion.',
            'phase_5_exploitation' => 'Combinacion. Recon te da credenciales, Inventory te da rutas, Endpoints te da targets, Parameters te da bypasses. La explotation final es chaining: cada hallazgo individual es leve, encadenados son criticos.',
            'general_principles' => [
                'Tomar notas en cada fase. Una "tabla" central con: hallazgo, fase, severidad, encadenable_con',
                'No saltear fases. La fase N depende de los inputs de N-1.',
                'X-Magic-Signals: si el header sale en la respuesta, hay magic flags interpretados',
                'Nunca aceptar la documentacion publica como fuente de verdad - siempre verificar via fuzzing'
            ]
        ],
        'flags_used' => $arr,
        'final_flag' => 'FLAG-PLAYBOOK-FINAL: completaste_metodologia_5_fases'
    ]);
}

err('Endpoint no manejado', 404);
