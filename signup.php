<?php
session_start();
require 'include/config.php';
require "vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (isset($_GET['role'])) {
    $_SESSION['role'] = $_GET['role'];
}

$role = $_SESSION['role'] ?? 'participant';
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "❌ Invalid email address";
    } else {
        list($userPart, $domain) = explode('@', $email);
        if (!checkdnsrr($domain, 'MX') && $domain !== 'gmail.com' && $domain !== 'yahoo.com') {
            $error = "❌ Email domain is not valid or does not exist";
        }
    }

    if (!$error && $password !== $confirm) {
        $error = "❌ Passwords do not match";
    } elseif (!$error && strlen($password) < 6) {
        $error = "❌ Password must be at least 6 characters";
    } elseif (!$error) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND role = ?");
        $stmt->bind_param("ss", $email, $role);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "❌ Email already exists";
        } else {
            $stmt->close();

            $otp = mt_rand(100000, 999999);
            $hashedPass = password_hash($password, PASSWORD_BCRYPT);
            $_SESSION['temp_signup_data'] = [
                'name' => $name,
                'email' => $email,
                'password' => $hashedPass,
                'role' => $role,
                'otp_code' => $otp,
                'otp_time' => time()
            ];

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'akhilsatheesan557@gmail.com';
                $mail->Password = 'oudw pxez ycoq elet';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = 465;

                $mail->setFrom('akhilsatheesan557@gmail.com', 'CollabBuddy Admin');
                $mail->addAddress($email);
                $mail->Subject = "CollabBuddy - Email Verification";
                $mail->Body = "Hello $name,\n\nYour One-Time Password (OTP) is: $otp\nThis OTP will expire soon.\n\nPlease enter it on the verification page.\n\nThank you,\nCollabBuddy Team";

                echo "<h1>🔑 OTP for Debugging: **{$otp}**</h1>";

                $mail->send();

                echo "<script>alert('OTP sent successfully! Check your email.'); window.location.href='otp.php';</script>";
                exit;
            } catch (Exception $e) {
                unset($_SESSION['temp_signup_data']);
                $error = "Email could not be sent. Error: {$mail->ErrorInfo}";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Signup - CollabBuddy</title>
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background: #f3f4f6;
      margin: 0;
      padding: 0;
      min-height: 100vh;
    }
    .main-content {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: calc(100vh - 120px);
        padding: 40px 0;
    }
    .signup-container {
      max-width: 420px;
      width: 100%;
      background: #fff;
      padding: 40px;
      border-radius: 12px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.1);
    }
    h2 { text-align: center; margin-bottom: 25px; font-size: 24px; color: #111827; }
    .form-group { margin-bottom: 20px; }
    label { display: block; font-weight: 600; margin-bottom: 6px; color: #374151; }
    input {
      width: 100%; padding: 12px; border: 1px solid #d1d5db;
      border-radius: 8px; font-size: 16px;
      transition: border 0.3s;
    }
    input:focus { border-color: #6366f1; outline: none; }
    .validation-message { 
      font-size: 13px; 
      margin-top: 5px; 
      min-height: 18px;
      font-weight: 500;
    }
    .valid { color: #059669; }
    .invalid { color: #dc2626; }
    .btn-primary {
      width:100%; padding:12px; margin-top:10px;
      background:linear-gradient(90deg,#6366f1,#8b5cf6);
      border:none; border-radius:8px;
      color:#fff; font-weight:bold; font-size:16px;
      cursor:pointer; transition:all .2s;
    }
    .btn-primary:hover:not(:disabled) { 
      transform:translateY(-2px);
      box-shadow:0 6px 12px rgba(0,0,0,0.15);
    }
    .btn-primary:disabled { 
      background: #9ca3af; 
      cursor: not-allowed;
      box-shadow: none;
      transform: none;
    }
    .error { color: #dc2626; margin-bottom: 15px; text-align: center; font-weight: 500; }
    .redirect { text-align: center; margin-top: 15px; font-size: 14px; }
  </style>
</head>
<body>
<?php require 'include/navbar.php'; ?>

<div class="main-content">
<div class="signup-container">
  <h2>Create Your <?php echo ucfirst($role); ?> Account</h2>

  <?php if ($error): ?>
    <p class="error"><?php echo $error; ?></p>
  <?php endif; ?>

  <form method="POST" action="" id="signup-form">
    <div class="form-group">
      <label>Full Name</label>
      <input type="text" name="name" id="name" required>
    </div>

    <div class="form-group">
      <label>Email Address</label>
      <input type="email" name="email" id="email" required>
      <div id="email-status" class="validation-message"></div>     </div>

    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" id="password" required>
      <div id="password-status" class="validation-message"></div>     </div>

    <div class="form-group">
      <label>Confirm Password</label>
      <input type="password" name="confirm_password" id="confirm_password" required>
      <div id="match-status" class="validation-message"></div>     </div>

    <button type="submit" class="btn-primary" id="submit-btn" disabled>Continue</button>
  </form>

  <p class="redirect">
    Already have an account? 
    <a href="login.php?role=<?php echo urlencode($_SESSION['role'] ?? 'participant'); ?>">Login</a>
  </p>
</div>
</div>

<script>
const form = document.getElementById('signup-form');
const nameInput = document.getElementById('name');
const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const confirmInput = document.getElementById('confirm_password');
const submitBtn = document.getElementById('submit-btn');

const emailStatus = document.getElementById('email-status');
const passwordStatus = document.getElementById('password-status');
const matchStatus = document.getElementById('match-status');

let isNameValid = false;
let isEmailValid = false;
let isPasswordStrong = false;
let isPasswordMatch = false;

const validateEmail = () => {
    const email = emailInput.value.trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/; 

    if (email.length === 0) {
        emailStatus.innerHTML = '';
        isEmailValid = false;
    } else if (emailRegex.test(email)) {
        emailStatus.innerHTML = '✅ Valid email format.';
        emailStatus.className = 'validation-message valid';
        isEmailValid = true;
    } else {
        emailStatus.innerHTML = '❌ Invalid email format (eg: user@domain.com)';
        emailStatus.className = 'validation-message invalid';
        isEmailValid = false;
    }
    checkFormValidity();
};

const validatePassword = () => {
    const password = passwordInput.value;
    let checks = [];

    checks.push({ condition: password.length >= 6, message: 'Minimum 6 characters' });
    checks.push({ condition: /[A-Z]/.test(password), message: 'Uppercase (A-Z)' });
    checks.push({ condition: /[0-9]/.test(password), message: 'Number (0-9)' });

    const failedChecks = checks.filter(c => !c.condition);
    const requiredFailed = checks.filter(c => c.message.includes('Minimum 6 characters') && !c.condition);

    if (password.length === 0) {
        passwordStatus.innerHTML = '';
        isPasswordStrong = false;
    } else if (requiredFailed.length === 0) {
        passwordStatus.innerHTML = '💪 Password strength: <span class="valid">Good</span>';
        passwordStatus.className = 'validation-message';
        isPasswordStrong = true;
    } else {
        passwordStatus.innerHTML = '⚠️ Required: ' + requiredFailed.map(c => c.message).join(', ');
        passwordStatus.className = 'validation-message invalid';
        isPasswordStrong = false;
    }

    validatePasswordMatch();
    checkFormValidity();
};

const validatePasswordMatch = () => {
    const password = passwordInput.value;
    const confirm = confirmInput.value;

    if (confirm.length === 0 && password.length === 0) {
        matchStatus.innerHTML = '';
        isPasswordMatch = false;
    } else if (password.length === 0) {
        matchStatus.innerHTML = '⚠️ Enter password first.';
        matchStatus.className = 'validation-message invalid';
        isPasswordMatch = false;
    } else if (password === confirm) {
        matchStatus.innerHTML = '✅ Passwords match.';
        matchStatus.className = 'validation-message valid';
        isPasswordMatch = true;
    } else {
        matchStatus.innerHTML = '❌ Passwords do not match!';
        matchStatus.className = 'validation-message invalid';
        isPasswordMatch = false;
    }
    checkFormValidity();
};

const checkFormValidity = () => {
 const isNameFilled = nameInput.value.trim().length > 0; 
    const allValid = isNameFilled && isEmailValid && isPasswordStrong && isPasswordMatch;

    submitBtn.disabled = !allValid;
};

nameInput.addEventListener('input', checkFormValidity);
emailInput.addEventListener('input', validateEmail);
passwordInput.addEventListener('input', validatePassword);
confirmInput.addEventListener('input', validatePasswordMatch);

checkFormValidity();
</script>

<?php require 'include/footer.php'; ?>
</body>
</html>