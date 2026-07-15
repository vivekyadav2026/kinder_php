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
            $success = 'Bapari added successfully!';
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
            $success = 'Bapari updated successfully!';
            $action = 'list';
        }
    }
}

// Handle Delete Bapari
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM baparis WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    $success = 'Bapari deleted successfully!';
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
    <!-- Add Bapari Form -->
    <div class="max-w-xl mx-auto glass-card rounded-2xl p-6 border border-slate-800">
        <h2 class="text-xl font-bold text-white mb-6 flex items-center">
            <i class="fa-solid fa-user-plus text-amber-400 mr-2"></i> Add New Bapari
        </h2>
        
        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Bapari Name *</label>
                <input type="text" name="name" required class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-amber-400 transition-colors" placeholder="e.g. Rahul Jewellers">
            </div>
            
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Mobile Number</label>
                <input type="text" name="mobile" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-amber-400 transition-colors" placeholder="e.g. +91 9876543210">
            </div>
            
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Address</label>
                <textarea name="address" rows="3" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-amber-400 transition-colors" placeholder="e.g. Zaveri Bazaar, Mumbai"></textarea>
            </div>
            
            <div class="flex items-center justify-end space-x-3 pt-4">
                <a href="baparis.php" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-slate-400 hover:text-white hover:bg-slate-800 transition-all">Cancel</a>
                <button type="submit" name="add_bapari" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-slate-950 gold-bg hover:opacity-90 shadow-md shadow-amber-500/10 transition-all">Save Bapari</button>
            </div>
        </form>
    </div>

<?php elseif ($action === 'edit'): 
    $editId = intval($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM baparis WHERE id = ? AND user_id = ?");
    $stmt->execute([$editId, $userId]);
    $bapari = $stmt->fetch();
    if (!$bapari) {
        echo "<p class='text-center py-10'>Bapari not found.</p>";
        require_once 'footer.php';
        exit();
    }
?>
    <!-- Edit Bapari Form -->
    <div class="max-w-xl mx-auto glass-card rounded-2xl p-6 border border-slate-800">
        <h2 class="text-xl font-bold text-white mb-6 flex items-center">
            <i class="fa-solid fa-user-pen text-amber-400 mr-2"></i> Edit Bapari Details
        </h2>
        
        <form method="POST" class="space-y-5">
            <input type="hidden" name="id" value="<?= $bapari['id'] ?>">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Bapari Name *</label>
                <input type="text" name="name" value="<?= htmlspecialchars($bapari['name']) ?>" required class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-amber-400 transition-colors">
            </div>
            
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Mobile Number</label>
                <input type="text" name="mobile" value="<?= htmlspecialchars($bapari['mobile']) ?>" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-amber-400 transition-colors">
            </div>
            
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Address</label>
                <textarea name="address" rows="3" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-amber-400 transition-colors"><?= htmlspecialchars($bapari['address']) ?></textarea>
            </div>
            
            <div class="flex items-center justify-end space-x-3 pt-4">
                <a href="baparis.php" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-slate-400 hover:text-white hover:bg-slate-800 transition-all">Cancel</a>
                <button type="submit" name="edit_bapari" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-slate-950 gold-bg hover:opacity-90 shadow-md shadow-amber-500/10 transition-all">Update Bapari</button>
            </div>
        </form>
    </div>

<?php else: ?>
    <!-- Baparis List View -->
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-white flex items-center">
                <span class="gold-text mr-2"><i class="fa-solid fa-users"></i></span> Bapari Directory
            </h1>
            <p class="text-slate-400 text-sm mt-1">Manage and edit your business client accounts.</p>
        </div>
        <a href="baparis.php?action=new" class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-950 gold-bg hover:opacity-90 shadow-md shadow-amber-500/10 transition-all flex items-center space-x-2">
            <i class="fa-solid fa-user-plus"></i> <span>Add Bapari</span>
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (empty($baparis)): ?>
            <div class="col-span-full glass-card rounded-2xl p-12 text-center text-slate-500">
                <i class="fa-regular fa-user text-4xl mb-3 text-slate-600 block"></i>
                <p class="text-sm">No Baparis added yet.</p>
                <a href="baparis.php?action=new" class="text-amber-400 hover:underline mt-2 inline-block text-xs font-semibold">Create one now</a>
            </div>
        <?php else: ?>
            <?php foreach ($baparis as $b): ?>
                <div class="glass-card rounded-2xl p-5 border border-slate-800 hover:border-amber-400/40 transition-all group flex flex-col justify-between">
                    <div>
                        <div class="flex items-start justify-between mb-2">
                            <h3 class="text-lg font-bold text-white group-hover:text-amber-400 transition-colors truncate pr-2"><?= htmlspecialchars($b['name']) ?></h3>
                            <span class="text-[10px] bg-slate-800 text-slate-400 px-2 py-1 rounded border border-slate-700/50 uppercase font-mono">ID: BP-<?= $b['id'] ?></span>
                        </div>
                        <p class="text-sm text-slate-300 flex items-center space-x-2 mb-3">
                            <i class="fa-solid fa-phone text-xs text-amber-500/80"></i>
                            <span><?= htmlspecialchars($b['mobile'] ?: 'No Contact') ?></span>
                        </p>
                        <p class="text-xs text-slate-400 line-clamp-2 min-h-[2rem]">
                            <?= htmlspecialchars($b['address'] ?: 'No address specified.') ?>
                        </p>
                    </div>
                    
                    <div class="mt-5 pt-4 border-t border-slate-800/80 flex items-center justify-between">
                        <a href="ledger.php?bapari_id=<?= $b['id'] ?>" class="text-xs font-semibold text-amber-400 hover:underline">
                            <i class="fa-solid fa-book-open mr-1"></i> Ledger
                        </a>
                        
                        <div class="flex items-center space-x-2">
                            <a href="baparis.php?action=edit&id=<?= $b['id'] ?>" class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition-colors" title="Edit">
                                <i class="fa-solid fa-pen text-xs"></i>
                            </a>
                            <a href="baparis.php?delete=<?= $b['id'] ?>" onclick="return confirm('Are you sure you want to delete this Bapari and all of their related deposits/kaj transactions?')" class="p-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 transition-colors" title="Delete">
                                <i class="fa-solid fa-trash text-xs"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
require_once 'footer.php';
?>
