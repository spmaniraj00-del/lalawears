<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login();
if ($user['role'] !== 'user') {
    flash('error', 'Only customer accounts can place orders.');
    redirect('index.php');
}

$cartMode = (int) ($_GET['cart'] ?? $_POST['cart_mode'] ?? 0) === 1;
$allowedSizes = ['S', 'M', 'L', 'XL', 'XXL'];
$sizesPost = is_array($_POST['sizes'] ?? null) ? $_POST['sizes'] : [];

/** @var array<int, array{product: array, qty: int}> $items */
$items = [];

if ($cartMode) {
    $cart = cart_items();
    if (!$cart) {
        flash('error', 'Your cart is empty.');
        redirect('cart.php');
    }
    $ids = array_map('intval', array_keys($cart));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND is_active = 1");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $p) {
        $items[] = [
            'product' => $p,
            'qty' => max(1, min(10, (int) ($cart[(int) $p['id']] ?? 1))),
        ];
    }
    if (!$items) {
        flash('error', 'Your cart is empty.');
        redirect('cart.php');
    }
} else {
    $productId = (int) ($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        flash('error', 'Product not found.');
        redirect('index.php#collection');
    }
    $items[] = [
        'product' => $product,
        'qty' => max(1, min(10, (int) ($_POST['quantity'] ?? 1))),
    ];
}

$error = '';
$values = [
    'customer_name' => $_POST['customer_name'] ?? $user['name'],
    'customer_phone' => $_POST['customer_phone'] ?? ($user['phone'] ?? ''),
    'shipping_address' => $_POST['shipping_address'] ?? '',
    'city' => $_POST['city'] ?? '',
    'state' => $_POST['state'] ?? '',
    'pincode' => $_POST['pincode'] ?? '',
    'landmark' => $_POST['landmark'] ?? '',
    'size' => $_POST['size'] ?? 'M',
    'quantity' => $_POST['quantity'] ?? '1',
    'notes' => $_POST['notes'] ?? '',
];

// Size per item: cart mode uses sizes[product_id], single mode uses size
function item_size(array $item, bool $cartMode, array $sizesPost, array $values): string
{
    $pid = (int) $item['product']['id'];
    $raw = $cartMode ? ($sizesPost[$pid] ?? 'M') : $values['size'];
    return strtoupper(trim((string) $raw));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    $name = trim((string) $values['customer_name']);
    $phone = trim((string) $values['customer_phone']);
    $address = trim((string) $values['shipping_address']);
    $city = trim((string) $values['city']);
    $state = trim((string) $values['state']);
    $pincode = preg_replace('/\D+/', '', (string) $values['pincode']) ?? '';
    $landmark = trim((string) $values['landmark']);
    $notes = trim((string) $values['notes']);

    $sizesValid = true;
    foreach ($items as $item) {
        if (!in_array(item_size($item, $cartMode, $sizesPost, $values), $allowedSizes, true)) {
            $sizesValid = false;
            break;
        }
    }

    if ($name === '' || strlen($name) < 2) {
        $error = 'Please enter your full name.';
    } elseif (!$sizesValid) {
        $error = 'Please choose a valid size for every item.';
    } elseif (!is_valid_indian_phone($phone)) {
        $error = 'Enter a valid 10-digit delivery phone number.';
    } elseif ($address === '' || strlen($address) < 8) {
        $error = 'Please enter your full house / street address.';
    } elseif ($city === '') {
        $error = 'Please enter your city.';
    } elseif ($state === '') {
        $error = 'Please enter your state.';
    } elseif (!preg_match('/^\d{6}$/', $pincode)) {
        $error = 'Enter a valid 6-digit PIN code.';
    } else {
        $phoneNorm = normalize_phone($phone);
        $fullAddress = $address
            . ($landmark !== '' ? ', Near ' . $landmark : '')
            . ', ' . $city . ', ' . $state . ' - ' . $pincode;

        $payMethod = $_POST['payment_method'] ?? 'upi';
        if (!in_array($payMethod, ['cod', 'upi'], true)) {
            $payMethod = 'upi';
        }

        $insert = db()->prepare(
            'INSERT INTO orders (
                user_id, product_id, product_name, product_image, product_description,
                price, quantity, size, customer_name, customer_phone, shipping_address,
                city, state, pincode, landmark, status, notes, payment_method, payment_status
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $orderIds = [];
        $grandTotal = 0.0;
        foreach ($items as $item) {
            $p = $item['product'];
            $qty = $item['qty'];
            $size = item_size($item, $cartMode, $sizesPost, $values);
            $insert->execute([
                (int) $user['id'],
                (int) $p['id'],
                $p['name'],
                $p['image'],
                $p['description'],
                (float) $p['price'],
                $qty,
                $size,
                $name,
                $phoneNorm,
                $fullAddress,
                $city,
                $state,
                $pincode,
                $landmark,
                'pending',
                $notes,
                $payMethod,
                'pending'
            ]);
            $orderId = (int) db()->lastInsertId();
            $orderIds[] = $orderId;
            $lineTotal = (float) $p['price'] * $qty;
            $grandTotal += $lineTotal;

            $trackMsg = $payMethod === 'upi' ? 'Order placed — awaiting UPI payment' : 'Order placed — Cash on Delivery';
            add_order_tracking($orderId, 'pending', $trackMsg, $city . ', ' . $state);

            notify_user(
                (int) $user['id'],
                'Order placed — Tracking ID ' . order_code($orderId),
                $p['name'] . ' (' . $size . ') — ' . money_inr($lineTotal) . ' · Track with ID ' . order_code($orderId) . '.',
                'tracking.php?q=' . order_code($orderId)
            );
            notify_admins(
                'New order #' . $orderId,
                $name . ' · ' . $p['name'] . ' · ' . money_inr($lineTotal) . ' · ' . $city . ', ' . $state,
                'admin/orders.php'
            );
        }

        if ($cartMode) {
            $_SESSION['cart'] = [];
        }

        if (count($orderIds) === 1) {
            flash('success', 'Order placed! Your Tracking ID is ' . order_code($orderIds[0]) . '.');
            redirect('account/order_view.php?id=' . $orderIds[0]);
        }
        $codes = implode(', ', array_map('order_code', $orderIds));
        flash('success', count($orderIds) . ' orders placed for ' . money_inr($grandTotal) . '. Tracking IDs: ' . $codes . '.');
        redirect('account/index.php#orders');
    }
}

$pageTitle = 'Checkout | ' . APP_NAME;
require __DIR__ . '/../includes/header.php';

$grandTotal = 0.0;
foreach ($items as $item) {
    $grandTotal += (float) $item['product']['price'] * $item['qty'];
}
$itemCount = array_sum(array_column($items, 'qty'));

// Single-product mode keeps the interactive qty stepper
$single = !$cartMode ? $items[0] : null;
$qtyValue = $single ? $single['qty'] : 0;
?>

<main class="checkout-page">
  <?php if ($error): ?>
    <div class="flash flash-error" style="position:static;transform:none;margin:0 auto 20px;"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" class="checkout-grid" <?= $single ? 'data-price="' . e((string) $single['product']['price']) . '"' : '' ?>>
    <?= csrf_field() ?>
    <?php if ($cartMode): ?>
      <input type="hidden" name="cart_mode" value="1">
    <?php else: ?>
      <input type="hidden" name="product_id" value="<?= (int) $single['product']['id'] ?>">
    <?php endif; ?>

    <div class="checkout-left">

      <section class="checkout-card">
        <h2 class="checkout-card-title">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
          Contact Information
        </h2>
        <div class="ck-field">
          <label for="customer_name">Full Name <em>*</em></label>
          <input type="text" id="customer_name" name="customer_name" required maxlength="100"
                 placeholder="Enter your full name" value="<?= e((string) $values['customer_name']) ?>">
        </div>
        <div class="ck-field-row">
          <div class="ck-field">
            <label for="ck-email">Email</label>
            <input type="email" id="ck-email" value="<?= e((string) ($user['email'] ?? '')) ?>" readonly>
          </div>
          <div class="ck-field">
            <label for="customer_phone">Phone Number <em>*</em></label>
            <input type="tel" id="customer_phone" name="customer_phone" required maxlength="15" inputmode="numeric"
                   placeholder="10-digit mobile number" value="<?= e((string) $values['customer_phone']) ?>">
          </div>
        </div>
      </section>

      <section class="checkout-card">
        <h2 class="checkout-card-title">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
          Shipping Address
        </h2>
        <div class="ck-field">
          <label for="shipping_address">Detailed Address <em>*</em></label>
          <textarea id="shipping_address" name="shipping_address" required
                    placeholder="House, Road, Area"><?= e((string) $values['shipping_address']) ?></textarea>
        </div>
        <div class="ck-field-row">
          <div class="ck-field">
            <label for="state">State <em>*</em></label>
            <input type="text" id="state" name="state" required maxlength="80"
                   placeholder="Enter your state" value="<?= e((string) $values['state']) ?>">
          </div>
          <div class="ck-field">
            <label for="city">City / District <em>*</em></label>
            <input type="text" id="city" name="city" required maxlength="80"
                   placeholder="Enter your city" value="<?= e((string) $values['city']) ?>">
          </div>
        </div>
        <div class="ck-field-row">
          <div class="ck-field">
            <label for="pincode">PIN Code <em>*</em></label>
            <input type="text" id="pincode" name="pincode" required maxlength="6" inputmode="numeric"
                   placeholder="6-digit PIN code" value="<?= e((string) $values['pincode']) ?>">
          </div>
          <div class="ck-field">
            <label for="landmark">Landmark</label>
            <input type="text" id="landmark" name="landmark" maxlength="120"
                   placeholder="Nearby landmark (optional)" value="<?= e((string) $values['landmark']) ?>">
          </div>
        </div>
        <div class="ck-field">
          <label for="notes">Note for Delivery</label>
          <textarea id="notes" name="notes"
                    placeholder="Special instructions (optional)"><?= e((string) $values['notes']) ?></textarea>
        </div>
      </section>
    </div>

    <aside class="checkout-right">

      <section class="checkout-card">
        <div class="summary-head">
          <h2 class="checkout-card-title no-icon">Order Summary</h2>
          <a class="modify-link" href="<?= e($cartMode ? url('cart.php') : url('product.php?id=' . (int) $single['product']['id'])) ?>">Modify</a>
        </div>

        <?php foreach ($items as $item): $p = $item['product']; $pid = (int) $p['id']; ?>
          <div class="summary-product">
            <img src="<?= e(product_image_url($p['image'])) ?>" alt="<?= e($p['name']) ?>">
            <div class="sp-info">
              <p class="sp-name"><?= e($p['name']) ?></p>
              <?php if ($cartMode): ?>
                <p class="sp-sub">
                  Qty: <?= $item['qty'] ?> · Size
                  <select name="sizes[<?= $pid ?>]" class="sp-size-select" required>
                    <?php foreach ($allowedSizes as $sz): ?>
                      <option value="<?= $sz ?>" <?= (($sizesPost[$pid] ?? 'M') === $sz) ? 'selected' : '' ?>><?= $sz ?></option>
                    <?php endforeach; ?>
                  </select>
                </p>
              <?php else: ?>
                <p class="sp-sub">Qty: <span data-qty-label><?= $item['qty'] ?></span></p>
              <?php endif; ?>
            </div>
            <div class="sp-price">
              <span class="sp-mrp"><?= e(money_inr(product_mrp((float) $p['price']) * $item['qty'])) ?></span>
              <strong <?= $cartMode ? '' : 'data-line-total' ?>><?= e(money_inr((float) $p['price'] * $item['qty'])) ?></strong>
            </div>
          </div>
        <?php endforeach; ?>

        <?php if (!$cartMode): ?>
          <div class="summary-options">
            <div class="summary-opt">
              <span>Size</span>
              <select name="size" required>
                <?php foreach ($allowedSizes as $sz): ?>
                  <option value="<?= $sz ?>" <?= ($values['size'] === $sz) ? 'selected' : '' ?>><?= $sz ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="summary-opt">
              <span>Quantity</span>
              <div class="qty-stepper small">
                <button type="button" class="qty-btn" data-qty="-1" aria-label="Decrease quantity">−</button>
                <input type="number" name="quantity" id="quantity" value="<?= $qtyValue ?>" min="1" max="10" readonly>
                <button type="button" class="qty-btn" data-qty="1" aria-label="Increase quantity">+</button>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <div class="summary-rows">
          <div class="summary-row"><span>Subtotal (<?= $itemCount ?> item<?= $itemCount === 1 ? '' : 's' ?>)</span><strong data-subtotal><?= e(money_inr($grandTotal)) ?></strong></div>
          <div class="summary-row"><span>Shipping</span><strong class="free-ship">FREE</strong></div>
          <div class="summary-row total"><span>Total</span><strong data-total><?= e(money_inr($grandTotal)) ?></strong></div>
        </div>

        <div class="delivery-chip">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="1"></rect><path d="M16 8h4l3 3v5h-7V8z"></path><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
          Delivery within <strong>5-7 Days</strong> after confirmation
        </div>
      </section>

      <section class="checkout-card">
        <h2 class="checkout-card-title no-icon">Payment Method</h2>

        <div class="pay-option disabled">
          <input type="radio" name="payment_method" value="cod" disabled>
          <span class="pay-icon" style="background: var(--bg-soft); color: var(--text-soft);">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"></rect><circle cx="12" cy="12" r="2.5"></circle><path d="M6 12h.01M18 12h.01"></path></svg>
          </span>
          <span class="pay-body">
            <span class="pay-name">Cash on Delivery <em class="pay-badge soon">Coming Soon</em></span>
            <small>Pay when you receive your order</small>
          </span>
        </div>

        <label class="pay-option selected">
          <input type="radio" name="payment_method" value="upi" checked>
          <span class="pay-icon" style="background:#e8f0fe; color:#1a73e8;">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
          </span>
          <span class="pay-body">
            <span class="pay-name">UPI / QR Code <em class="pay-badge popular" style="background:#1a73e8;">Instant</em></span>
            <small>Pay instantly via dynamic QR code scanning</small>
          </span>
        </label>

        <div class="pay-option disabled">
          <span class="pay-icon">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
          </span>
          <span class="pay-body">
            <span class="pay-name">Card Payment <em class="pay-badge soon">CoSoonming </em></span>
            <small>Visa, Mastercard, RuPay</small>
          </span>
        </div>
      </section>

      <section class="checkout-card checkout-confirm">
        <label class="terms-check">
          <input type="checkbox" required>
          <span>I agree to the <a href="<?= e(url('page.php?p=terms')) ?>" target="_blank">Terms &amp; Conditions</a>, <a href="<?= e(url('page.php?p=privacy')) ?>" target="_blank">Privacy Policy</a></span>
        </label>
        <button type="submit" class="btn-confirm-order">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
          Confirm Order · <span data-total-btn><?= e(money_inr($grandTotal)) ?></span>
        </button>
        <div class="trust-row">
          <span>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            Secure Payment
          </span>
          <span>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
            Easy Returns
          </span>
          <span>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="1"></rect><path d="M16 8h4l3 3v5h-7V8z"></path><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
            Fast Delivery
          </span>
        </div>
      </section>
    </aside>
  </form>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const radioButtons = document.querySelectorAll('input[name="payment_method"]');
    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.pay-option').forEach(el => {
                el.classList.remove('selected');
            });
            if (this.checked) {
                this.closest('.pay-option').classList.add('selected');
            }
        });
    });
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
