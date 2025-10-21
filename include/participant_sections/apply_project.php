<?php
// FILE: C:\xampp\htdocs\collabuddy\ajax\apply_project.php

// 🛑 സുരക്ഷാ മുന്നറിയിപ്പ്: ഈ ഫയൽ AJAX വഴിയുള്ള POST അഭ്യർത്ഥനകൾക്ക് മാത്രമായി ഉപയോഗിക്കുന്നതാണ് ഉചിതം.
// GET വഴി ഫോം കാണിക്കാൻ \include\participant_sections\apply_project.php എന്ന പേജ് വേറെ ഉപയോഗിക്കുന്നതാണ് മികച്ച രീതി.
// എന്നാൽ, നിങ്ങളുടെ ആവശ്യപ്രകാരം, ഇവിടെത്തന്നെ GET ലോജിക്കും ചേർക്കുന്നു.

session_start();
require_once '../../include/config.php';

$conn = get_db_connection();

$is_form_submission = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_project']));

// --- 1. Common Authentication Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'participant') {
    $_SESSION['error'] = "Unauthorized access. Please log in as a Participant.";
    // 🔑 FIX 1: പൂർണ്ണമായ URL ഉപയോഗിച്ച് റീഡയറക്ട് ചെയ്യുന്നു.
    header("Location: http://localhost/collabuddy/index.php"); 
    exit;
}

// ----------------------------------------------------------------------------------
// --- POST REQUEST LOGIC (FORM SUBMISSION / HANDELLER) ---
// ----------------------------------------------------------------------------------
if ($is_form_submission) {

    $project_id = intval($_POST['project_id']);
    $participant_id = $_SESSION['user_id'];
    $cover_message = trim($_POST['cover_message'] ?? '');
    $availability = trim($_POST['availability'] ?? '');
    $is_reapply = ($_POST['reapply_status'] ?? 'false') === 'true'; 
    $resume_path = NULL; 
    $host_id = 0;

    // --- START: Resume Upload Logic ---
    if (!empty($_FILES['resume']['name']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        // ... (നിങ്ങളുടെ നിലവിലുള്ള Resume Upload ലോജിക്) ...
        $targetDir = __DIR__ . '/../../uploads/resumes/'; 
        
        if (!is_dir($targetDir)) {
             if (!mkdir($targetDir, 0777, true)) {
                 $_SESSION['error'] = "❌ Failed to create upload directory.";
                 header("Location: ../../participate_dashboard.php");
                 exit;
             }
        }
        $fileExtension = pathinfo($_FILES["resume"]["name"], PATHINFO_EXTENSION);
        $resume_name = $project_id . '_' . $participant_id . '_' . time() . '.' . $fileExtension;
        $targetFile = $targetDir . $resume_name;

        if (move_uploaded_file($_FILES["resume"]["tmp_name"], $targetFile)) {
             $resume_path = "uploads/resumes/" . $resume_name; 
        } else {
             $_SESSION['error'] = "❌ Error uploading resume file.";
             header("Location: ../../participate_dashboard.php");
             exit;
        }
    }
    // --- END: Resume Upload Logic ---
    
    // START DATABASE TRANSACTION
    $conn->begin_transaction();

    try {
        // 1. Find Project Host ID and check for project existence
        $hostSql = "SELECT host_id FROM projects WHERE project_id = ?";
        $hostStmt = $conn->prepare($hostSql);
        if (!$hostStmt) throw new Exception("Prepare Host ID failed: " . $conn->error);
        $hostStmt->bind_param("i", $project_id);
        $hostStmt->execute();
        $hostResult = $hostStmt->get_result();
        $hostData = $hostResult->fetch_assoc();
        $hostStmt->close();

        if (!$hostData) {
            throw new Exception("Project not found.");
        }
        $host_id = $hostData['host_id'];
        if ($host_id === 0) throw new Exception("Project host ID is invalid (0)."); 

        // 2. Check for an ACTIVE application in project_requests 
        $checkReqActive = $conn->prepare("
             SELECT request_id FROM project_requests 
             WHERE project_id=? AND participant_id=? AND status IN ('pending', 'accepted')
        ");
        if (!$checkReqActive) throw new Exception("Prepare active check failed: " . $conn->error);
        $checkReqActive->bind_param("ii", $project_id, $participant_id);
        $checkReqActive->execute();
        $checkReqActive->store_result();

        if ($checkReqActive->num_rows > 0 && !$is_reapply) {
             $checkReqActive->close();
             throw new Exception("You already have an active application (Pending/Accepted) for this project.");
        }
        $checkReqActive->close();
        
        // 3. Insert/Update `applications` table
        $check_app = $conn->prepare("SELECT application_id FROM applications WHERE project_id=? AND participant_id=?");
        if (!$check_app) throw new Exception("Prepare application check failed: " . $conn->error);
        $check_app->bind_param("ii", $project_id, $participant_id);
        $check_app->execute();
        $check_app->store_result();

        $action_type = "";
        $app_status = 'Pending'; // Capitalized status for 'applications' table
        
        if ($check_app->num_rows > 0) {
            // Application exists, UPDATE it.
            $sql_app_update = "UPDATE applications SET cover_message=?, resume_path=?, availability=?, status=?, applied_at=NOW() WHERE project_id=? AND participant_id=?";
            $stmt = $conn->prepare($sql_app_update);
            if (!$stmt) throw new Exception("Prepare UPDATE applications failed: " . $conn->error);
            
            $stmt->bind_param("ssssii", $cover_message, $resume_path, $availability, $app_status, $project_id, $participant_id);
            $action_type = "UPDATE";
        } else {
            // Application does NOT exist, INSERT a new one.
            $sql_app_insert = "INSERT INTO applications 
                 (project_id, participant_id, cover_message, resume_path, availability, status) 
                 VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql_app_insert);
            if (!$stmt) throw new Exception("Prepare INSERT applications failed: " . $conn->error);
            
            $stmt->bind_param("iissss", $project_id, $participant_id, $cover_message, $resume_path, $availability, $app_status);
            $action_type = "INSERT";
        }
        
        if (!$stmt->execute()) {
            $db_error = $stmt->error;
            error_log("DB ERROR on applications ($action_type): " . $db_error . " | ProID=$project_id, PartID=$participant_id");
            throw new Exception("Error saving application data to 'applications' table. DB Message: " . $db_error);
        }
        $stmt->close();
        $check_app->close();
        
        // 4. Update/Insert into `project_requests`
        $check_req = $conn->prepare("SELECT request_id FROM project_requests WHERE project_id=? AND participant_id=?");
        if (!$check_req) throw new Exception("Prepare request check failed: " . $conn->error);
        $check_req->bind_param("ii", $project_id, $participant_id);
        $check_req->execute();
        $check_req->store_result();
        
        $req_status = 'pending'; // Lowercase status for 'project_requests'

        if ($check_req->num_rows > 0) {
            // Request exists, UPDATE its status
            $updateReq = $conn->prepare("UPDATE project_requests SET host_id = ?, status = ?, created_at=NOW() WHERE project_id = ? AND participant_id = ?");
            if (!$updateReq) throw new Exception("Prepare UPDATE request failed: " . $conn->error);
            $updateReq->bind_param("isii", $host_id, $req_status, $project_id, $participant_id); 
            $updateReq->execute();
            $updateReq->close();
        } else {
            // Request does NOT exist, INSERT a new 'pending' request
            $insertReq = $conn->prepare("INSERT INTO project_requests (project_id, host_id, participant_id, status, created_at) VALUES (?, ?, ?, ?, NOW())");
            if (!$insertReq) throw new Exception("Prepare INSERT request failed: " . $conn->error);
            $insertReq->bind_param("iiis", $project_id, $host_id, $participant_id, $req_status); 
            $insertReq->execute();
            $insertReq->close();
        }
        $check_req->close();
        
        // 5. Chat Room Logic (1:1 chat between Host and Participant)
        $chat_status = 'pending';
        $checkChat = $conn->prepare("SELECT room_id FROM chat_rooms WHERE project_id = ? AND host_id = ? AND participant_id = ?");
        if (!$checkChat) throw new Exception("Prepare chat check failed: " . $conn->error);
        $checkChat->bind_param("iii", $project_id, $host_id, $participant_id);
        $checkChat->execute();
        $chatResult = $checkChat->get_result();

        if ($chatResult->num_rows == 0) {
            $chatSql = "INSERT INTO chat_rooms (project_id, host_id, participant_id, status, initial_message) VALUES (?, ?, ?, ?, ?)";
            $chatStmt = $conn->prepare($chatSql);
            if (!$chatStmt) throw new Exception("Prepare chat create failed: " . $conn->error);
            $initial_msg = "Application submitted for project ID: " . $project_id;
            $chatStmt->bind_param("iiiss", $project_id, $host_id, $participant_id, $chat_status, $initial_msg);
            
            if (!$chatStmt->execute()) {
                throw new Exception("Failed to create chat room.");
            }
            $chatStmt->close();
        } else {
            // Chat room exists, just ensure status is reset to pending (if reapply)
            $chatData = $chatResult->fetch_assoc();
            $chat_room_id = $chatData['room_id'];
            
            $updateChat = $conn->prepare("UPDATE chat_rooms SET status = ? WHERE room_id = ?");
            if (!$updateChat) throw new Exception("Prepare chat update failed: " . $conn->error);
            $updateChat->bind_param("si", $chat_status, $chat_room_id);
            $updateChat->execute();
            $updateChat->close();
        }
        $checkChat->close();

        // COMMIT TRANSACTION
        $conn->commit();
        $_SESSION['success'] = "✅ Application submitted! You can now chat with the Host about your request.";
        
    } catch (Exception $e) {
        // ROLLBACK TRANSACTION on error
        $conn->rollback();
        // Delete uploaded resume if transaction fails
        if ($resume_path && file_exists(__DIR__ . '/../../' . $resume_path)) {
             unlink(__DIR__ . '/../../' . $resume_path);
        }
        error_log("APPLICATION SUBMISSION FAILED: " . $e->getMessage());
        $_SESSION['error'] = "❌ Error submitting application: " . $e->getMessage();
    }

    // 🔑 FIX 2: ഫോം സബ്മിഷനു ശേഷം ഡാഷ്ബോർഡിലേക്ക് തിരിച്ചുവിടുന്നു.
    header("Location: http://localhost/collabuddy/participate_dashboard.php");
    exit;

// ----------------------------------------------------------------------------------
// --- GET REQUEST LOGIC (FORM DISPLAY) ---
// ----------------------------------------------------------------------------------
} else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['project_id'])) {
    
    // my_applications.php-യിൽ നിന്ന് വരുമ്പോൾ ഈ ഭാഗം പ്രവർത്തിക്കും.
    $project_id = intval($_GET['project_id']);
    $is_reapply = isset($_GET['reapply']);
    $participant_id = $_SESSION['user_id'];
    
    // ⚠️ ഈ ഭാഗം നിങ്ങളുടെ ഡാഷ്‌ബോർഡിൻ്റെ ഭാഗമായി ഒരു ഫോം റെൻഡർ ചെയ്യണം.
    // ഇത് ഒരു മിനിമൽ HTML ടെംപ്ലേറ്റ് മാത്രമാണ്. നിങ്ങളുടെ യഥാർത്ഥ ഫോം കോഡ് ഇവിടെ ചേർക്കുക.
    
    // ഒരു പ്രൊജക്റ്റ് ടൈറ്റിൽ എടുക്കുന്നു
    $projTitle = "Unknown Project";
    $titleStmt = $conn->prepare("SELECT title FROM projects WHERE project_id = ?");
    if ($titleStmt) {
        $titleStmt->bind_param("i", $project_id);
        $titleStmt->execute();
        $titleResult = $titleStmt->get_result();
        if ($titleData = $titleResult->fetch_assoc()) {
            $projTitle = htmlspecialchars($titleData['title']);
        }
        $titleStmt->close();
    }

    echo "
    <div class='p-6 bg-white rounded-lg shadow-xl'>
        <h2 class='text-3xl font-extrabold text-indigo-600 mb-2'>Apply for: {$projTitle}</h2>
        <p class='text-sm text-gray-500 mb-6'>" . ($is_reapply ? "<span class='font-semibold text-orange-600'>🔄 Reapply Mode:</span> Your previous application was Rejected/Withdrawn." : "Submit a new application.") . "</p>
        
        <form method='POST' action='http://localhost/collabuddy/ajax/apply_project.php' enctype='multipart/form-data' class='space-y-6'>
            <input type='hidden' name='apply_project' value='1'>
            <input type='hidden' name='project_id' value='{$project_id}'>
            <input type='hidden' name='reapply_status' value='" . ($is_reapply ? 'true' : 'false') . "'>
            
            <div>
                <label for='cover_message' class='block text-sm font-medium text-gray-700'>Cover Message / Why You're a Fit</label>
                <textarea id='cover_message' name='cover_message' rows='4' required class='mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-3 focus:ring-indigo-500 focus:border-indigo-500'></textarea>
            </div>
            
            <div>
                <label for='resume' class='block text-sm font-medium text-gray-700'>Upload Resume (Optional)</label>
                <input type='file' id='resume' name='resume' accept='.pdf,.doc,.docx' class='mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100'>
            </div>
            
            <div>
                <label for='availability' class='block text-sm font-medium text-gray-700'>Your Availability (e.g., 10 hrs/week)</label>
                <input type='text' id='availability' name='availability' required class='mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-3 focus:ring-indigo-500 focus:border-indigo-500'>
            </div>

            <button type='submit' class='w-full py-3 px-4 border border-transparent rounded-md shadow-sm text-lg font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150'>
                🚀 Submit Application
            </button>
        </form>
    </div>
    ";

} else {
    // POST request അല്ലാത്തതും, project_id ഇല്ലാത്തതുമായ മറ്റ് അഭ്യർത്ഥനകൾ ഡാഷ്‌ബോർഡിലേക്ക് തിരിച്ചുവിടുന്നു.
    header("Location: http://localhost/collabuddy/participate_dashboard.php");
    exit;
}
?>