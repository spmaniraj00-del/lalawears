<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? ('Sign In | ' . APP_NAME);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('css/otp.css')) ?>?v=1.2">
  <link rel="icon" href="<?= e(asset('images/log.png')) ?>">
</head>
<body class="otp-body">
  <div class="otp-ambient"><div class="otp-grain"></div></div>

  <header class="otp-nav">
    <div class="otp-nav-inner">
      <a class="otp-logo" href="<?= e(url('index.php')) ?>">
        <span class="otp-logo-mark"><img src="<?= e(asset('images/log.png')) ?>" alt=""></span>
        <span class="otp-logo-text">LALA WEARS</span>
      </a>
      <a class="otp-nav-cta" href="<?= e(url('index.php')) ?>#collection">Shop</a>
    </div>
  </header>

  <main class="otp-main">
    <div class="otp-col">
      <?= $otpContent ?? '' ?>
    </div>
  </main>

  <footer class="otp-footer">
    <div class="otp-footer-inner">
      <span>© <?= date('Y') ?> Lala Wears</span>
      <div class="otp-footer-links">
        <a href="<?= e(url('index.php')) ?>#contact">Help</a>
        <a href="<?= e(url('index.php')) ?>">Home</a>
        <a href="<?= e(url('admin/login.php')) ?>">Admin</a>
      </div>
    </div>
  </footer>
  <script src="<?= e(asset('js/otp.js')) ?>"></script>
</body>
</html>
