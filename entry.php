<?php
require_once 'db.php';

$error = '';
$success = '';
$activeTab = $_GET['tab'] ?? 'deposit';

// Fetch Baparis for dropdowns
$stmt = $pdo->prepare("SELECT id, name FROM baparis WHERE user_id = ? ORDER BY name ASC");
$stmt->execute([$userId]);
$baparis = $stmt->fetchAll();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isReadOnly) {
        $error = 'View-Only Mode: Administrators cannot modify user data.';
    } elseif (isset($_POST['submit_deposit'])) {
        $date = $_POST['date'] ?? date('Y-m-d');
        $bapariId = intval($_POST['bapari_id'] ?? 0);
        $fineWeight = floatval($_POST['fine_weight'] ?? 0);
        $purity = floatval($_POST['purity'] ?? 100);
        $cashReceived = floatval($_POST['cash_received'] ?? 0);
        $remark = trim($_POST['remark'] ?? '');

        // Calculate fine gold jama
        $jamaFine = round($fineWeight * ($purity / 100), 3);

        if ($bapariId <= 0 || ($fineWeight <= 0 && $cashReceived <= 0)) {
            $error = 'Please select a Bapari and enter a valid Gold weight or Cash amount!';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO fine_deposits (user_id, date, bapari_id, fine_weight, purity, jama_fine, cash_received, remark) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $date, $bapariId, $fineWeight, $purity, $jamaFine, $cashReceived, $remark]);
            $success = 'Fine Deposit added successfully!';
        }
    } elseif (isset($_POST['submit_kaj'])) {
        $date = $_POST['date'] ?? date('Y-m-d');
        $bapariId = intval($_POST['bapari_id'] ?? 0);
        $cashBill = floatval($_POST['cash_bill'] ?? 0);
        $remark = trim($_POST['remark'] ?? '');

        // Items arrays from POST
        $items = $_POST['items'] ?? [];
        
        if ($bapariId <= 0 || empty($items)) {
            $error = 'Please select a Bapari and add at least one ornament item!';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Calculate totals
                $totalKajFine = 0.0;
                $totalProfitFine = 0.0;
                
                foreach ($items as $it) {
                    $gross = floatval($it['gross'] ?? 0);
                    $less = floatval($it['less'] ?? 0);
                    $net = max(0, $gross - $less);
                    $milting = floatval($it['milting'] ?? 0);
                    $wastage = floatval($it['wastage'] ?? 0);
                    
                    $hisab = $milting + $wastage;
                    $kajFine = round($net * ($hisab / 100), 3);
                    $profitFine = round($net * ($wastage / 100), 3);
                    
                    $totalKajFine += $kajFine;
                    $totalProfitFine += $profitFine;
                }

                // Insert into main table
                $stmt = $pdo->prepare("
                    INSERT INTO kaj_entries (user_id, date, bapari_id, total_kaj_fine, total_profit_fine, cash_bill, remark) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$userId, $date, $bapariId, $totalKajFine, $totalProfitFine, $cashBill, $remark]);
                $kajEntryId = $pdo->lastInsertId();

                // Insert individual items
                $stmtItem = $pdo->prepare("
                    INSERT INTO kaj_items (kaj_entry_id, item, gross, less, net, milting, wastage, hisab, kaj_fine, profit_fine) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($items as $it) {
                    $item = trim($it['name'] ?? 'Ornament');
                    $gross = floatval($it['gross'] ?? 0);
                    $less = floatval($it['less'] ?? 0);
                    $net = max(0, $gross - $less);
                    $milting = floatval($it['milting'] ?? 0);
                    $wastage = floatval($it['wastage'] ?? 0);
                    
                    $hisab = $milting + $wastage;
                    $kajFine = round($net * ($hisab / 100), 3);
                    $profitFine = round($net * ($wastage / 100), 3);
                    
                    $stmtItem->execute([$kajEntryId, $item, $gross, $less, $net, $milting, $wastage, $hisab, $kajFine, $profitFine]);
                }
                
                $pdo->commit();
                $success = 'Kaarigari Job entry recorded successfully!';
                $activeTab = 'kaj';
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Failed saving Kaj entry: ' . $e->getMessage();
            }
        }
    }
}

require_once 'header.php';
?>

<!-- Title Heading -->
<div class="mb-4 mt-2">
    <h1 class="text-3xl font-extrabold tracking-tight text-white flex items-center">
        Entry
    </h1>
</div>

<!-- Tab Switcher (Matching Reference Images) -->
<div class="flex items-center space-x-0 bg-slate-900/60 p-1 rounded-2xl border border-white/[0.04] mb-6">
    <button onclick="switchTab('deposit')" id="tabBtnDeposit" class="flex-1 py-3 text-center text-sm font-bold rounded-xl transition-all tap-target <?= $activeTab == 'deposit' ? 'bg-[#d8a735] text-slate-950 shadow-md' : 'text-slate-400' ?>">
        Fine Deposit
    </button>
    <button onclick="switchTab('kaj')" id="tabBtnKaj" class="flex-1 py-3 text-center text-sm font-bold rounded-xl transition-all tap-target <?= $activeTab == 'kaj' ? 'bg-[#d8a735] text-slate-950 shadow-md' : 'text-slate-400' ?>">
        Kaj Entry
    </button>
</div>

<!-- Feedback Messages -->
<?php if ($error): ?>
    <div class="mb-5 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs flex items-center space-x-2">
        <span class="material-symbols-rounded text-lg">error</span> <span><?= htmlspecialchars($error) ?></span>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="mb-5 p-4 rounded-2xl bg-[#d8a735]/10 border border-[#d8a735]/20 text-[#d8a735] text-xs flex items-center space-x-2">
        <span class="material-symbols-rounded text-lg">check_circle</span> <span><?= htmlspecialchars($success) ?></span>
    </div>
<?php endif; ?>

<?php if ($isReadOnly): ?>
    <div class="mb-5 p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 text-xs flex items-center space-x-2 no-print animate-scale-up">
        <span class="material-symbols-rounded text-lg">info</span>
        <span><strong>View-Only Mode:</strong> Administrators cannot create or modify transactions on this account.</span>
    </div>
<?php endif; ?>

<!-- 1. Fine Deposit Content -->
<div id="tabContentDeposit" class="<?= $activeTab == 'deposit' ? '' : 'hidden' ?>">
    <form method="POST" class="space-y-5">
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Date</label>
            <input type="date" name="date" required value="<?= date('Y-m-d') ?>" <?= $isReadOnly ? 'disabled' : '' ?> class="premium-input text-sm">
        </div>
        
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Select Bapari</label>
            <select name="bapari_id" required <?= $isReadOnly ? 'disabled' : '' ?> class="premium-input text-sm">
                <option value="">Select Bapari</option>
                <?php foreach ($baparis as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Fine Weight (GM)</label>
            <input type="number" step="0.001" id="fineWeight" name="fine_weight" <?= $isReadOnly ? 'disabled' : '' ?> oninput="calcDepositFine()" class="premium-input text-sm" placeholder="0.000">
        </div>
        
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Purity %</label>
            <input type="number" step="0.01" id="purity" name="purity" value="100" <?= $isReadOnly ? 'disabled' : '' ?> oninput="calcDepositFine()" class="premium-input text-sm">
        </div>
        
        <!-- Calculated Jama Fine Display Block (Matching Image 4) -->
        <div class="premium-card bg-transparent border-[#d8a735]/30">
            <span class="text-slate-500 text-[10px] uppercase font-bold block mb-1">Jama Fine</span>
            <div class="text-2xl font-bold text-[#d8a735] font-mono leading-none" id="jamaFineLabel">0.000 g</div>
        </div>
        
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Cash Received (₹)</label>
            <input type="number" step="0.01" name="cash_received" <?= $isReadOnly ? 'disabled' : '' ?> class="premium-input text-sm" placeholder="0">
        </div>
        
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Remark</label>
            <input type="text" name="remark" <?= $isReadOnly ? 'disabled' : '' ?> class="premium-input text-sm" placeholder="Add optional remarks">
        </div>
        
        <button type="submit" name="submit_deposit" <?= $isReadOnly ? 'disabled' : '' ?> class="w-full btn-gold tracking-wide mt-2 <?= $isReadOnly ? 'opacity-50 cursor-not-allowed' : '' ?>">
            ADD FINE ENTRY
        </button>
    </form>
</div>

<!-- 2. Kaj Entry Content -->
<div id="tabContentKaj" class="<?= $activeTab == 'kaj' ? '' : 'hidden' ?>">
    <form method="POST" class="space-y-5">
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Date</label>
            <input type="date" name="date" required value="<?= date('Y-m-d') ?>" <?= $isReadOnly ? 'disabled' : '' ?> class="premium-input text-sm">
        </div>
        
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Select Bapari</label>
            <select name="bapari_id" required <?= $isReadOnly ? 'disabled' : '' ?> class="premium-input text-sm">
                <option value="">Select Bapari</option>
                <?php foreach ($baparis as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Dynamic Items Container -->
        <div id="kajItemsContainer" class="space-y-5">
            <!-- Item Card Block Template (Matching Image 5 Layout) -->
            <div class="premium-card item-card border-slate-800" id="itemBlock_0">
                <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-2">
                    <span class="text-xs font-bold text-[#d8a735] item-header">#1 Item Name</span>
                    <?php if (!$isReadOnly): ?>
                        <button type="button" onclick="removeItemBlock(0)" class="text-slate-500 hover:text-rose-400 text-xs flex items-center justify-center">
                            <span class="material-symbols-rounded text-base">delete</span>
                        </button>
                    <?php endif; ?>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-[9px] font-bold uppercase text-slate-500 mb-1">Item Name</label>
                        <input type="text" name="items[0][name]" required <?= $isReadOnly ? 'disabled' : '' ?> class="premium-input text-sm" placeholder="Enter item name">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3.5">
                        <div>
                            <label class="block text-[9px] font-bold uppercase text-slate-500 mb-1">Gross</label>
                            <input type="number" step="0.001" name="items[0][gross]" id="gross_0" oninput="calcKajItem(0)" <?= $isReadOnly ? 'disabled' : '' ?> class="premium-input text-sm gross-input" placeholder="0.000" required>
                        </div>
                        <div>
                            <label class="block text-[9px] font-bold uppercase text-slate-500 mb-1">Less</label>
                            <input type="number" step="0.001" name="items[0][less]" id="less_0" value="0" oninput="calcKajItem(0)" <?= $isReadOnly ? 'disabled' : '' ?> class="premium-input text-sm less-input" placeholder="0">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3.5">
                        <div>
                            <label class="block text-[9px] font-bold uppercase text-slate-500 mb-1">Milting %</label>
                            <input type="number" step="0.01" name="items[0][milting]" id="milting_0" value="91.80" oninput="calcKajItem(0)" <?= $isReadOnly ? 'disabled' : '' ?> class="premium-input text-sm milting-input">
                        </div>
                        <div>
                            <label class="block text-[9px] font-bold uppercase text-slate-500 mb-1">Wastage %</label>
                            <input type="number" step="0.01" name="items[0][wastage]" id="wastage_0" value="3.50" oninput="calcKajItem(0)" <?= $isReadOnly ? 'disabled' : '' ?> class="premium-input text-sm wastage-input">
                        </div>
                    </div>
                    
                    <!-- Dynamic Output Result Boxes Grid (Matching Image 5) -->
                    <div class="grid grid-cols-2 gap-3 mt-3">
                        <div class="bg-slate-950/60 p-2.5 rounded-xl border border-white/[0.03]">
                            <span class="text-slate-500 text-[8px] uppercase font-bold block">Net</span>
                            <div class="text-sm font-bold text-white font-mono mt-0.5" id="netLabel_0">0.000 g</div>
                        </div>
                        
                        <div class="bg-slate-950/60 p-2.5 rounded-xl border border-white/[0.03]">
                            <span class="text-slate-500 text-[8px] uppercase font-bold block">Hisab %</span>
                            <div class="text-sm font-bold text-white font-mono mt-0.5" id="hisabLabel_0">95.30%</div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div class="premium-card bg-transparent border-[#d8a735]/20 p-2.5">
                            <span class="text-[#d8a735] text-[8px] uppercase font-bold block">Kaj Fine</span>
                            <div class="text-base font-bold text-[#d8a735] font-mono mt-0.5" id="kajFineLabel_0">0.000 g</div>
                        </div>
                        
                        <div class="premium-card bg-transparent border-[#d8a735]/20 p-2.5">
                            <span class="text-[#d8a735] text-[8px] uppercase font-bold block">Profit Fine</span>
                            <div class="text-base font-bold text-[#d8a735] font-mono mt-0.5" id="profitLabel_0">0.000 g</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Add more button with yellow dashed border (Matching Image 5) -->
        <?php if (!$isReadOnly): ?>
            <button type="button" onclick="addItemBlock()" class="w-full py-3.5 rounded-xl border border-dashed border-[#d8a735]/50 text-xs font-bold text-[#d8a735] hover:bg-[#d8a735]/5 transition-colors flex items-center justify-center space-x-1.5 tap-target">
                <span class="material-symbols-rounded text-base">add</span>
                <span>ADD MORE ITEM</span>
            </button>
        <?php endif; ?>
        
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Cash Bill (₹)</label>
            <input type="number" step="0.01" name="cash_bill" <?= $isReadOnly ? 'disabled' : '' ?> class="premium-input text-sm" placeholder="0">
        </div>
        
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Remark</label>
            <input type="text" name="remark" <?= $isReadOnly ? 'disabled' : '' ?> class="premium-input text-sm" placeholder="Add optional remarks">
        </div>
        
        <!-- Total aggregate indicator footer boxes -->
        <div class="grid grid-cols-2 gap-3.5">
            <div class="premium-card bg-transparent border-[#d8a735]/30">
                <span class="text-slate-500 text-[9px] uppercase font-bold block mb-1">Σ Kaj Fine</span>
                <div class="text-lg font-bold text-[#d8a735] font-mono leading-none" id="totalKajFineLabel">0.000 g</div>
            </div>
            
            <div class="premium-card bg-transparent border-[#d8a735]/30">
                <span class="text-slate-500 text-[9px] uppercase font-bold block mb-1">Σ Profit Fine</span>
                <div class="text-lg font-bold text-[#d8a735] font-mono leading-none" id="totalProfitLabel">0.000 g</div>
            </div>
        </div>
        
        <button type="submit" name="submit_kaj" <?= $isReadOnly ? 'disabled' : '' ?> class="w-full btn-gold tracking-wide mt-2 <?= $isReadOnly ? 'opacity-50 cursor-not-allowed' : '' ?>">
            ADD KAJ ENTRY
        </button>
    </form>
</div>

<script>
    var isReadOnlyActive = <?= $isReadOnly ? 'true' : 'false' ?>;

    // Tab switching handler
    function switchTab(tab) {
        const btnDeposit = document.getElementById('tabBtnDeposit');
        const btnKaj = document.getElementById('tabBtnKaj');
        const contentDeposit = document.getElementById('tabContentDeposit');
        const contentKaj = document.getElementById('tabContentKaj');
        
        if (tab === 'deposit') {
            btnDeposit.className = "flex-1 py-3 text-center text-sm font-bold rounded-xl transition-all tap-target bg-[#d8a735] text-slate-950 shadow-md";
            btnKaj.className = "flex-1 py-3 text-center text-sm font-bold rounded-xl transition-all tap-target text-slate-400";
            contentDeposit.classList.remove('hidden');
            contentKaj.classList.add('hidden');
        } else {
            btnKaj.className = "flex-1 py-3 text-center text-sm font-bold rounded-xl transition-all tap-target bg-[#d8a735] text-slate-950 shadow-md";
            btnDeposit.className = "flex-1 py-3 text-center text-sm font-bold rounded-xl transition-all tap-target text-slate-400";
            contentKaj.classList.remove('hidden');
            contentDeposit.classList.add('hidden');
        }
    }

    // Dynamic calculator for deposits
    function calcDepositFine() {
        const wt = parseFloat(document.getElementById('fineWeight').value) || 0;
        const purity = parseFloat(document.getElementById('purity').value) || 0;
        const fine = (wt * (purity / 100)).toFixed(3);
        document.getElementById('jamaFineLabel').textContent = fine + " g";
    }

    // Dynamic multi-item blocks counter for Kaj Job
    let itemBlockCounter = 1;

    function addItemBlock() {
        if (isReadOnlyActive) return;
        const container = document.getElementById('kajItemsContainer');
        const newIndex = itemBlockCounter++;
        
        const div = document.createElement('div');
        div.className = "premium-card item-card border-slate-800 animate-scale-up";
        div.id = `itemBlock_${newIndex}`;
        div.innerHTML = `
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-2">
                <span class="text-xs font-bold text-[#d8a735] item-header">#${newIndex + 1} Item Name</span>
                <button type="button" onclick="removeItemBlock(${newIndex})" class="text-slate-500 hover:text-rose-400 text-xs flex items-center justify-center">
                    <span class="material-symbols-rounded text-base">delete</span>
                </button>
            </div>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-[9px] font-bold uppercase text-slate-500 mb-1">Item Name</label>
                    <input type="text" name="items[${newIndex}][name]" required class="premium-input text-sm" placeholder="Enter item name">
                </div>
                
                <div class="grid grid-cols-2 gap-3.5">
                    <div>
                        <label class="block text-[9px] font-bold uppercase text-slate-500 mb-1">Gross</label>
                        <input type="number" step="0.001" name="items[${newIndex}][gross]" id="gross_${newIndex}" oninput="calcKajItem(${newIndex})" class="premium-input text-sm gross-input" placeholder="0.000" required>
                    </div>
                    <div>
                        <label class="block text-[9px] font-bold uppercase text-slate-500 mb-1">Less</label>
                        <input type="number" step="0.001" name="items[${newIndex}][less]" id="less_${newIndex}" value="0" oninput="calcKajItem(${newIndex})" class="premium-input text-sm less-input" placeholder="0">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-3.5">
                    <div>
                        <label class="block text-[9px] font-bold uppercase text-slate-500 mb-1">Milting %</label>
                        <input type="number" step="0.01" name="items[${newIndex}][milting]" id="milting_${newIndex}" value="91.80" oninput="calcKajItem(${newIndex})" class="premium-input text-sm milting-input">
                    </div>
                    <div>
                        <label class="block text-[9px] font-bold uppercase text-slate-500 mb-1">Wastage %</label>
                        <input type="number" step="0.01" name="items[${newIndex}][wastage]" id="wastage_${newIndex}" value="3.50" oninput="calcKajItem(${newIndex})" class="premium-input text-sm wastage-input">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-3 mt-3">
                    <div class="bg-slate-950/60 p-2.5 rounded-xl border border-white/[0.03]">
                        <span class="text-slate-500 text-[8px] uppercase font-bold block">Net</span>
                        <div class="text-sm font-bold text-white font-mono mt-0.5" id="netLabel_${newIndex}">0.000 g</div>
                    </div>
                    
                    <div class="bg-slate-950/60 p-2.5 rounded-xl border border-white/[0.03]">
                        <span class="text-slate-500 text-[8px] uppercase font-bold block">Hisab %</span>
                        <div class="text-sm font-bold text-white font-mono mt-0.5" id="hisabLabel_${newIndex}">95.30%</div>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-3">
                    <div class="premium-card bg-transparent border-[#d8a735]/20 p-2.5">
                        <span class="text-[#d8a735] text-[8px] uppercase font-bold block">Kaj Fine</span>
                        <div class="text-base font-bold text-[#d8a735] font-mono mt-0.5" id="kajFineLabel_${newIndex}">0.000 g</div>
                    </div>
                    
                    <div class="premium-card bg-transparent border-[#d8a735]/20 p-2.5">
                        <span class="text-[#d8a735] text-[8px] uppercase font-bold block">Profit Fine</span>
                        <div class="text-base font-bold text-[#d8a735] font-mono mt-0.5" id="profitLabel_${newIndex}">0.000 g</div>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(div);
        reindexHeaders();
    }

    function removeItemBlock(index) {
        if (isReadOnlyActive) return;
        const block = document.getElementById(`itemBlock_${index}`);
        if (block) {
            block.remove();
            reindexHeaders();
            calcTotals();
        }
    }

    function reindexHeaders() {
        const blocks = document.querySelectorAll('#kajItemsContainer .item-card');
        blocks.forEach((b, idx) => {
            b.querySelector('.item-header').textContent = `#${idx + 1} Item Name`;
        });
    }

    // Dynamic calculators for individual items
    function calcKajItem(idx) {
        const gross = parseFloat(document.getElementById(`gross_${idx}`).value) || 0;
        const less = parseFloat(document.getElementById(`less_${idx}`).value) || 0;
        const net = Math.max(0, gross - less);
        
        const milting = parseFloat(document.getElementById(`milting_${idx}`).value) || 0;
        const wastage = parseFloat(document.getElementById(`wastage_${idx}`).value) || 0;
        
        const hisab = milting + wastage;
        const kajFine = net * (hisab / 100);
        const profitFine = net * (wastage / 100);
        
        document.getElementById(`netLabel_${idx}`).textContent = net.toFixed(3) + " g";
        document.getElementById(`hisabLabel_${idx}`).textContent = hisab.toFixed(2) + "%";
        document.getElementById(`kajFineLabel_${idx}`).textContent = kajFine.toFixed(3) + " g";
        document.getElementById(`profitLabel_${idx}`).textContent = profitFine.toFixed(3) + " g";
        
        calcTotals();
    }

    // Dynamic totals calculation
    function calcTotals() {
        let grandKajFine = 0;
        let grandProfitFine = 0;
        
        const cards = document.querySelectorAll('#kajItemsContainer .item-card');
        cards.forEach(card => {
            const idxStr = card.id.split('_')[1];
            const gross = parseFloat(document.getElementById(`gross_${idxStr}`).value) || 0;
            const lgVal = document.getElementById(`less_${idxStr}`);
            const less = lgVal ? (parseFloat(lgVal.value) || 0) : 0;
            const net = Math.max(0, gross - less);
            
            const milting = parseFloat(document.getElementById(`milting_${idxStr}`).value) || 0;
            const wastage = parseFloat(document.getElementById(`wastage_${idxStr}`).value) || 0;
            
            const hisab = milting + wastage;
            const kajFine = net * (hisab / 100);
            const profitFine = net * (wastage / 100);
            
            grandKajFine += kajFine;
            grandProfitFine += profitFine;
        });
        
        document.getElementById('totalKajFineLabel').textContent = grandKajFine.toFixed(3) + " g";
        document.getElementById('totalProfitLabel').textContent = grandProfitFine.toFixed(3) + " g";
    }
</script>

<?php
require_once 'footer.php';
?>
