<?php
// Google OAuth Configuration settings template
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');

// Detect hostname dynamically for redirect URI
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$path = ($host === 'localhost' || $host === '127.0.0.1') ? '/kinder_php/google-callback.php' : '/google-callback.php';

define('GOOGLE_REDIRECT_URI', $protocol . '://' . $host . $path);
?>
