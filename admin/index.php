<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_admin();
$pdo = db();

$stats = [
    'products' => (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
    'active' => (int) $pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn(),
    'users' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn(),
    'orders' => (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'pending' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn(),
    'confirmed' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'confirmed'")->fetchColumn(),
    'shipped' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'shipped'")->fetchColumn(),
    'delivered' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered'")->fetchColumn(),
    'cancelled' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'cancelled'")->fetchColumn(),
    'revenue' => (float) $pdo->query("SELECT COALESCE(SUM(price * quantity),0) FROM orders WHERE status != 'cancelled'")->fetchColumn(),
];

$recentOrders = $pdo->query(
    "SELECT o.*,
            COALESCE(NULLIF(o.customer_name,''), u.name) AS display_name,
            COALESCE(NULLIF(o.customer_phone,''), u.phone) AS display_phone
     FROM orders o
     JOIN users u ON u.id = o.user_id
     ORDER BY o.id DESC LIMIT 10"
)->fetchAll();

$pageTitle = 'Admin Dashboard | ' . APP_NAME;
$adminActive = 'dashboard';
$adminHeading = 'Dashboard';
require __DIR__ . '/../includes/admin_header.php';
?>

<div class="admin-stats">
  <div class="admin-stat-card">
    <span class="asc-icon green">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
    </span>
    <div>
      <p class="asc-label">Total Revenue</p>
      <p class="asc-value"><?= e(money_inr($stats['revenue'])) ?></p>
    </div>
  </div>
  <div class="admin-stat-card">
    <span class="asc-icon blue">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
    </span>
    <div>
      <p class="asc-label">Total Orders</p>
      <p class="asc-value"><?= $stats['orders'] ?></p>
    </div>
  </div>
  <div class="admin-stat-card">
    <span class="asc-icon purple">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
    </span>
    <div>
      <p class="asc-label">Total Products</p>
      <p class="asc-value"><?= $stats['products'] ?></p>
    </div>
  </div>
  <div class="admin-stat-card">
    <span class="asc-icon orange">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
    </span>
    <div>
      <p class="asc-label">Total Customers</p>
      <p class="asc-value"><?= $stats['users'] ?></p>
    </div>
  </div>
</div>

<div class="admin-mini-stats">
  <div class="admin-mini-stat"><strong><?= $stats['pending'] ?></strong><span>Pending</span></div>
  <div class="admin-mini-stat"><strong><?= $stats['confirmed'] ?></strong><span>Confirmed</span></div>
  <div class="admin-mini-stat"><strong><?= $stats['shipped'] ?></strong><span>Shipped</span></div>
  <div class="admin-mini-stat"><strong><?= $stats['delivered'] ?></strong><span>Delivered</span></div>
  <div class="admin-mini-stat"><strong><?= $stats['cancelled'] ?></strong><span>Cancelled</span></div>
</div>

<div class="admin-card">
  <div class="admin-card-head">
    <h2 class="admin-card-title"><span style="display:inline-block;width:4px;height:18px;border-radius:99px;background:var(--green);"></span> Recent Orders</h2>
    <a class="btn-admin-outline" href="<?= e(url('admin/orders.php')) ?>">View All</a>
  </div>
  <?php if (!$recentOrders): ?>
    <p class="admin-empty">No orders yet.</p>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Order</th>
            <th>Customer</th>
            <th>Product</th>
            <th>Total</th>
            <th>Status</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentOrders as $row): ?>
            <tr>
              <td><strong><?= e(order_code((int) $row['id'])) ?></strong></td>
              <td>
                <strong><?= e($row['display_name']) ?></strong><br>
                <small>+91 <?= e($row['display_phone']) ?></small>
              </td>
              <td>
                <?= e($row['product_name']) ?><br>
                <small><?= e($row['size']) ?> · Qty <?= (int) $row['quantity'] ?></small>
              </td>
              <td><strong><?= e(money_inr((float) $row['price'] * (int) $row['quantity'])) ?></strong></td>
              <td><span class="badge <?= e($row['status']) ?>"><?= e(order_status_label($row['status'])) ?></span></td>
              <td><?= e(date('n/j/Y', strtotime((string) $row['created_at']))) ?></td>
              <td>
                <a class="icon-btn view" href="<?= e(url('admin/order_view.php?id=' . (int) $row['id'])) ?>" title="View order">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
