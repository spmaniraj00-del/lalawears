<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (current_user()) {
    $next = $_GET['next'] ?? '';
    if ($next && str_starts_with($next, 'account/')) {
        redirect($next);
    }
    redirect(current_user()['role'] === 'admin' ? 'admin/index.php' : 'account/index.php');
}

if (!empty($_GET['next'])) {
    $_SESSION['login_next'] = (string) $_GET['next'];
}

$error = '';
$prefill = [
    'email' => $_POST['email'] ?? '',
];
$googleReady = google_configured();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    if (!verify_recaptcha()) {
        $error = 'Please complete the reCAPTCHA check.';
        $prefill['email'] = $_POST['email'] ?? '';
    } else {
        $result = attempt_user_login(
            $_POST['email'] ?? '',
            $_POST['password'] ?? ''
        );
        if ($result['ok']) {
            flash('success', 'Welcome back, ' . $result['user']['name'] . '!');
            $next = $_SESSION['login_next'] ?? '';
            unset($_SESSION['login_next']);
            if (is_string($next) && $next !== '' && str_starts_with($next, 'account/')) {
                redirect($next);
            }
            redirect('account/index.php');
        }
        $error = $result['error'];
        $prefill['email'] = $_POST['email'] ?? '';
    }
}

$pageTitle = 'Sign In | ' . APP_NAME;

ob_start();
?>
      <div class="otp-eyebrow">
        <span class="otp-pill">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-1 6h2v6h-2V7zm0 8h2v2h-2v-2z"/></svg>
          Secure Sign In
        </span>
      </div>

      <div class="otp-card">
        <div class="otp-icon-tile" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 17a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm6-9h-1V6a5 5 0 0 0-10 0v2H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V10a2 2 0 0 0-2-2zM9 6a3 3 0 1 1 6 0v2H9V6z"/></svg>
        </div>
        <h1>Sign In</h1>
        <p class="otp-sub">Sign in to manage your orders, tracking, and profile.</p>

        <?php if ($error): ?>
          <div class="otp-error"><?= e($error) ?></div>
        <?php endif; ?>
        <?php foreach (get_flashes() as $flash): ?>
          <?php if ($flash['type'] === 'success'): ?>
            <div class="otp-error" style="border-color:var(--accent); background:rgba(228, 164, 189, 0.05); color:var(--text);"><?= e($flash['message']) ?></div>
          <?php else: ?>
            <div class="otp-error"><?= e($flash['message']) ?></div>
          <?php endif; ?>
        <?php endforeach; ?>



        <form method="post" autocomplete="on">
          <?= csrf_field() ?>
          <div class="otp-form-group">
            <label for="email">Email or Mobile Number</label>
            <input type="text" id="email" name="email" required maxlength="120"
                   placeholder="you@email.com or mobile number" value="<?= e($prefill['email']) ?>">
          </div>
          <div class="otp-form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required placeholder="••••••••">
          </div>
          <?= recaptcha_widget_html() ?>
          <button type="submit" class="otp-btn" style="margin-top: 24px;">
            Sign In
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
          </button>
        </form>
      </div>

      <div class="otp-helpers">
        <a href="<?= e(url('auth/register.php')) ?>">Create Account / Sign Up</a>
        <span class="div"></span>
        <a href="<?= e(url('auth/forgot_password.php')) ?>">Forgot Password?</a>
        <span class="div"></span>
        <a href="<?= e(url('index.php')) ?>">Back to shop</a>
      </div>
<?php
$otpContent = ob_get_clean();
require __DIR__ . '/../includes/otp_layout.php';
