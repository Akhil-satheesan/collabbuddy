<?php
if (session_status() == PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'participant') {
    echo "Unauthorized";
    exit;
}

require 'include/config.php';

$userId = $_SESSION['user_id'];

// Check if we have a pending password in session
if (!isset($_SESSION['pending_password'])) {
    $_SESSION['msg'] = "No password update pending.";
    header("Location: profile.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp_input = trim($_POST['otp']);

    // Fetch OTP from DB
    $stmt = $conn->prepare("SELECT otp_code, otp_expiry FROM users WHERE user_id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!$result) {
        $_SESSION['msg'] = "User not found.";
        header("Location: profile.php");
        exit;
    }

    $otp_db = $result['otp_code'];
    $expiry = $result['otp_expiry'];

    if (time() > strtotime($expiry)) {
        $_SESSION['msg'] = "OTP has expired. Try updating password again.";
        unset($_SESSION['pending_password']);
        header("Location: profile.php");
        exit;
    }

    if ($otp_input != $otp_db) {
        $_SESSION['msg'] = "Invalid OTP. Please try again.";
        header("Location: participant_verify_otp.php");
        exit;
    }

    // OTP valid → update password
    $new_hashed_password = $_SESSION['pending_password'];

    $stmt = $conn->prepare("UPDATE users SET password=?, otp_code=NULL, otp_expiry=NULL WHERE user_id=?");
    $stmt->bind_param("si", $new_hashed_password, $userId);
    $stmt->execute();

    unset($_SESSION['pending_password']);
    $_SESSION['msg'] = "Password updated successfully!";
    header("Location: profile.php");
    exit;
}
?>

<!-- HTML Form for OTP Input -->
<div class="max-w-md mx-auto bg-white p-6 rounded shadow mt-10">
    <h2 class="text-xl font-bold mb-4">Verify OTP to Change Password</h2>

    <?php if (isset($_SESSION['msg'])): ?>
        <div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">
            <?= $_SESSION['msg']; unset($_SESSION['msg']); ?>
        </div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">Enter OTP</label>
            <input type="text" name="otp" required class="mt-1 block w-full border border-gray-300 rounded px-3 py-2">
        </div>
        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
            Verify OTP
        </button>
    </form>
</div>
