<?php
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host') { 
    http_response_code(401);
    echo "<p class='text-red-600 p-6'>❌ Unauthorized access. Please log in as a Host.</p>";
    exit;
}

require_once 'include/config.php'; 

$section = $_GET['section'] ?? '';

$allowed_sections = [
    'dashboard', 
    'my_projects',
    'team_status', 
    'task_management', // ✅ Ensure this key is present
    'pending_requests',
    'group_chat', 
    'one_to_one_chat',
    'team_members',
    'reports',
    'profile',
    'my_tasks',
    'change_password',
    'project_details'
];

$base_path = 'include/host_sections/';
$file_to_load = '';

if(in_array($section, $allowed_sections)) {
    
    // 🔴 NEW LOGIC: project_details, task_management എന്നിവയ്ക്ക് project_id ആവശ്യമാണ്
    if ($section === 'project_details' || $section === 'task_management') {
        if (!isset($_GET['project_id']) || !is_numeric($_GET['project_id'])) {
            http_response_code(400);
            echo "<div class='p-4 bg-red-100 text-red-700'>Error: Project ID is missing or invalid for " . htmlspecialchars($section) . " loading.</div>";
            exit;
        }
        $project_id = (int)$_GET['project_id']; // $project_id will be available to the included file
        $file_to_load = $base_path . "{$section}.php";
        
    } else {
        // Logic for simple sections (dashboard, my_projects, etc.)
        $file_to_load = $base_path . "{$section}.php";
    }
    
    if (file_exists($file_to_load)) {
        include $file_to_load; 
    } else {
        http_response_code(404);
        echo "<div class='p-10 bg-red-100 border-l-4 border-red-500 text-red-700'>
                <h2 class='text-xl font-bold mb-2'>❌ Section File Missing!</h2>
                <p>Could not find the file: <code>{$file_to_load}</code>. 
                    Did you forget to create <code>{$section}.php</code>?</p>
            </div>";
    }
} else {
    http_response_code(403);
    echo "<p class='text-red-600 p-6'>Access to the requested section is not allowed.</p>";
}
?>