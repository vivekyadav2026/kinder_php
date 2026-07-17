<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Dasgold Ledger Pro</title>
    <!-- PWA Settings -->
    <link rel="manifest" href="manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#050505">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Material Symbols Rounded Icon Pack -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block" rel="stylesheet">
    <!-- Favicon -->
    <link class="rounded-lg" rel="icon" type="image/png" href="assets/images/karigor-icon.png">
    
    <style>
        .material-symbols-rounded {
            font-family: 'Material Symbols Rounded';
            font-weight: normal;
            font-style: normal;
            line-height: 1;
            letter-spacing: normal;
            text-transform: none;
            display: inline-block;
            white-space: nowrap;
            word-wrap: normal;
            direction: ltr;
            -webkit-font-smoothing: antialiased;
            font-feature-settings: 'liga';
        }
        :root {
            --bg-color: #050505;
            --card-color: #111111;
            --gold-color: #d8a735;
            --accent-color: #6366F1;
            --success-color: #10B981;
            --danger-color: #EF4444;
            --text-secondary: #94A3B8;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-color);
            min-height: 100vh;
            color: #ffffff;
            overscroll-behavior-y: contain;
            padding-top: calc(env(safe-area-inset-top, 0px) + 56px); /* Mobile Padding: Rates bar only */
            padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 90px);
        }

        @media (min-width: 768px) {
            body {
                padding-top: calc(env(safe-area-inset-top, 0px) + 112px); /* Desktop Padding: Rates bar + Logo Header */
            }
        }

        /* Glassmorphic premium card layouts */
        .premium-card {
            background-color: var(--card-color);
            border-radius: 16px;
            padding: 18px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.4);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .gold-border {
            border: 1px solid rgba(216, 167, 53, 0.35);
            box-shadow: 0 4px 20px rgba(216, 167, 53, 0.05);
        }

        /* Typography Override */
        .text-desc {
            font-size: 10px;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.08em;
        }

        /* Modern Input Styling */
        .premium-input {
            width: 100%;
            background-color: rgba(20, 20, 20, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 14px 16px;
            color: #ffffff;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        .premium-input:focus {
            outline: none;
            border-color: var(--gold-color);
            box-shadow: 0 0 0 3px rgba(216, 167, 53, 0.15);
        }
        
        /* Premium Buttons */
        .btn-gold {
            background: linear-gradient(135deg, #e5b842 0%, #c19224 100%);
            color: #050505;
            font-weight: 700;
            border-radius: 24px;
            padding: 14px 28px;
            font-size: 15px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: center;
            box-shadow: 0 4px 14px rgba(216, 167, 53, 0.2);
        }
        .btn-gold:active {
            transform: scale(0.97);
            opacity: 0.95;
        }
        
        .btn-secondary {
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #ffffff;
            font-weight: 700;
            border-radius: 24px;
            padding: 14px 28px;
            font-size: 15px;
            transition: all 0.2s ease;
            text-align: center;
        }
        .btn-secondary:active {
            transform: scale(0.97);
        }

        /* Material Symbols Utilities */
        .material-symbols-rounded {
            font-variation-settings: 'FILL' 1, 'wght' 500, 'GRAD' 0, 'opsz' 24;
            display: inline-block;
            vertical-align: middle;
        }
        
        /* Tap feedback */
        .tap-target {
            transition: transform 0.15s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.15s ease;
        }
        .tap-target:active {
            transform: scale(0.95);
            opacity: 0.8;
        }

        /* Floating Action Button */
        .fab-btn {
            position: fixed;
            bottom: 78px;
            right: 16px;
            width: 60px;
            height: 60px;
            border-radius: 30px;
            background: linear-gradient(135deg, #e5b842 0%, #c19224 100%);
            color: #050505;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 20px rgba(216, 167, 53, 0.35);
            z-index: 40;
            transition: all 0.2s ease;
        }
        .fab-btn:active {
            transform: scale(0.9);
        }

        /* CSS Keyframe Animations for premium feel on mobile */
        @keyframes slideUp {
            from {
                transform: translateY(12px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .animate-slide-up {
            animation: slideUp 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        .animate-fade-in {
            animation: fadeIn 0.25s ease-out forwards;
        }
    </style>
</head>
<body class="animate-fade-in">
    <!-- Live Rate Top Bar (Replicated from screenshot but with rich premium design) -->
    <div class="fixed top-0 left-0 right-0 z-50 bg-[#070708]/95 backdrop-blur-md border-b border-white/[0.04] h-10 px-3 flex items-center justify-between no-print" style="padding-top: env(safe-area-inset-top, 0px);">
        <div class="flex items-center space-x-2.5 overflow-x-auto no-scrollbar scroll-smooth">
            <!-- Pulse Dot indicator for active rates -->
            <div class="flex items-center space-x-1 bg-[#d8a735]/10 border border-[#d8a735]/25 rounded-full px-2 py-0.5 shrink-0">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-[8px] font-extrabold uppercase text-[#d8a735] tracking-wider">Live Rates</span>
            </div>
            
            <div class="flex items-center space-x-2 text-[10px] font-bold text-white shrink-0">
                <!-- 24K Badge -->
                <span class="flex items-center bg-white/[0.02] border border-white/[0.06] rounded-xl px-2.5 py-0.5 font-mono shadow-inner shadow-black/20">
                    <span class="text-[#d8a735] font-sans font-extrabold mr-1">24K</span>
                    <span class="text-slate-200">₹<?= number_format($rate24k, 0) ?></span>
                    <span class="text-slate-500 font-sans font-normal text-[8px] ml-0.5">/g</span>
                </span>
                
                <!-- 22K Badge -->
                <span class="flex items-center bg-white/[0.02] border border-white/[0.06] rounded-xl px-2.5 py-0.5 font-mono shadow-inner shadow-black/20">
                    <span class="text-[#c19224] font-sans font-extrabold mr-1">22K</span>
                    <span class="text-slate-200">₹<?= number_format($rate22k, 0) ?></span>
                    <span class="text-slate-500 font-sans font-normal text-[8px] ml-0.5">/g</span>
                </span>
                
                <!-- Silver (AG) Badge -->
                <span class="flex items-center bg-white/[0.02] border border-white/[0.06] rounded-xl px-2.5 py-0.5 font-mono shadow-inner shadow-black/20">
                    <span class="text-slate-400 font-sans font-extrabold mr-1">AG</span>
                    <span class="text-slate-200">₹<?= number_format($rateAg, 0) ?></span>
                    <span class="text-slate-500 font-sans font-normal text-[8px] ml-0.5">/g</span>
                </span>
            </div>
        </div>
        
        <button onclick="window.location.reload()" class="w-7 h-7 rounded-lg hover:bg-white/[0.04] flex items-center justify-center text-slate-400 hover:text-[#d8a735] transition-all tap-target shrink-0 ml-2">
            <span class="material-symbols-rounded text-sm">refresh</span>
        </button>
    </div>

    <!-- Main Header Bar (Desktop Only - Hidden on Mobile Views to match screenshots) -->
    <header class="hidden md:flex fixed top-10 left-0 right-0 z-40 bg-[#0A0A0A]/95 backdrop-blur-md border-b border-white/[0.04] h-16 px-4 flex items-center justify-between">
        <!-- Logo and Brand Title -->
        <a href="index.php" class="flex items-center space-x-2.5">
            <img src="assets/images/karigor-icon.png" alt="Dasgold Logo" class="w-9 h-9 rounded-xl object-cover border border-[#d8a735]/30 shadow-md">
            <span class="text-lg font-extrabold text-white tracking-tight">Dasgold</span>
        </a>

        <!-- Desktop Horizontal Navigation (Menu & Submenus) -->
        <nav class="flex items-center space-x-6 text-xs font-bold text-slate-400">
            <a href="index.php" class="hover:text-[#d8a735] transition-colors py-2">Dashboard</a>
            
            <!-- Customers Menu with Dropdown -->
            <div class="relative group">
                <button class="hover:text-[#d8a735] transition-colors py-2 flex items-center space-x-1 focus:outline-none">
                    <span>Bapari</span>
                    <span class="material-symbols-rounded text-sm">keyboard_arrow_down</span>
                </button>
                <div class="absolute left-0 mt-1 w-44 rounded-xl bg-[#121212] border border-white/[0.06] p-1.5 shadow-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                    <a href="baparis.php" class="block px-3.5 py-2 rounded-lg hover:bg-white/[0.03] hover:text-[#d8a735]">Bapari List</a>
                    <a href="baparis.php?action=new" class="block px-3.5 py-2 rounded-lg hover:bg-white/[0.03] hover:text-[#d8a735]">Add Bapari</a>
                </div>
            </div>

            <!-- Entry Menu with Dropdown -->
            <div class="relative group">
                <button class="hover:text-[#d8a735] transition-colors py-2 flex items-center space-x-1 focus:outline-none">
                    <span>Entry</span>
                    <span class="material-symbols-rounded text-sm">keyboard_arrow_down</span>
                </button>
                <div class="absolute left-0 mt-1 w-44 rounded-xl bg-[#121212] border border-white/[0.06] p-1.5 shadow-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                    <a href="entry.php?tab=deposit" class="block px-3.5 py-2 rounded-lg hover:bg-white/[0.03] hover:text-[#d8a735]">Fine Deposit</a>
                    <a href="entry.php?tab=kaj" class="block px-3.5 py-2 rounded-lg hover:bg-white/[0.03] hover:text-[#d8a735]">Kaj Entry</a>
                </div>
            </div>

            <a href="reports.php" class="hover:text-[#d8a735] transition-colors py-2">Ledger Reports</a>
            <?php if ($isAdmin): ?>
                <a href="admin.php" class="hover:text-[#d8a735] transition-colors py-2 text-[#d8a735] flex items-center space-x-0.5"><span class="material-symbols-rounded text-xs">shield</span> <span>Admin</span></a>
            <?php endif; ?>
            <a href="settings.php" class="hover:text-[#d8a735] transition-colors py-2">Settings</a>
        </nav>

        <!-- Right Side Icons (Notification & Logout) -->
        <div class="flex items-center space-x-3.5">
            <button class="w-9 h-9 rounded-xl bg-slate-900/60 border border-white/[0.04] flex items-center justify-center text-slate-400 hover:text-[#d8a735] transition-colors tap-target relative">
                <span class="material-symbols-rounded text-xl">notifications</span>
                <span class="absolute top-2 right-2 w-1.5 h-1.5 bg-rose-500 rounded-full animate-pulse"></span>
            </button>
            <a href="logout.php" class="w-9 h-9 rounded-xl bg-slate-900/60 border border-white/[0.04] flex items-center justify-center text-slate-400 hover:text-rose-500 transition-colors tap-target">
                <span class="material-symbols-rounded text-xl">power_settings_new</span>
            </a>
        </div>
    </header>

    <?php if (isset($_SESSION['impersonator_id'])): ?>
        <div class="fixed top-10 left-0 right-0 z-30 bg-amber-500 text-slate-950 px-4 py-2 text-center text-xs font-bold flex items-center justify-center space-x-2 shadow-md" style="padding-top: env(safe-area-inset-top, 0px);">
            <span>⚠️ Viewing: <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong></span>
            <a href="admin.php?stop_impersonating=1" class="underline hover:text-slate-800 ml-2 font-extrabold flex items-center"><span class="material-symbols-rounded text-xs mr-0.5">logout</span> Return to Admin</a>
        </div>
        <!-- Adjust body top padding when banner is active -->
        <style>
            body {
                padding-top: calc(env(safe-area-inset-top, 0px) + 92px) !important;
            }
            @media (min-width: 768px) {
                body {
                    padding-top: calc(env(safe-area-inset-top, 0px) + 148px) !important;
                }
            }
        </style>
    <?php endif; ?>
    
    <div class="max-w-6xl mx-auto px-4 pt-4 animate-slide-up">
