<?php

// ഡാറ്റാബേസ് കണക്ഷൻ വിവരങ്ങൾ. നിങ്ങളുടെ dbname, user, password എന്നിവ ഇവിടെ നൽകുക.
try {
    // നിങ്ങളുടെ ഡാറ്റാബേസ് ക്രെഡൻഷ്യലുകൾ ഇവിടെ നൽകുക
    $pdo = new PDO("mysql:host=localhost;dbname=collabbuddy", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ==========================================================
// ശ്രദ്ധിക്കുക: നിങ്ങൾ ഇത് production-ൽ ഉപയോഗിക്കുമ്പോൾ, താഴെ കാണുന്ന Test Host ID മാറ്റി
// commented-out ചെയ്തിട്ടുള്ള $_SESSION['user_id'] ഉപയോഗിച്ച് ഹോസ്റ്റിൻ്റെ ലോഗിൻ ഉറപ്പാക്കുക.
// ==========================================================
$host_id = 1; // TEST HOST ID 

// സുരക്ഷാ പരിശോധന: ഹോസ്റ്റ് ലോഗിൻ ചെയ്തിട്ടുണ്ടോ എന്ന് ഉറപ്പാക്കുക.
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host') {
//     header('Location: login.php');
//     exit;
// }
// $host_id = $_SESSION['user_id']; 

$selected_project_id = $_GET['project_id'] ?? null;
$participants = [];
$project_tasks = [];
$host_projects = [];
$message = '';
$error = '';

// 1. ഹോസ്റ്റിൻ്റെ എല്ലാ പ്രോജക്റ്റുകളും Fetch ചെയ്യുന്നു
$stmt = $pdo->prepare("SELECT project_id, title, status FROM projects WHERE host_id = ?");
$stmt->execute([$host_id]);
$host_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($selected_project_id) {
    // 2. തിരഞ്ഞെടുത്ത പ്രോജക്റ്റിലെ അംഗങ്ങളെ Fetch ചെയ്യുന്നു
    $stmt = $pdo->prepare("
        SELECT 
            pp.participant_id, 
            u.name, 
            pp.role_taken 
        FROM project_participants pp
        JOIN users u ON pp.participant_id = u.user_id
        WHERE pp.project_id = ?
    ");
    $stmt->execute([$selected_project_id]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. തിരഞ്ഞെടുത്ത പ്രോജക്റ്റിലെ നിലവിലെ ടാസ്‌ക്കുകൾ Fetch ചെയ്യുന്നു
    $stmt = $pdo->prepare("
        SELECT 
            t.task_id, t.title, t.status, t.due_date, t.priority, u.name AS assigned_to 
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to_user_id = u.user_id
        WHERE t.project_id = ?
        ORDER BY FIELD(t.priority, 'Critical', 'High', 'Medium', 'Low'), t.due_date ASC
    ");
    $stmt->execute([$selected_project_id]);
    $project_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ടാസ്‌ക്ക് അസൈൻമെൻ്റ് കൈകാര്യം ചെയ്യുന്നതിനുള്ള ലോജിക് (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_task'])) {
    $p_id = $_POST['project_id'];
    $u_id = $_POST['assigned_to_user_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $due_date = $_POST['due_date'];
    $priority = $_POST['priority'];
    
    // ടാസ്‌ക്ക് അസൈൻ ചെയ്യാനുള്ള INSERT SQL
    $insert_stmt = $pdo->prepare("
        INSERT INTO tasks (project_id, assigned_to_user_id, title, description, due_date, status, priority)
        VALUES (?, ?, ?, ?, ?, 'To Do', ?)
    ");

    if ($insert_stmt->execute([$p_id, $u_id, $title, $description, $due_date, $priority])) {
        $message = "Task assigned successfully!";
        // Successful assignment: Refresh to see the new task
        header("Location: host_tasks.php?project_id=" . $p_id);
        exit;
    } else {
        $error = "Failed to assign task.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Host Dashboard - Task Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; }
        .sidebar { background-color: #2c3e50; min-height: 100vh; color: white; padding-top: 20px; }
        .sidebar a { color: #ecf0f1; text-decoration: none; display: block; padding: 10px 15px; border-radius: 5px; margin-bottom: 5px; transition: background-color 0.3s; }
        .sidebar a:hover, .sidebar a.active { background-color: #34495e; border-left: 3px solid #3498db; }
        .content-area { padding: 30px; }
        .card-member { border-left: 5px solid #3498db; }
        .task-list-card { border-left: 5px solid #e74c3c; }
        .participant-list { max-height: 350px; overflow-y: auto; }
        .task-form { background-color: #ffffff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .priority-high { background-color: #e74c3c; color: white; }
        .priority-medium { background-color: #f39c12; color: white; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3 sidebar">
            <h4 class="mb-4 text-center"><i class="fas fa-list-check me-2"></i> Project Management</h4>
            <hr class="text-white-50">
            <?php if (empty($host_projects)): ?>
                <p class="text-white-50 text-center">No projects found.</p>
            <?php else: ?>
                <?php foreach ($host_projects as $project): ?>
                    <a href="?project_id=<?php echo $project['project_id']; ?>" 
                        class="<?php echo ($project['project_id'] == $selected_project_id) ? 'active' : ''; ?>">
                        <strong><?php echo htmlspecialchars($project['title']); ?></strong> <br>
                        <small class="text-white-50">(Status: <?php echo htmlspecialchars($project['status']); ?>)</small>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="col-md-9 content-area">
            <?php if ($selected_project_id): ?>
                <?php 
                    // Find the title of the selected project
                    $current_project_title = 'Selected Project';
                    foreach ($host_projects as $p) {
                        if ($p['project_id'] == $selected_project_id) {
                            $current_project_title = $p['title'];
                            break;
                        }
                    }
                ?>
                <h2 class="mb-4">Task Assignment for: <span class="text-primary"><?php echo htmlspecialchars($current_project_title); ?></span></h2>
                <hr>

                <?php if ($message): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-7">
                        <div class="task-form">
                            <h4 class="mb-4 text-secondary"><i class="fas fa-plus-circle me-2"></i>Assign New Task</h4>
                            <form method="POST" action="host_tasks.php?project_id=<?php echo $selected_project_id; ?>">
                                <input type="hidden" name="assign_task" value="1">
                                <input type="hidden" name="project_id" value="<?php echo $selected_project_id; ?>">

                                <div class="mb-3">
                                    <label for="assigned_to" class="form-label fw-bold">Assign To Participant</label>
                                    <select id="assigned_to" name="assigned_to_user_id" class="form-select" required>
                                        <option value="">-- Select Participant --</option>
                                        <?php foreach ($participants as $p): ?>
                                            <option value="<?php echo $p['participant_id']; ?>">
                                                <?php echo htmlspecialchars($p['name']); ?> (Role: <?php echo htmlspecialchars($p['role_taken'] ?: 'Undefined'); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="title" class="form-label fw-bold">Task Title</label>
                                    <input type="text" id="title" name="title" class="form-control" required>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label fw-bold">Description</label>
                                    <textarea id="description" name="description" class="form-control" rows="2"></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="due_date" class="form-label fw-bold">Due Date</label>
                                        <input type="date" id="due_date" name="due_date" class="form-control" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="priority" class="form-label fw-bold">Priority</label>
                                        <select id="priority" name="priority" class="form-select" required>
                                            <option value="Medium">Medium</option>
                                            <option value="High">High</option>
                                            <option value="Critical">Critical</option>
                                            <option value="Low">Low</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-paper-plane me-2"></i>Assign Task</button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="col-lg-5">
                        <div class="card card-member h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0 text-primary"><i class="fas fa-users me-2"></i>Joined Team Members (<?php echo count($participants); ?>)</h5>
                            </div>
                            <div class="card-body participant-list">
                                <?php if (empty($participants)): ?>
                                    <p class="text-muted text-center mt-3">No participants have joined this project yet.</p>
                                <?php else: ?>
                                    <?php foreach ($participants as $p): ?>
                                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                            <div>
                                                <strong><?php echo htmlspecialchars($p['name']); ?></strong> <br>
                                                <small class="text-muted"><i class="fas fa-user-tag me-1"></i><?php echo htmlspecialchars($p['role_taken'] ?: 'Role: Undefined'); ?></small>
                                            </div>
                                            <span class="badge bg-success"><i class="fas fa-circle-check"></i> Joined</span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <h4 class="mt-5 mb-3 text-secondary"><i class="fas fa-clipboard-list me-2"></i>Current Tasks Status</h4>
                <div class="row">
                    <?php if (empty($project_tasks)): ?>
                        <p class="text-muted text-center">No tasks have been assigned for this project yet.</p>
                    <?php else: ?>
                        <?php foreach ($project_tasks as $task): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card task-list-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="card-title text-dark"><?php echo htmlspecialchars($task['title']); ?></h6>
                                            <span class="badge 
                                                <?php 
                                                    if ($task['priority'] == 'Critical') echo 'bg-danger';
                                                    else if ($task['priority'] == 'High') echo 'priority-high';
                                                    else if ($task['priority'] == 'Medium') echo 'priority-medium';
                                                    else echo 'bg-info';
                                                ?>">
                                                <?php echo htmlspecialchars($task['priority']); ?>
                                            </span>
                                        </div>
                                        <p class="card-text my-2"><i class="fas fa-calendar-alt me-1"></i> Due: <strong><?php echo htmlspecialchars($task['due_date']); ?></strong></p>
                                        <p class="card-text text-muted mb-2"><i class="fas fa-user-check me-1"></i> Assigned to: <strong><?php echo htmlspecialchars($task['assigned_to'] ?: 'Unassigned'); ?></strong></p>
                                        <span class="badge 
                                            <?php 
                                                if ($task['status'] == 'Completed') echo 'bg-success';
                                                else if ($task['status'] == 'In Progress') echo 'bg-warning text-dark';
                                                else echo 'bg-secondary';
                                            ?>">
                                            <?php echo htmlspecialchars($task['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="alert alert-info text-center" role="alert">
                    <i class="fas fa-hand-point-left me-2"></i>Please <strong>select a project</strong> from the left sidebar to manage tasks and view team members.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>