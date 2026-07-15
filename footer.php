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

    <!-- Desktop Footer -->
    <footer class="mt-16 mb-8 md:mb-6 text-center text-xs text-slate-600">
        <p>&copy; 2026 Karigor Ledger Pro. All rights reserved.</p>
    </footer>

    <!-- Service Worker Installer Script -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .then((reg) => console.log('PWA Service Worker: Registered', reg.scope))
                    .catch((err) => console.error('PWA Service Worker: Registration failed', err));
            });
        }
    </script>
</body>
</html>
