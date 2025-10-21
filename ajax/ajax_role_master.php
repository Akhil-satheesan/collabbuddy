<?php
require '../../config.php';
$term = $_GET['term'] ?? '';
$category = $_GET['category'] ?? '';

if (!$term) { echo json_encode([]); exit; }

$sql = "SELECT role_name FROM roles_master WHERE role_name LIKE ?";
$params = ["%$term%"];
if ($category) { $sql .= " AND category=?"; $params[] = $category; }

$stmt = $conn->prepare($sql);
$stmt->bind_param(str_repeat('s', count($params)), ...$params);
$stmt->execute();
$res = $stmt->get_result();

$roles = [];
while ($row = $res->fetch_assoc()) $roles[] = $row['role_name'];

echo json_encode($roles);
