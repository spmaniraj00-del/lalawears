<?php
// Router for PHP built-in server: php -S localhost:8080 router.php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Railway / uptime probes — no bootstrap, no redirects, no firewall
if ($path === '/healthz' || $path === '/health') {
    http_response_code(200);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    echo 'ok';
    return true;
}

$file = __DIR__ . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

if ($path !== '/' && is_file($file . '.php')) {
    require $file . '.php';
    return true;
}

require __DIR__ . '/index.php';
