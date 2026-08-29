/**
 * Upgrade to Enterprise Project Modal Component
 * Smoothly migrates personal focus tasks to a new or existing Enterprise project.
 */
import { api } from '../core/api.js';
import { store } from '../core/store.js';
import { toast } from './Toast.js';
import { eventBus } from '../core/eventBus.js';

export class UpgradePrompt {
    constructor() {
        this.backdrop = null;
        this.isOpen = false;
    }

    open() {
        if (this.isOpen) return;
        this.isOpen = true;
        this.render();
    }

    close() {
        this.isOpen = false;
        if (this.backdrop) {
            this.backdrop.remove();
            this.backdrop = null;
        }
    }

    render() {
        const state = store.getState();
        const enterpriseWorkspaces = state.workspaces.filter(w => !w.is_personal);

        this.backdrop = document.createElement('div');
        this.backdrop.className = 'modal-backdrop';
        this.backdrop.id = 'upgrade-modal-backdrop';

        this.backdrop.innerHTML = `
            <div class="modal" style="max-width: 520px;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-subtle); padding-bottom: 0.75rem;">
                    <div>
                        <h3 style="font-size: 1.2rem; font-weight: 700;">Upgrade to Enterprise Project</h3>
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;">
                            Convert these tasks into a multi-member project with Gantt charts, custom fields, and folders.
                        </p>
                    </div>
                    <button class="btn btn-ghost btn-sm" id="btn-close-upgrade">✕</button>
                </div>

                <div style="display: flex; flex-direction: column; gap: 1rem; padding: 0.5rem 0;">
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 0.4rem;">Target Enterprise Workspace</label>
                        <select class="field-inline-select" id="upgrade-workspace-select" style="width: 100%;">
                            ${enterpriseWorkspaces.map(w => `<option value="${w.id}">${this.escapeHtml(w.name)}</option>`).join('')}
                        </select>
                    </div>

                    <div>
                        <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 0.4rem;">New Project Name</label>
                        <input type="text" class="quick-add-input" id="upgrade-project-name" placeholder="e.g. Q3 Mobile App Launch" style="border: 1px solid var(--border-strong); border-radius: 6px; padding: 0.5rem 0.75rem; width: 100%;" />
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; border-top: 1px solid var(--border-subtle); padding-top: 1rem;">
                    <button class="btn btn-secondary" id="btn-cancel-upgrade">Cancel</button>
                    <button class="btn btn-primary" id="btn-confirm-upgrade">Convert to Project</button>
                </div>
            </div>
        `;

        document.body.appendChild(this.backdrop);

        this.backdrop.addEventListener('click', (e) => {
            if (e.target === this.backdrop) this.close();
        });

        const closeBtn = this.backdrop.querySelector('#btn-close-upgrade');
        const cancelBtn = this.backdrop.querySelector('#btn-cancel-upgrade');
        const confirmBtn = this.backdrop.querySelector('#btn-confirm-upgrade');

        if (closeBtn) closeBtn.onclick = () => this.close();
        if (cancelBtn) cancelBtn.onclick = () => this.close();

        if (confirmBtn) {
            confirmBtn.onclick = async () => {
                const wsId = parseInt(this.backdrop.querySelector('#upgrade-workspace-select').value, 10);
                const projName = this.backdrop.querySelector('#upgrade-project-name').value.trim();

                if (!projName) {
                    toast.error('Please enter a project name');
                    return;
                }

                confirmBtn.disabled = true;
                confirmBtn.textContent = 'Migrating...';

                try {
                    // 1. Create Enterprise Project
                    const newProj = await api.post('/api/v1/projects', {
                        workspace_id: wsId,
                        name: projName,
                        icon: 'briefcase',
                        color_hex: '#6366F1',
                    });

                    const newProjId = newProj.data.id;

                    // 2. Assign Personal Tasks to New Project
                    const currentTasks = await api.get('/api/v1/tasks', { view: 'all' });
                    const taskIds = (currentTasks.data || []).map(t => t.id);

                    if (taskIds.length > 0) {
                        for (const tId of taskIds) {
                            await api.patch(`/api/v1/tasks/${tId}`, {
                                workspace_id: wsId,
                                project_id: newProjId,
                            });
                        }
                    }

                    toast.success(`Project "${projName}" created with ${taskIds.length} tasks!`);
                    store.setMode('enterprise');
                    eventBus.emit('mode:changed', 'enterprise');
                    this.close();
                } catch (err) {
                    toast.error(err.message || 'Failed to upgrade project');
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = 'Convert to Project';
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

export const upgradePrompt = new UpgradePrompt();
