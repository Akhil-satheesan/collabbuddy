<?php
// FILE: ajax/ajax_send_group_message.php (Shared: Host & Participant)

session_start();
require_once __DIR__ . '/../include/config.php'; 

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Unauthorized access.';
    http_response_code(401);
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    http_response_code(405);
    echo json_encode($response);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'];
$group_id = (int)($_POST['group_id'] ?? 0);
$message_content = trim($_POST['message'] ?? '');

if ($group_id <= 0 || empty($message_content)) {
    $response['message'] = 'Invalid Group ID or empty message.';
    http_response_code(400);
    echo json_encode($response);
    exit;
}

$conn = get_db_connection();
$has_access = false;

// 1. Check if the user is a Group Member (Participant Check)
$member_check_sql = "SELECT 1 FROM group_chat_members WHERE group_id = ? AND user_id = ?";
$stmt_check = $conn->prepare($member_check_sql);
$stmt_check->bind_param("ii", $group_id, $current_user_id);
$stmt_check->execute();
if ($stmt_check->get_result()->num_rows > 0) {
    $has_access = true;
}
$stmt_check->close();

// 2. Check if the user is the Host of the Project associated with the Group (Host Check)
if (!$has_access && $current_user_role === 'host') {
    $host_check_sql = "SELECT 1 FROM group_chats gc JOIN projects p ON gc.project_id = p.project_id WHERE gc.group_id = ? AND p.host_id = ?";
    $stmt_host_check = $conn->prepare($host_check_sql);
    $stmt_host_check->bind_param("ii", $group_id, $current_user_id);
    $stmt_host_check->execute();
    if ($stmt_host_check->get_result()->num_rows > 0) {
        $has_access = true;
    }
    $stmt_host_check->close();
}

if (!$has_access) {
    $response['message'] = 'Permission denied. You are not authorized to send messages in this group.';
    http_response_code(403);
    $conn->close();
    echo json_encode($response);
    exit;
}

// --- Insert Message Logic (Same as before) ---
$insert_sql = "
    INSERT INTO messages (chat_identifier, sender_id, message_content, is_group_chat)
    VALUES (?, ?, ?, 1)
";
$stmt = $conn->prepare($insert_sql);
$stmt->bind_param("iis", $group_id, $current_user_id, $message_content);

if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Message sent successfully.';
} else {
    $response['message'] = 'Failed to insert message: ' . $conn->error;
}

$stmt->close();
$conn->close();
echo json_encode($response);
?>