<?php
// FILE: include/host_sections/group_chat_list.php (Host List)

// Assuming required files and session are loaded earlier, but ensuring basic security
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host' || !isset($conn)) {
    echo "<div class='p-4 text-red-500'>Unauthorized access or missing context.</div>";
    exit;
}

$host_id = (int)$_SESSION['user_id'];
$current_group_id = (int)($_GET['group_id'] ?? 0);

// Host-ൻ്റെ Projects-മായി ബന്ധിപ്പിച്ച എല്ലാ Group Chats-ഉം Fetch ചെയ്യുന്നു.
$sql = "
    SELECT 
        gc.group_id, 
        gc.group_name, 
        p.title AS project_title,
        (SELECT COUNT(user_id) FROM group_chat_members WHERE group_id = gc.group_id) AS member_count
    FROM group_chats gc
    INNER JOIN projects p ON gc.project_id = p.project_id
    WHERE p.host_id = ? 
    ORDER BY gc.created_at DESC
";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    echo "<div class='p-4 text-red-500 font-bold'>Database Error: Failed to prepare statement.</div>";
    exit; 
}
$stmt->bind_param("i", $host_id);
$stmt->execute();
$result = $stmt->get_result();
$groups = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="w-full h-full bg-white border-r border-gray-200 overflow-y-auto">
    <div class="p-4 border-b">
        <h3 class="text-lg font-bold text-gray-800">My Project Chats (Host)</h3>
    </div>
    
    <?php if (empty($groups)): ?>
        <p class="p-4 text-gray-500 text-sm">No group chats found for your active projects.</p>
    <?php else: ?>
        <ul class="divide-y divide-gray-100">
            <?php foreach ($groups as $group): 
                $isActive = $group['group_id'] == $current_group_id;
            ?>
                <li class="chat-list-item">
                    <a href="?section=group_chat&group_id=<?= $group['group_id'] ?>" 
                        data-group-id="<?= $group['group_id'] ?>"
                        class="flex items-center p-3 hover:bg-gray-50 transition duration-150 
                                     <?= $isActive ? 'bg-indigo-50 border-r-4 border-indigo-600' : 'bg-white' ?>">
                        <div class="flex-shrink-0 w-10 h-10 bg-indigo-600 text-white rounded-full flex items-center justify-center font-bold text-sm">
                            <?= htmlspecialchars($group['member_count']) ?>
                        </div>
                        <div class="ml-3 flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($group['project_title']) ?></p>
                            <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($group['group_name']) ?></p>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<script>
// jQuery script for AJAX loading on click
$(document).off('click', '.chat-list-item a').on('click', '.chat-list-item a', function(e) {
    e.preventDefault(); 
    
    const groupId = $(this).data('group-id');
    const sectionName = 'group_chat';
    const newUrl = `?section=${sectionName}&group_id=${groupId}`;
    
    // Stop the existing host chat polling interval
    if (typeof window.clearHostChatInterval === 'function') {
        window.clearHostChatInterval();
    }
    
    // Load the new section content via AJAX/JS function
    if (typeof window.loadHostSection === 'function') {
        history.pushState(null, '', newUrl);
        
        // Load the display component
        window.loadHostSection(sectionName, { group_id: groupId });
        
        // Update CSS highlight
        $('.chat-list-item a').removeClass('bg-indigo-50 border-r-4 border-indigo-600').addClass('bg-white');
        $(this).removeClass('bg-white').addClass('bg-indigo-50 border-r-4 border-indigo-600');
    } else {
        window.location.href = newUrl;
    }
});
</script>