<?php
require_once 'db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $name = trim($_POST['name'] ?? '');

    if (empty($email) || empty($password) || empty($name)) {
        $error = 'All fields are required!';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long!';
    } else {
        // Check if user already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email is already registered!';
        } else {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, name) VALUES (?, ?, ?)");
            $stmt->execute([$email, $passwordHash, $name]);
            
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            
            header("Location: index.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Register - Dasgold Ledger Pro</title>
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
        .material-symbols-rounded {
            font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="w-full max-w-md premium-card p-8">
        <div class="text-center mb-8">
            <img src="assets/images/karigor-icon.png" alt="Dasgold Logo" class="w-16 h-16 rounded-2xl object-cover mx-auto border border-[#F4B400]/25 shadow-lg shadow-[#F4B400]/10 mb-4">
            <h1 class="text-2xl font-extrabold text-white tracking-tight">Create Account</h1>
            <p class="text-xs text-slate-400 mt-1">Start managing your goldsmith accounts ledger</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-5 p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs flex items-center space-x-2">
                <span class="material-symbols-rounded text-base">error</span> <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Full Name</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-500"><span class="material-symbols-rounded text-lg">person</span></span>
                    <input type="text" name="name" required class="premium-input pl-11" placeholder="Suman Kanti Das">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Email Address</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-500"><span class="material-symbols-rounded text-lg">mail</span></span>
                    <input type="email" name="email" required class="premium-input pl-11" placeholder="admin@admin.com">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-500"><span class="material-symbols-rounded text-lg">lock</span></span>
                    <input type="password" name="password" required class="premium-input pl-11" placeholder="••••••••">
                </div>
            </div>

            <button type="submit" class="w-full btn-gold mt-6">
                Register & Login
            </button>
        </form>

        <p class="text-center text-xs text-slate-400 mt-6">
            Already have an account? <a href="login.php" class="text-[#F4B400] font-semibold hover:underline">Login</a>
        </p>
    </div>
</body>
</html>
