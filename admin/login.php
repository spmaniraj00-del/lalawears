<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (current_user() && current_user()['role'] === 'admin') {
    redirect('admin/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $result = attempt_admin_login($_POST['username'] ?? '', $_POST['password'] ?? '');
    if ($result['ok']) {
        flash('success', 'Welcome, ' . $result['user']['name'] . ' (Admin)');
        redirect('admin/index.php');
    }
    $error = $result['error'];
}

$pageTitle = 'Admin Login | ' . APP_NAME;
require __DIR__ . '/../includes/header.php';
?>

<div class="page-shell">
  <div class="auth-card">
    <p class="eyebrow">Staff Access</p>
    <h1>Admin Login</h1>
    <p class="lead">Full control of orders, delivery tracking, products, and customers.</p>

    <?php if ($error): ?>
      <div class="flash flash-error" style="position:static;transform:none;margin-bottom:18px;"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required maxlength="40"
               value="<?= e($_POST['username'] ?? 'admin') ?>">
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn">Enter Admin Panel</button>
      </div>
    </form>

    <p class="auth-links">
      <a href="<?= e(url('index.php')) ?>">Back to website</a>
    </p>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
