<?php
session_start();
header('Content-Type: application/json');

// Fake login for testing
$_SESSION['user_id'] = 1;        // Assume participant with ID 1 exists
$_SESSION['role'] = 'participant';

echo json_encode(['success'=>true,'action'=>'test']);
exit;
