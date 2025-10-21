<?php
session_start();
require '../config.php';

$user_id = $_SESSION['user_id'];
$type = $_GET['type'] ?? '';
$room = $_GET['room'] ?? '';

// Load chat list
if ($type === 'projects') {
    $sql = "SELECT p.project_id, p.title 
            FROM projects p 
            JOIN project_participants pp ON p.project_id=pp.project_id
            WHERE pp.participant_id=? OR p.host_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii",$user_id,$user_id);
    $stmt->execute();
    $res=$stmt->get_result();
    while($row=$res->fetch_assoc()){
        echo "<button class='chat-room-btn w-full text-left p-3 rounded-lg hover:bg-gray-50' data-room='project_{$row['project_id']}'>
                <p class='font-medium text-gray-900'>{$row['title']}</p>
              </button>";
    }
    exit;
}

// Load messages in a room
if ($room) {
    if (strpos($room,'project_')===0) {
        $project_id = str_replace('project_','',$room);
        $sql = "SELECT m.*, u.name 
                FROM chat_messages m 
                JOIN users u ON m.sender_id=u.user_id
                WHERE m.project_id=? ORDER BY m.created_at ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i",$project_id);
    } 
    $stmt->execute();
    $res=$stmt->get_result();
    while($row=$res->fetch_assoc()){
        $align = ($row['sender_id']==$user_id) ? 'text-right' : 'text-left';
        echo "<div class='$align mb-2'>
                <div class='inline-block bg-white px-3 py-2 rounded shadow'>
                    <p class='text-sm'>{$row['message']}</p>
                </div>
                <p class='text-xs text-gray-500'>{$row['name']} • {$row['created_at']}</p>
              </div>";
    }
    exit;
}
