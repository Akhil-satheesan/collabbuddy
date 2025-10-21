<?php
session_start();
// Ensure you have a valid path to your configuration file
require_once __DIR__ . '/../include/config.php'; 

// 1. Authentication Check
if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: admin_login.php");
    exit;
}

$message = $error = '';

// --- Helper Functions ---
function update_report_status($conn, $report_id, $report_type, $status = 'Resolved') {
    $table_name = "";
    switch ($report_type) {
        case 'chat': $table_name = "chat_reports"; break;
        case 'participant': $table_name = "participant_reports"; break;
        case 'host': $table_name = "host_reports"; break;
        default: return "Invalid report type submitted.";
    }

    $sql = "UPDATE {$table_name} SET status = ? WHERE report_id = ? AND status = 'Pending'";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("si", $status, $report_id);
        $stmt->execute();
        $rows_affected = $stmt->affected_rows;
        $stmt->close();
        return $rows_affected > 0 ? "" : "Report #{$report_id} was not updated. It might already be marked as resolved.";
    }
    return "Error updating report status: " . $conn->error;
}

// 2. --- Generalized Action Handler (Resolve, Suspend, Ban) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- Case 1: Mark as Resolved ---
    if (isset($_POST['action']) && $_POST['action'] == 'resolve' && isset($_POST['report_id']) && isset($_POST['report_type'])) {
        $report_id = intval($_POST['report_id']);
        $report_type = $_POST['report_type'];
        
        $result = update_report_status($conn, $report_id, $report_type);
        if ($result === "") {
            $message = "Report #{$report_id} (" . ucfirst($report_type) . " Report) has been resolved successfully.";
        } else {
            $error = $result;
        }
    } 
    
    // --- Case 2: Suspend / Ban User (New Logic) ---
    elseif (isset($_POST['action']) && in_array($_POST['action'], ['suspend', 'ban']) && isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
        $action = $_POST['action'];
        $reason = trim($_POST['reason'] ?? 'Admin action');
        $report_ids_str = $_POST['report_ids'] ?? ''; // Comma separated list of report IDs to resolve

        $status = 'Active';
        $suspension_end_date = null;

        if ($action == 'suspend') {
            $duration = $_POST['duration']; 
            $status = 'Suspended';
            $timestamp = strtotime("+".$duration);
            $suspension_end_date = date("Y-m-d H:i:s", $timestamp);
            
            $sql = "UPDATE users SET status = ?, suspension_end_date = ?, suspension_reason = ? WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $status, $suspension_end_date, $reason, $user_id);
        } elseif ($action == 'ban') {
            $status = 'Banned';
            $sql = "UPDATE users SET status = ?, suspension_end_date = NULL, suspension_reason = ? WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $status, $reason, $user_id);
        }

        if (isset($stmt) && $stmt->execute()) {
            $action_desc = ($action == 'suspend') ? "suspended until {$suspension_end_date}" : "permanently banned";
            $message = "User ID: {$user_id} has been **{$action_desc}**. Reason: {$reason}";

            // Auto-resolve linked reports (Loop through report IDs and types)
            if (!empty($report_ids_str)) {
                $report_id_pairs = explode(',', $report_ids_str);
                foreach ($report_id_pairs as $pair) {
                    list($report_id, $report_type) = explode('|', $pair);
                    if ($report_id && $report_type) {
                         // We don't check the return value here, just attempt to resolve
                         update_report_status($conn, intval($report_id), $report_type);
                    }
                }
                $message .= " <br>Linked pending reports have been automatically resolved.";
            }

        } elseif (isset($stmt)) {
            $error = "Error performing action on user: " . $stmt->error;
        } else {
            $error = "Invalid action submitted.";
        }
        if (isset($stmt)) $stmt->close();
    }
}

// 3. Data Fetching Functions
function fetch_reports($conn, $sql) {
    $reports = [];
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $reports[] = $row;
        }
    }
    return $reports;
}

// --- Fetching Queries for Pending Reports ---

// Chat Reports Query: Reporter reports Reported User
$sql_chat = "
SELECT 
    r.report_id, 
    r.report_reason AS reason,
    u_reporter.name AS reporter_name, 
    u_reported.name AS reported_name, 
    u_reported.user_id AS reported_user_id,
    r.reported_at AS created_at,
    'chat' AS report_type
FROM chat_reports r
JOIN users u_reporter ON r.reporter_id = u_reporter.user_id
JOIN users u_reported ON r.reported_user_id = u_reported.user_id
WHERE r.status = 'Pending'
ORDER BY r.reported_at ASC";
$chat_reports = fetch_reports($conn, $sql_chat);

// Host Reports Query: Host reports Participant
$sql_host = "
SELECT 
    r.report_id, 
    r.reason,
    u_host.name AS reporter_name, 
    u_participant.name AS reported_name, 
    u_participant.user_id AS reported_user_id,
    r.created_at,
    'host' AS report_type
FROM host_reports r
JOIN users u_host ON r.host_id = u_host.user_id
JOIN users u_participant ON r.participant_id = u_participant.user_id
WHERE r.status = 'Pending'
ORDER BY r.created_at ASC";
$host_reports = fetch_reports($conn, $sql_host);


// Participant Reports Query: Participant reports Host
$sql_participant = "
SELECT 
    r.report_id, 
    r.reason,
    u_participant.name AS reporter_name, 
    u_host.name AS reported_name, 
    u_host.user_id AS reported_user_id,
    r.created_at,
    'participant' AS report_type
FROM participant_reports r
JOIN users u_participant ON r.participant_id = u_participant.user_id
JOIN users u_host ON r.host_id = u_host.user_id
WHERE r.status = 'Pending'
ORDER BY r.created_at ASC";
$participant_reports = fetch_reports($conn, $sql_participant);

$conn->close(); // Close connection after all data is fetched

// 4. Group all reports for structured display
$all_reports = [
    'Chat Reports' => ['reports' => $chat_reports, 'type' => 'chat', 'color' => 'red'],
    'Host Reports (Host reported Participant)' => ['reports' => $host_reports, 'type' => 'host', 'color' => 'orange'],
    'Participant Reports (Participant reported Host)' => ['reports' => $participant_reports, 'type' => 'participant', 'color' => 'indigo']
];
$pending_count = count($chat_reports) + count($participant_reports) + count($host_reports);

// --- HTML Structure and Display ---
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Reports - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .sidebar { background: linear-gradient(180deg, #4c51bf 0%, #6b46c1 100%); }
        .nav-link.active { background-color: rgba(255, 255, 255, 0.2); border-left: 4px solid #fff; }
        
        /* Modal Style */
        .modal { 
            background-color: rgba(0, 0, 0, 0.5); 
        }
        .modal-content { max-width: 400px; }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">
    <div class="flex h-screen">
        <aside class="sidebar w-64 p-5 text-white flex flex-col fixed h-full z-20">
            <div class="text-2xl font-bold mb-10 border-b border-indigo-400 pb-3">CollabBuddy Admin</div>
            <nav class="flex flex-col space-y-2">
                <a href="admin_dashboard.php" class="nav-link flex items-center p-3 rounded-lg hover:bg-white hover:text-indigo-700 transition-colors"><span class="mr-3">🏠</span> Dashboard</a>
                <a href="manage_users.php" class="nav-link flex items-center p-3 rounded-lg hover:bg-white hover:text-indigo-700 transition-colors"><span class="mr-3">👥</span> Manage Users</a>
                <a href="manage_projects.php" class="nav-link flex items-center p-3 rounded-lg hover:bg-white hover:text-indigo-700 transition-colors"><span class="mr-3">💡</span> Monitor Projects</a>
                <a href="review_reports.php" class="nav-link active flex items-center p-3 rounded-lg hover:bg-white hover:text-indigo-700 transition-colors"><span class="mr-3">🚨</span> Review Reports</a>
            </nav>
            <div class="mt-auto pt-4 border-t border-indigo-400">
                <p class="text-sm font-semibold mb-2">Logged in as: <?php echo htmlspecialchars($_SESSION["admin_username"] ?? "Admin"); ?></p>
                <a href="admin_logout.php" class="flex items-center text-red-300 hover:text-red-100 transition-colors"><span class="mr-2">🚪</span> Logout</a>
            </div>
        </aside>

        <main class="flex-1 p-8 ml-64 overflow-y-auto">
            <header class="mb-8">
                <h1 class="text-3xl font-extrabold text-gray-800">Pending Reports Review</h1>
                <p class="text-gray-600 mt-2">Action required: Review and take appropriate action on reported items.</p>
            </header>

            <?php if (!empty($message)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4"><?php echo $message; ?></div>
            <?php elseif (!empty($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($pending_count === 0): ?>
                <div class="bg-white p-6 rounded-xl shadow-lg text-center py-10 text-green-600">
                    <span class="text-3xl block mb-2">🎉</span>
                    <p class="text-lg font-semibold">Great job! No pending reports to review.</p>
                </div>
            <?php else: ?>
                <div class="space-y-12">
                    <?php 
                    // This array will hold unique reported user IDs and the report IDs linked to them
                    $reported_users_data = []; 
                    ?>
                    
                    <?php foreach ($all_reports as $title => $data): ?>
                        <section>
                            <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2"><?php echo htmlspecialchars($title); ?> (<span class="text-<?php echo $data['color']; ?>-600"><?php echo count($data['reports']); ?></span> Pending)</h2>
                            <?php if (empty($data['reports'])): ?>
                                <p class="text-gray-500 bg-white p-4 rounded-xl shadow">No pending <?php echo strtolower($title); ?>.</p>
                            <?php else: ?>
                                <div class="space-y-6">
                                    <?php foreach ($data['reports'] as $report): 
                                        $border_color = "border-".$data['color']."-500";
                                        $reported_user_id = htmlspecialchars($report['reported_user_id']);
                                        $report_id = htmlspecialchars($report['report_id']);
                                        $report_type = htmlspecialchars($report['report_type']);

                                        // Store report ID and type associated with the reported user
                                        if (!isset($reported_users_data[$reported_user_id])) {
                                            $reported_users_data[$reported_user_id] = [];
                                        }
                                        $reported_users_data[$reported_user_id][] = "{$report_id}|{$report_type}";
                                    ?>
                                        <div class="bg-white p-6 rounded-xl shadow-lg border-l-4 <?php echo $border_color; ?>">
                                            <div class="flex justify-between items-start mb-3">
                                                <h3 class="text-xl font-bold text-gray-900">Report #<?php echo $report_id; ?></h3>
                                                <span class="text-sm font-medium text-<?php echo $data['color']; ?>-600"><?php echo ucfirst($report_type); ?> Report</span>
                                            </div>
                                            <p class="text-sm text-gray-500 mb-2">
                                                <span class="font-medium text-green-600">Reporter: <?php echo htmlspecialchars($report['reporter_name']); ?></span>
                                                | 
                                                <span class="font-medium text-red-600">Reported User: <?php echo htmlspecialchars($report['reported_name']); ?> (ID: <?php echo $reported_user_id; ?>)</span>
                                                on <?php echo date("Y-m-d H:i", strtotime($report['created_at'])); ?>
                                            </p>
                                            
                                            <div class="bg-gray-100 p-4 rounded-lg mt-3">
                                                <p class="text-xs font-semibold text-gray-700 mb-1">Reason/Content:</p>
                                                <p class="text-sm text-gray-800 italic"><?php echo nl2br(htmlspecialchars($report['reason'])); ?></p>
                                            </div>

                                            <div class="mt-4 text-right space-x-2">
                                                <form method="POST" action="review_reports.php" class="inline">
                                                    <input type="hidden" name="report_id" value="<?php echo $report_id; ?>">
                                                    <input type="hidden" name="report_type" value="<?php echo $report_type; ?>">
                                                    <input type="hidden" name="action" value="resolve">
                                                    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors" onclick="return confirm('Confirm marking Report #<?php echo $report_id; ?> as RESOLVED?')">
                                                        Mark as Resolved
                                                    </button>
                                                </form>
                                                
                                                <button 
                                                    onclick="openActionModal(
                                                        '<?php echo $reported_user_id; ?>', 
                                                        '<?php echo htmlspecialchars($report['reported_name']); ?>',
                                                        '<?php echo implode(',', $reported_users_data[$reported_user_id]); ?>'
                                                    )" 
                                                    class="text-sm bg-red-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-red-700 transition-colors">
                                                    Take Action (Suspend/Ban)
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <div id="actionModal" class="modal fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
        <div class="modal-content bg-white p-6 rounded-lg shadow-2xl w-full">
            <h2 id="modalTitle" class="text-2xl font-bold text-gray-800 mb-4">Take Action on User</h2>
            <form method="POST" action="review_reports.php">
                <input type="hidden" name="user_id" id="modalUserId">
                <input type="hidden" name="action" id="modalActionType">
                <input type="hidden" name="report_ids" id="modalReportIds"> <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">User:</label>
                    <p id="modalUserName" class="text-lg font-semibold text-indigo-600"></p>
                </div>
                
                <div class="mb-4">
                    <label for="actionSelect" class="block text-sm font-medium text-gray-700 mb-1">Select Action</label>
                    <select id="actionSelect" onchange="updateModal(this.value)" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500">
                        <option value="suspend">Suspend Temporarily</option>
                        <option value="ban">Permanently Ban</option>
                    </select>
                </div>

                <div id="durationGroup" class="mb-4">
                    <label for="duration" class="block text-sm font-medium text-gray-700 mb-1">Duration (Suspension Only)</label>
                    <select name="duration" id="duration" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="24 hours">24 Hours</option>
                        <option value="7 days">7 Days</option>
                        <option value="14 days">14 Days</option>
                        <option value="30 days">30 Days</option>
                    </select>
                </div>

                <div class="mb-6">
                    <label for="reason" class="block text-sm font-medium text-gray-700 mb-1">Reason for Action (This will be saved)</label>
                    <textarea name="reason" id="reason" rows="3" required class="w-full p-2 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500"></textarea>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal()" class="bg-gray-300 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-400">Cancel</button>
                    <button type="submit" id="modalSubmitBtn" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">Confirm Suspension</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // --- Modal Control ---
        const modal = document.getElementById('actionModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalUserId = document.getElementById('modalUserId');
        const modalUserName = document.getElementById('modalUserName');
        const modalActionType = document.getElementById('modalActionType');
        const modalReportIds = document.getElementById('modalReportIds');
        const actionSelect = document.getElementById('actionSelect');
        const durationGroup = document.getElementById('durationGroup');
        const reasonInput = document.getElementById('reason');
        const modalSubmitBtn = document.getElementById('modalSubmitBtn');

        // Function to update modal based on selected action (Suspend/Ban)
        function updateModal(actionType) {
            modalActionType.value = actionType;
            if (actionType === 'suspend') {
                modalTitle.textContent = 'Suspend User';
                durationGroup.classList.remove('hidden');
                modalSubmitBtn.textContent = 'Confirm Suspension';
                modalSubmitBtn.classList.remove('bg-red-600');
                modalSubmitBtn.classList.add('bg-yellow-600');
            } else if (actionType === 'ban') {
                modalTitle.textContent = 'Permanently Ban User';
                durationGroup.classList.add('hidden');
                modalSubmitBtn.textContent = 'Confirm Permanent Ban';
                modalSubmitBtn.classList.remove('bg-yellow-600');
                modalSubmitBtn.classList.add('bg-red-600');
            }
        }

        // Function to open the modal and set user/report data
        function openActionModal(userId, userName, reportIds) {
            modalUserId.value = userId;
            modalUserName.textContent = userName + ' (ID: ' + userId + ')';
            modalReportIds.value = reportIds; // Set linked report IDs
            reasonInput.value = 'Multiple reports received. Violations include: '; // Suggested initial reason
            
            // Default to Suspend when opening
            actionSelect.value = 'suspend';
            updateModal('suspend'); 
            
            // Show the modal
            modal.classList.remove('hidden');
            modal.classList.add('flex'); 
        }

        function closeModal() {
            // Hide the modal
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>