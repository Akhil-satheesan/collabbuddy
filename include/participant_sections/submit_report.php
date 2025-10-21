<?php
session_start();
require __DIR__ . '/../../include/config.php'; 

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'participant') {
    http_response_code(401); 
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$participant_id = $_SESSION['user_id'];
$host_id = (int)($_POST['host_id'] ?? 0);
$reason_dropdown = trim($_POST['reason'] ?? '');
$details_textarea = trim($_POST['details'] ?? '');

$final_reason_text = "Reason: " . $reason_dropdown . "\nDetails: " . $details_textarea;

if ($host_id === 0 || empty($reason_dropdown) || empty($details_textarea)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields. Please select a reason and provide details.']);
    exit;
}

$conn = get_db_connection();

$sql = "INSERT INTO host_reports (host_id, participant_id, reason, status) 
        VALUES (?, ?, ?, 'Pending')";
        
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'SQL prepare failed: ' . $conn->error]);
    $conn->close();
    exit;
}

$stmt->bind_param("iis", $host_id, $participant_id, $final_reason_text);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Report submitted.']);
} else {
    echo json_encode(['success' => false, 'error' => 'Database execution error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>