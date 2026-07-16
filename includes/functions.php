<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    if (!preg_match('#^https?://#i', $path)) {
        $path = url(ltrim($path, '/'));
    }
    header('Location: ' . $path);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $flashes;
}

function csrf_token(): string
{
    if (empty($_SESSION[CSRF_TOKEN_KEY])) {
        $_SESSION[CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_KEY];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool
{
    $session = $_SESSION[CSRF_TOKEN_KEY] ?? '';
    return is_string($token) && $session !== '' && hash_equals($session, $token);
}

function require_post_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($token)) {
        http_response_code(403);
        exit('Invalid security token. Please go back and try again.');
    }
}

function money_inr(float|int|string $amount): string
{
    return '₹' . number_format((float) $amount, 0, '.', ',');
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'item-' . bin2hex(random_bytes(3));
}

function normalize_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (str_starts_with($digits, '91') && strlen($digits) === 12) {
        $digits = substr($digits, 2);
    }
    if (str_starts_with($digits, '0') && strlen($digits) === 11) {
        $digits = substr($digits, 1);
    }
    return $digits;
}

function is_valid_indian_phone(string $phone): bool
{
    return (bool) preg_match('/^[6-9]\d{9}$/', normalize_phone($phone));
}

function product_image_url(string $image): string
{
    if ($image === '') {
        return asset('images/ss.png');
    }
    if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
        return $image;
    }
    // seeded paths like images/ss.png or uploads/xyz.jpg
    return asset(ltrim($image, '/'));
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function record_login_attempt(PDO $pdo, string $identifier): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO login_attempts (identifier, ip_address) VALUES (?, ?)'
    );
    $stmt->execute([strtolower($identifier), client_ip()]);
}

function clear_login_attempts(PDO $pdo, string $identifier): void
{
    $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE identifier = ?');
    $stmt->execute([strtolower($identifier)]);
}

function is_login_locked(PDO $pdo, string $identifier): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM login_attempts
         WHERE identifier = ?
           AND attempted_at > datetime('now', 'localtime', ?) "
    );
    $stmt->execute([
        strtolower($identifier),
        '-' . LOGIN_LOCK_MINUTES . ' minutes',
    ]);
    return (int) $stmt->fetchColumn() >= LOGIN_MAX_ATTEMPTS;
}

function safe_upload_image(array $file, string $prefix = 'product'): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed.');
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Image must be under 5MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, WEBP or GIF images allowed.');
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $name = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $dest = UPLOAD_DIR . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save uploaded image.');
    }

    return 'uploads/' . $name;
}

function set_security_headers(): void
{
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header(
        "Content-Security-Policy: default-src 'self'; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://www.gstatic.com; "
        . "font-src 'self' https://fonts.gstatic.com data:; "
        . "img-src 'self' data: blob: https://lh3.googleusercontent.com https://*.googleusercontent.com https://www.gstatic.com https://www.google.com; "
        . "script-src 'self' 'unsafe-inline' https://www.google.com https://www.gstatic.com; "
        . "connect-src 'self' https://api.resend.com https://www.google.com https://accounts.google.com https://oauth2.googleapis.com; "
        . "frame-src https://www.google.com https://accounts.google.com; "
        . "form-action 'self' https://accounts.google.com; "
        . "frame-ancestors 'self';"
    );
    // Tell browsers to prefer HTTPS after the first secure visit
    if (function_exists('request_is_https') && request_is_https() && !is_local_request_host()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}

function notify_user(int $userId, string $title, string $message, string $link = ''): void
{
    $stmt = db()->prepare(
        'INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $title, $message, $link]);
}

function notify_admins(string $title, string $message, string $link = ''): void
{
    $admins = db()->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1")->fetchAll();
    foreach ($admins as $admin) {
        notify_user((int) $admin['id'], $title, $message, $link);
    }
}

function unread_notification_count(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function user_notifications(int $userId, int $limit = 20): array
{
    $stmt = db()->prepare(
        'SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ?'
    );
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function mark_notification_read(int $userId, int $notificationId): void
{
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')
        ->execute([$notificationId, $userId]);
}

function mark_all_notifications_read(int $userId): void
{
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')
        ->execute([$userId]);
}

/* ——— Site settings (DB backed, admin editable) ——— */

function all_settings(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT key, value FROM settings') as $row) {
            $cache[$row['key']] = $row['value'];
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $value = all_settings()[$key] ?? '';
    return $value !== '' ? $value : $default;
}

function set_setting(string $key, string $value): void
{
    db()->prepare(
        'INSERT INTO settings (key, value) VALUES (?, ?)
         ON CONFLICT(key) DO UPDATE SET value = excluded.value'
    )->execute([$key, $value]);
}

function site_phone(): string
{
    return setting('contact_phone', CONTACT_PHONE);
}

function site_email(): string
{
    return setting('contact_email', CONTACT_EMAIL);
}

function site_whatsapp(): string
{
    return setting('whatsapp_url', WHATSAPP_URL);
}

function site_instagram(): string
{
    return setting('instagram_url', INSTAGRAM_URL);
}

function site_facebook(): string
{
    return setting('facebook_url', '');
}

function site_youtube(): string
{
    return setting('youtube_url', '');
}

function site_logo_url(): string
{
    $logo = setting('site_logo', '');
    return $logo !== '' ? asset($logo) : asset('images/log.png');
}

function site_founder_photo_url(): string
{
    $photo = setting('founder_photo', '');
    return $photo !== '' ? asset($photo) : asset('images/log.png');
}

function site_founder_has_photo(): bool
{
    return setting('founder_photo', '') !== '';
}

/* ——— Support chat ——— */

function support_thread_key(?array $user, string $email): string
{
    if ($user) {
        return 'u:' . (int) $user['id'];
    }
    return 'g:' . strtolower(trim($email));
}

function add_support_message(
    string $threadKey,
    ?int $userId,
    string $name,
    string $email,
    string $phone,
    string $message,
    string $sender = 'customer'
): void {
    db()->prepare(
        'INSERT INTO support_messages (thread_key, user_id, name, email, phone, message, sender)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([$threadKey, $userId, $name, $email, $phone, $message, $sender]);
}

function unread_support_count(): int
{
    return (int) db()->query(
        "SELECT COUNT(*) FROM support_messages WHERE sender = 'customer' AND is_read = 0"
    )->fetchColumn();
}

/* ——— Cart (session based) ——— */

function cart_items(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_count(): int
{
    $total = 0;
    foreach (cart_items() as $qty) {
        $total += (int) $qty;
    }
    return $total;
}

function cart_add(int $productId, int $qty = 1): void
{
    $cart = cart_items();
    $cart[$productId] = min(10, ($cart[$productId] ?? 0) + max(1, $qty));
    $_SESSION['cart'] = $cart;
}

function cart_set(int $productId, int $qty): void
{
    $cart = cart_items();
    if ($qty <= 0) {
        unset($cart[$productId]);
    } else {
        $cart[$productId] = min(10, $qty);
    }
    $_SESSION['cart'] = $cart;
}

function cart_remove(int $productId): void
{
    cart_set($productId, 0);
}

/* ——— Product pricing / reviews ——— */

function product_mrp(float $price): float
{
    // Display MRP so the selling price shows as a flat 20% discount
    return ceil($price / 0.8);
}

function product_discount_percent(float $price): int
{
    $mrp = product_mrp($price);
    return $mrp > 0 ? (int) round((1 - $price / $mrp) * 100) : 0;
}

function product_rating_summary(int $productId): array
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) AS review_count, COALESCE(AVG(rating), 0) AS avg_rating
         FROM reviews WHERE product_id = ?'
    );
    $stmt->execute([$productId]);
    $row = $stmt->fetch() ?: ['review_count' => 0, 'avg_rating' => 0];
    return [
        'count' => (int) $row['review_count'],
        'avg' => round((float) $row['avg_rating'], 1),
    ];
}

function product_sold_count(int $productId): int
{
    $stmt = db()->prepare(
        "SELECT COALESCE(SUM(quantity), 0) FROM orders
         WHERE product_id = ? AND status != 'cancelled'"
    );
    $stmt->execute([$productId]);
    return (int) $stmt->fetchColumn();
}

function product_reviews(int $productId, int $limit = 30): array
{
    $stmt = db()->prepare(
        'SELECT r.*, u.name AS user_name, u.avatar AS user_avatar
         FROM reviews r
         JOIN users u ON u.id = r.user_id
         WHERE r.product_id = ?
         ORDER BY r.id DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $productId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function order_code(int $orderId): string
{
    return 'LW-' . str_pad((string) $orderId, 6, '0', STR_PAD_LEFT);
}

function parse_order_code(string $input): int
{
    // Accepts "LW-000012", "lw12", "#12", "12" etc.
    $digits = preg_replace('/\D+/', '', $input) ?? '';
    return $digits === '' ? 0 : (int) ltrim($digits, '0');
}

function order_status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        default => ucfirst($status),
    };
}

function add_order_tracking(int $orderId, string $status, string $note = '', string $location = '', ?int $adminId = null): void
{
    db()->prepare(
        'INSERT INTO order_tracking (order_id, status, note, location, created_by) VALUES (?, ?, ?, ?, ?)'
    )->execute([$orderId, $status, $note, $location, $adminId]);
}

function get_order_tracking(int $orderId): array
{
    $stmt = db()->prepare(
        'SELECT t.*, u.name AS admin_name
         FROM order_tracking t
         LEFT JOIN users u ON u.id = t.created_by
         WHERE t.order_id = ?
         ORDER BY t.id ASC'
    );
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}
