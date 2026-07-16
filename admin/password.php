<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_admin();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([(int) $admin['id']]);
    $hash = (string) $stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        db()->prepare('UPDATE users SET password_hash = ?, updated_at = datetime(\'now\',\'localtime\') WHERE id = ?')
            ->execute([$newHash, (int) $admin['id']]);
        flash('success', 'Password updated successfully.');
        redirect('admin/index.php');
    }
}

$pageTitle = 'Settings | ' . APP_NAME;
$adminActive = 'password';
$adminHeading = 'Settings';
require __DIR__ . '/../includes/admin_header.php';
?>

<div class="admin-card" style="max-width:520px;">
    <div class="admin-card-head">
      <h2 class="admin-card-title"><span style="display:inline-block;width:4px;height:18px;border-radius:99px;background:var(--green);"></span> Change Password</h2>
    </div>
    <p style="font-size:12.5px;color:var(--text-soft);font-weight:500;margin:-8px 0 18px;">Update your admin password regularly to keep the panel secure.</p>

    <?php if ($error): ?>
      <div class="flash flash-error" style="position:static;transform:none;margin-bottom:18px;"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="current_password">Current Password</label>
        <input type="password" id="current_password" name="current_password" required>
      </div>
      <div class="form-group">
        <label for="new_password">New Password</label>
        <input type="password" id="new_password" name="new_password" required minlength="8">
      </div>
      <div class="form-group">
        <label for="confirm_password">Confirm New Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
      </div>
      <div class="form-actions" style="display:flex;gap:10px;">
        <button type="submit" class="btn-admin-primary">Update Password</button>
        <a class="btn-admin-outline" href="<?= e(url('admin/index.php')) ?>">Back</a>
      </div>
    </form>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
