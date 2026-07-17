<?php
require_once 'db.php';

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Handle POST submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isReadOnly) {
        $error = 'View-Only Mode: Administrators cannot modify user data.';
    } elseif (isset($_POST['add_bapari'])) {
        $name = trim($_POST['name'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (empty($name)) {
            $error = 'Name field is required!';
        } else {
            $stmt = $pdo->prepare("INSERT INTO baparis (user_id, name, mobile, address) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $name, $mobile, $address]);
            $success = 'Customer added successfully!';
            $action = 'list';
        }
    } elseif (isset($_POST['edit_bapari'])) {
        $id = intval($_POST['id']);
        $name = trim($_POST['name'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (empty($name)) {
            $error = 'Name field is required!';
        } else {
            $stmt = $pdo->prepare("UPDATE baparis SET name = ?, mobile = ?, address = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$name, $mobile, $address, $id, $userId]);
            $success = 'Customer details updated successfully!';
            $action = 'list';
        }
    }
}

// Handle Delete Bapari
if (isset($_GET['delete'])) {
    if ($isReadOnly) {
        die("Access Denied: View-Only Mode is active.");
    }
    
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM baparis WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    $success = 'Customer deleted successfully!';
    header("Location: baparis.php");
    exit();
}

// Fetch all baparis
$stmt = $pdo->prepare("SELECT * FROM baparis WHERE user_id = ? ORDER BY name ASC");
$stmt->execute([$userId]);
$baparis = $stmt->fetchAll();

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
    <!-- Add Bapari Form -->
    <div class="max-w-xl mx-auto premium-card">
        <h2 class="title-section text-white mb-6 flex items-center">
            <span class="material-symbols-rounded text-[#F4B400] mr-2">person_add</span> Add Customer / Bapari
        </h2>
        
        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Bapari / Customer Name *</label>
                <input type="text" name="name" required <?= $isReadOnly ? 'disabled' : '' ?> class="premium-input" placeholder="e.g. Suman Jewellers">
            </div>
            
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Mobile Number</label>
                <input type="text" name="mobile" <?= $isReadOnly ? 'disabled' : '' ?> class="premium-input" placeholder="e.g. 9876543210">
            </div>
            
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Address</label>
                <input type="text" name="address" <?= $isReadOnly ? 'disabled' : '' ?> class="premium-input" placeholder="e.g. Panvel, Mumbai">
            </div>
            
            <div class="flex items-center justify-end space-x-3 pt-4">
                <a href="baparis.php" class="btn-secondary text-sm px-5 py-2.5">Cancel</a>
                <button type="submit" name="add_bapari" <?= $isReadOnly ? 'disabled' : '' ?> class="btn-gold text-sm px-5 py-2.5 <?= $isReadOnly ? 'opacity-50 cursor-not-allowed' : '' ?>">Save Customer</button>
            </div>
        </form>
    </div>

<?php elseif ($action === 'edit'): 
    $editId = intval($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM baparis WHERE id = ? AND user_id = ?");
    $stmt->execute([$editId, $userId]);
    $bap = $stmt->fetch();
    
    if (!$bap) {
        echo "<p class='text-center py-10 text-slate-400'>Customer not found.</p>";
        require_once 'footer.php';
        exit();
    }
?>
    <!-- Edit Bapari Form -->
    <div class="max-w-xl mx-auto premium-card">
        <h2 class="title-section text-white mb-6 flex items-center">
            <span class="material-symbols-rounded text-[#F4B400] mr-2">edit</span> Edit Customer Details
        </h2>
        
        <form method="POST" class="space-y-5">
            <input type="hidden" name="id" value="<?= $bap['id'] ?>">
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Bapari / Customer Name *</label>
                <input type="text" name="name" value="<?= htmlspecialchars($bap['name']) ?>" required <?= $isReadOnly ? 'disabled' : '' ?> class="premium-input">
            </div>
            
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Mobile Number</label>
                <input type="text" name="mobile" value="<?= htmlspecialchars($bap['mobile']) ?>" <?= $isReadOnly ? 'disabled' : '' ?> class="premium-input">
            </div>
            
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Address</label>
                <input type="text" name="address" value="<?= htmlspecialchars($bap['address']) ?>" <?= $isReadOnly ? 'disabled' : '' ?> class="premium-input">
            </div>
            
            <div class="flex items-center justify-end space-x-3 pt-4">
                <a href="baparis.php" class="btn-secondary text-sm px-5 py-2.5">Cancel</a>
                <button type="submit" name="edit_bapari" <?= $isReadOnly ? 'disabled' : '' ?> class="btn-gold text-sm px-5 py-2.5 <?= $isReadOnly ? 'opacity-50 cursor-not-allowed' : '' ?>">Update Customer</button>
            </div>
        </form>
    </div>

<?php else: ?>
    <!-- Customers List View -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-white flex items-center">
                <span class="material-symbols-rounded text-[#F4B400] mr-2 text-3xl">group</span> Customers / Baparis
            </h1>
        </div>
        <?php if (!$isReadOnly): ?>
            <a href="baparis.php?action=new" class="btn-gold inline-flex items-center text-xs px-3.5 py-2 shadow-md">
                <span class="material-symbols-rounded text-sm mr-1">add</span> Add Customer
            </a>
        <?php endif; ?>
    </div>

    <!-- Responsive List Block -->
    <div class="space-y-4">
        <?php if (empty($baparis)): ?>
            <div class="premium-card text-center py-12 flex flex-col items-center justify-center border-dashed">
                <span class="material-symbols-rounded text-5xl text-slate-600 mb-3">group_off</span>
                <h3 class="text-sm font-semibold text-slate-300">No Customers Added</h3>
                <p class="text-xs text-slate-500 mt-1">Register your first Bapari to start tracking gold/cash balances.</p>
            </div>
        <?php else: ?>
            <?php foreach ($baparis as $b): ?>
                <div class="premium-card bg-[#111111]/85 p-4 flex items-center justify-between">
                    <a href="ledger.php?bapari_id=<?= $b['id'] ?>" class="flex-1 min-w-0 pr-3 select-none">
                        <h3 class="text-sm font-bold text-white leading-tight truncate hover:text-[#d8a735] transition-colors"><?= htmlspecialchars($b['name']) ?></h3>
                        <p class="text-[10px] text-slate-500 mt-0.5 truncate"><?= htmlspecialchars($b['mobile'] ?: 'No mobile number') ?></p>
                        <p class="text-[9px] text-slate-600 truncate"><?= htmlspecialchars($b['address'] ?: 'No address registered') ?></p>
                    </a>
                    
                    <div class="flex items-center space-x-2 shrink-0">
                        <?php if (!$isReadOnly): ?>
                            <a href="baparis.php?action=edit&id=<?= $b['id'] ?>" class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-750 flex items-center justify-center text-slate-300 transition-colors tap-target" title="Edit">
                                <span class="material-symbols-rounded text-base">edit</span>
                            </a>
                            <a href="baparis.php?delete=<?= $b['id'] ?>" onclick="return confirm('Are you sure you want to delete this customer? All their deposits, jobs, and history will be permanently deleted!')" class="w-8 h-8 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 flex items-center justify-center transition-colors tap-target" title="Delete">
                                <span class="material-symbols-rounded text-base">delete</span>
                            </a>
                        <?php endif; ?>
                        
                        <a href="ledger.php?bapari_id=<?= $b['id'] ?>" class="text-slate-500 hover:text-[#d8a735] transition-colors pl-1 shrink-0">
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
