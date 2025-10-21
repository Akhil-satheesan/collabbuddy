<?php
session_start();
require __DIR__ . '/../config.php'; 
header('Content-Type: application/json');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'participant' || !isset($_POST['status'])){
    echo json_encode(['html' => '<div class="text-center p-10 text-xl text-red-600">Error: Authentication Failed or Missing Filter Data.</div>', 'count' => 0]);
    exit;
}

$participant_id = $_SESSION['user_id'];
$status_filter = $_POST['status'];
$allowed_statuses = ['All', 'Pending', 'Accepted', 'Rejected', 'Withdrawn'];
if (!in_array($status_filter, $allowed_statuses)) $status_filter = 'All';

$status_subquery = "(SELECT status FROM applications a WHERE a.project_id = p.project_id AND a.participant_id = ? LIMIT 1)";

$sql = "
    SELECT 
        p.*, 
        b.created_at AS bookmarked_at, 
        u.name AS host_name,
        {$status_subquery} AS application_status
    FROM projects p
    JOIN bookmarks b ON p.project_id = b.project_id
    JOIN users u ON p.host_id = u.user_id
    WHERE b.participant_id=?
";

$param_types = "ii";
$params = [$participant_id, $participant_id];

if ($status_filter !== 'All') {
    $sql .= " AND ({$status_subquery}) = ? ";
    $param_types .= "is"; 
    $params[] = $participant_id;
    $params[] = $status_filter;
}

$sql .= " ORDER BY b.created_at DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['html' => '<div class="text-center p-10 text-xl text-red-600">SQL Error: '.$conn->error.'</div>', 'count'=>0]);
    exit;
}

$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$html = '';

if($result->num_rows > 0) {
    $html .= '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">';
    while($row = $result->fetch_assoc()){
        $projectId = $row['project_id'];
        $projectTitle = htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'); 
        $application_status = $row['application_status'];
        $has_applied = !empty($application_status);
        $is_pending = ($application_status === 'Pending');

        $borderColor = 'border-l-indigo-500';
        if ($is_pending) $borderColor = 'border-l-yellow-500';
        elseif ($application_status === 'Accepted') $borderColor = 'border-l-green-600';

        $reapply_reason = ($application_status === 'Rejected') ? 'Rejected' : 'Withdrawn';
        
        $html .= '
        <div class="bg-white shadow-2xl hover:shadow-3xl transition-all duration-500 rounded-xl border border-gray-100 project-card overflow-hidden cursor-pointer '.$borderColor.' border-l-4" data-project-id="'.$projectId.'">
            <div class="flex items-start p-5 border-b border-gray-100">
                <div class="w-12 h-12 bg-indigo-600 rounded-full flex items-center justify-center text-white font-bold text-xl flex-shrink-0 shadow-lg mr-4">'.strtoupper(substr($row['title'],0,2)).'</div>
                <div class="flex-1 overflow-hidden">
                    <h3 class="text-xl font-bold text-gray-900 truncate mb-1">'.htmlspecialchars($row['title']).'</h3>
                    <p class="text-sm text-gray-500">'.htmlspecialchars($row['project_category'].' • '.$row['host_name']).'</p>
                </div>
                <button class="text-red-500/70 hover:text-red-600 transition-colors ml-4 bookmark-btn" data-project="'.$projectId.'" title="Remove Bookmark">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <p class="text-gray-700 text-base leading-snug">'.htmlspecialchars(substr($row['description'],0,120)).'...</p>
                <div class="flex justify-between items-center pt-2">';
                    if (!$has_applied){
                        $html .= '<button onclick="openApplyModal(\''.$projectId.'\', \''.$projectTitle.'\')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg font-semibold transition shadow-md hover:shadow-xl transform hover:scale-105">Apply Now</button>';
                    } elseif ($is_pending){
                        $html .= '<button class="bg-gray-400 text-white px-5 py-2 rounded-lg font-medium cursor-default opacity-80" disabled>Applied (Pending)</button>';
                    } elseif ($application_status === 'Rejected' || $application_status === 'Withdrawn'){
                        $html .= '<button onclick="openApplyModal(\''.$projectId.'\', \''.$projectTitle.'\', true)" class="bg-yellow-500 hover:bg-yellow-600 text-gray-900 px-5 py-2 rounded-lg font-bold transition shadow-md hover:shadow-xl transform hover:scale-105">Reapply ('.$reapply_reason.')</button>';
                    } else {
                        $html .= '<a href="my_applications.php?project_id='.$projectId.'" class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg font-semibold transition shadow-md hover:shadow-xl transform hover:scale-105">View Reply ('.htmlspecialchars($application_status).')</a>';
                    }
        $html .= '<span class="text-gray-400 text-xs italic">Posted: '.date("M d, Y", strtotime($row['created_at'])).'</span>';
        $html .= '</div></div></div>';
    }
    $html .= '</div>';
} else {
    $html .= '<div class="text-center p-12 text-gray-500 text-lg">No projects found for this filter.</div>';
}

echo json_encode(['html'=>$html, 'count'=>$result->num_rows]);

$stmt->close();
$conn->close();
