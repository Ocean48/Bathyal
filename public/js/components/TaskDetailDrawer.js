/**
 * Sliding Task Detail Drawer & Subtasks Manager
 */
import { api } from '../core/api.js';
import { toast } from './Toast.js';
import { eventBus } from '../core/eventBus.js';
import { fieldRegistry } from '../core/fieldRegistry.js';

export class TaskDetailDrawer {
    constructor() {
        this.isOpen = false;
        this.task = null;
        this.subtasks = [];
        this.customFields = [];
        this.container = null;
        this.backdrop = null;
        this.timerInterval = null;
        this.timerSeconds = 0;
        this.activeTimerLogId = null;
    }

    open(task) {
        this.task = task;
        this.isOpen = true;
        this.render();
        this.loadDetails();
    }

    close() {
        this.isOpen = false;
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
            this.timerInterval = null;
        }
        if (this.backdrop) {
            this.backdrop.remove();
            this.backdrop = null;
        }
    }

    async loadDetails() {
        try {
            const [taskRes, customFieldsRes] = await Promise.all([
                api.get(`/api/v1/tasks/${this.task.id}`),
                api.get(`/api/v1/custom-fields`, { workspace_id: this.task.workspace_id, project_id: this.task.project_id || '' })
            ]);

            this.task = taskRes.data || this.task;
            this.customFields = customFieldsRes.data || [];
            
            // Load subtasks
            const subtasksRes = await api.get('/api/v1/tasks', { workspace_id: this.task.workspace_id, parent_id: this.task.id });
            this.subtasks = subtasksRes.data || [];

            this.renderDrawerContent();
        } catch (err) {
            toast.error('Failed to load full task details');
        }
    }

    render() {
        if (this.backdrop) this.backdrop.remove();

        this.backdrop = document.createElement('div');
        this.backdrop.className = 'drawer-backdrop';
        this.backdrop.id = 'task-detail-backdrop';

        this.backdrop.innerHTML = `
            <div class="task-drawer">
                <div class="drawer-header">
                    <div class="drawer-header-left">
                        <span class="badge-tag" id="drawer-task-id">#${this.task.id}</span>
                        <span class="drawer-breadcrumb">${this.task.project_name ? this.escapeHtml(this.task.project_name) : 'Personal Task'}</span>
                    </div>
                    <div class="drawer-header-right">
                        <button class="btn btn-icon btn-ghost" id="drawer-close-btn" title="Close Drawer (Esc)">✕</button>
                    </div>
                </div>
                <div class="drawer-body" id="drawer-body-mount">
                    <div style="padding: 2rem; text-align: center; color: var(--text-muted);">Loading details...</div>
                </div>
            </div>
        `;

        document.body.appendChild(this.backdrop);

        this.backdrop.addEventListener('click', (e) => {
            if (e.target === this.backdrop) this.close();
        });

        const closeBtn = this.backdrop.querySelector('#drawer-close-btn');
        if (closeBtn) closeBtn.onclick = () => this.close();
    }

    renderDrawerContent() {
        const mount = this.backdrop.querySelector('#drawer-body-mount');
        if (!mount) return;

        const isCompleted = this.task.status_type === 'completed' || this.task.status_id == 3;
        const totalSub = this.subtasks.length;
        const completedSub = this.subtasks.filter(s => s.status_type === 'completed' || s.status_id == 3).length;
        const progressPct = totalSub > 0 ? Math.round((completedSub / totalSub) * 100) : 0;

        mount.innerHTML = `
            <!-- Title & Status -->
            <div class="drawer-section">
                <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                    <button class="task-checkbox ${isCompleted ? 'completed' : ''}" id="drawer-toggle-complete" style="margin-top: 4px;">
                        ${isCompleted ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>' : ''}
                    </button>
                    <input type="text" class="drawer-title-input" id="drawer-task-title" value="${this.escapeHtml(this.task.title)}" placeholder="Task title..." />
                </div>
            </div>

            <!-- Meta Attributes Grid -->
            <div class="drawer-meta-grid">
                <div class="drawer-meta-row">
                    <span class="drawer-meta-label">Status</span>
                    <select class="field-inline-select" id="drawer-status-select">
                        <option value="1" ${this.task.status_id == 1 ? 'selected' : ''}>To Do</option>
                        <option value="2" ${this.task.status_id == 2 ? 'selected' : ''}>In Progress</option>
                        <option value="3" ${this.task.status_id == 3 ? 'selected' : ''}>Completed</option>
                    </select>
                </div>
                <div class="drawer-meta-row">
                    <span class="drawer-meta-label">Priority</span>
                    <select class="field-inline-select" id="drawer-priority-select">
                        <option value="none" ${this.task.priority === 'none' ? 'selected' : ''}>None</option>
                        <option value="low" ${this.task.priority === 'low' ? 'selected' : ''}>Low</option>
                        <option value="medium" ${this.task.priority === 'medium' ? 'selected' : ''}>Medium</option>
                        <option value="high" ${this.task.priority === 'high' ? 'selected' : ''}>High</option>
                        <option value="urgent" ${this.task.priority === 'urgent' ? 'selected' : ''}>Urgent</option>
                    </select>
                </div>
                <div class="drawer-meta-row">
                    <span class="drawer-meta-label">Due Date</span>
                    <input type="date" class="field-inline-input" id="drawer-due-date" value="${this.task.due_date ? this.task.due_date.split(' ')[0] : ''}" />
                </div>
                <div class="drawer-meta-row">
                    <span class="drawer-meta-label">Estimate (hrs)</span>
                    <input type="number" step="0.5" class="field-inline-input" id="drawer-estimate-hours" value="${this.task.estimated_hours || ''}" placeholder="0.0" style="width: 90px;" />
                </div>
            </div>

            <!-- Description -->
            <div class="drawer-section">
                <div class="drawer-section-title">Description</div>
                <textarea class="drawer-description-input" id="drawer-description" placeholder="Add detailed notes or requirements...">${this.escapeHtml(this.task.description || '')}</textarea>
            </div>

            <!-- Subtask Checklist -->
            <div class="drawer-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <div class="drawer-section-title" style="margin-bottom: 0;">Subtasks (${completedSub}/${totalSub})</div>
                    <span style="font-size: 0.75rem; color: var(--text-muted);">${progressPct}%</span>
                </div>
                <div class="progress-bar-track">
                    <div class="progress-bar-fill" style="width: ${progressPct}%;"></div>
                </div>
                <div class="subtask-list" id="drawer-subtask-list">
                    ${this.subtasks.map(sub => `
                        <div class="subtask-item ${sub.status_id == 3 ? 'completed' : ''}" data-id="${sub.id}">
                            <input type="checkbox" class="subtask-chk" data-id="${sub.id}" ${sub.status_id == 3 ? 'checked' : ''} />
                            <span class="subtask-title">${this.escapeHtml(sub.title)}</span>
                            <button class="btn-icon btn-ghost btn-sm subtask-del-btn" data-id="${sub.id}">✕</button>
                        </div>
                    `).join('')}
                </div>
                <div class="subtask-add-row">
                    <input type="text" class="quick-add-input" id="drawer-new-subtask-input" placeholder="Add subtask and press Enter..." style="font-size: 0.85rem;" />
                </div>
            </div>

            <!-- Time Tracker Widget -->
            <div class="drawer-section">
                <div class="drawer-section-title">Time Tracking</div>
                <div class="time-tracker-box">
                    <div class="timer-display" id="drawer-timer-display">00:00:00</div>
                    <button class="btn btn-sm btn-primary" id="drawer-timer-toggle">Start Timer</button>
                </div>
            </div>
        `;

        this.bindDrawerEvents();
    }

    bindDrawerEvents() {
        const titleInput = this.backdrop.querySelector('#drawer-task-title');
        const descInput = this.backdrop.querySelector('#drawer-description');
        const statusSelect = this.backdrop.querySelector('#drawer-status-select');
        const prioSelect = this.backdrop.querySelector('#drawer-priority-select');
        const dueDateInput = this.backdrop.querySelector('#drawer-due-date');
        const estimateInput = this.backdrop.querySelector('#drawer-estimate-hours');
        const toggleCompleteBtn = this.backdrop.querySelector('#drawer-toggle-complete');
        const newSubtaskInput = this.backdrop.querySelector('#drawer-new-subtask-input');

        const autoSave = async () => {
            const updates = {
                title: titleInput.value.trim() || this.task.title,
                description: descInput.value.trim() || null,
                status_id: parseInt(statusSelect.value, 10),
                priority: prioSelect.value,
                due_date: dueDateInput.value ? `${dueDateInput.value} 23:59:59` : null,
                estimated_hours: estimateInput.value ? parseFloat(estimateInput.value) : null,
            };

            try {
                const res = await api.patch(`/api/v1/tasks/${this.task.id}`, updates);
                this.task = res.data;
                toast.success('Task details saved');
                eventBus.emit('task:created', this.task);
            } catch (err) {
                toast.error('Failed to save task updates');
            }
        };

        titleInput.onblur = autoSave;
        descInput.onblur = autoSave;
        statusSelect.onchange = autoSave;
        prioSelect.onchange = autoSave;
        dueDateInput.onchange = autoSave;
        estimateInput.onblur = autoSave;

        if (toggleCompleteBtn) {
            toggleCompleteBtn.onclick = async () => {
                const isCompleted = this.task.status_type === 'completed' || this.task.status_id == 3;
                const newStatus = isCompleted ? 1 : 3;
                statusSelect.value = String(newStatus);
                await autoSave();
                this.renderDrawerContent();
            };
        }

        // Subtask Create
        if (newSubtaskInput) {
            newSubtaskInput.onkeydown = async (e) => {
                if (e.key === 'Enter' && newSubtaskInput.value.trim()) {
                    const text = newSubtaskInput.value.trim();
                    newSubtaskInput.value = '';
                    try {
                        await api.post('/api/v1/tasks', {
                            title: text,
                            workspace_id: this.task.workspace_id,
                            project_id: this.task.project_id,
                            parent_id: this.task.id,
                        });
                        toast.success('Subtask added');
                        this.loadDetails();
                    } catch (err) {
                        toast.error('Failed to add subtask');
                    }
                }
            };
        }

        // Subtask Toggle
        this.backdrop.querySelectorAll('.subtask-chk').forEach(chk => {
            chk.onchange = async () => {
                const subId = chk.dataset.id;
                const newStatus = chk.checked ? 3 : 1;
                try {
                    await api.patch(`/api/v1/tasks/${subId}`, { status_id: newStatus });
                    this.loadDetails();
                } catch (err) {
                    toast.error('Failed to update subtask');
                }
            };
        });

        // Subtask Delete
        this.backdrop.querySelectorAll('.subtask-del-btn').forEach(btn => {
            btn.onclick = async () => {
                const subId = btn.dataset.id;
                try {
                    await api.delete(`/api/v1/tasks/${subId}`);
                    toast.info('Subtask deleted');
                    this.loadDetails();
                } catch (err) {
                    toast.error('Failed to delete subtask');
                }
            };
        });

        // Time Tracker Toggle
        const timerBtn = this.backdrop.querySelector('#drawer-timer-toggle');
        const timerDisplay = this.backdrop.querySelector('#drawer-timer-display');

        if (timerBtn) {
            timerBtn.onclick = async () => {
                if (this.timerInterval) {
                    // Stop timer
                    clearInterval(this.timerInterval);
                    this.timerInterval = null;
                    timerBtn.textContent = 'Start Timer';
                    timerBtn.className = 'btn btn-sm btn-primary';

                    if (this.activeTimerLogId) {
                        await api.post(`/api/v1/time-logs/${this.activeTimerLogId}/stop`);
                        toast.success('Time logged successfully');
                        this.activeTimerLogId = null;
                    }
                } else {
                    // Start timer
                    try {
                        const res = await api.post('/api/v1/time-logs', { task_id: this.task.id });
                        this.activeTimerLogId = res.data?.id;
                        this.timerSeconds = 0;
                        timerBtn.textContent = 'Stop Timer';
                        timerBtn.className = 'btn btn-sm btn-secondary';

                        this.timerInterval = setInterval(() => {
                            this.timerSeconds++;
                            const hrs = String(Math.floor(this.timerSeconds / 3600)).padStart(2, '0');
                            const mins = String(Math.floor((this.timerSeconds % 3600) / 60)).padStart(2, '0');
                            const secs = String(this.timerSeconds % 60).padStart(2, '0');
                            if (timerDisplay) timerDisplay.textContent = `${hrs}:${mins}:${secs}`;
                        }, 1000);
                    } catch (err) {
                        toast.error('Failed to start timer');
                    }
                }
            };
        }
    }

    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}

export const taskDetailDrawer = new TaskDetailDrawer();
