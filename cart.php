<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = $_POST['action'] ?? '';
    $productId = (int) ($_POST['product_id'] ?? 0);

    if ($action === 'update' && $productId > 0) {
        cart_set($productId, (int) ($_POST['quantity'] ?? 1));
    } elseif ($action === 'remove' && $productId > 0) {
        cart_remove($productId);
        flash('success', 'Item removed from cart.');
    }
    redirect('cart.php');
}

$cart = cart_items();
$items = [];
$total = 0.0;

if ($cart) {
    $ids = array_map('intval', array_keys($cart));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        "SELECT * FROM products WHERE id IN ($placeholders) AND is_active = 1"
    );
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $p) {
        $qty = (int) ($cart[(int) $p['id']] ?? 1);
        $subtotal = (float) $p['price'] * $qty;
        $total += $subtotal;
        $items[] = ['product' => $p, 'qty' => $qty, 'subtotal' => $subtotal];
    }
}

$pageTitle = 'My Cart | ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>

<main class="cart-page">
  <div class="cart-head reveal-up">
    <h1>
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="9" cy="21" r="1"></circle>
        <circle cx="20" cy="21" r="1"></circle>
        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
      </svg>
      My Cart
    </h1>
    <p><?= cart_count() ?> item<?= cart_count() === 1 ? '' : 's' ?> in your cart</p>
  </div>

  <?php if (!$items): ?>
    <div class="cart-empty reveal-up">
      <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="9" cy="21" r="1"></circle>
        <circle cx="20" cy="21" r="1"></circle>
        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
      </svg>
      <p>Your cart is empty.</p>
      <a class="btn-buy-now" href="<?= e(url('index.php')) ?>#deals">Continue Shopping</a>
    </div>
  <?php else: ?>
    <div class="cart-grid reveal-up">
      <div class="cart-list">
        <?php foreach ($items as $item): $p = $item['product']; ?>
          <article class="cart-item">
            <a class="cart-item-img" href="<?= e(url('product.php?id=' . (int) $p['id'])) ?>">
              <img src="<?= e(product_image_url($p['image'])) ?>" alt="<?= e($p['name']) ?>">
            </a>
            <div class="cart-item-body">
              <a class="cart-item-name" href="<?= e(url('product.php?id=' . (int) $p['id'])) ?>"><?= e($p['name']) ?></a>
              <div class="cart-item-controls">
                <span class="cart-item-price"><?= e(money_inr($item['subtotal'])) ?></span>
                <form method="post" class="cart-qty-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                  <div class="qty-stepper small">
                    <button type="submit" name="quantity" value="<?= $item['qty'] - 1 ?>" class="qty-btn" <?= $item['qty'] <= 1 ? 'disabled' : '' ?>>−</button>
                    <input type="number" value="<?= $item['qty'] ?>" readonly>
                    <button type="submit" name="quantity" value="<?= $item['qty'] + 1 ?>" class="qty-btn" <?= $item['qty'] >= 10 ? 'disabled' : '' ?>>+</button>
                  </div>
                  <input type="hidden" name="action" value="update">
                </form>
              </div>
            </div>
            <form method="post" class="cart-remove-form">
              <?= csrf_field() ?>
              <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
              <button type="submit" name="action" value="remove" class="cart-remove-x" aria-label="Remove item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
              </button>
            </form>
          </article>
        <?php endforeach; ?>
      </div>

      <aside class="cart-summary">
        <h2>Order Summary</h2>
        <div class="cart-summary-items">
          <?php foreach ($items as $item): $p = $item['product']; ?>
            <div class="cart-summary-line">
              <span class="csl-name"><?= e($p['name']) ?></span>
              <span class="csl-price">
                <s><?= e(money_inr(product_mrp((float) $p['price']) * $item['qty'])) ?></s>
                <strong><?= e(money_inr($item['subtotal'])) ?></strong>
              </span>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="cart-summary-row"><span>Subtotal (<?= cart_count() ?> item<?= cart_count() === 1 ? '' : 's' ?>)</span><strong><?= e(money_inr($total)) ?></strong></div>
        <div class="cart-summary-row"><span>Delivery</span><strong class="free">FREE</strong></div>
        <div class="cart-summary-row total"><span>Total</span><strong><?= e(money_inr($total)) ?></strong></div>

        <a class="btn-place-order" href="<?= e(($user && $user['role'] === 'user')
            ? url('account/checkout.php?cart=1')
            : url('auth/login.php?next=' . urlencode('account/checkout.php?cart=1'))) ?>">
          Place Order
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </a>
        <a class="btn-continue-shopping" href="<?= e(url('index.php')) ?>#deals">Continue Shopping</a>
      </aside>
    </div>
  <?php endif; ?>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
