<?php
// /index.php

require_once 'core/database.php';
require_once 'core/auth_check.php';

// Simple routing logic could go here
$request_uri = $_SERVER['REQUEST_URI'];

require_once 'core/db_query.php';
$db = new DBQueries($pdo);

// Fetch recent projects
$recent_projects = [];
if (isset($pdo) && isset($currentUser['id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.* 
            FROM projects p
            JOIN project_members pm ON p.id = pm.project_id
            WHERE pm.user_id = ?
            ORDER BY p.created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$currentUser['id']]);
        $recent_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        // Table might not exist yet
    }
}

// Fetch user preferences
$stmt = $pdo->prepare("SELECT dashboard_preferences FROM users WHERE id = ?");
$stmt->execute([$currentUser['id']]);
$prefsJson = $stmt->fetchColumn();
$prefs = $prefsJson ? json_decode($prefsJson, true) : [
    'show_stats' => true,
    'show_priorities' => true,
    'show_projects' => true
];

// Fetch dashboard stats
$stats = $db->getDashboardStats($currentUser['id']);

// Fetch active timer
$activeTimer = $db->getActiveTimerForUser($currentUser['id']);

// Fetch tasks for My Priorities
$myTasks = $db->getTasksByAssigneeId($currentUser['id']);
$upcomingTasks = [];
$overdueTasks = [];
$now = time();
foreach ($myTasks as $t) {
    if ($t['status'] === 'done' || $t['status'] === 'completed') continue;
    if ($t['expected_due_date'] && strtotime($t['expected_due_date']) < $now) {
        $overdueTasks[] = $t;
    } else {
        $upcomingTasks[] = $t;
    }
}
// Sort by due date mostly
usort($upcomingTasks, function($a, $b) {
    if (!$a['expected_due_date']) return 1;
    if (!$b['expected_due_date']) return -1;
    return strtotime($a['expected_due_date']) <=> strtotime($b['expected_due_date']);
});

// Basic entry point
require_once 'views/layouts/header.php';
?>

<!-- Customization Modal -->
<div id="customizeModal" class="fixed inset-0 bg-slate-900/50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-xl font-semibold text-slate-800 mb-4">Customize Dashboard</h2>
        <div class="space-y-4">
            <label class="flex items-center space-x-3">
                <input type="checkbox" id="pref_stats" class="form-checkbox h-5 w-5 text-teal-600 rounded border-slate-300" <?= !empty($prefs['show_stats']) ? 'checked' : '' ?>>
                <span class="text-slate-700 font-medium">Quick Stats</span>
            </label>
            <label class="flex items-center space-x-3">
                <input type="checkbox" id="pref_priorities" class="form-checkbox h-5 w-5 text-teal-600 rounded border-slate-300" <?= !empty($prefs['show_priorities']) ? 'checked' : '' ?>>
                <span class="text-slate-700 font-medium">My Priorities</span>
            </label>
            <label class="flex items-center space-x-3">
                <input type="checkbox" id="pref_projects" class="form-checkbox h-5 w-5 text-teal-600 rounded border-slate-300" <?= !empty($prefs['show_projects']) ? 'checked' : '' ?>>
                <span class="text-slate-700 font-medium">Recent Projects</span>
            </label>
        </div>
        <div class="mt-8 flex justify-end space-x-3">
            <button onclick="document.getElementById('customizeModal').classList.add('hidden')" class="px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">Cancel</button>
            <button onclick="saveDashboardPreferences()" class="px-4 py-2 text-sm font-medium text-white bg-teal-600 hover:bg-teal-700 rounded-lg transition-colors">Save Changes</button>
        </div>
    </div>
</div>

<div class="max-w-6xl mx-auto px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-semibold text-slate-800">Home</h1>
        <div class="text-sm text-teal-600 hover:text-teal-700 cursor-pointer font-medium" onclick="document.getElementById('customizeModal').classList.remove('hidden')">Customize</div>
    </div>

    <?php if (!empty($prefs['show_stats'])): ?>
    <!-- Quick Stats / Status -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
        <div onclick="window.location.href='/bathyal/tasks?status=completed'" class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm text-center transform transition duration-300 hover:-translate-y-1 hover:shadow-md cursor-pointer">
            <p class="text-3xl font-light text-slate-700"><?= $stats['tasks_completed'] ?></p>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mt-1">Tasks Completed</p>
        </div>
        <div onclick="window.location.href='/bathyal/teams'" class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm text-center transform transition duration-300 hover:-translate-y-1 hover:shadow-md cursor-pointer">
            <p class="text-3xl font-light text-indigo-600"><?= $stats['collaborators'] ?></p>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mt-1">Collaborators</p>
        </div>
        <div onclick="window.location.href='/bathyal/tasks?filter=due_soon'" class="bg-gradient-to-br from-teal-500 to-cyan-600 rounded-xl border border-transparent p-5 shadow-sm text-center text-white transform transition duration-300 hover:-translate-y-1 hover:shadow-md cursor-pointer">
            <p class="text-3xl font-light"><?= $stats['tasks_due_soon'] ?></p>
            <p class="text-xs font-semibold text-cyan-50 uppercase tracking-wide mt-1">Tasks Due Soon</p>
        </div>
        <!-- Time tracked box -->
        <?php if ($activeTimer): ?>
        <div onclick="window.location.href='/bathyal/project?id=<?= $activeTimer['project_id'] ?>&task_id=<?= $activeTimer['task_id'] ?>'" class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm flex flex-col justify-center items-center cursor-pointer hover:-translate-y-1 hover:shadow-md transition duration-300">
            <div class="flex items-center text-emerald-600">
                <span class="relative flex h-3 w-3 mr-2">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                </span>
                <span class="text-xl font-medium font-mono" id="activeTimerClock" data-start="<?= strtotime($activeTimer['start_time']) ?>">00:00:00</span>
            </div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mt-1 text-center truncate w-full" title="<?= htmlspecialchars($activeTimer['title']) ?>"><?= htmlspecialchars($activeTimer['title']) ?></p>
        </div>
        <?php else: ?>
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm flex flex-col justify-center items-center">
            <div class="flex items-center text-slate-400">
                <span class="text-xl font-medium font-mono">--:--:--</span>
            </div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mt-1 text-center">No Active Timer</p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- My Priorities & Tasks (The Asana "My Tasks" vibe) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        
        <?php if (!empty($prefs['show_priorities'])): ?>
        <!-- Left Column: Tasks -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col h-96">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/80">
                <h2 class="text-sm font-semibold text-slate-700">My Priorities</h2>
                <div class="flex space-x-2">
                    <button onclick="togglePriorityTab('upcoming')" id="tab-upcoming" class="text-xs text-teal-600 font-medium bg-teal-50 hover:bg-teal-100 px-2 py-1 rounded transition-colors">Upcoming</button>
                    <button onclick="togglePriorityTab('overdue')" id="tab-overdue" class="text-xs text-slate-500 font-medium hover:bg-slate-100 px-2 py-1 rounded transition-colors">Overdue <?php if(count($overdueTasks)>0): ?><span class="text-rose-500">(<?=count($overdueTasks)?>)</span><?php endif; ?></button>
                </div>
            </div>
            
            <div class="flex-1 overflow-y-auto p-2">
                <!-- Task List Upcoming -->
                <ul id="list-upcoming" class="space-y-1">
                    <?php if (empty($upcomingTasks)): ?>
                        <li class="px-3 py-4 text-center text-sm text-slate-400">No upcoming tasks!</li>
                    <?php else: ?>
                        <?php foreach ($upcomingTasks as $task): ?>
                        <li id="dash-task-<?= $task['id'] ?>" class="flex items-center px-3 py-2.5 hover:bg-slate-50 rounded-lg group cursor-pointer border border-transparent hover:border-slate-200 transition-all" onclick="window.location.href='/bathyal/project?id=<?= $task['project_id'] ?>&task_id=<?= $task['id'] ?>'">
                            <button onclick="completeDashboardTask(event, <?= $task['id'] ?>)" class="w-5 h-5 rounded-full border border-slate-300 mr-3 flex items-center justify-center hover:border-teal-500 hover:bg-teal-50 flex-shrink-0 transition-colors">
                                <svg class="w-3 h-3 text-transparent group-hover:text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            </button>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-slate-800 truncate"><?= htmlspecialchars($task['title']) ?></p>
                                <p class="text-xs text-slate-400 mt-0.5 truncate"><?= htmlspecialchars($task['project_name'] ?? 'No Project') ?></p>
                            </div>
                            <div class="ml-4 flex-shrink-0 flex items-center text-xs text-slate-400">
                                <?php if ($task['expected_due_date']): ?>
                                    <span class="text-slate-500"><?= convertUtcToToronto($task['expected_due_date'], 'M j') ?></span>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>

                <!-- Task List Overdue -->
                <ul id="list-overdue" class="space-y-1 hidden">
                    <?php if (empty($overdueTasks)): ?>
                        <li class="px-3 py-4 text-center text-sm text-slate-400">No overdue tasks!</li>
                    <?php else: ?>
                        <?php foreach ($overdueTasks as $task): ?>
                        <li id="dash-task-<?= $task['id'] ?>" class="flex items-center px-3 py-2.5 hover:bg-slate-50 rounded-lg group cursor-pointer border border-transparent hover:border-slate-200 transition-all" onclick="window.location.href='/bathyal/project?id=<?= $task['project_id'] ?>&task_id=<?= $task['id'] ?>'">
                            <button onclick="completeDashboardTask(event, <?= $task['id'] ?>)" class="w-5 h-5 rounded-full border border-slate-300 mr-3 flex items-center justify-center hover:border-teal-500 hover:bg-teal-50 flex-shrink-0 transition-colors">
                                <svg class="w-3 h-3 text-transparent group-hover:text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            </button>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-slate-800 truncate"><?= htmlspecialchars($task['title']) ?></p>
                                <p class="text-xs text-slate-400 mt-0.5 truncate"><?= htmlspecialchars($task['project_name'] ?? 'No Project') ?></p>
                            </div>
                            <div class="ml-4 flex-shrink-0 flex items-center text-xs text-slate-400">
                                <?php if ($task['expected_due_date']): ?>
                                    <span class="text-rose-500 font-medium"><?= convertUtcToToronto($task['expected_due_date'], 'M j') ?></span>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
                
                <button onclick="window.location.href='/bathyal/tasks'" class="flex items-center text-sm text-slate-500 hover:text-teal-600 mt-3 px-3 py-2 transition-colors group w-full">
                    <div class="w-5 h-5 rounded-full border border-slate-300 group-hover:border-teal-500 mr-3 flex items-center justify-center">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    </div>
                    Add task
                </button>
            </div>
        </div>
        <?php else: ?>
        <div></div> <!-- Placeholder for grid -->
        <?php endif; ?>

        <?php if (!empty($prefs['show_projects'])): ?>
        <!-- Right Column: Projects / Inbox summary -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col h-96">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/80">
                <h2 class="text-sm font-semibold text-slate-700">Recent Projects</h2>
            </div>
            
            <div class="flex-1 overflow-y-auto p-4 space-y-3">
                <?php if (!empty($recent_projects)): ?>
                    <?php foreach ($recent_projects as $idx => $proj): 
                        // Generate initials
                        $words = explode(' ', trim($proj['name']));
                        $initials = strtoupper(substr($words[0], 0, 1));
                        if(isset($words[1])) {
                            $initials .= strtoupper(substr($words[1], 0, 1));
                        }
                        
                        // Select colors from an array based on id for variety
                        $colors = [
                            ['bg-teal-100', 'text-teal-700', 'hover:border-teal-300', 'group-hover:text-teal-700'],
                            ['bg-cyan-100', 'text-cyan-700', 'hover:border-cyan-300', 'group-hover:text-cyan-700'],
                            ['bg-indigo-100', 'text-indigo-700', 'hover:border-indigo-300', 'group-hover:text-indigo-700'],
                            ['bg-rose-100', 'text-rose-700', 'hover:border-rose-300', 'group-hover:text-rose-700'],
                            ['bg-amber-100', 'text-amber-700', 'hover:border-amber-300', 'group-hover:text-amber-700']
                        ];
                        $theme = $colors[$idx % count($colors)];
                    ?>
                    <!-- Project Item -->
                    <div class="group flex items-center p-3 border border-slate-100 rounded-lg <?= $theme[2] ?> hover:shadow-sm cursor-pointer transition-all" onclick="window.location.href='/bathyal/project?id=<?= $proj['id'] ?>'">
                        <div class="w-10 h-10 rounded-lg <?= $theme[0] ?> flex items-center justify-center <?= $theme[1] ?> font-bold mr-4">
                            <?= htmlspecialchars($initials) ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-medium text-slate-800 truncate <?= $theme[3] ?>"><?= htmlspecialchars($proj['name']) ?></h3>
                            <p class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars(ucfirst($proj['status'] ?? 'active')) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center h-full text-slate-400">
                        <svg class="w-12 h-12 mb-3 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        <p class="text-sm font-medium">No projects created yet</p>
                    </div>
                <?php endif; ?>
            </div>
            <div onclick="window.location.href='/bathyal/projects'" class="px-5 py-3 bg-slate-50 text-center border-t border-slate-100 cursor-pointer hover:bg-slate-100 transition-colors">
                <span class="text-sm text-teal-600 font-medium">View all projects</span>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function togglePriorityTab(tab) {
    if (tab === 'upcoming') {
        document.getElementById('list-upcoming').classList.remove('hidden');
        document.getElementById('list-overdue').classList.add('hidden');
        document.getElementById('tab-upcoming').className = "text-xs text-teal-600 font-medium bg-teal-50 hover:bg-teal-100 px-2 py-1 rounded transition-colors";
        document.getElementById('tab-overdue').className = "text-xs text-slate-500 font-medium hover:bg-slate-100 px-2 py-1 rounded transition-colors";
    } else {
        document.getElementById('list-upcoming').classList.add('hidden');
        document.getElementById('list-overdue').classList.remove('hidden');
        document.getElementById('tab-overdue').className = "text-xs text-rose-600 font-medium bg-rose-50 hover:bg-rose-100 px-2 py-1 rounded transition-colors";
        document.getElementById('tab-upcoming').className = "text-xs text-slate-500 font-medium hover:bg-slate-100 px-2 py-1 rounded transition-colors";
    }
}

function completeDashboardTask(e, taskId) {
    e.stopPropagation();
    
    fetch('/bathyal/api/tasks.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update_status', task_id: taskId, status: 'completed' })
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            const el = document.getElementById('dash-task-' + taskId);
            if (el) {
                el.classList.add('opacity-50', 'line-through');
                setTimeout(() => el.remove(), 1000);
            }
        }
    });
}

function saveDashboardPreferences() {
    const prefs = {
        show_stats: document.getElementById('pref_stats').checked,
        show_priorities: document.getElementById('pref_priorities').checked,
        show_projects: document.getElementById('pref_projects').checked
    };

    fetch('/bathyal/api/users.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update_preferences', preferences: prefs })
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            window.location.reload();
        } else {
showAlert('Error', 'Failed to save preferences', 'danger');
        }
    });
}

// Active timer live clock
const activeClock = document.getElementById('activeTimerClock');
if (activeClock) {
    const startTimestamp = parseInt(activeClock.getAttribute('data-start'));
    if (!isNaN(startTimestamp)) {
        setInterval(() => {
            const now = Math.floor(Date.now() / 1000);
            const diff = now - startTimestamp;
            if (diff >= 0) {
                const h = Math.floor(diff / 3600).toString().padStart(2, '0');
                const m = Math.floor((diff % 3600) / 60).toString().padStart(2, '0');
                const s = Math.floor(diff % 60).toString().padStart(2, '0');
                activeClock.innerText = `${h}:${m}:${s}`;
            }
        }, 1000);
    }
}
</script>

<?php
require_once 'views/layouts/footer.php';
?>