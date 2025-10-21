<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../include/config.php';

$current_user_id = $_SESSION['user_id'] ?? 0;
if($current_user_id <= 0){
    echo json_encode(['success'=>false,'error'=>'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$conn = get_db_connection();
if(!$conn){
    echo json_encode(['success'=>false,'error'=>'DB connection failed']);
    exit;
}

// Fetch tasks for participant or host in project
if($action==='fetch_my_tasks'){
    $sql = "SELECT t.task_id, t.task_title, t.status, t.priority, t.due_date, p.title AS project_title
            FROM tasks t
            JOIN projects p ON t.project_id = p.project_id
            JOIN project_participant pp ON pp.project_id = t.project_id
            WHERE pp.participant_id = ?
            ORDER BY t.due_date ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i",$current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $tasks = [];
    while($row = $result->fetch_assoc()) $tasks[] = $row;
    $stmt->close();
    $conn->close();

    echo json_encode(['success'=>true,'tasks'=>$tasks]);
    exit;
}

// Update task status
if($action==='update_status'){
    $task_id = intval($_POST['task_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';

    if($task_id<=0 || !in_array($new_status,['To Do','In Progress','Completed','Blocked'])){
        echo json_encode(['success'=>false,'error'=>'Invalid input']);
        exit;
    }

    $checkSql = "SELECT t.task_id FROM tasks t 
                 JOIN project_participant pp ON pp.project_id = t.project_id
                 WHERE t.task_id = ? AND pp.participant_id = ?";
    $stmt = $conn->prepare($checkSql);
    $stmt->bind_param("ii",$task_id,$current_user_id);
    $stmt->execute();
    $stmt->store_result();
    if($stmt->num_rows===0){
        echo json_encode(['success'=>false,'error'=>'Access denied']);
        $stmt->close(); $conn->close(); exit;
    }
    $stmt->close();

    $updateSql = "UPDATE tasks SET status=? WHERE task_id=?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("si",$new_status,$task_id);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    echo json_encode(['success'=>true]);
    exit;
}

echo json_encode(['success'=>false,'error'=>'Invalid action']);
exit;
?>
