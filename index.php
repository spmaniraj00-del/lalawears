<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$searchQuery = trim((string) ($_GET['q'] ?? ''));
if ($searchQuery !== '') {
    $stmt = db()->prepare(
        'SELECT * FROM products WHERE is_active = 1 AND (name LIKE :q OR description LIKE :q)
         ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute([':q' => '%' . $searchQuery . '%']);
    $products = $stmt->fetchAll();
} else {
    $products = db()->query(
        'SELECT * FROM products WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
    )->fetchAll();
}
$pageTitle = APP_NAME . ' | ' . APP_TAGLINE;
require __DIR__ . '/includes/header.php';
$user = current_user();

// Simple category tag per product (used by the filter pills)
function product_category(array $p): string
{
    $name = strtolower($p['name'] . ' ' . $p['description']);
    if (str_contains($name, 'embroider') || str_contains($name, 'heritage') || str_contains($name, 'bihar')) {
        return 'heritage';
    }
    if (str_contains($name, 'cotton') || str_contains($name, 'tee')) {
        return 'cotton';
    }
    return 'comfort';
}
?>

<main class="home-page" id="home-journey">

  <!-- ═══════════ HERO BANNER ═══════════ -->
  <section class="hero-banner" id="home">
    <div class="hero-slide">
      <div class="hero-banner-inner">
        <div class="hero-banner-copy reveal-up">
          <div class="hero-brand-lockup">
            <img class="hero-brand-logo" src="<?= e(site_logo_url()) ?>" alt="">
            <h1 class="hero-brand-name">lala<span>wears</span><small>.com</small></h1>
          </div>
          <p class="hero-tagline">Best Styles, For Everyone</p>

          <div class="hero-features">
            <div class="hero-feature">
              <span class="hero-feature-icon hf-orange">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="8" r="6"></circle>
                  <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"></path>
                </svg>
              </span>
              <span class="hero-feature-label">Trusted Quality</span>
            </div>
            <div class="hero-feature">
              <span class="hero-feature-icon hf-green">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M20.59 13.41 12 22l-8.59-8.59a2 2 0 0 1 0-2.82L11 3h9v9l-8.59 8.59"></path>
                  <path d="M20.59 13.41 12 22"></path>
                  <circle cx="16" cy="7" r="1.2" fill="currentColor"></circle>
                </svg>
              </span>
              <span class="hero-feature-label">Affordable Price</span>
            </div>
            <div class="hero-feature">
              <span class="hero-feature-icon hf-green">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="1" y="3" width="15" height="13" rx="1"></rect>
                  <path d="M16 8h4l3 3v5h-7V8z"></path>
                  <circle cx="5.5" cy="18.5" r="2.5"></circle>
                  <circle cx="18.5" cy="18.5" r="2.5"></circle>
                </svg>
              </span>
              <span class="hero-feature-label">Fast Delivery</span>
            </div>
            <div class="hero-feature">
              <span class="hero-feature-icon hf-orange">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
                  <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
                </svg>
              </span>
              <span class="hero-feature-label">Reliable Service</span>
            </div>
          </div>

          <div class="hero-links">
            <span class="hero-link">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
              www.lalawearscraftedforstyle.com
            </span>
            <a class="hero-link" href="<?= e(INSTAGRAM_URL) ?>" target="_blank" rel="noopener">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
              lalawears.co.in
            </a>
          </div>
        </div>

        <div class="hero-banner-media reveal-up">
          <?php $heroImg = setting('hero_image', ''); ?>
          <img src="<?= e($heroImg !== '' ? asset($heroImg) : asset('images/hero-bag.png')) ?>" alt="LALA WEARS shopping bag full of premium clothing">
        </div>
      </div>

      <svg class="hero-wave" viewBox="0 0 1440 90" preserveAspectRatio="none" aria-hidden="true">
        <path d="M0,50 C240,95 480,10 720,35 C960,60 1200,80 1440,30 L1440,90 L0,90 Z" fill="#fdf8f3"></path>
      </svg>
    </div>

    <div class="hero-dots" aria-hidden="true">
      <span class="hero-dot is-active" data-dot="0"></span>
      <span class="hero-dot" data-dot="1"></span>
      <span class="hero-dot" data-dot="2"></span>
    </div>
  </section>

  <!-- ═══════════ DEALS ═══════════ -->
  <section class="deals" id="deals">
    <h2 class="deals-title reveal-up">Deals <span>you can't miss</span></h2>

    <?php if ($searchQuery !== ''): ?>
      <p class="deals-search-note reveal-up">
        Showing results for “<?= e($searchQuery) ?>” — <a href="<?= e(url('index.php')) ?>#deals">clear search</a>
      </p>
    <?php endif; ?>

    <div class="deals-filters reveal-up" role="tablist" aria-label="Product categories">
      <button class="filter-pill is-active" data-filter="all">All</button>
      <button class="filter-pill" data-filter="heritage">Heritage</button>
      <button class="filter-pill" data-filter="cotton">Cotton</button>
      <button class="filter-pill" data-filter="comfort">Comfort</button>
    </div>

    <?php if (!$products): ?>
      <p class="deals-empty reveal-up">No products found. Try a different search.</p>
    <?php endif; ?>

    <div class="deals-grid">
      <?php foreach ($products as $product): ?>
        <?php
          $productUrl = url('product.php?id=' . (int) $product['id']);
          $gallery = product_gallery_paths($product);
          $wished = wishlist_has((int) $product['id'], $user ?? null);
        ?>
        <article class="deal-card reveal-up" data-category="<?= e(product_category($product)) ?>">
          <div class="deal-media-wrap">
            <a class="deal-media" href="<?= e($productUrl) ?>" data-hover-slide>
              <?php foreach ($gallery as $i => $imgPath): ?>
                <img src="<?= e(product_image_url($imgPath)) ?>" alt="<?= e($product['name']) ?>" <?= $i === 0 ? '' : 'loading="lazy"' ?> class="<?= $i === 0 ? 'is-active' : '' ?>" width="600" height="600">
              <?php endforeach; ?>
              <?php if (count($gallery) > 1): ?>
                <div class="deal-media-dots" aria-hidden="true">
                  <?php foreach ($gallery as $i => $_): ?>
                    <span class="<?= $i === 0 ? 'is-active' : '' ?>"></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </a>
            <span class="deal-tag">Premium</span>
            <form method="post" action="<?= e(url('wishlist.php')) ?>" class="deal-wish-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
              <input type="hidden" name="next" value="index.php#deals">
              <button type="submit" class="deal-wish<?= $wished ? ' is-active' : '' ?>" aria-label="<?= $wished ? 'Remove from wishlist' : 'Add to wishlist' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="<?= $wished ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
              </button>
            </form>
          </div>
          <div class="deal-body">
            <h3 class="deal-name"><a href="<?= e($productUrl) ?>"><?= e($product['name']) ?></a></h3>
            <p class="deal-desc"><?= e($product['description']) ?></p>
            <div class="deal-meta">
              <span class="deal-price"><?= e(money_inr($product['price'])) ?></span>
              <span class="deal-stock">In stock: <?= (int) $product['stock'] ?></span>
            </div>
            <a class="deal-buy" href="<?= e($productUrl) ?>">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="9" cy="21" r="1"></circle>
                <circle cx="20" cy="21" r="1"></circle>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
              </svg>
              Buy Now
            </a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ═══════════ CRAFT ═══════════ -->
  <section class="craft" id="craft">
    <h2 class="deals-title reveal-up">Made <span>with pride</span></h2>
    <div class="craft-grid reveal-up">
      <article class="craft-card">
        <div class="craft-icon">◈</div>
        <h3>240 GSM</h3>
        <p>Premium fabric with a substantial everyday feel that holds shape wash after wash.</p>
      </article>
      <article class="craft-card">
        <div class="craft-icon">✦</div>
        <h3>Embroidery</h3>
        <p>Luxury finish with durable stitched details inspired by Bihar identity.</p>
      </article>
      <article class="craft-card">
        <div class="craft-icon">◎</div>
        <h3>Quality</h3>
        <p>Built for repeat wear, clean fit, comfort, and lasting confidence.</p>
      </article>
    </div>
  </section>

  <!-- ═══════════ CONTACT / FOUNDER ═══════════ -->
  <section id="contact">
    <div class="contact-split">
      <div class="reveal-up">
        <h2 class="deals-title">Start <span>a conversation</span></h2>
        <p class="founder-intro">
          Order direct or talk to the founder. Every piece ships with care from Bettiah, Bihar.
        </p>
      </div>

      <article class="founder-card reveal-up" data-founder-card>
        <div class="founder-card-glow" aria-hidden="true"></div>
        <div class="founder-photo-wrap">
          <div class="founder-photo-ring"></div>
          <img
            class="founder-photo<?= site_founder_has_photo() ? '' : ' is-logo' ?>"
            src="<?= e(site_founder_photo_url()) ?>"
            alt="<?= e(FOUNDER_NAME) ?>"
            loading="lazy"
          >
          <span class="founder-status" title="Available on WhatsApp">
            <span class="founder-status-dot"></span>
            Online
          </span>
        </div>

        <div class="founder-body">
          <p class="founder-role">Founder &amp; CEO</p>
          <h3 class="founder-name"><?= e(FOUNDER_NAME) ?></h3>
          <p class="founder-tagline">Building LALA WEARS from Bettiah — heritage style, everyday comfort.</p>

          <ul class="founder-meta">
            <li>
              <span class="fm-icon" aria-hidden="true">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
              </span>
              <div>
                <strong>Phone</strong>
                <a href="tel:<?= e(str_replace(' ', '', CONTACT_PHONE)) ?>"><?= e(CONTACT_PHONE) ?></a>
              </div>
            </li>
            <li>
              <span class="fm-icon" aria-hidden="true">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
              </span>
              <div>
                <strong>Location</strong>
                <span><?= e(CONTACT_LOCATION) ?></span>
              </div>
            </li>
            <li>
              <span class="fm-icon" aria-hidden="true">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
              </span>
              <div>
                <strong>Email</strong>
                <a href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a>
              </div>
            </li>
          </ul>

          <div class="founder-actions">
            <a class="founder-btn founder-btn-wa" href="<?= e(WHATSAPP_URL) ?>" target="_blank" rel="noopener">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
              WhatsApp
            </a>
            <a class="founder-btn founder-btn-ig" href="<?= e(INSTAGRAM_URL) ?>" target="_blank" rel="noopener">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
              Instagram
            </a>
            <a class="founder-btn founder-btn-mail" href="mailto:<?= e(CONTACT_EMAIL) ?>">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
              Email Us
            </a>
          </div>
        </div>
      </article>
    </div>
  </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
