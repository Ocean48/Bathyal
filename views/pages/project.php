<?php
// /project.php

require_once 'core/database.php';
require_once 'core/auth_check.php';

// Include the UI wrapper
require_once 'views/layouts/header.php';
?>

<div class="flex flex-col h-full bg-slate-50">
    <!-- Project Header -->
    <header class="px-6 py-4 border-b border-slate-200 bg-white flex justify-between items-center shrink-0">
        <div class="flex items-center">
            <div class="w-8 h-8 rounded bg-teal-100 flex items-center justify-center text-teal-700 font-bold mr-3 shadow-sm">
                <svg class="w-4 h-4" auto fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
            </div>
            <h1 class="text-xl font-semibold text-slate-800" id="project-title">Loading...</h1>
            <span class="ml-3 px-2.5 py-0.5 rounded-full bg-slate-100 text-xs font-medium text-slate-600" id="project-status">--</span>
        </div>
        <div class="flex space-x-3 items-center">
            <div class="flex -space-x-2 mr-4" id="project-members">
                <!-- Avatars will be injected here -->
                <img class="w-8 h-8 rounded-full border-2 border-white cursor-pointer" src="https://ui-avatars.com/api/?name=Admin+User&background=0D8ABC&color=fff" alt="Member">
                <img class="w-8 h-8 rounded-full border-2 border-white cursor-pointer" src="https://ui-avatars.com/api/?name=John+Doe&background=0284C7&color=fff" alt="Member">
            </div>
            <button class="text-slate-500 hover:text-slate-700 px-3 py-1.5 rounded-md text-sm font-medium transition-colors border border-slate-200 bg-white shadow-sm" onclick="alert('Share link copied to clipboard')">Share</button>
            <button onclick="openTaskModal(true)" class="bg-teal-500 hover:bg-teal-600 text-white px-4 py-1.5 rounded-md text-sm font-medium shadow-sm transition-colors flex items-center">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Add Task
            </button>
        </div>
    </header>

    <!-- Sub Navigation for Project Views -->
    <div class="px-6 border-b border-slate-200 bg-white shrink-0 flex space-x-6" id="view-tabs">
        <button onclick="switchView('board')" id="tab-board" class="border-b-2 border-teal-500 text-teal-600 py-2.5 px-1 text-sm font-medium">Board</button>
        <button onclick="switchView('list')" id="tab-list" class="border-b-2 border-transparent text-slate-500 hover:text-slate-700 py-2.5 px-1 text-sm font-medium">List</button>
        <button onclick="switchView('timeline')" id="tab-timeline" class="border-b-2 border-transparent text-slate-500 hover:text-slate-700 py-2.5 px-1 text-sm font-medium">Timeline</button>
        <button onclick="switchView('dashboard')" id="tab-dashboard" class="border-b-2 border-transparent text-slate-500 hover:text-slate-700 py-2.5 px-1 text-sm font-medium">Dashboard</button>
    </div>

    <!-- Views Container -->
    <div class="flex-1 overflow-hidden relative flex flex-col">
        <!-- Board View -->
        <div id="view-board" class="flex-1 overflow-x-auto overflow-y-hidden p-6 view-panel">
            <div id="kanban-board" class="flex space-x-4 h-full items-start pb-4">
            <!-- Example Section/Column -->
            <div class="bg-slate-100 rounded-lg w-80 shrink-0 flex flex-col max-h-full">
                <div class="p-3 border-b border-slate-200 flex justify-between items-center bg-slate-100 rounded-t-lg">
                    <h3 class="font-semibold text-slate-700">To Do <span class="text-xs font-normal text-slate-500 ml-1">3</span></h3>
                    <div class="flex space-x-1">
                        <button class="text-slate-400 hover:text-slate-600 p-1" title="Uncheck all subtasks" onclick="uncheckSectionSubtasks(this)">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                        </button>
                        <button class="text-slate-400 hover:text-slate-600 p-1" onclick="alert('Section options open')"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg></button>
                    </div>
                </div>
                <div class="p-2 overflow-y-auto flex-1 space-y-2 min-h-[50px] dropzone">
                    <!-- Example Task Card -->
                    <div class="bg-white p-3 rounded shadow-sm border border-slate-200 cursor-grab hover:border-teal-400 transition-colors draggable-task task-card" onclick="openTaskModal()">
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex flex-wrap gap-1">
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-100 text-blue-700">Frontend</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-red-100 text-red-700">High Priority</span>
                            </div>
                        </div>
                        <h4 class="text-sm font-medium text-slate-800 mb-1">Implement Drag & Drop</h4>
                        <div class="flex items-center text-xs text-slate-500 mb-3">
                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Due: Tomorrow
                        </div>
                        <div class="flex justify-between items-center">
                            <div class="flex items-center space-x-2 text-xs text-slate-400">
                                <span title="Estimated exactly 4 hrs" class="flex items-center"><svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg> 4h</span>
                                <span class="flex items-center"><svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg> 1/3</span>
                            </div>
                            <img class="w-6 h-6 rounded-full" src="https://ui-avatars.com/api/?name=Admin+User&background=0D8ABC&color=fff" alt="Assignee">
                        </div>
                    </div>
                </div>
                <div class="p-2 bg-slate-100 rounded-b-lg">
                    <button onclick="openTaskModal(true)" class="w-full text-left text-sm text-slate-500 hover:text-slate-700 hover:bg-slate-200 p-1.5 rounded flex items-center transition-colors">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        Add Task
                    </button>
                </div>
            </div>
            
            <button onclick="addSection()" class="shrink-0 w-80 bg-slate-50 hover:bg-slate-100 border-2 border-dashed border-slate-300 rounded-lg p-3 text-slate-500 font-medium flex items-center justify-center transition-colors h-14">
                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Add Section
            </button>
        </div>
        </div>

        <!-- List View -->
        <div id="view-list" class="flex-1 overflow-auto p-6 hidden view-panel">
            <div class="bg-white rounded-lg shadow-sm border border-slate-200">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 font-medium">Task / Subtask</th>
                            <th class="px-4 py-3 font-medium">Assignee</th>
                            <th class="px-4 py-3 font-medium">Due Date</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr class="hover:bg-slate-50 cursor-pointer" onclick="openTaskModal()">
                            <td class="px-4 py-3 font-medium text-slate-800">Implement Drag & Drop</td>
                            <td class="px-4 py-3"><img class="w-6 h-6 rounded-full inline" src="https://ui-avatars.com/api/?name=Admin+User&background=0D8ABC&color=fff" alt="Assignee"> Admin User</td>
                            <td class="px-4 py-3 text-slate-500">Tomorrow</td>
                            <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-xs bg-slate-100 text-slate-600">To Do</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Timeline View -->
        <div id="view-timeline" class="flex-1 overflow-auto p-6 hidden view-panel flex items-center justify-center">
            <div class="text-center text-slate-400">
                <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                Timeline (Gantt Chart) rendering module initializing...
            </div>
        </div>

        <!-- Dashboard View -->
        <div id="view-dashboard" class="flex-1 overflow-auto p-6 hidden view-panel flex items-center justify-center">
            <div class="text-center text-slate-400">
                <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                Metrics Dashboard loading charts...
            </div>
        </div>
    </div>
</div>

<!-- Task Detail Modal Backdrop -->
<div id="task-modal" class="fixed inset-0 bg-slate-900/50 hidden z-50 flex justify-end">
    <!-- Sliding Panel -->
    <div class="bg-white w-full max-w-2xl h-full shadow-2xl flex flex-col animate-slide-in-right">
        <!-- Modal Header -->
        <div class="flex justify-between items-center px-6 py-4 border-b border-slate-200">
            <div class="flex space-x-3 items-center">
                <button class="bg-emerald-500 hover:bg-emerald-600 text-white px-3 py-1.5 rounded text-sm font-medium shadow-sm flex items-center transition-colors" onclick="toggleProgress(this)">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Start Progress
                </button>
                <div class="text-sm font-medium text-slate-500 bg-slate-100 px-2.5 py-1 rounded" id="time-tracker-display">00:00:00</div>
            </div>
            <div class="flex items-center space-x-4 text-slate-500">
                <button class="hover:text-slate-800" title="Attach to another project" onclick="attachToProject()"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg></button>
                <button class="hover:text-slate-800" title="Options" onclick="showTaskOptions()"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg></button>
                <button class="hover:text-red-500" onclick="closeTaskModal()"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
            </div>
        </div>
        
        <!-- Modal Content -->
        <div class="flex-1 overflow-y-auto p-6">
            <input type="text" class="text-2xl font-bold text-slate-800 w-full mb-6 border-none focus:ring-0 p-0" value="Implement Drag & Drop">
            
            <!-- Metadata Grid -->
            <div class="grid grid-cols-2 gap-y-4 gap-x-8 mb-8">
                <div>
                    <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1 block">Assignee</label>
                    <div class="flex items-center space-x-2 cursor-pointer hover:bg-slate-50 p-1 -ml-1 rounded transition-colors w-fit">
                        <img class="w-6 h-6 rounded-full" src="https://ui-avatars.com/api/?name=Admin+User&background=0D8ABC&color=fff" alt="Assignee">
                        <span class="text-sm text-slate-700">Admin User</span>
                    </div>
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1 block">Due Date</label>
                    <input type="date" class="text-sm text-slate-700 border-none focus:ring-0 p-0 hover:bg-slate-50 rounded cursor-pointer">
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1 block">Projects</label>
                    <div class="flex flex-wrap gap-2">
                        <span class="px-2.5 py-1 bg-slate-100 text-slate-700 rounded text-xs font-medium border border-slate-200">Main Project</span>
                        <button class="px-2.5 py-1 text-slate-500 border border-dashed border-slate-300 rounded text-xs hover:bg-slate-50 transition-colors">+</button>
                    </div>
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1 block">Est. Time</label>
                    <input type="text" value="4h 30m" class="text-sm text-slate-700 border-none focus:ring-0 p-0 hover:bg-slate-50 rounded w-full">
                </div>
            </div>

            <!-- Description -->
            <div class="mb-8">
                <label class="font-semibold text-slate-800 mb-2 block">Description</label>
                <div id="task-desc" class="prose prose-sm text-slate-600 border border-transparent hover:border-slate-200 p-2 -ml-2 rounded cursor-text ring-1 ring-slate-100 min-h-[100px]" contenteditable="true">
                    Users need to be able to drag tasks across columns. Hyperlinks should autolink like https://example.com.
                </div>
            </div>

            <!-- Subtasks -->
            <div class="mb-8">
                <div class="flex justify-between items-center mb-3">
                    <label class="font-semibold text-slate-800">Subtasks</label>
                    <button onclick="addSubtask(this)" class="text-xs text-teal-600 font-medium hover:text-teal-700">Add Subtask</button>
                </div>
                <div class="space-y-2" id="subtask-list">
                    <div class="flex items-center space-x-3 group">
                        <input type="checkbox" class="rounded text-teal-500 focus:ring-teal-500 focus:ring-offset-0 w-4 h-4 cursor-pointer">
                        <input type="text" class="flex-1 text-sm border-none focus:ring-0 p-0 bg-transparent text-slate-700" value="Setup Sortable.js">
                        <button onclick="this.parentElement.remove()" class="opacity-0 group-hover:opacity-100 text-slate-400 hover:text-red-500 transition-opacity"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                    </div>
                    <!-- Trigger demo -->
                    <div class="ml-7 pt-1 hidden group-hover:block transition-all">
                        <span class="text-xs text-amber-600 bg-amber-50 px-2 py-0.5 rounded flex items-center w-fit border border-amber-200 cursor-pointer">
                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            Add Trigger: Start Next Task Automations
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
let timerInterval;
let timerSeconds = 0;
let isProgressRunning = false;

function switchView(viewName) {
    // Highlight correct tab
    document.querySelectorAll('#view-tabs button').forEach(btn => {
        btn.classList.remove('border-teal-500', 'text-teal-600');
        btn.classList.add('border-transparent', 'text-slate-500');
    });
    
    const activeTab = document.getElementById('tab-' + viewName);
    if(activeTab) {
        activeTab.classList.remove('border-transparent', 'text-slate-500');
        activeTab.classList.add('border-teal-500', 'text-teal-600');
    }

    // Show correct view container
    document.querySelectorAll('.view-panel').forEach(panel => {
        panel.classList.add('hidden');
    });
    
    const activeView = document.getElementById('view-' + viewName);
    if(activeView) {
        activeView.classList.remove('hidden');
    }
}

function openTaskModal(isNew = false) {
    const modal = document.getElementById('task-modal');
    modal.classList.remove('hidden');
    
    // Auto-link functionality wrapper
    const desc = document.getElementById('task-desc');
    if (desc) {
        desc.addEventListener('blur', function() {
            autoLinkText(this);
        });
    }

    if (isNew) {
        document.getElementById('task-title-input').value = "New Task";
        document.getElementById('timer-display').innerText = "00:00:00";
        stopTimer(true);
    }
}

function closeTaskModal() {
    document.getElementById('task-modal').classList.add('hidden');
}

function toggleProgress() {
    isProgressRunning = !isProgressRunning;
    const btn = document.getElementById('btn-start-progress');
    const span = btn.querySelector('span');
    
    if (isProgressRunning) {
        btn.classList.replace('bg-emerald-500', 'bg-amber-500');
        btn.classList.replace('hover:bg-emerald-600', 'hover:bg-amber-600');
        span.innerText = "Pause Progress";
        
        timerInterval = setInterval(() => {
            timerSeconds++;
            const h = String(Math.floor(timerSeconds / 3600)).padStart(2, '0');
            const m = String(Math.floor((timerSeconds % 3600) / 60)).padStart(2, '0');
            const s = String(timerSeconds % 60).padStart(2, '0');
            document.getElementById('timer-display').innerText = `${h}:${m}:${s}`;
        }, 1000);
    } else {
        btn.classList.replace('bg-amber-500', 'bg-emerald-500');
        btn.classList.replace('hover:bg-amber-600', 'hover:bg-emerald-600');
        span.innerText = "Start Progress";
        clearInterval(timerInterval);
    }
}

function stopTimer(reset = false) {
    clearInterval(timerInterval);
    isProgressRunning = false;
    const btn = document.getElementById('btn-start-progress');
    if(btn) {
        btn.classList.replace('bg-amber-500', 'bg-emerald-500');
        btn.classList.replace('hover:bg-amber-600', 'hover:bg-emerald-600');
        btn.querySelector('span').innerText = "Start Progress";
    }
    if (reset) timerSeconds = 0;
}

function addSection() {
    // Dynamically insert a new column into the Kanban board
    const newSectionHtml = `
        <div class="bg-slate-100 rounded-lg w-80 shrink-0 flex flex-col max-h-full">
            <div class="p-3 border-b border-slate-200 flex justify-between items-center bg-slate-100 rounded-t-lg">
                <h3 class="font-semibold text-slate-700" contenteditable="true" onblur="this.contentEditable=false" onclick="this.contentEditable=true; this.focus()">New Section <span class="text-xs font-normal text-slate-500 ml-1">0</span></h3>
                <div class="flex space-x-1">
                    <button class="text-slate-400 hover:text-slate-600 p-1" title="Uncheck all subtasks" onclick="uncheckSectionSubtasks(this)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    </button>
                    <button class="text-slate-400 hover:text-slate-600 p-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg></button>
                </div>
            </div>
            <div class="p-2 overflow-y-auto flex-1 space-y-2 min-h-[50px] dropzone"></div>
            <div class="p-2 bg-slate-100 rounded-b-lg">
                <button onclick="openTaskModal(true)" class="w-full text-left text-sm text-slate-500 hover:text-slate-700 hover:bg-slate-200 p-1.5 rounded flex items-center transition-colors">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Add Task
                </button>
            </div>
        </div>
    `;

    const board = document.getElementById('kanban-board');
    const addButton = board.lastElementChild;
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = newSectionHtml.trim();
    const newSection = tempDiv.firstChild;
    board.insertBefore(newSection, addButton);
    
    // Re-initialize sortable on the new column
    const newList = newSection.querySelector('.dropzone');
    new Sortable(newList, {
        group: 'shared',
        animation: 150,
        ghostClass: 'bg-slate-50',
        dragClass: 'opacity-50'
    });
}

function uncheckSectionSubtasks(btnElement) {
    // Traverse from header to the container holding the tasks/subtasks
    const container = btnElement.closest('.bg-slate-100');
    // Logically subtasks would be inside tasks, but this is mocking it since it's a structural demo
    const checkboxes = container.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(cb => cb.checked = false);
    
    // In our specific modal we have subtasks checkable 
    const modalCheckboxes = document.querySelectorAll('#subtask-list input[type="checkbox"]');
    modalCheckboxes.forEach(cb => cb.checked = false);

    alert('All subtasks in section unchecked');
}

function addSubtask(btn) {
    const list = document.getElementById('subtask-list');
    const newItem = document.createElement('div');
    newItem.className = 'flex items-center space-x-3 group';
    newItem.innerHTML = `
        <input type="checkbox" class="rounded text-teal-500 focus:ring-teal-500 focus:ring-offset-0 w-4 h-4 cursor-pointer">
        <input type="text" class="flex-1 text-sm border-none focus:ring-0 p-0 bg-transparent text-slate-700" value="New Subtask" autofocus>
        <button onclick="this.parentElement.remove()" class="opacity-0 group-hover:opacity-100 text-slate-400 hover:text-red-500 transition-opacity"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
    `;
    list.appendChild(newItem);
}

function autoLinkText(element) {
    const html = element.innerHTML;
    // Basic regex for auto-linking http/https URLs not already in a tag
    const urlRegex = /(?<!href="|">)(https?:\/\/[^\s<]+)/g;
    if (urlRegex.test(html)) {
        element.innerHTML = html.replace(urlRegex, function(url) {
            return `<a href="${url}" target="_blank" class="text-blue-500 hover:underline" contenteditable="false">${url}</a>`;
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const list = document.querySelector('.dropzone');
    if (list && typeof Sortable !== 'undefined') {
        new Sortable(list, {
            group: 'shared',
            animation: 150,
            ghostClass: 'bg-slate-50',
            dragClass: 'opacity-50'
        });
    }
    switchView('list');
});
</script>

<style>
@keyframes slideInRight {
    from { transform: translateX(100%); }
    to { transform: translateX(0); }
}
.animate-slide-in-right {
    animation: slideInRight 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}
</style>

<?php 
require_once 'views/layouts/footer.php'; 
?>