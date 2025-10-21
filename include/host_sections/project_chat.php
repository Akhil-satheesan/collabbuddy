<?php
// FILE: include/host_sections/group_chat.php (Container File)

require_once __DIR__ . '/../../include/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Host സെക്ഷൻ ആയതുകൊണ്ട് Host ആണോ എന്ന് മാത്രം പരിശോധിക്കുന്നു
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host') {
    echo "<p class='text-red-600 p-4'>Unauthorized access.</p>";
    exit;
}
?>

<div class="flex h-full min-h-[70vh] max-h-[800px] border border-gray-200 rounded-xl shadow-lg bg-white">
    
    <div class="w-1/4 min-w-[250px] border-r border-gray-200">
        <?php 
        // 🔑 Host-ൻ്റെ ലിസ്റ്റ് ഫയൽ ഉൾപ്പെടുത്തുന്നു (അതേ ഡയറക്ടറിയിൽ ഉണ്ടെങ്കിൽ)
        // ensure you saved the list file as include/host_sections/group_chat_list.php
        include 'group_chat_list.php'; 
        ?>
    </div>

    <div class="flex-1">
        <?php 
        $group_id = (int)($_GET['group_id'] ?? 0);
        
        if ($group_id): 
            // 🔑 Group Chat Display Common ഫയൽ ഉൾപ്പെടുത്തുന്നു.
            // പാത്ത്: host_sections-ൽ നിന്ന് പുറത്തുകടന്ന് include ഡയറക്ടറിയിലെ group_chat_display.php
            include __DIR__ . '/../group_chat_display.php'; 
        else: 
        ?>
            <div class="flex items-center justify-center h-full text-gray-500 p-8">
                <p class="text-lg">⬅️ Select a project chat to communicate with your team.</p>
            </div>
        <?php endif; ?>
    </div>
</div>