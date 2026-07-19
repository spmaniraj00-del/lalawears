<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

// Production: HTTPS + canonical www host (GoDaddy / Cloudflare / Railway)
force_https_redirect();

require_once __DIR__ . '/firewall.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/resend.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/terminalx.php';
require_once __DIR__ . '/otp.php';
require_once __DIR__ . '/recaptcha.php';

// Run Web Application Firewall
run_firewall();

set_security_headers();
db(); // ensure DB ready
log_visitor_activity(); // record page view activity
