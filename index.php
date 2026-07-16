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
            <h1 class="hero-brand-name">lala<span>wears</span><small>.co.in</small></h1>
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
              www.lalawears.co.in
            </span>
            <a class="hero-link" href="<?= e(INSTAGRAM_URL) ?>" target="_blank" rel="noopener">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
              /lalawears.co.in
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
        <?php $productUrl = url('product.php?id=' . (int) $product['id']); ?>
        <article class="deal-card reveal-up" data-category="<?= e(product_category($product)) ?>">
          <a class="deal-media" href="<?= e($productUrl) ?>">
            <img src="<?= e(product_image_url($product['image'])) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
            <span class="deal-tag">Premium</span>
          </a>
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

  <!-- ═══════════ CONTACT ═══════════ -->
  <section id="contact">
    <div class="contact-split">
      <div class="reveal-up">
        <h2 class="deals-title">Start <span>a conversation</span></h2>
        <p style="color:var(--text-soft);font-size:1.15rem;font-weight:500;max-width:28ch;">
          Order direct or talk to the founder. Every piece ships with care from Bettiah, Bihar.
        </p>
      </div>
      <div class="glowing-edge-card light-mode reveal-up" data-glowing-edge>
        <div class="glowing-card-mesh-border" aria-hidden="true"></div>
        <div class="glowing-card-mesh-bg" aria-hidden="true"></div>
        <div class="glowing-card-glow" aria-hidden="true"></div>
        <div class="glowing-card-inner contact-card">
          <p class="eyebrow">Founder &amp; CEO</p>
          <h3><?= e(FOUNDER_NAME) ?></h3>
          <ul class="contact-list">
            <li><strong>Phone</strong><?= e(CONTACT_PHONE) ?></li>
            <li><strong>Location</strong><?= e(CONTACT_LOCATION) ?></li>
            <li><strong>Email</strong><?= e(CONTACT_EMAIL) ?></li>
          </ul>
          <div class="contact-actions">
            <a class="btn" href="<?= e(WHATSAPP_URL) ?>" target="_blank" rel="noopener">WhatsApp</a>
            <a class="btn-outline" href="<?= e(INSTAGRAM_URL) ?>" target="_blank" rel="noopener">Instagram</a>
            <a class="btn-outline" href="mailto:<?= e(CONTACT_EMAIL) ?>">Email Us</a>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
