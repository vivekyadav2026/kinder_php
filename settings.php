<?php
require_once 'db.php';

// Handle JSON Backup Trigger
if (isset($_GET['backup']) && $_GET['backup'] === 'json') {
    $backupData = [];
    
    // Fetch Baparis
    $stmt = $pdo->prepare("SELECT * FROM baparis WHERE user_id = ?");
    $stmt->execute([$userId]);
    $backupData['baparis'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Deposits
    $stmt = $pdo->prepare("SELECT * FROM fine_deposits WHERE user_id = ?");
    $stmt->execute([$userId]);
    $backupData['deposits'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Kaj Entries
    $stmt = $pdo->prepare("SELECT * FROM kaj_entries WHERE user_id = ?");
    $stmt->execute([$userId]);
    $backupData['kaj_entries'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="Dasgold_Backup_' . date('Ymd_His') . '.json"');
    echo json_encode($backupData, JSON_PRETTY_PRINT);
    exit();
}

// Handle Save Company Profile Details (Mockup for GST/Company parameters)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_company'])) {
    $companyName = trim($_POST['company_name'] ?? '');
    $companyMobile = trim($_POST['company_mobile'] ?? '');
    $companyAddress = trim($_POST['company_address'] ?? '');
    $companyGst = trim($_POST['company_gst'] ?? '');
    
    // Store in Session or user metadata (mockup settings persistence)
    $_SESSION['company_name'] = $companyName;
    $_SESSION['company_mobile'] = $companyMobile;
    $_SESSION['company_address'] = $companyAddress;
    $_SESSION['company_gst'] = $companyGst;
    
    $success = 'Company details updated successfully!';
}

// Get report filter parameters
$filterMonth = $_GET['month'] ?? '';
$filterYear = $_GET['year'] ?? '';
$filterItem = trim($_GET['item'] ?? '');

// Base Queries for aggregates
$depQuery = "SELECT * FROM fine_deposits WHERE user_id = ?";
$depParams = [$userId];
if ($filterYear) { $depQuery .= " AND YEAR(date) = ?"; $depParams[] = $filterYear; }
if ($filterMonth) { $depQuery .= " AND MONTH(date) = ?"; $depParams[] = $filterMonth; }

$stmt = $pdo->prepare($depQuery);
$stmt->execute($depParams);
$deposits = $stmt->fetchAll();

$kajQuery = "SELECT k.* FROM kaj_entries k WHERE k.user_id = ?";
$kajParams = [$userId];
if ($filterYear) { $kajQuery .= " AND YEAR(k.date) = ?"; $kajParams[] = $filterYear; }
if ($filterMonth) { $kajQuery .= " AND MONTH(k.date) = ?"; $kajParams[] = $filterMonth; }
if ($filterItem) {
    $kajQuery .= " AND EXISTS (SELECT 1 FROM kaj_items ki WHERE ki.kaj_entry_id = k.id AND ki.item LIKE ?)";
    $kajParams[] = '%' . $filterItem . '%';
}

$stmt = $pdo->prepare($kajQuery);
$stmt->execute($kajParams);
$kajEntries = $stmt->fetchAll();

// Calculate aggregates
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

$netFineBalance = round($totalJama - $totalKajFine, 3);
$netCashBalance = round($totalRec - $totalBill, 2);

require_once 'header.php';
?>

<!-- Title (Matching Image 2) -->
<div class="mb-5 mt-2">
    <h1 class="text-3xl font-extrabold tracking-tight text-white">
        Settings
    </h1>
</div>

<!-- ACCOUNT Section -->
<div class="mb-6">
    <span class="text-slate-500 text-[10px] uppercase font-bold tracking-wider block mb-3">Account</span>
    <div class="premium-card bg-[#121212]/80 flex items-center justify-between p-4">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-full bg-[#d8a735]/15 border border-[#d8a735]/20 flex items-center justify-center font-bold text-[#d8a735]">
                <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
            </div>
            <div>
                <h3 class="text-sm font-bold text-white leading-tight"><?= htmlspecialchars($_SESSION['user_name'] ?? 'new') ?></h3>
                <p class="text-[10px] text-slate-500 mt-0.5"><?= htmlspecialchars($_SESSION['user_email'] ?? 'admin@demo.com') ?></p>
            </div>
        </div>
        <a href="logout.php" class="py-1.5 px-3.5 rounded-xl border border-rose-500/20 bg-rose-500/10 hover:bg-rose-500/20 text-xs font-semibold text-rose-400 flex items-center space-x-1 transition-colors tap-target">
            <span class="material-symbols-rounded text-sm">logout</span>
            <span>Logout</span>
        </a>
    </div>
</div>


<!-- COMPANY PROFILE Section -->
<div class="mb-6">
    <span class="text-slate-500 text-[10px] uppercase font-bold tracking-wider block mb-3">Company Profile</span>
    <div class="premium-card bg-[#121212]/80">
        <form method="POST" class="space-y-4">
            <div class="flex items-center space-x-4 mb-2">
                <div class="w-14 h-14 rounded-xl border border-dashed border-slate-700 bg-slate-950 flex flex-col items-center justify-center text-slate-500 cursor-pointer hover:border-[#d8a735]/40 hover:text-slate-300 transition-colors">
                    <span class="material-symbols-rounded text-lg">photo_camera</span>
                    <span class="text-[8px] font-bold mt-1 uppercase">Logo</span>
                </div>
                <div class="text-[10px] text-slate-500">Upload your gold shop logo to display on statements.</div>
            </div>
            
            <div>
                <input type="text" name="company_name" value="<?= htmlspecialchars($_SESSION['company_name'] ?? '') ?>" class="premium-input text-xs" placeholder="Company Name">
            </div>
            <div>
                <input type="text" name="company_mobile" value="<?= htmlspecialchars($_SESSION['company_mobile'] ?? '') ?>" class="premium-input text-xs" placeholder="Mobile">
            </div>
            <div>
                <input type="text" name="company_address" value="<?= htmlspecialchars($_SESSION['company_address'] ?? '') ?>" class="premium-input text-xs" placeholder="Address">
            </div>
            <div>
                <input type="text" name="company_gst" value="<?= htmlspecialchars($_SESSION['company_gst'] ?? '') ?>" class="premium-input text-xs" placeholder="GST Number">
            </div>
            
            <button type="submit" name="save_company" class="w-full btn-gold text-xs font-bold py-3.5 tracking-wide mt-2">
                Save Company
            </button>
        </form>
    </div>
</div>

<!-- REPORTS Filtering Panel -->
<div class="mb-6">
    <span class="text-slate-500 text-[10px] uppercase font-bold tracking-wider block mb-3">Reports</span>
    
    <div class="premium-card bg-[#121212]/80 space-y-4">
        <form method="GET" id="reportFilterForm">
            <div class="mb-3">
                <span class="px-4 py-2 rounded-full text-xs font-bold bg-[#d8a735]/15 border border-[#d8a735]/25 text-[#d8a735] inline-block">All Baparis</span>
            </div>
            
            <div class="grid grid-cols-2 gap-3.5 mb-3">
                <input type="number" name="month" id="filterMonthInput" value="<?= $filterMonth ?>" class="premium-input text-xs" placeholder="Month (MM)">
                <input type="number" name="year" value="<?= $filterYear ?: date('Y') ?>" class="premium-input text-xs" placeholder="Year (YYYY)">
            </div>
            
            <!-- Horizontal month selector bar (Matching Image 2) -->
            <div class="flex items-center space-x-1.5 overflow-x-auto pb-2.5 mb-3 scrollbar-hide">
                <?php 
                $months = ['All' => '', 'Jan' => '1', 'Feb' => '2', 'Mar' => '3', 'Apr' => '4', 'May' => '5', 'Jun' => '6', 'Jul' => '7', 'Aug' => '8', 'Sep' => '9', 'Oct' => '10', 'Nov' => '11', 'Dec' => '12'];
                foreach ($months as $label => $val):
                    $isActive = ($filterMonth === $val);
                ?>
                    <button type="button" onclick="selectMonthPill('<?= $val ?>')" class="px-3.5 py-1.5 rounded-full text-[10px] font-bold whitespace-nowrap transition-colors <?= $isActive ? 'bg-[#d8a735] text-slate-950' : 'bg-slate-900 border border-white/[0.04] text-slate-400' ?>">
                        <?= $label ?>
                    </button>
                <?php endforeach; ?>
            </div>
            
            <div class="mb-4">
                <input type="text" name="item" value="<?= htmlspecialchars($filterItem) ?>" class="premium-input text-xs" placeholder="Item filter">
            </div>
            
            <div class="grid grid-cols-2 gap-3.5">
                <button type="submit" class="btn-gold text-xs py-3">Apply</button>
                <a href="settings.php" class="btn-secondary text-xs py-3 flex items-center justify-center">Clear</a>
            </div>
        </form>

        <!-- Dynamic aggregates results inside settings (Matching Image 2) -->
        <div class="space-y-3 pt-3.5 border-t border-slate-800">
            <div class="bg-slate-950/60 p-3 rounded-xl border border-white/[0.03] flex justify-between items-center text-xs">
                <span class="text-slate-500 font-semibold uppercase text-[9px]">Total Fine Deposit</span>
                <span class="font-bold font-mono"><?= number_format($totalJama, 3) ?> g</span>
            </div>
            <div class="bg-slate-950/60 p-3 rounded-xl border border-white/[0.03] flex justify-between items-center text-xs">
                <span class="text-slate-500 font-semibold uppercase text-[9px]">Total Kaj Fine</span>
                <span class="font-bold font-mono"><?= number_format($totalKajFine, 3) ?> g</span>
            </div>
            <div class="bg-transparent p-3 rounded-xl border border-[#d8a735]/25 flex justify-between items-center text-xs">
                <span class="text-[#d8a735] font-semibold uppercase text-[9px]">Profit Fine</span>
                <span class="font-bold font-mono text-[#d8a735]"><?= number_format($totalProfitFine, 3) ?> g</span>
            </div>
            <div class="bg-slate-950/60 p-3 rounded-xl border border-white/[0.03] flex justify-between items-center text-xs">
                <span class="text-slate-500 font-semibold uppercase text-[9px]">Cash Received</span>
                <span class="font-bold font-mono">₹<?= number_format($totalRec, 0) ?></span>
            </div>
            <div class="bg-slate-950/60 p-3 rounded-xl border border-white/[0.03] flex justify-between items-center text-xs">
                <span class="text-slate-500 font-semibold uppercase text-[9px]">Cash Bill</span>
                <span class="font-bold font-mono">₹<?= number_format($totalBill, 0) ?></span>
            </div>
            <div class="bg-slate-950/60 p-3 rounded-xl border border-white/[0.03] flex justify-between items-center text-xs">
                <span class="text-slate-500 font-semibold uppercase text-[9px]">Fine Balance</span>
                <span class="font-bold font-mono text-emerald-400"><?= number_format($netFineBalance, 3) ?> g</span>
            </div>
            <div class="bg-slate-950/60 p-3 rounded-xl border border-white/[0.03] flex justify-between items-center text-xs">
                <span class="text-slate-500 font-semibold uppercase text-[9px]">Cash Balance</span>
                <span class="font-bold font-mono text-emerald-400">₹<?= number_format($netCashBalance, 0) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- EXPORTS Action Links -->
<div class="mb-6">
    <span class="text-slate-500 text-[10px] uppercase font-bold tracking-wider block mb-3">Exports</span>
    <div class="premium-card bg-[#121212]/80 p-0 overflow-hidden divide-y divide-white/[0.04] text-xs">
        <button onclick="window.print()" class="w-full px-4.5 py-4 flex items-center justify-between hover:bg-white/[0.02] text-left">
            <span class="flex items-center"><span class="material-symbols-rounded text-slate-500 mr-2.5">description</span> Download Report PDF</span>
            <span class="material-symbols-rounded text-slate-500">chevron_right</span>
        </button>
        
        <button onclick="alert('CSV download triggered!')" class="w-full px-4.5 py-4 flex items-center justify-between hover:bg-white/[0.02] text-left">
            <span class="flex items-center"><span class="material-symbols-rounded text-slate-500 mr-2.5">grid_view</span> Download Report CSV</span>
            <span class="material-symbols-rounded text-slate-500">chevron_right</span>
        </button>
        
        <a href="settings.php?backup=json" class="w-full px-4.5 py-4 flex items-center justify-between hover:bg-white/[0.02] text-left block">
            <span class="flex items-center"><span class="material-symbols-rounded text-[#d8a735] mr-2.5">cloud_download</span> Backup JSON</span>
            <span class="material-symbols-rounded text-slate-500">chevron_right</span>
        </a>
    </div>
</div>

<script>
    function selectMonthPill(monthVal) {
        document.getElementById('filterMonthInput').value = monthVal;
        document.getElementById('reportFilterForm').submit();
    }
</script>

<?php
require_once 'footer.php';
?>
