<?php
session_start();
require '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'participant') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

$userId = $_SESSION['user_id'];

header('Content-Type: application/json'); // Respond with JSON

try {
    // Get current profile pic path
    $stmt = $conn->prepare("SELECT profile_pic_url FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $oldPicPath = $user['profile_pic_url'];
    $stmt->close();

    // Set profile_pic_url to NULL in database
    $stmt = $conn->prepare("UPDATE users SET profile_pic_url = NULL WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        // Delete the actual file from the server
        if ($oldPicPath && file_exists('../../' . $oldPicPath) && strpos($oldPicPath, 'uploads/profile_pics/') === 0) {
            unlink('../../' . $oldPicPath);
        }
        echo json_encode(['success' => true, 'message' => 'Profile picture removed successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove profile picture from database.']);
    }
    $stmt->close();

} catch (Exception $e) {
    error_log("Profile pic removal error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred during removal.']);
}
exit;
?>