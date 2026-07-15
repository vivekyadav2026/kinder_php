<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// $host = 'localhost';
// $db   = 'kinder_db';
// $user = 'root';
// $pass = '';

$host = 'localhost';
$db   = 'u798623491_dasgolddb';
$user = 'u798623491_dasgold';
$pass = 'Dasgold2026@';


$charset = 'utf8mb4';




$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // If connection fails because DB does not exist, try to connect without db name and create it
     try {
         $pdo = new PDO("mysql:host=$host;charset=$charset", $user, $pass, $options);
         $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
         $pdo->exec("USE `$db`");
         // Import schema
         $schema = file_get_contents(__DIR__ . '/schema.sql');
         $pdo->exec($schema);
     } catch (\PDOException $ex) {
         die("Database connection failed: " . $ex->getMessage());
     }
}

// Enforce Authentication
$current_page = basename($_SERVER['PHP_SELF']);
$public_pages = ['login.php', 'register.php'];

if (!isset($_SESSION['user_id']) && !in_array($current_page, $public_pages)) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'] ?? null;
?>
