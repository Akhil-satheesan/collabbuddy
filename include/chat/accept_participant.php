<?php
session_start();
require '../config.php';

// ✅ Ensure only logged-in hosts can access this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host') {
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

$host_id       = $_SESSION['user_id'];
$project_id    = intval($_POST['project_id'] ?? 0);
$participant_id = intval($_POST['participant_id'] ?? 0);

if (!$project_id || !$participant_id) {
    http_response_code(400);
    echo "Invalid request";
    exit;
}

// ✅ Verify that the project belongs to the logged-in host
$check = $conn->prepare("SELECT project_id FROM projects WHERE project_id=? AND host_id=?");
$check->bind_param("ii", $project_id, $host_id);
$check->execute();
$check->store_result();

if ($check->num_rows === 0) {
    http_response_code(403);
    echo "You are not the owner of this project.";
    exit;
}
$check->close();

// ✅ Update participant status to accepted
$sql = "UPDATE project_participants 
        SET status='accepted' 
        WHERE project_id=? AND participant_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $project_id, $participant_id);

if ($stmt->execute()) {
    echo "Participant accepted successfully";
} else {
    http_response_code(500);
    echo "Error: " . $stmt->error;
}
$stmt->close();
$conn->close();
?>