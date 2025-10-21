<?php
// FILE: include/host_sections/ajax_delete_project.php (പുതിയ ഫയൽ)

ob_start(); 

session_start();
header('Content-Type: application/json');

// 🚨 config.php-യിലേക്കുള്ള പാത കൃത്യമായിരിക്കണം

require_once __DIR__ . '/../config.php';
// ===============================================
// 1. സുരക്ഷാ പരിശോധന
// ===============================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean(); 
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authorization failed.']);
    exit;
}

$project_id = $_POST['project_id'] ?? null;
$host_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => 'Project ID missing or invalid.'];

if (empty($project_id) || !is_numeric($project_id)) {
    goto output;
}

// ===============================================
// 2. ഡിലീറ്റ് ലോജിക് (Foreign Key Support)
// ===============================================
try {
    // ⚠️ പ്രധാനപ്പെട്ടത്: പ്രോജക്റ്റ് ഡിലീറ്റ് ചെയ്യുന്നതിന് മുമ്പ് ബന്ധപ്പെട്ട എല്ലാ ഡാറ്റയും ഡിലീറ്റ് ചെയ്യണം.
    
    // a) Project Requests (Applications/Team Members) ഡിലീറ്റ് ചെയ്യുന്നു
    $conn->query("DELETE FROM project_requests WHERE project_id = " . (int)$project_id);
    
    // b) Tasks ഡിലീറ്റ് ചെയ്യുന്നു
    $conn->query("DELETE FROM tasks WHERE project_id = " . (int)$project_id);
    
    // c) പ്രധാന പ്രോജക്റ്റ് ഡിലീറ്റ് ചെയ്യുന്നു
    $query = "DELETE FROM projects WHERE project_id = ? AND host_id = ?";
    $stmt = $conn->prepare($query);

    if ($stmt) {
        $stmt->bind_param("ii", $project_id, $host_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Project (' . $project_id . ') and all related data have been permanently deleted.';
        } else {
            $response['message'] = 'Project not found, or access denied for ID: ' . $project_id;
        }
        $stmt->close();
    } else {
        $response['message'] = 'Database preparation error: ' . $conn->error;
    }

} catch (Exception $e) {
    $response['message'] = 'Server exception during deletion: ' . $e->getMessage();
}

// ===============================================
// 3. JSON റെസ്പോൺസ്
// ===============================================
output:
ob_clean(); // Clean the buffer to ensure clean JSON output
echo json_encode($response);
exit;

// ⚠️ No closing PHP tag