<?php
// FILE: include/participant_sections/group_chat_display.php
// Note: Assumes $conn, $group_id, and $current_user_id are available.

if (!isset($group_id) || $group_id <= 0) {
    echo '<p class="p-4 text-red-500">Invalid Group ID for display.</p>';
    exit;
}

// Fetch group details
$group_info_sql = "
    SELECT gc.group_name, p.title AS project_title
    FROM group_chats gc
    JOIN projects p ON gc.project_id = p.project_id
    WHERE gc.group_id = ?";
$stmt = $conn->prepare($group_info_sql);
$stmt->bind_param("i", $group_id);
$stmt->execute();
$group_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

$group_name = htmlspecialchars($group_info['group_name'] ?? 'Group Chat');
$project_title = htmlspecialchars($group_info['project_title'] ?? 'Project');

// Pass the current user ID to JavaScript
$current_user_id_js = (int)($current_user_id ?? 0); 
?>

<div class="flex flex-col h-full">
    
    <div class="p-4 border-b bg-white flex items-center justify-between shadow-sm">
        <div>
            <h4 class="text-md font-semibold text-gray-800"><?= $project_title ?></h4>
            <p class="text-xs text-indigo-600"><?= $group_name ?></p>
        </div>
        <button class="text-gray-500 hover:text-indigo-600 transition">
            <i class="fas fa-info-circle"></i>
        </button>
    </div>

    <div id="part-chatMessages" class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50" style="scroll-behavior: smooth;">
        <div class="flex justify-center items-center h-full">
            <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-indigo-500"></div>
            <p class="text-gray-600 ml-3">Loading messages...</p>
        </div>
    </div>

    <div class="p-4 border-t bg-white">
        <form id="part-chatForm">
            <input type="hidden" name="group_id" value="<?= $group_id ?>">
            <div class="flex space-x-2">
                <input type="text" name="message" id="part-messageInput" placeholder="Type your message..." 
                        class="flex-1 border border-gray-300 rounded-lg p-2 focus:ring-indigo-500 focus:border-indigo-500">
                <button type="submit" id="part-sendButton" class="bg-indigo-600 text-white p-2 rounded-lg hover:bg-indigo-700 transition duration-150 flex items-center">
                    <i class="fas fa-paper-plane mr-2"></i> Send
                </button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    // Unique IDs for Participant elements
    const $messagesDiv = $('#part-chatMessages');
    const $messageInput = $('#part-messageInput');
    const $chatForm = $('#part-chatForm');
    const $sendBtn = $('#part-sendButton');

    const groupId = <?= $group_id ?>;
    const currentUserId = <?= $current_user_id_js ?>;
    
    let chatInterval = null;
    let lastMessageId = 0; // Initialize ID for polling
    
    // Global function for clearing interval
    window.clearPartChatInterval = function() {
        if (chatInterval) {
            clearInterval(chatInterval);
            chatInterval = null;
        }
    };
    
    function scrollToBottom(force = false) {
        if (!$messagesDiv.length) return; 
        const isScrolledToBottom = $messagesDiv[0].scrollHeight - $messagesDiv[0].clientHeight <= $messagesDiv[0].scrollTop + 50;

        if (force || isScrolledToBottom) {
            $messagesDiv.animate({
                scrollTop: $messagesDiv[0].scrollHeight
            }, 300);
        }
    }

    // =================================================================
    // 🔄 FETCH MESSAGES (JSON - Participant Side)
    // =================================================================
    function fetchMessages() {
        $.ajax({
            url: 'ajax/ajax_fetch_group_messages.php', 
            method: 'GET',
            data: { 
                group_id: groupId,
                last_id: lastMessageId // Use ID-based polling
            },
            dataType: 'json', // Expect JSON response
            success: function(response) {
                let isInitialLoad = lastMessageId === 0;
                
                if (response.messages && response.messages.length > 0) {
                    let html = '';
                    
                    response.messages.forEach(msg => {
                        const isCurrentUser = msg.user_id == currentUserId;
                        const nameDisplay = isCurrentUser ? 'You' : msg.full_user_name;
                        const floatClass = isCurrentUser ? 'ml-auto bg-indigo-600 text-white rounded-br-none' : 'mr-auto bg-white text-gray-800 rounded-tl-none border border-gray-200';
                        const nameColor = isCurrentUser ? 'text-indigo-200' : 'text-gray-700';
                        const profilePicUrl = msg.profile_pic; 
                        
                        // No data attributes or event handlers for reporting now
                        
                        html += `
                            <div class="flex ${isCurrentUser ? 'justify-end' : 'justify-start'}">
                                
                                ${!isCurrentUser ? `<img class="w-8 h-8 rounded-full mr-2 self-end object-cover" src="${profilePicUrl}" alt="${msg.full_user_name}">` : ''}
                                
                                <div class="max-w-xs md:max-w-md p-3 rounded-xl shadow-sm ${floatClass}">
                                    <p class="font-semibold text-xs mb-1 flex items-center ${nameColor}">
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
                        $messagesDiv.html(html);
                        scrollToBottom(true); 
                    } else {
                        $messagesDiv.append(html);
                        scrollToBottom();
                    }

                    // Update the last message ID for the next poll
                    lastMessageId = response.last_id || lastMessageId;
                } else if (lastMessageId === 0) {
                     $messagesDiv.html('<div class="text-center py-8 text-gray-500">Start the conversation! No messages yet.</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error("Error fetching messages:", error, xhr.responseText);
                if(lastMessageId === 0) {
                    $messagesDiv.html('<div class="text-center py-8 text-red-500">❌ Failed to load messages. Server error.</div>');
                }
            }
        });
    }

    // =================================================================
    // ✍️ SEND MESSAGE
    // =================================================================
    $chatForm.on('submit', function(e) {
        e.preventDefault();
        const message = $messageInput.val().trim();
        
        if (message === '') return;
        
        $sendBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Sending');
        
        $.ajax({
            url: 'ajax/ajax_send_group_message.php', 
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    $messageInput.val(''); 
                    // Reset last ID to force a full refresh and show new message
                    lastMessageId = 0; 
                    fetchMessages(); 
                } else {
                    alert('Error sending message: ' + (response.message || 'Unknown error.'));
                }
            },
            complete: function() {
                 $sendBtn.prop('disabled', false).html('<i class="fas fa-paper-plane mr-2"></i> Send');
            }
        });
    });

    // =================================================================
    // 🚀 INITIALIZATION
    // =================================================================
    
    window.clearPartChatInterval(); 
    fetchMessages(); // Initial fetch
    chatInterval = setInterval(fetchMessages, 3000); 
    
    $messageInput.on('keypress', function(e) {
        if (e.which === 13 && !e.shiftKey) { 
            e.preventDefault();
            $chatForm.submit();
        }
    });
});
</script>