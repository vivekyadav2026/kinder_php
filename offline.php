<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Offline - Karigor Ledger Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
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
    <div class="w-full max-w-md glass-card rounded-3xl p-8 border border-slate-800 shadow-2xl text-center">
        <img src="assets/images/karigor-icon.png" alt="Karigor Logo" class="w-20 h-20 rounded-3xl object-cover mx-auto shadow-lg shadow-amber-500/10 border border-amber-500/20 mb-6">
        
        <div class="w-16 h-16 rounded-full bg-amber-500/10 flex items-center justify-center text-amber-400 text-2xl mx-auto mb-4">
            <i class="fa-solid fa-wifi-slash"></i>
        </div>
        
        <h1 class="text-2xl font-extrabold text-white tracking-tight">You are Offline</h1>
        <p class="text-sm text-slate-400 mt-2 max-w-xs mx-auto">Please check your internet connection. Some features are unavailable without a network connection.</p>
        
        <button onclick="window.location.reload()" class="w-full py-3 rounded-xl text-sm font-bold text-slate-950 bg-gradient-to-r from-amber-500 to-amber-600 hover:opacity-90 transition-all shadow-lg shadow-amber-500/10 mt-8 flex items-center justify-center space-x-2">
            <i class="fa-solid fa-rotate-right"></i> <span>Try Again</span>
        </button>
    </div>
</body>
</html>
