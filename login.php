<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
require 'include/config.php';

if (isset($_GET['role'])) {
    $_SESSION['role'] = $_GET['role'];
}

$role = $_SESSION['role'] ?? 'participant';
$error = "";

if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $current_role = $_SESSION['role'];
    if ($current_role === 'host') {
        header("Location: host_dashboard.php");
        exit;
    } elseif ($current_role === 'participant') {
        header("Location: participate_dashboard.php");
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    // 1. UPDATED QUERY to fetch user status and suspension details
    $stmt = $conn->prepare("SELECT user_id, name, password, role, is_verified, status, suspension_end_date, suspension_reason FROM users WHERE email=? AND role=?");
    $stmt->bind_param("ss", $email, $role);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        // 2. UPDATED bind_result to include new columns
        $stmt->bind_result($user_id, $name, $hashed_password, $role, $is_verified, $status, $suspension_end_date, $suspension_reason);
        $stmt->fetch();
        $stmt->close();

        if (!$is_verified) {
            $error = "❌ Please verify your email before logging in. ";
        } elseif (password_verify($password, $hashed_password)) {
            
            // 3. --- NEW: CHECK USER STATUS (Suspended / Banned) ---

            $current_time = new DateTime();
            $allow_login = true;

            if ($status === 'Banned') {
                $error = "❌ Your account has been **permanently banned**. Please contact support for assistance.";
                $allow_login = false;
            } elseif ($status === 'Suspended') {
                $end_date = new DateTime($suspension_end_date);
                
                if ($current_time < $end_date) {
                    // Suspension is still active
                    $reason_display = htmlspecialchars($suspension_reason ?? 'No reason provided');
                    $end_date_display = date("F j, Y, g:i a", strtotime($suspension_end_date));
                    $error = "❌ Your account is currently **suspended** until **{$end_date_display}** (Reason: {$reason_display}).";
                    $allow_login = false;
                } else {
                    // Suspension has expired. Automatically reactivate the user.
                    $conn->query("UPDATE users SET status = 'Active', suspension_end_date = NULL, suspension_reason = NULL WHERE user_id = $user_id");
                    // $allow_login remains true, proceed to login
                }
            }
            
            // 4. Proceed only if login is allowed (User is Active or Suspension expired)
            if ($allow_login) {
                $_SESSION['user_id'] = $user_id;
                $_SESSION['email']   = $email;
                $_SESSION['name']    = $name;
                $_SESSION['role']    = $role;

                if ($role === "host") {
                    $stmt = $conn->prepare("SELECT * FROM hosts WHERE host_id=?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $stmt->store_result();

                    if ($stmt->num_rows === 0) {
                        header("Location: host_signup.php");
                    } else {
                        $_SESSION['host_profile_completed'] = true;
                        header("Location: host_dashboard.php");
                    }
                    $stmt->close();

                } else {
                    $stmt = $conn->prepare("SELECT * FROM participants WHERE participant_id=?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $stmt->store_result();

                    if ($stmt->num_rows === 0) {
                        header("Location: participate_signup.php");
                    } else {
                        header("Location: participate_dashboard.php");
                    }
                    $stmt->close();
                }
                exit;
            }

        } else {
            $error = "❌ Invalid password.";
        }
    } else {
        $error = "❌ No account found with that email.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CollabBuddy - Login</title>
    <link rel="stylesheet" href="css/style.css"/>
    <style>
        .login-container {
            max-width: 400px;
            margin: 80px auto;
            background:#fff;
            padding:40px;
            border-radius:12px;
            box-shadow:0 8px 24px rgba(0,0,0,0.1);
        }
        h2 {text-align:center; color:#111827; margin-bottom:20px;}
        .form-group {margin-bottom:15px;}
        label {font-weight:600; display:block; margin-bottom:6px;}
        input {width:100%; padding:12px; border:1px solid #ccc; border-radius:8px; font-size:16px;}
        .btn-primary {
            width:100%; padding:12px; margin-top:10px;
            background:linear-gradient(90deg,#6366f1,#8b5cf6);
            border:none; border-radius:8px;
            color:#fff; font-weight:bold; font-size:16px;
            cursor:pointer; transition:.2s;
        }
        .btn-primary:hover {transform:translateY(-2px); box-shadow:0 6px 12px rgba(0,0,0,0.15);}
        .error {color:#dc2626; text-align:center; margin-bottom:15px; font-weight: bold;}
        .redirect {text-align:center; margin-top:15px;}
    </style>
</head>
<body>
<?php require 'include/navbar.php'; ?>

<div class="login-container">
    <h2>Login to CollabBuddy as <?php echo ucfirst($role); ?></h2>

    <?php if ($error): ?>
        <p class="error"><?= $error ?></p>
    <?php endif; ?>

    <form method="POST">
    <div class="form-group">
        <label>Email Address</label>
        <input type="email" id="email" name="email" placeholder="Enter your email" required>
        <small id="email-feedback" style="color:red;"></small>
    </div>
    <div class="form-group">
        <label>Password</label>
        <input type="password" id="password" name="password" placeholder="Enter password" required>
    </div>
        <button type="submit" class="btn-primary">Login</button>
    </form>

    <p class="redirect">
        Don’t have an account? 
        <a href="signup.php?role=<?php echo urlencode($_SESSION['role'] ?? 'participant'); ?>">Sign up</a>
    </p>
    <p class="redirect">
        Forgot your password? 
        <a href="forgot_password.php?role=<?php echo urlencode($_SESSION['role'] ?? 'participant'); ?>">Forgot password</a>
    </p>
</div>

<?php require 'include/footer.php'; ?>
<script>
const emailInput = document.getElementById('email');
const feedback = document.getElementById('email-feedback');

emailInput.addEventListener('input', function() {
    const email = emailInput.value;
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(email)) {
      feedback.style.color = 'red';
        feedback.textContent = '❌ Invalid email format';
        return;
    }
    fetch('check_email.php?email=' + encodeURIComponent(email))
        .then(res => res.json())
        .then(data => {
            // Note: The role check here might give a false positive if the email is registered
            // under the *other* role (e.g., host trying to login as participant).
            // The PHP script handles the role check correctly upon form submission.
            if (data.exists) {
                feedback.style.color = 'green';
                feedback.textContent = '✅ Email registered';
            } else {
                feedback.style.color = 'red';
                feedback.textContent = '❌ Not registered email';
            }
        })
        .catch(err => {
            feedback.style.color = 'red';
            feedback.textContent = '⚠️ Error checking email';
        });
});
</script>
</body>
</html>