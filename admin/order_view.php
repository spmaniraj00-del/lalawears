<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_admin();
$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$allowedStatus = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];

$stmt = $pdo->prepare(
    "SELECT o.*, u.name AS account_name, u.email AS account_email, u.phone AS account_phone
     FROM orders o
     JOIN users u ON u.id = o.user_id
     WHERE o.id = ? LIMIT 1"
);
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    flash('error', 'Order not found.');
    redirect('admin/orders.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = $_POST['action'] ?? 'update';

    if ($action === 'update') {
        $status = $_POST['status'] ?? '';
        $courier = trim($_POST['courier_name'] ?? '');
        $trackingNo = trim($_POST['tracking_number'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $note = trim($_POST['tracking_note'] ?? '');

        if (!in_array($status, $allowedStatus, true)) {
            $error = 'Invalid status.';
        } else {
            $pdo->prepare(
                "UPDATE orders SET
                    status = ?,
                    courier_name = ?,
                    tracking_number = ?,
                    tracking_note = ?,
                    updated_at = datetime('now','localtime')
                 WHERE id = ?"
            )->execute([$status, $courier, $trackingNo, $note, $id]);

            $trackMsg = $note !== '' ? $note : ('Marked as ' . order_status_label($status));
            if ($courier !== '') {
                $trackMsg .= ' · Courier: ' . $courier;
            }
            if ($trackingNo !== '') {
                $trackMsg .= ' · AWB: ' . $trackingNo;
            }

            add_order_tracking($id, $status, $trackMsg, $location, (int) $admin['id']);

            $notifyMsg = 'Order #' . $id . ' is now ' . order_status_label($status);
            if ($location !== '') {
                $notifyMsg .= ' · Location: ' . $location;
            }
            if ($trackingNo !== '') {
                $notifyMsg .= ' · Tracking: ' . $trackingNo;
            }

            notify_user(
                (int) $order['user_id'],
                'Order #' . $id . ' tracking update',
                $notifyMsg,
                'account/order_view.php?id=' . $id
            );

            flash('success', 'Tracking updated. Customer notified.');
            redirect('admin/order_view.php?id=' . $id);
        }
    }
}

// refresh after possible failed post
$stmt->execute([$id]);
$order = $stmt->fetch();
$tracking = get_order_tracking($id);
$steps = ['pending', 'confirmed', 'shipped', 'delivered'];
$statusIndex = array_search($order['status'], $steps, true);
$img = $order['product_image'] ?: 'images/ss.png';
$displayName = $order['customer_name'] !== '' ? $order['customer_name'] : $order['account_name'];
$displayPhone = $order['customer_phone'] !== '' ? $order['customer_phone'] : $order['account_phone'];

$pageTitle = 'Track Order #' . $id . ' | Admin';
$adminActive = 'orders';
$adminHeading = 'Order ' . order_code($id);
require __DIR__ . '/../includes/admin_header.php';
?>

<div class="admin-card">
    <div class="admin-card-head">
      <h2 class="admin-card-title"><span style="display:inline-block;width:4px;height:18px;border-radius:99px;background:var(--green);"></span> Order <?= e(order_code($id)) ?></h2>
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <span class="badge <?= e($order['status']) ?>"><?= e(order_status_label($order['status'])) ?></span>
        <a class="btn-admin-outline" href="<?= e(url('admin/orders.php')) ?>">Back to Orders</a>
      </div>
    </div>
    <p style="font-size:12px;color:var(--text-faint);font-weight:600;margin:-8px 0 18px;">
      Placed <?= e($order['created_at']) ?> · Last update <?= e($order['updated_at']) ?>
    </p>

    <?php if ($error): ?>
      <div class="flash flash-error" style="position:static;transform:none;margin-bottom:18px;"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="order-detail">
      <div class="order-detail-img">
        <img src="<?= e(product_image_url($img)) ?>" alt="<?= e($order['product_name']) ?>">
      </div>
      <div>
        <p class="portfolio-cat">Product</p>
        <h2 style="font-size:2rem;font-weight:900;text-transform:uppercase;line-height:0.95;margin:8px 0 12px;">
          <?= e($order['product_name']) ?>
        </h2>
        <?php if ($order['product_description']): ?>
          <p style="color:var(--text-soft);font-weight:500;"><?= e($order['product_description']) ?></p>
        <?php endif; ?>

        <div class="detail-rows">
          <div class="detail-row"><span>Price</span><strong><?= e(money_inr($order['price'])) ?></strong></div>
          <div class="detail-row"><span>Qty / Size</span><strong><?= (int) $order['quantity'] ?> · <?= e($order['size']) ?></strong></div>
          <div class="detail-row"><span>Total</span><strong><?= e(money_inr((float) $order['price'] * (int) $order['quantity'])) ?></strong></div>
          <div class="detail-row"><span>Customer</span><strong><?= e($displayName) ?></strong></div>
          <div class="detail-row"><span>Phone</span><strong>+91 <?= e($displayPhone) ?></strong></div>
          <?php if ($order['account_email']): ?>
            <div class="detail-row"><span>Email</span><strong><?= e($order['account_email']) ?></strong></div>
          <?php endif; ?>
          <?php if ($order['shipping_address']): ?>
            <div class="detail-row"><span>Full address</span><strong style="text-align:right;max-width:65%;"><?= e($order['shipping_address']) ?></strong></div>
          <?php endif; ?>
          <?php if ($order['courier_name']): ?>
            <div class="detail-row"><span>Courier</span><strong><?= e($order['courier_name']) ?></strong></div>
          <?php endif; ?>
          <?php if ($order['tracking_number']): ?>
            <div class="detail-row"><span>Tracking No.</span><strong><?= e($order['tracking_number']) ?></strong></div>
          <?php endif; ?>
        </div>

        <div class="timeline" style="margin-top:28px;">
          <p class="eyebrow" style="margin-bottom:8px;">Delivery progress</p>
          <?php if ($order['status'] === 'cancelled'): ?>
            <div class="timeline-step done"><span class="timeline-dot"></span> Cancelled</div>
          <?php else: ?>
            <?php foreach ($steps as $i => $step): ?>
              <div class="timeline-step <?= ($statusIndex !== false && $i <= $statusIndex) ? 'done' : '' ?>">
                <span class="timeline-dot"></span>
                <?= e(order_status_label($step)) ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div style="margin-top:36px;display:grid;grid-template-columns:1.1fr 0.9fr;gap:28px;">
      <div>
        <h2 style="font-size:1.4rem;font-weight:900;text-transform:uppercase;margin-bottom:16px;">Update / Track</h2>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update">
          <div class="form-group">
            <label for="status">Order status</label>
            <select id="status" name="status" required>
              <?php foreach ($allowedStatus as $st): ?>
                <option value="<?= $st ?>" <?= $order['status'] === $st ? 'selected' : '' ?>><?= order_status_label($st) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="location">Current location / city</label>
            <input type="text" id="location" name="location" maxlength="120"
                   placeholder="e.g. Bettiah hub / Out for delivery"
                   value="<?= e($order['city'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label for="courier_name">Courier name</label>
            <input type="text" id="courier_name" name="courier_name" maxlength="80"
                   value="<?= e($order['courier_name'] ?? '') ?>" placeholder="India Post / DTDC / Local">
          </div>
          <div class="form-group">
            <label for="tracking_number">Tracking / AWB number</label>
            <input type="text" id="tracking_number" name="tracking_number" maxlength="80"
                   value="<?= e($order['tracking_number'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label for="tracking_note">Admin note (customer will see in notification)</label>
            <textarea id="tracking_note" name="tracking_note" placeholder="e.g. Packed and handed to courier"><?= e($order['tracking_note'] ?? '') ?></textarea>
          </div>
          <div class="form-actions" style="display:flex;gap:10px;">
            <button type="submit" class="btn-admin-primary">Save & Notify Customer</button>
            <a class="btn-admin-outline" href="<?= e(url('admin/orders.php')) ?>">Back to Orders</a>
          </div>
        </form>
      </div>

      <div>
        <h2 style="font-size:1.4rem;font-weight:900;text-transform:uppercase;margin-bottom:16px;">Tracking history</h2>
        <?php if (!$tracking): ?>
          <p class="lead">No tracking updates yet. Save a status to start the log.</p>
        <?php else: ?>
          <div class="notif-list">
            <?php foreach (array_reverse($tracking) as $t): ?>
              <article class="notif-item">
                <div>
                  <h3><?= e(order_status_label($t['status'])) ?></h3>
                  <p><?= e($t['note'] ?: 'Status update') ?></p>
                  <?php if ($t['location']): ?>
                    <p><strong>Location:</strong> <?= e($t['location']) ?></p>
                  <?php endif; ?>
                  <time><?= e($t['created_at']) ?><?= $t['admin_name'] ? ' · ' . e($t['admin_name']) : '' ?></time>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
</div>

<style>
@media (max-width: 900px) {
  .admin-card > div[style*="grid-template-columns:1.1fr"] {
    grid-template-columns: 1fr !important;
  }
}
</style>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
