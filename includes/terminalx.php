<?php
declare(strict_types=1);

function terminalx_configured(): bool
{
    return TERMINALX_TOKEN !== ''
        && filter_var(TERMINALX_CREATE_URL, FILTER_VALIDATE_URL)
        && filter_var(TERMINALX_STATUS_URL, FILTER_VALIDATE_URL);
}

function terminalx_post(string $url, array $payload): array
{
    if (!terminalx_configured()) {
        return ['ok' => false, 'error' => 'Payment gateway is not configured.'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'Payment connection is unavailable on this server.'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'error' => 'Could not reach payment gateway: ' . $error];
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return ['ok' => false, 'error' => 'Payment gateway returned an invalid response (HTTP ' . $httpCode . ').'];
    }

    return ['ok' => true, 'http_code' => $httpCode, 'data' => $json];
}

function terminalx_create_payment(array $order, array $user): array
{
    $amount = round((float) $order['price'] * (int) $order['quantity'], 2);
    if ($amount <= 0) {
        return ['ok' => false, 'error' => 'Invalid payment amount.'];
    }

    $gatewayOrderId = 'LW' . str_pad((string) $order['id'], 6, '0', STR_PAD_LEFT)
        . '-' . strtoupper(bin2hex(random_bytes(4)));
    $phone = normalize_phone((string) ($order['customer_phone'] ?: ($user['phone'] ?? '')));
    if (!is_valid_indian_phone($phone)) {
        return ['ok' => false, 'error' => 'A valid 10-digit mobile number is required for payment.'];
    }

    $response = terminalx_post(TERMINALX_CREATE_URL, [
        'customer_mobile' => $phone,
        'user_token' => TERMINALX_TOKEN,
        'amount' => number_format($amount, 2, '.', ''),
        'order_id' => $gatewayOrderId,
        'redirect_url' => app_absolute_url('account/payment.php?id=' . (int) $order['id'] . '&returned=1'),
        'remark1' => mb_substr((string) $order['product_name'], 0, 100),
        'remark2' => order_code((int) $order['id']),
    ]);
    if (!$response['ok']) {
        return $response;
    }

    $data = $response['data'];
    $paymentUrl = trim((string) ($data['result']['payment_url'] ?? ''));
    $scheme = strtolower((string) parse_url($paymentUrl, PHP_URL_SCHEME));
    if (($data['status'] ?? '') !== 'SUCCESS' || $paymentUrl === '' || $scheme !== 'https') {
        return [
            'ok' => false,
            'error' => (string) ($data['message'] ?? 'Payment transaction could not be created.'),
        ];
    }

    return [
        'ok' => true,
        'gateway_order_id' => $gatewayOrderId,
        'payment_url' => $paymentUrl,
    ];
}

function terminalx_check_payment(string $gatewayOrderId): array
{
    $gatewayOrderId = trim($gatewayOrderId);
    if ($gatewayOrderId === '') {
        return ['ok' => false, 'state' => 'unknown', 'error' => 'Missing gateway reference.'];
    }

    $response = terminalx_post(TERMINALX_STATUS_URL, [
        'user_token' => TERMINALX_TOKEN,
        'order_id' => $gatewayOrderId,
    ]);
    if (!$response['ok']) {
        return $response + ['state' => 'unknown'];
    }

    $data = $response['data'];
    $rawStatus = strtoupper((string) ($data['result']['txnStatus'] ?? $data['status'] ?? 'PENDING'));
    $state = match ($rawStatus) {
        'COMPLETED', 'PAID' => 'paid',
        'FAILED', 'FAILURE', 'EXPIRED', 'CANCELLED' => 'failed',
        default => 'pending',
    };

    return [
        'ok' => true,
        'state' => $state,
        'gateway_status' => $rawStatus,
        'utr' => trim((string) ($data['result']['utr'] ?? '')),
        'message' => trim((string) ($data['message'] ?? $data['result']['resultInfo'] ?? '')),
        'raw' => $data,
    ];
}

/**
 * Apply a verified gateway status exactly once.
 */
function terminalx_apply_status(array $order, array $check, ?int $adminId = null): array
{
    if (empty($check['ok']) || !in_array($check['state'] ?? '', ['paid', 'failed'], true)) {
        return ['changed' => false, 'state' => $check['state'] ?? 'unknown'];
    }

    $pdo = db();
    $id = (int) $order['id'];

    if ($check['state'] === 'paid') {
        $stmt = $pdo->prepare(
            "UPDATE orders SET payment_status='paid', payment_utr=?, payment_checked_at=datetime('now','localtime'),
             status=CASE WHEN status='pending' THEN 'confirmed' ELSE status END,
             updated_at=datetime('now','localtime')
             WHERE id=? AND payment_status!='paid'"
        );
        $stmt->execute([(string) ($check['utr'] ?? ''), $id]);
        $changed = $stmt->rowCount() > 0;
        if ($changed) {
            $utr = (string) ($check['utr'] ?? '');
            add_order_tracking(
                $id,
                'confirmed',
                'Payment verified by gateway' . ($utr !== '' ? ' · UTR: ' . $utr : '') . ' · Order confirmed',
                (string) ($order['city'] ?? ''),
                $adminId
            );
            notify_user(
                (int) $order['user_id'],
                'Payment verified for ' . order_code($id),
                'Your payment is complete and the order is confirmed.',
                'account/order_view.php?id=' . $id
            );
            notify_admins(
                'Payment received for ' . order_code($id),
                money_inr((float) $order['price'] * (int) $order['quantity']) . ' verified by the gateway.',
                'admin/order_view.php?id=' . $id
            );
        }
        return ['changed' => $changed, 'state' => 'paid'];
    }

    $stmt = $pdo->prepare(
        "UPDATE orders SET payment_status='failed', payment_checked_at=datetime('now','localtime'),
         updated_at=datetime('now','localtime')
         WHERE id=? AND payment_status='submitted'"
    );
    $stmt->execute([$id]);
    return ['changed' => $stmt->rowCount() > 0, 'state' => 'failed'];
}
