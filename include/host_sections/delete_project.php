<?php
// delete_project.php

session_start();
require_once 'include/config.php'; 

// 1. ഓതൻ്റിക്കേഷൻ
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host') {
    header("Location: login.php");
    exit;
}

// 2. പ്രോജക്ട് ഐഡി എടുക്കുന്നു
$project_id = $_GET['id'] ?? null;
if (!$project_id || !is_numeric($project_id)) {
    $_SESSION['toast'] = ['message' => 'Invalid project ID.', 'type' => 'error'];
    header("Location: host_dashboard.php?section=my_projects");
    exit;
}

$host_id = $_SESSION['user_id'];
$success = false;

// 3. പ്രോജക്ട് നിലവിലുണ്ടോ എന്നും 'Active' ആണോ എന്നും പരിശോധിക്കുന്നു
$check_stmt = $conn->prepare("SELECT status FROM projects WHERE project_id = ? AND host_id = ?");
if (!$check_stmt) {
    $_SESSION['toast'] = ['message' => 'Error checking project status.', 'type' => 'error'];
    header("Location: host_dashboard.php?section=my_projects");
    exit;
}

$check_stmt->bind_param("ii", $project_id, $host_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    $_SESSION['toast'] = ['message' => 'Project not found or you do not have permission.', 'type' => 'error'];
    header("Location: host_dashboard.php?section=my_projects");
    exit;
}

$project_data = $check_result->fetch_assoc();
$check_stmt->close();

if (strtolower($project_data['status']) !== 'active') {
    $_SESSION['toast'] = ['message' => 'Only active projects can be deleted.', 'type' => 'error'];
    header("Location: host_dashboard.php?section=my_projects");
    exit;
}

// 4. പ്രോജക്റ്റ് ഡിലീറ്റ് ചെയ്യുന്നു (Foreign Key constraints കാരണം ആദ്യം project_requests ഡിലീറ്റ് ചെയ്യേണ്ടിവരും)
try {
    $conn->begin_transaction();

    // 4.1. Project Requests (Applications) ഡിലീറ്റ് ചെയ്യുന്നു
    $del_requests = $conn->prepare("DELETE FROM project_requests WHERE project_id = ?");
    $del_requests->bind_param("i", $project_id);
    $del_requests->execute();
    $del_requests->close();
    
    // 4.2. Tasks ഡിലീറ്റ് ചെയ്യുന്നു (Active ആണെങ്കിൽ task കുറവായിരിക്കും, പക്ഷെ safetyക്ക് വേണ്ടി)
    $del_tasks = $conn->prepare("DELETE FROM tasks WHERE project_id = ?");
    $del_tasks->bind_param("i", $project_id);
    $del_tasks->execute();
    $del_tasks->close();

    // 4.3. Project ഡിലീറ്റ് ചെയ്യുന്നു
    $delete_stmt = $conn->prepare("DELETE FROM projects WHERE project_id = ? AND host_id = ?");
    $delete_stmt->bind_param("ii", $project_id, $host_id);
    $delete_stmt->execute();
    
    if ($delete_stmt->affected_rows > 0) {
        $conn->commit();
        $_SESSION['toast'] = ['message' => 'Project deleted successfully!', 'type' => 'success'];
    } else {
        $conn->rollback();
        $_SESSION['toast'] = ['message' => 'Project not found or unable to delete.', 'type' => 'error'];
    }
    $delete_stmt->close();
    
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['toast'] = ['message' => 'An error occurred during deletion: ' . $e->getMessage(), 'type' => 'error'];
}

header("Location: host_dashboard.php?section=my_projects");
exit;
?>