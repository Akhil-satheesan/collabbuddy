<?php
// FILE: include/host_sections/host_update_profile.php

session_start();
require_once '../config.php'; 

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host') {
    echo json_encode(['success' => false, 'message' => 'Access denied. Please log in as a Host.']);
    exit;
}

$userId = $_SESSION['user_id'];

// 2. Data Validation and Sanitization
// NOTE: Ensure your form fields use the exact 'name' attributes as below.
$host_type = trim($_POST['host_type'] ?? '');
$company_name = trim($_POST['company_name'] ?? '');
$product_name = trim($_POST['product_name'] ?? '');
$website_url = trim($_POST['website_url'] ?? '');
$about_host = trim($_POST['about_host'] ?? '');


// 💥 FIX: If your host details are optional for some user flows,
// remove the stringent validation below. If they are required, check 
// if the POST data is actually empty when it shouldn't be.

/* // ❌ ഇത് ഒഴിവാക്കുകയോ അല്ലെങ്കിൽ ആവശ്യമുണ്ടെങ്കിൽ മാത്രം ഉപയോഗിക്കുകയോ ചെയ്യുക
if (empty($host_type) || empty($company_name) || empty($about_host)) {
    echo json_encode(['success' => false, 'message' => 'Host Type, Company Name, and About Me are required.']);
    exit;
}
*/


try {
    // 3. Prepare SQL Statement for Update
    $sql = "
        UPDATE hosts 
        SET host_type = ?, 
            company_name = ?, 
            product_name = ?, 
            website_url = ?, 
            about_host = ?
        WHERE host_id = ?
    ";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("Host profile update failed (SQL Prepare): " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error: Could not prepare update statement.']);
        exit;
    }
    
    // 4. Bind parameters and Execute
    $stmt->bind_param(
        "sssssi", 
        $host_type, 
        $company_name, 
        $product_name, 
        $website_url, 
        $about_host, 
        $userId
    );
    
    if ($stmt->execute()) {
        
        // 5. Successful Response
        echo json_encode(['success' => true, 'message' => 'Host Profile details updated successfully!']);
        
    } else {
        error_log("Host profile update failed (Execution): " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Database update failed. Please try again.']);
    }
    
    $stmt->close();

} catch (Exception $e) {
    error_log("Host profile update exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected server error occurred.']);
}

exit;
?>