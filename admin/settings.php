<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_admin();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    try {
        // Global settings
        set_setting('contact_phone', trim($_POST['contact_phone'] ?? ''));
        $email = trim($_POST['contact_email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Contact email is not a valid email address.');
        }
        set_setting('contact_email', $email);

        // Social links
        foreach (['whatsapp_url', 'instagram_url', 'facebook_url', 'youtube_url'] as $key) {
            $val = trim($_POST[$key] ?? '');
            if ($val !== '' && !filter_var($val, FILTER_VALIDATE_URL)) {
                throw new RuntimeException(ucfirst(str_replace('_url', '', $key)) . ' URL is not valid.');
            }
            set_setting($key, $val);
        }

        // Branding uploads
        if (!empty($_FILES['site_logo']['name'])) {
            $uploaded = safe_upload_image($_FILES['site_logo'], 'logo');
            if ($uploaded) {
                set_setting('site_logo', $uploaded);
            }
        }
        if (!empty($_FILES['hero_image']['name'])) {
            $uploaded = safe_upload_image($_FILES['hero_image'], 'hero');
            if ($uploaded) {
                set_setting('hero_image', $uploaded);
            }
        }

        flash('success', 'Settings saved.');
        redirect('admin/settings.php');
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

$pageTitle = 'Settings | ' . APP_NAME;
$adminActive = 'settings';
$adminHeading = 'Settings';
require __DIR__ . '/../includes/admin_header.php';

$currentLogo = setting('site_logo', '');
$currentHero = setting('hero_image', '');
?>

<?php if ($error): ?>
  <div class="flash flash-error" style="position:static;transform:none;"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="settings-form">
  <?= csrf_field() ?>

  <div class="admin-card">
    <div class="admin-card-head">
      <h2 class="admin-card-title"><span style="display:inline-block;width:4px;height:18px;border-radius:99px;background:var(--green);"></span> Global Settings</h2>
    </div>
    <div class="settings-grid">
      <div class="form-group">
        <label for="contact_phone">Contact Number</label>
        <input type="text" id="contact_phone" name="contact_phone" maxlength="25"
               value="<?= e(site_phone()) ?>">
      </div>
      <div class="form-group">
        <label for="contact_email">Contact Email</label>
        <input type="email" id="contact_email" name="contact_email" maxlength="120"
               value="<?= e(site_email()) ?>">
      </div>
    </div>
  </div>

  <div class="admin-card">
    <div class="admin-card-head">
      <h2 class="admin-card-title"><span style="display:inline-block;width:4px;height:18px;border-radius:99px;background:var(--green);"></span> Branding &amp; Hero Banner</h2>
    </div>
    <div class="settings-grid">
      <div class="form-group">
        <label for="site_logo">Site Logo <?= $currentLogo ? '(leave empty to keep current)' : '(default: images/log.png)' ?></label>
        <input type="file" id="site_logo" name="site_logo" accept="image/jpeg,image/png,image/webp,image/gif">
        <img class="settings-preview" src="<?= e(site_logo_url()) ?>" alt="Current logo">
      </div>
      <div class="form-group">
        <label for="hero_image">Hero Banner Image <?= $currentHero ? '(leave empty to keep current)' : '(default: images/hero-bag.png)' ?></label>
        <input type="file" id="hero_image" name="hero_image" accept="image/jpeg,image/png,image/webp,image/gif">
        <img class="settings-preview" src="<?= e($currentHero !== '' ? asset($currentHero) : asset('images/hero-bag.png')) ?>" alt="Current hero banner">
      </div>
    </div>
  </div>

  <div class="admin-card">
    <div class="admin-card-head">
      <h2 class="admin-card-title"><span style="display:inline-block;width:4px;height:18px;border-radius:99px;background:var(--green);"></span> Social Media Links</h2>
    </div>
    <div class="settings-grid">
      <div class="form-group">
        <label for="whatsapp_url">WhatsApp URL</label>
        <input type="url" id="whatsapp_url" name="whatsapp_url" maxlength="300"
               placeholder="https://api.whatsapp.com/send?phone=…" value="<?= e(setting('whatsapp_url', WHATSAPP_URL)) ?>">
      </div>
      <div class="form-group">
        <label for="instagram_url">Instagram URL</label>
        <input type="url" id="instagram_url" name="instagram_url" maxlength="300"
               placeholder="https://instagram.com/yourprofile" value="<?= e(setting('instagram_url', INSTAGRAM_URL)) ?>">
      </div>
      <div class="form-group">
        <label for="facebook_url">Facebook URL</label>
        <input type="url" id="facebook_url" name="facebook_url" maxlength="300"
               placeholder="https://facebook.com/yourpage" value="<?= e(site_facebook()) ?>">
      </div>
      <div class="form-group">
        <label for="youtube_url">YouTube URL</label>
        <input type="url" id="youtube_url" name="youtube_url" maxlength="300"
               placeholder="https://youtube.com/@yourchannel" value="<?= e(site_youtube()) ?>">
      </div>
    </div>
  </div>

  <div class="admin-card">
    <div class="admin-card-head" style="margin-bottom:0;">
      <h2 class="admin-card-title"><span style="display:inline-block;width:4px;height:18px;border-radius:99px;background:var(--green);"></span> Security</h2>
      <a class="btn-admin-outline" href="<?= e(url('admin/password.php')) ?>">Change Admin Password</a>
    </div>
  </div>

  <div>
    <button type="submit" class="btn-admin-primary" style="padding:12px 28px;">Save Settings</button>
  </div>
</form>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
