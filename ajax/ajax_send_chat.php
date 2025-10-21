<?php
// FILE: ajax\ajax_send_chat.php
session_start();
// 🔑 Path: host_dashboard.php യുടെ റൂട്ട് ഫോൾഡറിൽ നിന്നാണ് വിളിക്കുന്നതെങ്കിൽ, ഇത് ശരിയാണ്.
require_once '../include/config.php'; 

header('Content-Type: application/json');
// 🚨 Debugging എളുപ്പമാക്കാൻ error_reporting ചേർക്കുന്നു.
ini_set('display_errors', 0); // Production-ൽ 0 ആയി നിലനിർത്തുക
error_reporting(E_ALL);

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    $response['message'] = 'User not logged in or session expired.';
    echo json_encode($response);
    exit;
}

$conn = get_db_connection();
$sender_id = $_SESSION['user_id'];

$chat_identifier = $_POST['chat_identifier'] ?? null; 
$message_content = trim($_POST['message_content'] ?? ''); 
$is_group_chat = 0; 

if (empty($chat_identifier) || empty($message_content)) {
    http_response_code(400);
    $response['message'] = 'Missing chat identifier or message content.';
    echo json_encode($response);
    exit;
}

if (!is_numeric($chat_identifier) || $sender_id === null) {
    http_response_code(400);
    $response['message'] = 'Invalid Chat Identifier or Sender ID.';
    echo json_encode($response);
    exit;
}

try {
    // 1. Chat Room Authority Check (ചാറ്റ് റൂം host_id, participant_id എന്നിവയിൽ ഒന്നാണോ sender_id എന്ന് പരിശോധിക്കുന്നു)
    $checkSql = "SELECT room_id FROM chat_rooms WHERE room_id = ? AND (host_id = ? OR participant_id = ?)";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("iii", $chat_identifier, $sender_id, $sender_id);
    $checkStmt->execute();
    
    if ($checkStmt->get_result()->num_rows === 0) {
        $checkStmt->close();
        throw new Exception("Unauthorized to send message to this chat room.");
    }
    $checkStmt->close();

    // 2. Insert Message
    $insertSql = "INSERT INTO messages (chat_identifier, sender_id, message_content, is_group_chat) VALUES (?, ?, ?, ?)";
    $insertStmt = $conn->prepare($insertSql);
    // Data Types: i (integer), i (integer), s (string), i (integer)
    $insertStmt->bind_param("iisi", $chat_identifier, $sender_id, $message_content, $is_group_chat);

    if (!$insertStmt->execute()) {
        $db_error = $insertStmt->error;
        $insertStmt->close();
        // 🚨 DB Error Message തിരികെ നൽകുന്നു
        throw new Exception("Database INSERT failed. SQL Error: " . $db_error);
    }

    $insertStmt->close();
    $response['success'] = true;
    $response['message'] = 'Message sent successfully.';

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'Server exception: ' . $e->getMessage();
}

echo json_encode($response);
?>