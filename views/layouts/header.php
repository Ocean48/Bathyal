<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bathyal Project Management</title>
    <link rel="stylesheet" href="/bathyal/assets/css/style.css">
    <!-- Optional: Add Tailwind CSS via CDN for rapid UI development -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans h-screen flex overflow-hidden">

    <!-- Sidebar -->
    <aside class="w-64 bg-slate-900 text-slate-300 flex flex-col shadow-lg z-20">
        <div class="h-16 flex items-center px-6 border-b border-white/10">
            <!-- Logo Icon (Ocean Theme) -->
            <svg class="w-6 h-6 text-teal-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            <span class="font-bold text-xl text-white tracking-wide">Bathyal</span>
        </div>

        <nav class="flex-1 overflow-y-auto py-4">
            <ul class="space-y-1">
                <li>
                    <a href="/bathyal/" class="flex items-center px-6 py-2 hover:bg-slate-800 hover:text-white group bg-slate-800 border-l-4 border-teal-400 text-white">
                        <svg class="w-5 h-5 mr-3 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                </li>
                <li>
                    <a href="/bathyal/tasks" class="flex items-center px-6 py-2 hover:bg-slate-800 hover:text-white group">
                        <svg class="w-5 h-5 mr-3 text-slate-400 group-hover:text-teal-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        My Tasks
                    </a>
                </li>
                <li>
                    <a href="/bathyal/inbox" class="flex items-center px-6 py-2 hover:bg-slate-800 hover:text-white group">
                        <svg class="w-5 h-5 mr-3 text-slate-400 group-hover:text-teal-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        Inbox Menu
                    </a>
                </li>
                <li>
                    <a href="/bathyal/reports" class="flex items-center px-6 py-2 hover:bg-slate-800 hover:text-white group">
                        <svg class="w-5 h-5 mr-3 text-slate-400 group-hover:text-teal-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        Reporting
                    </a>
                </li>
            </ul>

            <div class="px-6 mt-8 mb-2">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Projects</p>
            </div>
            <ul class="space-y-1">
                <li>
                    <div class="flex items-center justify-between px-6 py-2 hover:bg-slate-800 group text-sm transition-colors">
                        <a href="/bathyal/project?id=1" class="flex items-center flex-1 hover:text-white">
                            <span class="w-2 h-2 rounded-full bg-teal-400 mr-3"></span>
                            Website Redesign
                        </a>
                        <a href="/bathyal/project_settings?id=1" class="text-slate-500 hover:text-slate-300 opacity-0 group-hover:opacity-100 transition-opacity" title="Project Settings">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                            </svg>
                        </a>
                    </div>
                </li>
                <li>
                    <div class="flex items-center justify-between px-6 py-2 hover:bg-slate-800 group text-sm transition-colors">
                        <a href="/bathyal/project?id=2" class="flex items-center flex-1 hover:text-white">
                            <span class="w-2 h-2 rounded-full bg-cyan-500 mr-3"></span>
                            Marketing Q3
                        </a>
                        <a href="/bathyal/project_settings?id=2" class="text-slate-500 hover:text-slate-300 opacity-0 group-hover:opacity-100 transition-opacity" title="Project Settings">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                            </svg>
                        </a>
                    </div>
                </li>
                <li>
                    <div class="flex items-center justify-between px-6 py-2 hover:bg-slate-800 group text-sm transition-colors">
                        <a href="/bathyal/project?id=3" class="flex items-center flex-1 hover:text-white">
                            <span class="w-2 h-2 rounded-full bg-blue-500 mr-3"></span>
                            Ocean Conservation App
                        </a>
                        <a href="/bathyal/project_settings?id=3" class="text-slate-500 hover:text-slate-300 opacity-0 group-hover:opacity-100 transition-opacity" title="Project Settings">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                            </svg>
                        </a>
                    </div>
                </li>
            </ul>
        </nav>
        
        <!-- User Settings -->
        <div class="p-4 border-t border-white/10 group cursor-pointer relative">
            <div class="flex items-center w-full hover:text-white transition-colors">
                <?php 
                    // Dynamic username for Avatar
                    $displayName = htmlspecialchars($currentUser['name'] ?? 'User');
                    $displayEmail = htmlspecialchars($currentUser['email'] ?? '');
                    $encodedName = urlencode($displayName);
                ?>
                <img src="https://ui-avatars.com/api/?name=<?= $encodedName ?>&background=0D8ABC&color=fff" alt="User" class="w-8 h-8 rounded-full border-2 border-slate-700 mr-3">
                <div class="flex-1 text-left">
                    <p class="text-sm font-medium truncate w-32"><?= $displayName ?></p>
                    <p class="text-xs text-slate-500 truncate w-32"><?= $displayEmail ?></p>
                </div>
            </div>
            <!-- Logout dropdown overlay -->
            <div class="absolute bottom-full left-0 w-full p-2 mb-2 hidden group-hover:block transition-all z-50">
                <div class="bg-slate-800 rounded-lg shadow-lg border border-slate-700 py-1">
                    <a href="/bathyal/settings" class="block px-4 py-2 text-sm text-slate-300 hover:text-white hover:bg-slate-700 flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        Settings
                    </a>
                    <a href="/bathyal/logout" class="block px-4 py-2 text-sm text-rose-400 hover:text-rose-300 hover:bg-slate-700 flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content wrapper -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-50">
        
        <!-- Top header / Search & actions -->
        <header class="bg-white border-b border-slate-200 h-14 flex items-center justify-between px-6 z-10 shrink-0">
            <!-- Search -->
            <div class="flex-1 max-w-xl">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </span>
                    <input type="text" class="w-full bg-slate-100 border-transparent rounded-full py-1.5 pl-10 pr-4 text-sm focus:bg-white focus:border-teal-400 focus:ring-2 focus:ring-teal-100 outline-none transition-all placeholder-slate-400" placeholder="Search tasks, projects...">
                </div>
            </div>

            <!-- Actions -->
            <div class="ml-4 flex items-center space-x-3">
                <button class="text-sm bg-teal-500 hover:bg-teal-600 text-white font-medium py-1.5 px-3 rounded-full transition-colors flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    New
                </button>
                <button class="p-1.5 text-slate-400 hover:text-teal-600 rounded-full hover:bg-slate-100 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                </button>
            </div>
        </header>

        <!-- Main scrolling area -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto">
