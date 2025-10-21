<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn) || !isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host' || !function_exists('get_db_connection')) { 
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host') {
        http_response_code(401);
        echo "<p class='text-red-600 p-6'>Unauthorized access. Please log in as a Host.</p>";
    }
    exit(); 
}

$current_user_id = $_SESSION['user_id'];

$chatRooms = [];
// NOTE: Joining project_requests (pr) to get the correct application status, and users (u_p) to get the profile_pic_url.
$roomSql = "SELECT cr.room_id, cr.project_id, pr.status, p.title AS project_title, u_p.name AS participant_name, u_p.user_id AS participant_id, u_p.profile_pic_url
             FROM chat_rooms cr
             JOIN projects p ON p.project_id = cr.project_id
             JOIN users u_p ON u_p.user_id = cr.participant_id
             INNER JOIN project_requests pr ON pr.host_id = cr.host_id AND pr.participant_id = cr.participant_id AND pr.project_id = cr.project_id
             WHERE cr.host_id = ?
             ORDER BY cr.created_at DESC";

$roomStmt = $conn->prepare($roomSql);
$roomStmt->bind_param("i", $current_user_id);
$roomStmt->execute();
$roomResult = $roomStmt->get_result();

while ($room = $roomResult->fetch_assoc()) {
    $room['other_user_name'] = $room['participant_name']; 
    $chatRooms[] = $room;
}
$roomStmt->close();

$selected_room_id = (isset($_GET['room_id']) && is_numeric($_GET['room_id'])) 
                     ? (int)$_GET['room_id'] 
                     : ($chatRooms[0]['room_id'] ?? null);

$selected_room_data = null;
$request_data = null;

if ($selected_room_id) {
    foreach ($chatRooms as $room) {
        if ($room['room_id'] == $selected_room_id) {
            $selected_room_data = $room;
            break;
        }
    }
    
    if ($selected_room_data) {
        $participant_id = $selected_room_data['participant_id'];
        $project_id = $selected_room_data['project_id'];
        
        // Fetch specific request data (for action buttons)
        $requestSql = "SELECT request_id, status 
                         FROM project_requests 
                         WHERE participant_id = ? AND project_id = ? AND host_id = ?";
                         
        $requestStmt = $conn->prepare($requestSql);
        $requestStmt->bind_param("iii", $participant_id, $project_id, $current_user_id);
        $requestStmt->execute();
        $requestResult = $requestStmt->get_result();
        $request_data = $requestResult->fetch_assoc();
        $requestStmt->close();
    }
}

$current_request_status = $request_data['status'] ?? null;
// Check if chat should be closed (Application rejected or withdrawn)
$is_chat_closed = in_array($current_request_status, ['rejected', 'withdrawn']);
?>

<div class="flex h-[80vh] bg-white rounded-lg shadow-2xl border border-gray-100 overflow-hidden">
    
    <div class="w-1/3 md:w-1/4 bg-gray-50 border-r border-gray-200 flex flex-col">
        <div class="p-4 border-b border-gray-200">
            <h3 class="text-xl font-extrabold text-indigo-600">Applicant Chats (1:1)</h3>
            <p class="text-xs text-gray-500">Conversations related to project applications.</p>
        </div>
        <div class="flex-1 overflow-y-auto" id="chatListContainer">
            <?php if (empty($chatRooms)): ?>
                <p class="text-center text-sm text-gray-500 p-4 mt-5">No active 1:1 chats yet.</p>
            <?php else: ?>
                <?php foreach ($chatRooms as $room): 
                    $isActive = $room['room_id'] == $selected_room_id;
                    $status = $room['status'];
                    $statusText = ucfirst(htmlspecialchars($status));
                    $profile_pic = !empty($room['profile_pic_url']) 
                                   ? $room['profile_pic_url']
                                   : 'assets/images/default_host.png'; // Using a default placeholder for now

                    $statusBadgeClass = 'bg-gray-100 text-gray-800'; 
                    if ($status === 'pending') {
                        $statusBadgeClass = 'bg-yellow-100 text-yellow-800';
                    } elseif ($status === 'active' || $status === 'accepted') {
                        $statusBadgeClass = 'bg-green-100 text-green-800';
                        $statusText = 'Accepted';
                    } elseif ($status === 'rejected' || $status === 'withdrawn') {
                        $statusBadgeClass = 'bg-red-100 text-red-800';
                        $statusText = 'Closed';
                    }
                ?>
                    <a href="#" 
                        data-section="one_to_one_chat" 
                        data-room-id="<?= $room['room_id'] ?>"
                        id="chat-link-<?= $room['room_id'] ?>"
                        class="load-chat-link flex items-center p-3 border-b border-gray-100 transition duration-150 <?= $isActive ? 'bg-indigo-50 border-l-4 border-indigo-500' : 'hover:bg-gray-100' ?>">
                        
                        <div class="w-10 h-10 rounded-full overflow-hidden mr-3 relative flex items-center justify-center bg-gray-300">
                             <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Participant PFP" class="w-full h-full object-cover">
                        </div>
                        
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-gray-800 truncate"><?= htmlspecialchars($room['other_user_name']) ?></p>
                            <p class="text-xs text-gray-500 truncate">Proj: <?= htmlspecialchars($room['project_title']) ?></p>
                        </div>
                        
                        <div class="flex flex-col items-end space-y-1 ml-2">
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full <?= $statusBadgeClass ?>">
                                <?= $statusText ?>
                            </span>
                            <span id="unread-<?= $room['room_id'] ?>" 
                                   class="hidden w-5 h-5 text-xs font-bold bg-red-500 text-white rounded-full flex items-center justify-center">
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="w-2/3 md:w-3/4 flex flex-col">
        <?php if ($selected_room_data): ?>
            <div class="p-4 border-b border-gray-200 bg-white shadow-sm flex justify-between items-center">
                <div>
                    <h4 class="text-lg font-bold text-gray-800">Chat with <?= htmlspecialchars($selected_room_data['other_user_name']) ?> (Applicant)</h4>
                    <p class="text-sm text-gray-500">Project: <?= htmlspecialchars($selected_room_data['project_title']) ?></p>
                </div>
                
                <?php if ($current_request_status === 'pending'): ?>
                    <div class="flex space-x-3" id="chatActionButtons">
                        <button class="chat-approve-btn bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition duration-150"
                                data-id="<?= (int)$request_data['request_id'] ?>">
                            <i class="fas fa-check-circle mr-1"></i> Approve
                        </button>
                        <button class="chat-reject-btn bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition duration-150"
                                data-id="<?= (int)$request_data['request_id'] ?>">
                            <i class="fas fa-times-circle mr-1"></i> Reject
                        </button>
                    </div>
                <?php elseif ($is_chat_closed): ?>
                    <span class="text-sm font-semibold text-red-600 bg-red-100 px-3 py-1 rounded-full">
                        ❌ Application Closed
                    </span>
                <?php else: // active/accepted ?>
                    <span class="text-sm font-semibold text-green-600 bg-green-100 px-3 py-1 rounded-full">
                        ✅ Application Accepted
                    </span>
                <?php endif; ?>
            </div>
            
            <div id="typingIndicator" class="px-6 py-2 bg-yellow-50 text-yellow-700 text-sm hidden border-b border-yellow-200">
                <i class="fas fa-ellipsis-h mr-1"></i> <?= htmlspecialchars($selected_room_data['other_user_name']) ?> is typing...
            </div>

            <div id="messageDisplay" class="flex-1 p-6 space-y-4 overflow-y-auto bg-gray-50">
                <?php if ($is_chat_closed): ?>
                    <div class="text-center p-10 mt-10 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-lg shadow-md mx-auto max-w-md">
                        <i class="fas fa-ban fa-2x mb-3"></i>
                        <h4 class="text-xl font-bold">Chat Closed</h4>
                        <p class="text-sm mt-2">This chat is **permanently closed** because the application was rejected or withdrawn.</p>
                    </div>
                <?php else: ?>
                    <div class="text-center text-gray-400 p-10">Loading messages...</div>
                <?php endif; ?>
            </div>

            <div class="p-4 border-t border-gray-200 bg-white">
                <form id="chatForm" class="flex" <?= $is_chat_closed ? 'onsubmit="return false;"' : '' ?>>
                    <input type="hidden" name="chat_identifier" id="chatIdentifierInput" value="<?= $selected_room_id ?>">
                    <input type="hidden" name="is_group_chat" id="isGroupChatInput" value="0"> 
                    
                    <input type="text" name="message_content" id="messageInput" placeholder="<?= $is_chat_closed ? 'Chat is disabled: Application Closed.' : 'Type a message...' ?>"
                            class="flex-1 p-3 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" 
                            <?= $is_chat_closed ? 'disabled' : 'required' ?>>
                    <button type="submit" class="bg-indigo-600 text-white px-5 py-3 rounded-r-lg font-semibold hover:bg-indigo-700 transition duration-150"
                            <?= $is_chat_closed ? 'disabled' : '' ?>>
                        Send
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="flex-1 flex items-center justify-center text-center text-gray-500">
                <p class="text-xl">Select a chat from the left panel to start messaging.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() { 
    const chatIdentifierInput = document.getElementById('chatIdentifierInput');
    if (!chatIdentifierInput) {
        return; 
    }
    
    const chatIdentifier = chatIdentifierInput.value; 
    const isGroup = $('#isGroupChatInput').val(); 
    const messageDisplay = $('#messageDisplay')[0];
    const chatForm = $('#chatForm');
    const messageInput = $('#messageInput');
    const typingIndicator = $('#typingIndicator');
    let lastScrollHeight = 0; 
    let messageLoadInterval;
    let statusPollingInterval;
    let typingTimeout; 
    const typingStatusDuration = 1500;
    
    // PHP array of all room IDs for unread count polling
    const allRooms = [<?= implode(',', array_map(fn($r) => $r['room_id'], $chatRooms)) ?>];

    window.clearChatInterval = function() {
        if(messageLoadInterval) {
            clearInterval(messageLoadInterval);
            messageLoadInterval = null; 
        }
        if(statusPollingInterval) {
            clearInterval(statusPollingInterval);
            statusPollingInterval = null;
        }
        clearTimeout(typingTimeout);
        typingTimeout = undefined;
    };

    if (chatIdentifier) {
        
        function loadMessages() {
            if (!messageDisplay) return;
            
            // Do not try to load messages if chat is closed and we have already shown the alert
            if (messageInput.prop('disabled') && lastScrollHeight !== 0) {
                 messageDisplay.scrollTop = messageDisplay.scrollHeight;
                 return;
            }

            const isScrolledToBottom = messageDisplay.scrollHeight - messageDisplay.clientHeight <= messageDisplay.scrollTop + 50; 
            
            $.get('ajax/ajax_load_chat.php', { chat_identifier: chatIdentifier, is_group: isGroup, current_user_id: '<?= $current_user_id ?>' }, function(data) {
                $('#messageDisplay').html(data);
                
                if (isScrolledToBottom || lastScrollHeight === 0) {
                    messageDisplay.scrollTop = messageDisplay.scrollHeight;
                }
                lastScrollHeight = messageDisplay.scrollHeight;
            }).fail(function(xhr) {
                console.error("Error loading chat:", xhr.statusText);
            });
        }
        
        function setTypingStatus(isTyping) {
            $.post('ajax/ajax_set_typing.php', { 
                room_id: chatIdentifier, 
                user_id: '<?= $current_user_id ?>',
                is_typing: isTyping ? 1 : 0 
            }).fail(function() {
            });
        }
        
        messageInput.on('input', function() {
            if (!messageInput.prop('disabled')) {
                if (typingTimeout === undefined) {
                    setTypingStatus(true);
                }
                
                clearTimeout(typingTimeout);
                typingTimeout = setTimeout(() => {
                    setTypingStatus(false);
                    typingTimeout = undefined; 
                }, typingStatusDuration);
            }
        });
        
        function loadChatStatus() {
            // 1. Load Typing Status
            $.get('ajax/ajax_get_typing.php', { 
                room_id: chatIdentifier, 
                current_user_id: '<?= $current_user_id ?>' 
            }, function(response) {
                if (response.is_typing && !messageInput.prop('disabled')) {
                    typingIndicator.slideDown(100);
                } else {
                    typingIndicator.slideUp(100);
                }
            }, 'json').fail(function() {
            });
            
            // 2. Load Unread Counts
            if (allRooms.length > 0) {
                $.get('ajax/ajax_get_unread_count.php', { 
                    room_ids: allRooms.join(','), 
                    user_id: '<?= $current_user_id ?>' 
                }, function(counts) {
                    for (const roomId in counts) {
                        const count = counts[roomId];
                        const $badge = $('#unread-' + roomId);
                        
                        if (count > 0) {
                            $badge.removeClass('hidden');
                            if (count > 99) {
                                $badge.text('99+');
                            } else {
                                $badge.text(count);
                            }
                        } else {
                            $badge.addClass('hidden');
                        }
                    }
                }, 'json').fail(function() {
                });
            }
        }

        if (!messageInput.prop('disabled')) {
            loadMessages(); 
            window.clearChatInterval();
            messageLoadInterval = setInterval(loadMessages, 3000); 
        }
        
        loadChatStatus();
        statusPollingInterval = setInterval(loadChatStatus, 5000);


        if (chatForm.length) {
            chatForm.on('submit', function(e) {
                e.preventDefault(); 
                
                if (messageInput.prop('disabled')) return; 
                
                const messageContent = messageInput.val().trim();
                if (messageContent === '') return;
                
                setTypingStatus(false);
                clearTimeout(typingTimeout); 
                typingTimeout = undefined;

                const sendButton = chatForm.find('button[type="submit"]');
                sendButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                
                const postData = {
                    chat_identifier: chatIdentifier, 
                    message_content: messageContent 
                };

                $.post('ajax/ajax_send_chat.php', postData, function(response) {
                    if (response.success) {
                        messageInput.val(''); 
                        loadMessages(); 
                    } else {
                        Swal.fire('Error', 'Error sending message: ' + response.message, 'error');
                    }
                }, 'json').fail(function(xhr) {
                    Swal.fire('Error', 'Failed to connect to server. Status: ' + xhr.status, 'error');
                }).always(function() {
                    sendButton.prop('disabled', false).html('Send');
                });
            });
        }

        // Application Action Handlers
        $(document).on("click", ".chat-approve-btn, .chat-reject-btn", function() {
            let reqId = $(this).data("id");
            let action = $(this).hasClass("chat-approve-btn") ? "accept" : "reject";

            Swal.fire({
                title: 'Confirm Action',
                text: `You are about to ${action} this application for the project. The chat status will be updated accordingly. Continue?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, ' + action + ' it',
                cancelButtonText: 'Cancel',
                confirmButtonColor: action === 'accept' ? '#10B981' : '#EF4444',
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post("include/host_sections/actions/handle_request.php", { 
                        id: reqId, 
                        action: action 
                    }, function(res) {
                        try {
                            let data = JSON.parse(res);
                            if (data.success) {
                                Swal.fire('Success', data.message, 'success');
                                
                                // Reload the chat section to update buttons and chat status
                                if(typeof window.loadHostSection === 'function') {
                                    window.clearChatInterval(); 
                                    const currentSection = 'one_to_one_chat';
                                    const historyUrl = `?section=${currentSection}&room_id=${chatIdentifier}`;
                                    history.pushState(null, '', historyUrl);
                                    window.loadHostSection(currentSection); 
                                } else {
                                    setTimeout(() => location.reload(), 1000); 
                                }
                                
                            } else {
                                Swal.fire('Error', data.message, 'error');
                            }
                        } catch (e) {
                            Swal.fire('Error', 'Unexpected response from the server.', 'error');
                        }
                    }).fail(function() {
                        Swal.fire('Error', 'Failed to communicate with the server.', 'error');
                    });
                }
            });
        });
    } 

    $('.load-chat-link').on('click', function(e) {
        e.preventDefault(); 
        
        window.clearChatInterval(); 
        
        const section = $(this).data('section');
        const roomId = $(this).data('room-id');

        const historyUrl = `?section=${section}&room_id=${roomId}`;
        history.pushState(null, '', historyUrl);
        
        if (typeof window.loadHostSection === 'function') {
            window.loadHostSection(section); 
        } else {
            window.location.href = historyUrl;
        }
    });
});

// 🚨 Report Message Modal (SweetAlert)
$(document).on('click', '.report-btn', function() {
    const $button = $(this);
    const messageId = $button.data('message-id');
    const reportedUserId = $button.data('reported-user-id');
    const isGroup = $button.data('is-group');

    Swal.fire({
        title: '<span class="text-xl font-bold text-red-600">Report Message</span>',
        html: 
            '<div class="text-left p-2">' +
                '<p class="text-sm text-gray-600 mb-3">Please state the specific reason for reporting this message (e.g., hate speech, spam, harassment).</p>' +
                '<textarea id="reportReason" ' +
                        'class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm transition duration-150" ' +
                        'placeholder="Enter reason here..." rows="4"></textarea>' +
            '</div>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-flag mr-1"></i> Submit Report',
        confirmButtonColor: '#dc2626',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            const reason = $('#reportReason').val().trim();
            if (!reason) {
                Swal.showValidationMessage('Please provide a reason to submit the report.');
                return false;
            }
            return reason;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const reason = result.value;
            
            $.post('ajax/ajax_report_chat.php', {
                message_id: messageId,
                reported_user_id: reportedUserId,
                reason: reason,
                is_group_chat: isGroup
            }, function(response) {
                if (response.success) {
                    Swal.fire('Report Submitted', response.message, 'success');
                    $button.html('<i class="fas fa-check"></i> Reported').prop('disabled', true).removeClass('hover:text-red-500');
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            }, 'json').fail(function() {
                Swal.fire('Error', 'Failed to connect to the reporting server.', 'error');
            });
        }
    });
});
</script>