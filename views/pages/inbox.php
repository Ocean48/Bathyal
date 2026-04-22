<?php
// /views/pages/inbox.php
require_once 'core/database.php';
require_once 'core/auth_check.php';
require_once 'core/db_query.php';

$db = new DBQueries($pdo);
$userId = $_SESSION['user_id'] ?? 1; // Assuming 1 for mock/default or session id

$category = $_GET['category'] ?? 'all';
$notifications = $db->getUserNotifications($userId, 50, $category);

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read_id'])) {
    $db->markNotificationRead($_POST['mark_read_id'], $userId);
    $catParam = isset($_GET['category']) ? "?category=" . urlencode($_GET['category']) : "";
    header("Location: /bathyal/inbox{$catParam}");
    exit;
}

require_once 'views/layouts/header.php';
?>

<div class="max-w-4xl mx-auto px-6 py-8">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800">Inbox</h1>
            <p class="text-sm text-slate-500 mt-1">Updates and notifications across your projects.</p>
        </div>
    </div>

    <!-- Category Tabs -->
    <div class="flex items-center space-x-1 mb-6 border-b border-slate-200">
        <a href="?category=all" class="px-4 py-2 text-sm font-medium border-b-2 transition-colors <?= $category === 'all' ? 'text-teal-600 border-teal-500' : 'text-slate-500 border-transparent hover:text-slate-700 hover:border-slate-300' ?>">All</a>
        <a href="?category=task_update" class="px-4 py-2 text-sm font-medium border-b-2 transition-colors <?= $category === 'task_update' ? 'text-teal-600 border-teal-500' : 'text-slate-500 border-transparent hover:text-slate-700 hover:border-slate-300' ?>">Tasks</a>
        <a href="?category=system" class="px-4 py-2 text-sm font-medium border-b-2 transition-colors <?= $category === 'system' ? 'text-teal-600 border-teal-500' : 'text-slate-500 border-transparent hover:text-slate-700 hover:border-slate-300' ?>">System</a>
        <a href="?category=mention" class="px-4 py-2 text-sm font-medium border-b-2 transition-colors <?= $category === 'mention' ? 'text-teal-600 border-teal-500' : 'text-slate-500 border-transparent hover:text-slate-700 hover:border-slate-300' ?>">Mentions</a>
    </div>

    <div class="bg-white border text-sm border-slate-200 shadow-sm rounded-xl overflow-hidden divide-y divide-slate-200">
        <?php if (empty($notifications)): ?>
            <div class="p-8 text-center text-slate-500">
                <i class="fas fa-bell-slash text-4xl mb-3 text-slate-300"></i>
                <p>No notifications here.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $n): ?>
                <div class="p-4 flex items-start gap-4 hover:bg-slate-50 transition-colors <?= $n['is_read'] ? 'opacity-60' : 'bg-blue-50/30' ?>">
                    <div class="w-10 h-10 rounded-full flex-shrink-0 flex items-center justify-center <?= $n['is_read'] ? 'bg-slate-100 text-slate-400' : 'bg-blue-100 text-blue-600' ?>">
                        <?php if($n['category'] === 'task_update'): ?>
                            <i class="fas fa-tasks"></i>
                        <?php elseif($n['category'] === 'mention'): ?>
                            <i class="fas fa-at"></i>
                        <?php elseif($n['category'] === 'system'): ?>
                            <i class="fas fa-cog"></i>
                        <?php else: ?>
                            <i class="fas fa-<?= $n['task_id'] ? 'check-circle' : 'info-circle' ?>"></i>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <h4 class="font-semibold text-slate-800 <?= !$n['is_read'] ? 'text-blue-900' : '' ?>">
                                <?= htmlspecialchars($n['message']) ?>
                            </h4>
                            <span class="text-xs text-slate-400 whitespace-nowrap ml-4">
                                <?= convertUtcToToronto($n['created_at'], 'M j, Y g:i A') ?>
                            </span>
                        </div>
                        <?php if ($n['task_id']): ?>
                            <p class="text-slate-600 mt-1 hover:text-teal-600 inline-block transition-colors">
                                <a href="/bathyal/tasks?id=<?= $n['task_id'] ?>">Related to Task #<?= $n['task_id'] ?></a>
                            </p>
                        <?php endif; ?>
                    </div>
                    <?php if (!$n['is_read']): ?>
                        <div class="flex-shrink-0 ml-4">
                            <form method="POST">
                                <input type="hidden" name="mark_read_id" value="<?= $n['id'] ?>">
                                <button type="submit" class="text-xs font-medium text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-full transition-colors">
                                    Mark as read
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'views/layouts/footer.php'; ?>
