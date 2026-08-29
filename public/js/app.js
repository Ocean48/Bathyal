/**
 * Bathyal Frontend SPA Engine - Initial Entry Point
 */

document.addEventListener('DOMContentLoaded', async () => {
    initSystemHealthCheck();
    initModeToggle();
    initTaskHandlers();
});

async function initSystemHealthCheck() {
    const phpStatusEl = document.getElementById('php-status');
    const aiStatusEl = document.getElementById('ai-status');
    const dbStatusEl = document.getElementById('db-status');
    const taskListView = document.getElementById('task-list-view');

    // 1. Check PHP Core API & Database status
    try {
        const phpRes = await fetch('/api/v1/health');
        if (phpRes.ok) {
            const data = await phpRes.json();
            phpStatusEl.textContent = 'Online';
            phpStatusEl.className = 'indicator online';

            if (data.database === 'connected') {
                dbStatusEl.textContent = 'Connected';
                dbStatusEl.className = 'indicator online';
            } else {
                dbStatusEl.textContent = 'Disconnected';
                dbStatusEl.className = 'indicator error';
            }
        } else {
            throw new Error(`PHP API responded with ${phpRes.status}`);
        }
    } catch (err) {
        phpStatusEl.textContent = 'Offline';
        phpStatusEl.className = 'indicator error';
        dbStatusEl.textContent = 'Unknown';
        dbStatusEl.className = 'indicator error';
    }

    // 2. Check Python AI Microservice status (via Nginx proxy /api/v1/ai/*)
    try {
        const aiRes = await fetch('/api/v1/ai/health');
        if (aiRes.ok) {
            const aiData = await aiRes.json();
            aiStatusEl.textContent = 'Online';
            aiStatusEl.className = 'indicator online';
        } else {
            throw new Error(`AI Gateway responded with ${aiRes.status}`);
        }
    } catch (err) {
        aiStatusEl.textContent = 'Offline';
        aiStatusEl.className = 'indicator error';
    }

    // 3. Fetch default statuses to display
    try {
        const statusRes = await fetch('/api/v1/statuses');
        if (statusRes.ok) {
            const res = await statusRes.json();
            if (res.data && res.data.length > 0) {
                renderInitialTasks(res.data);
            }
        }
    } catch (err) {
        console.warn('Could not fetch statuses:', err);
    }
}

function renderInitialTasks(statuses) {
    const taskListView = document.getElementById('task-list-view');
    taskListView.innerHTML = '';

    const initialTasks = [
        { title: 'Explore Bathyal Architecture', status: 'Completed', color: '#10B981' },
        { title: 'Configure Multi-Container Docker Stack', status: 'Completed', color: '#10B981' },
        { title: 'Implement Vanilla RESTful Core & Dynamic View Scoping', status: 'In Progress', color: '#3B82F6' },
        { title: 'Connect Gantt Auto-Scheduler to Python AI Gateway', status: 'To Do', color: '#94A3B8' }
    ];

    initialTasks.forEach(task => {
        const card = document.createElement('div');
        card.className = 'task-card';
        card.innerHTML = `
            <div class="task-info">
                <span class="task-title">${escapeHtml(task.title)}</span>
            </div>
            <span class="badge" style="border-color: ${task.color}; color: ${task.color}">${task.status}</span>
        `;
        taskListView.appendChild(card);
    });
}

function initModeToggle() {
    const toggleBtn = document.getElementById('toggle-mode-btn');
    const modeBadge = document.getElementById('mode-badge');
    let isEnterprise = false;

    toggleBtn.addEventListener('click', () => {
        isEnterprise = !isEnterprise;
        if (isEnterprise) {
            modeBadge.textContent = 'Enterprise Mode';
            modeBadge.style.color = '#10B981';
            modeBadge.style.borderColor = '#10B981';
            toggleBtn.textContent = 'Switch to Simple';
            document.querySelectorAll('.enterprise-only').forEach(el => el.style.display = 'block');
        } else {
            modeBadge.textContent = 'Simple Mode';
            modeBadge.style.color = 'var(--accent-blue)';
            modeBadge.style.borderColor = 'rgba(59, 130, 246, 0.4)';
            toggleBtn.textContent = 'Switch to Enterprise';
            document.querySelectorAll('.enterprise-only').forEach(el => el.style.display = 'none');
        }
    });
}

function initTaskHandlers() {
    const addBtn = document.getElementById('add-task-btn');
    const input = document.getElementById('quick-task-input');

    const handleAdd = () => {
        const text = input.value.trim();
        if (!text) return;

        const taskListView = document.getElementById('task-list-view');
        const card = document.createElement('div');
        card.className = 'task-card';
        card.innerHTML = `
            <div class="task-info">
                <span class="task-title">${escapeHtml(text)}</span>
            </div>
            <span class="badge" style="border-color: #3B82F6; color: #3B82F6">To Do</span>
        `;
        taskListView.prepend(card);
        input.value = '';
    };

    addBtn.addEventListener('click', handleAdd);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') handleAdd();
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
