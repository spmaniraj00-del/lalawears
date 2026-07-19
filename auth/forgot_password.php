<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (current_user()) {
    redirect(current_user()['role'] === 'admin' ? 'admin/index.php' : 'account/index.php');
}

$error = '';
$success = '';
$resetLink = '';
$emailSent = false;
$email = $_POST['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $result = send_password_reset_link($email);
    if ($result['ok']) {
        $success = $result['message'];
        $resetLink = (string) ($result['reset_link'] ?? '');
        $emailSent = !empty($result['email_sent']);
        $email = '';
    } else {
        $error = $result['error'];
    }
}

$pageTitle = 'Forgot Password | ' . APP_NAME;

ob_start();
?>
      <div class="otp-eyebrow">
        <span class="otp-pill">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-1 6h2v6h-2V7zm0 8h2v2h-2v-2z"/></svg>
          Reset Password
        </span>
      </div>

      <div class="otp-card">
        <div class="otp-icon-tile" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM9 6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9V6zm9 14H6V10h12v10zm-6-3c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"/></svg>
        </div>
        <h1>Forgot Password</h1>
        <p class="otp-sub">Enter the email address linked to your account. We’ll email you a reset link — and also show it on this page if delivery fails.</p>

        <?php if ($error): ?>
          <div class="otp-error"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="otp-error" style="border-color:var(--accent); background:rgba(228, 164, 189, 0.05); color:var(--text);"><?= e($success) ?></div>
          <?php if ($emailSent): ?>
            <p class="otp-google-hint">Sent from onboarding@resend.dev — check Inbox + Spam.</p>
          <?php endif; ?>
          <?php if ($resetLink !== ''): ?>
            <div class="otp-onsite-reset">
              <p class="otp-onsite-label"><?= $emailSent ? 'Backup link (also emailed)' : 'Use this reset link now' ?></p>
              <a class="otp-onsite-link" href="<?= e($resetLink) ?>"><?= e($resetLink) ?></a>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="post" autocomplete="on">
          <?= csrf_field() ?>
          <div class="otp-form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" required maxlength="120"
                   placeholder="you@example.com" value="<?= e($email) ?>">
          </div>
          <button type="submit" class="otp-btn" style="margin-top: 24px;">
            Send Reset Link
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
          </button>
        </form>
        <?php else: ?>
          <a href="<?= e(url('auth/forgot_password.php')) ?>" class="otp-btn" style="margin-top:24px;text-decoration:none;">Send another link</a>
        <?php endif; ?>
      </div>

      <div class="otp-helpers">
        <a href="<?= e(url('auth/login.php')) ?>">Back to Sign In</a>
        <span class="div"></span>
        <a href="<?= e(url('index.php')) ?>">Back to shop</a>
      </div>
<?php
$otpContent = ob_get_clean();
require __DIR__ . '/../includes/otp_layout.php';
