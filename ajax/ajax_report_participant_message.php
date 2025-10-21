<?php
session_start();
require '../include/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'participant') {
    http_response_code(403);
    echo "Access denied";
    exit;
}

$message_id = $_POST['message_id'] ?? 0;
if (!$message_id) {
    echo "Invalid message";
    exit;
}

// Mark message as reported (from participant side, reporting host messages)
$sql = "UPDATE project_chat SET is_reported=1 WHERE id=? AND sender_role='host'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $message_id);
if ($stmt->execute()) {
    echo "reported";
} else {
    echo "error";
}
