<?php
// FILE: include/host_sections/host_process_password_change_otp.php

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
        // 🔑 നിങ്ങളുടെ യഥാർത്ഥ Gmail credentials (App Password) ഉപയോഗിക്കുക
        $mail->Username   = 'akhilsatheesan557@gmail.com'; 
        $mail->Password   = 'oudw pxez ycoq elet';         
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        $mail->setFrom('akhilsatheesan557@gmail.com', 'CollabBuddy Host Password Reset');
        $mail->addAddress($recipientEmail);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->isHTML(false);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error (Host): {$e->getMessage()}"); 
        return false;
    }
}


// Main Logic Starts
require __DIR__ . '/../../include/config.php'; 
if (!isset($conn) || ($conn && $conn->connect_error)) {
    sendJsonResponse(false, "❌ Database connection failed. Check config.php.", null);
}

// Host Authorization Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host') {
    sendJsonResponse(false, "Unauthorized Access. Host session required.", $conn);
}

// Host-ൻ്റെ user_id ഉപയോഗിക്കുന്നു
$host_user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, "Invalid request method.", $conn);
}

$action = $_POST['action'] ?? (isset($_POST['otp_code']) ? 'verify_and_change' : 'send_otp');


if ($action === 'verify_and_change') {
    // STEP 2: OTP VERIFICATION
    $otp_code_submitted = $_POST['otp_code'] ?? '';
    $session_otp = $_SESSION['password_change_otp'] ?? null;
    $new_password_hash = $_SESSION['new_password_hash'] ?? null;
    $otp_time = $_SESSION['password_change_otp_time'] ?? 0;

    // OTP Expiry Check (5 minutes = 300 seconds)
    if (!$session_otp || !$new_password_hash || (time() - $otp_time) > 300) {
        unset($_SESSION['password_change_otp'], $_SESSION['new_password_hash'], $_SESSION['password_change_otp_time']);
        sendJsonResponse(false, "Verification code expired or session data missing. Please restart the process.", $conn, 2);
    }

    if ($otp_code_submitted === $session_otp) {
        try {
            // 🔑 CHANGE: 'users' ടേബിൾ ഉപയോഗിക്കുന്നു, role = 'host' ചെക്ക് ചെയ്യുന്നു.
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ? AND role = 'host'");
            if (!$update_stmt) throw new Exception("Prepare statement failed: " . $conn->error);
            $update_stmt->bind_param("si", $new_password_hash, $host_user_id);
            
            if ($update_stmt->execute()) {
                $update_stmt->close();
                
                unset($_SESSION['password_change_otp']);
                unset($_SESSION['new_password_hash']);
                unset($_SESSION['password_change_otp_time']);
                
                sendJsonResponse(true, "Password successfully changed! Redirecting to profile.", $conn, 3); 
            } else {
                throw new Exception("Database update failed: " . $conn->error);
            }
        } catch (Exception $e) {
              sendJsonResponse(false, "An error occurred during password update: " . $e->getMessage(), $conn, 2);
        }
    } else {
        sendJsonResponse(false, "Invalid OTP provided. Please try again.", $conn, 2); 
    }

} elseif ($action === 'send_otp') {
    // STEP 1: INITIAL PASSWORD SUBMISSION & OTP GENERATION
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Backend validation (Min 8 chars, as per JS validation)
    if ($new_password !== $confirm_password || strlen($new_password) < 8) { 
        sendJsonResponse(false, "Validation failed: New password and confirmation must match and meet security requirements (Min 8 chars).", $conn);
    }
    
    // 🔑 CHANGE: 'users' ടേബിളിൽ നിന്ന് email കണ്ടെത്തുന്നു, role = 'host' ചെക്ക് ചെയ്യുന്നു.
    $stmt = $conn->prepare("SELECT email FROM users WHERE user_id = ? AND role = 'host'");
    if (!$stmt) sendJsonResponse(false, "DB preparation failed: " . $conn->error, $conn);
    $stmt->bind_param("i", $host_user_id);
    if (!$stmt->execute()) sendJsonResponse(false, "DB query failed: " . $stmt->error, $conn);
    
    $result = $stmt->get_result();
    if ($result->num_rows !== 1) {
        sendJsonResponse(false, "Host verification failed. Please re-login or ensure your account role is correct.", $conn);
    }
    
    $user_email = $result->fetch_assoc()['email'];
    $stmt->close();

    $otp = strval(rand(100000, 999999));
    $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Store data in session for Step 2
    $_SESSION['password_change_otp'] = $otp;
    $_SESSION['new_password_hash'] = $new_hashed_password;
    $_SESSION['password_change_otp_time'] = time(); // OTP Expiry time set
    
    $mail_subject = "Your OTP for CollabBuddy Host Password Change";
    $mail_body = "Your one-time code to change your CollabBuddy Host password is: " . $otp . ".\n\nThis code is valid for 5 minutes.\n\nThank you,\nCollabBuddy Team";
    
    if (send_otp_mail($user_email, $mail_subject, $mail_body)) { 
        sendJsonResponse(true, "Verification code sent to your email (" . substr($user_email, 0, 3) . "***@" . explode('@', $user_email)[1] . "). Please enter it below.", $conn, 2); 
    } else {
        unset($_SESSION['password_change_otp']); 
        unset($_SESSION['new_password_hash']);
        unset($_SESSION['password_change_otp_time']);
        sendJsonResponse(false, "Failed to send verification email. Please try again later.", $conn);
    }
} else {
     sendJsonResponse(false, "Invalid action parameter.", $conn);
}
?>