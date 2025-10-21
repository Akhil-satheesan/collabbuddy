<?php
// FILE: ajax/get_group_messages.php

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

$user_id = (int)$_SESSION['user_id'];
$group_id = (int)($_GET['group_id'] ?? 0);
$last_message_id = (int)($_GET['last_id'] ?? 0); // പുതിയ മെസ്സേജുകൾ മാത്രം എടുക്കാൻ

if (!$group_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid Group ID']);
    $conn->close();
    exit;
}

// 1. യൂസർ ഈ ഗ്രൂപ്പിലെ അംഗമാണോ എന്ന് പരിശോധിക്കുന്നു
$member_stmt = $conn->prepare("SELECT 1 FROM group_chat_members WHERE group_id = ? AND user_id = ?");
$member_stmt->bind_param("ii", $group_id, $user_id);
$member_stmt->execute();
$member_stmt->store_result();

if ($member_stmt->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not a member of this group.']);
    $member_stmt->close();
    $conn->close();
    exit;
}
$member_stmt->close();

// 2. മെസ്സേജുകൾ Fetch ചെയ്യുന്നു
$sql = "
    SELECT 
        m.message_id, m.sender_id, m.message_content, m.sent_at,
        u.name AS sender_name
    FROM messages m
    INNER JOIN users u ON m.sender_id = u.user_id
    WHERE m.chat_identifier = ? 
      AND m.is_group_chat = 1
      AND m.message_id > ?
    ORDER BY m.sent_at ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB Prepare Error: ' . $conn->error]);
    $conn->close();
    exit;
}

$is_group_chat_val = 1; 
$stmt->bind_param("ii", $group_id, $last_message_id); // chat_identifier ആണ് group_id
$stmt->execute();
$result = $stmt->get_result();
$messages = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'current_user_id' => $user_id
]);

$conn->close();
?>