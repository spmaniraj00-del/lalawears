<?php
declare(strict_types=1);

function recaptcha_configured(): bool
{
    return defined('RECAPTCHA_SITE_KEY')
        && defined('RECAPTCHA_SECRET_KEY')
        && RECAPTCHA_SITE_KEY !== ''
        && RECAPTCHA_SECRET_KEY !== ''
        && !str_contains(RECAPTCHA_SITE_KEY, 'YOUR_')
        && !str_contains(RECAPTCHA_SECRET_KEY, 'YOUR_');
}

/** HTML widget for forms (v2 checkbox). Empty string if not configured. */
function recaptcha_widget_html(): string
{
    if (!recaptcha_configured()) {
        return '';
    }
    return '<div class="recaptcha-wrap">'
        . '<div class="g-recaptcha" data-sitekey="' . e(RECAPTCHA_SITE_KEY) . '" data-theme="light"></div>'
        . '</div>';
}

/** Script tag for pages that show the widget. */
function recaptcha_script_tag(): string
{
    if (!recaptcha_configured()) {
        return '';
    }
    return '<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
}

/**
 * Verify Google reCAPTCHA v2 response. Returns true when not configured (optional).
 */
function verify_recaptcha(?string $response = null): bool
{
    if (!recaptcha_configured()) {
        return true;
    }
    $token = trim((string) ($response ?? ($_POST['g-recaptcha-response'] ?? '')));
    if ($token === '') {
        return false;
    }

    try {
        $result = http_post_form('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => RECAPTCHA_SECRET_KEY,
            'response' => $token,
            'remoteip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);
        return !empty($result['success']);
    } catch (Throwable $e) {
        return false;
    }
}
