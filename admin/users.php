<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_admin();
$pdo = db();

$users = $pdo->query(
    "SELECT u.*,
            (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS order_count
     FROM users u
     WHERE u.role = 'user'
     ORDER BY u.id DESC"
)->fetchAll();

$pageTitle = 'Users | ' . APP_NAME;
$adminActive = 'users';
$adminHeading = 'Users';
require __DIR__ . '/../includes/admin_header.php';
?>

<div class="admin-card">
  <div class="admin-card-head">
    <h2 class="admin-card-title"><span style="display:inline-block;width:4px;height:18px;border-radius:99px;background:var(--green);"></span> Users</h2>
    <span class="role-badge"><?= count($users) ?> total</span>
  </div>

  <?php if (!$users): ?>
    <p class="admin-empty">No users registered yet.</p>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Phone</th>
            <th>Email</th>
            <th>Role</th>
            <th>Orders</th>
            <th>Joined</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td>
                <div style="display:inline-flex;align-items:center;gap:10px;">
                  <?php if (!empty($u['avatar'])): ?>
                    <img src="<?= e($u['avatar']) ?>" alt="" style="width:30px;height:30px;border-radius:50%;object-fit:cover;border:1px solid rgba(38,38,38,0.1);">
                  <?php else: ?>
                    <span style="width:30px;height:30px;border-radius:50%;background:#e7f6ec;color:var(--green-dark);display:grid;place-items:center;font-size:11px;font-weight:800;">
                      <?= e(strtoupper(mb_substr($u['name'], 0, 1))) ?>
                    </span>
                  <?php endif; ?>
                  <strong><?= e($u['name']) ?></strong>
                </div>
              </td>
              <td>
                <?php if (!empty($u['phone'])): ?>
                  +91 <?= e($u['phone']) ?>
                <?php elseif ($u['google_id']): ?>
                  <span style="font-size:9px;font-weight:900;color:#4285F4;background:rgba(66,133,244,0.1);padding:3px 8px;border-radius:999px;letter-spacing:0.05em;text-transform:uppercase;">Google Auth</span>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td><?= e($u['email'] ?: '—') ?></td>
              <td><span class="role-badge">Customer</span></td>
              <td><strong><?= (int) $u['order_count'] ?></strong></td>
              <td><?= e(date('n/j/Y', strtotime((string) $u['created_at']))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
