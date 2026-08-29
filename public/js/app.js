/**
 * Bathyal Frontend Engine - Component Orchestration
 */
import { api } from './core/api.js';
import { store } from './core/store.js';
import { eventBus } from './core/eventBus.js';
import { componentRegistry } from './core/ComponentRegistry.js';
import { QuickAddBar } from './components/QuickAddBar.js';
import { SimpleTaskList } from './components/SimpleTaskList.js';
import { SimpleKanban } from './components/SimpleKanban.js';
import { TaskTable } from './components/TaskTable.js';
import { KanbanBoard } from './components/KanbanBoard.js';
import { GanttChart } from './components/GanttChart.js';
import { DocEditor } from './components/DocEditor.js';
import { ViewSwitcher } from './components/ViewSwitcher.js';
import { FilterBar } from './components/FilterBar.js';
import { taskDetailDrawer } from './components/TaskDetailDrawer.js';
import { upgradePrompt } from './components/UpgradePrompt.js';
import { workspaceModal } from './components/WorkspaceModal.js';
import { dataImportExport } from './components/DataImportExport.js';
import { bulkActionToolbar } from './components/BulkActionToolbar.js';
import { toast } from './components/Toast.js';
import { themeManager } from './components/ThemeManager.js';
import { commandPalette } from './components/CommandPalette.js';
import { keyboardShortcuts } from './components/KeyboardShortcuts.js';

// Register core UI widgets
componentRegistry.register('QuickAddBar', QuickAddBar);
componentRegistry.register('SimpleTaskList', SimpleTaskList);
componentRegistry.register('SimpleKanban', SimpleKanban);
componentRegistry.register('TaskTable', TaskTable);
componentRegistry.register('KanbanBoard', KanbanBoard);
componentRegistry.register('GanttChart', GanttChart);
componentRegistry.register('DocEditor', DocEditor);
componentRegistry.register('ViewSwitcher', ViewSwitcher);
componentRegistry.register('FilterBar', FilterBar);

let currentViewType = 'list'; // 'list', 'kanban', 'table', 'gantt', 'docs'

document.addEventListener('DOMContentLoaded', async () => {
    initThemeControls();
    await initAuth();
    initSystemHealth();
    initModeSwitcher();
    initNavigation();
    initHeaderActions();
    mountComponents();
});

function initThemeControls() {
    const themeBtn = document.getElementById('theme-toggle-btn');
    if (themeBtn) {
        themeBtn.onclick = () => {
            themeManager.toggleMode();
        };
    }

    const paletteDots = document.querySelectorAll('.palette-dot');
    paletteDots.forEach(dot => {
        dot.onclick = () => {
            paletteDots.forEach(d => d.classList.remove('active'));
            dot.classList.add('active');
            const pal = dot.dataset.palette;
            themeManager.setPalette(pal);
            toast.info(`Accent theme updated to ${pal}`);
        };
    });
}

function initHeaderActions() {
    const cmdBtn = document.getElementById('cmd-palette-btn');
    if (cmdBtn) {
        cmdBtn.onclick = () => commandPalette.open();
    }

    const helpBtn = document.getElementById('shortcuts-help-btn');
    if (helpBtn) {
        helpBtn.onclick = () => keyboardShortcuts.toggleCheatSheet();
    }

    // View type changed from switcher
    eventBus.on('view-type:changed', (type) => {
        currentViewType = type;
        mountTaskView();
    });

    // Open Task Detail Drawer
    eventBus.on('task:open-drawer', (task) => {
        taskDetailDrawer.open(task);
    });

    // Prompt Upgrade to Enterprise
    eventBus.on('prompt:upgrade', () => {
        upgradePrompt.open();
    });

    // Prompt Import / Export
    eventBus.on('prompt:impexp', () => {
        dataImportExport.open();
    });

    // Prompt Workspace / Project Hub
    eventBus.on('prompt:workspace-modal', () => {
        workspaceModal.open();
    });

    // Listen to global events from command palette or shortcuts
    eventBus.on('navigate:view', (view) => {
        const links = document.querySelectorAll('#simple-sidebar-views .nav-link');
        links.forEach(l => {
            if (l.dataset.view === view) {
                l.classList.add('active');
            } else {
                l.classList.remove('active');
            }
        });

        const titleEl = document.getElementById('active-view-title');
        if (titleEl) titleEl.textContent = view.charAt(0).toUpperCase() + view.slice(1);
        mountTaskView();
    });

    eventBus.on('mode:changed', (mode) => {
        const toggleBtn = document.getElementById('mode-toggle-btn');
        const modeBadge = document.getElementById('mode-badge');
        const simpleViews = document.getElementById('simple-sidebar-views');
        const enterpriseViews = document.getElementById('enterprise-sidebar-views');

        if (mode === 'enterprise') {
            if (modeBadge) {
                modeBadge.textContent = 'Enterprise Mode';
                modeBadge.style.color = 'var(--info)';
                modeBadge.style.backgroundColor = 'var(--info-light)';
                modeBadge.style.borderColor = 'rgba(56, 189, 248, 0.4)';
            }
            if (toggleBtn) toggleBtn.textContent = 'Switch to Simple';
            if (simpleViews) simpleViews.style.display = 'none';
            if (enterpriseViews) enterpriseViews.style.display = 'flex';
            loadEnterpriseTree();
        } else {
            if (modeBadge) {
                modeBadge.textContent = 'Simple Mode';
                modeBadge.style.color = 'var(--primary)';
                modeBadge.style.backgroundColor = 'var(--primary-light)';
                modeBadge.style.borderColor = 'rgba(99, 102, 241, 0.3)';
            }
            if (toggleBtn) toggleBtn.textContent = 'Switch to Enterprise';
            if (simpleViews) simpleViews.style.display = 'flex';
            if (enterpriseViews) enterpriseViews.style.display = 'none';
        }

        mountTaskView();
    });
}


async function initAuth() {
    let token = api.getToken();

    if (!token) {
        try {
            const loginRes = await api.post('/api/v1/auth/login', {
                email: 'demo@bathyal.local',
                password: 'password123',
            });

            if (loginRes.data && loginRes.data.token) {
                api.setToken(loginRes.data.token);
                store.setState({
                    currentUser: loginRes.data.user,
                    workspaces: loginRes.data.workspaces || [],
                    activeWorkspaceId: loginRes.data.workspaces?.[0]?.id || 1,
                });
            }
        } catch (err) {
            console.warn('Auto-login error:', err);
        }
    } else {
        try {
            const meRes = await api.get('/api/v1/auth/me');
            if (meRes.data && meRes.data.user) {
                store.setState({
                    currentUser: meRes.data.user,
                    workspaces: meRes.data.workspaces || [],
                    activeWorkspaceId: meRes.data.workspaces?.[0]?.id || 1,
                });
            }
        } catch (err) {
            api.setToken('');
            await initAuth();
        }
    }
}

async function initSystemHealth() {
    const apiBadge = document.getElementById('api-status-badge');
    const aiBadge = document.getElementById('ai-status-badge');

    try {
        const res = await api.get('/api/v1/health');
        if (res.data && res.data.components) {
            const { database, ai_service } = res.data.components;
            if (apiBadge) {
                apiBadge.textContent = database === 'connected' ? 'Online' : 'Degraded';
                apiBadge.style.color = database === 'connected' ? 'var(--success)' : 'var(--danger)';
            }
            if (aiBadge) {
                aiBadge.textContent = ai_service === 'connected' ? 'Online' : 'Degraded';
                aiBadge.style.color = ai_service === 'connected' ? 'var(--success)' : 'var(--danger)';
            }
        }
    } catch (err) {
        if (apiBadge) {
            apiBadge.textContent = 'Offline';
            apiBadge.style.color = 'var(--danger)';
        }
        if (aiBadge) {
            aiBadge.textContent = 'Offline';
            aiBadge.style.color = 'var(--danger)';
        }
    }
}

function initModeSwitcher() {
    const toggleBtn = document.getElementById('mode-toggle-btn');
    const modeBadge = document.getElementById('mode-badge');
    const simpleViews = document.getElementById('simple-sidebar-views');
    const enterpriseViews = document.getElementById('enterprise-sidebar-views');

    const updateModeUI = (mode) => {
        if (mode === 'enterprise') {
            modeBadge.textContent = 'Enterprise Mode';
            modeBadge.style.color = 'var(--info)';
            modeBadge.style.backgroundColor = 'var(--info-light)';
            modeBadge.style.borderColor = 'rgba(56, 189, 248, 0.4)';
            toggleBtn.textContent = 'Switch to Simple';
            simpleViews.style.display = 'none';
            enterpriseViews.style.display = 'flex';
            loadEnterpriseTree();
        } else {
            modeBadge.textContent = 'Simple Mode';
            modeBadge.style.color = 'var(--primary)';
            modeBadge.style.backgroundColor = 'var(--primary-light)';
            modeBadge.style.borderColor = 'rgba(99, 102, 241, 0.3)';
            toggleBtn.textContent = 'Switch to Enterprise';
            simpleViews.style.display = 'flex';
            enterpriseViews.style.display = 'none';
        }
    };

    updateModeUI(store.getState().activeMode);

    toggleBtn.onclick = () => {
        const current = store.getState().activeMode;
        const next = current === 'simple' ? 'enterprise' : 'simple';
        store.setMode(next);
        updateModeUI(next);
        toast.info(`Switched to ${next === 'simple' ? 'Simple' : 'Enterprise'} Mode`);
    };
}

async function loadEnterpriseTree() {
    const container = document.getElementById('workspace-tree-container');
    const state = store.getState();
    const enterpriseWs = state.workspaces.find(w => !w.is_personal) || state.workspaces[0];

    if (!enterpriseWs) {
        container.innerHTML = `<span style="font-size: 0.8rem; color: var(--text-muted);">No enterprise workspace found.</span>`;
        return;
    }

    try {
        const res = await api.get(`/api/v1/workspaces/${enterpriseWs.id}/tree`);
        const tree = res.data;

        let html = `
            <div style="font-weight: 600; font-size: 0.85rem; padding: 0.4rem 0.5rem; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
                ${escapeHtml(tree.workspace.name)}
            </div>
        `;

        if (tree.folders && tree.folders.length > 0) {
            tree.folders.forEach(folder => {
                html += `
                    <div style="margin-left: 0.5rem; margin-top: 0.35rem;">
                        <div style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted); padding: 0.2rem 0.4rem;">
                            📁 ${escapeHtml(folder.name)}
                        </div>
                `;
                if (folder.projects && folder.projects.length > 0) {
                    folder.projects.forEach(project => {
                        html += `
                            <a class="nav-link" style="margin-left: 0.5rem; font-size: 0.8rem; padding: 0.35rem 0.6rem;" data-project-id="${project.id}">
                                <div class="nav-link-left">
                                    <span style="width: 8px; height: 8px; border-radius: 2px; background-color: ${project.color_hex};"></span>
                                    <span>${escapeHtml(project.name)}</span>
                                </div>
                                <span class="nav-count">${project.task_count}</span>
                            </a>
                        `;
                    });
                }
                html += `</div>`;
            });
        }

        if (tree.standalone_projects && tree.standalone_projects.length > 0) {
            tree.standalone_projects.forEach(project => {
                html += `
                    <a class="nav-link" style="font-size: 0.8rem; padding: 0.35rem 0.6rem;" data-project-id="${project.id}">
                        <div class="nav-link-left">
                            <span style="width: 8px; height: 8px; border-radius: 2px; background-color: ${project.color_hex};"></span>
                            <span>${escapeHtml(project.name)}</span>
                        </div>
                        <span class="nav-count">${project.task_count}</span>
                    </a>
                `;
            });
        }

        container.innerHTML = html;
    } catch (err) {
        container.innerHTML = `<span style="font-size: 0.8rem; color: var(--danger);">Failed to load hierarchy.</span>`;
    }
}

function initNavigation() {
    const links = document.querySelectorAll('#simple-sidebar-views .nav-link');
    const titleEl = document.getElementById('active-view-title');
    const subtitleEl = document.getElementById('active-view-subtitle');

    const subtitles = {
        inbox: 'Zero-friction personal task management and quick capture',
        today: 'Tasks scheduled or created for today',
        upcoming: 'Scheduled tasks arriving over the next 7 days',
        completed: 'Archive of finished and satisfied tasks',
    };

    links.forEach(link => {
        link.onclick = (e) => {
            e.preventDefault();
            links.forEach(l => l.classList.remove('active'));
            link.classList.add('active');

            const view = link.dataset.view;
            store.setState({ activeView: view });

            titleEl.textContent = view.charAt(0).toUpperCase() + view.slice(1);
            subtitleEl.textContent = subtitles[view] || '';

            mountComponents();
        };
    });
}

function mountComponents() {
    const viewSwitcherMount = document.getElementById('view-switcher-mount');
    const filterBarMount = document.getElementById('filter-bar-mount');
    const quickAddMount = document.getElementById('quick-add-mount');

    if (viewSwitcherMount) {
        componentRegistry.mount('ViewSwitcher', viewSwitcherMount);
    }
    if (filterBarMount) {
        componentRegistry.mount('FilterBar', filterBarMount);
    }
    if (quickAddMount) {
        componentRegistry.mount('QuickAddBar', quickAddMount);
    }

    mountTaskView();
}

function mountTaskView() {
    const taskViewMount = document.getElementById('task-view-mount');
    const quickAddMount = document.getElementById('quick-add-mount');
    const filterBarMount = document.getElementById('filter-bar-mount');

    if (!taskViewMount) return;

    const isEnterprise = store.getState().activeMode === 'enterprise';

    // Show/hide quick add and filter bar depending on view type
    if (currentViewType === 'docs') {
        if (quickAddMount) quickAddMount.style.display = 'none';
        if (filterBarMount) filterBarMount.style.display = 'none';
        componentRegistry.mount('DocEditor', taskViewMount);
    } else if (currentViewType === 'gantt') {
        if (quickAddMount) quickAddMount.style.display = 'block';
        if (filterBarMount) filterBarMount.style.display = 'none';
        componentRegistry.mount('GanttChart', taskViewMount);
    } else if (currentViewType === 'kanban') {
        if (quickAddMount) quickAddMount.style.display = 'block';
        if (filterBarMount) filterBarMount.style.display = 'block';
        if (isEnterprise) {
            componentRegistry.mount('KanbanBoard', taskViewMount);
        } else {
            componentRegistry.mount('SimpleKanban', taskViewMount);
        }
    } else if (currentViewType === 'table') {
        if (quickAddMount) quickAddMount.style.display = 'block';
        if (filterBarMount) filterBarMount.style.display = 'block';
        componentRegistry.mount('TaskTable', taskViewMount);
    } else {
        if (quickAddMount) quickAddMount.style.display = 'block';
        if (filterBarMount) filterBarMount.style.display = 'block';
        componentRegistry.mount('SimpleTaskList', taskViewMount);
    }
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

