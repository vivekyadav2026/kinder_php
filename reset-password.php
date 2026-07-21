<?php
require_once 'db.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$isValid = false;
$user = null;

if (empty($token)) {
    $error = 'Invalid token!';
} else {
    // Verify token exists and is not expired
    $stmt = $pdo->prepare("SELECT id, reset_expires FROM users WHERE reset_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $expiry = strtotime($user['reset_expires']);
        if ($expiry > time()) {
            $isValid = true;
        } else {
            $error = 'This password reset link has expired!';
        }
    } else {
        $error = 'Invalid reset token!';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isValid && $user) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($password)) {
        $error = 'Password cannot be empty!';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match!';
    } else {
        // Hash and update password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $updateStmt->execute([$passwordHash, $user['id']]);

        $success = 'Password has been updated successfully!';
        $isValid = false; // Disable form after success
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Reset Password - Dasgold</title>
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
            <h1 class="text-2xl font-extrabold text-white tracking-tight">Reset Password</h1>
            <p class="text-xs text-slate-400 mt-1">Set a new, secure password for your Dasgold account</p>
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

        <?php if ($isValid): ?>
            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">New Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-500"><span class="material-symbols-rounded text-lg">lock</span></span>
                        <input type="password" id="resetPass" name="password" required class="premium-input pl-11 pr-11" placeholder="••••••••">
                        <button type="button" onclick="togglePasswordVisibility('resetPass', 'resetPassIcon')" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-white transition-colors focus:outline-none" tabindex="-1" title="Toggle password visibility">
                            <span id="resetPassIcon" class="material-symbols-rounded text-lg">visibility</span>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Confirm Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-500"><span class="material-symbols-rounded text-lg">lock</span></span>
                        <input type="password" id="confirmPass" name="confirm_password" required class="premium-input pl-11 pr-11" placeholder="••••••••">
                        <button type="button" onclick="togglePasswordVisibility('confirmPass', 'confirmPassIcon')" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-white transition-colors focus:outline-none" tabindex="-1" title="Toggle password visibility">
                            <span id="confirmPassIcon" class="material-symbols-rounded text-lg">visibility</span>
                        </button>
                    </div>
                </div>

                <button type="submit" class="w-full btn-gold text-center">
                    Reset Password
                </button>
            </form>

            <script>
                function togglePasswordVisibility(inputId, iconId) {
                    const input = document.getElementById(inputId);
                    const icon = document.getElementById(iconId);
                    if (!input || !icon) return;
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.textContent = 'visibility_off';
                    } else {
                        input.type = 'password';
                        icon.textContent = 'visibility';
                    }
                }
            </script>
        <?php else: ?>
            <div class="text-center mt-6">
                <a href="login.php" class="btn-gold inline-block w-full text-center">
                    Back to Login
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
