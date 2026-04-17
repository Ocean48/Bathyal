<?php
// /project.php

require_once 'config/database.php';

// Include the UI wrapper
require_once 'includes/header.php';
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
                <img class="w-8 h-8 rounded-full border-2 border-white" src="https://ui-avatars.com/api/?name=Admin+User&background=0D8ABC&color=fff" alt="Member">
                <img class="w-8 h-8 rounded-full border-2 border-white" src="https://ui-avatars.com/api/?name=John+Doe&background=0284C7&color=fff" alt="Member">
            </div>
            <button class="text-slate-500 hover:text-slate-700 px-3 py-1.5 rounded-md text-sm font-medium transition-colors border border-slate-200 bg-white">Share</button>
            <button class="bg-teal-500 hover:bg-teal-600 text-white px-4 py-1.5 rounded-md text-sm font-medium shadow-sm transition-colors flex items-center">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Add Task
            </button>
        </div>
    </header>

    <!-- Sub Navigation for Project Views -->
    <div class="px-6 border-b border-slate-200 bg-white shrink-0 flex space-x-6">
        <button class="border-b-2 border-teal-500 text-teal-600 py-2.5 px-1 text-sm font-medium">Board</button>
        <button class="border-b-2 border-transparent text-slate-500 hover:text-slate-700 py-2.5 px-1 text-sm font-medium">List</button>
        <button class="border-b-2 border-transparent text-slate-500 hover:text-slate-700 py-2.5 px-1 text-sm font-medium">Timeline</button>
        <button class="border-b-2 border-transparent text-slate-500 hover:text-slate-700 py-2.5 px-1 text-sm font-medium">Dashboard</button>
    </div>

    <!-- Kanban Board Scrollable Area -->
    <div class="flex-1 overflow-x-auto overflow-y-hidden p-6">
        <div id="kanban-board" class="flex space-x-4 h-full items-start pb-4">
            <!-- Columns injected by JavaScript -->
            <div class="flex items-center justify-center w-full h-full text-slate-400">
                <svg class="animate-spin h-8 w-8 text-teal-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>
    </div>
</div>

<?php 
require_once 'includes/footer.php'; 
?>