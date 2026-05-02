<?php
require_once 'core/database.php';
require_once 'core/auth_check.php';

$teams = $db->getTeamsForUser($currentUser['id'], $currentUser['role'] ?? null);
require 'views/layouts/header.php';
?>

<div class="flex flex-col h-full bg-slate-50 w-full overflow-hidden" id="teams-app">
    <!-- Header Area -->
    <header class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between shrink-0">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Team Management</h1>
            <p class="text-sm text-slate-500 mt-1">Manage your teams and collaborate with others</p>
        </div>
        <button id="btn-new-team" class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded-lg shadow-sm font-medium transition-colors flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Create New Team
        </button>
    </header>

    <!-- Main Content Grid -->
    <div class="flex-1 flex overflow-hidden">
        
        <!-- Teams List Sidebar -->
        <div class="w-1/3 border-r border-slate-200 bg-white overflow-y-auto w-[350px]">
            <div class="p-4 border-b border-slate-100 bg-slate-50 sticky top-0 z-10">
                <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wide">My Teams</h2>
            </div>
            <ul id="teams-list" class="divide-y divide-slate-100">
                <?php if(empty($teams)): ?>
                    <li class="p-8 text-center text-slate-400">
                        <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        <p class="text-sm">You haven't joined any teams yet.</p>
                    </li>
                <?php else: ?>
                    <?php foreach($teams as $team): ?>
                        <li class="team-item p-4 hover:bg-slate-50 cursor-pointer transition-colors group <?= isset($_GET['team_id']) && $_GET['team_id'] == $team['id'] ? 'bg-teal-50 border-l-4 border-teal-500' : 'border-l-4 border-transparent' ?>" data-id="<?= htmlspecialchars($team['id']) ?>" data-name="<?= htmlspecialchars($team['name']) ?>" data-role="<?= htmlspecialchars($team['role']) ?>">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-slate-800 text-sm group-hover:text-teal-700 transition-colors"><?= htmlspecialchars($team['name']) ?></h3>
                                    <p class="text-xs text-slate-500 mt-1 flex items-center">
                                        <?php if (!empty($team['role'])): ?>
                                            <span class="inline-block w-2h-2 rounded-full mr-1 <?= $team['role'] === 'owner' ? 'text-amber-500' : 'text-slate-400' ?>">★</span>
                                            <?= ucfirst($team['role']) ?>
                                        <?php else: ?>
                                            <span class="inline-block w-2h-2 rounded-full mr-1 text-slate-400">○</span>
                                            Not a member
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <svg class="w-5 h-5 text-slate-300 group-hover:text-teal-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Team Details Area -->
        <div class="flex-1 bg-slate-50 overflow-y-auto" id="team-details">
            <div class="p-8 h-full flex flex-col justify-center items-center text-center text-slate-500" id="empty-state">
                <svg class="w-16 h-16 mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                <h3 class="text-lg font-medium text-slate-700">Select a team</h3>
                <p class="text-sm mt-1 max-w-sm">Choose a team from the sidebar to view details, manage members, and configure settings.</p>
            </div>

            <div id="active-state" class="hidden p-6 max-w-5xl mx-auto space-y-6">
                
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-start justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-800" id="active-team-name">Team Name</h2>
                        <p class="text-sm text-slate-500 mt-2 flex items-center">
                            <svg class="w-4 h-4 mr-1 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                            Your role: <span class="font-medium text-slate-700 ml-1" id="active-team-role">Member</span>
                        </p>
                    </div>
                    <div>
                        <button id="btn-delete-team" class="text-sm bg-rose-50 hover:bg-rose-100 text-rose-600 font-medium py-1.5 px-3 rounded-lg transition-colors flex items-center hidden">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            Delete Team
                        </button>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-6 border-b border-slate-200 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-slate-800">Team Members</h3>
                        <button id="btn-add-member" class="text-sm bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium py-1.5 px-3 rounded-lg transition-colors flex items-center hidden">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                            Add Member
                        </button>
                    </div>
                    <div>
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-slate-500 bg-slate-50 uppercase border-b border-slate-100">
                                <tr>
                                    <th scope="col" class="px-6 py-3">Member</th>
                                    <th scope="col" class="px-6 py-3">Role</th>
                                    <th scope="col" class="px-6 py-3">Joined</th>
                                    <th scope="col" class="px-6 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="members-list" class="divide-y divide-slate-100">
                                <!-- Members injected by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Modals -->

<!-- Create Team Modal -->
<div id="modal-create-team" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center hidden opacity-0 transition-opacity">
    <div class="bg-white w-96 rounded-xl shadow-2xl border border-slate-200 transform scale-95 transition-transform overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <h3 class="font-semibold text-slate-800">Create New Team</h3>
            <button class="modal-close text-slate-400 hover:text-slate-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form id="form-create-team" class="p-5 space-y-4">
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Team Name</label>
                <input type="text" name="name" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:bg-white focus:border-teal-500 focus:ring-2 focus:ring-teal-200 outline-none transition-all placeholder-slate-400" placeholder="e.g. Design Team">
            </div>
            <div class="pt-2 flex justify-end space-x-2">
                <button type="button" class="modal-close px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium bg-teal-600 hover:bg-teal-700 text-white rounded-lg transition-colors shadow-sm">Create Team</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Member Modal -->
<div id="modal-add-member" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center hidden opacity-0 transition-opacity">
    <div class="bg-white w-96 rounded-xl shadow-2xl border border-slate-200 transform scale-95 transition-transform overflow-visible">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <h3 class="font-semibold text-slate-800">Add Team Member</h3>
            <button class="modal-close text-slate-400 hover:text-slate-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form id="form-add-member" class="p-5 space-y-4">
            <input type="hidden" name="team_id" id="add-member-team-id">
            
            <div class="relative">
                <label class="block text-xs font-medium text-slate-700 mb-1">Search User</label>
                <input type="text" id="user-search-input" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:bg-white focus:border-teal-500 focus:ring-2 focus:ring-teal-200 outline-none transition-all placeholder-slate-400" placeholder="Search by name or email..." autocomplete="off">
                <input type="hidden" name="user_id" id="selected-user-id" required>
                <ul id="user-search-results" class="absolute z-[60] w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-xl max-h-[250px] overflow-y-auto hidden divide-y divide-slate-50"></ul>
                <div id="selected-user-display" class="hidden mt-2 p-2 bg-teal-50 border border-teal-200 rounded text-sm text-teal-800 flex items-center justify-between">
                    <span id="selected-user-name" class="font-medium"></span>
                    <button type="button" id="clear-selected-user" class="text-teal-600 hover:text-teal-800"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
                </div>
            </div>
            
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Role</label>
                <select name="role" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:bg-white focus:border-teal-500 focus:ring-2 focus:ring-teal-200 outline-none transition-all">
                    <option value="member">Member</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            
            <div class="pt-2 flex justify-end space-x-2">
                <button type="button" class="modal-close px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium bg-teal-600 hover:bg-teal-700 text-white rounded-lg transition-colors shadow-sm disabled:opacity-50" id="btn-submit-member" disabled>Add to Team</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let currentTeamId = null;
    let currentTeamRole = null;
    const currentUserId = <?= (int)$currentUser['id'] ?>;
    const userSystemRole = "<?= $currentUser['role'] ?? 'data_analyst' ?>";

    // Modals config
    const modalCreate = document.getElementById('modal-create-team');
    const modalAdd = document.getElementById('modal-add-member');
    
    function openModal(modal) {
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('div').classList.remove('scale-95');
        }, 10);
    }
    
    function closeModal(modal) {
        modal.classList.add('opacity-0');
        modal.querySelector('div').classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
            // reset forms
            const form = modal.querySelector('form');
            if(form) form.reset();
        }, 300);
    }

    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const modal = e.target.closest('.fixed');
            if (modal) closeModal(modal);
        });
    });

    document.getElementById('btn-new-team').addEventListener('click', () => {
        openModal(modalCreate);
    });

    // Create Team Form
    const formCreate = document.getElementById('form-create-team');
    formCreate.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = new FormData(formCreate);
        
        try {
            const res = await fetch(`/api/teams.php?action=create`, {
                method: 'POST',
                body: data
            });
            const json = await res.json();
            if (json.success) {
                closeModal(modalCreate);
                window.location.reload(); // Quick refresh to update list
            } else {
                showAlert('Error', json.error || 'Failed to create team', 'danger');
            }
        } catch (e) {
            console.error(e);
            showAlert('Error', 'An error occurred.', 'danger');
        }
    });

    // Handle selecting a team
    document.querySelectorAll('.team-item').forEach(item => {
        item.addEventListener('click', async () => {
            currentTeamId = item.dataset.id;
            currentTeamRole = item.dataset.role;
            
            // UI Switch
            document.querySelectorAll('.team-item').forEach(i => {
                i.classList.remove('bg-teal-50', 'border-teal-500');
                i.classList.add('border-transparent');
            });
            item.classList.remove('border-transparent');
            item.classList.add('bg-teal-50', 'border-teal-500');
            
            document.getElementById('empty-state').classList.add('hidden');
            document.getElementById('active-state').classList.remove('hidden');
            
            document.getElementById('active-team-name').textContent = item.dataset.name;
            document.getElementById('active-team-role').textContent = currentTeamRole ? (currentTeamRole.charAt(0).toUpperCase() + currentTeamRole.slice(1)) : 'Observer';
            
            if(['owner', 'admin'].includes(currentTeamRole) || ['admin'].includes(userSystemRole)) {
                document.getElementById('btn-add-member').classList.remove('hidden');
            } else {
                document.getElementById('btn-add-member').classList.add('hidden');
            }

            if(currentTeamRole === 'owner' || userSystemRole === 'admin') {
                document.getElementById('btn-delete-team').classList.remove('hidden');
            } else {
                document.getElementById('btn-delete-team').classList.add('hidden');
            }
            
            await loadTeamMembers(currentTeamId, currentTeamRole);
        });
    });

    async function loadTeamMembers(teamId, userRole) {
        const tbody = document.getElementById('members-list');
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-slate-500">Loading...</td></tr>';
        
        try {
            const res = await fetch(`/api/teams.php?action=members&team_id=${teamId}`);
            const json = await res.json();
            if (json.success) {
                tbody.innerHTML = '';
                json.members.forEach(member => {
                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-slate-50 transition-colors group';
                    
                    // Simple Date Format
                    const jDate = new Date(member.joined_at.replace(/-/g, '/')).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                    
                    let actionHtml = '';
                    if (['owner', 'admin'].includes(userRole) || ['admin'].includes(userSystemRole) || parseInt(member.id) === currentUserId) {
                        if (!(userRole === 'admin' && member.team_role === 'owner') && !(parseInt(member.id) !== currentUserId && member.team_role === 'owner')) {
                            actionHtml = `<button class="text-rose-500 hover:text-rose-700 text-xs font-medium px-2 py-1 rounded hover:bg-rose-50 transition-colors" onclick="removeMember(${member.id}, ${teamId})">${parseInt(member.id) === currentUserId ? 'Leave' : 'Remove'}</button>`;
                        }
                    }
                    
                    let roleDisplayHtml = '';
                    const canChangeRole = (['owner', 'admin'].includes(userRole) || ['admin'].includes(userSystemRole));
                    
                    if (canChangeRole && parseInt(member.id) !== currentUserId && !(userRole === 'admin' && member.team_role === 'owner')) {
                        roleDisplayHtml = `
                            <select class="bg-slate-50 border border-slate-200 rounded px-2 py-1 text-xs focus:ring-2 focus:ring-teal-200 outline-none w-24" 
                                    onchange="changeMemberRole(${member.id}, ${teamId}, this.value, '${member.team_role}')">
                                <option value="member" ${member.team_role === 'member' ? 'selected' : ''}>Member</option>
                                <option value="admin" ${member.team_role === 'admin' ? 'selected' : ''}>Admin</option>
                                ${['owner'].includes(userRole) || ['admin'].includes(userSystemRole) ? `<option value="owner" ${member.team_role === 'owner' ? 'selected' : ''}>Owner</option>` : ''}
                            </select>
                        `;
                    } else {
                        roleDisplayHtml = `
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                member.team_role === 'owner' ? 'bg-amber-100 text-amber-800' :
                                member.team_role === 'admin' ? 'bg-indigo-100 text-indigo-800' :
                                'bg-slate-100 text-slate-800'
                            }">
                                ${member.team_role.charAt(0).toUpperCase() + member.team_role.slice(1)}
                            </span>
                        `;
                    }

                    tr.innerHTML = `
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <img class="w-8 h-8 rounded-full border border-slate-200 mr-3" src="https://ui-avatars.com/api/?name=${encodeURIComponent(member.name)}&background=f1f5f9&color=64748b" alt="${member.name}">
                                <div>
                                    <div class="font-medium text-slate-800">${member.name}</div>
                                    <div class="text-xs text-slate-500">${member.email}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            ${roleDisplayHtml}
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-500 whitespace-nowrap">
                            ${jDate}
                        </td>
                        <td class="px-6 py-4 text-right">
                            ${actionHtml}
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-rose-500">${json.error || 'Failed to load members'}</td></tr>`;
            }
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-rose-500">Connection error</td></tr>`;
        }
    }

    // Assign to window to make available to onclick handlers
    window.removeMember = async function(userId, teamId) {
        if (!await showConfirm('Remove Member', 'Are you sure you want to remove this user from the team?', 'danger')) return;
        
        try {
            const fd = new FormData();
            fd.append('team_id', teamId);
            fd.append('user_id', userId);
            
            const res = await fetch(`/api/teams.php?action=remove_member`, { method: 'POST', body: fd });
            const json = await res.json();
            
            if (json.success) {
                if (parseInt(userId) === currentUserId) {
                    // Removed self
                    window.location.reload();
                } else {
                    loadTeamMembers(currentTeamId, currentTeamRole);
                }
            } else {
                showAlert('Error', json.error || 'Failed to remove member', 'danger');
            }
        } catch (e) {
             showAlert('Error', 'Connection error', 'danger');
        }
    };

    window.changeMemberRole = async function(userId, teamId, role, originalRole) {
        if (!await showConfirm('Change Role', `Are you sure you want to change this member's role to ${role}?`, 'warning')) {
            // Revert select visually
            event.target.value = originalRole;
            return;
        }

        try {
            const fd = new FormData();
            fd.append('team_id', teamId);
            fd.append('user_id', userId);
            fd.append('role', role);
            
            const res = await fetch(`/api/teams.php?action=update_role`, { method: 'POST', body: fd });
            const json = await res.json();
            
            if (json.success) {
                loadTeamMembers(currentTeamId, currentTeamRole);
            } else {
                showAlert('Error', json.error || 'Failed to update member role', 'danger');
                event.target.value = originalRole;
            }
        } catch (e) {
             showAlert('Error', 'Connection error', 'danger');
             event.target.value = originalRole;
        }
    };

    document.getElementById('btn-delete-team').addEventListener('click', async () => {
        if (!currentTeamId) return;
        if (!await showConfirm('Delete Team', 'Are you sure you want to permanently delete this team? All associated sub-projects and team data will be removed. This action cannot be undone.', 'danger')) return;
        
        try {
            const fd = new FormData();
            fd.append('team_id', currentTeamId);
            
            const res = await fetch(`/api/teams.php?action=delete`, { method: 'POST', body: fd });
            const json = await res.json();
            
            if (json.success) {
                window.location.href = '/teams.php';
            } else {
                showAlert('Error', json.error || 'Failed to delete team', 'danger');
            }
        } catch (e) {
            console.error(e);
            showAlert('Error', 'Connection error', 'danger');
        }
    });

    // Add Member Flow
    document.getElementById('btn-add-member').addEventListener('click', () => {
        document.getElementById('add-member-team-id').value = currentTeamId;
        openModal(modalAdd);
        clearSelectedUser();
    });
    
    // User search autopsy
    const searchInput = document.getElementById('user-search-input');
    const searchResults = document.getElementById('user-search-results');
    const userIdInput = document.getElementById('selected-user-id');
    const userDisplay = document.getElementById('selected-user-display');
    const btnSubmitMember = document.getElementById('btn-submit-member');
    
    let searchTimeout;
    
    searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        const q = e.target.value.trim();
        if (q.length < 2) {
            searchResults.classList.add('hidden');
            return;
        }
        
        searchTimeout = setTimeout(async () => {
            try {
                // Pass currentTeamId to exclude existing team members
                const res = await fetch(`/api/teams.php?action=search_users&q=${encodeURIComponent(q)}&team_id=${currentTeamId}`);
                const json = await res.json();
                
                searchResults.innerHTML = '';
                if (json.success && json.users.length > 0) {
                    json.users.forEach(u => {
                        const li = document.createElement('li');
                        li.className = 'px-4 py-2 hover:bg-slate-50 cursor-pointer flex justify-between items-center text-sm';
                        li.innerHTML = `
                            <span class="font-medium text-slate-800">${u.name}</span>
                            <span class="text-xs text-slate-500">${u.email}</span>
                        `;
                        li.onclick = () => selectUser(u);
                        searchResults.appendChild(li);
                    });
                    searchResults.classList.remove('hidden');
                } else {
                    searchResults.innerHTML = '<li class="px-4 py-2 text-sm text-slate-500">No users found</li>';
                    searchResults.classList.remove('hidden');
                }
            } catch (e) {
                console.error(e);
            }
        }, 300);
    });
    
    function selectUser(user) {
        userIdInput.value = user.id;
        document.getElementById('selected-user-name').textContent = `${user.name} (${user.email})`;
        
        searchInput.classList.add('hidden');
        searchResults.classList.add('hidden');
        userDisplay.classList.remove('hidden');
        btnSubmitMember.disabled = false;
        searchInput.value = '';
    }
    
    function clearSelectedUser() {
        userIdInput.value = '';
        searchInput.classList.remove('hidden');
        userDisplay.classList.add('hidden');
        btnSubmitMember.disabled = true;
    }
    
    document.getElementById('clear-selected-user').addEventListener('click', clearSelectedUser);
    
    // Add Member Submit
    document.getElementById('form-add-member').addEventListener('submit', async (e) => {
         e.preventDefault();
         const data = new FormData(document.getElementById('form-add-member'));
         try {
             const res = await fetch(`/api/teams.php?action=add_member`, { method: 'POST', body: data });
             const json = await res.json();
             
             if (json.success) {
                 closeModal(modalAdd);
                 loadTeamMembers(currentTeamId, currentTeamRole);
             } else {
                 showAlert('Error', json.error || 'Failed to add member', 'danger');
             }
         } catch (e) {
             showAlert('Error', 'Connection error', 'danger');
         }
    });

    // Auto-select team from URL if present
    const urlParams = new URLSearchParams(window.location.search);
    const initialTeamId = urlParams.get('team_id');
    if (initialTeamId) {
        const item = document.querySelector(`.team-item[data-id="${initialTeamId}"]`);
        if (item) item.click();
    } else {
        // Auto-select first if available
        const firstTeam = document.querySelector('.team-item');
        if (firstTeam) firstTeam.click();
    }
});
</script>

<?php require 'views/layouts/footer.php'; ?>
