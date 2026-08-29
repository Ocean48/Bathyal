/**
 * Simple Kanban Board Component (Focus / Personal Mode)
 * Streamlined 3-column drag-and-drop board (To Do, In Progress, Completed).
 */
import { api } from '../core/api.js';
import { store } from '../core/store.js';
import { toast } from './Toast.js';
import { eventBus } from '../core/eventBus.js';
import { optimistic } from '../core/optimistic.js';

export class SimpleKanban {
    constructor(container) {
        this.container = container;
        this.unsubscribe = null;
        this.tasks = [];
        this.draggedTaskId = null;
    }

    mount() {
        this.loadTasks();
        this.unsubscribe = eventBus.on('task:created', () => this.loadTasks());
    }

    async loadTasks() {
        const state = store.getState();
        const wsId = state.activeWorkspaceId;

        this.container.innerHTML = `<div style="text-align: center; color: var(--text-muted); padding: 2rem;">Loading Kanban board...</div>`;

        try {
            const params = { view: 'all' };
            if (wsId) params.workspace_id = wsId;

            const res = await api.get('/api/v1/tasks', params);
            this.tasks = res.data || [];
            this.render();
        } catch (err) {
            this.container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-title" style="color: var(--danger);">Failed to load Kanban board</div>
                    <div class="empty-state-desc">${this.escapeHtml(err.message || 'Please check your connection')}</div>
                    <button class="btn btn-secondary btn-sm" id="btn-retry-kanban">Retry</button>
                </div>
            `;
            const retryBtn = this.container.querySelector('#btn-retry-kanban');
            if (retryBtn) retryBtn.onclick = () => this.loadTasks();
        }
    }

    render() {
        const columns = [
            { id: 1, name: 'To Do', color: '#94a3b8', type: 'todo' },
            { id: 2, name: 'In Progress', color: '#3b82f6', type: 'in_progress' },
            { id: 3, name: 'Completed', color: '#10b981', type: 'completed' },
        ];

        const columnsHtml = columns.map(col => {
            const colTasks = this.tasks.filter(t => {
                if (col.id === 3) return t.status_type === 'completed' || t.status_id == 3;
                if (col.id === 2) return t.status_type === 'in_progress' || t.status_id == 2;
                return !t.status_type || t.status_type === 'todo' || t.status_id == 1;
            });

            const cardsHtml = colTasks.map(task => {
                const prioClass = task.priority && task.priority !== 'none' ? `prio-${task.priority}` : 'prio-none';
                let dueText = '';
                if (task.due_date) {
                    const d = new Date(task.due_date);
                    dueText = d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
                }

                return `
                    <div class="kanban-card" draggable="true" data-id="${task.id}">
                        <div class="kanban-card-title">${this.escapeHtml(task.title)}</div>
                        <div class="kanban-card-meta">
                            ${task.project_name ? `<span class="badge-tag" style="background-color: ${task.project_color}20; color: ${task.project_color};">${this.escapeHtml(task.project_name)}</span>` : ''}
                            ${prioClass !== 'prio-none' ? `<span class="badge-prio ${prioClass}">${task.priority}</span>` : ''}
                            ${dueText ? `<span class="kanban-card-due">Due ${dueText}</span>` : ''}
                        </div>
                    </div>
                `;
            }).join('');

            return `
                <div class="kanban-column" data-status-id="${col.id}">
                    <div class="kanban-column-header">
                        <div class="kanban-column-title">
                            <span class="kanban-status-dot" style="background-color: ${col.color};"></span>
                            <span>${this.escapeHtml(col.name)}</span>
                        </div>
                        <span class="nav-count">${colTasks.length}</span>
                    </div>
                    <div class="kanban-card-list" data-status-id="${col.id}">
                        ${cardsHtml}
                    </div>
                </div>
            `;
        }).join('');

        this.container.innerHTML = `<div class="kanban-board-container">${columnsHtml}</div>`;
        this.bindEvents();
    }

    bindEvents() {
        // Drag & Drop
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
                        task.status_type = newStatusId === 3 ? 'completed' : (newStatusId === 2 ? 'in_progress' : 'todo');
                        this.render();
                    },
                    rollback: () => {
                        task.status_id = oldStatusId;
                        this.render();
                    },
                    apiCall: () => api.patch(`/api/v1/tasks/${taskId}`, { status_id: newStatusId }),
                    successMessage: `Task moved to ${newStatusId === 3 ? 'Completed' : (newStatusId === 2 ? 'In Progress' : 'To Do')}`,
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
