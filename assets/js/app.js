// /assets/js/app.js

document.addEventListener('DOMContentLoaded', () => {
    console.log('Bathyal app core loaded.');
    
    // Check if we are on the project board page
    const board = document.getElementById('kanban-board');
    if (board) {
        // By default, load project ID 1 for testing
        // In a real app, this would be read from the URL (e.g., ?id=1)
        const urlParams = new URLSearchParams(window.location.search);
        const projectId = urlParams.get('id') || 1;
        loadProjectBoard(projectId);
    }
});

async function loadProjectBoard(projectId) {
    try {
        const response = await fetch(`/api/projects.php?id=${projectId}`);
        const project = await response.json();
        
        if (project.error) {
            document.getElementById('kanban-board').innerHTML = `<p class="text-red-500 p-4">${project.error}</p>`;
            return;
        }

        // Update Header
        document.getElementById('project-title').textContent = project.name;
        document.getElementById('project-status').textContent = project.status.toUpperCase();
        
        const board = document.getElementById('kanban-board');
        board.innerHTML = ''; // Clear loading spinner
        
        // Render Sections (Columns)
        project.sections.forEach(section => {
            const col = document.createElement('div');
            col.className = 'w-[320px] flex-shrink-0 flex flex-col bg-slate-100 rounded-xl max-h-full border border-slate-200/60 shadow-sm';
            
            let html = `
                <div class="px-4 py-3 flex justify-between items-center cursor-move group">
                    <h3 class="font-semibold text-slate-700 text-sm">${section.name}</h3>
                    <div class="flex items-center space-x-2">
                        <span class="bg-slate-200 text-slate-600 text-xs font-medium px-2 py-0.5 rounded-full">${section.tasks.length}</span>
                        <button class="text-slate-400 hover:text-slate-600 opacity-0 group-hover:opacity-100 transition-opacity">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto px-3 pb-2 space-y-2.5 min-h-[100px] kanban-col" data-section-id="${section.id}">
            `;
            
            // Render Tasks (Cards)
            section.tasks.forEach(task => {
                // Color formatting based on status or due date placeholder
                const statusColor = task.status === 'completed' ? 'text-emerald-500' : 'text-slate-400';
                const assigneeInitials = task.assignee_name ? task.assignee_name.split(' ').map(n => n[0]).join('').substring(0,2) : '?';
                
                html += `
                    <div class="bg-white p-3.5 rounded-lg shadow-sm border border-slate-200 task-card hover:border-teal-400 hover:shadow transition-all cursor-grab active:cursor-grabbing" data-task-id="${task.id}">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="text-sm text-slate-800 font-medium leading-snug">${task.title}</h4>
                            <div class="w-6 h-6 rounded-full bg-teal-100 text-teal-700 text-[10px] font-bold flex items-center justify-center flex-shrink-0 ml-2" title="${task.assignee_name || 'Unassigned'}">
                                ${assigneeInitials}
                            </div>
                        </div>
                        ${task.description ? `<p class="text-xs text-slate-500 line-clamp-2 mb-3 mt-1">${task.description}</p>` : ''}
                        
                        <!-- Task Metadata Footer -->
                        <div class="flex justify-between items-center text-xs mt-3 pt-3 border-t border-slate-50">
                            <!-- Status / Due Date -->
                            <div class="flex items-center space-x-2 text-slate-400 font-medium">
                                <svg class="w-3.5 h-3.5 ${statusColor}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                <span>${task.status}</span>
                            </div>
                            
                            <!-- Timer/Estimated -->
                            <div class="flex items-center text-slate-400" title="Estimated time: ${task.estimated_minutes} min">
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                ${task.estimated_minutes}m
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += `</div>
                <div class="p-2 pt-0 mt-1 pb-3">
                    <button class="w-full flex items-center justify-center text-slate-500 hover:text-teal-600 hover:bg-slate-200/50 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Add task
                    </button>
                </div>
            `;
            col.innerHTML = html;
            board.appendChild(col);
        });

        // Placeholder for adding a new section column
        const addCol = document.createElement('button');
        addCol.className = 'w-[320px] flex-shrink-0 flex items-center justify-center bg-slate-200/50 hover:bg-slate-200 border-2 border-dashed border-slate-300 hover:border-slate-400 text-slate-500 hover:text-slate-700 rounded-xl h-14 font-medium transition-colors text-sm';
        addCol.innerHTML = `
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Add Section
        `;
        board.appendChild(addCol);

    } catch (e) {
        console.error('Error loading project:', e);
        document.getElementById('kanban-board').innerHTML = `<p class="text-red-500 p-4">Error loading project. Check console.</p>`;
    }
}