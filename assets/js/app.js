// /assets/js/app.js

let currentProjectId = 1; // Global context

// Global Modal Functions (Alert / Confirm)

// Clipboard Sharer
window.copyProjectShareLink = function() {
    const url = window.location.href;
    navigator.clipboard.writeText(url).then(() => {
        showAlert('Link Copied', 'The project link has been copied to your clipboard.', 'success');
    }).catch(err => {
        showAlert('Copy Failed', 'Unable to copy the link. Please copy it from your browser address bar.', 'danger');
    });
};
window.showAlert = function(title, message, type = 'info') {
    return _showGlobalModal(title, message, type, false);
};

window.showConfirm = function(title, message, type = 'warning') {
    return _showGlobalModal(title, message, type, true);
};

function _showGlobalModal(title, message, type, isConfirm) {
    return new Promise((resolve) => {
        const overlay = document.getElementById('global-modal-overlay');
        const box = document.getElementById('global-modal-box');
        const titleEl = document.getElementById('global-modal-title');
        const msgEl = document.getElementById('global-modal-message');
        const iconContainer = document.getElementById('global-modal-icon');
        const btnCancel = document.getElementById('global-modal-cancel');
        const btnConfirm = document.getElementById('global-modal-confirm');

        if (!overlay) return resolve(false);

        // Reset state
        btnCancel.classList.add('hidden');
        btnCancel.onclick = null;
        btnConfirm.onclick = null;

        titleEl.textContent = title;
        msgEl.innerHTML = message;

        // Type Configuration
        let iconHtml = '';
        let btnClasses = 'px-4 py-2 text-white rounded-lg text-sm font-medium transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-1 active:scale-[0.98] ';
        let iconBg = '';
        let iconText = '';

        switch(type) {
            case 'danger':
            case 'error':
                iconHtml = `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>`;
                iconBg = 'bg-red-100'; iconText = 'text-red-600';
                btnConfirm.className = btnClasses + 'bg-red-600 hover:bg-red-700 focus:ring-red-500';
                btnConfirm.textContent = isConfirm ? 'Delete' : 'OK';
                break;
            case 'warning':
                iconHtml = `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
                iconBg = 'bg-amber-100'; iconText = 'text-amber-600';
                btnConfirm.className = btnClasses + 'bg-amber-500 hover:bg-amber-600 focus:ring-amber-500';
                btnConfirm.textContent = isConfirm ? 'Confirm' : 'OK';
                break;
            case 'success':
                iconHtml = `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
                iconBg = 'bg-teal-100'; iconText = 'text-teal-600';
                btnConfirm.className = btnClasses + 'bg-teal-600 hover:bg-teal-700 focus:ring-teal-500';
                btnConfirm.textContent = 'OK';
                break;
            default: // info
                iconHtml = `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
                iconBg = 'bg-blue-100'; iconText = 'text-blue-600';
                btnConfirm.className = btnClasses + 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500';
                btnConfirm.textContent = 'OK';
                break;
        }

        iconContainer.innerHTML = iconHtml;
        iconContainer.className = `w-10 h-10 rounded-full flex flex-shrink-0 items-center justify-center shrink-0 ${iconBg} ${iconText}`;

        if (isConfirm) {
            btnCancel.classList.remove('hidden');
            btnCancel.onclick = () => { closeModal(); resolve(false); };
        }

        btnConfirm.onclick = () => { closeModal(); resolve(true); };

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
    });
}

document.addEventListener('DOMContentLoaded', () => {
    console.log('Bathyal app core loaded.');
    
    const board = document.getElementById('kanban-board');
    if (board) {
        const urlParams = new URLSearchParams(window.location.search);
        currentProjectId = urlParams.get('id') || 1;
        loadProjectBoard(currentProjectId);
    }
});

async function loadProjectBoard(projectId) {
    try {
        const response = await fetch(`api/projects.php?id=${projectId}`);
        const project = await response.json();
        
        if (project.error) {
            document.getElementById('kanban-board').innerHTML = `<p class="text-red-500 p-4">${project.error}</p>`;
            return;
        }

        document.getElementById('project-title').textContent = project.name;
        document.getElementById('project-status').textContent = project.status.toUpperCase();
        
        // Render project members
        const projectMembersDiv = document.getElementById('project-members');
        if (projectMembersDiv) {
            projectMembersDiv.innerHTML = '';
            if (project.member_names) {
                const names = project.member_names.split(',');
                const ids = project.member_ids.split(',');
                
                // Keep track of current project members for the dropdown
                window.currentProjectMemberIds = ids.map(id => parseInt(id));
                
                names.slice(0, 5).forEach(n => {
                    const initial = n.trim().charAt(0).toUpperCase();
                    projectMembersDiv.innerHTML += `<div class="w-8 h-8 rounded-full bg-teal-100 border-2 border-white cursor-pointer text-teal-700 text-sm font-bold flex items-center justify-center shadow-sm" title="${n}" onclick="toggleProjectMemberDropdown(event)">${initial}</div>`;
                });
                if (names.length > 5) {
                    projectMembersDiv.innerHTML += `<div class="w-8 h-8 rounded-full bg-slate-100 border-2 border-white cursor-pointer text-slate-500 text-xs font-bold flex items-center justify-center shadow-sm" onclick="toggleProjectMemberDropdown(event)">+${names.length - 5}</div>`;
                }
            } else {
                window.currentProjectMemberIds = [];
                // Empty state avatar
                projectMembersDiv.innerHTML = `
                    <div class="w-8 h-8 rounded-full bg-slate-100 border-2 border-white cursor-pointer hover:bg-slate-200 transition-colors flex items-center justify-center text-slate-400 group shadow-sm" onclick="toggleProjectMemberDropdown(event)" title="Add Member">
                        <svg class="w-4 h-4 group-hover:text-slate-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    </div>
                `;
            }
        }
        
        const board = document.getElementById('kanban-board');
        board.innerHTML = '';
        
        // Prepare the list view table
        const listTable = document.getElementById('list-table');
        if(listTable) {
            // Remove existing tbodys
            listTable.querySelectorAll('tbody').forEach(tb => tb.remove());
        }

        project.sections.forEach(section => {
            // --- KANBAN RENDER ---
            const col = document.createElement('div');
            col.className = 'w-[320px] flex-shrink-0 flex flex-col bg-slate-100 rounded-xl max-h-full border border-slate-200/60 shadow-sm kanban-col-container';
            
            let html = `
                <div class="px-4 py-3 flex justify-between items-center cursor-move group">
                    <h3 class="font-semibold text-slate-700 text-sm whitespace-nowrap overflow-hidden text-ellipsis flex-1 pr-2 cursor-text" onclick="promptEditSection(${section.id}, '${section.name.replace(/'/g, "\\'")}')" title="Click to rename">${section.name}</h3>
                    <div class="flex items-center space-x-1 shrink-0">
                        <span class="bg-slate-200 text-slate-600 text-xs font-medium px-2 py-0.5 rounded-full">${section.tasks.length}</span>
                        <button onclick="promptDeleteSection(${section.id}, '${section.name.replace(/'/g, "\\'")}')" class="text-slate-400 hover:text-rose-500 opacity-0 group-hover:opacity-100 transition-opacity p-0.5 rounded hover:bg-slate-200" title="Delete Section">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto px-3 pb-2 space-y-2.5 min-h-[100px] kanban-col" data-section-id="${section.id}">
            `;
            
        section.tasks.forEach(task => {
            html += renderTaskCard(task);
        });
        
        html += `</div>
            <div class="p-2 pt-0 mt-1 pb-3">
                <button onclick="promptAddTask(${section.id})" class="w-full flex items-center justify-center text-slate-500 hover:text-teal-600 hover:bg-slate-200/50 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Add task
                </button>
            </div>
        `;
        col.innerHTML = html;
        board.appendChild(col);

            // --- LIST VIEW RENDER (WITH SECTION HEADER) ---
            if (listTable) {
                const tbody = document.createElement('tbody');
                tbody.className = 'divide-y divide-slate-100 sortable-section';
                tbody.dataset.sectionId = section.id;

                const secTr = document.createElement('tr');
                secTr.className = 'bg-slate-50/80 border-b border-slate-200/60 sticky top-0 z-10 cursor-pointer hover:bg-slate-100 transition-colors section-header';
                secTr.onclick = (e) => toggleListSection(tbody, secTr);
                secTr.innerHTML = `
                    <td colspan="4" class="p-0">
                        <div class="px-4 py-2 font-semibold text-slate-700 text-sm flex items-center justify-between group">
                            <div class="flex items-center">
                                <svg class="w-4 h-4 mr-2 text-slate-400 transform transition-transform section-toggle-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                <span class="hover:underline cursor-text" onclick="event.stopPropagation(); promptEditSection(${section.id}, '${section.name.replace(/'/g, "\\'")}')" title="Click to rename">${section.name}</span>
                                <span class="ml-2 text-xs font-normal text-slate-500">(${section.tasks.length})</span>
                            </div>
                            <div class="flex items-center space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button onclick="event.stopPropagation(); promptAddTask(${section.id})" class="text-slate-400 hover:text-teal-600 p-1 rounded hover:bg-slate-200" title="Add Task">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                </button>
                                <button onclick="event.stopPropagation(); promptDeleteSection(${section.id}, '${section.name.replace(/'/g, "\\'")}')" class="text-slate-400 hover:text-rose-500 p-1 rounded hover:bg-slate-200" title="Delete Section">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </div>
                    </td>
                `;
                tbody.appendChild(secTr);

                section.tasks.forEach(task => {
                    renderTaskListRow(task, tbody, 0, null); // 0 = depth, null = no parent ID
                });
                listTable.appendChild(tbody);
            }
        });

        // Placeholder for adding a new section column
        const addCol = document.createElement('button');
        addCol.onclick = () => promptAddSection();
        addCol.className = 'w-[320px] flex-shrink-0 flex items-center justify-center bg-slate-200/50 hover:bg-slate-200 border-2 border-dashed border-slate-300 hover:border-slate-400 text-slate-500 hover:text-slate-700 rounded-xl h-14 font-medium transition-colors text-sm';
        addCol.id = 'kanban-add-section-btn';
        addCol.innerHTML = `
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Add Section
        `;
        board.appendChild(addCol);

        // --- Drag and Drop Initialization ---
        if (typeof Sortable !== 'undefined') {
            // Board Sections (Columns)
            if (board) {
                new Sortable(board, {
                    animation: 150,
                    handle: '.cursor-move',
                    filter: '#kanban-add-section-btn',
                    draggable: '.kanban-col-container',
                    onEnd: function (evt) {
                        const sectionIds = Array.from(board.querySelectorAll('.kanban-col-container'))
                            .map(el => el.querySelector('.kanban-col').dataset.sectionId);
                        
                        fetch('api/sections.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({ action: 'reorder', section_ids: sectionIds })
                        }).catch(console.error);
                    }
                });

                // Board Tasks and Nested Subtasks Dropzones
                const sortableOptions = {
                    group: 'shared-board',
                    animation: 150,
                    handle: '.cursor-grab',
                    fallbackOnBody: true,
                    swapThreshold: 0.65,
                    onEnd: function (evt) {
                        const isSubtaskZone = evt.to.classList.contains('kanban-subtasks');
                        let newSectionId = null;
                        let parentTaskId = null;

                        if (isSubtaskZone) {
                            parentTaskId = evt.to.dataset.parentTaskId;
                            const column = evt.to.closest('.kanban-col');
                            newSectionId = column ? column.dataset.sectionId : null;
                        } else {
                            newSectionId = evt.to.dataset.sectionId;
                        }

                        const taskIds = Array.from(evt.to.children)
                            .map(c => c.closest('.task-card')?.dataset.taskId)
                            .filter(id => id != null);
                        
                        // For dropping directly into tasks, ensure evt.to.children are properly mapped to their task ID 
                        fetch('api/tasks.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({ action: 'reorder', section_id: newSectionId, task_ids: taskIds, parent_task_id: parentTaskId })
                        }).catch(console.error);
                    }
                };

                board.querySelectorAll('.kanban-col, .kanban-subtasks').forEach(area => {
                    new Sortable(area, sortableOptions);
                });
            }

            // List Sections (Tbodys)
            if (listTable) {
                new Sortable(listTable, {
                    animation: 150,
                    handle: '.section-header', // Can drag by the whole section header row
                    draggable: 'tbody.sortable-section',
                    onEnd: function (evt) {
                        const sectionIds = Array.from(listTable.querySelectorAll('tbody.sortable-section'))
                            .map(el => el.dataset.sectionId);
                        
                        fetch('api/sections.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({ action: 'reorder', section_ids: sectionIds })
                        }).catch(console.error);
                    }
                });

                // List Tasks
                listTable.querySelectorAll('tbody.sortable-section').forEach(tbody => {
                    new Sortable(tbody, {
                        group: 'shared-list',
                        animation: 150,
                        handle: '.cursor-grab-list',
                        filter: '.section-header',
                        onMove: function(evt) {
                            // Don't allow dropping items before the section header
                            return evt.related.className.indexOf('section-header') === -1;
                        },
                        onEnd: function(evt) {
                            const newSectionId = evt.to.dataset.sectionId;
                            
                            // Get dragged task ID
                            const draggedTaskMatch = evt.item.className.match(/task-row-(\d+)/);
                            const draggedTaskId = draggedTaskMatch ? draggedTaskMatch[1] : null;

                            // Detect hierarchy based on previous element
                            let newParentTaskId = null;
                            let newDepth = 0;
                            const prev = evt.item.previousElementSibling;
                            
                            if (prev && prev.classList.contains('task-row')) {
                                const childMatch = prev.className.match(/child-of-(\d+)/);
                                if (childMatch) {
                                    newParentTaskId = childMatch[1];
                                } else {
                                    const next = evt.item.nextElementSibling;
                                    if (next && next.classList.contains('task-row')) {
                                        const nextChildMatch = next.className.match(/child-of-(\d+)/);
                                        if (nextChildMatch && nextChildMatch[1] === prev.dataset.taskId) {
                                            newParentTaskId = prev.dataset.taskId;
                                        }
                                    }
                                }
                            }

                            // Visually update the dragged task's classes and styling
                            if (draggedTaskId) {
                                evt.item.className = evt.item.className.replace(/child-of-\d+/g, '').replace(/\s+/g, ' ');
                                if (newParentTaskId) {
                                    evt.item.classList.add(`child-of-${newParentTaskId}`);
                                    newDepth = 1;
                                }
                                const paddingVal = newDepth * 24 + 16;
                                const firstTd = evt.item.querySelector('td:first-child');
                                if (firstTd) firstTd.style.paddingLeft = paddingVal + 'px';
                            }
                            
                            const taskIds = Array.from(evt.to.querySelectorAll('tr.task-row:not(.section-header)'))
                                .map(c => {
                                    const match = c.className.match(/task-row-(\d+)/);
                                    return match ? match[1] : null;
                                })
                                .filter(id => id != null);
                            
                            fetch('api/tasks.php', {
                                method: 'POST',
                                headers: {'Content-Type': 'application/json'},
                                body: JSON.stringify({ 
                                    action: 'reorder', 
                                    section_id: newSectionId, 
                                    task_ids: taskIds,
                                    dragged_task_id: draggedTaskId,
                                    parent_task_id: newParentTaskId 
                                })
                            }).catch(console.error);
                        }
                    });
                });
            }
        }

    } catch (e) {
        console.error('Error loading project:', e);
        document.getElementById('kanban-board').innerHTML = `<p class="text-red-500 p-4">Error loading project. Check console.</p>`;
    }
}

// ... section prompt logic below loading logic



function renderTaskCard(task) {
    const statusColor = task.status === 'completed' ? 'text-emerald-500' : 'text-slate-400';
    
    // Multiple Assignees Visual Stack
    let assigneesHtml = '';
    if (task.assignee_name) {
        const names = task.assignee_name.split(',');
        assigneesHtml = `<div class="flex -space-x-2 overflow-hidden ml-2 flex-shrink-0" title="${task.assignee_name}">`;
        names.slice(0, 3).forEach(n => {
            const initial = n.trim().charAt(0).toUpperCase();
            assigneesHtml += `<div class="w-6 h-6 rounded-full bg-teal-100 border-2 border-white text-teal-700 text-[10px] font-bold flex items-center justify-center">${initial}</div>`;
        });
        if (names.length > 3) {
            assigneesHtml += `<div class="w-6 h-6 rounded-full bg-slate-100 border-2 border-white text-slate-500 text-[9px] font-bold flex items-center justify-center">+${names.length - 3}</div>`;
        }
        assigneesHtml += `</div>`;
    }

    const subtaskBadge = task.subtasks && task.subtasks.length > 0 
        ? `<div class="text-[10px] text-slate-500 flex items-center"><svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>${task.subtasks.length} subtasks</div>` 
        : '';
    
    return `
        <div class="bg-white p-3.5 rounded-lg shadow-sm border border-slate-200 task-card hover:border-teal-400 hover:shadow transition-all cursor-grab active:cursor-grabbing" data-task-id="${task.id}" onclick="openTaskModal(${task.id})">
            <div class="flex justify-between items-start mb-2">
                <h4 class="text-sm text-slate-800 font-medium leading-snug">${task.title}</h4>
                ${assigneesHtml}
            </div>
            ${task.description ? `<p class="text-xs text-slate-500 line-clamp-2 mb-3 mt-1">${task.description}</p>` : ''}
            
            <div class="flex justify-between items-center text-xs mt-3 pt-3 border-t border-slate-50 relative pointer-events-none">
                <div class="flex items-center space-x-2 text-slate-400 font-medium">
                    <svg class="w-3.5 h-3.5 ${statusColor}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                    <span>${task.status}</span>
                </div>
                ${subtaskBadge}
            </div>
            <div class="kanban-subtasks min-h-[4px] mt-2 border-t border-dashed border-slate-200 pt-1" data-parent-task-id="${task.id}" onclick="event.stopPropagation()"></div>
        </div>
    `;
}

function renderTaskListRow(task, tbody, depth = 0, parentId = null) {
    const tr = document.createElement('tr');
    tr.className = `hover:bg-slate-50 cursor-pointer border-b border-slate-100 task-row ${parentId ? 'hidden child-of-' + parentId : ''} task-row-${task.id}`;
    tr.dataset.taskId = task.id;
    tr.onclick = (e) => {
        // Prevent modal open if clicking explicitly on the tree expand toggle or drag handle
        if (e.target.closest('.subtask-toggle') || e.target.closest('.cursor-grab-list')) return;
        openTaskModal(task.id);
    };
    
    // Add padding based on subtask depth
    const paddingVal = depth * 24 + 16;
    
    const hasSubtasks = task.subtasks && task.subtasks.length > 0;
    let toggleIcon = '';
    if (hasSubtasks) {
        toggleIcon = `
            <button class="subtask-toggle mr-1.5 text-slate-400 hover:text-slate-600 transition-transform" onclick="toggleSubtasks(this, ${task.id})">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </button>
        `;
    } else {
        // give it an invisible placeholder to align text
        toggleIcon = `<span class="w-4 h-4 mr-1.5 inline-block"></span>`;
    }
    
    // Multiple Assignees Visual Stack
    let assigneesHtml = '<span class="text-slate-400 italic">Unassigned</span>';
    if (task.assignee_name) {
        const names = task.assignee_name.split(',');
        assigneesHtml = `<div class="flex -space-x-2 overflow-hidden" title="${task.assignee_name}">`;
        names.slice(0, 3).forEach(n => {
            const initial = n.trim().charAt(0).toUpperCase();
            assigneesHtml += `<div class="w-6 h-6 rounded-full bg-teal-100 border-2 border-white text-teal-700 text-[10px] font-bold flex items-center justify-center">${initial}</div>`;
        });
        if (names.length > 3) {
            assigneesHtml += `<div class="w-6 h-6 rounded-full bg-slate-100 border-2 border-white text-slate-500 text-[9px] font-bold flex items-center justify-center">+${names.length - 3}</div>`;
        }
        assigneesHtml += `</div>`;
    }

    tr.innerHTML = `
        <td class="py-3 font-medium text-slate-800 flex items-center" style="padding-left: ${paddingVal}px">
            <div class="cursor-grab-list text-slate-300 hover:text-slate-500 mr-2 flex items-center justify-center cursor-move" title="Drag to reorder">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M10 9h4V6h3l-5-5-5 5h3v3zm-1 1H6V7l-5 5 5 5v-3h3v-4zm14 2l-5-5v3h-3v4h3v3l5-5zm-9 3h-4v3H7l5 5 5-5h-3v-3z"></path></svg>
            </div>
            ${toggleIcon}
            <span class="truncate block max-w-sm">${task.title}</span>
        </td>
        <td class="px-4 py-3">
            ${assigneesHtml}
        </td>
        <td class="px-4 py-3 text-slate-500 text-sm whitespace-nowrap">${task.due_date ? task.due_date.split(' ')[0] : '-'}</td>
        <td class="px-4 py-3 whitespace-nowrap">
            <span class="px-2 py-0.5 rounded text-xs bg-slate-100 text-slate-600 border border-slate-200/60">${task.status}</span>
            <button onclick="event.stopPropagation(); promptAddSubtask(${task.id})" class="ml-2 text-xs text-teal-600 hover:underline font-medium">+ Subtask</button>
        </td>
    `;
    tbody.appendChild(tr);

    if (hasSubtasks) {
        task.subtasks.forEach(sub => renderTaskListRow(sub, tbody, depth + 1, task.id));
    }
}

function toggleSubtasks(btn, taskId) {
    const isExpanded = btn.classList.contains('rotate-90');
    
    if (isExpanded) {
        // Collapse
        btn.classList.remove('rotate-90');
        hideAllChildren(taskId);
    } else {
        // Expand directly only the immediate children
        btn.classList.add('rotate-90');
        const children = document.querySelectorAll('.child-of-' + taskId);
        children.forEach(child => {
            child.classList.remove('hidden');
            child.style.display = '';
        });
    }
}

function hideAllChildren(parentId) {
    const children = document.querySelectorAll('.child-of-' + parentId);
    children.forEach(child => {
        child.classList.add('hidden');
        // Reset subtask toggle btn state manually if needed
        const btn = child.querySelector('.subtask-toggle');
        if(btn) btn.classList.remove('rotate-90');
        const childId = child.className.match(/task-row-(\d+)/);
        if (childId && childId[1]) {
            hideAllChildren(childId[1]);
        }
    });
}

function toggleListSection(tbody, secTr) {
    const isExpanded = !secTr.classList.contains('section-collapsed');
    const toggleIcon = secTr.querySelector('.section-toggle-icon');
    
    if (isExpanded) {
        // Collapse
        secTr.classList.add('section-collapsed');
        if(toggleIcon) toggleIcon.classList.add('-rotate-90');
        
        // Hide all rows in this tbody EXCEPT the header (secTr)
        Array.from(tbody.children).forEach(child => {
            if (child !== secTr) {
                child.style.display = 'none';
            }
        });
    } else {
        // Expand
        secTr.classList.remove('section-collapsed');
        if(toggleIcon) toggleIcon.classList.remove('-rotate-90');
        
        // We only want to show root level tasks. The subtasks should follow their toggle state
        Array.from(tbody.children).forEach(child => {
            if (child !== secTr) {
                // If it's a top-level task (no child-of- class)
                const isChild = Array.from(child.classList).some(c => c.startsWith('child-of-'));
                if (!isChild) {
                    child.style.display = '';
                    // If this task has an expanded toggle, also show its immediate children
                    const btn = child.querySelector('.subtask-toggle');
                    if (btn && btn.classList.contains('rotate-90')) {
                        const taskId = child.className.match(/task-row-(\d+)/);
                        if (taskId) {
                            showExpandedChildren(taskId[1]);
                        }
                    }
                }
            }
        });
    }
}

// Helper to recursively restore visibility of expanded trees
function showExpandedChildren(parentId) {
    const children = document.querySelectorAll('.child-of-' + parentId);
    children.forEach(child => {
        child.style.display = '';
        const btn = child.querySelector('.subtask-toggle');
        if (btn && btn.classList.contains('rotate-90')) {
            const childId = child.className.match(/task-row-(\d+)/);
            if (childId) {
                showExpandedChildren(childId[1]);
            }
        }
    });
}

async function promptAddSection() {
    openCreateModal('section', currentProjectId, 'Add New Section', 'Section Name', '');
}

async function promptEditSection(sectionId, currentName) {
    openCreateModal('edit_section', sectionId, 'Rename Section', 'New Section Name', currentName);
    document.getElementById('create-btn-text').innerText = 'Save';
}

async function promptDeleteSection(sectionId, sectionName) {
    const confirmed = await showConfirm('Delete Section', `Are you sure you want to delete the section <strong>${sectionName}</strong>?<br><br><span class="text-rose-600">Warning: All tasks inside this section will be permanently deleted!</span>`, 'danger');
    if (!confirmed) return;

    try {
        const res = await fetch('api/sections.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'delete', section_id: sectionId })
        });
        const data = await res.json();
        
        if (data.success) {
            loadProjectBoard(currentProjectId);
        } else {
            showAlert('Error', data.error || 'Failed to delete section.', 'danger');
        }
    } catch (e) {
        console.error(e);
        showAlert('Error', 'Network error. Please try again.', 'danger');
    }
}

async function promptAddTask(sectionId) {
    openCreateModal('task', sectionId, 'Add New Task', 'Task Title', '');
}

async function promptAddSubtask(parentTaskId) {
    openCreateModal('subtask', parentTaskId, 'Add New Subtask', 'Subtask Title', '');
}

function openCreateModal(actionType, targetId, title, label, initialValue = '') {
    const modal = document.getElementById('create-modal-backdrop');
    const content = document.getElementById('create-modal-content');
    
    document.getElementById('create-action-type').value = actionType;
    document.getElementById('create-target-id').value = targetId;
    document.getElementById('create-modal-title-text').innerText = title;
    document.getElementById('create-title-label').innerText = label;
    document.getElementById('create-btn-text').innerText = 'Create';
    document.getElementById('create-title-input').value = initialValue;
    
    // Show modal
    modal.classList.remove('hidden');
    // small delay for transition
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        content.classList.remove('scale-95');
        document.getElementById('create-title-input').focus();
        document.getElementById('create-title-input').select();
    }, 10);
}

function closeCreateModal() {
    const modal = document.getElementById('create-modal-backdrop');
    const content = document.getElementById('create-modal-content');
    
    modal.classList.add('opacity-0');
    content.classList.add('scale-95');
    
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 200); // match transition duration
}

async function handleCreateSubmit(e) {
    e.preventDefault();
    
    const actionType = document.getElementById('create-action-type').value;
    const targetId = document.getElementById('create-target-id').value;
    const title = document.getElementById('create-title-input').value;
    const submitBtnText = document.getElementById('create-btn-text');
    
    // Simple saving state
    let oldText = submitBtnText.innerText;
    submitBtnText.innerText = 'Saving...';
    
    try {
        if (actionType === 'section') {
            await fetch(`api/sections.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'create', project_id: currentProjectId, name: title })
            });
        } else if (actionType === 'edit_section') {
            await fetch(`api/sections.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'update', section_id: targetId, name: title })
            });
        } else if (actionType === 'task') {
            await fetch(`api/tasks.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ project_id: currentProjectId, section_id: targetId, title: title })
            });
        } else if (actionType === 'subtask') {
            await fetch(`api/tasks.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ parent_task_id: targetId, title: title })
            });
        }
        
        closeCreateModal();
        loadProjectBoard(currentProjectId);
    } catch (err) {
        console.error('Error saving:', err);
        alert('An error occurred. Please try again.');
    } finally {
        submitBtnText.innerText = oldText;
    }
}

function switchView(viewName) {
    document.querySelectorAll('.view-panel').forEach(el => el.classList.add('hidden'));
    document.getElementById('view-' + viewName).classList.remove('hidden');
    
    document.querySelectorAll('#view-tabs button').forEach(el => {
        el.classList.remove('border-teal-500', 'text-teal-600');
        el.classList.add('border-transparent', 'text-slate-500');
    });
    
    const activeTab = document.getElementById('tab-' + viewName);
    if(activeTab) {
        activeTab.classList.remove('border-transparent', 'text-slate-500');
        activeTab.classList.add('border-teal-500', 'text-teal-600');
    }
}