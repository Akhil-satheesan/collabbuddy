<?php

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    exit; 
}

$filter_status = $_GET['filter_status'] ?? 'all'; 
$filter_status = strtolower($filter_status); 

$where_clause = "WHERE host_id = ?";
$params = [$_SESSION['user_id']];
$param_types = "i"; 

if ($filter_status !== 'all') {
    $db_status = '';
    
    $clean_filter = str_replace('+', ' ', $filter_status);
    
    if ($clean_filter === 'in progress') {
        $db_status = 'In Progress'; 
    } else {
        $db_status = ucwords($clean_filter); 
    }
    
    $where_clause .= " AND status = ?";
    $params[] = $db_status;
    $param_types .= "s"; 
}

$query = "
    SELECT *,
            FIELD(status, 'Active', 'In Progress', 'Completed', 'Cancelled') AS status_order
    FROM projects 
    {$where_clause} 
    ORDER BY status_order, created_at DESC
";

$stmt = $conn->prepare($query);

if ($stmt === false) {
    die('<p class="text-red-600 p-6">Prepare failed: ' . htmlspecialchars($conn->error) . '</p>');
}

if (!empty($params)) {
    $bind_names[] = $param_types;
    for ($i=0; $i<count($params); $i++) {
        $bind_names[] = &$params[$i];
    }
    call_user_func_array(array($stmt, 'bind_param'), $bind_names);
}

$stmt->execute();
$result = $stmt->get_result();

$statuses = ['all', 'Active', 'In Progress', 'Completed', 'Cancelled'];
?>

<div class="p-6 md:p-10 max-w-7xl mx-auto bg-gray-50">

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
        <h2 class="text-3xl font-extrabold text-gray-900 mb-4 md:mb-0">My Projects Dashboard</h2>
        
        <div class="flex flex-wrap space-x-1 p-1 bg-gray-100 rounded-lg text-sm">
            <?php 
            foreach ($statuses as $status): 
                $current_status_lower = strtolower($status);
                
                $isActive = ($filter_status === $current_status_lower);
                
                $class = $isActive 
                            ? 'bg-indigo-600 text-white shadow-md' 
                            : 'text-gray-700 hover:bg-gray-200';
                $label = ($status === 'all') ? 'All Projects' : ucfirst($status);
            ?>
                <a href="#" 
                    class="ajax-project-filter px-3 py-1.5 rounded-md font-medium transition <?= $class ?>"
                    data-filter-value="<?= urlencode($current_status_lower) ?>">
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    
    <hr class="my-4">

    <div id="project-list" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php
    if ($result && $result->num_rows > 0):
        while ($row = $result->fetch_assoc()):
            $project_id = $row['project_id'];
            $project_status = $row['status'];
            
            $members = 0;
            if ($stmt_members = $conn->prepare("SELECT COUNT(*) FROM project_requests WHERE project_id=? AND status='accepted'")) {
                $stmt_members->bind_param("i", $project_id);
                $stmt_members->execute();
                $stmt_members->bind_result($members);
                $stmt_members->fetch();
                $stmt_members->close();
            }
            
            $applications = 0;
            if ($stmt_app = $conn->prepare("SELECT COUNT(*) FROM project_requests WHERE project_id=? AND status='pending'")) {
                $stmt_app->bind_param("i", $project_id);
                $stmt_app->execute();
                $stmt_app->bind_result($applications);
                $stmt_app->fetch();
                $stmt_app->close();
            }

            $totalTasks = 0; $completedTasks = 0; $progress = 0;
            if ($stmt_tasks = $conn->prepare("SELECT COUNT(*), SUM(CASE WHEN status='Completed' THEN 1 ELSE 0 END) FROM tasks WHERE project_id=?")) {
                $stmt_tasks->bind_param("i", $project_id);
                $stmt_tasks->execute();
                $stmt_tasks->bind_result($totalTasks, $completedTasks);
                $stmt_tasks->fetch();
                $stmt_tasks->close();

                if ($totalTasks > 0) {
                    $progress = round(($completedTasks / $totalTasks) * 100);
                }
            }

            $stat1_label = ""; $stat1_value = "";
            $stat2_label = ""; $stat2_value = "";
            $stat3_label = ""; $stat3_value = "";
            $actions = "";
            $progressColorClass = "bg-gray-400"; 
            
            $required_size = (int)filter_var($row['team_size'], FILTER_SANITIZE_NUMBER_INT);
            $team_size_display = $members . '/' . ($required_size > 0 ? $required_size : 'N/A');

            switch (strtolower($project_status)) {
                
                case 'active': 
                    $stat1_label = "Team Size"; $stat1_value = $team_size_display;
                    $stat2_label = "Applications"; $stat2_value = $applications;
                    $stat3_label = "Posted On"; $stat3_value = date('M d, Y', strtotime($row['created_at']));
                    $progressColorClass = "bg-green-500";
                    $actions = '
                        <a href="#" 
                           data-project-id="' . $project_id . '" 
                           class="edit-project-btn bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition">
                            Manage/Edit Details
                        </a>
                        <a href="#" data-project-id="' . $project_id . '" class="text-red-600 hover:text-red-800 font-medium ml-4" onclick="event.preventDefault(); confirmDelete(' . $project_id . ');">Delete</a>
                    ';
                    break;
                    
                case 'in progress': 
                    $stat1_label = "Team Size"; $stat1_value = $team_size_display;
                    $stat2_label = "Tasks"; $stat2_value = $completedTasks . ' / ' . $totalTasks;
                    $stat3_label = "Progress"; $stat3_value = $progress . '%';
                    $progressColorClass = "bg-indigo-600";
                    
                    $daysLeft = 'N/A'; $daysLeftClass = "text-gray-900";
                    if (!empty($row['end_date'])) {
                        $end_date_time = new DateTime($row['end_date']);
                        $current_date_time = new DateTime();
                        if ($end_date_time < $current_date_time && $progress < 100) {
                            $daysLeft = 'OVERDUE';
                            $daysLeftClass = "text-red-600 font-bold";
                        } else {
                            $interval = $current_date_time->diff($end_date_time);
                            $daysLeft = $interval->days . ' Days Left';
                        }
                    } else {
                        $daysLeft = 'Duration: ' . htmlspecialchars($row['duration']);
                        $daysLeftClass = "text-gray-700";
                    }
                    
                  // my_projects.php (inside case 'in progress':)

// പഴയ ലിങ്ക്: <a href="manage_tasks.php?id=' . $project_id . '" class="bg-indigo-600 ...">Manage Tasks</a>
// പുതിയ ലിങ്ക്:
$actions = '
<a href="#" data-project-id="' . $project_id . '" class="edit-project-btn text-indigo-600 hover:text-indigo-800 font-medium">View Details</a>

<a href="#" data-project-id="' . $project_id . '" class="load-manage-tasks-btn bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition ml-4">
    Manage Tasks
</a>

<span class="' . $daysLeftClass . ' font-semibold ml-4"> ' . $daysLeft . '</span>
';
                    break;
                    
                case 'completed': 
                case 'cancelled': 
                    $stat1_label = "Start Date"; $stat1_value = date('M d, Y', strtotime($row['created_at']));
                    $stat2_label = "End Date"; $stat2_value = !empty($row['end_date']) ? date('M d, Y', strtotime($row['end_date'])) : 'N/A';
                    $stat3_label = "Final Status"; $stat3_value = $project_status;
                    $progressColorClass = (strtolower($project_status) == 'completed') ? "bg-blue-500" : "bg-red-500";
                    
                    $actions = '
                        <a href="#" data-project-id="' . $project_id . '" class="edit-project-btn text-gray-600 hover:text-gray-800 font-medium">View Details</a>
                    ';
                    break;
                    
                default:
                    $actions = '<span class="text-gray-500">No Actions</span>';
            }
            
            $statusClass = match(strtolower($project_status)) {
                'active'        => 'bg-green-100 text-green-800',
                'in progress'   => 'bg-blue-100 text-blue-800',
                'completed'     => 'bg-gray-200 text-gray-700',
                'cancelled'     => 'bg-red-100 text-red-800',
                default         => 'bg-gray-100 text-gray-800'
            };
    ?>
        <div class="group border border-gray-200 rounded-xl p-6 bg-white shadow-sm hover:shadow-lg transition-all transform hover:-translate-y-1">
            
            <div class="flex justify-between items-start mb-3">
                <h3 class="text-xl font-bold text-gray-900 truncate"><?= htmlspecialchars($row['title']) ?></h3>
                <span class="text-sm font-semibold px-3 py-1 rounded-full <?= $statusClass ?>"><?= $project_status ?></span>
            </div>
            <p class="text-sm text-gray-600 mb-4 line-clamp-2"><?= htmlspecialchars($row['description']) ?></p>

            <div class="grid grid-cols-3 gap-4 mb-5 border-t border-b border-gray-100 py-3">
                <div class="text-center">
                    <p class="text-base font-bold text-gray-900"><?= $stat1_value ?></p>
                    <p class="text-xs text-gray-600"><?= $stat1_label ?></p>
                </div>
                <div class="text-center">
                    <p class="text-base font-bold text-gray-900"><?= $stat2_value ?></p>
                    <p class="text-xs text-gray-600"><?= $stat2_label ?></p>
                </div>
                <div class="text-center">
                    <p class="text-base font-bold text-gray-900"><?= $stat3_value ?></p>
                    <p class="text-xs text-gray-600"><?= $stat3_label ?></p>
                </div>
            </div>

            <?php if (strtolower($project_status) === 'in progress'): ?>
            <div class="w-full bg-gray-100 rounded-full h-2 mb-4">
                <div class="<?= $progressColorClass ?> h-2 rounded-full transition-all" style="width: <?= $progress ?>%"></div>
            </div>
            <?php endif; ?>

            <div class="flex justify-end items-center mt-4 pt-2 border-t">
                <?= $actions ?>
            </div>
        </div>
    <?php
        endwhile;
    else:
        $message_title = ($filter_status === 'all') ? "You haven't hosted any projects yet." : "No projects found with the ‘" . ucfirst(str_replace('+', ' ', $filter_status)) . "’ status.";
        $message_subtitle = ($filter_status === 'all') ? "Create a new project and find your team." : "Select another filter or try changing the status of your projects.";
    ?>
        <div class="col-span-full text-center py-16 bg-white border-2 border-dashed border-gray-300 rounded-xl">
            <svg class="w-12 h-12 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 19.172A4 4 0 018 17.586V3a1 1 0 011-1h6a1 1 0 011 1v14.586a4 4 0 01-1.172 2.586M12 21a2 2 0 002-2h-4a2 2 0 002 2z"></path></svg>
            <p class="text-xl font-semibold text-gray-900 mt-4"><?= $message_title ?></p>
            <p class="text-sm text-gray-500 mt-2"><?= $message_subtitle ?></p>
        </div>
    <?php
    endif;
    
    if (isset($stmt)) $stmt->close();
    ?>
    </div>
</div>

<script>
</script>