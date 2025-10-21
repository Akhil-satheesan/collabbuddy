<?php
// FILE: ajax/ajax_task_handler.php

// config.php ഫയലിന്റെ പാത്ത് ശരിയാണെന്ന് ഉറപ്പാക്കുക
require '../include/config.php'; 

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid action or request.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'assign_task') {
        // Sanitize and validate inputs
        $project_id = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);
        $assigned_to_user_id = filter_input(INPUT_POST, 'assigned_to_user_id', FILTER_VALIDATE_INT);
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $due_date = trim($_POST['due_date']);
        $priority = trim($_POST['priority']);
        
        // Ensure required fields are present and valid
        if ($project_id && $assigned_to_user_id && !empty($title) && !empty($due_date)) {
            
            // Assume initial status is 'To Do' based on standard task flow
            $status = 'To Do'; 
            
            // SQL to insert the new task
            $sql = "INSERT INTO tasks (project_id, assigned_to_user_id, title, description, due_date, status, priority) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            
            if ($stmt === false) {
                $response['message'] = "Database Error: Could not prepare statement for task insertion.";
            } else {
                // 'iississ' -> integer, integer, string, string, integer, string, string
                $stmt->bind_param("iississ", $project_id, $assigned_to_user_id, $title, $description, $due_date, $status, $priority);
                
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = "Task '{$title}' assigned successfully!";
                } else {
                    $response['message'] = "Task assignment failed: " . $stmt->error;
                }
                $stmt->close();
            }
        } else {
            $response['message'] = "Missing or invalid required fields (Project ID, Assigned User, Title, or Due Date).";
        }
    }
}

$conn->close();
echo json_encode($response);
exit;
?>