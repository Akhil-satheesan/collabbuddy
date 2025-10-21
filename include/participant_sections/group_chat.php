<?php

$group_id = (int)($_GET['group_id'] ?? 0); 

if (!isset($conn, $current_user_id)) {
echo "<div class='p-4 text-red-500'>Error: Required context is missing.</div>";
 exit;
}
?>

<div class="flex h-full min-h-[70vh] max-h-[800px] border border-gray-200 rounded-xl shadow-lg bg-white">
    
    <div class="w-1/4 min-w-[250px] border-r border-gray-200">
        <?php 
        include 'group_chat_list.php'; 
        ?>
    </div>

    <div class="flex-1 flex flex-col">
        <?php 
        if ($group_id > 0): 
            include 'group_chat_display.php'; 
        else: 
        ?>
            <div class="flex items-center justify-center h-full text-gray-500 p-8">
                <p class="text-lg">⬅️ Select a project chat to communicate with your team.</p>
            </div>
        <?php endif; ?>
    </div>
</div>