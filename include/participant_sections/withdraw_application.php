<?php
// FILE: ajax/withdraw_application.php (or similar)

session_start();
// Adjust path to config.php if necessary
require __DIR__ . '/../config.php'; 

// Establish connection and assign to $conn (Assuming get_db_connection() is defined in config.php)
$conn = get_db_connection(); 

header("Content-Type: application/json");

// Check login and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'participant') {
    echo json_encode(["success"=>false,"message"=>"Unauthorized access or role."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $application_id = intval($_POST['application_id'] ?? 0);
    $participant_id = $_SESSION['user_id'];

    if (!$application_id) {
        echo json_encode(["success"=>false,"message"=>"Invalid application ID"]);
        exit;
    }

    // START TRANSACTION to ensure atomicity
    $conn->begin_transaction();

    try {
        // 1. Verify application ownership & fetch project_id
        $stmt = $conn->prepare("SELECT project_id FROM applications WHERE application_id=? AND participant_id=?");
        if (!$stmt) throw new Exception("Prepare verify failed: " . $conn->error);
        
        $stmt->bind_param("ii", $application_id, $participant_id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            $stmt->close();
            throw new Exception("Application not found or access denied.");
        }

        $row = $res->fetch_assoc();
        $project_id = $row['project_id'];
        $stmt->close();

        // 2. Update application status in applications table (Using 'Withdrawn' for capitalized Enum)
        $withdraw_status = 'Withdrawn'; 
        $stmt = $conn->prepare("UPDATE applications SET status=? WHERE application_id=? AND participant_id=?");
        if (!$stmt) throw new Exception("Prepare application update failed: " . $conn->error);
        
        $stmt->bind_param("sii", $withdraw_status, $application_id, $participant_id);
        if (!$stmt->execute()) throw new Exception("Error updating application status.");
        $stmt->close();

        // 3. Delete pending request from project_requests (Using 'pending' for small-case Enum)
        $req_pending_status = 'pending'; 
        $stmt = $conn->prepare("DELETE FROM project_requests WHERE project_id=? AND participant_id=? AND status=?");
        if (!$stmt) throw new Exception("Prepare request delete failed: " . $conn->error);
        
        $stmt->bind_param("iis", $project_id, $participant_id, $req_pending_status);
        if (!$stmt->execute()) throw new Exception("Error deleting project request.");
        $stmt->close();
        
        // 4. Update Chat Room Status (Optional but recommended)
        $chat_closed_status = 'closed';
        $stmt = $conn->prepare("UPDATE chat_rooms SET status=? WHERE project_id=? AND participant_id=?");
        if (!$stmt) throw new Exception("Prepare chat update failed: " . $conn->error);
        
        $stmt->bind_param("sii", $chat_closed_status, $project_id, $participant_id);
        $stmt->execute(); 
        $stmt->close();


        // COMMIT TRANSACTION if all steps succeeded
        $conn->commit();
        echo json_encode(["success"=>true,"message"=>"✅ Application withdrawn successfully."]);
        exit;

    } catch (Exception $e) {
        // ROLLBACK TRANSACTION on error
        $conn->rollback();
        error_log("APPLICATION WITHDRAWAL FAILED: " . $e->getMessage());
        
        $display_msg = "System Error: Could not withdraw the application. Details logged.";
        
        echo json_encode(["success"=>false,"message"=>$display_msg]);
        exit;
    }
}
$conn->close();
?>