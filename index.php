<?php
// /index.php

require_once 'config/database.php';
require_once 'includes/auth_check.php';

// Simple routing logic could go here
$request_uri = $_SERVER['REQUEST_URI'];

// Basic entry point
require_once 'includes/header.php';
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
                <!-- Project Item -->
                <div class="group flex items-center p-3 border border-slate-100 rounded-lg hover:border-teal-300 hover:shadow-sm cursor-pointer transition-all">
                    <div class="w-10 h-10 rounded-lg bg-teal-100 flex items-center justify-center text-teal-700 font-bold mr-4">
                        WR
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-medium text-slate-800 truncate group-hover:text-teal-700">Website Redesign</h3>
                        <p class="text-xs text-slate-500 mt-0.5">On Track &middot; 4 tasks left</p>
                    </div>
                    <div class="bg-slate-100 w-20 h-1.5 rounded-full overflow-hidden">
                        <div class="bg-teal-500 h-full" style="width: 65%;"></div>
                    </div>
                </div>

                <!-- Project Item -->
                <div class="group flex items-center p-3 border border-slate-100 rounded-lg hover:border-cyan-300 hover:shadow-sm cursor-pointer transition-all">
                    <div class="w-10 h-10 rounded-lg bg-cyan-100 flex items-center justify-center text-cyan-700 font-bold mr-4">
                        MQ
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-medium text-slate-800 truncate group-hover:text-cyan-700">Marketing Q3</h3>
                        <p class="text-xs text-amber-600 mt-0.5">At Risk &middot; 12 tasks left</p>
                    </div>
                    <div class="bg-slate-100 w-20 h-1.5 rounded-full overflow-hidden">
                        <div class="bg-amber-400 h-full" style="width: 30%;"></div>
                    </div>
                </div>
            </div>
            <div class="px-5 py-3 bg-slate-50 text-center border-t border-slate-100 cursor-pointer hover:bg-slate-100 transition-colors">
                <span class="text-sm text-teal-600 font-medium">View all projects</span>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>