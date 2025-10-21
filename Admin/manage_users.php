<?php
// FILE: manage_users.php
if (session_status() === PHP_SESSION_NONE) session_start();
// Ensure the path is correct
require_once __DIR__ . '/../include/config.php'; 

// 1. Authentication Check
if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: admin_login.php");
    exit;
}

$users = [];
$error = '';
$message = '';

// --- 2. Action Handling (Ban/Suspend/Approve/Change Status) ---
if (isset($_GET['user_id']) && isset($_GET['action'])) {
    $user_id = intval($_GET['user_id']);
    $action = $_GET['action'];
    $new_status = null;
    $column_to_update = 'status';
    
    // Determine the new status based on the action
    switch ($action) {
        case 'ban':
            $new_status = 'Banned';
            $message = "User (ID: $user_id) has been **Banned** successfully.";
            break;
        case 'suspend':
            $new_status = 'Suspended';
            $message = "User (ID: $user_id) has been **Suspended** successfully.";
            break;
        case 'activate': // Used for Unbanned or Unsuspended
            $new_status = 'Active';
            $message = "User (ID: $user_id) has been **Activated** successfully.";
            break;
        case 'approve':
            $column_to_update = 'is_verified';
            $new_status = 1; // 1 for verified
            $message = "User (ID: $user_id) has been **Approved** (Verified) successfully.";
            break;
        default:
            $error = "Invalid action specified.";
    }

    if ($new_status !== null && empty($error)) {
        // Handle two possible updates: 'status' or 'is_verified'
        $sql = "UPDATE users SET {$column_to_update} = ? WHERE user_id = ?";
        if ($stmt = $conn->prepare($sql)) {
            // Check if we are updating 'is_verified' (integer) or 'status' (string)
            $bind_type = ($column_to_update === 'is_verified') ? "ii" : "si";
            $stmt->bind_param($bind_type, $new_status, $user_id);
            
            if (!$stmt->execute()) {
                $error = "Failed to execute update: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Failed to prepare update query: " . $conn->error;
        }
        // Redirect to clear GET parameters after action
        if (empty($error)) {
            header("Location: manage_users.php?msg=" . urlencode($message));
            exit;
        }
    }
}

// Check for success message after redirection
if (isset($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
}


// --- 3. Fetch All Users with Role Details ---
$sql_users = "
SELECT 
    u.user_id, 
    u.name, 
    u.email, 
    u.role, 
    u.is_verified,
    u.status,        /* CORRECT COLUMN NAME */
    u.created_at,
    p.preferred_role AS participant_role,
    h.host_type
FROM users u
LEFT JOIN participants p ON u.user_id = p.participant_id AND u.role = 'participant'
LEFT JOIN hosts h ON u.user_id = h.host_id AND u.role = 'host'
ORDER BY u.created_at DESC";

$result = $conn->query($sql_users);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
} else {
    $error = "Database query failed: " . $conn->error;
}

$conn->close();

/**
 * Helper function to determine the user's current status and available primary action
 */
function getUserStatusInfo($user) {
    if ($user['status'] === 'Banned') {
        return ['text' => 'Banned', 'color' => 'bg-red-600', 'action_text' => 'Activate', 'action' => 'activate', 'action_color' => 'bg-green-500'];
    }
    if ($user['status'] === 'Suspended') {
        return ['text' => 'Suspended', 'color' => 'bg-yellow-500', 'action_text' => 'Activate', 'action' => 'activate', 'action_color' => 'bg-green-500'];
    }
    if ($user['is_verified'] == 0) {
         return ['text' => 'Unverified', 'color' => 'bg-gray-500', 'action_text' => 'Approve', 'action' => 'approve', 'action_color' => 'bg-blue-500'];
    }
    return ['text' => 'Active', 'color' => 'bg-green-500', 'action_text' => '', 'action' => '', 'action_color' => ''];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .sidebar { background: linear-gradient(180deg, #4c51bf 0%, #6b46c1 100%); }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">
    <div class="flex h-screen">
        <aside class="sidebar w-64 p-5 text-white flex flex-col fixed h-full z-20">
            <div class="text-2xl font-bold mb-10 border-b border-indigo-400 pb-3">CollabBuddy Admin</div>
            <nav class="flex flex-col space-y-2">
                <a href="admin_dashboard.php" class="nav-link flex items-center p-3 rounded-lg hover:bg-white hover:text-indigo-700 transition-colors"><span class="mr-3">🏠</span> Dashboard</a>
                <a href="manage_users.php" class="nav-link active flex items-center p-3 rounded-lg bg-white bg-opacity-20 border-l-4 border-white"><span class="mr-3">👥</span> Manage Users</a>
                <a href="manage_projects.php" class="nav-link flex items-center p-3 rounded-lg hover:bg-white hover:text-indigo-700 transition-colors"><span class="mr-3">💡</span> Monitor Projects</a>
                <a href="review_reports.php" class="nav-link flex items-center p-3 rounded-lg hover:bg-white hover:text-indigo-700 transition-colors"><span class="mr-3">🚨</span> Review Reports</a>
            </nav>
            <div class="mt-auto pt-4 border-t border-indigo-400">
                <a href="admin_logout.php" class="flex items-center text-red-300 hover:text-red-100 transition-colors"><span class="mr-2">🚪</span> Logout</a>
            </div>
        </aside>

        <main class="flex-1 p-8 ml-64 overflow-y-auto">
            <header class="mb-8">
                <h1 class="text-3xl font-extrabold text-gray-800">User Management Dashboard</h1>
                <p class="text-gray-600 mt-2">View and control user access (Active, Suspended, Banned).</p>
            </header>

            <?php if (!empty($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                    <p class="font-bold">Error:</p>
                    <p class="text-sm"><?php echo $error; ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($message)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                    <p class="text-sm font-medium"><?php echo $message; ?></p>
                </div>
            <?php endif; ?>

            <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">All Registered Users (<?php echo count($users); ?>)</h2>

                <?php if (empty($users)): ?>
                    <div class="text-center py-10 text-gray-500">
                        <span class="text-3xl block mb-2">🤷‍♂️</span>
                        <p class="text-lg font-semibold">No users found.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User Details</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($users as $user): 
                                    $status_info = getUserStatusInfo($user);
                                ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['user_id']); ?></td>
                                    <td class="px-6 py-4 text-sm">
                                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($user['name']); ?></div>
                                        <div class="text-gray-500 text-xs"><?php echo htmlspecialchars($user['email']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <div class="font-bold uppercase text-xs"><?php echo htmlspecialchars($user['role']); ?></div>
                                        <?php if ($user['role'] === 'participant'): ?>
                                            <div class="text-xs text-indigo-500"><?php echo htmlspecialchars(substr($user['participant_role'], 0, 30)) . (strlen($user['participant_role']) > 30 ? '...' : ''); ?></div>
                                        <?php elseif ($user['role'] === 'host'): ?>
                                            <div class="text-xs text-pink-500"><?php echo htmlspecialchars($user['host_type']); ?> Host</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full text-white <?php echo $status_info['color']; ?>">
                                            <?php echo $status_info['text']; ?>
                                            <?php if ($user['is_verified'] == 0 && $user['status'] === 'Active') echo ' (Email)'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium flex space-x-2">
                                        <?php if ($status_info['action_text']): ?>
                                            <a href="?user_id=<?php echo $user['user_id']; ?>&action=<?php echo $status_info['action']; ?>" 
                                               onclick="return confirm('Are you sure you want to <?php echo strtolower($status_info['action_text']); ?> this user?');"
                                               class="text-white <?php echo $status_info['action_color']; ?> hover:opacity-80 px-3 py-1 rounded text-xs font-semibold">
                                                <?php echo $status_info['action_text']; ?>
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($status_info['text'] === 'Active' || $status_info['text'] === 'Unverified'): ?>
                                            <a href="?user_id=<?php echo $user['user_id']; ?>&action=suspend" 
                                                onclick="return confirm('Are you sure you want to suspend this user?');"
                                                class="text-gray-700 bg-yellow-200 hover:bg-yellow-300 px-3 py-1 rounded text-xs font-semibold">
                                                Suspend
                                            </a>
                                            <a href="?user_id=<?php echo $user['user_id']; ?>&action=ban" 
                                                onclick="return confirm('Are you sure you want to BAN this user permanently?');"
                                                class="text-white bg-red-600 hover:bg-red-700 px-3 py-1 rounded text-xs font-semibold">
                                                Ban
                                            </a>
                                        <?php endif; ?>
                                        
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>