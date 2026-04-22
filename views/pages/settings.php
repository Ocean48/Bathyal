<?php
// /settings.php

require_once 'core/database.php';
require_once 'core/auth_check.php';

$successMsg = '';
$errorMsg = '';

// Handle role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_role') {
    if ($currentUser['role'] !== 'admin') {
        $errorMsg = 'Access Denied: Only Team Admins can change roles.';
    } else {
        $targetUserId = (int)$_POST['user_id'];
        $newRole = $_POST['role'];
        
        // Prevent admin from locking themselves out (demoting themselves)
        if ($targetUserId === $currentUser['id'] && $newRole !== 'admin') {
            $errorMsg = 'You cannot demote yourself from admin.';
        } else {
            $db = new DBQueries($pdo);
            if ($db->updateSystemUserRole($targetUserId, $newRole)) {
                $successMsg = 'User role updated successfully.';
            } else {
                $errorMsg = 'Failed to update user role.';
            }
        }
    }
}

// Fetch users if admin
$systemUsers = [];
if ($currentUser['role'] === 'admin') {
    $db = new DBQueries($pdo);
    $systemUsers = $db->getAllSystemUsers();
}

require_once 'views/layouts/header.php';
?>

<div class="max-w-6xl mx-auto px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-semibold text-slate-800">Settings</h1>
    </div>

    <!-- Messages -->
    <?php if($successMsg): ?>
        <div class="bg-emerald-50 text-emerald-700 p-3 rounded mb-6 border border-emerald-200">
            <?= htmlspecialchars($successMsg) ?>
        </div>
    <?php endif; ?>
    <?php if($errorMsg): ?>
        <div class="bg-rose-50 text-rose-700 p-3 rounded mb-6 border border-rose-200">
            <?= htmlspecialchars($errorMsg) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 <?= $currentUser['role'] === 'admin' ? 'md:grid-cols-3' : 'md:grid-cols-1 max-w-2xl mx-auto' ?> gap-8">
        
        <!-- Profile Column -->
        <div class="md:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <div class="flex flex-col items-center justify-center text-center">
                    <div class="w-20 h-20 rounded-full bg-gradient-to-r from-teal-400 to-cyan-500 flex items-center justify-center text-white font-semibold text-2xl shadow-sm ring-4 ring-white mb-4">
                        <?= htmlspecialchars(strtoupper(substr($currentUser['name'] ?? 'U', 0, 1))) ?>
                    </div>
                    <h2 class="text-xl font-bold text-slate-800"><?= htmlspecialchars($currentUser['name']) ?></h2>
                    <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-full text-xs font-semibold uppercase tracking-wider mt-2 inline-block">
                        <?= htmlspecialchars(str_replace('_', ' ', $currentUser['role'])) ?>
                    </span>
                </div>
                
                <hr class="my-6 border-slate-100">
                
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-4">Account Information</h3>
                
                <div class="space-y-4">
                    <div>
                        <p class="text-xs text-slate-500 mb-1">Email Address</p>
                        <p class="text-sm font-medium text-slate-800"><?= htmlspecialchars($currentUser['email']) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 mb-1">User ID</p>
                        <p class="text-sm font-medium text-slate-800">#<?= htmlspecialchars($currentUser['id']) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 mb-1">Account Created</p>
                        <p class="text-sm font-medium text-slate-800">
                            <?= isset($currentUser['created_at']) ? convertUtcToToronto($currentUser['created_at'], 'M j, Y') : 'Unknown' ?>
                        </p>
                    </div>
                    <?php if (isset($currentUser['team_id']) && $currentUser['team_id']): ?>
                    <div>
                        <p class="text-xs text-slate-500 mb-1">Assigned Team ID</p>
                        <p class="text-sm font-medium text-slate-800"><?= htmlspecialchars($currentUser['team_id']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($currentUser['role'] === 'admin'): ?>
        <!-- System Users Settings Column -->
        <div class="md:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-200 bg-slate-50/50">
                    <h2 class="text-lg font-medium text-slate-800">System Users</h2>
                    <p class="text-sm text-slate-500 mt-1">Manage global access levels across all accounts in the system.</p>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white text-xs text-slate-500 uppercase tracking-wide border-b border-slate-200">
                        <th class="px-6 py-3 font-medium">User</th>
                        <th class="px-6 py-3 font-medium">Joined</th>
                        <th class="px-6 py-3 font-medium">Role</th>
                        <th class="px-6 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php foreach($systemUsers as $member): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 font-medium text-slate-800">
                                <div class="flex items-center">
                                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($member['name']) ?>&background=E2E8F0&color=475569" class="w-8 h-8 rounded-full mr-3 border border-slate-200">
                                    <div>
                                        <div class="leading-tight"><?= htmlspecialchars($member['name']) ?>
                                            <?php if($member['id'] === $currentUser['id']): ?>
                                                <span class="ml-2 bg-slate-100 text-slate-500 text-[10px] px-2 py-0.5 rounded-full">You</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-xs text-slate-500 font-normal mt-0.5"><?= htmlspecialchars($member['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-slate-500">
                                <?= isset($member['created_at']) ? convertUtcToToronto($member['created_at'], 'M j, Y') : '-' ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php
                                    $roleColors = [
                                        'admin' => 'bg-indigo-100 text-indigo-700',
                                        'member' => 'bg-slate-100 text-slate-600',
                                        'data_analyst' => 'bg-teal-100 text-teal-700'
                                    ];
                                    $colorClass = $roleColors[$member['role']] ?? 'bg-slate-100 text-slate-600';
                                ?>
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= $colorClass ?>">
                                    <?= ucfirst(str_replace('_', ' ', $member['role'])) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php if($currentUser['role'] === 'admin' && $member['id'] !== $currentUser['id']): ?>
                                    <form method="POST" action="" class="inline-flex items-center">
                                        <input type="hidden" name="action" value="update_role">
                                        <input type="hidden" name="user_id" value="<?= $member['id'] ?>">
                                        <select name="role" class="bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded p-1.5 mr-2 outline-none focus:border-teal-400">
                                            <option value="member" <?= $member['role'] === 'member' ? 'selected' : '' ?>>Member</option>
                                            <option value="data_analyst" <?= $member['role'] === 'data_analyst' ? 'selected' : '' ?>>Data Analyst</option>
                                            <option value="admin" <?= $member['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                        </select>
                                        <button type="submit" class="text-teal-600 hover:text-teal-800 font-medium text-xs px-2 py-1 bg-teal-50 hover:bg-teal-100 rounded transition-colors">Save</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400 italic">
                                        <?= $member['id'] === $currentUser['id'] ? 'Current User' : '' ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div> <!-- End md:col-span-2 -->
<?php endif; ?>
</div> <!-- End grid -->
</div> <!-- End max-w-6xl -->

<?php require_once 'views/layouts/footer.php'; ?>