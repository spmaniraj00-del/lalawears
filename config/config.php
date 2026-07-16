<?php
declare(strict_types=1);

/**
 * LALA WEARS — application configuration
 */
define('APP_NAME', 'LALA WEARS');
define('APP_TAGLINE', 'Crafted For Style');
define('APP_ROOT', dirname(__DIR__));

define('DB_PATH', APP_ROOT . '/data/lalawears.sqlite');
define('UPLOAD_DIR', APP_ROOT . '/assets/uploads');

define('WHATSAPP_NUMBER', '916205484119');
define('WHATSAPP_URL', 'https://api.whatsapp.com/send?phone=' . WHATSAPP_NUMBER . '&text=Hello%2C%20I%20want%20to%20know%20about%20apparel.%20Could%20you%20please%20send%20the%20full%20details%3F');
define('CONTACT_PHONE', '+91 6205484119');
define('CONTACT_EMAIL', 'dibyanshu.1324@gmail.com');
define('CONTACT_LOCATION', 'Bettiah, Bihar');
define('INSTAGRAM_URL', 'https://instagram.com/lalawears.co.in');
define('FOUNDER_NAME', 'Divyanshu Shrivastava');

define('DEFAULT_ADMIN_USER', 'admin');
define('DEFAULT_ADMIN_PASS', 'LalaAdmin@2026');
define('DEFAULT_ADMIN_NAME', 'Divyanshu Shrivastava');

// Optional local overrides (Google / Resend keys, etc.)
$__local = [
    'GOOGLE_CLIENT_ID' => getenv('GOOGLE_CLIENT_ID') ?: '',
    'GOOGLE_CLIENT_SECRET' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
    'RESEND_API_KEY' => getenv('RESEND_API_KEY') ?: '',
    'RESEND_FROM' => getenv('RESEND_FROM') ?: 'onboarding@resend.dev',
    'OTP_SHOW_ON_SITE' => true,
];
$localFile = APP_ROOT . '/config/config.local.php';
if (is_file($localFile)) {
    $loaded = require $localFile;
    if (is_array($loaded)) {
        $__local = array_merge($__local, $loaded);
    }
}

define(
    'GOOGLE_CLIENT_ID',
    $__local['GOOGLE_CLIENT_ID'] !== ''
        ? $__local['GOOGLE_CLIENT_ID']
        : 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com'
);
define(
    'GOOGLE_CLIENT_SECRET',
    $__local['GOOGLE_CLIENT_SECRET'] !== ''
        ? $__local['GOOGLE_CLIENT_SECRET']
        : 'YOUR_GOOGLE_CLIENT_SECRET'
);
define('RESEND_API_KEY', (string) ($__local['RESEND_API_KEY'] ?? ''));
define('RESEND_FROM', (string) (($__local['RESEND_FROM'] ?? '') !== '' ? $__local['RESEND_FROM'] : 'onboarding@resend.dev'));
define('OTP_LENGTH', 6);
define('OTP_EXPIRY_MINUTES', 5);
define('OTP_RESEND_SECONDS', 30);
// Show OTP on verify page (dev / easy login). Set false in production.
define('OTP_SHOW_ON_SITE', array_key_exists('OTP_SHOW_ON_SITE', $__local) ? (bool) $__local['OTP_SHOW_ON_SITE'] : true);

define('SESSION_NAME', 'LALAWEARSSESSID');
define('CSRF_TOKEN_KEY', '_csrf_token');
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCK_MINUTES', 15);

date_default_timezone_set('Asia/Kolkata');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    session_name(SESSION_NAME);
    session_start();
}

function app_base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $dir = dirname($script);
    if (preg_match('#/(admin|auth|account)$#', $dir)) {
        $dir = dirname($dir);
    }
    $base = ($dir === '/' || $dir === '\\' || $dir === '.') ? '' : rtrim($dir, '/');
    return $base;
}

function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $base = app_base_url();
    return $base . '/' . $path;
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function app_absolute_url(string $path = ''): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
    return $scheme . '://' . $host . url($path);
}

function google_configured(): bool
{
    return GOOGLE_CLIENT_ID !== ''
        && GOOGLE_CLIENT_SECRET !== ''
        && !str_contains(GOOGLE_CLIENT_ID, 'YOUR_GOOGLE')
        && !str_contains(GOOGLE_CLIENT_SECRET, 'YOUR_GOOGLE');
}

function resend_configured(): bool
{
    return RESEND_API_KEY !== '' && str_starts_with(RESEND_API_KEY, 're_');
}
