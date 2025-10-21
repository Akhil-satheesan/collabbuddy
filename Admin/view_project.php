<?php
// FILE: view_project.php
if (session_status() === PHP_SESSION_NONE) session_start();
// Ensure you have a valid path to your configuration file
require_once __DIR__ . '/../include/config.php'; 

// 1. Authentication Check
if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: admin_login.php");
    exit;
}

// 2. Get Project ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // If ID is missing or invalid, redirect back to manage_projects.php
    header("location: manage_projects.php");
    exit;
}

$project_id = intval($_GET['id']);
$project = null;
$members = [];
$error = '';

// 3. Fetch Project Details and Host Info (REQUIRED CHANGE APPLIED HERE)
$sql_project = "
SELECT 
    p.project_id, 
    p.title, 
    p.description, 
    p.required_skills, /* <-- CORRECTED COLUMN NAME */
    p.status, 
    p.created_at, 
    u.name AS host_name,
    u.email AS host_email,
    u.user_id AS host_id,
    u.profile_pic_url AS host_pic
FROM projects p
JOIN users u ON p.host_id = u.user_id
WHERE p.project_id = ?";

if ($stmt = $conn->prepare($sql_project)) {
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $project = $result->fetch_assoc();
    } else {
        $error = "Project not found or invalid Project ID.";
    }
    $stmt->close();
} else {
    $error = "Database query preparation failed for project details: " . $conn->error;
}


// 4. Fetch Project Members (if project exists)
if ($project) {
    // Assuming 'project_members' table links projects and participants
    $sql_members = "
    SELECT 
        u.user_id, 
        u.name, 
        u.role,
        u.email,
        u.profile_pic_url
    FROM project_members pm
    JOIN users u ON pm.participant_id = u.user_id
    WHERE pm.project_id = ?";
    
    if ($stmt = $conn->prepare($sql_members)) {
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
        $stmt->close();
    } else {
        // Append member fetch error to the main error message
        $error .= " | Error fetching members: " . $conn->error;
    }
}

$conn->close();

// Set default profile picture for display
$default_pic = "path/to/default/profile.png"; // CHANGE THIS PATH to your actual default profile image path
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Project - Admin</title>
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
            <header class="mb-8 flex justify-between items-center">
                <h1 class="text-3xl font-extrabold text-gray-800">Project Details</h1>
                <a href="manage_projects.php" class="text-indigo-600 hover:underline flex items-center">
                    <span class="mr-1">&larr;</span> Back to Projects
                </a>
            </header>

            <?php if (!empty($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                    <p class="font-bold">Error:</p>
                    <p class="text-sm"><?php echo $error; ?></p>
                </div>
            <?php endif; ?>

            <?php if ($project): ?>
                
                <div class="bg-white p-6 rounded-xl shadow-lg border-t-4 border-indigo-500 mb-8">
                    <h2 class="text-3xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($project['title']); ?></h2>
                    <p class="text-sm font-medium text-indigo-600 mb-4">Project ID: <?php echo $project['project_id']; ?></p>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 border-b pb-4 mb-4">
                        <div class="text-gray-700">
                            <p class="font-semibold text-sm">Status:</p>
                            <span class="px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                                <?php echo htmlspecialchars($project['status']); ?>
                            </span>
                        </div>
                        <div class="text-gray-700">
                            <p class="font-semibold text-sm">Created At:</p>
                            <p><?php echo date("F j, Y, g:i a", strtotime($project['created_at'])); ?></p>
                        </div>
                        <div class="text-gray-700">
                            <p class="font-semibold text-sm">Host:</p>
                            <p class="font-bold text-indigo-700"><?php echo htmlspecialchars($project['host_name']); ?> (ID: <?php echo $project['host_id']; ?>)</p>
                            <p class="text-xs"><?php echo htmlspecialchars($project['host_email']); ?></p>
                        </div>
                    </div>

                    <div class="mb-4">
                        <p class="font-semibold text-gray-700 mb-1">Description:</p>
                        <p class="text-gray-800 bg-gray-50 p-3 rounded-lg"><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                    </div>

                    <div>
                        <p class="font-semibold text-gray-700 mb-1">Skills Required:</p>
                        <div class="flex flex-wrap gap-2">
                            <?php 
                            // CORRECTED VARIABLE NAME HERE: $project['required_skills']
                            $skills = array_filter(array_map('trim', explode(',', $project['required_skills'])));
                            foreach ($skills as $skill): ?>
                                <span class="bg-yellow-100 text-yellow-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                    <?php echo htmlspecialchars($skill); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-lg mb-8">
                    <h3 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">Host Details</h3>
                    <div class="flex items-center space-x-4">
                        <img class="w-16 h-16 rounded-full object-cover" src="<?php echo htmlspecialchars($project['host_pic'] ?: $default_pic); ?>" alt="<?php echo htmlspecialchars($project['host_name']); ?>">
                        <div>
                            <p class="text-lg font-semibold"><?php echo htmlspecialchars($project['host_name']); ?></p>
                            <p class="text-sm text-gray-500">Host (ID: <?php echo $project['host_id']; ?>)</p>
                            <p class="text-sm text-indigo-600"><?php echo htmlspecialchars($project['host_email']); ?></p>
                        </div>
                        <a href="manage_users.php?user_id=<?php echo $project['host_id']; ?>" class="ml-auto bg-indigo-100 text-indigo-700 px-3 py-1 rounded text-sm hover:bg-indigo-200">
                            Go to User Profile
                        </a>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-lg">
                    <h3 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">Project Members (<?php echo count($members); ?>)</h3>
                    
                    <?php if (empty($members)): ?>
                        <p class="text-gray-500">There are no participants currently assigned to this project.</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($members as $member): ?>
                                <div class="flex items-center justify-between p-3 border rounded-lg bg-gray-50 hover:bg-gray-100">
                                    <div class="flex items-center space-x-3">
                                        <img class="w-10 h-10 rounded-full object-cover" src="<?php echo htmlspecialchars($member['profile_pic_url'] ?: $default_pic); ?>" alt="<?php echo htmlspecialchars($member['name']); ?>">
                                        <div>
                                            <p class="font-semibold"><?php echo htmlspecialchars($member['name']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($member['email']); ?></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        <span class="text-sm font-medium text-gray-600">ID: <?php echo $member['user_id']; ?></span>
                                        <a href="manage_users.php?user_id=<?php echo $member['user_id']; ?>" class="text-sm text-indigo-600 hover:text-indigo-800">
                                            View User
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php endif; ?>
        </main>
    </div>
</body>
</html>