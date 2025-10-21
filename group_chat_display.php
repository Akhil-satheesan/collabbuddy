<?php
// FILE: include/group_chat_display.php (Common to Host/Participant)

// Note: This file relies on $conn being OPEN and $_SESSION['user_id'] being set 
// in the parent container file (host_sections/group_chat.php or participant_sections/group_chat.php).

if (!isset($conn) || !isset($_SESSION['user_id'])) {
    // This should ideally not happen if the container file is set up correctly.
    echo "<p class='text-red-600 p-4'>Error: Database connection or session missing.</p>";
    exit;
}

$current_group_id = (int)($_GET['group_id'] ?? 0);
if (!$current_group_id) {
    echo "<p class='text-gray-500 p-4'>No group selected.</p>";
    exit;
}

// Group Details Fetching
$group_details = null;
$stmt = $conn->prepare("
    SELECT gc.group_name, p.title 
    FROM group_chats gc 
    INNER JOIN projects p ON gc.project_id = p.project_id 
    WHERE gc.group_id = ?
");
$stmt->bind_param("i", $current_group_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $group_details = $result->fetch_assoc();
}
$stmt->close(); // Close the statement (NOT the connection)

if (!$group_details) {
    echo "<p class='text-red-500 p-4'>Group chat not found or access denied.</p>";
    exit;
}

$group_name = htmlspecialchars($group_details['group_name']);
$project_title = htmlspecialchars($group_details['title']);

?>

<div class="bg-white rounded-xl shadow-lg flex flex-col h-full">
    <div class="p-4 border-b border-gray-200 bg-indigo-600 text-white">
        <h3 class="text-xl font-bold"><?= $group_name ?></h3>
        <p class="text-sm opacity-90">Project: <?= $project_title ?></p>
    </div>

    <div id="messages-container" class="flex-1 p-4 overflow-y-auto space-y-4 bg-gray-50">
        </div>

    <div class="p-4 border-t border-gray-200 bg-white">
        <form id="group-chat-form" class="flex space-x-3">
            <input type="hidden" id="group-id" value="<?= $current_group_id ?>">
            <input type="text" id="message-input" placeholder="Type your message..."
                   class="flex-1 border border-gray-300 rounded-lg p-3 focus:ring-indigo-500 focus:border-indigo-500" required>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-3 rounded-lg transition duration-150">
                Send
            </button>
        </form>
    </div>
</div>

<script>
// Ensure jQuery and SweetAlert2 are loaded in your main dashboard file.
$(document).ready(function() {
    const groupId = $('#group-id').val();
    const messagesContainer = $('#messages-container');
    let lastMessageId = 0;
    const currentUserId = <?= $_SESSION['user_id'] ?>;

    function fetchMessages() {
        $.ajax({
            // 🚨 AJAX Path: You might need to adjust this path based on your file structure 
            // (e.g., if you are loading the section from the root, 'ajax/...' is fine).
            url: 'ajax/get_group_messages.php', 
            method: 'GET',
            data: { group_id: groupId, last_id: lastMessageId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.messages.length > 0) {
                    // Check if we were scrolled to the bottom before new messages arrive
                    const containerElement = messagesContainer[0];
                    let scrollToBottom = containerElement.scrollHeight - containerElement.clientHeight <= containerElement.scrollTop + 50; // 50px buffer
                    
                    response.messages.forEach(msg => {
                        // Update the lastMessageId
                        if (parseInt(msg.message_id) > lastMessageId) {
                            lastMessageId = parseInt(msg.message_id);
                        }

                        const isSender = parseInt(msg.sender_id) === currentUserId;
                        const senderName = isSender ? 'You' : msg.sender_name;
                        const timestamp = new Date(msg.sent_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                        const messageHtml = `
                            <div class="flex ${isSender ? 'justify-end' : 'justify-start'}">
                                <div class="max-w-xs md:max-w-md lg:max-w-lg p-3 rounded-xl shadow-md 
                                    ${isSender ? 'bg-indigo-500 text-white rounded-br-none' : 'bg-white text-gray-800 rounded-tl-none border border-gray-200'}">
                                    <p class="font-semibold text-xs ${isSender ? 'text-indigo-200' : 'text-indigo-600'} mb-1">${senderName}</p>
                                    <p class="text-sm whitespace-pre-wrap">${$('<div>').text(msg.message_content).html()}</p>
                                    <p class="text-xs mt-1 text-right ${isSender ? 'text-indigo-200' : 'text-gray-500'}">${timestamp}</p>
                                </div>
                            </div>
                        `;
                        messagesContainer.append(messageHtml);
                    });
                    
                    // Only scroll if the user was near the bottom
                    if (scrollToBottom || messagesContainer.children().length <= response.messages.length) {
                        messagesContainer.scrollTop(containerElement.scrollHeight);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error("Error fetching messages:", error);
            }
        });
    }

    // Message sending logic
    $('#group-chat-form').on('submit', function(e) {
        e.preventDefault();
        const messageInput = $('#message-input');
        const content = messageInput.val().trim();

        if (content === '') return;

        $.ajax({
            url: 'ajax/send_group_message.php',
            method: 'POST',
            data: { group_id: groupId, content: content },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    messageInput.val(''); // Clear input
                    fetchMessages(); // Immediately refresh messages
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Failed to send message.', 'error');
            }
        });
    });

    // Initial load and polling for new messages
    fetchMessages();
    setInterval(fetchMessages, 3000); // Poll every 3 seconds
});
</script>