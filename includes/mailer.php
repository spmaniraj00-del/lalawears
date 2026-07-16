<?php
declare(strict_types=1);

/**
 * App mailer — Resend first, then Gmail SMTP fallback (any recipient).
 */

function mailer_configured(): bool
{
    return resend_configured() || smtp_configured();
}

function smtp_configured(): bool
{
    return SMTP_USER !== '' && SMTP_PASS !== '' && SMTP_HOST !== '';
}

/**
 * Preferred send helper for password reset / OTP / etc.
 */
function send_app_email(string $to, string $subject, string $html): array
{
    $to = trim(strtolower($to));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid recipient email.'];
    }

    // 1) Resend (works for any Gmail after domain verify; testing mode = only account email)
    if (resend_configured()) {
        $sent = resend_send_email($to, $subject, $html);
        if (!empty($sent['ok'])) {
            return $sent + ['via' => 'resend'];
        }
        $resendError = (string) ($sent['error'] ?? '');

        // 2) Gmail SMTP fallback — can deliver to any Gmail
        if (smtp_configured()) {
            $smtp = smtp_send_email($to, $subject, $html);
            if (!empty($smtp['ok'])) {
                return $smtp + ['via' => 'smtp'];
            }
            return [
                'ok' => false,
                'error' => 'Resend: ' . $resendError . ' | SMTP: ' . ($smtp['error'] ?? 'failed'),
            ];
        }

        return $sent;
    }

    if (smtp_configured()) {
        $smtp = smtp_send_email($to, $subject, $html);
        if (!empty($smtp['ok'])) {
            return $smtp + ['via' => 'smtp'];
        }
        return $smtp;
    }

    return ['ok' => false, 'error' => 'No email service configured (Resend or Gmail SMTP).'];
}

function smtp_send_email(string $to, string $subject, string $html): array
{
    if (!smtp_configured()) {
        return ['ok' => false, 'error' => 'Gmail SMTP is not configured.'];
    }

    $host = SMTP_HOST;
    $port = SMTP_PORT;
    $user = SMTP_USER;
    $pass = SMTP_PASS;
    $from = MAIL_FROM !== '' ? MAIL_FROM : $user;
    $fromName = MAIL_FROM_NAME !== '' ? MAIL_FROM_NAME : APP_NAME;

    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client(
        "tcp://{$host}:{$port}",
        $errno,
        $errstr,
        25,
        STREAM_CLIENT_CONNECT
    );
    if (!$socket) {
        return ['ok' => false, 'error' => "SMTP connect failed: {$errstr}"];
    }
    stream_set_timeout($socket, 25);

    $read = static function () use ($socket): string {
        $data = '';
        while ($line = fgets($socket, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $write = static function (string $cmd) use ($socket): void {
        fwrite($socket, $cmd . "\r\n");
    };
    $expect = static function (string $resp, string $code) use (&$read): bool {
        return str_starts_with(trim($resp), $code);
    };

    $banner = $read();
    if (!$expect($banner, '220')) {
        fclose($socket);
        return ['ok' => false, 'error' => 'SMTP banner failed.'];
    }

    $write('EHLO lalawears.local');
    $ehlo = $read();
    if (!$expect($ehlo, '250')) {
        $write('HELO lalawears.local');
        $read();
    }

    $write('STARTTLS');
    $tls = $read();
    if (!$expect($tls, '220')) {
        fclose($socket);
        return ['ok' => false, 'error' => 'SMTP STARTTLS failed.'];
    }

    $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
    if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
        $crypto = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
    }
    if (!@stream_socket_enable_crypto($socket, true, $crypto)) {
        fclose($socket);
        return ['ok' => false, 'error' => 'SMTP TLS handshake failed.'];
    }

    $write('EHLO lalawears.local');
    $read();

    $write('AUTH LOGIN');
    $read();
    $write(base64_encode($user));
    $read();
    $write(base64_encode($pass));
    $auth = $read();
    if (!$expect($auth, '235')) {
        fclose($socket);
        return ['ok' => false, 'error' => 'Gmail SMTP login failed. Use a Google App Password (not normal password).'];
    }

    $write('MAIL FROM:<' . $from . '>');
    $read();
    $write('RCPT TO:<' . $to . '>');
    $rcpt = $read();
    if (!$expect($rcpt, '250') && !$expect($rcpt, '251')) {
        fclose($socket);
        return ['ok' => false, 'error' => 'SMTP rejected recipient: ' . trim($rcpt)];
    }

    $write('DATA');
    $dataResp = $read();
    if (!$expect($dataResp, '354')) {
        fclose($socket);
        return ['ok' => false, 'error' => 'SMTP DATA not accepted.'];
    }

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = [
        'Date: ' . date('r'),
        'From: ' . sprintf('"%s" <%s>', addcslashes($fromName, '"'), $from),
        'To: <' . $to . '>',
        'Subject: ' . $encodedSubject,
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@lalawears>',
    ];

    $body = implode("\r\n", $headers) . "\r\n\r\n"
        . chunk_split(base64_encode($html))
        . "\r\n.";

    fwrite($socket, $body . "\r\n");
    $sent = $read();
    $write('QUIT');
    fclose($socket);

    if (!$expect($sent, '250')) {
        return ['ok' => false, 'error' => 'SMTP send failed: ' . trim($sent)];
    }

    return ['ok' => true, 'id' => null];
}
