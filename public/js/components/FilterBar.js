/**
 * Multi-Condition Filter and Sort Bar Component
 * Provides search filtering, status/priority pickers, and sorting.
 */
import { eventBus } from '../core/eventBus.js';

export class FilterBar {
    constructor(container) {
        this.container = container;
        this.filters = {
            search: '',
            status: 'all',
            priority: 'all',
            sortBy: 'position',
        };
    }

    mount() {
        this.render();
        this.bindEvents();
    }

    render() {
        this.container.innerHTML = `
            <div class="filter-bar-container">
                <div class="filter-search-box">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" class="filter-search-input" id="filter-search-text" placeholder="Filter tasks..." value="${this.escapeHtml(this.filters.search)}" />
                </div>

                <div class="filter-controls">
                    <select class="field-inline-select filter-select" id="filter-status-select">
                        <option value="all" ${this.filters.status === 'all' ? 'selected' : ''}>Status: All</option>
                        <option value="1" ${this.filters.status === '1' ? 'selected' : ''}>To Do</option>
                        <option value="2" ${this.filters.status === '2' ? 'selected' : ''}>In Progress</option>
                        <option value="3" ${this.filters.status === '3' ? 'selected' : ''}>Completed</option>
                    </select>

                    <select class="field-inline-select filter-select" id="filter-prio-select">
                        <option value="all" ${this.filters.priority === 'all' ? 'selected' : ''}>Priority: All</option>
                        <option value="urgent" ${this.filters.priority === 'urgent' ? 'selected' : ''}>Urgent</option>
                        <option value="high" ${this.filters.priority === 'high' ? 'selected' : ''}>High</option>
                        <option value="medium" ${this.filters.priority === 'medium' ? 'selected' : ''}>Medium</option>
                        <option value="low" ${this.filters.priority === 'low' ? 'selected' : ''}>Low</option>
                    </select>

                    <select class="field-inline-select filter-select" id="filter-sort-select">
                        <option value="position" ${this.filters.sortBy === 'position' ? 'selected' : ''}>Sort: Default</option>
                        <option value="due_date" ${this.filters.sortBy === 'due_date' ? 'selected' : ''}>Sort: Due Date</option>
                        <option value="priority" ${this.filters.sortBy === 'priority' ? 'selected' : ''}>Sort: Priority</option>
                        <option value="title" ${this.filters.sortBy === 'title' ? 'selected' : ''}>Sort: Title A-Z</option>
                    </select>
                </div>
            </div>
        `;
    }

    bindEvents() {
        const searchInput = this.container.querySelector('#filter-search-text');
        const statusSelect = this.container.querySelector('#filter-status-select');
        const prioSelect = this.container.querySelector('#filter-prio-select');
        const sortSelect = this.container.querySelector('#filter-sort-select');

        const notify = () => {
            this.filters.search = searchInput ? searchInput.value.trim().toLowerCase() : '';
            this.filters.status = statusSelect ? statusSelect.value : 'all';
            this.filters.priority = prioSelect ? prioSelect.value : 'all';
            this.filters.sortBy = sortSelect ? sortSelect.value : 'position';

            eventBus.emit('filters:changed', this.filters);
        };

        if (searchInput) searchInput.oninput = notify;
        if (statusSelect) statusSelect.onchange = notify;
        if (prioSelect) prioSelect.onchange = notify;
        if (sortSelect) sortSelect.onchange = notify;
    }

    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    unmount() {
        this.container.innerHTML = '';
    }
}
