<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_admin();
$pdo = db();
$allowedStatus = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
$filter = $_GET['status'] ?? 'all';
if ($filter !== 'all' && !in_array($filter, $allowedStatus, true)) {
    $filter = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if ($id > 0 && in_array($status, $allowedStatus, true)) {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        if ($order && $order['status'] !== $status) {
            $pdo->prepare(
                "UPDATE orders SET status = ?, updated_at = datetime('now','localtime') WHERE id = ?"
            )->execute([$status, $id]);

            add_order_tracking(
                $id,
                $status,
                'Status changed to ' . order_status_label($status),
                (string) ($order['city'] ?? ''),
                (int) $admin['id']
            );

            notify_user(
                (int) $order['user_id'],
                'Order #' . $id . ' updated',
                'Your order for ' . $order['product_name'] . ' is now: ' . order_status_label($status),
                'account/order_view.php?id=' . $id
            );
            flash('success', 'Order #' . $id . ' updated · customer notified.');
        }
    }
    $q = $filter !== 'all' ? ('?status=' . urlencode($filter)) : '';
    redirect('admin/orders.php' . $q);
}

$sql = "SELECT o.*,
               COALESCE(NULLIF(o.customer_name,''), u.name) AS display_name,
               COALESCE(NULLIF(o.customer_phone,''), u.phone) AS display_phone,
               u.email AS customer_email
        FROM orders o
        JOIN users u ON u.id = o.user_id";
$params = [];
if ($filter !== 'all') {
    $sql .= ' WHERE o.status = ?';
    $params[] = $filter;
}
$sql .= ' ORDER BY o.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$counts = [];
foreach ($allowedStatus as $st) {
    $counts[$st] = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = " . $pdo->quote($st))->fetchColumn();
}
$counts['all'] = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();

$pageTitle = 'Orders | ' . APP_NAME;
$adminActive = 'orders';
$adminHeading = 'Orders';
require __DIR__ . '/../includes/admin_header.php';
?>

<div class="admin-card">
  <div class="admin-card-head">
    <h2 class="admin-card-title"><span style="display:inline-block;width:4px;height:18px;border-radius:99px;background:var(--green);"></span> Orders</h2>
    <div class="deals-filters" style="margin:0;justify-content:flex-end;">
      <?php
      $tabs = ['all' => 'All'] + array_combine($allowedStatus, array_map('order_status_label', $allowedStatus));
      foreach ($tabs as $key => $label):
      ?>
        <a class="filter-pill <?= $filter === $key ? 'is-active' : '' ?>"
           style="padding:8px 16px;font-size:12px;"
           href="<?= e(url('admin/orders.php' . ($key === 'all' ? '' : '?status=' . $key))) ?>">
          <?= e($label) ?> (<?= (int) ($counts[$key] ?? 0) ?>)
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (!$orders): ?>
    <p class="admin-empty">No orders found.</p>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Order</th>
            <th>Image</th>
            <th>Customer</th>
            <th>Product</th>
            <th>Delivery</th>
            <th>Total</th>
            <th>Payment</th>
            <th>Status</th>
            <th>Quick Update</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $row): ?>
            <tr>
              <td><strong><?= e(order_code((int) $row['id'])) ?></strong></td>
              <td>
                <?php if ($row['product_image']): ?>
                  <img class="athumb" src="<?= e(product_image_url($row['product_image'])) ?>" alt="">
                <?php endif; ?>
              </td>
              <td>
                <strong><?= e($row['display_name']) ?></strong><br>
                <small>+91 <?= e($row['display_phone']) ?></small>
                <?php if (!empty($row['customer_email'])): ?>
                  <br><small><?= e($row['customer_email']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <?= e(mb_strlen($row['product_name']) > 40 ? mb_substr($row['product_name'], 0, 37) . '…' : $row['product_name']) ?><br>
                <small>Size <?= e($row['size']) ?> · Qty <?= (int) $row['quantity'] ?></small>
              </td>
              <td>
                <?php if (!empty($row['shipping_address'])): ?>
                  <small><?= e(strlen((string) $row['shipping_address']) > 60 ? substr((string) $row['shipping_address'], 0, 57) . '…' : (string) $row['shipping_address']) ?></small>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td><strong><?= e(money_inr((float) $row['price'] * (int) $row['quantity'])) ?></strong></td>
              <td>
                <span style="font-weight: 800; font-size: 11px; text-transform: uppercase; display: block; margin-bottom: 2px;">
                  <?= ($row['payment_method'] ?? 'cod') === 'upi' ? 'UPI' : 'COD' ?>
                </span>
                <?php if (($row['payment_method'] ?? 'cod') === 'upi'): ?>
                  <?php if ($row['payment_status'] === 'pending'): ?>
                    <span class="badge pending" style="background:#fff3e0; color:#e65100; font-size:10px; padding:2px 6px;">Unpaid</span>
                  <?php elseif ($row['payment_status'] === 'submitted'): ?>
                    <span class="badge pending" style="background:#e3f2fd; color:#0d47a1; font-size:10px; padding:2px 6px; border:1px solid #90caf9;" title="UTR: <?= e($row['transaction_id']) ?>">Verify UTR</span>
                  <?php elseif ($row['payment_status'] === 'paid'): ?>
                    <span class="badge shipped" style="background:#e8f5e9; color:#1b5e20; font-size:10px; padding:2px 6px;">Paid</span>
                  <?php elseif ($row['payment_status'] === 'failed'): ?>
                    <span class="badge cancelled" style="background:#ffebee; color:#b71c1c; font-size:10px; padding:2px 6px;">Rejected</span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="badge pending" style="background:#f5f5f5; color:#616161; font-size:10px; padding:2px 6px;">COD</span>
                <?php endif; ?>
              </td>
              <td><span class="badge <?= e($row['status']) ?>"><?= e(order_status_label($row['status'])) ?></span></td>
              <td>
                <form method="post" class="admin-row-actions">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                  <select name="status" class="admin-select">
                    <?php foreach ($allowedStatus as $st): ?>
                      <option value="<?= $st ?>" <?= $row['status'] === $st ? 'selected' : '' ?>><?= order_status_label($st) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn-admin-outline">Save</button>
                </form>
              </td>
              <td>
                <a class="icon-btn view" href="<?= e(url('admin/order_view.php?id=' . (int) $row['id'])) ?>" title="Track / details">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
