<?php
// FILE: include/host_sections/host_remove_profile_pic.php
// Description: Handles AJAX request to remove a host's profile picture.

session_start();
// FIX: Path adjusted assuming config.php is located at the project root (../config.php).
// Adjust this path if 'config.php' is located elsewhere relative to this file.
require_once '../config.php';

// 1. Authorization Check (Must be logged in as a 'host')
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access denied or not logged in as a Host.']);
    exit;
}

$userId = $_SESSION['user_id'];

header('Content-Type: application/json'); // Respond with JSON

try {
    // 2. Get current profile pic path (from users table)
    $stmt = $conn->prepare("SELECT profile_pic_url FROM users WHERE user_id = ?");
    
    // Check if prepare was successful
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $oldPicPath = $user['profile_pic_url'];
    $stmt->close();

    // 3. Set profile_pic_url to NULL in database
    $stmt = $conn->prepare("UPDATE users SET profile_pic_url = NULL WHERE user_id = ?");
    
    if (!$stmt) {
        throw new Exception("Database update prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        
        // 4. Delete the actual file from the server
        // Path adjusted: '../../' goes up two directories from this file's location 
        // (e.g., from include/host_sections/ to project root).
        $fullPath = '../../' . $oldPicPath;

        // Security check: Ensure the path starts with 'uploads/profile_pics/' 
        // to prevent deleting arbitrary files outside the intended folder.
        if ($oldPicPath && file_exists($fullPath) && strpos($oldPicPath, 'uploads/profile_pics/') === 0) {
            if (unlink($fullPath)) {
                // File deleted successfully
            } else {
                // Database updated, but file deletion failed (maybe permission error)
                error_log("Failed to unlink host profile picture file: " . $fullPath);
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Profile picture removed successfully!']);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove profile picture from database.']);
    }
    $stmt->close();

} catch (Exception $e) {
    // Log detailed error and send a generic message to the client
    error_log("Host profile pic removal error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred during removal.']);
}
exit;
?>