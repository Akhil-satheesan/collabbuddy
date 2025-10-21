<?php
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING); 
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require __DIR__ . '/../../../include/config.php'; 

$conn = get_db_connection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '🚫 Access denied.']);
    $conn->close();
    exit;
}

$host_id = (int)$_SESSION['user_id'];
$request_id = (int)($_POST['id'] ?? 0);
$action = trim($_POST['action'] ?? '');
$assigned_role_host_select = trim($_POST['assigned_role'] ?? '');

if (!$request_id || !in_array($action, ['accept', 'reject'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '❌ Invalid request ID or action.']);
    $conn->close();
    exit;
}

$conn->begin_transaction();
$response_array = ['success' => false, 'message' => 'Operation failed.'];

try {
    $sql_details = "
        SELECT pr.project_id, pr.participant_id 
        FROM project_requests pr
        JOIN projects p ON pr.project_id = p.project_id
        WHERE pr.request_id = ? 
          AND pr.status = 'pending' 
          AND p.host_id = ?
    ";
    $stmt_details = $conn->prepare($sql_details);
    if (!$stmt_details) throw new Exception("Prepare details failed: " . $conn->error);
    $stmt_details->bind_param("ii", $request_id, $host_id);
    $stmt_details->execute();
    $details_result = $stmt_details->get_result();
    
    if ($details_result->num_rows === 0) {
        throw new Exception("Request not found, already processed, or access denied (Host mismatch).");
    }
    
    $request_data = $details_result->fetch_assoc();
    $project_id = (int)$request_data['project_id'];
    $participant_id = (int)$request_data['participant_id'];
    $stmt_details->close();
    
    $req_status = ($action === 'accept') ? 'accepted' : 'rejected'; 
    $app_status = ($action === 'accept') ? 'Accepted' : 'Rejected'; 
    
    if ($action === 'accept') {
        if (empty($assigned_role_host_select)) {
            throw new Exception("Role selection is mandatory for accepting the request. Please reload the page.");
        }
        
        $sql_update = "UPDATE project_requests SET status = ? WHERE request_id = ?";
        $stmt_update = $conn->prepare($sql_update);
        if (!$stmt_update) throw new Exception("Prepare update failed: " . $conn->error);
        $stmt_update->bind_param("si", $req_status, $request_id); 
        if (!$stmt_update->execute()) throw new Exception("Update status failed: " . $stmt_update->error);
        $stmt_update->close();

        $sql_app_update = "UPDATE applications SET status = ? WHERE project_id = ? AND participant_id = ?";
        $stmt_app_update = $conn->prepare($sql_app_update);
        if (!$stmt_app_update) throw new Exception("Prepare app update failed: " . $conn->error);
        $stmt_app_update->bind_param("sii", $app_status, $project_id, $participant_id); 
        if (!$stmt_app_update->execute()) throw new Exception("Update app status failed: " . $stmt_app_update->error);
        $stmt_app_update->close();

        $sql_part = "INSERT IGNORE INTO project_participants (project_id, participant_id) VALUES (?, ?)";
        $stmt_part = $conn->prepare($sql_part);
        if (!$stmt_part) throw new Exception("Prepare participant failed: " . $conn->error);
        $stmt_part->bind_param("ii", $project_id, $participant_id);
        if (!$stmt_part->execute()) throw new Exception("Insert participant failed: " . $stmt_part->error);
        $stmt_part->close();

        $sql_roles = "SELECT required_roles_list, team_size_per_role FROM projects WHERE project_id = ?";
        $stmt_roles = $conn->prepare($sql_roles);
        if (!$stmt_roles) throw new Exception("Prepare roles check failed: " . $conn->error);
        $stmt_roles->bind_param("i", $project_id);
        $stmt_roles->execute();
        $stmt_roles->bind_result($roles_list_str, $roles_size_str);
        $stmt_roles->fetch();
        $stmt_roles->close();

        $roles_array = array_map('trim', explode(',', $roles_list_str));
        $size_array = [];
        foreach (explode(',', $roles_size_str) as $role_pair) {
            $parts = array_map('trim', explode(':', $role_pair));
            if (count($parts) === 2) {
                $size_array[$parts[0]] = (int)$parts[1];
            }
        }

        if (!in_array($assigned_role_host_select, $roles_array)) {
            throw new Exception("Selected role '{$assigned_role_host_select}' is not a valid required role for this project. Check project settings.");
        }

        $role_to_assign = $assigned_role_host_select;
        $role_capacity = $size_array[$role_to_assign] ?? 1;

        $sql_count = "SELECT COUNT(*) FROM project_participants WHERE project_id = ? AND role_taken = ?";
        $stmt_count = $conn->prepare($sql_count);
        if (!$stmt_count) throw new Exception("Prepare role count failed: " . $conn->error);
        $stmt_count->bind_param("is", $project_id, $role_to_assign);
        $stmt_count->execute();
        $stmt_count->bind_result($current_count);
        $stmt_count->fetch();
        $stmt_count->close();

        if ($current_count >= $role_capacity) {
            throw new Exception("🛑 Role '{$role_to_assign}' is currently full (Max: {$role_capacity}). Cannot accept the applicant.");
        }

        $sql_assign = "UPDATE project_participants SET role_taken = ? WHERE project_id = ? AND participant_id = ?";
        $stmt_assign = $conn->prepare($sql_assign);
        if (!$stmt_assign) throw new Exception("Prepare role assignment failed: " . $conn->error);
        $stmt_assign->bind_param("sii", $role_to_assign, $project_id, $participant_id);
        if (!$stmt_assign->execute()) throw new Exception("Update role assignment failed: " . $stmt_assign->error);
        $stmt_assign->close();

        $assigned_role = $role_to_assign;

        $group_id = null;
        $sql_chat_get = "SELECT group_id FROM group_chats WHERE project_id = ?";
        $stmt_chat_get = $conn->prepare($sql_chat_get);
        $stmt_chat_get->bind_param("i", $project_id);
        $stmt_chat_get->execute();
        $stmt_chat_get->bind_result($group_id);
        $stmt_chat_get->fetch();
        $stmt_chat_get->close();

        if (!$group_id) {
            $sql_chat_name = "SELECT title FROM projects WHERE project_id = ?";
            $stmt_chat_name = $conn->prepare($sql_chat_name);
            $stmt_chat_name->bind_param("i", $project_id);
            $stmt_chat_name->execute();
            $stmt_chat_name->bind_result($project_title);
            $stmt_chat_name->fetch();
            $stmt_chat_name->close();

            $group_name = $project_title ? htmlspecialchars($project_title) . " Team Chat" : "New Project Chat";
            $group_name = substr($group_name, 0, 250); 

            $sql_chat_create = "INSERT INTO group_chats (project_id, group_name, created_by) VALUES (?, ?, ?)";
            $stmt_chat_create = $conn->prepare($sql_chat_create);
            $stmt_chat_create->bind_param("isi", $project_id, $group_name, $host_id); 
            $stmt_chat_create->execute();
            $group_id = $conn->insert_id;
            $stmt_chat_create->close();
            
            $sql_host_member = "INSERT IGNORE INTO group_chat_members (group_id, user_id) VALUES (?, ?)";
            $stmt_host_member = $conn->prepare($sql_host_member);
            $stmt_host_member->bind_param("ii", $group_id, $host_id);
            $stmt_host_member->execute(); 
            $stmt_host_member->close();
        }
        
        if ($group_id) {
            $sql_member = "INSERT IGNORE INTO group_chat_members (group_id, user_id) VALUES (?, ?)";
            $stmt_member = $conn->prepare($sql_member);
            $stmt_member->bind_param("ii", $group_id, $participant_id);
            $stmt_member->execute();
            $stmt_member->close();
        }

        $conn->commit();
        $response_array = [
            'success' => true,
            'message' => "✅ Request accepted and participant assigned role '{$assigned_role}' and added to the project group chat!",
            'group_id' => $group_id,
            'role_assigned' => $assigned_role
        ];

    } else {
        $sql_update = "UPDATE project_requests SET status = ? WHERE request_id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("si", $req_status, $request_id); 
        $stmt_update->execute();
        $stmt_update->close();
        
        $sql_app_update = "UPDATE applications SET status = ? WHERE project_id = ? AND participant_id = ?";
        $stmt_app_update = $conn->prepare($sql_app_update);
        $stmt_app_update->bind_param("sii", $app_status, $project_id, $participant_id); 
        $stmt_app_update->execute();
        $stmt_app_update->close();
        
        $conn->commit();
        $response_array = [
            'success' => true,
            'message' => '❌ Request rejected successfully!',
        ];
    }
    
} catch(Exception $e) {
    $conn->rollback();
    http_response_code(500);
    $error_message = 'Database transaction error: ' . $e->getMessage();
    error_log("HOST REQUEST ERROR: " . $error_message);
    
    if (strpos($e->getMessage(), '🛑 Role') !== false) {
        $response_array = [
            'success' => false,
            'message' => $e->getMessage() 
        ];
        http_response_code(409);
    } elseif (strpos($e->getMessage(), 'mandatory for accepting') !== false) {
         $response_array = [
            'success' => false,
            'message' => 'Role selection is mandatory for accepting the request. Please select a role and try again.'
        ];
        http_response_code(400);
    } else {
        $response_array = [
            'success' => false,
            'message' => 'System error. Could not process the request. Details logged.'
        ];
    }
}

echo json_encode($response_array);
$conn->close();
?>