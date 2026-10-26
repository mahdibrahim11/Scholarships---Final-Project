<?php
/**
 * Include this FIRST on every page:  require_once __DIR__ . '/includes/bootstrap.php';
 * (from a subfolder: require_once __DIR__ . '/../includes/bootstrap.php';)
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$configFile = APP_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Missing config/config.php. Copy config/config.sample.php to config/config.php and edit it.');
}
$GLOBALS['config'] = require $configFile;

function config(string $path, $default = null)
{
    $v = $GLOBALS['config'];
    foreach (explode('.', $path) as $part) {
        if (!is_array($v) || !array_key_exists($part, $v)) {
            return $default;
        }
        $v = $v[$part];
    }
    return $v;
}

// Errors: show while developing, never in production (they can leak file paths and queries).
if (config('app.env') === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

// Session cookie hardening (FR-AUTH-04 groundwork).
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Basic security headers. CDN hosts are listed explicitly; add to this list if you add a library.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; "
     . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://cdn.datatables.net https://cdnjs.cloudflare.com; "
     . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.datatables.net https://fonts.googleapis.com; "
     . "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; img-src 'self' data:");

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';
