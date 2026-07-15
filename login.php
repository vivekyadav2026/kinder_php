<?php
require_once 'db.php';

$error = '';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Both email and password are required!';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            
            header("Location: index.php");
            exit();
        } else {
            $error = 'Invalid email or password!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Karigor Ledger Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            min-height: 100vh;
        }
        .glass-card {
            background: rgba(30, 41, 59, 0.45);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="w-full max-w-md glass-card rounded-3xl p-8 border border-slate-800 shadow-2xl">
        <div class="text-center mb-8">
            <img src="assets/images/karigor-icon.png" alt="Karigor Logo" class="w-16 h-16 rounded-2xl object-cover mx-auto shadow-lg shadow-amber-500/10 border border-amber-500/20 mb-3">
            <h1 class="text-2xl font-extrabold text-white tracking-tight">Welcome Back</h1>
            <p class="text-xs text-slate-400 mt-1">Log in to manage your gold ledger</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-5 p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs flex items-center space-x-2">
                <i class="fa-solid fa-circle-exclamation"></i> <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Email Address</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-regular fa-envelope text-xs"></i></span>
                    <input type="email" name="email" required class="w-full bg-slate-900/60 border border-slate-800 rounded-xl pl-10 pr-4 py-3 text-slate-200 text-sm focus:outline-none focus:border-amber-400 transition-colors" placeholder="admin@admin.com">
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Password</label>
                </div>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-lock text-xs"></i></span>
                    <input type="password" name="password" required class="w-full bg-slate-900/60 border border-slate-800 rounded-xl pl-10 pr-4 py-3 text-slate-200 text-sm focus:outline-none focus:border-amber-400 transition-colors" placeholder="••••••••">
                </div>
            </div>

            <button type="submit" class="w-full py-3 rounded-xl text-sm font-bold text-slate-950 bg-gradient-to-r from-amber-500 to-amber-600 hover:opacity-90 transition-all shadow-lg shadow-amber-500/10 mt-6">
                Log In
            </button>
        </form>

        <p class="text-center text-xs text-slate-400 mt-6">
            Don't have an account? <a href="register.php" class="text-amber-400 font-semibold hover:underline">Register</a>
        </p>
    </div>
</body>
</html>
