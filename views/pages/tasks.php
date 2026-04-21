<?php
// /views/pages/tasks.php

require_once 'core/database.php';
require_once 'core/auth_check.php';
require_once 'core/db_query.php';

// Simple routing logic could go here
$request_uri = $_SERVER['REQUEST_URI'];

// Basic entry point
require_once 'views/layouts/header.php';

$db = new DBQueries($pdo);

// Assuming user auth logic exists to fetch user ID
// For now, let's assume user ID 1 is the logged-in user
$userId = 1;

// Fetch tasks for the user
$tasks = $db->getTasksByAssigneeId($userId);


?>

<!-- Main Content Area -->
<div class="flex-1 flex flex-col h-screen overflow-hidden bg-slate-50 relative">

    <!-- Top Navigation / Toolbar -->
    <header class="h-16 flex items-center justify-between px-6 border-b border-slate-200 bg-white shrink-0 z-10 relative">
        <div class="flex items-center">
            <h1 class="text-xl font-semibold text-slate-800 tracking-tight flex items-center">
                <span class="bg-indigo-100 text-indigo-700 p-1.5 rounded-lg mr-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </span>
                My Tasks
            </h1>
        </div>

        <!-- Global Actions -->
        <div class="flex items-center space-x-3 relative z-30">
            <!-- Space reserved for future local task actions if needed -->
        </div>
    </header>

    <!-- Board Container -->
    <main class="flex-1 overflow-x-auto overflow-y-auto p-6 bg-slate-50/50 relative">

        <div class="max-w-6xl mx-auto">
            
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col min-h-[500px]">
                <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/80">
                    <h2 class="text-sm font-semibold text-slate-700">Tasks assigned to you</h2>
                    <div class="flex space-x-2 filter-buttons">
                        <button id="btn-filter-all" onclick="filterTasks('all', this)" class="filter-btn text-xs text-slate-500 font-medium hover:bg-slate-100 px-2 py-1 rounded transition-colors">All Tasks</button>
                        <button id="btn-filter-incomplete" onclick="filterTasks('incomplete', this)" class="filter-btn text-xs text-teal-600 bg-teal-50 font-medium px-2 py-1 rounded transition-colors">Incomplete</button>
                        <button id="btn-filter-done" onclick="filterTasks('done', this)" class="filter-btn text-xs text-slate-500 font-medium hover:bg-slate-100 px-2 py-1 rounded transition-colors">Completed</button>
                    </div>
                </div>
                
                <div class="flex-1 p-2">
                    <?php if (empty($tasks)): ?>
                        <div class="text-center py-10 opacity-70">
                            <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <p class="text-slate-500 font-medium">You have no tasks assigned to you right now.</p>
                            <p class="text-slate-400 text-sm mt-1">Enjoy the peace and quiet!</p>
                        </div>
                    <?php else: ?>
                        <!-- Main Tasks Table -->
                        <div class="w-full">
                            
                            <!-- Table Header -->
                            <div class="flex items-center text-xs font-medium text-slate-400 uppercase tracking-wider px-4 py-2 border-b border-slate-100">
                                <div class="flex-1 min-w-[200px]">Task Name</div>
                                <div class="w-48 shrink-0 hidden md:block">Project</div>
                                <div class="w-32 shrink-0">Expected End Date</div>
                                <div class="w-32 shrink-0 hidden sm:block">Status</div>
                            </div>

                            <!-- Task List -->
                            <div class="divide-y divide-slate-50">
                                <?php foreach ($tasks as $task): ?>
                                    
                                <?php 
                                    // Status styling logic 
                                    $statusClasses = '';
                                    if ($task['status'] === 'todo') $statusClasses = 'bg-slate-100 text-slate-600';
                                    elseif ($task['status'] === 'in_progress') $statusClasses = 'bg-amber-100 text-amber-700';
                                    elseif ($task['status'] === 'review') $statusClasses = 'bg-indigo-100 text-indigo-700';
                                    elseif ($task['status'] === 'done' || $task['status'] === 'completed') $statusClasses = 'bg-emerald-100 text-emerald-700';
                                    
                                    // Status text formatting
                                    $statusText = str_replace('_', ' ', $task['status']);
                                    $statusText = ucwords($statusText);
                                    
                                    // Determine if late
                                    $isLate = false;
                                    $dueDateText = '--';
                                    if ($task['expected_due_date']) {
                                        $dueDateText = date('M j', strtotime($task['expected_due_date']));
                                        if (strtotime($task['expected_due_date']) < time() && $task['status'] !== 'done' && $task['status'] !== 'completed') {
                                            $isLate = true;
                                        }
                                    }
                                    
                                    // Default filter visibility logic
                                    $isCompleted = ($task['status'] === 'done' || $task['status'] === 'completed');
                                ?>

                                <!-- Single Task Row -->
                                <div class="task-item flex items-center px-4 py-3 hover:bg-slate-50/80 group transition-colors cursor-pointer border-l-2 border-transparent hover:border-teal-400" style="<?= $isCompleted ? 'display: none;' : '' ?>" data-status="<?= htmlspecialchars($task['status']) ?>" <?= $task['project_id'] ? 'onclick="window.location.href=\'/bathyal/project?id='.$task['project_id'].'&task_id='.$task['id'].'\'"' : '' ?>>
                                    
                                    <!-- Task Name -->
                                    <div class="flex-1 min-w-[200px] flex items-center">
                                        <span class="text-sm font-medium text-slate-800 <?= $isCompleted ? 'line-through text-slate-400' : '' ?>">
                                            <?= htmlspecialchars($task['title']) ?>
                                        </span>
                                    </div>

                                    <!-- Project -->
                                    <div class="w-48 shrink-0 hidden md:flex items-center text-xs text-slate-500">
                                         <?php if($task['project_id']): ?>
                                            <a href="/bathyal/project?id=<?= $task['project_id'] ?>" class="hover:text-teal-600 hover:underline truncate mr-2 max-w-full" onclick="event.stopPropagation()">
                                                <?= htmlspecialchars($task['project_name'] ?? 'Unknown Project') ?>
                                            </a>
                                         <?php else: ?>
                                            <span class="text-slate-300 italic">No Project</span>
                                         <?php endif; ?>
                                    </div>

                                    <!-- Expected End Date -->
                                    <div class="w-32 shrink-0 flex items-center">
                                        <span class="text-xs font-medium <?= $isLate ? 'text-rose-500' : ($task['status'] === 'done' ? 'text-slate-400' : 'text-slate-600') ?>">
                                            <?= $dueDateText ?>
                                        </span>
                                    </div>

                                    <!-- Status -->
                                    <div class="w-32 shrink-0 hidden sm:flex items-center">
                                        <span class="px-2.5 py-1 text-[10px] font-semibold rounded-full <?= $statusClasses ?>">
                                            <?= $statusText ?>
                                        </span>
                                    </div>
                                    
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </main>
</div>

<script>
function filterTasks(filterType, element) {
    // Reset all buttons
    const buttons = document.querySelectorAll('.filter-btn');
    buttons.forEach(btn => {
        btn.classList.remove('text-teal-600', 'bg-teal-50');
        btn.classList.add('text-slate-500', 'hover:bg-slate-100');
    });

    // Make the clicked button active
    element.classList.remove('text-slate-500', 'hover:bg-slate-100');
    element.classList.add('text-teal-600', 'bg-teal-50');

    // Filter the items
    const tasks = document.querySelectorAll('.task-item');
    tasks.forEach(task => {
        const status = task.getAttribute('data-status');
        
        if (filterType === 'all') {
            task.style.display = '';
        } else if (filterType === 'incomplete') {
            if (status !== 'done' && status !== 'completed') {
                task.style.display = '';
            } else {
                task.style.display = 'none';
            }
        } else if (filterType === 'done') {
            if (status === 'done' || status === 'completed') {
                task.style.display = '';
            } else {
                task.style.display = 'none';
            }
        }
    });
}
</script>

<?php require_once 'views/layouts/footer.php'; ?>
