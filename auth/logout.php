<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

logout_user();

// Start fresh session for flash message after destroy
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}
flash('success', 'You have been logged out.');
redirect('index.php');
