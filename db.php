<?php
if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Prevent caching on shared hosts (like Hostinger/LiteSpeed) to prevent data leaks between users
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

    require_once 'rates_helper.php';

    // Dynamic Environment DB Configuration
    $isLocal = ($_SERVER['HTTP_HOST'] ?? 'localhost') === 'localhost' || ($_SERVER['HTTP_HOST'] ?? '127.0.0.1') === '127.0.0.1';
    if ($isLocal) {
        $host = 'localhost';
        $db   = 'kinder_db';
        $user = 'root';
        $pass = '';
    } else {
        $host = 'localhost';
        $db   = 'u798623491_dasgolddb';
        $user = 'u798623491_dasgold';
        $pass = 'Dasgold2026@';
    }


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

    // Run schema migration to support Google OAuth & Password Resets & Admin Privileges
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `oauth_provider` VARCHAR(50) DEFAULT 'local'");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `oauth_id` VARCHAR(100) DEFAULT NULL");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `reset_token` VARCHAR(255) DEFAULT NULL");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `reset_expires` DATETIME DEFAULT NULL");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `password_hash` VARCHAR(255) NULL");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `is_admin` TINYINT(1) DEFAULT 0");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `is_active` TINYINT(1) DEFAULT 1");
    } catch (Exception $e) {}
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `ledger_settlements` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `bapari_id` INT NOT NULL,
                `settlement_date` DATE NOT NULL,
                `closing_gold` DECIMAL(12, 3) NOT NULL,
                `closing_cash` DECIMAL(12, 2) NOT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                FOREIGN KEY (`bapari_id`) REFERENCES `baparis` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB;
        ");
    } catch (Exception $e) {}

    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `company_name` VARCHAR(255) DEFAULT NULL");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `company_mobile` VARCHAR(100) DEFAULT NULL");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `company_address` TEXT DEFAULT NULL");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `company_gst` VARCHAR(100) DEFAULT NULL");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `company_logo` VARCHAR(255) DEFAULT NULL");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `gold_api_key` VARCHAR(255) DEFAULT NULL");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `rate_24k` DECIMAL(12, 2) DEFAULT 12565.00");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `rate_22k` DECIMAL(12, 2) DEFAULT 11510.00");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `rate_ag` DECIMAL(12, 2) DEFAULT 179.00");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `rates_last_updated` INT DEFAULT 0");
    } catch (Exception $e) {}

    // Auto-seed admin access and reset password to password123
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute(['admin@admin.com']);
        $adminExists = $stmt->fetch();
        
        $hash = password_hash('password123', PASSWORD_DEFAULT);
        if (!$adminExists) {
            $insertStmt = $pdo->prepare("INSERT INTO users (email, password_hash, name, is_admin, is_active) VALUES ('admin@admin.com', ?, 'Suman Kanti Das', 1, 1)");
            $insertStmt->execute([$hash]);
        } else {
            $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ?, is_admin = 1, is_active = 1 WHERE email = 'admin@admin.com'");
            $updateStmt->execute([$hash]);
        }
        
        // Also promote the screenshot admin account if it exists
        $pdo->exec("UPDATE `users` SET is_admin = 1, is_active = 1 WHERE email = 'sumankantidas100@gmail.com'");
    } catch (Exception $e) {}

    // Enforce Authentication
    $current_page = basename($_SERVER['PHP_SELF']);
    $public_pages = ['login.php', 'register.php', 'google-callback.php', 'forgot-password.php', 'reset-password.php'];

    if (isset($_SESSION['user_id']) && !in_array($current_page, $public_pages)) {
        // Check if user is still active in database
        $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $uStatus = $stmt->fetch();
        if (!$uStatus || intval($uStatus['is_active']) !== 1) {
            session_unset();
            session_destroy();
            header("Location: login.php?error=deactivated");
            exit();
        }
    }

    if (!isset($_SESSION['user_id']) && !in_array($current_page, $public_pages)) {
        header("Location: login.php");
        exit();
    }

    $userId = $_SESSION['user_id'] ?? null;
    $currentUser = null;

    $rate24k = 12565.0;
    $rate22k = 11510.0;
    $rateAg = 179.0;

    // Expose global Admin check flag
    $isAdmin = false;
    $isReadOnly = false;
    if ($userId) {
        // Fetch active user details (including company profile parameters)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $currentUser = $stmt->fetch();

        $ratesConfig = refreshRatesIfNeeded($pdo, $userId);
        $rate24k = $ratesConfig['rate_24k'];
        $rate22k = $ratesConfig['rate_22k'];
        $rateAg = $ratesConfig['rate_ag'];

        $checkId = $_SESSION['impersonator_id'] ?? $userId;
        $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
        $stmt->execute([$checkId]);
        $uCheck = $stmt->fetch();
        $isAdmin = ($uCheck && intval($uCheck['is_admin']) === 1);
        $isReadOnly = isset($_SESSION['impersonator_id']);
    }
    ?>
