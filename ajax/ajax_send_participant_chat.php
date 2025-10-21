<?php
session_start();
require '../include/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'participant') {
    http_response_code(403);
    echo "Access denied";
    exit;
}

$participant_id = $_SESSION['user_id'];
$project_id = $_POST['project_id'] ?? 0;
$message = trim($_POST['message'] ?? '');

if (!$project_id || !$message) {
    echo "Invalid input";
    exit;
}

$sql = "INSERT INTO project_chat (project_id, sender_id, sender_role, message) VALUES (?, ?, 'participant', ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iis", $project_id, $participant_id, $message);

if ($stmt->execute()) {
    echo "success";
} else {
    echo "error";
}
