<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$current_user_id = $current_user_id ?? ($_SESSION['user_id'] ?? 0);

if ($current_user_id <= 0) {
    echo '<p class="p-4 text-red-500">Authentication error. User ID missing.</p>';
    exit;
}

if (!function_exists('get_db_connection')) {
    require_once __DIR__ . '/../../include/config.php'; 
}
$conn = $conn ?? get_db_connection();

$sql = "SELECT project_id, title FROM projects WHERE host_id = ? AND status = 'In Progress'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $current_user_id);

if (!$stmt->execute()) {
    echo '<p class="p-4 text-red-500">Database Error: Could not fetch projects.</p>';
    exit;
}

$result = $stmt->get_result();
$in_progress_projects = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="p-6 bg-gray-50 min-h-screen">
    <h1 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-2">Task Management Dashboard</h1>

    <div id="project-list" class="space-y-6">
        <?php if (empty($in_progress_projects)): ?>
            <p class="text-lg text-gray-500">No active projects to manage tasks for.</p>
        <?php else: ?>
            <?php foreach ($in_progress_projects as $project): ?>
                <div class="bg-white p-5 rounded-lg shadow-lg border border-gray-100">
                    <h2 class="text-xl font-semibold text-indigo-700 mb-3"><?= htmlspecialchars($project['title']) ?> (ID: <?= $project['project_id'] ?>)</h2>
                    
                    <button 
                        class="assign-task-btn bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition duration-150 shadow-md"
                        data-project-id="<?= $project['project_id'] ?>"
                        data-project-title="<?= htmlspecialchars($project['title']) ?>">
                        <i class="fas fa-plus mr-2"></i> Assign New Task
                    </button>

                    <div id="tasks-list-<?= $project['project_id'] ?>" class="mt-4 border-t pt-4">
                        
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="taskModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg">
        <div class="flex justify-between items-center p-5 border-b">
            <h3 class="text-xl font-semibold text-gray-800">Assign Task to <span id="modalProjectTitle" class="text-indigo-600"></span></h3>
            <button onclick="closeTaskModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <form id="taskAssignmentForm">
            <input type="hidden" name="action" value="add_task">
            <input type="hidden" id="taskProjectId" name="project_id">
            
            <div class="p-5 space-y-4">
                
                <div>
                    <label for="taskTitle" class="block text-sm font-medium text-gray-700">Task Title <span class="text-red-500">*</span></label>
                    <input type="text" id="taskTitle" name="task_title" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                </div>
                
                <div>
                    <label for="assignedUserId" class="block text-sm font-medium text-gray-700">Assign To <span class="text-red-500">*</span></label>
                    <select id="assignedUserId" name="assigned_user_id" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 bg-white">
                        <option value="">Loading Participants...</option>
                    </select>
                </div>

                <div>
                    <label for="dueDate" class="block text-sm font-medium text-gray-700">Due Date <span class="text-red-500">*</span></label>
                    <input type="date" id="dueDate" name="due_date" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" min="<?= date('Y-m-d') ?>">
                </div>

                <div class="flex space-x-4">
                    <div class="flex-1">
                        <label for="priority" class="block text-sm font-medium text-gray-700">Priority</label>
                        <select id="priority" name="priority" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 bg-white">
                            <option value="Medium">Medium</option>
                            <option value="Critical">Critical</option>
                            <option value="High">High</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea id="description" name="description" rows="3" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2"></textarea>
                </div>
            </div>

            <div class="p-5 border-t flex justify-end space-x-3">
                <button type="button" onclick="closeTaskModal()" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-300 transition duration-150">Cancel</button>
                <button type="submit" id="saveTaskBtn" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition duration-150 shadow-md">Assign Task</button>
            </div>
        </form>
    </div>
</div>


<script>
$(document).ready(function() {
    
    function showSuccessToast(message) {
        console.log("SUCCESS: " + message);
        alert(message);
    }

    function closeTaskModal() {
        $('#taskModal').addClass('hidden');
        $('#taskAssignmentForm')[0].reset();
    }
    
    function renderTaskHtml(task) {
        let priorityClass = '';
        switch (task.priority) {
            case 'Critical': priorityClass = 'bg-red-500'; break;
            case 'High': priorityClass = 'bg-yellow-500'; break;
            case 'Medium': priorityClass = 'bg-blue-500'; break;
            case 'Low': priorityClass = 'bg-gray-400'; break;
            default: priorityClass = 'bg-gray-400';
        }
        
        let statusClass = '';
        switch (task.status) {
            case 'Pending': statusClass = 'bg-indigo-500'; break;
            case 'In Progress': statusClass = 'bg-yellow-600'; break;
            case 'Completed': statusClass = 'bg-green-600'; break;
            default: statusClass = 'bg-gray-400';
        }

        return `
            <div class="flex items-center justify-between p-3 mb-2 bg-white border border-gray-100 rounded-md shadow-sm">
                <div class="flex-1 min-w-0 mr-4">
                    <p class="text-sm font-medium text-gray-900 truncate">${task.task_title}</p>
                    <div class="flex items-center mt-1 text-xs text-gray-500 space-x-2">
                        <span class="flex items-center">
                            <i class="fas fa-user-tag mr-1"></i> Assigned to: ${task.assigned_to_name || 'N/A'}
                        </span>
                        <span class="text-gray-400">|</span>
                        <span class="flex items-center">
                            <i class="fas fa-clock mr-1"></i> Due: ${task.due_date || 'N/A'}
                        </span>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full text-white ${priorityClass}">
                        ${task.priority}
                    </span>
                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full text-white ${statusClass}">
                        ${task.status}
                    </span>
                </div>
            </div>
        `;
    }
    
    function fetchProjectParticipants(projectId) {
        const $select = $('#assignedUserId');
        $select.html('<option value="">Loading...</option>').prop('disabled', true);

        $.ajax({
            url: 'ajax/ajax_task_actions.php',
            method: 'GET',
            dataType: 'json',
            data: { action: 'fetch_participants', project_id: projectId },
            success: function(response) {
                $select.empty().prop('disabled', false);
                if (response.success && response.participants.length > 0) {
                    $select.append('<option value="">-- Select Participant --</option>');
                    response.participants.forEach(p => {
                        $select.append(`<option value="${p.participant_id}">${p.full_user_name} (${p.role_taken})</option>`);
                    });
                } else {
                    $select.html('<option value="">No participants found</option>');
                }
            },
            error: function(xhr) {
                console.error("Error fetching participants:", xhr.responseText);
                $select.html('<option value="">Error loading participants</option>').prop('disabled', true);
            }
        });
    }

    function fetchTasksForProject(projectId) {
        const $taskListDiv = $(`#tasks-list-${projectId}`);
        
        // ലോഡിങ് തുടങ്ങുമ്പോൾ സ്പിന്നർ കാണിക്കുന്നു
        $taskListDiv.html('<p class="text-gray-500 italic"><i class="fas fa-circle-notch fa-spin mr-1"></i> Checking for tasks...</p>'); 

        $.ajax({
            url: 'ajax/ajax_fetch_tasks.php', 
            method: 'GET',
            dataType: 'json',
            data: { action: 'fetch_tasks', project_id: projectId },
            success: function(response) {
                if (response.success) {
                    if (response.tasks && response.tasks.length > 0) {
                        let tasksHtml = '';
                        response.tasks.forEach(task => {
                            tasksHtml += renderTaskHtml(task);
                        });
                        $taskListDiv.html('<p class="text-xs font-semibold text-gray-500 mb-2 border-b pb-1">Current Tasks:</p><div class="space-y-2">' + tasksHtml + '</div>');
                    } else {
                        // ടാസ്‌ക്കുകൾ ഇല്ലെങ്കിൽ, സ്പിന്നർ മാറ്റി ഈ മെസ്സേജ് കാണിക്കുന്നു
                        $taskListDiv.html('<p class="text-gray-500 italic pt-2"><i class="fas fa-info-circle mr-1"></i> No tasks assigned to this project yet.</p>');
                    }
                } else {
                    // PHP കോഡിൽ നിന്ന് error ലഭിച്ചാൽ, സ്പിന്നർ മാറ്റി എറർ കാണിക്കുന്നു
                    $taskListDiv.html('<p class="text-red-500">Error loading tasks: ' + (response.error || 'Server returned an error.') + '</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error("Error fetching tasks:", error, "Response Text:", xhr.responseText);
                // AJAX കോൾ പരാജയപ്പെട്ടാൽ, സ്പിന്നർ മാറ്റി എറർ കാണിക്കുന്നു
                let errorMessage = `AJAX Failed. Status: ${xhr.status}. Check console for details.`;
                if (xhr.status === 404) {
                    errorMessage = 'Task fetch URL not found (404). Check ajax/ajax_fetch_tasks.php path.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server Error (500). Check PHP logs in ajax/ajax_fetch_tasks.php.';
                }
                $taskListDiv.html('<p class="text-red-500"><i class="fas fa-exclamation-triangle mr-1"></i> ' + errorMessage + '</p>');
            }
        });
    }

    $('.assign-task-btn').each(function() {
        const projectId = $(this).data('project-id');
        fetchTasksForProject(projectId);
    });

    $('.assign-task-btn').on('click', function() {
        const projectId = $(this).data('project-id');
        const projectTitle = $(this).data('project-title');
        
        $('#taskProjectId').val(projectId);
        $('#modalProjectTitle').text(projectTitle);
        fetchProjectParticipants(projectId);
        
        $('#taskModal').removeClass('hidden');
    });

    $('#taskAssignmentForm').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $btn = $('#saveTaskBtn');
        const originalText = $btn.text();
        
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Saving...');
        
        $.ajax({
            url: 'ajax/ajax_task_actions.php',
            method: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showSuccessToast('Task assigned successfully!'); 
                    closeTaskModal();
                    
                    const projectId = $('#taskProjectId').val();
                    fetchTasksForProject(projectId); 

                } else {
                    alert('Failed to assign task: ' + (response.error || 'Unknown error.'));
                }
            },
            error: function(xhr, status, error) {
                console.error("Task assignment error:", error, xhr.responseText);
                
                let errorText = 'Unknown error occurred.';
                try {
                    const responseJson = JSON.parse(xhr.responseText);
                    errorText = responseJson.error || xhr.responseText;
                } catch (e) {
                    errorText = xhr.responseText.substring(0, 150) + '...';
                }
                
                alert('An error occurred during task assignment. Status: ' + xhr.status + '. Details: ' + errorText);
            },
            complete: function() {
                $btn.prop('disabled', false).text(originalText);
            }
        });
        
    });
});
</script>