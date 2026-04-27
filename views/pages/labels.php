<?php
// views/pages/labels.php
require_once 'core/database.php';
require_once 'core/auth_check.php';

// Restrict to admin and member
if (!isset($currentUser['role']) || !in_array($currentUser['role'], ['admin', 'member'])) {
    http_response_code(403);
    echo "<h1 style='text-align:center; margin-top: 50px; font-family:sans-serif;'>403 - Forbidden</h1>";
    exit;
}

require_once 'views/layouts/header.php';
?>

<div class="max-w-6xl mx-auto px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800">Labels Management</h1>
            <p class="text-sm text-slate-500 mt-1">Manage global labels used across all projects.</p>
        </div>
        <button onclick="openLabelModal()" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Create Label
        </button>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden p-6">
        <div id="labels-list" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="text-sm text-slate-400 italic col-span-full">Loading labels...</div>
        </div>
    </div>
</div>

<!-- Label Edit Modal -->
<div id="label-modal" class="fixed inset-0 bg-slate-900/50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h2 id="label-modal-title" class="text-xl font-semibold text-slate-800 mb-4">Edit Label</h2>
        <input type="hidden" id="label-modal-id">
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                <input type="text" id="label-modal-name" class="w-full border-slate-300 rounded-lg shadow-sm focus:border-teal-500 focus:ring-teal-500 p-2 border">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Color</label>
                <div class="flex items-center space-x-3">
                    <input type="color" id="label-modal-color" class="h-10 w-20 border border-slate-300 rounded cursor-pointer p-1">
                    <span class="text-sm text-slate-500" id="label-modal-color-hex">#000000</span>
                </div>
            </div>
        </div>
        <div class="mt-8 flex justify-between items-center">
            <button id="label-modal-delete-btn" onclick="deleteLabel()" class="text-sm text-red-600 hover:text-red-800 font-medium hidden">Delete Label</button>
            <div class="flex space-x-3 ml-auto">
                <button onclick="document.getElementById('label-modal').classList.add('hidden')" class="px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">Cancel</button>
                <button onclick="saveLabel()" class="px-4 py-2 text-sm font-medium text-white bg-teal-600 hover:bg-teal-700 rounded-lg transition-colors shadow-sm">Save Label</button>
            </div>
        </div>
    </div>
</div>

<script>
let allLabels = [];

document.addEventListener('DOMContentLoaded', fetchLabels);

document.getElementById('label-modal-color').addEventListener('input', function(e) {
    document.getElementById('label-modal-color-hex').innerText = e.target.value.toUpperCase();
});

async function fetchLabels() {
    try {
        const res = await fetch('/bathyal/api/labels.php');
        const data = await res.json();
        if (data.status === 'success') {
            allLabels = data.data;
            renderLabels();
        }
    } catch (e) {
        console.error(e);
    }
}

function renderLabels() {
    const list = document.getElementById('labels-list');
    list.innerHTML = '';
    
    if (allLabels.length === 0) {
        list.innerHTML = '<div class="text-sm text-slate-400 italic col-span-full">No labels exist yet.</div>';
        return;
    }

    allLabels.forEach(l => {
        list.innerHTML += `
            <div class="flex items-center justify-between p-3 bg-white border border-slate-200 rounded-lg shadow-sm hover:shadow hover:border-teal-300 transition-all cursor-pointer group" onclick="openLabelModal(${l.id})">
                <div class="flex items-center space-x-3">
                    <span class="w-4 h-4 rounded-full flex-shrink-0" style="background-color: ${l.color}"></span>
                    <span class="text-sm font-medium text-slate-700 truncate">${l.name}</span>
                </div>
                <svg class="w-4 h-4 text-slate-400 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
            </div>
        `;
    });
}

function openLabelModal(id = null) {
    const modal = document.getElementById('label-modal');
    const title = document.getElementById('label-modal-title');
    const idInput = document.getElementById('label-modal-id');
    const nameInput = document.getElementById('label-modal-name');
    const colorInput = document.getElementById('label-modal-color');
    const hexSpan = document.getElementById('label-modal-color-hex');
    const delBtn = document.getElementById('label-modal-delete-btn');

    if (id) {
        const l = allLabels.find(x => x.id == id);
        title.innerText = 'Edit Label';
        idInput.value = l.id;
        nameInput.value = l.name;
        colorInput.value = l.color || '#000000';
        hexSpan.innerText = (l.color || '#000000').toUpperCase();
        delBtn.classList.remove('hidden');
    } else {
        title.innerText = 'Create Label';
        idInput.value = '';
        nameInput.value = '';
        
        // Random color generator
        const randomColor = '#' + Math.floor(Math.random()*16777215).toString(16).padStart(6, '0');
        colorInput.value = randomColor;
        hexSpan.innerText = randomColor.toUpperCase();
        delBtn.classList.add('hidden');
    }

    modal.classList.remove('hidden');
}

async function saveLabel() {
    const id = document.getElementById('label-modal-id').value;
    const name = document.getElementById('label-modal-name').value;
    const color = document.getElementById('label-modal-color').value;

    if (!name.trim()) {
        alert('Name is required');
        return;
    }

    const payload = {
        action: id ? 'update' : 'create',
        name: name.trim(),
        color: color
    };
    if (id) payload.id = id;

    try {
        const res = await fetch('/bathyal/api/labels.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.status === 'success') {
            document.getElementById('label-modal').classList.add('hidden');
            fetchLabels(); // refresh
        } else {
            alert(data.message || 'Error saving label');
        }
    } catch (e) {
        console.error(e);
    }
}

async function deleteLabel() {
    const id = document.getElementById('label-modal-id').value;
    if (!id || !confirm('Are you sure you want to delete this label? It will be removed from all tasks.')) return;

    try {
        const res = await fetch('/bathyal/api/labels.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: id })
        });
        const data = await res.json();
        if (data.status === 'success') {
            document.getElementById('label-modal').classList.add('hidden');
            fetchLabels(); // refresh
        }
    } catch (e) {
        console.error(e);
    }
}
</script>

<?php require_once 'views/layouts/footer.php'; ?>