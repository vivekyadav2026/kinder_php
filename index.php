<?php
require_once 'db.php';

// Fetch overall stats
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM baparis WHERE user_id = ?");
$stmt->execute([$userId]);
$totalBaparis = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT SUM(jama_fine) as total_jama, SUM(cash_received) as total_rec FROM fine_deposits WHERE user_id = ?");
$stmt->execute([$userId]);
$depStats = $stmt->fetch();
$totalJamaFine = $depStats['total_jama'] ?? 0.0;
$totalCashRec = $depStats['total_rec'] ?? 0.0;

$stmt = $pdo->prepare("SELECT SUM(total_kaj_fine) as total_kaj, SUM(total_profit_fine) as total_profit, SUM(cash_bill) as total_bill FROM kaj_entries WHERE user_id = ?");
$stmt->execute([$userId]);
$kajStats = $stmt->fetch();
$totalKajFine = $kajStats['total_kaj'] ?? 0.0;
$totalProfitFine = $kajStats['total_profit'] ?? 0.0;
$totalCashBill = $kajStats['total_bill'] ?? 0.0;

$netFineBalance = round($totalJamaFine - $totalKajFine, 3);
$netCashBalance = round($totalCashRec - $totalCashBill, 2);

// Fetch all Baparis with their individual balances
$stmt = $pdo->prepare("
    SELECT b.id, b.name, b.mobile, b.address,
           COALESCE(fd.total_jama, 0) as total_jama,
           COALESCE(fd.total_rec, 0) as total_rec,
           COALESCE(kj.total_kaj, 0) as total_kaj,
           COALESCE(kj.total_bill, 0) as total_bill
    FROM baparis b
    LEFT JOIN (
        SELECT bapari_id, SUM(jama_fine) as total_jama, SUM(cash_received) as total_rec
        FROM fine_deposits
        WHERE user_id = ?
        GROUP BY bapari_id
    ) fd ON b.id = fd.bapari_id
    LEFT JOIN (
        SELECT bapari_id, SUM(total_kaj_fine) as total_kaj, SUM(cash_bill) as total_bill
        FROM kaj_entries
        WHERE user_id = ?
        GROUP BY bapari_id
    ) kj ON b.id = kj.bapari_id
    WHERE b.user_id = ?
    ORDER BY b.name ASC
");
$stmt->execute([$userId, $userId, $userId]);
$baparis = $stmt->fetchAll();

// Dynamic Greeting
$hour = date('H');
$greeting = "Good Day";
if ($hour < 12) {
    $greeting = "Good Morning";
} elseif ($hour < 17) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}

require_once 'header.php';
?>

<!-- Dynamic Greeting & Header -->
<div class="mb-6 mt-2">
    <span class="text-desc uppercase tracking-widest text-[10px] font-bold">Workspace Overview</span>
    <h1 class="text-xl sm:text-2xl font-bold text-white tracking-tight mt-0.5">
        <?= $greeting ?>, <span class="text-[#F4B400]"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
    </h1>
</div>

<!-- 2x2 Quick Statistics Grid -->
<div class="grid grid-cols-2 gap-4 mb-6">
    <!-- Stat 1: Gold Bal -->
    <div class="premium-card gold-gradient-border glow-gold">
        <div class="flex items-center justify-between mb-2">
            <span class="text-desc font-semibold uppercase text-[10px]">Gold Balance</span>
            <span class="material-symbols-rounded text-lg text-[#F4B400]">scale</span>
        </div>
        <div class="text-xl font-bold text-white font-mono leading-none">
            <?= number_format($netFineBalance, 3) ?><span class="text-xs font-normal text-slate-400 ml-0.5">g</span>
        </div>
        <p class="text-[10px] text-slate-500 mt-1">Outstanding weight</p>
    </div>

    <!-- Stat 2: Cash Bal -->
    <div class="premium-card">
        <div class="flex items-center justify-between mb-2">
            <span class="text-desc font-semibold uppercase text-[10px]">Cash Balance</span>
            <span class="material-symbols-rounded text-lg text-emerald-400">payments</span>
        </div>
        <div class="text-xl font-bold text-white font-mono leading-none">
            ₹<?= number_format($netCashBalance, 2) ?>
        </div>
        <p class="text-[10px] text-slate-500 mt-1">Outstanding cash</p>
    </div>

    <!-- Stat 3: Total Customers -->
    <div class="premium-card">
        <div class="flex items-center justify-between mb-2">
            <span class="text-desc font-semibold uppercase text-[10px]">Customers</span>
            <span class="material-symbols-rounded text-lg text-indigo-400">group</span>
        </div>
        <div class="text-xl font-bold text-white leading-none">
            <?= $totalBaparis ?>
        </div>
        <p class="text-[10px] text-slate-500 mt-1">Registered accounts</p>
    </div>

    <!-- Stat 4: Profit Gold -->
    <div class="premium-card">
        <div class="flex items-center justify-between mb-2">
            <span class="text-desc font-semibold uppercase text-[10px]">Profit Gold</span>
            <span class="material-symbols-rounded text-lg text-pink-400">trending_up</span>
        </div>
        <div class="text-xl font-bold text-pink-400 font-mono leading-none">
            <?= number_format($totalProfitFine, 3) ?><span class="text-xs font-normal text-slate-400 ml-0.5">g</span>
        </div>
        <p class="text-[10px] text-slate-500 mt-1">Wastage earnings</p>
    </div>
</div>

<!-- Customer Ledger Accounts Section -->
<div class="mb-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="title-section text-white flex items-center">
            <span class="material-symbols-rounded text-[#F4B400] mr-2">account_balance_wallet</span> Customer Ledger
        </h2>
        <a href="baparis.php" class="text-xs text-[#F4B400] hover:underline flex items-center font-medium">
            View All <span class="material-symbols-rounded text-sm ml-0.5">arrow_forward</span>
        </a>
    </div>

    <div class="space-y-3">
        <?php if (empty($baparis)): ?>
            <!-- Redesigned Empty State -->
            <div class="premium-card text-center py-10 flex flex-col items-center justify-center">
                <span class="material-symbols-rounded text-5xl text-slate-600 mb-3">folder_open</span>
                <h3 class="text-sm font-semibold text-slate-300">No Customers Added</h3>
                <p class="text-xs text-slate-500 mt-1 max-w-[240px]">Create customer profiles to start recording Gold Jama and Kaarigari Jobs.</p>
                <a href="baparis.php?action=new" class="btn-gold mt-5 inline-flex items-center text-xs px-4 py-2">
                    <span class="material-symbols-rounded text-sm mr-1">person_add</span> Add First Customer
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($baparis as $b): 
                $fineBal = round($b['total_jama'] - $b['total_kaj'], 3);
                $cashBal = round($b['total_rec'] - $b['total_bill'], 2);
                $initials = strtoupper(substr($b['name'], 0, 2));
            ?>
                <!-- Customer Card Tile -->
                <div class="premium-card flex items-center justify-between hover:border-slate-700/80 transition-all">
                    <div class="flex items-center space-x-3.5 min-w-0">
                        <div class="w-10 h-10 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-[#F4B400] text-sm shrink-0">
                            <?= $initials ?>
                        </div>
                        <div class="min-w-0">
                            <a href="ledger.php?bapari_id=<?= $b['id'] ?>" class="font-semibold text-white hover:text-[#F4B400] transition-colors block text-sm truncate">
                                <?= htmlspecialchars($b['name']) ?>
                            </a>
                            <span class="text-[11px] text-slate-500 block truncate mt-0.5"><?= htmlspecialchars($b['mobile'] ?: 'No mobile') ?></span>
                        </div>
                    </div>
                    
                    <div class="text-right shrink-0">
                        <div class="text-xs font-mono font-bold <?= $fineBal >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>">
                            <?= $fineBal >= 0 ? '+' : '' ?><?= number_format($fineBal, 3) ?> g
                        </div>
                        <div class="text-[10px] font-mono text-slate-500 mt-1">
                            ₹<?= number_format($cashBal, 2) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Floating Action Button (FAB) and Modal -->
<button onclick="toggleFabMenu()" class="fab-btn tap-target" id="fabBtn">
    <span class="material-symbols-rounded text-2xl" id="fabIcon">add</span>
</button>

<!-- FAB Menu Backdrop -->
<div id="fabBackdrop" onclick="toggleFabMenu()" class="fixed inset-0 bg-[#0F172A]/80 backdrop-blur-sm z-30 hidden opacity-0 transition-opacity duration-300"></div>

<!-- FAB Actions Sheet -->
<div id="fabMenu" class="fixed bottom-[160px] right-5 z-40 hidden flex-col items-end space-y-3 pointer-events-none transform translate-y-4 scale-95 opacity-0 transition-all duration-300">
    <!-- Action 1 -->
    <a href="baparis.php?action=new" class="pointer-events-auto flex items-center space-x-2.5 px-5 py-2.5 rounded-xl bg-[#1E293B] border border-white/[0.06] text-white shadow-xl tap-target">
        <span class="text-xs font-semibold">New Customer</span>
        <span class="material-symbols-rounded text-lg text-[#F4B400] bg-[#F4B400]/10 p-1.5 rounded-lg">person_add</span>
    </a>
    <!-- Action 2 -->
    <a href="deposits.php?action=new" class="pointer-events-auto flex items-center space-x-2.5 px-5 py-2.5 rounded-xl bg-[#1E293B] border border-white/[0.06] text-white shadow-xl tap-target">
        <span class="text-xs font-semibold">Gold Jama Entry</span>
        <span class="material-symbols-rounded text-lg text-emerald-400 bg-emerald-400/10 p-1.5 rounded-lg">arrow_downward</span>
    </a>
    <!-- Action 3 -->
    <a href="kaj.php?action=new" class="pointer-events-auto flex items-center space-x-2.5 px-5 py-2.5 rounded-xl bg-[#1E293B] border border-white/[0.06] text-white shadow-xl tap-target">
        <span class="text-xs font-semibold">New Kaarigari Job</span>
        <span class="material-symbols-rounded text-lg text-indigo-400 bg-indigo-400/10 p-1.5 rounded-lg">construction</span>
    </a>
</div>

<script>
    let isFabOpen = false;
    function toggleFabMenu() {
        const backdrop = document.getElementById('fabBackdrop');
        const menu = document.getElementById('fabMenu');
        const icon = document.getElementById('fabIcon');
        const fabBtn = document.getElementById('fabBtn');
        
        isFabOpen = !isFabOpen;
        
        if (isFabOpen) {
            backdrop.classList.remove('hidden');
            menu.classList.remove('hidden');
            setTimeout(() => {
                backdrop.classList.add('opacity-100');
                menu.classList.remove('opacity-0', 'translate-y-4', 'scale-95');
                menu.classList.add('opacity-100', 'translate-y-0', 'scale-100');
            }, 10);
            icon.innerText = 'close';
            fabBtn.style.transform = 'rotate(90deg)';
        } else {
            backdrop.classList.remove('opacity-100');
            menu.classList.remove('opacity-100', 'translate-y-0', 'scale-100');
            menu.classList.add('opacity-0', 'translate-y-4', 'scale-95');
            setTimeout(() => {
                backdrop.classList.add('hidden');
                menu.classList.add('hidden');
            }, 300);
            icon.innerText = 'add';
            fabBtn.style.transform = 'rotate(0deg)';
        }
    }
</script>

<?php
require_once 'footer.php';
?>
