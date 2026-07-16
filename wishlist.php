<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = $_POST['action'] ?? 'toggle';
    $productId = (int) ($_POST['product_id'] ?? 0);
    $next = (string) ($_POST['next'] ?? '');

    if ($action === 'toggle' && $productId > 0) {
        $added = wishlist_toggle($productId, $user);
        flash('success', $added ? 'Added to wishlist.' : 'Removed from wishlist.');
    }
    if ($action === 'remove' && $productId > 0) {
        if (wishlist_has($productId, $user)) {
            wishlist_toggle($productId, $user);
        }
        flash('success', 'Removed from wishlist.');
    }

    if ($next !== '' && (str_starts_with($next, 'product.php') || str_starts_with($next, 'wishlist.php') || str_starts_with($next, 'index.php'))) {
        redirect($next);
    }
    redirect('wishlist.php');
}

$items = wishlist_products($user);
$pageTitle = 'Wishlist | ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>

<div class="page-shell">
  <div class="panel wide">
    <p class="eyebrow">Saved pieces</p>
    <h1>Your Wishlist</h1>
    <p class="lead"><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?> saved. Tap the heart on any product to add or remove.</p>

    <?php if (!$items): ?>
      <p class="lead" style="margin-top:28px;">Wishlist is empty. Browse the shop and tap the heart icon.</p>
      <a class="btn" href="<?= e(url('index.php')) ?>#deals" style="margin-top:16px;display:inline-flex;">Shop collection</a>
    <?php else: ?>
      <div class="wishlist-grid">
        <?php foreach ($items as $product): ?>
          <?php
            $gallery = product_gallery_paths($product);
            $productUrl = url('product.php?id=' . (int) $product['id']);
          ?>
          <article class="deal-card wishlist-card">
            <a class="deal-media" href="<?= e($productUrl) ?>" data-hover-slide>
              <?php foreach ($gallery as $i => $imgPath): ?>
                <img src="<?= e(product_image_url($imgPath)) ?>" alt="<?= e($product['name']) ?>" <?= $i === 0 ? '' : 'loading="lazy"' ?> class="<?= $i === 0 ? 'is-active' : '' ?>">
              <?php endforeach; ?>
              <span class="deal-tag">Saved</span>
            </a>
            <div class="deal-body">
              <h3 class="deal-name"><a href="<?= e($productUrl) ?>"><?= e($product['name']) ?></a></h3>
              <div class="deal-meta">
                <span class="deal-price"><?= e(money_inr($product['price'])) ?></span>
              </div>
              <div class="wishlist-card-actions">
                <a class="deal-buy" href="<?= e($productUrl) ?>">View</a>
                <form method="post" action="<?= e(url('wishlist.php')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                  <input type="hidden" name="next" value="wishlist.php">
                  <button type="submit" class="btn-wish is-active" aria-label="Remove from wishlist">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                  </button>
                </form>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
