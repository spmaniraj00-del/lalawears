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

/** All gallery paths for a product (primary + extras), max 4. */
function product_gallery_paths(array $product): array
{
    $paths = [];
    $primary = trim((string) ($product['image'] ?? ''));
    if ($primary !== '') {
        $paths[] = $primary;
    }
    $extra = trim((string) ($product['images'] ?? ''));
    if ($extra !== '') {
        $decoded = json_decode($extra, true);
        if (is_array($decoded)) {
            foreach ($decoded as $p) {
                $p = trim((string) $p);
                if ($p !== '' && !in_array($p, $paths, true)) {
                    $paths[] = $p;
                }
            }
        }
    }
    if (!$paths) {
        $paths[] = 'images/ss.png';
    }
    return array_slice($paths, 0, 4);
}

function ensure_upload_dir(): void
{
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }
    if (!is_writable(UPLOAD_DIR)) {
        @chmod(UPLOAD_DIR, 0775);
    }
    if (!is_writable(UPLOAD_DIR)) {
        throw new RuntimeException('Upload folder is not writable. Fix permissions on assets/uploads.');
    }
}

function client_ip(): string
{
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function log_visitor_activity(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    
    // Skip static asset loads or admin ajax status syncs if necessary, but tracking all frontend hits
    $url = $_SERVER['REQUEST_URI'] ?? '/';
    if (preg_match('/\.(js|css|png|jpg|jpeg|gif|webp|svg|ico)$/i', $url)) {
        return;
    }

    try {
        $pdo = db();
        $ip = client_ip();
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $sessId = session_id();
        
        $userId = null;
        if (isset($_SESSION['user_id'])) {
            $userId = (int) $_SESSION['user_id'];
        }
        
        $stmt = $pdo->prepare(
            'INSERT INTO visitor_activity (ip_address, user_agent, page_url, user_id, session_id)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$ip, $ua, $url, $userId, $sessId]);
    } catch (Throwable $e) {
        // Fail silently so a database glitch doesn't take down the website
    }
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
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $minutes = (int) LOGIN_LOCK_MINUTES;

    if ($driver === 'pgsql') {
        $sql = "SELECT COUNT(*) FROM login_attempts 
                WHERE identifier = ? 
                  AND attempted_at > timezone('Asia/Kolkata', now()) - interval '{$minutes} minutes'";
    } elseif ($driver === 'mysql') {
        $sql = "SELECT COUNT(*) FROM login_attempts 
                WHERE identifier = ? 
                  AND attempted_at > DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE)";
    } else { // sqlite
        $sql = "SELECT COUNT(*) FROM login_attempts
                WHERE identifier = ?
                  AND attempted_at > datetime('now', 'localtime', '-{$minutes} minutes')";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([strtolower($identifier)]);
    return (int) $stmt->fetchColumn() >= LOGIN_MAX_ATTEMPTS;
}

function safe_upload_image(array $file, string $prefix = 'product'): ?string
{
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE || trim((string) ($file['name'] ?? '')) === '') {
        return null;
    }
    $errMessages = [
        UPLOAD_ERR_INI_SIZE => 'Image is too large for the server limit.',
        UPLOAD_ERR_FORM_SIZE => 'Image is too large.',
        UPLOAD_ERR_PARTIAL => 'Image upload was incomplete. Try again.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server temp folder missing.',
        UPLOAD_ERR_CANT_WRITE => 'Server could not write the image.',
        UPLOAD_ERR_EXTENSION => 'Upload blocked by server extension.',
    ];
    if ($err !== UPLOAD_ERR_OK) {
        throw new RuntimeException($errMessages[$err] ?? 'Image upload failed.');
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Image must be under 5MB.');
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid uploaded file.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, WEBP or GIF images allowed.');
    }

    ensure_upload_dir();

    $name = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $dest = UPLOAD_DIR . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Could not save uploaded image.');
    }

    return 'uploads/' . $name;
}

/** Normalize $_FILES['photos'] / photos[] into a list of single-file arrays. */
function uploaded_files_list(string $field): array
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field])) {
        return [];
    }
    $bag = $_FILES[$field];
    if (!isset($bag['name']) || !is_array($bag['name'])) {
        return [$bag];
    }
    $out = [];
    foreach ($bag['name'] as $i => $name) {
        $out[] = [
            'name' => $name,
            'type' => $bag['type'][$i] ?? '',
            'tmp_name' => $bag['tmp_name'][$i] ?? '',
            'error' => $bag['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $bag['size'][$i] ?? 0,
        ];
    }
    return $out;
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
        . "img-src 'self' data: blob: https://api.qrserver.com https://lh3.googleusercontent.com https://*.googleusercontent.com https://www.gstatic.com https://www.google.com; "
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
        foreach (db()->query('SELECT key_name, val_value FROM settings') as $row) {
            $cache[$row['key_name']] = $row['val_value'];
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
    $pdo = db();
    $pdo->prepare('DELETE FROM settings WHERE key_name = ?')->execute([$key]);
    $pdo->prepare('INSERT INTO settings (key_name, val_value) VALUES (?, ?)')
        ->execute([$key, $value]);
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

/** Safe display URL for a user avatar (Google photo or local). */
function user_avatar_url(?array $user): string
{
    if (!$user) {
        return '';
    }
    $avatar = trim((string) ($user['avatar'] ?? ''));
    if ($avatar === '') {
        return '';
    }
    if (str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://')) {
        return $avatar;
    }
    return asset(ltrim($avatar, '/'));
}

function user_avatar_initial(array $user): string
{
    $name = trim((string) ($user['name'] ?? '?'));
    return strtoupper(mb_substr($name !== '' ? $name : '?', 0, 1));
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
        "SELECT r.*, u.name AS user_name, u.avatar AS user_avatar,
                EXISTS(
                    SELECT 1 FROM orders o
                    WHERE o.user_id=r.user_id
                      AND o.product_id=r.product_id
                      AND o.status!='cancelled'
                ) AS verified_purchase
         FROM reviews r
         JOIN users u ON u.id = r.user_id
         WHERE r.product_id = ?
         ORDER BY r.id DESC
         LIMIT ?"
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

/* ——— Wishlist ——— */

function wishlist_guest_ids(): array
{
    $ids = $_SESSION['wishlist'] ?? [];
    if (!is_array($ids)) {
        return [];
    }
    return array_values(array_unique(array_map('intval', $ids)));
}

function wishlist_set_guest(array $ids): void
{
    $_SESSION['wishlist'] = array_values(array_unique(array_map('intval', $ids)));
}

function wishlist_has(int $productId, ?array $user = null): bool
{
    $user = $user ?? current_user();
    if ($user && ($user['role'] ?? '') === 'user') {
        $stmt = db()->prepare('SELECT 1 FROM wishlists WHERE user_id = ? AND product_id = ? LIMIT 1');
        $stmt->execute([(int) $user['id'], $productId]);
        return (bool) $stmt->fetchColumn();
    }
    return in_array($productId, wishlist_guest_ids(), true);
}

function wishlist_toggle(int $productId, ?array $user = null): bool
{
    $user = $user ?? current_user();
    if ($productId <= 0) {
        return false;
    }
    $check = db()->prepare('SELECT id FROM products WHERE id = ? AND is_active = 1 LIMIT 1');
    $check->execute([$productId]);
    if (!$check->fetch()) {
        return false;
    }

    if ($user && ($user['role'] ?? '') === 'user') {
        $uid = (int) $user['id'];
        if (wishlist_has($productId, $user)) {
            db()->prepare('DELETE FROM wishlists WHERE user_id = ? AND product_id = ?')
                ->execute([$uid, $productId]);
            return false;
        }
        db()->prepare('INSERT OR IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)')
            ->execute([$uid, $productId]);
        return true;
    }

    $ids = wishlist_guest_ids();
    if (in_array($productId, $ids, true)) {
        $ids = array_values(array_filter($ids, static fn ($id) => $id !== $productId));
        wishlist_set_guest($ids);
        return false;
    }
    $ids[] = $productId;
    wishlist_set_guest($ids);
    return true;
}

function wishlist_count(?array $user = null): int
{
    $user = $user ?? current_user();
    if ($user && ($user['role'] ?? '') === 'user') {
        $stmt = db()->prepare('SELECT COUNT(*) FROM wishlists WHERE user_id = ?');
        $stmt->execute([(int) $user['id']]);
        return (int) $stmt->fetchColumn();
    }
    return count(wishlist_guest_ids());
}

function wishlist_products(?array $user = null): array
{
    $user = $user ?? current_user();
    if ($user && ($user['role'] ?? '') === 'user') {
        $stmt = db()->prepare(
            'SELECT p.* FROM wishlists w
             JOIN products p ON p.id = w.product_id
             WHERE w.user_id = ? AND p.is_active = 1
             ORDER BY w.id DESC'
        );
        $stmt->execute([(int) $user['id']]);
        return $stmt->fetchAll();
    }
    $ids = wishlist_guest_ids();
    if (!$ids) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        "SELECT * FROM products WHERE is_active = 1 AND id IN ($placeholders)"
    );
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();
    // Keep guest order
    $byId = [];
    foreach ($rows as $row) {
        $byId[(int) $row['id']] = $row;
    }
    $ordered = [];
    foreach (array_reverse($ids) as $id) {
        if (isset($byId[$id])) {
            $ordered[] = $byId[$id];
        }
    }
    return $ordered;
}

/** Merge guest wishlist into user account after login. */
function wishlist_merge_guest_into_user(int $userId): void
{
    foreach (wishlist_guest_ids() as $pid) {
        if ($pid > 0) {
            db()->prepare('INSERT OR IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)')
                ->execute([$userId, $pid]);
        }
    }
    unset($_SESSION['wishlist']);
}
