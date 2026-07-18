<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login();
$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    'SELECT o.*, p.image AS live_image, p.name AS live_name
     FROM orders o
     LEFT JOIN products p ON p.id = o.product_id
     WHERE o.id = ? LIMIT 1'
);
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    flash('error', 'Order not found.');
    redirect('account/index.php');
}

if ($user['role'] !== 'admin' && (int) $order['user_id'] !== (int) $user['id']) {
    http_response_code(403);
    flash('error', 'You cannot view this order.');
    redirect('account/index.php');
}

// Generate transaction reference ID if empty or if it starts with COD
if (empty($order['transaction_id']) || str_starts_with($order['transaction_id'], 'COD') || !str_starts_with($order['transaction_id'], 'TXN')) {
    $txnId = 'TXN' . str_pad((string)$order['id'], 5, '0', STR_PAD_LEFT) . 'T' . time();
    db()->prepare("UPDATE orders SET transaction_id = ?, updated_at = datetime('now','localtime') WHERE id = ?")->execute([$txnId, $id]);
    $order['transaction_id'] = $txnId;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $utr = trim($_POST['utr'] ?? '');
    
    if (!preg_match('/^\d{12}$/', $utr)) {
        $error = 'Please enter a valid 12-digit UPI UTR/Transaction Reference Number.';
    } else {
        db()->prepare(
            "UPDATE orders SET
                payment_status = 'submitted',
                transaction_id = ?,
                updated_at = datetime('now','localtime')
             WHERE id = ?"
        )->execute([$utr, $id]);

        add_order_tracking(
            $id,
            'pending',
            'Payment submitted: UTR ' . $utr . ' · Awaiting admin verification',
            (string) ($order['city'] ?? '')
        );

        notify_admins(
            'Payment submitted for Order #' . $id,
            'Customer ' . ($order['customer_name'] ?: $user['name']) . ' submitted UTR ' . $utr . ' for ' . money_inr((float) $order['price'] * (int) $order['quantity']),
            'admin/order_view.php?id=' . $id
        );

        flash('success', 'Payment reference submitted! Awaiting verification.');
        redirect('account/order_view.php?id=' . $id);
    }
}

$amount = (float) $order['price'] * (int) $order['quantity'];
$upiUrl = "upi://pay?pa=" . urlencode(UPI_ID) . "&pn=" . urlencode(UPI_NAME) . "&am=" . $amount . "&cu=INR&tn=" . urlencode($order['transaction_id']);
$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($upiUrl);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Secure Checkout | <?= e(UPI_NAME) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #1a73e8;
      --primary-gradient: linear-gradient(135deg, #1fa2ff, #12d6df);
      --bg: #eef2f7;
      --text: #262626;
      --text-soft: #595959;
      --card-bg: #ffffff;
      --border: rgba(0, 0, 0, 0.08);
      --shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
      --green: #25d366;
    }
    
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    
    body {
      font-family: 'Outfit', sans-serif;
      background-color: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 20px;
    }

    .checkout-container {
      width: 100%;
      max-width: 1000px;
      display: grid;
      grid-template-columns: 1.1fr 1fr;
      gap: 30px;
      margin-top: 20px;
    }

    @media (max-width: 850px) {
      .checkout-container {
        grid-template-columns: 1fr;
        gap: 20px;
      }
    }

    /* Left Column Details */
    .summary-column {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .card {
      background: var(--card-bg);
      border-radius: 16px;
      padding: 24px;
      box-shadow: var(--shadow);
      border: 1px solid var(--border);
    }

    .card-title {
      font-size: 1.2rem;
      font-weight: 800;
      margin-bottom: 20px;
      color: var(--text);
      border-bottom: 1.5px solid var(--border);
      padding-bottom: 12px;
    }

    .summary-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;
      font-size: 0.95rem;
    }

    .summary-row span {
      color: var(--text-soft);
      font-weight: 500;
    }

    .summary-row strong {
      color: var(--text);
      font-weight: 700;
    }

    .divider {
      border: 0;
      border-top: 1px dashed var(--border);
      margin: 16px 0;
    }

    .total-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 1.15rem;
      font-weight: 800;
      color: var(--text);
      margin-top: 12px;
    }

    .secure-badge {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      background: #e8f5e9;
      color: #2e7d32;
      padding: 12px;
      border-radius: 8px;
      font-size: 0.9rem;
      font-weight: 700;
      margin-top: 20px;
      border: 1px solid #c8e6c9;
    }

    .help-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      width: 100%;
      background: #fff;
      border: 1px solid var(--border);
      color: var(--text-soft);
      padding: 12px;
      border-radius: 8px;
      font-size: 0.9rem;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.2s ease;
      margin-top: 12px;
    }

    .help-btn:hover {
      background: #f5f5f5;
      color: var(--text);
    }

    /* Connection Security Graph styling */
    .graph-card {
      padding: 20px;
    }

    .graph-title {
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--text-soft);
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 12px;
    }

    .graph-container {
      width: 100%;
      height: 70px;
      overflow: hidden;
      position: relative;
    }

    .chart-line {
      stroke-dasharray: 1000;
      stroke-dashoffset: 1000;
      animation: drawChart 2.5s ease-out forwards infinite alternate;
    }

    @keyframes drawChart {
      to {
        stroke-dashoffset: 0;
      }
    }

    /* Right Column Gateway styling */
    .gateway-column {
      display: flex;
      flex-direction: column;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: var(--shadow);
      border: 1px solid var(--border);
      background: var(--card-bg);
    }

    .gateway-header {
      background: var(--primary-gradient);
      color: #fff;
      padding: 24px;
      text-align: center;
    }

    .gateway-header h1 {
      font-size: 1.4rem;
      font-weight: 950;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 4px;
    }

    .gateway-header p {
      font-size: 0.85rem;
      font-weight: 600;
      opacity: 0.9;
    }

    .gateway-body {
      padding: 28px;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .amount-display {
      font-size: 2.2rem;
      font-weight: 900;
      color: var(--text);
      margin-bottom: 4px;
    }

    .txn-id-display {
      font-size: 0.85rem;
      font-weight: 700;
      color: var(--text-soft);
      margin-bottom: 24px;
    }

    .qr-container {
      padding: 16px;
      border: 1.5px solid var(--primary);
      border-radius: 16px;
      background: #fff;
      display: flex;
      flex-direction: column;
      align-items: center;
      margin-bottom: 12px;
      box-shadow: 0 4px 20px rgba(26, 115, 232, 0.08);
      position: relative;
    }

    .qr-container img {
      width: 210px;
      height: 210px;
      display: block;
      border-radius: 8px;
    }

    .scan-text {
      font-size: 0.85rem;
      font-weight: 700;
      color: var(--text-soft);
      margin-bottom: 20px;
    }

    /* UPI Apps display grid */
    .apps-title {
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.8rem;
      font-weight: 700;
      color: var(--text-soft);
      margin-bottom: 14px;
      position: relative;
    }

    .apps-title::before, .apps-title::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--border);
      margin: 0 10px;
    }

    .apps-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 12px;
      width: 100%;
      margin-bottom: 24px;
    }

    .app-link {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 6px;
      padding: 10px;
      border: 1px solid var(--border);
      border-radius: 10px;
      cursor: pointer;
      text-decoration: none;
      color: var(--text);
      transition: all 0.2s ease;
      background: #fff;
    }

    .app-link:hover {
      border-color: var(--primary);
      box-shadow: 0 4px 12px rgba(26, 115, 232, 0.06);
    }

    .app-icon {
      width: 24px;
      height: 24px;
      object-fit: contain;
    }

    .app-name {
      font-size: 0.75rem;
      font-weight: 700;
    }

    /* UTR entry form styling */
    .utr-form {
      width: 100%;
      border-top: 1px solid var(--border);
      padding-top: 24px;
      margin-top: 12px;
    }

    .form-label {
      font-size: 0.85rem;
      font-weight: 800;
      color: var(--text);
      text-transform: uppercase;
      margin-bottom: 8px;
      display: block;
    }

    .form-label em {
      color: #c62828;
      font-style: normal;
    }

    .input-wrap {
      position: relative;
      margin-bottom: 16px;
    }

    .utr-input {
      width: 100%;
      padding: 14px;
      border: 1.5px solid var(--border);
      border-radius: 12px;
      font-size: 1.2rem;
      font-weight: 800;
      letter-spacing: 2px;
      text-align: center;
      font-family: inherit;
      color: var(--text);
      outline: none;
      transition: border-color 0.2s ease;
    }

    .utr-input:focus {
      border-color: var(--primary);
    }

    .submit-btn {
      width: 100%;
      background: var(--primary);
      color: #fff;
      border: none;
      padding: 14px;
      border-radius: 12px;
      font-size: 1rem;
      font-weight: 800;
      cursor: pointer;
      box-shadow: 0 4px 14px rgba(26, 115, 232, 0.3);
      transition: all 0.2s ease;
    }

    .submit-btn:hover {
      background: #1557b0;
      box-shadow: 0 4px 18px rgba(26, 115, 232, 0.4);
    }

    .error-box {
      background: #ffebee;
      border: 1px solid #ffcdd2;
      color: #c62828;
      padding: 12px;
      border-radius: 8px;
      font-size: 0.88rem;
      font-weight: 600;
      margin-bottom: 16px;
      text-align: center;
    }

    .back-link {
      margin-top: 16px;
      font-size: 0.88rem;
      font-weight: 700;
      color: var(--text-soft);
      text-decoration: none;
      transition: color 0.2s ease;
    }

    .back-link:hover {
      color: var(--primary);
    }
  </style>
</head>
<body>
  
  <div class="checkout-container">
    
    <!-- Left Column Details -->
    <div class="summary-column">
      
      <div class="card">
        <h2 class="card-title">Order Summary</h2>
        <div class="summary-row">
          <span>Merchant</span>
          <strong><?= e(UPI_NAME) ?></strong>
        </div>
        <div class="summary-row">
          <span>Txn ID</span>
          <strong><?= e($order['transaction_id']) ?></strong>
        </div>
        <div class="summary-row">
          <span>Amount</span>
          <strong><?= e(money_inr($amount)) ?></strong>
        </div>
        <hr class="divider">
        <div class="total-row">
          <span>Total Payable</span>
          <strong><?= e(money_inr($amount)) ?></strong>
        </div>
        
        <div class="secure-badge">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
          256-bit Secure Encryption
        </div>
        
        <?php
        $waMessage = "Need help with payment for Order " . order_code((int)$order['id']) . ". Amount: " . money_inr($amount) . ".";
        $waUrl = "https://api.whatsapp.com/send?phone=" . WHATSAPP_NUMBER . "&text=" . urlencode($waMessage);
        ?>
        <a href="<?= e($waUrl) ?>" target="_blank" rel="noopener" class="help-btn">
          Need help? Contact Support
        </a>
      </div>
      
      <div class="card graph-card">
        <div class="graph-title">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#25d366" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
          Connection Security
        </div>
        <div class="graph-container">
          <svg width="100%" height="60" viewBox="0 0 300 60" preserveAspectRatio="none">
            <!-- Background Area -->
            <path d="M 0 50 Q 50 20 100 40 T 200 15 T 300 25 L 300 60 L 0 60 Z" fill="rgba(26,115,232,0.06)"/>
            <!-- Animated Line -->
            <path class="chart-line" d="M 0 50 Q 50 20 100 40 T 200 15 T 300 25" fill="none" stroke="#1a73e8" stroke-width="2.5" stroke-linecap="round"/>
          </svg>
        </div>
      </div>
      
    </div>
    
    <!-- Right Column Gateway Panel -->
    <div class="gateway-column">
      
      <div class="gateway-header">
        <h1><?= e(UPI_NAME) ?></h1>
        <p>Secure Checkout</p>
      </div>
      
      <div class="gateway-body">
        <div class="amount-display"><?= e(money_inr($amount)) ?></div>
        <div class="txn-id-display">Txn ID: <?= e($order['transaction_id']) ?></div>
        
        <div class="qr-container">
          <img src="<?= e($qrUrl) ?>" alt="UPI QR Code">
        </div>
        <div class="scan-text">Scan QR with any UPI app</div>
        
        <!-- App Quick Launcher Grid -->
        <div class="apps-title">Or Pay Using Apps</div>
        <div class="apps-grid">
          <a href="<?= e($upiUrl) ?>" class="app-link">
            <svg class="app-icon" viewBox="0 0 24 24" fill="none" stroke="#1a73e8" stroke-width="2.2"><path d="M2 17h20M12 2v20"/></svg>
            <span class="app-name">Paytm</span>
          </a>
          <a href="<?= e($upiUrl) ?>" class="app-link">
            <svg class="app-icon" viewBox="0 0 24 24" fill="none" stroke="#1a73e8" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><path d="M12 8v8M8 12h8"/></svg>
            <span class="app-name">PhonePe</span>
          </a>
          <a href="<?= e($upiUrl) ?>" class="app-link">
            <svg class="app-icon" viewBox="0 0 24 24" fill="none" stroke="#1a73e8" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>
            <span class="app-name">GPay</span>
          </a>
          <a href="<?= e($upiUrl) ?>" class="app-link">
            <svg class="app-icon" viewBox="0 0 24 24" fill="none" stroke="#1a73e8" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            <span class="app-name">Other</span>
          </a>
        </div>
        
        <!-- UTR Entry Form -->
        <div class="utr-form">
          <?php if ($error): ?>
            <div class="error-box"><?= e($error) ?></div>
          <?php endif; ?>
          
          <form method="post">
            <?= csrf_field() ?>
            <div class="input-wrap">
              <label for="utr" class="form-label">Enter 12-Digit UPI Ref No. (UTR) <em>*</em></label>
              <input type="text" id="utr" name="utr" required pattern="\d{12}" maxlength="12" inputmode="numeric" placeholder="e.g. 304561284759" class="utr-input">
            </div>
            <button type="submit" class="submit-btn">CONFIRM PAYMENT</button>
          </form>
        </div>
        
        <a href="<?= e(url('account/order_view.php?id=' . $id)) ?>" class="back-link">
          &larr; Back to Order Details
        </a>
      </div>
      
    </div>
    
  </div>

</body>
</html>
