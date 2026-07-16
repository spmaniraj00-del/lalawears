<?php
declare(strict_types=1);

/**
 * LALA WEARS — Lightweight Web Application Firewall (WAF)
 * Protects against SQL Injection, XSS, Path Traversal, and Malicious Scanners.
 */

function run_firewall(): void
{
    $uriPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    // Never block Railway / uptime health probes
    if ($uriPath === '/healthz' || $uriPath === '/health') {
        return;
    }

    // 1. Blacklisted User Agents (Scanners and automated tools)
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $blockedAgents = [
        'sqlmap', 'acunetix', 'nikto', 'dirbuster', 'nmap', 'havij',
        'w3af', 'netsparker', 'censys', 'shodan',
        // NOTE: do NOT block curl/wget — Railway healthchecks use them
    ];

    foreach ($blockedAgents as $agent) {
        if (stripos($userAgent, $agent) !== false) {
            firewall_block_request('Malicious User Agent Detected');
        }
    }

    // 2. Scan request variables (GET, POST, COOKIES, Query String)
    $patterns = [
        // SQL Injection attempts
        'sql_injection' => '/(union\s+all\s+select|select\s+.*\s+from|insert\s+into|delete\s+from|drop\s+table|update\s+.*\s+set|union\s+select|or\s+\d+=\d+|[\'"]\s*or\s*[\'"]\d+[\'"]\s*=\s*[\'"]\d+)/i',
        // Cross-Site Scripting (XSS)
        'xss' => '/(<script|javascript:|onerror\s*=|onload\s*=|onmouseover\s*=|alert\(|<iframe|<svg)/i',
        // Path Traversal / LFI
        'path_traversal' => '/(\.\.\/|\.\.\\\\|\/etc\/passwd|boot\.ini|win\.ini)/i'
    ];

    // Scan Query String directly
    $queryString = $_SERVER['QUERY_STRING'] ?? '';
    foreach ($patterns as $type => $pattern) {
        if (preg_match($pattern, rawurldecode($queryString))) {
            firewall_block_request('Suspicious pattern detected in query parameters');
        }
    }

    // Recursively scan arrays (GET, POST, COOKIES)
    firewall_scan_array($_GET, $patterns);
    firewall_scan_array($_POST, $patterns);
    firewall_scan_array($_COOKIE, $patterns);
}

function firewall_scan_array(array $array, array $patterns): void
{
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            firewall_scan_array($value, $patterns);
        } else {
            $decodedValue = rawurldecode((string)$value);
            foreach ($patterns as $type => $pattern) {
                if (preg_match($pattern, $decodedValue)) {
                    // Avoid false positives for normal descriptions/text in contact forms
                    if ($type === 'sql_injection' && strlen($decodedValue) > 100) {
                        continue;
                    }
                    firewall_block_request('Suspicious pattern detected in inputs');
                }
            }
        }
    }
}

function firewall_block_request(string $reason): void
{
    header('HTTP/1.1 403 Forbidden');
    
    // Log the block to safety logs (optional, let's keep it simple and clean)
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $time = date('Y-m-d H:i:s');
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $logMsg = "[{$time}] Blocked IP: {$ip} | Reason: {$reason} | URI: {$uri}\n";
    @file_put_contents(APP_ROOT . '/data/firewall_blocked.log', $logMsg, FILE_APPEND);

    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Access Blocked | LALA WEARS Security</title>
      <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700;900&display=swap" rel="stylesheet">
      <style>
        body { font-family: "League Spartan", sans-serif; background: #fdf8f3; color: #262626; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
        .card { background: #fff; padding: 40px; border-radius: 24px; box-shadow: 0 10px 40px rgba(38,38,38,0.06); max-width: 500px; text-align: center; border: 1px solid rgba(228, 164, 189, 0.3); }
        .icon { width: 64px; height: 64px; margin: 0 auto 24px; color: #ef4444; }
        h1 { font-size: 2rem; font-weight: 900; text-transform: uppercase; margin: 0 0 12px; color: #262626; }
        p { font-size: 1.05rem; line-height: 1.5; color: #595959; margin: 0 0 24px; }
        .meta { font-size: 0.85rem; font-weight: 600; color: #a6a6a6; text-transform: uppercase; letter-spacing: 0.1em; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 12px 32px; border-radius: 999px; background: #e4a4bd; color: #262626; font-size: 11px; font-weight: 900; letter-spacing: 0.2em; text-transform: uppercase; text-decoration: none; transition: transform 0.3s ease; }
        .btn:hover { transform: scale(1.04); }
      </style>
    </head>
    <body>
      <div class="card">
        <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <h1>Security Block</h1>
        <p>Your request was blocked by our security system. If you believe this was an error, please contact our support.</p>
        <a href="/" class="btn">Back to Home</a>
        <div style="margin-top: 28px;" class="meta">REF ID: ' . bin2hex(random_bytes(4)) . '</div>
      </div>
    </body>
    </html>';
    exit;
}
