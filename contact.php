<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$user = current_user();
$error = '';
$old = ['name' => '', 'email' => '', 'phone' => '', 'message' => ''];

if ($user) {
    $old['name'] = (string) $user['name'];
    $old['email'] = (string) ($user['email'] ?? '');
    $old['phone'] = (string) ($user['phone'] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $old['name'] = trim($_POST['name'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $old['phone'] = trim($_POST['phone'] ?? '');
    $old['message'] = trim($_POST['message'] ?? '');

    if (mb_strlen($old['name']) < 2) {
        $error = 'Please enter your name.';
    } elseif ($old['email'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (mb_strlen($old['message']) < 5) {
        $error = 'Please write your message (at least 5 characters).';
    } elseif (mb_strlen($old['message']) > 2000) {
        $error = 'Message is too long (max 2000 characters).';
    } elseif (!verify_recaptcha()) {
        $error = 'Please complete the reCAPTCHA check.';
    } else {
        $threadKey = support_thread_key($user, $old['email']);
        add_support_message(
            $threadKey,
            $user ? (int) $user['id'] : null,
            $old['name'],
            $old['email'],
            $old['phone'],
            $old['message']
        );
        notify_admins(
            'New support message',
            $old['name'] . ': ' . mb_substr($old['message'], 0, 80),
            'admin/support.php?t=' . urlencode($threadKey)
        );
        flash('success', 'Message sent! Our support team will get back to you soon.');
        redirect('contact.php');
    }
}

$pageTitle = 'Contact Us | ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>

<main class="contact-page">
  <div class="contact-head reveal-up">
    <h1>
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
      Contact Us
    </h1>
    <p>At LALA WEARS, your satisfaction is our priority. Our support team is ready to assist you with any inquiries.</p>
  </div>

  <div class="contact-grid">
    <div class="contact-cards">
      <div class="contact-card reveal-up">
        <span class="contact-card-icon">
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
        </span>
        <div>
          <h3>Call / WhatsApp</h3>
          <p><?= e(site_phone()) ?></p>
          <a href="<?= e(site_whatsapp()) ?>" target="_blank" rel="noopener">Chat on WhatsApp</a>
        </div>
      </div>

      <div class="contact-card reveal-up">
        <span class="contact-card-icon">
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
        </span>
        <div>
          <h3>Email Address</h3>
          <p><?= e(site_email()) ?></p>
          <a href="mailto:<?= e(site_email()) ?>">Write to us anytime</a>
        </div>
      </div>

      <div class="contact-card reveal-up">
        <span class="contact-card-icon">
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        </span>
        <div>
          <h3>Response Time</h3>
          <p>Usually replies within 2-4 hours</p>
          <span class="contact-card-note">Active 10 AM - 10 PM</span>
        </div>
      </div>
    </div>

    <div class="contact-form-card reveal-up">
      <h2>Send a Message</h2>
      <?php if ($error): ?>
        <div class="flash flash-error" style="position:static;transform:none;margin-bottom:14px;"><?= e($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="form-group">
          <label for="c-name">Your Name</label>
          <input type="text" id="c-name" name="name" maxlength="80" required placeholder="Enter your name" value="<?= e($old['name']) ?>">
        </div>
        <div class="form-group">
          <label for="c-email">Email Address</label>
          <input type="email" id="c-email" name="email" maxlength="120" required placeholder="example@gmail.com" value="<?= e($old['email']) ?>">
        </div>
        <div class="form-group">
          <label for="c-phone">Phone Number</label>
          <input type="tel" id="c-phone" name="phone" maxlength="15" placeholder="e.g. 9876543210" value="<?= e($old['phone']) ?>">
        </div>
        <div class="form-group">
          <label for="c-message">Message</label>
          <textarea id="c-message" name="message" required maxlength="2000" placeholder="How can we help you?"><?= e($old['message']) ?></textarea>
        </div>
        <?= recaptcha_widget_html() ?>
        <button type="submit" class="contact-send-btn">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
          Send Message
        </button>
      </form>
    </div>
  </div>
</main>

<?= recaptcha_script_tag() ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
