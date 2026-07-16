<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'toggle' && $id > 0) {
        $pdo->prepare('UPDATE products SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END, updated_at = datetime(\'now\',\'localtime\') WHERE id = ?')
            ->execute([$id]);
        flash('success', 'Product visibility updated.');
    }

    if ($action === 'delete' && $id > 0) {
        $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
        flash('success', 'Product deleted.');
    }

    redirect('admin/products.php');
}

$products = $pdo->query('SELECT * FROM products ORDER BY sort_order ASC, id ASC')->fetchAll();

$pageTitle = 'Manage Products | ' . APP_NAME;
$adminActive = 'products';
$adminHeading = 'Products';
require __DIR__ . '/../includes/admin_header.php';
?>

<div class="admin-card">
  <div class="admin-card-head">
    <h2 class="admin-card-title"><span style="display:inline-block;width:4px;height:18px;border-radius:99px;background:var(--green);"></span> Products</h2>
    <a class="btn-admin-primary" href="<?= e(url('admin/product_edit.php')) ?>">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
      Add Product
    </a>
  </div>

  <?php if (!$products): ?>
    <p class="admin-empty">No products found.</p>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Image</th>
            <th>Title</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $p): ?>
            <tr>
              <td><img class="athumb" src="<?= e(product_image_url($p['image'])) ?>" alt=""></td>
              <td>
                <strong><?= e(mb_strlen($p['name']) > 55 ? mb_substr($p['name'], 0, 52) . '…' : $p['name']) ?></strong>
              </td>
              <td><strong style="color:var(--green-dark);"><?= e(money_inr($p['price'])) ?></strong></td>
              <td>
                <?php if ((int) $p['stock'] > 0): ?>
                  <span class="stock-badge"><?= (int) $p['stock'] ?> In Stock</span>
                <?php else: ?>
                  <span class="stock-badge out">Out of Stock</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge <?= (int) $p['is_active'] ? 'confirmed' : 'cancelled' ?>">
                  <?= (int) $p['is_active'] ? 'Active' : 'Hidden' ?>
                </span>
              </td>
              <td>
                <div class="admin-row-actions">
                  <a class="icon-btn edit" href="<?= e(url('admin/product_edit.php?id=' . (int) $p['id'])) ?>" title="Edit">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"></path></svg>
                  </a>
                  <form class="inline-form" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button type="submit" class="icon-btn toggle" title="<?= (int) $p['is_active'] ? 'Hide from site' : 'Show on site' ?>">
                      <?php if ((int) $p['is_active']): ?>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                      <?php else: ?>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"></path><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"></path><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                      <?php endif; ?>
                    </button>
                  </form>
                  <form class="inline-form" method="post" onsubmit="return confirm('Delete this product permanently?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button type="submit" class="icon-btn delete" title="Delete">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
