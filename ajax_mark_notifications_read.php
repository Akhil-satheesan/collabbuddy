<?php
session_start();
require_once 'include/config.php'; // Adjust path as needed

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}
$participant_id = $_SESSION['user_id'];

// Update all unread notifications for this user
$stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE");
$stmt->bind_param("i", $participant_id);
$stmt->execute();
$stmt->close();

// Respond with a success signal
echo 1;
?>