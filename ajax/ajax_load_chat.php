<?php
// C:\xampp\htdocs\collabuddy\ajax\ajax_load_chat.php

session_start();
// config.php-യിലേക്കുള്ള path നിങ്ങളുടെ പ്രോജക്റ്റ് ഘടന അനുസരിച്ച് ശരിയായിരിക്കണം.
require '../include/config.php';

if (!isset($_SESSION['user_id'])) { 
    http_response_code(401); 
    die(); 
}

$current_user_id = $_SESSION['user_id'];
// ലഭിക്കുന്ന parameters സുരക്ഷിതമാക്കുന്നു
$chat_identifier = (int)($_GET['chat_identifier'] ?? 0);
$is_group = (int)($_GET['is_group'] ?? 0); // 0 for 1:1, 1 for Group
$user_role = $_SESSION['role'];
$conn = get_db_connection();

if ($chat_identifier <= 0) {
    echo "<p class='text-center text-red-500 p-5'>Invalid chat room identifier.</p>";
    $conn->close();
    exit;
}

// 1. സന്ദേശങ്ങൾ എടുക്കുക
$msgSql = "SELECT m.message_id, m.sender_id, m.message_content, m.sent_at, u.name AS sender_name 
            FROM messages m
            JOIN users u ON u.user_id = m.sender_id
            WHERE m.chat_identifier = ? AND m.is_group_chat = ?
            ORDER BY m.sent_at ASC";
            
$msgStmt = $conn->prepare($msgSql);

if ($msgStmt === false) {
    error_log("Chat Load Prepare Error: " . $conn->error);
    echo "<p class='text-center text-red-500 p-5'>Error loading messages.</p>";
    $conn->close();
    exit;
}

$msgStmt->bind_param("ii", $chat_identifier, $is_group);
$msgStmt->execute();
$messages = $msgStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$msgStmt->close();

// 2. HTML റെൻഡർ ചെയ്യുക (Chat Bubble Style + Report Option)
$main_color = $user_role === 'host' ? 'indigo' : 'blue'; 
$output = '';

foreach ($messages as $msg) {
    $isMe = $msg['sender_id'] == $current_user_id;
    $senderName = $isMe ? 'You' : htmlspecialchars($msg['sender_name']);
    
    // ⭐️ റിപ്പോർട്ട് ബട്ടൺ ലോജിക്
    $report_button = '';
    // സ്വന്തം മെസ്സേജ് റിപ്പോർട്ട് ചെയ്യേണ്ട, കൂടാതെ Host/Participant വേർതിരിവ് ആവശ്യമില്ല
    if (!$isMe) { 
        $report_button = '
        <button class="report-btn text-gray-400 hover:text-red-500 text-sm ml-2"
                title="Report this message"
                data-message-id="' . (int)$msg['message_id'] . '"
                data-reported-user-id="' . (int)$msg['sender_id'] . '"
                data-is-group="' . (int)$is_group . '">
            <i class="fas fa-flag"></i> Report
        </button>';
    }

    $output .= '<div class="flex ' . ($isMe ? 'justify-end' : 'justify-start') . '">
                    <div class="max-w-xs md:max-w-md p-3 rounded-2xl shadow-md relative ' . 
                        ($isMe ? "bg-{$main_color}-600 text-white" : 'bg-white text-gray-800 border border-gray-200') . ' ' . 
                        ($isMe ? 'rounded-br-none' : 'rounded-tl-none') . '">
                        
                        <p class="font-semibold text-xs mb-1 ' . ($isMe ? 'text-white' : 'text-gray-600') . '">
                            ' . $senderName . '
                        </p>
                        
                        <p class="text-sm whitespace-pre-wrap">' . htmlspecialchars($msg['message_content']) . '</p>
                        
                        <div class="flex justify-between items-center mt-1">
                            <span class="text-[10px] ' . ($isMe ? 'text-gray-200' : 'text-gray-500') . '">' . date("g:i a", strtotime($msg['sent_at'])) . '</span>
                            ' . $report_button . '
                        </div>
                    </div>
                </div>';
}
echo $output;

$conn->close();
?>