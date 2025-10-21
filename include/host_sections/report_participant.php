<?php
// FILE: include/host_sections/actions/report_participant.php
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING); 
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

// config.php-ലേക്ക് ശരിയായ പാത്ത് ഉപയോഗിക്കുക
require_once __DIR__ . '/../../include/config.php';

$conn = get_db_connection();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'host') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '🚫 Access denied.']);
    $conn->close();
    exit;
}

$host_id = (int)$_SESSION['user_id']; 
$participant_id = (int)($_POST['participant_id'] ?? 0); 
$reason_dropdown = trim($_POST['reason'] ?? '');
$details_textarea = trim($_POST['details'] ?? '');
$project_id_context = (int)($_POST['project_id_context'] ?? 0); 

if ($participant_id === 0 || empty($reason_dropdown) || empty($details_textarea)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
    $conn->close();
    exit;
}

// ⭐️ ടേബിളിൻ്റെ പേര് 'participant_reports'
$table_name = 'participant_reports'; 

// Report details combining reason and additional details
$final_reason_text = "Primary Reason: " . $reason_dropdown . "\nDetails: " . $details_textarea;
if ($project_id_context > 0) {
    // You might want to fetch project name here if desired
    $final_reason_text .= "\n(Context: Project ID: " . $project_id_context . ")";
}

// ടേബിൾ ഘടന: (participant_id, host_id, reason, status)
$sql = "INSERT INTO {$table_name} (participant_id, host_id, reason, status) 
        VALUES (?, ?, ?, 'Pending')";
        
$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'SQL prepare failed: ' . $conn->error]);
    $conn->close();
    exit;
}

// Binding: iis (participant_id, host_id, reason)
$status = 'Pending';
$stmt->bind_param("iis", $participant_id, $host_id, $final_reason_text);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => '✅ Report against participant submitted successfully!']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => '❌ Database execution error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>