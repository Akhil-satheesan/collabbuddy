<?php
// FILE: ajax/send_group_message.php

require_once __DIR__ . '/../../include/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$conn = get_db_connection();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    $conn->close();
    exit;
}

$sender_id = (int)$_SESSION['user_id'];
$group_id = (int)($_POST['group_id'] ?? 0);
$content = trim($_POST['content'] ?? '');

if (!$group_id || empty($content)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid group or message content.']);
    $conn->close();
    exit;
}

// 1. യൂസർ ഈ ഗ്രൂപ്പിലെ അംഗമാണോ എന്ന് പരിശോധിക്കുന്നു (Security Check)
$member_stmt = $conn->prepare("SELECT 1 FROM group_chat_members WHERE group_id = ? AND user_id = ?");
$member_stmt->bind_param("ii", $group_id, $sender_id);
$member_stmt->execute();
$member_stmt->store_result();

if ($member_stmt->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You cannot send messages to this group.']);
    $member_stmt->close();
    $conn->close();
    exit;
}
$member_stmt->close();

// 2. മെസ്സേജ് ഡാറ്റാബേസിൽ ചേർക്കുന്നു
$stmt = $conn->prepare("
    INSERT INTO messages (chat_identifier, sender_id, message_content, is_group_chat)
    VALUES (?, ?, ?, 1)
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB Prepare Error: ' . $conn->error]);
    $conn->close();
    exit;
}

// chat_identifier ആയി group_id ഉപയോഗിക്കുന്നു
$stmt->bind_param("iis", $group_id, $sender_id, $content); 

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Message sent!', 'message_id' => $conn->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to send message: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>