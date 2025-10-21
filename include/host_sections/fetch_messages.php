<?php
// FILE: include/host_sections/fetch_messages.php (Host AJAX)

require_once __DIR__ . '/../../include/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host' || !isset($_GET['group_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized or missing group ID.']);
    exit;
}

$host_id = (int)$_SESSION['user_id'];
$group_id = (int)$_GET['group_id'];
$last_id = (int)($_GET['last_id'] ?? 0);

$conn = get_db_connection(); 

$auth_sql = "SELECT COUNT(*) FROM group_chats gc INNER JOIN projects p ON gc.project_id = p.project_id WHERE gc.group_id = ? AND p.host_id = ?";
$auth_stmt = $conn->prepare($auth_sql);
$auth_stmt->bind_param("ii", $group_id, $host_id);
$auth_stmt->execute();
$auth_stmt->bind_result($is_authorized);
$auth_stmt->fetch();
$auth_stmt->close();

if ($is_authorized === 0) {
    echo json_encode(['success' => false, 'error' => 'Not authorized to view this group.']);
    $conn->close();
    exit;
}

// time_ago helper function (Must be defined here or included)
function time_ago($datetime) {
    $timestamp = strtotime($datetime);
    if (!is_numeric($timestamp) || $timestamp <= 0) return "N/A";
    $diff = time() - $timestamp;
    if ($diff < 0) return "Just now"; 
    if ($diff < 60) return $diff . ' seconds ago';
    if ($diff < 3600) return round($diff / 60) . ' minutes ago';
    if ($diff < 86400) return round($diff / 3600) . ' hours ago';
    if ($diff < 604800) return round($diff / 86400) . ' days ago';
    return date("M d, Y", $timestamp);
}

// Fetch messages with User Role info
$messages = [];
$last_fetched_id = $last_id;

$sql = "
    SELECT 
        cm.message_id, 
        cm.user_id, 
        cm.message_text, 
        cm.created_at, 
        u.name AS user_name,
        u.role AS user_type,
        p.preferred_role AS participant_role
    FROM chat_messages cm
    JOIN users u ON cm.user_id = u.user_id
    LEFT JOIN participants p ON u.user_id = p.participant_id
    WHERE cm.group_id = ? AND cm.message_id > ?
    ORDER BY cm.created_at ASC
    LIMIT 50
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $group_id, $last_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $row['message_text'] = htmlspecialchars($row['message_text']);
    
    $display_role = '';
    if ($row['user_type'] === 'host') {
        $display_role = 'Host';
    } elseif ($row['user_type'] === 'participant') {
        $display_role = !empty($row['participant_role']) ? $row['participant_role'] : 'Participant';
    }
    
    $row['full_user_name'] = htmlspecialchars($row['user_name']) . ($display_role ? " ({$display_role})" : '');
    $row['time_ago'] = time_ago($row['created_at']);
    
    $messages[] = $row;
    $last_fetched_id = $row['message_id'];
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'last_id' => $last_fetched_id
]);
?>