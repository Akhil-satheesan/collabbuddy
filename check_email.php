<?php
require 'include/config.php';

$email = $_GET['email'] ?? '';
$response = ['exists' => false];

if ($email) {
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $response['exists'] = true;
    }

    $stmt->close();
}

header('Content-Type: application/json');
echo json_encode($response);
exit;       
?>