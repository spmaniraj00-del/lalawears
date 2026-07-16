<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (!google_configured()) {
    flash('error', 'Google login is not configured.');
    redirect('auth/login.php');
}

$error = $_GET['error'] ?? '';
if ($error) {
    flash('error', 'Google sign-in was cancelled.');
    redirect('auth/login.php');
}

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if ($code === '' || !google_oauth_state_valid(is_string($state) ? $state : null)) {
    flash('error', 'Invalid Google sign-in response. Please try again.');
    redirect('auth/login.php');
}

try {
    $token = http_post_form('https://oauth2.googleapis.com/token', [
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => app_absolute_url('auth/google_callback.php'),
        'grant_type' => 'authorization_code',
    ]);

    if (empty($token['access_token'])) {
        throw new RuntimeException($token['error_description'] ?? 'Token exchange failed.');
    }

    $profile = http_get_json(
        'https://www.googleapis.com/oauth2/v2/userinfo',
        (string) $token['access_token']
    );

    $result = login_or_register_google($profile);
    if (!$result['ok']) {
        flash('error', $result['error']);
        redirect('auth/login.php');
    }

    flash('success', 'Welcome, ' . $result['user']['name'] . '!');
    $next = $_SESSION['login_next'] ?? '';
    unset($_SESSION['login_next']);
    if (is_string($next) && $next !== '' && str_starts_with($next, 'account/')) {
        redirect($next);
    }
    redirect('account/index.php');
} catch (Throwable $e) {
    flash('error', 'Google login failed: ' . $e->getMessage());
    redirect('auth/login.php');
}
