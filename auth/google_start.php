<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (!google_configured()) {
    flash('error', 'Google login is not configured. Add credentials in config/config.local.php');
    redirect('auth/login.php');
}

if (empty($_SESSION[CSRF_TOKEN_KEY])) {
    csrf_token();
}

redirect(google_oauth_url());
