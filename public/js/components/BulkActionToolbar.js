/**
 * Floating Bulk Actions Toolbar Component
 * Appears when multiple tasks are selected and performs batch updates.
 */
import { api } from '../core/api.js';
import { toast } from './Toast.js';
import { eventBus } from '../core/eventBus.js';

export class BulkActionToolbar {
    constructor() {
        this.selectedTaskIds = new Set();
        this.container = null;
        this.init();
    }

    init() {
        eventBus.on('tasks:selection-changed', (taskIds) => {
            this.selectedTaskIds = new Set(taskIds);
            this.updateVisibility();
        });
    }

    updateVisibility() {
        if (this.selectedTaskIds.size > 0) {
            this.render();
        } else {
            this.remove();
        }
    }

    render() {
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'bulk-action-toolbar';
            this.container.id = 'bulk-action-toolbar-mount';
            document.body.appendChild(this.container);
        }

        const count = this.selectedTaskIds.size;

        this.container.innerHTML = `
            <div class="bulk-toolbar-inner">
                <span class="bulk-count-badge">${count} selected</span>

                <div class="bulk-actions-group">
                    <!-- Status -->
                    <select class="field-inline-select bulk-select" id="bulk-status-select">
                        <option value="">Set Status...</option>
                        <option value="1">To Do</option>
                        <option value="2">In Progress</option>
                        <option value="3">Completed</option>
                    </select>

                    <!-- Priority -->
                    <select class="field-inline-select bulk-select" id="bulk-priority-select">
                        <option value="">Set Priority...</option>
                        <option value="urgent">Urgent</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                        <option value="none">None</option>
                    </select>

                    <!-- Delete -->
                    <button class="btn btn-sm btn-ghost" id="btn-bulk-delete" style="color: var(--danger);" title="Delete selected tasks">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        <span>Delete</span>
                    </button>
                </div>

                <button class="btn-icon btn-ghost btn-sm" id="btn-bulk-clear" title="Deselect All">✕</button>
            </div>
        `;

        this.bindEvents();
    }

    bindEvents() {
        if (!this.container) return;

        const statusSelect = this.container.querySelector('#bulk-status-select');
        const prioSelect = this.container.querySelector('#bulk-priority-select');
        const deleteBtn = this.container.querySelector('#btn-bulk-delete');
        const clearBtn = this.container.querySelector('#btn-bulk-clear');

        if (statusSelect) {
            statusSelect.onchange = async () => {
                const statusId = statusSelect.value;
                if (!statusId) return;

                try {
                    await api.post('/api/v1/tasks/batch', {
                        task_ids: Array.from(this.selectedTaskIds),
                        action: 'set_status',
                        status_id: parseInt(statusId, 10),
                    });
                    toast.success(`Updated status for ${this.selectedTaskIds.size} tasks`);
                    this.clear();
                    eventBus.emit('task:created');
                } catch (err) {
                    toast.error('Batch status update failed');
                }
            };
        }

        if (prioSelect) {
            prioSelect.onchange = async () => {
                const priority = prioSelect.value;
                if (!priority) return;

                try {
                    await api.post('/api/v1/tasks/batch', {
                        task_ids: Array.from(this.selectedTaskIds),
                        action: 'set_priority',
                        priority,
                    });
                    toast.success(`Updated priority for ${this.selectedTaskIds.size} tasks`);
                    this.clear();
                    eventBus.emit('task:created');
                } catch (err) {
                    toast.error('Batch priority update failed');
                }
            };
        }

        if (deleteBtn) {
            deleteBtn.onclick = async () => {
                if (!confirm(`Delete ${this.selectedTaskIds.size} selected tasks?`)) return;

                try {
                    await api.post('/api/v1/tasks/batch', {
                        task_ids: Array.from(this.selectedTaskIds),
                        action: 'delete',
                    });
                    toast.info(`Deleted ${this.selectedTaskIds.size} tasks`);
                    this.clear();
                    eventBus.emit('task:created');
                } catch (err) {
                    toast.error('Batch delete failed');
                }
            };
        }

        if (clearBtn) {
            clearBtn.onclick = () => this.clear();
        }
    }

    clear() {
        this.selectedTaskIds.clear();
        eventBus.emit('tasks:selection-cleared');
        this.remove();
    }

    remove() {
        if (this.container) {
            this.container.remove();
            this.container = null;
        }
    }
}

export const bulkActionToolbar = new BulkActionToolbar();
