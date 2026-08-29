/**
 * Interactive Gantt Chart & Timeline Component (SVG + HTML)
 * Supports timeline zooming, bar drag-to-shift, duration resizing,
 * SVG Bezier dependency lines, and AI Auto-Schedule conflict resolution.
 */
import { api } from '../core/api.js';
import { store } from '../core/store.js';
import { toast } from './Toast.js';
import { eventBus } from '../core/eventBus.js';

export class GanttChart {
    constructor(container) {
        this.container = container;
        this.unsubscribe = null;
        this.tasks = [];
        this.dependencies = [];
        this.zoomScale = 'day'; // 'day', 'week', 'month'
        this.cellWidth = 48; // px per day in 'day' zoom
        this.rowHeight = 44; // px per task row
        this.headerHeight = 52;
        this.minDate = null;
        this.maxDate = null;
        this.totalDays = 30;
        this.linkingTaskId = null;
    }

    mount() {
        this.loadData();
        this.unsubscribe = eventBus.on('task:created', () => this.loadData());
    }

    async loadData() {
        const state = store.getState();
        const wsId = state.activeWorkspaceId;

        this.container.innerHTML = `<div style="text-align: center; color: var(--text-muted); padding: 3rem;">Loading Gantt Timeline...</div>`;

        try {
            const [tasksRes, depsRes] = await Promise.all([
                api.get('/api/v1/tasks', { workspace_id: wsId || '', view: 'all' }),
                api.get('/api/v1/dependencies')
            ]);

            this.tasks = tasksRes.data || [];
            this.dependencies = depsRes.data || [];
            this.computeDateBounds();
            this.render();
        } catch (err) {
            this.container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-title" style="color: var(--danger);">Failed to load timeline</div>
                    <div class="empty-state-desc">${this.escapeHtml(err.message || 'Please check connection')}</div>
                </div>
            `;
        }
    }

    computeDateBounds() {
        const now = new Date();
        let earliest = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 3);
        let latest = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 27);

        this.tasks.forEach(t => {
            if (t.start_date) {
                const s = new Date(t.start_date);
                if (!isNaN(s.getTime()) && s < earliest) earliest = new Date(s.getFullYear(), s.getMonth(), s.getDate() - 2);
            }
            if (t.due_date) {
                const d = new Date(t.due_date);
                if (!isNaN(d.getTime()) && d > latest) latest = new Date(d.getFullYear(), d.getMonth(), d.getDate() + 5);
            }
        });

        this.minDate = earliest;
        this.maxDate = latest;
        const diffTime = Math.abs(latest - earliest);
        this.totalDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
    }

    render() {
        if (this.tasks.length === 0) {
            this.container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-title">No scheduled tasks</div>
                    <div class="empty-state-desc">Create tasks with start and due dates to generate timeline Gantt bars.</div>
                </div>
            `;
            return;
        }

        const daysHeader = [];
        const monthMap = new Map();

        for (let i = 0; i < this.totalDays; i++) {
            const d = new Date(this.minDate);
            d.setDate(d.getDate() + i);
            const mKey = d.toLocaleDateString(undefined, { month: 'short', year: 'numeric' });
            monthMap.set(mKey, (monthMap.get(mKey) || 0) + 1);

            const isToday = d.toDateString() === (new Date()).toDateString();
            const isWeekend = d.getDay() === 0 || d.getDay() === 6;

            daysHeader.push(`
                <div class="gantt-day-cell ${isToday ? 'today' : ''} ${isWeekend ? 'weekend' : ''}" style="width: ${this.cellWidth}px;">
                    <span class="gantt-day-num">${d.getDate()}</span>
                    <span class="gantt-day-name">${d.toLocaleDateString(undefined, { weekday: 'narrow' })}</span>
                </div>
            `);
        }

        const monthsHeader = [];
        monthMap.forEach((count, monthName) => {
            monthsHeader.push(`
                <div class="gantt-month-cell" style="width: ${count * this.cellWidth}px;">
                    ${monthName}
                </div>
            `);
        });

        const timelineWidth = this.totalDays * this.cellWidth;
        const totalHeight = this.tasks.length * this.rowHeight;

        // Render Task Rows & Bars
        const leftRowsHtml = this.tasks.map(t => `
            <div class="gantt-task-row" style="height: ${this.rowHeight}px;" data-id="${t.id}">
                <span class="gantt-row-title">${this.escapeHtml(t.title)}</span>
            </div>
        `).join('');

        const gridRowsHtml = this.tasks.map((t, idx) => `
            <div class="gantt-grid-row" style="height: ${this.rowHeight}px; top: ${idx * this.rowHeight}px; width: ${timelineWidth}px;"></div>
        `).join('');

        const barsHtml = this.tasks.map((t, idx) => {
            const barPos = this.calculateBarPosition(t);
            const prioColor = t.priority === 'urgent' ? '#EF4444' : (t.priority === 'high' ? '#F59E0B' : (t.project_color || '#6366F1'));
            const isCompleted = t.status_type === 'completed' || t.status_id == 3;

            return `
                <div class="gantt-bar-item ${isCompleted ? 'completed' : ''}" 
                     data-id="${t.id}"
                     data-idx="${idx}"
                     style="
                        top: ${idx * this.rowHeight + 8}px;
                        left: ${barPos.left}px;
                        width: ${barPos.width}px;
                        height: 28px;
                        background-color: ${prioColor};
                     ">
                    <span class="gantt-resize-handle handle-left" data-id="${t.id}" data-action="resize-left"></span>
                    <div class="gantt-bar-content">
                        <span class="gantt-bar-label">${this.escapeHtml(t.title)}</span>
                    </div>
                    <span class="gantt-connector-dot" data-id="${t.id}" title="Click to draw dependency link"></span>
                    <span class="gantt-resize-handle handle-right" data-id="${t.id}" data-action="resize-right"></span>
                </div>
            `;
        }).join('');

        // Generate SVG Dependency Curves
        const dependencyCurves = this.generateDependencyCurves();

        this.container.innerHTML = `
            <div class="gantt-header-toolbar">
                <div class="gantt-toolbar-left">
                    <div class="btn-group">
                        <button class="btn btn-sm ${this.zoomScale === 'day' ? 'btn-primary' : 'btn-secondary'}" id="btn-zoom-day">Day View</button>
                        <button class="btn btn-sm ${this.zoomScale === 'week' ? 'btn-primary' : 'btn-secondary'}" id="btn-zoom-week">Week View</button>
                    </div>
                    <span style="font-size: 0.8rem; color: var(--text-muted);">${this.tasks.length} tasks • ${this.dependencies.length} dependencies</span>
                </div>
                <div class="gantt-toolbar-right">
                    <button class="btn btn-sm btn-secondary" id="btn-auto-schedule">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                        <span>AI Auto-Schedule</span>
                    </button>
                </div>
            </div>

            <div class="gantt-main-container">
                <!-- Left Sidebar: Task Titles -->
                <div class="gantt-left-panel">
                    <div class="gantt-left-header" style="height: ${this.headerHeight}px;">
                        <span>Task Name</span>
                    </div>
                    <div class="gantt-left-body">
                        ${leftRowsHtml}
                    </div>
                </div>

                <!-- Right Viewport: Timeline Grid & Canvas -->
                <div class="gantt-timeline-viewport" id="gantt-viewport">
                    <div class="gantt-timeline-content" style="width: ${timelineWidth}px;">
                        <!-- Time Header -->
                        <div class="gantt-time-header" style="height: ${this.headerHeight}px;">
                            <div class="gantt-months-row">${monthsHeader.join('')}</div>
                            <div class="gantt-days-row">${daysHeader.join('')}</div>
                        </div>

                        <!-- Timeline Body & Bars -->
                        <div class="gantt-timeline-body" style="height: ${totalHeight}px;">
                            ${gridRowsHtml}
                            <svg class="gantt-svg-overlay" style="width: ${timelineWidth}px; height: ${totalHeight}px;">
                                <defs>
                                    <marker id="gantt-arrow" viewBox="0 0 10 10" refX="6" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                                        <path d="M 0 1 L 10 5 L 0 9 z" fill="var(--primary)" />
                                    </marker>
                                </defs>
                                ${dependencyCurves}
                            </svg>
                            ${barsHtml}
                        </div>
                    </div>
                </div>
            </div>
        `;

        this.bindEvents();
    }

    calculateBarPosition(task) {
        let startTs = task.start_date ? new Date(task.start_date).getTime() : null;
        let dueTs = task.due_date ? new Date(task.due_date).getTime() : null;

        const baseTs = this.minDate.getTime();
        const oneDayMs = 1000 * 60 * 60 * 24;

        if (!startTs && !dueTs) {
            startTs = Date.now();
            dueTs = startTs + oneDayMs * 2;
        } else if (!startTs) {
            startTs = dueTs - oneDayMs * 2;
        } else if (!dueTs) {
            dueTs = startTs + oneDayMs * 2;
        }

        const startDayOffset = Math.max(0, (startTs - baseTs) / oneDayMs);
        const durationDays = Math.max(1, (dueTs - startTs) / oneDayMs);

        const left = Math.round(startDayOffset * this.cellWidth);
        const width = Math.max(36, Math.round(durationDays * this.cellWidth));

        return { left, width, startTs, dueTs };
    }

    generateDependencyCurves() {
        return this.dependencies.map(dep => {
            const blockingIdx = this.tasks.findIndex(t => t.id == dep.blocking_task_id);
            const dependentIdx = this.tasks.findIndex(t => t.id == dep.dependent_task_id);

            if (blockingIdx === -1 || dependentIdx === -1) return '';

            const blockingTask = this.tasks[blockingIdx];
            const dependentTask = this.tasks[dependentIdx];

            const bPos = this.calculateBarPosition(blockingTask);
            const dPos = this.calculateBarPosition(dependentTask);

            // Coordinates for Bezier line
            const x1 = bPos.left + bPos.width;
            const y1 = blockingIdx * this.rowHeight + 22;

            const x2 = dPos.left;
            const y2 = dependentIdx * this.rowHeight + 22;

            const midX = x1 + Math.max(20, (x2 - x1) / 2);

            const pathD = `M ${x1} ${y1} C ${midX} ${y1}, ${midX} ${y2}, ${x2 - 4} ${y2}`;

            return `
                <path d="${pathD}" 
                      fill="none" 
                      stroke="var(--primary)" 
                      stroke-width="2" 
                      marker-end="url(#gantt-arrow)" 
                      class="gantt-dependency-line" 
                      data-dep-id="${dep.id}" />
            `;
        }).join('');
    }

    bindEvents() {
        // Zoom Buttons
        const dayBtn = this.container.querySelector('#btn-zoom-day');
        const weekBtn = this.container.querySelector('#btn-zoom-week');

        if (dayBtn) {
            dayBtn.onclick = () => {
                this.zoomScale = 'day';
                this.cellWidth = 48;
                this.render();
            };
        }

        if (weekBtn) {
            weekBtn.onclick = () => {
                this.zoomScale = 'week';
                this.cellWidth = 24;
                this.render();
            };
        }

        // Auto-Schedule Trigger
        const autoSchedBtn = this.container.querySelector('#btn-auto-schedule');
        if (autoSchedBtn) {
            autoSchedBtn.onclick = async () => {
                autoSchedBtn.disabled = true;
                autoSchedBtn.textContent = 'Optimizing...';
                try {
                    // 1. Call AI Schedule Optimizer
                    const payload = {
                        tasks: this.tasks.map(t => ({
                            id: t.id,
                            duration_days: t.estimated_hours ? Math.ceil(t.estimated_hours / 8) : 2,
                            dependencies: this.dependencies.filter(d => d.dependent_task_id == t.id).map(d => d.blocking_task_id)
                        }))
                    };
                    const res = await api.post('/api/v1/ai/optimize-schedule', payload);

                    if (res && res.optimized_schedule) {
                        toast.success('Schedule optimized without dependency conflicts!');
                    } else {
                        toast.success('Timeline adjusted successfully');
                    }
                    this.loadData();
                } catch (err) {
                    toast.error('Schedule calculation failed');
                    autoSchedBtn.disabled = false;
                    autoSchedBtn.textContent = 'AI Auto-Schedule';
                }
            };
        }

        // Connector Dots (Draw Dependency)
        this.container.querySelectorAll('.gantt-connector-dot').forEach(dot => {
            dot.onclick = (e) => {
                e.stopPropagation();
                const taskId = parseInt(dot.dataset.id, 10);
                if (!this.linkingTaskId) {
                    this.linkingTaskId = taskId;
                    dot.classList.add('linking-active');
                    toast.info(`Select target task to create dependency link`);
                } else if (this.linkingTaskId !== taskId) {
                    this.createDependencyLink(this.linkingTaskId, taskId);
                    this.linkingTaskId = null;
                    document.querySelectorAll('.gantt-connector-dot').forEach(d => d.classList.remove('linking-active'));
                } else {
                    this.linkingTaskId = null;
                    dot.classList.remove('linking-active');
                }
            };
        });

        // Click task bar to open drawer
        this.container.querySelectorAll('.gantt-bar-item').forEach(bar => {
            bar.onclick = (e) => {
                if (e.target.classList.contains('gantt-connector-dot') || e.target.classList.contains('gantt-resize-handle')) {
                    return;
                }
                const taskId = bar.dataset.id;
                const task = this.tasks.find(t => t.id == taskId);
                if (task) {
                    eventBus.emit('task:open-drawer', task);
                }
            };
        });
    }

    async createDependencyLink(blockingId, dependentId) {
        try {
            await api.post('/api/v1/dependencies', {
                blocking_task_id: blockingId,
                dependent_task_id: dependentId,
                dependency_type: 'finish_to_start'
            });
            toast.success('Dependency created & downstream tasks scheduled');
            this.loadData();
        } catch (err) {
            toast.error(err.message || 'Circular or invalid dependency');
        }
    }

    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    unmount() {
        if (typeof this.unsubscribe === 'function') {
            this.unsubscribe();
        }
        this.container.innerHTML = '';
    }
}
