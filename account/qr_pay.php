<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login();

// Support single ID or multiple IDs (comma-separated list for cart checkout orders)
$idString = trim((string) ($_GET['id'] ?? $_GET['ids'] ?? $_POST['ids'] ?? ''));
if ($idString === '') {
    flash('error', 'No order specified for QR payment.');
    redirect('account/index.php');
}

$orderIds = array_map('intval', explode(',', $idString));
$orderIds = array_filter($orderIds, fn($val) => $val > 0);

if (!$orderIds) {
    flash('error', 'Invalid order list.');
    redirect('account/index.php');
}

$placeholders = implode(',', array_fill(0, count($orderIds), '?'));
$stmt = db()->prepare("SELECT * FROM orders WHERE id IN ($placeholders) AND user_id = ?");
$params = array_merge($orderIds, [(int) $user['id']]);
$stmt->execute($params);
$orders = $stmt->fetchAll();

if (count($orders) !== count($orderIds)) {
    flash('error', 'One or more orders could not be found.');
    redirect('account/index.php');
}

$grandTotal = 0.0;
$descriptions = [];
foreach ($orders as $order) {
    $grandTotal += (float) $order['price'] * (int) $order['quantity'];
    $descriptions[] = $order['product_name'] . ' (x' . $order['quantity'] . ')';
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $utr = trim((string) ($_POST['payment_utr'] ?? ''));

    if ($utr === '' || !preg_match('/^\d{12}$/', $utr)) {
        $error = 'Please enter a valid 12-digit UPI Transaction ID / UTR number.';
    } else {
        // Update all orders with the UTR number and set status to submitted
        $update = db()->prepare(
            "UPDATE orders SET payment_status = 'submitted', payment_utr = ?, updated_at = datetime('now','localtime') WHERE id = ?"
        );
        foreach ($orders as $order) {
            $update->execute([$utr, (int) $order['id']]);
            add_order_tracking(
                (int) $order['id'],
                'pending',
                'Payment UTR submitted: ' . $utr . ' · Awaiting Admin verification',
                (string) ($order['city'] ?? '')
            );
            notify_admins(
                'Payment UTR Submitted for #' . $order['id'],
                $user['name'] . ' submitted UTR: ' . $utr . ' for ' . money_inr((float) $order['price'] * (int) $order['quantity']),
                'admin/order_view.php?id=' . $order['id']
            );
        }

        flash('success', 'UTR Code submitted successfully! Admin will verify and confirm your order shortly.');
        if (count($orders) === 1) {
            redirect('account/order_view.php?id=' . $orders[0]['id']);
        }
        redirect('account/index.php#orders');
    }
}

$pageTitle = 'Pay with UPI QR Code | ' . APP_NAME;
require __DIR__ . '/../includes/header.php';
?>

<main class="checkout-page" style="display:flex; justify-content:center; align-items:center; min-height:80vh; padding:40px 20px; margin-top: 80px;">
  <section class="payment-shell" style="background:#fff; width:100%; max-width:480px; padding:30px; border-radius:24px; box-shadow:0 10px 40px rgba(0,0,0,0.05); border:1px solid rgba(0,0,0,0.06); text-align:center;">
    
    <div style="margin-bottom:20px;">
      <span style="display:inline-block; padding:6px 14px; background:#fce8e6; color:#c5221f; font-size:12px; font-weight:700; border-radius:99px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:12px;">UPI QR Payment</span>
      <h1 style="font-size:24px; font-weight:900; margin:0 0 6px; color:#111;">Scan &amp; Pay</h1>
      <p style="color:#666; font-size:14px; margin:0; line-height:1.4;"><?= implode(', ', $descriptions) ?></p>
    </div>

    <?php if ($error): ?>
      <div class="flash flash-error" style="position:static; transform:none; margin:15px 0; text-align:left;"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- QR Code Section -->
    <div style="background:#f9f9f9; padding:20px; border-radius:20px; margin:20px 0; border:1px dashed rgba(0,0,0,0.1); display:flex; flex-direction:column; align-items:center; gap:12px;">
      <!-- QR Image Link -->
      <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=<?= urlencode('upi://pay?pa=' . UPI_ID . '&pn=' . urlencode(UPI_NAME) . '&am=' . number_format($grandTotal, 2, '.', '') . '&cu=INR&tn=' . urlencode(implode('-', array_map('order_code', $orderIds)))) ?>" 
           alt="Scan to Pay QR Code" 
           style="width:230px; height:230px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.06); background:#fff; padding:10px;" />
      
      <div>
        <p style="margin:5px 0 0; font-size:13px; color:#555;">Scan QR using Google Pay, PhonePe, Paytm or BHIM app.</p>
        <p style="margin:5px 0 0; font-size:14px; font-weight:700; color:#111;">Payee UPI: <span style="font-family:monospace;"><?= e(UPI_ID) ?></span></p>
      </div>
    </div>

    <!-- Amount Panel -->
    <div style="display:flex; justify-content:space-between; align-items:center; background:#f4f4f4; padding:16px 20px; border-radius:16px; margin-bottom:24px;">
      <span style="font-weight:700; color:#555; font-size:15px;">Amount to Pay</span>
      <strong style="font-size:22px; font-weight:900; color:#c5221f;"><?= e(money_inr($grandTotal)) ?></strong>
    </div>

    <!-- Payment submission form -->
    <form method="post" style="text-align:left;">
      <?= csrf_field() ?>
      <input type="hidden" name="ids" value="<?= e($idString) ?>">
      
      <div class="form-group" style="margin-bottom:20px;">
        <label for="payment_utr" style="display:block; font-weight:700; margin-bottom:8px; color:#333; font-size:14px;">UPI UTR / Transaction ID (12-Digits) <em>*</em></label>
        <input type="text" id="payment_utr" name="payment_utr" required maxlength="12" pattern="\d{12}" inputmode="numeric"
               placeholder="e.g. 304561284561" style="width:100%; padding:12px; border:1.5px solid rgba(0,0,0,0.12); border-radius:12px; font-size:15px; outline:none; transition:border-color 0.2s;"
               value="<?= e($_POST['payment_utr'] ?? '') ?>">
        <small style="display:block; color:#777; font-size:12px; margin-top:6px; line-height:1.4;">Submit the 12-digit transaction ID or UTR number from your payment receipt to help us verify your transaction.</small>
      </div>

      <button type="submit" class="btn" style="width:100%; display:flex; justify-content:center; align-items:center; gap:8px; padding:14px; font-size:16px; font-weight:700; border-radius:12px; background:#c5221f; color:#fff; border:none; cursor:pointer;">
        Submit Verification ID
      </button>
    </form>

    <div style="margin-top:20px; padding-top:15px; border-top:1px solid rgba(0,0,0,0.06);">
      <p style="font-size:12px; color:#888; margin:0 0 10px; line-height:1.4;">Need help? Contact founder on WhatsApp: <a href="<?= e(WHATSAPP_URL) ?>" target="_blank" style="color:#25d366; font-weight:700; text-decoration:none;">WhatsApp Chat</a></p>
      <a href="<?= e(url('account/order_view.php?id=' . $orders[0]['id'])) ?>" style="font-size:13px; font-weight:700; color:#555; text-decoration:none;">Cancel / Go back to order</a>
    </div>

  </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const utrInput = document.getElementById('payment_utr');
    if(utrInput) {
        utrInput.addEventListener('focus', function() {
            this.style.borderColor = '#c5221f';
        });
        utrInput.addEventListener('blur', function() {
            this.style.borderColor = 'rgba(0,0,0,0.12)';
        });
        // Limit input to digits only
        utrInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
    }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
