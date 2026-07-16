<?php
declare(strict_types=1);
/** Admin panel layout — sidebar + topbar (ponnofy style) */
$adminUser = current_user();
$adminActive = $adminActive ?? '';
$pageTitle = $pageTitle ?? ('Admin | ' . APP_NAME);
$adminHeading = $adminHeading ?? 'Dashboard';
$adminSupportUnread = unread_support_count();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#f4f6f5">
  <meta name="robots" content="noindex">
  <title><?= e($pageTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>?v=2.0">
  <link rel="icon" href="<?= e(asset('images/log.png')) ?>">
</head>
<body class="admin-body">
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <a class="admin-logo" href="<?= e(url('admin/index.php')) ?>">
        <img src="<?= e(site_logo_url()) ?>" alt="LALA WEARS">
        <span>lala<em>wears</em><small>.co.in</small></span>
      </a>

      <nav class="admin-menu">
        <a class="<?= $adminActive === 'dashboard' ? 'active' : '' ?>" href="<?= e(url('admin/index.php')) ?>">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
          Dashboard
        </a>
        <a class="<?= in_array($adminActive, ['products', 'add'], true) ? 'active' : '' ?>" href="<?= e(url('admin/products.php')) ?>">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41 12 22l-8.59-8.59a2 2 0 0 1 0-2.82L11 3h9v9z"></path><circle cx="16" cy="7" r="1.2" fill="currentColor"></circle></svg>
          Products
        </a>
        <a class="<?= $adminActive === 'orders' ? 'active' : '' ?>" href="<?= e(url('admin/orders.php')) ?>">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
          Orders
        </a>
        <a class="<?= $adminActive === 'users' ? 'active' : '' ?>" href="<?= e(url('admin/users.php')) ?>">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
          Users
        </a>
        <a class="<?= $adminActive === 'support' ? 'active' : '' ?>" href="<?= e(url('admin/support.php')) ?>">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
          Support Chat
          <?php if ($adminSupportUnread > 0): ?><span class="admin-menu-badge"><?= $adminSupportUnread > 9 ? '9+' : $adminSupportUnread ?></span><?php endif; ?>
        </a>
        <a class="<?= in_array($adminActive, ['settings', 'password'], true) ? 'active' : '' ?>" href="<?= e(url('admin/settings.php')) ?>">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
          Settings
        </a>
      </nav>

      <div class="admin-sidebar-bottom">
        <a href="<?= e(url('index.php')) ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
          View Site
        </a>
        <a href="<?= e(url('auth/logout.php')) ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
          Secure Logout
        </a>
      </div>
    </aside>

    <div class="admin-main">
      <header class="admin-topbar">
        <h1><?= e($adminHeading) ?></h1>
        <div class="admin-user">
          <div class="au-meta">
            <p class="au-name"><?= e($adminUser['name']) ?></p>
            <p class="au-role">Super Admin</p>
          </div>
          <span class="au-avatar"><?= e(strtoupper(mb_substr($adminUser['name'], 0, 1))) ?></span>
        </div>
      </header>

      <div class="admin-content">
        <?php foreach (get_flashes() as $flash): ?>
          <div class="flash flash-<?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>
