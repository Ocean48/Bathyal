<?php
// views/pages/reports.php

require_once 'core/database.php';
require_once 'core/auth_check.php';

require_once 'views/layouts/header.php';

// Prepare data for the reporting dashboard
$userId = $currentUser['id'] ?? 1;

try {
    // 1. Task Status Breakdown (Overall)
    $stmtStatus = $pdo->prepare("SELECT status, COUNT(*) as count FROM tasks GROUP BY status");
    $stmtStatus->execute();
    $statusDataRaw = $stmtStatus->fetchAll(PDO::FETCH_ASSOC);
    $statusData = ['todo' => 0, 'in_progress' => 0, 'paused' => 0, 'completed' => 0];
    foreach ($statusDataRaw as $row) {
        $statusKey = strtolower($row['status']);
        if (isset($statusData[$statusKey]) || array_key_exists($statusKey, $statusData)) {
            $statusData[$statusKey] = (int)$row['count'];
        } else {
            $statusData[$row['status']] = (int)$row['count'];
        }
    }

        // 2. Tasks by Project
    $stmtProject = $pdo->prepare("
        SELECT p.name, COUNT(tp.task_id) as task_count 
        FROM projects p 
        LEFT JOIN task_projects tp ON p.id = tp.project_id 
        GROUP BY p.id 
        ORDER BY task_count DESC 
        LIMIT 5
    ");
    $stmtProject->execute();
    $projectsData = $stmtProject->fetchAll(PDO::FETCH_ASSOC);
    $projectNames = [];
    $projectTaskCounts = [];
    foreach ($projectsData as $p) {
        $projectNames[] = $p['name'];
        $projectTaskCounts[] = $p['task_count'];
    }

    // 3. User Workload (Tasks Assignee Breakdown)
    $stmtWorkload = $pdo->prepare("
        SELECT u.name as assignee_name, COUNT(ta.task_id) as count 
        FROM users u 
        JOIN task_assignees ta ON u.id = ta.user_id 
        JOIN tasks t ON ta.task_id = t.id
        WHERE t.status != 'completed'
        GROUP BY u.id 
        ORDER BY count DESC 
        LIMIT 5
    ");
    $stmtWorkload->execute();
    $workloadData = $stmtWorkload->fetchAll(PDO::FETCH_ASSOC);
    $workloadLabels = [];
    $workloadCounts = [];
    foreach ($workloadData as $w) {
        $workloadLabels[] = $w['assignee_name'];
        $workloadCounts[] = $w['count'];
    }

    // 4. Overdue Tasks
    $stmtOverdue = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM tasks 
        WHERE expected_due_date < NOW() 
        AND status != 'completed'
    ");
    $stmtOverdue->execute();
    $overdueCount = $stmtOverdue->fetchColumn();

} catch (\PDOException $e) {
    // If table doesn't exist or query fails, ignore and use empty arrays
    $statusData = ['todo' => 0, 'in_progress' => 0, 'review' => 0, 'done' => 0];
    $projectNames = [];
    $projectTaskCounts = [];
    $workloadLabels = [];
    $workloadCounts = [];
    $overdueCount = 0;
}
?>

<!-- Include Chart.js via CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div id="report-wrapper" class="max-w-7xl mx-auto px-6 py-8 flex-1 overflow-y-auto">
    <!-- Inner container isolated for PDF rendering to prevent layout shifts -->
    <div id="pdf-export-target" class="w-full bg-slate-50">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-semibold text-slate-800">Reporting & Analytics</h1>
                <p class="text-sm text-slate-500 mt-1">Overview of project progress, workload, and task statuses.</p>
            </div>
            <div class="flex space-x-3" data-html2canvas-ignore="true">
                <button onclick="exportToJSON()" class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors shadow-sm flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    Export JSON
                </button>
                <button onclick="showAlert('Custom Report Builder', 'The custom report drag-and-drop builder is currently in development. Coming soon!', 'info')" class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
                    Custom Report
                </button>
            </div>
        </div>

        <!-- Wrap charts and KPIs logically to be exported -->
        <div id="report-content">
            <!-- Top KPIs -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm transform transition duration-300 hover:-translate-y-1 hover:shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total Tasks</p>
                    <p class="text-3xl font-light text-slate-800 mt-2"><?= array_sum($statusData) ?></p>
                </div>
                <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
            <div class="mt-4 text-xs text-slate-500">Across all projects</div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm transform transition duration-300 hover:-translate-y-1 hover:shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Completed Tasks</p>
                    <p class="text-3xl font-light text-emerald-600 mt-2"><?= $statusData['completed'] ?? 0 ?></p>
                </div>
                <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
            <div class="mt-4 flex items-center text-xs">
                <?php 
                $total = array_sum($statusData);
                $percent = $total > 0 ? round((($statusData['completed'] ?? 0) / $total) * 100) : 0;
                ?>
                <span class="text-emerald-600 font-medium mr-2"><?= $percent ?>%</span> 
                <span class="text-slate-500">Completion rate</span>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm transform transition duration-300 hover:-translate-y-1 hover:shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">In Progress</p>
                    <p class="text-3xl font-light text-indigo-600 mt-2"><?= $statusData['in_progress'] ?? 0 ?></p>
                </div>
                <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
            </div>
            <div class="mt-4 text-xs text-slate-500">Currently being worked on</div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm transform transition duration-300 hover:-translate-y-1 hover:shadow-md <?php echo $overdueCount > 0 ? 'ring-2 ring-rose-300' : ''; ?>">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Overdue</p>
                    <p class="text-3xl font-light <?php echo $overdueCount > 0 ? 'text-rose-600' : 'text-slate-800'; ?> mt-2"><?= $overdueCount ?></p>
                </div>
                <div class="w-12 h-12 <?php echo $overdueCount > 0 ? 'bg-rose-50 text-rose-600' : 'bg-slate-50 text-slate-400'; ?> rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
            <div class="mt-4 flex items-center text-xs">
                <?php if ($overdueCount > 0): ?>
                    <span class="text-rose-600 font-medium bg-rose-50 px-2 py-0.5 rounded mr-2">Action Required</span>
                <?php else: ?>
                    <span class="text-emerald-600 font-medium">On Track</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Charts Area -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        
        <!-- Task Status Doughnut Chart -->
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">Task Status Breakdown</h3>
            <div class="relative h-64">
                <canvas id="taskStatusChart"></canvas>
            </div>
        </div>

        <!-- Workload Bar Chart -->
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">Team Workload (Active Tasks)</h3>
            <div class="relative h-64">
                <canvas id="workloadChart"></canvas>
            </div>
            <?php if (empty($workloadLabels)): ?>
            <div class="absolute inset-0 flex items-center justify-center bg-white/80">
                <p class="text-slate-500 text-sm">No active tasks assigned to users.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 mb-8">
        <!-- Projects Distribution Chart -->
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">Top 5 Projects by Task Volume</h3>
            <div class="relative h-72">
                <canvas id="projectsChart"></canvas>
            </div>
            <?php if (empty($projectNames)): ?>
            <div class="absolute inset-0 flex items-center justify-center bg-white/80">
                <p class="text-slate-500 text-sm">No project tasks available.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
        </div> <!-- End report-content -->
    </div> <!-- End pdf-export-target -->
</div>

<script>
// Export to JSON 
function exportToJSON() {
    // Gather all PHP-generated report data into a structured payload
    const reportData = {
        metadata: {
            title: "<?= addslashes($appName ?? 'Bathyal') ?> Project Report",
            exportedAt: new Date().toISOString()
        },
        kpis: {
            totalTasks: <?= array_sum($statusData) ?>,
            completedTasks: <?= $statusData['completed'] ?? 0 ?>,
            inProgressTasks: <?= $statusData['in_progress'] ?? 0 ?>,
            overdueTasks: <?= $overdueCount ?>
        },
        statusBreakdown: {
            todo: <?= $statusData['todo'] ?? 0 ?>,
            inProgress: <?= $statusData['in_progress'] ?? 0 ?>,
            paused: <?= $statusData['paused'] ?? 0 ?>,
            completed: <?= $statusData['completed'] ?? 0 ?>
        },
        topProjectsByTaskVolume: {
            labels: <?= json_encode($projectNames) ?>,
            data: <?= json_encode($projectTaskCounts) ?>
        },
        teamWorkloadActiveTasks: {
            labels: <?= json_encode($workloadLabels) ?>,
            data: <?= json_encode($workloadCounts) ?>
        }
    };

    // Convert object to a pretty JSON string
    const jsonString = JSON.stringify(reportData, null, 4);

    // Create a Blob from the JSON string
    const blob = new Blob([jsonString], { type: "application/json" });

    // Create a temporary anchor element and trigger download
    const tempElement = document.createElement("a");
    const url = URL.createObjectURL(blob);
    tempElement.href = url;
    tempElement.download = "<?= strtolower($appName ?? 'bathyal') ?>-project-report.json";
    
    document.body.appendChild(tempElement);
    tempElement.click();
    
    // Cleanup
    document.body.removeChild(tempElement);
    URL.revokeObjectURL(url);
    
    if (typeof showAlert === 'function') {
        showAlert('Export Successful', 'Your report has been downloaded as a JSON file.', 'success');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    
    // Default Font settings for Chart.js
    Chart.defaults.font.family = "'Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'sans-serif'";
    Chart.defaults.color = '#64748b'; // slate-500

    // 1. Task Status Chart (Doughnut)
    const ctxStatus = document.getElementById('taskStatusChart').getContext('2d');
    new Chart(ctxStatus, {
        type: 'doughnut',
        data: {
            labels: ['To Do', 'In Progress', 'Paused', 'Completed'],
            datasets: [{
                data: [
                    <?= $statusData['todo'] ?? 0 ?>, 
                    <?= $statusData['in_progress'] ?? 0 ?>, 
                    <?= $statusData['paused'] ?? 0 ?>, 
                    <?= $statusData['completed'] ?? 0 ?>
                ],
                backgroundColor: [
                    '#cbd5e1', // slate-300
                    '#818cf8', // indigo-400
                    '#fbbf24', // amber-400
                    '#34d399'  // emerald-400
                ],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '75%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                }
            }
        }
    });

    // 2. Workload Chart (Bar)
    <?php if (!empty($workloadLabels)): ?>
    const ctxWorkload = document.getElementById('workloadChart').getContext('2d');
    new Chart(ctxWorkload, {
        type: 'bar',
        data: {
            labels: <?= json_encode($workloadLabels) ?>,
            datasets: [{
                label: 'Active Tasks',
                data: <?= json_encode($workloadCounts) ?>,
                backgroundColor: '#14b8a6', // teal-500
                borderRadius: 4,
                barThickness: 32
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    },
                    grid: {
                        color: '#f1f5f9', // slate-100
                        drawBorder: false
                    }
                },
                x: {
                    grid: {
                        display: false,
                        drawBorder: false
                    }
                }
            }
        }
    });
    <?php endif; ?>

    // 3. Projects Distribution Chart (Horizontal Bar)
    <?php if (!empty($projectNames)): ?>
    const ctxProjects = document.getElementById('projectsChart').getContext('2d');
    new Chart(ctxProjects, {
        type: 'bar',
        data: {
            labels: <?= json_encode($projectNames) ?>,
            datasets: [{
                label: 'Total Tasks',
                data: <?= json_encode($projectTaskCounts) ?>,
                backgroundColor: '#0ea5e9', // cyan-500
                borderRadius: 4,
                barThickness: 24
            }]
        },
        options: {
            indexAxis: 'y', // Makes it horizontal
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: {
                        color: '#f1f5f9',
                        drawBorder: false
                    }
                },
                y: {
                    grid: {
                        display: false,
                        drawBorder: false
                    }
                }
            }
        }
    });
    <?php endif; ?>
});
</script>

<?php
require_once 'views/layouts/footer.php';
?>