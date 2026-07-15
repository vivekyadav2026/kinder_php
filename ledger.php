<?php
require_once 'db.php';

$bapariId = intval($_GET['bapari_id'] ?? 0);

if ($bapariId <= 0) {
    echo "Invalid Bapari ID";
    exit();
}

// Fetch Bapari Details
$stmt = $pdo->prepare("SELECT * FROM baparis WHERE id = ? AND user_id = ?");
$stmt->execute([$bapariId, $userId]);
$bapari = $stmt->fetch();

if (!$bapari) {
    echo "Bapari not found";
    exit();
}

// Fetch Deposits
$stmt = $pdo->prepare("
    SELECT id, date, 'deposit' as type, jama_fine, 0.0 as kaj_fine, cash_received, 0.0 as cash_bill, remark, created_at 
    FROM fine_deposits 
    WHERE bapari_id = ? AND user_id = ?
");
$stmt->execute([$bapariId, $userId]);
$deposits = $stmt->fetchAll();

// Fetch Kaj Entries
$stmt = $pdo->prepare("
    SELECT id, date, 'kaj' as type, 0.0 as jama_fine, total_kaj_fine as kaj_fine, 0.0 as cash_received, cash_bill, remark, created_at 
    FROM kaj_entries 
    WHERE bapari_id = ? AND user_id = ?
");
$stmt->execute([$bapariId, $userId]);
$kajs = $stmt->fetchAll();

// Merge and sort ascending for running balance calculation
$entries = array_merge($deposits, $kajs);
usort($entries, function($a, $b) {
    $cmp = strcmp($a['date'], $b['date']);
    if ($cmp === 0) {
        return strcmp($a['created_at'], $b['created_at']);
    }
    return $cmp;
});

// Calculate running balances
$fineBal = 0.0;
$cashBal = 0.0;
$totalJama = 0.0;
$totalKaj = 0.0;
$totalRec = 0.0;
$totalBill = 0.0;

foreach ($entries as &$e) {
    $totalJama += floatval($e['jama_fine']);
    $totalKaj += floatval($e['kaj_fine']);
    $totalRec += floatval($e['cash_received']);
    $totalBill += floatval($e['cash_bill']);
    
    $fineBal += floatval($e['jama_fine']) - floatval($e['kaj_fine']);
    $cashBal += floatval($e['cash_received']) - floatval($e['cash_bill']);
    
    $e['running_fine'] = $fineBal;
    $e['running_cash'] = $cashBal;
    
    // If it's a Kaj entry, fetch items for description tooltip/view
    if ($e['type'] === 'kaj') {
        $stmtItems = $pdo->prepare("SELECT item, gross, net, hisab FROM kaj_items WHERE kaj_entry_id = ?");
        $stmtItems->execute([$e['id']]);
        $e['items'] = $stmtItems->fetchAll();
    }
}
unset($e); // Break reference

// Reverse for displaying most recent at the top
$displayEntries = array_reverse($entries);

require_once 'header.php';
?>

<!-- Back & Title -->
<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div class="flex items-center space-x-3">
        <a href="index.php" class="w-9 h-9 rounded-lg bg-slate-800 border border-slate-700/60 flex items-center justify-center text-slate-400 hover:text-white transition-colors">
            <i class="fa-solid fa-arrow-left text-sm"></i>
        </a>
        <div>
            <h1 class="text-2xl font-extrabold text-white flex items-center">
                <?= htmlspecialchars($bapari['name']) ?>
            </h1>
            <p class="text-xs text-slate-400">Mobile: <?= htmlspecialchars($bapari['mobile'] ?: '--') ?> | Address: <?= htmlspecialchars($bapari['address'] ?: '--') ?></p>
        </div>
    </div>
    
    <div class="flex items-center space-x-2">
        <a href="deposits.php?action=new" class="px-3.5 py-2 rounded-xl text-xs font-semibold text-slate-950 gold-bg hover:opacity-90 shadow-md transition-all">
            <i class="fa-solid fa-plus mr-1"></i> Add Deposit
        </a>
        <a href="kaj.php?action=new" class="px-3.5 py-2 rounded-xl text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-500 shadow-md transition-all">
            <i class="fa-solid fa-plus mr-1"></i> Add Kaj
        </a>
    </div>
</div>

<!-- Balances Row -->
<div class="grid grid-cols-2 gap-5 mb-8">
    <div class="glass-card rounded-2xl p-5 border-l-4 border-l-emerald-500">
        <span class="text-slate-400 text-xs font-bold uppercase tracking-wider block mb-1">Fine Gold Balance</span>
        <div class="text-2xl sm:text-3xl font-extrabold text-emerald-400 font-mono"><?= number_format($fineBal, 3) ?> g</div>
        <p class="text-[10px] text-slate-500 mt-0.5">Estimated outstanding weight</p>
    </div>
    <div class="glass-card rounded-2xl p-5 border-l-4 border-l-blue-500">
        <span class="text-slate-400 text-xs font-bold uppercase tracking-wider block mb-1">Cash Balance</span>
        <div class="text-2xl sm:text-3xl font-extrabold text-blue-400 font-mono">₹<?= number_format($cashBal, 2) ?></div>
        <p class="text-[10px] text-slate-500 mt-0.5">Estimated outstanding cash</p>
    </div>
</div>

<!-- Ledger Transaction Table -->
<div class="glass-card rounded-2xl border border-slate-800 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-800/80 flex items-center justify-between">
        <h3 class="text-base font-bold text-white"><i class="fa-solid fa-receipt text-amber-400 mr-2"></i> Account Ledger Statement</h3>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="bg-slate-900/40 text-slate-400 font-bold uppercase tracking-wider border-b border-slate-800">
                    <th class="px-4 py-3.5">Date</th>
                    <th class="px-4 py-3.5">Type</th>
                    <th class="px-4 py-3.5">Narration / Details</th>
                    <th class="px-4 py-3.5 text-right">Jama Fine (g)</th>
                    <th class="px-4 py-3.5 text-right">Kaj Fine (g)</th>
                    <th class="px-4 py-3.5 text-right">Fine Bal (g)</th>
                    <th class="px-4 py-3.5 text-right">Cash Rec.</th>
                    <th class="px-4 py-3.5 text-right">Cash Bill</th>
                    <th class="px-4 py-3.5 text-right">Cash Bal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-mono text-slate-300">
                <?php if (empty($displayEntries)): ?>
                <tr>
                    <td colspan="9" class="px-6 py-12 text-center text-slate-500 text-sm">
                        No transactions recorded for this Bapari yet.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($displayEntries as $e): ?>
                    <tr class="hover:bg-slate-800/20 transition-colors">
                        <td class="px-4 py-3 text-slate-400 font-sans">
                            <?= date('d-m-Y', strtotime($e['date'])) ?>
                        </td>
                        <td class="px-4 py-3 font-sans">
                            <?php if ($e['type'] === 'deposit'): ?>
                                <span class="bg-emerald-500/10 text-emerald-400 px-2 py-0.5 rounded border border-emerald-500/20 text-[10px]">Deposit</span>
                            <?php else: ?>
                                <span class="bg-indigo-500/10 text-indigo-400 px-2 py-0.5 rounded border border-indigo-500/20 text-[10px]">Kaj</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-slate-400 font-sans">
                            <?php 
                            if ($e['type'] === 'deposit') {
                                echo htmlspecialchars($e['remark'] ?: 'Gold deposit');
                            } else {
                                $itemsStr = [];
                                foreach ($e['items'] as $it) {
                                    $itemsStr[] = htmlspecialchars($it['item']) . " ({$it['gross']}g)";
                                }
                                echo implode(', ', $itemsStr) ?: 'Kaj ornament job';
                            }
                            ?>
                        </td>
                        <td class="px-4 py-3 text-right font-medium text-emerald-400">
                            <?= $e['jama_fine'] > 0 ? '+' . number_format($e['jama_fine'], 3) : '--' ?>
                        </td>
                        <td class="px-4 py-3 text-right font-medium text-rose-400">
                            <?= $e['kaj_fine'] > 0 ? '-' . number_format($e['kaj_fine'], 3) : '--' ?>
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-slate-200">
                            <?= number_format($e['running_fine'], 3) ?>
                        </td>
                        <td class="px-4 py-3 text-right text-blue-400">
                            <?= $e['cash_received'] > 0 ? '₹' . number_format($e['cash_received'], 2) : '--' ?>
                        </td>
                        <td class="px-4 py-3 text-right text-rose-400">
                            <?= $e['cash_bill'] > 0 ? '₹' . number_format($e['cash_bill'], 2) : '--' ?>
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-slate-200">
                            ₹<?= number_format($e['running_cash'], 2) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="bg-slate-900/30 text-slate-200 font-bold border-t border-slate-800">
                    <td colspan="3" class="px-4 py-4 text-left font-sans">Totals</td>
                    <td class="px-4 py-4 text-right text-emerald-400 font-mono">+<?= number_format($totalJama, 3) ?> g</td>
                    <td class="px-4 py-4 text-right text-rose-400 font-mono">-<?= number_format($totalKaj, 3) ?> g</td>
                    <td class="px-4 py-4 text-right text-slate-200 font-mono"><?= number_format($fineBal, 3) ?> g</td>
                    <td class="px-4 py-4 text-right text-blue-400 font-mono">₹<?= number_format($totalRec, 2) ?></td>
                    <td class="px-4 py-4 text-right text-rose-400 font-mono">₹<?= number_format($totalBill, 2) ?></td>
                    <td class="px-4 py-4 text-right text-slate-200 font-mono">₹<?= number_format($cashBal, 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php
require_once 'footer.php';
?>
