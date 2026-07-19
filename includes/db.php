<?php
declare(strict_types=1);

require_once APP_ROOT . '/includes/functions.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $dbType = strtolower(DB_TYPE);
        $dbUrl = DB_URL;

        if ($dbUrl !== '') {
            $parsed = parse_url($dbUrl);
            if (isset($parsed['scheme'])) {
                if ($parsed['scheme'] === 'postgres' || $parsed['scheme'] === 'postgresql') {
                    $dbType = 'pgsql';
                } elseif ($parsed['scheme'] === 'mysql') {
                    $dbType = 'mysql';
                }
                
                $host = $parsed['host'] ?? '';
                $port = $parsed['port'] ?? '';
                $user = $parsed['user'] ?? '';
                $pass = $parsed['pass'] ?? '';
                $path = ltrim($parsed['path'] ?? '', '/');
            }
        }

        if ($dbType === 'mysql' || $dbType === 'pgsql') {
            if ($dbUrl !== '' && isset($host)) {
                $dsn = "{$dbType}:host={$host};dbname={$path}";
                if ($port !== '') {
                    $dsn .= ";port={$port}";
                }
                $username = $user;
                $password = $pass;
            } else {
                $dsn = "{$dbType}:host=" . DB_HOST . ";dbname=" . DB_DATABASE;
                if (DB_PORT !== '') {
                    $dsn .= ";port=" . DB_PORT;
                }
                $username = DB_USERNAME;
                $password = DB_PASSWORD;
            }

            // Connection with timeout for fast fallback / resilience
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            
            $needsSeed = false; // Remote DBs usually managed manually, but we can verify tables
            $tableCheck = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'users' LIMIT 1");
            if (!$tableCheck || $tableCheck->fetchColumn() === false) {
                $needsSeed = true;
            }
        } else {
            // SQLite Fallback
            $dir = dirname(DB_PATH);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }

            $needsSeed = !file_exists(DB_PATH);
            $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON');
        }

        init_schema($pdo);
        migrate_schema($pdo);
        if ($needsSeed || !admin_exists($pdo)) {
            seed_database($pdo);
        }
    } catch (PDOException $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width, initial-scale=1.0">
          <title>Database Connection Error | LALA WEARS</title>
          <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700;900&display=swap" rel="stylesheet">
          <style>
            body { font-family: "League Spartan", sans-serif; background: #fdf8f3; color: #262626; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
            .card { background: #fff; padding: 40px; border-radius: 24px; box-shadow: 0 10px 40px rgba(38,38,38,0.06); max-width: 500px; text-align: center; border: 1px solid rgba(38,38,38,0.05); }
            h1 { font-size: 2.2rem; font-weight: 900; text-transform: uppercase; margin: 0 0 16px; color: #e4a4bd; }
            p { font-size: 1.1rem; line-height: 1.5; color: #595959; margin: 0 0 24px; }
            .steps { text-align: left; background: #f5f0eb; padding: 20px; border-radius: 16px; font-size: 0.95rem; font-weight: 600; color: #262626; line-height: 1.6; }
            .code { font-family: monospace; background: #fff; padding: 2px 6px; border-radius: 4px; border: 1px solid rgba(0,0,0,0.08); }
          </style>
        </head>
        <body>
          <div class="card">
            <h1>Database Connection Error</h1>
            <p>' . htmlspecialchars($e->getMessage()) . '</p>
            <div class="steps">
              <strong>Check your DB variables:</strong>
              <p>Verify that your <span class="code">DB_TYPE</span>, <span class="code">DB_HOST</span>, <span class="code">DB_DATABASE</span>, or <span class="code">DATABASE_URL</span> environment variables are set correctly on your hosting platform (Railway, etc.).</p>
            </div>
          </div>
        </body>
        </html>';
        exit;
    }

    return $pdo;
}

function table_columns(PDO $pdo, string $table): array
{
    $cols = [];
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        foreach ($pdo->query("PRAGMA table_info({$table})") as $row) {
            $cols[$row['name']] = true;
        }
    } elseif ($driver === 'mysql') {
        $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?");
        $stmt->execute([$table]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $col) {
            $cols[$col] = true;
        }
    } elseif ($driver === 'pgsql') {
        $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ?");
        $stmt->execute([$table]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $col) {
            $cols[$col] = true;
        }
    }
    return $cols;
}

function init_schema(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $ai = 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $text = 'TEXT';
    $real = 'REAL';
    $now = "datetime('now','localtime')";

    if ($driver === 'mysql') {
        $ai = 'INT AUTO_INCREMENT PRIMARY KEY';
        $text = 'VARCHAR(255)';
        $real = 'DECIMAL(10,2)';
        $now = 'NOW()';
    } elseif ($driver === 'pgsql') {
        $ai = 'SERIAL PRIMARY KEY';
        $text = 'VARCHAR(255)';
        $real = 'DECIMAL(10,2)';
        $now = "timezone('Asia/Kolkata', now())";
    }

    $longText = ($driver === 'sqlite') ? 'TEXT' : 'TEXT';

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id {$ai},
            name {$text} NOT NULL,
            phone {$text} DEFAULT '',
            email {$text} DEFAULT '',
            password_hash {$text} DEFAULT '',
            google_id {$text} UNIQUE,
            avatar {$text} DEFAULT '',
            role {$text} NOT NULL DEFAULT 'user',
            username {$text} UNIQUE,
            is_active INT NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT {$now},
            updated_at TIMESTAMP NOT NULL DEFAULT {$now}
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id {$ai},
            name {$text} NOT NULL,
            slug {$text} NOT NULL UNIQUE,
            description {$longText} NOT NULL,
            price {$real} NOT NULL DEFAULT 0.00,
            image {$text} NOT NULL DEFAULT '',
            stock INT NOT NULL DEFAULT 50,
            is_active INT NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT {$now},
            updated_at TIMESTAMP NOT NULL DEFAULT {$now}
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id {$ai},
            user_id INT NOT NULL,
            product_id INT,
            product_name {$text} NOT NULL,
            product_image {$text} DEFAULT '',
            product_description {$longText} DEFAULT '',
            price {$real} NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            size {$text} DEFAULT 'M',
            customer_name {$text} DEFAULT '',
            customer_phone {$text} DEFAULT '',
            shipping_address {$longText} DEFAULT '',
            city {$text} DEFAULT '',
            state {$text} DEFAULT '',
            pincode {$text} DEFAULT '',
            landmark {$text} DEFAULT '',
            status {$text} NOT NULL DEFAULT 'pending',
            notes {$longText} DEFAULT '',
            created_at TIMESTAMP NOT NULL DEFAULT {$now},
            updated_at TIMESTAMP NOT NULL DEFAULT {$now}
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id {$ai},
            user_id INT NOT NULL,
            title {$text} NOT NULL,
            message {$longText} NOT NULL,
            link {$text} DEFAULT '',
            is_read INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT {$now}
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id {$ai},
            identifier {$text} NOT NULL,
            ip_address {$text} NOT NULL,
            attempted_at TIMESTAMP NOT NULL DEFAULT {$now}
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            `key` {$text} PRIMARY KEY,
            `value` {$longText} NOT NULL
        );
    ");
}

function migrate_schema(PDO $pdo): void
{
    $users = table_columns($pdo, 'users');
    if (!isset($users['google_id'])) {
        $pdo->exec('ALTER TABLE users ADD COLUMN google_id TEXT');
    }
    if (!isset($users['avatar'])) {
        $pdo->exec("ALTER TABLE users ADD COLUMN avatar TEXT DEFAULT ''");
    }

    $orders = table_columns($pdo, 'orders');
    $orderExtras = [
        'product_image' => "ALTER TABLE orders ADD COLUMN product_image TEXT DEFAULT ''",
        'product_description' => "ALTER TABLE orders ADD COLUMN product_description TEXT DEFAULT ''",
        'customer_phone' => "ALTER TABLE orders ADD COLUMN customer_phone TEXT DEFAULT ''",
        'shipping_address' => "ALTER TABLE orders ADD COLUMN shipping_address TEXT DEFAULT ''",
        'customer_name' => "ALTER TABLE orders ADD COLUMN customer_name TEXT DEFAULT ''",
        'city' => "ALTER TABLE orders ADD COLUMN city TEXT DEFAULT ''",
        'state' => "ALTER TABLE orders ADD COLUMN state TEXT DEFAULT ''",
        'pincode' => "ALTER TABLE orders ADD COLUMN pincode TEXT DEFAULT ''",
        'landmark' => "ALTER TABLE orders ADD COLUMN landmark TEXT DEFAULT ''",
        'courier_name' => "ALTER TABLE orders ADD COLUMN courier_name TEXT DEFAULT ''",
        'tracking_number' => "ALTER TABLE orders ADD COLUMN tracking_number TEXT DEFAULT ''",
        'tracking_note' => "ALTER TABLE orders ADD COLUMN tracking_note TEXT DEFAULT ''",
        'payment_method' => "ALTER TABLE orders ADD COLUMN payment_method TEXT DEFAULT 'cod'",
        'payment_status' => "ALTER TABLE orders ADD COLUMN payment_status TEXT DEFAULT 'pending'",
        'transaction_id' => "ALTER TABLE orders ADD COLUMN transaction_id TEXT DEFAULT ''",
        'payment_url' => "ALTER TABLE orders ADD COLUMN payment_url TEXT DEFAULT ''",
        'payment_utr' => "ALTER TABLE orders ADD COLUMN payment_utr TEXT DEFAULT ''",
        'payment_checked_at' => "ALTER TABLE orders ADD COLUMN payment_checked_at TEXT DEFAULT ''",
        'cancel_reason' => "ALTER TABLE orders ADD COLUMN cancel_reason TEXT DEFAULT ''",
    ];
    foreach ($orderExtras as $col => $sql) {
        if (!isset($orders[$col])) {
            $pdo->exec($sql);
        }
    }

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $ai = 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $text = 'TEXT';
    $real = 'REAL';
    $now = "datetime('now','localtime')";

    if ($driver === 'mysql') {
        $ai = 'INT AUTO_INCREMENT PRIMARY KEY';
        $text = 'VARCHAR(255)';
        $real = 'DECIMAL(10,2)';
        $now = 'NOW()';
    } elseif ($driver === 'pgsql') {
        $ai = 'SERIAL PRIMARY KEY';
        $text = 'VARCHAR(255)';
        $real = 'DECIMAL(10,2)';
        $now = "timezone('Asia/Kolkata', now())";
    }

    $longText = 'TEXT';

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id {$ai},
            user_id INT NOT NULL,
            title {$text} NOT NULL,
            message {$longText} NOT NULL,
            link {$text} DEFAULT '',
            is_read INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT {$now}
        );

        CREATE TABLE IF NOT EXISTS otp_codes (
            id {$ai},
            phone {$text} NOT NULL,
            email {$text} NOT NULL,
            name {$text} DEFAULT '',
            code_hash {$text} NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            expires_at {$text} NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT {$now}
        );

        CREATE TABLE IF NOT EXISTS order_tracking (
            id {$ai},
            order_id INT NOT NULL,
            status {$text} NOT NULL,
            note {$longText} DEFAULT '',
            location {$text} DEFAULT '',
            created_by INT,
            created_at TIMESTAMP NOT NULL DEFAULT {$now}
        );

        CREATE TABLE IF NOT EXISTS password_resets (
            id {$ai},
            email {$text} NOT NULL,
            token {$text} NOT NULL UNIQUE,
            expires_at {$text} NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT {$now}
        );

        CREATE TABLE IF NOT EXISTS reviews (
            id {$ai},
            product_id INT NOT NULL,
            user_id INT NOT NULL,
            rating INT NOT NULL,
            comment {$longText} DEFAULT '',
            images {$longText} DEFAULT '',
            created_at TIMESTAMP NOT NULL DEFAULT {$now}
        );

        CREATE TABLE IF NOT EXISTS support_messages (
            id {$ai},
            thread_key {$text} NOT NULL,
            user_id INT,
            name {$text} NOT NULL DEFAULT '',
            email {$text} DEFAULT '',
            phone {$text} DEFAULT '',
            message {$longText} NOT NULL,
            sender {$text} NOT NULL DEFAULT 'customer',
            is_read INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT {$now}
        );
    ");

    $reviewCols = table_columns($pdo, 'reviews');
    if (!isset($reviewCols['images'])) {
        $pdo->exec("ALTER TABLE reviews ADD COLUMN images TEXT DEFAULT ''");
    }

    $productCols = table_columns($pdo, 'products');
    if (!isset($productCols['images'])) {
        $pdo->exec("ALTER TABLE products ADD COLUMN images TEXT DEFAULT ''");
    }
    if (!isset($productCols['category'])) {
        $pdo->exec("ALTER TABLE products ADD COLUMN category TEXT DEFAULT 'comfort'");
        $pdo->exec("UPDATE products SET category='heritage' WHERE lower(name || ' ' || description) LIKE '%heritage%' OR lower(name || ' ' || description) LIKE '%bihar%' OR lower(name || ' ' || description) LIKE '%embroider%'");
        $pdo->exec("UPDATE products SET category='cotton' WHERE lower(name || ' ' || description) LIKE '%cotton%' OR lower(name || ' ' || description) LIKE '%tee%'");
    }
    if (!isset($productCols['keywords'])) {
        $pdo->exec("ALTER TABLE products ADD COLUMN keywords TEXT DEFAULT ''");
    }

    // One review per customer/product at database level.
    $pdo->exec(
        'DELETE FROM reviews
         WHERE id NOT IN (
             SELECT MAX(id) FROM reviews GROUP BY product_id, user_id
         )'
    );
    
    // Check if unique index exists or create it
    try {
        if ($driver === 'sqlite') {
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_reviews_product_user ON reviews(product_id, user_id)');
        } else {
            $pdo->exec('CREATE UNIQUE INDEX idx_reviews_product_user ON reviews(product_id, user_id)');
        }
    } catch (PDOException $e) {
        // Index might already exist
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wishlists (
            id {$ai},
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT {$now}
        )
    ");
    
    try {
        if ($driver === 'sqlite') {
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_wishlists_user_product ON wishlists(user_id, product_id)');
        } else {
            $pdo->exec('CREATE UNIQUE INDEX idx_wishlists_user_product ON wishlists(user_id, product_id)');
        }
    } catch (PDOException $e) {
        // Index might already exist
    }
}

function admin_exists(PDO $pdo): bool
{
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    return (int) $stmt->fetchColumn() > 0;
}

function seed_database(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    if ($count === 0) {
        $products = [
            ['Born In Bihar', 'born-in-bihar', 'Premium embroidery design with a proud heritage statement.', 999, 'images/ss.png', 1],
            ['Magadh Heritage Tree', 'magadh-heritage-tree', 'Limited edition Bihar collection with refined detailing.', 699, 'images/cc.png', 2],
            ['Wear For Comfort', 'wear-for-comfort', 'Soft daily-wear fabric with a premium stitched finish.', 899, 'images/lw_b.png', 3],
            ['Pure Cotton Blue Tee', 'pure-cotton-blue-tee', 'Clean blue cotton T-shirt made for everyday comfort.', 599, 'images/t_front_b.png', 4],
        ];
        $stmt = $pdo->prepare(
            'INSERT INTO products (name, slug, description, price, image, stock, sort_order)
             VALUES (?, ?, ?, ?, ?, 50, ?)'
        );
        foreach ($products as $p) {
            $stmt->execute($p);
        }
    }

    if (!admin_exists($pdo)) {
        $hash = password_hash(DEFAULT_ADMIN_PASS, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, phone, email, password_hash, role, username)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            DEFAULT_ADMIN_NAME,
            '6205484119',
            CONTACT_EMAIL,
            $hash,
            'admin',
            DEFAULT_ADMIN_USER,
        ]);
    }
}
