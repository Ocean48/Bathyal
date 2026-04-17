<?php
// /settings.php

require_once 'core/database.php';
require_once 'core/auth_check.php';

// Only admins should ideally manage team roles. Assuming role 'admin'
if ($currentUser['role'] !== 'admin') {
    die("<div style='padding:20px; font-family:sans-serif; color:red;'>Access Denied. Only Team Admins can view settings. <a href='index.php'>Go back</a></div>");
}

$successMsg = '';
$errorMsg = '';

// Handle role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_role') {
    $targetUserId = (int)$_POST['user_id'];
    $newRole = $_POST['role'];
    
    // Prevent admin from locking themselves out (demoting themselves)
    if ($targetUserId === $currentUser['id'] && $newRole !== 'admin') {
        $errorMsg = 'You cannot demote yourself from admin.';
    } else {
        $db = new DBQueries($pdo);
        if ($db->updateUserRole($targetUserId, $currentUser['team_id'], $newRole)) {
            $successMsg = 'User role updated successfully.';
        } else {
            $errorMsg = 'Failed to update user role.';
        }
    }
}

// Fetch team members
$db = new DBQueries($pdo);
$members = $db->getTeamMembers($currentUser['team_id']);

require_once 'views/layouts/header.php';
?>

<div class="max-w-6xl mx-auto px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-semibold text-slate-800">Team Settings</h1>
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

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-200 bg-slate-50/50">
            <h2 class="text-lg font-medium text-slate-800">Manage Members</h2>
            <p class="text-sm text-slate-500 mt-1">Control access levels and permissions for your team workspace.</p>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white text-xs text-slate-500 uppercase tracking-wide border-b border-slate-200">
                        <th class="px-6 py-3 font-medium">Name</th>
                        <th class="px-6 py-3 font-medium">Email</th>
                        <th class="px-6 py-3 font-medium">Role</th>
                        <th class="px-6 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php foreach($members as $member): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 font-medium text-slate-800">
                                <div class="flex items-center">
                                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($member['name']) ?>&background=E2E8F0&color=475569" class="w-7 h-7 rounded-full mr-3 border border-slate-200">
                                    <?= htmlspecialchars($member['name']) ?>
                                    <?php if($member['id'] === $currentUser['id']): ?>
                                        <span class="ml-2 bg-slate-100 text-slate-500 text-[10px] px-2 py-0.5 rounded-full">You</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-slate-500">
                                <?= htmlspecialchars($member['email']) ?>
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
                                <?php if($member['id'] !== $currentUser['id']): ?>
                                    <form method="POST" action="settings.php" class="inline-flex items-center">
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
                                    <span class="text-xs text-slate-400 italic">Global Admin</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'views/layouts/footer.php'; ?>