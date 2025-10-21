<?php
session_start();
require '../config.php';

header('Content-Type: application/json');

// Check login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

$participant_id = $_SESSION['user_id'];
$project_id = intval($_POST['project_id'] ?? 0);

if (!$project_id) {
    echo json_encode(["success" => false, "message" => "Invalid project"]);
    exit;
}

// Check if already bookmarked
$check = $conn->prepare("SELECT id FROM bookmarks WHERE project_id=? AND participant_id=?");
$check->bind_param("ii", $project_id, $participant_id);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    // Remove bookmark
    $del = $conn->prepare("DELETE FROM bookmarks WHERE project_id=? AND participant_id=?");
    $del->bind_param("ii", $project_id, $participant_id);
    $del->execute();
    echo json_encode(["success" => true, "action" => "removed"]);
} else {
    // Add bookmark
    $ins = $conn->prepare("INSERT INTO bookmarks (project_id, participant_id) VALUES (?, ?)");
    $ins->bind_param("ii", $project_id, $participant_id);
    $ins->execute();
    echo json_encode(["success" => true, "action" => "added"]);
}
?>
