<?php
// C:\xampp\htdocs\collabuddy\ajax\ajax_send_group_chat.php
session_start();
require_once '../include/config.php';

if (!isset($_SESSION['user_id'])) { http_response_code(401); die(); }

$chat_identifier = (int)$_POST['chat_identifier']; // project_id
$message_content = trim($_POST['message_content']);
$sender_id = $_SESSION['user_id'];
$conn = get_db_connection();

// [Security Check: User must be Host or Member of the project]

$insertStmt = $conn->prepare("INSERT INTO messages (chat_identifier, sender_id, message_content, is_group_chat) VALUES (?, ?, ?, 1)");
$insertStmt->bind_param("iis", $chat_identifier, $sender_id, $message_content);
$insertStmt->execute();

echo json_encode(['success' => true]);
?>