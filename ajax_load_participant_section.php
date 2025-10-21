<?php
// FILE: ajax_load_participant_section.php

session_start();
// Adjust path for config.php if needed (Assuming 'include/config.php' is in the root directory)
// 🔑 മാറ്റം 1: config.php ഉൾപ്പെടുത്തുന്നു
require_once 'include/config.php'; 

// -------------------------------------------------------------------------
// Security Check
// -------------------------------------------------------------------------
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'participant') { // 🔑 user_id കൂടി ചെക്ക് ചെയ്യുന്നത് നല്ലതാണ്
    http_response_code(401); // Unauthorized
    echo "<p class='text-red-600 p-6'>❌ Unauthorized access. Please log in as a Participant.</p>";
    exit;
}

// -------------------------------------------------------------------------
// Variable Setup
// -------------------------------------------------------------------------
$section = $_GET['section'] ?? '';
$current_user_id = $_SESSION['user_id']; // 🔑 ഉൾപ്പെടുന്ന ഫയലുകൾക്ക് എളുപ്പത്തിൽ ആക്സസ് ചെയ്യാൻ
$conn = get_db_connection();
$group_id = (int)($_GET['group_id'] ?? 0); 
$room_id = (int)($_GET['room_id'] ?? 0); // 🔑 ഡാറ്റാബേസ് കണക്ഷൻ എടുക്കുന്നു (config.php ൽ ഇത് നിർവചിച്ചിരിക്കണം)

// Whitelist of allowed sections (must match the keys in your $menu array)
$allowed_sections = [
    'browse_projects',
    'my_applications',
    'joined_projects',
    'bookmarks',
    'group_chat', 
    'one_to_one_chat',
    'view_my_tasks',
    'profile',
    'change_password',
    'host_profile',    // For showing host profile from chat/project views
    'project_details'  // For showing project details
];

// -------------------------------------------------------------------------
// Section Loading Logic
// -------------------------------------------------------------------------
if(in_array($section, $allowed_sections)) {
    // Construct the file path
    $filepath = "include/participant_sections/{$section}.php";
    
    // Check if the file exists before including it (prevents PHP warnings)
    if (file_exists($filepath)) {
        // 🔑 ഉൾപ്പെടുന്ന ഫയലുകൾക്ക് $conn, $current_user_id എന്നിവ ലഭ്യമാകും
        include $filepath;
    } else {
        http_response_code(404); // File not found
        echo "<div class='p-10 bg-red-100 border-l-4 border-red-500 text-red-700'>
            <h2 class='text-xl font-bold mb-2'>❌ Section File Missing!</h2>
            <p>Could not find the file: <code>{$filepath}</code></p>
            <p>Please ensure that <code>{$section}.php</code> exists inside your <code>include/participant_sections/</code> folder.</p>
        </div>";
    }
} else {
    http_response_code(403); // Forbidden
    echo "<p class='p-6 text-red-600'>Access to the requested section is not allowed.</p>";
}
// 🔑 ഡാറ്റാബേസ് കണക്ഷൻ ക്ലോസ് ചെയ്യുന്നത് നല്ലതാണ്
if (isset($conn)) {
    $conn->close();
}
?>