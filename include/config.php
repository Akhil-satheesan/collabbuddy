
<?php
// FILE: C:\xampp\htdocs\collabuddy\include\config.php

// PHP Error Reporting (Optional, but highly recommended for debugging)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// SESSION START
if (session_status() == PHP_SESSION_NONE) {
 session_start();
}

// =========================================================================
// DATABASE CONFIGURATION
// =========================================================================
$host  = "localhost";
$username = "root";
$password = "";
$dbname  = "collabbuddy";

// Database Connection
$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
 die("Database connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");


// ⭐ FIX 1: Define get_db_connection() to resolve the Fatal Error.
// This allows files like one_to_one_chat.php to get the connection object.
if (!function_exists('get_db_connection')) {
    function get_db_connection() {
        global $conn;
        return $conn;
    }
}

// =========================================================================
// EMAIL CONFIGURATION (PHPMailer)
// =========================================================================
// The path below assumes vendor is in the root: collabuddy/vendor/
require __DIR__ . '/../vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ⭐ FIX 2: Use defined() check for constants to resolve the "already defined" Warnings.
if (!defined('MAIL_HOST')) {
    define('MAIL_HOST', 'smtp.example.com');
}
if (!defined('MAIL_USERNAME')) {
    define('MAIL_USERNAME', 'youremail@example.com');
}
if (!defined('MAIL_PASSWORD')) {
    define('MAIL_PASSWORD', 'YourAppPassword'); 
}
if (!defined('MAIL_PORT')) {
    define('MAIL_PORT', 587); 
}
if (!defined('MAIL_SENDER_NAME')) {
    define('MAIL_SENDER_NAME', 'CollabBuddy Notifications'); 
}

// ⭐ FIX 3: Use function_exists() check for send_mail() to resolve the "redeclare" Fatal Error.
if (!function_exists('send_mail')) { 
    function send_mail($to, $subject, $body, $is_html = false) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
            $mail->Port = MAIL_PORT;
            
            $mail->setFrom(MAIL_USERNAME, MAIL_SENDER_NAME);
            $mail->addAddress($to); 
            $mail->isHTML($is_html); 
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            // Log the error for debugging purposes
            error_log("Email sending failed for {$to}. Mailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }
}

// NO CLOSING PHP TAG