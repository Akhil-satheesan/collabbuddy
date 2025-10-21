<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$current_user_id = $current_user_id ?? ($_SESSION['user_id'] ?? 0);
$current_user_role = $_SESSION['role'] ?? 'guest';

if ($current_user_id <= 0 || $current_user_role !== 'participant') {
    echo '<p class="p-4 text-red-500">Authentication error. Access denied for this role.</p>';
    exit;
}

// config.php ഫയൽ ആവശ്യമുണ്ടെങ്കിൽ ഇവിടെ ചേർക്കുക
if (!function_exists('get_db_connection')) {
    require_once __DIR__ . '/../../include/config.php'; 
}
?>

<div class="p-6 bg-gray-50 min-h-screen">
    <h1 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-2">My Assigned Tasks</h1>

    <div id="tasks-list-container" class="space-y-6">
        </div>
</div>

<div id="statusModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-sm">
        <div class="flex justify-between items-center p-5 border-b">
            <h3 class="text-xl font-semibold text-gray-800">Update Task Status</h3>
            <button onclick="closeStatusModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <form id="statusUpdateForm">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" id="modalTaskId" name="task_id">
            
            <div class="p-5 space-y-4">
                <p class="text-gray-700">Task: <strong id="modalTaskTitle" class="text-indigo-600"></strong></p>
                
                <div>
                    <label for="newStatus" class="block text-sm font-medium text-gray-700">New Status</label>
                    <select id="newStatus" name="status" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 bg-white">
                        <option value="To Do">To Do</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="Blocked">Blocked</option>
                    </select>
                </div>
            </div>

            <div class="p-5 border-t flex justify-end space-x-3">
                <button type="button" onclick="closeStatusModal()" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-300 transition duration-150">Cancel</button>
                <button type="submit" id="saveStatusBtn" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition duration-150 shadow-md">Save Status</button>
            </div>
        </form>
    </div>
</div>


<script>
$(document).ready(function() {
    
    const currentUserId = <?= $current_user_id ?>;
    
    function closeStatusModal() {
        $('#statusModal').addClass('hidden');
    }
    window.closeStatusModal = closeStatusModal; 
    
    function getRemainingDays(dueDate) {
        if (!dueDate || dueDate === '0000-00-00') {
            return '<span class="text-gray-500">No Deadline</span>';
        }
        
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const due = new Date(dueDate);
        due.setHours(0, 0, 0, 0);
        
        const diffTime = due - today;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        let colorClass = 'text-gray-600';
        let text = `${diffDays} days left`;

        if (diffDays === 0) {
            colorClass = 'text-red-600 font-bold';
            text = 'DUE TODAY!';
        } else if (diffDays < 0) {
            colorClass = 'text-red-700 font-bold';
            text = `OVERDUE (${Math.abs(diffDays)} days)`;
        } else if (diffDays <= 3) {
            colorClass = 'text-yellow-600 font-semibold';
        }

        return `<span class="${colorClass}">${text}</span>`;
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
            case 'To Do': statusClass = 'bg-indigo-500'; break;
            case 'In Progress': statusClass = 'bg-yellow-600'; break;
            case 'Completed': statusClass = 'bg-green-600'; break;
            case 'Blocked': statusClass = 'bg-red-700'; break;
            default: statusClass = 'bg-gray-400';
        }

        return `
            <div class="bg-white p-4 rounded-lg shadow-md border border-gray-100 flex items-center justify-between" data-task-id="${task.task_id}">
                <div class="flex-1 min-w-0 mr-4">
                    <p class="text-lg font-semibold text-gray-900">${task.task_title}</p>
                    <p class="text-sm text-gray-500 mb-2">Project: ${task.project_title}</p>
                    
                    <div class="flex items-center text-sm space-x-4">
                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full text-white ${priorityClass}">
                            ${task.priority} Priority
                        </span>
                        <span class="text-gray-400">|</span>
                        <span class="text-sm">
                            <i class="fas fa-calendar-alt mr-1"></i> Deadline: ${task.due_date || 'N/A'} 
                            (<span class="days-left">${getRemainingDays(task.due_date)}</span>)
                        </span>
                    </div>
                </div>
                
                <div class="flex flex-col items-end space-y-2">
                    <span class="px-3 py-1 text-sm font-bold rounded-full text-white ${statusClass} cursor-pointer update-status-btn"
                          data-task-id="${task.task_id}" 
                          data-task-title="${task.task_title}"
                          data-current-status="${task.status}">
                        ${task.status}
                    </span>
                </div>
            </div>
        `;
    }
    
    function fetchMyTasks() {
        const $listContainer = $('#tasks-list-container');
        
        // ലോഡിങ് സ്പിന്നർ
        $listContainer.html('<div class="text-center p-8 text-gray-500"><i class="fas fa-circle-notch fa-spin mr-2"></i> Loading your tasks...</div>');

        $.ajax({
            url: 'ajax/ajax_participant_tasks.php', 
            method: 'GET',
            dataType: 'json',
            data: { action: 'fetch_my_tasks', user_id: currentUserId },
            success: function(response) {
                if (response.success) {
                    if (response.tasks && response.tasks.length > 0) {
                        let tasksHtml = '';
                        response.tasks.forEach(task => {
                            tasksHtml += renderTaskHtml(task);
                        });
                        $listContainer.html(tasksHtml);
                    } else {
                        $listContainer.html('<div class="p-8 text-center text-lg text-gray-500"><i class="fas fa-check-circle mr-2"></i> Congratulations! You have no pending tasks.</div>');
                    }
                } else {
                    $listContainer.html('<div class="p-8 text-center text-red-500">Error loading tasks: ' + (response.error || 'Server returned an error.') + '</div>');
                }
            },
            error: function(xhr) {
                console.error("Error fetching tasks:", xhr.responseText);
                $listContainer.html('<div class="p-8 text-center text-red-500">AJAX Request Failed. Could not load tasks.</div>');
            }
        });
    }

    // Task Status Update Button Click Handler
    $(document).on('click', '.update-status-btn', function() {
        const taskId = $(this).data('task-id');
        const taskTitle = $(this).data('task-title');
        const currentStatus = $(this).data('current-status');
        
        $('#modalTaskId').val(taskId);
        $('#modalTaskTitle').text(taskTitle);
        $('#newStatus').val(currentStatus); // Set current status in select field
        
        $('#statusModal').removeClass('hidden');
    });

    // Task Status Update Form Submission
    $('#statusUpdateForm').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $btn = $('#saveStatusBtn');
        const originalText = $btn.text();
        
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Saving...');
        
        $.ajax({
            url: 'ajax/ajax_participant_tasks.php',
            method: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Status updated successfully!'); 
                    closeStatusModal();
                    fetchMyTasks(); // Reload tasks to show new status

                } else {
                    alert('Failed to update status: ' + (response.error || 'Unknown error.'));
                }
            },
            error: function(xhr) {
                console.error("Status update error:", xhr.responseText);
                alert('An error occurred during status update. Status: ' + xhr.status);
            },
            complete: function() {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });

    // Load tasks on page load
    fetchMyTasks();
});
</script>