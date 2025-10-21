<?php
session_start();
// ------------------------------------------------------------------
// CONFIG PATH NOTE: host_upload_profile_pic.php is likely in 
// 'include/host_sections/'. If config.php is in the project root (/),
// the path is correct ('../config.php'). If it's failing, try 
// require_once '../../config.php'; OR require_once '../../include/config.php';
// Use require_once to prevent 'Cannot redeclare' error.
// ------------------------------------------------------------------
require_once '../config.php'; 

// 1. Authorization Check (Changed to 'host')
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access denied. You must be logged in as a Host.']);
    exit;
}

$userId = $_SESSION['user_id'];
$uploadDir = '../../uploads/profile_pics/'; // Create this directory in your root folder!
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif']; 
$maxSize = 5 * 1024 * 1024; // 5MB

// Create the upload directory if it doesn't exist
if (!is_dir($uploadDir)) {
    // Note: 0777 is often restricted. Use a secure permission if possible.
    if (!mkdir($uploadDir, 0777, true)) { 
         header('Content-Type: application/json');
         echo json_encode(['success' => false, 'message' => "Error: Could not create upload directory."]);
         exit;
    }
}

header('Content-Type: application/json'); // Respond with JSON

// 2. File Upload Handling (from Cropper.js blob)
if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['profile_pic'];

    // Validation
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => "Invalid file type. Only JPG, PNG, and GIF are allowed."]);
        exit;
    } elseif ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => "File size exceeds 5MB limit."]);
        exit;
    } else {
        // Generate a unique filename (e.g., user_id_timestamp.png)
        $ext = 'png'; // Force to png/jpeg as output from Cropper is usually standardized
        $newFileName = $userId . '_host_' . time() . '.' . $ext;
        $uploadPath = $uploadDir . $newFileName;
        $dbPath = 'uploads/profile_pics/' . $newFileName; // Path relative to your web root

        // Move the uploaded file
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            
            // 3. Database Update
            try {
                // Get old profile pic path for deletion 
                $stmt = $conn->prepare("SELECT profile_pic_url FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $oldPicRow = $result->fetch_assoc();
                $oldPicPath = $oldPicRow['profile_pic_url'];
                $stmt->close();

                // Update the profile_pic_url in the 'users' table
                $stmt = $conn->prepare("UPDATE users SET profile_pic_url = ? WHERE user_id = ?");
                $stmt->bind_param("si", $dbPath, $userId);
                
                if ($stmt->execute()) {
                    // 4. Delete Old File (Security check added: only delete from inside the dedicated folder)
                    $deleteFileRoot = '../../';
                    $defaultPicPath = 'assets/default_profile.png'; 
                    
                    if ($oldPicPath && 
                        $oldPicPath !== $defaultPicPath && 
                        file_exists($deleteFileRoot . $oldPicPath) && 
                        strpos($oldPicPath, 'uploads/profile_pics/') === 0) {
                        
                        unlink($deleteFileRoot . $oldPicPath); 
                    }
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => "Host profile picture updated successfully!",
                        'new_pic_url' => $dbPath // Return the new URL for frontend update
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => "Database error: Could not update picture path."]);
                }
                $stmt->close();
            } catch (Exception $e) {
                error_log("Host profile pic upload DB error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => "An unexpected database error occurred."]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => "File upload failed. Check server permissions."]);
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => "No image file selected or upload error occurred."]);
}
exit;
?>