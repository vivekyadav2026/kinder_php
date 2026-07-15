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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Karigor Ledger Pro</title>
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
            <h1 class="text-2xl font-extrabold text-white tracking-tight">Create Account</h1>
            <p class="text-xs text-slate-400 mt-1">Start managing your goldsmith ledger easily</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-5 p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs flex items-center space-x-2">
                <i class="fa-solid fa-circle-exclamation"></i> <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Full Name</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-regular fa-user text-xs"></i></span>
                    <input type="text" name="name" required class="w-full bg-slate-900/60 border border-slate-800 rounded-xl pl-10 pr-4 py-3 text-slate-200 text-sm focus:outline-none focus:border-amber-400 transition-colors" placeholder="Suman Kanti Das">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Email Address</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-regular fa-envelope text-xs"></i></span>
                    <input type="email" name="email" required class="w-full bg-slate-900/60 border border-slate-800 rounded-xl pl-10 pr-4 py-3 text-slate-200 text-sm focus:outline-none focus:border-amber-400 transition-colors" placeholder="admin@admin.com">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-lock text-xs"></i></span>
                    <input type="password" name="password" required class="w-full bg-slate-900/60 border border-slate-800 rounded-xl pl-10 pr-4 py-3 text-slate-200 text-sm focus:outline-none focus:border-amber-400 transition-colors" placeholder="••••••••">
                </div>
            </div>

            <button type="submit" class="w-full py-3 rounded-xl text-sm font-bold text-slate-950 bg-gradient-to-r from-amber-500 to-amber-600 hover:opacity-90 transition-all shadow-lg shadow-amber-500/10 mt-6">
                Register & Login
            </button>
        </form>

        <p class="text-center text-xs text-slate-400 mt-6">
            Already have an account? <a href="login.php" class="text-amber-400 font-semibold hover:underline">Log in</a>
        </p>
    </div>
</body>
</html>
