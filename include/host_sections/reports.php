<?php
// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get current user info
$current_user_id = $_SESSION['user_id'] ?? 0;
$current_user_role = $_SESSION['role'] ?? 'guest';

// Access control
if ($current_user_id <= 0 || $current_user_role !== 'host') {
    echo '<p class="p-4 text-red-500">Authentication error. Access denied.</p>';
    exit;
}

// DB connection
require_once __DIR__ . '/../../include/config.php';
$conn = get_db_connection();

// Fetch projects managed by this host
$projects = [];
if ($conn) {
    $sql = "SELECT project_id, title FROM projects WHERE host_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $projects = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Project Reports - CollabBuddy</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.3.3/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">

<div class="p-6 min-h-screen">
    <h1 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-2">Project Reports Dashboard 📊</h1>

    <!-- Project Selection -->
    <div class="mb-6 bg-white p-4 rounded-lg shadow-md max-w-lg">
        <label for="report_project_select" class="block text-sm font-medium text-gray-700 mb-2">
            Select a Project to View Report
        </label>
        <select id="report_project_select" class="block w-full border border-gray-300 rounded-md p-2 bg-white">
            <option value="0">--- Select Project ---</option>
            <?php foreach ($projects as $project): ?>
                <option value="<?= htmlspecialchars($project['project_id']) ?>">
                    <?= htmlspecialchars($project['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Report Content -->
    <div id="report-content" class="space-y-8 mt-8 hidden">
        <h2 class="text-2xl font-semibold text-gray-700 border-b pb-2" id="project-report-title">Report for Selected Project</h2>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Task Status Chart -->
            <div class="bg-white p-6 rounded-lg shadow-md relative h-80">
                <h3 class="text-xl font-medium text-gray-800 mb-4">Task Status Breakdown</h3>
                <canvas id="taskStatusChart" class="w-full h-full"></canvas>
                <div class="text-center mt-4 text-sm text-gray-500" id="total-tasks-count"></div>
            </div>

            <!-- Priority Chart -->
            <div class="bg-white p-6 rounded-lg shadow-md relative h-80">
                <h3 class="text-xl font-medium text-gray-800 mb-4">Priority Distribution</h3>
                <canvas id="priorityChart" class="w-full h-full"></canvas>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-medium text-gray-800">Member Performance Summary</h3>
            <p class="text-gray-500 mt-2">Detailed breakdown of tasks completed by each participant (future feature).</p>
        </div>
    </div>

    <!-- Initial & Loading Messages -->
    <div id="initial-message" class="mt-8 p-10 text-center bg-white rounded-lg shadow-md text-gray-600">
        Please select a project from the dropdown to view its report.
    </div>

    <div id="loading-message" class="mt-8 p-10 text-center bg-yellow-100 rounded-lg shadow-md text-yellow-800 hidden">
        <i class="fas fa-spinner fa-spin mr-2"></i> Loading project data...
    </div>
</div>

<script>
$(document).ready(function() {
    let taskStatusChartInstance = null;
    let priorityChartInstance = null;

    const STATUS_COLORS = { 'To Do': '#3b82f6', 'In Progress': '#f59e0b', 'Completed': '#10b981', 'Blocked': '#ef4444' };
    const PRIORITY_COLORS = { 'Critical': '#dc2626', 'High': '#f97316', 'Medium': '#3b82f6', 'Low': '#9ca3af' };

    function createChart(chartId, type, data, oldChartInstance) {
        if (oldChartInstance) oldChartInstance.destroy();
        const ctx = document.getElementById(chartId).getContext('2d');
        return new Chart(ctx, {
            type: type,
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: {
                    y: type === 'bar' ? { beginAtZero: true } : {},
                    x: type === 'bar' ? { maxBarThickness: 60 } : {}
                }
            }
        });
    }

    function fetchReportData(projectId) {
        if (projectId === 0) {
            $('#report-content').addClass('hidden');
            $('#initial-message').removeClass('hidden');
            return;
        }

        $('#report-content, #initial-message').addClass('hidden');
        $('#loading-message').removeClass('hidden');
        const projectName = $('#report_project_select option:selected').text();
        $('#project-report-title').text(`Report for: ${projectName}`);

        $.ajax({
            url: 'ajax/ajax_report_actions.php',
            method: 'GET',
            dataType: 'json',
            data: { action: 'fetch_full_report', project_id: projectId },
            success: function(response) {
                $('#loading-message').addClass('hidden');
                if (response.success && response.report) {
                    $('#report-content').removeClass('hidden');
                    const report = response.report;

                    // Task Status Chart
                    const statusData = Object.keys(STATUS_COLORS).map(label => Number(report.status_summary[label] || 0));
                    $('#total-tasks-count').text(`Total Tasks: ${statusData.reduce((a,b)=>a+b,0)}`);
                    const statusChartData = { labels: Object.keys(STATUS_COLORS), datasets: [{ data: statusData, backgroundColor: Object.values(STATUS_COLORS) }] };
                    taskStatusChartInstance = createChart('taskStatusChart', 'pie', statusChartData, taskStatusChartInstance);

                    // Priority Chart
                    const priorityData = Object.keys(PRIORITY_COLORS).map(label => Number(report.priority_summary[label] || 0));
                    const priorityChartData = { labels: Object.keys(PRIORITY_COLORS), datasets: [{ label: 'Tasks by Priority', data: priorityData, backgroundColor: Object.values(PRIORITY_COLORS) }] };
                    priorityChartInstance = createChart('priorityChart', 'bar', priorityChartData, priorityChartInstance);

                } else {
                    $('#report-content').removeClass('hidden').html('<div class="p-4 text-red-500">Error loading report: '+(response.error||'No data found')+'</div>');
                }
            },
            error: function(xhr) {
                $('#loading-message').addClass('hidden');
                $('#report-content').removeClass('hidden').html('<div class="p-4 text-red-500">AJAX Request Failed. Status: '+xhr.status+'</div>');
            }
        });
    }

    // Project selection change
    $('#report_project_select').on('change', function() {
        fetchReportData(parseInt($(this).val()));
    });

    // Initial load
    fetchReportData(0);
});
</script>
</body>
</html>
