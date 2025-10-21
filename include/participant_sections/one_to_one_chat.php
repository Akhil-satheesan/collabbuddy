<?php
if (!isset($conn) || !isset($_SESSION['user_id']) || $_SESSION['role'] !== 'participant') { 
    http_response_code(401);
    echo "<p class='text-red-600 p-6'>Authentication or connection error. Please refresh the dashboard.</p>";
    exit(); 
}
$current_user_id = $_SESSION['user_id'];

$chatRooms = [];

$roomSql = "SELECT cr.room_id, cr.project_id, pr.status, p.title AS project_title, u_h.name AS host_name, u_h.user_id AS host_id, u_h.profile_pic_url
            FROM chat_rooms cr
            JOIN projects p ON p.project_id = cr.project_id
            JOIN users u_h ON u_h.user_id = cr.host_id
            INNER JOIN project_requests pr 
                ON pr.host_id = cr.host_id 
                AND pr.participant_id = cr.participant_id 
                AND pr.project_id = cr.project_id
            WHERE cr.participant_id = ?
            ORDER BY cr.created_at DESC";

$roomStmt = $conn->prepare($roomSql);
$roomStmt->bind_param("i", $current_user_id); 
$roomStmt->execute();
$roomResult = $roomStmt->get_result();

while ($room = $roomResult->fetch_assoc()) {
    $room['other_user_name'] = $room['host_name']; 
    $chatRooms[] = $room;
}
$roomStmt->close();

$initial_project_id = (isset($_GET['project_id']) && is_numeric($_GET['project_id'])) 
                     ? (int)$_GET['project_id'] 
                     : null;
                     
$selected_room_id = (isset($_GET['room_id']) && is_numeric($_GET['room_id'])) 
                    ? (int)$_GET['room_id'] 
                    : null;

if (!$selected_room_id && $initial_project_id) {
    foreach ($chatRooms as $room) {
        if ($room['project_id'] == $initial_project_id) {
            $selected_room_id = $room['room_id'];
            break;
        }
    }
}

if (!$selected_room_id && !empty($chatRooms)) {
    $selected_room_id = $chatRooms[0]['room_id'];
}

$selected_room_data = null;
if ($selected_room_id) {
    foreach ($chatRooms as $room) {
        if ($room['room_id'] == $selected_room_id) {
            $selected_room_data = $room;
            break;
        }
    }
}
$other_user_id = $selected_room_data['host_id'] ?? null;
$is_chat_rejected = ($selected_room_data['status'] ?? '') === 'rejected';

?>

<div class="flex h-[80vh] bg-white rounded-lg shadow-2xl border border-gray-100 overflow-hidden">
    
    <div class="w-1/3 md:w-1/4 bg-gray-50 border-r border-gray-200 flex flex-col">
        <div class="p-4 border-b border-gray-200">
            <h3 class="text-xl font-extrabold text-indigo-600">Host Chats (1:1)</h3>
            <p class="text-xs text-gray-500">Conversations with project hosts.</p>
        </div>
        <div class="flex-1 overflow-y-auto" id="chatListContainer">
            <?php if (empty($chatRooms)): ?>
                <p class="text-center text-sm text-gray-500 p-4 mt-5">No active 1:1 chats yet.</p>
            <?php else: ?>
                <?php foreach ($chatRooms as $room): 
                    $isActive = $room['room_id'] == $selected_room_id;
                    $statusBadge = ($room['status'] === 'pending') ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800';
                    if ($room['status'] === 'rejected') $statusBadge = 'bg-red-100 text-red-800';
                    $profile_pic = !empty($room['profile_pic_url']) 
                                   ? $room['profile_pic_url']
                                   : 'assets/images/default_host.png';
                ?>
                    <a href="#" 
                        data-section="one_to_one_chat" 
                        data-room-id="<?= $room['room_id'] ?>"
                        id="chat-link-<?= $room['room_id'] ?>"
                        class="load-chat-link flex items-center p-3 border-b border-gray-100 transition duration-150 <?= $isActive ? 'bg-indigo-50 border-l-4 border-indigo-500' : 'hover:bg-gray-100' ?>">
                         
                         <div class="w-10 h-10 rounded-full overflow-hidden mr-3 relative">
                             <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Host PFP" class="w-full h-full object-cover">
                         </div>
                         
                         <div class="flex-1 min-w-0">
                             <p class="font-semibold text-gray-800 truncate"><?= htmlspecialchars($room['other_user_name']) ?></p>
                             <p class="text-xs text-gray-500 truncate">Proj: <?= htmlspecialchars($room['project_title']) ?></p>
                         </div>
                         
                         <div class="flex flex-col items-end space-y-1">
                             <span class="text-xs font-medium px-2 py-0.5 rounded-full <?= $statusBadge ?>">
                                 <?= ucfirst(htmlspecialchars($room['status'])) ?>
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
                    <h4 class="text-lg font-bold text-gray-800">Chat with <?= htmlspecialchars($selected_room_data['other_user_name']) ?> (Host)</h4>
                    <p class="text-sm text-gray-500">Project: <?= htmlspecialchars($selected_room_data['project_title']) ?></p>
                </div>
                <button onclick="window.showHostProfile(<?= $other_user_id ?>, '<?= htmlspecialchars($selected_room_data['other_user_name']) ?>')" 
                         class="text-indigo-600 hover:text-indigo-800 text-sm font-semibold">
                    <i class="fas fa-eye mr-1"></i> View Host Profile
                </button>
            </div>
            
            <div id="typingIndicator" class="px-6 py-2 bg-yellow-50 text-yellow-700 text-sm hidden border-b border-yellow-200">
                <i class="fas fa-ellipsis-h mr-1"></i> <?= htmlspecialchars($selected_room_data['other_user_name']) ?> is typing...
            </div>
            
            <div id="messageDisplay" class="flex-1 p-6 space-y-4 overflow-y-auto bg-gray-50">
                <?php if ($is_chat_rejected): ?>
                    <div class="text-center p-10 mt-10 bg-red-50 border-l-4 border-red-500 text-red-700">
                        <i class="fas fa-trash-alt fa-2x mb-3"></i>
                        <h4 class="text-xl font-bold">Chat Cleared</h4>
                        <p class="text-sm">This chat has been closed because your application was **rejected** by the host. All messages are now archived.</p>
                    </div>
                <?php else: ?>
                    <div class="text-center text-gray-400 p-10">Loading messages...</div>
                <?php endif; ?>
            </div>

            <div class="p-4 border-t border-gray-200 bg-white">
                <form id="chatForm" class="flex" <?= $is_chat_rejected ? 'onsubmit="return false;"' : '' ?>>
                    <input type="hidden" name="chat_identifier" id="chatIdentifierInput" value="<?= $selected_room_id ?>">
                    <input type="hidden" name="is_group_chat" id="isGroupChatInput" value="0"> 
                    <input type="text" name="message_content" id="messageInput" placeholder="<?= $is_chat_rejected ? 'Chat is disabled for rejected applications.' : 'Type a message...' ?>"
                            class="flex-1 p-3 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" 
                            <?= $is_chat_rejected ? 'disabled' : 'required' ?>>
                    <button type="submit" class="bg-indigo-600 text-white px-5 py-3 rounded-r-lg font-semibold hover:bg-indigo-700 transition duration-150"
                            <?= $is_chat_rejected ? 'disabled' : '' ?>>
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

    window.clearChatInterval = function() {
        if(messageLoadInterval) {
            clearInterval(messageLoadInterval);
            messageLoadInterval = null; 
        }
        if(statusPollingInterval) {
            clearInterval(statusPollingInterval);
            statusPollingInterval = null;
        }
    };
    
    if (typeof window.showHostProfile !== 'function') {
        window.showHostProfile = function(hostId, hostName) {
            alert(`Feature missing: Host Profile for ${hostName} (ID: ${hostId}) is not yet configured.`);
        };
    }

    function loadMessages() {
        if (!messageDisplay) return;

        if (messageInput.prop('disabled')) {
            messageDisplay.scrollTop = messageDisplay.scrollHeight;
            return;
        }

        const isScrolledToBottom = messageDisplay.scrollHeight - messageDisplay.clientHeight <= messageDisplay.scrollTop + 50; 
        
        $.get('ajax/ajax_load_chat.php', { 
            chat_identifier: chatIdentifier, 
            is_group: isGroup, 
            current_user_id: '<?= $current_user_id ?>' 
        }, function(data) {
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
        
        const allRooms = [<?= implode(',', array_map(fn($r) => $r['room_id'], $chatRooms)) ?>];
        
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
    
    if (chatForm.length) {
        chatForm.on('submit', function(e) {
            e.preventDefault(); 
            
            if (messageInput.prop('disabled')) return; 
            
            const messageContent = messageInput.val().trim();
            if (messageContent === '') return;

            setTypingStatus(false);
            clearTimeout(typingTimeout); 
            typingTimeout = undefined;
            
            const postData = {
                chat_identifier: chatIdentifier, 
                message_content: messageContent 
            };

            $.post('ajax/ajax_send_chat.php', postData, function(response) {
                if (response.success) {
                    messageInput.val(''); 
                    loadMessages(); 
                } else {
                    alert('Error sending message: ' + response.message); 
                }
            }, 'json').fail(function(xhr) {
                alert('Failed to connect to server. Status: ' + xhr.status);
            }).always(function() {
                sendButton.prop('disabled', false).html('Send');
            });
        });
    }

    if (!messageInput.prop('disabled')) {
        loadMessages(); 
        window.clearChatInterval();
        messageLoadInterval = setInterval(loadMessages, 3000); 
    }
    
    loadChatStatus();
    statusPollingInterval = setInterval(loadChatStatus, 5000);

    $('.load-chat-link').on('click', function(e) {
        e.preventDefault(); 
        
        window.clearChatInterval();
        
        const section = $(this).data('section');
        const roomId = $(this).data('room-id');

        const historyUrl = `?section=${section}&room_id=${roomId}`;
        history.pushState(null, '', historyUrl);
        
        if (typeof window.loadSection === 'function') {
            window.loadSection(section); 
        } else {
            window.location.href = historyUrl;
        }
    });
});

$(document).on('click', '.report-btn', function() {
    const $button = $(this);
    const messageId = $button.data('message-id');
    const reportedUserId = $button.data('reported-user-id');
    const isGroup = $button.data('is-group');

    Swal.fire({
        title: 'Report Message',
        html: 
            '<div class="text-left">' +
            '<p class="text-sm text-gray-600 mb-3">Please state the reason for reporting this message.</p>' +
            '<textarea id="reportReason" class="swal2-textarea" placeholder="Enter reason here..." rows="4"></textarea>' +
            '</div>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Submit Report',
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