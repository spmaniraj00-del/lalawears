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

// Auto status check for pending UPI orders that have a gateway transaction ID
if (($order['payment_method'] ?? '') === 'upi' && ($order['payment_status'] ?? '') === 'pending' && !empty($order['transaction_id'])) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, TERMINALX_STATUS_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'user_token' => TERMINALX_TOKEN,
        'order_id' => $order['transaction_id']
    ]));
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $result = json_decode($response, true);
        if (($result['status'] ?? '') === 'COMPLETED' || ($result['result']['txnStatus'] ?? '') === 'COMPLETED') {
            $pdo = db();
            $pdo->prepare(
                "UPDATE orders SET
                    payment_status = 'paid',
                    status = 'confirmed',
                    updated_at = datetime('now','localtime')
                 WHERE id = ?"
            )->execute([$id]);

            add_order_tracking(
                $id,
                'confirmed',
                'Payment verified automatically via gateway (UTR: ' . ($result['result']['utr'] ?? 'N/A') . ') · Order Confirmed',
                (string) ($order['city'] ?? '')
            );

            notify_user(
                (int) $order['user_id'],
                'Order #' . $id . ' payment verified',
                'Your payment was verified automatically. Order is now Confirmed.',
                'account/order_view.php?id=' . $id
            );

            notify_admins(
                'Payment verified automatically for Order #' . $id,
                'Order #' . $id . ' total ' . money_inr((float) $order['price'] * (int) $order['quantity']) . ' verified via gateway.',
                'admin/order_view.php?id=' . $id
            );

            // Refresh order data
            $stmt->execute([$id]);
            $order = $stmt->fetch();
        } elseif (($result['status'] ?? '') === 'FAILED' || ($result['result']['txnStatus'] ?? '') === 'FAILED') {
            $pdo = db();
            $pdo->prepare(
                "UPDATE orders SET
                    payment_status = 'failed',
                    updated_at = datetime('now','localtime')
                 WHERE id = ?"
            )->execute([$id]);
            
            // Refresh order data
            $stmt->execute([$id]);
            $order = $stmt->fetch();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'pay') {
        $amount = (float) $order['price'] * (int) $order['quantity'];
        // Generate unique payment order ID to avoid ORDER_ID_ALREADY_EXISTS on retry
        $txnId = order_code((int) $order['id']) . 'T' . time();
        $redirectUrl = app_absolute_url('account/order_view.php?id=' . $id);

        $postData = [
            'customer_mobile' => normalize_phone($order['customer_phone'] ?: $user['phone'] ?: '9999999999'),
            'user_token' => TERMINALX_TOKEN,
            'amount' => (string) $amount,
            'order_id' => $txnId,
            'redirect_url' => $redirectUrl,
            'remark1' => $order['product_name'],
            'remark2' => 'Order #' . $id,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, TERMINALX_CREATE_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response) {
            $result = json_decode($response, true);
            if (($result['status'] ?? '') === 'SUCCESS' && !empty($result['result']['payment_url'])) {
                $pdo = db();
                $pdo->prepare(
                    "UPDATE orders SET
                        payment_status = 'submitted',
                        transaction_id = ?,
                        updated_at = datetime('now','localtime')
                     WHERE id = ?"
                )->execute([$txnId, $id]);

                header('Location: ' . $result['result']['payment_url']);
                exit;
            } else {
                flash('error', 'Payment gateway error: ' . ($result['message'] ?? 'Could not create transaction.'));
            }
        } else {
            flash('error', 'Unable to reach payment gateway: ' . $err);
        }
        redirect('account/order_view.php?id=' . $id);
    } elseif ($action === 'submit_payment') {
        $utr = trim($_POST['utr'] ?? '');
        if (!preg_match('/^\d{12}$/', $utr)) {
            flash('error', 'Please enter a valid 12-digit UPI UTR/Transaction Reference Number.');
        } else {
            $pdo = db();
            $pdo->prepare(
                "UPDATE orders SET
                    payment_status = 'submitted',
                    transaction_id = ?,
                    updated_at = datetime('now','localtime')
                 WHERE id = ?"
            )->execute([$utr, $id]);

            add_order_tracking(
                $id,
                'pending',
                'Payment details submitted: UTR ' . $utr . ' · Awaiting admin verification',
                (string) ($order['city'] ?? '')
            );

            notify_admins(
                'Payment submitted for Order #' . $id,
                'Customer ' . ($order['customer_name'] ?: $user['name']) . ' submitted UTR ' . $utr . ' for ' . money_inr((float) $order['price'] * (int) $order['quantity']),
                'admin/order_view.php?id=' . $id
            );

            flash('success', 'Payment details submitted successfully! Awaiting verification.');
            redirect('account/order_view.php?id=' . $id);
        }
    }
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
          <div class="detail-row"><span>Payment Method</span><strong><?= ($order['payment_method'] ?? 'cod') === 'upi' ? 'UPI / QR Code' : 'Cash on Delivery' ?></strong></div>
          <?php if (($order['payment_method'] ?? 'cod') === 'upi' && $order['transaction_id']): ?>
            <div class="detail-row"><span>UPI UTR / Ref</span><strong><?= e($order['transaction_id']) ?></strong></div>
          <?php endif; ?>
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

        <?php if (($order['payment_method'] ?? 'cod') === 'upi'): ?>
          <div class="checkout-card payment-card" style="margin-top: 28px; padding: 24px; border: 1.5px solid rgba(26, 115, 232, 0.2); border-radius: 20px; background: #fbfdff; box-shadow: 0 4px 16px rgba(26, 115, 232, 0.04);">
            <h3 style="font-size: 1.3rem; font-weight: 800; color: #1a73e8; margin-top: 0; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
              UPI Gateway Payment
            </h3>

            <?php if ($order['payment_status'] === 'pending'): ?>
              <?php $amount = (float) $order['price'] * (int) $order['quantity']; ?>
              <div style="text-align: center; background: #fff; border: 1px solid rgba(0,0,0,0.06); padding: 24px; border-radius: 16px; display:flex; flex-direction:column; gap:16px;">
                <p style="font-size: 1.05rem; font-weight: 600; color: var(--text-soft); margin: 0;">
                  Click below to pay securely via UPI apps or Card
                  <strong style="color: #1a73e8; font-size: 1.3rem; display: block; margin-top: 6px;"><?= e(money_inr($amount)) ?></strong>
                </p>
                
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="pay">
                  <button type="submit" class="btn" style="width: 100%; background: #1a73e8; color: #fff; padding: 14px; border-radius: 10px; border: none; font-weight: 700; font-size:1.05rem; cursor: pointer; transition: background 0.2s; box-shadow:0 4px 12px rgba(26,115,232,0.24); display:flex; align-items:center; justify-content:center; gap:8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                    Pay Now
                  </button>
                </form>
              </div>

            <?php elseif ($order['payment_status'] === 'submitted'): ?>
              <div style="background: #fff8e1; border: 1px solid #ffe082; padding: 16px; border-radius: 12px; display: flex; flex-direction: column; gap: 12px;">
                <div>
                  <strong style="color: #b78103; font-size: 0.95rem; display: block; margin-bottom: 4px;">Payment Initiated</strong>
                  <span style="font-size: 0.88rem; color: #665;">Ref: <strong><?= e($order['transaction_id']) ?></strong></span>
                </div>
                <p style="font-size: 0.88rem; line-height: 1.4; color: #554; margin: 0;">
                  The payment transaction was initiated. If you completed the payment, refresh this page or click below to check status.
                </p>
                
                <div style="display:flex; gap:10px;">
                  <a href="<?= e(url('account/order_view.php?id=' . $order['id'])) ?>" class="btn" style="flex:1; background: #e65100; color: #fff; padding: 10px; border-radius: 8px; font-weight: 700; text-decoration: none; font-size: 0.9rem; text-align: center;">
                    Check Payment Status
                  </a>
                  
                  <?php
                  $amount = (float) $order['price'] * (int) $order['quantity'];
                  $orderCode = order_code((int) $order['id']);
                  $waMessage = "Hello Admin, I have initiated payment for Order " . $orderCode . ". Amount: " . money_inr($amount) . ". My transaction Ref is: " . $order['transaction_id'] . ". Please verify my order.";
                  $waUrl = "https://api.whatsapp.com/send?phone=" . WHATSAPP_NUMBER . "&text=" . urlencode($waMessage);
                  ?>
                  <a href="<?= e($waUrl) ?>" target="_blank" rel="noopener" style="display: flex; align-items: center; justify-content: center; gap: 6px; background: #25d366; color: #fff; padding: 10px; border-radius: 8px; font-weight: 700; text-decoration: none; font-size: 0.9rem; text-align: center;">
                    WhatsApp Support
                  </a>
                </div>
              </div>

            <?php elseif ($order['payment_status'] === 'paid'): ?>
              <div style="background: #e8f5e9; border: 1px solid #c8e6c9; padding: 16px; border-radius: 12px; color: #2e7d32; font-size: 0.9rem; line-height: 1.4;">
                <strong style="font-size: 0.95rem; display: block; margin-bottom: 4px; color: #1b5e20;">Payment Verified</strong>
                Your payment of <strong><?= e(money_inr((float) $order['price'] * (int) $order['quantity'])) ?></strong> has been verified automatically. Transaction Ref: <strong><?= e($order['transaction_id']) ?></strong>.
              </div>

            <?php elseif ($order['payment_status'] === 'failed'): ?>
              <div style="background: #ffebee; border: 1px solid #ffcdd2; padding: 16px; border-radius: 12px; color: #c62828; font-size: 0.9rem; line-height: 1.4; display: flex; flex-direction: column; gap: 12px;">
                <div>
                  <strong style="font-size: 0.95rem; display: block; margin-bottom: 4px; color: #b71c1c;">Payment Failed / Expired</strong>
                  The transaction <strong><?= e($order['transaction_id']) ?></strong> failed or expired. Please click below to try paying again.
                </div>
                
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="pay">
                  <button type="submit" class="btn" style="width: 100%; background: #c62828; color: #fff; padding: 12px; border-radius: 10px; border: none; font-weight: 700; cursor: pointer;">
                    Retry Payment
                  </button>
                </form>
              </div>
            <?php endif; ?>
          </div>
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
