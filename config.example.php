<?php
// Google OAuth Configuration settings template
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');

// Detect hostname dynamically for redirect URI
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$path = ($host === 'localhost' || $host === '127.0.0.1') ? '/kinder_php/google-callback.php' : '/google-callback.php';

define('GOOGLE_REDIRECT_URI', $protocol . '://' . $host . $path);

// SMTP Mail Configuration (For Password Reset Emails)
define('SMTP_HOST', 'smtp.gmail.com'); // e.g. smtp.gmail.com or mail.yourdomain.com
define('SMTP_PORT', 587);              // 587 (TLS/STARTTLS) or 465 (SSL)
define('SMTP_USER', 'YOUR_SMTP_USERNAME');
define('SMTP_PASS', 'YOUR_SMTP_PASSWORD');
define('SMTP_SECURE', 'tls');          // tls or ssl
define('MAIL_FROM_EMAIL', 'no-reply@dasgold.in');
define('MAIL_FROM_NAME', 'Dasgold Ledger');
?>
