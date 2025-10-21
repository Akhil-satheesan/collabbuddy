<?php
require '../include/config.php'; 
header('Content-Type: application/json');

$term = trim($_GET['term'] ?? '');

if ($term === '') {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("SELECT role_name FROM roles_master WHERE role_name LIKE CONCAT('%', ?, '%') LIMIT 10");
$stmt->bind_param("s", $term);
$stmt->execute();
$result = $stmt->get_result();

$suggestions = [];
while ($row = $result->fetch_assoc()) {
    $suggestions[] = $row['role_name'];
}

echo json_encode($suggestions);
?>
