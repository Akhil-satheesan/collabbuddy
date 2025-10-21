<?php
// File: ajax/ajax_task_participant_action.php
session_start();
require_once '../../include/config.php'; // adjust path as needed

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'participant') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$user_id = intval($_SESSION['user_id']);

if ($action === 'fetch_my_tasks') {

    try {
        $sql = "
            SELECT 
                t.task_id, 
                t.task_title, 
                t.due_date, 
                t.priority, 
                t.status, 
                p.project_title
            FROM tasks t
            INNER JOIN projects p ON t.project_id = p.project_id
            WHERE t.assigned_to = ?
            ORDER BY 
                CASE 
                    WHEN t.status = 'To Do' THEN 1
                    WHEN t.status = 'In Progress' THEN 2
                    WHEN t.status = 'Blocked' THEN 3
                    WHEN t.status = 'Completed' THEN 4
                    ELSE 5
                END, 
                t.due_date ASC
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $tasks = [];
        while ($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }

        echo json_encode(['success' => true, 'tasks' => $tasks]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }

} elseif ($action === 'update_status') {

    $task_id = intval($_POST['task_id'] ?? 0);
    $new_status = trim($_POST['status'] ?? '');

    if ($task_id <= 0 || empty($new_status)) {
        echo json_encode(['success' => false, 'error' => 'Invalid input.']);
        exit;
    }

    try {
        // Verify task ownership before update
        $check = $conn->prepare("SELECT task_id FROM tasks WHERE task_id = ? AND assigned_to = ?");
        $check->bind_param("ii", $task_id, $user_id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'You are not authorized to update this task.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE tasks SET status = ? WHERE task_id = ?");
        $stmt->bind_param("si", $new_status, $task_id);
        $success = $stmt->execute();

        if ($success) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Update failed.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action.']);
}
?>
