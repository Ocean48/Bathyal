<?php
// /project_settings.php

require_once 'core/database.php';
require_once 'core/auth_check.php';
require_once 'core/db_query.php';

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$projectId) {
    die("<div style='padding:20px; font-family:sans-serif; color:red;'>Invalid Project ID. <a href='/'>Go back</a></div>");
}

$db = new DBQueries($pdo);

if (!isset($currentUser['id']) || !$db->isProjectMember($projectId, $currentUser['id'])) {
    header("Location: /projects");
    exit;
}

// Get current user's role in this project
$userProjectRole = $db->getProjectMemberRole($projectId, $currentUser['id']);

// Handle POST requests for settings updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_member_role') {
            $targetUserId = (int)$_POST['user_id'];
            $newRole = $_POST['role'];
            
            // Enforce role assignment rules
            if ($newRole === 'manager' && $userProjectRole !== 'manager') {
                $newRole = 'member'; // Fallback
            }
            
            // Managers can update anyone, members can update non-managers
            $canUpdate = false;
            if ($userProjectRole === 'manager') {
                $canUpdate = true;
            } elseif ($userProjectRole === 'member') {
                // Check current role of target user
                $targetRole = $db->getProjectMemberRole($projectId, $targetUserId);
                
                if ($targetRole !== 'manager') {
                    $canUpdate = true;
                }
            }

            if ($canUpdate) {
                $db->updateProjectMemberRole($projectId, $targetUserId, $newRole);
            }
        } elseif ($_POST['action'] === 'remove_member') {
            $targetUserId = (int)$_POST['user_id'];
            
            // Managers can remove anyone, members can remove non-managers
            $canRemove = false;
            if ($userProjectRole === 'manager') {
                $canRemove = true;
            } elseif ($userProjectRole === 'member') {
                $targetRole = $db->getProjectMemberRole($projectId, $targetUserId);
                
                if ($targetRole !== 'manager') {
                    $canRemove = true;
                }
            }

            if ($canRemove) {
                $db->removeProjectMember($projectId, $targetUserId);
            }
        } elseif ($_POST['action'] === 'add_member') {
            $newUserId = (int)$_POST['user_id'];
            $newRole = $_POST['role'];
            
            // Managers can add any role. Members can only add member/viewer
            if ($userProjectRole === 'manager' || $userProjectRole === 'member') {
                if ($newRole === 'manager' && $userProjectRole !== 'manager') {
                    $newRole = 'member'; // Fallback
                }
                
                // Add member if not already exists
                $db->addProjectMember($projectId, $newUserId, $newRole);
            }
        } elseif ($_POST['action'] === 'add_team_members') {
            $teamIdToAdd = (int)$_POST['team_id'];
            $newRole = $_POST['role'];
            
            if ($userProjectRole === 'manager' || $userProjectRole === 'member') {
                if ($newRole === 'manager' && $userProjectRole !== 'manager') {
                    $newRole = 'member'; // Fallback
                }
                
                $db->addProjectTeam($projectId, $teamIdToAdd);

                $teamMembers = $db->getTeamMembersWithRoles($teamIdToAdd);
                foreach ($teamMembers as $tm) {
                    $db->addProjectMember($projectId, $tm['id'], $newRole);
                }
            }
        }
        
        // Redirect back to same tab to avoid resubmission on refresh
        $tab = isset($_GET['tab']) ? $_GET['tab'] : 'members';
        header("Location: /project_settings?id={$projectId}&tab={$tab}");
        exit;
    }
}

// Fetch Project basics
$project = $db->getProjectById($projectId);

if (!$project || !$db->getProjectMemberRole($projectId, $currentUser['id'])) {
    die("<div style='padding:20px; font-family:sans-serif; color:red;'>Project not found or access denied. <a href='/'>Go back</a></div>");
}

// Fetch project members for settings UI
$members = $db->getProjectMembersWithRoles($projectId);
$memberIds = array_column($members, 'id');

// Fetch potential members (users not yet in this project)
// Show any user the current user has access to see (from their teams)
$availableUsers = $db->getAvailableUsersForProject($currentUser['team_id'] ?? null, $memberIds);

// Fetch teams the user has access to, for the "Add Team" dropdown
$availableTeams = $db->getTeamsForUser($currentUser['id'], $currentUser['role'] ?? null);

require_once 'views/layouts/header.php';
?>

<div class="max-w-7xl mx-auto px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800">Project Settings</h1>
            <p class="text-sm text-slate-500 mt-1">Manage configuration for <strong><?= htmlspecialchars($project['name']) ?></strong>.</p>
        </div>
        <a href="/project?id=<?= $projectId ?>" class="text-sm font-medium text-teal-600 hover:text-teal-700 bg-teal-50 px-4 py-2 rounded-lg transition-colors">
            Back to Project
        </a>
    </div>

    <div class="flex flex-col md:flex-row gap-6">
        <!-- Settings Nav -->
        <div class="w-full md:w-1/4 lg:w-1/5 shrink-0">
            <nav class="space-y-1 md:sticky md:top-8">
                <a href="#" onclick="switchTab('general', event)" id="nav-general" class="flex items-center px-4 py-2.5 bg-white text-teal-700 text-sm font-medium rounded-lg shadow-sm border border-slate-200/60">
                    <svg class="w-5 h-5 mr-3 text-teal-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                    General
                </a>
                <a href="#" onclick="switchTab('members', event)" id="nav-members" class="flex items-center px-4 py-2.5 text-slate-600 hover:bg-slate-50 hover:text-slate-900 text-sm font-medium rounded-lg transition-colors">
                    <svg class="w-5 h-5 mr-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    Members & Permissions
                </a>
            </nav>
        </div>

        <!-- Settings Form -->
        <div class="flex-1 space-y-6">
            
            <div id="panel-general" class="space-y-6">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-6 py-5 border-b border-slate-200">
                        <h2 class="text-lg font-medium text-slate-800">Project Details</h2>
                    </div>
                    <div class="p-6 space-y-4 text-sm">
                        <div>
                            <label class="block text-slate-700 font-medium mb-1">Project Name</label>
                            <input type="text" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition-all" value="<?= htmlspecialchars($project['name']) ?>">
                        </div>
                        <div>
                            <label class="block text-slate-700 font-medium mb-1">Description</label>
                            <textarea class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition-all h-24"><?= htmlspecialchars($project['description'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <button class="bg-teal-500 hover:bg-teal-600 text-white font-medium px-4 py-2 rounded-lg shadow-sm transition-colors">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div class="bg-white rounded-xl shadow-sm border border-rose-200 overflow-hidden">
                    <div class="px-6 py-5 border-b border-rose-100 bg-rose-50/30">
                        <h2 class="text-lg font-medium text-rose-700">Danger Zone</h2>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-sm font-medium text-slate-800">Archive Project</h4>
                                <p class="text-xs text-slate-500 mt-1">Mark this project as read-only. It will be hidden from the active list.</p>
                            </div>
                            <button class="bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                Archive
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel Members -->
            <div id="panel-members" class="hidden space-y-6">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-6 py-5 border-b border-slate-200 flex flex-col xl:flex-row xl:justify-between xl:items-start space-y-4 xl:space-y-0">
                        <div>
                            <h2 class="text-lg font-medium text-slate-800">Members & Permissions</h2>
                            <p class="text-xs text-slate-500 mt-1">Adjust who has access to this project, and tweak their role.</p>
                        </div>
                        <?php if ($userProjectRole === 'manager' || $userProjectRole === 'member'): ?>
                        <div class="flex flex-col space-y-3 shrink-0 xl:w-2/3 xl:items-end">
                            <!-- Add Team Form -->
                            <form method="POST" action="/project_settings?id=<?= $projectId ?>&tab=members" class="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0 sm:space-x-2 m-0 bg-slate-50/50 p-2 rounded-xl border border-slate-200/80 shadow-sm relative overflow-visible w-full md:w-auto">
                                <input type="hidden" name="action" value="add_team_members">
                                
                                <div class="relative group flex-1">
                                    <select name="team_id" required class="bg-white border border-slate-300 text-slate-700 text-sm rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 block px-3 py-2 outline-none w-full sm:w-56 shadow-sm hover:border-slate-400 transition-colors">
                                        <option value="" disabled selected>Select Team...</option>
                                        <?php foreach($availableTeams as $t): ?>
                                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="relative group">
                                    <select name="role" required class="bg-white border border-slate-300 text-slate-700 text-sm rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 block px-3 py-2 outline-none w-full sm:w-32 shadow-sm hover:border-slate-400 transition-colors">
                                        <?php if ($userProjectRole === 'manager'): ?>
                                            <option value="manager">Manager</option>
                                        <?php endif; ?>
                                        <option value="member" selected>Member</option>
                                        <option value="viewer">Viewer</option>
                                    </select>
                                </div>
                                <button type="submit" class="bg-teal-500 hover:bg-teal-600 text-white p-2 rounded-lg transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-1 active:scale-95 flex items-center justify-center font-medium px-4 w-[130px]" title="Add Team">
                                    <svg class="w-4 h-4 mr-1.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                    Add Team
                                </button>
                            </form>

                            <!-- Add Individual Member Form -->
                            <form method="POST" action="/project_settings?id=<?= $projectId ?>&tab=members" id="add-member-form" class="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0 sm:space-x-2 m-0 bg-slate-50/50 p-2 rounded-xl border border-slate-200/80 shadow-sm relative overflow-visible w-full md:w-auto">
                                <input type="hidden" name="action" value="add_member">
                                
                                <!-- Custom Searchable Dropdown -->
                                <div class="relative group flex-1" id="userDropdownContainer">
                                    <input type="hidden" name="user_id" id="selectedUserValue" required>
                                    <input type="text" id="userSearchInput" autocomplete="off" placeholder="Search user to add..." class="bg-white border border-slate-300 text-slate-700 text-sm rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 block pl-3 pr-8 py-2 outline-none w-full sm:w-56 shadow-sm hover:border-slate-400 transition-colors placeholder:text-slate-400">
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-400 cursor-pointer">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </div>
                                    
                                    <!-- Dropdown List -->
                                    <div id="userDropdownList" class="hidden absolute top-full left-0 mt-1 max-h-60 w-full overflow-y-auto bg-white border border-slate-200 rounded-lg shadow-lg z-50">
                                        <ul class="text-sm text-slate-700" id="userDropdownUl">
                                            <?php foreach($availableUsers as $index => $u): ?>
                                                <li class="px-4 py-2 hover:bg-teal-50 cursor-pointer transition-colors user-item" 
                                                    data-id="<?= $u['id'] ?>" 
                                                    data-name="<?= htmlspecialchars($u['name']) ?>" 
                                                    data-email="<?= htmlspecialchars($u['email']) ?>">
                                                    <div class="font-medium text-slate-800"><?= htmlspecialchars($u['name']) ?></div>
                                                    <div class="text-[11px] text-slate-500"><?= htmlspecialchars($u['email']) ?></div>
                                                </li>
                                            <?php endforeach; ?>
                                            <?php if(empty($availableUsers)): ?>
                                                <li class="px-4 py-3 text-slate-500 italic">No available users.</li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>

                                <div class="relative" id="roleDropdownContainer">
                                    <input type="hidden" name="role" id="selectedRoleValue" value="member">
                                    <button type="button" id="roleDropdownBtn" class="bg-white border border-slate-300 text-slate-700 text-sm rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 flex justify-between items-center pl-3 pr-2 py-2 outline-none w-full sm:w-32 shadow-sm hover:border-slate-400 transition-colors cursor-pointer text-left">
                                        <span id="roleDropdownLabel">Member</span>
                                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </button>
                                    
                                    <!-- Dropdown List -->
                                    <div id="roleDropdownList" class="hidden absolute top-full left-0 mt-1 max-h-60 w-full overflow-y-auto bg-white border border-slate-200 rounded-lg shadow-lg z-50">
                                        <ul class="text-sm text-slate-700 py-1">
                                            <?php if ($userProjectRole === 'manager'): ?>
                                                <li class="px-4 py-2 hover:bg-teal-50 cursor-pointer transition-colors role-item" data-value="manager">Manager</li>
                                            <?php endif; ?>
                                            <li class="px-4 py-2 hover:bg-teal-50 cursor-pointer transition-colors role-item" data-value="member">Member</li>
                                            <li class="px-4 py-2 hover:bg-teal-50 cursor-pointer transition-colors role-item" data-value="viewer">Viewer</li>
                                        </ul>
                                    </div>
                                </div>
                                <button type="submit" class="bg-teal-500 hover:bg-teal-600 text-white p-2 rounded-lg transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-1 active:scale-95 flex items-center justify-center font-medium px-4 w-full sm:w-[130px]" title="Add User">
                                    <svg class="w-4 h-4 mr-1.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                    Add User
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="p-0 overflow-x-auto">
                        <table class="w-full text-left border-collapse text-sm">
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach($members as $member): ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-6 py-3">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-600 font-bold flex items-center justify-center mr-3 text-xs shrink-0">
                                                <?= strtoupper(substr($member['name'], 0, 1)) ?>
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="font-medium text-slate-800 leading-tight"><?= htmlspecialchars($member['name']) ?></span>
                                                <span class="text-[11px] text-slate-500 leading-tight"><?= htmlspecialchars($member['email']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        <div class="flex items-center justify-end space-x-2">
                                            <?php if ($userProjectRole === 'manager' || $userProjectRole === 'member'): ?>
                                                <form method="POST" action="/project_settings?id=<?= $projectId ?>&tab=members" class="m-0" id="role-form-<?= $member['id'] ?>">
                                                    <input type="hidden" name="action" value="update_member_role">
                                                    <input type="hidden" name="user_id" value="<?= $member['id'] ?>">
                                                    <input type="hidden" name="role" class="real-role-input" value="<?= $member['role'] ?>">
                                                    
                                                    <div class="relative existing-role-dropdown">
                                                        <button type="button" class="existing-role-btn bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 flex justify-between items-center px-2.5 py-1.5 outline-none w-[100px] cursor-pointer hover:bg-slate-100 transition-colors">
                                                            <span class="role-label-text"><?= ucfirst($member['role']) ?></span>
                                                            <svg class="w-3.5 h-3.5 text-slate-400 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                                        </button>
                                                        
                                                        <div class="existing-role-list hidden absolute top-full right-0 mt-1 w-[120px] bg-white border border-slate-200 rounded-lg shadow-lg z-40 overflow-hidden">
                                                            <ul class="text-xs text-slate-700 py-1">
                                                                <?php if ($userProjectRole === 'manager' || $member['role'] === 'manager'): ?>
                                                                    <?php $isManagerDisabled = ($userProjectRole !== 'manager' && $member['role'] !== 'manager'); ?>
                                                                    <li class="px-3 py-2 <?= $isManagerDisabled ? 'opacity-50 cursor-not-allowed' : 'hover:bg-teal-50 cursor-pointer' ?> transition-colors role-option" data-value="manager" <?= $isManagerDisabled ? 'data-disabled="true"' : '' ?>>
                                                                        Manager <?= $member['role'] === 'manager' ? '✓' : '' ?>
                                                                    </li>
                                                                <?php endif; ?>
                                                                <li class="px-3 py-2 hover:bg-teal-50 cursor-pointer transition-colors role-option" data-value="member">
                                                                    Member <?= $member['role'] === 'member' ? '✓' : '' ?>
                                                                </li>
                                                                <li class="px-3 py-2 hover:bg-teal-50 cursor-pointer transition-colors role-option" data-value="viewer">
                                                                    Viewer <?= $member['role'] === 'viewer' ? '✓' : '' ?>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </form>
                                                
                                                <?php if ($userProjectRole === 'manager' || ($userProjectRole === 'member' && $member['role'] !== 'manager')): ?>
                                                <form method="POST" action="/project_settings?id=<?= $projectId ?>&tab=members" class="m-0" onsubmit="event.preventDefault(); showConfirm('Remove Member', 'Are you sure you want to remove <strong><?= htmlspecialchars($member['name']) ?></strong> from the project?', 'danger').then(res => { if(res) this.submit(); });">
                                                    <input type="hidden" name="action" value="remove_member">
                                                    <input type="hidden" name="user_id" value="<?= $member['id'] ?>">
                                                    <button type="submit" class="text-rose-500 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 p-[7px] rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-rose-500 shadow-sm" title="Remove Member">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $member['role'] === 'manager' ? 'bg-purple-100 text-purple-800' : 'bg-slate-100 text-slate-600' ?>">
                                                    <?= ucfirst($member['role']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($members)): ?>
                                    <tr>
                                        <td colspan="2" class="px-6 py-8 text-center text-slate-500 text-sm">No members are currently assigned to this project.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const initialTab = new URLSearchParams(window.location.search).get('tab') || 'general';
        switchTab(initialTab, null);
    });

    function switchTab(tabId, event) {
        if (event) event.preventDefault();

        // Arrays of our tabs
        const tabs = ['general', 'members'];

        // Apply URL parameter state without reloading the page if HTML5 history API is available
        if (event && window.history.replaceState) {
            const url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.replaceState(null, '', url.toString());
        }

        tabs.forEach(t => {
            const panel = document.getElementById('panel-' + t);
            const nav = document.getElementById('nav-' + t);
            const icon = nav.querySelector('svg');

            if (t === tabId) {
                // Set active states
                panel.classList.remove('hidden');
                nav.className = 'flex items-center px-4 py-2.5 bg-white text-teal-700 text-sm font-medium rounded-lg shadow-sm border border-slate-200/60';
                icon.classList.remove('text-slate-400');
                icon.classList.add('text-teal-500');
            } else {
                // Set inactive states
                panel.classList.add('hidden');
                nav.className = 'flex items-center px-4 py-2.5 text-slate-600 hover:bg-slate-50 hover:text-slate-900 text-sm font-medium rounded-lg transition-colors';
                icon.classList.remove('text-teal-500');
                icon.classList.add('text-slate-400');
            }
        });
    }

    // Custom Searchable Dropdown Logic
    const searchInput = document.getElementById('userSearchInput');
    const dropdownList = document.getElementById('userDropdownList');
    const userDropdownUl = document.getElementById('userDropdownUl');
    const hiddenUserId = document.getElementById('selectedUserValue');

    if (searchInput) {
        // Click outside to close
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !dropdownList.contains(e.target)) {
                dropdownList.classList.add('hidden');
                // Revert to selected if clicked out without match and value exists
                if (!hiddenUserId.value && searchInput.value) {
                     searchInput.value = '';
                }
            }
        });

        let searchTimeout;

        // Filter Logic via API
        searchInput.addEventListener('input', (e) => {
            dropdownList.classList.remove('hidden');
            const term = e.target.value.trim();
            
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(async () => {
                userDropdownUl.innerHTML = '<li class="px-4 py-3 text-slate-500 italic">Searching...</li>';
                try {
                    // Fetch all users that match query, minus existing project members
                    const res = await fetch(`/api/users.php?search=${encodeURIComponent(term)}&exclude_project_id=<?= $projectId ?>`);
                    const json = await res.json();
                    
                    userDropdownUl.innerHTML = '';
                    
                    if (json.length > 0) {
                        json.forEach(u => {
                            const li = document.createElement('li');
                            li.className = 'px-4 py-2 hover:bg-teal-50 cursor-pointer transition-colors user-item';
                            li.dataset.id = u.id;
                            li.dataset.name = u.name;
                            li.dataset.email = u.email;
                            li.innerHTML = `
                                <div class="font-medium text-slate-800">${u.name}</div>
                                <div class="text-[11px] text-slate-500">${u.email}</div>
                            `;
                            
                            // Rebind selection event
                            li.addEventListener('click', () => {
                                hiddenUserId.value = u.id;
                                searchInput.value = u.name + ' (' + u.email + ')';
                                dropdownList.classList.add('hidden');
                            });
                            
                            userDropdownUl.appendChild(li);
                        });
                    } else {
                        userDropdownUl.innerHTML = '<li class="px-4 py-3 text-slate-500 italic">No available users.</li>';
                    }
                } catch (error) {
                    userDropdownUl.innerHTML = '<li class="px-4 py-3 text-rose-500 italic">Search error.</li>';
                }
            }, 300);
        });

        // Initialize options on focus if empty
        searchInput.addEventListener('focus', () => {
            dropdownList.classList.remove('hidden');
            if (userDropdownUl.children.length === 0 || searchInput.value === '') {
                searchInput.dispatchEvent(new Event('input'));
            }
        });
    }

    // Role Dropdown Logic for Add Member
    const roleBtn = document.getElementById('roleDropdownBtn');
    const roleList = document.getElementById('roleDropdownList');
    const roleLabel = document.getElementById('roleDropdownLabel');
    const hiddenRoleVal = document.getElementById('selectedRoleValue');
    const roleItems = document.querySelectorAll('.role-item');

    if (roleBtn) {
        roleBtn.addEventListener('click', () => {
            roleList.classList.toggle('hidden');
        });

        document.addEventListener('click', (e) => {
            if (!roleBtn.contains(e.target) && !roleList.contains(e.target)) {
                roleList.classList.add('hidden');
            }
        });

        roleItems.forEach(item => {
            item.addEventListener('click', () => {
                hiddenRoleVal.value = item.dataset.value;
                roleLabel.textContent = item.textContent;
                roleList.classList.add('hidden');
            });
        });
    }

    // Role Dropdowns Logic for Existing Members Table
    const existingTableDropdowns = document.querySelectorAll('.existing-role-dropdown');
    existingTableDropdowns.forEach(dropdown => {
        const btn = dropdown.querySelector('.existing-role-btn');
        const list = dropdown.querySelector('.existing-role-list');
        const options = list.querySelectorAll('.role-option');
        const hiddenInput = dropdown.parentElement.querySelector('.real-role-input');
        const submitForm = dropdown.closest('form');

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            // Close all others first
            document.querySelectorAll('.existing-role-list').forEach(l => {
                if (l !== list) l.classList.add('hidden');
            });
            list.classList.toggle('hidden');
        });

        options.forEach(opt => {
            opt.addEventListener('click', (e) => {
                e.stopPropagation();
                if (opt.dataset.disabled === 'true') return;

                const val = opt.dataset.value;
                if (val !== hiddenInput.value) {
                    hiddenInput.value = val;
                    submitForm.submit(); // Automatically submit just like the original select element
                } else {
                    list.classList.add('hidden');
                }
            });
        });
    });

    // Close all table dropdowns if clicked outside
    document.addEventListener('click', (e) => {
        document.querySelectorAll('.existing-role-list').forEach(list => {
            if (!list.parentElement.contains(e.target)) {
                list.classList.add('hidden');
            }
        });
    });
</script>

<?php require_once 'views/layouts/footer.php'; ?>
