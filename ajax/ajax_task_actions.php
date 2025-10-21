<?php
session_start();
require_once __DIR__ . '/../include/config.php'; 

header('Content-Type: application/json');

// 🔒 Check host authentication
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'host') {
    http_response_code(401); 
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

$conn = get_db_connection();

if ($conn->connect_error) {
    error_log("DB Connection Failed: " . $conn->connect_error);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}

// ✅ Ensure valid SQL mode (avoids invalid dates)
$conn->query("SET SESSION sql_mode = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION'");

$action = $_REQUEST['action'] ?? '';
$host_id = $_SESSION['user_id'];


// 🧩 1️⃣ FETCH PARTICIPANTS
if ($action === 'fetch_participants') {
    $project_id = (int)($_GET['project_id'] ?? 0);
    
    if ($project_id === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Project ID missing.']);
        exit;
    }

    $sql = "
        SELECT 
            pp.participant_id, 
            u.name AS full_user_name, 
            pp.role_taken 
        FROM project_participants pp
        JOIN users u ON pp.participant_id = u.user_id
        WHERE pp.project_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $participants = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['success' => true, 'participants' => $participants]);
    exit;
}



// 🧩 2️⃣ ADD TASK
elseif ($action === 'add_task') {
    $project_id = (int)($_POST['project_id'] ?? 0);
    $assigned_user_id = (int)($_POST['assigned_user_id'] ?? 0);
    $task_title = trim($_POST['task_title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $due_date = trim($_POST['due_date'] ?? ''); 
    $priority = trim($_POST['priority'] ?? 'Medium');
    $status_value = 'To Do'; // ✅ match enum in DB

    // Validate required fields
    if ($project_id === 0 || $assigned_user_id === 0 || empty($task_title)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields (Title, Project, Assignee).']);
        exit;
    }

    // 🗓 Validate due date
    $valid_due_date = null;
    if (!empty($due_date)) {
        if (preg_match("/^\d{4}-\d{2}-\d{2}$/", $due_date)) {
            $today = date('Y-m-d');
            if ($due_date < $today) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'The Due Date cannot be in the past.']);
                exit;
            }
            $valid_due_date = $due_date;
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid Due Date format. Must be YYYY-MM-DD.']);
            exit;
        }
    }

    // 🧠 Insert Query (handles both with and without due_date)
    if ($valid_due_date === null) {
        $sql = "
            INSERT INTO tasks 
            (project_id, assigned_to_user_id, title, description, due_date, status, priority, created_by_id)
            VALUES (?, ?, ?, ?, NULL, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iissssi",
            $project_id,
            $assigned_user_id,
            $task_title,
            $description,
            $status_value,
            $priority,
            $host_id
        );
    } else {
        $sql = "
            INSERT INTO tasks 
            (project_id, assigned_to_user_id, title, description, due_date, status, priority, created_by_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisssssi",
            $project_id,
            $assigned_user_id,
            $task_title,
            $description,
            $valid_due_date,
            $status_value,
            $priority,
            $host_id
        );
    }

    if (!$stmt) {
        error_log("Task Insert Prepare Error: " . $conn->error);
        http_response_code(500); 
        echo json_encode(['success' => false, 'error' => 'SQL Prepare Failed: ' . $conn->error]);
        exit;
    }

    // ✅ Execute and handle result
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Task assigned successfully.',
            'task_id' => $stmt->insert_id
        ]);
    } else {
        error_log("Task Insert Execute Error: " . $stmt->error);
        http_response_code(500); 
        echo json_encode(['success' => false, 'error' => 'Database error: Could not save task. Details: ' . $stmt->error]);
    }

    $stmt->close();
    exit;
}



// 🚫 3️⃣ Invalid Action
else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing action parameter.']);
    exit;
}

$conn->close();
?>
