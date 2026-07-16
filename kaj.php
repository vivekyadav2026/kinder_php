<?php
require_once 'db.php';

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';
$warning = '';

// Helper function to check if a transaction date falls into a settled period
function isSettled($pdo, $bapariId, $userId, $date) {
    $stmt = $pdo->prepare("SELECT MAX(settlement_date) as last_settle FROM ledger_settlements WHERE bapari_id = ? AND user_id = ?");
    $stmt->execute([$bapariId, $userId]);
    $lastSettle = $stmt->fetch()['last_settle'];
    return ($lastSettle && $date <= $lastSettle);
}

// Handle POST submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_kaj']) || isset($_POST['edit_kaj'])) {
        $date = $_POST['date'] ?? date('Y-m-d');
        $bapariIdInput = intval($_POST['bapari_id']);
        $cashBill = floatval($_POST['cash_bill'] ?? 0.0);
        $remark = trim($_POST['remark'] ?? '');
        $items = $_POST['items'] ?? []; // Array of items

        if (isset($_POST['edit_kaj'])) {
            $kajEntryId = intval($_POST['id']);
            $stmt = $pdo->prepare("SELECT bapari_id, date FROM kaj_entries WHERE id = ? AND user_id = ?");
            $stmt->execute([$kajEntryId, $userId]);
            $origTxn = $stmt->fetch();
            
            if (!$origTxn) {
                $error = 'Transaction not found!';
            } elseif (isSettled($pdo, $origTxn['bapari_id'], $userId, $origTxn['date']) || isSettled($pdo, $bapariIdInput, $userId, $date)) {
                if (!$isAdmin) {
                    $error = 'Access Denied: You are not authorized to edit jobs in a settled period.';
                }
            }
        } else {
            if (isSettled($pdo, $bapariIdInput, $userId, $date)) {
                if (!$isAdmin) {
                    $error = 'Access Denied: You cannot add job transactions to a settled period.';
                }
            }
        }

        if ($bapariIdInput <= 0 || empty($items)) {
            $error = 'Invalid Customer or items list is empty!';
        }

        if (empty($error)) {
            try {
                $pdo->beginTransaction();
                
                $totalKajFine = 0.0;
                $totalProfitFine = 0.0;
                
                if (isset($_POST['edit_kaj'])) {
                    // Update existing
                    $stmt = $pdo->prepare("UPDATE kaj_entries SET date = ?, bapari_id = ?, cash_bill = ?, remark = ? WHERE id = ? AND user_id = ?");
                    $stmt->execute([$date, $bapariIdInput, $cashBill, $remark, $kajEntryId, $userId]);
                    
                    // Delete previous items
                    $stmtDel = $pdo->prepare("DELETE FROM kaj_items WHERE kaj_entry_id = ?");
                    $stmtDel->execute([$kajEntryId]);
                } else {
                    // Insert new
                    $stmt = $pdo->prepare("INSERT INTO kaj_entries (user_id, date, bapari_id, cash_bill, remark) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$userId, $date, $bapariIdInput, $cashBill, $remark]);
                    $kajEntryId = $pdo->lastInsertId();
                }
                
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
                $success = 'Kaarigari Job saved successfully!';
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
    
    $stmt = $pdo->prepare("SELECT bapari_id, date FROM kaj_entries WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    $origTxn = $stmt->fetch();
    
    if ($origTxn) {
        if (isSettled($pdo, $origTxn['bapari_id'], $userId, $origTxn['date'])) {
            if (!$isAdmin) {
                die("Access Denied: This transaction is settled and cannot be deleted by non-administrators.");
            }
        }
        $stmt = $pdo->prepare("DELETE FROM kaj_entries WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $success = 'Kaarigari Job deleted successfully!';
    }
    header("Location: kaj.php");
    exit();
}

$editEntry = null;
$editItems = [];
if ($action === 'edit') {
    $editId = intval($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM kaj_entries WHERE id = ? AND user_id = ?");
    $stmt->execute([$editId, $userId]);
    $editEntry = $stmt->fetch();
    
    if (!$editEntry) {
        $error = 'Entry not found!';
        $action = 'list';
    } else {
        $stmtItems = $pdo->prepare("SELECT * FROM kaj_items WHERE kaj_entry_id = ?");
        $stmtItems->execute([$editId]);
        $editItems = $stmtItems->fetchAll();
    }
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
    <div class="mb-5 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs flex items-center space-x-2">
        <span class="material-symbols-rounded text-lg">error</span> <span><?= htmlspecialchars($error) ?></span>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="mb-5 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs flex items-center space-x-2">
        <span class="material-symbols-rounded text-lg">check_circle</span> <span><?= htmlspecialchars($success) ?></span>
    </div>
<?php endif; ?>

<?php if ($action === 'new' || $action === 'edit'): 
    $blockForm = false;
    if ($action === 'edit' && $editEntry) {
        $isTxnSettled = isSettled($pdo, $editEntry['bapari_id'], $userId, $editEntry['date']);
        $blockForm = ($isTxnSettled && !$isAdmin);
        
        if ($isTxnSettled) {
            if ($blockForm) {
                $error = 'Access Denied: This transaction is settled and cannot be edited by non-administrators.';
            } else {
                $warning = '⚠️ WARNING: This transaction belongs to a settled period. Editing it will shift settled balances.';
            }
        }
    }
?>
    <!-- Add/Edit Kaj Entry Form -->
    <div class="premium-card">
        <h2 class="title-section text-white mb-6 flex items-center">
            <span class="material-symbols-rounded text-[#F4B400] mr-2">construction</span> <?= $action === 'edit' ? 'Edit' : 'Add' ?> Kaarigari Job Entry
        </h2>
        
        <!-- Error & Warning blocks -->
        <?php if ($error): ?>
            <div class="mb-5 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if ($warning): ?>
            <div class="mb-5 p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 text-xs">
                <?= htmlspecialchars($warning) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="kajForm" class="space-y-5">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="id" value="<?= $editEntry['id'] ?>">
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Date *</label>
                    <input type="date" name="date" value="<?= $action === 'edit' ? $editEntry['date'] : date('Y-m-d') ?>" required <?= $blockForm ? 'disabled' : '' ?> class="premium-input">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Select Customer *</label>
                    <select name="bapari_id" required <?= $blockForm ? 'disabled' : '' ?> class="premium-input">
                        <option value="">-- Choose --</option>
                        <?php foreach ($baparisList as $b): 
                            $selected = ($action === 'edit' && intval($editEntry['bapari_id']) === intval($b['id'])) ? 'selected' : '';
                        ?>
                            <option value="<?= $b['id'] ?>" <?= $selected ?>><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Majdoori Bill / Labor Charge (₹)</label>
                    <input type="number" step="0.01" name="cash_bill" value="<?= $action === 'edit' ? $editEntry['cash_bill'] : '' ?>" <?= $blockForm ? 'disabled' : '' ?> class="premium-input" placeholder="0.00">
                </div>
            </div>
            
            <!-- Items Area -->
            <div class="mt-4">
                <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-800">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Items List</h3>
                    <?php if (!$blockForm): ?>
                        <button type="button" onclick="addItemRow()" class="px-3 py-1.5 rounded-lg bg-indigo-600/10 text-indigo-400 hover:bg-indigo-600 hover:text-white transition-all text-[11px] font-bold flex items-center space-x-1">
                            <span class="material-symbols-rounded text-sm">add</span> <span>Add Item</span>
                        </button>
                    <?php endif; ?>
                </div>
                
                <div id="itemRows" class="space-y-4">
                    <!-- Dynamic rows injected here -->
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Remark / Narration</label>
                <input type="text" name="remark" value="<?= $action === 'edit' ? htmlspecialchars($editEntry['remark']) : '' ?>" <?= $blockForm ? 'disabled' : '' ?> class="premium-input" placeholder="Optional details...">
            </div>
            
            <div class="flex items-center justify-between pt-4 border-t border-slate-800">
                <div class="text-xs font-mono text-slate-400">
                    Est. Total Gold: <span id="totalFineDisp" class="text-rose-400 font-bold">0.000 g</span>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="kaj.php" class="btn-secondary text-sm px-5 py-2.5">Cancel</a>
                    <?php if (!$blockForm): ?>
                        <button type="submit" name="<?= $action === 'edit' ? 'edit_kaj' : 'add_kaj' ?>" class="btn-gold text-sm px-5 py-2.5">Save Job</button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <script>
        var rowCount = 0;
        var formBlocked = <?= $blockForm ? 'true' : 'false' ?>;
        
        function addItemRow(item = '', gross = '', less = '0', milting = '', wastage = '0') {
            var container = document.getElementById('itemRows');
            var div = document.createElement('div');
            div.id = 'row_' + rowCount;
            div.className = 'premium-card bg-slate-900/50 p-4 border border-slate-850 space-y-3';
            
            var deleteBtn = formBlocked ? '' : `<button type="button" onclick="removeRow(\${rowCount})" class="text-rose-400 hover:text-rose-500 tap-target flex items-center justify-center"><span class="material-symbols-rounded text-lg">delete</span></button>`;
            
            div.innerHTML = `
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold text-slate-500 uppercase">Item #\${rowCount + 1}</span>
                    \${deleteBtn}
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <input type="text" name="items[\${rowCount}][item]" value="\${item}" required \${formBlocked ? 'disabled' : ''} class="premium-input" placeholder="Ornament Name (e.g. Chain)">
                    </div>
                    <div>
                        <label class="block text-[9px] uppercase text-slate-500 mb-1">Gross (g)</label>
                        <input type="number" step="0.001" name="items[\${rowCount}][gross]" id="gross_\${rowCount}" value="\${gross}" required \${formBlocked ? 'disabled' : ''} class="premium-input text-right font-mono" placeholder="0.000" oninput="calculateRow(\${rowCount})">
                    </div>
                    <div>
                        <label class="block text-[9px] uppercase text-slate-500 mb-1">Less (g)</label>
                        <input type="number" step="0.001" name="items[\${rowCount}][less]" id="less_\${rowCount}" value="\${less}" \${formBlocked ? 'disabled' : ''} class="premium-input text-right font-mono" oninput="calculateRow(\${rowCount})">
                    </div>
                    <div>
                        <label class="block text-[9px] uppercase text-slate-500 mb-1">Mel / Purity (%)</label>
                        <input type="number" step="0.01" name="items[\${rowCount}][milting]" id="milting_\${rowCount}" value="\${milting}" required \${formBlocked ? 'disabled' : ''} class="premium-input text-right font-mono" placeholder="0.00" oninput="calculateRow(\${rowCount})">
                    </div>
                    <div>
                        <label class="block text-[9px] uppercase text-slate-500 mb-1">Chhij / Wastage (%)</label>
                        <input type="number" step="0.01" name="items[\${rowCount}][wastage]" id="wastage_\${rowCount}" value="\${wastage}" \${formBlocked ? 'disabled' : ''} class="premium-input text-right font-mono" oninput="calculateRow(\${rowCount})">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-3 pt-2 text-[11px] font-mono border-t border-slate-800/40 text-slate-400">
                    <div>Gold Billed: <span id="kajfine_\${rowCount}" class="font-bold text-white">0.000</span>g</div>
                    <div>Profit Gold: <span id="profit_\${rowCount}" class="font-bold text-white">0.000</span>g</div>
                </div>
            `;
            container.appendChild(div);
            calculateRow(rowCount);
            rowCount++;
        }
        
        function removeRow(id) {
            var row = document.getElementById('row_' + id);
            row.parentNode.removeChild(row);
            updateTotalFine();
        }
        
        function calculateRow(id) {
            var grossEl = document.getElementById('gross_' + id);
            if (!grossEl) return;
            var gross = parseFloat(grossEl.value) || 0;
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
        
        // Add default rows on load
        window.onload = function() {
            <?php if ($action === 'edit' && !empty($editItems)): ?>
                <?php foreach ($editItems as $it): ?>
                    addItemRow(
                        "<?= htmlspecialchars($it['item']) ?>",
                        "<?= floatval($it['gross']) ?>",
                        "<?= floatval($it['less']) ?>",
                        "<?= floatval($it['milting']) ?>",
                        "<?= floatval($it['wastage']) ?>"
                    );
                <?php endforeach; ?>
            <?php else: ?>
                addItemRow();
            <?php endif; ?>
        }
    </script>

<?php else: ?>
    <!-- Standard list view fallback -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-white flex items-center">
                <span class="material-symbols-rounded text-[#F4B400] mr-2 text-3xl">construction</span> Kaarigari Jobs
            </h1>
            <p class="text-slate-400 text-xs mt-1">Logs of jewelry manufacturing and metal wastage calculations.</p>
        </div>
        <a href="kaj.php?action=new" class="btn-gold inline-flex items-center text-xs px-3.5 py-2 shadow-md">
            <span class="material-symbols-rounded text-sm mr-1">add</span> Add Job
        </a>
    </div>

    <!-- Redesigned Jobs Mobile Cards Stack -->
    <div class="space-y-4">
        <?php if (empty($kajEntries)): ?>
            <div class="premium-card text-center py-12 flex flex-col items-center justify-center">
                <span class="material-symbols-rounded text-5xl text-slate-600 mb-3">folder_open</span>
                <h3 class="text-sm font-semibold text-slate-300">No Job Work Found</h3>
                <p class="text-xs text-slate-500 mt-1">Record a new Kaarigari Job to calculate metal weight outcomes.</p>
            </div>
        <?php else: ?>
            <?php foreach ($kajEntries as $k): ?>
                <div class="premium-card">
                    <div class="flex items-start justify-between border-b border-slate-800/80 pb-3 mb-3">
                        <div>
                            <span class="text-[10px] text-slate-500 font-semibold font-mono"><?= date('d-M-Y', strtotime($k['date'])) ?></span>
                            <h3 class="font-bold text-white text-base mt-0.5"><?= htmlspecialchars($k['bapari_name']) ?></h3>
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-slate-500 block">Majdoori Bill</span>
                            <span class="font-mono text-rose-400 font-bold text-sm"><?= $k['cash_bill'] > 0 ? '₹' . number_format($k['cash_bill'], 2) : '--' ?></span>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="text-[10px] text-slate-500 uppercase block font-semibold">Gold Billed</span>
                            <span class="font-mono font-bold text-rose-400 text-base">-<?= number_format($k['total_kaj_fine'], 3) ?> g</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-500 uppercase block font-semibold">Profit Gold</span>
                            <span class="font-mono font-bold text-pink-400 text-base">+<?= number_format($k['total_profit_fine'], 3) ?> g</span>
                        </div>
                    </div>

                    <?php if ($k['remark']): ?>
                        <div class="bg-slate-900/50 p-2.5 rounded-xl border border-slate-800/80 text-[11px] text-slate-400 mt-3 flex items-start space-x-1">
                            <span class="material-symbols-rounded text-sm text-slate-500 mt-0.5">sticky_note</span>
                            <span class="truncate"><?= htmlspecialchars($k['remark']) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="flex items-center justify-end space-x-2 mt-4 pt-3 border-t border-slate-800/40">
                        <a href="kaj.php?action=edit&id=<?= $k['id'] ?>" class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 flex items-center justify-center text-slate-300 transition-colors tap-target" title="Edit">
                            <span class="material-symbols-rounded text-base">edit</span>
                        </a>
                        <a href="kaj.php?delete=<?= $k['id'] ?>" onclick="return confirm('Are you sure you want to delete this Kaarigari Job entry? This will also delete all of its items.')" class="w-8 h-8 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 flex items-center justify-center transition-colors tap-target" title="Delete">
                            <span class="material-symbols-rounded text-base">delete</span>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
require_once 'footer.php';
?>
