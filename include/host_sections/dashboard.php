<?php
if (!isset($_SESSION['user_id'])) {
    exit("Access denied. Please log in.");
}

$hostId = $_SESSION['user_id']; 
$activeProjects = 0;
$teamMembers = 0;
$pendingRequests = 0;
$completedTasks = 0;

$stmt = $conn->prepare("SELECT COUNT(*) FROM projects WHERE host_id = ? AND status = 'Active'");
if ($stmt) {
    $stmt->bind_param("i", $hostId);
    $stmt->execute();
    $stmt->bind_result($activeProjects);
    $stmt->fetch();
    $stmt->close();
}

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT pr.participant_id) 
    FROM project_requests pr
    INNER JOIN projects p ON pr.project_id = p.project_id
    WHERE p.host_id = ? AND pr.status = 'accepted'
");
if ($stmt) {
    $stmt->bind_param("i", $hostId);
    $stmt->execute();
    $stmt->bind_result($teamMembers);
    $stmt->fetch();
    $stmt->close();
}

$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM project_requests pr
    INNER JOIN projects p ON pr.project_id = p.project_id
    WHERE p.host_id = ? AND pr.status = 'pending'
");
if ($stmt) {
    $stmt->bind_param("i", $hostId);
    $stmt->execute();
    $stmt->bind_result($pendingRequests);
    $stmt->fetch();
    $stmt->close();
}

$completed_tasks_query = "
    SELECT COUNT(t.task_id) 
    FROM tasks t
    INNER JOIN projects p ON t.project_id = p.project_id
    WHERE p.host_id = ? AND t.status = 'Completed'
";

$stmt = $conn->prepare($completed_tasks_query);

if ($stmt) {
    $stmt->bind_param("i", $hostId);
    $stmt->execute();
    $stmt->bind_result($completedTasks);
    $stmt->fetch();
    $stmt->close();
} 
?>

<div id="dashboard-content" class="content-section">
    <h1 class="text-3xl font-bold text-gray-900 mb-6">Host Dashboard</h1>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Active Projects</p>
                    <p class="text-3xl font-bold text-gray-900"><?= $activeProjects ?></p>
                    <p class="text-sm text-green-600 mt-1">Currently running</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <span class="text-2xl">📂</span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Team Members</p>
                    <p class="text-3xl font-bold text-gray-900"><?= $teamMembers ?></p>
                    <p class="text-sm text-blue-600 mt-1">Across all projects</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <span class="text-2xl">👥</span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Pending Requests</p>
                    <p class="text-3xl font-bold text-gray-900"><?= $pendingRequests ?></p>
                    <p class="text-sm text-orange-600 mt-1">Need review</p>
                </div>
                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                    <span class="text-2xl">⏳</span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Completed Tasks</p>
                    <p class="text-3xl font-bold text-gray-900"><?= $completedTasks ?></p>
                    <p class="text-sm text-purple-600 mt-1">All time</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <span class="text-2xl">✅</span>
                </div>
            </div>
        </div>
    </div>
    
    </div>
    <?php
if (!isset($conn) || !isset($_SESSION['user_id'])) {
    exit("<div style='color:red;padding:15px;border:1px solid red;'>Configuration Error: Database connection or user session not found.</div>");
}

$hostId = $_SESSION['user_id']; 
$recentActivities = [];
$activity_limit = 5;

function time_ago($timestamp) {
    $diff = time() - $timestamp;
    if ($diff < 0) return date("M d, Y H:i", $timestamp);
    if ($diff < 60) return $diff . ' seconds ago';
    if ($diff < 3600) return round($diff / 60) . ' minutes ago';
    if ($diff < 86400) return round($diff / 3600) . ' hours ago';
    if ($diff < 604800) return round($diff / 86400) . ' days ago';
    return date("M d, Y", $timestamp);
}

$activity_query = "
    (
        SELECT 'NEW_REQUEST' AS type, p.title AS project_title, u.name AS actor_name, pr.created_at AS activity_time, 'wants to join' AS action, NULL AS task_title
        FROM project_requests pr
        JOIN projects p ON pr.project_id = p.project_id
        JOIN users u ON pr.participant_id = u.user_id
        WHERE p.host_id = ? AND pr.status = 'pending'
    )
    UNION ALL
    (
        SELECT 'MEMBER_ACCEPTED' AS type, p.title AS project_title, u.name AS actor_name, pr.created_at AS activity_time, 'joined the team' AS action, NULL AS task_title
        FROM project_requests pr
        JOIN projects p ON pr.project_id = p.project_id
        JOIN users u ON pr.participant_id = u.user_id
        WHERE p.host_id = ? AND pr.status = 'accepted'
    )
    UNION ALL
    (
        SELECT 'TASK_COMPLETED' AS type, p.title AS project_title, u.name AS actor_name, t.completed_at AS activity_time, 'completed the task' AS action, t.title AS task_title
        FROM tasks t
        JOIN projects p ON t.project_id = p.project_id
        LEFT JOIN users u ON t.assigned_to_user_id = u.user_id
        WHERE p.host_id = ? AND t.status = 'Completed' AND t.completed_at IS NOT NULL
    )
    ORDER BY activity_time DESC
    LIMIT ?
";

$stmt_activity = $conn->prepare($activity_query);
if ($stmt_activity) {
    $stmt_activity->bind_param("iiii", $hostId, $hostId, $hostId, $activity_limit);
    $stmt_activity->execute();
    $result_activity = $stmt_activity->get_result();

    while ($row = $result_activity->fetch_assoc()) {
        $recentActivities[] = $row;
    }
    $stmt_activity->close();
}
?>

<div class="bg-white rounded-xl shadow-md border border-gray-100 p-0">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-xl font-semibold text-gray-900">Recent Activity</h3>
    </div>
    <div class="p-6">
        <div class="space-y-4">
            <?php if (empty($recentActivities)): ?>
                <div class="text-center py-6 text-gray-500 bg-gray-50 rounded-lg border border-dashed border-gray-200">
                    <p class="font-medium">No recent activity yet. 🙁</p>
                    <p class="text-sm mt-1">Activity includes new requests, member approvals, or task completions.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($recentActivities as $activity): 
                $timestamp = strtotime($activity['activity_time']);
                $time_ago = time_ago($timestamp); 
                $actor_name = htmlspecialchars($activity['actor_name'] ?? 'Unassigned User');
                $project_title = htmlspecialchars($activity['project_title']);
                
                $icon = '';
                $bgColor = '';
                $message = '';
                
                switch ($activity['type']) {
                    case 'NEW_REQUEST':
                        $icon = '📝';
                        $bgColor = 'bg-red-50';
                        $message = "<strong>{$actor_name}</strong> sent a request to join <strong>{$project_title}</strong>.";
                        break;
                    case 'MEMBER_ACCEPTED':
                        $icon = '🎉';
                        $bgColor = 'bg-blue-50';
                        $message = "<strong>{$actor_name}</strong> joined the team on <strong>{$project_title}</strong>.";
                        break;
                    case 'TASK_COMPLETED':
                        $icon = '✅';
                        $bgColor = 'bg-green-50';
                        $task_title = htmlspecialchars($activity['task_title']);
                        $message = "Task <strong>\"{$task_title}\"</strong> completed in <strong>{$project_title}</strong>.";
                        break;
                }
            ?>
            <div class="flex items-start space-x-3 p-3 <?= $bgColor ?> rounded-xl border border-<?= substr($bgColor, 3) ?> transform hover:shadow-sm transition duration-150">
                <span class="text-2xl flex-shrink-0"><?= $icon ?></span>
                <div class="text-sm">
                    <p class="font-medium text-gray-900"><?= $message ?></p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        <span class="font-semibold text-gray-600 mr-2"><?= str_replace('_', ' ', strtolower($activity['type'])) ?></span> • <?= $time_ago ?>
                    </p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>