<?php
// FILE: manage_projects.php
if (session_status() === PHP_SESSION_NONE) session_start();
// Ensure the path is correct based on where your admin files are located relative to include/config.php
require_once __DIR__ . '/../include/config.php'; 

// 1. Authentication Check
if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: admin_login.php");
    exit;
}

$active_projects = [];
$error = '';

// 2. Fetch Active Projects
$sql = "
SELECT 
    p.project_id, 
    p.title, 
    p.description, 
    p.status, 
    p.created_at, 
    u.name AS host_name
FROM projects p
JOIN users u ON p.host_id = u.user_id
WHERE p.status = 'Active' 
ORDER BY p.created_at DESC";

$result = $conn->query($sql);

if ($result) {
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $active_projects[] = $row;
        }
    }
} else {
    $error = "Database query failed: " . $conn->error;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Projects - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .sidebar { background: linear-gradient(180deg, #4c51bf 0%, #6b46c1 100%); }
        .nav-link.active { background-color: rgba(255, 255, 255, 0.2); border-left: 4px solid #fff; }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">
    <div class="flex h-screen">
        <aside class="sidebar w-64 p-5 text-white flex flex-col fixed h-full z-20">
            <div class="text-2xl font-bold mb-10 border-b border-indigo-400 pb-3">CollabBuddy Admin</div>
            <nav class="flex flex-col space-y-2">
                <a href="admin_dashboard.php" class="nav-link flex items-center p-3 rounded-lg hover:bg-white hover:text-indigo-700 transition-colors"><span class="mr-3">🏠</span> Dashboard</a>
                <a href="manage_users.php" class="nav-link flex items-center p-3 rounded-lg hover:bg-white hover:text-indigo-700 transition-colors"><span class="mr-3">👥</span> Manage Users</a>
                <a href="manage_projects.php" class="nav-link active flex items-center p-3 rounded-lg hover:bg-white hover:text-indigo-700 transition-colors"><span class="mr-3">💡</span> Monitor Projects</a>
                <a href="review_reports.php" class="nav-link flex items-center p-3 rounded-lg hover:bg-white hover:text-indigo-700 transition-colors"><span class="mr-3">🚨</span> Review Reports</a>
            </nav>
            <div class="mt-auto pt-4 border-t border-indigo-400">
                <p class="text-sm font-semibold mb-2">Logged in as: <?php echo htmlspecialchars($_SESSION["admin_username"] ?? "Admin"); ?></p>
                <a href="admin_logout.php" class="flex items-center text-red-300 hover:text-red-100 transition-colors"><span class="mr-2">🚪</span> Logout</a>
            </div>
        </aside>

        <main class="flex-1 p-8 ml-64 overflow-y-auto">
            <header class="mb-8">
                <h1 class="text-3xl font-extrabold text-gray-800">Monitor Active Projects</h1>
                <p class="text-gray-600 mt-2">Currently running collaborative projects on the platform.</p>
            </header>

            <?php if (!empty($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                    <p class="font-bold">Error:</p>
                    <p class="text-sm"><?php echo $error; ?></p>
                </div>
            <?php endif; ?>

            <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Active Projects (<?php echo count($active_projects); ?>)</h2>

                <?php if (empty($active_projects)): ?>
                    <div class="text-center py-10 text-gray-500">
                        <span class="text-3xl block mb-2">🤔</span>
                        <p class="text-lg font-semibold">No active projects found right now.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Host</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($active_projects as $project): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($project['project_id']); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <div class="font-semibold"><?php echo htmlspecialchars($project['title']); ?></div>
                                        <div class="text-gray-500 truncate w-64 text-xs"><?php echo htmlspecialchars(substr($project['description'], 0, 80)) . (strlen($project['description']) > 80 ? '...' : ''); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-indigo-600 font-medium"><?php echo htmlspecialchars($project['host_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            <?php echo htmlspecialchars($project['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date("Y-m-d", strtotime($project['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="view_project.php?id=<?php echo $project['project_id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3 font-semibold" title="View Project Details">
                                            View Details
                                        </a>
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