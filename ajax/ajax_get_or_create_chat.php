<?php
// FILE: ajax/ajax_get_or_create_chat.php

session_start();
require '../include/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$host_id = (int)$_SESSION['user_id'];
$participant_id = filter_input(INPUT_POST, 'participant_id', FILTER_VALIDATE_INT);
$project_id = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);
$conn = get_db_connection();

if (!$participant_id || !$project_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing IDs']);
    $conn->close();
    exit;
}

// 1. നിലവിലുള്ള Chat Room കണ്ടെത്തുക
$sql = "SELECT room_id FROM chat_rooms WHERE host_id = ? AND participant_id = ? AND project_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $host_id, $participant_id, $project_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // റൂം നിലവിലുണ്ട്
    $room = $result->fetch_assoc();
    echo json_encode(['success' => true, 'room_id' => (int)$room['room_id'], 'message' => 'Existing chat room loaded.']);
    $stmt->close();
    $conn->close();
    exit;
}

$stmt->close();

// 2. Chat Room ഇല്ലെങ്കിൽ, പുതിയത് ഉണ്ടാക്കുക
// project_requests ടേബിളിൽ സ്റ്റാറ്റസ് 'pending' ആണോ എന്നും പരിശോധിക്കണം. (ചാറ്റ് തുടങ്ങുന്നതിന് മുൻപ് അപേക്ഷ Approve ചെയ്യണം എന്നില്ല)

// ഇവിടെ സ്റ്റാറ്റസ് 'pending' ആയി സെറ്റ് ചെയ്യുന്നു. Host-ൻ്റെ ഭാഗത്തുനിന്ന് ആദ്യമായി ചാറ്റ് റൂം ഉണ്ടാക്കുമ്പോൾ.
$insertSql = "INSERT INTO chat_rooms (host_id, participant_id, project_id, status) VALUES (?, ?, ?, 'pending')"; 
$insertStmt = $conn->prepare($insertSql);
$insertStmt->bind_param("iii", $host_id, $participant_id, $project_id);

if ($insertStmt->execute()) {
    $new_room_id = $conn->insert_id;
    echo json_encode(['success' => true, 'room_id' => $new_room_id, 'message' => 'New chat room created.']);
} else {
    error_log("Chat Room Creation Error: " . $insertStmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create chat room.']);
}

$insertStmt->close();
$conn->close();
?>