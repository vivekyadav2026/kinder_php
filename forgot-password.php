<?php
require_once 'db.php';

$error = '';
$success = '';
$debugLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Email is required!';
    } else {
        // Find user by email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate secure reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Update user record
            $updateStmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $updateStmt->execute([$token, $expires, $user['id']]);

            $success = 'Password reset instructions have been generated!';
            
            // Construct local debug reset link for easy testing
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $isLocal = ($host === 'localhost' || $host === '127.0.0.1');
            $path = $isLocal ? '/kinder_php/reset-password.php' : '/reset-password.php';
            $debugLink = "{$proto}://{$host}{$path}?token={$token}";
        } else {
            $error = 'Email address not found!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Forgot Password - Dasgold</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #0F172A;
            min-height: 100vh;
        }
        .premium-card {
            background-color: #1E293B;
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 15px 35px -10px rgba(0, 0, 0, 0.6);
        }
        .premium-input {
            width: 100%;
            background-color: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            padding: 14px 16px;
            color: #ffffff;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        .premium-input:focus {
            outline: none;
            border-color: #F4B400;
            box-shadow: 0 0 0 3px rgba(244, 180, 0, 0.15);
        }
        .btn-gold {
            background: linear-gradient(135deg, #F4B400 0%, #D99B00 100%);
            color: #0F172A;
            font-weight: 600;
            border-radius: 14px;
            padding: 14px;
            font-size: 15px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(244, 180, 0, 0.2);
        }
        .btn-gold:active {
            transform: scale(0.98);
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="w-full max-w-md premium-card p-8">
        <div class="text-center mb-8">
            <img src="assets/images/karigor-icon.png" alt="Dasgold Logo" class="w-16 h-16 rounded-2xl object-cover mx-auto border border-[#F4B400]/25 shadow-lg shadow-[#F4B400]/10 mb-4">
            <h1 class="text-2xl font-extrabold text-white tracking-tight">Forgot Password</h1>
            <p class="text-xs text-slate-400 mt-1">Enter your registered email to request a reset token</p>
        </div>

        <!-- Feedback Alerts -->
        <?php if ($error): ?>
            <div class="mb-5 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs flex items-center space-x-2">
                <span class="material-symbols-rounded text-base">error</span>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="mb-5 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs flex items-center space-x-2">
                <span class="material-symbols-rounded text-base">check_circle</span>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Email Address</label>
                <div class="relative">
                    <input type="email" name="email" required class="premium-input" placeholder="name@domain.com">
                </div>
            </div>

            <button type="submit" class="w-full btn-gold text-center">
                Send Reset Link
            </button>
            
            <div class="text-center mt-4">
                <a href="login.php" class="text-xs text-[#F4B400] hover:underline font-semibold flex items-center justify-center space-x-1">
                    <span class="material-symbols-rounded text-sm">arrow_back</span>
                    <span>Back to Login</span>
                </a>
            </div>
        </form>

        <!-- Debug link block for local test environment -->
        <?php if ($debugLink): ?>
            <div class="mt-6 p-4 rounded-xl bg-[#F4B400]/10 border border-[#F4B400]/20 text-xs">
                <span class="font-bold text-[#F4B400] block mb-1">Local Testing Link:</span>
                <p class="text-slate-300 mb-2">Since local servers don't mail reset links, click below to update your password:</p>
                <a href="<?= $debugLink ?>" class="text-sky-400 underline break-all font-mono hover:text-sky-300"><?= $debugLink ?></a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
