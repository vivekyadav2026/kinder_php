<?php
require_once 'db.php';

/* --- Demo Data loading commented out ---
// Handle Demo Data loading
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['load_demo_data'])) {
    try {
        $pdo->beginTransaction();
        
        // 1. Insert a demo Bapari
        $stmt = $pdo->prepare("INSERT INTO baparis (user_id, name, mobile, address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, 'Kajri Jewellers', '9876543210', 'Panvel, Mumbai']);
        $bapariId = $pdo->lastInsertId();
        
        // 2. Insert a demo Gold Deposit (Gold Jama)
        // 99.5g at 100% Purity
        $stmt = $pdo->prepare("INSERT INTO fine_deposits (user_id, date, bapari_id, fine_weight, purity, jama_fine, cash_received, remark) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, date('Y-m-d'), $bapariId, 99.500, 100.00, 99.500, 0.00, 'Gold Jama deposit']);
        
        // 3. Insert a demo Kaj Entry (Kaarigari Job)
        // Mangalsutra, Gross 100g, less 20g = 80g net, milting 91.80%, wastage 3.50%
        // net 80g * hisab 95.30% = 76.240g billed. Profit = 3.50% * 80g = 2.800g
        $stmt = $pdo->prepare("INSERT INTO kaj_entries (user_id, date, bapari_id, total_kaj_fine, total_profit_fine, cash_bill, remark) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, date('Y-m-d'), $bapariId, 76.240, 2.800, 0.00, 'Demo Mangalsutra work']);
        $kajId = $pdo->lastInsertId();
        
        // Insert item details
        $stmtItem = $pdo->prepare("INSERT INTO kaj_items (kaj_entry_id, item, gross, less, net, milting, wastage, hisab, kaj_fine, profit_fine) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtItem->execute([$kajId, 'Mangalsutra', 100.000, 20.000, 80.000, 91.80, 3.50, 95.30, 76.240, 2.800]);
        
        $pdo->commit();
        header("Location: index.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Failed loading demo data: " . $e->getMessage());
    }
}
--- End Demo Data loading --- */

// Fetch stats
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

$stmt = $pdo->prepare("SELECT id FROM baparis WHERE user_id = ?");
$stmt->execute([$userId]);
$bapariIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

$netFineBalance = 0.0;
$netCashBalance = 0.0;

foreach ($bapariIds as $bid) {
    $settleStmt = $pdo->prepare("SELECT * FROM ledger_settlements WHERE bapari_id = ? AND user_id = ? ORDER BY settlement_date DESC LIMIT 1");
    $settleStmt->execute([$bid, $userId]);
    $settlement = $settleStmt->fetch();
    
    $from = '1970-01-01';
    $gold = 0.0;
    $cash = 0.0;
    
    if ($settlement) {
        $gold = floatval($settlement['closing_gold']);
        $cash = floatval($settlement['closing_cash']);
        $from = date('Y-m-d', strtotime($settlement['settlement_date'] . ' +1 day'));
    }
    
    $depStmt = $pdo->prepare("SELECT SUM(jama_fine) as g, SUM(cash_received) as c FROM fine_deposits WHERE bapari_id = ? AND user_id = ? AND date >= ?");
    $depStmt->execute([$bid, $userId, $from]);
    $dep = $depStmt->fetch();
    
    $kajStmt = $pdo->prepare("SELECT SUM(total_kaj_fine) as g, SUM(cash_bill) as c FROM kaj_entries WHERE bapari_id = ? AND user_id = ? AND date >= ?");
    $kajStmt->execute([$bid, $userId, $from]);
    $kaj = $kajStmt->fetch();
    
    $gold += floatval($dep['g'] ?? 0) - floatval($kaj['g'] ?? 0);
    $cash += floatval($dep['c'] ?? 0) - floatval($kaj['c'] ?? 0);
    
    $netFineBalance += $gold;
    $netCashBalance += $cash;
}

$netFineBalance = round($netFineBalance, 3);
$netCashBalance = round($netCashBalance, 2);

require_once 'header.php';
?>

<!-- Dynamic Greeting (Matching Reference Namaste layout) -->
<div class="mb-6 mt-1">
    <span class="text-slate-500 text-xs font-semibold block uppercase tracking-wider">Namaste,</span>
    <h1 class="text-3xl font-extrabold text-white tracking-tight mt-0.5">
        <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>
    </h1>
</div>

<!-- Highlighted Fine Balance Block -->
<div class="mb-6">
    <span class="text-slate-500 text-[10px] uppercase font-bold tracking-wider block mb-1">Fine Balance</span>
    <div class="text-5xl font-extrabold text-[#d8a735] font-mono tracking-tight leading-none">
        <?= number_format($netFineBalance, 3) ?> <span class="text-xl font-normal text-slate-400">g</span>
    </div>
</div>

<!-- Two-Column Balance Sub-grid -->
<div class="grid grid-cols-2 gap-4 mb-8">
    <div class="premium-card bg-[#121212]/80">
        <span class="text-slate-500 text-[9px] uppercase font-semibold block mb-1">Cash Balance</span>
        <div class="text-lg font-bold text-emerald-400 font-mono">₹<?= number_format($netCashBalance, 2) ?></div>
    </div>
    
    <div class="premium-card bg-[#121212]/80">
        <span class="text-slate-500 text-[9px] uppercase font-semibold block mb-1">Active Baparis</span>
        <div class="text-lg font-bold text-white font-mono"><?= $totalBaparis ?></div>
    </div>
</div>

<!-- Quick Actions Grid (Matching Layout Exactly) -->
<div class="mb-8">
    <span class="text-[#d8a735] text-[10px] uppercase font-bold tracking-wider block mb-4">Quick Actions</span>
    <div class="grid grid-cols-2 gap-4">
        <a href="entry.php?tab=deposit" class="premium-card flex items-center space-x-3.5 hover:border-slate-800">
            <span class="material-symbols-rounded text-[26px] text-[#d8a735] bg-[#d8a735]/10 p-2 rounded-xl">payments</span>
            <div>
                <span class="text-sm font-bold text-white block">Fine Deposit</span>
            </div>
        </a>
        <a href="entry.php?tab=kaj" class="premium-card flex items-center space-x-3.5 hover:border-slate-800">
            <span class="material-symbols-rounded text-[26px] text-[#d8a735] bg-[#d8a735]/10 p-2 rounded-xl">construction</span>
            <div>
                <span class="text-sm font-bold text-white block">Kaj Entry</span>
            </div>
        </a>
        <a href="baparis.php?action=new" class="premium-card flex items-center space-x-3.5 hover:border-slate-800">
            <span class="material-symbols-rounded text-[26px] text-[#d8a735] bg-[#d8a735]/10 p-2 rounded-xl">person_add</span>
            <div>
                <span class="text-sm font-bold text-white block">Add Bapari</span>
            </div>
        </a>
        <a href="reports.php" class="premium-card flex items-center space-x-3.5 hover:border-slate-800">
            <span class="material-symbols-rounded text-[26px] text-[#d8a735] bg-[#d8a735]/10 p-2 rounded-xl">menu_book</span>
            <div>
                <span class="text-sm font-bold text-white block">Ledger</span>
            </div>
        </a>
    </div>
</div>

<!-- Reports Grid (Matching Layout Exactly) -->
<div class="mb-8">
    <span class="text-[#d8a735] text-[10px] uppercase font-bold tracking-wider block mb-4">Reports</span>
    
    <div class="grid grid-cols-2 gap-4">
        <div class="premium-card bg-[#121212]/50">
            <span class="text-slate-500 text-[9px] uppercase font-bold block mb-1">Total Fine Deposit</span>
            <div class="text-base font-bold text-white font-mono"><?= number_format($totalJamaFine, 3) ?> g</div>
        </div>
        
        <div class="premium-card bg-[#121212]/50">
            <span class="text-slate-500 text-[9px] uppercase font-bold block mb-1">Total Kaj Fine</span>
            <div class="text-base font-bold text-white font-mono"><?= number_format($totalKajFine, 3) ?> g</div>
        </div>
        
        <div class="premium-card bg-[#121212]/50 gold-border">
            <span class="text-slate-500 text-[9px] uppercase font-bold block mb-1">Profit Fine</span>
            <div class="text-base font-bold text-white font-mono"><?= number_format($totalProfitFine, 3) ?> g</div>
        </div>
        
        <div class="premium-card bg-[#121212]/50">
            <span class="text-slate-500 text-[9px] uppercase font-bold block mb-1">Cash Received</span>
            <div class="text-base font-bold text-white font-mono">₹<?= number_format($totalCashRec, 0) ?></div>
        </div>
        
        <div class="premium-card bg-[#121212]/50">
            <span class="text-slate-500 text-[9px] uppercase font-bold block mb-1">Cash Bill</span>
            <div class="text-base font-bold text-white font-mono">₹<?= number_format($totalCashBill, 0) ?></div>
        </div>
        
        <div class="premium-card bg-[#121212]/50">
            <span class="text-slate-500 text-[9px] uppercase font-bold block mb-1">Cash Balance</span>
            <div class="text-base font-bold text-emerald-400 font-mono">₹<?= number_format($netCashBalance, 0) ?></div>
        </div>
    </div>
</div>

<!-- Load Demo Data Button - COMMENTED OUT
<div class="mb-6">
    <form method="POST">
        <button type="submit" name="load_demo_data" class="w-full py-3.5 rounded-3xl text-sm font-bold text-slate-950 bg-[#d8a735] hover:opacity-90 transition-all flex items-center justify-center space-x-1.5 shadow-lg shadow-[#d8a735]/10">
            <span class="material-symbols-rounded text-lg">auto_awesome</span>
            <span>Load Demo Data</span>
        </button>
    </form>
</div>
-->

<?php
require_once 'footer.php';
?>
