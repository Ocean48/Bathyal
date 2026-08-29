/**
 * Enterprise Kanban Board Component (Macro-Tier)
 * Dynamic columns mapped to project/workspace statuses with fluid drag-and-drop.
 */
import { api } from '../core/api.js';
import { store } from '../core/store.js';
import { toast } from './Toast.js';
import { eventBus } from '../core/eventBus.js';
import { optimistic } from '../core/optimistic.js';

export class KanbanBoard {
    constructor(container) {
        this.container = container;
        this.unsubscribe = null;
        this.tasks = [];
        this.statuses = [];
        this.draggedTaskId = null;
    }

    mount() {
        this.loadData();
        this.unsubscribe = eventBus.on('task:created', () => this.loadData());
    }

    async loadData() {
        const state = store.getState();
        const wsId = state.activeWorkspaceId;

        this.container.innerHTML = `<div style="text-align: center; color: var(--text-muted); padding: 2rem;">Loading Enterprise Kanban...</div>`;

        try {
            const [tasksRes, statusesRes] = await Promise.all([
                api.get('/api/v1/tasks', { workspace_id: wsId || '', view: 'all' }),
                api.get('/api/v1/statuses')
            ]);

            this.tasks = tasksRes.data || [];
            this.statuses = statusesRes.data || [
                { id: 1, name: 'To Do', color_hex: '#94A3B8' },
                { id: 2, name: 'In Progress', color_hex: '#3B82F6' },
                { id: 3, name: 'Completed', color_hex: '#10B981' }
            ];
            this.render();
        } catch (err) {
            this.container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-title" style="color: var(--danger);">Failed to load Kanban board</div>
                    <div class="empty-state-desc">${this.escapeHtml(err.message || 'Please check connection')}</div>
                </div>
            `;
        }
    }

    render() {
        const columnsHtml = this.statuses.map(status => {
            const colTasks = this.tasks.filter(t => t.status_id == status.id);

            const cardsHtml = colTasks.map(task => {
                const prioClass = task.priority && task.priority !== 'none' ? `prio-${task.priority}` : 'prio-none';
                let dueText = '';
                if (task.due_date) {
                    const d = new Date(task.due_date);
                    dueText = d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
                }

                return `
                    <div class="kanban-card enterprise-card" draggable="true" data-id="${task.id}">
                        <div class="kanban-card-title">${this.escapeHtml(task.title)}</div>
                        ${task.description ? `<div class="kanban-card-desc">${this.escapeHtml(task.description.substring(0, 80))}${task.description.length > 80 ? '...' : ''}</div>` : ''}
                        <div class="kanban-card-meta">
                            ${task.project_name ? `<span class="badge-tag" style="background-color: ${task.project_color}20; color: ${task.project_color};">${this.escapeHtml(task.project_name)}</span>` : ''}
                            ${prioClass !== 'prio-none' ? `<span class="badge-prio ${prioClass}">${task.priority}</span>` : ''}
                            ${dueText ? `<span class="kanban-card-due">Due ${dueText}</span>` : ''}
                        </div>
                    </div>
                `;
            }).join('');

            return `
                <div class="kanban-column" data-status-id="${status.id}">
                    <div class="kanban-column-header">
                        <div class="kanban-column-title">
                            <span class="kanban-status-dot" style="background-color: ${status.color_hex || '#3b82f6'};"></span>
                            <span>${this.escapeHtml(status.name)}</span>
                        </div>
                        <span class="nav-count">${colTasks.length}</span>
                    </div>
                    <div class="kanban-card-list" data-status-id="${status.id}">
                        ${cardsHtml}
                    </div>
                </div>
            `;
        }).join('');

        this.container.innerHTML = `<div class="kanban-board-container">${columnsHtml}</div>`;
        this.bindEvents();
    }

    bindEvents() {
        this.container.querySelectorAll('.kanban-card').forEach(card => {
            card.addEventListener('dragstart', (e) => {
                this.draggedTaskId = card.dataset.id;
                card.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', card.dataset.id);
            });

            card.addEventListener('dragend', () => {
                card.classList.remove('dragging');
                this.draggedTaskId = null;
            });

            card.addEventListener('click', (e) => {
                e.stopPropagation();
                const task = this.tasks.find(t => t.id == card.dataset.id);
                if (task) {
                    eventBus.emit('task:open-drawer', task);
                }
            });
        });

        this.container.querySelectorAll('.kanban-card-list').forEach(list => {
            list.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                list.classList.add('drag-over');
            });

            list.addEventListener('dragleave', () => {
                list.classList.remove('drag-over');
            });

            list.addEventListener('drop', async (e) => {
                e.preventDefault();
                list.classList.remove('drag-over');
                const taskId = e.dataTransfer.getData('text/plain') || this.draggedTaskId;
                const newStatusId = parseInt(list.dataset.statusId, 10);

                if (!taskId || !newStatusId) return;

                const task = this.tasks.find(t => t.id == taskId);
                if (!task || task.status_id == newStatusId) return;

                const oldStatusId = task.status_id;

                await optimistic.run({
                    mutationType: 'task:status',
                    optimisticApply: () => {
                        task.status_id = newStatusId;
                        this.render();
                    },
                    rollback: () => {
                        task.status_id = oldStatusId;
                        this.render();
                    },
                    apiCall: () => api.patch(`/api/v1/tasks/${taskId}`, { status_id: newStatusId }),
                    successMessage: 'Task status updated',
                });
            });
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
