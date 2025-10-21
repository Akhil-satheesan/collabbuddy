<?php
session_start();
require 'include/config.php';

if (!isset($_SESSION['temp_signup_data']) || !isset($_SESSION['temp_signup_data']['otp_code'])) {
    header("Location: signup.php");
    exit;
}

$temp_data = $_SESSION['temp_signup_data'];
$error = "";

$success_message = "";
if (isset($_SESSION['otp_success_message'])) {
    $success_message = $_SESSION['otp_success_message'];
    unset($_SESSION['otp_success_message']);
}

if (time() - $temp_data['otp_time'] > 600) { 
    unset($_SESSION['temp_signup_data']);
    $error = "❌ OTP expired. Please try signing up again.";
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && !$error) {
    $user_otp = trim($_POST['otp']);
    
    if ($user_otp == $temp_data['otp_code']) {
        
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, is_verified) VALUES (?, ?, ?, ?, 1)");
        $stmt->bind_param("ssss", 
            $temp_data['name'], 
            $temp_data['email'], 
            $temp_data['password'], 
            $temp_data['role']
        );
        
        if ($stmt->execute()) {
            $_SESSION['user_id'] = $conn->insert_id;
            $_SESSION['email'] = $temp_data['email'];
            $_SESSION['role'] = $temp_data['role'];

            unset($_SESSION['temp_signup_data']);

            if ($_SESSION['role'] === 'host') {
                header("Location: host_signup.php"); 
            } else {
                header("Location: participant_dashboard.php");
            }
            exit;
            
        } else {
            $error = "❌ Database error during user creation: " . $conn->error;
        }
        $stmt->close();
        
    } else {
        $error = "❌ Invalid OTP. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - CollabBuddy</title>
    
    <style>
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background: #f3f4f6; 
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .signup-container { 
            max-width: 420px; 
            width: 100%; 
            background: #fff; 
            padding: 30px; 
            border-radius: 12px; 
            box-shadow: 0 6px 18px rgba(0,0,0,0.1); 
        }
        h2 { text-align: center; margin-bottom: 10px; font-size: 24px; color: #111827; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin-bottom: 6px; color: #374151; text-align: center; }
        .text-center { text-align: center; }
        .text-sm { font-size: 14px; }
        .mb-6 { margin-bottom: 1.5rem; }
        .mt-4 { margin-top: 1rem; }
        
        .otp-input-container {
            display: flex;
            justify-content: space-between;
            margin: 0 auto;
            max-width: 320px; 
        }
        .otp-input {
            width: 45px;
            height: 50px;
            text-align: center;
            font-size: 24px;
            border: 2px solid #d1d5db;
            border-radius: 8px;
            margin: 0 4px;
            transition: border-color 0.3s, box-shadow 0.3s;
            caret-color: #4f46e5; 
        }
        .otp-input:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
            outline: none;
        }
        
        .btn-primary {
            background: #4f46e5; color: white; padding: 12px; border: none;
            border-radius: 8px; width: 100%; font-size: 16px; font-weight: bold;
            cursor: pointer; transition: background 0.3s;
        }
        .btn-primary:hover { background: #4338ca; }
        .error { color: #dc2626; text-align:center; margin-bottom: 15px; font-weight: 500; padding: 10px; border: 1px solid #fee2e2; background: #fef2f2; border-radius: 6px; }
        .redirect { text-align: center; font-size: 14px; }
        .text-indigo-600 { color: #4f46e5; text-decoration: none; }
        .text-indigo-600:hover { text-decoration: underline; }

        .success-notification {
            background: #dcfce7; 
            color: #047857; 
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
            border: 1px solid #a7f3d0;
        }
    </style>
</head>
<body>
<div class="signup-container">
    <h2>Verify Your Email</h2>
    
    <?php if ($success_message): ?>
      <p class="success-notification"><?php echo $success_message; ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
      <p class="error"><?php echo $error; ?></p>
    <?php endif; ?>

    <p class="text-center text-sm text-gray-600 mb-6">
        An OTP has been sent to **<?php echo htmlspecialchars($temp_data['email']); ?>**.
        Please enter the 6-digit code below.
    </p>

    <form method="POST" action="" onsubmit="return combineOTP()">
        <div class="form-group">
            <label>One-Time Password (OTP)</label>
            
            <div class="otp-input-container">
                <?php for ($i = 1; $i <= 6; $i++): ?>
                    <input type="number" 
                           class="otp-input" 
                           id="otp-<?php echo $i; ?>" 
                           maxlength="1" 
                           inputmode="numeric" 
                           required 
                           autocomplete="off">
                <?php endfor; ?>
            </div>
            
            <input type="hidden" name="otp" id="hidden-otp-input">
        </div>

        <button type="submit" class="btn-primary">Verify Account</button>
    </form>
    
    <p class="redirect mt-4">
        Didn't receive the code? 
        <a href="signup.php?resend=1" class="text-indigo-600 font-bold">Resend OTP</a>
    </p>
</div>

<script>
    const otpInputs = document.querySelectorAll('.otp-input');
    const hiddenOtpInput = document.getElementById('hidden-otp-input');

    otpInputs.forEach((input, index) => {
        input.addEventListener('input', (e) => {
            if (e.target.value.length === 1 && index < otpInputs.length - 1) {
                otpInputs[index + 1].focus();
            }
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && e.target.value.length === 0 && index > 0) {
                otpInputs[index - 1].focus();
            }
        });
        
        input.addEventListener('paste', (e) => {
            const paste = e.clipboardData.getData('text');
            if (paste.length === 6 && /^\d+$/.test(paste)) {
                e.preventDefault();
                paste.split('').forEach((char, i) => {
                    if (otpInputs[index + i]) {
                        otpInputs[index + i].value = char;
                    }
                });
                otpInputs[otpInputs.length - 1].focus();
            }
        });
    });
    
    function combineOTP() {
        let otpValue = '';
        otpInputs.forEach(input => {
            otpValue += input.value;
        });

        if (otpValue.length === 6) {
            hiddenOtpInput.value = otpValue;
            return true;
        } else {
            alert('Please enter the complete 6-digit OTP.');
            otpInputs[0].focus();
            return false;
        }
    }
</script>
</body>
</html>