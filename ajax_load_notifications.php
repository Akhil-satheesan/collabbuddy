<?php
// FILE: include/host_sections/ajax_delete_project.php (Finalized Notification Logic)

ob_start(); 
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php'; 

// [ ... Security and Initialization remains the same ... ]

$project_id = $_POST['project_id'] ?? null;
$host_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => 'Project ID missing or invalid.'];

if (empty($project_id) || !is_numeric($project_id)) {
    goto output;
}

$conn->begin_transaction(); 

try {
    // --- STEP 1: READ data BEFORE CASCADING DELETE ---

    // a) Get Project Title AND verify host ownership
    $stmt_title = $conn->prepare("SELECT title FROM projects WHERE project_id = ? AND host_id = ?");
    $stmt_title->bind_param("ii", $project_id, $host_id);
    $stmt_title->execute();
    $project_data = $stmt_title->get_result()->fetch_assoc();
    $stmt_title->close();
    
    // Check if project exists and belongs to the host (Critical check for deletion success)
    if (!$project_data) {
        $conn->rollback();
        $response['message'] = 'Deletion Failed: Project not found or access denied for ID: ' . $project_id;
        goto output;
    }
    $project_title = htmlspecialchars($project_data['title']);

    // b) Get all participants who applied (from project_requests) BEFORE deletion
    $participant_ids = [];
    // Ensure you are selecting from the table that holds applications/requests!
    $stmt_participants = $conn->prepare("SELECT participant_id FROM project_requests WHERE project_id = ?"); 
    $stmt_participants->bind_param("i", $project_id);
    $stmt_participants->execute();
    $result_participants = $stmt_participants->get_result();
    
    while ($row = $result_participants->fetch_assoc()) {
        $participant_ids[] = $row['participant_id'];
    }
    $stmt_participants->close();
    
    // --- STEP 2: DELETE the main Project (This triggers CASCADE) ---

    // The host_id parameter here ensures the transaction will only proceed if ownership is confirmed.
    $query = "DELETE FROM projects WHERE project_id = ? AND host_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $project_id, $host_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        // --- STEP 3: GENERATE NOTIFICATIONS (Only if deletion was successful) ---
        
        $notification_count = 0;
        if (!empty($participant_ids)) {
            $notification_message = "The project '{$project_title}' you applied to has been deleted by the host.";
            $notification_type = 'info'; 

            $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, message, type, is_read) VALUES (?, ?, ?, 0)");
            
            foreach ($participant_ids as $p_id) {
                // Insert a notification for each participant
                $stmt_notif->bind_param("iss", $p_id, $notification_message, $notification_type);
                if ($stmt_notif->execute()) {
                    $notification_count++;
                }
            }
            $stmt_notif->close();
        }

        // Final commit if project was deleted and notifications were handled
        $conn->commit();
        $response['success'] = true;
        $response['message'] = 'Project (' . $project_id . ') deleted. Notifications sent to ' . $notification_count . ' participant(s).'; // Added debugging count
    } else {
        // Rollback if the main delete failed
        $conn->rollback(); 
        $response['message'] = 'Deletion Failed: Project not found or access denied for ID: ' . $project_id;
    }
    $stmt->close();

} catch (Exception $e) {
    $conn->rollback(); 
    $response['message'] = 'Server exception during deletion: ' . $e->getMessage();
}

// ===============================================
// 3. JSON Response (Outputs the success/fail message, including notification count)
// ===============================================
output:
ob_clean();
echo json_encode($response);
exit;