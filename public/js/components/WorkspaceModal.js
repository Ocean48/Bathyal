/**
 * Workspace & Project Management Modal Component
 */
import { api } from '../core/api.js';
import { store } from '../core/store.js';
import { toast } from './Toast.js';
import { eventBus } from '../core/eventBus.js';

export class WorkspaceModal {
    constructor() {
        this.backdrop = null;
        this.isOpen = false;
        this.activeTab = 'create_project'; // 'create_project', 'create_folder', 'create_workspace'
    }

    open(defaultTab = 'create_project') {
        this.activeTab = defaultTab;
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
        const workspaces = state.workspaces || [];
        const currentWs = workspaces.find(w => w.id === state.activeWorkspaceId) || workspaces[0] || { id: 2, name: 'Acme Corporation' };

        this.backdrop = document.createElement('div');
        this.backdrop.className = 'modal-backdrop';
        this.backdrop.id = 'workspace-mgmt-backdrop';

        this.backdrop.innerHTML = `
            <div class="modal" style="max-width: 540px;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-subtle); padding-bottom: 0.75rem;">
                    <h3 style="font-size: 1.15rem; font-weight: 700;">Workspace &amp; Project Hub</h3>
                    <button class="btn btn-ghost btn-sm" id="btn-close-ws-modal">✕</button>
                </div>

                <!-- Tabs -->
                <div class="view-tabs" style="border-bottom: 1px solid var(--border-subtle); padding-bottom: 0.5rem;">
                    <button class="view-tab ${this.activeTab === 'create_project' ? 'active' : ''}" data-tab="create_project">New Project</button>
                    <button class="view-tab ${this.activeTab === 'create_folder' ? 'active' : ''}" data-tab="create_folder">New Folder</button>
                    <button class="view-tab ${this.activeTab === 'create_workspace' ? 'active' : ''}" data-tab="create_workspace">New Workspace</button>
                </div>

                <!-- Tab Content -->
                <div id="ws-tab-content-mount" style="padding: 0.5rem 0;">
                    ${this.renderTabContent(currentWs, workspaces)}
                </div>
            </div>
        `;

        document.body.appendChild(this.backdrop);

        this.backdrop.addEventListener('click', (e) => {
            if (e.target === this.backdrop) this.close();
        });

        const closeBtn = this.backdrop.querySelector('#btn-close-ws-modal');
        if (closeBtn) closeBtn.onclick = () => this.close();

        this.backdrop.querySelectorAll('.view-tab').forEach(tab => {
            tab.onclick = () => {
                this.backdrop.querySelectorAll('.view-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                this.activeTab = tab.dataset.tab;
                const mount = this.backdrop.querySelector('#ws-tab-content-mount');
                if (mount) mount.innerHTML = this.renderTabContent(currentWs, workspaces);
                this.bindTabEvents(currentWs);
            };
        });

        this.bindTabEvents(currentWs);
    }

    renderTabContent(currentWs, workspaces) {
        if (this.activeTab === 'create_folder') {
            return `
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 0.4rem;">Folder Name</label>
                        <input type="text" class="quick-add-input" id="folder-name-input" placeholder="e.g. Design Systems, Backlog" style="border: 1px solid var(--border-strong); border-radius: 6px; padding: 0.5rem 0.75rem; width: 100%;" />
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.5rem;">
                        <button class="btn btn-primary" id="btn-submit-folder">Create Folder</button>
                    </div>
                </div>
            `;
        }

        if (this.activeTab === 'create_workspace') {
            return `
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 0.4rem;">Organization / Workspace Name</label>
                        <input type="text" class="quick-add-input" id="workspace-name-input" placeholder="e.g. Acme Global, Studio Labs" style="border: 1px solid var(--border-strong); border-radius: 6px; padding: 0.5rem 0.75rem; width: 100%;" />
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.5rem;">
                        <button class="btn btn-primary" id="btn-submit-workspace">Create Workspace</button>
                    </div>
                </div>
            `;
        }

        // Default: create_project
        return `
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <div>
                    <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 0.4rem;">Project Name</label>
                    <input type="text" class="quick-add-input" id="project-name-input" placeholder="e.g. iOS App v2, Core API" style="border: 1px solid var(--border-strong); border-radius: 6px; padding: 0.5rem 0.75rem; width: 100%;" />
                </div>
                <div>
                    <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 0.4rem;">Description (Optional)</label>
                    <textarea class="drawer-description-input" id="project-desc-input" placeholder="Brief project summary..." style="min-height: 60px;"></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.5rem;">
                    <button class="btn btn-primary" id="btn-submit-project">Create Project</button>
                </div>
            </div>
        `;
    }

    bindTabEvents(currentWs) {
        const state = store.getState();
        const wsId = currentWs ? currentWs.id : 2;

        const projBtn = this.backdrop.querySelector('#btn-submit-project');
        if (projBtn) {
            projBtn.onclick = async () => {
                const name = this.backdrop.querySelector('#project-name-input').value.trim();
                const desc = this.backdrop.querySelector('#project-desc-input').value.trim();
                if (!name) return toast.error('Project name required');

                try {
                    await api.post('/api/v1/projects', {
                        workspace_id: wsId,
                        name,
                        description: desc || null,
                        color_hex: '#6366F1'
                    });
                    toast.success(`Project "${name}" created`);
                    eventBus.emit('mode:changed', 'enterprise');
                    this.close();
                } catch (err) {
                    toast.error('Failed to create project');
                }
            };
        }

        const folderBtn = this.backdrop.querySelector('#btn-submit-folder');
        if (folderBtn) {
            folderBtn.onclick = async () => {
                const name = this.backdrop.querySelector('#folder-name-input').value.trim();
                if (!name) return toast.error('Folder name required');

                try {
                    await api.post('/api/v1/folders', {
                        workspace_id: wsId,
                        name,
                    });
                    toast.success(`Folder "${name}" created`);
                    eventBus.emit('mode:changed', 'enterprise');
                    this.close();
                } catch (err) {
                    toast.error('Failed to create folder');
                }
            };
        }

        const wsBtn = this.backdrop.querySelector('#btn-submit-workspace');
        if (wsBtn) {
            wsBtn.onclick = async () => {
                const name = this.backdrop.querySelector('#workspace-name-input').value.trim();
                if (!name) return toast.error('Workspace name required');

                try {
                    const res = await api.post('/api/v1/workspaces', { name });
                    toast.success(`Workspace "${name}" created`);
                    store.setState({ activeWorkspaceId: res.data.id });
                    eventBus.emit('mode:changed', 'enterprise');
                    this.close();
                } catch (err) {
                    toast.error('Failed to create workspace');
                }
            };
        }
    }
}

export const workspaceModal = new WorkspaceModal();
