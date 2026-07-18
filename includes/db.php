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
          <title>Database Setup Error | LALA WEARS</title>
          <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700;900&display=swap" rel="stylesheet">
          <style>
            body { font-family: "League Spartan", sans-serif; background: #fdf8f3; color: #262626; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
            .card { background: #fff; padding: 40px; border-radius: 24px; box-shadow: 0 10px 40px rgba(38,38,38,0.06); max-width: 500px; text-align: center; border: 1px solid rgba(38,38,38,0.05); }
            h1 { font-size: 2.2rem; font-weight: 900; text-transform: uppercase; margin: 0 0 16px; color: #e4a4bd; }
            p { font-size: 1.1rem; line-height: 1.5; color: #595959; margin: 0 0 24px; }
            .steps { text-align: left; background: #f5f0eb; padding: 20px; border-radius: 16px; font-size: 0.95rem; font-weight: 600; color: #262626; line-height: 1.6; }
            .steps ol { margin: 8px 0 0; padding-left: 20px; }
            .code { font-family: monospace; background: #fff; padding: 2px 6px; border-radius: 4px; border: 1px solid rgba(0,0,0,0.08); }
          </style>
        </head>
        <body>
          <div class="card">
            <h1>Database Error</h1>
            <p>We could not initialize the database. This usually happens when the server does not have permission to write to the database folder.</p>
            <div class="steps">
              <strong>How to fix on InfinityFree:</strong>
              <ol>
                <li>Open the <strong>File Manager</strong> in your InfinityFree panel.</li>
                <li>Go to <span class="code">htdocs</span>.</li>
                <li>Right-click the <span class="code">data</span> folder and click <strong>Permissions</strong> (or Chmod).</li>
                <li>Set permissions to <strong>777</strong> (check Write permissions) and save.</li>
              </ol>
            </div>
          </div>
        </body>
        </html>';
        exit;
    }

    return $pdo;
}

function init_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            phone TEXT DEFAULT '',
            email TEXT DEFAULT '',
            password_hash TEXT DEFAULT '',
            google_id TEXT UNIQUE,
            avatar TEXT DEFAULT '',
            role TEXT NOT NULL DEFAULT 'user' CHECK(role IN ('user','admin')),
            username TEXT UNIQUE,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
        );

        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            description TEXT NOT NULL DEFAULT '',
            price REAL NOT NULL CHECK(price >= 0),
            image TEXT NOT NULL DEFAULT '',
            stock INTEGER NOT NULL DEFAULT 50,
            is_active INTEGER NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
        );

            CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            product_id INTEGER,
            product_name TEXT NOT NULL,
            product_image TEXT DEFAULT '',
            product_description TEXT DEFAULT '',
            price REAL NOT NULL,
            quantity INTEGER NOT NULL DEFAULT 1,
            size TEXT DEFAULT 'M',
            customer_name TEXT DEFAULT '',
            customer_phone TEXT DEFAULT '',
            shipping_address TEXT DEFAULT '',
            city TEXT DEFAULT '',
            state TEXT DEFAULT '',
            pincode TEXT DEFAULT '',
            landmark TEXT DEFAULT '',
            status TEXT NOT NULL DEFAULT 'pending'
                CHECK(status IN ('pending','confirmed','shipped','delivered','cancelled')),
            notes TEXT DEFAULT '',
            created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            message TEXT NOT NULL,
            link TEXT DEFAULT '',
            is_read INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            identifier TEXT NOT NULL,
            ip_address TEXT NOT NULL,
            attempted_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
        );

        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );
    ");
}

function table_columns(PDO $pdo, string $table): array
{
    $cols = [];
    foreach ($pdo->query("PRAGMA table_info({$table})") as $row) {
        $cols[$row['name']] = true;
    }
    return $cols;
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

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            message TEXT NOT NULL,
            link TEXT DEFAULT '',
            is_read INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS otp_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            phone TEXT NOT NULL,
            email TEXT NOT NULL,
            name TEXT DEFAULT '',
            code_hash TEXT NOT NULL,
            attempts INTEGER NOT NULL DEFAULT 0,
            expires_at TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
        );

        CREATE TABLE IF NOT EXISTS order_tracking (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            status TEXT NOT NULL,
            note TEXT DEFAULT '',
            location TEXT DEFAULT '',
            created_by INTEGER,
            created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS password_resets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            token TEXT NOT NULL UNIQUE,
            expires_at TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
        );

        CREATE TABLE IF NOT EXISTS reviews (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            rating INTEGER NOT NULL CHECK(rating BETWEEN 1 AND 5),
            comment TEXT DEFAULT '',
            images TEXT DEFAULT '',
            created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS support_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            thread_key TEXT NOT NULL,
            user_id INTEGER,
            name TEXT NOT NULL DEFAULT '',
            email TEXT DEFAULT '',
            phone TEXT DEFAULT '',
            message TEXT NOT NULL,
            sender TEXT NOT NULL DEFAULT 'customer' CHECK(sender IN ('customer','admin')),
            is_read INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
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
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_reviews_product_user ON reviews(product_id, user_id)');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wishlists (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
            UNIQUE(user_id, product_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )
    ");
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
