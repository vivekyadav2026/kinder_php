    </div> <!-- Close max-w-6xl -->
    
    <!-- Mobile Sticky Bottom Navigation Bar (Pill style) -->
    <div class="fixed bottom-0 left-0 right-0 z-50 bg-[#0F172A]/90 backdrop-blur-lg border-t border-white/[0.04] md:hidden block shadow-2xl" style="padding-bottom: env(safe-area-inset-bottom, 0px);">
        <div class="flex justify-around items-center h-16 px-2">
            <?php 
            $curr = basename($_SERVER['PHP_SELF']); 
            ?>
            <a href="index.php" class="flex flex-col items-center justify-center py-1.5 w-16 rounded-2xl transition-all tap-target <?= $curr == 'index.php' ? 'text-[#F4B400]' : 'text-slate-400' ?>">
                <span class="material-symbols-rounded text-[22px] <?= $curr == 'index.php' ? 'fill-1' : '' ?>">grid_view</span>
                <span class="text-[9px] mt-1 font-semibold">Home</span>
            </a>
            <a href="baparis.php" class="flex flex-col items-center justify-center py-1.5 w-16 rounded-2xl transition-all tap-target <?= $curr == 'baparis.php' ? 'text-[#F4B400]' : 'text-slate-400' ?>">
                <span class="material-symbols-rounded text-[22px] <?= $curr == 'baparis.php' ? 'fill-1' : '' ?>">group</span>
                <span class="text-[9px] mt-1 font-semibold">Customers</span>
            </a>
            <a href="deposits.php" class="flex flex-col items-center justify-center py-1.5 w-16 rounded-2xl transition-all tap-target <?= $curr == 'deposits.php' ? 'text-[#F4B400]' : 'text-slate-400' ?>">
                <span class="material-symbols-rounded text-[22px] <?= $curr == 'deposits.php' ? 'fill-1' : '' ?>">arrow_downward</span>
                <span class="text-[9px] mt-1 font-semibold">Jama</span>
            </a>
            <a href="kaj.php" class="flex flex-col items-center justify-center py-1.5 w-16 rounded-2xl transition-all tap-target <?= $curr == 'kaj.php' ? 'text-[#F4B400]' : 'text-slate-400' ?>">
                <span class="material-symbols-rounded text-[22px] <?= $curr == 'kaj.php' ? 'fill-1' : '' ?>">construction</span>
                <span class="text-[9px] mt-1 font-semibold">Jobs</span>
            </a>
            <a href="reports.php" class="flex flex-col items-center justify-center py-1.5 w-16 rounded-2xl transition-all tap-target <?= $curr == 'reports.php' ? 'text-[#F4B400]' : 'text-slate-400' ?>">
                <span class="material-symbols-rounded text-[22px] <?= $curr == 'reports.php' ? 'fill-1' : '' ?>">analytics</span>
                <span class="text-[9px] mt-1 font-semibold">Reports</span>
            </a>
        </div>
    </div>

    <!-- PWA Install Action Prompt Card -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <div id="installAppContainer" class="hidden max-w-md mx-auto px-4 mt-8 mb-4">
            <button onclick="installPWA()" class="w-full flex items-center justify-center space-x-2 py-3.5 rounded-2xl bg-gradient-to-r from-amber-500 to-amber-600 text-slate-950 font-bold text-sm shadow-xl shadow-amber-500/10 tap-target">
                <span class="material-symbols-rounded">download</span>
                <span>Download Dasgold App</span>
            </button>
        </div>
    <?php endif; ?>

    <!-- Desktop Footer -->
    <footer class="mt-16 mb-8 md:mb-6 text-center text-xs text-slate-600">
        <p>&copy; 2026 Dasgold Ledger Pro. All rights reserved.</p>
    </footer>

    <!-- Service Worker Installer & PWA Install Listener Script -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .then((reg) => console.log('PWA Service Worker: Registered', reg.scope))
                    .catch((err) => console.error('PWA Service Worker: Registration failed', err));
            });
        }

        // PWA Native Installation Prompter Logic
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            const installContainer = document.getElementById('installAppContainer');
            if (installContainer) {
                installContainer.classList.remove('hidden');
            }
        });

        function installPWA() {
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('User accepted the PWA install prompt');
                }
                deferredPrompt = null;
                const installContainer = document.getElementById('installAppContainer');
                if (installContainer) {
                    installContainer.classList.add('hidden');
                }
            });
        }

        // iOS Safari Specific Installation Tooltip Fallback
        window.addEventListener('DOMContentLoaded', () => {
            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches;
            if (isIOS && !isStandalone) {
                const installContainer = document.getElementById('installAppContainer');
                if (installContainer) {
                    const installBtn = installContainer.querySelector('button');
                    if (installBtn) {
                        installBtn.outerHTML = `
                            <div class="w-full bg-[#1E293B] border border-white/[0.06] p-4 rounded-2xl text-center text-xs text-slate-300 shadow-xl">
                                <span class="font-semibold text-white flex items-center justify-center mb-1.5"><span class="material-symbols-rounded text-base text-[#F4B400] mr-1.5">download</span> Install Dasgold App</span>
                                To install on iPhone, tap the <span class="font-bold text-white">Share</span> button below and select <span class="font-bold text-[#F4B400]">Add to Home Screen</span>.
                            </div>
                        `;
                    }
                    installContainer.classList.remove('hidden');
                }
            }
        });
    </script>
</body>
</html>
