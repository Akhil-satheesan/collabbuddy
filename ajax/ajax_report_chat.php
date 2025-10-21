<?php
// FILE: ajax/ajax_report_chat.php

// Ensure session is started and config is loaded
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../include/config.php'; // Path might need adjustment

header('Content-Type: application/json');

// -------------------------
// 1. Security Check
// -------------------------
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$reporter_id = (int)$_SESSION['user_id'];
$conn = get_db_connection();

// -------------------------
// 2. Input Validation
// -------------------------
$message_id = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT);
$reported_user_id = filter_input(INPUT_POST, 'reported_user_id', FILTER_VALIDATE_INT);
$reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$is_group_chat = filter_input(INPUT_POST, 'is_group_chat', FILTER_VALIDATE_INT);

// Check for critical missing data
if (!$message_id || !$reported_user_id || empty($reason)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields (message ID, user ID, or reason).']);
    $conn->close();
    exit;
}

// Ensure user is not reporting themselves
if ($reporter_id === $reported_user_id) {
    echo json_encode(['success' => false, 'message' => 'You cannot report yourself.']);
    $conn->close();
    exit;
}

// -------------------------
// 3. Database Insertion
// -------------------------
$sql = "INSERT INTO chat_reports (message_id, reporter_id, reported_user_id, report_reason, is_group_chat)
        VALUES (?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    error_log("Report SQL Prepare Error: " . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error during preparation.']);
    $conn->close();
    exit;
}

$stmt->bind_param("iiisi", $message_id, $reporter_id, $reported_user_id, $reason, $is_group_chat);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Message successfully reported. An admin will review it shortly.']);
} else {
    error_log("Report SQL Execute Error: " . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to submit report due to a server error.']);
}

$stmt->close();
$conn->close();
?>