<?php
declare(strict_types=1);

/**
 * Send OTP email via Resend HTTP API.
 */
function resend_ca_bundle(): string
{
    $local = APP_ROOT . '/config/cacert.pem';
    if (is_file($local) && filesize($local) > 1000) {
        return $local;
    }
    return '';
}

function resend_send_email(string $to, string $subject, string $html): array
{
    if (!resend_configured()) {
        return ['ok' => false, 'error' => 'Resend API key is not configured.'];
    }

    $payload = json_encode([
        'from' => RESEND_FROM,
        'to' => [$to],
        'subject' => $subject,
        'html' => $html,
    ], JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        return ['ok' => false, 'error' => 'Could not build email payload.'];
    }

    $ca = resend_ca_bundle();

    if (function_exists('curl_init')) {
        $ch = curl_init('https://api.resend.com/emails');
        $opts = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . RESEND_API_KEY,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 25,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if ($ca !== '') {
            $opts[CURLOPT_CAINFO] = $ca;
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            // Fallback once if CA still fails on some Windows setups
            if ($ca !== '' && stripos($err, 'SSL') !== false) {
                $ch = curl_init('https://api.resend.com/emails');
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . RESEND_API_KEY,
                        'Content-Type: application/json',
                    ],
                    CURLOPT_TIMEOUT => 25,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_CAINFO => $ca,
                ]);
                $raw = curl_exec($ch);
                $err = curl_error($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
            }
        }

        if ($raw === false) {
            return [
                'ok' => false,
                'error' => 'Email service connection failed. Please try again in a moment.',
                'debug' => $err,
            ];
        }
        $json = json_decode($raw, true);
        if ($code >= 200 && $code < 300) {
            return ['ok' => true, 'id' => $json['id'] ?? null];
        }
        $msg = is_array($json) ? (string) ($json['message'] ?? $json['error'] ?? '') : '';
        if ($msg === '') {
            $msg = 'Could not send email (HTTP ' . $code . ').';
        }
        // Resend free tier often only allows the account owner's email
        if (stripos($msg, 'only send') !== false || stripos($msg, 'verify') !== false) {
            $msg .= ' Tip: with free Resend, send OTP to your Resend account email, or verify a domain.';
        }
        return ['ok' => false, 'error' => $msg];
    }

    $ssl = ['verify_peer' => true, 'verify_peer_name' => true];
    if ($ca !== '') {
        $ssl['cafile'] = $ca;
    }
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Authorization: Bearer " . RESEND_API_KEY . "\r\nContent-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 25,
            'ignore_errors' => true,
        ],
        'ssl' => $ssl,
    ]);
    $raw = @file_get_contents('https://api.resend.com/emails', false, $ctx);
    if ($raw === false) {
        return ['ok' => false, 'error' => 'Could not reach Resend email API.'];
    }
    $json = json_decode($raw, true);
    if (is_array($json) && !empty($json['id'])) {
        return ['ok' => true, 'id' => $json['id']];
    }
    return ['ok' => false, 'error' => is_array($json) ? (string) ($json['message'] ?? 'Send failed') : 'Send failed'];
}
