<?php
session_start();
require 'include/config.php';
require 'vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$role = $_GET['role'] ?? 'participant'; // default role
$_SESSION['role'] = $role;

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    // Check if email exists with the given role
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email=? AND role=? LIMIT 1");
    $stmt->bind_param("ss", $email, $role);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $token = bin2hex(random_bytes(32));
        $expiry = date("Y-m-d H:i:s", time() + 3600); // 1 hour

        $stmt->close();
        $stmt = $conn->prepare("UPDATE users SET reset_token=?, reset_expiry=? WHERE email=? AND role=?");
        $stmt->bind_param("ssss", $token, $expiry, $email, $role);
        $stmt->execute();

        // Send reset link
        $resetLink = "http://localhost/collabuddy/reset_password.php?token=$token";

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = "smtp.gmail.com";
            $mail->SMTPAuth = true;
            $mail->Username = "akhilsatheesan557@gmail.com"; 
            $mail->Password = "oudw pxez ycoq elet";   
            $mail->SMTPSecure = "tls";
            $mail->Port = 587;

            $mail->setFrom("akhilsatheesan557@gmail.com", "CollabBuddy");
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = "Reset your CollabBuddy Password";
            $mail->Body = "
                <p>Hello,</p>
                <p>Click the link below to reset your password. This link is valid for 1 hour.</p>
                <p><a href='$resetLink'>$resetLink</a></p>
                <p>If you did not request this, please ignore this email.</p>
            ";

            $mail->send();
            $message = "<span style='color:green;'>✅ Password reset link sent to your $role email!</span>";
        } catch (Exception $e) {
            $message = "<span style='color:red;'>Mail error: {$mail->ErrorInfo}</span>";
        }
    } else {
        $message = "<span style='color:red;'>❌ No $role account found with this email!</span>";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Forgot Password - CollabBuddy</title>
<link rel="stylesheet" href="css/style.css"/>
<style>
.auth-container {max-width:400px;margin:80px auto;background:#fff;padding:40px;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.1);}
h2{text-align:center;margin-bottom:20px;color:#111827;}
input{width:100%;padding:12px;border:1px solid #ccc;border-radius:8px;margin-bottom:15px;}
.btn-primary{width:100%;padding:12px;background:linear-gradient(90deg,#6366f1,#8b5cf6);color:#fff;border:none;border-radius:8px;font-weight:bold;cursor:pointer;transition:0.3s ease;}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 12px rgba(0,0,0,0.15);}
.message{text-align:center;margin-top:15px;font-size:14px;}
.redirect{text-align:center;margin-top:15px;}
</style>
</head>
<body>
<?php require 'include/navbar.php'; ?>
<div class="auth-container">
<h2>Forgot Password (<?php echo ucfirst($role); ?>)</h2>
<form method="POST">
    <input type="email" name="email" placeholder="Enter your email" required>
    <button type="submit" class="btn-primary">Send Reset Link</button>
</form>
<?php if($message) echo "<p class='message'>{$message}</p>"; ?>
<p class="redirect">Back to <a href="login.php?role=<?php echo urlencode($role); ?>">Login</a></p>
</div>
<?php require 'include/footer.php'; ?>
</body>
</html>
