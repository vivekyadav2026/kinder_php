<?php
require_once 'db.php';

$bapariId = intval($_GET['bapari_id'] ?? 0);
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$isPrintMode = isset($_GET['print']) && $_GET['print'] == 1;

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

// 1. Handle Ledger Settlement Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settle_ledger'])) {
    $settleDate = $_POST['settlement_date'] ?? date('Y-m-d');
    $closeGold = floatval($_POST['closing_gold']);
    $closeCash = floatval($_POST['closing_cash']);
    
    $stmt = $pdo->prepare("INSERT INTO ledger_settlements (user_id, bapari_id, settlement_date, closing_gold, closing_cash) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $bapariId, $settleDate, $closeGold, $closeCash]);
    
    header("Location: ledger.php?bapari_id=" . $bapariId);
    exit();
}

// Handle Delete Deposit directly from Ledger
if (isset($_GET['delete_deposit'])) {
    $id = intval($_GET['delete_deposit']);
    $stmt = $pdo->prepare("DELETE FROM fine_deposits WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    header("Location: ledger.php?bapari_id=" . $bapariId . ($from ? "&from=".$from : "") . ($to ? "&to=".$to : ""));
    exit();
}

// Handle Delete Kaj directly from Ledger
if (isset($_GET['delete_kaj'])) {
    $id = intval($_GET['delete_kaj']);
    $stmt = $pdo->prepare("DELETE FROM kaj_entries WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    header("Location: ledger.php?bapari_id=" . $bapariId . ($from ? "&from=".$from : "") . ($to ? "&to=".$to : ""));
    exit();
}

// 2. Fetch Latest Settlement Checkpoint
$stmt = $pdo->prepare("SELECT * FROM ledger_settlements WHERE bapari_id = ? AND user_id = ? ORDER BY settlement_date DESC LIMIT 1");
$stmt->execute([$bapariId, $userId]);
$latestSettle = $stmt->fetch();

// 3. Setup Default Dates based on Settlement checkpoint if not user-defined
if (empty($from) && $latestSettle) {
    // Show entries only AFTER the last settlement
    $from = date('Y-m-d', strtotime($latestSettle['settlement_date'] . ' + 1 day'));
}

// 4. Calculate Opening Balance before $from Date
$openingGold = 0.0;
$openingCash = 0.0;
$startCalculationFromDate = '';

// Find the most recent settlement dated *before* the filtered From Date
$settleStmt = $pdo->prepare("SELECT * FROM ledger_settlements WHERE bapari_id = ? AND user_id = ? AND settlement_date < ? ORDER BY settlement_date DESC LIMIT 1");
$settleStmt->execute([$bapariId, $userId, $from ?: '9999-12-31']);
$prevSettle = $settleStmt->fetch();

if ($prevSettle) {
    $openingGold = floatval($prevSettle['closing_gold']);
    $openingCash = floatval($prevSettle['closing_cash']);
    $startCalculationFromDate = $prevSettle['settlement_date'];
}

// Calculate intermediate transactions between the last settlement and the $from Date
$depParamsBefore = [$userId, $bapariId];
$depQueryBefore = "SELECT SUM(jama_fine) as g, SUM(cash_received) as c FROM fine_deposits WHERE user_id = ? AND bapari_id = ?";
if (!empty($startCalculationFromDate)) {
    $depQueryBefore .= " AND date > ?";
    $depParamsBefore[] = $startCalculationFromDate;
}
if (!empty($from)) {
    $depQueryBefore .= " AND date < ?";
    $depParamsBefore[] = $from;
}
$stmt = $pdo->prepare($depQueryBefore);
$stmt->execute($depParamsBefore);
$depBefore = $stmt->fetch();

$kajParamsBefore = [$userId, $bapariId];
$kajQueryBefore = "SELECT SUM(total_kaj_fine) as g, SUM(cash_bill) as c FROM kaj_entries WHERE user_id = ? AND bapari_id = ?";
if (!empty($startCalculationFromDate)) {
    $kajQueryBefore .= " AND date > ?";
    $kajParamsBefore[] = $startCalculationFromDate;
}
if (!empty($from)) {
    $kajQueryBefore .= " AND date < ?";
    $kajParamsBefore[] = $from;
}
$stmt = $pdo->prepare($kajQueryBefore);
$stmt->execute($kajParamsBefore);
$kajBefore = $stmt->fetch();

$openingGold += floatval($depBefore['g'] ?? 0) - floatval($kajBefore['g'] ?? 0);
$openingCash += floatval($depBefore['c'] ?? 0) - floatval($kajBefore['c'] ?? 0);

// 5. Fetch Deposits within Date Range
$depQuery = "SELECT id, date, 'deposit' as type, fine_weight, purity, jama_fine, cash_received, remark, created_at FROM fine_deposits WHERE user_id = ? AND bapari_id = ?";
$depParams = [$userId, $bapariId];
if (!empty($from)) { $depQuery .= " AND date >= ?"; $depParams[] = $from; }
if (!empty($to)) { $depQuery .= " AND date <= ?"; $depParams[] = $to; }
$stmt = $pdo->prepare($depQuery);
$stmt->execute($depParams);
$deposits = $stmt->fetchAll();

// 6. Fetch Kaj Entries within Date Range
$kajQuery = "SELECT id, date, 'kaj' as type, total_kaj_fine, total_profit_fine, cash_bill, remark, created_at FROM kaj_entries WHERE user_id = ? AND bapari_id = ?";
$kajParams = [$userId, $bapariId];
if (!empty($from)) { $kajQuery .= " AND date >= ?"; $kajParams[] = $from; }
if (!empty($to)) { $kajQuery .= " AND date <= ?"; $kajParams[] = $to; }
$stmt = $pdo->prepare($kajQuery);
$stmt->execute($kajParams);
$kajs = $stmt->fetchAll();

// Merge and sort ascending by date & creation time
$entries = array_merge($deposits, $kajs);
usort($entries, function($a, $b) {
    $cmp = strcmp($a['date'], $b['date']);
    if ($cmp === 0) {
        return strcmp($a['created_at'], $b['created_at']);
    }
    return $cmp;
});

// Fetch subitems for Kaj entries
foreach ($entries as &$e) {
    if ($e['type'] === 'kaj') {
        $stmtItems = $pdo->prepare("SELECT item, gross, less, net, milting, wastage, hisab, kaj_fine, profit_fine FROM kaj_items WHERE kaj_entry_id = ?");
        $stmtItems->execute([$e['id']]);
        $e['items'] = $stmtItems->fetchAll();
    }
}
unset($e);

// 7. Flat map transactions into individual Ledger rows (Tally layout)
$ledgerRows = [];

// Insert Opening Balance Row first
$ledgerRows[] = [
    'date' => $from ?: (!empty($entries) ? $entries[0]['date'] : date('Y-m-d')),
    'no' => '',
    'name' => 'Opening Balance',
    'gross' => 0.0,
    'less' => 0.0,
    'net' => 0.0,
    'tch' => 0.0,
    'wst' => 0.0,
    'fine' => $openingGold,
    'cash' => $openingCash,
    'remark' => 'Opening Balance',
    'is_opening' => true
];

foreach ($entries as $e) {
    if ($e['type'] === 'deposit') {
        if (floatval($e['fine_weight']) > 0) {
            $ledgerRows[] = [
                'date' => $e['date'],
                'no' => 'Inward No : ' . $e['id'],
                'name' => floatval($e['purity']) === 100.0 ? 'Fine' : '995 metal',
                'gross' => floatval($e['fine_weight']),
                'less' => 0.0,
                'net' => floatval($e['fine_weight']),
                'tch' => floatval($e['purity']),
                'wst' => 0.0,
                'fine' => floatval($e['jama_fine']),
                'cash' => 0.0,
                'remark' => $e['remark'],
                'is_opening' => false,
                'type' => 'deposit',
                'id' => $e['id']
            ];
        }
        if (floatval($e['cash_received']) > 0) {
            $ledgerRows[] = [
                'date' => $e['date'],
                'no' => 'Inward No : ' . $e['id'],
                'name' => 'Cash',
                'gross' => 0.0,
                'less' => 0.0,
                'net' => 0.0,
                'tch' => 0.0,
                'wst' => 0.0,
                'fine' => 0.0,
                'cash' => floatval($e['cash_received']),
                'remark' => $e['remark'],
                'is_opening' => false,
                'type' => 'deposit',
                'id' => $e['id']
            ];
        }
    } else {
        foreach ($e['items'] as $it) {
            $ledgerRows[] = [
                'date' => $e['date'],
                'no' => 'Outward No : ' . $e['id'],
                'name' => $it['item'],
                'gross' => floatval($it['gross']),
                'less' => floatval($it['less']),
                'net' => floatval($it['net']),
                'tch' => floatval($it['milting']),
                'wst' => floatval($it['wastage']),
                'fine' => -floatval($it['kaj_fine']),
                'cash' => 0.0,
                'remark' => $e['remark'],
                'is_opening' => false,
                'type' => 'kaj',
                'id' => $e['id']
            ];
        }
        if (floatval($e['cash_bill']) > 0) {
            $ledgerRows[] = [
                'date' => $e['date'],
                'no' => 'Outward No : ' . $e['id'],
                'name' => 'Cash',
                'gross' => 0.0,
                'less' => 0.0,
                'net' => 0.0,
                'tch' => 0.0,
                'wst' => 0.0,
                'fine' => 0.0,
                'cash' => -floatval($e['cash_bill']),
                'remark' => $e['remark'],
                'is_opening' => false,
                'type' => 'kaj',
                'id' => $e['id']
            ];
        }
    }
}

// 8. Calculate cumulative running balance columns
$fineRunning = 0.0;
$cashRunning = 0.0;

$totGross = 0.0;
$totLess = 0.0;
$totNet = 0.0;

foreach ($ledgerRows as &$row) {
    $fineRunning += $row['fine'];
    $cashRunning += $row['cash'];
    $row['running_fine'] = $fineRunning;
    $row['running_cash'] = $cashRunning;

    if (!$row['is_opening']) {
        $totGross += $row['gross'];
        $totLess += $row['less'];
        $totNet += $row['net'];
    }
}
unset($row);

// Outstanding aggregates for display
$currentOutstandingGold = $fineRunning;
$currentOutstandingCash = $cashRunning;
$lastTransactionDate = !empty($entries) ? end($entries)['date'] : '--';

// 9. Fetch Settlement History list
$stmt = $pdo->prepare("SELECT * FROM ledger_settlements WHERE bapari_id = ? AND user_id = ? ORDER BY settlement_date DESC");
$stmt->execute([$bapariId, $userId]);
$settlementsHistory = $stmt->fetchAll();

if ($isPrintMode) {
    // Print View
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Account Detail - <?= htmlspecialchars($bapari['name']) ?></title>
        <style>
            body {
                font-family: 'Times New Roman', Times, serif;
                font-size: 11px;
                color: #000000;
                margin: 20px;
                background-color: #ffffff;
            }
            .title-block {
                text-align: center;
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 25px;
            }
            .header-info {
                font-size: 13px;
                font-weight: bold;
                margin-bottom: 10px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
            }
            th, td {
                border: 1px solid #777777;
                padding: 5px 6px;
                text-align: left;
                vertical-align: top;
            }
            th {
                background-color: #e2e8f0;
                font-weight: bold;
                text-align: center;
            }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .font-mono { font-family: monospace; }
            .text-green { color: #047857; font-weight: bold; }
            .text-red { color: #b91c1c; font-weight: bold; }
            .stacked-val {
                display: block;
                font-size: 9.5px;
                color: #555555;
            }
        </style>
    </head>
    <body onload="window.print()">
        <div class="title-block">Account Detail</div>
        <div class="header-info"><?= htmlspecialchars($bapari['name']) ?></div>
        <?php if ($from || $to): ?>
            <div style="font-size:11px; margin-bottom: 10px;">
                Period: <strong><?= $from ? date('d/m/Y', strtotime($from)) : 'Start' ?></strong> to <strong><?= $to ? date('d/m/Y', strtotime($to)) : 'Today' ?></strong>
            </div>
        <?php endif; ?>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 80px;">Rec Dt.</th>
                    <th style="width: 90px;">No</th>
                    <th>Name</th>
                    <th style="width: 70px;">Gross<br><span style="font-size:9px; font-weight:normal;">Less</span></th>
                    <th style="width: 70px;">Net</th>
                    <th style="width: 60px;">Tch<br><span style="font-size:9px; font-weight:normal;">Wst</span></th>
                    <th style="width: 90px;">Fine</th>
                    <th style="width: 85px;">Amt Pcs</th>
                    <th>Narration</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ledgerRows as $r): ?>
                    <tr>
                        <td class="text-center"><?= date('d/m/Y', strtotime($r['date'])) ?></td>
                        <td class="text-center"><?= htmlspecialchars($r['no']) ?></td>
                        <td><?= htmlspecialchars($r['name']) ?></td>
                        <td class="text-right font-mono">
                            <?php if (!$r['is_opening'] && $r['name'] !== 'Cash'): ?>
                                <?= number_format($r['gross'], 3) ?><br>
                                <span class="stacked-val"><?= number_format($r['less'], 3) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right font-mono">
                            <?php if (!$r['is_opening'] && $r['name'] !== 'Cash'): ?>
                                <?= number_format($r['net'], 3) ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-right font-mono">
                            <?php if (!$r['is_opening'] && $r['name'] !== 'Cash'): ?>
                                <?= number_format($r['tch'], 2) ?><br>
                                <span class="stacked-val"><?= number_format($r['wst'], 2) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right font-mono">
                            <?php 
                            if ($r['fine'] != 0) {
                                $isCredit = $r['fine'] >= 0;
                                $displayFine = abs($r['fine']);
                                $colorClass = $isCredit ? 'text-green' : 'text-red';
                                echo "<span class='{$colorClass}'>" . number_format($displayFine, 3) . ($isCredit ? ' Cr' : ' Db') . "</span>";
                            } else {
                                echo '0.000';
                            }
                            ?>
                        </td>
                        <td class="text-right font-mono">
                            <?php 
                            if ($r['cash'] != 0) {
                                $isCredit = $r['cash'] >= 0;
                                $displayCash = abs($r['cash']);
                                $colorClass = $isCredit ? 'text-green' : 'text-red';
                                echo "<span class='{$colorClass}'>" . number_format($displayCash, 0) . ($isCredit ? ' Cr' : ' Db') . "</span>";
                            } else {
                                echo '0';
                            }
                            ?>
                        </td>
                        <td style="font-size: 10px;"><?= htmlspecialchars($r['remark']) ?></td>
                    </tr>
                <?php endforeach; ?>
                
                <tr style="font-weight: bold; background-color: #f8fafc;">
                    <td colspan="3" class="text-center">Total</td>
                    <td class="text-right font-mono">
                        <?= number_format($totGross, 3) ?><br>
                        <span class="stacked-val"><?= number_format($totLess, 3) ?></span>
                    </td>
                    <td class="text-right font-mono"><?= number_format($totNet, 3) ?></td>
                    <td></td>
                    <td class="text-right font-mono">
                        <?php 
                        $isCr = $currentOutstandingGold >= 0;
                        $colorClass = $isCr ? 'text-green' : 'text-red';
                        echo "<span class='{$colorClass}'>" . number_format(abs($currentOutstandingGold), 3) . ($isCr ? ' Cr' : ' Db') . "</span>";
                        ?>
                    </td>
                    <td class="text-right font-mono">
                        <?php 
                        $isCr = $currentOutstandingCash >= 0;
                        $colorClass = $isCr ? 'text-green' : 'text-red';
                        echo "<span class='{$colorClass}'>" . number_format(abs($currentOutstandingCash), 0) . ($isCr ? ' Cr' : ' Db') . "</span>";
                        ?>
                    </td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit();
}

// SCREEN VIEW LAYOUT (Tailwind UI)
require_once 'header.php';
?>

<!-- Title & Navigation -->
<div class="mb-5 mt-2 flex items-center justify-between no-print">
    <div class="flex items-center space-x-3">
        <a href="reports.php" class="w-9 h-9 rounded-xl bg-slate-900 border border-white/[0.04] flex items-center justify-center text-slate-400 hover:text-white transition-colors tap-target">
            <span class="material-symbols-rounded text-lg">arrow_back</span>
        </a>
        <div class="min-w-0">
            <h1 class="text-xl font-bold text-white truncate leading-tight"><?= htmlspecialchars($bapari['name']) ?></h1>
            <p class="text-[10px] text-slate-500 mt-0.5 truncate"><?= htmlspecialchars($bapari['mobile'] ?: 'No mobile') ?> | <?= htmlspecialchars($bapari['address'] ?: 'No address') ?></p>
        </div>
    </div>
</div>

<!-- Outstanding Balance Panel (Tally Style aggregates top display) -->
<div class="mb-6">
    <div class="flex items-center justify-between mb-3">
        <span class="text-[#d8a735] text-[10px] uppercase font-bold tracking-wider block">Outstanding Balance</span>
        <!-- Settlement Button Trigger Modal -->
        <?php if (!$isReadOnly): ?>
            <button onclick="document.getElementById('settlementModal').classList.remove('hidden')" class="px-3 py-1.5 rounded-lg bg-[#d8a735]/15 border border-[#d8a735]/25 text-[10px] text-[#d8a735] font-bold tracking-wider hover:bg-[#d8a735]/20 tap-target no-print flex items-center space-x-1">
                <span class="material-symbols-rounded text-xs">done_all</span>
                <span>Settle Ledger</span>
            </button>
        <?php endif; ?>
    </div>
    
    <div class="grid grid-cols-2 gap-4">
        <div class="premium-card bg-[#121212]/70 border-white/[0.03]">
            <span class="text-slate-500 text-[9px] uppercase font-bold block mb-1">Gold Outstanding</span>
            <div class="text-lg font-bold font-mono <?= $currentOutstandingGold >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>">
                <?= number_format(abs($currentOutstandingGold), 3) ?> g <?= $currentOutstandingGold >= 0 ? 'Cr' : 'Db' ?>
            </div>
        </div>
        <div class="premium-card bg-[#121212]/70 border-white/[0.03]">
            <span class="text-slate-500 text-[9px] uppercase font-bold block mb-1">Cash Outstanding</span>
            <div class="text-lg font-bold font-mono <?= $currentOutstandingCash >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>">
                ₹<?= number_format(abs($currentOutstandingCash), 2) ?> <?= $currentOutstandingCash >= 0 ? 'Cr' : 'Db' ?>
            </div>
        </div>
    </div>
    <div class="flex items-center justify-between text-[9.5px] text-slate-500 mt-2.5 font-mono px-1">
        <span>Last Transaction Date: <span class="text-slate-400 font-bold"><?= $lastTransactionDate ?></span></span>
        <?php if ($latestSettle): ?>
            <span>Last Settlement Date: <span class="text-[#d8a735] font-bold"><?= date('d/m/Y', strtotime($latestSettle['settlement_date'])) ?></span></span>
        <?php endif; ?>
    </div>
</div>

<!-- Date Filter Form (Requirement #3) -->
<form method="GET" class="premium-card bg-[#121212]/80 p-4 mb-6 grid grid-cols-2 gap-3.5 no-print">
    <input type="hidden" name="bapari_id" value="<?= $bapariId ?>">
    <div>
        <label class="block text-[9px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">From Date</label>
        <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="premium-input text-xs">
    </div>
    <div>
        <label class="block text-[9px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">To Date</label>
        <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="premium-input text-xs">
    </div>
    <div class="col-span-2 flex items-center space-x-2 pt-1.5">
        <button type="submit" class="flex-1 btn-gold text-xs py-3 font-bold tracking-wider flex items-center justify-center space-x-1.5">
            <span class="material-symbols-rounded text-sm">filter_alt</span>
            <span>Filter Ledger</span>
        </button>
        <a href="ledger.php?bapari_id=<?= $bapariId ?>" class="px-4 py-3 rounded-xl bg-slate-900 border border-white/[0.04] text-[10px] font-bold text-slate-400 hover:text-white flex items-center justify-center">
            Reset
        </a>
    </div>
</form>

<!-- Print PDF Action row -->
<div class="grid grid-cols-2 gap-3.5 mb-6 no-print">
    <a href="ledger.php?bapari_id=<?= $bapariId ?>&from=<?= $from ?>&to=<?= $to ?>&print=1" target="_blank" class="w-full py-3 rounded-xl border border-[#d8a735]/40 bg-transparent text-xs font-bold text-[#d8a735] hover:bg-[#d8a735]/5 flex items-center justify-center space-x-1.5 tap-target">
        <span class="material-symbols-rounded text-base">print</span>
        <span>Download PDF</span>
    </a>
    
    <button onclick="shareLedgerText()" class="w-full py-3 rounded-xl bg-emerald-600/10 hover:bg-emerald-600/20 border border-emerald-500/20 text-xs font-bold text-emerald-400 flex items-center justify-center space-x-1.5 tap-target">
        <span class="material-symbols-rounded text-base">share</span>
        <span>WhatsApp</span>
    </button>
</div>

<!-- Statement List (Exposing both edit and delete options) -->
<div class="mb-6">
    <span class="text-slate-400 text-[10px] uppercase font-bold tracking-wider block mb-4">Transactions Log</span>
    
    <div class="space-y-4">
        <?php 
        $activeRows = array_filter($ledgerRows, function($r) { return !$r['is_opening']; });
        
        if (empty($activeRows)): ?>
            <div class="premium-card text-center py-10">
                <span class="material-symbols-rounded text-4xl text-slate-700 mb-2">receipt</span>
                <p class="text-xs text-slate-500">No transactions recorded in this date range.</p>
            </div>
        <?php else: 
            $screenRows = array_reverse($activeRows);
            foreach ($screenRows as $row): 
                $isDeposit = ($row['type'] === 'deposit');
            ?>
                <div class="premium-card bg-[#111111]/90">
                    <div class="flex items-start justify-between border-b border-white/[0.04] pb-2.5 mb-2.5">
                        <div>
                            <span class="text-[9px] text-slate-500 font-mono"><?= date('d/m/Y', strtotime($row['date'])) ?></span>
                            <div class="mt-0.5">
                                <?php if ($isDeposit): ?>
                                    <span class="bg-emerald-500/10 text-emerald-400 px-2 py-0.5 rounded-lg border border-emerald-500/20 text-[8px] font-bold uppercase tracking-wider">Fine Deposit</span>
                                <?php else: ?>
                                    <span class="bg-rose-500/10 text-rose-400 px-2 py-0.5 rounded-lg border border-rose-500/20 text-[8px] font-bold uppercase tracking-wider">Kaj Entry</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="text-right">
                            <span class="text-[8px] text-slate-500 uppercase block font-semibold">Running Balance</span>
                            <span class="font-mono text-white text-xs block"><?= number_format($row['running_fine'], 3) ?> g</span>
                            <span class="font-mono text-slate-500 text-[9px] block">₹<?= number_format($row['running_cash'], 2) ?></span>
                        </div>
                    </div>

                    <!-- Details of change -->
                    <div class="grid grid-cols-2 gap-3 text-xs my-2">
                        <div>
                            <span class="text-[8px] text-slate-500 uppercase block">Gold Weight</span>
                            <span class="font-mono font-bold text-sm <?= $row['fine'] >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>">
                                <?= $row['fine'] >= 0 ? '+' : '-' ?><?= number_format(abs($row['fine']), 3) ?> g
                            </span>
                        </div>
                        <div>
                            <span class="text-[8px] text-slate-500 uppercase block">Cash Amount</span>
                            <span class="font-mono font-bold text-sm <?= $row['cash'] >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>">
                                <?= $row['cash'] != 0 ? ($row['cash'] >= 0 ? '+' : '-') . '₹' . number_format(abs($row['cash']), 0) : '--' ?>
                            </span>
                        </div>
                    </div>

                    <div class="text-[10px] text-slate-400 mt-2 font-mono bg-slate-950/40 p-2.5 rounded-xl border border-white/[0.02]">
                        <span class="font-sans font-bold text-slate-500 block mb-0.5">Details (<?= htmlspecialchars($row['name']) ?>):</span>
                        <?php if ($row['name'] !== 'Cash'): ?>
                            Gross: <?= number_format($row['gross'], 3) ?>g | Less: <?= number_format($row['less'], 3) ?>g<br>
                            Purity: <?= number_format($row['tch'], 1) ?>% | Wastage: <?= number_format($row['wst'], 1) ?>%
                        <?php else: ?>
                            Cash Entry
                        <?php endif; ?>
                        <?php if ($row['remark']): ?>
                            <div class="mt-1 text-slate-500 font-sans">Narration: <?= htmlspecialchars($row['remark']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Edit/Delete Action Links -->
                    <?php if (!$isReadOnly): ?>
                        <div class="flex items-center justify-end space-x-2.5 mt-3 pt-2.5 border-t border-white/[0.03] no-print">
                            <?php if ($isDeposit): ?>
                                <a href="deposits.php?action=edit&id=<?= $row['id'] ?>" class="w-8 h-8 rounded-lg bg-slate-900 hover:bg-slate-800 border border-white/[0.05] flex items-center justify-center text-slate-400 transition-colors tap-target" title="Edit Entry">
                                    <span class="material-symbols-rounded text-base">edit</span>
                                </a>
                                <a href="ledger.php?bapari_id=<?= $bapariId ?>&delete_deposit=<?= $row['id'] ?>&from=<?= $from ?>&to=<?= $to ?>" onclick="return confirm('Are you sure you want to delete this deposit entry?')" class="w-8 h-8 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 flex items-center justify-center transition-colors tap-target" title="Delete Entry">
                                    <span class="material-symbols-rounded text-base">delete</span>
                                </a>
                            <?php else: ?>
                                <a href="kaj.php?action=edit&id=<?= $row['id'] ?>" class="w-8 h-8 rounded-lg bg-slate-900 hover:bg-slate-800 border border-white/[0.05] flex items-center justify-center text-slate-400 transition-colors tap-target" title="Edit Entry">
                                    <span class="material-symbols-rounded text-base">edit</span>
                                </a>
                                <a href="ledger.php?bapari_id=<?= $bapariId ?>&delete_kaj=<?= $row['id'] ?>&from=<?= $from ?>&to=<?= $to ?>" onclick="return confirm('Are you sure you want to delete this job entry?')" class="w-8 h-8 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 flex items-center justify-center transition-colors tap-target" title="Delete Entry">
                                    <span class="material-symbols-rounded text-base">delete</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- SETTLEMENT HISTORY SECTION -->
<div class="mb-8 no-print">
    <span class="text-slate-400 text-[10px] uppercase font-bold tracking-wider block mb-3">Settlement History</span>
    <?php if (empty($settlementsHistory)): ?>
        <div class="premium-card text-center py-6 text-xs text-slate-500 bg-[#121212]/40 border-dashed">
            No settlements completed yet for this customer.
        </div>
    <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($settlementsHistory as $sh): ?>
                <div class="premium-card bg-[#121212]/60 p-3.5 flex items-center justify-between text-xs">
                    <div>
                        <span class="text-[9px] text-slate-500 font-mono"><?= date('d/m/Y', strtotime($sh['settlement_date'])) ?></span>
                        <div class="font-bold text-white mt-0.5">Gold Checkpoint: <?= number_format($sh['closing_gold'], 3) ?> g</div>
                        <div class="text-[10px] text-slate-400">Cash Checkpoint: ₹<?= number_format($sh['closing_cash'], 0) ?></div>
                    </div>
                    <!-- Download PDF representing state at this checkpoint -->
                    <a href="ledger.php?bapari_id=<?= $bapariId ?>&to=<?= $sh['settlement_date'] ?>&print=1" target="_blank" class="px-2.5 py-1.5 bg-[#d8a735]/15 hover:bg-[#d8a735]/25 border border-[#d8a735]/25 rounded-lg text-[9px] text-[#d8a735] font-bold flex items-center space-x-1 transition-colors tap-target">
                        <span class="material-symbols-rounded text-xs">print</span>
                        <span>Statement PDF</span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL OVERLAY FOR SETTLE LEDGER -->
<div id="settlementModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-sm p-4 no-print">
    <div class="w-full max-w-md premium-card border-[#d8a735]/35 shadow-2xl shadow-[#d8a735]/5 animate-scale-up">
        <h2 class="text-lg font-bold text-[#d8a735] mb-4 flex items-center">
            <span class="material-symbols-rounded text-xl mr-1.5">done_all</span> Settle Ledger Account
        </h2>
        
        <p class="text-xs text-slate-400 mb-4 leading-relaxed">
            Settling this ledger registers the current outstanding gold and cash balances as a fixed checkpoint. Future statements will start with these values as their opening balances.
        </p>
        
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-[10px] font-bold text-slate-400 mb-1.5">Settlement Date</label>
                <input type="date" name="settlement_date" value="<?= date('Y-m-d') ?>" required class="premium-input text-xs">
            </div>
            
            <div>
                <label class="block text-[10px] font-bold text-slate-400 mb-1.5">Closing Gold Balance (gm) *</label>
                <input type="number" step="0.001" name="closing_gold" value="<?= round($currentOutstandingGold, 3) ?>" required class="premium-input text-xs font-mono">
            </div>
            
            <div>
                <label class="block text-[10px] font-bold text-slate-400 mb-1.5">Closing Cash Balance (₹) *</label>
                <input type="number" step="0.01" name="closing_cash" value="<?= round($currentOutstandingCash, 2) ?>" required class="premium-input text-xs font-mono">
            </div>
            
            <div class="grid grid-cols-2 gap-3.5 pt-3 border-t border-slate-800">
                <button type="button" onclick="document.getElementById('settlementModal').classList.add('hidden')" class="btn-secondary text-xs py-3">Cancel</button>
                <button type="submit" name="settle_ledger" class="btn-gold text-xs py-3">Confirm Settle</button>
            </div>
        </form>
    </div>
</div>

<script>
    function shareLedgerText() {
        let text = "*Dasgold Ledger Statement*\n";
        text += "*Customer:* <?= htmlspecialchars($bapari['name']) ?>\n";
        text += "*Gold Balance:* <?= number_format($fineRunning, 3) ?> g <?= $fineRunning >= 0 ? 'Cr' : 'Db' ?>\n";
        text += "*Cash Balance:* ₹<?= number_format($cashRunning, 2) ?> <?= $cashRunning >= 0 ? 'Cr' : 'Db' ?>\n\n";
        text += "*Recent Ledger Entries:*\n";
        
        <?php 
        $shareCount = 0;
        foreach (array_reverse($activeRows) as $row) {
            if ($shareCount >= 5) break; 
            $dateStr = date('d-M-Y', strtotime($row['date']));
            $sign = $row['fine'] >= 0 ? '+' : '-';
            $fineVal = number_format(abs($row['fine']), 3);
            $cashStr = $row['cash'] != 0 ? " (Cash: " . ($row['cash'] >= 0 ? '+' : '-') . "₹" . number_format(abs($row['cash']), 0) . ")" : "";
            
            echo "text += '• {$dateStr}: {$row['name']} {$sign}{$fineVal}g{$cashStr}\\n';\n";
            $shareCount++;
        }
        ?>
        const encodedText = encodeURIComponent(text);
        window.open("https://api.whatsapp.com/send?text=" + encodedText, "_blank");
    }
</script>

<?php
require_once 'footer.php';
?>
