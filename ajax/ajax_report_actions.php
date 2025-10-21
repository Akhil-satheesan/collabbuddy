<?php
session_start();
require_once __DIR__ . '/../include/config.php';

// Check user authentication
$current_user_id = $_SESSION['user_id'] ?? 0;
$current_user_role = $_SESSION['role'] ?? 'guest';

if ($current_user_id <= 0 || $current_user_role !== 'host') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$project_id = intval($_GET['project_id'] ?? 0);

if ($action === 'fetch_full_report' && $project_id > 0) {
    $conn = get_db_connection();

    // 1️⃣ Task Status Summary
    $status_summary = [
        'To Do' => 0,
        'In Progress' => 0,
        'Completed' => 0,
        'Blocked' => 0
    ];

    $stmt = $conn->prepare("
        SELECT status, COUNT(*) AS cnt
        FROM tasks
        WHERE project_id = ?
        GROUP BY status
    ");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (isset($status_summary[$row['status']])) {
            $status_summary[$row['status']] = intval($row['cnt']);
        }
    }
    $stmt->close();

    // 2️⃣ Priority Summary
    $priority_summary = [
        'Critical' => 0,
        'High' => 0,
        'Medium' => 0,
        'Low' => 0
    ];

    $stmt = $conn->prepare("
        SELECT priority, COUNT(*) AS cnt
        FROM tasks
        WHERE project_id = ?
        GROUP BY priority
    ");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (isset($priority_summary[$row['priority']])) {
            $priority_summary[$row['priority']] = intval($row['cnt']);
        }
    }
    $stmt->close();

    $conn->close();

    echo json_encode([
        'success' => true,
        'report' => [
            'status_summary' => $status_summary,
            'priority_summary' => $priority_summary
        ]
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
exit;
