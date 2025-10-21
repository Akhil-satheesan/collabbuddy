<?php
// process_password_change_otp_only.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start(); 

if (session_status() == PHP_SESSION_NONE) session_start();

// --- PHPMailer Autoload ---
require __DIR__ . '/../../vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Helper Functions
function sendJsonResponse($success, $message, $conn = null, $step = 1) {
    if ($conn && $conn instanceof mysqli && $conn->ping()) $conn->close();
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'next_step' => $step]);
    exit;
}

function send_otp_mail($recipientEmail, $subject, $body) {
    $mail = new PHPMailer(true);
    try {
        // --- SMTP കോൺഫിഗറേഷൻ ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'akhilsatheesan557@gmail.com'; 
        $mail->Password   = 'oudw pxez ycoq elet';         
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        $mail->setFrom('akhilsatheesan557@gmail.com', 'CollabBuddy Password Reset');
        $mail->addAddress($recipientEmail);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->isHTML(false);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: {$e->getMessage()}"); 
        return false;
    }
}


// Main Logic Starts
require __DIR__ . '/../../include/config.php'; 
if (!isset($conn) || ($conn && $conn->connect_error)) {
    sendJsonResponse(false, "❌ Database connection failed. Check config.php.", null);
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'participant') {
    sendJsonResponse(false, "Unauthorized Access.");
}

$participant_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, "Invalid request method.");
}

// STEP 2: OTP VERIFICATION
if (isset($_POST['otp_code'])) {
    $otp_code_submitted = $_POST['otp_code'];
    $session_otp = $_SESSION['password_change_otp'] ?? null;
    $new_password_hash = $_SESSION['new_password_hash'] ?? null;

    if (!$session_otp || !$new_password_hash) {
        sendJsonResponse(false, "Session data expired. Please restart the password change process.");
    }

    if ($otp_code_submitted === $session_otp) {
        try {
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            if (!$update_stmt) throw new Exception("Prepare statement failed: " . $conn->error);
            $update_stmt->bind_param("si", $new_password_hash, $participant_id);
            
            if ($update_stmt->execute()) {
                $update_stmt->close();
                unset($_SESSION['password_change_otp']);
                unset($_SESSION['new_password_hash']);
                sendJsonResponse(true, "Password successfully changed! You may need to re-login.", $conn, 3); 
            } else {
                throw new Exception("Database update failed: " . $conn->error);
            }
        } catch (Exception $e) {
             sendJsonResponse(false, "An error occurred during password update: " . $e->getMessage(), $conn);
        }
    } else {
        sendJsonResponse(false, "Invalid OTP provided. Please try again.", $conn, 2); 
    }

} else {
    // STEP 1: INITIAL PASSWORD SUBMISSION & OTP GENERATION
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($new_password !== $confirm_password || strlen($new_password) < 6) {
        sendJsonResponse(false, "Validation failed: New password and confirmation must match and be at least 6 characters long.");
    }
    
    $stmt = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
    if (!$stmt) sendJsonResponse(false, "DB preparation failed: " . $conn->error, $conn);
    $stmt->bind_param("i", $participant_id);
    if (!$stmt->execute()) sendJsonResponse(false, "DB query failed: " . $stmt->error, $conn);
    
    $result = $stmt->get_result();
    if ($result->num_rows !== 1) {
        sendJsonResponse(false, "User verification failed. Please re-login.");
    }
    
    $user_email = $result->fetch_assoc()['email'];
    $stmt->close();

    $otp = strval(rand(100000, 999999));
    $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $_SESSION['password_change_otp'] = $otp;
    $_SESSION['new_password_hash'] = $new_hashed_password;
    
    $mail_subject = "Your OTP for CollabBuddy Password Change";
    $mail_body = "Your one-time code to change your CollabBuddy password is: " . $otp . ".\n\nThis code is valid for 5 minutes.\n\nThank you,\nCollabBuddy Team";
    
    if (send_otp_mail($user_email, $mail_subject, $mail_body)) { 
        sendJsonResponse(true, "Verification code sent to your email (" . substr($user_email, 0, 3) . "***@" . explode('@', $user_email)[1] . "). Please enter it below.", $conn, 2); 
    } else {
        unset($_SESSION['password_change_otp']); 
        unset($_SESSION['new_password_hash']);
        sendJsonResponse(false, "Failed to send verification email. Please try again later.", $conn);
    }
}
?>