<?php

session_start();
require '../../../include/config.php'; 

$conn->autocommit(FALSE);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host') {
 $_SESSION['toast'] = ['message' => '🚫 Access denied. Please log in as a Host.', 'type' => 'error'];
 header("Location: ../../../login.php?role=host");
 exit;
}

$host_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
 $_SESSION['toast'] = ['message' => '❌ Invalid request method.', 'type' => 'error'];
 header("Location: ../../../host_dashboard.php");
 exit;
}

$title = trim($_POST['title'] ?? '');
$project_category = trim($_POST['project_category'] ?? '');
$complexity_level = trim($_POST['complexity_level'] ?? '');
$duration = trim($_POST['duration'] ?? ''); 
$team_size = trim($_POST['team_size'] ?? ''); 
$required_roles_list = trim($_POST['required_roles_list'] ?? ''); 
$team_size_per_role = trim($_POST['team_size_per_role'] ?? '');
$required_skills = trim($_POST['required_skills'] ?? '');
$description = trim($_POST['description'] ?? ''); 
$status_default = 'Active'; 

$host_is_member = isset($_POST['host_is_member']) ? 1 : 0; 
$host_role = $host_is_member ? trim($_POST['host_role'] ?? 'Project Host & Contributor') : null;

if (empty($title) || empty($project_category) || empty($complexity_level) || empty($required_roles_list) || empty($team_size_per_role) || empty($required_skills) || empty($description)) {
 $conn->rollback(); 
 $_SESSION['toast'] = ['message' => '⚠️ Please fill in all crucial project details.', 'type' => 'error'];
  header("Location: ../../../host_dashboard.php?section=my_projects");
  exit;
}

if ($host_is_member == 1 && empty($host_role)) {
    $conn->rollback(); 
    $_SESSION['toast'] = ['message' => '⚠️ Please specify your role in the team, or uncheck the host participation box.', 'type' => 'error'];
    header("Location: ../../../host_dashboard.php?section=my_projects");
    exit;
}

$check_sql = "SELECT COUNT(*) FROM projects 
 WHERE host_id = ? AND title = ? AND status IN ('Active', 'In Progress')"; 
 
$check_stmt = $conn->prepare($check_sql);
if ($check_stmt) {
 $check_stmt->bind_param("is", $host_id, $title);
 $check_stmt->execute();
 $check_stmt->bind_result($count);
 $check_stmt->fetch();
  $check_stmt->close();
 if ($count > 0) {
     $conn->rollback();
         $_SESSION['toast'] = ['message' => '🚫 You already have an active project with the title "' . htmlspecialchars($title) . '".', 'type' => 'error'];
         header("Location: ../../../host_dashboard.php?section=my_projects");
         exit;  
         }
} 

$sql = "INSERT INTO projects (
            host_id, 
            title, 
            description, 
            project_category, 
            complexity_level, 
            duration, 
            team_size, 
            required_roles_list, 
            team_size_per_role, 
            required_skills, 
            status,
            host_is_member 
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; 

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param(
        "issssssssssi", 
        $host_id, 
        $title, 
        $description, 
        $project_category, 
        $complexity_level, 
        $duration, 
        $team_size, 
        $required_roles_list, 
        $team_size_per_role, 
        $required_skills, 
        $status_default, 
        $host_is_member 
    );

    if ($stmt->execute()) {
        $project_id = $conn->insert_id; 
        $stmt->close(); 

        $success = true; 
        $error = '';
        
        if ($host_is_member == 1) {

            $sql_pp = "INSERT INTO project_participants (project_id, participant_id, role_taken) VALUES (?, ?, ?)";
            $stmt_pp = $conn->prepare($sql_pp);
            if ($stmt_pp && $stmt_pp->bind_param("iis", $project_id, $host_id, $host_role) && $stmt_pp->execute()) {
                $stmt_pp->close();
            } else {
                $success = false;
                $error = $conn->error;
            }

            if ($success) {
                $sql_pm = "INSERT INTO project_members (project_id, participant_id) VALUES (?, ?)";
                $stmt_pm = $conn->prepare($sql_pm);
                if ($stmt_pm && $stmt_pm->bind_param("ii", $project_id, $host_id) && $stmt_pm->execute()) {
                    $stmt_pm->close();
                } else {
                    $success = false;
                    $error = $conn->error;
                }
            }

            if ($success) {
                $chat_name = "Team Chat for: " . $title;
                $sql_gc = "INSERT INTO group_chats (project_id, group_name, created_by) VALUES (?, ?, ?)";
                $stmt_gc = $conn->prepare($sql_gc);
                if ($stmt_gc && $stmt_gc->bind_param("isi", $project_id, $chat_name, $host_id) && $stmt_gc->execute()) {
                    $group_chat_id = $conn->insert_id;
                    $stmt_gc->close();
                } else {
                    $success = false;
                    $error = $conn->error;
                }
            }

            if ($success && isset($group_chat_id)) {
                $sql_gcm = "INSERT INTO group_chat_members (group_id, user_id) VALUES (?, ?)";
                $stmt_gcm = $conn->prepare($sql_gcm);
                if ($stmt_gcm && $stmt_gcm->bind_param("ii", $group_chat_id, $host_id) && $stmt_gcm->execute()) {
                    $stmt_gcm->close();
                } else {
                    $success = false;
                    $error = $conn->error;
                }
            }
        }
        
        if ($success) {
            $conn->commit(); 
            $_SESSION['toast'] = ['message' => '✅ Project idea "' . htmlspecialchars($title) . '" posted successfully!', 'type' => 'success'];
            header("Location: ../../../host_dashboard.php?section=my_projects");
        } else {
            $conn->rollback(); 
            $_SESSION['toast'] = ['message' => '❌ Project posted, but team setup failed. Please contact support. Error: (' . $error . ')', 'type' => 'error'];
            header("Location: ../../../host_dashboard.php?section=my_projects");
        }
        
    } else {
        $conn->rollback(); 
        $_SESSION['toast'] = ['message' => '❌ Database execution error: Could not post project. (' . $stmt->error . ')', 'type' => 'error'];
        header("Location: ../../../host_dashboard.php?section=my_projects");
        $stmt->close();
    }
    
} else {
    $conn->rollback(); 
    $error_message = $conn->error ? 'MySQL Error: ' . $conn->error : 'Unknown prepare error.';
    $_SESSION['toast'] = ['message' => '❌ System error: Could not prepare statement. ' . $error_message, 'type' => 'error'];
    header("Location: ../../../host_dashboard.php?section=my_projects");
}

$conn->autocommit(TRUE);
$conn->close();
exit;
?>