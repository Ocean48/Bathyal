/**
 * Sliding Task Detail Drawer & Subtasks Manager
 */
import { api } from '../core/api.js';
import { toast } from './Toast.js';
import { eventBus } from '../core/eventBus.js';
import { fieldRegistry } from '../core/fieldRegistry.js';

export class TaskDetailDrawer {
    constructor() {
        this.isOpen = false;
        this.task = null;
        this.subtasks = [];
        this.documents = [];
        this.availableDocs = [];
        this.customFields = [];
        this.container = null;
        this.backdrop = null;
        this.timerInterval = null;
        this.timerSeconds = 0;
        this.activeTimerLogId = null;
    }

    open(task) {
        this.task = task;
        this.isOpen = true;
        this.render();
        this.loadDetails();
    }

    close() {
        this.isOpen = false;
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
            this.timerInterval = null;
        }
        if (this.backdrop) {
            this.backdrop.remove();
            this.backdrop = null;
        }
    }

    async loadDetails() {
        try {
            const [taskRes, customFieldsRes, subtasksRes, docsRes, allDocsRes] = await Promise.all([
                api.get(`/api/v1/tasks/${this.task.id}`),
                api.get(`/api/v1/custom-fields`, { workspace_id: this.task.workspace_id, project_id: this.task.project_id || '' }),
                api.get('/api/v1/tasks', { workspace_id: this.task.workspace_id, parent_id: this.task.id }),
                api.get(`/api/v1/tasks/${this.task.id}/documents`),
                api.get('/api/v1/documents', { workspace_id: this.task.workspace_id })
            ]);

            this.task = taskRes.data || this.task;
            this.customFields = customFieldsRes.data || [];
            this.subtasks = subtasksRes.data || (this.task.subtasks || []);
            this.documents = docsRes.data || [];
            this.availableDocs = (allDocsRes.data || []).filter(d => !d.task_id || d.task_id != this.task.id);

            this.renderDrawerContent();
        } catch (err) {
            toast.error('Failed to load full task details');
        }
    }

    render() {
        if (this.backdrop) this.backdrop.remove();

        this.backdrop = document.createElement('div');
        this.backdrop.className = 'drawer-backdrop';
        this.backdrop.id = 'task-detail-backdrop';

        this.backdrop.innerHTML = `
            <div class="task-drawer">
                <div class="drawer-header">
                    <div class="drawer-header-left">
                        <span class="badge-tag" id="drawer-task-id">#${this.task.id}</span>
                        <span class="drawer-breadcrumb">${this.task.project_name ? this.escapeHtml(this.task.project_name) : 'Personal Task'}</span>
                    </div>
                    <div class="drawer-header-right">
                        <button class="btn btn-icon btn-ghost" id="drawer-close-btn" title="Close Drawer (Esc)">✕</button>
                    </div>
                </div>
                <div class="drawer-body" id="drawer-body-mount">
                    <div style="padding: 2rem; text-align: center; color: var(--text-muted);">Loading details...</div>
                </div>
            </div>
        `;

        document.body.appendChild(this.backdrop);

        this.backdrop.addEventListener('click', (e) => {
            if (e.target === this.backdrop) this.close();
        });

        const closeBtn = this.backdrop.querySelector('#drawer-close-btn');
        if (closeBtn) closeBtn.onclick = () => this.close();
    }

    renderDrawerContent() {
        const mount = this.backdrop.querySelector('#drawer-body-mount');
        if (!mount) return;

        const isCompleted = this.task.status_type === 'completed' || this.task.status_id == 3;
        const totalSub = this.subtasks.length;
        const completedSub = this.subtasks.filter(s => s.status_type === 'completed' || s.status_id == 3).length;
        const progressPct = totalSub > 0 ? Math.round((completedSub / totalSub) * 100) : 0;

        // Render attachments list
        const docsHtml = this.documents.map(doc => {
            const isFile = doc.doc_type === 'file';
            const isImg = isFile && (doc.mime_type?.startsWith('image/') || ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'].includes(doc.file_extension));
            const sizeStr = doc.file_size ? this.formatFileSize(doc.file_size) : '';
            const extBadge = (doc.file_extension || (isFile ? 'file' : 'doc')).toUpperCase();

            let iconOrThumb = '';
            if (isImg && doc.file_path) {
                iconOrThumb = `<img src="${doc.file_path}" alt="${this.escapeHtml(doc.title)}" class="attachment-thumb" />`;
            } else {
                iconOrThumb = `<div class="attachment-type-badge badge-${extBadge.toLowerCase()}">${extBadge}</div>`;
            }

            return `
                <div class="attachment-card" data-id="${doc.id}">
                    <div class="attachment-card-left">
                        ${iconOrThumb}
                        <div class="attachment-info">
                            <div class="attachment-title" title="${this.escapeHtml(doc.title)}">${this.escapeHtml(doc.title)}</div>
                            <div class="attachment-meta">
                                <span>${sizeStr ? sizeStr + ' • ' : ''}${doc.author_name ? this.escapeHtml(doc.author_name) : 'User'}</span>
                            </div>
                        </div>
                    </div>
                    <div class="attachment-actions">
                        ${isFile ? `<a href="${this.getDownloadUrl(doc.id)}" target="_blank" class="btn btn-icon btn-ghost btn-sm" title="Download Document">↓</a>` : ''}
                        <button class="btn btn-icon btn-ghost btn-sm drawer-doc-preview-btn" data-id="${doc.id}" title="Preview Document">👁</button>
                        <button class="btn btn-icon btn-ghost btn-sm drawer-doc-detach-btn" data-id="${doc.id}" title="Detach Document">✕</button>
                    </div>
                </div>
            `;
        }).join('');

        // Options for linking existing docs
        const linkDocOptions = this.availableDocs.map(d => `
            <option value="${d.id}">${this.escapeHtml(d.title)} (${d.doc_type === 'file' ? (d.file_extension || 'file').toUpperCase() : 'Doc'})</option>
        `).join('');

        mount.innerHTML = `
            <!-- Title & Status -->
            <div class="drawer-section">
                <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                    <button class="task-checkbox ${isCompleted ? 'completed' : ''}" id="drawer-toggle-complete" style="margin-top: 4px;">
                        ${isCompleted ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>' : ''}
                    </button>
                    <input type="text" class="drawer-title-input" id="drawer-task-title" value="${this.escapeHtml(this.task.title)}" placeholder="Task title..." />
                </div>
            </div>

            <!-- Meta Attributes Grid -->
            <div class="drawer-meta-grid">
                <div class="drawer-meta-row">
                    <span class="drawer-meta-label">Status</span>
                    <select class="field-inline-select" id="drawer-status-select">
                        <option value="1" ${this.task.status_id == 1 ? 'selected' : ''}>To Do</option>
                        <option value="2" ${this.task.status_id == 2 ? 'selected' : ''}>In Progress</option>
                        <option value="3" ${this.task.status_id == 3 ? 'selected' : ''}>Completed</option>
                    </select>
                </div>
                <div class="drawer-meta-row">
                    <span class="drawer-meta-label">Priority</span>
                    <select class="field-inline-select" id="drawer-priority-select">
                        <option value="none" ${this.task.priority === 'none' ? 'selected' : ''}>None</option>
                        <option value="low" ${this.task.priority === 'low' ? 'selected' : ''}>Low</option>
                        <option value="medium" ${this.task.priority === 'medium' ? 'selected' : ''}>Medium</option>
                        <option value="high" ${this.task.priority === 'high' ? 'selected' : ''}>High</option>
                        <option value="urgent" ${this.task.priority === 'urgent' ? 'selected' : ''}>Urgent</option>
                    </select>
                </div>
                <div class="drawer-meta-row">
                    <span class="drawer-meta-label">Start Date &amp; Time</span>
                    <input type="datetime-local" class="field-inline-input" id="drawer-start-date" value="${this.formatForInput(this.task.start_date)}" />
                </div>
                <div class="drawer-meta-row">
                    <span class="drawer-meta-label">Due Date &amp; Time</span>
                    <input type="datetime-local" class="field-inline-input" id="drawer-due-date" value="${this.formatForInput(this.task.due_date)}" />
                </div>
                <div class="drawer-meta-row">
                    <span class="drawer-meta-label">Estimate (hrs)</span>
                    <input type="number" step="0.5" class="field-inline-input" id="drawer-estimate-hours" value="${this.task.estimated_hours || ''}" placeholder="0.0" style="width: 90px;" />
                </div>
            </div>

            <!-- Description -->
            <div class="drawer-section">
                <div class="drawer-section-title">Description</div>
                <textarea class="drawer-description-input" id="drawer-description" placeholder="Add detailed notes or requirements...">${this.escapeHtml(this.task.description || '')}</textarea>
            </div>

            <!-- Documents & Attachments Section -->
            <div class="drawer-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <div class="drawer-section-title" style="margin-bottom: 0;">Documents &amp; Attachments (${this.documents.length})</div>
                    <label class="btn btn-sm btn-primary" for="drawer-file-picker-input" style="cursor: pointer; margin: 0;">
                        + Upload
                    </label>
                    <input type="file" id="drawer-file-picker-input" multiple style="display: none;" accept="image/*,.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.csv,.txt,.zip" />
                </div>

                <!-- Drag-and-drop Dropzone -->
                <div class="attachment-dropzone" id="drawer-upload-dropzone">
                    <div class="dropzone-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                    </div>
                    <div class="dropzone-text">Drop images, PDFs, Word, PowerPoint, or docs here to attach</div>
                </div>

                <!-- Attached Documents List -->
                <div class="attachment-list" id="drawer-attachment-list">
                    ${docsHtml || '<div style="font-size: 0.8rem; color: var(--text-muted); padding: 0.5rem 0;">No documents attached yet.</div>'}
                </div>

                <!-- Link Existing Doc Selector -->
                ${this.availableDocs.length > 0 ? `
                    <div class="link-doc-row" style="display: flex; gap: 0.5rem; margin-top: 0.75rem;">
                        <select class="field-inline-select" id="drawer-link-doc-select" style="flex: 1;">
                            <option value="">-- Link Existing Workspace Document --</option>
                            ${linkDocOptions}
                        </select>
                        <button class="btn btn-sm btn-secondary" id="drawer-link-doc-btn">Link</button>
                    </div>
                ` : ''}
            </div>

            <!-- Subtask Checklist -->
            <div class="drawer-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <div class="drawer-section-title" style="margin-bottom: 0;">Subtasks (${completedSub}/${totalSub})</div>
                    <span style="font-size: 0.75rem; color: var(--text-muted);">${progressPct}%</span>
                </div>
                <div class="progress-bar-track">
                    <div class="progress-bar-fill" style="width: ${progressPct}%;"></div>
                </div>
                <div class="subtask-list" id="drawer-subtask-list">
                    ${this.subtasks.map(sub => `
                        <div class="subtask-item ${sub.status_id == 3 ? 'completed' : ''}" data-id="${sub.id}">
                            <input type="checkbox" class="subtask-chk" data-id="${sub.id}" ${sub.status_id == 3 ? 'checked' : ''} />
                            <span class="subtask-title">${this.escapeHtml(sub.title)}</span>
                            <button class="btn-icon btn-ghost btn-sm subtask-del-btn" data-id="${sub.id}">✕</button>
                        </div>
                    `).join('')}
                </div>
                <div class="subtask-add-row">
                    <input type="text" class="quick-add-input" id="drawer-new-subtask-input" placeholder="Add subtask and press Enter..." style="font-size: 0.85rem;" />
                </div>
            </div>

            <!-- Time Tracker Widget -->
            <div class="drawer-section">
                <div class="drawer-section-title">Time Tracking</div>
                <div class="time-tracker-box">
                    <div class="timer-display" id="drawer-timer-display">00:00:00</div>
                    <button class="btn btn-sm btn-primary" id="drawer-timer-toggle">Start Timer</button>
                </div>
            </div>
        `;

        this.bindDrawerEvents();
    }

    bindDrawerEvents() {
        const titleInput = this.backdrop.querySelector('#drawer-task-title');
        const descInput = this.backdrop.querySelector('#drawer-description');
        const statusSelect = this.backdrop.querySelector('#drawer-status-select');
        const prioSelect = this.backdrop.querySelector('#drawer-priority-select');
        const startDateInput = this.backdrop.querySelector('#drawer-start-date');
        const dueDateInput = this.backdrop.querySelector('#drawer-due-date');
        const estimateInput = this.backdrop.querySelector('#drawer-estimate-hours');
        const toggleCompleteBtn = this.backdrop.querySelector('#drawer-toggle-complete');
        const newSubtaskInput = this.backdrop.querySelector('#drawer-new-subtask-input');
        const filePicker = this.backdrop.querySelector('#drawer-file-picker-input');
        const dropzone = this.backdrop.querySelector('#drawer-upload-dropzone');
        const linkDocBtn = this.backdrop.querySelector('#drawer-link-doc-btn');
        const linkDocSelect = this.backdrop.querySelector('#drawer-link-doc-select');

        const autoSave = async () => {
            const formatForDb = (val) => {
                if (!val) return null;
                const clean = val.replace('T', ' ');
                return clean.length === 16 ? `${clean}:00` : (clean.length === 10 ? `${clean} 00:00:00` : clean);
            };

            const updates = {
                title: titleInput.value.trim() || this.task.title,
                description: descInput.value.trim() || null,
                status_id: parseInt(statusSelect.value, 10),
                priority: prioSelect.value,
                start_date: formatForDb(startDateInput.value),
                due_date: formatForDb(dueDateInput.value),
                estimated_hours: estimateInput.value ? parseFloat(estimateInput.value) : null,
            };

            try {
                const res = await api.patch(`/api/v1/tasks/${this.task.id}`, updates);
                this.task = res.data;
                toast.success('Task details saved');
                eventBus.emit('task:created', this.task);
            } catch (err) {
                toast.error('Failed to save task updates');
            }
        };

        titleInput.onblur = autoSave;
        descInput.onblur = autoSave;
        statusSelect.onchange = autoSave;
        prioSelect.onchange = autoSave;
        startDateInput.onchange = autoSave;
        dueDateInput.onchange = autoSave;
        estimateInput.onblur = autoSave;

        if (toggleCompleteBtn) {
            toggleCompleteBtn.onclick = async () => {
                const isCompleted = this.task.status_type === 'completed' || this.task.status_id == 3;
                const newStatus = isCompleted ? 1 : 3;
                statusSelect.value = String(newStatus);
                await autoSave();
                this.renderDrawerContent();
            };
        }

        // File Upload Handler
        const handleFiles = async (fileList) => {
            if (!fileList || fileList.length === 0) return;

            const formData = new FormData();
            formData.append('task_id', this.task.id);
            formData.append('workspace_id', this.task.workspace_id);
            if (this.task.project_id) {
                formData.append('project_id', this.task.project_id);
            }

            for (let i = 0; i < fileList.length; i++) {
                formData.append('files[]', fileList[i]);
            }

            try {
                toast.info('Uploading document(s)...');
                await api.upload('/api/v1/documents/upload', formData);
                toast.success('Document(s) uploaded and attached');
                this.loadDetails();
                eventBus.emit('document:updated');
            } catch (err) {
                toast.error(err.message || 'Upload failed');
            }
        };

        if (filePicker) {
            filePicker.onchange = (e) => {
                handleFiles(e.target.files);
                filePicker.value = '';
            };
        }

        if (dropzone) {
            dropzone.onclick = () => filePicker?.click();
            dropzone.ondragover = (e) => {
                e.preventDefault();
                dropzone.classList.add('drag-active');
            };
            dropzone.ondragleave = () => {
                dropzone.classList.remove('drag-active');
            };
            dropzone.ondrop = (e) => {
                e.preventDefault();
                dropzone.classList.remove('drag-active');
                if (e.dataTransfer?.files?.length) {
                    handleFiles(e.dataTransfer.files);
                }
            };
        }

        // Link Existing Doc
        if (linkDocBtn && linkDocSelect) {
            linkDocBtn.onclick = async () => {
                const docId = parseInt(linkDocSelect.value, 10);
                if (!docId) {
                    toast.error('Please select a document to link');
                    return;
                }
                try {
                    await api.post(`/api/v1/tasks/${this.task.id}/documents`, { document_id: docId });
                    toast.success('Document linked to task');
                    this.loadDetails();
                    eventBus.emit('document:updated');
                } catch (err) {
                    toast.error('Failed to link document');
                }
            };
        }

        // Detach Document
        this.backdrop.querySelectorAll('.drawer-doc-detach-btn').forEach(btn => {
            btn.onclick = async (e) => {
                e.stopPropagation();
                const docId = btn.dataset.id;
                try {
                    await api.delete(`/api/v1/tasks/${this.task.id}/documents/${docId}`);
                    toast.info('Document detached from task');
                    this.loadDetails();
                    eventBus.emit('document:updated');
                } catch (err) {
                    toast.error('Failed to detach document');
                }
            };
        });

        // Preview Document Modal
        this.backdrop.querySelectorAll('.drawer-doc-preview-btn').forEach(btn => {
            btn.onclick = (e) => {
                e.stopPropagation();
                const docId = parseInt(btn.dataset.id, 10);
                const doc = this.documents.find(d => d.id === docId);
                if (doc) {
                    this.showDocumentPreviewModal(doc);
                }
            };
        });

        // Subtask Create
        if (newSubtaskInput) {
            newSubtaskInput.onkeydown = async (e) => {
                if (e.key === 'Enter' && newSubtaskInput.value.trim()) {
                    const text = newSubtaskInput.value.trim();
                    newSubtaskInput.value = '';
                    try {
                        await api.post('/api/v1/tasks', {
                            title: text,
                            workspace_id: this.task.workspace_id,
                            project_id: this.task.project_id,
                            parent_id: this.task.id,
                        });
                        toast.success('Subtask added');
                        this.loadDetails();
                        eventBus.emit('task:created');
                    } catch (err) {
                        toast.error('Failed to add subtask');
                    }
                }
            };
        }

        // Subtask Toggle
        this.backdrop.querySelectorAll('.subtask-chk').forEach(chk => {
            chk.onchange = async () => {
                const subId = chk.dataset.id;
                const newStatus = chk.checked ? 3 : 1;
                try {
                    await api.patch(`/api/v1/tasks/${subId}`, { status_id: newStatus });
                    this.loadDetails();
                    eventBus.emit('task:created');
                } catch (err) {
                    toast.error('Failed to update subtask');
                }
            };
        });

        // Subtask Delete
        this.backdrop.querySelectorAll('.subtask-del-btn').forEach(btn => {
            btn.onclick = async () => {
                const subId = btn.dataset.id;
                try {
                    await api.delete(`/api/v1/tasks/${subId}`);
                    toast.info('Subtask deleted');
                    this.loadDetails();
                    eventBus.emit('task:created');
                } catch (err) {
                    toast.error('Failed to delete subtask');
                }
            };
        });

        // Time Tracker Toggle
        const timerBtn = this.backdrop.querySelector('#drawer-timer-toggle');
        const timerDisplay = this.backdrop.querySelector('#drawer-timer-display');

        if (timerBtn) {
            timerBtn.onclick = async () => {
                if (this.timerInterval) {
                    // Stop timer
                    clearInterval(this.timerInterval);
                    this.timerInterval = null;
                    timerBtn.textContent = 'Start Timer';
                    timerBtn.className = 'btn btn-sm btn-primary';

                    if (this.activeTimerLogId) {
                        await api.post(`/api/v1/time-logs/${this.activeTimerLogId}/stop`);
                        toast.success('Time logged successfully');
                        this.activeTimerLogId = null;
                    }
                } else {
                    // Start timer
                    try {
                        const res = await api.post('/api/v1/time-logs', { task_id: this.task.id });
                        this.activeTimerLogId = res.data?.id;
                        this.timerSeconds = 0;
                        timerBtn.textContent = 'Stop Timer';
                        timerBtn.className = 'btn btn-sm btn-secondary';

                        this.timerInterval = setInterval(() => {
                            this.timerSeconds++;
                            const hrs = String(Math.floor(this.timerSeconds / 3600)).padStart(2, '0');
                            const mins = String(Math.floor((this.timerSeconds % 3600) / 60)).padStart(2, '0');
                            const secs = String(this.timerSeconds % 60).padStart(2, '0');
                            if (timerDisplay) timerDisplay.textContent = `${hrs}:${mins}:${secs}`;
                        }, 1000);
                    } catch (err) {
                        toast.error('Failed to start timer');
                    }
                }
            };
        }
    }

    showDocumentPreviewModal(doc) {
        const modalBackdrop = document.createElement('div');
        modalBackdrop.className = 'modal-backdrop';
        modalBackdrop.style.zIndex = '99999';

        const isFile = doc.doc_type === 'file';
        const ext = (doc.file_extension || '').toLowerCase();
        const isImage = isFile && (doc.mime_type?.startsWith('image/') || ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'].includes(ext));
        const isPdf = isFile && (doc.mime_type === 'application/pdf' || ext === 'pdf');

        let previewBody = '';
        if (isImage) {
            previewBody = `
                <div style="text-align: center; max-height: 70vh; overflow: auto;">
                    <img src="${doc.file_path}" alt="${this.escapeHtml(doc.title)}" style="max-width: 100%; border-radius: var(--radius-md); box-shadow: var(--shadow-md);" />
                </div>
            `;
        } else if (isPdf) {
            previewBody = `
                <div style="height: 70vh;">
                    <iframe src="${doc.file_path}" style="width: 100%; height: 100%; border: none; border-radius: var(--radius-md);"></iframe>
                </div>
            `;
        } else if (isFile) {
            previewBody = `
                <div class="doc-file-showcase" style="padding: 2rem; text-align: center;">
                    <div class="attachment-type-badge badge-${ext}" style="font-size: 1.5rem; width: 64px; height: 64px; margin: 0 auto 1rem; border-radius: 12px; display: flex; align-items: center; justify-content: center;">${ext.toUpperCase()}</div>
                    <div style="font-weight: 600; font-size: 1.1rem; margin-bottom: 0.5rem;">${this.escapeHtml(doc.title)}</div>
                    <div style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem;">${doc.file_size ? this.formatFileSize(doc.file_size) : ''} • ${doc.mime_type || 'Document file'}</div>
                    <a href="${this.getDownloadUrl(doc.id)}" target="_blank" class="btn btn-primary">Download ${ext.toUpperCase()} Document</a>
                </div>
            `;
        } else {
            let contentText = '';
            try {
                const parsed = typeof doc.content === 'string' ? JSON.parse(doc.content) : doc.content;
                if (parsed?.blocks) {
                    contentText = parsed.blocks.map(b => `<p>${this.escapeHtml(b.text || '')}</p>`).join('');
                } else {
                    contentText = this.escapeHtml(String(doc.content || ''));
                }
            } catch (e) {
                contentText = this.escapeHtml(String(doc.content || ''));
            }

            previewBody = `
                <div style="max-height: 60vh; overflow-y: auto; padding: 1rem; background: var(--bg-surface); border-radius: var(--radius-md); line-height: 1.6;">
                    ${contentText}
                </div>
            `;
        }

        modalBackdrop.innerHTML = `
            <div class="modal-dialog" style="max-width: 800px; width: 90%;">
                <div class="modal-header">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="font-weight: 600;">${this.escapeHtml(doc.title)}</span>
                    </div>
                    <button class="btn btn-icon btn-ghost" id="preview-modal-close">✕</button>
                </div>
                <div class="modal-body">
                    ${previewBody}
                </div>
                <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                    ${isFile ? `<a href="${this.getDownloadUrl(doc.id)}" target="_blank" class="btn btn-secondary btn-sm">Download</a>` : ''}
                    <button class="btn btn-primary btn-sm" id="preview-modal-ok">Close</button>
                </div>
            </div>
        `;

        document.body.appendChild(modalBackdrop);

        const close = () => modalBackdrop.remove();
        modalBackdrop.querySelector('#preview-modal-close').onclick = close;
        modalBackdrop.querySelector('#preview-modal-ok').onclick = close;
        modalBackdrop.onclick = (e) => {
            if (e.target === modalBackdrop) close();
        };
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

    formatForInput(val) {
        if (!val) return '';
        // Convert MySQL 'YYYY-MM-DD HH:MM:SS' or ISO string to 'YYYY-MM-DDTHH:MM'
        const parts = String(val).split(' ');
        if (parts.length >= 2) {
            return `${parts[0]}T${parts[1].substring(0, 5)}`;
        }
        if (val.includes('T')) {
            return val.substring(0, 16);
        }
        return `${val}T00:00`;
    }

    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}

export const taskDetailDrawer = new TaskDetailDrawer();
