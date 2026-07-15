<?php
require_once 'db.php';

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Handle POST submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_bapari'])) {
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

<?php if ($action === 'new'): ?>
    <!-- Add Bapari Form -->
    <div class="max-w-xl mx-auto premium-card">
        <h2 class="title-section text-white mb-6 flex items-center">
            <span class="material-symbols-rounded text-[#F4B400] mr-2">person_add</span> Add New Customer
        </h2>
        
        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Customer Name *</label>
                <input type="text" name="name" required class="premium-input" placeholder="e.g. Suman Das">
            </div>
            
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Mobile / Contact Number</label>
                <input type="text" name="mobile" class="premium-input" placeholder="e.g. +91 98765 43210">
            </div>
            
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">City / Business Address</label>
                <input type="text" name="address" class="premium-input" placeholder="e.g. Zaveri Bazaar, Mumbai">
            </div>
            
            <div class="flex items-center justify-end space-x-3 pt-4">
                <a href="baparis.php" class="btn-secondary text-sm px-5 py-2.5">Cancel</a>
                <button type="submit" name="add_bapari" class="btn-gold text-sm px-5 py-2.5">Save Customer</button>
            </div>
        </form>
    </div>

<?php elseif ($action === 'edit'): 
    $editId = intval($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM baparis WHERE id = ? AND user_id = ?");
    $stmt->execute([$editId, $userId]);
    $b = $stmt->fetch();
    if (!$b) {
        echo "<p class='text-center py-10 text-slate-400'>Customer not found.</p>";
        require_once 'footer.php';
        exit();
    }
?>
    <!-- Edit Bapari Form -->
    <div class="max-w-xl mx-auto premium-card">
        <h2 class="title-section text-white mb-6 flex items-center">
            <span class="material-symbols-rounded text-[#F4B400] mr-2">edit_note</span> Edit Customer Details
        </h2>
        
        <form method="POST" class="space-y-5">
            <input type="hidden" name="id" value="<?= $b['id'] ?>">
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Customer Name *</label>
                <input type="text" name="name" value="<?= htmlspecialchars($b['name']) ?>" required class="premium-input">
            </div>
            
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Mobile / Contact Number</label>
                <input type="text" name="mobile" value="<?= htmlspecialchars($b['mobile']) ?>" class="premium-input">
            </div>
            
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">City / Business Address</label>
                <input type="text" name="address" value="<?= htmlspecialchars($b['address']) ?>" class="premium-input">
            </div>
            
            <div class="flex items-center justify-end space-x-3 pt-4">
                <a href="baparis.php" class="btn-secondary text-sm px-5 py-2.5">Cancel</a>
                <button type="submit" name="edit_bapari" class="btn-gold text-sm px-5 py-2.5">Save Changes</button>
            </div>
        </form>
    </div>

<?php else: ?>
    <!-- Baparis List View -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-white flex items-center">
                <span class="material-symbols-rounded text-[#F4B400] mr-2 text-3xl">group</span> Customers
            </h1>
            <p class="text-slate-400 text-xs mt-1">Directory of jewelers, merchants, and karigars.</p>
        </div>
        <a href="baparis.php?action=new" class="btn-gold inline-flex items-center text-xs px-3.5 py-2 shadow-md">
            <span class="material-symbols-rounded text-sm mr-1">person_add</span> Add New
        </a>
    </div>

    <!-- Redesigned Customer Cards Stack -->
    <div class="space-y-4">
        <?php if (empty($baparis)): ?>
            <div class="premium-card text-center py-12 flex flex-col items-center justify-center">
                <span class="material-symbols-rounded text-5xl text-slate-600 mb-3">group_off</span>
                <h3 class="text-sm font-semibold text-slate-300">No Customers Found</h3>
                <p class="text-xs text-slate-500 mt-1">Register customers to record custom balance metrics.</p>
            </div>
        <?php else: ?>
            <?php foreach ($baparis as $b): 
                $initials = strtoupper(substr($b['name'], 0, 2));
            ?>
                <div class="premium-card">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center space-x-3.5 min-w-0">
                            <div class="w-12 h-12 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-[#F4B400] text-sm shrink-0">
                                <?= $initials ?>
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-bold text-white text-base truncate"><?= htmlspecialchars($b['name']) ?></h3>
                                <div class="flex flex-col space-y-0.5 mt-1 text-[11px] text-slate-400">
                                    <span class="flex items-center"><span class="material-symbols-rounded text-xs mr-1 text-slate-500">call</span> <?= htmlspecialchars($b['mobile'] ?: 'No mobile') ?></span>
                                    <span class="flex items-center"><span class="material-symbols-rounded text-xs mr-1 text-slate-500">location_on</span> <?= htmlspecialchars($b['address'] ?: 'No address') ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Actions Dropdown Trigger or row of buttons -->
                        <div class="flex items-center space-x-1.5 shrink-0">
                            <a href="baparis.php?action=edit&id=<?= $b['id'] ?>" class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 flex items-center justify-center text-slate-300 transition-colors tap-target" title="Edit">
                                <span class="material-symbols-rounded text-base">edit</span>
                            </a>
                            <a href="baparis.php?delete=<?= $b['id'] ?>" onclick="return confirm('Are you sure you want to delete this customer? All connected Gold deposits and Kaarigari Jobs will be deleted!')" class="w-8 h-8 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 flex items-center justify-center transition-colors tap-target" title="Delete">
                                <span class="material-symbols-rounded text-base">delete</span>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Bottom Quick Actions inside Card -->
                    <div class="mt-5 pt-4 border-t border-slate-800/80 flex items-center justify-between gap-3">
                        <a href="ledger.php?bapari_id=<?= $b['id'] ?>" class="flex-1 py-2 text-center text-xs font-semibold text-[#F4B400] bg-[#F4B400]/5 border border-[#F4B400]/10 rounded-xl hover:bg-[#F4B400] hover:text-slate-950 transition-all inline-flex items-center justify-center space-x-1">
                            <span class="material-symbols-rounded text-sm">book_open</span> <span>View Ledger</span>
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
