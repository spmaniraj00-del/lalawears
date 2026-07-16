<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login();
if ($user['role'] === 'admin') {
    redirect('admin/index.php');
}

$pdo = db();
$uid = (int) $user['id'];

$totalOrders = (int) $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?')
    ->execute([$uid]) ?: 0;
$stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
$stmt->execute([$uid]);
$totalOrders = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$uid]);
$pendingOrders = (int) $stmt->fetchColumn();

$unread = unread_notification_count($uid);

$orders = $pdo->prepare(
    'SELECT o.*, p.image AS live_image
     FROM orders o
     LEFT JOIN products p ON p.id = o.product_id
     WHERE o.user_id = ?
     ORDER BY o.id DESC LIMIT 30'
);
$orders->execute([$uid]);
$orderRows = $orders->fetchAll();

$pageTitle = 'My Account | ' . APP_NAME;
require __DIR__ . '/../includes/header.php';
?>

<div class="page-shell">
  <div class="panel wide">
    <p class="eyebrow">My Account</p>
    <h1>Hello, <?= e(explode(' ', $user['name'])[0]) ?></h1>
    <p class="lead">
      <?= e($user['email']) ?>
      · Track orders, clothing details, and notifications in one place.
    </p>

    <div class="account-grid">
      <div class="stat-box"><h3><?= $totalOrders ?></h3><p>Total Orders</p></div>
      <div class="stat-box"><h3><?= $pendingOrders ?></h3><p>Pending</p></div>
      <div class="stat-box"><h3><?= $unread ?></h3><p>Unread Alerts</p></div>
    </div>

    <div style="margin-top:28px;display:flex;flex-wrap:wrap;gap:12px;">
      <a class="btn" href="<?= e(url('index.php')) ?>#collection">Shop Collection</a>
      <a class="btn-outline" href="<?= e(url('account/notifications.php')) ?>">Notifications<?= $unread ? " ({$unread})" : '' ?></a>
    </div>

    <h2 style="margin-top:48px;font-size:2rem;font-weight:900;text-transform:uppercase;letter-spacing:-0.03em;">Your Orders</h2>
    <?php if (!$orderRows): ?>
      <p class="lead" style="margin-top:16px;">No orders yet. Browse the collection and place your first order.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data">
          <thead>
            <tr>
              <th>Piece</th>
              <th>Product</th>
              <th>Price</th>
              <th>Size</th>
              <th>Status</th>
              <th>Date</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orderRows as $row):
              $img = $row['product_image'] ?: ($row['live_image'] ?? 'images/ss.png');
            ?>
              <tr>
                <td><img class="thumb" src="<?= e(product_image_url($img)) ?>" alt=""></td>
                <td>
                  <strong style="color:var(--text)"><?= e($row['product_name']) ?></strong><br>
                  <small>Qty <?= (int) $row['quantity'] ?></small>
                </td>
                <td><?= e(money_inr($row['price'])) ?></td>
                <td><?= e($row['size']) ?></td>
                <td><span class="badge <?= e($row['status']) ?>"><?= e(order_status_label($row['status'])) ?></span></td>
                <td><?= e($row['created_at']) ?></td>
                <td><a class="btn-outline" href="<?= e(url('account/order_view.php?id=' . (int) $row['id'])) ?>">Details</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
