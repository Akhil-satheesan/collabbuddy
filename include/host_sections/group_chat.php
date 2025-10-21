<?php
// FILE: include/host_sections/group_chat.php (Host Container)

// Ensure context is available
if (session_status() === PHP_SESSION_NONE) session_start();
// Assuming $conn and $current_user_id are set in the main dashboard file
// Fallback for standalone testing (adjust paths as necessary)
if (!isset($conn) || !isset($current_user_id)) {
    require_once __DIR__ . '/../../include/config.php';
    if (!isset($conn)) $conn = get_db_connection();
    $current_user_id = $_SESSION['user_id'] ?? 0;
}

$group_id = (int)($_GET['group_id'] ?? 0); 
?>

<div class="flex h-full min-h-[70vh] max-h-[800px] border border-gray-200 rounded-xl shadow-lg bg-white">
    
    <div class="w-1/4 min-w-[250px] border-r border-gray-200">
        <?php 
        // Loads the list of project chats owned by the host
        include 'group_chat_list.php'; 
        ?>
    </div>

    <div class="flex-1 flex flex-col">
        <?php 
        if ($group_id > 0): 
            // Loads the chat display for the selected group
            include 'group_chat_display.php'; 
        else: 
        ?>
            <div class="flex items-center justify-center h-full text-gray-500 p-8">
                <p class="text-lg">⬅️ Select a project chat to communicate with your team.</p>
            </div>
        <?php endif; ?>
    </div>
</div>