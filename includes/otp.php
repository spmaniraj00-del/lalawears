<?php
declare(strict_types=1);

function mask_phone(string $phone): string
{
    $phone = normalize_phone($phone);
    if (strlen($phone) < 4) {
        return '••••';
    }
    return '•••• ' . substr($phone, -4);
}

function mask_email(string $email): string
{
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        return '••••@••••';
    }
    $name = $parts[0];
    $shown = strlen($name) <= 2 ? str_repeat('•', strlen($name)) : substr($name, 0, 1) . str_repeat('•', max(2, strlen($name) - 2)) . substr($name, -1);
    return $shown . '@' . $parts[1];
}

function generate_otp_code(): string
{
    return str_pad((string) random_int(0, 999999), OTP_LENGTH, '0', STR_PAD_LEFT);
}

function clear_otps_for_phone(string $phone): void
{
    db()->prepare('DELETE FROM otp_codes WHERE phone = ?')->execute([normalize_phone($phone)]);
}

/**
 * Start OTP login/register: phone is identity, OTP delivered by email (Resend).
 */
function request_phone_otp(string $phone, string $email, string $name = ''): array
{
    $pdo = db();
    $phone = normalize_phone($phone);
    $email = strtolower(trim($email));
    $name = trim($name);

    if (!is_valid_indian_phone($phone)) {
        return ['ok' => false, 'error' => 'Enter a valid 10-digit Indian mobile number.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Enter a valid email to receive the OTP.'];
    }
    if (is_login_locked($pdo, 'otp:' . $phone)) {
        return ['ok' => false, 'error' => 'Too many attempts. Try again in ' . LOGIN_LOCK_MINUTES . ' minutes.'];
    }

    // Existing user by phone — use stored email if present
    $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ? AND role = 'user' LIMIT 1");
    $stmt->execute([$phone]);
    $existing = $stmt->fetch();
    if ($existing) {
        if (!empty($existing['email'])) {
            $email = strtolower((string) $existing['email']);
        }
        if ($name === '' && !empty($existing['name'])) {
            $name = (string) $existing['name'];
        }
    } elseif ($name === '' || strlen($name) < 2) {
        return ['ok' => false, 'error' => 'New users: please enter your full name.'];
    }

    // Resend cooldown
    $recent = $pdo->prepare(
        "SELECT created_at FROM otp_codes WHERE phone = ? ORDER BY id DESC LIMIT 1"
    );
    $recent->execute([$phone]);
    $last = $recent->fetchColumn();
    if ($last) {
        $elapsed = time() - strtotime((string) $last);
        if ($elapsed < OTP_RESEND_SECONDS) {
            $wait = OTP_RESEND_SECONDS - $elapsed;
            return ['ok' => false, 'error' => "Please wait {$wait}s before requesting another code.", 'wait' => $wait];
        }
    }

    $code = generate_otp_code();
    $hash = password_hash($code, PASSWORD_DEFAULT);
    $expires = date('Y-m-d H:i:s', time() + OTP_EXPIRY_MINUTES * 60);

    clear_otps_for_phone($phone);
    $pdo->prepare(
        'INSERT INTO otp_codes (phone, email, name, code_hash, expires_at) VALUES (?, ?, ?, ?, ?)'
    )->execute([$phone, $email, $name, $hash, $expires]);

    $html = '<div style="font-family:Inter,Arial,sans-serif;max-width:480px;margin:0 auto;padding:24px;background:#f6f5f2;color:#1c1c1e">'
        . '<p style="font-size:12px;letter-spacing:0.16em;text-transform:uppercase;color:#3a3a3c">LALA WEARS</p>'
        . '<h1 style="font-size:22px;margin:12px 0">Your verification code</h1>'
        . '<p style="font-size:14px;color:#2c2c2e">Use this 6-digit code to sign in. It expires in ' . OTP_EXPIRY_MINUTES . ' minutes.</p>'
        . '<p style="font-size:32px;font-weight:800;letter-spacing:0.35em;margin:24px 0">' . htmlspecialchars($code) . '</p>'
        . '<p style="font-size:12px;color:#9a9aa0">If you did not request this, you can ignore this email.</p>'
        . '</div>';

    $sent = ['ok' => false, 'error' => ''];
    if (mailer_configured()) {
        $sent = send_app_email(
            $email,
            'Your LALA WEARS code: ' . $code,
            $html
        );
    }

    // Always keep session so user can verify. If email fails but on-site OTP is on, continue.
    if (!$sent['ok'] && !OTP_SHOW_ON_SITE) {
        clear_otps_for_phone($phone);
        return ['ok' => false, 'error' => $sent['error'] !== '' ? $sent['error'] : 'Could not send OTP.'];
    }

    $_SESSION['otp_phone'] = $phone;
    $_SESSION['otp_email'] = $email;
    $_SESSION['otp_name'] = $name;
    $_SESSION['otp_sent_at'] = time();
    if (OTP_SHOW_ON_SITE) {
        $_SESSION['otp_display_code'] = $code;
    } else {
        unset($_SESSION['otp_display_code']);
    }

    return [
        'ok' => true,
        'phone' => $phone,
        'email' => $email,
        'masked_phone' => mask_phone($phone),
        'masked_email' => mask_email($email),
        'email_sent' => !empty($sent['ok']),
        'show_on_site' => OTP_SHOW_ON_SITE,
    ];
}

function verify_phone_otp(string $phone, string $code): array
{
    $pdo = db();
    $phone = normalize_phone($phone);
    $code = preg_replace('/\D+/', '', $code) ?? '';

    if (!is_valid_indian_phone($phone)) {
        return ['ok' => false, 'error' => 'Invalid phone number.'];
    }
    if (strlen($code) !== OTP_LENGTH) {
        return ['ok' => false, 'error' => 'Enter the full 6-digit code.'];
    }
    if (is_login_locked($pdo, 'otp:' . $phone)) {
        return ['ok' => false, 'error' => 'Too many failed attempts. Try again later.'];
    }

    $stmt = $pdo->prepare(
        "SELECT * FROM otp_codes WHERE phone = ? ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$phone]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['ok' => false, 'error' => 'No active code. Please request a new one.'];
    }
    if (strtotime((string) $row['expires_at']) < time()) {
        clear_otps_for_phone($phone);
        return ['ok' => false, 'error' => 'Code expired. Please resend.'];
    }
    if ((int) $row['attempts'] >= 5) {
        record_login_attempt($pdo, 'otp:' . $phone);
        clear_otps_for_phone($phone);
        return ['ok' => false, 'error' => 'Too many wrong attempts. Request a new code.'];
    }

    if (!password_verify($code, (string) $row['code_hash'])) {
        $pdo->prepare('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?')
            ->execute([(int) $row['id']]);
        record_login_attempt($pdo, 'otp:' . $phone);
        return ['ok' => false, 'error' => 'Incorrect code. Try again.'];
    }

    clear_otps_for_phone($phone);
    clear_login_attempts($pdo, 'otp:' . $phone);

    $email = strtolower((string) $row['email']);
    $name = trim((string) $row['name']);
    if ($name === '') {
        $name = 'Customer';
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ? AND role = 'user' LIMIT 1");
    $stmt->execute([$phone]);
    $user = $stmt->fetch();

    if ($user) {
        $pdo->prepare(
            "UPDATE users SET email = CASE WHEN email = '' OR email IS NULL THEN ? ELSE email END,
             name = CASE WHEN name = '' THEN ? ELSE name END,
             updated_at = datetime('now','localtime') WHERE id = ?"
        )->execute([$email, $name, (int) $user['id']]);
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([(int) $user['id']]);
        $user = $stmt->fetch();
    } else {
        $pdo->prepare(
            "INSERT INTO users (name, phone, email, password_hash, role) VALUES (?, ?, ?, '', 'user')"
        )->execute([$name, $phone, $email]);
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

    unset($_SESSION['otp_phone'], $_SESSION['otp_email'], $_SESSION['otp_name'], $_SESSION['otp_sent_at'], $_SESSION['otp_display_code']);
    login_user($user);

    return ['ok' => true, 'user' => $user];
}
