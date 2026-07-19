<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_admin();
$pdo = db();

// Filter range: default to "today"
$range = $_GET['range'] ?? 'today';
$allowedRanges = ['today', 'yesterday', '7days', '30days', 'all'];
if (!in_array($range, $allowedRanges, true)) {
    $range = 'today';
}

function get_date_filter(string $driver, string $range, string $column): string {
    if ($driver === 'sqlite') {
        return match ($range) {
            'today' => "{$column} >= datetime('now', 'start of day', 'localtime')",
            'yesterday' => "{$column} >= datetime('now', '-1 day', 'start of day', 'localtime') AND {$column} < datetime('now', 'start of day', 'localtime')",
            '7days' => "{$column} >= datetime('now', '-7 days', 'localtime')",
            '30days' => "{$column} >= datetime('now', '-30 days', 'localtime')",
            'all' => "1=1",
        };
    } elseif ($driver === 'pgsql') {
        return match ($range) {
            'today' => "{$column} >= date_trunc('day', timezone('Asia/Kolkata', now()))",
            'yesterday' => "{$column} >= date_trunc('day', timezone('Asia/Kolkata', now())) - interval '1 day' AND {$column} < date_trunc('day', timezone('Asia/Kolkata', now()))",
            '7days' => "{$column} >= timezone('Asia/Kolkata', now()) - interval '7 days'",
            '30days' => "{$column} >= timezone('Asia/Kolkata', now()) - interval '30 days'",
            'all' => "1=1",
        };
    } else { // mysql
        return match ($range) {
            'today' => "{$column} >= CURDATE()",
            'yesterday' => "{$column} >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND {$column} < CURDATE()",
            '7days' => "{$column} >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            '30days' => "{$column} >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            'all' => "1=1",
        };
    }
}

$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$dateFilter = get_date_filter($driver, $range, "visitor_activity.created_at");

// 1. Core aggregates
$totalHits = (int) $pdo->query("SELECT COUNT(*) FROM visitor_activity WHERE $dateFilter")->fetchColumn();
$uniqueIPs = (int) $pdo->query("SELECT COUNT(DISTINCT ip_address) FROM visitor_activity WHERE $dateFilter")->fetchColumn();
$registeredViews = (int) $pdo->query("SELECT COUNT(*) FROM visitor_activity WHERE $dateFilter AND user_id IS NOT NULL")->fetchColumn();

// 2. Page views ranking
$pages = $pdo->query(
    "SELECT page_url, COUNT(*) as hit_count 
     FROM visitor_activity 
     WHERE $dateFilter 
     GROUP BY page_url 
     ORDER BY hit_count DESC 
     LIMIT 15"
)->fetchAll();

// 3. User agent platforms
$platformSql = "SELECT user_agent, COUNT(*) as counts FROM visitor_activity WHERE $dateFilter GROUP BY user_agent";
$platformRows = $pdo->query($platformSql)->fetchAll();

$platforms = ['Mobile' => 0, 'Desktop' => 0, 'Tablet/Other' => 0];
foreach ($platformRows as $row) {
    $ua = strtolower((string)$row['user_agent']);
    if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet') || str_contains($ua, 'kindle')) {
        $platforms['Tablet/Other'] += (int)$row['counts'];
    } elseif (str_contains($ua, 'mobi') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
        $platforms['Mobile'] += (int)$row['counts'];
    } else {
        $platforms['Desktop'] += (int)$row['counts'];
    }
}

// 4. Activity log
$activityDateFilter = get_date_filter($driver, $range, "a.created_at");
$recentActivity = $pdo->query(
    "SELECT a.*, u.name AS user_name, u.email AS user_email 
     FROM visitor_activity a 
     LEFT JOIN users u ON u.id = a.user_id 
     WHERE $activityDateFilter 
     ORDER BY a.id DESC 
     LIMIT 50"
)->fetchAll();

$pageTitle = 'Visitor Analytics | Admin';
$adminActive = 'analytics';
$adminHeading = 'Visitor Analytics';
require __DIR__ . '/../includes/admin_header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:28px;">
  <p style="color:var(--text-soft); font-weight:600; margin:0;">Real-time visitor logs and aggregate page view analytics.</p>
  <div style="display:flex; gap:8px; background:#fff; padding:4px; border-radius:10px; border:1px solid rgba(0,0,0,0.06);">
    <?php foreach ($allowedRanges as $r): ?>
      <a href="?range=<?= $r ?>" 
         style="padding:6px 14px; text-decoration:none; font-size:13px; font-weight:700; border-radius:8px; transition:all 0.15s; 
                <?= $range === $r ? 'background:var(--green); color:#fff;' : 'color:var(--text-soft);' ?>">
        <?= ucfirst($r === '7days' ? '7 Days' : ($r === '30days' ? '30 Days' : $r)) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Aggregates Grid -->
<div class="admin-stats" style="margin-bottom:28px;">
  <div class="admin-stat-card">
    <span class="asc-icon blue">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
    </span>
    <div>
      <p class="asc-label">Total Page Views</p>
      <p class="asc-value"><?= number_format($totalHits) ?></p>
    </div>
  </div>
  <div class="admin-stat-card">
    <span class="asc-icon green">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
    </span>
    <div>
      <p class="asc-label">Unique Visitors (IPs)</p>
      <p class="asc-value"><?= number_format($uniqueIPs) ?></p>
    </div>
  </div>
  <div class="admin-stat-card">
    <span class="asc-icon purple">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8" cy="7" r="4"></circle><polyline points="17 11 19 13 23 9"></polyline></svg>
    </span>
    <div>
      <p class="asc-label">Registered Users Views</p>
      <p class="asc-value"><?= number_format($registeredViews) ?></p>
    </div>
  </div>
</div>

<div class="admin-two-col" style="grid-template-columns: 1.2fr 0.8fr; gap:28px; margin-bottom:28px;">
  
  <!-- Left Column: Page Views Ranking -->
  <div class="admin-card">
    <div class="admin-card-head">
      <h2 class="admin-card-title"><span style="display:inline-block;width:4px;height:18px;border-radius:99px;background:var(--green);"></span> Popular Pages</h2>
    </div>
    <?php if (!$pages): ?>
      <p class="admin-empty">No activity recorded for this period.</p>
    <?php else: ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Page URL</th>
              <th style="text-align:right;">Views</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pages as $pRow): ?>
              <tr>
                <td><code style="background:#f4f6f5; padding:3px 6px; border-radius:6px; font-size:13px; font-family:monospace;"><?= e($pRow['page_url']) ?></code></td>
                <td style="text-align:right; font-weight:700; color:var(--text-dark);"><?= number_format((int)$pRow['hit_count']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Right Column: Device/Platform Distribution -->
  <div class="admin-card">
    <div class="admin-card-head">
      <h2 class="admin-card-title"><span style="display:inline-block;width:4px;height:18px;border-radius:99px;background:var(--green);"></span> Device Breakdowns</h2>
    </div>
    <div style="display:flex; flex-direction:column; gap:20px; padding:10px 0;">
      <?php
        $sum = array_sum($platforms);
        foreach ($platforms as $plat => $count):
          $percentage = $sum > 0 ? round(($count / $sum) * 100) : 0;
          $color = $plat === 'Mobile' ? 'var(--green)' : ($plat === 'Desktop' ? '#1a73e8' : '#f2994a');
      ?>
        <div>
          <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:700; margin-bottom:6px;">
            <span style="color:var(--text-dark);"><?= $plat ?></span>
            <span style="color:var(--text-soft);"><?= number_format($count) ?> (<?= $percentage ?>%)</span>
          </div>
          <div style="background:#eee; height:8px; border-radius:99px; overflow:hidden;">
            <div style="background:<?= $color ?>; width:<?= $percentage ?>%; height:100%; border-radius:99px;"></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<!-- Recent Session Actions Logs -->
<div class="admin-card">
  <div class="admin-card-head">
    <h2 class="admin-card-title"><span style="display:inline-block;width:4px;height:18px;border-radius:99px;background:var(--green);"></span> Live Visitor Activity Logs (Last 50 Views)</h2>
  </div>
  <?php if (!$recentActivity): ?>
    <p class="admin-empty">No visitor logs found.</p>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table" style="font-size:13px;">
        <thead>
          <tr>
            <th>Time</th>
            <th>IP Address</th>
            <th>Account</th>
            <th>Visited Page</th>
            <th>User Agent</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentActivity as $act): ?>
            <tr>
              <td style="white-space:nowrap;"><?= e($act['created_at']) ?></td>
              <td><strong><?= e($act['ip_address']) ?></strong></td>
              <td>
                <?php if ($act['user_id']): ?>
                  <strong style="color:var(--green);"><?= e($act['user_name']) ?></strong><br>
                  <small style="color:var(--text-soft);"><?= e($act['user_email']) ?></small>
                <?php else: ?>
                  <span style="color:var(--text-soft);">Guest Visitor</span>
                <?php endif; ?>
              </td>
              <td><code style="background:#f4f6f5; padding:2px 5px; border-radius:4px; font-family:monospace;"><?= e($act['page_url']) ?></code></td>
              <td>
                <span title="<?= e($act['user_agent']) ?>" style="display:inline-block; max-width:250px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--text-soft);">
                  <?= e($act['user_agent']) ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
