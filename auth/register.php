<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (current_user()) {
    redirect(current_user()['role'] === 'admin' ? 'admin/index.php' : 'account/index.php');
}

$error = '';
$prefill = [
    'name' => $_POST['name'] ?? '',
    'phone' => $_POST['phone'] ?? '',
    'email' => $_POST['email'] ?? '',
];
$googleReady = google_configured();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    if (!verify_recaptcha()) {
        $error = 'Please complete the reCAPTCHA check.';
        $prefill['name'] = $_POST['name'] ?? '';
        $prefill['phone'] = $_POST['phone'] ?? '';
        $prefill['email'] = $_POST['email'] ?? '';
    } else {
        $result = register_user(
            $_POST['name'] ?? '',
            $_POST['phone'] ?? '',
            $_POST['email'] ?? '',
            $_POST['password'] ?? ''
        );
        if ($result['ok']) {
            flash('success', 'Account created successfully!');
            $next = $_SESSION['login_next'] ?? '';
            unset($_SESSION['login_next']);
            if (is_string($next) && $next !== '' && str_starts_with($next, 'account/')) {
                redirect($next);
            }
            redirect('account/index.php');
        }
        $error = $result['error'];
        $prefill['name'] = $_POST['name'] ?? '';
        $prefill['phone'] = $_POST['phone'] ?? '';
        $prefill['email'] = $_POST['email'] ?? '';
    }
}

$pageTitle = 'Sign Up | ' . APP_NAME;

ob_start();
?>
      <div class="otp-eyebrow">
        <span class="otp-pill">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-1 6h2v6h-2V7zm0 8h2v2h-2v-2z"/></svg>
          Create Account
        </span>
      </div>

      <div class="otp-card">
        <div class="otp-icon-tile" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
        </div>
        <h1>Sign Up</h1>
        <p class="otp-sub">Fastest way: continue with Google — Gmail photo &amp; name sync automatically.</p>

        <?php if ($error): ?>
          <div class="otp-error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($googleReady): ?>
          <a href="<?= e(google_oauth_url()) ?>" class="google-btn google-btn-primary" rel="nofollow">
            <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">
              <path fill="#EA4335" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
              <path fill="#34A853" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.85c.87-2.6 3.3-4.53 6.16-4.53z"/>
              <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l3.66-2.85z"/>
              <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            </svg>
            Continue with Google
          </a>
          <p class="otp-google-hint">One tap · Gmail photo shows in your profile</p>

          <div class="otp-divider">
            <span class="otp-divider-text">Or create with email</span>
            <div class="otp-divider-line"></div>
          </div>
        <?php endif; ?>

        <form method="post" autocomplete="on">
          <?= csrf_field() ?>
          <div class="otp-form-group">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" required maxlength="80"
                   placeholder="Your full name" value="<?= e($prefill['name']) ?>">
          </div>
          <div class="otp-form-group">
            <label for="phone">Mobile Number</label>
            <input type="tel" id="phone" name="phone" required maxlength="15" inputmode="numeric"
                   placeholder="10-digit mobile" value="<?= e($prefill['phone']) ?>">
          </div>
          <div class="otp-form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" required maxlength="120"
                   placeholder="you@email.com" value="<?= e($prefill['email']) ?>">
          </div>
          <div class="otp-form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required placeholder="Min 6 characters">
          </div>
          <?= recaptcha_widget_html() ?>
          <button type="submit" class="otp-btn" style="margin-top: 24px;">
            Create Account
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
          </button>
        </form>
      </div>

      <div class="otp-helpers">
        <span>Already have an account?</span>
        <a href="<?= e(url('auth/login.php')) ?>">Sign In</a>
        <span class="div"></span>
        <a href="<?= e(url('index.php')) ?>">Back to shop</a>
      </div>
<?php
$otpContent = ob_get_clean();
require __DIR__ . '/../includes/otp_layout.php';
