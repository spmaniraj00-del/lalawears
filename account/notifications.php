<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login();
$isAdmin = $user['role'] === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'read_one') {
        mark_notification_read((int) $user['id'], (int) ($_POST['id'] ?? 0));
    } elseif ($action === 'read_all') {
        mark_all_notifications_read((int) $user['id']);
        flash('success', 'All notifications marked as read.');
    }
    redirect('account/notifications.php');
}

$notes = user_notifications((int) $user['id'], 50);
$pageTitle = 'Notifications | ' . APP_NAME;
$adminActive = 'alerts';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-shell">
  <div class="panel wide">
    <p class="eyebrow">Alerts</p>
    <h1>Notifications</h1>
    <p class="lead">Order updates and account messages appear here.</p>

    <?php if ($isAdmin): ?>
      <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:22px;">
        <a class="btn-outline" href="<?= e(url('admin/index.php')) ?>">← Admin Panel</a>
        <a class="btn-outline" href="<?= e(url('admin/orders.php')) ?>">Orders</a>
      </div>
    <?php else: ?>
      <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:22px;">
        <a class="btn-outline" href="<?= e(url('account/index.php')) ?>">← My Account</a>
        <a class="btn-outline" href="<?= e(url('index.php')) ?>">Shop</a>
      </div>
    <?php endif; ?>

    <?php if ($notes): ?>
      <form method="post" style="margin-bottom:12px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="read_all">
        <button type="submit" class="btn-outline">Mark all as read</button>
      </form>
    <?php endif; ?>

    <div class="notif-list">
      <?php if (!$notes): ?>
        <p class="lead">No notifications yet.</p>
      <?php else: ?>
        <?php foreach ($notes as $n): ?>
          <article class="notif-item <?= (int) $n['is_read'] ? '' : 'unread' ?>">
            <div>
              <h3><?= e($n['title']) ?></h3>
              <p><?= e($n['message']) ?></p>
              <time><?= e($n['created_at']) ?></time>
              <?php if ($n['link']): ?>
                <div style="margin-top:10px;">
                  <a class="arrow-cta" href="<?= e(url($n['link'])) ?>">Open →</a>
                </div>
              <?php endif; ?>
            </div>
            <?php if (!(int) $n['is_read']): ?>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="read_one">
                <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                <button type="submit" class="btn-outline">Read</button>
              </form>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
