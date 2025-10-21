<?php
ob_start(); // Output Buffering ആരംഭിക്കുന്നു (Warnings ഒഴിവാക്കാൻ)

if (session_status() == PHP_SESSION_NONE) session_start();

// JSON response function to clean up code
function sendJsonResponse($success, $message, $conn = null) {
    if ($conn) {
        // Close database connection if needed
        $conn->close();
    }
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

// 1. Authorization Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'participant') {
    sendJsonResponse(false, "Unauthorized Access.");
}

// 2. Database Connection
// Path correction: Assume '../config.php' is correct based on previous debugging.
require '../config.php'; 

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $name = trim($_POST['name'] ?? ''); // Use null coalescing for safety
    $preferred_role = trim($_POST['preferred_role'] ?? '');
    $skills = trim($_POST['skills'] ?? '');
    $languages = trim($_POST['languages'] ?? '');

    // Basic Validation
    if (empty($name)) {
        sendJsonResponse(false, "Name field cannot be empty.", $conn);
    }
    // You can add more validation checks here (e.g., empty preferred_role)

    try {
        // Start transaction for atomicity
        $conn->begin_transaction();

        // 3. Update users table (name)
        $stmtUser = $conn->prepare("UPDATE users SET name=? WHERE user_id=?");
        if (!$stmtUser) {
            throw new Exception("Prepare statement failed for users: " . $conn->error);
        }
        $stmtUser->bind_param("si", $name, $userId);
        $stmtUser->execute();
        $stmtUser->close();

        // 4. Update participants table (role, skills, languages)
        $stmtPart = $conn->prepare("
            UPDATE participants 
            SET preferred_role=?, skills=?, languages=? 
            WHERE participant_id=?
        ");
        if (!$stmtPart) {
            throw new Exception("Prepare statement failed for participants: " . $conn->error);
        }
        $stmtPart->bind_param("sssi", $preferred_role, $skills, $languages, $userId);
        $stmtPart->execute();
        $stmtPart->close();
        
        // Commit changes if both updates were successful
        $conn->commit();

        // Optionally, update session name
        $_SESSION['name'] = $name;

        // 5. Send Success JSON Response
        sendJsonResponse(true, "Profile updated successfully!", $conn);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        // Log the error for backend debugging
        error_log("Profile Update Error: " . $e->getMessage()); 
        
        // 6. Send Error JSON Response
        sendJsonResponse(false, "An error occurred during the update: " . $e->getMessage(), $conn);
    }

} else {
    // If request method is not POST
    sendJsonResponse(false, "Invalid request method.", $conn);
}
?>