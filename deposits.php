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
            $error = 'Invalid Bapari or Fine Weight!';
        } else {
            // Formula: jamaFine = (fineWeight * purity) / 100
            $jamaFine = round(($fineWeight * $purity) / 100.0, 3);
            
            $stmt = $pdo->prepare("INSERT INTO fine_deposits (user_id, date, bapari_id, fine_weight, purity, jama_fine, cash_received, remark) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $date, $bapariIdInput, $fineWeight, $purity, $jamaFine, $cashReceived, $remark]);
            
            $success = 'Fine Deposit added successfully!';
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
            $error = 'Invalid Bapari or Fine Weight!';
        } else {
            // Formula: jamaFine = (fineWeight * purity) / 100
            $jamaFine = round(($fineWeight * $purity) / 100.0, 3);
            
            $stmt = $pdo->prepare("UPDATE fine_deposits SET date = ?, bapari_id = ?, fine_weight = ?, purity = ?, jama_fine = ?, cash_received = ?, remark = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$date, $bapariIdInput, $fineWeight, $purity, $jamaFine, $cashReceived, $remark, $id, $userId]);
            
            $success = 'Fine Deposit updated successfully!';
            $action = 'list';
        }
    }
}

// Handle Delete Deposit
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM fine_deposits WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    $success = 'Fine Deposit deleted successfully!';
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
    <!-- Add Deposit Form -->
    <div class="max-w-xl mx-auto glass-card rounded-2xl p-6 border border-slate-800">
        <h2 class="text-xl font-bold text-white mb-6 flex items-center">
            <i class="fa-solid fa-circle-down text-emerald-400 mr-2"></i> Add Fine Deposit
        </h2>
        
        <form method="POST" class="space-y-5">
            <div class="grid grid-cols-2 gap-4">
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
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Fine Weight (g) *</label>
                    <input type="number" step="0.001" name="fine_weight" id="fine_weight" required class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-amber-400 transition-colors" placeholder="0.000" oninput="calcFine()">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Purity (%)</label>
                    <input type="number" step="0.01" name="purity" id="purity" value="100" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-amber-400 transition-colors" placeholder="100.00" oninput="calcFine()">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Calculated Jama Fine (g)</label>
                    <input type="text" id="calculated_jama" disabled class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-emerald-400 font-mono" placeholder="0.000">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Cash Received (₹)</label>
                    <input type="number" step="0.01" name="cash_received" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-amber-400 transition-colors" placeholder="0.00">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Remark / Narration</label>
                <input type="text" name="remark" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-amber-400 transition-colors" placeholder="Optional details...">
            </div>
            
            <div class="flex items-center justify-end space-x-3 pt-4">
                <a href="deposits.php" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-slate-400 hover:text-white hover:bg-slate-800 transition-all">Cancel</a>
                <button type="submit" name="add_deposit" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-slate-950 gold-bg hover:opacity-90 shadow-md shadow-amber-500/10 transition-all">Save Deposit</button>
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
        echo "<p class='text-center py-10'>Fine Deposit not found.</p>";
        require_once 'footer.php';
        exit();
    }
?>
    <!-- Edit Deposit Form -->
    <div class="max-w-xl mx-auto glass-card rounded-2xl p-6 border border-slate-800">
        <h2 class="text-xl font-bold text-white mb-6 flex items-center">
            <i class="fa-solid fa-pen-to-square text-amber-400 mr-2"></i> Edit Fine Deposit
        </h2>
        
        <form method="POST" class="space-y-5">
            <input type="hidden" name="id" value="<?= $dep['id'] ?>">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Date *</label>
                    <input type="date" name="date" value="<?= $dep['date'] ?>" required class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-amber-400 transition-colors">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Select Bapari *</label>
                    <select name="bapari_id" required class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-amber-400 transition-colors">
                        <?php foreach ($baparisList as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $dep['bapari_id'] == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Fine Weight (g) *</label>
                    <input type="number" step="0.001" name="fine_weight" id="fine_weight" value="<?= $dep['fine_weight'] ?>" required class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-amber-400 transition-colors" oninput="calcFine()">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Purity (%)</label>
                    <input type="number" step="0.01" name="purity" id="purity" value="<?= $dep['purity'] ?>" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-amber-400 transition-colors" oninput="calcFine()">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Calculated Jama Fine (g)</label>
                    <input type="text" id="calculated_jama" disabled class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-emerald-400 font-mono" value="<?= $dep['jama_fine'] ?> g">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Cash Received (₹)</label>
                    <input type="number" step="0.01" name="cash_received" value="<?= $dep['cash_received'] ?>" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-amber-400 transition-colors">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Remark / Narration</label>
                <input type="text" name="remark" value="<?= htmlspecialchars($dep['remark']) ?>" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-amber-400 transition-colors">
            </div>
            
            <div class="flex items-center justify-end space-x-3 pt-4">
                <a href="deposits.php" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-slate-400 hover:text-white hover:bg-slate-800 transition-all">Cancel</a>
                <button type="submit" name="edit_deposit" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-slate-950 gold-bg hover:opacity-90 shadow-md shadow-amber-500/10 transition-all">Update Deposit</button>
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
    <!-- Deposits Table View -->
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-white flex items-center">
                <span class="gold-text mr-2"><i class="fa-solid fa-circle-down"></i></span> Fine Deposits
            </h1>
            <p class="text-slate-400 text-sm mt-1">Record deposits of gold bars, dust or cash from Baparis.</p>
        </div>
        <a href="deposits.php?action=new" class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-950 gold-bg hover:opacity-90 shadow-md shadow-amber-500/10 transition-all flex items-center space-x-2">
            <i class="fa-solid fa-plus"></i> <span>Add Deposit</span>
        </a>
    </div>

    <div class="glass-card rounded-2xl border border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-900/40 text-slate-400 text-xs font-bold uppercase tracking-wider border-b border-slate-800">
                        <th class="px-6 py-4.5">Date</th>
                        <th class="px-6 py-4.5">Bapari</th>
                        <th class="px-6 py-4.5 text-right">Fine Weight</th>
                        <th class="px-6 py-4.5 text-right">Purity</th>
                        <th class="px-6 py-4.5 text-right">Jama Fine (g)</th>
                        <th class="px-6 py-4.5 text-right">Cash Received</th>
                        <th class="px-6 py-4.5">Remark</th>
                        <th class="px-6 py-4.5 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($deposits)): ?>
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-slate-500 text-sm">
                            <i class="fa-regular fa-face-frown text-3xl mb-2 text-slate-600 block"></i>
                            No deposits recorded yet.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($deposits as $d): ?>
                        <tr class="hover:bg-slate-800/25 transition-colors text-sm">
                            <td class="px-6 py-4 font-mono text-slate-300">
                                <?= date('d-m-Y', strtotime($d['date'])) ?>
                            </td>
                            <td class="px-6 py-4 font-semibold text-white">
                                <?= htmlspecialchars($d['bapari_name']) ?>
                            </td>
                            <td class="px-6 py-4 text-right font-mono text-slate-300">
                                <?= number_format($d['fine_weight'], 3) ?> g
                            </td>
                            <td class="px-6 py-4 text-right font-mono text-slate-400">
                                <?= number_format($d['purity'], 2) ?>%
                            </td>
                            <td class="px-6 py-4 text-right font-mono text-emerald-400 font-semibold">
                                +<?= number_format($d['jama_fine'], 3) ?> g
                            </td>
                            <td class="px-6 py-4 text-right font-mono text-blue-400">
                                <?= $d['cash_received'] > 0 ? '₹' . number_format($d['cash_received'], 2) : '--' ?>
                            </td>
                            <td class="px-6 py-4 text-slate-400 max-w-xs truncate">
                                <?= htmlspecialchars($d['remark'] ?: '--') ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="inline-flex space-x-1">
                                    <a href="deposits.php?action=edit&id=<?= $d['id'] ?>" class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors" title="Edit">
                                        <i class="fa-solid fa-pen text-xs"></i>
                                    </a>
                                    <a href="deposits.php?delete=<?= $d['id'] ?>" onclick="return confirm('Are you sure you want to delete this deposit?')" class="p-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 transition-colors" title="Delete">
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </a>
                                </div>
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
