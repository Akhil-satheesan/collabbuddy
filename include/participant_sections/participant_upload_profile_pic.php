<?php
session_start();
require '../config.php';

// 1. Authorization Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'participant') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

$userId = $_SESSION['user_id'];
$uploadDir = '../../uploads/profile_pics/'; // Create this directory in your root folder!
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif']; // Cropper.js usually outputs PNG or JPEG
$maxSize = 5 * 1024 * 1024; // 5MB

// Create the upload directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true); // Ensure proper permissions
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
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION); // Usually 'png' from cropper.toBlob('image/png')
        $newFileName = $userId . '_' . time() . '.' . $ext;
        $uploadPath = $uploadDir . $newFileName;
        $dbPath = 'uploads/profile_pics/' . $newFileName; // Path relative to your web root

        // Move the uploaded file
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            
            // 3. Database Update
            try {
                // Get old profile pic path for deletion (optional but recommended)
                $stmt = $conn->prepare("SELECT profile_pic_url FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $oldPicRow = $result->fetch_assoc();
                $oldPicPath = $oldPicRow['profile_pic_url'];
                $stmt->close();

                $stmt = $conn->prepare("UPDATE users SET profile_pic_url = ? WHERE user_id = ?");
                $stmt->bind_param("si", $dbPath, $userId);
                
                if ($stmt->execute()) {
                    // 4. Delete Old File (if it exists and is not the default)
                    // Ensure the old path is valid and not trying to delete outside uploads
                    if ($oldPicPath && file_exists('../../' . $oldPicPath) && strpos($oldPicPath, 'uploads/profile_pics/') === 0) {
                        unlink('../../' . $oldPicPath); 
                    }
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => "Profile picture updated successfully!",
                        'new_pic_url' => $dbPath // Return the new URL for frontend update
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => "Database error: Could not update picture path."]);
                }
                $stmt->close();
            } catch (Exception $e) {
                error_log("Profile pic upload DB error: " . $e->getMessage());
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