<?php
// /views/pages/projects.php

require_once 'core/database.php';
require_once 'core/auth_check.php';

// Handle Project Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_project') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $cycle = trim($_POST['cycle'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if ($name !== '') {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO projects (name, description, cycle, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $description, $cycle, $status]);
            $newProjectId = $pdo->lastInsertId();

            $stmtMember = $pdo->prepare("INSERT INTO project_members (project_id, user_id, role) VALUES (?, ?, 'manager')");
            // Assuming $currentUser['id'] is available from auth_check.php
            $userId = isset($currentUser['id']) ? $currentUser['id'] : 1; 
            $stmtMember->execute([$newProjectId, $userId]);

            $pdo->commit();
            header("Location: /bathyal/projects");
            exit;
        } catch (\PDOException $e) {
            $pdo->rollBack();
            $errorMsg = "Error creating project.";
        }
    } else {
        $errorMsg = "Project name is required.";
    }
}

require_once 'views/layouts/header.php';

// Fetch all projects
$stmt = $pdo->query("SELECT * FROM projects ORDER BY created_at DESC");
$allProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getInitials($string) {
    if (empty($string)) return "P";
    $words = explode(" ", $string);
    $initials = "";
    foreach ($words as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
        if (strlen($initials) >= 2) break;
    }
    return $initials;
}

$colors = [
    ['bg' => 'bg-teal-100', 'text' => 'text-teal-700', 'bar' => 'bg-teal-500', 'border' => 'hover:border-teal-300'],
    ['bg' => 'bg-cyan-100', 'text' => 'text-cyan-700', 'bar' => 'bg-cyan-500', 'border' => 'hover:border-cyan-300'],
    ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'bar' => 'bg-blue-500', 'border' => 'hover:border-blue-300'],
    ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'bar' => 'bg-emerald-500', 'border' => 'hover:border-emerald-300'],
    ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-700', 'bar' => 'bg-indigo-500', 'border' => 'hover:border-indigo-300']
];
?>

<div class="max-w-6xl mx-auto px-6 py-8 transform transition-opacity duration-500">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800">All Projects</h1>
            <p class="text-slate-500 text-sm mt-1">Manage and view all your active and past projects.</p>
        </div>
        <button onclick="document.getElementById('newProjectModal').classList.remove('hidden')" class="bg-teal-500 hover:bg-teal-600 text-white font-medium py-2 px-4 rounded-lg transition-colors flex items-center shadow-sm">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            New Project
        </button>
    </div>

    <?php if (isset($errorMsg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($errorMsg) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($allProjects as $index => $project): 
            $c = $colors[$index % count($colors)];
        ?>
        <div class="group bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col <?= $c['border'] ?> hover:shadow-md cursor-pointer transition-all" onclick="window.location.href='/bathyal/project?id=<?= $project['id'] ?>'">
            <div class="p-5 flex items-start justify-between">
                <div class="w-12 h-12 rounded-xl <?= $c['bg'] ?> flex items-center justify-center <?= $c['text'] ?> font-bold text-lg mr-4 shrink-0">
                    <?= getInitials($project['name']) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-start">
                        <h3 class="text-lg font-medium text-slate-800 truncate group-hover:<?= $c['text'] ?> transition-colors" title="<?= htmlspecialchars($project['name']) ?>">
                            <?= htmlspecialchars($project['name']) ?>
                        </h3>
                    </div>
                    <p class="text-sm text-slate-500 mt-1 line-clamp-2 h-10">
                        <?= htmlspecialchars($project['description'] ?? 'No description provided.') ?>
                    </p>
                </div>
            </div>
            <div class="px-5 py-4 border-t border-slate-100 bg-slate-50/80 flex items-center justify-between mt-auto">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $project['status'] === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-800' ?>">
                    <?= ucfirst($project['status']) ?>
                </span>
                <span class="text-xs text-slate-500 font-medium">
                    <?= htmlspecialchars($project['cycle'] ?? '') ?>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($allProjects)): ?>
            <div class="col-span-full py-12 text-center text-slate-500">
                No projects found.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- New Project Modal -->
<div id="newProjectModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" onclick="document.getElementById('newProjectModal').classList.add('hidden')"></div>
    
    <!-- Modal Content -->
    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden transform transition-all">
        <form action="/bathyal/projects" method="POST">
            <input type="hidden" name="action" value="create_project">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/80">
                <h3 class="text-lg font-semibold text-slate-800">Create New Project</h3>
                <button type="button" class="text-slate-400 hover:text-slate-600 transition-colors" onclick="document.getElementById('newProjectModal').classList.add('hidden')">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="px-6 py-5 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Project Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition-colors" placeholder="e.g., Website Redesign">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                    <textarea name="description" rows="3" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition-colors placeholder-slate-400" placeholder="Briefly describe the project goals..."></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Cycle / Sprint</label>
                        <input type="text" name="cycle" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition-colors" placeholder="e.g., Q3 2026">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Initial Status</label>
                        <select name="status" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition-colors">
                            <option value="active">Active</option>
                            <option value="planning">Planning</option>
                            <option value="completed">Completed</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end space-x-3">
                <button type="button" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors" onclick="document.getElementById('newProjectModal').classList.add('hidden')">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-teal-500 rounded-lg hover:bg-teal-600 shadow-sm transition-colors">Create Project</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'views/layouts/footer.php'; ?>