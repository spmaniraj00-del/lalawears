<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (current_user()) {
    redirect(current_user()['role'] === 'admin' ? 'admin/index.php' : 'account/index.php');
}

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$success = '';

if ($token === '') {
    $error = 'Invalid or missing reset token.';
} else {
    // Basic verification of token presence
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? LIMIT 1");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    if (!$reset) {
        $error = 'Invalid or expired reset token.';
    } elseif (strtotime($reset['expires_at']) < time()) {
        $error = 'This reset link has expired.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    require_post_csrf();
    $result = reset_user_password($token, $_POST['password'] ?? '');
    if ($result['ok']) {
        flash('success', $result['message']);
        redirect('auth/login.php');
    }
    $error = $result['error'];
}

$pageTitle = 'Reset Password | ' . APP_NAME;

ob_start();
?>
      <div class="otp-eyebrow">
        <span class="otp-pill">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-1 6h2v6h-2V7zm0 8h2v2h-2v-2z"/></svg>
          New Password
        </span>
      </div>

      <div class="otp-card">
        <div class="otp-icon-tile" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 17a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm6-9h-1V6a5 5 0 0 0-10 0v2H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V10a2 2 0 0 0-2-2zM9 6a3 3 0 1 1 6 0v2H9V6z"/></svg>
        </div>
        <h1>Reset Password</h1>
        <p class="otp-sub">Enter and confirm your new password below.</p>

        <?php if ($error): ?>
          <div class="otp-error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if (!$error): ?>
          <form method="post" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="otp-form-group">
              <label for="password">New Password</label>
              <input type="password" id="password" name="password" required placeholder="Min 6 characters">
            </div>
            <button type="submit" class="otp-btn" style="margin-top: 24px;">
              Update Password
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
            </button>
          </form>
        <?php endif; ?>
      </div>

      <div class="otp-helpers">
        <a href="<?= e(url('auth/login.php')) ?>">Back to Sign In</a>
      </div>
<?php
$otpContent = ob_get_clean();
require __DIR__ . '/../includes/otp_layout.php';
