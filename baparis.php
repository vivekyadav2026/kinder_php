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

<!-- Title Header (Matching reference) -->
<div class="mb-4 mt-2">
    <h1 class="text-3xl font-extrabold tracking-tight text-white flex items-center">
        Bapari
    </h1>
</div>

<!-- Search Input (Matching reference) -->
<div class="relative mb-6">
    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
        <span class="material-symbols-rounded text-lg">search</span>
    </span>
    <input type="text" id="bapariSearch" onkeyup="searchBapari()" placeholder="Search bapari..." class="premium-input pl-10 text-sm">
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

<!-- Customers Cards/List Grid -->
<div id="baparisContainer" class="space-y-4 pb-24 px-4">
    <?php if (empty($baparis)): ?>
        <div class="text-center py-20 flex flex-col items-center justify-center">
            <span class="material-symbols-rounded text-6xl text-slate-700 mb-4">group</span>
            <h3 class="text-base font-semibold text-slate-400">No Baparis yet. Add your first one.</h3>
        </div>
    <?php else: ?>
        <?php foreach ($baparis as $b): ?>
            <div class="premium-card bapari-card flex items-center justify-between">
                <div class="min-w-0 flex-1">
                    <h3 class="font-bold text-white text-base truncate bapari-name"><?= htmlspecialchars($b['name']) ?></h3>
                    <p class="text-xs text-slate-500 truncate mt-0.5"><?= htmlspecialchars($b['mobile'] ?: 'No mobile') ?> | <?= htmlspecialchars($b['address'] ?: 'No address') ?></p>
                </div>
                
                <div class="flex items-center space-x-2.5 shrink-0 ml-4">
                    <a href="ledger.php?bapari_id=<?= $b['id'] ?>" class="w-9 h-9 rounded-xl bg-[#d8a735]/15 hover:bg-[#d8a735]/25 border border-[#d8a735]/20 flex items-center justify-center text-[#d8a735] transition-colors tap-target" title="View Ledger">
                        <span class="material-symbols-rounded text-base">menu_book</span>
                    </a>
                    <a href="baparis.php?action=edit&id=<?= $b['id'] ?>" class="w-9 h-9 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 flex items-center justify-center text-slate-300 transition-colors tap-target" title="Edit Profile">
                        <span class="material-symbols-rounded text-base">edit</span>
                    </a>
                    <a href="baparis.php?delete=<?= $b['id'] ?>" onclick="return confirm('Are you sure you want to delete this Bapari?')" class="w-9 h-9 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 flex items-center justify-center transition-colors tap-target" title="Delete Profile">
                        <span class="material-symbols-rounded text-base">delete</span>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Static Add New Bapari Button below the list (No floating/overlapping) -->
<div class="mt-6 px-4 mb-8 no-print">
    <a href="baparis.php?action=new" class="w-full btn-gold py-3.5 flex items-center justify-center space-x-1.5 shadow-xl shadow-[#d8a735]/10 tap-target">
        <span class="material-symbols-rounded text-lg">person_add</span>
        <span>Add New Bapari</span>
    </a>
</div>

<!-- Modal Overlay for Add Bapari (Matching reference Image 2) -->
<?php if ($action === 'new'): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-sm p-4">
        <div class="w-full max-w-lg premium-card border-[#d8a735]/35 shadow-2xl shadow-[#d8a735]/5 animate-scale-up">
            <h2 class="text-xl font-bold text-[#d8a735] mb-5">Add Bapari</h2>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1.5">Name</label>
                    <input type="text" name="name" required class="premium-input text-sm" placeholder="Enter name">
                </div>
                
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1.5">Mobile</label>
                    <input type="text" name="mobile" class="premium-input text-sm" placeholder="Enter mobile">
                </div>
                
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1.5">Address</label>
                    <input type="text" name="address" class="premium-input text-sm" placeholder="Enter address">
                </div>
                
                <div class="grid grid-cols-2 gap-3.5 pt-4">
                    <a href="baparis.php" class="btn-secondary text-sm font-semibold flex items-center justify-center">Cancel</a>
                    <button type="submit" name="add_bapari" class="btn-gold text-sm font-semibold flex items-center justify-center">Save</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Modal Overlay for Edit Bapari -->
<?php if ($action === 'edit'): 
    $editId = intval($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM baparis WHERE id = ? AND user_id = ?");
    $stmt->execute([$editId, $userId]);
    $b = $stmt->fetch();
    if ($b):
?>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-sm p-4">
        <div class="w-full max-w-lg premium-card border-[#d8a735]/35 shadow-2xl shadow-[#d8a735]/5 animate-scale-up">
            <h2 class="text-xl font-bold text-[#d8a735] mb-5">Edit Bapari</h2>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="id" value="<?= $b['id'] ?>">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1.5">Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($b['name']) ?>" required class="premium-input text-sm">
                </div>
                
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1.5">Mobile</label>
                    <input type="text" name="mobile" value="<?= htmlspecialchars($b['mobile']) ?>" class="premium-input text-sm">
                </div>
                
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1.5">Address</label>
                    <input type="text" name="address" value="<?= htmlspecialchars($b['address']) ?>" class="premium-input text-sm">
                </div>
                
                <div class="grid grid-cols-2 gap-3.5 pt-4">
                    <a href="baparis.php" class="btn-secondary text-sm font-semibold flex items-center justify-center">Cancel</a>
                    <button type="submit" name="edit_bapari" class="btn-gold text-sm font-semibold flex items-center justify-center">Save</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; endif; ?>

<script>
    function searchBapari() {
        const query = document.getElementById('bapariSearch').value.toLowerCase();
        const cards = document.querySelectorAll('.bapari-card');
        cards.forEach(card => {
            const name = card.querySelector('.bapari-name').textContent.toLowerCase();
            if (name.includes(query)) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }
</script>

<?php
require_once 'footer.php';
?>
