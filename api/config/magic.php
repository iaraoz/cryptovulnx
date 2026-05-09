<?php
// CryptoVulnX - Magic params, headers y cookies
// VULN (API8): Helpers que respetan parametros y headers ocultos no documentados
// Pensados para parameter-mining tools (arjun, x8, param-miner)

if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

/**
 * Devuelve true si el cliente pidio modo debug.
 * Activado por: ?debug=1, header X-Debug-Token: 1, cookie debug_mode=1
 */
function isDebugRequested() {
    if (isset($_GET['debug']) && $_GET['debug']) return true;
    if (isset($_POST['debug']) && $_POST['debug']) return true;
    $headers = getallheaders();
    $debugHeader = $headers['X-Debug-Token'] ?? $headers['x-debug-token'] ?? null;
    if ($debugHeader) return true;
    if (isset($_COOKIE['debug_mode']) && $_COOKIE['debug_mode']) return true;
    return false;
}

/**
 * Devuelve la lista de campos extra a incluir en la respuesta.
 * Activado por ?include=field1,field2,*
 */
function getIncludeFields() {
    $include = $_GET['include'] ?? '';
    if (empty($include)) return [];
    if ($include === '*') return ['*'];
    return array_map('trim', explode(',', $include));
}

/**
 * Devuelve true si el cliente pidio dump completo de columnas.
 * Activado por ?fields=*
 */
function wantsAllFields() {
    return isset($_GET['fields']) && $_GET['fields'] === '*';
}

/**
 * Si esta presente, permite impersonar a otro user_id.
 * Activado por ?as_user_id=N (legacy de QA)
 */
function getImpersonationUserId() {
    return isset($_GET['as_user_id']) ? (int)$_GET['as_user_id'] : null;
}

/**
 * Devuelve true si el cliente pidio bypass de rate limit.
 * Activado por ?bypass_rate_limit=1, header X-Bypass-RateLimit: 1
 */
function wantsRateLimitBypass() {
    if (isset($_GET['bypass_rate_limit']) && $_GET['bypass_rate_limit']) return true;
    $headers = getallheaders();
    if (!empty($headers['X-Bypass-RateLimit']) || !empty($headers['x-bypass-ratelimit'])) return true;
    return false;
}

/**
 * Devuelve true si el cliente esta marcado como "interno".
 * Activado por:
 *   - X-Forwarded-For que empieza con 10., 127., 192.168.
 *   - X-Real-IP idem
 *   - Cookie internal_user=admin
 *   - X-Api-Version: internal
 *   - X-Service-Token: internal_svc_token_2024_q1
 *   - X-Original-URL apuntando a /api/internal/
 */
function isInternalRequest() {
    $headers = getallheaders();

    foreach (['X-Forwarded-For','x-forwarded-for','X-Real-IP','x-real-ip'] as $h) {
        $val = $headers[$h] ?? null;
        if ($val) {
            $ip = trim(explode(',', $val)[0]);
            if (preg_match('/^(127\.|10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/', $ip)) {
                return true;
            }
        }
    }

    if (!empty($_COOKIE['internal_user'])) return true;

    $apiVer = $headers['X-Api-Version'] ?? $headers['x-api-version'] ?? '';
    if (strtolower($apiVer) === 'internal') return true;

    $svcToken = $headers['X-Service-Token'] ?? $headers['x-service-token'] ?? '';
    if ($svcToken === 'internal_svc_token_2024_q1') return true;

    $origUrl = $headers['X-Original-URL'] ?? $headers['x-original-url'] ?? '';
    if (strpos($origUrl, '/api/internal/') === 0 || strpos($origUrl, '/admin') === 0) return true;

    return false;
}

/**
 * Devuelve true si el cliente trae el bypass de admin.
 * Activado por: header X-Admin-Token: admin_dev_bypass_v1, cookie internal_user=admin
 */
function hasAdminBypass() {
    $headers = getallheaders();
    $tok = $headers['X-Admin-Token'] ?? $headers['x-admin-token'] ?? '';
    if ($tok === 'admin_dev_bypass_v1') return true;
    if (($_COOKIE['internal_user'] ?? '') === 'admin') return true;
    if (in_array('admin_panel', explode(',', $_COOKIE['feature_flags'] ?? ''))) return true;
    return false;
}

/**
 * Cuenta cuantos magic-params/headers/cookies vinieron en este request.
 * Util para que algunos endpoints solo activen "modos extra" cuando
 * se acumulan varios magic flags.
 */
function countMagicSignals() {
    $n = 0;
    if (isDebugRequested())      $n++;
    if (wantsAllFields())        $n++;
    if (getIncludeFields())      $n++;
    if (wantsRateLimitBypass())  $n++;
    if (isInternalRequest())     $n++;
    if (hasAdminBypass())        $n++;
    if (getImpersonationUserId()) $n++;
    return $n;
}

// VULN (API8): Header de respuesta que filtra cuantos magic-flags se detectaron
// Esto ayuda al fuzzer: si X-Magic-Signals va en la respuesta, esta cerca
function emitMagicSignalsHeader() {
    $n = countMagicSignals();
    if ($n > 0) {
        header("X-Magic-Signals: $n");
    }
}
