<?php
require_once 'db.php';

// Handle CSV export request
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $bapariId = intval($_GET['bapari_id'] ?? 0);
    $karigorId = intval($_GET['karigor_id'] ?? 0);
    $from = $_GET['from'] ?? '';
    $to = $_GET['to'] ?? '';

    if ($bapariId > 0) {
        // Fetch Bapari
        $stmt = $pdo->prepare("SELECT name FROM baparis WHERE id = ? AND user_id = ?");
        $stmt->execute([$bapariId, $userId]);
        $bapari = $stmt->fetch();
        $filename = ($bapari ? str_replace(' ', '_', $bapari['name']) : 'Bapari') . "_Statement_" . date('Ymd') . ".csv";

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Date', 'Type', 'Gold Weight (g)', 'Purity (%)', 'Jama/Kaj Fine (g)', 'Cash Amount (Rs)', 'Remark']);

        // Fetch Deposits
        $depQuery = "SELECT date, 'Gold Jama' as type, fine_weight, purity, jama_fine as fine, cash_received as cash, remark FROM fine_deposits WHERE bapari_id = ? AND user_id = ?";
        $params = [$bapariId, $userId];
        if ($from) { $depQuery .= " AND date >= ?"; $params[] = $from; }
        if ($to) { $depQuery .= " AND date <= ?"; $params[] = $to; }
        $stmt = $pdo->prepare($depQuery);
        $stmt->execute($params);
        $deposits = $stmt->fetchAll();

        // Fetch Kaj Entries
        $kajQuery = "SELECT date, 'Kaarigari Job' as type, 0.0 as fine_weight, 0.0 as purity, total_kaj_fine as fine, cash_bill as cash, remark FROM kaj_entries WHERE bapari_id = ? AND user_id = ?";
        $paramsK = [$bapariId, $userId];
        if ($from) { $kajQuery .= " AND date >= ?"; $paramsK[] = $from; }
        if ($to) { $kajQuery .= " AND date <= ?"; $paramsK[] = $to; }
        $stmt = $pdo->prepare($kajQuery);
        $stmt->execute($paramsK);
        $kajs = $stmt->fetchAll();

        $entries = array_merge($deposits, $kajs);
        usort($entries, function($a, $b) { return strcmp($a['date'], $b['date']); });

        foreach ($entries as $e) {
            fputcsv($output, [
                date('d/m/Y', strtotime($e['date'])),
                $e['type'],
                $e['fine_weight'] > 0 ? $e['fine_weight'] : '--',
                $e['purity'] > 0 ? $e['purity'] . '%' : '--',
                $e['fine'],
                $e['cash'] > 0 ? $e['cash'] : '--',
                $e['remark']
            ]);
        }
        fclose($output);
        exit();
    } elseif ($karigorId > 0) {
        // Fetch Karigor
        $stmt = $pdo->prepare("SELECT name FROM karigors WHERE id = ? AND user_id = ?");
        $stmt->execute([$karigorId, $userId]);
        $karigor = $stmt->fetch();
        $filename = ($karigor ? str_replace(' ', '_', $karigor['name']) : 'Karigor') . "_Statement_" . date('Ymd') . ".csv";

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Date', 'Type', 'Gold Weight (g)', 'Purity (%)', 'Issue/Receive Fine (g)', 'Cash Amount (Rs)', 'Remark']);

        // Fetch Issues
        $issueQuery = "SELECT date, 'Material Issue (OUT)' as type, fine_weight, purity, issue_fine as fine, cash_paid as cash, remark FROM karigor_material_issues WHERE karigor_id = ? AND user_id = ?";
        $params = [$karigorId, $userId];
        if ($from) { $issueQuery .= " AND date >= ?"; $params[] = $from; }
        if ($to) { $issueQuery .= " AND date <= ?"; $params[] = $to; }
        $stmt = $pdo->prepare($issueQuery);
        $stmt->execute($params);
        $issues = $stmt->fetchAll();

        // Fetch Receives
        $recQuery = "SELECT date, 'Kaj Receive (IN)' as type, 0.0 as fine_weight, 0.0 as purity, total_receive_fine as fine, cash_paid as cash, remark FROM karigor_kaj_receives WHERE karigor_id = ? AND user_id = ?";
        $paramsK = [$karigorId, $userId];
        if ($from) { $recQuery .= " AND date >= ?"; $paramsK[] = $from; }
        if ($to) { $recQuery .= " AND date <= ?"; $paramsK[] = $to; }
        $stmt = $pdo->prepare($recQuery);
        $stmt->execute($paramsK);
        $receives = $stmt->fetchAll();

        $entries = array_merge($issues, $receives);
        usort($entries, function($a, $b) { return strcmp($a['date'], $b['date']); });

        foreach ($entries as $e) {
            fputcsv($output, [
                date('d/m/Y', strtotime($e['date'])),
                $e['type'],
                $e['fine_weight'] > 0 ? $e['fine_weight'] : '--',
                $e['purity'] > 0 ? $e['purity'] . '%' : '--',
                $e['fine'],
                $e['cash'] > 0 ? $e['cash'] : '--',
                $e['remark']
            ]);
        }
        fclose($output);
        exit();
    }
}

// Fetch baparis for selector
$stmt = $pdo->prepare("SELECT id, name FROM baparis WHERE user_id = ? ORDER BY name ASC");
$stmt->execute([$userId]);
$baparis = $stmt->fetchAll();

// Fetch karigors for selector
$stmt = $pdo->prepare("SELECT id, name FROM karigors WHERE user_id = ? ORDER BY name ASC");
$stmt->execute([$userId]);
$karigors = $stmt->fetchAll();

require_once 'header.php';
?>

<!-- Title (Matching Image 1) -->
<div class="mb-5 mt-2 flex items-center justify-between">
    <h1 class="text-3xl font-extrabold tracking-tight text-[#d8a735]">
        Ledger Statement
    </h1>
</div>

<!-- Mode Selector (Bapari vs Karigor) -->
<div class="flex items-center space-x-2 mb-6">
    <button type="button" id="btnTypeBapari" onclick="switchLedgerType('bapari')" class="flex-1 py-3 rounded-xl text-xs font-bold transition-all bg-[#d8a735] text-slate-950 shadow-md">
        Bapari Ledger
    </button>
    <button type="button" id="btnTypeKarigor" onclick="switchLedgerType('karigor')" class="flex-1 py-3 rounded-xl text-xs font-bold transition-all bg-slate-900 text-slate-400 border border-white/[0.04] hover:text-white">
        Karigor Ledger
    </button>
</div>

<form id="ledgerForm" method="GET" action="ledger.php" class="space-y-6">
    <!-- Select Bapari Dropdown -->
    <div id="bapariSelectGroup" class="relative">
        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
            <span class="material-symbols-rounded text-lg">person</span>
        </span>
        <select name="bapari_id" id="bapariId" class="premium-input pl-10 text-sm appearance-none">
            <option value="">Select Bapari</option>
            <?php foreach ($baparis as $b): ?>
                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-slate-500">
            <span class="material-symbols-rounded text-lg">keyboard_arrow_down</span>
        </span>
    </div>

    <!-- Select Karigor Dropdown (Hidden initially) -->
    <div id="karigorSelectGroup" class="relative hidden">
        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
            <span class="material-symbols-rounded text-lg">engineering</span>
        </span>
        <select name="karigor_id" id="karigorId" class="premium-input pl-10 text-sm appearance-none">
            <option value="">Select Karigor</option>
            <?php foreach ($karigors as $k): ?>
                <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-slate-500">
            <span class="material-symbols-rounded text-lg">keyboard_arrow_down</span>
        </span>
    </div>


    <!-- From and To Date Pickers Side by Side -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">From</label>
            <input type="date" name="from" id="fromDate" class="premium-input text-sm">
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">To</label>
            <input type="date" name="to" id="toDate" class="premium-input text-sm">
        </div>
    </div>

    <!-- Action Buttons Row (PDF and CSV) -->
    <div class="space-y-3 pt-2">
        <button type="submit" class="w-full py-4 rounded-xl bg-[#d8a735] hover:bg-[#d8a735]/90 text-sm font-bold text-slate-950 flex items-center justify-center space-x-1.5 shadow-lg shadow-[#d8a735]/10 tap-target">
            <span class="material-symbols-rounded text-lg">visibility</span>
            <span>View Ledger Statement</span>
        </button>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <button type="button" onclick="generatePDF()" class="w-full py-3.5 rounded-xl border border-[#d8a735]/40 bg-transparent text-sm font-semibold text-[#d8a735] hover:bg-[#d8a735]/5 flex items-center justify-center space-x-1.5 tap-target">
                <span class="material-symbols-rounded text-lg">description</span>
                <span>PDF Print</span>
            </button>
            
            <button type="button" onclick="generateCSV()" class="w-full py-3.5 rounded-xl border border-[#d8a735]/40 bg-transparent text-sm font-semibold text-[#d8a735] hover:bg-[#d8a735]/5 flex items-center justify-center space-x-1.5 tap-target">
                <span class="material-symbols-rounded text-lg">grid_view</span>
                <span>CSV Export</span>
            </button>
        </div>
    </div>
</form>

<script>
    let currentLedgerType = 'bapari';

    function switchLedgerType(type) {
        currentLedgerType = type;
        const form = document.getElementById('ledgerForm');
        const bGroup = document.getElementById('bapariSelectGroup');
        const kGroup = document.getElementById('karigorSelectGroup');
        const bBtn = document.getElementById('btnTypeBapari');
        const kBtn = document.getElementById('btnTypeKarigor');

        if (type === 'bapari') {
            form.action = 'ledger.php';
            bGroup.classList.remove('hidden');
            kGroup.classList.add('hidden');
            bBtn.className = "flex-1 py-3 rounded-xl text-xs font-bold transition-all bg-[#d8a735] text-slate-950 shadow-md";
            kBtn.className = "flex-1 py-3 rounded-xl text-xs font-bold transition-all bg-slate-900 text-slate-400 border border-white/[0.04] hover:text-white";
        } else {
            form.action = 'karigor_ledger.php';
            kGroup.classList.remove('hidden');
            bGroup.classList.add('hidden');
            kBtn.className = "flex-1 py-3 rounded-xl text-xs font-bold transition-all bg-[#d8a735] text-slate-950 shadow-md";
            bBtn.className = "flex-1 py-3 rounded-xl text-xs font-bold transition-all bg-slate-900 text-slate-400 border border-white/[0.04] hover:text-white";
        }
    }

    function generatePDF() {
        const from = document.getElementById('fromDate').value;
        const to = document.getElementById('toDate').value;

        if (currentLedgerType === 'bapari') {
            const bapariId = document.getElementById('bapariId').value;
            if (!bapariId) {
                alert('Please select a Bapari first!');
                return;
            }
            let url = `ledger.php?bapari_id=${bapariId}`;
            if (from) url += `&from=${from}`;
            if (to) url += `&to=${to}`;
            url += `&print=1`;
            const w = window.open(url, '_blank');
            w.focus();
        } else {
            const karigorId = document.getElementById('karigorId').value;
            if (!karigorId) {
                alert('Please select a Karigor first!');
                return;
            }
            let url = `karigor_ledger.php?karigor_id=${karigorId}`;
            if (from) url += `&from=${from}`;
            if (to) url += `&to=${to}`;
            url += `&print=1`;
            const w = window.open(url, '_blank');
            w.focus();
        }
    }

    function generateCSV() {
        const from = document.getElementById('fromDate').value;
        const to = document.getElementById('toDate').value;
        
        if (currentLedgerType === 'bapari') {
            const bapariId = document.getElementById('bapariId').value;
            if (!bapariId) {
                alert('Please select a Bapari first!');
                return;
            }
            let url = `reports.php?export=csv&bapari_id=${bapariId}`;
            if (from) url += `&from=${from}`;
            if (to) url += `&to=${to}`;
            window.location.href = url;
        } else {
            const karigorId = document.getElementById('karigorId').value;
            if (!karigorId) {
                alert('Please select a Karigor first!');
                return;
            }
            let url = `reports.php?export=csv&karigor_id=${karigorId}`;
            if (from) url += `&from=${from}`;
            if (to) url += `&to=${to}`;
            window.location.href = url;
        }
    }
</script>

<?php
require_once 'footer.php';
?>


