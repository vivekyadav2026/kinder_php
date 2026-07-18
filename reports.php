<?php
require_once 'db.php';

// Handle CSV export request
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $bapariId = intval($_GET['bapari_id'] ?? 0);
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
    }
}

// Fetch baparis for selector
$stmt = $pdo->prepare("SELECT id, name FROM baparis WHERE user_id = ? ORDER BY name ASC");
$stmt->execute([$userId]);
$baparis = $stmt->fetchAll();

require_once 'header.php';
?>

<!-- Title (Matching Image 1) -->
<div class="mb-5 mt-2">
    <h1 class="text-3xl font-extrabold tracking-tight text-[#d8a735]">
        Ledger
    </h1>
</div>

<form id="ledgerForm" method="GET" action="ledger.php" class="space-y-6">
    <!-- Select Bapari Dropdown -->
    <div class="relative">
        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
            <span class="material-symbols-rounded text-lg">person</span>
        </span>
        <select name="bapari_id" id="bapariId" required class="premium-input pl-10 text-sm appearance-none">
            <option value="">Select Bapari</option>
            <?php foreach ($baparis as $b): ?>
                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-slate-500">
            <span class="material-symbols-rounded text-lg">keyboard_arrow_down</span>
        </span>
    </div>

    <!-- From and To Date Pickers Side by Side -->
    <div class="grid grid-cols-2 gap-4">
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

        <div class="grid grid-cols-2 gap-4">
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
    function generatePDF() {
        const bapariId = document.getElementById('bapariId').value;
        if (!bapariId) {
            alert('Please select a Bapari first!');
            return;
        }
        const from = document.getElementById('fromDate').value;
        const to = document.getElementById('toDate').value;
        let url = `ledger.php?bapari_id=${bapariId}`;
        if (from) url += `&from=${from}`;
        if (to) url += `&to=${to}`;
        url += `&print=1`;
        
        // Open PDF Print view
        const w = window.open(url, '_blank');
        w.focus();
    }

    function generateCSV() {
        const bapariId = document.getElementById('bapariId').value;
        if (!bapariId) {
            alert('Please select a Bapari first!');
            return;
        }
        const from = document.getElementById('fromDate').value;
        const to = document.getElementById('toDate').value;
        let url = `reports.php?export=csv&bapari_id=${bapariId}`;
        if (from) url += `&from=${from}`;
        if (to) url += `&to=${to}`;
        
        // Trigger file download
        window.location.href = url;
    }
</script>

<?php
require_once 'footer.php';
?>
