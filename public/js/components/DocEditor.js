/**
 * Slash-Command Block Document Editor & Multi-Format File Viewer Component
 * Notion-style block editor for project wikis, architecture specs, and workspace notes,
 * plus multi-format document viewer (Images, PDFs, Word, PowerPoint, Spreadsheets).
 */
import { api } from '../core/api.js';
import { store } from '../core/store.js';
import { toast } from './Toast.js';
import { blockRegistry } from '../core/blockRegistry.js';
import { taskDetailDrawer } from './TaskDetailDrawer.js';
import { eventBus } from '../core/eventBus.js';

export class DocEditor {
    constructor(container) {
        this.container = container;
        this.documents = [];
        this.activeDoc = null;
        this.slashMenu = null;
        this.activeBlockEl = null;
        this.saveTimeout = null;
        this.activeFilter = 'all'; // 'all', 'rich_text', 'file'
        this.searchQuery = '';
        this.workspaceTasks = [];

        this.onDocUpdated = () => this.loadDocuments(false);
        eventBus.on('document:updated', this.onDocUpdated);
    }

    mount() {
        this.loadDocuments(true);
    }

    async loadDocuments(resetActive = false) {
        const state = store.getState();
        const wsId = state.activeWorkspaceId || 2;

        try {
            const [docRes, taskRes] = await Promise.all([
                api.get('/api/v1/documents', { workspace_id: wsId }),
                api.get('/api/v1/tasks', { workspace_id: wsId, all_levels: true })
            ]);

            this.documents = docRes.data || [];
            this.workspaceTasks = taskRes.data || [];

            if (this.documents.length > 0 && (!this.activeDoc || resetActive || !this.documents.some(d => d.id === this.activeDoc.id))) {
                this.activeDoc = this.documents[0];
            } else if (this.activeDoc) {
                this.activeDoc = this.documents.find(d => d.id === this.activeDoc.id) || this.documents[0] || null;
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
        const filteredDocs = this.documents.filter(doc => {
            if (this.activeFilter === 'rich_text' && doc.doc_type === 'file') return false;
            if (this.activeFilter === 'file' && doc.doc_type !== 'file') return false;
            if (this.searchQuery) {
                const q = this.searchQuery.toLowerCase();
                const titleMatch = (doc.title || '').toLowerCase().includes(q);
                const taskMatch = (doc.task_title || '').toLowerCase().includes(q);
                return titleMatch || taskMatch;
            }
            return true;
        });

        const docListHtml = filteredDocs.map(doc => {
            const isFile = doc.doc_type === 'file';
            const ext = (doc.file_extension || (isFile ? 'file' : 'doc')).toUpperCase();
            const isImg = isFile && ['PNG', 'JPG', 'JPEG', 'GIF', 'WEBP', 'SVG'].includes(ext);

            let typeIcon = '';
            if (isImg) {
                typeIcon = `<span class="doc-badge badge-img">IMG</span>`;
            } else if (isFile) {
                typeIcon = `<span class="doc-badge badge-${ext.toLowerCase()}">${ext}</span>`;
            } else {
                typeIcon = `<span class="doc-badge badge-spec">DOC</span>`;
            }

            return `
                <div class="doc-list-item ${this.activeDoc && this.activeDoc.id === doc.id ? 'active' : ''}" data-id="${doc.id}">
                    <div style="display: flex; flex-direction: column; gap: 0.25rem; flex: 1; min-width: 0;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; overflow: hidden;">
                            ${typeIcon}
                            <span class="doc-item-title" title="${this.escapeHtml(doc.title)}">${this.escapeHtml(doc.title)}</span>
                        </div>
                        ${doc.task_title ? `
                            <div class="doc-attached-task-chip" data-task-id="${doc.task_id}" title="Attached to Task: ${this.escapeHtml(doc.task_title)}">
                                <span>#${doc.task_id}</span> ${this.escapeHtml(doc.task_title)}
                            </div>
                        ` : ''}
                    </div>
                    <button class="btn-icon btn-ghost btn-sm doc-del-btn" data-id="${doc.id}" title="Delete document">✕</button>
                </div>
            `;
        }).join('');

        let mainContentHtml = '';

        if (!this.activeDoc) {
            mainContentHtml = `
                <div class="empty-state" style="margin: 4rem auto;">
                    <div class="empty-state-title">No document selected</div>
                    <div class="empty-state-desc">Select a document from the sidebar or upload a new file.</div>
                </div>
            `;
        } else if (this.activeDoc.doc_type === 'file') {
            mainContentHtml = this.renderFileViewer(this.activeDoc);
        } else {
            mainContentHtml = this.renderBlockEditor(this.activeDoc);
        }

        this.container.innerHTML = `
            <div class="doc-workspace-container">
                <!-- Document Sidebar -->
                <div class="doc-sidebar">
                    <div class="doc-sidebar-header">
                        <span style="font-weight: 600; font-size: 0.9rem;">Documents</span>
                        <div style="display: flex; gap: 0.35rem;">
                            <button class="btn btn-sm btn-secondary" id="btn-upload-doc" title="Upload Document File">+ Upload</button>
                            <button class="btn btn-sm btn-primary" id="btn-new-doc" title="Create Rich Text Note">+ Note</button>
                        </div>
                    </div>

                    <!-- Search and Filters -->
                    <div class="doc-sidebar-search">
                        <input type="text" class="quick-add-input" id="doc-search-input" placeholder="Search docs &amp; tasks..." value="${this.escapeHtml(this.searchQuery)}" style="font-size: 0.8rem; padding: 0.35rem 0.6rem;" />
                    </div>
                    <div class="doc-filter-tabs">
                        <button class="doc-filter-tab ${this.activeFilter === 'all' ? 'active' : ''}" data-filter="all">All (${this.documents.length})</button>
                        <button class="doc-filter-tab ${this.activeFilter === 'rich_text' ? 'active' : ''}" data-filter="rich_text">Notes</button>
                        <button class="doc-filter-tab ${this.activeFilter === 'file' ? 'active' : ''}" data-filter="file">Files</button>
                    </div>

                    <div class="doc-sidebar-list">
                        ${docListHtml || '<div style="padding: 1.5rem; text-align: center; color: var(--text-muted); font-size: 0.85rem;">No matching documents.</div>'}
                    </div>
                </div>

                <!-- Document Content Canvas -->
                <div class="doc-main-panel">
                    ${mainContentHtml}
                </div>
            </div>

            <input type="file" id="global-doc-file-input" multiple style="display: none;" accept="image/*,.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.csv,.txt,.zip" />
        `;

        this.bindEvents();
    }

    renderFileViewer(doc) {
        const ext = (doc.file_extension || '').toLowerCase();
        const isImage = doc.mime_type?.startsWith('image/') || ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'].includes(ext);
        const isPdf = doc.mime_type === 'application/pdf' || ext === 'pdf';
        const sizeStr = doc.file_size ? this.formatFileSize(doc.file_size) : '';

        let viewerBody = '';
        if (isImage) {
            viewerBody = `
                <div class="file-preview-image-container">
                    <img src="${doc.file_path}" alt="${this.escapeHtml(doc.title)}" class="file-preview-image" />
                </div>
            `;
        } else if (isPdf) {
            viewerBody = `
                <div class="file-preview-pdf-container">
                    <iframe src="${doc.file_path}" class="file-preview-pdf-frame"></iframe>
                </div>
            `;
        } else {
            viewerBody = `
                <div class="doc-file-card-hero">
                    <div class="doc-badge-lg badge-${ext}">${ext.toUpperCase()}</div>
                    <div class="doc-file-hero-title">${this.escapeHtml(doc.title)}</div>
                    <div class="doc-file-hero-meta">${sizeStr} • ${doc.mime_type || 'Attached Document'}</div>
                    <div style="display: flex; gap: 0.75rem; justify-content: center; margin-top: 1.5rem;">
                        <a href="${this.getDownloadUrl(doc.id)}" target="_blank" class="btn btn-primary">
                            Download ${ext.toUpperCase()}
                        </a>
                        <a href="${doc.file_path}" target="_blank" class="btn btn-secondary">
                            Open Raw File
                        </a>
                    </div>
                </div>
            `;
        }

        return `
            <div class="doc-header-bar">
                <div style="display: flex; flex-direction: column; gap: 0.35rem; flex: 1;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span class="doc-badge badge-${ext}">${ext.toUpperCase()}</span>
                        <input type="text" class="doc-title-input" id="active-doc-title" value="${this.escapeHtml(doc.title)}" />
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <a href="${this.getDownloadUrl(doc.id)}" target="_blank" class="btn btn-sm btn-secondary" title="Download file">
                        Download
                    </a>
                </div>
            </div>

            <!-- Attached Task Banner -->
            ${this.renderAttachedTaskBanner(doc)}

            <div class="doc-viewer-scroll-area">
                ${viewerBody}
            </div>
        `;
    }

    renderBlockEditor(doc) {
        let blocks = [];
        if (doc.content) {
            try {
                const parsed = typeof doc.content === 'string' ? JSON.parse(doc.content) : doc.content;
                blocks = parsed.blocks || [];
            } catch (e) {
                blocks = [{ type: 'paragraph', text: doc.content }];
            }
        }

        if (blocks.length === 0) {
            blocks = [
                { type: 'heading1', text: doc.title || 'Untitled Spec' },
                { type: 'paragraph', text: 'Type "/" for commands or start writing notes here...' }
            ];
        }

        const blocksHtml = blocks.map(block => {
            const def = blockRegistry.get(block.type);
            return def ? def.render(block) : `<p class="doc-block doc-p" contenteditable="true">${this.escapeHtml(block.text || '')}</p>`;
        }).join('');

        return `
            <div class="doc-header-bar">
                <input type="text" class="doc-title-input" id="active-doc-title" value="${this.escapeHtml(doc.title)}" placeholder="Document Title..." />
                <div class="doc-save-status" id="doc-save-status">Saved</div>
            </div>

            <!-- Attached Task Banner -->
            ${this.renderAttachedTaskBanner(doc)}

            <div class="doc-blocks-container" id="doc-blocks-mount">
                ${blocksHtml}
            </div>
        `;
    }

    renderAttachedTaskBanner(doc) {
        if (doc.task_id && doc.task_title) {
            return `
                <div class="doc-attached-task-banner">
                    <div class="doc-banner-left">
                        <span class="doc-banner-label">Attached to Task:</span>
                        <button class="doc-task-link-btn" id="btn-open-attached-task" data-task-id="${doc.task_id}">
                            <span class="badge-tag">#${doc.task_id}</span>
                            <span style="font-weight: 500;">${this.escapeHtml(doc.task_title)}</span>
                        </button>
                    </div>
                    <div class="doc-banner-right">
                        <button class="btn btn-ghost btn-sm" id="btn-detach-task" title="Unlink task">Unlink Task</button>
                    </div>
                </div>
            `;
        }

        // Dropdown to attach a task
        const taskOptions = this.workspaceTasks.map(t => `
            <option value="${t.id}">#${t.id} ${this.escapeHtml(t.title)}</option>
        `).join('');

        return `
            <div class="doc-attached-task-banner unattached">
                <div class="doc-banner-left">
                    <span class="doc-banner-label">Task Attachment:</span>
                    <span style="font-size: 0.8rem; color: var(--text-muted);">Not attached to any task</span>
                </div>
                <div class="doc-banner-right" style="display: flex; gap: 0.5rem;">
                    <select class="field-inline-select" id="select-attach-task" style="font-size: 0.8rem; max-width: 200px;">
                        <option value="">-- Attach to Task --</option>
                        ${taskOptions}
                    </select>
                    <button class="btn btn-sm btn-secondary" id="btn-attach-task-submit">Attach</button>
                </div>
            </div>
        `;
    }

    bindEvents() {
        // Document selection
        this.container.querySelectorAll('.doc-list-item').forEach(item => {
            item.onclick = (e) => {
                if (e.target.classList.contains('doc-del-btn') || e.target.closest('.doc-attached-task-chip')) return;
                const docId = parseInt(item.dataset.id, 10);
                const found = this.documents.find(d => d.id === docId);
                if (found) {
                    this.activeDoc = found;
                    this.render();
                }
            };
        });

        // Click task chip in sidebar
        this.container.querySelectorAll('.doc-attached-task-chip').forEach(chip => {
            chip.onclick = (e) => {
                e.stopPropagation();
                const taskId = parseInt(chip.dataset.taskId, 10);
                if (taskId) {
                    taskDetailDrawer.open({ id: taskId, workspace_id: store.getState().activeWorkspaceId || 2 });
                }
            };
        });

        // Filter Tabs
        this.container.querySelectorAll('.doc-filter-tab').forEach(tab => {
            tab.onclick = () => {
                this.activeFilter = tab.dataset.filter;
                this.render();
            };
        });

        // Search Input
        const searchInput = this.container.querySelector('#doc-search-input');
        if (searchInput) {
            searchInput.oninput = (e) => {
                this.searchQuery = e.target.value;
                this.render();
                // Restore focus to search input
                const reSearch = this.container.querySelector('#doc-search-input');
                if (reSearch) {
                    reSearch.focus();
                    reSearch.setSelectionRange(reSearch.value.length, reSearch.value.length);
                }
            };
        }

        // Open Attached Task Button
        const openTaskBtn = this.container.querySelector('#btn-open-attached-task');
        if (openTaskBtn) {
            openTaskBtn.onclick = () => {
                const taskId = parseInt(openTaskBtn.dataset.taskId, 10);
                if (taskId) {
                    taskDetailDrawer.open({ id: taskId, workspace_id: store.getState().activeWorkspaceId || 2 });
                }
            };
        }

        // Attach Task Submit
        const attachTaskSubmit = this.container.querySelector('#btn-attach-task-submit');
        const attachTaskSelect = this.container.querySelector('#select-attach-task');
        if (attachTaskSubmit && attachTaskSelect) {
            attachTaskSubmit.onclick = async () => {
                const taskId = parseInt(attachTaskSelect.value, 10);
                if (!taskId || !this.activeDoc) return;
                try {
                    await api.post(`/api/v1/tasks/${taskId}/documents`, { document_id: this.activeDoc.id });
                    toast.success('Attached document to task');
                    await this.loadDocuments(false);
                } catch (err) {
                    toast.error('Failed to attach document to task');
                }
            };
        }

        // Detach Task Button
        const detachTaskBtn = this.container.querySelector('#btn-detach-task');
        if (detachTaskBtn && this.activeDoc?.task_id) {
            detachTaskBtn.onclick = async () => {
                try {
                    await api.patch(`/api/v1/documents/${this.activeDoc.id}`, { task_id: null });
                    toast.info('Document detached from task');
                    await this.loadDocuments(false);
                } catch (err) {
                    toast.error('Failed to detach document');
                }
            };
        }

        // Create New Rich Text Document
        const newDocBtn = this.container.querySelector('#btn-new-doc');
        if (newDocBtn) {
            newDocBtn.onclick = async () => {
                const state = store.getState();
                const wsId = state.activeWorkspaceId || 2;

                try {
                    const res = await api.post('/api/v1/documents', {
                        workspace_id: wsId,
                        title: 'Untitled Spec',
                        doc_type: 'rich_text',
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

        // Upload Document File Button
        const uploadDocBtn = this.container.querySelector('#btn-upload-doc');
        const fileInput = this.container.querySelector('#global-doc-file-input');
        if (uploadDocBtn && fileInput) {
            uploadDocBtn.onclick = () => fileInput.click();
            fileInput.onchange = async (e) => {
                const files = e.target.files;
                if (!files || files.length === 0) return;

                const wsId = store.getState().activeWorkspaceId || 2;
                const formData = new FormData();
                formData.append('workspace_id', wsId);

                for (let i = 0; i < files.length; i++) {
                    formData.append('files[]', files[i]);
                }

                try {
                    toast.info('Uploading document file(s)...');
                    const res = await api.upload('/api/v1/documents/upload', formData);
                    toast.success('Document file(s) uploaded');
                    fileInput.value = '';
                    await this.loadDocuments(false);
                    if (Array.isArray(res.data) && res.data.length > 0) {
                        this.activeDoc = res.data[0];
                    } else if (res.data) {
                        this.activeDoc = res.data;
                    }
                    this.render();
                } catch (err) {
                    toast.error(err.message || 'Upload failed');
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

        // Slash command triggers on editable blocks (for rich_text documents)
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
        const blockHtml = def ? def.render({ type, text: '' }) : '';
        
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

        if (this.activeDoc.doc_type === 'file') {
            try {
                await api.patch(`/api/v1/documents/${this.activeDoc.id}`, { title });
                this.activeDoc.title = title;
                if (statusEl) statusEl.textContent = 'Saved';
            } catch (err) {
                if (statusEl) statusEl.textContent = 'Error saving';
            }
            return;
        }

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

    formatFileSize(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    getDownloadUrl(docId) {
        const token = api.getToken();
        return `/api/v1/documents/${docId}/download${token ? `?token=${encodeURIComponent(token)}` : ''}`;
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
        eventBus.off('document:updated', this.onDocUpdated);
        this.container.innerHTML = '';
    }
}
