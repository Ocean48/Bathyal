/**
 * Enterprise Table / Spreadsheet Grid Component
 * High-performance virtualized table with inline cell editing and custom fields.
 */
import { api } from '../core/api.js';
import { store } from '../core/store.js';
import { toast } from './Toast.js';
import { eventBus } from '../core/eventBus.js';
import { fieldRegistry } from '../core/fieldRegistry.js';

export class TaskTable {
    constructor(container) {
        this.container = container;
        this.unsubscribe = null;
        this.tasks = [];
        this.customFields = [];
        this.expandedTasks = new Set();
        this.subtasksCache = new Map();
    }

    mount() {
        this.loadData();
        this.unsubscribe = eventBus.on('task:created', () => this.loadData());
    }

    async loadData() {
        const state = store.getState();
        const wsId = state.activeWorkspaceId;

        this.container.innerHTML = `<div style="text-align: center; color: var(--text-muted); padding: 2rem;">Loading table view...</div>`;

        try {
            const [tasksRes, fieldsRes] = await Promise.all([
                api.get('/api/v1/tasks', { workspace_id: wsId || '', view: 'all' }),
                api.get('/api/v1/custom-fields', { workspace_id: wsId || 1 }),
            ]);

            this.tasks = tasksRes.data || [];
            this.customFields = fieldsRes.data || [];
            await this.loadExpandedSubtasks();
            this.render();
        } catch (err) {
            this.container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-title" style="color: var(--danger);">Failed to load table view</div>
                    <div class="empty-state-desc">${this.escapeHtml(err.message || 'Please check connection')}</div>
                </div>
            `;
        }
    }

    async loadExpandedSubtasks() {
        const promises = [];
        for (const taskId of this.expandedTasks) {
            promises.push(
                api.get('/api/v1/tasks', { parent_id: taskId })
                    .then(res => this.subtasksCache.set(taskId, res.data || []))
                    .catch(() => this.subtasksCache.set(taskId, []))
            );
        }
        if (promises.length > 0) {
            await Promise.all(promises);
        }
    }

    render() {
        if (this.tasks.length === 0) {
            this.container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-title">No tasks in table</div>
                    <div class="empty-state-desc">Capture your first task to start filling out the spreadsheet grid.</div>
                </div>
            `;
            return;
        }

        const totalHours = this.tasks.reduce((sum, t) => sum + (parseFloat(t.estimated_hours) || 0), 0);

        const customHeaders = this.customFields.map(f => `
            <th class="table-th">${this.escapeHtml(f.field_name)}</th>
        `).join('');

        const rowsHtml = this.tasks.map(task => {
            const isCompleted = task.status_type === 'completed' || task.status_id == 3;
            const hasSubtasks = (task.subtask_count || 0) > 0;
            const isExpanded = this.expandedTasks.has(task.id);
            const cachedSubtasks = this.subtasksCache.get(task.id) || [];

            let startText = '';
            if (task.start_date) {
                const s = new Date(task.start_date);
                startText = s.toISOString().split('T')[0];
            }

            let dueText = '';
            if (task.due_date) {
                const d = new Date(task.due_date);
                dueText = d.toISOString().split('T')[0];
            }

            const customCells = this.customFields.map(field => {
                const taskFieldVal = (task.custom_fields || []).find(cf => cf.field_id === field.id);
                const rawVal = taskFieldVal ? taskFieldVal.value : null;
                const renderedHtml = fieldRegistry.render(field.field_type, rawVal, field.options);

                return `
                    <td class="table-td custom-field-cell" data-task-id="${task.id}" data-field-id="${field.id}" data-field-type="${field.field_type}">
                        ${renderedHtml}
                    </td>
                `;
            }).join('');

            const parentRow = `
                <tr class="table-tr ${isCompleted ? 'row-completed' : ''}" data-id="${task.id}">
                    <td class="table-td" style="width: 40px; text-align: center;">
                        <input type="checkbox" class="table-row-chk" data-id="${task.id}" ${isCompleted ? 'checked' : ''} />
                    </td>
                    <td class="table-td cell-title" data-id="${task.id}" style="font-weight: 500;">
                        <div style="display: flex; align-items: center; gap: 0.4rem;">
                            <button class="btn-tree-toggle ${hasSubtasks ? 'has-subtasks' : ''} ${isExpanded ? 'expanded' : ''}" data-id="${task.id}" title="${hasSubtasks ? 'Toggle subtasks' : 'Add subtask'}">
                                ${hasSubtasks ? '❯' : '·'}
                            </button>
                            <span class="table-title-text">${this.escapeHtml(task.title)}</span>
                            ${hasSubtasks ? `<span class="badge-subtasks-count">${task.completed_subtask_count || 0}/${task.subtask_count}</span>` : ''}
                        </div>
                    </td>
                    <td class="table-td">
                        <select class="field-inline-select table-status-select" data-id="${task.id}">
                            <option value="1" ${task.status_id == 1 ? 'selected' : ''}>To Do</option>
                            <option value="2" ${task.status_id == 2 ? 'selected' : ''}>In Progress</option>
                            <option value="3" ${task.status_id == 3 ? 'selected' : ''}>Completed</option>
                        </select>
                    </td>
                    <td class="table-td">
                        <select class="field-inline-select table-prio-select" data-id="${task.id}">
                            <option value="none" ${task.priority === 'none' ? 'selected' : ''}>None</option>
                            <option value="low" ${task.priority === 'low' ? 'selected' : ''}>Low</option>
                            <option value="medium" ${task.priority === 'medium' ? 'selected' : ''}>Medium</option>
                            <option value="high" ${task.priority === 'high' ? 'selected' : ''}>High</option>
                            <option value="urgent" ${task.priority === 'urgent' ? 'selected' : ''}>Urgent</option>
                        </select>
                    </td>
                    <td class="table-td">
                        <input type="date" class="field-inline-input table-start-date-input" data-id="${task.id}" value="${startText}" title="Start Date" />
                    </td>
                    <td class="table-td">
                        <input type="date" class="field-inline-input table-date-input" data-id="${task.id}" value="${dueText}" title="Due Date" />
                    </td>
                    <td class="table-td">
                        <input type="number" step="0.5" class="field-inline-input table-hours-input" data-id="${task.id}" value="${task.estimated_hours || ''}" placeholder="-" style="width: 70px;" />
                    </td>
                    ${customCells}
                    <td class="table-td" style="width: 50px; text-align: right;">
                        <button class="btn-icon btn-ghost btn-sm table-del-btn" data-id="${task.id}" title="Delete task">✕</button>
                    </td>
                </tr>
            `;

            const subRows = isExpanded ? cachedSubtasks.map(sub => {
                const subCompleted = sub.status_type === 'completed' || sub.status_id == 3;
                let subDueText = '';
                if (sub.due_date) {
                    const d = new Date(sub.due_date);
                    subDueText = d.toISOString().split('T')[0];
                }

                return `
                    <tr class="table-tr subtask-table-row ${subCompleted ? 'row-completed' : ''}" data-id="${sub.id}" data-parent-id="${task.id}">
                        <td class="table-td" style="width: 40px; text-align: center;">
                            <input type="checkbox" class="table-row-chk" data-id="${sub.id}" ${subCompleted ? 'checked' : ''} />
                        </td>
                        <td class="table-td cell-title subtask-table-cell" data-id="${sub.id}">
                            <div style="display: flex; align-items: center; gap: 0.5rem; padding-left: 1.5rem;">
                                <span style="color: var(--text-subtle); font-size: 0.75rem;">└─</span>
                                <span>${this.escapeHtml(sub.title)}</span>
                            </div>
                        </td>
                        <td class="table-td">
                            <select class="field-inline-select table-status-select" data-id="${sub.id}">
                                <option value="1" ${sub.status_id == 1 ? 'selected' : ''}>To Do</option>
                                <option value="2" ${sub.status_id == 2 ? 'selected' : ''}>In Progress</option>
                                <option value="3" ${sub.status_id == 3 ? 'selected' : ''}>Completed</option>
                            </select>
                        </td>
                        <td class="table-td">
                            <select class="field-inline-select table-prio-select" data-id="${sub.id}">
                                <option value="none" ${sub.priority === 'none' ? 'selected' : ''}>None</option>
                                <option value="low" ${sub.priority === 'low' ? 'selected' : ''}>Low</option>
                                <option value="medium" ${sub.priority === 'medium' ? 'selected' : ''}>Medium</option>
                                <option value="high" ${sub.priority === 'high' ? 'selected' : ''}>High</option>
                                <option value="urgent" ${sub.priority === 'urgent' ? 'selected' : ''}>Urgent</option>
                            </select>
                        </td>
                        <td class="table-td"></td>
                        <td class="table-td">
                            <input type="date" class="field-inline-input table-date-input" data-id="${sub.id}" value="${subDueText}" title="Due Date" />
                        </td>
                        <td class="table-td">
                            <input type="number" step="0.5" class="field-inline-input table-hours-input" data-id="${sub.id}" value="${sub.estimated_hours || ''}" placeholder="-" style="width: 70px;" />
                        </td>
                        ${this.customFields.map(() => '<td class="table-td"></td>').join('')}
                        <td class="table-td" style="width: 50px; text-align: right;">
                            <button class="btn-icon btn-ghost btn-sm table-del-btn" data-id="${sub.id}" title="Delete subtask">✕</button>
                        </td>
                    </tr>
                `;
            }).join('') : '';

            return parentRow + subRows;
        }).join('');

        this.container.innerHTML = `
            <div class="table-wrapper">
                <table class="task-spreadsheet">
                    <thead>
                        <tr>
                            <th class="table-th" style="width: 40px;"></th>
                            <th class="table-th">Task Title</th>
                            <th class="table-th">Status</th>
                            <th class="table-th">Priority</th>
                            <th class="table-th">Start Date</th>
                            <th class="table-th">Due Date</th>
                            <th class="table-th">Est. Hours</th>
                            ${customHeaders}
                            <th class="table-th" style="width: 50px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml}
                    </tbody>
                    <tfoot>
                        <tr class="table-tfoot-row">
                            <td colspan="2" class="table-td" style="font-weight: 600;">Total: ${this.tasks.length} parent tasks</td>
                            <td class="table-td"></td>
                            <td class="table-td"></td>
                            <td class="table-td"></td>
                            <td class="table-td"></td>
                            <td class="table-td" style="font-weight: 600;">${totalHours.toFixed(1)} hrs</td>
                            ${this.customFields.map(() => '<td class="table-td"></td>').join('')}
                            <td class="table-td"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        `;

        this.bindEvents();
    }

    bindEvents() {
        // Toggle Tree Expand/Collapse
        this.container.querySelectorAll('.btn-tree-toggle').forEach(btn => {
            btn.onclick = async (e) => {
                e.stopPropagation();
                const taskId = parseInt(btn.dataset.id, 10);
                if (this.expandedTasks.has(taskId)) {
                    this.expandedTasks.delete(taskId);
                } else {
                    this.expandedTasks.add(taskId);
                    try {
                        const res = await api.get('/api/v1/tasks', { parent_id: taskId });
                        this.subtasksCache.set(taskId, res.data || []);
                    } catch (err) {
                        this.subtasksCache.set(taskId, []);
                    }
                }
                this.render();
            };
        });

        // Row Title Click to Open Drawer
        this.container.querySelectorAll('.table-title-text').forEach(span => {
            span.onclick = () => {
                const tr = span.closest('.table-tr');
                if (tr) {
                    const taskId = tr.dataset.id;
                    const task = this.tasks.find(t => t.id == taskId);
                    if (task) eventBus.emit('task:open-drawer', task);
                }
            };
        });

        this.container.querySelectorAll('.subtask-table-cell').forEach(cell => {
            cell.onclick = () => {
                const subId = cell.dataset.id;
                api.get(`/api/v1/tasks/${subId}`).then(res => {
                    if (res.data) eventBus.emit('task:open-drawer', res.data);
                });
            };
        });

        // Row Checkbox Toggle
        this.container.querySelectorAll('.table-row-chk').forEach(chk => {
            chk.onchange = async () => {
                const taskId = chk.dataset.id;
                const newStatus = chk.checked ? 3 : 1;
                try {
                    await api.patch(`/api/v1/tasks/${taskId}`, { status_id: newStatus });
                    toast.success('Task status updated');
                    this.loadData();
                    eventBus.emit('task:created');
                } catch (err) {
                    toast.error('Failed to update status');
                }
            };
        });

        // Status Select
        this.container.querySelectorAll('.table-status-select').forEach(sel => {
            sel.onchange = async () => {
                const taskId = sel.dataset.id;
                try {
                    await api.patch(`/api/v1/tasks/${taskId}`, { status_id: parseInt(sel.value, 10) });
                    toast.success('Status updated');
                    this.loadData();
                    eventBus.emit('task:created');
                } catch (err) {
                    toast.error('Failed to update status');
                }
            };
        });

        // Priority Select
        this.container.querySelectorAll('.table-prio-select').forEach(sel => {
            sel.onchange = async () => {
                const taskId = sel.dataset.id;
                try {
                    await api.patch(`/api/v1/tasks/${taskId}`, { priority: sel.value });
                    toast.success('Priority updated');
                } catch (err) {
                    toast.error('Failed to update priority');
                }
            };
        });

        // Start Date Change
        this.container.querySelectorAll('.table-start-date-input').forEach(input => {
            input.onchange = async () => {
                const taskId = input.dataset.id;
                try {
                    await api.patch(`/api/v1/tasks/${taskId}`, {
                        start_date: input.value ? `${input.value} 00:00:00` : null
                    });
                    toast.success('Start date updated');
                    eventBus.emit('task:created');
                } catch (err) {
                    toast.error('Failed to update start date');
                }
            };
        });

        // Due Date Change
        this.container.querySelectorAll('.table-date-input').forEach(input => {
            input.onchange = async () => {
                const taskId = input.dataset.id;
                try {
                    await api.patch(`/api/v1/tasks/${taskId}`, {
                        due_date: input.value ? `${input.value} 23:59:59` : null
                    });
                    toast.success('Due date updated');
                    eventBus.emit('task:created');
                } catch (err) {
                    toast.error('Failed to update date');
                }
            };
        });

        // Estimated Hours Change
        this.container.querySelectorAll('.table-hours-input').forEach(input => {
            input.onblur = async () => {
                const taskId = input.dataset.id;
                try {
                    await api.patch(`/api/v1/tasks/${taskId}`, {
                        estimated_hours: input.value ? parseFloat(input.value) : null
                    });
                    toast.success('Hours updated');
                    eventBus.emit('task:created');
                } catch (err) {
                    toast.error('Failed to update hours');
                }
            };
        });

        // Delete Row
        this.container.querySelectorAll('.table-del-btn').forEach(btn => {
            btn.onclick = async () => {
                const taskId = btn.dataset.id;
                if (!confirm('Delete this task?')) return;
                try {
                    await api.delete(`/api/v1/tasks/${taskId}`);
                    toast.info('Task deleted');
                    this.loadData();
                    eventBus.emit('task:created');
                } catch (err) {
                    toast.error('Failed to delete task');
                }
            };
        });
    }

    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    unmount() {
        if (typeof this.unsubscribe === 'function') {
            this.unsubscribe();
        }
        this.container.innerHTML = '';
    }
}
