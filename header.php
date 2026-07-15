<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karigor Ledger Pro</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/images/karigor-icon.png">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            min-height: 100vh;
            color: #f8fafc;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Outfit', sans-serif;
        }
        .glass-card {
            background: rgba(30, 41, 59, 0.45);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .gold-glow {
            box-shadow: 0 0 15px rgba(212, 175, 55, 0.15);
        }
        .gold-border {
            border-color: rgba(212, 175, 55, 0.3);
        }
        .gold-text {
            color: #fbbf24;
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 50%, #fef08a 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .gold-bg {
            background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
        }
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(15, 23, 42, 0.5);
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(212, 175, 55, 0.3);
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(212, 175, 55, 0.6);
        }
    </style>
</head>
<body class="pb-12">
    <!-- Sticky Navigation Header -->
    <nav class="sticky top-0 z-50 glass-card border-b border-slate-800 px-4 py-3.5 mb-6">
        <div class="max-w-6xl mx-auto flex items-center justify-between">
            <a href="index.php" class="flex items-center space-x-2.5">
                <img src="assets/images/karigor-icon.png" alt="Karigor Logo" class="w-10 h-10 rounded-xl object-cover shadow-lg shadow-amber-500/10 border border-amber-500/20">
                <div>
                    <span class="font-extrabold text-xl tracking-tight text-white block">Karigor Ledger</span>
                    <span class="text-[10px] uppercase tracking-widest text-amber-400 font-semibold block -mt-1">PRO PHP</span>
                </div>
            </a>
            
            <div class="hidden md:flex items-center space-x-6">
                <a href="index.php" class="text-slate-300 hover:text-amber-400 transition-colors flex items-center space-x-1.5"><i class="fa-solid fa-chart-pie text-sm"></i> <span>Dashboard</span></a>
                <a href="baparis.php" class="text-slate-300 hover:text-amber-400 transition-colors flex items-center space-x-1.5"><i class="fa-solid fa-users text-sm"></i> <span>Baparis</span></a>
                <a href="deposits.php" class="text-slate-300 hover:text-amber-400 transition-colors flex items-center space-x-1.5"><i class="fa-solid fa-circle-down text-sm"></i> <span>Fine Deposits</span></a>
                <a href="kaj.php" class="text-slate-300 hover:text-amber-400 transition-colors flex items-center space-x-1.5"><i class="fa-solid fa-hammer text-sm"></i> <span>Kaj Entries</span></a>
                <a href="reports.php" class="text-slate-300 hover:text-amber-400 transition-colors flex items-center space-x-1.5"><i class="fa-solid fa-file-invoice text-sm"></i> <span>Reports</span></a>
            </div>

            <div class="flex items-center space-x-3">
                <div class="hidden sm:flex flex-col text-right">
                    <span class="text-xs text-slate-400">Welcome,</span>
                    <span class="text-sm font-semibold text-slate-200"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Guest') ?></span>
                </div>
                <a href="logout.php" class="w-9 h-9 rounded-full bg-slate-800 hover:bg-rose-500/10 border border-slate-700 hover:border-rose-500/30 flex items-center justify-center text-slate-400 hover:text-rose-400 transition-colors" title="Log Out">
                    <i class="fa-solid fa-power-off text-sm"></i>
                </a>
            </div>
        </div>
        
        <!-- Mobile Navigation bar -->
        <div class="flex md:hidden justify-around mt-4 pt-3 border-t border-slate-800/80 text-xs">
            <a href="index.php" class="text-slate-400 hover:text-amber-400 transition-colors flex flex-col items-center"><i class="fa-solid fa-chart-pie text-lg mb-1"></i><span>Dashboard</span></a>
            <a href="baparis.php" class="text-slate-400 hover:text-amber-400 transition-colors flex flex-col items-center"><i class="fa-solid fa-users text-lg mb-1"></i><span>Baparis</span></a>
            <a href="deposits.php" class="text-slate-400 hover:text-amber-400 transition-colors flex flex-col items-center"><i class="fa-solid fa-circle-down text-lg mb-1"></i><span>Deposits</span></a>
            <a href="kaj.php" class="text-slate-400 hover:text-amber-400 transition-colors flex flex-col items-center"><i class="fa-solid fa-hammer text-lg mb-1"></i><span>Kaj</span></a>
            <a href="reports.php" class="text-slate-400 hover:text-amber-400 transition-colors flex flex-col items-center"><i class="fa-solid fa-file-invoice text-lg mb-1"></i><span>Reports</span></a>
        </div>
    </nav>
    <div class="max-w-6xl mx-auto px-4">
