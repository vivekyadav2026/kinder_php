<?php
require_once 'db.php';

// Get filters
$filterBapari = intval($_GET['bapari_id'] ?? 0);
$filterMonth = $_GET['month'] ?? '';
$filterYear = $_GET['year'] ?? '';

// Build query components for deposits
$depQuery = "SELECT * FROM fine_deposits WHERE user_id = ?";
$depParams = [$userId];

if ($filterBapari > 0) {
    $depQuery .= " AND bapari_id = ?";
    $depParams[] = $filterBapari;
}
if ($filterYear) {
    $depQuery .= " AND YEAR(date) = ?";
    $depParams[] = $filterYear;
}
if ($filterMonth) {
    $depQuery .= " AND MONTH(date) = ?";
    $depParams[] = $filterMonth;
}

$stmt = $pdo->prepare($depQuery);
$stmt->execute($depParams);
$deposits = $stmt->fetchAll();

// Build query components for kaj entries
$kajQuery = "SELECT * FROM kaj_entries WHERE user_id = ?";
$kajParams = [$userId];

if ($filterBapari > 0) {
    $kajQuery .= " AND bapari_id = ?";
    $kajParams[] = $filterBapari;
}
if ($filterYear) {
    $kajQuery .= " AND YEAR(date) = ?";
    $kajParams[] = $filterYear;
}
if ($filterMonth) {
    $kajQuery .= " AND MONTH(date) = ?";
    $kajParams[] = $filterMonth;
}

$stmt = $pdo->prepare($kajQuery);
$stmt->execute($kajParams);
$kajEntries = $stmt->fetchAll();

// Process aggregates
$totalJama = 0.0;
$totalRec = 0.0;
foreach ($deposits as $d) {
    $totalJama += floatval($d['jama_fine']);
    $totalRec += floatval($d['cash_received']);
}

$totalKajFine = 0.0;
$totalProfitFine = 0.0;
$totalBill = 0.0;

foreach ($kajEntries as $k) {
    $totalKajFine += floatval($k['total_kaj_fine']);
    $totalProfitFine += floatval($k['total_profit_fine']);
    $totalBill += floatval($k['cash_bill']);
}

$fineBal = round($totalJama - $totalKajFine, 3);
$cashBal = round($totalRec - $totalBill, 2);

// Fetch Baparis for filter dropdown
$stmt = $pdo->prepare("SELECT id, name FROM baparis WHERE user_id = ? ORDER BY name ASC");
$stmt->execute([$userId]);
$baparisList = $stmt->fetchAll();

require_once 'header.php';
?>

<!-- Title -->
<div class="mb-6 mt-2">
    <h1 class="text-3xl font-extrabold tracking-tight text-white flex items-center">
        <span class="material-symbols-rounded text-[#F4B400] mr-2 text-3xl">analytics</span> Reports
    </h1>
    <p class="text-slate-400 text-xs mt-1">Generate dynamic statements, check margins, and track transactions.</p>
</div>

<!-- Filters Card -->
<div class="premium-card mb-6">
    <form method="GET" class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-[9px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Customer Filter</label>
                <select name="bapari_id" class="premium-input text-xs">
                    <option value="">All Customers</option>
                    <?php foreach ($baparisList as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $filterBapari == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-[9px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Month</label>
                <select name="month" class="premium-input text-xs">
                    <option value="">All Months</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $filterMonth == $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-[9px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Year</label>
                <select name="year" class="premium-input text-xs">
                    <option value="">All Years</option>
                    <?php 
                    $currentYear = intval(date('Y'));
                    for ($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
                        <option value="<?= $y ?>" <?= $filterYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        
        <div class="flex items-center justify-end space-x-2 pt-2">
            <a href="reports.php" class="btn-secondary text-xs px-4 py-2 flex items-center justify-center"><span class="material-symbols-rounded text-sm mr-1">restart_alt</span> Reset</a>
            <button type="submit" class="btn-gold text-xs px-4 py-2 flex items-center justify-center"><span class="material-symbols-rounded text-sm mr-1">filter_list</span> Apply Filter</button>
        </div>
    </form>
</div>

<!-- Aggregated Metrics Grid -->
<div class="grid grid-cols-2 gap-4 mb-6">
    <div class="premium-card">
        <span class="text-desc font-semibold uppercase text-[9px] block mb-1">Total Gold Jama</span>
        <div class="text-lg font-bold text-emerald-400 font-mono"><?= number_format($totalJama, 3) ?> g</div>
    </div>
    
    <div class="premium-card">
        <span class="text-desc font-semibold uppercase text-[9px] block mb-1">Total Gold Billed</span>
        <div class="text-lg font-bold text-rose-400 font-mono">-<?= number_format($totalKajFine, 3) ?> g</div>
    </div>
    
    <div class="premium-card">
        <span class="text-desc font-semibold uppercase text-[9px] block mb-1">Total Profit Gold</span>
        <div class="text-lg font-bold text-pink-400 font-mono"><?= number_format($totalProfitFine, 3) ?> g</div>
    </div>
    
    <div class="premium-card">
        <span class="text-desc font-semibold uppercase text-[9px] block mb-1">Total Cash Received</span>
        <div class="text-lg font-bold text-blue-400 font-mono">₹<?= number_format($totalRec, 2) ?></div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    <!-- Fine Balance -->
    <div class="premium-card">
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Net Gold Balance</h3>
        <div class="text-2xl font-bold font-mono <?= $fineBal >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>">
            <?= number_format($fineBal, 3) ?> g
        </div>
        
        <!-- Premium Visual Progress Indicator -->
        <div class="w-full bg-slate-900 rounded-full h-1.5 mt-4 overflow-hidden">
            <?php 
            $maxGold = max(1, $totalJama + $totalKajFine);
            $percentage = min(100, round(($totalJama / $maxGold) * 100));
            ?>
            <div class="bg-gradient-to-r from-amber-500 to-[#F4B400] h-1.5 rounded-full" style="width: <?= $percentage ?>%;"></div>
        </div>
        <p class="text-[9px] text-slate-500 mt-2">Active Gold flow ratio: <?= $percentage ?>% credited</p>
    </div>
    
    <!-- Cash Balance -->
    <div class="premium-card">
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Net Cash Balance</h3>
        <div class="text-2xl font-bold font-mono <?= $cashBal >= 0 ? 'text-blue-400' : 'text-rose-400' ?>">
            ₹<?= number_format($cashBal, 2) ?>
        </div>
        
        <!-- Premium Visual Progress Indicator -->
        <div class="w-full bg-slate-900 rounded-full h-1.5 mt-4 overflow-hidden">
            <?php 
            $maxCash = max(1, $totalRec + $totalBill);
            $cashPercentage = min(100, round(($totalRec / $maxCash) * 100));
            ?>
            <div class="bg-gradient-to-r from-blue-500 to-[#6366F1] h-1.5 rounded-full" style="width: <?= $cashPercentage ?>%;"></div>
        </div>
        <p class="text-[9px] text-slate-500 mt-2">Active Cash collection ratio: <?= $cashPercentage ?>% settled</p>
    </div>
</div>

<?php
require_once 'footer.php';
?>
