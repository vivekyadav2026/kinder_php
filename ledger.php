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

// Handle Delete Deposit directly from Ledger
if (isset($_GET['delete_deposit'])) {
    $id = intval($_GET['delete_deposit']);
    $stmt = $pdo->prepare("DELETE FROM fine_deposits WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    header("Location: ledger.php?bapari_id=" . $bapariId);
    exit();
}

// Handle Delete Kaj directly from Ledger
if (isset($_GET['delete_kaj'])) {
    $id = intval($_GET['delete_kaj']);
    $stmt = $pdo->prepare("DELETE FROM kaj_entries WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    header("Location: ledger.php?bapari_id=" . $bapariId);
    exit();
}

// Fetch Deposits (with fine_weight and purity)
$stmt = $pdo->prepare("
    SELECT id, date, 'deposit' as type, fine_weight, purity, jama_fine, 0.0 as kaj_fine, cash_received, 0.0 as cash_bill, remark, created_at 
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
    
    // Fetch detailed items for Kaj entries
    if ($e['type'] === 'kaj') {
        $stmtItems = $pdo->prepare("SELECT item, gross, less, net, milting, wastage, hisab, kaj_fine, profit_fine FROM kaj_items WHERE kaj_entry_id = ?");
        $stmtItems->execute([$e['id']]);
        $e['items'] = $stmtItems->fetchAll();
    }
}
unset($e); // Break reference

// Reverse for displaying most recent at the top
$displayEntries = array_reverse($entries);

require_once 'header.php';
?>

<style>
    @media print {
        body {
            background: white !important;
            color: black !important;
            padding: 10px !important;
            font-size: 12px !important;
        }
        header, footer, .fab-btn, .btn-gold, .btn-secondary, .tap-target, .no-print {
            display: none !important;
        }
        .premium-card {
            background: transparent !important;
            border: 1px solid #cbd5e1 !important;
            box-shadow: none !important;
            color: black !important;
            margin-bottom: 12px !important;
            page-break-inside: avoid !important;
        }
        .text-white {
            color: black !important;
        }
        .text-slate-400, .text-slate-500, .text-desc {
            color: #475569 !important;
        }
        .bg-slate-900\/50 {
            background-color: #f1f5f9 !important;
            border: 1px solid #e2e8f0 !important;
        }
    }
</style>

<!-- Back & Title -->
<div class="mb-6 flex items-center justify-between mt-2 no-print">
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

<!-- Rebrand watermark header on Print View -->
<div class="hidden print:block mb-6 border-b pb-3">
    <h1 class="text-2xl font-bold text-slate-900">Dasgold Ledger Account Statement</h1>
    <p class="text-xs text-slate-600 mt-1">Customer: <strong class="text-black"><?= htmlspecialchars($bapari['name']) ?></strong> | Mobile: <?= htmlspecialchars($bapari['mobile'] ?: 'N/A') ?></p>
    <p class="text-xs text-slate-600">Generated on: <?= date('d-M-Y H:i') ?></p>
</div>

<!-- Balances Row (Two Column Grid) -->
<div class="grid grid-cols-2 gap-4 mb-6">
    <div class="premium-card gold-gradient-border glow-gold">
        <span class="text-desc font-semibold uppercase text-[10px] block mb-1">Gold Balance</span>
        <div class="text-xl font-bold text-[#F4B400] font-mono leading-none print:text-black"><?= number_format($fineBal, 3) ?> g</div>
        <p class="text-[9px] text-slate-500 mt-1">Outstanding pure gold</p>
    </div>
    <div class="premium-card">
        <span class="text-desc font-semibold uppercase text-[10px] block mb-1">Cash Balance</span>
        <div class="text-xl font-bold text-blue-400 font-mono leading-none print:text-black">₹<?= number_format($cashBal, 2) ?></div>
        <p class="text-[9px] text-slate-500 mt-1">Outstanding cash</p>
    </div>
</div>

<!-- Statement Cards -->
<div class="mb-4 flex items-center justify-between no-print">
    <h2 class="title-section text-white flex items-center">
        <span class="material-symbols-rounded text-[#F4B400] mr-2">receipt_long</span> Ledger Statement
    </h2>
</div>

<!-- Print & Share WhatsApp Trigger Buttons -->
<div class="grid grid-cols-2 gap-3 mb-6 no-print">
    <button onclick="window.print()" class="btn-secondary text-xs py-3 flex items-center justify-center space-x-1.5 tap-target">
        <span class="material-symbols-rounded text-base">print</span>
        <span>Download PDF / Print</span>
    </button>
    <button onclick="shareLedgerText()" class="btn-gold text-xs py-3 flex items-center justify-center space-x-1.5 tap-target">
        <span class="material-symbols-rounded text-base">share</span>
        <span>Share WhatsApp</span>
    </button>
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
                <div class="flex items-start justify-between border-b border-slate-800/80 print:border-slate-300 pb-2.5 mb-2.5">
                    <div>
                        <span class="text-[10px] text-slate-500 font-mono"><?= date('d-M-Y', strtotime($e['date'])) ?></span>
                        <div class="mt-0.5">
                            <?php if ($e['type'] === 'deposit'): ?>
                                <span class="bg-emerald-500/10 text-emerald-400 px-2 py-0.5 rounded-lg border border-emerald-500/20 text-[9px] font-bold uppercase tracking-wider print:border-slate-300 print:text-black">Gold Jama</span>
                            <?php else: ?>
                                <span class="bg-indigo-500/10 text-indigo-400 px-2 py-0.5 rounded-lg border border-indigo-500/20 text-[9px] font-bold uppercase tracking-wider print:border-slate-300 print:text-black">Kaarigari Job</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="text-right">
                        <span class="text-[9px] text-slate-500 block uppercase font-semibold">Running Balance</span>
                        <span class="font-mono text-white text-xs font-semibold block print:text-black"><?= number_format($e['running_fine'], 3) ?> g</span>
                        <span class="font-mono text-slate-400 text-[10px] block mt-0.5 print:text-black">₹<?= number_format($e['running_cash'], 2) ?></span>
                    </div>
                </div>

                <!-- Transaction values -->
                <div class="grid grid-cols-2 gap-3 my-2 text-xs">
                    <div>
                        <span class="text-[9px] text-slate-500 uppercase block">Gold Weight Change</span>
                        <span class="font-mono font-bold text-sm <?= $e['type'] == 'deposit' ? 'text-emerald-400' : 'text-rose-400' print:text-black">
                            <?= $e['type'] == 'deposit' ? '+' : '-' ?><?= number_format($e['type'] == 'deposit' ? $e['jama_fine'] : $e['kaj_fine'], 3) ?> g
                        </span>
                    </div>
                    <div>
                        <span class="text-[9px] text-slate-500 uppercase block">Cash Amount Change</span>
                        <span class="font-mono font-bold text-sm <?= $e['type'] == 'deposit' ? 'text-blue-400' : 'text-rose-400' print:text-black">
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

                <!-- Full Sub-details (Gross, Less, Purity, Wastage, Net) -->
                <?php if ($e['type'] === 'kaj'): ?>
                    <div class="bg-slate-900/50 p-3.5 rounded-xl border border-slate-800/80 print:border-slate-300 text-xs text-slate-400 mt-2.5 space-y-2 print:text-black">
                        <div class="font-semibold text-white border-b border-slate-800 print:border-slate-300 pb-1 flex items-center print:text-black">
                            <span class="material-symbols-rounded text-sm text-[#F4B400] mr-1 print:hidden">list</span> Items Detail:
                        </div>
                        <?php foreach ($e['items'] as $it): 
                            $netWeight = round($it['gross'] - $it['less'], 3);
                        ?>
                            <div class="pb-2 border-b border-slate-850 last:border-b-0 last:pb-0 space-y-1">
                                <div class="flex justify-between text-slate-200 font-bold print:text-black">
                                    <span><?= htmlspecialchars($it['item']) ?></span>
                                    <span class="text-rose-400 font-mono print:text-black"><?= number_format($it['kaj_fine'], 3) ?> g (Billed)</span>
                                </div>
                                <div class="grid grid-cols-3 gap-1.5 text-[10px] text-slate-500 font-mono print:text-black">
                                    <div>Gross: <?= number_format($it['gross'], 3) ?>g</div>
                                    <div>Less: <?= number_format($it['less'], 3) ?>g</div>
                                    <div>Net: <?= number_format($netWeight, 3) ?>g</div>
                                    <div>Mel/Purity: <?= number_format($it['milting'], 1) ?>%</div>
                                    <div>Chhij/Wastage: <?= number_format($it['wastage'], 1) ?>%</div>
                                    <div>Profit: <?= number_format($it['profit_fine'], 3) ?>g</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-slate-900/50 p-3.5 rounded-xl border border-slate-800/80 print:border-slate-300 text-xs text-slate-400 mt-2.5 space-y-1 font-mono print:text-black">
                        <div class="font-semibold text-white border-b border-slate-800 print:border-slate-300 pb-1 mb-1.5 flex items-center font-sans print:text-black">
                            <span class="material-symbols-rounded text-sm text-[#F4B400] mr-1 print:hidden">receipt</span> Deposit Detail:
                        </div>
                        <div>Gross Wt: <?= number_format($e['fine_weight'], 3) ?> g</div>
                        <div>Purity / Mel: <?= number_format($e['purity'], 1) ?>%</div>
                        <?php if ($e['remark']): ?>
                            <div class="text-[10px] text-slate-500 font-sans mt-1">Remark: <?= htmlspecialchars($e['remark']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Inline Action Triggers -->
                <div class="flex items-center justify-end space-x-2.5 mt-3 pt-2.5 border-t border-slate-800/40 no-print">
                    <?php if ($e['type'] === 'deposit'): ?>
                        <a href="deposits.php?action=edit&id=<?= $e['id'] ?>" class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 flex items-center justify-center text-slate-300 transition-colors tap-target" title="Edit Entry">
                            <span class="material-symbols-rounded text-base">edit</span>
                        </a>
                        <a href="ledger.php?bapari_id=<?= $bapariId ?>&delete_deposit=<?= $e['id'] ?>" onclick="return confirm('Are you sure you want to delete this Gold Jama entry?')" class="w-8 h-8 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 flex items-center justify-center transition-colors tap-target" title="Delete Entry">
                            <span class="material-symbols-rounded text-base">delete</span>
                        </a>
                    <?php else: ?>
                        <a href="ledger.php?bapari_id=<?= $bapariId ?>&delete_kaj=<?= $e['id'] ?>" onclick="return confirm('Are you sure you want to delete this Kaarigari Job entry? This will also delete all items inside the job!')" class="w-8 h-8 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 flex items-center justify-center transition-colors tap-target" title="Delete Entry">
                            <span class="material-symbols-rounded text-base">delete</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
    function shareLedgerText() {
        let text = "*Dasgold Ledger Statement*\n";
        text += "*Customer:* <?= htmlspecialchars($bapari['name']) ?>\n";
        text += "*Gold Balance:* <?= number_format($fineBal, 3) ?> g\n";
        text += "*Cash Balance:* ₹<?= number_format($cashBal, 2) ?>\n\n";
        text += "*Recent Ledger Entries:*\n";
        
        <?php 
        $shareCount = 0;
        foreach ($displayEntries as $e) {
            if ($shareCount >= 5) break; 
            $dateStr = date('d-M-Y', strtotime($e['date']));
            if ($e['type'] == 'deposit') {
                echo "text += '• {$dateStr}: Gold Jama +'+parseFloat({$e['jama_fine']}).toFixed(3)+'g (Purity: '+parseFloat({$e['purity']})+'%)\\n';\n";
            } else {
                echo "text += '• {$dateStr}: Kaarigari Job -'+parseFloat({$e['kaj_fine']}).toFixed(3)+'g\\n';\n";
                foreach ($e['items'] as $it) {
                    echo "text += '   - {$it['item']}: Gross: {$it['gross']}g, Less: {$it['less']}g, Purity: {$it['milting']}%\\n';\n";
                }
            }
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
