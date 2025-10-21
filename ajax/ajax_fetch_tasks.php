<?php
// FILE: ajax/ajax_fetch_tasks.php
// Fetch all tasks related to a project for a host.

session_start();
require_once __DIR__ . '/../include/config.php'; 

header('Content-Type: application/json');

// --- 1️⃣ Security Check ---
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'host') {
    http_response_code(401); 
    echo json_encode(['success' => false, 'error' => 'Unauthorized access. Only hosts can view tasks.']);
    exit;
}

// --- 2️⃣ Database Connection ---
$conn = get_db_connection();

if ($conn->connect_error) {
    error_log("DB Connection Failed: " . $conn->connect_error);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}

// Avoid invalid zero dates (keep consistent with add_task)
$conn->query("SET SESSION sql_mode = 'NO_ZERO_DATE,NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION'");

$action = $_REQUEST['action'] ?? '';

if ($action !== 'fetch_tasks') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action parameter. Expected "fetch_tasks".']);
    exit;
}

$project_id = (int)($_GET['project_id'] ?? 0);

if ($project_id === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Project ID missing.']);
    exit;
}

// --- 3️⃣ Fetch Tasks Query ---
$sql = "
    SELECT 
        t.task_id, 
        t.title AS task_title, 
        -- ✅ Convert NULL or 0000-00-00 to NULL for safety
        NULLIF(t.due_date, '0000-00-00') AS due_date, 
        t.status, 
        t.priority,
        COALESCE(u.name, 'Unassigned') AS assigned_to_name,
        u.user_id AS assigned_user_id
    FROM tasks t
    LEFT JOIN users u ON t.assigned_to_user_id = u.user_id
    WHERE t.project_id = ? 
    ORDER BY FIELD(t.priority, 'Critical', 'High', 'Medium', 'Low'), t.due_date ASC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Task Fetch Prepare Error: " . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'SQL prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $project_id);

if (!$stmt->execute()) {
    error_log("Task Fetch Execute Error: " . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'SQL execute failed: ' . $stmt->error]);
    exit;
}

$result = $stmt->get_result();
$tasks = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- 4️⃣ Format Results for Frontend ---
foreach ($tasks as &$task) {
    // If due_date is NULL, make it an empty string for JS display
    $task['due_date'] = $task['due_date'] ? date('Y-m-d', strtotime($task['due_date'])) : '';
}

echo json_encode([
    'success' => true,
    'tasks' => $tasks
]);

$conn->close();
?>
