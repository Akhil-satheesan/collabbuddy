<?php
// FILE: ajax/ajax_submit_report.php
// Handles saving a user's report about a message to the database.

session_start();
require_once __DIR__ . '/../include/config.php'; 

header('Content-Type: application/json');

// --- 1. Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

$reporter_id = $_SESSION['user_id'];

// --- 2. Data Validation ---
if (empty($_POST['message_id']) || empty($_POST['reported_user_id']) || empty($_POST['report_reason'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields (message, user, or reason).']);
    exit;
}

$message_id = (int)$_POST['message_id'];
$reported_user_id = (int)$_POST['reported_user_id'];
$report_reason = trim($_POST['report_reason']);
$is_group_chat = isset($_POST['is_group_chat']) ? (int)$_POST['is_group_chat'] : 0; 

// --- 3. Self-Report Check ---
if ($reporter_id == $reported_user_id) {
     echo json_encode(['success' => false, 'error' => 'You cannot report your own message.']);
     exit;
}

$conn = get_db_connection();

// --- 4. Database Insertion ---
// SQL for inserting into the 'reports' table:
$sql = "INSERT INTO reports (message_id, reporter_id, reported_user_id, report_reason, is_group_chat, reported_at) 
        VALUES (?, ?, ?, ?, ?, NOW())";

$stmt = $conn->prepare($sql);
// "iiisi" stands for: Integer, Integer, Integer, String, Integer
$stmt->bind_param("iiisi", $message_id, $reporter_id, $reported_user_id, $report_reason, $is_group_chat);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Report submitted successfully.']);
} else {
    // Error logging is useful for internal debugging
    error_log("Report DB Error: " . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Failed to save report to database.']);
}

$stmt->close();
$conn->close();
?>