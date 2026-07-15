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

require_once 'header.php';
?>

<!-- Header Banner -->
<div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
    <div>
        <h1 class="text-3xl font-extrabold tracking-tight text-white flex items-center">
            <span class="gold-text mr-2"><i class="fa-solid fa-chart-line"></i></span> Dashboard
        </h1>
        <p class="text-slate-400 text-sm mt-1">Overview of your gold accounts, baparis, and transaction summaries.</p>
    </div>
    
    <div class="flex flex-wrap gap-2.5">
        <a href="baparis.php?action=new" class="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-slate-800 hover:bg-slate-700 border border-slate-700/60 transition-all flex items-center space-x-2">
            <i class="fa-solid fa-user-plus text-amber-400"></i> <span>Add Bapari</span>
        </a>
        <a href="deposits.php?action=new" class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-950 gold-bg hover:opacity-90 shadow-md shadow-amber-500/10 transition-all flex items-center space-x-2">
            <i class="fa-solid fa-circle-down"></i> <span>Add Fine Deposit</span>
        </a>
        <a href="kaj.php?action=new" class="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 shadow-md shadow-indigo-600/10 transition-all flex items-center space-x-2">
            <i class="fa-solid fa-hammer"></i> <span>Add Kaj Entry</span>
        </a>
    </div>
</div>

<!-- Metrics Row -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    <!-- Stat Card 1 -->
    <div class="glass-card gold-glow rounded-2xl p-5 border-l-4 border-l-amber-500">
        <div class="flex items-center justify-between mb-3">
            <span class="text-slate-400 text-xs font-bold uppercase tracking-wider">Total Baparis</span>
            <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center text-amber-400"><i class="fa-solid fa-users"></i></div>
        </div>
        <div class="text-3xl font-extrabold text-white"><?= $totalBaparis ?></div>
        <p class="text-[11px] text-slate-500 mt-1">Active ledger accounts</p>
    </div>

    <!-- Stat Card 2 -->
    <div class="glass-card rounded-2xl p-5 border-l-4 border-l-emerald-500">
        <div class="flex items-center justify-between mb-3">
            <span class="text-slate-400 text-xs font-bold uppercase tracking-wider">Net Fine Balance</span>
            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-400"><i class="fa-solid fa-scale-balanced"></i></div>
        </div>
        <div class="text-3xl font-extrabold text-emerald-400"><?= number_format($netFineBalance, 3) ?> <span class="text-xs font-medium text-slate-400">g</span></div>
        <p class="text-[11px] text-slate-500 mt-1">Total outstanding gold weight</p>
    </div>

    <!-- Stat Card 3 -->
    <div class="glass-card rounded-2xl p-5 border-l-4 border-l-blue-500">
        <div class="flex items-center justify-between mb-3">
            <span class="text-slate-400 text-xs font-bold uppercase tracking-wider">Net Cash Balance</span>
            <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center text-blue-400"><i class="fa-solid fa-indian-rupee-sign"></i></div>
        </div>
        <div class="text-3xl font-extrabold text-blue-400">₹<?= number_format($netCashBalance, 2) ?></div>
        <p class="text-[11px] text-slate-500 mt-1">Outstanding cash ledger</p>
    </div>

    <!-- Stat Card 4 -->
    <div class="glass-card rounded-2xl p-5 border-l-4 border-l-pink-500">
        <div class="flex items-center justify-between mb-3">
            <span class="text-slate-400 text-xs font-bold uppercase tracking-wider">Total Profit Fine</span>
            <div class="w-8 h-8 rounded-lg bg-pink-500/10 flex items-center justify-center text-pink-400"><i class="fa-solid fa-chart-line-up"></i></div>
        </div>
        <div class="text-3xl font-extrabold text-pink-400"><?= number_format($totalProfitFine, 3) ?> <span class="text-xs font-medium text-slate-400">g</span></div>
        <p class="text-[11px] text-slate-500 mt-1">Wastage earnings accumulated</p>
    </div>
</div>

<!-- Baparis Ledger Summary Table -->
<div class="glass-card rounded-2xl border border-slate-800 overflow-hidden">
    <div class="px-6 py-4.5 border-b border-slate-800/80 flex items-center justify-between">
        <h3 class="text-lg font-bold text-white flex items-center">
            <i class="fa-solid fa-list-check text-amber-400 mr-2 text-base"></i> Bapari Ledger Accounts
        </h3>
        <span class="text-xs bg-slate-800 text-slate-400 px-3 py-1.5 rounded-lg border border-slate-700/50">Sorted A-Z</span>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-900/40 text-slate-400 text-xs font-bold uppercase tracking-wider border-b border-slate-800">
                    <th class="px-6 py-3.5">Bapari Name</th>
                    <th class="px-6 py-3.5">Contact Details</th>
                    <th class="px-6 py-3.5 text-right">Fine Balance (g)</th>
                    <th class="px-6 py-3.5 text-right">Cash Balance</th>
                    <th class="px-6 py-3.5 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                <?php if (empty($baparis)): ?>
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-slate-500 text-sm">
                        <div class="flex flex-col items-center justify-center space-y-2">
                            <i class="fa-regular fa-folder-open text-3xl text-slate-600"></i>
                            <p>No Bapari accounts found. Start by adding a Bapari.</p>
                            <a href="baparis.php?action=new" class="text-amber-400 hover:underline text-xs font-semibold">Add First Bapari &rarr;</a>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($baparis as $b): 
                        $fineBal = round($b['total_jama'] - $b['total_kaj'], 3);
                        $cashBal = round($b['total_rec'] - $b['total_bill'], 2);
                    ?>
                    <tr class="hover:bg-slate-800/25 transition-colors">
                        <td class="px-6 py-4">
                            <a href="ledger.php?bapari_id=<?= $b['id'] ?>" class="font-semibold text-white hover:text-amber-400 transition-colors block">
                                <?= htmlspecialchars($b['name']) ?>
                            </a>
                            <span class="text-xs text-slate-500 block truncate max-w-xs"><?= htmlspecialchars($b['address'] ?? 'No address') ?></span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-300">
                            <?= htmlspecialchars($b['mobile'] ?: '--') ?>
                        </td>
                        <td class="px-6 py-4 text-right font-mono font-medium text-sm <?= $fineBal >= 0 ? 'text-emerald-400' : 'text-red-400' ?>">
                            <?= number_format($fineBal, 3) ?> g
                        </td>
                        <td class="px-6 py-4 text-right font-mono font-medium text-sm <?= $cashBal >= 0 ? 'text-blue-400' : 'text-rose-400' ?>">
                            ₹<?= number_format($cashBal, 2) ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <a href="ledger.php?bapari_id=<?= $b['id'] ?>" class="inline-flex items-center space-x-1 px-3 py-1.5 rounded-lg bg-amber-500/10 text-amber-400 hover:bg-amber-500 hover:text-slate-950 transition-all text-xs font-semibold">
                                <i class="fa-solid fa-book-open"></i> <span>View Ledger</span>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once 'footer.php';
?>
