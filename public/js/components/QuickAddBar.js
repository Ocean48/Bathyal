/**
 * Rapid Task Capture Component (QuickAddBar)
 */
import { api } from '../core/api.js';
import { store } from '../core/store.js';
import { toast } from './Toast.js';
import { eventBus } from '../core/eventBus.js';

export class QuickAddBar {
    constructor(container) {
        this.container = container;
    }

    mount() {
        this.render();
        this.bindEvents();
    }

    render() {
        this.container.innerHTML = `
            <div class="quick-add-card">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary); flex-shrink: 0;">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                <input type="text" class="quick-add-input" placeholder="Add a new task... (e.g. 'Review pull request @today !urgent')" />
                <span class="quick-add-hint">Press Enter</span>
            </div>
        `;
    }

    bindEvents() {
        const input = this.container.querySelector('.quick-add-input');
        if (!input) return;

        input.addEventListener('keydown', async (e) => {
            if (e.key === 'Enter' && input.value.trim() !== '') {
                const text = input.value.trim();
                input.value = '';
                input.disabled = true;

                try {
                    const state = store.getState();
                    const res = await api.post('/api/v1/tasks', {
                        raw_input: text,
                        workspace_id: state.activeWorkspaceId,
                    });

                    if (res && res.data) {
                        toast.success(`Task created: "${res.data.title}"`);
                        eventBus.emit('task:created', res.data);
                    }
                } catch (err) {
                    toast.error(err.message || 'Failed to create task');
                    input.value = text;
                } finally {
                    input.disabled = false;
                    input.focus();
                }
            }
        });
    }

    unmount() {
        this.container.innerHTML = '';
    }
}
