<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM orders WHERE id=? AND user_id=? LIMIT 1');
$stmt->execute([$id, (int) $user['id']]);
$order = $stmt->fetch();

if (!$order) {
    flash('error', 'Order not found.');
    redirect('account/index.php');
}
if (($order['payment_method'] ?? '') !== 'upi') {
    flash('error', 'Online payment is not available for this order.');
    redirect('account/order_view.php?id=' . $id);
}

$error = '';
$check = null;

if (!empty($_GET['returned']) && !empty($order['transaction_id'])) {
    $check = terminalx_check_payment((string) $order['transaction_id']);
    terminalx_apply_status($order, $check);
    $stmt->execute([$id, (int) $user['id']]);
    $order = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'start_payment') {
        if (($order['payment_status'] ?? '') === 'paid') {
            redirect('account/order_view.php?id=' . $id);
        }

        // Reuse a live hosted URL instead of accidentally creating duplicates.
        if (($order['payment_status'] ?? '') === 'submitted' && !empty($order['payment_url'])) {
            header('Location: ' . $order['payment_url'], true, 303);
            exit;
        }

        $created = terminalx_create_payment($order, $user);
        if ($created['ok']) {
            db()->prepare(
                "UPDATE orders SET payment_status='submitted', transaction_id=?, payment_url=?,
                 payment_checked_at='', updated_at=datetime('now','localtime')
                 WHERE id=? AND payment_status!='paid'"
            )->execute([$created['gateway_order_id'], $created['payment_url'], $id]);
            header('Location: ' . $created['payment_url'], true, 303);
            exit;
        }
        $error = (string) ($created['error'] ?? 'Could not start payment.');
    }

    if ($action === 'check_status' && !empty($order['transaction_id'])) {
        $check = terminalx_check_payment((string) $order['transaction_id']);
        terminalx_apply_status($order, $check);
        $stmt->execute([$id, (int) $user['id']]);
        $order = $stmt->fetch();
        if (empty($check['ok'])) {
            $error = (string) ($check['error'] ?? 'Could not verify payment.');
        }
    }
}

$amount = (float) $order['price'] * (int) $order['quantity'];
$pageTitle = 'Secure Payment | ' . APP_NAME;
require __DIR__ . '/../includes/header.php';
?>

<main class="checkout-page">
  <section class="payment-shell">
    <div class="payment-summary-card">
      <p class="eyebrow">Secure payment</p>
      <h1>Complete your order</h1>
      <p class="lead"><?= e(order_code($id)) ?> · <?= e($order['product_name']) ?></p>

      <?php if ($error): ?>
        <div class="flash flash-error" style="position:static;transform:none;margin:18px 0;"><?= e($error) ?></div>
      <?php endif; ?>

      <div class="payment-amount">
        <span>Amount payable</span>
        <strong><?= e(money_inr($amount)) ?></strong>
      </div>

      <?php if (($order['payment_status'] ?? '') === 'paid'): ?>
        <div class="payment-state success">
          <strong>Payment successful</strong>
          <span>Your order is confirmed.<?= !empty($order['payment_utr']) ? ' UTR: ' . e($order['payment_utr']) : '' ?></span>
        </div>
        <a class="btn" href="<?= e(url('account/order_view.php?id=' . $id)) ?>">View order</a>
      <?php elseif (($order['payment_status'] ?? '') === 'submitted'): ?>
        <div class="payment-state pending">
          <strong>Payment initiated</strong>
          <span>Complete payment on the secure gateway, then verify its status here.</span>
        </div>
        <div class="payment-actions">
          <?php if (!empty($order['payment_url'])): ?>
            <a class="btn" href="<?= e($order['payment_url']) ?>">Continue payment</a>
          <?php endif; ?>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="action" value="check_status">
            <button class="btn-outline" type="submit">Check payment status</button>
          </form>
        </div>
      <?php else: ?>
        <?php if (($order['payment_status'] ?? '') === 'failed'): ?>
          <div class="payment-state failed">
            <strong>Previous payment failed or expired</strong>
            <span>You can safely create a new payment.</span>
          </div>
        <?php endif; ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="action" value="start_payment">
          <button class="btn payment-pay-button" type="submit" <?= terminalx_configured() ? '' : 'disabled' ?>>
            Pay <?= e(money_inr($amount)) ?> securely
          </button>
        </form>
        <?php if (!terminalx_configured()): ?>
          <p class="payment-help">Online payment is temporarily unavailable. Contact support.</p>
        <?php endif; ?>
      <?php endif; ?>

      <p class="payment-help">The gateway opens on a separate secure page. Payment is confirmed only after server verification.</p>
      <a class="btn-outline" href="<?= e(url('account/order_view.php?id=' . $id)) ?>">Back to order</a>
    </div>
  </section>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
