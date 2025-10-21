<?php
require_once __DIR__ . '/../../include/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$conn = get_db_connection();

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'participant') {
    http_response_code(401);
    echo "<p class='text-red-600 p-4'>Unauthorized access or role mismatch.</p>";
    $conn->close();
    exit;
}

$participant_id = (int)$_SESSION['user_id'];

// Fetch Joined Projects
$sql = "
    SELECT 
        p.project_id, 
        p.title, 
        p.description, 
        p.created_at,
        u.name AS host_name,
        p.required_roles_list,
        p.team_size_per_role
    FROM project_participants pp
    INNER JOIN projects p ON pp.project_id = p.project_id
    INNER JOIN users u ON p.host_id = u.user_id
    WHERE pp.participant_id = ?
    GROUP BY p.project_id
    ORDER BY p.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $participant_id);
$stmt->execute();
$result = $stmt->get_result();
$joined_projects = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div id="joined-projects-content" class="content-section">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="border-b border-gray-200 pb-4 mb-4">
            <h3 class="text-2xl font-semibold text-gray-900">My Joined Projects (<?= count($joined_projects) ?>)</h3>
            <p class="text-gray-600">These are the projects you are currently collaborating on.</p>
        </div>

        <div class="space-y-6">
            <?php if (!empty($joined_projects)): ?>
                <?php foreach ($joined_projects as $project): ?>
                    <?php
                        // Calculate total required size
                        $total_required = 0;
                        $role_counts = [];

                        if (!empty($project['team_size_per_role'])) {
                            $roles = explode(',', $project['team_size_per_role']); // e.g. Developer:2, Designer:1
                            foreach ($roles as $role_entry) {
                                $parts = explode(':', trim($role_entry));
                                $role_name = $parts[0];
                                $count = isset($parts[1]) ? (int)$parts[1] : 1;
                                $role_counts[$role_name] = $count;
                                $total_required += $count;
                            }
                        }

                        // Count current members per project
                        $stmt_count = $conn->prepare("SELECT COUNT(*) as member_count FROM project_participants WHERE project_id=?");
                        $stmt_count->bind_param("i", $project['project_id']);
                        $stmt_count->execute();
                        $member_count = $stmt_count->get_result()->fetch_assoc()['member_count'] ?? 0;
                        $stmt_count->close();
                    ?>
                    <div class="border border-green-200 rounded-lg p-5 bg-green-50 shadow-md transition hover:shadow-lg">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <h4 class="text-xl font-bold text-green-800"><?= htmlspecialchars($project['title']) ?></h4>
                                <p class="text-sm text-gray-600 mt-1">Hosted by: 
                                    <span class="font-medium text-indigo-600"><?= htmlspecialchars($project['host_name']) ?></span>
                                </p>
                            </div>
                            <span class="text-xs text-gray-500">
                                Joined on: <?= date("M d, Y", strtotime($project['created_at'])) ?>
                            </span>
                        </div>

                        <p class="text-sm text-gray-700 mb-4 line-clamp-2">
                            <?= nl2br(htmlspecialchars($project['description'])) ?>
                        </p>

                        <div class="bg-gray-100 p-3 rounded-lg border-l-4 border-blue-500 shadow-md inline-block">
                            <p class="text-gray-800 font-medium">
                                Team Members: <?= $member_count ?> / <?= $total_required ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center p-8 border border-dashed rounded-lg bg-gray-50">
                    <p class="text-gray-500 text-lg">You haven't joined any projects yet. Start exploring and apply!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
