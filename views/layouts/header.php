<!DOCTYPE html>
<html lang="en">
<head>
    <?php
    $appConfig = file_exists(__DIR__ . '/../../config.php') ? require __DIR__ . '/../../config.php' : ['app_name' => 'Bathyal'];
    $appName = $appConfig['app_name'] ?? 'Bathyal';
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($appName) ?> Project Management</title>
    <link rel="stylesheet" href="/bathyal/assets/css/style.css">
    <!-- Optional: Add Tailwind CSS via CDN for rapid UI development -->
    <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans h-screen flex overflow-hidden">

    <!-- Sidebar -->
    <aside class="w-64 bg-slate-900 text-slate-300 flex flex-col shadow-lg z-20">
        <div class="h-16 flex items-center px-6 border-b border-white/10">
            <!-- Logo Icon (Ocean Theme) -->
            <svg class="w-6 h-6 text-teal-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            <span class="font-bold text-xl text-white tracking-wide"><?= htmlspecialchars($appName) ?></span>
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
                        <svg class="w-5 h-5 mr-3 text-slate-400 group-hover:text-teal-400 transition-colors" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                        Inbox
                        <?php 
                        $headerDbQueries = $headerDbQueries ?? new DBQueries($pdo);
                        $unreadCount = $headerDbQueries->getUnreadNotificationCount($currentUser['id'] ?? 1); 
                        if ($unreadCount > 0): 
                        ?>
                        <span class="ml-auto bg-rose-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="/bathyal/reports" class="flex items-center px-6 py-2 hover:bg-slate-800 hover:text-white group">
                        <svg class="w-5 h-5 mr-3 text-slate-400 group-hover:text-teal-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        Reporting
                    </a>
                </li>
                <li>
                    <a href="/bathyal/teams" class="flex items-center px-6 py-2 hover:bg-slate-800 hover:text-white group">
                        <svg class="w-5 h-5 mr-3 text-slate-400 group-hover:text-teal-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        Teams
                    </a>
                </li>
                <li>
                    <a href="/bathyal/projects" class="flex items-center px-6 py-2 hover:bg-slate-800 hover:text-white group">
                        <svg class="w-5 h-5 mr-3 text-slate-400 group-hover:text-teal-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
                        Projects
                    </a>
                </li>
            </ul>

            <div class="px-6 mt-8 mb-2">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Recent Projects</p>
            </div>
            <ul class="space-y-1 max-h-64 overflow-y-auto overflow-x-hidden" style="scrollbar-width: thin; scrollbar-color: #475569 transparent;">
                <?php
                // Fetch up to 10 most recently accessed projects for this user
                try {
                    require_once 'core/db_query.php';
                    $headerDbQueries = new DBQueries($pdo);
                    $userId = isset($currentUser['id']) ? (int)$currentUser['id'] : 1;
                    
                    $recentProjects = $headerDbQueries->getRecentProjects($userId, 10);
                    
                    $colors = ['bg-teal-400', 'bg-cyan-500', 'bg-blue-500', 'bg-emerald-500', 'bg-indigo-500'];
                    $colorIndex = 0;
                    
                    foreach ($recentProjects as $rp) {
                        $circleColor = $colors[$colorIndex % count($colors)];
                        $colorIndex++;
                ?>
                <li>
                    <div class="flex items-center justify-between px-6 py-2 hover:bg-slate-800 group text-sm transition-colors">
                        <a href="/bathyal/project?id=<?= $rp['id'] ?>" class="flex items-center flex-1 hover:text-white">
                            <span class="w-2 h-2 rounded-full <?= $circleColor ?> mr-3"></span>
                            <?= htmlspecialchars($rp['name']) ?>
                        </a>
                        <a href="/bathyal/project_settings?id=<?= $rp['id'] ?>" class="text-slate-500 hover:text-slate-300 opacity-0 group-hover:opacity-100 transition-opacity" title="Project Settings">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                            </svg>
                        </a>
                    </div>
                </li>
                <?php 
                    }
                } catch (\PDOException $e) {
                    echo "<li class='px-6 py-2 text-xs text-rose-400'>Error loading projects</li>";
                }
                ?>
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
            <div class="absolute bottom-full left-0 w-full p-2 pb-4 hidden group-hover:block transition-all z-50">
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
        <header class="bg-white border-b border-slate-200 h-14 flex items-center justify-between px-6 z-40 shrink-0 relative">
            <!-- Search -->
            <div class="flex-1 max-w-xl">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </span>
                    <input type="text" class="w-full bg-slate-100 border-transparent rounded-full py-1.5 pl-10 pr-4 text-sm focus:bg-white focus:border-teal-400 focus:ring-2 focus:ring-teal-100 outline-none transition-all placeholder-slate-400" placeholder="Search tasks, projects...">
                </div>
            </div>

            <!-- Global Actions & Notifications -->
            <div class="ml-4 flex items-center space-x-3 relative z-50">
                <!-- Notifications dropdown trigger handled via script below -->
                <div class="relative">
                    <button id="header-bell-btn" class="relative p-2 text-slate-400 hover:text-slate-600 focus:outline-none transition-colors group">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        <?php if ($unreadCount > 0): ?>
                        <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-rose-500 rounded-full ring-2 ring-white"></span>
                        <?php endif; ?>
                    </button>

                    <!-- Notifications Dropdown -->
                    <div id="global-notifications-dropdown" class="hidden absolute top-full right-0 mt-2 w-80 bg-white border border-slate-200 shadow-xl rounded-xl flex flex-col transform origin-top-right z-[100] max-h-[85vh]">
                        <div class="px-4 py-3 border-b border-slate-100 flex justify-between items-center bg-slate-50/50 relative">
                            <h3 class="text-sm font-semibold text-slate-800">Notifications</h3>
                        </div>
                        <div class="overflow-y-auto w-full p-2 space-y-1 bg-white">
                            <?php 
                            $recentNotifications = $headerDbQueries->getUserNotifications($currentUser['id'] ?? 1, 5); 
                            if (empty($recentNotifications)): ?>
                                <div class="px-3 py-4 text-center text-slate-500 text-sm">No new notifications</div>
                            <?php else: foreach ($recentNotifications as $rn): ?>
                            <a href="<?= $rn['task_id'] ? '/bathyal/tasks?id='.$rn['task_id'] : '/bathyal/inbox' ?>" class="block px-3 py-2.5 rounded-lg hover:bg-slate-50 cursor-pointer transition-colors relative border border-transparent hover:border-slate-200">
                                <?php if (!$rn['is_read']): ?>
                                <span class="absolute left-1.5 top-1/2 -translate-y-1/2 w-1.5 h-1.5 bg-teal-500 rounded-full"></span>
                                <?php endif; ?>
                                <div class="pl-3">
                                    <p class="text-sm <?= !$rn['is_read'] ? 'text-slate-800 font-medium' : 'text-slate-600' ?> leading-snug break-words">
                                        <?= htmlspecialchars($rn['message']) ?>
                                    </p>
                                    <p class="text-[10px] text-slate-400 mt-1"><?= date('M j, g:i A', strtotime($rn['created_at'])) ?></p>
                                </div>
                            </a>
                            <?php endforeach; endif; ?>
                        </div>
                        <div class="px-4 py-2 border-t border-slate-100 text-center bg-slate-50/50 rounded-b-xl">
                            <a href="/bathyal/inbox" class="text-xs font-semibold text-teal-600 hover:text-teal-800 transition-colors">Open Inbox</a>
                        </div>
                    </div>
                </div>

                <script>
                    document.getElementById('header-bell-btn').addEventListener('click', function(e) {
                        e.stopPropagation();
                        document.getElementById('global-notifications-dropdown').classList.toggle('hidden');
                    });
                    document.addEventListener('click', function(e) {
                        const dropdown = document.getElementById('global-notifications-dropdown');
                        const btn = document.getElementById('header-bell-btn');
                        if (!btn.contains(e.target) && !dropdown.contains(e.target)) {
                            dropdown.classList.add('hidden');
                        }
                    });
                </script>

                <!-- Avatar -->
                <div class="relative group">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-r from-teal-400 to-cyan-500 flex items-center justify-center text-white font-medium text-sm shadow-sm ring-2 ring-white cursor-pointer hover:shadow-md transition-all" title="Profile">
                        <?php 
                            $name = $currentUser['name'] ?? 'User';
                            $initials = strtoupper(substr($name, 0, 1));
                            echo $initials;
                        ?>
                    </div>
                    <!-- Quick Profile Dropdown -->
                    <div class="absolute right-0 top-full pt-2 w-48 hidden group-hover:block z-50">
                        <div class="bg-white border border-slate-200 shadow-xl rounded-xl overflow-hidden flex flex-col transform origin-top-right">
                            <a href="/bathyal/settings" class="px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 transition-colors">Settings</a>
                            <a href="/bathyal/logout" class="px-4 py-2 text-sm text-rose-600 hover:bg-slate-50 transition-colors">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main scrolling area -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto">
