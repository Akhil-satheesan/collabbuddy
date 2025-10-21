<?php
// FILE: ajax_update_project.php - Final Fixed Version

// Start output buffering immediately to catch any stray output (like the 404 HTML)
ob_start(); 

session_start();
header('Content-Type: application/json');

// 1. Authorization Check (Host)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host') {
    // Clean the buffer before sending the error response
    ob_clean(); 
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authorization failed.']);
    exit;
}

// 🚨 Ensure this path is correct for your file structure
require_once __DIR__ . '/../config.php';

// 2. POST Data Collection
$project_id = $_POST['project_id'] ?? null;
$description = $_POST['description'] ?? null;
$required_roles = $_POST['required_roles_list'] ?? null; // Holds the skills/roles data
$team_size = $_POST['team_size'] ?? null;
$new_status = $_POST['new_status'] ?? null;
$action = $_POST['action'] ?? null; 
$host_id = $_SESSION['user_id'];

// 3. Validation & Action Handling
if (empty($project_id) || empty($action)) {
    // Clean the buffer before sending the error response
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing project ID or action.']);
    exit;
}

$project_id = (int)$project_id;
$message = 'No action performed.';

if ($action === 'update_details') {
    // Save Updates Logic
    if (empty($description) || empty($required_roles) || empty($team_size) || empty($new_status)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing fields for project details update.']);
        exit;
    }
    
    $team_size = (int)$team_size;
    
    // Using required_skills as per the database schema fix
    $query = "UPDATE projects SET 
                description = ?, 
                required_skills = ?, 
                team_size = ?, 
                status = ? 
              WHERE project_id = ? AND host_id = ?";
              
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("ssisii", $description, $required_roles, $team_size, $new_status, $project_id, $host_id);
        $stmt->execute();
        
        $message = ($stmt->affected_rows > 0) ? 'Project details updated successfully!' : 'Update request received, but no changes were applied (data was identical).';
        $stmt->close();
        
        // 🚨 CRITICAL FIX: Discard the 404 HTML and ensure only JSON is sent
        ob_clean(); 
        
        echo json_encode(['success' => true, 'message' => $message, 'reload_section' => true]);
        exit;
    } else {
        // Handle database preparation error
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error during update: ' . $conn->error]);
        exit;
    }

} elseif ($action === 'cancel_project') {
    // Cancel Project Logic (Setting status to 'Cancelled')
    
    $query = "UPDATE projects SET status = 'Cancelled' WHERE project_id = ? AND host_id = ? AND status IN ('Active', 'In Progress')";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("ii", $project_id, $host_id);
        $stmt->execute();
        $message = ($stmt->affected_rows > 0) ? 'Project successfully cancelled!' : 'Project could not be cancelled. Check project status/permissions.';
        $stmt->close();
        
        ob_clean(); 
        echo json_encode(['success' => true, 'message' => $message, 'reload_section' => true]);
        exit;
    } else {
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error during cancellation: ' . $conn->error]);
        exit;
    }
}

// Default response for invalid action
ob_clean();
echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);

// ⚠️ Ensure there is NO closing ?> tag after this line!