<?php
session_start();

// ✅ Redirect if already logged in
if (isset($_SESSION["admin_loggedin"]) && $_SESSION["admin_loggedin"] === true) {
    header("location: admin_dashboard.php");
    exit;
}

require_once 'db_connect.php'; // Ensure correct path

$username = $password = "";
$username_err = $password_err = $login_err = "";

// ✅ Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1️⃣ Username validation
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter username.";
    } else {
        $username = trim($_POST["username"]);
    }

    // 2️⃣ Password validation
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }

    // 3️⃣ If no validation errors
    if (empty($username_err) && empty($password_err)) {
        $sql = "SELECT admin_id, username, password FROM admins WHERE username = ?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_username);
            $param_username = $username;

            if ($stmt->execute()) {
                $stmt->store_result();

                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($id, $fetched_username, $stored_password);
                    if ($stmt->fetch()) {

                        // ✅ Plaintext comparison (no hashing)
                        if (trim($password) === trim($stored_password)) {

                            // ✅ Login success
                            $_SESSION["admin_loggedin"] = true;
                            $_SESSION["admin_id"] = $id;
                            $_SESSION["admin_username"] = $fetched_username;

                            header("location: admin_dashboard.php");
                            exit;
                        } else {
                            $login_err = "Invalid username or password.";
                        }
                    }
                } else {
                    $login_err = "Invalid username or password.";
                }
            } else {
                $login_err = "Oops! Something went wrong with the database. Please try again later.";
            }

            $stmt->close();
        }
    }

    // ✅ Close DB connection
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - CollabBuddy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { min-height: 100vh; box-sizing: border-box; }
        .demo-badge { position: fixed; top: 10px; right: 10px; background: #ef4444; color: white; padding: 8px 16px; border-radius: 20px; font-size: 12px; font-weight: bold; z-index: 1000; box-shadow: 0 2px 10px rgba(0,0,0,0.2); }
        .login-container { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .login-card { backdrop-filter: blur(10px); background: rgba(255, 255, 255, 0.95); box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15); }
        .input-field { transition: all 0.3s ease; }
        .input-field.is-invalid { border-color: #dc3545; padding-right: 2.25rem; background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3cpath stroke-linecap='round' d='M6 8.25V8.25'/%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: right 0.65rem center; background-size: 1.25em 1.25em; }
        .input-field:focus { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1); }
        .login-btn { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); transition: all 0.3s ease; }
        .login-btn:hover { transform: translateY(-2px); box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4); }
        .floating-shapes { position: absolute; width: 100%; height: 100%; overflow: hidden; z-index: 1; }
        .shape { position: absolute; background: rgba(255, 255, 255, 0.1); border-radius: 50%; animation: float 6s ease-in-out infinite; }
        .shape:nth-child(1) { width: 80px; height: 80px; top: 20%; left: 10%; animation-delay: 0s; }
        .shape:nth-child(2) { width: 120px; height: 120px; top: 60%; right: 10%; animation-delay: 2s; }
        .shape:nth-child(3) { width: 60px; height: 60px; bottom: 20%; left: 20%; animation-delay: 4s; }
        @keyframes float { 0%, 100% { transform: translateY(0px) rotate(0deg); } 50% { transform: translateY(-20px) rotate(180deg); } }
    </style>
</head>
<body class="font-sans">
    <div class="demo-badge">ADMIN PORTAL</div>
    
    <div class="login-container flex items-center justify-center min-h-full relative">
        <div class="floating-shapes">
            <div class="shape"></div>
            <div class="shape"></div>
            <div class="shape"></div>
        </div>
        
        <div class="login-card rounded-2xl p-8 w-full max-w-md mx-4 relative z-10">
            <div class="text-center mb-8">
                <div class="w-20 h-20 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg">
                    <span class="text-3xl text-white">🔐</span>
                </div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">CollabBuddy Admin</h1>
                <p class="text-gray-600">Sign in to access the administrator dashboard</p>
            </div>
            
            <?php 
            if (!empty($login_err)) {
                echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">';
                echo '<div class="flex items-center">';
                echo '<span class="text-red-600 mr-2">❌</span>';
                echo '<p class="text-red-800 text-sm font-medium">Authentication Failed: ' . $login_err . '</p>';
                echo '</div></div>';
            }
            ?>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-6">
                
                <div>
                    <label for="username" class="block text-sm font-semibold text-gray-700 mb-2">Username</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-400">👤</span>
                        </div>
                        <input 
                            type="text" 
                            id="username" 
                            name="username"
                            class="input-field w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?> "
                            placeholder="Enter your username"
                            value="<?php echo htmlspecialchars($username); ?>"
                            required
                        >
                        <p class="text-sm text-red-500 mt-1"><?php echo $username_err; ?></p>
                    </div>
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-400">🔒</span>
                        </div>
                        <input 
                            type="password" 
                            id="password" 
                            name="password"
                            class="input-field w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?> "
                            placeholder="Enter your password"
                            required
                        >
                        <button 
                            type="button" 
                            class="absolute inset-y-0 right-0 pr-3 flex items-center"
                            onclick="togglePassword()"
                        >
                            <span id="eyeIcon" class="text-gray-400 hover:text-gray-600 cursor-pointer">👁️</span>
                        </button>
                        <p class="text-sm text-red-500 mt-1"><?php echo $password_err; ?></p>
                    </div>
                </div>
                
                <button 
                    type="submit" 
                    class="login-btn w-full py-3 px-4 text-white font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Sign In to Dashboard
                </button>
            </form>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                eyeIcon.textContent = '🙈';
            } else {
                passwordField.type = 'password';
                eyeIcon.textContent = '👁️';
            }
        }
    </script>
</body>
</html>
