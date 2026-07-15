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
<div class="mb-6">
    <h1 class="text-3xl font-extrabold tracking-tight text-white flex items-center">
        <span class="gold-text mr-2"><i class="fa-solid fa-file-invoice"></i></span> Reports
    </h1>
    <p class="text-slate-400 text-sm mt-1">Generate dynamic statements, check margins, and track transactions.</p>
</div>

<!-- Filters Card -->
<div class="glass-card rounded-2xl p-5 mb-8 border border-slate-800">
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Bapari Filter</label>
            <select name="bapari_id" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3.5 py-2.5 text-slate-200 focus:outline-none focus:border-amber-400 transition-colors text-sm">
                <option value="">All Baparis</option>
                <?php foreach ($baparisList as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $filterBapari == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Month</label>
            <select name="month" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3.5 py-2.5 text-slate-200 focus:outline-none focus:border-amber-400 transition-colors text-sm">
                <option value="">All Months</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $filterMonth == $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                <?php endphp ?>
                <?php endfor; ?>
            </select>
        </div>
        
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Year</label>
            <select name="year" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3.5 py-2.5 text-slate-200 focus:outline-none focus:border-amber-400 transition-colors text-sm">
                <option value="">All Years</option>
                <?php 
                $currentYear = intval(date('Y'));
                for ($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
                    <option value="<?= $y ?>" <?= $filterYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        
        <div class="flex space-x-2">
            <button type="submit" class="w-full px-4 py-2.5 rounded-xl text-sm font-semibold text-slate-950 gold-bg hover:opacity-90 shadow-md transition-all flex items-center justify-center space-x-1.5">
                <i class="fa-solid fa-filter"></i> <span>Filter</span>
            </button>
            <a href="reports.php" class="px-4 py-2.5 rounded-xl text-sm font-semibold text-slate-400 bg-slate-800 border border-slate-700/60 hover:text-white transition-all flex items-center justify-center" title="Reset">
                <i class="fa-solid fa-arrow-rotate-left"></i>
            </a>
        </div>
    </form>
</div>

<!-- Aggregated Metrics -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-5 mb-8">
    <div class="glass-card rounded-2xl p-5 border-l-4 border-l-emerald-500">
        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider block mb-1">Total Fine Deposit</span>
        <div class="text-xl font-extrabold text-emerald-400 font-mono"><?= number_format($totalJama, 3) ?> g</div>
    </div>
    
    <div class="glass-card rounded-2xl p-5 border-l-4 border-l-rose-500">
        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider block mb-1">Total Kaj Fine</span>
        <div class="text-xl font-extrabold text-rose-400 font-mono">-<?= number_format($totalKajFine, 3) ?> g</div>
    </div>
    
    <div class="glass-card rounded-2xl p-5 border-l-4 border-l-pink-500">
        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider block mb-1">Total Profit Earned</span>
        <div class="text-xl font-extrabold text-pink-400 font-mono"><?= number_format($totalProfitFine, 3) ?> g</div>
    </div>
    
    <div class="glass-card rounded-2xl p-5 border-l-4 border-l-blue-500">
        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider block mb-1">Total Cash Received</span>
        <div class="text-xl font-extrabold text-blue-400 font-mono">₹<?= number_format($totalRec, 2) ?></div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Fine Balance -->
    <div class="glass-card rounded-2xl p-6 border border-slate-800">
        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400 mb-3">Net Gold Balance</h3>
        <div class="text-3xl font-extrabold font-mono <?= $fineBal >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>">
            <?= number_format($fineBal, 3) ?> g
        </div>
        <p class="text-xs text-slate-500 mt-1">Based on <?= count($deposits) ?> deposits & <?= count($kajEntries) ?> manufacturing entries.</p>
    </div>
    
    <!-- Cash Balance -->
    <div class="glass-card rounded-2xl p-6 border border-slate-800">
        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400 mb-3">Net Cash Balance</h3>
        <div class="text-3xl font-extrabold font-mono <?= $cashBal >= 0 ? 'text-blue-400' : 'text-rose-400' ?>">
            ₹<?= number_format($cashBal, 2) ?>
        </div>
        <p class="text-xs text-slate-500 mt-1">Total labor charges billed: ₹<?= number_format($totalBill, 2) ?></p>
    </div>
</div>

<?php
require_once 'footer.php';
?>
