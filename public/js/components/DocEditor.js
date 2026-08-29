/**
 * Slash-Command Block Document Editor Component
 * Notion-style block editor for project wikis, architecture specs, and workspace notes.
 */
import { api } from '../core/api.js';
import { store } from '../core/store.js';
import { toast } from './Toast.js';
import { blockRegistry } from '../core/blockRegistry.js';

export class DocEditor {
    constructor(container) {
        this.container = container;
        this.documents = [];
        this.activeDoc = null;
        this.slashMenu = null;
        this.activeBlockEl = null;
        this.saveTimeout = null;
    }

    mount() {
        this.loadDocuments();
    }

    async loadDocuments() {
        const state = store.getState();
        const wsId = state.activeWorkspaceId || 2;

        this.container.innerHTML = `<div style="text-align: center; color: var(--text-muted); padding: 3rem;">Loading documents...</div>`;

        try {
            const res = await api.get('/api/v1/documents', { workspace_id: wsId });
            this.documents = res.data || [];

            if (this.documents.length > 0 && !this.activeDoc) {
                this.activeDoc = this.documents[0];
            }

            this.render();
        } catch (err) {
            this.container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-title" style="color: var(--danger);">Failed to load documents</div>
                    <div class="empty-state-desc">${this.escapeHtml(err.message || 'Please check connection')}</div>
                </div>
            `;
        }
    }

    render() {
        const docListHtml = this.documents.map(doc => `
            <div class="doc-list-item ${this.activeDoc && this.activeDoc.id === doc.id ? 'active' : ''}" data-id="${doc.id}">
                <div style="display: flex; align-items: center; gap: 0.5rem; flex: 1; overflow: hidden;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                    <span class="doc-item-title">${this.escapeHtml(doc.title)}</span>
                </div>
                <button class="btn-icon btn-ghost btn-sm doc-del-btn" data-id="${doc.id}" title="Delete document">✕</button>
            </div>
        `).join('');

        let blocks = [];
        if (this.activeDoc && this.activeDoc.content) {
            try {
                const parsed = typeof this.activeDoc.content === 'string' ? JSON.parse(this.activeDoc.content) : this.activeDoc.content;
                blocks = parsed.blocks || [];
            } catch (e) {
                blocks = [{ type: 'paragraph', text: this.activeDoc.content }];
            }
        }

        if (blocks.length === 0) {
            blocks = [
                { type: 'heading1', text: this.activeDoc ? this.activeDoc.title : 'Untitled Document' },
                { type: 'paragraph', text: 'Type "/" for commands or start writing notes here...' }
            ];
        }

        const blocksHtml = blocks.map(block => {
            const def = blockRegistry.get(block.type);
            return def.render(block);
        }).join('');

        this.container.innerHTML = `
            <div class="doc-workspace-container">
                <!-- Document Sidebar -->
                <div class="doc-sidebar">
                    <div class="doc-sidebar-header">
                        <span style="font-weight: 600; font-size: 0.85rem;">Documents</span>
                        <button class="btn btn-sm btn-primary" id="btn-new-doc">+ New Doc</button>
                    </div>
                    <div class="doc-sidebar-list">
                        ${docListHtml}
                    </div>
                </div>

                <!-- Document Content Canvas -->
                <div class="doc-main-panel">
                    ${this.activeDoc ? `
                        <div class="doc-header-bar">
                            <input type="text" class="doc-title-input" id="active-doc-title" value="${this.escapeHtml(this.activeDoc.title)}" placeholder="Document Title..." />
                            <div class="doc-save-status" id="doc-save-status">Saved</div>
                        </div>
                        <div class="doc-blocks-container" id="doc-blocks-mount">
                            ${blocksHtml}
                        </div>
                    ` : `
                        <div class="empty-state" style="margin: 4rem auto;">
                            <div class="empty-state-title">No document selected</div>
                            <div class="empty-state-desc">Select a document from the left or create a new one.</div>
                        </div>
                    `}
                </div>
            </div>
        `;

        this.bindEvents();
    }

    bindEvents() {
        // Document selection
        this.container.querySelectorAll('.doc-list-item').forEach(item => {
            item.onclick = async (e) => {
                if (e.target.classList.contains('doc-del-btn')) return;
                const docId = parseInt(item.dataset.id, 10);
                const found = this.documents.find(d => d.id === docId);
                if (found) {
                    this.activeDoc = found;
                    this.render();
                }
            };
        });

        // Create New Document
        const newDocBtn = this.container.querySelector('#btn-new-doc');
        if (newDocBtn) {
            newDocBtn.onclick = async () => {
                const state = store.getState();
                const wsId = state.activeWorkspaceId || 2;

                try {
                    const res = await api.post('/api/v1/documents', {
                        workspace_id: wsId,
                        title: 'Untitled Spec',
                        content: {
                            blocks: [
                                { type: 'heading1', text: 'Untitled Spec' },
                                { type: 'paragraph', text: 'Start writing or press "/" for blocks...' }
                            ]
                        }
                    });

                    toast.success('New document created');
                    this.documents.unshift(res.data);
                    this.activeDoc = res.data;
                    this.render();
                } catch (err) {
                    toast.error('Failed to create document');
                }
            };
        }

        // Delete Document
        this.container.querySelectorAll('.doc-del-btn').forEach(btn => {
            btn.onclick = async (e) => {
                e.stopPropagation();
                const docId = parseInt(btn.dataset.id, 10);
                if (!confirm('Are you sure you want to delete this document?')) return;

                try {
                    await api.delete(`/api/v1/documents/${docId}`);
                    toast.info('Document deleted');
                    this.documents = this.documents.filter(d => d.id !== docId);
                    if (this.activeDoc && this.activeDoc.id === docId) {
                        this.activeDoc = this.documents[0] || null;
                    }
                    this.render();
                } catch (err) {
                    toast.error('Failed to delete document');
                }
            };
        });

        // Title rename
        const titleInput = this.container.querySelector('#active-doc-title');
        if (titleInput) {
            titleInput.oninput = () => this.scheduleAutoSave();
        }

        // Slash command triggers on editable blocks
        const blocksContainer = this.container.querySelector('#doc-blocks-mount');
        if (blocksContainer) {
            blocksContainer.addEventListener('keyup', (e) => {
                const target = e.target;
                if (target.isContentEditable) {
                    const text = target.textContent;
                    if (text === '/') {
                        this.showSlashMenu(target);
                    } else if (!text.startsWith('/')) {
                        this.hideSlashMenu();
                    }
                    this.scheduleAutoSave();
                }
            });

            // Enter key to insert a new paragraph block
            blocksContainer.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    const target = e.target;
                    if (target.isContentEditable && !this.slashMenu) {
                        e.preventDefault();
                        const newP = document.createElement('p');
                        newP.className = 'doc-block doc-p';
                        newP.contentEditable = 'true';
                        newP.dataset.type = 'paragraph';
                        newP.textContent = '';
                        
                        const parentBlock = target.closest('.doc-block') || target;
                        parentBlock.after(newP);
                        newP.focus();
                        this.scheduleAutoSave();
                    }
                }
            });

            // Checklist toggle
            blocksContainer.querySelectorAll('.doc-chk').forEach(chk => {
                chk.onchange = () => {
                    const block = chk.closest('.doc-block');
                    if (block) {
                        block.classList.toggle('checked', chk.checked);
                    }
                    this.scheduleAutoSave();
                };
            });
        }
    }

    showSlashMenu(targetEl) {
        this.hideSlashMenu();
        this.activeBlockEl = targetEl;

        const rect = targetEl.getBoundingClientRect();
        this.slashMenu = document.createElement('div');
        this.slashMenu.className = 'doc-slash-menu';
        this.slashMenu.style.top = `${rect.bottom + window.scrollY + 4}px`;
        this.slashMenu.style.left = `${rect.left + window.scrollX}px`;

        const blockTypes = blockRegistry.getAll();
        const menuItemsHtml = blockTypes.map(b => `
            <div class="slash-menu-item" data-type="${b.type}">
                <span class="slash-item-icon">${b.icon}</span>
                <div class="slash-item-text">
                    <span class="slash-item-label">${this.escapeHtml(b.label)}</span>
                    <span class="slash-item-desc">${this.escapeHtml(b.description)}</span>
                </div>
            </div>
        `).join('');

        this.slashMenu.innerHTML = `<div class="slash-menu-list">${menuItemsHtml}</div>`;
        document.body.appendChild(this.slashMenu);

        this.slashMenu.querySelectorAll('.slash-menu-item').forEach(item => {
            item.onclick = (e) => {
                e.stopPropagation();
                const type = item.dataset.type;
                this.insertBlockType(type);
                this.hideSlashMenu();
            };
        });

        // Click outside closes menu
        const onOutsideClick = (e) => {
            if (this.slashMenu && !this.slashMenu.contains(e.target)) {
                this.hideSlashMenu();
                document.removeEventListener('click', onOutsideClick);
            }
        };
        setTimeout(() => document.addEventListener('click', onOutsideClick), 10);
    }

    hideSlashMenu() {
        if (this.slashMenu) {
            this.slashMenu.remove();
            this.slashMenu = null;
        }
    }

    insertBlockType(type) {
        if (!this.activeBlockEl) return;
        const def = blockRegistry.get(type);
        const blockHtml = def.render({ type, text: '' });
        
        const temp = document.createElement('div');
        temp.innerHTML = blockHtml;
        const newBlock = temp.firstElementChild;

        const parentBlock = this.activeBlockEl.closest('.doc-block') || this.activeBlockEl;
        parentBlock.replaceWith(newBlock);

        const editable = newBlock.querySelector('[contenteditable="true"]') || newBlock;
        editable.focus();
        this.scheduleAutoSave();
    }

    scheduleAutoSave() {
        const statusEl = this.container.querySelector('#doc-save-status');
        if (statusEl) statusEl.textContent = 'Saving...';

        if (this.saveTimeout) clearTimeout(this.saveTimeout);
        this.saveTimeout = setTimeout(() => this.saveDocument(), 800);
    }

    async saveDocument() {
        if (!this.activeDoc) return;

        const titleInput = this.container.querySelector('#active-doc-title');
        const blocksContainer = this.container.querySelector('#doc-blocks-mount');
        const statusEl = this.container.querySelector('#doc-save-status');

        const title = titleInput ? titleInput.value.trim() : this.activeDoc.title;
        const blockEls = blocksContainer ? blocksContainer.querySelectorAll('.doc-block') : [];

        const blocks = [];
        blockEls.forEach(el => {
            const type = el.dataset.type || 'paragraph';
            const editable = el.querySelector('[contenteditable="true"]') || el;
            const text = editable ? editable.textContent : '';
            const chk = el.querySelector('.doc-chk');
            const checked = chk ? chk.checked : false;

            blocks.push({ type, text, checked });
        });

        try {
            await api.patch(`/api/v1/documents/${this.activeDoc.id}`, {
                title,
                content: { blocks }
            });

            this.activeDoc.title = title;
            this.activeDoc.content = { blocks };

            if (statusEl) statusEl.textContent = 'Saved';
        } catch (err) {
            if (statusEl) statusEl.textContent = 'Error saving';
        }
    }

    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    unmount() {
        this.hideSlashMenu();
        if (this.saveTimeout) clearTimeout(this.saveTimeout);
        this.container.innerHTML = '';
    }
}
