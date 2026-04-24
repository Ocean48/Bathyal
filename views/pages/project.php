<?php
// /project.php

require_once 'core/database.php';
require_once 'core/auth_check.php';
require_once 'core/db_query.php';

// Track recent project access for current user
$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 1;
if (isset($currentUser['id'])) {
    $dbQueries = new DBQueries($pdo);
    // Check if user is member of project
    if (!$dbQueries->isProjectMember($projectId, $currentUser['id'])) {
        header("Location: /bathyal/projects");
        exit;
    }
    $dbQueries->trackProjectAccess($currentUser['id'], $projectId);
}

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
            <h1 class="text-xl font-semibold text-slate-800 leading-none" id="project-title">Loading...</h1>
            <select id="project-status" onchange="updateProjectStatus(this.value)" class="ml-3 px-4 py-1 mt-0.5 rounded-full text-[10px] font-bold tracking-wider uppercase border focus:outline-none focus:ring-2 focus:ring-teal-500 appearance-none cursor-pointer bg-slate-100 text-slate-600 border-slate-200 text-center" style="text-align-last: center; width: 95px;">
                <option value="planning">PLANNING</option>
                <option value="active">ACTIVE</option>
                <option value="completed">COMPLETED</option>
                <option value="archived">ARCHIVED</option>
            </select>
        </div>
        <div class="flex space-x-3 items-center relative">
            <div class="flex -space-x-2 mr-4 transition-colors" id="project-members" title="Manage Default Notifications">
                <!-- Avatars will be injected here by app.js based on role -->
                <div class="w-8 h-8 rounded-full border-2 border-white bg-slate-100 flex items-center justify-center text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                </div>
            </div>
            
            <!-- Project Member Dropdown -->
            <div id="project-member-dropdown" class="hidden absolute top-[110%] right-32 mt-1 w-64 bg-white border border-slate-200 rounded-lg shadow-lg z-50 max-h-64 flex-col">
                <div class="px-2 py-2 border-b border-slate-100 shrink-0">
                    <input type="text" id="project-member-search" oninput="searchProjectMembers(this.value)" class="w-full bg-slate-50 border border-slate-200 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" placeholder="Search people by name or email..." onclick="event.stopPropagation()">
                </div>
                <ul id="project-member-dropdown-list" class="overflow-y-auto flex-1 p-1 text-sm text-slate-600">
                    <!-- Project-added users injected here -->
                </ul>
            </div>

            <button class="text-slate-500 hover:text-slate-700 px-3 py-1.5 rounded-md text-sm font-medium transition-colors border border-slate-200 bg-white shadow-sm" onclick="copyProjectShareLink()">Share</button>
            <!-- <button onclick="promptAddTask(null)" class="bg-teal-500 hover:bg-teal-600 text-white px-4 py-1.5 rounded-md text-sm font-medium shadow-sm transition-colors flex items-center">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Add Task
            </button> -->
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
                        <button class="text-slate-400 hover:text-slate-600 p-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg></button>
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
            
            <button onclick="promptAddSection()" class="shrink-0 w-80 bg-slate-50 hover:bg-slate-100 border-2 border-dashed border-slate-300 rounded-lg p-3 text-slate-500 font-medium flex items-center justify-center transition-colors h-14">
                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Add Section
            </button>
        </div>
        </div>

        <!-- List View -->
        <div id="view-list" class="flex-1 overflow-auto p-6 hidden view-panel">
            <!-- Filter Bar -->
            <div class="mb-4 flex flex-wrap gap-3 items-center bg-white px-4 py-3 rounded-lg shadow-sm border border-slate-200">
                <span class="text-sm font-semibold text-slate-700 flex items-center shrink-0">
                    <svg class="w-4 h-4 mr-1.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                    Filters
                </span>
                
                <input type="text" id="filter-search-list" oninput="applyListFilters()" placeholder="Search tasks..." class="text-sm border border-slate-200 rounded-md px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-teal-500 w-48 sm:w-64 transition-shadow placeholder-slate-400">
                
                <select id="filter-status-list" onchange="applyListFilters()" class="text-sm border border-slate-200 rounded-md px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-teal-500 bg-slate-50 text-slate-600 appearance-none cursor-pointer pr-8 bg-no-repeat bg-[right_0.5rem_center] bg-[length:1em_1em] hover:bg-slate-100 transition-colors" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22currentColor%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E')">
                    <option value="">All Statuses</option>
                    <option value="todo">To Do</option>
                    <option value="in_progress">In Progress</option>
                    <option value="paused">Paused</option>
                    <option value="completed">Completed</option>
                </select>

                <select id="filter-assignee-list" onchange="applyListFilters()" class="text-sm border border-slate-200 rounded-md px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-teal-500 bg-slate-50 text-slate-600 appearance-none cursor-pointer pr-8 bg-no-repeat bg-[right_0.5rem_center] bg-[length:1em_1em] hover:bg-slate-100 transition-colors" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22currentColor%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E')">
                    <option value="">All Assignees</option>
                    <!-- Populated dynamically via loadProjectBoard in app.js -->
                </select>
                
                <button onclick="clearListFilters()" class="text-slate-400 hover:text-rose-500 text-xs font-medium ml-auto transition-colors flex items-center">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    Clear
                </button>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-slate-200 mb-4">
                <table class="w-full text-left text-sm text-slate-600" id="list-table">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 font-medium">Tasks</th>
                            <th class="px-4 py-3 font-medium">Assignees</th>
                            <th class="px-4 py-3 font-medium">Collaborators</th>
                            <th class="px-4 py-3 font-medium">Completed On</th>
                            <th class="px-4 py-3 font-medium">Status / Actions</th>
                        </tr>
                    </thead>
                    <!-- Tbodys injected per section -->
                </table>
            </div>
            
            <button onclick="promptAddSection()" class="w-full bg-slate-50 hover:bg-slate-100 border-2 border-dashed border-slate-300 rounded-lg p-3 text-slate-500 font-medium flex items-center justify-center transition-colors h-14">
                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Add Section
            </button>
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
<div id="task-modal" class="fixed inset-0 pointer-events-none hidden z-40 flex justify-end">
    <!-- Sliding Panel -->
    <div class="bg-white w-full max-w-2xl h-full shadow-2xl flex flex-col animate-slide-in-right pointer-events-auto border-l border-slate-200">
        <!-- Modal Header -->
        <div class="flex justify-between items-center px-6 py-4 border-b border-slate-200">
            <div class="flex space-x-3 items-center">
                <button id="btn-back-task" class="hidden text-slate-500 hover:bg-slate-100 hover:text-slate-800 p-1.5 rounded transition-colors -ml-2" title="Go Back to Previous Task" onclick="goBackToPreviousTask()">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </button>
                <div class="h-6 border-l border-slate-300 mx-1 hidden" id="task-modal-divider"></div>
                <button id="btn-start-progress" class="bg-emerald-500 hover:bg-emerald-600 text-white px-3 py-1.5 rounded text-sm font-medium shadow-sm flex items-center transition-colors" onclick="toggleProgress()">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Start Progress</span>
                </button>
                <div class="text-sm font-medium text-slate-500 bg-slate-100 px-2.5 py-1 rounded" id="timer-display">00:00:00</div>
            </div>
            <div class="flex items-center space-x-4 text-slate-500">
                <button class="hover:text-slate-800" title="Copy Task Link" onclick="copyTaskLink()"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg></button>
                <button class="hover:text-red-500" title="Close" onclick="closeTaskModal()"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
            </div>
        </div>
        
        <!-- Modal Content -->
        <div class="flex-1 overflow-y-auto p-6" id="task-modal-body">
            <input type="hidden" id="task-modal-id" value="">
            <input type="text" id="task-modal-title" onchange="updateTaskDetails()" class="text-2xl font-bold text-slate-800 w-full mb-6 border-none focus:ring-0 p-0" value="Loading...">
            
            <!-- Metadata Grid -->
            <div class="grid grid-cols-2 gap-y-4 gap-x-8 mb-8">
                <div class="relative">
                    <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1 block">Assignees</label>
                    <div class="flex items-center space-x-2 cursor-pointer hover:bg-slate-50 p-1.5 -ml-1.5 rounded-md transition-colors" onclick="toggleAssigneeDropdown(event)">
                        <div id="task-modal-assignees-stack" class="flex -space-x-2 overflow-hidden items-center hidden">
                        </div>
                        <span class="text-sm text-slate-700 ml-2" id="task-modal-assignee-name">Unassigned</span>
                    </div>
                    <!-- Dropdown for assignees -->
                    <div id="assignee-dropdown" class="hidden absolute top-full left-0 mt-1 w-64 bg-white border border-slate-200 rounded-lg shadow-lg z-50 max-h-64 flex-col">
                        <div class="p-2 border-b border-slate-100">
                            <input type="text" id="assignee-search" oninput="searchAssignees(this.value)" class="w-full bg-slate-50 border border-slate-200 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" placeholder="Search people by name or email..." onclick="event.stopPropagation()">
                        </div>
                        <ul id="assignee-dropdown-list" class="overflow-y-auto flex-1 p-1 text-sm text-slate-600">
                            <!-- Items here -->
                        </ul>
                    </div>
                </div>

                <div class="relative">
                    <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1 block">Collaborators</label>
                    <div class="flex items-center space-x-2 cursor-pointer hover:bg-slate-50 p-1.5 -ml-1.5 rounded-md transition-colors" onclick="toggleCollaboratorDropdown(event)">
                        <div id="task-modal-collaborators-stack" class="flex -space-x-2 overflow-hidden items-center hidden">
                        </div>
                        <span class="text-sm text-slate-700 ml-2" id="task-modal-collaborator-name">No collaborators</span>
                    </div>
                    <!-- Dropdown for collaborators -->
                    <div id="collaborator-dropdown" class="hidden absolute top-full left-0 mt-1 w-64 bg-white border border-slate-200 rounded-lg shadow-lg z-50 max-h-64 flex-col">
                        <div class="p-2 border-b border-slate-100">
                            <input type="text" id="collaborator-search" oninput="searchCollaborators(this.value)" class="w-full bg-slate-50 border border-slate-200 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" placeholder="Search people by name or email..." onclick="event.stopPropagation()">
                        </div>
                        <ul id="collaborator-dropdown-list" class="overflow-y-auto flex-1 p-1 text-sm text-slate-600">
                            <!-- Items here -->
                        </ul>
                    </div>
                </div>

                <div>
                    <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1 block">Expected Start Date</label>
                    <input type="date" id="task-modal-expected-start-date" onchange="updateTaskDetails()" class="text-sm text-slate-700 border-none focus:ring-0 p-0 hover:bg-slate-50 rounded cursor-pointer">
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1 block">Expected End Date</label>
                    <input type="date" id="task-modal-expected-due-date" onchange="updateTaskDetails()" class="text-sm text-slate-700 border-none focus:ring-0 p-0 hover:bg-slate-50 rounded cursor-pointer">
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1 block">Start Date</label>
                    <input type="date" id="task-modal-start-date" onchange="updateTaskDetails()" class="text-sm text-slate-700 border-none focus:ring-0 p-0 hover:bg-slate-50 rounded cursor-pointer">
                </div>
                <div class="hidden" id="task-modal-completed-date-container">
                    <label class="text-xs font-semibold text-emerald-600 uppercase tracking-wider mb-1 block">Completed On</label>
                    <span id="task-modal-completed-date" class="text-sm font-medium text-emerald-700"></span>
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1 block">Status</label>
                    <select id="task-modal-status" onchange="updateTaskDetails()" class="text-sm border-none focus:ring-0 p-0 rounded bg-transparent font-medium text-emerald-600">
                        <option value="todo">To Do</option>
                        <option value="in_progress">In Progress</option>
                        <option value="paused">Paused</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1 block" title="Estimated Time in minutes">Est. Time (min)</label>
                    <input type="number" id="task-modal-estimated" onchange="updateTaskDetails()" min="0" placeholder="0" class="text-sm text-slate-700 border border-transparent hover:border-slate-200 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 p-1 -ml-1 rounded cursor-pointer w-24">
                </div>
                <!-- Parent Task Selection Removed -->
            </div>
            
            <!-- Projects -->
            <div class="mb-8">
                <div class="flex justify-between items-center mb-2">
                    <label class="font-semibold text-slate-800">Projects</label>
                    <div class="relative">
                        <button onclick="toggleProjectLinkDropdown()" class="text-xs text-teal-600 font-medium hover:text-teal-700 whitespace-nowrap px-2 py-1 bg-teal-50 hover:bg-teal-100 rounded transition-colors flex items-center">
                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                            Add to Project
                        </button>
                        <div id="project-link-dropdown" class="hidden absolute right-0 top-full mt-2 w-72 bg-white border border-slate-200 rounded-lg shadow-xl z-50 flex-col whitespace-normal">
                            <div class="p-2 border-b border-slate-100 flex">
                                <input type="text" id="project-link-search" oninput="searchProjectsToLink(this.value)" class="w-full bg-slate-50 border border-slate-200 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" placeholder="Search projects..." autofocus>
                            </div>
                            <ul id="project-link-list" class="overflow-y-auto max-h-48 p-1 text-sm text-slate-600">
                                <li class="p-2 text-slate-400 italic text-xs">Type to search...</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div id="task-modal-projects" class="space-y-2">
                    <!-- Project rows added dynamically -->
                </div>
            </div>

            <!-- Description -->
            <div class="mb-8">
                <div class="flex justify-between items-center mb-2">
                    <label class="font-semibold text-slate-800 block">Description</label>
                    <button onclick="updateTaskDetails(true)" class="bg-teal-600 hover:bg-teal-700 text-white px-3 py-1 rounded text-xs font-medium shadow-sm transition-colors">Save Description</button>
                </div>
                <div class="border border-slate-300 rounded bg-white overflow-hidden flex flex-col min-h-[200px]">
                    <!-- RTE Toolbar -->
                    <div class="bg-slate-50 border-b border-slate-200 px-2 py-1.5 flex flex-wrap gap-1 items-center">
                        <button type="button" onmousedown="event.preventDefault(); formatText('bold', 'task-modal-desc')" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Bold">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6z M6 12h9a4 4 0 014 4 4 4 0 01-4 4H6z"></path></svg>
                        </button>
                        <button type="button" onmousedown="event.preventDefault(); formatText('italic', 'task-modal-desc')" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Italic">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 6h6M12 6v12M9 18h6"></path></svg>
                        </button>
                        <button type="button" onmousedown="event.preventDefault(); formatText('underline', 'task-modal-desc')" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Underline">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 3v7a6 6 0 006 6 6 6 0 006-6V3m-9 18h6"></path></svg>
                        </button>
                        <button type="button" onmousedown="event.preventDefault(); insertLink('task-modal-desc')" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Insert Link">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                        </button>
                        <div class="w-px h-5 bg-slate-300 mx-1"></div>
                        <button type="button" onmousedown="event.preventDefault(); formatText('insertUnorderedList', 'task-modal-desc')" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Bullet List">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                        </button>
                        <button type="button" onmousedown="event.preventDefault(); formatText('insertOrderedList', 'task-modal-desc')" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Numbered List">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 6h11M9 12h11M9 18h11M5 6v.01M5 12v.01M5 18v.01"></path></svg>
                        </button>
                        <div class="w-px h-5 bg-slate-300 mx-1"></div>
                        <button type="button" onmousedown="event.preventDefault(); insertTable('task-modal-desc')" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Insert Table">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm2 4h12M12 10v10"></path></svg>
                        </button>
                        <button type="button" onclick="document.getElementById('rte-image-upload-desc').click()" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Insert Image">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        </button>
                        <input type="file" id="rte-image-upload-desc" accept="image/*" class="hidden" onchange="uploadRteImage(this, 'task-modal-desc')">
                        <button type="button" onclick="insertMention();" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Insert Mention">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                        </button>
                        <div class="w-px h-5 bg-slate-300 mx-1"></div>
                        <button type="button" onmousedown="event.preventDefault(); editTable('addRow', 'task-modal-desc')" class="px-1.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-200 rounded" title="Add Row">+Row</button>
                        <button type="button" onmousedown="event.preventDefault(); editTable('addCol', 'task-modal-desc')" class="px-1.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-200 rounded" title="Add Column">+Col</button>
                        <button type="button" onmousedown="event.preventDefault(); editTable('delRow', 'task-modal-desc')" class="px-1.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-200 rounded" title="Delete Row">-Row</button>
                        <button type="button" onmousedown="event.preventDefault(); editTable('delCol', 'task-modal-desc')" class="px-1.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-200 rounded" title="Delete Column">-Col</button>
                        <div class="flex-1"></div>
                        <button type="button" onclick="toggleCodeView('task-modal-desc')" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Toggle Code View">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l-4 3 4 3m8-6l4 3-4 3"></path></svg>
                        </button>
                    </div>
                    <div id="task-modal-desc" class="prose prose-sm text-slate-600 p-4 outline-none flex-1 max-w-none break-words min-h-[100px] border border-transparent focus:border-slate-200" contenteditable="true" onkeydown="handleRteKeyDown(event)" oninput="handleRteInput(event)">
                    </div>
                    <textarea id="task-modal-desc-code" class="hidden font-mono text-sm p-4 w-full flex-1 outline-none text-slate-700 bg-slate-50 break-words min-h-[100px]"></textarea>
                </div>
            </div>

            <!-- Attachments -->
            <div class="mb-8">
                <div class="flex justify-between items-center mb-3">
                    <label class="font-semibold text-slate-800">Attachments</label>
                    <button onclick="document.getElementById('task-attachment-upload').click()" class="text-xs text-teal-600 font-medium hover:text-teal-700">Add File</button>
                    <input type="file" id="task-attachment-upload" class="hidden" onchange="uploadTaskAttachment(this)">
                </div>
                <div class="space-y-2" id="task-modal-attachments-list">
                    <!-- Dynamic Attachments -->
                </div>
            </div>

            <!-- Subtasks -->
            <div class="mb-8 relative">
                <div class="flex justify-between items-center mb-3">
                    <label class="font-semibold text-slate-800">Subtasks</label>
                    <div class="flex space-x-3 items-center shrink-0">
                        <button onclick="promptAddSubtask(document.getElementById('task-modal-id').value)" class="text-xs text-teal-600 font-medium hover:text-teal-700 whitespace-nowrap">Add Subtask</button>
                    </div>
                </div>
                <div class="space-y-2" id="task-modal-subtasks">
                    <!-- Dynamic -->
                </div>
            </div>

            <hr class="border-slate-200 my-8">

            <!-- Comments -->
            <div class="mb-8">
                <label class="font-semibold text-slate-800 mb-4 block">Comments</label>
                
                <div class="flex space-x-3 mb-6">
                    <img class="w-8 h-8 rounded-full" src="https://ui-avatars.com/api/?name=Admin+User&background=0D8ABC&color=fff" alt="You">
                    <div class="flex-1">
                        <div class="border border-slate-300 rounded bg-white mb-2 overflow-hidden flex flex-col min-h-[120px]">
                            <!-- RTE Toolbar -->
                            <div class="bg-slate-50 border-b border-slate-200 px-2 py-1.5 flex flex-wrap gap-1 items-center">
                                <button type="button" onmousedown="event.preventDefault(); formatText('bold', 'task-modal-new-comment')" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Bold">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6z M6 12h9a4 4 0 014 4 4 4 0 01-4 4H6z"></path></svg>
                                </button>
                                <button type="button" onmousedown="event.preventDefault(); formatText('italic', 'task-modal-new-comment')" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Italic">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 6h6M12 6v12M9 18h6"></path></svg>
                                </button>
                                <button type="button" onmousedown="event.preventDefault(); formatText('underline', 'task-modal-new-comment')" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Underline">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 3v7a6 6 0 006 6 6 6 0 006-6V3m-9 18h6"></path></svg>
                                </button>
                                <button type="button" onmousedown="event.preventDefault(); insertLink('task-modal-new-comment')" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Insert Link">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                                </button>
                                <div class="w-px h-5 bg-slate-300 mx-1"></div>
                                <button type="button" onmousedown="event.preventDefault(); formatText('insertUnorderedList', 'task-modal-new-comment')" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Bullet List">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                                </button>
                                <button type="button" onmousedown="event.preventDefault(); formatText('insertOrderedList', 'task-modal-new-comment')" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Numbered List">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 6h11M9 12h11M9 18h11M5 6v.01M5 12v.01M5 18v.01"></path></svg>
                                </button>
                                <div class="w-px h-5 bg-slate-300 mx-1"></div>
                                <button type="button" onmousedown="event.preventDefault(); insertTable('task-modal-new-comment')" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Insert Table">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm2 4h12M12 10v10"></path></svg>
                                </button>
                                <button type="button" onclick="document.getElementById('rte-image-upload-comment').click()" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Insert Image">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </button>
                                <input type="file" id="rte-image-upload-comment" accept="image/*" class="hidden" onchange="uploadRteImage(this, 'task-modal-new-comment')">
                                <div class="w-px h-5 bg-slate-300 mx-1"></div>
                                <button type="button" onmousedown="event.preventDefault(); editTable('addRow', 'task-modal-new-comment')" class="px-1.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-200 rounded" title="Add Row">+Row</button>
                                <button type="button" onmousedown="event.preventDefault(); editTable('addCol', 'task-modal-new-comment')" class="px-1.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-200 rounded" title="Add Column">+Col</button>
                                <button type="button" onmousedown="event.preventDefault(); editTable('delRow', 'task-modal-new-comment')" class="px-1.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-200 rounded" title="Delete Row">-Row</button>
                                <button type="button" onmousedown="event.preventDefault(); editTable('delCol', 'task-modal-new-comment')" class="px-1.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-200 rounded" title="Delete Column">-Col</button>
                                <div class="flex-1"></div>
                                <button type="button" onclick="toggleCodeView('task-modal-new-comment')" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Toggle Code View">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l-4 3 4 3m8-6l4 3-4 3"></path></svg>
                                </button>
                            </div>
                            
                            <div id="task-modal-new-comment" class="prose prose-sm text-slate-600 p-3 outline-none flex-1 max-w-none break-words" contenteditable="true" data-placeholder="Ask a question or post an update..." onkeydown="handleRteKeyDown(event)" oninput="handleRteInput(event)" onfocus="if(this.innerHTML==='<p><br></p>') this.innerHTML='';" onblur="if(this.innerHTML==='') this.innerHTML='<p><br></p>';"></div>
                            <textarea id="task-modal-new-comment-code" class="hidden font-mono text-sm p-3 w-full flex-1 outline-none text-slate-700 bg-slate-50 break-words min-h-[100px]"></textarea>
                        </div>
                        <div class="mt-2 flex justify-end flex-wrap gap-2">
                            <button onclick="submitTaskComment()" class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-1.5 rounded-lg text-sm font-medium shadow-sm transition-colors">Comment</button>
                        </div>
                    </div>
                </div>

                <div class="space-y-5" id="task-modal-comments-list">
                    <!-- Dynamic Comments -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mention Popover -->
<div id="mention-popover" class="hidden fixed z-[100] bg-white border border-slate-200 rounded-lg shadow-xl max-h-60 overflow-y-auto w-64 flex-col text-sm text-slate-700">
    <ul id="mention-popover-list" class="p-1"></ul>
</div>

<!-- Create Entry Modals (Ocean Theme) -->
<div id="create-modal-backdrop" class="fixed inset-0 bg-slate-900/60 hidden z-[60] flex items-center justify-center backdrop-blur-sm transition-opacity opacity-0">
    <div id="create-modal-content" class="bg-white w-full max-w-md rounded-xl shadow-2xl overflow-hidden transform transition-all scale-95 duration-200">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 class="text-lg font-semibold text-slate-800 flex items-center" id="create-modal-title">
                <div class="w-8 h-8 rounded-lg bg-teal-100 text-teal-600 flex items-center justify-center mr-3">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                </div>
                <span id="create-modal-title-text">Create Item</span>
            </h3>
            <button type="button" onclick="closeCreateModal()" class="text-slate-400 hover:text-slate-600 hover:bg-slate-100 p-1.5 rounded-lg transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        
        <form id="create-entry-form" onsubmit="handleCreateSubmit(event)" class="p-6">
            <input type="hidden" id="create-action-type" value="">
            <input type="hidden" id="create-target-id" value="">
            
            <div class="space-y-4">
                <div>
                    <label for="create-title-input" class="block text-sm font-medium text-slate-700 mb-1" id="create-title-label">Title</label>
                    <input type="text" id="create-title-input" required class="w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition-shadow text-slate-800 placeholder-slate-400" placeholder="Enter name...">
                </div>
                
                <div id="create-desc-container" class="hidden">
                    <label for="create-desc-input" class="block text-sm font-medium text-slate-700 mb-1">Description (Optional)</label>
                    <textarea id="create-desc-input" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition-shadow text-slate-800 placeholder-slate-400" placeholder="Add more details..."></textarea>
                </div>
            </div>
            
            <div class="mt-6 pt-4 border-t border-slate-100 flex justify-end space-x-3">
                <button type="button" onclick="closeCreateModal()" class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 transition-colors shadow-sm">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-teal-600 hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 shadow-sm transition-colors flex items-center">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7m-4-4v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h14a2 2 0 012 2z"></path></svg>
                    <span id="create-btn-text">Create</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
// showSavingOverlay and hideSavingOverlay are now globally defined in header.php

document.addEventListener('click', function(event) {
    const projDropdown = document.getElementById('project-link-dropdown');
    const projButton = document.querySelector('button[onclick="toggleProjectLinkDropdown()"]');
    
    if (projDropdown && !projDropdown.classList.contains('hidden')) {
        if (!projDropdown.contains(event.target) && (!projButton || !projButton.contains(event.target))) {
            projDropdown.classList.add('hidden');
        }
    }

    const link = event.target.closest('a');
    if (link && link.href) {
        const editable = link.closest('[contenteditable="true"]');
        if (editable) {
            // Check if user is clicking on a link inside an editor
            event.preventDefault();
            window.open(link.href, link.target || '_blank');
        }
    }
});

window.getStatusBadgeClass = function(status) {
    const s = (status || 'todo').toLowerCase();
    if (s === 'completed' || s === 'done') {
        return 'text-emerald-700 bg-emerald-50 border-emerald-200';
    } else if (s === 'in_progress' || s === 'ongoing' || s === 'active') {
        return 'text-blue-700 bg-blue-50 border-blue-200';
    } else if (s === 'paused' || s === 'planning') {
        return 'text-amber-700 bg-amber-50 border-amber-200';
    } else if (s === 'in_review') {
        return 'text-purple-700 bg-purple-50 border-purple-200';
    } else if (s === 'cancelled' || s === 'archived') {
        return 'text-rose-700 bg-rose-50 border-rose-200';
    } else {
        return 'text-slate-600 bg-slate-50 border-slate-200';
    }
};

window.getStatusTextClass = function(status) {
    const s = (status || 'todo').toLowerCase();
    if (s === 'completed' || s === 'done') return 'text-emerald-600';
    if (s === 'in_progress' || s === 'ongoing' || s === 'active') return 'text-blue-600';
    if (s === 'paused' || s === 'planning') return 'text-amber-500';
    if (s === 'in_review') return 'text-purple-600';
    if (s === 'cancelled' || s === 'archived') return 'text-rose-500';
    return 'text-slate-600';
};

let timerInterval;

// Custom Rich Text Editor Functions
let mentionQuery = null;
let mentionRange = null;
let mentionType = null; 
let mentionActiveEditor = null;
let mentionResults = [];
let mentionSelectedIndex = 0;

function handleRteKeyDown(event) {
    if (event.key === 'Tab') {
        if (!document.getElementById('mention-popover').classList.contains('hidden')) {
            event.preventDefault();
            insertMention();
            return;
        }
        event.preventDefault();
        if (event.shiftKey) {
            document.execCommand('outdent', false, null);
        } else {
            document.execCommand('indent', false, null);
        }
        return;
    }

    const popover = document.getElementById('mention-popover');
    if (!popover.classList.contains('hidden')) {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            mentionSelectedIndex = (mentionSelectedIndex + 1) % mentionResults.length;
            renderMentionPopover();
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            mentionSelectedIndex = (mentionSelectedIndex - 1 + mentionResults.length) % mentionResults.length;
            renderMentionPopover();
        } else if (event.key === 'Enter') {
            event.preventDefault();
            insertMention();
        } else if (event.key === 'Escape') {
            event.preventDefault();
            closeMentionPopover();
        }
    }
}

function handleRteInput(event) {
    const selection = window.getSelection();
    if (!selection.rangeCount) return;

    const range = selection.getRangeAt(0);
    const node = selection.anchorNode;
    if (!node || node.nodeType !== Node.TEXT_NODE) {
        closeMentionPopover();
        return;
    }

    const textBeforeCursor = node.textContent.substring(0, range.startOffset);
    const match = textBeforeCursor.match(/(?:^|\s)([@#])([a-zA-Z0-9\-_ ]*)$/);

    if (match && match[2].length < 30) {
        mentionType = match[1];
        mentionQuery = match[2].trim();
        
        mentionRange = document.createRange();
        // match[0] contains the matched string including the leading space if present
        // we only want to replace the @word part.
        const matchedText = match[0].trimStart(); // remove leading space
        mentionRange.setStart(node, range.startOffset - matchedText.length);
        mentionRange.setEnd(node, range.startOffset);
        
        mentionActiveEditor = event.currentTarget;
        
        const rect = range.getBoundingClientRect();
        showMentionPopover(rect);
        fetchMentions(mentionQuery);
    } else {
        closeMentionPopover();
    }
}

function showMentionPopover(rect) {
    const popover = document.getElementById('mention-popover');
    popover.classList.remove('hidden');
    popover.classList.add('flex');
    popover.style.left = rect.left + 'px';
    popover.style.top = (rect.bottom + 5) + 'px';
}

function closeMentionPopover() {
    const popover = document.getElementById('mention-popover');
    if(popover) {
        popover.classList.add('hidden');
        popover.classList.remove('flex');
    }
    mentionQuery = null;
    mentionRange = null;
    mentionActiveEditor = null;
    mentionResults = [];
    mentionSelectedIndex = 0;
}

document.addEventListener('mousedown', function(event) {
    const popover = document.getElementById('mention-popover');
    if (popover && !popover.classList.contains('hidden')) {
        if (!popover.contains(event.target)) {
            closeMentionPopover();
        }
    }
});

async function fetchMentions(query) {
    mentionResults = [];
    mentionSelectedIndex = 0;
    
    try {
        const resTasks = await fetch('api/tasks.php');
        const allTasks = await resTasks.json();
        
        const filteredTasks = allTasks.filter(t => t.title.toLowerCase().includes(query.toLowerCase()) || String(t.id).includes(query));
        mentionResults.push(...filteredTasks.map(t => ({...t, itemType: 'task'})));
        
        const resProj = await fetch('api/projects.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'search', query: query })
        });
        const projData = await resProj.json();
        if (projData.status === 'success') {
            mentionResults.push(...projData.projects.map(p => ({...p, itemType: 'project'})));
        }
        
        mentionResults = mentionResults.slice(0, 10);
        renderMentionPopover();
        
    } catch(e) {
        console.error('Mention fetch error', e);
    }
}

function renderMentionPopover() {
    const list = document.getElementById('mention-popover-list');
    list.innerHTML = '';
    
    if (mentionResults.length === 0) {
        list.innerHTML = '<li class="p-2 text-slate-400 italic text-xs">No matching tasks or projects</li>';
        return;
    }
    
    mentionResults.forEach((item, index) => {
        const li = document.createElement('li');
        const isSelected = index === mentionSelectedIndex;
        li.className = `p-2 cursor-pointer flex items-center justify-between text-slate-700 hover:bg-slate-100 rounded mt-0.5 ${isSelected ? 'bg-teal-50' : ''}`;
        
        if (item.itemType === 'task') {
            li.innerHTML = `
                <div class="flex items-center truncate">
                    <svg class="w-3.5 h-3.5 mr-2 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    <span class="truncate"><span class="text-xs text-slate-400 font-medium">#${item.id}</span> ${item.title}</span>
                </div>
            `;
        } else {
            li.innerHTML = `
                <div class="flex items-center truncate">
                    <svg class="w-3.5 h-3.5 mr-2 text-emerald-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
                    <span class="truncate"><span class="text-xs text-slate-400 font-medium">PRJ-${item.id}</span> ${item.name}</span>
                </div>
            `;
        }
        
        li.onmousedown = (e) => {
            e.preventDefault();
            mentionSelectedIndex = index;
            insertMention();
        };
        li.onmouseenter = () => {
            mentionSelectedIndex = index;
            Array.from(list.children).forEach((child, i) => {
                if (i === index) child.classList.add('bg-teal-50');
                else child.classList.remove('bg-teal-50');
            });
        };
        
        list.appendChild(li);
    });
}

function insertMention() {
    if (mentionResults.length === 0) {
        closeMentionPopover();
        return;
    }
    
    const item = mentionResults[mentionSelectedIndex];
    
    if (mentionActiveEditor) {
        mentionActiveEditor.focus();
    }
    
    const selection = window.getSelection();
    
    selection.removeAllRanges();
    if (mentionRange) {
        selection.addRange(mentionRange);
    }
    
    let html = '';
    if (item.itemType === 'task') {
        const url = window.location.origin + window.location.pathname + '?id=' + (typeof currentProjectId !== 'undefined' ? currentProjectId : 1) + '&task_id=' + item.id;
        html = `<a href="${url}" class="text-indigo-600 font-medium hover:underline px-1 py-0.5 rounded bg-indigo-50" contenteditable="false" target="_blank">#${item.id} ${item.title}</a>&nbsp;`;
    } else {
        const url = window.location.origin + window.location.pathname + '?id=' + item.id;
        html = `<a href="${url}" class="text-emerald-600 font-medium hover:underline px-1 py-0.5 rounded bg-emerald-50" contenteditable="false" target="_blank">PRJ-${item.id} ${item.name}</a>&nbsp;`;
    }
    
    document.execCommand('insertHTML', false, html);
    closeMentionPopover();
    
    if (mentionActiveEditor && mentionActiveEditor.id === 'task-modal-desc') {
        updateTaskDetails();
    }
}

function formatText(command, editorId) {
    document.getElementById(editorId).focus();
    document.execCommand(command, false, null);
}

function showPromptModal(title, label, defaultValue) {
    return new Promise((resolve) => {
        const overlay = document.getElementById('global-modal-overlay');
        const box = document.getElementById('global-modal-box');
        const titleEl = document.getElementById('global-modal-title');
        const msgEl = document.getElementById('global-modal-message');
        const iconContainer = document.getElementById('global-modal-icon');
        const btnCancel = document.getElementById('global-modal-cancel');
        const btnConfirm = document.getElementById('global-modal-confirm');

        if (!overlay) return resolve(null);

        // Setup UI for prompt
        titleEl.textContent = title;
        msgEl.innerHTML = `
            <label class="block text-sm font-medium text-slate-700 mb-1">${label}</label>
            <input type="text" id="global-prompt-input" value="${defaultValue}" class="w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-slate-800" autofocus>
        `;
        
        iconContainer.innerHTML = `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>`;
        iconContainer.className = 'w-10 h-10 rounded-full flex flex-shrink-0 items-center justify-center shrink-0 bg-blue-100 text-blue-600';

        btnCancel.classList.remove('hidden');
        btnCancel.onclick = () => { closeModal(); resolve(null); };
        
        btnConfirm.textContent = 'Insert';
        btnConfirm.className = 'px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 active:scale-[0.98]';
        
        const inputEl = document.getElementById('global-prompt-input');
        
        btnConfirm.onclick = () => { 
            const val = inputEl.value;
            closeModal(); 
            resolve(val); 
        };
        
        inputEl.onkeydown = (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                btnConfirm.click();
            } else if (e.key === 'Escape') {
                btnCancel.click();
            }
        };

        function closeModal() {
            overlay.classList.remove('opacity-100', 'pointer-events-auto');
            overlay.classList.add('opacity-0', 'pointer-events-none');
            box.classList.remove('scale-100');
            box.classList.add('scale-95');
        }

        // Open Modal
        overlay.classList.remove('opacity-0', 'pointer-events-none');
        overlay.classList.add('opacity-100', 'pointer-events-auto');
        box.classList.remove('scale-95');
        box.classList.add('scale-100');
        
        setTimeout(() => {
            inputEl.focus();
            inputEl.setSelectionRange(inputEl.value.length, inputEl.value.length);
        }, 100);
    });
}

async function insertLink(editorId) {
    document.getElementById(editorId).focus();
    const selection = window.getSelection();
    
    if (!selection.rangeCount) return;
    
    // Save the selection range so we can restore it after the async modal closes
    const range = selection.getRangeAt(0).cloneRange();
    const selectedText = selection.toString();
    
    const url = await showPromptModal('Insert Link', 'URL:', '');
    
    if (!url || url === 'https://') return;
    
    // Restore the selection to the editor
    document.getElementById(editorId).focus();
    selection.removeAllRanges();
    selection.addRange(range);
    
    const textToDisplay = selectedText.length > 0 ? selectedText : url;
    const html = `<a href="${url}" target="_blank" class="text-blue-600 hover:underline">${textToDisplay}</a>`;
    
    document.execCommand('insertHTML', false, html);
    
    if (editorId === 'task-modal-desc') {
        updateTaskDetails();
    }
}

function toggleCodeView(editorId) {
    const editor = document.getElementById(editorId);
    const textarea = document.getElementById(editorId + '-code');
    if (!editor || !textarea) return;

    if (editor.classList.contains('hidden')) {
        // Switch to rich text view
        editor.innerHTML = textarea.value;
        textarea.classList.add('hidden');
        editor.classList.remove('hidden');
    } else {
        // Switch to code view
        textarea.value = editor.innerHTML;
        editor.classList.add('hidden');
        textarea.classList.remove('hidden');
    }
}

function getEditorContent(editorId) {
    const editor = document.getElementById(editorId);
    const textarea = document.getElementById(editorId + '-code');
    if (editor && !editor.classList.contains('hidden')) {
        return editor.innerHTML;
    } else if (textarea) {
        return textarea.value;
    }
    return '';
}

function editTable(action, editorId) {
    const selection = window.getSelection();
    if (!selection.rangeCount) return;
    
    let node = selection.anchorNode;
    if (!node) return;
    if (node.nodeType === 3) node = node.parentNode; // text node
    
    const td = node.closest('td, th');
    if (!td || !document.getElementById(editorId).contains(td)) {
        showAlert('Invalid Selection', 'Please place your cursor inside a table cell to modify the table.', 'warning');
        return;
    }
    
    const tr = td.closest('tr');
    const table = tr.closest('table');
    const cellIndex = td.cellIndex;

    if (action === 'addRow') {
        const newTr = tr.cloneNode(true);
        Array.from(newTr.children).forEach(cell => cell.innerHTML = '<br>');
        tr.after(newTr);
    } else if (action === 'addCol') {
        Array.from(table.rows).forEach(row => {
            const newTd = row.children[cellIndex].cloneNode(true);
            newTd.innerHTML = '<br>';
            row.children[cellIndex].after(newTd);
        });
    } else if (action === 'delRow') {
        tr.remove();
        if (table.rows.length === 0) table.remove();
    } else if (action === 'delCol') {
        Array.from(table.rows).forEach(row => {
            if (row.children[cellIndex]) row.children[cellIndex].remove();
        });
        if (table.rows[0] && table.rows[0].children.length === 0) table.remove();
    }
    
    // Update background syncing if focused elsewhere
    if(editorId === 'task-modal-desc') updateTaskDetails();
}

function insertTable(editorId) {
    const rows = prompt('Enter number of rows:', '2');
    const cols = prompt('Enter number of columns:', '2');
    if (!rows || !cols) return;
    
    let html = '<table class="w-full border-collapse border border-slate-300 my-4 text-sm">';
    for (let r = 0; r < rows; r++) {
        html += '<tr>';
        for (let c = 0; c < cols; c++) {
            html += '<td class="border border-slate-300 p-2 min-w-[50px]"><br></td>';
        }
        html += '</tr>';
    }
    html += '</table><p><br></p>';
    
    document.getElementById(editorId).focus();
    document.execCommand('insertHTML', false, html);
}

async function uploadRteImage(input, editorId) {
    if (!input.files || input.files.length === 0) return;
    
    const file = input.files[0];
    const formData = new FormData();
    formData.append('file', file);
    
    try {
        const res = await fetch('api/upload.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        
        if (data.location) {
            document.getElementById(editorId).focus();
            document.execCommand('insertImage', false, data.location);
        } else {
            console.error("Upload response data:", data);
            showAlert('Upload Failed', 'Image upload failed. Error details: ' + JSON.stringify(data), 'danger');
        }
    } catch(e) {
        console.error(e);
        showAlert('Upload Error', 'Image upload error', 'danger');
    } finally {
        input.value = '';
    }
}

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

    if (viewName === 'timeline' && typeof renderTimeline === 'function') {
        renderTimeline();
    } else if (viewName === 'dashboard' && typeof renderDashboard === 'function') {
        renderDashboard();
    }
}

async function openTaskModal(taskId) {
    const modal = document.getElementById('task-modal');
    
    // Fetch Task Details
    try {
        const res = await fetch(`api/tasks.php?id=${taskId}`);
        const task = await res.json();
        if(task.error) {
            showAlert('Error', task.error, 'danger');
            return;
        }
        
        document.getElementById('task-modal-id').value = task.id;
        document.getElementById('task-modal-title').value = task.title;
        const statusSelect = document.getElementById('task-modal-status');
        statusSelect.value = task.status;
        statusSelect.className = `text-sm border-none focus:ring-0 p-0 rounded bg-transparent font-medium ${window.getStatusTextClass(task.status)}`;
        
        let startDateVal = '';
        if(task.start_date) {
            startDateVal = task.start_date.split(' ')[0];
        }
        document.getElementById('task-modal-start-date').value = startDateVal;

        let expectedStartDateVal = '';
        if(task.expected_start_date) {
            expectedStartDateVal = task.expected_start_date.split(' ')[0]; // format: YYYY-MM-DD
        }
        document.getElementById('task-modal-expected-start-date').value = expectedStartDateVal;

        let expectedDueDateVal = '';
        if(task.expected_due_date) {
            expectedDueDateVal = task.expected_due_date.split(' ')[0]; // format: YYYY-MM-DD
        }
        document.getElementById('task-modal-expected-due-date').value = expectedDueDateVal;
        
        const completedContainer = document.getElementById('task-modal-completed-date-container');
        if (task.completed_date) {
            completedContainer.classList.remove('hidden');
            document.getElementById('task-modal-completed-date').innerText = task.completed_date;
        } else {
            completedContainer.classList.add('hidden');
        }

        // Parent Task UI removed
        
        // Track current assignee IDs globally for the dropdown
        window.currentTaskAssigneeIds = task.assignee_ids ? task.assignee_ids.split(',').map(id => parseInt(id)) : [];
        
        document.getElementById('task-modal-assignee-name').innerText = task.assignee_name || 'Unassigned';
        const stack = document.getElementById('task-modal-assignees-stack');
        if (stack) {
            stack.innerHTML = '';
            if (task.assignee_name) {
                stack.classList.remove('hidden');
                const names = task.assignee_name.split(',');
                names.slice(0, 3).forEach(n => {
                    const initial = n.trim().charAt(0).toUpperCase();
                    stack.innerHTML += `<div class="w-6 h-6 rounded-full bg-teal-100 border-2 border-white text-teal-700 text-[10px] font-bold flex items-center justify-center">${initial}</div>`;
                });
                if (names.length > 3) {
                    stack.innerHTML += `<div class="w-6 h-6 rounded-full bg-slate-100 border-2 border-white text-slate-500 text-[9px] font-bold flex items-center justify-center">+${names.length - 3}</div>`;
                }
            } else {
                stack.classList.add('hidden');
            }
        }
        
        window.currentTaskCollaboratorIds = task.collaborator_ids ? task.collaborator_ids.split(',').map(id => parseInt(id)) : [];
        
        document.getElementById('task-modal-collaborator-name').innerText = task.collaborator_name || 'No collaborators';
        const collabStack = document.getElementById('task-modal-collaborators-stack');
        if (collabStack) {
            collabStack.innerHTML = '';
            if (task.collaborator_name) {
                collabStack.classList.remove('hidden');
                const names = task.collaborator_name.split(',');
                names.slice(0, 3).forEach(n => {
                    const initial = n.trim().charAt(0).toUpperCase();
                    collabStack.innerHTML += `<div class="w-6 h-6 rounded-full bg-indigo-100 border-2 border-white text-indigo-700 text-[10px] font-bold flex items-center justify-center">${initial}</div>`;
                });
                if (names.length > 3) {
                    collabStack.innerHTML += `<div class="w-6 h-6 rounded-full bg-slate-100 border-2 border-white text-slate-500 text-[9px] font-bold flex items-center justify-center">+${names.length - 3}</div>`;
                }
            } else {
                collabStack.classList.add('hidden');
            }
        }
        
        document.getElementById('task-modal-estimated').value = task.estimated_minutes || 0;
        
        // Render custom editor content
        document.getElementById('task-modal-desc').innerHTML = task.description || '<p><br></p>';

        // Render Attachments
        const attachmentsContainer = document.getElementById('task-modal-attachments-list');
        attachmentsContainer.innerHTML = '';
        if(task.attachments && task.attachments.length > 0) {
            task.attachments.forEach(att => {
                const isImage = att.file_type.startsWith('image/');
                const icon = isImage ? '<svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>'
                                     : '<svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>';
                attachmentsContainer.innerHTML += `
                    <div class="flex items-center justify-between bg-slate-50 border border-slate-200 p-2 rounded relative group">
                        <a href="${att.file_path}" target="_blank" class="flex items-center hover:underline text-slate-700 text-sm truncate max-w-[80%]">
                            ${icon} <span class="ml-2 truncate">${att.file_name}</span>
                        </a>
                        <button onclick="deleteAttachment(${att.id})" class="text-red-500 opacity-0 group-hover:opacity-100 hover:text-red-700 p-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                    </div>
                `;
            });
        } else {
            attachmentsContainer.innerHTML = '<div class="text-sm text-slate-400 italic">No attachments</div>';
        }
        
        // Render Projects
        const projectsContainer = document.getElementById('task-modal-projects');
        if (projectsContainer) {
            projectsContainer.innerHTML = '';
            if (task.projects && task.projects.length > 0) {
                task.projects.forEach(p => {
                    let options = '';
                    if (p.all_sections) {
                        p.all_sections.forEach(sec => {
                            const sel = sec.id == p.section_id ? 'selected' : '';
                            options += `<option value="${sec.id}" ${sel}>${sec.name}</option>`;
                        });
                    }
                    
                    projectsContainer.innerHTML += `
                        <div class="flex items-center justify-between bg-slate-50 border border-slate-200 p-2 rounded">
                            <span class="text-sm font-medium text-slate-700 truncate w-1/2">${p.project_name}</span>
                            <select onchange="changeTaskProjectSection(${p.project_id}, this)" class="text-sm border-slate-300 focus:ring-teal-500 focus:border-teal-500 rounded p-1 w-1/2 ml-2 text-slate-600 bg-white shadow-sm">
                                ${options}
                            </select>
                        </div>
                    `;
                });
            } else {
                projectsContainer.innerHTML = '<div class="text-sm text-slate-400 italic">Task is not in any project</div>';
            }
        }
        
        // Render Subtasks
        const subtasksContainer = document.getElementById('task-modal-subtasks');
        subtasksContainer.innerHTML = '';
        window.currentTaskSubtaskIds = [];
        if(task.subtasks) {
            window.currentTaskSubtaskIds = task.subtasks.map(s => s.id);
            task.subtasks.forEach(sub => {
                const isLinked = sub.parent_task_id !== task.id;
                const isShared = parseInt(sub.project_count || 1, 10) > 1;
                const sharedClass = isShared ? 'shared-task' : '';
                const unlinkBtn = isLinked 
                    ? `<button onclick="unlinkSubtask(${task.id}, ${sub.id})" class="text-slate-300 hover:text-rose-500 hover:bg-rose-50 p-1 rounded transition-colors" title="Unlink Subtask">
                           <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                       </button>`
                    : `<div class="w-6 h-6"></div>`;

                subtasksContainer.innerHTML += `
                    <div class="flex flex-col mb-1 group items-start justify-between bg-white border border-transparent hover:bg-slate-50 hover:border-slate-200 rounded px-2 py-1.5 transition-colors task-row task-row-${sub.id} ${sharedClass}" data-task-id="${sub.id}" data-parent-id="${task.id}" data-depth="1">
                        <div class="flex items-center space-x-2 w-full">
                            <div class="cursor-grab text-slate-300 hover:text-slate-500 flex items-center justify-center" title="Drag to reorder">
                                <svg class="w-4 h-4 pointer-events-none" fill="currentColor" viewBox="0 0 24 24"><path d="M10 9h4V6h3l-5-5-5 5h3v3zm-1 1H6V7l-5 5 5 5v-3h3v-4zm14 2l-5-5v3h-3v4h3v3l5-5zm-9 3h-4v3H7l5 5 5-5h-3v-3z"></path></svg>
                            </div>
                            <input type="checkbox" ${sub.status==='completed'?'checked':''} onchange="updateSubtaskStatus(${sub.id}, this)" class="rounded text-teal-500 focus:ring-teal-500 focus:ring-offset-0 w-4 h-4 cursor-pointer">
                            <span class="flex-1 text-sm cursor-pointer truncate pl-1 ${sub.status==='completed'?'line-through text-slate-400':'text-slate-700'}" onclick="openTaskModal(${sub.id})" title="${sub.title}">${sub.title}</span>
                            <span class="text-[10px] items-center px-1.5 py-0.5 rounded font-medium tracking-wide uppercase shadow-sm border ${window.getStatusBadgeClass(sub.status)}">${sub.status.replace('_', ' ')}</span>
                            ${unlinkBtn}
                        </div>
                    </div>
                `;
            });
            
            // Initialize Sortable for subtasks
            if (window.modalSubtasksSortable) {
                window.modalSubtasksSortable.destroy();
            }
            if (typeof Sortable !== 'undefined') {
                window.modalSubtasksSortable = new Sortable(subtasksContainer, {
                    group: { name: 'modal-subtasks', put: ['main-board-list', 'shared-board'], pull: ['main-board-list', 'shared-board'] }, // Shared with main board
                    animation: 150,
                    handle: '.cursor-grab',
                    onMove: function(evt) {
                        // Prevent dropping a shared task into a subtask zone
                        if (evt.dragged.classList.contains('shared-task')) {
                            return false;
                        }
                        return true;
                    },
                    onEnd: function(evt) {
                        if (typeof window.handleTaskReorder === 'function') {
                            window.handleTaskReorder(evt);
                        }
                    }
                });
            }
        } else {
            subtasksContainer.innerHTML = '<div class="text-sm text-slate-400 italic">No subtasks</div>';
            
            // Allow dropping even if empty
            if (window.modalSubtasksSortable) {
                window.modalSubtasksSortable.destroy();
            }
            if (typeof Sortable !== 'undefined') {
                window.modalSubtasksSortable = new Sortable(subtasksContainer, {
                    group: { name: 'modal-subtasks', put: ['main-board-list', 'shared-board'], pull: ['main-board-list', 'shared-board'] }, // Shared with main board
                    animation: 150,
                    handle: '.cursor-grab',
                    onMove: function(evt) {
                        // Prevent dropping a shared task into a subtask zone
                        if (evt.dragged.classList.contains('shared-task')) {
                            return false;
                        }
                        return true;
                    },
                    onEnd: function(evt) {
                        if (typeof window.handleTaskReorder === 'function') {
                            window.handleTaskReorder(evt);
                        }
                    }
                });
            }
        }
        
        // Render Comments
        const commentsContainer = document.getElementById('task-modal-comments-list');
        commentsContainer.innerHTML = '';
        if(task.comments) {
            task.comments.forEach(comment => {
                const dt = new Date(comment.created_at.replace(/-/g, '/')).toLocaleString();
                const initials = comment.user_name ? comment.user_name.substring(0, 2) : 'U';
                
                // Assumes mock user ID 1 OR user can edit their own comment
                const isOwner = parseInt(comment.user_id) === 1;
                const editBtn = isOwner ? `<button type="button" onclick="enableCommentEdit(${comment.id})" class="text-[11px] text-teal-600 hover:text-teal-800 ml-2 font-medium">Edit</button>` : '';

                commentsContainer.innerHTML += `
                    <div class="flex space-x-3" id="comment-${comment.id}">
                        <div class="w-8 h-8 rounded-full bg-teal-100 text-teal-700 text-xs font-bold flex items-center justify-center flex-shrink-0" title="${comment.user_name}">${initials}</div>
                        <div class="flex-1 bg-slate-50 p-3 rounded-lg rounded-tl-none border border-slate-100">
                            <div class="flex justify-between items-start mb-1">
                                <div>
                                    <span class="font-medium text-slate-800 text-sm">${comment.user_name}</span>
                                    ${editBtn}
                                </div>
                                <span class="text-xs text-slate-400">${dt}</span>
                            </div>
                            <div class="text-sm text-slate-600 prose prose-sm max-w-none comment-content">${comment.content}</div>
                        </div>
                    </div>
                `;
            });
        }
        
        // Handle Time Tracker
        if (task.time_log_status) {
            const btn = document.getElementById('btn-start-progress');
            const span = btn.querySelector('span');
            const display = document.getElementById('timer-display');
            
            stopTimer(false); // Stop current interval if any
            
            let totalSecs = task.time_log_status.total_tracked_seconds || 0;
            if (task.time_log_status.is_running) {
                // Calculate elapsed since running_since
                const start = new Date(task.time_log_status.running_since.replace(/-/g, '/'));
                const now = new Date();
                const diff = Math.floor((now - start) / 1000);
                timerSeconds = totalSecs + (diff > 0 ? diff : 0);
                
                isProgressRunning = true;
                btn.classList.replace('bg-emerald-500', 'bg-amber-500');
                btn.classList.replace('hover:bg-emerald-600', 'hover:bg-amber-600');
                span.innerText = "Pause Progress";
                
                timerInterval = setInterval(() => {
                    timerSeconds++;
                    const h = String(Math.floor(timerSeconds / 3600)).padStart(2, '0');
                    const m = String(Math.floor((timerSeconds % 3600) / 60)).padStart(2, '0');
                    const s = String(timerSeconds % 60).padStart(2, '0');
                    display.innerText = `${h}:${m}:${s}`;
                }, 1000);
            } else {
                timerSeconds = totalSecs;
                
                // Also update display immediately to avoiding waiting 1 second
                const h = String(Math.floor(timerSeconds / 3600)).padStart(2, '0');
                const m = String(Math.floor((timerSeconds % 3600) / 60)).padStart(2, '0');
                const s = String(timerSeconds % 60).padStart(2, '0');
                display.innerText = `${h}:${m}:${s}`;
                
                isProgressRunning = false;
                btn.classList.replace('bg-amber-500', 'bg-emerald-500');
                btn.classList.replace('hover:bg-amber-600', 'hover:bg-emerald-600');
                span.innerText = "Start Progress";
            }
        }
        
        modal.classList.remove('hidden');
    } catch(e) {
        console.error(e);
        showAlert('Load Failed', 'Failed to load task details', 'danger');
    }
}

async function updateTaskDetails(forceSaveDescription = false) {
    showSavingOverlay();
    const id = document.getElementById('task-modal-id').value;
    const title = document.getElementById('task-modal-title').value;
    const statusSelect = document.getElementById('task-modal-status');
    const status = statusSelect.value;
    statusSelect.className = `text-sm border-none focus:ring-0 p-0 rounded bg-transparent font-medium ${window.getStatusTextClass(status)}`;

    const startDate = document.getElementById('task-modal-start-date').value;
    
    let expectedStartDate = document.getElementById('task-modal-expected-start-date').value;
    if (expectedStartDate) { expectedStartDate = expectedStartDate + ' 00:00:00'; }
    let expectedDueDate = document.getElementById('task-modal-expected-due-date').value;
    if (expectedDueDate) { expectedDueDate = expectedDueDate + ' 00:00:00'; }
    
    const estimatedMinutesInput = document.getElementById('task-modal-estimated').value;
    const estimatedMinutes = estimatedMinutesInput ? parseInt(estimatedMinutesInput, 10) : 0;
    
    // Only save the description when the Save Description button is pressed
    let desc;
    if (forceSaveDescription) {
        desc = getEditorContent('task-modal-desc');
        if (desc === '<p><br></p>') desc = '';
    }
    
    if(!id) return;
    
    // Update completed date text instantly if user selected 'completed'
    const completedContainer = document.getElementById('task-modal-completed-date-container');
    if (status === 'completed') {
        const d = new Date();
        const cDate = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0') + ' ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0') + ':' + String(d.getSeconds()).padStart(2, '0');
        document.getElementById('task-modal-completed-date').innerText = cDate;
        completedContainer.classList.remove('hidden');
    } else {
        completedContainer.classList.add('hidden');
    }

    const payloadDetails = { title, status, start_date: startDate, expected_start_date: expectedStartDate, expected_due_date: expectedDueDate, estimated_minutes: estimatedMinutes };
    if (forceSaveDescription) {
        payloadDetails.description = desc;
    }

    await fetch('api/tasks.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'update_details',
            task_id: id,
            details: payloadDetails,
            project_id: typeof currentProjectId !== 'undefined' ? currentProjectId : null
        })
    });
    
    if (forceSaveDescription) {
        showAlert('Success', 'Description saved successfully.', 'success');
    }
    
    // Refresh board in background
    if(typeof currentProjectId !== 'undefined') loadProjectBoard(currentProjectId);
    hideSavingOverlay();
}

async function updateSubtaskStatus(id, checkbox) {
    showSavingOverlay();
    const status = checkbox.checked ? 'completed' : 'todo';
    await fetch('api/tasks.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'update_status', task_id: id, status })
    });
    if(typeof currentProjectId !== 'undefined') loadProjectBoard(currentProjectId);

    // Dynamically update the UI
    const subtaskContainer = checkbox.closest('.group');
    if (subtaskContainer) {
        const titleSpan = subtaskContainer.querySelector('.truncate');
        const statusSpan = subtaskContainer.querySelector('.text-\\[10px\\]');
        if (titleSpan) {
            if (checkbox.checked) {
                titleSpan.classList.add('line-through', 'text-slate-400');
                titleSpan.classList.remove('text-slate-700');
            } else {
                titleSpan.classList.remove('line-through', 'text-slate-400');
                titleSpan.classList.add('text-slate-700');
            }
        }
        if (statusSpan) {
            statusSpan.textContent = status.replace('_', ' ');
            statusSpan.className = `text-[10px] items-center px-1.5 py-0.5 rounded font-medium tracking-wide uppercase shadow-sm border ${window.getStatusBadgeClass(status)}`;
        }
    }
    hideSavingOverlay();
}

async function submitTaskComment() {
    showSavingOverlay();
    const id = document.getElementById('task-modal-id').value;
    let content = getEditorContent('task-modal-new-comment').trim();
    if (content === '<p><br></p>') content = '';
    
    if(!id || !content) {
        hideSavingOverlay();
        return;
    }

    const res = await fetch('api/tasks.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'add_comment', task_id: id, content })
    });
    
    if(res.ok) {
        // clear comment input
        const editor = document.getElementById('task-modal-new-comment');
        if (editor) {
            editor.innerHTML = '<p><br></p>';
        }
        const editorCode = document.getElementById('task-modal-new-comment-code');
        if (editorCode) {
            editorCode.value = '';
        }
        
        openTaskModal(id); // Reload modal to show new comment and attachments
    }
    hideSavingOverlay();
}

function enableCommentEdit(commentId) {
    const parent = document.getElementById('comment-' + commentId);
    if (!parent) return;

    const contentDiv = parent.querySelector('.comment-content');
    const existingHtml = contentDiv.innerHTML;

    // Use dataset to store original content for cancellation
    contentDiv.dataset.originalHtml = escape(existingHtml);

    contentDiv.innerHTML = `
        <div class="mt-2 w-full">
            <div class="flex flex-col border border-slate-200 rounded-lg overflow-hidden focus-within:border-teal-500 focus-within:ring-1 focus-within:ring-teal-500 transition-all bg-white mb-2">
                <div class="bg-slate-50 border-b border-slate-200 px-2 py-1.5 flex flex-wrap gap-1 items-center" id="edit-toolbar-${commentId}">
                    <button type="button" onmousedown="event.preventDefault(); formatText('bold', 'edit-comment-area-${commentId}')" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Bold">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6z M6 12h9a4 4 0 014 4 4 4 0 01-4 4H6z"></path></svg>
                    </button>
                    <button type="button" onmousedown="event.preventDefault(); formatText('italic', 'edit-comment-area-${commentId}')" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Italic">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 6h6M12 6v12M9 18h6"></path></svg>
                    </button>
                    <button type="button" onmousedown="event.preventDefault(); formatText('underline', 'edit-comment-area-${commentId}')" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Underline">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 3v7a6 6 0 006 6 6 6 0 006-6V3m-9 18h6"></path></svg>
                    </button>
                    <button type="button" onmousedown="event.preventDefault(); insertLink('edit-comment-area-${commentId}')" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Insert Link">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                    </button>
                    <div class="w-px h-5 bg-slate-300 mx-1"></div>
                    <button type="button" onmousedown="event.preventDefault(); formatText('insertUnorderedList', 'edit-comment-area-${commentId}')" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Bullet List">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    <button type="button" onmousedown="event.preventDefault(); formatText('insertOrderedList', 'edit-comment-area-${commentId}')" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Numbered List">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 6h11M9 12h11M9 18h11M5 6v.01M5 12v.01M5 18v.01"></path></svg>
                    </button>
                    <div class="w-px h-5 bg-slate-300 mx-1"></div>
                    <button type="button" onmousedown="event.preventDefault(); insertTable('edit-comment-area-${commentId}')" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Insert Table">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm2 4h12M12 10v10"></path></svg>
                    </button>
                    <button type="button" onclick="document.getElementById('rte-image-upload-editcomment-${commentId}').click()" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Insert Image">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </button>
                    <input type="file" id="rte-image-upload-editcomment-${commentId}" accept="image/*" class="hidden" onchange="uploadRteImage(this, 'edit-comment-area-${commentId}')">
                    <div class="w-px h-5 bg-slate-300 mx-1"></div>
                    <button type="button" onmousedown="event.preventDefault(); editTable('addRow', 'edit-comment-area-${commentId}')" class="px-1.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-200 rounded" title="Add Row">+Row</button>
                    <button type="button" onmousedown="event.preventDefault(); editTable('addCol', 'edit-comment-area-${commentId}')" class="px-1.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-200 rounded" title="Add Column">+Col</button>
                    <button type="button" onmousedown="event.preventDefault(); editTable('delRow', 'edit-comment-area-${commentId}')" class="px-1.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-200 rounded" title="Delete Row">-Row</button>
                    <button type="button" onmousedown="event.preventDefault(); editTable('delCol', 'edit-comment-area-${commentId}')" class="px-1.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-200 rounded" title="Delete Column">-Col</button>
                    <div class="flex-1"></div>
                    <button type="button" onclick="toggleCodeView('edit-comment-area-${commentId}')" class="p-1.5 text-slate-600 hover:bg-slate-200 rounded" title="Toggle Code View">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l-4 3 4 3m8-6l4 3-4 3"></path></svg>
                    </button>
                </div>
                <div 
                    id="edit-comment-area-${commentId}" 
                    contenteditable="true" 
                    onkeydown="handleRteKeyDown(event)"
                    oninput="handleRteInput(event)"
                    class="p-3 min-h-[60px] max-h-48 overflow-y-auto focus:outline-none text-sm text-slate-700 bg-white list-disc list-inside prose prose-sm max-w-none"
                    style="outline: none;"
                >${existingHtml}</div>
                <textarea 
                    id="edit-comment-area-${commentId}-code" 
                    class="hidden font-mono text-sm p-3 w-full flex-1 outline-none text-slate-700 bg-slate-50 break-words min-h-[100px] resize-y"
                ></textarea>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="cancelCommentEdit(${commentId})" class="px-3 py-1.5 text-xs text-slate-600 bg-white border border-slate-300 hover:bg-slate-50 rounded shadow-sm font-medium transition-colors">Cancel</button>
                <button type="button" onclick="submitEditedComment(${commentId})" class="px-3 py-1.5 text-xs text-white bg-teal-600 hover:bg-teal-700 rounded shadow-sm font-medium transition-colors">Save</button>
            </div>
        </div>
    `;
}

function cancelCommentEdit(commentId) {
    const parent = document.getElementById('comment-' + commentId);
    if (!parent) return;

    const contentDiv = parent.querySelector('.comment-content');
    contentDiv.innerHTML = unescape(contentDiv.dataset.originalHtml);
}

async function submitEditedComment(commentId) {
    showSavingOverlay();
    let content = getEditorContent('edit-comment-area-' + commentId).trim();
    if (content === '<p><br></p>') content = '';
    
    if (!content) {
        showAlert('Invalid Input', 'Comment cannot be empty.', 'warning');
        hideSavingOverlay();
        return;
    }

    const res = await fetch('api/tasks.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'edit_comment', comment_id: commentId, content })
    });

    if(res.ok) {
        const taskId = document.getElementById('task-modal-id').value;
        openTaskModal(taskId); // refresh task modal
    } else {
        showAlert('Edit Failed', 'Failed to edit comment. Ensure you have permission.', 'danger');
    }
    hideSavingOverlay();
}

async function deleteAttachment(attachmentId) {
    if(!confirm('Are you sure you want to delete this attachment?')) return;
    
    showSavingOverlay();
    const res = await fetch('api/tasks.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'delete_attachment', attachment_id: attachmentId })
    });
    if(res.ok) {
        const id = document.getElementById('task-modal-id').value;
        openTaskModal(id);
    }
    hideSavingOverlay();
}

async function uploadTaskAttachment(input) {
    const id = document.getElementById('task-modal-id').value;
    if (!id || input.files.length === 0) return;
    
    const formData = new FormData();
    formData.append('action', 'add_attachment');
    formData.append('task_id', id);
    formData.append('file', input.files[0]);
    
    const btn = input.previousElementSibling;
    const oldText = btn.innerText;
    btn.innerText = 'Uploading...';
    btn.disabled = true;
    showSavingOverlay();
    
    try {
        const res = await fetch('api/tasks.php', {
            method: 'POST',
            body: formData
        });
        
        if (res.ok) {
            const data = await res.json();
            if (window.handleApiError) window.handleApiError(data);
            openTaskModal(id);
        } else {
            showAlert('Upload Failed', 'Upload failed', 'danger');
        }
    } catch(e) {
        console.error(e);
        showAlert('Upload Failed', 'Upload completely failed.', 'danger');
    } finally {
        input.value = '';
        btn.innerText = oldText;
        btn.disabled = false;
        hideSavingOverlay();
    }
}

// Global list of all available users cached
window.allUsersCache = [];
window.allProjectUsersCache = [];
window.currentProjectMemberIds = [];
window.allCollaboratorsCache = [];
window.lastSearchedCollaborators = [];

// ==========================================
// Project Member Assignment UI
// ==========================================
async function toggleProjectMemberDropdown(event) {
    if (window.currentUserProjectRole === 'viewer') {
        showAlert('Permission Denied', 'You must be a manager or member of this project to edit default notifications.', 'warning');
        return;
    }

    const dropdown = document.getElementById('project-member-dropdown');
    
    if (dropdown.classList.contains('hidden')) {
        dropdown.classList.remove('hidden');
        dropdown.classList.add('flex');
        
        if (window.allProjectUsersCache.length === 0) {
            await searchProjectMembers('');
        } else {
            renderProjectMemberList(window.allProjectUsersCache);
        }
        
        setTimeout(() => document.getElementById('project-member-search').focus(), 50);
        
        const outsideClickListener = (e) => {
            if (!dropdown.contains(e.target) && !e.target.closest('#project-members')) {
                dropdown.classList.add('hidden');
                dropdown.classList.remove('flex');
                document.removeEventListener('click', outsideClickListener);
            }
        };
        setTimeout(() => document.addEventListener('click', outsideClickListener), 10);
    } else {
        dropdown.classList.add('hidden');
        dropdown.classList.remove('flex');
    }
}

async function searchProjectMembers(query) {
    try {
        const res = await fetch(`api/users.php?project_id=${currentProjectId}&search=${encodeURIComponent(query)}`);
        const users = await res.json();
        
        if (query === '') {
            window.allProjectUsersCache = users;
        }
        window.lastSearchedProjectUsers = users;
        renderProjectMemberList(users);
    } catch(e) {
        console.error('Failed to search project users', e);
    }
}

function renderProjectMemberList(users) {
    const ul = document.getElementById('project-member-dropdown-list');
    ul.innerHTML = '';
    
    if (users.length === 0) {
        ul.innerHTML = '<li class="p-2 text-slate-400 italic">No users found</li>';
        return;
    }
    
    users.forEach(user => {
        const isSelected = window.currentProjectMemberIds.includes(user.id);
        const checkIcon = isSelected 
            ? `<svg class="w-4 h-4 text-teal-500 ml-auto" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>` 
            : `<div class="w-4 h-4 ml-auto"></div>`;
            
        const li = document.createElement('li');
        li.className = 'p-1.5 hover:bg-slate-100 rounded flex justify-between items-center cursor-pointer transition-colors mt-0.5';
        li.onclick = (e) => {
            e.stopPropagation();
            toggleProjectMemberAssignment(user.id);
        };
        li.innerHTML = `
            <div class="flex items-center space-x-2">
                <div class="w-6 h-6 rounded-full bg-slate-200 border-2 border-white text-slate-600 text-[10px] font-bold flex items-center justify-center flex-shrink-0">
                    ${user.name.charAt(0).toUpperCase()}
                </div>
                <div class="flex flex-col text-left">
                    <span class="font-medium text-slate-700 leading-tight">${user.name}</span>
                    <span class="text-[10px] text-slate-400 leading-tight">${user.email}</span>
                </div>
            </div>
            ${checkIcon}
        `;
        ul.appendChild(li);
    });
}

async function toggleProjectMemberAssignment(userId) {
    showSavingOverlay();
    const index = window.currentProjectMemberIds.indexOf(userId);
    if (index === -1) {
        window.currentProjectMemberIds.push(userId);
    } else {
        window.currentProjectMemberIds.splice(index, 1);
    }
    
    renderProjectMemberList(document.getElementById('project-member-search').value ? window.lastSearchedProjectUsers : window.allProjectUsersCache);
    
    try {
        await fetch('api/projects.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'update_default_notify', project_id: currentProjectId, member_ids: window.currentProjectMemberIds })
        });
        
        // Soft refresh the project members stack without jittering the UI
        const res = await fetch(`api/projects.php?id=${currentProjectId}`);
        const project = await res.json();
        
        if (!project.error) {
            const projectMembersDiv = document.getElementById('project-members');
            if (projectMembersDiv) {
                projectMembersDiv.innerHTML = '';
                if (project.default_notify_names) {
                    const names = project.default_notify_names.split(',');
                    names.slice(0, 5).forEach(n => {
                        const initial = n.trim().charAt(0).toUpperCase();
                        projectMembersDiv.innerHTML += `<div class="w-8 h-8 rounded-full bg-teal-100 border-2 border-white cursor-pointer text-teal-700 text-sm font-bold flex items-center justify-center shadow-sm" title="${n}" onclick="toggleProjectMemberDropdown(event)">${initial}</div>`;
                    });
                    if (names.length > 5) {
                        projectMembersDiv.innerHTML += `<div class="w-8 h-8 rounded-full bg-slate-100 border-2 border-white cursor-pointer text-slate-500 text-xs font-bold flex items-center justify-center shadow-sm" onclick="toggleProjectMemberDropdown(event)">+${names.length - 5}</div>`;
                    }
                } else {
                    projectMembersDiv.innerHTML = `
                        <div class="w-8 h-8 rounded-full bg-slate-100 border-2 border-white cursor-pointer hover:bg-slate-200 transition-colors flex items-center justify-center text-slate-400 group shadow-sm" onclick="toggleProjectMemberDropdown(event)" title="Add Default Notify User">
                            <svg class="w-4 h-4 group-hover:text-slate-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                        </div>
                    `;
                }
            }
        }
    } catch (e) {
        console.error('Error updating project members:', e);
    } finally {
        hideLoadingOverlay();
    }
}

// ==========================================
// Task Assignee UI
// ==========================================

window.activeAssigneeTaskId = null;
window.assigneeDropdownContext = 'modal'; // 'modal' or 'list'

function closeAssigneeDropdownUI(dropdown) {
    dropdown.classList.add('hidden');
    dropdown.classList.remove('flex');
    closeAssigneeBackdrop();
}

function openAssigneeBackdrop(closeCallback) {
    let backdrop = document.getElementById('assignee-dropdown-backdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.id = 'assignee-dropdown-backdrop';
        backdrop.className = 'fixed inset-0 z-[60] bg-transparent';
        document.body.appendChild(backdrop);
    }
    backdrop.style.display = 'block';
    backdrop.onclick = (e) => {
        e.stopPropagation();
        closeAssigneeBackdrop();
        if (closeCallback) closeCallback();
    };
}

function closeAssigneeBackdrop() {
    const backdrop = document.getElementById('assignee-dropdown-backdrop');
    if (backdrop) {
        backdrop.style.display = 'none';
        backdrop.onclick = null;
    }
}

async function toggleAssigneeDropdown(event) {
    window.assigneeDropdownContext = 'modal';
    window.activeAssigneeTaskId = document.getElementById('task-modal-id').value || null;

    const dropdown = document.getElementById('assignee-dropdown');
    
    // Toggle visibility
    if (dropdown.classList.contains('hidden')) {
        // Move dropdown to body to avoid clipping and z-index issues with the transparent backdrop
        if (dropdown.parentElement !== document.body) {
            document.body.appendChild(dropdown);
        }

        const rect = event.currentTarget.getBoundingClientRect();
        dropdown.style.position = 'fixed';
        dropdown.style.top = (rect.bottom + 4) + 'px';
        dropdown.style.left = rect.left + 'px';
        dropdown.style.zIndex = '61';

        dropdown.classList.remove('hidden');
        dropdown.classList.add('flex');

        // Fetch or render users
        if (window.allUsersCache.length === 0) {
            await searchAssignees('');
        } else {
            renderAssigneeList(window.allUsersCache);
        }

        // Focus search box
        setTimeout(() => document.getElementById('assignee-search').focus(), 50);

        // Open an invisible backdrop to catch clicks and prevent interaction with elements underneath
        openAssigneeBackdrop(() => {
            dropdown.classList.add('hidden');
            dropdown.classList.remove('flex');
        });
    } else {
        closeAssigneeDropdownUI(dropdown);
    }
}

window.openListViewAssigneeDropdown = async function(event, taskId, assigneeIdsStr) {
    event.stopPropagation();
    window.assigneeDropdownContext = 'list';
    window.activeAssigneeTaskId = taskId;
    window.currentTaskAssigneeIds = assigneeIdsStr ? String(assigneeIdsStr).split(',').map(id => parseInt(id)) : [];

    const dropdown = document.getElementById('assignee-dropdown');
    
    // Move dropdown to body for absolute positioning avoiding table clipping
    if (dropdown.parentElement !== document.body) {
        document.body.appendChild(dropdown);
    }

    const rect = event.currentTarget.getBoundingClientRect();
    dropdown.style.position = 'fixed';
    dropdown.style.top = (rect.bottom + 4) + 'px';
    dropdown.style.left = rect.left + 'px';
    dropdown.style.zIndex = '61';
    
    dropdown.classList.remove('hidden');
    dropdown.classList.add('flex');

    if (window.allUsersCache.length === 0) {
        await searchAssignees('');
    } else {
        renderAssigneeList(window.allUsersCache);
    }

    setTimeout(() => document.getElementById('assignee-search').focus(), 50);

    // Open an invisible backdrop to catch clicks and prevent interaction with elements underneath
    openAssigneeBackdrop(() => {
        dropdown.classList.add('hidden');
        dropdown.classList.remove('flex');
    });
};

async function searchAssignees(query) {
    try {
        const projectIdParam = (typeof currentProjectId !== 'undefined') ? `&project_id=${currentProjectId}` : '';
        const res = await fetch(`api/users.php?search=${encodeURIComponent(query)}${projectIdParam}`);
        const users = await res.json();
        
        if (query === '') {
            window.allUsersCache = users;
        }
        window.lastSearchedUsers = users;
        renderAssigneeList(users);
    } catch(e) {
        console.error('Failed to search users', e);
    }
}

function renderAssigneeList(users) {
    const ul = document.getElementById('assignee-dropdown-list');
    ul.innerHTML = '';
    
    if (users.length === 0) {
        ul.innerHTML = '<li class="p-2 text-slate-400 italic">No users found</li>';
        return;
    }
    
    users.forEach(user => {
        const isSelected = window.currentTaskAssigneeIds.includes(Number(user.id));
        const checkIcon = isSelected 
            ? `<svg class="w-4 h-4 text-indigo-500 ml-auto" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>` 
            : `<div class="w-4 h-4 ml-auto"></div>`;
            
        const li = document.createElement('li');
        li.className = 'p-1.5 hover:bg-slate-100 rounded flex justify-between items-center cursor-pointer transition-colors mt-0.5';
        li.onclick = (e) => {
            e.stopPropagation(); // prevent closing
            toggleUserAssignment(Number(user.id));
        };
        li.innerHTML = `
            <div class="flex items-center space-x-2">
                <div class="w-6 h-6 rounded-full bg-slate-200 border-2 border-white text-slate-600 text-[10px] font-bold flex items-center justify-center flex-shrink-0">
                    ${user.name.charAt(0).toUpperCase()}
                </div>
                <div class="flex flex-col text-left">
                    <span class="font-medium text-slate-700 leading-tight">${user.name}</span>
                    <span class="text-[10px] text-slate-400 leading-tight">${user.email}</span>
                </div>
            </div>
            ${checkIcon}
        `;
        ul.appendChild(li);
    });
}

async function toggleUserAssignment(userId) {
    showSavingOverlay();
    const index = window.currentTaskAssigneeIds.indexOf(userId);
    if (index === -1) {
        window.currentTaskAssigneeIds.push(userId);
    } else {
        window.currentTaskAssigneeIds.splice(index, 1);
    }
    
    // Re-render the dropdown list with new selection states
    renderAssigneeList(document.getElementById('assignee-search').value ? window.lastSearchedUsers : window.allUsersCache);
    
    const taskId = window.activeAssigneeTaskId || document.getElementById('task-modal-id').value;
    if (!taskId) return;

    // Calculate selected users for soft DOM updates
    const allPossibleUsers = [...window.allUsersCache, ...window.lastSearchedUsers];
    const selectedUsers = [];
    window.currentTaskAssigneeIds.forEach(id => {
        const u = allPossibleUsers.find(user => Number(user.id) === id);
        if (u && !selectedUsers.find(su => su.id === u.id)) selectedUsers.push(u);
    });

    const assigneesString = window.currentTaskAssigneeIds.join(',');

    // 1. Immediately update modal UI locally
    const nameEl = document.getElementById('task-modal-assignee-name');
    const stack = document.getElementById('task-modal-assignees-stack');
    if (nameEl) {
        if (selectedUsers.length === 0) {
            nameEl.innerText = 'Unassigned';
            if (stack) {
                stack.innerHTML = '';
                stack.classList.add('hidden');
            }
        } else {
            nameEl.innerText = selectedUsers.map(u => u.name).join(', ') || 'Unassigned';
            if (stack) {
                stack.classList.remove('hidden');
                stack.innerHTML = '';
                selectedUsers.slice(0, 3).forEach(u => {
                    const initial = u.name.charAt(0).toUpperCase();
                    stack.innerHTML += `<div class="w-6 h-6 rounded-full bg-teal-100 border-2 border-white text-teal-700 text-[10px] font-bold flex items-center justify-center">${initial}</div>`;
                });
                if (selectedUsers.length > 3) {
                    stack.innerHTML += `<div class="w-6 h-6 rounded-full bg-slate-100 border-2 border-white text-slate-500 text-[9px] font-bold flex items-center justify-center">+${selectedUsers.length - 3}</div>`;
                }
            }
        }
    }

    // 2. Soft update List View Row
    const rows = document.querySelectorAll('.task-row-' + taskId);
    rows.forEach(row => {
        row.dataset.assigneeIds = assigneesString;
        const cells = row.querySelectorAll('td');
        if (cells.length > 1) {
            const container = cells[1].querySelector('.inline-flex');
            if (container) {
                container.setAttribute('onclick', `if(window.openListViewAssigneeDropdown) window.openListViewAssigneeDropdown(event, ${taskId}, '${assigneesString}')`);
                let html = '<span class="text-slate-400 italic">Unassigned</span>';
                if (selectedUsers.length > 0) {
                    const nameStr = selectedUsers.map(u => u.name).join(', ');
                    html = `<div class="flex -space-x-2 overflow-hidden" title="${nameStr}">`;
                    selectedUsers.slice(0, 3).forEach(u => {
                        const initial = u.name.charAt(0).toUpperCase();
                        html += `<div class="w-6 h-6 rounded-full bg-teal-100 border-2 border-white text-teal-700 text-[10px] font-bold flex items-center justify-center">${initial}</div>`;
                    });
                    if (selectedUsers.length > 3) {
                        html += `<div class="w-6 h-6 rounded-full bg-slate-100 border-2 border-white text-slate-500 text-[9px] font-bold flex items-center justify-center">+${selectedUsers.length - 3}</div>`;
                    }
                    html += `</div>`;
                }
                container.innerHTML = html;
            }
        }
    });

    // 3. Soft update Kanban Board Card
    const cards = document.querySelectorAll(`.task-card[data-task-id="${taskId}"]`);
    cards.forEach(card => {
        const header = card.querySelector('.flex.justify-between.items-start.mb-2');
        if (header) {
            const oldStack = header.querySelector('.flex.-space-x-2.overflow-hidden');
            if (oldStack) oldStack.remove();

            if (selectedUsers.length > 0) {
                const nameStr = selectedUsers.map(u => u.name).join(', ');
                let html = `<div class="flex -space-x-2 overflow-hidden ml-2 flex-shrink-0" title="${nameStr}">`;
                selectedUsers.slice(0, 3).forEach(u => {
                    const initial = u.name.charAt(0).toUpperCase();
                    html += `<div class="w-6 h-6 rounded-full bg-teal-100 border-2 border-white text-teal-700 text-[10px] font-bold flex items-center justify-center">${initial}</div>`;
                });
                if (selectedUsers.length > 3) {
                    html += `<div class="w-6 h-6 rounded-full bg-slate-100 border-2 border-white text-slate-500 text-[9px] font-bold flex items-center justify-center">+${selectedUsers.length - 3}</div>`;
                }
                html += `</div>`;
                header.insertAdjacentHTML('beforeend', html);
            }
        }
    });
    
    try {
        // Run network requests and await completion without blocking UI
        const resAssign = await fetch('api/tasks.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'update_details', task_id: taskId, details: { assignee_ids: window.currentTaskAssigneeIds } })
        });
        const assignData = await resAssign.json();
        if (window.handleApiError) window.handleApiError(assignData);
    } catch (e) {
        console.error('Error updating assignees:', e);
    } finally {
        hideSavingOverlay();
    }
}

// ==========================================
// Task Collaborator UI
// ==========================================

window.activeCollaboratorTaskId = null;

window.openListViewCollaboratorDropdown = async function(event, taskId, collaboratorIdsStr) {
    event.stopPropagation();
    window.activeCollaboratorTaskId = taskId;
    window.currentTaskCollaboratorIds = collaboratorIdsStr ? String(collaboratorIdsStr).split(',').map(id => parseInt(id)) : [];

    const dropdown = document.getElementById('collaborator-dropdown');
    
    // Move dropdown to body for absolute positioning avoiding table clipping
    if (dropdown.parentElement !== document.body) {
        document.body.appendChild(dropdown);
    }

    const rect = event.currentTarget.getBoundingClientRect();
    dropdown.style.position = 'fixed';
    dropdown.style.top = (rect.bottom + 4) + 'px';
    dropdown.style.left = rect.left + 'px';
    dropdown.style.zIndex = '61';
    
    dropdown.classList.remove('hidden');
    dropdown.classList.add('flex');

    if (!window.allCollaboratorsCache || window.allCollaboratorsCache.length === 0) {
        await searchCollaborators('');
    } else {
        renderCollaboratorList(window.allCollaboratorsCache);
    }

    setTimeout(() => document.getElementById('collaborator-search').focus(), 50);

    // Open an invisible backdrop to catch clicks and prevent interaction with elements underneath
    openAssigneeBackdrop(() => {
        dropdown.classList.add('hidden');
        dropdown.classList.remove('flex');
    });
};

async function toggleCollaboratorDropdown(event) {
    window.activeCollaboratorTaskId = document.getElementById('task-modal-id').value || null;

    const dropdown = document.getElementById('collaborator-dropdown');
    
    // Toggle visibility
    if (dropdown.classList.contains('hidden')) {
        if (dropdown.parentElement !== document.body) {
            document.body.appendChild(dropdown);
        }

        const rect = event.currentTarget.getBoundingClientRect();
        dropdown.style.position = 'fixed';
        dropdown.style.top = (rect.bottom + 4) + 'px';
        dropdown.style.left = rect.left + 'px';
        dropdown.style.zIndex = '61';

        dropdown.classList.remove('hidden');
        dropdown.classList.add('flex');

        if (!window.allCollaboratorsCache || window.allCollaboratorsCache.length === 0) {
            await searchCollaborators('');
        } else {
            renderCollaboratorList(window.allCollaboratorsCache);
        }

        setTimeout(() => document.getElementById('collaborator-search').focus(), 50);

        openAssigneeBackdrop(() => {
            dropdown.classList.add('hidden');
            dropdown.classList.remove('flex');
        });
    } else {
        dropdown.classList.add('hidden');
        dropdown.classList.remove('flex');
        closeAssigneeBackdrop();
    }
}

async function searchCollaborators(query) {
    try {
        const projectIdParam = (typeof currentProjectId !== 'undefined') ? `&project_id=${currentProjectId}` : '';
        const res = await fetch(`api/users.php?search=${encodeURIComponent(query)}${projectIdParam}`);
        const users = await res.json();
        
        if (query === '') {
            window.allCollaboratorsCache = users;
        }
        window.lastSearchedCollaborators = users;
        renderCollaboratorList(users);
    } catch(e) {
        console.error('Failed to search collaborators', e);
    }
}

function renderCollaboratorList(users) {
    const ul = document.getElementById('collaborator-dropdown-list');
    ul.innerHTML = '';
    
    if (users.length === 0) {
        ul.innerHTML = '<li class="p-2 text-slate-400 italic">No users found</li>';
        return;
    }
    
    users.forEach(user => {
        const isSelected = window.currentTaskCollaboratorIds.includes(Number(user.id));
        const checkIcon = isSelected 
            ? `<svg class="w-4 h-4 text-indigo-500 ml-auto" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>` 
            : `<div class="w-4 h-4 ml-auto"></div>`;
            
        const li = document.createElement('li');
        li.className = 'p-1.5 hover:bg-slate-100 rounded flex justify-between items-center cursor-pointer transition-colors mt-0.5';
        li.onclick = (e) => {
            e.stopPropagation(); // prevent closing
            toggleCollaboratorAssignment(Number(user.id));
        };
        li.innerHTML = `
            <div class="flex items-center space-x-2">
                <div class="w-6 h-6 rounded-full bg-slate-200 border-2 border-white text-slate-600 text-[10px] font-bold flex items-center justify-center flex-shrink-0">
                    ${user.name.charAt(0).toUpperCase()}
                </div>
                <div class="flex flex-col text-left">
                    <span class="font-medium text-slate-700 leading-tight">${user.name}</span>
                    <span class="text-[10px] text-slate-400 leading-tight">${user.email}</span>
                </div>
            </div>
            ${checkIcon}
        `;
        ul.appendChild(li);
    });
}

async function toggleCollaboratorAssignment(userId) {
    showSavingOverlay();
    const index = window.currentTaskCollaboratorIds.indexOf(userId);
    if (index === -1) {
        window.currentTaskCollaboratorIds.push(userId);
    } else {
        window.currentTaskCollaboratorIds.splice(index, 1);
    }
    
    // Re-render the dropdown list with new selection states
    renderCollaboratorList(document.getElementById('collaborator-search').value ? window.lastSearchedCollaborators : window.allCollaboratorsCache);
    
    const taskId = window.activeCollaboratorTaskId || document.getElementById('task-modal-id').value;
    if (!taskId) return;

    // Immediately update modal UI locally
    const nameEl = document.getElementById('task-modal-collaborator-name');
    const stack = document.getElementById('task-modal-collaborators-stack');
    if (window.currentTaskCollaboratorIds.length === 0) {
        nameEl.innerText = 'No collaborators';
        if (stack) {
            stack.innerHTML = '';
            stack.classList.add('hidden');
        }
    } else {
        if (stack) {
            stack.classList.remove('hidden');
            stack.innerHTML = '';
        }
        
        const allPossibleUsers = [...window.allCollaboratorsCache, ...window.lastSearchedCollaborators];
        const selectedUsers = [];
        window.currentTaskCollaboratorIds.forEach(id => {
            const u = allPossibleUsers.find(user => Number(user.id) === id);
            if (u && !selectedUsers.find(su => su.id === u.id)) selectedUsers.push(u);
        });
        
        nameEl.innerText = selectedUsers.map(u => u.name).join(', ') || 'No collaborators';
        
        if (stack) {
            selectedUsers.slice(0, 3).forEach(u => {
                const initial = u.name.charAt(0).toUpperCase();
                stack.innerHTML += `<div class="w-6 h-6 rounded-full bg-indigo-100 border-2 border-white text-indigo-700 text-[10px] font-bold flex items-center justify-center">${initial}</div>`;
            });
            if (selectedUsers.length > 3) {
                stack.innerHTML += `<div class="w-6 h-6 rounded-full bg-slate-100 border-2 border-white text-slate-500 text-[9px] font-bold flex items-center justify-center">+${selectedUsers.length - 3}</div>`;
            }
        }
    }

    const collaboratorsString = window.currentTaskCollaboratorIds.join(',');
    const allPossibleUsersList = [...window.allCollaboratorsCache, ...window.lastSearchedCollaborators];
    const selectedUsersList = [];
    window.currentTaskCollaboratorIds.forEach(id => {
        const u = allPossibleUsersList.find(user => Number(user.id) === id);
        if (u && !selectedUsersList.find(su => su.id === u.id)) selectedUsersList.push(u);
    });

    // 2. Soft update List View Row
    const rows = document.querySelectorAll('.task-row-' + taskId);
    rows.forEach(row => {
        row.dataset.collaboratorIds = collaboratorsString;
        const cells = row.querySelectorAll('td');
        if (cells.length > 2) {
            const container = cells[2].querySelector('.inline-flex');
            if (container) {
                container.setAttribute('onclick', `if(window.openListViewCollaboratorDropdown) window.openListViewCollaboratorDropdown(event, ${taskId}, '${collaboratorsString}')`);
                let html = '<span class="text-slate-400 italic">None</span>';
                if (selectedUsersList.length > 0) {
                    const nameStr = selectedUsersList.map(u => u.name).join(', ');
                    html = `<div class="flex -space-x-2 overflow-hidden" title="${nameStr}">`;
                    selectedUsersList.slice(0, 3).forEach(u => {
                        const initial = u.name.charAt(0).toUpperCase();
                        html += `<div class="w-6 h-6 rounded-full bg-indigo-100 border-2 border-white text-indigo-700 text-[10px] font-bold flex items-center justify-center">${initial}</div>`;
                    });
                    if (selectedUsersList.length > 3) {
                        html += `<div class="w-6 h-6 rounded-full bg-slate-100 border-2 border-white text-slate-500 text-[9px] font-bold flex items-center justify-center">+${selectedUsersList.length - 3}</div>`;
                    }
                    html += `</div>`;
                }
                container.innerHTML = html;
            }
        }
    });
    
    try {
        const resAssign = await fetch('api/tasks.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'update_details', task_id: taskId, details: { collaborator_ids: window.currentTaskCollaboratorIds } })
        });
        const assignData = await resAssign.json();
        if (window.handleApiError) window.handleApiError(assignData);
    } catch (e) {
        console.error('Error updating collaborators:', e);
    } finally {
        hideSavingOverlay();
    }
}

function closeTaskModal() {
    document.getElementById('task-modal').classList.add('hidden');
    stopTimer(true); // Stop timer on close
}

function copyTaskLink() {
    const taskId = document.getElementById('task-modal-id').value;
    if (!taskId) return;
    const url = window.location.origin + window.location.pathname + '?id=' + currentProjectId + '&task_id=' + taskId;
    navigator.clipboard.writeText(url).then(() => {
        showAlert('Link Copied', 'Task link copied to your clipboard.', 'success');
    }).catch(() => {
        showAlert('Error', 'Failed to copy link.', 'danger');
    });
}

function toggleProgress() {
    showSavingOverlay();
    const taskId = document.getElementById('task-modal-id').value;
    if (!taskId) {
        hideSavingOverlay();
        return;
    }

    fetch('api/tasks.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'toggle_time_track', task_id: taskId })
    }).then(res => res.json())
      .then(data => {
          if (data.status === 'success') {
              // Refresh the modal to sync state from database
              openTaskModal(taskId);
          } else {
              showAlert('Error', 'Failed to toggle time track', 'danger');
          }
          hideSavingOverlay();
      });
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

    showAlert('Success', 'All subtasks in section unchecked', 'success');
}

function promptAddSubtask(parentId) {
    const title = prompt("Enter subtask title:");
    if(!title) return;
    
    showSavingOverlay();
    fetch('api/tasks.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            title: title,
            parent_task_id: parentId,
            status: 'todo'
        })
    }).then(res => res.json()).then(data => {
        if(data.status === 'success') {
            openTaskModal(parentId); // Reload modal
            if(typeof currentProjectId !== 'undefined') loadProjectBoard(currentProjectId); // Refresh board view to show new subtask expander
        } else {
            showAlert('Error', 'Failed to create subtask', 'danger');
        }
        hideSavingOverlay();
    });
}

function toggleProjectLinkDropdown() {
    const dropdown = document.getElementById('project-link-dropdown');
    dropdown.classList.toggle('hidden');
    if (!dropdown.classList.contains('hidden')) {
        document.getElementById('project-link-search').focus();
        searchProjectsToLink('');
    }
}

async function searchProjectsToLink(query) {
    const list = document.getElementById('project-link-list');
    
    try {
        const res = await fetch('api/projects.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'search', query: query })
        });
        const clone = res.clone();
        let data;
        try {
            data = await res.json();
        } catch (jsonErr) {
            const rawText = await clone.text();
            console.error("searchProjectsToLink JSON parse failed. Raw response:", rawText);
            throw jsonErr;
        }
        if(window.handleApiError) window.handleApiError(data);
        
        if(data.status !== 'success') return;
        
        list.innerHTML = '';
        if(data.projects.length === 0) {
            list.innerHTML = '<li class="p-2 text-slate-400 italic text-xs">No projects found.</li>';
            return;
        }
        
        data.projects.forEach(p => {
            const li = document.createElement('li');
            li.className = 'p-2 hover:bg-slate-50 cursor-pointer text-slate-700 truncate';
            li.textContent = p.name;
            li.onclick = () => addProjectToTask(p.id);
            list.appendChild(li);
        });
    } catch (e) {
        console.error(e);
        list.innerHTML = '<li class="p-2 text-red-500 text-xs">Search failed.</li>';
    }
}

async function addProjectToTask(projectId) {
    const taskId = document.getElementById('task-modal-id').value;
    showSavingOverlay();
    try {
        const res = await fetch('api/tasks.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'add_to_project', task_id: taskId, project_id: projectId })
        });
        const data = await res.json();
        if(window.handleApiError) window.handleApiError(data);
        
        document.getElementById('project-link-dropdown').classList.add('hidden');
        if(data.status === 'success') {
            openTaskModal(taskId); // Reload
        }
    } catch (e) {
        console.error(e);
    } finally {
        hideSavingOverlay();
    }
}

async function changeTaskProjectSection(projectId, selectElem) {
    const taskId = document.getElementById('task-modal-id').value;
    const sectionId = selectElem.value;
    showSavingOverlay();
    try {
        const res = await fetch('api/tasks.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'change_project_section', task_id: taskId, project_id: projectId, section_id: sectionId })
        });
        const data = await res.json();
        if(window.handleApiError) window.handleApiError(data);
        if (data.status === 'success' && projectId == currentProjectId) {
            loadProjectBoard(currentProjectId); // Refresh board if it affects current view
        }
    } catch (e) {
        console.error(e);
    } finally {
        hideSavingOverlay();
    }
}

// Subtasks linking (removed from UI but kept in API/logic)
function toggleLinkSubtaskDropdown() {
    const dropdown = document.getElementById('link-subtask-dropdown');
    if (dropdown.classList.contains('hidden')) {
        dropdown.classList.remove('hidden');
        dropdown.classList.add('flex');
        document.getElementById('link-subtask-search').focus();
        
        const outsideClickListener = (e) => {
            if (!dropdown.contains(e.target) && !e.target.closest('[onclick="toggleLinkSubtaskDropdown()"]')) {
                dropdown.classList.add('hidden');
                dropdown.classList.remove('flex');
                document.removeEventListener('click', outsideClickListener);
            }
        };
        setTimeout(() => document.addEventListener('click', outsideClickListener), 10);
    } else {
        dropdown.classList.add('hidden');
        dropdown.classList.remove('flex');
    }
}

async function searchTasksToLink(query) {
    if(!query || query.length < 2) {
        document.getElementById('link-subtask-list').innerHTML = '<li class="p-2 text-slate-400 italic text-xs">Type at least 2 char...</li>';
        return;
    }
    const res = await fetch(`api/tasks.php`);
    const allTasks = await res.json();
    
    const parentIdStr = document.getElementById('task-modal-id').value;
    if(!parentIdStr) return;
    const parentId = parseInt(parentIdStr, 10);

    const filtered = allTasks.filter(t => 
        t.title.toLowerCase().includes(query.toLowerCase()) 
        && t.id !== parentId 
        && t.parent_task_id !== parentId
        && !(window.currentTaskSubtaskIds && window.currentTaskSubtaskIds.includes(t.id))
    );
    
    const ul = document.getElementById('link-subtask-list');
    ul.innerHTML = '';
    if(filtered.length === 0) {
        ul.innerHTML = '<li class="p-2 text-slate-400 italic text-xs">No matching tasks...</li>';
        return;
    }
    
    filtered.forEach(task => {
        const li = document.createElement('li');
        li.className = 'p-2 hover:bg-slate-100 rounded cursor-pointer transition-colors mt-0.5 flex flex-col';
        
        const countStr = task.subtask_count > 0 ? `<span class="text-[10px] font-semibold text-slate-500 bg-slate-200 px-1 rounded ml-1">(${task.subtask_count})</span>` : '';
        const projStr = task.project_names ? `<span class="text-[10px] text-teal-600 bg-teal-50 px-1.5 py-0.5 rounded border border-teal-100 mr-2">${task.project_names}</span>` : '';
        
        li.innerHTML = `
            <div class="flex items-center w-full truncate">
                <span class="text-xs font-medium text-slate-400 mr-2">#${task.id}</span>
                <span class="text-sm font-medium text-slate-700 truncate" title="${task.title}">${task.title}</span>
                ${countStr}
            </div>
            <div class="flex items-center mt-1 w-full truncate">
                ${projStr}
            </div>
        `;
        li.onclick = () => submitLinkSubtask(parentId, task.id);
        ul.appendChild(li);
    });
}

function submitLinkSubtask(parentId, subtaskId) {
    showSavingOverlay();
    fetch('api/tasks.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'link_subtask',
            parent_task_id: parentId,
            subtask_id: subtaskId
        })
    }).then(res => res.json()).then(data => {
        if(data.status === 'success') {
            toggleLinkSubtaskDropdown();
            openTaskModal(parentId); // Reload parent to show new subtask
        } else {
            showAlert('Error', 'Failed to link subtask.', 'danger');
        }
        hideSavingOverlay();
    });
}

function unlinkSubtask(parentId, subtaskId) {
    if (!confirm('Are you sure you want to unlink this subtask?')) return;
    
    showSavingOverlay();
    fetch('api/tasks.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'unlink_subtask',
            parent_task_id: parentId,
            subtask_id: subtaskId
        })
    }).then(res => res.json()).then(data => {
        if(data.status === 'success') {
            openTaskModal(parentId); // Reload parent to reflect changes
            if(typeof currentProjectId !== 'undefined') loadProjectBoard(currentProjectId);
        } else {
            showAlert('Error', 'Failed to unlink subtask: ' + data.message, 'danger');
        }
        hideSavingOverlay();
    }).catch(e => {
        console.error('Error unlinking subtask', e);
        showAlert('Error', 'An error occurred while unlinking.', 'danger');
        hideSavingOverlay();
    });
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
