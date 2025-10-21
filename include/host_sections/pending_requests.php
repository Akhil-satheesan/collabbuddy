<?php
// FILE: include/host_sections/pending_requests.php
// ----------------------------------------------------------------------
require_once __DIR__ . '/../../include/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Ensure user is logged in and is a host
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host') {
    http_response_code(401);
    echo "<p class='text-red-600 p-4'>Unauthorized access or role mismatch.</p>";
    exit;
}

$host_id = (int) $_SESSION['user_id'];
$conn = get_db_connection(); 

// Fetch all projects of host for filter dropdown
$projSql = "SELECT project_id, title FROM projects WHERE host_id = ?";
$stmtProj = $conn->prepare($projSql);
if ($stmtProj === false) { error_log("ProjSQL Prepare Error: " . $conn->error); }
$stmtProj->bind_param("i", $host_id);
$stmtProj->execute();
$projectsResult = $stmtProj->get_result();
$stmtProj->close();

// Apply filter
$filterProject = isset($_GET['project_id']) && $_GET['project_id'] !== "" ? (int)$_GET['project_id'] : null;

// Fetch pending requests
$sql = "SELECT pr.request_id, pr.created_at,
               u.user_id, u.name, u.email,
               p.project_id, p.title,
               part.preferred_role, part.skills,
               a.cover_message
        FROM project_requests pr
        INNER JOIN projects p ON pr.project_id = p.project_id
        INNER JOIN users u ON pr.participant_id = u.user_id
        LEFT JOIN participants part ON part.participant_id = u.user_id
        LEFT JOIN applications a ON a.project_id = p.project_id AND a.participant_id = u.user_id
        WHERE p.host_id = ? AND pr.status = 'pending'";

if ($filterProject) {
    $sql .= " AND p.project_id = ?";
}

$sql .= " ORDER BY pr.created_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt === false) { error_log("RequestSQL Prepare Error: " . $conn->error); }

if ($filterProject) {
    $stmt->bind_param("ii", $host_id, $filterProject);
} else {
    $stmt->bind_param("i", $host_id);
}
$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();


// --- NEW: Fetch Roles and Capacity for ALL Host Projects ---
$allRoles = [];
$rolesSql = "SELECT project_id, required_roles_list, team_size_per_role FROM projects WHERE host_id = ?";
$stmtRoles = $conn->prepare($rolesSql);
if ($stmtRoles) {
    $stmtRoles->bind_param("i", $host_id);
    $stmtRoles->execute();
    $rolesResult = $stmtRoles->get_result();
    while ($projRole = $rolesResult->fetch_assoc()) {
        $projectId = (int)$projRole['project_id'];
        $roles_list_str = $projRole['required_roles_list'];
        $roles_size_str = $projRole['team_size_per_role'];

        $roles_array = array_map('trim', explode(',', $roles_list_str));
        $size_array = [];
        $total_capacity = 0;
        
        foreach (explode(',', $roles_size_str) as $role_pair) {
            $parts = array_map('trim', explode(':', $role_pair));
            if (count($parts) === 2) {
                $size_array[$parts[0]] = (int)$parts[1];
                $total_capacity += (int)$parts[1];
            }
        }
        
        // Fetch current count for all roles in this project (to display capacity)
        $current_counts = [];
        if (!empty($roles_array)) {
            $placeholders = implode(',', array_fill(0, count($roles_array), '?'));
            // NEW (Correct) Query
$sql_current_count = "SELECT role_taken, COUNT(*) as current_count 
FROM project_participants 
WHERE project_id = ? AND role_taken IN ({$placeholders}) 
GROUP BY role_taken";

// ... ഈ മാറ്റം വരുത്തിയതിന് ശേഷം ബാക്കി PHP കോഡ് അതേപടി ഉപയോഗിക്കാം.
            $stmtCount = $conn->prepare($sql_current_count);
            if ($stmtCount) {
                $types = 'i' . str_repeat('s', count($roles_array));
                $params = array_merge([$types, $projectId], $roles_array);
                $stmtCount->bind_param(...$params);
                $stmtCount->execute();
                $countResult = $stmtCount->get_result();
                while ($row_count = $countResult->fetch_assoc()) {
                    $current_counts[$row_count['role_taken']] = (int)$row_count['current_count'];
                }
                $stmtCount->close();
            }
        }

        $allRoles[$projectId] = [
            'roles' => $roles_array, 
            'sizes' => $size_array,
            'counts' => $current_counts,
            'total_capacity' => $total_capacity
        ];
    }
    $stmtRoles->close();
}
// Note: $conn->close() is handled by the main AJAX loader, but should ideally be closed here if this file is loaded outside an AJAX context.
?>

<div id="requests-content" class="content-section">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Pending Join Requests (<?= count($rows) ?>)</h3>
            
            <form id="filterForm" method="GET" class="flex items-center space-x-3">
                <label for="project_id" class="text-sm font-medium text-gray-600">Project:</label>
                <select name="project_id" id="project_id" 
                    class="bg-white border border-gray-300 text-gray-700 text-sm rounded-lg px-3 py-2 shadow-sm 
                            focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                    <option value="">All Projects</option>
                    <?php while($proj = $projectsResult->fetch_assoc()): ?>
                        <option value="<?= $proj['project_id'] ?>" 
                            <?= ($filterProject == $proj['project_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($proj['title']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </form>
        </div>
        
        <div class="p-6">
            <div class="space-y-6">
                <?php if (!empty($rows)): ?>
                    <?php foreach ($rows as $row): ?>
                        <?php
                            $initials = strtoupper(substr($row['name'] ?? '??', 0, 2));
                            $role = !empty($row['preferred_role']) ? $row['preferred_role'] : 'Participant';
                            $skills = !empty($row['skills']) ? array_filter(array_map('trim', explode(',', $row['skills']))) : [];
                            $coverMessage = !empty($row['cover_message']) ? $row['cover_message'] : "Hi! I'm interested in this project.";
                            
                            $projRoles = $allRoles[(int)$row['project_id']] ?? ['roles' => [], 'sizes' => [], 'counts' => [], 'total_capacity' => 0];
                            $currentRoles = $projRoles['roles'];
                            $currentSizes = $projRoles['sizes'];
                            $currentCounts = $projRoles['counts'];

                            // --- NEW LIVE COUNT CALCULATION FOR DISPLAY ---
                            $currentTotalAssigned = array_sum($currentCounts); 
                            $totalCapacity = $projRoles['total_capacity'];
                            // ---------------------------------------------
                        ?>
                        <div class="border border-gray-200 rounded-lg p-6 request-card" data-request-id="<?= (int)$row['request_id'] ?>">
                            <div class="flex items-start space-x-4">
                                <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center text-white text-xl font-semibold">
                                    <?= htmlspecialchars($initials) ?>
                                </div>
                                <div class="flex-1">
                                    <div class="flex justify-between items-start mb-3">
                                        <div>
                                            <h4 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($row['name']) ?></h4>
                                            <p class="text-indigo-600 font-medium"><?= htmlspecialchars($role) ?></p>
                                            <p class="text-sm text-gray-600 mt-2">
                                                Wants to join: 
                                                <span class="ml-2 relative inline-block text-indigo-700 font-bold text-base">
                                                    <?= htmlspecialchars($row['title']) ?>
                                                    <span class="absolute left-0 bottom-0 w-full h-1 bg-indigo-400 rounded-full animate-pulse"></span>
                                                </span>
                                            </p>
                                        </div>
                                        <span class="text-xs text-gray-500 time-elapsed" data-time="<?= htmlspecialchars($row['created_at']) ?>">just now</span>
                                    </div>

                                    <div class="bg-gray-50 rounded-lg p-4 mb-4">
                                        <p class="text-sm text-gray-700">"<?= nl2br(htmlspecialchars($coverMessage)) ?>"</p>
                                    </div>

                                    <?php if (!empty($skills)): ?>
                                        <div class="flex flex-wrap items-center space-x-2 mb-4">
                                            <span class="text-sm text-gray-600">Skills:</span>
                                            <?php foreach ($skills as $skill): ?>
                                                <?php $skill = trim($skill); if ($skill === '') continue; ?>
                                                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs mt-1"><?= htmlspecialchars($skill) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($currentRoles)): ?>
    <div class="mb-4 flex items-center space-x-3 bg-yellow-50 p-3 rounded-lg border border-yellow-200">
        
        <label for="role-select-<?= (int)$row['request_id'] ?>" class="text-sm font-bold text-yellow-800">Assign Role:</label>
        
        <span class="text-sm font-semibold text-yellow-900 border-l border-yellow-300 pl-3">
            Team Status: 
            <span class="font-black text-lg"><?= $currentTotalAssigned ?> / <?= $totalCapacity ?></span>
        </span>

        <select id="role-select-<?= (int)$row['request_id'] ?>" 
                class="role-select bg-white border border-gray-300 text-gray-900 text-sm rounded-lg p-2.5 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
            <option value="" selected disabled>-- Select a Role --</option>
            
            <?php foreach ($currentRoles as $role): 
                $capacity = $currentSizes[$role] ?? 1;
                $current = $currentCounts[$role] ?? 0;
                $remaining = $capacity - $current;
            ?>
                <option value="<?= htmlspecialchars($role) ?>" <?= ($remaining <= 0) ? 'disabled' : '' ?>>
                    <?= htmlspecialchars($role) ?> (Current: <?= $current ?> / Max: <?= $capacity ?>) <?= ($remaining <= 0) ? '(FULL)' : '' ?>
                </option>
            <?php endforeach; ?>
            
        </select>
    </div>
<?php endif; ?>
                                    <div class="flex space-x-3">
                                        <button class="approve-btn bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium"
                                                    data-id="<?= (int)$row['request_id'] ?>">
                                            ✓ Approve
                                        </button>
                                        <button class="reject-btn bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium"
                                                    data-id="<?= (int)$row['request_id'] ?>">
                                            ✗ Reject
                                        </button>
                                        <button class="view-profile-btn border border-gray-300 hover:border-gray-400 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium"
                                                    data-user-id="<?= (int)$row['user_id'] ?>"
                                                    data-request-id="<?= (int)$row['request_id'] ?>">
                                            👁️ View Profile
                                        </button>
                                        <button class="chat-btn text-indigo-600 hover:text-indigo-700 text-sm font-medium"
                                                    data-participant-id="<?= (int)$row['user_id'] ?>" 
                                                    data-project-id="<?= (int)$row['project_id'] ?>"> 
                                            💬 Chat (1:1)
                                        </button>
                                        <button class="report-btn text-red-600 hover:text-red-700 text-sm font-medium ml-4"
                                            title="Report this Participant"
                                            onclick="openHostReportModal(
                                                <?= (int)$row['user_id'] ?>, 
                                                '<?= htmlspecialchars(addslashes($row['name'])) ?>', 
                                                <?= (int)$row['project_id'] ?>
                                            )">
                                        <i class="fas fa-flag mr-1"></i> Report
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-500 p-4">No pending requests yet. <?= $filterProject ? "Try changing the filter." : "" ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div id="hostReportModal" class="hidden fixed inset-0 z-[70] bg-black bg-opacity-70 flex items-center justify-center p-4">
    <div class="bg-white p-6 rounded-xl shadow-2xl w-full max-w-md">
        <h2 class="text-2xl font-bold text-red-600 mb-4 flex items-center">
            <i class="fas fa-flag mr-2"></i> Report Participant
        </h2>
        <p class="text-gray-700 mb-4">You are reporting <span class="font-semibold text-gray-900" id="hostReportTargetName"></span>.</p>
        
        <form id="hostReportForm">
            <input type="hidden" name="participant_id" id="host_report_participant_id"> 
            <input type="hidden" name="project_id_context" id="host_report_project_id"> 
            
            <div class="mb-4">
                <label for="host_report_reason" class="block text-sm font-medium text-gray-700 mb-2">Reason for Report</label>
                <select name="reason" id="host_report_reason" required class="w-full border border-gray-300 rounded-lg p-3 focus:border-red-500 focus:ring-red-500">
                    <option value="">-- Select Reason --</option>
                    <option value="Unprofessional Communication">Unprofessional Communication</option>
                    <option value="Inappropriate Content">Inappropriate Content</option>
                    <option value="Spam/Scam Attempt">Spam/Scam Attempt</option>
                    <option value="Unreliable Application">Unreliable Application/Ghosting</option>
                    <option value="Other">Other (Please specify below)</option>
                </select>
            </div>
            
            <div class="mb-4">
                <label for="host_report_details" class="block text-sm font-medium text-gray-700 mb-2">Details (Describe the issue)</label>
                <textarea name="details" id="host_report_details" rows="4" required class="w-full border border-gray-300 rounded-lg p-3" placeholder="Provide details about the incident..."></textarea>
            </div>
            
            <div id="hostReportMessage" class="mt-4 hidden text-sm font-medium p-3 rounded-lg"></div>
            
            <div class="flex justify-end space-x-3 mt-5">
                <button type="button" onclick="closeHostReportModal()" class="px-5 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium" id="hostSubmitReportBtn">Submit Report</button>
            </div>
        </form>
    </div>
</div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // ... [existing JavaScript code] ...

// ----------------------------------------------------------------------
// ⭐️ HOST REPORT MODAL LOGIC ⭐️
// ----------------------------------------------------------------------

/**
 * Opens the modal to report a participant.
 * @param {number} participantId - The ID of the participant being reported.
 * @param {string} participantName - The name of the participant.
 * @param {number} projectId - The project context ID.
 */
window.openHostReportModal = function(participantId, participantName, projectId) {
    $('#host_report_participant_id').val(participantId);
    $('#host_report_project_id').val(projectId); 
    $('#hostReportTargetName').text(participantName);
    $('#hostReportModal').removeClass('hidden');
}

window.closeHostReportModal = function() {
    $('#hostReportModal').addClass('hidden');
    $('#hostReportForm')[0].reset(); 
    $('#hostReportMessage').empty().addClass('hidden'); 
    $('#hostSubmitReportBtn').prop('disabled', false).text('Submit Report'); 
}

// റിപ്പോർട്ട് ഫോം സമർപ്പിക്കാനുള്ള AJAX
$(document).on('submit', '#hostReportForm', function(e) {
    e.preventDefault();
    const form = $(this);
    const submitBtn = $('#hostSubmitReportBtn');
    const messageArea = $('#hostReportMessage');

    submitBtn.prop('disabled', true).text('Submitting...');
    messageArea.removeClass('hidden bg-red-100 bg-green-100').addClass('bg-gray-100 text-gray-700').text('Processing report...');

    $.ajax({
        // ⭐️ റിപ്പോർട്ട് ലോജിക് ഫയൽ ഇരിക്കുന്ന ശരിയായ പാത്ത് ഉപയോഗിക്കുക
        url: 'include/host_sections/report_participant.php', 
        type: 'POST',
        data: form.serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                messageArea.removeClass('bg-gray-100 bg-red-100').addClass('bg-green-100 text-green-700').text(response.message);
                // 3 സെക്കൻഡിന് ശേഷം മോഡൽ ക്ലോസ് ചെയ്യുന്നു
                setTimeout(closeHostReportModal, 3000); 
            } else {
                messageArea.removeClass('bg-gray-100 bg-green-100').addClass('bg-red-100 text-red-700').text('❌ Submission failed: ' + (response.error || response.message || 'Unknown error.'));
                submitBtn.prop('disabled', false).text('Submit Report');
            }
        },
        error: function(xhr) {
            let errorMsg = '❌ Server error during submission.';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                 errorMsg = '❌ Server error: ' + xhr.responseJSON.error;
            }
            messageArea.removeClass('bg-gray-100 bg-green-100').addClass('bg-red-100 text-red-700').text(errorMsg);
            submitBtn.prop('disabled', false).text('Submit Report');
            console.error("Host Report AJAX error:", xhr.responseText);
        }
    });
});
// Update elapsed times logic (working correctly)
function updateTimes() {
    $(".time-elapsed").each(function() {
        let createdAt = new Date($(this).data("time"));
        let now = new Date();
        let diffSec = Math.floor((now - createdAt) / 1000);

        let display = "";
        if (diffSec < 60) {
            display = diffSec + " second" + (diffSec != 1 ? "s" : "") + " ago";
        } else if (diffSec < 3600) {
            let mins = Math.floor(diffSec / 60);
            display = mins + " minute" + (mins != 1 ? "s" : "") + " ago";
        } else if (diffSec < 86400) {
            let hrs = Math.floor(diffSec / 3600);
            display = hrs + " hour" + (hrs != 1 ? "s" : "") + " ago";
        } else {
            let days = Math.floor(diffSec / 86400);
            display = days + " day" + (days != 1 ? "s" : "") + " ago";
        }
        $(this).text(display);
    });
}
setInterval(updateTimes, 60000);
updateTimes();

// Auto submit filter on change (AJAX reload)
$(document).on("change", "#project_id", function() {
    let projectId = $(this).val();
    // Assuming the main dashboard uses a dynamic loader:
    const url = "include/host_sections/pending_requests.php?project_id=" + projectId;
    $("#requests-content").load(url, function(response, status, xhr) {
        if (status === "error") {
             console.error("AJAX Load Error: " + xhr.status + " " + xhr.statusText);
        } else {
             updateTimes(); // Re-initialize time update after load
        }
    });
});

// Approve / Reject requests with SweetAlert
$(document).on("click", ".approve-btn, .reject-btn", function() {
    let reqId = $(this).data("id");
    let action = $(this).hasClass("approve-btn") ? "accept" : "reject";
    let $card = $(this).closest(".request-card");
    
    // --- 🔑 NEW: Fetch the selected role from the card ---
    let selectedRole = '';
    if (action === 'accept') {
        const $roleSelect = $card.find(`#role-select-${reqId}`);
        selectedRole = $roleSelect.val();
        
        // Validation: If it's an approval and a role select exists, force selection
        if ($roleSelect.length && !selectedRole) {
            Swal.fire('Error', 'Please select a role to assign before approving the request.', 'warning');
            return; // Stop the action
        }
    }
    // --------------------------------------------------------

    Swal.fire({
        title: 'Are you sure?',
        text: `You are about to ${action} this request. ${selectedRole ? 'The role "' + selectedRole + '" will be assigned.' : ''}`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, ' + action,
        cancelButtonText: 'No',
    }).then((result) => {
        if (result.isConfirmed) {
            // Prepare data to send
            let postData = { id: reqId, action: action };
            if (action === 'accept' && selectedRole) {
                postData.assigned_role = selectedRole; // Send the selected role
            }
            
            // 🔑 IMPORTANT: Using 'json' dataType for automatic parsing to prevent the 'Unexpected response' error.
            $.post("include/host_sections/actions/handle_request.php", 
                        postData, // Send the new postData object
                        function(data) {
                
                if (data && data.success) {
                    Swal.fire('Success', data.message, 'success');
                    
                    // --- 🚀 REDIRECT / RELOAD LOGIC ---
                    if(action === 'accept' && data.group_id) {
                        const groupId = data.group_id;
                        
                        const historyUrl = `?section=group_chat&group_id=${groupId}`; 

                        if (typeof window.loadHostSection === 'function') {
                            // If using a custom function to load sections
                            history.pushState(null, '', historyUrl);
                            window.loadHostSection('group_chat', { group_id: groupId });
                        } else {
                            // Fallback: Full page redirect
                            window.location.href = historyUrl;
                        }
                        
                        $card.fadeOut(300, function(){ $(this).remove(); }); 
                        
                    } else {
                        // Reject or Accept without group_id (or if the list just needs to be updated)
                        $card.fadeOut(300, function(){ $(this).remove(); });
                        // Reload the list to update count
                        const currentProjectId = $('#project_id').val();
                        setTimeout(() => {
                            const url = "include/host_sections/pending_requests.php?project_id=" + currentProjectId;
                            $("#requests-content").load(url, function() { updateTimes(); });
                        }, 500);
                    }
                    
                } else {
                    Swal.fire('Error', data ? data.message : 'Unknown response error.', 'error');
                }
            }, 'json')
            .fail(function(xhr) {
                let errorMsg = 'Failed to communicate with the server.';
                // Handle JSON error response if available
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                Swal.fire('Error', errorMsg, 'error');
                console.error("AJAX Failed:", xhr.status, xhr.responseText);
            });
        }
    });
});

// 1:1 Chat button logic (Assumes you have a handler at 'ajax/ajax_get_or_create_chat.php')
$(document).on('click', '.chat-btn', function() {
    let participantId = $(this).data('participant-id');
    let projectId = $(this).data('project-id'); 
    
    Swal.fire({
        title: 'Starting Chat...',
        text: 'Creating or fetching the chat room...',
        icon: 'info',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => { Swal.showLoading(); }
    });
    
    $.post('ajax/ajax_get_or_create_chat.php', {
        host_id: <?= $host_id ?>,
        participant_id: participantId,
        project_id: projectId
    }, function(response) {
        Swal.close(); 
        if (response.success && response.room_id) {
            const roomId = response.room_id;
            const historyUrl = `?section=one_to_one_chat&room_id=${roomId}`;
            
            if (typeof window.loadHostSection === 'function') {
                history.pushState(null, '', historyUrl);
                window.loadHostSection('one_to_one_chat');
            } else {
                window.location.href = historyUrl;
            }
        } else {
            Swal.fire('Error', 'Failed to create or find 1:1 chat room: ' + (response.message || 'Unknown error'), 'error');
        }
    }, 'json').fail(function() {
        Swal.fire('Error', 'Communication failed with the chat server.', 'error');
    });
});

// View Profile Modal Logic
$(document).on('click', '.view-profile-btn', function() {
    const userId = $(this).data('user-id');
    const requestId = $(this).data('request-id'); 

    Swal.fire({
        title: 'Loading Profile...',
        html: '<div class="loader ease-linear rounded-full border-4 border-t-4 border-gray-200 h-12 w-12 mb-4"></div>',
        showConfirmButton: false,
        allowOutsideClick: true
    });

    $.ajax({
        url: 'ajax/ajax_fetch_participant_profile.php',
        type: 'GET',
        data: { user_id: userId, request_id: requestId },
        success: function(response) {
            Swal.fire({
                title: 'Participant Profile & Request Details',
                html: response,
                showCloseButton: true,
                focusConfirm: false,
                width: '850px', 
                confirmButtonText: 'Close',
                customClass: {
                    container: 'my-swal-container', 
                    htmlContainer: 'text-left' 
                }
            });
        },
        error: function(xhr) {
            Swal.fire('Error', 'Failed to load profile details. Status: ' + xhr.status, 'error');
        }
    });
});
</script>