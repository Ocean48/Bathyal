/**
 * View Switcher Component
 * Switches between List, Kanban, Table, Gantt, and Docs views.
 */
import { store } from '../core/store.js';
import { eventBus } from '../core/eventBus.js';

export class ViewSwitcher {
    constructor(container) {
        this.container = container;
        this.currentViewType = 'list'; // 'list', 'kanban', 'table', 'gantt', 'docs'
    }

    mount() {
        this.render();
        this.bindEvents();
    }

    render() {
        const isEnterprise = store.getState().activeMode === 'enterprise';

        this.container.innerHTML = `
            <div class="view-switcher-bar">
                <div class="view-tabs">
                    <button class="view-tab ${this.currentViewType === 'list' ? 'active' : ''}" data-view-type="list" title="Simple Task List">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                        <span>List</span>
                    </button>
                    <button class="view-tab ${this.currentViewType === 'kanban' ? 'active' : ''}" data-view-type="kanban" title="Kanban Board">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line><line x1="15" y1="3" x2="15" y2="21"></line></svg>
                        <span>Kanban</span>
                    </button>
                    <button class="view-tab ${this.currentViewType === 'table' ? 'active' : ''}" data-view-type="table" title="Spreadsheet Grid">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h18v18H3zM3 9h18M3 15h18M9 3v18M15 3v18"/></svg>
                        <span>Table</span>
                    </button>
                    <button class="view-tab ${this.currentViewType === 'gantt' ? 'active' : ''}" data-view-type="gantt" title="Gantt Timeline">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="6" x2="14" y2="6"></line><line x1="10" y1="12" x2="20" y2="12"></line><line x1="6" y1="18" x2="16" y2="18"></line></svg>
                        <span>Gantt</span>
                    </button>
                    <button class="view-tab ${this.currentViewType === 'docs' ? 'active' : ''}" data-view-type="docs" title="Document Specs">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                        <span>Docs</span>
                    </button>
                </div>

                <div class="view-switcher-actions">
                    <button class="btn btn-ghost btn-sm" id="btn-open-impexp" title="Import / Export Data">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        <span>Import/Export</span>
                    </button>
                    ${isEnterprise ? `
                        <button class="btn btn-primary btn-sm" id="btn-open-ws-modal" title="Manage Projects & Folders">
                            <span>+ Project / Folder</span>
                        </button>
                    ` : `
                        <button class="btn btn-ghost btn-sm" id="btn-upgrade-view" title="Upgrade this list to an Enterprise Project">
                            <span>🚀 Upgrade to Project</span>
                        </button>
                    `}
                </div>
            </div>
        `;
    }

    bindEvents() {
        this.container.querySelectorAll('.view-tab').forEach(tab => {
            tab.onclick = () => {
                this.container.querySelectorAll('.view-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                this.currentViewType = tab.dataset.viewType;
                eventBus.emit('view-type:changed', this.currentViewType);
            };
        });

        const upgradeBtn = this.container.querySelector('#btn-upgrade-view');
        if (upgradeBtn) {
            upgradeBtn.onclick = () => {
                eventBus.emit('prompt:upgrade');
            };
        }

        const impexpBtn = this.container.querySelector('#btn-open-impexp');
        if (impexpBtn) {
            impexpBtn.onclick = () => {
                eventBus.emit('prompt:impexp');
            };
        }

        const wsModalBtn = this.container.querySelector('#btn-open-ws-modal');
        if (wsModalBtn) {
            wsModalBtn.onclick = () => {
                eventBus.emit('prompt:workspace-modal');
            };
        }
    }

    unmount() {
        this.container.innerHTML = '';
    }
}
