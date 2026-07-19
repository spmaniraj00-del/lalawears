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
        <p class="otp-sub">Create an account to track orders and save your wishlist.</p>

        <?php if ($error): ?>
          <div class="otp-error"><?= e($error) ?></div>
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
