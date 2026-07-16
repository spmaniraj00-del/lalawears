<?php
declare(strict_types=1);
$user = current_user();
$pageTitle = $pageTitle ?? (APP_NAME . ' | ' . APP_TAGLINE);
$bodyClass = $bodyClass ?? '';
$unread = $user ? unread_notification_count((int) $user['id']) : 0;
$searchQuery = trim((string) ($_GET['q'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#fdf8f3">
  <meta name="description" content="LALA WEARS — premium clothing from Bihar. Crafted for style with heritage embroidery and everyday comfort.">
  <title><?= e($pageTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&family=League+Spartan:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>?v=2.6">
  <link rel="icon" href="<?= e(asset('images/log.png')) ?>">
</head>
<body class="<?= e($bodyClass) ?>">
  <header class="main-header">
    <button class="menu-toggle" aria-label="Toggle Menu" aria-expanded="false">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <line x1="3" y1="12" x2="21" y2="12" class="line-mid"></line>
        <line x1="3" y1="6" x2="21" y2="6" class="line-top"></line>
        <line x1="3" y1="18" x2="21" y2="18" class="line-bot"></line>
      </svg>
    </button>

    <a class="brand" href="<?= e(url('index.php')) ?>#home">
      <img src="<?= e(site_logo_url()) ?>" alt="LALA WEARS logo">
      <span class="brand-name">LALA<span class="brand-accent">WEARS</span><small>.com</small></span>
    </a>

    <nav class="nav-center" aria-label="Primary">
      <a href="<?= e(url('index.php')) ?>#home" class="nav-link-active">Home</a>
      <a href="<?= e(url('index.php')) ?>#deals">Shop</a>
      <a href="<?= e(url('contact.php')) ?>">Contact</a>
    </nav>

    <form class="nav-search" action="<?= e(url('index.php')) ?>" method="get" role="search">
      <input type="search" name="q" placeholder="Search" value="<?= e($searchQuery) ?>" aria-label="Search products">
      <button type="submit" aria-label="Search">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
      </button>
    </form>

    <div class="nav-right">
      <div class="nav-account-dropdown">
        <button class="nav-action dropdown-trigger" aria-haspopup="true" aria-expanded="false">
          <span class="nav-action-icon">
            <?php
              $navAvatar = $user ? user_avatar_url($user) : '';
            ?>
            <?php if ($navAvatar !== ''): ?>
              <img src="<?= e($navAvatar) ?>" alt="" class="nav-avatar" referrerpolicy="no-referrer" width="28" height="28">
            <?php elseif ($user): ?>
              <span class="nav-avatar nav-avatar-fallback"><?= e(user_avatar_initial($user)) ?></span>
            <?php else: ?>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                <circle cx="12" cy="7" r="4" />
              </svg>
            <?php endif; ?>
            <?php if ($unread > 0): ?><span class="badge-count"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
          </span>
          <span class="nav-action-label"><?= $user ? 'Account' : 'Profile' ?></span>
        </button>
        <div class="dropdown-menu">
          <?php if ($user): ?>
            <div class="dropdown-header-user">
              <?php if ($navAvatar !== ''): ?>
                <img class="dropdown-avatar" src="<?= e($navAvatar) ?>" alt="" referrerpolicy="no-referrer" width="44" height="44">
              <?php else: ?>
                <span class="dropdown-avatar dropdown-avatar-fallback"><?= e(user_avatar_initial($user)) ?></span>
              <?php endif; ?>
              <div class="dropdown-user-text">
                <span class="user-welcome">Hello, <?= e(explode(' ', $user['name'])[0]) ?></span>
                <span class="user-sub"><?= e($user['email']) ?></span>
              </div>
            </div>
            <a href="<?= e(url('account/index.php')) ?>" class="dropdown-item">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
              My Profile
            </a>
            <a href="<?= e(url('account/index.php')) ?>#orders" class="dropdown-item">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
              Orders
            </a>
            <a href="<?= e(url('account/notifications.php')) ?>" class="dropdown-item">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
              Notifications
              <?php if ($unread > 0): ?>
                <span class="badge-count-inline"><?= $unread > 9 ? '9+' : $unread ?></span>
              <?php endif; ?>
            </a>
            <?php if ($user['role'] === 'admin'): ?>
              <a href="<?= e(url('admin/index.php')) ?>" class="dropdown-item admin-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line></svg>
                Admin Panel
              </a>
            <?php endif; ?>
            <hr class="dropdown-divider">
            <a href="<?= e(url('auth/logout.php')) ?>" class="dropdown-item logout-link">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
              Logout
            </a>
          <?php else: ?>
            <div class="dropdown-header-user">
              <span class="user-welcome">Welcome to Lala Wears</span>
              <span class="user-sub">Login or register to order</span>
            </div>
            <a href="<?= e(url('auth/login.php')) ?>" class="dropdown-item">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>
              Sign In
            </a>
            <a href="<?= e(url('auth/register.php')) ?>" class="dropdown-item">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
              New Customer? Sign Up
            </a>
          <?php endif; ?>
        </div>
      </div>

      <a class="nav-action" href="<?= e(url('wishlist.php')) ?>">
        <span class="nav-action-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
          </svg>
          <?php $wishCount = wishlist_count($user); ?>
          <?php if ($wishCount > 0): ?><span class="badge-count"><?= $wishCount > 9 ? '9+' : $wishCount ?></span><?php endif; ?>
        </span>
        <span class="nav-action-label">Wishlist</span>
      </a>

      <a class="nav-action" href="<?= e(url('cart.php')) ?>">
        <span class="nav-action-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="9" cy="21" r="1"></circle>
            <circle cx="20" cy="21" r="1"></circle>
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
          </svg>
          <?php $cartCount = cart_count(); ?>
          <?php if ($cartCount > 0): ?><span class="badge-count cart-badge"><?= $cartCount > 9 ? '9+' : $cartCount ?></span><?php endif; ?>
        </span>
        <span class="nav-action-label">Cart</span>
      </a>

      <a class="nav-action" href="<?= e(url('tracking.php')) ?>">
        <span class="nav-action-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="1" y="3" width="15" height="13" rx="1"></rect>
            <path d="M16 8h4l3 3v5h-7V8z"></path>
            <circle cx="5.5" cy="18.5" r="2.5"></circle>
            <circle cx="18.5" cy="18.5" r="2.5"></circle>
          </svg>
        </span>
        <span class="nav-action-label">Tracking</span>
      </a>
    </div>
  </header>

  <?php foreach (get_flashes() as $flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?></div>
  <?php endforeach; ?>
