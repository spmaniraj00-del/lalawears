<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login();
$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    'SELECT o.*, p.image AS live_image, p.name AS live_name
     FROM orders o
     LEFT JOIN products p ON p.id = o.product_id
     WHERE o.id = ? LIMIT 1'
);
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    flash('error', 'Order not found.');
    redirect($user['role'] === 'admin' ? 'admin/orders.php' : 'account/index.php');
}

if ($user['role'] !== 'admin' && (int) $order['user_id'] !== (int) $user['id']) {
    http_response_code(403);
    flash('error', 'You cannot view this order.');
    redirect('account/index.php');
}

$img = $order['product_image'] ?: ($order['live_image'] ?? 'images/ss.png');
$steps = ['pending', 'confirmed', 'shipped', 'delivered'];
$statusIndex = array_search($order['status'], $steps, true);
if ($order['status'] === 'cancelled') {
    $statusIndex = -1;
}

$pageTitle = 'Order #' . $id . ' | ' . APP_NAME;
require __DIR__ . '/../includes/header.php';
?>

<div class="page-shell">
  <div class="panel wide">
    <p class="eyebrow">Order Detail</p>
    <h1>Order #<?= (int) $order['id'] ?></h1>
    <p class="lead">
      Status: <span class="badge <?= e($order['status']) ?>"><?= e(order_status_label($order['status'])) ?></span>
      · Placed <?= e($order['created_at']) ?>
    </p>

    <div class="order-detail">
      <div class="order-detail-img">
        <img src="<?= e(product_image_url($img)) ?>" alt="<?= e($order['product_name']) ?>">
      </div>
      <div>
        <p class="portfolio-cat">Clothing Piece</p>
        <h2 style="font-size:2.4rem;font-weight:900;text-transform:uppercase;line-height:0.95;margin:8px 0 12px;">
          <?= e($order['product_name']) ?>
        </h2>
        <?php if ($order['product_description']): ?>
          <p style="color:var(--text-soft);font-weight:500;"><?= e($order['product_description']) ?></p>
        <?php endif; ?>

        <div class="detail-rows">
          <div class="detail-row"><span>Unit Price</span><strong><?= e(money_inr($order['price'])) ?></strong></div>
          <div class="detail-row"><span>Quantity</span><strong><?= (int) $order['quantity'] ?></strong></div>
          <div class="detail-row"><span>Size</span><strong><?= e($order['size']) ?></strong></div>
          <div class="detail-row"><span>Total</span><strong><?= e(money_inr((float) $order['price'] * (int) $order['quantity'])) ?></strong></div>
          <?php if (!empty($order['customer_name'])): ?>
            <div class="detail-row"><span>Name</span><strong><?= e($order['customer_name']) ?></strong></div>
          <?php endif; ?>
          <?php if ($order['customer_phone']): ?>
            <div class="detail-row"><span>Phone</span><strong>+91 <?= e($order['customer_phone']) ?></strong></div>
          <?php endif; ?>
          <?php if ($order['shipping_address']): ?>
            <div class="detail-row"><span>Address</span><strong style="text-align:right;max-width:65%;"><?= e($order['shipping_address']) ?></strong></div>
          <?php endif; ?>
          <?php if (!empty($order['city'])): ?>
            <div class="detail-row"><span>City / State</span><strong><?= e($order['city']) ?>, <?= e($order['state'] ?? '') ?></strong></div>
          <?php endif; ?>
          <?php if (!empty($order['pincode'])): ?>
            <div class="detail-row"><span>PIN</span><strong><?= e($order['pincode']) ?></strong></div>
          <?php endif; ?>
          <?php if (!empty($order['landmark'])): ?>
            <div class="detail-row"><span>Landmark</span><strong><?= e($order['landmark']) ?></strong></div>
          <?php endif; ?>
          <?php if (!empty($order['courier_name'])): ?>
            <div class="detail-row"><span>Courier</span><strong><?= e($order['courier_name']) ?></strong></div>
          <?php endif; ?>
          <?php if (!empty($order['tracking_number'])): ?>
            <div class="detail-row"><span>Tracking No.</span><strong><?= e($order['tracking_number']) ?></strong></div>
          <?php endif; ?>
          <?php if (!empty($order['tracking_note'])): ?>
            <div class="detail-row"><span>Update note</span><strong style="text-align:right;max-width:65%;"><?= e($order['tracking_note']) ?></strong></div>
          <?php endif; ?>
          <?php if ($order['notes']): ?>
            <div class="detail-row"><span>Your note</span><strong style="text-align:right;max-width:65%;"><?= e($order['notes']) ?></strong></div>
          <?php endif; ?>
        </div>

        <?php
        $tracking = get_order_tracking((int) $order['id']);
        if ($tracking):
        ?>
          <div style="margin-top:28px;">
            <p class="eyebrow" style="margin-bottom:12px;">Where is my order</p>
            <div class="notif-list">
              <?php foreach (array_reverse($tracking) as $t): ?>
                <article class="notif-item">
                  <div>
                    <h3><?= e(order_status_label($t['status'])) ?></h3>
                    <p><?= e($t['note'] ?: 'Status update') ?></p>
                    <?php if ($t['location']): ?><p><strong>Location:</strong> <?= e($t['location']) ?></p><?php endif; ?>
                    <time><?= e($t['created_at']) ?></time>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="timeline">
          <p class="eyebrow" style="margin-bottom:8px;">Progress</p>
          <?php if ($order['status'] === 'cancelled'): ?>
            <div class="timeline-step done"><span class="timeline-dot"></span> Order cancelled</div>
          <?php else: ?>
            <?php foreach ($steps as $i => $step): ?>
              <div class="timeline-step <?= ($statusIndex !== false && $i <= $statusIndex) ? 'done' : '' ?>">
                <span class="timeline-dot"></span>
                <?= e(order_status_label($step)) ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div style="margin-top:28px;display:flex;flex-wrap:wrap;gap:12px;">
          <a class="btn-outline" href="<?= e(url($user['role'] === 'admin' ? 'admin/orders.php' : 'account/index.php')) ?>">Back</a>
          <?php if ($user['role'] === 'user'): ?>
            <a class="btn" href="<?= e(url('account/notifications.php')) ?>">Notifications</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
