<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_admin();
$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$product = null;
$error = '';

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) {
        flash('error', 'Product not found.');
        redirect('admin/products.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $stock = (int) ($_POST['stock'] ?? 0);
    $sort = (int) ($_POST['sort_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $slug = slugify($_POST['slug'] ?? $name);

    try {
        if ($name === '' || strlen($name) < 2) {
            throw new RuntimeException('Product name is required.');
        }
        if ($price < 0) {
            throw new RuntimeException('Price cannot be negative.');
        }

        $imagePath = $product['image'] ?? '';
        if (!empty($_FILES['image']['name'])) {
            $uploaded = safe_upload_image($_FILES['image'], 'product');
            if ($uploaded) {
                $imagePath = $uploaded;
            }
        }
        if ($imagePath === '') {
            throw new RuntimeException('Please upload a product image.');
        }

        // Unique slug
        $check = $pdo->prepare('SELECT id FROM products WHERE slug = ? AND id != ? LIMIT 1');
        $check->execute([$slug, $id]);
        if ($check->fetch()) {
            $slug .= '-' . bin2hex(random_bytes(2));
        }

        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE products SET name=?, slug=?, description=?, price=?, image=?, stock=?, is_active=?, sort_order=?, updated_at=datetime(\'now\',\'localtime\')
                 WHERE id=?'
            );
            $stmt->execute([$name, $slug, $description, $price, $imagePath, $stock, $isActive, $sort, $id]);
            flash('success', 'Product updated successfully.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO products (name, slug, description, price, image, stock, is_active, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$name, $slug, $description, $price, $imagePath, $stock, $isActive, $sort]);
            flash('success', 'New product added.');
        }
        redirect('admin/products.php');
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
        $product = [
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'price' => $price,
            'image' => $product['image'] ?? '',
            'stock' => $stock,
            'is_active' => $isActive,
            'sort_order' => $sort,
        ];
    }
}

$pageTitle = ($id ? 'Edit Product' : 'Add Product') . ' | ' . APP_NAME;
$adminActive = 'add';
$adminHeading = $id ? 'Edit Product' : 'Add Product';
require __DIR__ . '/../includes/admin_header.php';
?>

<div class="admin-card" style="max-width:760px;">
  <div class="admin-card-head">
    <h2 class="admin-card-title"><span style="display:inline-block;width:4px;height:18px;border-radius:99px;background:var(--green);"></span> <?= $id ? 'Edit Product' : 'Add New Product' ?></h2>
    <a class="btn-admin-outline" href="<?= e(url('admin/products.php')) ?>">Back to Products</a>
  </div>

    <?php if ($error): ?>
      <div class="flash flash-error" style="position:static;transform:none;margin-bottom:18px;"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" style="max-width:640px;">
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="name">Product Name</label>
        <input type="text" id="name" name="name" required maxlength="120"
               value="<?= e($product['name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="slug">Slug (optional)</label>
        <input type="text" id="slug" name="slug" maxlength="140"
               value="<?= e($product['slug'] ?? '') ?>" placeholder="auto-generated from name">
      </div>
      <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" required><?= e($product['description'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label for="price">Price (₹)</label>
        <input type="number" id="price" name="price" min="0" step="1" required
               value="<?= e((string) ($product['price'] ?? '')) ?>">
      </div>
      <div class="form-group">
        <label for="stock">Stock</label>
        <input type="number" id="stock" name="stock" min="0" step="1"
               value="<?= e((string) ($product['stock'] ?? '50')) ?>">
      </div>
      <div class="form-group">
        <label for="sort_order">Sort Order</label>
        <input type="number" id="sort_order" name="sort_order" step="1"
               value="<?= e((string) ($product['sort_order'] ?? '0')) ?>">
      </div>
      <div class="form-group">
        <label for="image">Product Photo <?= $id ? '(leave empty to keep current)' : '' ?></label>
        <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp,image/gif" <?= $id ? '' : 'required' ?>>
        <?php if (!empty($product['image'])): ?>
          <img class="preview-img" src="<?= e(product_image_url($product['image'])) ?>" alt="Current">
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label>
          <input type="checkbox" name="is_active" value="1" <?= !isset($product['is_active']) || (int) $product['is_active'] === 1 ? 'checked' : '' ?>>
          Active (show on website)
        </label>
      </div>
      <div class="form-actions" style="display:flex;gap:10px;">
        <button type="submit" class="btn-admin-primary"><?= $id ? 'Save Changes' : 'Add Product' ?></button>
        <a class="btn-admin-outline" href="<?= e(url('admin/products.php')) ?>">Cancel</a>
      </div>
    </form>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
