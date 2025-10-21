<?php
session_start();

// Include database connection file
require_once __DIR__ . '/../include/config.php';

// Check if the admin is logged in, if not, redirect to login page
if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: admin_login.php");
    exit;
}

// --- 1. Fetch Dashboard Data ---
$data = [
    'total_users' => 0,
    'total_projects' => 0,
    'pending_applications' => 0,
    'new_reports' => 0
];

// Query 1: Total Users
$result_users = $conn->query("SELECT COUNT(user_id) AS total FROM users");
if ($result_users) {
    $data['total_users'] = $result_users->fetch_assoc()['total'];
}

// Query 2: Total Projects
$result_projects = $conn->query("SELECT COUNT(project_id) AS total FROM projects WHERE status != 'Completed'");
if ($result_projects) {
    $data['total_projects'] = $result_projects->fetch_assoc()['total'];
}

// Query 3: Pending Applications (assuming status='Pending')
$result_applications = $conn->query("SELECT COUNT(application_id) AS total FROM applications WHERE status = 'Pending'");
if ($result_applications) {
    $data['pending_applications'] = $result_applications->fetch_assoc()['total'];
}

// Query 4: New/Pending Chat Reports (assuming status='Pending' or similar)
$result_reports = $conn->query("SELECT COUNT(report_id) AS total FROM chat_reports WHERE status = 'Pending'");
if ($result_reports) {
    $data['new_reports'] = $result_reports->fetch_assoc()['total'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CollabBuddy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .sidebar {
            /* Custom gradient for a modern feel */
            background: linear-gradient(180deg, #4c51bf 0%, #6b46c1 100%);
            transition: all 0.3s ease;
        }
        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            border-left: 4px solid #fff;
        }
        .card-shadow {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card-shadow:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }
        /* Gradient for buttons and highlights */
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">

    <div class="flex h-screen">
        
        <aside class="sidebar w-64 p-5 text-white flex flex-col fixed h-full z-20">
            <div class="text-2xl font-bold mb-10 border-b border-indigo-400 pb-3">
                CollabBuddy Admin
            </div>
            <nav class="flex flex-col space-y-2">
                <a href="admin_dashboard.php" class="nav-link active flex items-center p-3 rounded-lg hover:bg-white hover:text-indigo-700 transition-colors">
                    <span class="mr-3">🏠</span> Dashboard
                </a>
                <a href="manage_users.php" class="nav-link flex items-center p-3 rounded-lg hover:bg-white hover:text-indigo-700 transition-colors">
                    <span class="mr-3">👥</span> Manage Users
                </a>
                <a href="manage_projects.php" class="nav-link flex items-center p-3 rounded-lg hover:bg-white hover:text-indigo-700 transition-colors">
                    <span class="mr-3">💡</span> Monitor Projects
                </a>
                <a href="review_reports.php" class="nav-link flex items-center p-3 rounded-lg hover:bg-white hover:text-indigo-700 transition-colors">
                    <span class="mr-3">🚨</span> Review Reports
                    <?php if ($data['new_reports'] > 0): ?>
                        <span class="ml-auto bg-red-500 text-white text-xs font-semibold px-2 py-0.5 rounded-full"><?php echo $data['new_reports']; ?></span>
                    <?php endif; ?>
                </a>
            </nav>

            <div class="mt-auto pt-4 border-t border-indigo-400">
                <p class="text-sm font-semibold mb-2">Logged in as: <?php echo htmlspecialchars($_SESSION["admin_username"]); ?></p>
                <a href="admin_logout.php" class="flex items-center text-red-300 hover:text-red-100 transition-colors">
                    <span class="mr-2">🚪</span> Logout
                </a>
            </div>
        </aside>

        <main class="flex-1 p-8 ml-64 overflow-y-auto">
            
            <header class="mb-10">
                <h1 class="text-4xl font-extrabold text-gray-800">Dashboard Overview</h1>
                <p class="text-gray-600 mt-2">Welcome to the CollabBuddy Administrator Control Panel.</p>
            </header>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
                
                <div class="bg-white p-6 rounded-xl card-shadow border-t-4 border-indigo-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Total Users</p>
                            <h2 class="text-3xl font-bold text-gray-900 mt-1"><?php echo $data['total_users']; ?></h2>
                        </div>
                        <span class="text-4xl text-indigo-500">🧑‍🤝‍🧑</span>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl card-shadow border-t-4 border-purple-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Active Projects</p>
                            <h2 class="text-3xl font-bold text-gray-900 mt-1"><?php echo $data['total_projects']; ?></h2>
                        </div>
                        <span class="text-4xl text-purple-500">🚀</span>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl card-shadow border-t-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Pending Applications</p>
                            <h2 class="text-3xl font-bold text-gray-900 mt-1"><?php echo $data['pending_applications']; ?></h2>
                        </div>
                        <span class="text-4xl text-yellow-500">📝</span>
                    </div>
                </div>

                <a href="review_reports.php" class="bg-white p-6 rounded-xl card-shadow border-t-4 border-red-500 hover:bg-red-50 transition-colors">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">New Reports</p>
                            <h2 class="text-3xl font-bold text-gray-900 mt-1"><?php echo $data['new_reports']; ?></h2>
                        </div>
                        <span class="text-4xl text-red-500">🚨</span>
                    </div>
                </a>
            </div>
            
            <div class="bg-white p-8 rounded-xl card-shadow">
                <h3 class="text-2xl font-semibold text-gray-800 mb-6 border-b pb-3">Quick Actions</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    
                    <a href="manage_users.php" class="gradient-bg text-white p-4 rounded-lg text-center font-semibold hover:opacity-90 transition-opacity">
                        <span class="block text-2xl mb-1">✅</span>
                        Approve/Block Users
                    </a>
                    
                    <a href="manage_projects.php" class="bg-gray-200 text-gray-800 p-4 rounded-lg text-center font-semibold hover:bg-gray-300 transition-colors">
                        <span class="block text-2xl mb-1">📊</span>
                        View Project Analytics
                    </a>

                    <a href="review_reports.php" class="bg-red-500 text-white p-4 rounded-lg text-center font-semibold hover:bg-red-600 transition-colors">
                        <span class="block text-2xl mb-1">💬</span>
                        Handle Reported Chats
                    </a>
                </div>
            </div>

            <div class="mt-12">
                <h3 class="text-2xl font-semibold text-gray-800 mb-6">Recent System Activity</h3>
                <div class="bg-white p-6 rounded-xl card-shadow">
                    <p class="text-gray-500">
                        *Latest user signups, project creations, and critical warnings would appear here.*
                    </p>
                </div>
            </div>
        </main>
    </div>

    </body>
</html>