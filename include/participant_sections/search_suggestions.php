<?php
session_start();
require '../config.php'; // make sure this path is correct

$term = trim($_GET['term'] ?? '');

if (!$term) {
    echo json_encode([]);
    exit;
}

// Prepare query: search languages, project titles, and required skills
$stmt = $conn->prepare("
    SELECT language_name AS name, 'language' AS type 
    FROM languages 
    WHERE language_name LIKE ? 
    LIMIT 5

    UNION

    SELECT title AS name, 'project' AS type 
    FROM projects 
    WHERE title LIKE ? 
    LIMIT 5

    UNION

    SELECT required_skills AS name, 'skill' AS type 
    FROM projects 
    WHERE required_skills LIKE ? 
    LIMIT 5
");

$searchTerm = "%$term%";
$stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$suggestions = [];
while($row = $result->fetch_assoc()){
    $suggestions[] = $row;
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($suggestions);
