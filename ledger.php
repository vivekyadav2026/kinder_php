<?php
require_once 'db.php';

$bapariId = intval($_GET['bapari_id'] ?? 0);

if ($bapariId <= 0) {
    echo "Invalid Customer ID";
    exit();
}

// Fetch Bapari Details
$stmt = $pdo->prepare("SELECT * FROM baparis WHERE id = ? AND user_id = ?");
$stmt->execute([$bapariId, $userId]);
$bapari = $stmt->fetch();

if (!$bapari) {
    echo "Customer not found";
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
<div class="mb-6 flex items-center justify-between mt-2">
    <div class="flex items-center space-x-3">
        <a href="index.php" class="w-10 h-10 rounded-xl bg-slate-800 border border-slate-700/60 flex items-center justify-center text-slate-300 hover:text-white transition-colors tap-target">
            <span class="material-symbols-rounded text-xl">arrow_back</span>
        </a>
        <div class="min-w-0">
            <h1 class="text-xl font-bold text-white truncate leading-tight"><?= htmlspecialchars($bapari['name']) ?></h1>
            <p class="text-[11px] text-slate-500 truncate mt-0.5"><?= htmlspecialchars($bapari['mobile'] ?: 'No mobile') ?> | <?= htmlspecialchars($bapari['address'] ?: 'No address') ?></p>
        </div>
    </div>
</div>

<!-- Balances Row (Two Column Grid) -->
<div class="grid grid-cols-2 gap-4 mb-6">
    <div class="premium-card gold-gradient-border glow-gold">
        <span class="text-desc font-semibold uppercase text-[10px] block mb-1">Gold Balance</span>
        <div class="text-xl font-bold text-[#F4B400] font-mono leading-none"><?= number_format($fineBal, 3) ?> g</div>
        <p class="text-[9px] text-slate-500 mt-1">Outstanding pure gold</p>
    </div>
    <div class="premium-card">
        <span class="text-desc font-semibold uppercase text-[10px] block mb-1">Cash Balance</span>
        <div class="text-xl font-bold text-blue-400 font-mono leading-none">₹<?= number_format($cashBal, 2) ?></div>
        <p class="text-[9px] text-slate-500 mt-1">Outstanding cash</p>
    </div>
</div>

<!-- Statement Cards -->
<div class="mb-4 flex items-center justify-between">
    <h2 class="title-section text-white flex items-center">
        <span class="material-symbols-rounded text-[#F4B400] mr-2">receipt_long</span> Ledger Statement
    </h2>
</div>

<div class="space-y-4">
    <?php if (empty($displayEntries)): ?>
        <div class="premium-card text-center py-12 flex flex-col items-center justify-center">
            <span class="material-symbols-rounded text-5xl text-slate-600 mb-3">receipt</span>
            <h3 class="text-sm font-semibold text-slate-300">No Transactions</h3>
            <p class="text-xs text-slate-500 mt-1">No transaction records found for this customer.</p>
        </div>
    <?php else: ?>
        <?php foreach ($displayEntries as $e): ?>
            <div class="premium-card">
                <div class="flex items-start justify-between border-b border-slate-800/80 pb-2.5 mb-2.5">
                    <div>
                        <span class="text-[10px] text-slate-500 font-mono"><?= date('d-M-Y', strtotime($e['date'])) ?></span>
                        <div class="mt-0.5">
                            <?php if ($e['type'] === 'deposit'): ?>
                                <span class="bg-emerald-500/10 text-emerald-400 px-2 py-0.5 rounded-lg border border-emerald-500/20 text-[9px] font-bold uppercase tracking-wider">Gold Jama</span>
                            <?php else: ?>
                                <span class="bg-indigo-500/10 text-indigo-400 px-2 py-0.5 rounded-lg border border-indigo-500/20 text-[9px] font-bold uppercase tracking-wider">Kaarigari Job</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="text-right">
                        <span class="text-[9px] text-slate-500 block uppercase font-semibold">Running Balance</span>
                        <span class="font-mono text-white text-xs font-semibold block"><?= number_format($e['running_fine'], 3) ?> g</span>
                        <span class="font-mono text-slate-400 text-[10px] block mt-0.5">₹<?= number_format($e['running_cash'], 2) ?></span>
                    </div>
                </div>

                <!-- Transaction values -->
                <div class="grid grid-cols-2 gap-3 my-2 text-xs">
                    <div>
                        <span class="text-[9px] text-slate-500 uppercase block">Gold Weight Change</span>
                        <span class="font-mono font-bold text-sm <?= $e['type'] == 'deposit' ? 'text-emerald-400' : 'text-rose-400' ?>">
                            <?= $e['type'] == 'deposit' ? '+' : '-' ?><?= number_format($e['type'] == 'deposit' ? $e['jama_fine'] : $e['kaj_fine'], 3) ?> g
                        </span>
                    </div>
                    <div>
                        <span class="text-[9px] text-slate-500 uppercase block">Cash Amount Change</span>
                        <span class="font-mono font-bold text-sm <?= $e['type'] == 'deposit' ? 'text-blue-400' : 'text-rose-400' ?>">
                            <?php 
                            if ($e['type'] == 'deposit') {
                                echo $e['cash_received'] > 0 ? '₹' . number_format($e['cash_received'], 2) : '--';
                            } else {
                                echo $e['cash_bill'] > 0 ? '₹' . number_format($e['cash_bill'], 2) : '--';
                            }
                            ?>
                        </span>
                    </div>
                </div>

                <!-- Detail description -->
                <div class="bg-slate-900/50 p-2.5 rounded-xl border border-slate-800/80 text-[11px] text-slate-400 mt-2.5 flex items-start space-x-1">
                    <span class="material-symbols-rounded text-sm text-slate-500 mt-0.5">sticky_note</span>
                    <span>
                        <?php 
                        if ($e['type'] === 'deposit') {
                            echo htmlspecialchars($e['remark'] ?: 'Gold Jama deposit');
                        } else {
                            $itemsStr = [];
                            foreach ($e['items'] as $it) {
                                $itemsStr[] = htmlspecialchars($it['item']) . " ({$it['gross']}g)";
                            }
                            echo implode(', ', $itemsStr) ?: 'Kaarigari Job ornaments';
                        }
                        ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
require_once 'footer.php';
?>
