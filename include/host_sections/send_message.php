<?php
// FILE: include/host_sections/send_message.php (Host AJAX)

require_once __DIR__ . '/../../include/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host' || !isset($_POST['group_id']) || !isset($_POST['message'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized or invalid data.']);
    exit;
}

$host_id = (int)$_SESSION['user_id'];
$group_id = (int)$_POST['group_id'];
$message_text = trim($_POST['message']);

if (empty($message_text)) {
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty.']);
    exit;
}

$conn = get_db_connection();

// Authorization Check (Same as fetch_messages.php)
$auth_sql = "SELECT COUNT(*) FROM group_chats gc INNER JOIN projects p ON gc.project_id = p.project_id WHERE gc.group_id = ? AND p.host_id = ?";
$auth_stmt = $conn->prepare($auth_sql);
$auth_stmt->bind_param("ii", $group_id, $host_id);
$auth_stmt->execute();
$auth_stmt->bind_result($is_authorized);
$auth_stmt->fetch();
$auth_stmt->close();

if ($is_authorized === 0) {
    echo json_encode(['success' => false, 'error' => 'Not authorized to send message to this group.']);
    $conn->close();
    exit;
}

// Insert the message
$insert_sql = "INSERT INTO chat_messages (group_id, user_id, message_text) VALUES (?, ?, ?)";
$stmt = $conn->prepare($insert_sql);

if ($stmt) {
    $stmt->bind_param("iis", $group_id, $host_id, $message_text);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message_id' => $conn->insert_id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to prepare statement.']);
}

$conn->close();
?>