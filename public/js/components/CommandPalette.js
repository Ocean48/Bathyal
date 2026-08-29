/**
 * Universal Command Palette Component
 * Triggered by Ctrl+K, Cmd+K, or Slash (/).
 */
import { commandRegistry } from '../core/commandRegistry.js';
import { store } from '../core/store.js';
import { api } from '../core/api.js';
import { toast } from './Toast.js';
import { eventBus } from '../core/eventBus.js';

export class CommandPalette {
    constructor() {
        this.isOpen = false;
        this.selectedIndex = 0;
        this.results = [];
        this.backdrop = null;
        this.input = null;
        this.resultsContainer = null;
        this.initDefaultCommands();
    }

    initDefaultCommands() {
        // Navigation Commands
        commandRegistry.register({
            id: 'nav:inbox',
            title: 'Go to Inbox',
            category: 'Navigation',
            shortcut: 'G I',
            keywords: ['inbox', 'focus', 'tasks'],
            handler: () => {
                store.setState({ activeView: 'inbox' });
                eventBus.emit('navigate:view', 'inbox');
            }
        });

        commandRegistry.register({
            id: 'nav:today',
            title: 'Go to Today',
            category: 'Navigation',
            shortcut: 'G T',
            keywords: ['today', 'due', 'calendar'],
            handler: () => {
                store.setState({ activeView: 'today' });
                eventBus.emit('navigate:view', 'today');
            }
        });

        commandRegistry.register({
            id: 'nav:upcoming',
            title: 'Go to Upcoming',
            category: 'Navigation',
            shortcut: 'G U',
            keywords: ['upcoming', 'scheduled', 'next'],
            handler: () => {
                store.setState({ activeView: 'upcoming' });
                eventBus.emit('navigate:view', 'upcoming');
            }
        });

        commandRegistry.register({
            id: 'nav:completed',
            title: 'Go to Completed',
            category: 'Navigation',
            shortcut: 'G C',
            keywords: ['completed', 'done', 'archive'],
            handler: () => {
                store.setState({ activeView: 'completed' });
                eventBus.emit('navigate:view', 'completed');
            }
        });

        // Mode Switching Commands
        commandRegistry.register({
            id: 'mode:toggle',
            title: 'Toggle Simple / Enterprise Mode',
            category: 'Modes',
            keywords: ['switch', 'enterprise', 'simple', 'mode'],
            handler: () => {
                const current = store.getState().activeMode;
                const next = current === 'simple' ? 'enterprise' : 'simple';
                store.setMode(next);
                eventBus.emit('mode:changed', next);
                toast.info(`Switched to ${next === 'simple' ? 'Simple' : 'Enterprise'} Mode`);
            }
        });

        // Theme Commands
        commandRegistry.register({
            id: 'theme:toggle',
            title: 'Toggle Dark / Light Theme',
            category: 'Preferences',
            keywords: ['theme', 'dark', 'light', 'mode'],
            handler: () => {
                const current = store.getState().theme;
                const next = current === 'dark' ? 'light' : 'dark';
                store.setTheme(next);
                eventBus.emit('theme:changed', next);
                toast.info(`Theme set to ${next}`);
            }
        });

        // Task Creation Command
        commandRegistry.register({
            id: 'task:create',
            title: 'Create New Task',
            category: 'Actions',
            shortcut: 'C',
            keywords: ['new', 'task', 'add', 'create'],
            handler: () => {
                const input = document.querySelector('.quick-add-input');
                if (input) {
                    input.focus();
                    input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });

        // Dynamic Task Search Provider
        commandRegistry.registerProvider(async (query) => {
            const state = store.getState();
            if (!query || query.length < 2) return [];

            try {
                const res = await api.get('/api/v1/tasks', { workspace_id: state.activeWorkspaceId || '' });
                const tasks = res.data || [];

                return tasks.map(task => ({
                    id: `task:${task.id}`,
                    title: task.title,
                    category: 'Tasks',
                    subtitle: `${task.status_name} • Priority: ${task.priority}`,
                    keywords: [task.title, task.status_name, task.priority, task.project_name || ''],
                    handler: () => {
                        toast.info(`Selected task: "${task.title}"`);
                        eventBus.emit('task:selected', task);
                    }
                }));
            } catch (e) {
                return [];
            }
        });

        // Dynamic Project Search Provider
        commandRegistry.registerProvider(async (query) => {
            const state = store.getState();
            if (!query || query.length < 2) return [];

            try {
                const res = await api.get('/api/v1/projects', { workspace_id: state.activeWorkspaceId || '' });
                const projects = res.data || [];

                return projects.map(proj => ({
                    id: `project:${proj.id}`,
                    title: proj.name,
                    category: 'Projects',
                    subtitle: `${proj.task_count || 0} tasks`,
                    keywords: [proj.name, proj.description || ''],
                    handler: () => {
                        toast.info(`Opened project: "${proj.name}"`);
                        eventBus.emit('project:selected', proj);
                    }
                }));
            } catch (e) {
                return [];
            }
        });
    }

    open() {
        if (this.isOpen) return;
        this.isOpen = true;
        this.selectedIndex = 0;
        this.render();
        this.updateResults('');
    }

    close() {
        if (!this.isOpen) return;
        this.isOpen = false;
        if (this.backdrop) {
            this.backdrop.remove();
            this.backdrop = null;
        }
    }

    render() {
        this.backdrop = document.createElement('div');
        this.backdrop.className = 'modal-backdrop';
        this.backdrop.id = 'command-palette-backdrop';

        this.backdrop.innerHTML = `
            <div class="command-palette-modal" style="
                background-color: var(--bg-surface);
                border: 1px solid var(--border-subtle);
                border-radius: var(--radius-lg);
                width: 90%;
                max-width: 600px;
                box-shadow: var(--shadow-lg);
                display: flex;
                flex-direction: column;
                overflow: hidden;
                animation: toast-in 0.15s ease-out;
            ">
                <div class="command-palette-header" style="
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    padding: 0.85rem 1.25rem;
                    border-bottom: 1px solid var(--border-subtle);
                ">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--primary); flex-shrink: 0;">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" class="command-palette-input" placeholder="Type a command or search anything..." style="
                        flex: 1;
                        background: transparent;
                        border: none;
                        outline: none;
                        font-size: 1rem;
                        color: var(--text-main);
                    " />
                    <span style="font-size: 0.7rem; color: var(--text-subtle); background-color: var(--bg-surface-hover); padding: 0.2rem 0.4rem; border-radius: 4px;">ESC</span>
                </div>
                <div class="command-palette-results" style="
                    max-height: 380px;
                    overflow-y: auto;
                    padding: 0.5rem;
                    display: flex;
                    flex-direction: column;
                    gap: 0.25rem;
                "></div>
                <div class="command-palette-footer" style="
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 0.5rem 1.25rem;
                    border-top: 1px solid var(--border-subtle);
                    font-size: 0.75rem;
                    color: var(--text-subtle);
                    background-color: var(--bg-surface-hover);
                ">
                    <div style="display: flex; gap: 1rem;">
                        <span><kbd>↑↓</kbd> Navigate</span>
                        <span><kbd>↵</kbd> Select</span>
                        <span><kbd>ESC</kbd> Close</span>
                    </div>
                    <span>Bathyal Universal Launcher</span>
                </div>
            </div>
        `;

        document.body.appendChild(this.backdrop);

        this.input = this.backdrop.querySelector('.command-palette-input');
        this.resultsContainer = this.backdrop.querySelector('.command-palette-results');

        this.input.focus();

        this.backdrop.addEventListener('click', (e) => {
            if (e.target === this.backdrop) this.close();
        });

        this.input.addEventListener('input', (e) => {
            this.selectedIndex = 0;
            this.updateResults(e.target.value);
        });

        this.input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.close();
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (this.results.length > 0) {
                    this.selectedIndex = (this.selectedIndex + 1) % this.results.length;
                    this.renderResultList();
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (this.results.length > 0) {
                    this.selectedIndex = (this.selectedIndex - 1 + this.results.length) % this.results.length;
                    this.renderResultList();
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                this.executeSelected();
            }
        });
    }

    async updateResults(query) {
        this.results = await commandRegistry.search(query);
        this.renderResultList();
    }

    renderResultList() {
        if (!this.resultsContainer) return;

        if (this.results.length === 0) {
            this.resultsContainer.innerHTML = `
                <div style="padding: 2rem; text-align: center; color: var(--text-muted); font-size: 0.875rem;">
                    No commands or matching items found.
                </div>
            `;
            return;
        }

        let html = '';
        this.results.forEach((item, index) => {
            const isSelected = index === this.selectedIndex;
            html += `
                <div class="command-palette-item ${isSelected ? 'active' : ''}" data-index="${index}" style="
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 0.6rem 0.85rem;
                    border-radius: var(--radius-sm);
                    cursor: pointer;
                    background-color: ${isSelected ? 'var(--primary-light)' : 'transparent'};
                    color: ${isSelected ? 'var(--primary)' : 'var(--text-main)'};
                    transition: background-color 0.1s;
                ">
                    <div style="display: flex; flex-direction: column; gap: 0.1rem;">
                        <span style="font-weight: 500; font-size: 0.9rem;">${this.escapeHtml(item.title)}</span>
                        ${item.subtitle ? `<span style="font-size: 0.75rem; color: var(--text-muted);">${this.escapeHtml(item.subtitle)}</span>` : ''}
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span class="badge-tag" style="font-size: 0.7rem;">${this.escapeHtml(item.category || 'General')}</span>
                        ${item.shortcut ? `<span style="font-size: 0.7rem; color: var(--text-subtle); background-color: var(--bg-surface-hover); padding: 0.15rem 0.35rem; border-radius: 4px;">${this.escapeHtml(item.shortcut)}</span>` : ''}
                    </div>
                </div>
            `;
        });

        this.resultsContainer.innerHTML = html;

        // Click handlers
        this.resultsContainer.querySelectorAll('.command-palette-item').forEach(el => {
            el.addEventListener('click', () => {
                this.selectedIndex = parseInt(el.dataset.index, 10);
                this.executeSelected();
            });
            el.addEventListener('mouseenter', () => {
                this.selectedIndex = parseInt(el.dataset.index, 10);
                this.renderResultList();
            });
        });

        // Ensure active item is visible in scroll container
        const activeEl = this.resultsContainer.querySelector('.command-palette-item.active');
        if (activeEl) {
            activeEl.scrollIntoView({ block: 'nearest' });
        }
    }

    executeSelected() {
        if (this.results.length === 0) return;
        const item = this.results[this.selectedIndex];
        this.close();

        if (item && typeof item.handler === 'function') {
            try {
                item.handler();
            } catch (err) {
                console.error('Command execution failed:', err);
                toast.error('Command failed to execute');
            }
        }
    }

    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}

export const commandPalette = new CommandPalette();
