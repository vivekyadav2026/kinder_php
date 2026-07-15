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
    <meta name="theme-color" content="#0F172A">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Material Symbols Rounded Icon Pack -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" rel="stylesheet">
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/images/karigor-icon.png">
    
    <style>
        :root {
            --bg-color: #0F172A;
            --card-color: #1E293B;
            --gold-color: #F4B400;
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
            padding-top: calc(env(safe-area-inset-top, 0px) + 64px);
            padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 80px);
        }

        /* Card styles with gradient borders & modern elevations */
        .premium-card {
            background-color: var(--card-color);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .premium-card:active {
            transform: scale(0.98);
            border-color: rgba(244, 180, 0, 0.3);
        }
        
        .gold-gradient-border {
            border: 1px solid transparent;
            background: linear-gradient(var(--card-color), var(--card-color)) padding-box,
                        linear-gradient(135deg, var(--gold-color), rgba(255,255,255,0.05)) border-box;
        }
        
        .glow-gold {
            box-shadow: 0 0 20px rgba(244, 180, 0, 0.1);
        }

        /* Typography Override */
        .title-large {
            font-size: 24px;
            font-weight: 700;
            line-height: 1.3;
            letter-spacing: -0.01em;
        }
        .title-section {
            font-size: 16px;
            font-weight: 600;
            letter-spacing: -0.01em;
        }
        .card-value {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .text-desc {
            font-size: 12px;
            color: var(--text-secondary);
        }

        /* Modern Input Styling */
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
            border-color: var(--gold-color);
            box-shadow: 0 0 0 3px rgba(244, 180, 0, 0.15);
        }
        
        /* Premium Buttons */
        .btn-gold {
            background: linear-gradient(135deg, var(--gold-color) 0%, #D99B00 100%);
            color: #0F172A;
            font-weight: 600;
            border-radius: 14px;
            padding: 12px 24px;
            font-size: 15px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(244, 180, 0, 0.2);
        }
        .btn-gold:active {
            transform: scale(0.97);
            opacity: 0.9;
        }
        
        .btn-secondary {
            background-color: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.05);
            color: #ffffff;
            font-weight: 600;
            border-radius: 14px;
            padding: 12px 24px;
            font-size: 15px;
            transition: all 0.2s ease;
        }
        .btn-secondary:active {
            transform: scale(0.97);
            background-color: rgba(255, 255, 255, 0.1);
        }

        /* Material Symbols Utilities */
        .material-symbols-rounded {
            font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            display: inline-block;
            vertical-align: middle;
        }
        
        /* Tap feedback */
        .tap-target {
            transition: transform 0.1s ease, opacity 0.1s ease;
        }
        .tap-target:active {
            transform: scale(0.96);
            opacity: 0.85;
        }

        /* Floating Action Button */
        .fab-btn {
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 56px;
            height: 56px;
            border-radius: 28px;
            background: linear-gradient(135deg, var(--gold-color) 0%, #D99B00 100%);
            color: #0F172A;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 20px rgba(244, 180, 0, 0.35);
            z-index: 40;
            transition: all 0.2s ease;
        }
        .fab-btn:active {
            transform: scale(0.9);
        }
    </style>
</head>
<body>
    <!-- Premium Compact Top Header with responsive desktop menu navigation -->
    <header class="fixed top-0 left-0 right-0 z-50 bg-[#0F172A]/85 backdrop-blur-md border-b border-white/[0.04] px-4 py-3 flex items-center justify-between" style="padding-top: calc(env(safe-area-inset-top, 0px) + 12px);">
        <div class="max-w-6xl w-full mx-auto flex items-center justify-between">
            <a href="index.php" class="flex items-center space-x-2.5">
                <img src="assets/images/karigor-icon.png" alt="Dasgold Logo" class="w-8 h-8 rounded-lg object-cover border border-[#F4B400]/25">
                <span class="font-bold text-base tracking-tight text-white">Dasgold</span>
            </a>

            <!-- Laptop/Desktop Navigation Links (Shown only on larger screens) -->
            <div class="hidden md:flex items-center space-x-6 text-sm font-medium">
                <?php 
                $currHeader = basename($_SERVER['PHP_SELF']); 
                ?>
                <a href="index.php" class="transition-colors flex items-center space-x-1.5 <?= $currHeader == 'index.php' ? 'text-[#F4B400]' : 'text-slate-300 hover:text-[#F4B400]' ?>">
                    <span class="material-symbols-rounded text-lg">grid_view</span> <span>Home</span>
                </a>
                <a href="baparis.php" class="transition-colors flex items-center space-x-1.5 <?= $currHeader == 'baparis.php' ? 'text-[#F4B400]' : 'text-slate-300 hover:text-[#F4B400]' ?>">
                    <span class="material-symbols-rounded text-lg">group</span> <span>Customers</span>
                </a>
                <a href="deposits.php" class="transition-colors flex items-center space-x-1.5 <?= $currHeader == 'deposits.php' ? 'text-[#F4B400]' : 'text-slate-300 hover:text-[#F4B400]' ?>">
                    <span class="material-symbols-rounded text-lg">arrow_downward</span> <span>Gold Jama</span>
                </a>
                <a href="kaj.php" class="transition-colors flex items-center space-x-1.5 <?= $currHeader == 'kaj.php' ? 'text-[#F4B400]' : 'text-slate-300 hover:text-[#F4B400]' ?>">
                    <span class="material-symbols-rounded text-lg">construction</span> <span>Jobs</span>
                </a>
                <a href="reports.php" class="transition-colors flex items-center space-x-1.5 <?= $currHeader == 'reports.php' ? 'text-[#F4B400]' : 'text-slate-300 hover:text-[#F4B400]' ?>">
                    <span class="material-symbols-rounded text-lg">analytics</span> <span>Reports</span>
                </a>
            </div>
            
            <div class="flex items-center space-x-3.5 text-white/80">
                <button class="tap-target flex items-center justify-center w-8 h-8 rounded-full bg-white/[0.04]" title="Notifications">
                    <span class="material-symbols-rounded text-xl text-desc">notifications</span>
                </button>
                <a href="logout.php" class="tap-target flex items-center justify-center w-8 h-8 rounded-full bg-white/[0.04] text-rose-400" title="Logout">
                    <span class="material-symbols-rounded text-xl">power_settings_new</span>
                </a>
            </div>
        </div>
    </header>
    
    <div class="max-w-6xl mx-auto px-4 py-2">
