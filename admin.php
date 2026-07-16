<?php
require_once 'db.php';

// Access Control: Block standard users
if (!$isAdmin) {
    header("Location: index.php");
    exit();
}

// 1. Handle Impersonation (Masquerading) Swaps
if (isset($_GET['impersonate'])) {
    $targetId = intval($_GET['impersonate']);
    
    // Save original admin ID if not already impersonating
    if (!isset($_SESSION['impersonator_id'])) {
        $_SESSION['impersonator_id'] = $userId;
    }
    
    // Fetch target user details
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
    $stmt->execute([$targetId]);
    $targetUser = $stmt->fetch();
    
    if ($targetUser) {
        $_SESSION['user_id'] = $targetUser['id'];
        $_SESSION['user_name'] = $targetUser['name'];
        $_SESSION['user_email'] = $targetUser['email'];
    }
    
    header("Location: index.php");
    exit();
}

// 2. Stop Impersonating (Swap session back to original Admin)
if (isset($_GET['stop_impersonating'])) {
    if (isset($_SESSION['impersonator_id'])) {
        $adminId = $_SESSION['impersonator_id'];
        
        $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
        $stmt->execute([$adminId]);
        $adminUser = $stmt->fetch();
        
        if ($adminUser) {
            $_SESSION['user_id'] = $adminUser['id'];
            $_SESSION['user_name'] = $adminUser['name'];
            $_SESSION['user_email'] = $adminUser['email'];
        }
        unset($_SESSION['impersonator_id']);
    }
    header("Location: admin.php");
    exit();
}

// 3. Handle User Deletion (Cascade clears automatically via MySQL schema config)
if (isset($_GET['delete_user'])) {
    $deleteId = intval($_GET['delete_user']);
    
    // Protect active sessions and original admin accounts
    $originalAdminId = $_SESSION['impersonator_id'] ?? $userId;
    if ($deleteId !== $originalAdminId) {
        // If the admin is currently impersonating the deleted user, swap session back to admin
        if (isset($_SESSION['user_id']) && intval($_SESSION['user_id']) === $deleteId) {
            $_SESSION['user_id'] = $_SESSION['impersonator_id'];
            unset($_SESSION['impersonator_id']);
        }
        
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$deleteId]);
    }
    header("Location: admin.php");
    exit();
}

// 4. Handle Account Activation Toggle (Admin cannot deactivate themselves)
if (isset($_GET['toggle_active'])) {
    $targetId = intval($_GET['toggle_active']);
    $originalAdminId = $_SESSION['impersonator_id'] ?? $userId;
    
    if ($targetId !== $originalAdminId) {
        $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$targetId]);
    }
    header("Location: admin.php");
    exit();
}

// 5. Query System Aggregates Across ALL Users
$stmt = $pdo->query("SELECT SUM(jama_fine) as total_jama, SUM(cash_received) as total_rec FROM fine_deposits");
$depStats = $stmt->fetch();
$sysTotalJama = $depStats['total_jama'] ?? 0.0;
$sysTotalRec = $depStats['total_rec'] ?? 0.0;

$stmt = $pdo->query("SELECT SUM(total_kaj_fine) as total_kaj, SUM(total_profit_fine) as total_profit FROM kaj_entries");
$kajStats = $stmt->fetch();
$sysTotalKaj = $kajStats['total_kaj'] ?? 0.0;
$sysTotalProfit = $kajStats['total_profit'] ?? 0.0;

// 6. Fetch all users
$stmt = $pdo->query("SELECT id, name, email, is_admin, is_active FROM users ORDER BY is_admin DESC, name ASC");
$usersList = $stmt->fetchAll();

// Assemble user details stats counters
foreach ($usersList as &$u) {
    // Count baparis
    $cStmt = $pdo->prepare("SELECT COUNT(*) as count FROM baparis WHERE user_id = ?");
    $cStmt->execute([$u['id']]);
    $u['baparis_count'] = $cStmt->fetch()['count'];

    // Count deposits
    $cStmt = $pdo->prepare("SELECT COUNT(*) as count FROM fine_deposits WHERE user_id = ?");
    $cStmt->execute([$u['id']]);
    $u['deposits_count'] = $cStmt->fetch()['count'];

    // Count kaj entries
    $cStmt = $pdo->prepare("SELECT COUNT(*) as count FROM kaj_entries WHERE user_id = ?");
    $cStmt->execute([$u['id']]);
    $u['kaj_count'] = $cStmt->fetch()['count'];
}
unset($u);

require_once 'header.php';
?>

<!-- Title Header (Matching mockup screenshot 3) -->
<div class="mb-4 mt-2">
    <h1 class="text-3xl font-extrabold tracking-tight text-white flex items-center">
        <span class="material-symbols-rounded text-[#d8a735] mr-2 text-3xl">shield</span> Admin Panel
    </h1>
    <p class="text-slate-500 text-xs mt-1">All users overview</p>
</div>

<!-- Aggregates Dashboard Panel ACROSS ALL USERS -->
<div class="mb-6">
    <span class="text-[#d8a735] text-[10px] uppercase font-bold tracking-wider block mb-3">Across All <?= count($usersList) ?> Users</span>
    <div class="grid grid-cols-2 gap-4">
        <div class="premium-card bg-[#121212]/50">
            <span class="text-slate-500 text-[9px] uppercase font-bold block mb-1">Total Fine Deposit</span>
            <div class="text-base font-bold text-white font-mono"><?= number_format($sysTotalJama, 3) ?> g</div>
        </div>
        
        <div class="premium-card bg-[#121212]/50">
            <span class="text-slate-500 text-[9px] uppercase font-bold block mb-1">Total Kaj Fine</span>
            <div class="text-base font-bold text-white font-mono"><?= number_format($sysTotalKaj, 3) ?> g</div>
        </div>
        
        <div class="premium-card bg-[#121212]/50 gold-border">
            <span class="text-slate-500 text-[9px] uppercase font-bold block mb-1">Profit Fine</span>
            <div class="text-base font-bold text-white font-mono"><?= number_format($sysTotalProfit, 3) ?> g</div>
        </div>
        
        <div class="premium-card bg-[#121212]/50">
            <span class="text-slate-500 text-[9px] uppercase font-bold block mb-1">Cash Received</span>
            <div class="text-base font-bold text-white font-mono">₹<?= number_format($sysTotalRec, 0) ?></div>
        </div>
    </div>
</div>

<!-- Real-time Filter & Search (User Requirement) -->
<div class="mb-5 space-y-3">
    <!-- Search bar -->
    <div class="relative">
        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
            <span class="material-symbols-rounded text-lg">search</span>
        </span>
        <input type="text" id="userSearchInput" oninput="filterUsers()" class="premium-input pl-10 text-xs" placeholder="Search users by name or email...">
    </div>

    <!-- Status filter pills -->
    <div class="flex items-center space-x-2">
        <button onclick="setStatusFilter('all')" id="pill_all" class="px-4 py-1.5 rounded-full text-[10px] font-bold transition-all bg-[#d8a735] text-slate-950 shadow-md">
            All
        </button>
        <button onclick="setStatusFilter('active')" id="pill_active" class="px-4 py-1.5 rounded-full text-[10px] font-bold transition-all bg-slate-900 border border-white/[0.04] text-slate-400">
            Active
        </button>
        <button onclick="setStatusFilter('inactive')" id="pill_inactive" class="px-4 py-1.5 rounded-full text-[10px] font-bold transition-all bg-slate-900 border border-white/[0.04] text-slate-400">
            Inactive
        </button>
    </div>
</div>

<!-- Users List Grid (Matching screenshot layout 3) -->
<div class="mb-8">
    <span class="text-[#d8a735] text-[10px] uppercase font-bold tracking-wider block mb-4">Users (<?= count($usersList) ?>)</span>
    
    <div class="space-y-3.5" id="usersListContainer">
        <?php foreach ($usersList as $u): 
            $originalAdminId = $_SESSION['impersonator_id'] ?? $userId;
            $isTargetAdmin = (intval($u['is_admin']) === 1);
            $isActive = (intval($u['is_active']) === 1);
            $canManage = ($u['id'] !== $originalAdminId); // Admin cannot toggle/delete themselves
        ?>
            <div class="premium-card bg-[#121212]/80 flex items-center justify-between p-4 hover:border-slate-800 transition-colors user-row-card" 
                 data-name="<?= strtolower(htmlspecialchars($u['name'])) ?>" 
                 data-email="<?= strtolower(htmlspecialchars($u['email'])) ?>"
                 data-status="<?= $isActive ? 'active' : 'inactive' ?>">
                
                <!-- Impersonation Click Link (Swaps view to target user) -->
                <a href="admin.php?impersonate=<?= $u['id'] ?>" class="flex items-center space-x-3.5 min-w-0 flex-1 select-none tap-target" title="Click to view dashboard as this user">
                    <div class="w-10 h-10 rounded-full bg-slate-900 border border-white/[0.04] flex items-center justify-center shrink-0 <?= $isActive ? 'text-slate-500' : 'text-slate-700' ?>">
                        <span class="material-symbols-rounded text-lg"><?= $isActive ? 'person' : 'person_off' ?></span>
                    </div>
                    <div class="min-w-0 flex-1 pr-2">
                        <h3 class="text-xs font-bold text-white leading-tight truncate">
                            <?= htmlspecialchars($u['name'] ?: 'No Name') ?>
                            <?php if ($isTargetAdmin): ?>
                                <span class="text-[9px] text-[#d8a735] font-extrabold uppercase ml-1.5">- Admin</span>
                            <?php endif; ?>
                        </h3>
                        <p class="text-[10px] text-slate-500 truncate mt-0.5"><?= htmlspecialchars($u['email']) ?></p>
                        <p class="text-[9px] text-slate-400 font-mono mt-1">
                            <?= $u['baparis_count'] ?> bapari · <?= $u['deposits_count'] ?> dep · <?= $u['kaj_count'] ?> kaj
                        </p>
                    </div>
                </a>

                <!-- Admin Action Triggers -->
                <div class="flex items-center space-x-3 shrink-0">
                    <?php if ($canManage): ?>
                        <!-- Status Toggle (Deactivate/Activate) -->
                        <a href="admin.php?toggle_active=<?= $u['id'] ?>" class="px-2.5 py-1.5 rounded-lg text-[9px] font-bold border transition-colors tap-target <?= $isActive ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20 hover:bg-rose-500/10 hover:text-rose-400 hover:border-rose-500/20' : 'bg-rose-500/10 text-rose-400 border-rose-500/20 hover:bg-emerald-500/10 hover:text-emerald-400 hover:border-emerald-500/20' ?>" 
                           title="<?= $isActive ? 'Deactivate' : 'Activate' ?> Account">
                            <?= $isActive ? 'ACTIVE' : 'INACTIVE' ?>
                        </a>

                        <!-- Delete Account Button -->
                        <?php if (!$isTargetAdmin): ?>
                            <a href="admin.php?delete_user=<?= $u['id'] ?>" onclick="return confirm('Are you sure you want to delete this user? All their customers and transaction entries will be permanently deleted!')" class="w-8 h-8 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 flex items-center justify-center transition-colors tap-target" title="Delete Account">
                                <span class="material-symbols-rounded text-base">delete</span>
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Current User / Safe Indicator -->
                        <span class="text-[8px] bg-slate-900 border border-white/[0.04] text-slate-500 px-2 py-1.5 rounded-lg font-bold uppercase">YOU</span>
                    <?php endif; ?>

                    <a href="admin.php?impersonate=<?= $u['id'] ?>" class="text-slate-500 hover:text-[#d8a735] transition-colors shrink-0">
                        <span class="material-symbols-rounded text-lg">chevron_right</span>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    var currentStatusFilter = 'all';

    function setStatusFilter(status) {
        currentStatusFilter = status;
        
        // Update pill styles
        ['all', 'active', 'inactive'].forEach(s => {
            const el = document.getElementById('pill_' + s);
            if (s === status) {
                el.className = "px-4 py-1.5 rounded-full text-[10px] font-bold transition-all bg-[#d8a735] text-slate-950 shadow-md";
            } else {
                el.className = "px-4 py-1.5 rounded-full text-[10px] font-bold transition-all bg-slate-900 border border-white/[0.04] text-slate-400";
            }
        });

        filterUsers();
    }

    function filterUsers() {
        const query = document.getElementById('userSearchInput').value.toLowerCase().trim();
        const cards = document.querySelectorAll('.user-row-card');

        cards.forEach(card => {
            const name = card.getAttribute('data-name');
            const email = card.getAttribute('data-email');
            const status = card.getAttribute('data-status');

            const matchesSearch = (name.includes(query) || email.includes(query));
            const matchesStatus = (currentStatusFilter === 'all' || status === currentStatusFilter);

            if (matchesSearch && matchesStatus) {
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
