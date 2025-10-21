<?php
session_start();
require 'include/config.php'; // ഇവിടെ config.php വഴി ഡാറ്റാബേസ് കണക്ഷൻ ലഭിക്കുന്നു

$token = $_GET['token'] ?? null;
if (!$token) {
    die("<p style='text-align:center;color:red;margin-top:50px;'>Invalid or missing reset token.</p>");
}

$user_id = null;
$email = null;
$reset_expiry = null;

// ടോക്കൺ വാലിഡേറ്റ് ചെയ്യുമ്പോൾ, യൂസർ ID-യും, ഇമെയിലും, എക്സ്പയറി സമയവും ഫെച്ച് ചെയ്യുന്നു.
$stmt = $conn->prepare("SELECT user_id, email, reset_expiry FROM users WHERE reset_token=?");
$stmt->bind_param("s", $token);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($user_id, $email, $reset_expiry); 
$stmt->fetch();

// ടോക്കൺ വാലിഡേഷൻ
if ($stmt->num_rows === 0 || strtotime($reset_expiry) < time()) {
    die("<p style='text-align:center;color:red;margin-top:50px;'>This reset link is invalid or expired.</p>");
}
$stmt->close();

// =========================================================================
// 🚀 യൂസറിൻ്റെ റോൾ കണ്ടെത്തുന്നു (പുതിയ ലോജിക്)
// =========================================================================
$role = "User"; // Default value

if ($user_id) {
    // 1. Host ആണോ എന്ന് പരിശോധിക്കുന്നു
    $host_stmt = $conn->prepare("SELECT host_id FROM hosts WHERE host_id = ?");
    $host_stmt->bind_param("i", $user_id);
    $host_stmt->execute();
    $host_stmt->store_result();

    if ($host_stmt->num_rows > 0) {
        $role = "Host";
    }
    $host_stmt->close();

    // 2. Participant ആണോ എന്ന് പരിശോധിക്കുന്നു (Host അല്ലെങ്കിൽ മാത്രം)
    if ($role !== "Host") {
        $participant_stmt = $conn->prepare("SELECT participant_id FROM participants WHERE participant_id = ?");
        $participant_stmt->bind_param("i", $user_id);
        $participant_stmt->execute();
        $participant_stmt->store_result();

        if ($participant_stmt->num_rows > 0) {
            $role = "Participant";
        }
        $participant_stmt->close();
    }
}
// =========================================================================

$error = "";

// Handle password update
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Password validation logic
    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = "Password must include at least one uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = "Password must include at least one lowercase letter.";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = "Password must include at least one number.";
    } elseif (!preg_match('/[!@#$&*]/', $password)) {
        $error = "Password must include at least one special character (!@#$&*).";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        // User ID ഉപയോഗിച്ച് പാസ്‌വേഡ് അപ്ഡേറ്റ് ചെയ്യുന്നു.
        $update = $conn->prepare("UPDATE users SET password=?, reset_token=NULL, reset_expiry=NULL WHERE user_id=?");
        $update->bind_param("si", $hashedPassword, $user_id); 
        $update->execute();

        if ($update->affected_rows > 0) {
            header("Location: login.php?reset=success");
            exit;
        } else {
            $error = "Something went wrong. Please try again.";
        }
        $update->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reset Password - CollabBuddy</title>
<link rel="stylesheet" href="css/style.css"/>
<style>
.auth-container {
    max-width: 420px;
    margin: 80px auto;
    background: #fff;
    padding: 35px;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}
h2 {
    text-align: center;
    margin-bottom: 20px;
    color: #111827;
}
/* പുതിയ സ്റ്റൈൽ: റോൾ മെസ്സേജിന് വേണ്ടി */
.role-message {
    text-align: center; 
    margin-bottom: 20px; 
    font-size: 16px; 
    color: #4b5563;
    padding: 8px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background-color: #f9fafb;
}

input {
    width: 100%;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 8px;
    margin-bottom: 15px;
    font-size: 14px;
}
.btn-primary {
    width: 100%;
    padding: 12px;
    background: linear-gradient(90deg,#6366f1,#8b5cf6);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s ease;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.15);
}
.error {
    color: red;
    text-align: center;
    margin-bottom: 15px;
}
.show-pass {
    cursor: pointer;
    font-size: 13px;
    color: #6366f1;
    display: inline-block;
    margin-bottom: 12px;
}
</style>
</head>
<body>
<?php require 'include/navbar.php'; ?>
<div class="auth-container">
    <h2>Set New Password</h2>
    
    <p class="role-message">
        You are resetting the password for the role: <strong><?php echo htmlspecialchars($role); ?></strong>
    </p>

    <?php if ($error) echo "<p class='error'>$error</p>"; ?>

    <form method="POST">
        <label>New Password</label>
        <input type="password" name="password" id="password" placeholder="Enter new password" required>
        <span class="show-pass" id="togglePass">Show Password</span>

        <label>Confirm Password</label>
        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm password" required>

        <button type="submit" class="btn-primary">Update Password</button>
    </form>
</div>
<?php require 'include/footer.php'; ?>

<script>
const pass = document.getElementById("password");
const confirmPass = document.getElementById("confirm_password");
const toggle = document.getElementById("togglePass");

toggle.addEventListener("click", () => {
    if(pass.type === "password") {
        pass.type = "text";
        confirmPass.type = "text";
        toggle.textContent = "Hide Password";
    } else {
        pass.type = "password";
        confirmPass.type = "password";
        toggle.textContent = "Show Password";
    }
});
</script>
</body>
</html>