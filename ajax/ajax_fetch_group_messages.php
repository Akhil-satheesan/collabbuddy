<?php
session_start();
require_once __DIR__ . '/../include/config.php'; 
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401); 
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'] ?? ''; 
$group_id = (int)($_GET['group_id'] ?? 0);
$last_id = (int)($_GET['last_id'] ?? 0); 

if ($group_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid Group ID.']);
    exit;
}

$conn = get_db_connection();
$has_access = false;

if ($current_user_role === 'participant') {
    $member_check_sql = "SELECT 1 FROM group_chat_members WHERE group_id = ? AND user_id = ?";
    $stmt_check = $conn->prepare($member_check_sql);
    $stmt_check->bind_param("ii", $group_id, $current_user_id);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        $has_access = true;
    }
    $stmt_check->close();
}

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
    http_response_code(403); 
    echo json_encode(['error' => 'Access Denied: Not authorized to view this chat.']);
    $conn->close();
    exit;
}

$messages = [];
$highest_id = $last_id; 

$messages_sql = "
    SELECT 
        m.message_id,  
        m.message_content, 
        m.sender_id, 
        m.sent_at,
        u.name AS sender_name,
        u.profile_pic_url
    FROM messages m
    JOIN users u ON m.sender_id = u.user_id
    WHERE m.chat_identifier = ? AND m.is_group_chat = 1 AND m.message_id > ? 
    ORDER BY m.sent_at ASC
";

$stmt = $conn->prepare($messages_sql);
$stmt->bind_param("ii", $group_id, $last_id); 
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $PROJECT_BASE = '/collabuddy/'; 
    if ($PROJECT_BASE === '/') { 
        $PROJECT_BASE = '';
    }

    while ($row = $result->fetch_assoc()) {
        $default_pic = 'uploads/profile_pics/default.png'; 
        $user_pic_url = $row['profile_pic_url'] ?? $default_pic;
        if (substr($user_pic_url, 0, 1) !== '/' && !preg_match('#^https?://#', $user_pic_url)) {
             $user_pic_url = $PROJECT_BASE . $user_pic_url;
        }
        $messages[] = [
            'message_id' => $row['message_id'],
            'user_id' => $row['sender_id'],
            'full_user_name' => htmlspecialchars($row['sender_name']),
            'message_text' => htmlspecialchars($row['message_content']),
            'time_ago' => date('h:i A', strtotime($row['sent_at'])),
            'profile_pic' => htmlspecialchars($user_pic_url)
        ];
        if ($row['message_id'] > $highest_id) {
            $highest_id = $row['message_id'];
        }
    }
}

$stmt->close();
$conn->close();

echo json_encode(['messages' => $messages, 'last_id' => $highest_id]);
exit;
?>