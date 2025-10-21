<?php
session_start();
require '../include/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host') {
    http_response_code(403);
    echo "Access denied";
    exit;
}

$host_id = $_SESSION['user_id'];
$project_id = $_GET['project_id'] ?? 0;

if (!$project_id) {
    echo "<p>No project selected</p>";
    exit;
}

// Fetch chat messages for this project
$sql = "SELECT * FROM project_chat WHERE project_id=? ORDER BY created_at ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $sender = ($row['sender_role'] === 'host') ? 'You' : 'Participant';
    $reported = $row['is_reported'] ? '⚠️ Reported' : '';
    echo "<div class='chat-message mb-2'>
            <strong>{$sender}:</strong> ".htmlspecialchars($row['message'])." <small>{$reported}</small>
            <div class='text-xs text-gray-400'>{$row['created_at']}</div>
          </div>";
}
