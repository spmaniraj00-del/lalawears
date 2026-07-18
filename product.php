<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$productId = (int) ($_GET['id'] ?? $_POST['product_id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM products WHERE id = ? AND is_active = 1 LIMIT 1');
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    flash('error', 'Product not found.');
    redirect('index.php#deals');
}

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_to_cart') {
        $qty = max(1, min(10, (int) ($_POST['quantity'] ?? 1)));
        cart_add((int) $product['id'], $qty);
        flash('success', $product['name'] . ' added to your cart.');
        redirect('product.php?id=' . (int) $product['id']);
    }

    if ($action === 'toggle_wishlist') {
        $added = wishlist_toggle((int) $product['id'], $user);
        flash('success', $added ? 'Added to wishlist.' : 'Removed from wishlist.');
        redirect('product.php?id=' . (int) $product['id']);
    }

    if ($action === 'add_review') {
        if (!$user || $user['role'] !== 'user') {
            flash('error', 'Please sign in to write a review.');
            redirect('auth/login.php?next=' . urlencode('product.php?id=' . (int) $product['id']));
        }
        $rating = max(1, min(5, (int) ($_POST['rating'] ?? 5)));
        $comment = trim((string) ($_POST['comment'] ?? ''));
        if (mb_strlen($comment) > 1000) {
            $comment = mb_substr($comment, 0, 1000);
        }

        $images = [];
        foreach (array_slice(uploaded_files_list('photos'), 0, 3) as $file) {
            try {
                $path = safe_upload_image($file, 'review');
                if ($path !== null) {
                    $images[] = $path;
                }
            } catch (RuntimeException $ex) {
                flash('error', $ex->getMessage());
                redirect('product.php?id=' . (int) $product['id'] . '#reviews');
            }
        }

        db()->prepare(
            "INSERT INTO reviews (product_id, user_id, rating, comment, images)
             VALUES (?, ?, ?, ?, ?)
             ON CONFLICT(product_id, user_id) DO UPDATE SET
               rating=excluded.rating,
               comment=excluded.comment,
               images=excluded.images,
               created_at=datetime('now','localtime')"
        )
            ->execute([
                (int) $product['id'],
                (int) $user['id'],
                $rating,
                $comment,
                $images ? json_encode($images) : '',
            ]);
        flash('success', $images
            ? 'Thank you! Your review and photos have been posted.'
            : 'Thank you! Your review has been posted.');
        redirect('product.php?id=' . (int) $product['id'] . '#reviews');
    }
}

$rating = product_rating_summary((int) $product['id']);
$sold = product_sold_count((int) $product['id']);
$reviews = product_reviews((int) $product['id']);
$mrp = product_mrp((float) $product['price']);
$off = product_discount_percent((float) $product['price']);
$gallery = product_gallery_paths($product);
$inWishlist = wishlist_has((int) $product['id'], $user);

$checkoutUrl = ($user && $user['role'] === 'user')
    ? url('account/checkout.php?product_id=' . (int) $product['id'])
    : url('auth/login.php?next=' . urlencode('account/checkout.php?product_id=' . (int) $product['id']));

$highlights = [
    'Premium 240 GSM cotton fabric with a soft, substantial feel',
    'Durable stitched embroidery inspired by Bihar heritage',
    'Comfortable regular fit, perfect for everyday wear',
    'Colorfast, machine-washable fabric that holds its shape',
    'Available in sizes S to XXL with fast doorstep delivery',
];

$pageTitle = $product['name'] . ' | ' . APP_NAME;
require __DIR__ . '/includes/header.php';

function star_row(float $avg): string
{
    $html = '<span class="stars" aria-hidden="true">';
    for ($i = 1; $i <= 5; $i++) {
        $filled = $avg >= $i - 0.25;
        $html .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="' . ($filled ? '#f7941d' : 'none') . '" stroke="' . ($filled ? '#f7941d' : '#c8c8c8') . '" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>';
    }
    return $html . '</span>';
}
?>

<main class="product-page">
  <section class="product-hero">
    <div class="product-hero-grid">

      <div class="product-gallery reveal-up">
        <div class="product-slider" data-product-gallery data-count="<?= count($gallery) ?>">
          <div class="product-gallery-frame">
            <?php foreach ($gallery as $i => $imgPath): ?>
              <img src="<?= e(product_image_url($imgPath)) ?>" alt="<?= e($product['name']) ?> — photo <?= $i + 1 ?>" class="gallery-main<?= $i === 0 ? ' is-active' : '' ?>" data-gallery-index="<?= $i ?>" draggable="false">
            <?php endforeach; ?>

            <?php if (count($gallery) > 1): ?>
              <button type="button" class="gallery-nav gallery-prev" data-gallery-prev aria-label="Previous photo">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
              </button>
              <button type="button" class="gallery-nav gallery-next" data-gallery-next aria-label="Next photo">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
              </button>
              <div class="gallery-dots" aria-hidden="true">
                <?php foreach ($gallery as $i => $_): ?>
                  <button type="button" class="gallery-dot<?= $i === 0 ? ' is-active' : '' ?>" data-gallery-goto="<?= $i ?>" aria-label="Photo <?= $i + 1 ?>"></button>
                <?php endforeach; ?>
              </div>
              <span class="gallery-counter"><span data-gallery-current>1</span> / <?= count($gallery) ?></span>
            <?php endif; ?>
          </div>

          <?php if (count($gallery) > 1): ?>
            <div class="product-thumbs" role="tablist" aria-label="Product photos">
              <?php foreach ($gallery as $i => $imgPath): ?>
                <button type="button" class="product-thumb<?= $i === 0 ? ' is-active' : '' ?>" data-gallery-goto="<?= $i ?>" aria-label="Photo <?= $i + 1 ?>">
                  <img src="<?= e(product_image_url($imgPath)) ?>" alt="">
                </button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="product-info reveal-up">
        <div class="product-rating-row">
          <?= star_row($rating['avg']) ?>
          <span class="rating-score"><?= number_format($rating['avg'], 1) ?> / 5.0</span>
          <span class="rating-chip"><?= $rating['count'] ?> Review<?= $rating['count'] === 1 ? '' : 's' ?></span>
          <span class="rating-chip"><?= $sold ?> Sold</span>
        </div>

        <h1 class="product-title"><?= e($product['name']) ?></h1>

        <div class="product-price-row">
          <span class="price-now"><?= e(money_inr($product['price'])) ?></span>
          <span class="price-mrp"><?= e(money_inr($mrp)) ?></span>
          <span class="price-off"><?= $off ?>% OFF</span>
        </div>

        <form method="post" class="product-actions-form">
          <?= csrf_field() ?>
          <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">

          <p class="qty-label">Quantity</p>
          <div class="qty-stepper">
            <button type="button" class="qty-btn" data-qty="-1" aria-label="Decrease quantity">−</button>
            <input type="number" name="quantity" id="quantity" value="1" min="1" max="10" readonly>
            <button type="button" class="qty-btn" data-qty="1" aria-label="Increase quantity">+</button>
          </div>

          <div class="product-cta-row">
            <a class="btn-buy-now" href="<?= e($checkoutUrl) ?>">Buy Now</a>
            <button type="submit" name="action" value="add_to_cart" class="btn-add-cart">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="9" cy="21" r="1"></circle>
                <circle cx="20" cy="21" r="1"></circle>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
              </svg>
              Add to Cart
            </button>
            <button type="submit" name="action" value="toggle_wishlist" class="btn-wish<?= $inWishlist ? ' is-active' : '' ?>" aria-label="<?= $inWishlist ? 'Remove from wishlist' : 'Add to wishlist' ?>" aria-pressed="<?= $inWishlist ? 'true' : 'false' ?>">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="<?= $inWishlist ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
              </svg>
            </button>
          </div>
        </form>

        <div class="key-highlights">
          <p class="kh-title"><span class="kh-bar"></span>Key Highlights</p>
          <ul>
            <?php foreach ($highlights as $point): ?>
              <li><?= e($point) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <section class="product-description reveal-up">
    <p class="section-bar-title"><span class="kh-bar dark"></span>Detailed Description</p>
    <p class="description-text"><?= e($product['description']) ?>
      Crafted with care by LALA WEARS in Bettiah, Bihar, this piece combines premium 240 GSM cotton with
      finely stitched detailing for a finish that lasts wash after wash. Designed for everyday confidence,
      it offers a clean regular fit, breathable comfort, and heritage-inspired style you can wear anywhere.
      Every order is packed and shipped with care, straight from our workshop to your doorstep.</p>
  </section>

  <section class="product-reviews reveal-up" id="reviews">
    <div class="reviews-header">
      <p class="section-bar-title"><span class="kh-bar dark"></span>Customer Reviews</p>
      <div class="reviews-header-right">
        <?php if ($user && $user['role'] === 'user'): ?>
          <button type="button" class="btn-write-review" data-open-review>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
            Write a Review
          </button>
        <?php else: ?>
          <a class="btn-write-review" href="<?= e(url('auth/login.php?next=' . urlencode('product.php?id=' . (int) $product['id'] . '#reviews'))) ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
            Write a Review
          </a>
        <?php endif; ?>
        <span class="rating-score"><?= number_format($rating['avg'], 1) ?> / 5.0</span>
        <?= star_row($rating['avg']) ?>
      </div>
    </div>

    <?php if (!$reviews): ?>
      <p class="reviews-empty">No reviews yet. Be the first to review this product!</p>
    <?php else: ?>
      <div class="reviews-list">
        <?php foreach ($reviews as $rev): ?>
          <article class="review-item">
            <div class="review-avatar">
              <?php
                $revAvatar = trim((string) ($rev['user_avatar'] ?? ''));
                if ($revAvatar !== '' && !str_starts_with($revAvatar, 'http')) {
                    $revAvatar = asset(ltrim($revAvatar, '/'));
                }
              ?>
              <?php if ($revAvatar !== ''): ?>
                <img src="<?= e($revAvatar) ?>" alt="" referrerpolicy="no-referrer">
              <?php else: ?>
                <span><?= e(strtoupper(mb_substr($rev['user_name'], 0, 1))) ?></span>
              <?php endif; ?>
            </div>
            <div class="review-body">
              <div class="review-top">
                <strong><?= e($rev['user_name']) ?></strong>
                <?php if (!empty($rev['verified_purchase'])): ?>
                  <span class="verified-purchase">Verified purchase</span>
                <?php endif; ?>
                <?= star_row((float) $rev['rating']) ?>
                <time><?= e(date('d M Y', strtotime($rev['created_at']))) ?></time>
              </div>
              <?php if ($rev['comment'] !== ''): ?>
                <p><?= e($rev['comment']) ?></p>
              <?php endif; ?>
              <?php
                $revImages = $rev['images'] !== '' ? (json_decode((string) $rev['images'], true) ?: []) : [];
              ?>
              <?php if ($revImages): ?>
                <div class="review-photos">
                  <?php foreach ($revImages as $img): ?>
                    <a href="<?= e(product_image_url((string) $img)) ?>" target="_blank" rel="noopener">
                      <img src="<?= e(product_image_url((string) $img)) ?>" alt="Review photo">
                    </a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </section>

  <?php if ($user && $user['role'] === 'user'): ?>
    <div class="review-modal-overlay" data-review-modal hidden>
      <div class="review-modal" role="dialog" aria-modal="true" aria-labelledby="review-modal-title">
        <div class="review-modal-head">
          <h2 id="review-modal-title">Write a Review</h2>
          <button type="button" class="review-modal-close" data-close-review aria-label="Close">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
          </button>
        </div>

        <form method="post" enctype="multipart/form-data" id="write-review">
          <?= csrf_field() ?>
          <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">

          <p class="review-field-label">Overall Rating</p>
          <div class="rating-picker" role="radiogroup" aria-label="Your rating">
            <?php for ($i = 5; $i >= 1; $i--): ?>
              <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>>
              <label for="star<?= $i ?>" aria-label="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">★</label>
            <?php endfor; ?>
          </div>

          <p class="review-field-label">Your Review</p>
          <textarea name="comment" maxlength="1000"
            placeholder="What did you like or dislike? What did you use this product for?"></textarea>

          <p class="review-field-label">Add Photos <span class="optional-note">(Optional · up to 3)</span></p>
          <div class="review-photo-row" data-photo-row>
            <label class="review-upload-box" for="review-photos">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
              <span>Upload</span>
            </label>
            <input type="file" id="review-photos" name="photos[]" accept="image/png,image/jpeg,image/jpg,image/webp,image/gif" multiple class="sr-only-file">
          </div>
          <p class="review-upload-note">PNG, JPG, WEBP or GIF — max 5MB each. Photos show under your review after submit.</p>

          <div class="review-modal-actions">
            <button type="button" class="btn-cancel" data-close-review>Cancel</button>
            <button type="submit" name="action" value="add_review" class="btn-buy-now sm">Submit Review</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
