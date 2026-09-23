<?php
require_once 'db.php';

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Handle POST submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isReadOnly) {
        $error = 'View-Only Mode: Administrators cannot modify user data.';
    } elseif (isset($_POST['add_karigor'])) {
        $name = trim($_POST['name'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (empty($name)) {
            $error = 'Karigor Name field is required!';
        } else {
            $stmt = $pdo->prepare("INSERT INTO karigors (user_id, name, mobile, address) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $name, $mobile, $address]);
            $success = 'Karigor added successfully!';
            $action = 'list';
        }
    } elseif (isset($_POST['edit_karigor'])) {
        $id = intval($_POST['id']);
        $name = trim($_POST['name'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (empty($name)) {
            $error = 'Karigor Name field is required!';
        } else {
            $stmt = $pdo->prepare("UPDATE karigors SET name = ?, mobile = ?, address = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$name, $mobile, $address, $id, $userId]);
            $success = 'Karigor details updated successfully!';
            $action = 'list';
        }
    }
}

// Handle Delete Karigor
if (isset($_GET['delete'])) {
    if ($isReadOnly) {
        die("Access Denied: View-Only Mode is active.");
    }
    
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM karigors WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    $success = 'Karigor deleted successfully!';
    header("Location: karigors.php");
    exit();
}

// Fetch all karigors
$stmt = $pdo->prepare("SELECT * FROM karigors WHERE user_id = ? ORDER BY name ASC");
$stmt->execute([$userId]);
$karigors = $stmt->fetchAll();

function getKarigorBalance($pdo, $kid, $userId) {
    $settleStmt = $pdo->prepare("SELECT * FROM karigor_ledger_settlements WHERE karigor_id = ? AND user_id = ? ORDER BY settlement_date DESC, created_at DESC LIMIT 1");
    $settleStmt->execute([$kid, $userId]);
    $settlement = $settleStmt->fetch();
    
    $gold = 0.0;
    $cash = 0.0;
    
    if ($settlement) {
        $gold = floatval($settlement['closing_gold']);
        $cash = floatval($settlement['closing_cash']);
        
        $issStmt = $pdo->prepare("SELECT SUM(issue_fine) as g, SUM(cash_paid) as c FROM karigor_material_issues WHERE karigor_id = ? AND user_id = ? AND (date > ? OR (date = ? AND created_at > ?))");
        $issStmt->execute([$kid, $userId, $settlement['settlement_date'], $settlement['settlement_date'], $settlement['created_at']]);
        $iss = $issStmt->fetch();
        
        $recStmt = $pdo->prepare("SELECT SUM(total_receive_fine) as g, SUM(cash_paid) as c FROM karigor_kaj_receives WHERE karigor_id = ? AND user_id = ? AND (date > ? OR (date = ? AND created_at > ?))");
        $recStmt->execute([$kid, $userId, $settlement['settlement_date'], $settlement['settlement_date'], $settlement['created_at']]);
        $rec = $recStmt->fetch();
    } else {
        $issStmt = $pdo->prepare("SELECT SUM(issue_fine) as g, SUM(cash_paid) as c FROM karigor_material_issues WHERE karigor_id = ? AND user_id = ?");
        $issStmt->execute([$kid, $userId]);
        $iss = $issStmt->fetch();
        
        $recStmt = $pdo->prepare("SELECT SUM(total_receive_fine) as g, SUM(cash_paid) as c FROM karigor_kaj_receives WHERE karigor_id = ? AND user_id = ?");
        $recStmt->execute([$kid, $userId]);
        $rec = $recStmt->fetch();
    }
    
    $issG = $iss['g'] ?? 0;
    $issC = $iss['c'] ?? 0;
    $recG = $rec['g'] ?? 0;
    $recC = $rec['c'] ?? 0;
    
    $gold += ($recG - $issG);
    $cash += ($recC - $issC);
    
    return ['gold' => $gold, 'cash' => $cash];
}

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

<?php if ($isReadOnly): ?>
    <div class="mb-5 p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 text-xs flex items-center space-x-2 no-print">
        <span class="material-symbols-rounded text-lg">info</span>
        <span><strong>View-Only Mode:</strong> Administrators cannot create or modify transactions on this account.</span>
    </div>
<?php endif; ?>

<?php if ($action === 'new'): ?>
    <!-- Add Karigor Form -->
    <div class="max-w-xl mx-auto premium-card">
        <h2 class="title-section text-white mb-6 flex items-center">
            <span class="material-symbols-rounded text-[#F4B400] mr-2">engineering</span> Add Karigor / Artisan
        </h2>
        
        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Karigor Name *</label>
                <input type="text" name="name" required <?= $isReadOnly ? 'disabled' : '' ?> class="premium-input" placeholder="e.g. Ramesh Artisan">
            </div>
            
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Mobile Number</label>
                <input type="text" name="mobile" <?= $isReadOnly ? 'disabled' : '' ?> class="premium-input" placeholder="e.g. 9876543210">
            </div>
            
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Address / Workshop Location</label>
                <input type="text" name="address" <?= $isReadOnly ? 'disabled' : '' ?> class="premium-input" placeholder="e.g. Workshop Unit #4, Zaveri Bazar">
            </div>
            
            <div class="flex items-center justify-end space-x-3 pt-4">
                <a href="karigors.php" class="btn-secondary text-sm px-5 py-2.5">Cancel</a>
                <button type="submit" name="add_karigor" <?= $isReadOnly ? 'disabled' : '' ?> class="btn-gold text-sm px-5 py-2.5 <?= $isReadOnly ? 'opacity-50 cursor-not-allowed' : '' ?>">Save Karigor</button>
            </div>
        </form>
    </div>

<?php elseif ($action === 'edit'): 
    $editId = intval($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM karigors WHERE id = ? AND user_id = ?");
    $stmt->execute([$editId, $userId]);
    $kar = $stmt->fetch();
    
    if (!$kar) {
        echo "<p class='text-center py-10 text-slate-400'>Karigor not found.</p>";
        require_once 'footer.php';
        exit();
    }
?>
    <!-- Edit Karigor Form -->
    <div class="max-w-xl mx-auto premium-card">
        <h2 class="title-section text-white mb-6 flex items-center">
            <span class="material-symbols-rounded text-[#F4B400] mr-2">edit</span> Edit Karigor Details
        </h2>
        
        <form method="POST" class="space-y-5">
            <input type="hidden" name="id" value="<?= $kar['id'] ?>">
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Karigor Name *</label>
                <input type="text" name="name" value="<?= htmlspecialchars($kar['name']) ?>" required <?= $isReadOnly ? 'disabled' : '' ?> class="premium-input">
            </div>
            
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Mobile Number</label>
                <input type="text" name="mobile" value="<?= htmlspecialchars($kar['mobile']) ?>" <?= $isReadOnly ? 'disabled' : '' ?> class="premium-input">
            </div>
            
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Address / Workshop Location</label>
                <input type="text" name="address" value="<?= htmlspecialchars($kar['address']) ?>" <?= $isReadOnly ? 'disabled' : '' ?> class="premium-input">
            </div>
            
            <div class="flex items-center justify-end space-x-3 pt-4">
                <a href="karigors.php" class="btn-secondary text-sm px-5 py-2.5">Cancel</a>
                <button type="submit" name="edit_karigor" <?= $isReadOnly ? 'disabled' : '' ?> class="btn-gold text-sm px-5 py-2.5 <?= $isReadOnly ? 'opacity-50 cursor-not-allowed' : '' ?>">Update Karigor</button>
            </div>
        </form>
    </div>

<?php else: ?>
    <!-- Karigors List View -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-white flex items-center">
                <span class="material-symbols-rounded text-[#F4B400] mr-2 text-3xl">engineering</span> Karigors / Artisans
            </h1>
        </div>
        <?php if (!$isReadOnly): ?>
            <a href="karigors.php?action=new" class="btn-gold inline-flex items-center text-xs px-3.5 py-2 shadow-md">
                <span class="material-symbols-rounded text-sm mr-1">add</span> Add Karigor
            </a>
        <?php endif; ?>
    </div>

    <!-- Responsive List Block -->
    <div class="space-y-4">
        <?php if (empty($karigors)): ?>
            <div class="premium-card text-center py-12 flex flex-col items-center justify-center border-dashed">
                <span class="material-symbols-rounded text-5xl text-slate-600 mb-3">engineering</span>
                <h3 class="text-sm font-semibold text-slate-300">No Karigors Added</h3>
                <p class="text-xs text-slate-500 mt-1">Register your first Karigor / Artisan to start tracking material issues and receipts.</p>
            </div>
        <?php else: ?>
            <?php foreach ($karigors as $k): 
                $bal = getKarigorBalance($pdo, $k['id'], $userId);
                $goldCr = $bal['gold'] >= 0;
                $cashCr = $bal['cash'] >= 0;
            ?>
                <div class="premium-card bg-[#111111]/85 p-4 flex items-center justify-between">
                    <a href="karigor_ledger.php?karigor_id=<?= $k['id'] ?>" class="flex-1 min-w-0 pr-3 select-none">
                        <h3 class="text-sm font-bold text-white leading-tight truncate hover:text-[#d8a735] transition-colors"><?= htmlspecialchars($k['name']) ?></h3>
                        <div class="flex items-center space-x-3 mt-1.5 mb-1">
                            <span class="text-[10px] font-mono <?= $goldCr ? 'text-emerald-400' : 'text-rose-400' ?>">
                                G: <?= $goldCr ? '+' : '-' ?><?= number_format(abs($bal['gold']), 3) ?>
                            </span>
                            <span class="text-[10px] font-mono <?= $cashCr ? 'text-emerald-400' : 'text-rose-400' ?>">
                                ₹ <?= $cashCr ? '+' : '-' ?><?= number_format(abs($bal['cash']), 0) ?>
                            </span>
                        </div>
                        <p class="text-[10px] text-slate-500 truncate"><?= htmlspecialchars($k['mobile'] ?: 'No mobile') ?></p>
                    </a>
                    
                    <div class="flex items-center space-x-2 shrink-0">
                        <?php if (!$isReadOnly): ?>
                            <a href="karigors.php?action=edit&id=<?= $k['id'] ?>" class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-750 flex items-center justify-center text-slate-300 transition-colors tap-target" title="Edit">
                                <span class="material-symbols-rounded text-base">edit</span>
                            </a>
                            <a href="karigors.php?delete=<?= $k['id'] ?>" onclick="return confirm('Are you sure you want to delete this Karigor? All material issues, receives, and history will be permanently deleted!')" class="w-8 h-8 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 flex items-center justify-center transition-colors tap-target" title="Delete">
                                <span class="material-symbols-rounded text-base">delete</span>
                            </a>
                        <?php endif; ?>
                        
                        <a href="karigor_ledger.php?karigor_id=<?= $k['id'] ?>" class="text-slate-500 hover:text-[#d8a735] transition-colors pl-1 shrink-0">
                            <span class="material-symbols-rounded text-lg">chevron_right</span>
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
