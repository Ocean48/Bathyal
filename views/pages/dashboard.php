<?php
// /index.php

require_once 'core/database.php';
require_once 'core/auth_check.php';

// Simple routing logic could go here
$request_uri = $_SERVER['REQUEST_URI'];

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

// Basic entry point
require_once 'views/layouts/header.php';
?>

<div class="max-w-6xl mx-auto px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-semibold text-slate-800">Home</h1>
        <div class="text-sm text-teal-600 hover:text-teal-700 cursor-pointer font-medium">Customize</div>
    </div>

    <!-- Quick Stats / Status -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm text-center transform transition duration-300 hover:-translate-y-1 hover:shadow-md cursor-pointer">
            <p class="text-3xl font-light text-slate-700">12</p>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mt-1">Tasks Completed</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm text-center transform transition duration-300 hover:-translate-y-1 hover:shadow-md cursor-pointer">
            <p class="text-3xl font-light text-indigo-600">3</p>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mt-1">Collaborators</p>
        </div>
        <div class="bg-gradient-to-br from-teal-500 to-cyan-600 rounded-xl border border-transparent p-5 shadow-sm text-center text-white transform transition duration-300 hover:-translate-y-1 hover:shadow-md cursor-pointer">
            <p class="text-3xl font-light">5</p>
            <p class="text-xs font-semibold text-cyan-50 uppercase tracking-wide mt-1">Tasks Due Soon</p>
        </div>
        <!-- Time tracked box -->
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm flex flex-col justify-center items-center">
            <div class="flex items-center text-emerald-600">
                <span class="relative flex h-3 w-3 mr-2">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                </span>
                <span class="text-xl font-medium font-mono">01:24:05</span>
            </div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mt-1 text-center">Active Timer</p>
        </div>
    </div>

    <!-- My Priorities & Tasks (The Asana "My Tasks" vibe) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        
        <!-- Left Column: Tasks -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col h-96">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/80">
                <h2 class="text-sm font-semibold text-slate-700">My Priorities</h2>
                <div class="flex space-x-2">
                    <button class="text-xs text-teal-600 font-medium hover:bg-teal-50 px-2 py-1 rounded">Upcoming</button>
                    <button class="text-xs text-slate-500 font-medium hover:bg-slate-100 px-2 py-1 rounded">Overdue</button>
                </div>
            </div>
            
            <div class="flex-1 overflow-y-auto p-2">
                <!-- Task List -->
                <ul class="space-y-1">
                    <!-- Task Item -->
                    <li class="flex items-center px-3 py-2.5 hover:bg-slate-50 rounded-lg group cursor-pointer border border-transparent hover:border-slate-200 transition-all">
                        <button class="w-5 h-5 rounded-full border border-slate-300 mr-3 flex items-center justify-center hover:border-teal-500 hover:bg-teal-50 flex-shrink-0 transition-colors">
                            <svg class="w-3 h-3 text-transparent group-hover:text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        </button>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-800 truncate">Draft AI trigger automations</p>
                            <p class="text-xs text-slate-400 mt-0.5 truncate">Website Redesign</p>
                        </div>
                        <div class="ml-4 flex-shrink-0 flex items-center text-xs text-slate-400">
                            <span class="text-rose-500 font-medium">Tomorrow</span>
                        </div>
                    </li>
                    
                    <!-- Task Item -->
                    <li class="flex items-center px-3 py-2.5 hover:bg-slate-50 rounded-lg group cursor-pointer border border-transparent hover:border-slate-200 transition-all">
                        <button class="w-5 h-5 rounded-full border border-slate-300 mr-3 flex items-center justify-center hover:border-teal-500 hover:bg-teal-50 flex-shrink-0 transition-colors">
                            <svg class="w-3 h-3 text-transparent group-hover:text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        </button>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-800 truncate">Review Kanban board drag-and-drop</p>
                            <p class="text-xs text-slate-400 mt-0.5 truncate">Bathyal MVP</p>
                        </div>
                        <div class="ml-4 flex-shrink-0 flex items-center text-xs text-slate-400">
                            <span class="text-slate-500">Apr 21</span>
                        </div>
                    </li>
                </ul>
                
                <button class="flex items-center text-sm text-slate-500 hover:text-teal-600 mt-3 px-3 py-2 transition-colors group">
                    <div class="w-5 h-5 rounded-full border border-slate-300 group-hover:border-teal-500 mr-3 flex items-center justify-center">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    </div>
                    Add task
                </button>
            </div>
        </div>

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
            <div class="px-5 py-3 bg-slate-50 text-center border-t border-slate-100 cursor-pointer hover:bg-slate-100 transition-colors">
                <span class="text-sm text-teal-600 font-medium">View all projects</span>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'views/layouts/footer.php';
?>