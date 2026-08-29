/**
 * Keyboard Shortcuts Manager & Visual Cheat Sheet Modal
 */
import { commandPalette } from './CommandPalette.js';
import { store } from '../core/store.js';
import { eventBus } from '../core/eventBus.js';

export class KeyboardShortcuts {
    constructor() {
        this.selectedTaskIndex = -1;
        this.cheatSheetOpen = false;
        this.cheatSheetBackdrop = null;
        this.init();
    }

    init() {
        window.addEventListener('keydown', (e) => this.handleKeyDown(e));
        eventBus.on('navigate:view', () => {
            this.selectedTaskIndex = -1;
            this.updateTaskSelection();
        });
    }

    handleKeyDown(e) {
        // 1. Command Palette shortcut (Ctrl+K or Cmd+K) - works even when input focused
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            commandPalette.open();
            return;
        }

        // 2. Input Guard: If typing in input, textarea, select, or contenteditable, ignore hotkeys
        const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
        const isEditable = document.activeElement && (document.activeElement.isContentEditable || ['input', 'textarea', 'select'].includes(activeTag));

        if (isEditable) {
            if (e.key === 'Escape') {
                document.activeElement.blur();
            }
            return;
        }

        // 3. Modal close on Escape
        if (e.key === 'Escape') {
            if (this.cheatSheetOpen) {
                this.closeCheatSheet();
                return;
            }
            if (commandPalette.isOpen) {
                commandPalette.close();
                return;
            }
            this.selectedTaskIndex = -1;
            this.updateTaskSelection();
            return;
        }

        // 4. Slash (/) to open Command Palette or focus quick add
        if (e.key === '/') {
            e.preventDefault();
            commandPalette.open();
            return;
        }

        // 5. Question Mark (?) to open Cheat Sheet
        if (e.key === '?') {
            e.preventDefault();
            this.toggleCheatSheet();
            return;
        }

        // 6. Navigation: j (down) / k (up)
        if (e.key === 'j' || e.key === 'ArrowDown') {
            e.preventDefault();
            this.navigateTasks(1);
            return;
        }

        if (e.key === 'k' || e.key === 'ArrowUp') {
            e.preventDefault();
            this.navigateTasks(-1);
            return;
        }

        // 7. Actions on Selected Task
        const taskItems = document.querySelectorAll('.task-item');
        if (this.selectedTaskIndex >= 0 && this.selectedTaskIndex < taskItems.length) {
            const currentItem = taskItems[this.selectedTaskIndex];

            // x or Space: toggle complete
            if (e.key === 'x' || e.key === ' ') {
                e.preventDefault();
                const checkbox = currentItem.querySelector('.task-checkbox');
                if (checkbox) checkbox.click();
                return;
            }

            // e: edit title
            if (e.key === 'e') {
                e.preventDefault();
                const titleSpan = currentItem.querySelector('.task-title');
                if (titleSpan) titleSpan.click();
                return;
            }

            // Delete / Backspace: delete task
            if (e.key === 'Delete' || e.key === 'Backspace') {
                e.preventDefault();
                const deleteBtn = currentItem.querySelector('.btn-delete-task');
                if (deleteBtn) deleteBtn.click();
                return;
            }
        }

        // 8. Global Action: 'c' to create task
        if (e.key === 'c') {
            e.preventDefault();
            const input = document.querySelector('.quick-add-input');
            if (input) {
                input.focus();
                input.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }

        // 9. Number keys (1-4) for Quick View Switching
        if (e.key === '1') {
            store.setState({ activeView: 'inbox' });
            eventBus.emit('navigate:view', 'inbox');
        } else if (e.key === '2') {
            store.setState({ activeView: 'today' });
            eventBus.emit('navigate:view', 'today');
        } else if (e.key === '3') {
            store.setState({ activeView: 'upcoming' });
            eventBus.emit('navigate:view', 'upcoming');
        } else if (e.key === '4') {
            store.setState({ activeView: 'completed' });
            eventBus.emit('navigate:view', 'completed');
        }
    }

    navigateTasks(direction) {
        const taskItems = document.querySelectorAll('.task-item');
        if (taskItems.length === 0) return;

        this.selectedTaskIndex += direction;
        if (this.selectedTaskIndex < 0) this.selectedTaskIndex = 0;
        if (this.selectedTaskIndex >= taskItems.length) this.selectedTaskIndex = taskItems.length - 1;

        this.updateTaskSelection();
    }

    updateTaskSelection() {
        const taskItems = document.querySelectorAll('.task-item');
        taskItems.forEach((item, idx) => {
            if (idx === this.selectedTaskIndex) {
                item.style.borderColor = 'var(--primary)';
                item.style.boxShadow = '0 0 0 2px var(--primary-light)';
                item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                item.style.borderColor = '';
                item.style.boxShadow = '';
            }
        });
    }

    toggleCheatSheet() {
        if (this.cheatSheetOpen) {
            this.closeCheatSheet();
        } else {
            this.openCheatSheet();
        }
    }

    openCheatSheet() {
        this.cheatSheetOpen = true;
        this.cheatSheetBackdrop = document.createElement('div');
        this.cheatSheetBackdrop.className = 'modal-backdrop';
        this.cheatSheetBackdrop.id = 'cheat-sheet-backdrop';

        this.cheatSheetBackdrop.innerHTML = `
            <div class="modal" style="max-width: 540px;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-subtle); padding-bottom: 0.75rem;">
                    <h3 style="font-size: 1.15rem; font-weight: 700;">Keyboard Shortcuts</h3>
                    <button class="btn btn-ghost btn-sm" id="btn-close-cheat-sheet">✕</button>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; font-size: 0.85rem; padding: 0.5rem 0;">
                    <div>
                        <div style="font-weight: 700; font-size: 0.75rem; text-transform: uppercase; color: var(--text-subtle); margin-bottom: 0.5rem;">Navigation</div>
                        <div style="display: flex; flex-direction: column; gap: 0.4rem;">
                            <div style="display: flex; justify-content: space-between;"><kbd>Ctrl + K</kbd> <span>Command Palette</span></div>
                            <div style="display: flex; justify-content: space-between;"><kbd>j</kbd> / <kbd>↓</kbd> <span>Next task</span></div>
                            <div style="display: flex; justify-content: space-between;"><kbd>k</kbd> / <kbd>↑</kbd> <span>Previous task</span></div>
                            <div style="display: flex; justify-content: space-between;"><kbd>1</kbd> - <kbd>4</kbd> <span>Switch focus view</span></div>
                            <div style="display: flex; justify-content: space-between;"><kbd>?</kbd> <span>Shortcuts help</span></div>
                        </div>
                    </div>
                    <div>
                        <div style="font-weight: 700; font-size: 0.75rem; text-transform: uppercase; color: var(--text-subtle); margin-bottom: 0.5rem;">Task Actions</div>
                        <div style="display: flex; flex-direction: column; gap: 0.4rem;">
                            <div style="display: flex; justify-content: space-between;"><kbd>c</kbd> <span>Create new task</span></div>
                            <div style="display: flex; justify-content: space-between;"><kbd>x</kbd> / <kbd>Space</kbd> <span>Toggle complete</span></div>
                            <div style="display: flex; justify-content: space-between;"><kbd>e</kbd> <span>Edit title inline</span></div>
                            <div style="display: flex; justify-content: space-between;"><kbd>Del</kbd> <span>Delete task</span></div>
                            <div style="display: flex; justify-content: space-between;"><kbd>Esc</kbd> <span>Clear / Close</span></div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(this.cheatSheetBackdrop);

        this.cheatSheetBackdrop.addEventListener('click', (e) => {
            if (e.target === this.cheatSheetBackdrop) this.closeCheatSheet();
        });

        const closeBtn = this.cheatSheetBackdrop.querySelector('#btn-close-cheat-sheet');
        if (closeBtn) closeBtn.onclick = () => this.closeCheatSheet();
    }

    closeCheatSheet() {
        this.cheatSheetOpen = false;
        if (this.cheatSheetBackdrop) {
            this.cheatSheetBackdrop.remove();
            this.cheatSheetBackdrop = null;
        }
    }
}

export const keyboardShortcuts = new KeyboardShortcuts();
