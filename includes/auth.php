<?php
declare(strict_types=1);

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $cached = false;
    static $user = null;
    if ($cached) {
        return $user;
    }
    $stmt = db()->prepare(
        'SELECT id, name, phone, email, role, username, avatar, google_id, is_active
         FROM users WHERE id = ? LIMIT 1'
    );
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    if (!$user || !(int) $user['is_active']) {
        logout_user();
        $user = null;
    }
    $cached = true;
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['login_at'] = time();
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('error', 'Please sign in to continue.');
        redirect('auth/login.php');
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        flash('error', 'Admin access only.');
        redirect('index.php');
    }
    return $user;
}

function attempt_admin_login(string $username, string $password): array
{
    $pdo = db();
    $username = trim(strtolower($username));

    if ($username === '' || $password === '') {
        return ['ok' => false, 'error' => 'Username and password required.'];
    }
    if (is_login_locked($pdo, 'admin:' . $username)) {
        return ['ok' => false, 'error' => 'Too many failed attempts. Try again in ' . LOGIN_LOCK_MINUTES . ' minutes.'];
    }

    $stmt = $pdo->prepare(
        "SELECT * FROM users WHERE username = ? AND role = 'admin' LIMIT 1"
    );
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !(int) $user['is_active'] || !password_verify($password, (string) $user['password_hash'])) {
        record_login_attempt($pdo, 'admin:' . $username);
        return ['ok' => false, 'error' => 'Invalid admin credentials.'];
    }

    clear_login_attempts($pdo, 'admin:' . $username);
    login_user($user);
    return ['ok' => true, 'user' => $user];
}

function google_oauth_url(): string
{
    $state = bin2hex(random_bytes(24));
    $_SESSION['google_oauth_state'] = $state;

    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => app_absolute_url('auth/google_callback.php'),
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'access_type' => 'online',
        'prompt' => 'select_account',
        'include_granted_scopes' => 'true',
        'state' => $state,
    ];
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

function google_oauth_state_valid(?string $state): bool
{
    $expected = (string) ($_SESSION['google_oauth_state'] ?? '');
    unset($_SESSION['google_oauth_state']);
    return $expected !== '' && is_string($state) && hash_equals($expected, $state);
}

function curl_ssl_opts(): array
{
    $opts = [
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];
    $cacert = APP_ROOT . '/config/cacert.pem';
    if (is_file($cacert)) {
        $opts[CURLOPT_CAINFO] = $cacert;
    }
    return $opts;
}

function http_post_form(string $url, array $data): array
{
    $body = http_build_query($data);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 20,
        ] + curl_ssl_opts());
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('Network error: ' . $err);
        }
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            throw new RuntimeException('Invalid response from Google (HTTP ' . $code . ').');
        }
        return $json;
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $body,
            'timeout' => 20,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'cafile' => is_file(APP_ROOT . '/config/cacert.pem') ? APP_ROOT . '/config/cacert.pem' : null,
        ],
    ]);
    $raw = file_get_contents($url, false, $ctx);
    if ($raw === false) {
        throw new RuntimeException('Could not reach Google servers.');
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        throw new RuntimeException('Invalid response from Google.');
    }
    return $json;
}

function http_get_json(string $url, string $accessToken): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
            CURLOPT_TIMEOUT => 20,
        ] + curl_ssl_opts());
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('Could not fetch Google profile: ' . $err);
        }
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            throw new RuntimeException('Invalid Google profile response.');
        }
        return $json;
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer {$accessToken}\r\n",
            'timeout' => 20,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'cafile' => is_file(APP_ROOT . '/config/cacert.pem') ? APP_ROOT . '/config/cacert.pem' : null,
        ],
    ]);
    $raw = file_get_contents($url, false, $ctx);
    if ($raw === false) {
        throw new RuntimeException('Could not fetch Google profile.');
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        throw new RuntimeException('Invalid Google profile response.');
    }
    return $json;
}

function login_or_register_google(array $profile): array
{
    $googleId = (string) ($profile['id'] ?? $profile['sub'] ?? '');
    $email = strtolower(trim((string) ($profile['email'] ?? '')));
    $name = trim((string) ($profile['name'] ?? 'Customer'));
    $avatar = (string) ($profile['picture'] ?? '');

    if ($googleId === '' || $email === '') {
        return ['ok' => false, 'error' => 'Google did not return a valid profile.'];
    }

    $pdo = db();

    $stmt = $pdo->prepare('SELECT * FROM users WHERE google_id = ? LIMIT 1');
    $stmt->execute([$googleId]);
    $user = $stmt->fetch();

    if (!$user) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE lower(email) = ? AND role = 'user' LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            $pdo->prepare('UPDATE users SET google_id = ?, avatar = ?, name = ?, updated_at = datetime(\'now\',\'localtime\') WHERE id = ?')
                ->execute([$googleId, $avatar, $name, (int) $user['id']]);
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([(int) $user['id']]);
            $user = $stmt->fetch();
        }
    }

    if (!$user) {
        $pdo->prepare(
            'INSERT INTO users (name, email, google_id, avatar, password_hash, role)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$name, $email, $googleId, $avatar, '', 'user']);
        $id = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        notify_user(
            $id,
            'Welcome to LALA WEARS',
            'Your account is ready. Browse the collection and place your first order.',
            'account/index.php'
        );
    }

    if (!(int) $user['is_active']) {
        return ['ok' => false, 'error' => 'This account is disabled.'];
    }

    login_user($user);
    return ['ok' => true, 'user' => $user];
}

function attempt_user_login(string $emailOrPhone, string $password): array
{
    $pdo = db();
    $identity = trim(strtolower($emailOrPhone));

    if ($identity === '' || $password === '') {
        return ['ok' => false, 'error' => 'Email/phone and password required.'];
    }
    if (is_login_locked($pdo, 'user:' . $identity)) {
        return ['ok' => false, 'error' => 'Too many failed attempts. Try again in ' . LOGIN_LOCK_MINUTES . ' minutes.'];
    }

    $stmt = $pdo->prepare(
        "SELECT * FROM users WHERE (lower(email) = ? OR phone = ?) AND role = 'user' LIMIT 1"
    );
    $stmt->execute([$identity, $emailOrPhone]);
    $user = $stmt->fetch();

    if (!$user || !(int) $user['is_active'] || !password_verify($password, (string) $user['password_hash'])) {
        record_login_attempt($pdo, 'user:' . $identity);
        return ['ok' => false, 'error' => 'Invalid credentials.'];
    }

    clear_login_attempts($pdo, 'user:' . $identity);
    login_user($user);
    return ['ok' => true, 'user' => $user];
}

function register_user(string $name, string $phone, string $email, string $password): array
{
    $pdo = db();
    $name = trim($name);
    $phone = trim($phone);
    $email = trim(strtolower($email));

    if ($name === '' || $phone === '' || $email === '' || $password === '') {
        return ['ok' => false, 'error' => 'All fields are required.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid email address.'];
    }

    // Check if email or phone already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE lower(email) = ? OR phone = ?");
    $stmt->execute([$email, $phone]);
    if ((int) $stmt->fetchColumn() > 0) {
        return ['ok' => false, 'error' => 'An account with this email or phone number already exists.'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $pdo->prepare(
            'INSERT INTO users (name, phone, email, password_hash, role)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$name, $phone, $email, $hash, 'user']);
        
        $id = (int) $pdo->lastInsertId();
        
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        notify_user(
            $id,
            'Welcome to LALA WEARS',
            'Your account is ready. Browse the collection and place your first order.',
            'account/index.php'
        );

        login_user($user);
        return ['ok' => true, 'user' => $user];
    } catch (Exception $e) {
        return ['ok' => false, 'error' => 'Error creating account: ' . $e->getMessage()];
    }
}

function send_password_reset_link(string $email): array
{
    $pdo = db();
    $email = trim(strtolower($email));

    if ($email === '') {
        return ['ok' => false, 'error' => 'Email address is required.'];
    }

    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE lower(email) = ? AND role = 'user' LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // For security, don't leak if the user exists or not, but we can give a success message
        return ['ok' => true, 'message' => 'If your email is registered, you will receive a password reset link shortly.'];
    }

    // Generate a secure random token
    $token = bin2hex(random_bytes(16));
    $expires = date('Y-m-d H:i:s', time() + 15 * 60); // 15 mins expiry

    // Delete any existing tokens for this email
    $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

    // Insert new token
    $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)")
        ->execute([$email, $token, $expires]);

    // Send email using Resend
    $resetLink = app_absolute_url('auth/reset_password.php?token=' . $token);
    $subject = 'Reset Your Password - LALA WEARS';
    $html = '<div style="font-family:sans-serif; max-width:600px; margin:0 auto; padding:20px; border:1px solid #f0f0f0; border-radius:8px;">'
          . '<h2 style="color:#262626; text-transform:uppercase; letter-spacing:0.05em;">Reset Your Password</h2>'
          . '<p style="color:#4a4a4a; font-size:15px; line-height:1.5;">You requested a password reset for your LALA WEARS account. Click the button below to set a new password. This link will expire in 15 minutes.</p>'
          . '<div style="margin:28px 0;"><a href="' . htmlspecialchars($resetLink) . '" style="background:#e4a4bd; color:#262626; text-decoration:none; padding:12px 28px; font-weight:bold; border-radius:999px; font-size:14px; letter-spacing:0.05em; display:inline-block;">RESET PASSWORD</a></div>'
          . '<p style="color:#8a8a8a; font-size:12px; margin-top:30px;">If you did not request this, you can ignore this email. Your password will remain unchanged.</p>'
          . '</div>';

    $sent = resend_send_email($email, $subject, $html);
    if (!$sent['ok']) {
        return ['ok' => false, 'error' => $sent['error']];
    }

    return ['ok' => true, 'message' => 'If your email is registered, you will receive a password reset link shortly.'];
}

function reset_user_password(string $token, string $password): array
{
    $pdo = db();
    $token = trim($token);
    $password = trim($password);

    if ($token === '') {
        return ['ok' => false, 'error' => 'Invalid or expired token.'];
    }

    if ($password === '' || strlen($password) < 6) {
        return ['ok' => false, 'error' => 'Password must be at least 6 characters.'];
    }

    // Verify token
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? LIMIT 1");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    if (!$reset) {
        return ['ok' => false, 'error' => 'Invalid or expired token.'];
    }

    // Check expiry
    if (strtotime($reset['expires_at']) < time()) {
        $pdo->prepare("DELETE FROM password_resets WHERE token = ?")->execute([$token]);
        return ['ok' => false, 'error' => 'This reset link has expired. Please request a new one.'];
    }

    // Update password
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password_hash = ? WHERE lower(email) = ? AND role = 'user'")
        ->execute([$hash, strtolower($reset['email'])]);

    // Clean up token
    $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([strtolower($reset['email'])]);

    return ['ok' => true, 'message' => 'Your password has been reset successfully. You can now log in.'];
}


