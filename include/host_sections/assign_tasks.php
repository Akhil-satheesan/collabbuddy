<?php
// =========================================================================
// 1. DATABASE CONNECTION CONFIGURATION
// =========================================================================
$servername = "localhost";
$username = "root";       // Change if your DB username is different
$password = "";           // Change if your DB password is set
$dbname = "collabbuddy";  // The database name from the SQL dump

// =========================================================================
// 2. LOGGED-IN HOST ID (Placeholder)
//    NOTE: In a real application, this should be fetched from the session 
//    after the host logs in. We are using 17 as a placeholder based on the provided dump.
// =========================================================================
$host_id = 17; 

// =========================================================================
// 3. DATABASE CONNECTION ESTABLISHMENT
// =========================================================================
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// =========================================================================
// 4. SQL QUERY TO FETCH RELEVANT PROJECTS
//    Criteria: host_id matches, status is 'In Progress', AND has members in project_participants.
// =========================================================================
$sql = "
SELECT
    p.project_id,
    p.title,
    p.description
FROM
    projects p
WHERE
    p.host_id = ? 
    AND p.status = 'In Progress' 
    AND EXISTS (
        SELECT 1
        FROM project_participants pp
        WHERE pp.project_id = p.project_id
    )
ORDER BY
    p.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $host_id);
$stmt->execute();
$result = $stmt->get_result();

$projects = [];
while ($row = $result->fetch_assoc()) {
    $projects[] = $row;
}

$stmt->close();
$conn->close();

// Determine selected project (if any)
$selected_project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : null;
$selected_project = null;

if ($selected_project_id) {
    foreach ($projects as $project) {
        if ($project['project_id'] === $selected_project_id) {
            $selected_project = $project;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Task Assignment</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { display: flex; width: 100vw; min-height: 100vh; }
        /* Left Panel - Project List */
        .left-panel { width: 30%; background-color: #fff; box-shadow: 2px 0 5px rgba(0,0,0,0.1); padding: 20px; border-right: 1px solid #ddd; }
        .left-panel h2 { color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .project-list { list-style: none; padding: 0; }
        .project-item { padding: 15px; margin-bottom: 10px; border: 1px solid #eee; border-radius: 5px; cursor: pointer; transition: background-color 0.3s; }
        .project-item:hover { background-color: #f9f9f9; }
        .project-item.active { background-color: #e0f7fa; border-color: #00bcd4; font-weight: bold; }
        .project-item h3 { margin: 0 0 5px 0; color: #007bff; font-size: 1.1em; }
        .project-item p { margin: 0; color: #666; font-size: 0.9em; }
        /* Right Panel - Task Management */
        .right-panel { width: 70%; padding: 20px; }
        .info-box { background-color: #fff3cd; color: #856404; padding: 15px; border: 1px solid #ffeeba; border-radius: 5px; margin-bottom: 20px; }
        .task-management-area { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="container">
        <div class="left-panel">
            <h2>Select a Project to Begin</h2>
            <div class="info-box">
                Only projects that have moved past the initial 'Active' stage (i.e., **'In Progress'**) and have an **existing team** will appear here.
            </div>

            <?php if (empty($projects)): ?>
                <p>No projects currently meet the criteria for task assignment.</p>
            <?php else: ?>
                <ul class="project-list">
                    <?php foreach ($projects as $project): ?>
                        <a href="?project_id=<?php echo $project['project_id']; ?>" style="text-decoration: none; color: inherit;">
                            <li class="project-item <?php echo ($project['project_id'] === $selected_project_id) ? 'active' : ''; ?>">
                                <h3><?php echo htmlspecialchars($project['title']); ?> (ID: <?php echo $project['project_id']; ?>)</h3>
                                <p><?php echo substr(htmlspecialchars($project['description']), 0, 50) . '...'; ?></p>
                            </li>
                        </a>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="right-panel">
            <?php if ($selected_project): ?>
                <div class="task-management-area">
                    <h2>Task Management for: <?php echo htmlspecialchars($selected_project['title']); ?></h2>
                    <p><strong>Description:</strong> <?php echo htmlspecialchars($selected_project['description']); ?></p>
                    <hr>
                    
                    <h3>Assign a New Task</h3>
                    <form>
                        <label for="task_title">Task Title:</label><br>
                        <input type="text" id="task_title" name="task_title" required><br><br>
                        
                        <label for="task_description">Description:</label><br>
                        <textarea id="task_description" name="task_description"></textarea><br><br>
                        
                        <label for="assigned_to">Assign To:</label><br>
                        <select id="assigned_to" name="assigned_to">
                            <option value="">-- Select Team Member --</option>
                            </select><br><br>

                        <button type="submit">Assign Task</button>
                    </form>
                    
                    <h3 style="margin-top: 30px;">Current Tasks & Team Members</h3>
                    <p>*(Display current task list and team members for Project ID: <?php echo $selected_project['project_id']; ?>)*</p>

                </div>
            <?php else: ?>
                <div class="task-management-area">
                    <h2>Welcome to Task Assignment</h2>
                    <p>Please select a project from the left panel to manage tasks, view progress, and assign work to your team members.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>