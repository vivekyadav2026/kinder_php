<?php
require_once 'db.php';

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Handle POST submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_kaj'])) {
        $date = $_POST['date'] ?? date('Y-m-d');
        $bapariIdInput = intval($_POST['bapari_id']);
        $cashBill = floatval($_POST['cash_bill'] ?? 0.0);
        $remark = trim($_POST['remark'] ?? '');
        
        $items = $_POST['items'] ?? []; // Array of items

        if ($bapariIdInput <= 0 || empty($items)) {
            $error = 'Invalid Bapari or items list is empty!';
        } else {
            try {
                $pdo->beginTransaction();
                
                $totalKajFine = 0.0;
                $totalProfitFine = 0.0;
                
                // First insert the main kaj entry to get the insert id
                $stmt = $pdo->prepare("INSERT INTO kaj_entries (user_id, date, bapari_id, cash_bill, remark) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $date, $bapariIdInput, $cashBill, $remark]);
                $kajEntryId = $pdo->lastInsertId();
                
                // Prepare items statement
                $stmtItem = $pdo->prepare("
                    INSERT INTO kaj_items (kaj_entry_id, item, gross, less, net, milting, wastage, hisab, kaj_fine, profit_fine) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($items as $it) {
                    $item = trim($it['item'] ?? '');
                    $gross = floatval($it['gross'] ?? 0.0);
                    $less = floatval($it['less'] ?? 0.0);
                    $milting = floatval($it['milting'] ?? 0.0);
                    $wastage = floatval($it['wastage'] ?? 0.0);
                    
                    if (empty($item)) continue;
                    
                    // Calculations
                    $net = round($gross - $less, 3);
                    $hisab = round($milting + $wastage, 2);
                    $kajFine = round(($net * $hisab) / 100.0, 3);
                    $profitFine = round(($wastage * $net) / 100.0, 3);
                    
                    $stmtItem->execute([
                        $kajEntryId, $item, $gross, $less, $net, $milting, $wastage, $hisab, $kajFine, $profitFine
                    ]);
                    
                    $totalKajFine += $kajFine;
                    $totalProfitFine += $profitFine;
                }
                
                // Update the main entry totals
                $stmtUpdate = $pdo->prepare("UPDATE kaj_entries SET total_kaj_fine = ?, total_profit_fine = ? WHERE id = ?");
                $stmtUpdate->execute([$totalKajFine, $totalProfitFine, $kajEntryId]);
                
                $pdo->commit();
                $success = 'Kaj Entry saved successfully!';
                $action = 'list';
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Failed to save entry: ' . $e->getMessage();
            }
        }
    }
}

// Handle Delete Kaj Entry
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM kaj_entries WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    $success = 'Kaj Entry deleted successfully!';
    header("Location: kaj.php");
    exit();
}

// Fetch all Kaj Entries joined with Baparis
$stmt = $pdo->prepare("
    SELECT k.*, b.name as bapari_name 
    FROM kaj_entries k
    JOIN baparis b ON k.bapari_id = b.id 
    WHERE k.user_id = ? 
    ORDER BY k.date DESC, k.id DESC
");
$stmt->execute([$userId]);
$kajEntries = $stmt->fetchAll();

// Fetch Baparis for form selection
$stmt = $pdo->prepare("SELECT id, name FROM baparis WHERE user_id = ? ORDER BY name ASC");
$stmt->execute([$userId]);
$baparisList = $stmt->fetchAll();

require_once 'header.php';
?>

<!-- Feedback Messages -->
<?php if ($error): ?>
    <div class="mb-5 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm flex items-center space-x-2">
        <i class="fa-solid fa-circle-exclamation"></i> <span><?= htmlspecialchars($error) ?></span>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="mb-5 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-center space-x-2">
        <i class="fa-solid fa-circle-check"></i> <span><?= htmlspecialchars($success) ?></span>
    </div>
<?php endif; ?>

<?php if ($action === 'new'): ?>
    <!-- Add Kaj Entry Form -->
    <div class="glass-card rounded-2xl p-6 border border-slate-800">
        <h2 class="text-xl font-bold text-white mb-6 flex items-center">
            <i class="fa-solid fa-hammer text-indigo-400 mr-2"></i> Add Kaj (Manufacturing) Entry
        </h2>
        
        <form method="POST" id="kajForm" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Date *</label>
                    <input type="date" name="date" value="<?= date('Y-m-d') ?>" required class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-amber-400 transition-colors">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Select Bapari *</label>
                    <select name="bapari_id" required class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-amber-400 transition-colors">
                        <option value="">-- Choose --</option>
                        <?php foreach ($baparisList as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Labor Bill/Cash Charge (₹)</label>
                    <input type="number" step="0.01" name="cash_bill" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-amber-400 transition-colors" placeholder="0.00">
                </div>
            </div>
            
            <!-- Items Area -->
            <div>
                <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-2">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400">Items List</h3>
                    <button type="button" onclick="addItemRow()" class="px-3 py-1.5 rounded-lg bg-indigo-600/10 text-indigo-400 hover:bg-indigo-600 hover:text-white transition-all text-xs font-bold flex items-center space-x-1">
                        <i class="fa-solid fa-plus"></i> <span>Add Item</span>
                    </button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[700px]">
                        <thead>
                            <tr class="text-slate-400 text-[10px] font-bold uppercase tracking-wider border-b border-slate-800 pb-2">
                                <th class="py-2 pr-2 w-1/4">Item Name</th>
                                <th class="py-2 px-2 text-right">Gross (g)</th>
                                <th class="py-2 px-2 text-right">Less (g)</th>
                                <th class="py-2 px-2 text-right">Milting (%)</th>
                                <th class="py-2 px-2 text-right">Wastage (%)</th>
                                <th class="py-2 px-2 text-right">Kaj Fine (g)</th>
                                <th class="py-2 px-2 text-right">Profit (g)</th>
                                <th class="py-2 pl-2 text-center w-10"></th>
                            </tr>
                        </thead>
                        <tbody id="itemRows" class="divide-y divide-slate-800/40">
                            <!-- JS will inject dynamic rows here -->
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Remark / Narration</label>
                <input type="text" name="remark" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-amber-400 transition-colors" placeholder="Optional details...">
            </div>
            
            <div class="flex items-center justify-between pt-4 border-t border-slate-800">
                <div class="text-sm font-mono text-slate-400">
                    Est. Total Fine: <span id="totalFineDisp" class="text-red-400 font-semibold">0.000 g</span>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="kaj.php" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-slate-400 hover:text-white hover:bg-slate-800 transition-all">Cancel</a>
                    <button type="submit" name="add_kaj" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 shadow-md shadow-indigo-600/10 transition-all">Save Kaj Entry</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        var rowCount = 0;
        
        function addItemRow() {
            var tbody = document.getElementById('itemRows');
            var tr = document.createElement('tr');
            tr.id = 'row_' + rowCount;
            tr.className = 'hover:bg-slate-800/10 transition-colors';
            
            tr.innerHTML = `
                <td class="py-3 pr-2">
                    <input type="text" name="items[\${rowCount}][item]" required class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-2 text-slate-200 text-sm focus:outline-none focus:border-indigo-500" placeholder="e.g. Chain">
                </td>
                <td class="py-3 px-2">
                    <input type="number" step="0.001" name="items[\${rowCount}][gross]" id="gross_\${rowCount}" required class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-2 text-slate-200 text-sm text-right font-mono focus:outline-none" placeholder="0.000" oninput="calculateRow(\${rowCount})">
                </td>
                <td class="py-3 px-2">
                    <input type="number" step="0.001" name="items[\${rowCount}][less]" id="less_\${rowCount}" value="0" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-2 text-slate-200 text-sm text-right font-mono focus:outline-none" oninput="calculateRow(\${rowCount})">
                </td>
                <td class="py-3 px-2">
                    <input type="number" step="0.01" name="items[\${rowCount}][milting]" id="milting_\${rowCount}" required class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-2 text-slate-200 text-sm text-right font-mono focus:outline-none" placeholder="0.00" oninput="calculateRow(\${rowCount})">
                </td>
                <td class="py-3 px-2">
                    <input type="number" step="0.01" name="items[\${rowCount}][wastage]" id="wastage_\${rowCount}" value="0" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-2 text-slate-200 text-sm text-right font-mono focus:outline-none" oninput="calculateRow(\${rowCount})">
                </td>
                <td class="py-3 px-2 text-right">
                    <span id="kajfine_\${rowCount}" class="font-mono text-slate-400 text-sm">0.000</span>
                </td>
                <td class="py-3 px-2 text-right">
                    <span id="profit_\${rowCount}" class="font-mono text-slate-400 text-sm">0.000</span>
                </td>
                <td class="py-3 pl-2 text-center">
                    <button type="button" onclick="removeRow(\${rowCount})" class="p-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-white transition-all"><i class="fa-solid fa-trash-can text-xs"></i></button>
                </td>
            `;
            tbody.appendChild(tr);
            rowCount++;
        }
        
        function removeRow(id) {
            var row = document.getElementById('row_' + id);
            row.parentNode.removeChild(row);
            updateTotalFine();
        }
        
        function calculateRow(id) {
            var gross = parseFloat(document.getElementById('gross_' + id).value) || 0;
            var less = parseFloat(document.getElementById('less_' + id).value) || 0;
            var milting = parseFloat(document.getElementById('milting_' + id).value) || 0;
            var wastage = parseFloat(document.getElementById('wastage_' + id).value) || 0;
            
            var net = Math.max(0, gross - less);
            var hisab = milting + wastage;
            
            var kajFine = (net * hisab) / 100;
            var profitFine = (wastage * net) / 100;
            
            document.getElementById('kajfine_' + id).innerText = kajFine.toFixed(3);
            document.getElementById('profit_' + id).innerText = profitFine.toFixed(3);
            
            updateTotalFine();
        }
        
        function updateTotalFine() {
            var total = 0;
            var spans = document.querySelectorAll('[id^="kajfine_"]');
            spans.forEach(function(s) {
                total += parseFloat(s.innerText) || 0;
            });
            document.getElementById('totalFineDisp').innerText = total.toFixed(3) + ' g';
        }
        
        // Add first row on load
        window.onload = function() {
            addItemRow();
        }
    </script>

<?php else: ?>
    <!-- Kaj Entries View -->
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-white flex items-center">
                <span class="gold-text mr-2"><i class="fa-solid fa-hammer"></i></span> Kaj Entries
            </h1>
            <p class="text-slate-400 text-sm mt-1">Record gold ornament manufacturing metrics and wastage earnings.</p>
        </div>
        <a href="kaj.php?action=new" class="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 shadow-md shadow-indigo-600/10 transition-all flex items-center space-x-2">
            <i class="fa-solid fa-plus"></i> <span>Add Kaj Entry</span>
        </a>
    </div>

    <div class="glass-card rounded-2xl border border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-900/40 text-slate-400 text-xs font-bold uppercase tracking-wider border-b border-slate-800">
                        <th class="px-6 py-4.5">Date</th>
                        <th class="px-6 py-4.5">Bapari</th>
                        <th class="px-6 py-4.5 text-right">Labor Bill</th>
                        <th class="px-6 py-4.5 text-right">Kaj Fine (g)</th>
                        <th class="px-6 py-4.5 text-right">Profit Earned (g)</th>
                        <th class="px-6 py-4.5">Remark</th>
                        <th class="px-6 py-4.5 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($kajEntries)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-slate-500 text-sm">
                            <i class="fa-regular fa-face-frown text-3xl mb-2 text-slate-600 block"></i>
                            No Kaj Entries found.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($kajEntries as $k): ?>
                        <tr class="hover:bg-slate-800/25 transition-colors text-sm">
                            <td class="px-6 py-4 font-mono text-slate-300">
                                <?= date('d-m-Y', strtotime($k['date'])) ?>
                            </td>
                            <td class="px-6 py-4 font-semibold text-white">
                                <?= htmlspecialchars($k['bapari_name']) ?>
                            </td>
                            <td class="px-6 py-4 text-right font-mono text-rose-400">
                                <?= $k['cash_bill'] > 0 ? '₹' . number_format($k['cash_bill'], 2) : '--' ?>
                            </td>
                            <td class="px-6 py-4 text-right font-mono text-amber-500 font-semibold">
                                -<?= number_format($k['total_kaj_fine'], 3) ?> g
                            </td>
                            <td class="px-6 py-4 text-right font-mono text-pink-400">
                                +<?= number_format($k['total_profit_fine'], 3) ?> g
                            </td>
                            <td class="px-6 py-4 text-slate-400 max-w-xs truncate">
                                <?= htmlspecialchars($k['remark'] ?: '--') ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <a href="kaj.php?delete=<?= $k['id'] ?>" onclick="return confirm('Are you sure you want to delete this Kaj entry? This will also delete all of its items.')" class="p-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 transition-colors" title="Delete">
                                    <i class="fa-solid fa-trash text-xs"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php
require_once 'footer.php';
?>
