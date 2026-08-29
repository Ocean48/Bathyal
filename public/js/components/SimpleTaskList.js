/**
 * Simple Task List Component for Focus Mode
 */
import { api } from '../core/api.js';
import { store } from '../core/store.js';
import { toast } from './Toast.js';
import { eventBus } from '../core/eventBus.js';
import { optimistic } from '../core/optimistic.js';
import { fieldRegistry } from '../core/fieldRegistry.js';
import { renderPriorityBadge } from '../core/priorities.js';

export class SimpleTaskList {
    constructor(container) {
        this.container = container;
        this.unsubscribe = null;
        this.tasks = [];
    }

    mount() {
        this.loadTasks();
        this.unsubscribe = eventBus.on('task:created', () => this.loadTasks());
    }

    async loadTasks() {
        const state = store.getState();
        const view = state.activeView || 'inbox';
        const wsId = state.activeWorkspaceId;

        this.container.innerHTML = `<div style="text-align: center; color: var(--text-muted); padding: 2rem;">Loading tasks...</div>`;

        try {
            const params = { view };
            if (wsId) params.workspace_id = wsId;

            const res = await api.get('/api/v1/tasks', params);
            this.tasks = res.data || [];
            this.render();
        } catch (err) {
            this.container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-title" style="color: var(--danger);">Failed to load tasks</div>
                    <div class="empty-state-desc">${this.escapeHtml(err.message || 'Please check your connection')}</div>
                    <button class="btn btn-secondary btn-sm" id="btn-retry-tasks">Retry</button>
                </div>
            `;
            const retryBtn = this.container.querySelector('#btn-retry-tasks');
            if (retryBtn) retryBtn.onclick = () => this.loadTasks();
        }
    }

    render() {
        if (this.tasks.length === 0) {
            this.container.innerHTML = `
                <div class="empty-state">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color: var(--text-subtle);">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <div class="empty-state-title">No tasks found</div>
                    <div class="empty-state-desc">You are all caught up! Use the quick add bar above to capture your next thought.</div>
                </div>
            `;
            return;
        }

        const itemsHtml = this.tasks.map(task => {
            const isCompleted = task.status_type === 'completed' || task.status_id == 3;

            let dueText = '';
            if (task.due_date) {
                const d = new Date(task.due_date);
                dueText = d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
            }

            return `
                <div class="task-item ${isCompleted ? 'completed' : ''}" data-id="${task.id}">
                    <div class="task-item-left">
                        <button class="task-checkbox" data-id="${task.id}" title="${isCompleted ? 'Mark incomplete' : 'Mark complete'}">
                            ${isCompleted ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>' : ''}
                        </button>
                        <div class="task-info">
                            <span class="task-title" data-id="${task.id}">${this.escapeHtml(task.title)}</span>
                            <div class="task-meta">
                                ${task.project_name ? `<span class="badge-tag" style="background-color: ${task.project_color}20; color: ${task.project_color};">${this.escapeHtml(task.project_name)}</span>` : ''}
                                ${renderPriorityBadge(task.priority)}
                                ${dueText ? `<span>Due ${dueText}</span>` : ''}
                            </div>
                        </div>
                    </div>
                    <div class="task-item-right">
                        <button class="btn-icon btn-ghost btn-delete-task" data-id="${task.id}" title="Delete task">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            `;
        }).join('');

        this.container.innerHTML = `<div class="task-list">${itemsHtml}</div>`;
        this.bindItemEvents();
    }

    bindItemEvents() {
        // Toggle complete checkbox
        this.container.querySelectorAll('.task-checkbox').forEach(btn => {
            btn.onclick = async () => {
                const taskId = btn.dataset.id;
                const task = this.tasks.find(t => t.id == taskId);
                if (!task) return;

                const isCompleted = task.status_type === 'completed' || task.status_id == 3;
                const newStatusId = isCompleted ? 1 : 3;

                try {
                    await api.patch(`/api/v1/tasks/${taskId}`, { status_id: newStatusId });
                    toast.success(isCompleted ? 'Task marked active' : 'Task completed');
                    this.loadTasks();
                } catch (err) {
                    toast.error(err.message || 'Failed to update status');
                }
            };
        });

        // Inline Title Editing
        this.container.querySelectorAll('.task-title').forEach(span => {
            span.onclick = () => {
                const taskId = span.dataset.id;
                const currentTitle = span.textContent;
                const input = document.createElement('input');
                input.type = 'text';
                input.value = currentTitle;
                input.className = 'quick-add-input';
                input.style.borderBottom = '1px solid var(--primary)';
                span.replaceWith(input);
                input.focus();

                const save = async () => {
                    const updatedTitle = input.value.trim();
                    if (updatedTitle && updatedTitle !== currentTitle) {
                        try {
                            await api.patch(`/api/v1/tasks/${taskId}`, { title: updatedTitle });
                            toast.success('Task title updated');
                        } catch (err) {
                            toast.error(err.message || 'Failed to update title');
                        }
                    }
                    this.loadTasks();
                };

                input.onblur = save;
                input.onkeydown = (e) => {
                    if (e.key === 'Enter') save();
                    if (e.key === 'Escape') this.loadTasks();
                };
            };
        });

        // Click task meta or info to open side drawer
        this.container.querySelectorAll('.task-meta').forEach(meta => {
            meta.style.cursor = 'pointer';
            meta.onclick = (e) => {
                e.stopPropagation();
                const item = meta.closest('.task-item');
                if (item) {
                    const task = this.tasks.find(t => t.id == item.dataset.id);
                    if (task) eventBus.emit('task:open-drawer', task);
                }
            };
        });

        // Delete Task
        this.container.querySelectorAll('.btn-delete-task').forEach(btn => {
            btn.onclick = async () => {
                const taskId = btn.dataset.id;
                if (!confirm('Are you sure you want to delete this task?')) return;

                try {
                    await api.delete(`/api/v1/tasks/${taskId}`);
                    toast.info('Task deleted');
                    this.loadTasks();
                } catch (err) {
                    toast.error(err.message || 'Failed to delete task');
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
