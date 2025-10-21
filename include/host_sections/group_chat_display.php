<?php
if (!isset($group_id) || $group_id <= 0) {
    echo "<div class='p-4 text-red-500'>Invalid group selected.</div>";
    exit;
}

$host_id = (int)$current_user_id;
$current_user_role = 'Host';
$display_you_name = "You ({$current_user_role})";

$sql = "
    SELECT 
        gc.group_name, 
        p.title AS project_title
    FROM group_chats gc
    INNER JOIN projects p ON gc.project_id = p.project_id
    WHERE gc.group_id = ? AND p.host_id = ? 
";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    echo "<div class='p-4 text-red-500'>Database error: Failed to prepare statement.</div>";
    exit;
}

$stmt->bind_param("ii", $group_id, $host_id); 
$stmt->execute();
$result = $stmt->get_result();
$group_details = $result->fetch_assoc();
$stmt->close();

if (!$group_details) {
    echo "<div class='p-4 text-red-500'>Group not found or you are not the host of this project.</div>";
    exit;
}

$group_name = htmlspecialchars($group_details['group_name']);
$project_title = htmlspecialchars($group_details['project_title']);
?>

<div class="flex flex-col h-full">
    <div class="p-4 border-b border-gray-200 bg-gray-50">
        <h3 class="text-lg font-bold text-gray-900"><?= $project_title ?></h3>
        <p class="text-sm text-indigo-600 font-medium"><?= $group_name ?></p>
    </div>

    <div id="host-chat-messages" class="flex-1 overflow-y-auto p-4 space-y-4" style="scroll-behavior: smooth;">
        <div class="text-center text-gray-500">Loading messages...</div>
    </div>

    <div class="p-4 border-t border-gray-200 bg-white">
        <div class="flex space-x-3">
            <input 
                type="text" 
                id="host-message-input" 
                placeholder="Type your message..." 
                class="flex-1 border border-gray-300 rounded-full px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                autocomplete="off">
            <button 
                id="host-send-message-btn"
                data-group-id="<?= $group_id ?>"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-3 rounded-full shadow-md transition-colors duration-200 flex items-center">
                Send
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2 transform rotate-45" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                </svg>
            </button>
        </div>
    </div>
</div>
<script>
$(document).ready(function() {
    const $messageArea = $('#host-chat-messages');
    const $input = $('#host-message-input');
    const $sendBtn = $('#host-send-message-btn');
    const groupId = $sendBtn.data('group-id');
    const currentUserId = <?= (int)($current_user_id ?? 0) ?>; 
    const displayYouName = "<?= $display_you_name ?>";
    let chatInterval = null; 
    let lastMessageId = 0; 

    window.clearHostChatInterval = function() {
        if (chatInterval) {
            clearInterval(chatInterval);
            chatInterval = null;
        }
    };

    function scrollToBottom(force = false) {
        if (!$messageArea.length) return; 
        const isScrolledToBottom = $messageArea[0].scrollHeight - $messageArea[0].clientHeight <= $messageArea[0].scrollTop + 1;
        if (force || isScrolledToBottom) {
            $messageArea.animate({
                scrollTop: $messageArea[0].scrollHeight
            }, 300);
        }
    }

    function fetchMessages() {
        $.ajax({
            url: 'ajax/ajax_fetch_group_messages.php', 
            method: 'GET',
            data: { 
                group_id: groupId,
                last_id: lastMessageId 
            },
            dataType: 'json',
            success: function(response) {
                if (response.messages && response.messages.length > 0) {
                    let html = '';
                    let isInitialLoad = lastMessageId === 0;

                    response.messages.forEach(msg => {
                        const isCurrentUser = msg.user_id == currentUserId;
                        const nameDisplay = isCurrentUser ? displayYouName : msg.full_user_name; 
                        const floatClass = isCurrentUser ? 'ml-auto bg-indigo-600 text-white' : 'mr-auto bg-gray-200 text-gray-900';
                        const profilePicUrl = msg.profile_pic; 
                        html += `
                            <div class="flex ${isCurrentUser ? 'justify-end' : 'justify-start'}">
                                ${!isCurrentUser ? `<img class="w-8 h-8 rounded-full mr-2 self-end object-cover" src="${profilePicUrl}" alt="${msg.full_user_name}">` : ''}
                                <div class="max-w-xs md:max-w-md px-4 py-2 rounded-xl shadow ${floatClass}">
                                    <p class="font-semibold text-xs mb-1 flex items-center ${isCurrentUser ? 'text-indigo-200' : 'text-gray-700'}">
                                        ${nameDisplay}
                                    </p>
                                    <p class="text-sm whitespace-pre-wrap">${msg.message_text}</p>
                                    <p class="text-xs mt-1 text-right ${isCurrentUser ? 'text-indigo-300' : 'text-gray-500'}">${msg.time_ago}</p>
                                </div>
                                ${isCurrentUser ? `<img class="w-8 h-8 rounded-full ml-2 self-end object-cover" src="${profilePicUrl}" alt="You">` : ''}
                            </div>
                        `;
                    });

                    if (isInitialLoad) {
                        $messageArea.html(html);
                        scrollToBottom(true); 
                    } else {
                        $messageArea.append(html);
                        scrollToBottom();
                    }
                    lastMessageId = response.last_id || lastMessageId;
                } else if (lastMessageId === 0) {
                     $messageArea.html('<div class="text-center py-8 text-gray-500">Start the conversation! No messages yet.</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error("Error fetching messages:", error, xhr.responseText);
                if(lastMessageId === 0) {
                    $messageArea.html('<div class="text-center py-8 text-red-500">❌ Failed to load messages. Server error.</div>');
                }
            }
        });
    }

    function sendMessage() {
        const message = $input.val().trim();
        if (message === '') return;

        $sendBtn.prop('disabled', true).text('Sending...');

        $.ajax({
            url: 'ajax/ajax_send_group_message.php', 
            method: 'POST',
            data: { group_id: groupId, message: message },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $input.val('');
                    lastMessageId = 0; 
                    fetchMessages(); 
                } else {
                    alert('Error sending message: ' + (response.error || 'Unknown error.'));
                }
            },
            complete: function() {
                $sendBtn.prop('disabled', false).html('Send <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2 transform rotate-45" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>');
            },
            error: function() {
                alert('An error occurred during network request.');
            }
        });
    }

    window.clearHostChatInterval(); 
    fetchMessages(); 
    window.hostChatInterval = setInterval(fetchMessages, 3000); 

    $sendBtn.on('click', sendMessage);
    $input.on('keypress', function(e) {
        if (e.which === 13 && !e.shiftKey) { 
            e.preventDefault();
            sendMessage();
        }
    });
});
</script>