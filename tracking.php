<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$user = current_user();
$query = trim((string) ($_GET['q'] ?? ''));
$searched = $query !== '';
$order = null;
$tracking = [];

if ($searched) {
    $orderId = parse_order_code($query);
    if ($orderId > 0) {
        $stmt = db()->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch() ?: null;
        if ($order) {
            $tracking = get_order_tracking($orderId);
        }
    }
}

// My orders list for logged-in customers (each order links to its tracking)
$myOrders = [];
if ($user && $user['role'] === 'user') {
    $stmt = db()->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 20');
    $stmt->execute([(int) $user['id']]);
    $myOrders = $stmt->fetchAll();
}

$steps = ['pending', 'confirmed', 'shipped', 'delivered'];
$stepLabels = [
    'pending' => 'Order Placed',
    'confirmed' => 'Confirmed',
    'shipped' => 'Shipped',
    'delivered' => 'Delivered',
];
$statusIndex = $order ? array_search($order['status'], $steps, true) : false;

$pageTitle = 'Track Your Order | ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>

<main class="tracking-page">
  <div class="tracking-head reveal-up">
    <h1>
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="1" y="3" width="15" height="13" rx="1"></rect>
        <path d="M16 8h4l3 3v5h-7V8z"></path>
        <circle cx="5.5" cy="18.5" r="2.5"></circle>
        <circle cx="18.5" cy="18.5" r="2.5"></circle>
      </svg>
      Track Your Order
    </h1>
    <p>Enter your Order ID to see real-time updates of your delivery status.</p>
  </div>

  <form class="tracking-search reveal-up" method="get" action="<?= e(url('tracking.php')) ?>">
    <input type="text" name="q" placeholder="e.g. LW-000012" value="<?= e($query) ?>" aria-label="Order ID">
    <button type="submit">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="8"></circle>
        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
      </svg>
      Track Order
    </button>
  </form>

  <?php if (!$searched): ?>
    <div class="tracking-empty reveal-up">
      <svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
        <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
        <line x1="12" y1="22.08" x2="12" y2="12"></line>
      </svg>
      <h2>No Active Query</h2>
      <p>Enter your tracking identifier above to monitor shipment checkpoints.</p>
    </div>
  <?php elseif (!$order): ?>
    <div class="tracking-empty reveal-up">
      <svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="8"></circle>
        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        <line x1="8" y1="11" x2="14" y2="11"></line>
      </svg>
      <h2>Order Not Found</h2>
      <p>We could not find any order for “<?= e($query) ?>”. Please check your Tracking ID and try again.</p>
    </div>
  <?php else: ?>
    <div class="tracking-result reveal-up">
      <div class="tracking-order-card">
        <div class="to-head">
          <div>
            <p class="to-code"><?= e(order_code((int) $order['id'])) ?></p>
            <p class="to-date">Placed on <?= e(date('d M Y, h:i A', strtotime($order['created_at']))) ?></p>
          </div>
          <span class="badge <?= e($order['status']) ?>"><?= e(order_status_label($order['status'])) ?></span>
        </div>

        <div class="to-product">
          <img src="<?= e(product_image_url($order['product_image'])) ?>" alt="<?= e($order['product_name']) ?>">
          <div>
            <p class="to-name"><?= e($order['product_name']) ?></p>
            <p class="to-sub">Size <?= e($order['size']) ?> · Qty <?= (int) $order['quantity'] ?> · <?= e(money_inr((float) $order['price'] * (int) $order['quantity'])) ?></p>
            <p class="to-sub">Deliver to: <?= e($order['city']) ?>, <?= e($order['state']) ?> — <?= e($order['pincode']) ?></p>
          </div>
        </div>

        <?php if ($order['status'] === 'cancelled'): ?>
          <div class="to-cancelled">This order has been cancelled.</div>
        <?php else: ?>
          <div class="to-progress">
            <?php foreach ($steps as $i => $step): ?>
              <div class="to-step <?= ($statusIndex !== false && $i <= $statusIndex) ? 'done' : '' ?>">
                <span class="to-dot">
                  <?php if ($statusIndex !== false && $i <= $statusIndex): ?>
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                  <?php endif; ?>
                </span>
                <span class="to-step-label"><?= e($stepLabels[$step]) ?></span>
              </div>
              <?php if ($i < count($steps) - 1): ?>
                <span class="to-bar <?= ($statusIndex !== false && $i < $statusIndex) ? 'done' : '' ?>"></span>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($order['courier_name']) || !empty($order['tracking_number'])): ?>
          <div class="to-courier">
            <?php if (!empty($order['courier_name'])): ?><span><strong>Courier:</strong> <?= e($order['courier_name']) ?></span><?php endif; ?>
            <?php if (!empty($order['tracking_number'])): ?><span><strong>Courier Tracking No:</strong> <?= e($order['tracking_number']) ?></span><?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="tracking-timeline-card">
        <p class="section-bar-title"><span class="kh-bar"></span>Shipment Checkpoints</p>
        <?php if (!$tracking): ?>
          <p class="to-sub" style="margin-top:14px;">No checkpoints recorded yet. Updates will appear here as your order moves.</p>
        <?php else: ?>
          <div class="to-timeline">
            <?php foreach (array_reverse($tracking) as $t): ?>
              <div class="to-checkpoint">
                <span class="to-cp-dot"></span>
                <div>
                  <p class="to-cp-status"><?= e(order_status_label($t['status'])) ?></p>
                  <?php if ($t['note'] !== ''): ?><p class="to-cp-note"><?= e($t['note']) ?></p><?php endif; ?>
                  <p class="to-cp-meta">
                    <?= e(date('d M Y, h:i A', strtotime($t['created_at']))) ?>
                    <?= $t['location'] !== '' ? ' · ' . e($t['location']) : '' ?>
                  </p>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($myOrders): ?>
    <div class="tracking-my-orders reveal-up">
      <p class="section-bar-title"><span class="kh-bar"></span>My Orders</p>
      <div class="tmo-list">
        <?php foreach ($myOrders as $mo): ?>
          <a class="tmo-item" href="<?= e(url('tracking.php?q=' . order_code((int) $mo['id']))) ?>">
            <img src="<?= e(product_image_url($mo['product_image'])) ?>" alt="">
            <div class="tmo-info">
              <p class="tmo-name"><?= e($mo['product_name']) ?></p>
              <p class="tmo-sub"><?= e(order_code((int) $mo['id'])) ?> · <?= e(date('d M Y', strtotime($mo['created_at']))) ?></p>
            </div>
            <span class="badge <?= e($mo['status']) ?>"><?= e(order_status_label($mo['status'])) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
