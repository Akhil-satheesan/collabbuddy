<?php
// FILE: ajax/ajax_participant_tasks.php

session_start();
require_once __DIR__ . '/../include/config.php'; 

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'participant') {
    http_response_code(401); 
    echo json_encode(['success' => false, 'error' => 'Unauthorized access or role.']);
    exit;
}

$conn = get_db_connection();

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}

// ഡേറ്റ് പ്രശ്നം ഒഴിവാക്കാൻ SQL Mode സെറ്റ് ചെയ്യുന്നു
$conn->query("SET SESSION sql_mode = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION'");

$action = $_REQUEST['action'] ?? '';
$current_user_id = $_SESSION['user_id'];

// =================================================================
// ACTION 1: FETCH MY TASKS
// =================================================================
if ($action === 'fetch_my_tasks') {
    
    // തനിക്കുള്ള ടാസ്‌ക്കുകളും, അത് ഉൾപ്പെടുന്ന പ്രോജക്റ്റ് ടൈറ്റിലും ഫെച്ച് ചെയ്യുന്നു
    $sql = "
        SELECT 
            t.task_id, 
            t.title AS task_title, 
            t.due_date, 
            t.status, 
            t.priority,
            p.title AS project_title
        FROM tasks t
        JOIN projects p ON t.project_id = p.project_id
        WHERE t.assigned_to_user_id = ? 
        ORDER BY FIELD(t.priority, 'Critical', 'High', 'Medium', 'Low'), t.due_date ASC";

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'SQL prepare failed: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("i", $current_user_id);

    if (!$stmt->execute()) {
         http_response_code(500);
         echo json_encode(['success' => false, 'error' => 'SQL execute failed: ' . $stmt->error]);
         exit;
    }

    $result = $stmt->get_result();
    $tasks = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['success' => true, 'tasks' => $tasks]);

// =================================================================
// ACTION 2: UPDATE TASK STATUS
// =================================================================
} elseif ($action === 'update_status') {
    $task_id = (int)($_POST['task_id'] ?? 0);
    $new_status = trim($_POST['status'] ?? '');
    
    if ($task_id === 0 || empty($new_status)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing Task ID or Status.']);
        exit;
    }
    
    // ടാസ്‌ക് തൻ്റേതാണ് എന്ന് ഉറപ്പുവരുത്തുന്നു
    $sql = "
        UPDATE tasks 
        SET status = ?, completed_at = CASE WHEN ? = 'Completed' THEN NOW() ELSE NULL END
        WHERE task_id = ? AND assigned_to_user_id = ?";
        
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'SQL prepare failed: ' . $conn->error]);
        exit;
    }
    
    // രണ്ടാമത്തെ ? ന് പുതിയ സ്റ്റാറ്റസ് വാല്യൂ bind ചെയ്യുന്നു
    $stmt->bind_param("ssii", $new_status, $new_status, $task_id, $current_user_id); 

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
             echo json_encode(['success' => true, 'message' => 'Task status updated successfully.']);
        } else {
             // affected_rows 0 ആണെങ്കിൽ, സ്റ്റാറ്റസ് മാറിയിട്ടില്ല (ഒരുപക്ഷേ പഴയ സ്റ്റാറ്റസ് തന്നെയായിരിക്കും) അല്ലെങ്കിൽ ടാസ്‌ക് തന്റേതല്ല.
             echo json_encode(['success' => true, 'message' => 'Status saved (no change or task not found).']);
        }
    } else {
        http_response_code(500); 
        echo json_encode(['success' => false, 'error' => 'Database error: Could not update status. Details: ' . $stmt->error]); 
    }

    $stmt->close();

} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing action parameter.']);
}

$conn->close();
?>