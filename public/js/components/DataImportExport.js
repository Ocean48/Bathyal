/**
 * Data Import & Export Utility Component
 * Export project tasks to CSV/JSON and import tasks from file.
 */
import { api } from '../core/api.js';
import { store } from '../core/store.js';
import { toast } from './Toast.js';
import { eventBus } from '../core/eventBus.js';

export class DataImportExport {
    constructor() {
        this.backdrop = null;
        this.isOpen = false;
    }

    open() {
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

    async exportJson() {
        const state = store.getState();
        const res = await api.get('/api/v1/tasks', { workspace_id: state.activeWorkspaceId || '', view: 'all' });
        const tasks = res.data || [];

        const blob = new Blob([JSON.stringify(tasks, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `bathyal-tasks-export-${new Date().toISOString().split('T')[0]}.json`;
        a.click();
        URL.revokeObjectURL(url);
        toast.success(`Exported ${tasks.length} tasks to JSON`);
    }

    async exportCsv() {
        const state = store.getState();
        const res = await api.get('/api/v1/tasks', { workspace_id: state.activeWorkspaceId || '', view: 'all' });
        const tasks = res.data || [];

        if (tasks.length === 0) {
            return toast.info('No tasks to export');
        }

        const headers = ['ID', 'Title', 'Status', 'Priority', 'Start Date', 'Due Date', 'Estimated Hours', 'Project'];
        const rows = tasks.map(t => [
            t.id,
            `"${(t.title || '').replace(/"/g, '""')}"`,
            t.status_name || '',
            t.priority || 'none',
            t.start_date || '',
            t.due_date || '',
            t.estimated_hours || '',
            `"${(t.project_name || '').replace(/"/g, '""')}"`,
        ]);

        const csvContent = [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `bathyal-tasks-export-${new Date().toISOString().split('T')[0]}.csv`;
        a.click();
        URL.revokeObjectURL(url);
        toast.success(`Exported ${tasks.length} tasks to CSV`);
    }

    render() {
        this.backdrop = document.createElement('div');
        this.backdrop.className = 'modal-backdrop';
        this.backdrop.id = 'import-export-backdrop';

        this.backdrop.innerHTML = `
            <div class="modal" style="max-width: 500px;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-subtle); padding-bottom: 0.75rem;">
                    <h3 style="font-size: 1.15rem; font-weight: 700;">Import &amp; Export Center</h3>
                    <button class="btn btn-ghost btn-sm" id="btn-close-impexp">✕</button>
                </div>

                <div style="display: flex; flex-direction: column; gap: 1.25rem; padding: 0.5rem 0;">
                    <!-- Export -->
                    <div>
                        <div style="font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Export Data</div>
                        <div style="display: flex; gap: 0.5rem;">
                            <button class="btn btn-secondary btn-sm" id="btn-export-csv">Download CSV</button>
                            <button class="btn btn-secondary btn-sm" id="btn-export-json">Download JSON</button>
                        </div>
                    </div>

                    <!-- Import -->
                    <div style="border-top: 1px solid var(--border-subtle); padding-top: 1rem;">
                        <div style="font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem;">Quick Import Tasks (JSON or text per line)</div>
                        <textarea class="drawer-description-input" id="import-raw-input" placeholder="Paste task titles (one per line) or JSON task array..." style="min-height: 90px;"></textarea>
                        <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
                            <button class="btn btn-primary btn-sm" id="btn-run-import">Import Tasks</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(this.backdrop);

        this.backdrop.addEventListener('click', (e) => {
            if (e.target === this.backdrop) this.close();
        });

        const closeBtn = this.backdrop.querySelector('#btn-close-impexp');
        if (closeBtn) closeBtn.onclick = () => this.close();

        const csvBtn = this.backdrop.querySelector('#btn-export-csv');
        if (csvBtn) csvBtn.onclick = () => this.exportCsv();

        const jsonBtn = this.backdrop.querySelector('#btn-export-json');
        if (jsonBtn) jsonBtn.onclick = () => this.exportJson();

        const importBtn = this.backdrop.querySelector('#btn-run-import');
        if (importBtn) {
            importBtn.onclick = async () => {
                const text = this.backdrop.querySelector('#import-raw-input').value.trim();
                if (!text) return toast.error('No import data provided');

                importBtn.disabled = true;
                importBtn.textContent = 'Importing...';

                const state = store.getState();
                const wsId = state.activeWorkspaceId || 1;

                try {
                    if (text.startsWith('[') && text.endsWith(']')) {
                        // JSON array
                        const items = JSON.parse(text);
                        for (const item of items) {
                            await api.post('/api/v1/tasks', {
                                raw_input: item.title || item,
                                workspace_id: wsId,
                            });
                        }
                        toast.success(`Imported ${items.length} tasks`);
                    } else {
                        // Line-by-line
                        const lines = text.split('\n').map(l => l.trim()).filter(Boolean);
                        for (const line of lines) {
                            await api.post('/api/v1/tasks', {
                                raw_input: line,
                                workspace_id: wsId,
                            });
                        }
                        toast.success(`Imported ${lines.length} tasks`);
                    }

                    eventBus.emit('task:created');
                    this.close();
                } catch (err) {
                    toast.error('Import failed');
                    importBtn.disabled = false;
                    importBtn.textContent = 'Import Tasks';
                }
            };
        }
    }
}

export const dataImportExport = new DataImportExport();
