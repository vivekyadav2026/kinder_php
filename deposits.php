<?php
require_once 'db.php';

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Handle POST submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_deposit'])) {
        $date = $_POST['date'] ?? date('Y-m-d');
        $bapariIdInput = intval($_POST['bapari_id']);
        $fineWeight = floatval($_POST['fine_weight']);
        $purity = floatval($_POST['purity'] ?? 100.0);
        $cashReceived = floatval($_POST['cash_received'] ?? 0.0);
        $remark = trim($_POST['remark'] ?? '');

        if ($bapariIdInput <= 0 || $fineWeight < 0) {
            $error = 'Invalid Customer or Gold Weight!';
        } else {
            // Formula: jamaFine = (fineWeight * purity) / 100
            $jamaFine = round(($fineWeight * $purity) / 100.0, 3);
            
            $stmt = $pdo->prepare("INSERT INTO fine_deposits (user_id, date, bapari_id, fine_weight, purity, jama_fine, cash_received, remark) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $date, $bapariIdInput, $fineWeight, $purity, $jamaFine, $cashReceived, $remark]);
            
            $success = 'Gold Jama added successfully!';
            $action = 'list';
        }
    } elseif (isset($_POST['edit_deposit'])) {
        $id = intval($_POST['id']);
        $date = $_POST['date'] ?? date('Y-m-d');
        $bapariIdInput = intval($_POST['bapari_id']);
        $fineWeight = floatval($_POST['fine_weight']);
        $purity = floatval($_POST['purity'] ?? 100.0);
        $cashReceived = floatval($_POST['cash_received'] ?? 0.0);
        $remark = trim($_POST['remark'] ?? '');

        if ($bapariIdInput <= 0 || $fineWeight < 0) {
            $error = 'Invalid Customer or Gold Weight!';
        } else {
            // Formula: jamaFine = (fineWeight * purity) / 100
            $jamaFine = round(($fineWeight * $purity) / 100.0, 3);
            
            $stmt = $pdo->prepare("UPDATE fine_deposits SET date = ?, bapari_id = ?, fine_weight = ?, purity = ?, jama_fine = ?, cash_received = ?, remark = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$date, $bapariIdInput, $fineWeight, $purity, $jamaFine, $cashReceived, $remark, $id, $userId]);
            
            $success = 'Gold Jama updated successfully!';
            $action = 'list';
        }
    }
}

// Handle Delete Deposit
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM fine_deposits WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    $success = 'Gold Jama deleted successfully!';
    header("Location: deposits.php");
    exit();
}

// Fetch all deposits joined with Baparis
$stmt = $pdo->prepare("
    SELECT fd.*, b.name as bapari_name 
    FROM fine_deposits fd 
    JOIN baparis b ON fd.bapari_id = b.id 
    WHERE fd.user_id = ? 
    ORDER BY fd.date DESC, fd.id DESC
");
$stmt->execute([$userId]);
$deposits = $stmt->fetchAll();

// Fetch baparis for selectors
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

<?php if ($action === 'new'): ?>
    <!-- Add Deposit Form -->
    <div class="max-w-xl mx-auto premium-card">
        <h2 class="title-section text-white mb-6 flex items-center">
            <span class="material-symbols-rounded text-[#F4B400] mr-2">arrow_downward</span> Add Gold Jama
        </h2>
        
        <form method="POST" class="space-y-5">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Date *</label>
                    <input type="date" name="date" value="<?= date('Y-m-d') ?>" required class="premium-input">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Select Customer *</label>
                    <select name="bapari_id" required class="premium-input">
                        <option value="">-- Choose --</option>
                        <?php foreach ($baparisList as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Gold Weight (g) *</label>
                    <input type="number" step="0.001" name="fine_weight" id="fine_weight" required class="premium-input" placeholder="0.000" oninput="calcFine()">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Purity / Mel (%)</label>
                    <input type="number" step="0.01" name="purity" id="purity" value="100" class="premium-input" placeholder="100.00" oninput="calcFine()">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Calculated Gold Jama (g)</label>
                    <input type="text" id="calculated_jama" disabled class="premium-input bg-slate-900 border-none font-mono text-emerald-400" placeholder="0.000">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Cash Received (₹)</label>
                    <input type="number" step="0.01" name="cash_received" class="premium-input" placeholder="0.00">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Remark / Narration</label>
                <input type="text" name="remark" class="premium-input" placeholder="Optional details...">
            </div>
            
            <div class="flex items-center justify-end space-x-3 pt-4">
                <a href="deposits.php" class="btn-secondary text-sm px-5 py-2.5">Cancel</a>
                <button type="submit" name="add_deposit" class="btn-gold text-sm px-5 py-2.5">Save Gold Jama</button>
            </div>
        </form>
    </div>

    <script>
        function calcFine() {
            var w = parseFloat(document.getElementById('fine_weight').value) || 0;
            var p = parseFloat(document.getElementById('purity').value) || 0;
            var jama = (w * p) / 100;
            document.getElementById('calculated_jama').value = jama.toFixed(3) + ' g';
        }
    </script>

<?php elseif ($action === 'edit'): 
    $editId = intval($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM fine_deposits WHERE id = ? AND user_id = ?");
    $stmt->execute([$editId, $userId]);
    $dep = $stmt->fetch();
    if (!$dep) {
        echo "<p class='text-center py-10 text-slate-400'>Gold Jama entry not found.</p>";
        require_once 'footer.php';
        exit();
    }
?>
    <!-- Edit Deposit Form -->
    <div class="max-w-xl mx-auto premium-card">
        <h2 class="title-section text-white mb-6 flex items-center">
            <span class="material-symbols-rounded text-[#F4B400] mr-2">edit_note</span> Edit Gold Jama
        </h2>
        
        <form method="POST" class="space-y-5">
            <input type="hidden" name="id" value="<?= $dep['id'] ?>">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Date *</label>
                    <input type="date" name="date" value="<?= $dep['date'] ?>" required class="premium-input">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Select Customer *</label>
                    <select name="bapari_id" required class="premium-input">
                        <?php foreach ($baparisList as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $dep['bapari_id'] == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Gold Weight (g) *</label>
                    <input type="number" step="0.001" name="fine_weight" id="fine_weight" value="<?= $dep['fine_weight'] ?>" required class="premium-input" oninput="calcFine()">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Purity / Mel (%)</label>
                    <input type="number" step="0.01" name="purity" id="purity" value="<?= $dep['purity'] ?>" class="premium-input" oninput="calcFine()">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Calculated Gold Jama (g)</label>
                    <input type="text" id="calculated_jama" disabled class="premium-input bg-slate-900 border-none font-mono text-emerald-400" value="<?= $dep['jama_fine'] ?> g">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Cash Received (₹)</label>
                    <input type="number" step="0.01" name="cash_received" value="<?= $dep['cash_received'] ?>" class="premium-input">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Remark / Narration</label>
                <input type="text" name="remark" value="<?= htmlspecialchars($dep['remark']) ?>" class="premium-input">
            </div>
            
            <div class="flex items-center justify-end space-x-3 pt-4">
                <a href="deposits.php" class="btn-secondary text-sm px-5 py-2.5">Cancel</a>
                <button type="submit" name="edit_deposit" class="btn-gold text-sm px-5 py-2.5">Update Gold Jama</button>
            </div>
        </form>
    </div>

    <script>
        function calcFine() {
            var w = parseFloat(document.getElementById('fine_weight').value) || 0;
            var p = parseFloat(document.getElementById('purity').value) || 0;
            var jama = (w * p) / 100;
            document.getElementById('calculated_jama').value = jama.toFixed(3) + ' g';
        }
    </script>

<?php else: ?>
    <!-- Deposits Card View -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-white flex items-center">
                <span class="material-symbols-rounded text-[#F4B400] mr-2 text-3xl">arrow_downward</span> Gold Jama
            </h1>
            <p class="text-slate-400 text-xs mt-1">Logs of gold deposits and cash payments received.</p>
        </div>
        <a href="deposits.php?action=new" class="btn-gold inline-flex items-center text-xs px-3.5 py-2 shadow-md">
            <span class="material-symbols-rounded text-sm mr-1">add</span> Add Jama
        </a>
    </div>

    <div class="space-y-4">
        <?php if (empty($deposits)): ?>
            <div class="premium-card text-center py-12 flex flex-col items-center justify-center">
                <span class="material-symbols-rounded text-5xl text-slate-600 mb-3">inbox</span>
                <h3 class="text-sm font-semibold text-slate-300">No Gold Jama Found</h3>
                <p class="text-xs text-slate-500 mt-1">Add deposit transactions to credit your client accounts.</p>
            </div>
        <?php else: ?>
            <?php foreach ($deposits as $d): ?>
                <div class="premium-card flex flex-col justify-between">
                    <div class="flex items-start justify-between border-b border-slate-800/80 pb-3 mb-3">
                        <div>
                            <span class="text-[10px] text-slate-500 font-semibold font-mono"><?= date('d-M-Y', strtotime($d['date'])) ?></span>
                            <h3 class="font-bold text-white text-base mt-0.5"><?= htmlspecialchars($d['bapari_name']) ?></h3>
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-slate-500 block">Gold Weight</span>
                            <span class="font-mono text-slate-300 font-semibold text-xs"><?= number_format($d['fine_weight'], 3) ?>g @ <?= number_format($d['purity'], 1) ?>%</span>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 my-1">
                        <div>
                            <span class="text-[10px] text-slate-500 uppercase block font-semibold">Gold Jama</span>
                            <span class="font-mono font-bold text-emerald-400 text-lg">+<?= number_format($d['jama_fine'], 3) ?> g</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-500 uppercase block font-semibold">Cash Jama</span>
                            <span class="font-mono font-bold text-blue-400 text-lg"><?= $d['cash_received'] > 0 ? '₹' . number_format($d['cash_received'], 2) : '--' ?></span>
                        </div>
                    </div>

                    <?php if ($d['remark']): ?>
                        <div class="bg-slate-900/50 p-2.5 rounded-xl border border-slate-800/80 text-[11px] text-slate-400 mt-3 flex items-start space-x-1">
                            <span class="material-symbols-rounded text-sm text-slate-500 mt-0.5">sticky_note</span>
                            <span class="truncate"><?= htmlspecialchars($d['remark']) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="flex items-center justify-end space-x-2 mt-4 pt-3 border-t border-slate-800/40">
                        <a href="deposits.php?action=edit&id=<?= $d['id'] ?>" class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 flex items-center justify-center text-slate-300 transition-colors tap-target" title="Edit">
                            <span class="material-symbols-rounded text-base">edit</span>
                        </a>
                        <a href="deposits.php?delete=<?= $d['id'] ?>" onclick="return confirm('Are you sure you want to delete this deposit entry?')" class="w-8 h-8 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 flex items-center justify-center transition-colors tap-target" title="Delete">
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
