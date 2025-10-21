<?php
session_start();
require '../include/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host') {
    http_response_code(403);
    echo "Access denied";
    exit;
}

$project_id = $_POST['project_id'] ?? 0;
if (!$project_id) {
    echo "Invalid project";
    exit;
}

// Mark all participant messages as read
$sql = "UPDATE project_chat SET is_read=1 WHERE project_id=? AND sender_role='participant'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $project_id);
$stmt->execute();

echo "ok";
