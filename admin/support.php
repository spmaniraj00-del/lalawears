<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_admin();
$pdo = db();
$threadKey = trim((string) ($_GET['t'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $threadKey = trim((string) ($_POST['thread_key'] ?? ''));
    $reply = trim($_POST['reply'] ?? '');

    if ($threadKey !== '' && $reply !== '' && mb_strlen($reply) <= 2000) {
        $stmt = $pdo->prepare(
            "SELECT * FROM support_messages WHERE thread_key = ? AND sender = 'customer' ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$threadKey]);
        $last = $stmt->fetch();

        if ($last) {
            add_support_message(
                $threadKey,
                $last['user_id'] !== null ? (int) $last['user_id'] : null,
                $admin['name'],
                '',
                '',
                $reply,
                'admin'
            );
            if ($last['user_id'] !== null) {
                notify_user(
                    (int) $last['user_id'],
                    'Support replied to your message',
                    mb_substr($reply, 0, 120),
                    'contact.php'
                );
            }
            flash('success', 'Reply sent.');
        }
    }
    redirect('admin/support.php?t=' . urlencode($threadKey));
}

// Mark thread as read when opened
if ($threadKey !== '') {
    $pdo->prepare("UPDATE support_messages SET is_read = 1 WHERE thread_key = ? AND sender = 'customer'")
        ->execute([$threadKey]);
}

$threads = $pdo->query(
    "SELECT thread_key,
            MAX(id) AS last_id,
            MAX(created_at) AS last_at,
            SUM(CASE WHEN sender = 'customer' AND is_read = 0 THEN 1 ELSE 0 END) AS unread,
            (SELECT name FROM support_messages s2 WHERE s2.thread_key = s1.thread_key AND s2.sender = 'customer' ORDER BY s2.id DESC LIMIT 1) AS customer_name,
            (SELECT email FROM support_messages s3 WHERE s3.thread_key = s1.thread_key AND s3.sender = 'customer' ORDER BY s3.id DESC LIMIT 1) AS customer_email,
            (SELECT message FROM support_messages s4 WHERE s4.thread_key = s1.thread_key ORDER BY s4.id DESC LIMIT 1) AS last_message
     FROM support_messages s1
     GROUP BY thread_key
     ORDER BY last_id DESC"
)->fetchAll();

$messages = [];
$activeThread = null;
if ($threadKey !== '') {
    $stmt = $pdo->prepare('SELECT * FROM support_messages WHERE thread_key = ? ORDER BY id ASC');
    $stmt->execute([$threadKey]);
    $messages = $stmt->fetchAll();
    foreach ($threads as $t) {
        if ($t['thread_key'] === $threadKey) {
            $activeThread = $t;
            break;
        }
    }
}

$pageTitle = 'Support Chat | ' . APP_NAME;
$adminActive = 'support';
$adminHeading = 'Support Chat';
require __DIR__ . '/../includes/admin_header.php';
?>

<div class="admin-card support-shell">
  <div class="support-threads">
    <h2 class="admin-card-title" style="margin-bottom:14px;"><span style="display:inline-block;width:4px;height:18px;border-radius:99px;background:var(--green);"></span> Conversations</h2>
    <?php if (!$threads): ?>
      <p class="admin-empty">No messages yet. Customer messages from the Contact page appear here.</p>
    <?php else: ?>
      <div class="thread-list">
        <?php foreach ($threads as $t): ?>
          <a class="thread-item <?= $t['thread_key'] === $threadKey ? 'active' : '' ?>"
             href="<?= e(url('admin/support.php?t=' . urlencode($t['thread_key']))) ?>">
            <span class="thread-avatar"><?= e(strtoupper(mb_substr((string) ($t['customer_name'] ?: '?'), 0, 1))) ?></span>
            <span class="thread-meta">
              <strong><?= e($t['customer_name'] ?: 'Unknown') ?></strong>
              <small><?= e(mb_strlen((string) $t['last_message']) > 42 ? mb_substr((string) $t['last_message'], 0, 39) . '…' : (string) $t['last_message']) ?></small>
            </span>
            <?php if ((int) $t['unread'] > 0): ?>
              <span class="thread-unread"><?= (int) $t['unread'] ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="support-chat">
    <?php if (!$activeThread): ?>
      <div class="support-chat-empty">
        <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
        <p>Select a conversation to view messages and reply.</p>
      </div>
    <?php else: ?>
      <div class="support-chat-head">
        <div>
          <strong><?= e($activeThread['customer_name'] ?: 'Unknown') ?></strong>
          <?php if (!empty($activeThread['customer_email'])): ?>
            <small><?= e($activeThread['customer_email']) ?></small>
          <?php endif; ?>
        </div>
        <?php if (str_starts_with($threadKey, 'u:')): ?>
          <span class="stock-badge">Registered user</span>
        <?php else: ?>
          <span class="role-badge">Guest (reply via email)</span>
        <?php endif; ?>
      </div>

      <div class="support-messages">
        <?php foreach ($messages as $m): ?>
          <div class="support-msg <?= $m['sender'] === 'admin' ? 'from-admin' : 'from-customer' ?>">
            <div class="support-bubble">
              <p><?= nl2br(e($m['message'])) ?></p>
              <time><?= e($m['created_at']) ?><?= $m['sender'] === 'admin' ? ' · ' . e($m['name']) : '' ?></time>
            </div>
            <?php if ($m['sender'] === 'customer' && !empty($m['phone'])): ?>
              <small class="support-msg-phone">Phone: <?= e($m['phone']) ?></small>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <form method="post" class="support-reply">
        <?= csrf_field() ?>
        <input type="hidden" name="thread_key" value="<?= e($threadKey) ?>">
        <textarea name="reply" required maxlength="2000" placeholder="Type your reply…"></textarea>
        <button type="submit" class="btn-admin-primary">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
          Send Reply
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
