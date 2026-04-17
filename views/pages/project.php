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
        <div class="flex space-x-3 items-center relative">
            <div class="flex -space-x-2 mr-4 cursor-pointer hover:opacity-80 transition-opacity" id="project-members" onclick="toggleProjectMemberDropdown(event)" title="Manage Project Members">
                <!-- Avatars will be injected here -->
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
                    <!-- Global users injected here -->
                </ul>
            </div>

            <button class="text-slate-500 hover:text-slate-700 px-3 py-1.5 rounded-md text-sm font-medium transition-colors border border-slate-200 bg-white shadow-sm" onclick="copyProjectShareLink()">Share</button>
            <button onclick="promptAddTask(null)" class="bg-teal-500 hover:bg-teal-600 text-white px-4 py-1.5 rounded-md text-sm font-medium shadow-sm transition-colors flex items-center">
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
            
            <button onclick="promptAddSection()" class="shrink-0 w-80 bg-slate-50 hover:bg-slate-100 border-2 border-dashed border-slate-300 rounded-lg p-3 text-slate-500 font-medium flex items-center justify-center transition-colors h-14">
                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Add Section
            </button>
        </div>
        </div>

        <!-- List View -->
        <div id="view-list" class="flex-1 overflow-auto p-6 hidden view-panel">
            <div class="bg-white rounded-lg shadow-sm border border-slate-200 mb-4">
                <table class="w-full text-left text-sm text-slate-600" id="list-table">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 font-medium">Tasks</th>
                            <th class="px-4 py-3 font-medium">Assignees</th>
                            <th class="px-4 py-3 font-medium">Due Date</th>
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
<div id="task-modal" class="fixed inset-0 bg-slate-900/50 hidden z-50 flex justify-end" onclick="if(event.target === this) closeTaskModal()">
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
                <div>
                    <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1 block">Due Date</label>
                    <input type="date" id="task-modal-due-date" onchange="updateTaskDetails()" class="text-sm text-slate-700 border-none focus:ring-0 p-0 hover:bg-slate-50 rounded cursor-pointer">
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1 block">Status</label>
                    <select id="task-modal-status" onchange="updateTaskDetails()" class="text-sm text-slate-700 border-none focus:ring-0 p-0 rounded bg-transparent font-medium text-emerald-600">
                        <option value="todo">To Do</option>
                        <option value="in_progress">In Progress</option>
                        <option value="paused">Paused</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1 block">Est. Time</label>
                    <span id="task-modal-estimated" class="text-sm text-slate-700">0h 0m</span>
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1 block">Parent Task</label>
                    <select id="task-modal-parent-id" onchange="updateTaskDetails()" class="text-sm text-slate-700 border border-slate-200 focus:ring-teal-500 p-1 rounded bg-white w-full max-w-[150px]">
                        <option value="">None</option>
                        <!-- Dynamic options will be fetched -->
                    </select>
                </div>
            </div>

            <!-- Description -->
            <div class="mb-8">
                <label class="font-semibold text-slate-800 mb-2 block">Description</label>
                <div id="task-modal-desc" class="prose prose-sm text-slate-600 hover:bg-slate-50 focus:bg-white p-3 -ml-2 rounded cursor-text border border-transparent focus:border-slate-200 focus:shadow-inner outline-none min-h-[100px] transition-colors" contenteditable="true" onblur="updateTaskDetails()">
                </div>
            </div>

            <!-- Subtasks -->
            <div class="mb-8 relative">
                <div class="flex justify-between items-center mb-3">
                    <label class="font-semibold text-slate-800">Subtasks</label>
                    <div class="flex space-x-3 items-center">
                        <div class="relative">
                            <button onclick="toggleLinkSubtaskDropdown()" class="text-xs text-teal-600 font-medium hover:text-teal-700 focus:outline-none">Link Existing Task</button>
                            <div id="link-subtask-dropdown" class="hidden absolute right-0 bottom-full mb-2 w-72 bg-white border border-slate-200 rounded-lg shadow-xl z-50 flex-col">
                                <div class="p-2 border-b border-slate-100 flex">
                                    <input type="text" id="link-subtask-search" oninput="searchTasksToLink(this.value)" class="w-full bg-slate-50 border border-slate-200 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" placeholder="Search tasks by title..." autofocus>
                                </div>
                                <ul id="link-subtask-list" class="overflow-y-auto max-h-48 p-1 text-sm text-slate-600">
                                    <li class="p-2 text-slate-400 italic text-xs">Type to search...</li>
                                </ul>
                            </div>
                        </div>
                        <button onclick="promptAddSubtask(document.getElementById('task-modal-id').value)" class="text-xs text-teal-600 font-medium hover:text-teal-700">Add Subtask</button>
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
                        <textarea id="task-modal-new-comment" rows="2" class="w-full text-sm border border-slate-300 rounded-lg p-3 focus:ring-teal-500 focus:border-teal-500 shadow-sm" placeholder="Ask a question or post an update..."></textarea>
                        <div class="mt-2 flex justify-end">
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
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span id="create-btn-text">Create</span>
                </button>
            </div>
        </form>
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

async function openTaskModal(taskId) {
    const modal = document.getElementById('task-modal');
    
    // Fetch Task Details
    try {
        const res = await fetch(`api/tasks.php?id=${taskId}`);
        const task = await res.json();
        if(task.error) return alert(task.error);
        
        document.getElementById('task-modal-id').value = task.id;
        document.getElementById('task-modal-title').value = task.title;
        document.getElementById('task-modal-status').value = task.status;
        
        let dateVal = '';
        if(task.due_date) {
            dateVal = task.due_date.split(' ')[0];
        }
        document.getElementById('task-modal-due-date').value = dateVal;

        // Fetch parent tasks for dropdown
        const allTasksRes = await fetch('api/tasks.php');
        const allTasksList = await allTasksRes.json();
        const parentSelect = document.getElementById('task-modal-parent-id');
        parentSelect.innerHTML = '<option value="">None</option>';
        allTasksList.forEach(t => {
            if (t.id != task.id && t.id != task.parent_task_id) { // Prevent cyclic reference to self
                const opt = document.createElement('option');
                opt.value = t.id;
                opt.text = t.title;
                parentSelect.appendChild(opt);
            }
        });
        
        if (task.parent_task_id) {
            // Check if it exists in the new options list, if not add it so it selects properly
            if (!Array.from(parentSelect.options).find(o => o.value == task.parent_task_id)) {
                parentSelect.innerHTML += `<option value="${task.parent_task_id}">Task #${task.parent_task_id}</option>`;
            }
            parentSelect.value = task.parent_task_id;
        } else {
            parentSelect.value = '';
        }
        
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
        
        document.getElementById('task-modal-estimated').innerText = task.estimated_minutes ? `${Math.floor(task.estimated_minutes/60)}h ${task.estimated_minutes%60}m` : '0h 0m';
        document.getElementById('task-modal-desc').innerHTML = task.description || '';
        
        // Render Subtasks
        const subtasksContainer = document.getElementById('task-modal-subtasks');
        subtasksContainer.innerHTML = '';
        if(task.subtasks) {
            task.subtasks.forEach(sub => {
                subtasksContainer.innerHTML += `
                    <div class="flex flex-col mb-1 group">
                        <div class="flex items-center space-x-3">
                            <input type="checkbox" ${sub.status==='completed'?'checked':''} onchange="updateSubtaskStatus(${sub.id}, this)" class="rounded text-teal-500 focus:ring-teal-500 focus:ring-offset-0 w-4 h-4 cursor-pointer">
                            <span class="flex-1 text-sm text-slate-700 cursor-pointer" onclick="openTaskModal(${sub.id})">${sub.title}</span>
                            <span class="text-xs text-slate-400 bg-slate-100 px-2 py-0.5 rounded uppercase">${sub.status}</span>
                        </div>
                    </div>
                `;
            });
        }
        
        // Render Comments
        const commentsContainer = document.getElementById('task-modal-comments-list');
        commentsContainer.innerHTML = '';
        if(task.comments) {
            task.comments.forEach(comment => {
                const dt = new Date(comment.created_at).toLocaleString();
                const initials = comment.user_name ? comment.user_name.substring(0, 2) : 'U';
                commentsContainer.innerHTML += `
                    <div class="flex space-x-3">
                        <div class="w-8 h-8 rounded-full bg-teal-100 text-teal-700 text-xs font-bold flex items-center justify-center flex-shrink-0" title="${comment.user_name}">${initials}</div>
                        <div class="flex-1 bg-slate-50 p-3 rounded-lg rounded-tl-none border border-slate-100">
                            <div class="flex justify-between items-start mb-1">
                                <span class="font-medium text-slate-800 text-sm">${comment.user_name}</span>
                                <span class="text-xs text-slate-400">${dt}</span>
                            </div>
                            <div class="text-sm text-slate-600 whitespace-pre-wrap">${comment.content}</div>
                        </div>
                    </div>
                `;
            });
        }
        
        modal.classList.remove('hidden');
    } catch(e) {
        console.error(e);
        alert('Failed to load task details');
    }
}

async function updateTaskDetails() {
    const id = document.getElementById('task-modal-id').value;
    const title = document.getElementById('task-modal-title').value;
    const status = document.getElementById('task-modal-status').value;
    const dueDate = document.getElementById('task-modal-due-date').value;
    const desc = document.getElementById('task-modal-desc').innerHTML.trim();
    let parentId = document.getElementById('task-modal-parent-id').value;
    parentId = parentId === '' ? null : parentId;
    
    if(!id) return;
    
    await fetch('api/tasks.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'update_details',
            task_id: id,
            details: { title, status, due_date: dueDate, description: desc, parent_task_id: parentId }
        })
    });
    
    // Refresh board in background
    if(typeof currentProjectId !== 'undefined') loadProjectBoard(currentProjectId);
}

async function updateSubtaskStatus(id, checkbox) {
    const status = checkbox.checked ? 'completed' : 'todo';
    await fetch('api/tasks.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'update_status', task_id: id, status })
    });
    if(typeof currentProjectId !== 'undefined') loadProjectBoard(currentProjectId);
}

async function submitTaskComment() {
    const id = document.getElementById('task-modal-id').value;
    const content = document.getElementById('task-modal-new-comment').value.trim();
    if(!id || !content) return;
    
    const res = await fetch('api/tasks.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'add_comment', task_id: id, content })
    });
    
    if(res.ok) {
        document.getElementById('task-modal-new-comment').value = '';
        openTaskModal(id); // Reload modal to show new comment
    }
}

// Global list of all available users cached
window.allUsersCache = [];
window.currentProjectMemberIds = [];

// ==========================================
// Project Member Assignment UI
// ==========================================
async function toggleProjectMemberDropdown(event) {
    const dropdown = document.getElementById('project-member-dropdown');
    
    if (dropdown.classList.contains('hidden')) {
        dropdown.classList.remove('hidden');
        dropdown.classList.add('flex');
        
        if (window.allUsersCache.length === 0) {
            await searchProjectMembers('');
        } else {
            renderProjectMemberList(window.allUsersCache);
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
        const res = await fetch(`api/users.php?search=${encodeURIComponent(query)}`);
        const users = await res.json();
        
        if (query === '') {
            window.allUsersCache = users;
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
    const index = window.currentProjectMemberIds.indexOf(userId);
    if (index === -1) {
        window.currentProjectMemberIds.push(userId);
    } else {
        window.currentProjectMemberIds.splice(index, 1);
    }
    
    renderProjectMemberList(document.getElementById('project-member-search').value ? window.lastSearchedProjectUsers : window.allUsersCache);
    
    try {
        await fetch('api/projects.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'update_members', project_id: currentProjectId, member_ids: window.currentProjectMemberIds })
        });
        
        // Soft refresh the project members stack without jittering the UI
        const res = await fetch(`api/projects.php?id=${currentProjectId}`);
        const project = await res.json();
        
        if (!project.error) {
            const projectMembersDiv = document.getElementById('project-members');
            if (projectMembersDiv) {
                projectMembersDiv.innerHTML = '';
                if (project.member_names) {
                    const names = project.member_names.split(',');
                    names.slice(0, 5).forEach(n => {
                        const initial = n.trim().charAt(0).toUpperCase();
                        projectMembersDiv.innerHTML += `<div class="w-8 h-8 rounded-full bg-teal-100 border-2 border-white cursor-pointer text-teal-700 text-sm font-bold flex items-center justify-center shadow-sm" title="${n}" onclick="toggleProjectMemberDropdown(event)">${initial}</div>`;
                    });
                    if (names.length > 5) {
                        projectMembersDiv.innerHTML += `<div class="w-8 h-8 rounded-full bg-slate-100 border-2 border-white cursor-pointer text-slate-500 text-xs font-bold flex items-center justify-center shadow-sm" onclick="toggleProjectMemberDropdown(event)">+${names.length - 5}</div>`;
                    }
                } else {
                    projectMembersDiv.innerHTML = `
                        <div class="w-8 h-8 rounded-full bg-slate-100 border-2 border-white cursor-pointer hover:bg-slate-200 transition-colors flex items-center justify-center text-slate-400 group shadow-sm" onclick="toggleProjectMemberDropdown(event)" title="Add Member">
                            <svg class="w-4 h-4 group-hover:text-slate-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        </div>
                    `;
                }
            }
        }
    } catch (e) {
        console.error('Error updating project members:', e);
    }
}

// ==========================================
// Task Assignee UI
// ==========================================

async function toggleAssigneeDropdown(event) {
    const dropdown = document.getElementById('assignee-dropdown');
    
    // Toggle visibility
    if (dropdown.classList.contains('hidden')) {
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
        
        // Setup outside click listener to close
        const outsideClickListener = (e) => {
            if (!dropdown.contains(e.target) && !e.target.closest('[onclick="toggleAssigneeDropdown(event)"]')) {
                dropdown.classList.add('hidden');
                dropdown.classList.remove('flex');
                document.removeEventListener('click', outsideClickListener);
            }
        };
        // Small delay to prevent the current click from immediately triggering it
        setTimeout(() => document.addEventListener('click', outsideClickListener), 10);
    } else {
        dropdown.classList.add('hidden');
        dropdown.classList.remove('flex');
    }
}

async function searchAssignees(query) {
    try {
        const res = await fetch(`api/users.php?search=${encodeURIComponent(query)}`);
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
        const isSelected = window.currentTaskAssigneeIds.includes(user.id);
        const checkIcon = isSelected 
            ? `<svg class="w-4 h-4 text-teal-500 ml-auto" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>` 
            : `<div class="w-4 h-4 ml-auto"></div>`;
            
        const li = document.createElement('li');
        li.className = 'p-1.5 hover:bg-slate-100 rounded flex justify-between items-center cursor-pointer transition-colors mt-0.5';
        li.onclick = (e) => {
            e.stopPropagation(); // prevent closing
            toggleUserAssignment(user.id);
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
    const index = window.currentTaskAssigneeIds.indexOf(userId);
    if (index === -1) {
        window.currentTaskAssigneeIds.push(userId);
    } else {
        window.currentTaskAssigneeIds.splice(index, 1);
    }
    
    // Re-render the dropdown list with new selection states
    renderAssigneeList(document.getElementById('assignee-search').value ? window.lastSearchedUsers : window.allUsersCache);
    
    const taskId = document.getElementById('task-modal-id').value;
    
    try {
        await fetch('api/tasks.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'update_details', task_id: taskId, details: { assignee_ids: window.currentTaskAssigneeIds } })
        });
        
        // Soft refresh without entirely flickering the modal context
        const res = await fetch(`api/tasks.php?id=${taskId}`);
        const task = await res.json();
        if(!task.error) {
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
        }
        
        if(typeof currentProjectId !== 'undefined') loadProjectBoard(currentProjectId);
    } catch (e) {
        console.error('Error updating assignees:', e);
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

function promptAddSubtask(parentId) {
    const title = prompt("Enter subtask title:");
    if(!title) return;
    
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
            openTaskModal(parentId); // Reload
        } else {
            alert('Failed to create subtask');
        }
    });
}

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

    const filtered = allTasks.filter(t => t.title.toLowerCase().includes(query.toLowerCase()) && t.id !== parentId && t.parent_task_id !== parentId);
    
    const ul = document.getElementById('link-subtask-list');
    ul.innerHTML = '';
    if(filtered.length === 0) {
        ul.innerHTML = '<li class="p-2 text-slate-400 italic text-xs">No matching tasks...</li>';
        return;
    }
    
    filtered.forEach(task => {
        const li = document.createElement('li');
        li.className = 'p-1.5 hover:bg-slate-100 rounded cursor-pointer transition-colors mt-0.5 whitespace-nowrap overflow-hidden text-ellipsis';
        li.textContent = `#${task.id} - ${task.title}`;
        li.onclick = () => submitLinkSubtask(parentId, task.id);
        ul.appendChild(li);
    });
}

function submitLinkSubtask(parentId, subtaskId) {
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
            alert('Failed to link subtask.');
        }
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