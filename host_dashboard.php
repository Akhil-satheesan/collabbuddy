<?php
// FILE: C:\xampp\htdocs\collabuddy\host_dashboard.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'include/config.php'; 
// Ensure user is logged in and is a host
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host') {
    header("Location: login.php?role=host");
    exit;
}

// --- Menu Configuration ---
$menu = [
    'dashboard' => ['icon'=>'📊','title'=>'Dashboard','desc'=>'Overview & stats'],
    'my_projects' => ['icon'=>'📂','title'=>'My Projects','desc'=>'Manage posted projects'],
    'team_status' => ['icon'=>'🏗️','title'=>'Team Status','desc'=>'Track team building & roles fulfillment'], 
    'pending_requests'=> ['icon'=>'⏳','title'=>'Pending Requests','desc'=>'Applications & invites'],
    'group_chat' => ['icon'=>'👥','title'=>'Project Groups','desc'=>'Team communication'],
    'one_to_one_chat' => ['icon'=>'💬','title'=>'Applicant Chats','desc'=>'1:1 Application Q&A'],
    'team_members'=> ['icon'=>'👥','title'=>'Team Members','desc'=>'Manage your team'],
    'reports'=> ['icon'=>'📈','title'=>'Reports','desc'=>'Analytics & insights'],
    'profile' => ['icon'=>'👤','title'=>'Profile','desc'=>'Manage account details'],
   // 'my_tasks' => ['icon'=>'🗂️','title'=>'My Tasks','desc'=>'My assigned tasks on my project','badge'=>0], // Badge will be updated dynamically
    
];

$section = $_GET['section'] ?? 'dashboard';
$allowed_sections = array_keys($menu); 
if (!in_array($section, $allowed_sections)) $section = 'dashboard';

$toast_message = $_SESSION['toast']['message'] ?? null;
$toast_type = $_SESSION['toast']['type'] ?? null;
if (isset($_SESSION['toast'])) unset($_SESSION['toast']);

$current_title = $menu[$section]['title'] ?? 'Dashboard';
$current_desc = $menu[$section]['desc'] ?? 'Overview & stats';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CollabBuddy - Host Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>

<style>
    .toast-fixed { position: fixed; top: 1.25rem; right: 1.25rem; z-index: 50; transition: transform 0.3s ease-out, opacity 0.3s ease-out; }
    .toast-hidden { transform: translateX(100%); opacity: 0; }
    #projectSlidePanel, #projectDetailsPanel { transition: transform 0.3s ease-out; } 
    #panelOverlay { transition: opacity 0.3s ease; }
    .nav-item.active { background-color: #f0f4ff; border: 1px solid #c7d2fe; color: #4f46e5; font-weight: 600; box-shadow: 0 2px 5px rgba(79, 70, 229, 0.1); }
    .content-area-height { min-height: calc(100vh - 180px); }
</style>
</head>
<body class="bg-gray-50 min-h-screen">


<?php 
include 'include/header_host.php'; 
?> 

<div id="panelOverlay" class="fixed inset-0 bg-black bg-opacity-40 hidden z-40"></div>

<div id="projectSlidePanel" class="fixed top-0 right-0 w-full md:w-[600px] h-full bg-white shadow-2xl z-50 transform translate-x-full overflow-y-auto">
    <?php include 'include/host_sections/create_project.php'; ?>
</div>


<div id="projectDetailsPanel" class="fixed top-0 right-0 w-full md:w-[600px] h-full bg-white shadow-2xl z-50 transform translate-x-full overflow-y-auto">
    </div>

<div class="flex h-screen overflow-hidden">
    <aside class="w-80 bg-white border-r border-gray-200 flex flex-col shadow-lg z-30 overflow-y-auto">
    <div class="p-6 border-b">
    <h2 class="text-xl font-bold text-gray-900 mb-1"> <?= htmlspecialchars($display_name) ?></h2>
    <div class="flex items-center space-x-2 text-sm">
                <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-medium">🟢 Online Host</span>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto bg-white">
            <nav class="p-4 space-y-2">
                <?php
                foreach ($menu as $key => $item) {
                    $isActive = ($section === $key) ? 'active' : 'hover:bg-gray-100 text-gray-700';
                    $badge = isset($item['badge']) 
                        ? "<span class='bg-red-500 text-white px-2 py-1 rounded-full text-xs font-semibold'>{$item['badge']}</span>" 
                        : '';
                    
                    echo "<div class='nav-item w-full flex items-center space-x-3 p-3 rounded-xl transition-colors text-left $isActive cursor-pointer' data-section='$key'>
                                <span class='text-xl'>{$item['icon']}</span>
                                <div class='flex-1'>
                                    <p class='font-medium text-gray-800'>{$item['title']}</p>
                                    <p class='text-xs text-gray-500'>{$item['desc']}</p>
                                </div>
                                {$badge}
                            </div>";
                }
                ?>
            </nav>
        </div>
    </aside>
    <div id="customToast" class="toast-fixed toast-hidden px-6 py-3 rounded-xl text-white font-semibold shadow-2xl"></div>

    <div class="flex-1 flex flex-col overflow-hidden">
        
        <div id="sectionHeader" class="p-6 border-b border-gray-200 active-header sticky top-0 z-10 bg-white">
            <h1 class="text-3xl font-extrabold tracking-tight text-indigo-700">
                <?= htmlspecialchars($current_title) ?>
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                <?= htmlspecialchars($current_desc) ?>
            </p>
        </div>

        <div id="mainContent" class="p-6 flex-1 overflow-y-auto bg-gray-50 content-area-height">
        </div>
    </div>
</div>
<div id="notification-container" class="fixed bottom-4 right-4 z-[100] space-y-2">
</div>

<?php 
include 'include/host_sections/create_project.php'; 
?>


<script>
// --- Global Functions ---
function showToast(message, type='success') {
    const toast = $('#customToast');
    toast.text(message);
    toast.removeClass('bg-green-600 bg-red-600').addClass(type === 'success' ? 'bg-green-600' : 'bg-red-600');
    toast.removeClass('toast-hidden').css('transform', 'translateX(0)').css('opacity', 1);
    setTimeout(() => { 
        toast.css('transform', 'translateX(100%)').css('opacity', 0);
        setTimeout(() => toast.addClass('toast-hidden'), 300); 
    }, 3500); 
}
// host_dashboard.php (Global Functions സെക്ഷനിൽ ചേർക്കുക)

// =========================================================================
// 🧹 CHAT INTERVAL CLEAR FUNCTION (Global Access Point for Group/Private Chat)
// =========================================================================
window.clearHostChatInterval = function() {
    // group_chat_display.php ൽ നിർവചിച്ചിട്ടുള്ള hostChatInterval ക്ലിയർ ചെയ്യുന്നു.
    if (typeof window.hostChatInterval !== 'undefined' && window.hostChatInterval !== null) { 
        clearInterval(window.hostChatInterval);
        window.hostChatInterval = null;
    }
    // private_chat_display.php ൽ നിർവചിച്ചിട്ടുള്ള privateChatInterval ക്ലിയർ ചെയ്യുന്നു.
    if (typeof window.privateChatInterval !== 'undefined' && window.privateChatInterval !== null) { 
        clearInterval(window.privateChatInterval);
        window.privateChatInterval = null;
    }
};
// --- End CHAT INTERVAL CLEAR FUNCTION ---
function showLoading(sectionKey) {
    const formattedName = sectionKey.replace(/_/g, ' ');
    return `
        <div class="flex justify-center items-center content-area-height">
            <div class="flex flex-col items-center space-y-4">
                <div class="animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-indigo-600"></div>
                <p class="text-gray-600 font-medium text-lg">Loading ${formattedName}...</p>
            </div>
        </div>
    `;
}

function loadFilteredProjects(status) {
    $('#mainContent').html(showLoading('my projects'));
    $.ajax({
        url: 'ajax_load_host_section.php', 
        type: 'GET',
        data: { section: 'my_projects', filter_status: status },
        success: function(response){
            $('#mainContent').html(response);
            const projectListElement = document.getElementById('project-list');
            if (projectListElement) {
                setTimeout(() => {
                    projectListElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 100); 
            }
            history.pushState(null, '', '?section=my_projects');
        },
        error: function(xhr){
            $('#mainContent').html('<p class="text-red-600 p-6">❌ Error filtering projects: ' + (xhr.statusText || 'Unknown Error') + '</p>');
        }
    });
}
// host_dashboard.php (Add this inside the <script> tags)

// =========================================================================
// 🚀 TASK MANAGER LOADER FUNCTION
// =========================================================================
function loadManageTasksPage(projectId) {
    // task_management is the section key used in ajax_load_host_section.php
    const sectionKey = 'task_management'; 
    
    // 1. Update Header 
    $('#sectionHeader h1').text('Task Management');
    $('#sectionHeader p').text('Assign, track, and manage tasks for the selected project.');
    
    // 2. Show Loading
    $('#mainContent').html(showLoading('task management')); 

    // 3. Load content via AJAX
    $.ajax({
        url: 'ajax_load_host_section.php', 
        type: 'GET',
        // Note the section key and the project_id parameter
        data: { section: sectionKey, project_id: projectId }, 
        success: function(response){
            $('#mainContent').html(response);
            // Highlight 'My Projects' in the sidebar
            $('.nav-item').removeClass('active');
            $(".nav-item[data-section='my_projects']").addClass('active');
            // Update URL
            history.pushState(null, '', `?section=my_projects&view=tasks&project_id=${projectId}`); 
        },
        error: function(xhr){
            $('#mainContent').html('<p class="text-red-600 p-6">❌ Error loading tasks: ' + (xhr.statusText || 'Unknown Error') + '</p>');
        }
    });
}
// =========================================================================
function closeProjectPanel() {
    $('#projectSlidePanel').addClass('translate-x-full');
    if ($('#projectDetailsPanel').hasClass('translate-x-full')) {
        $('#panelOverlay').addClass('hidden');
    }
}
// --- End Global Functions ---

// =========================================================================
// 🔄 PROJECT UPDATE HANDLER & CLOSER
// =========================================================================
function closeDetailsPanel() {
    $('#projectDetailsPanel').addClass('translate-x-full');
    if ($('#projectSlidePanel').hasClass('translate-x-full')) {
        $('#panelOverlay').addClass('hidden');
    }
    window.loadHostSection('my_projects'); 
}

function handleProjectUpdate(event) {
    event.preventDefault(); 
    const form = document.getElementById('projectDetailsForm');
    if (!form) { showToast('Form submission error: Form ID missing.', 'error'); return; }
    
    const formData = new FormData(form);
    const messageDiv = $('#project-update-message'); 

    const saveButton = $(form).find('button[type="submit"]');
    saveButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Saving...');
    messageDiv.addClass('hidden');
    
    $.ajax({
        url: 'include/host_sections/ajax_update_project.php', 
        type: 'POST',
        data: formData,
        processData: false, 
        contentType: false, 
        dataType: 'json',   
        success: function(data) {
            if (data.success) {
                showToast(data.message || 'Project updated successfully!', 'success');
                closeDetailsPanel(); 
            } else {
                messageDiv.text('❌ Update Failed: ' + data.message)
                             .removeClass('hidden').addClass('bg-red-100 text-red-700');
            }
        },
        error: function(xhr) {
            showToast('❌ Failed: Server or path error. Check console.', 'error');
            messageDiv.text('An unexpected error occurred: ' + (xhr.statusText || 'Unknown'))
                        .removeClass('hidden').addClass('bg-red-100 text-red-700');
        },
        complete: function() {
            saveButton.prop('disabled', false).html('Save Updates');
        }
    });
}
// =========================================================================


$(document).ready(function(){
    
    var initialSection = '<?= $section ?>';
    const menuData = <?= json_encode($menu) ?>;

    <?php if ($toast_message): ?>
        showToast("<?= htmlspecialchars($toast_message) ?>", "<?= htmlspecialchars($toast_type) ?>");
    <?php endif; ?>
    
    function updateHeader(sectionKey) {
        const item = menuData[sectionKey];
        if (item) {
            $('#sectionHeader h1').text(item.title);
            $('#sectionHeader p').text(item.desc);
        }
    }
    // host_dashboard.php (Add this inside $(document).ready function)

// =========================================================================
// 🛠️ HANDLER: MANAGE TASKS CLICK
// =========================================================================
$('#mainContent').on('click', '.load-manage-tasks-btn', function(e) { 
    e.preventDefault(); 
    const projectId = $(this).data('project-id');
    
    if (projectId) {
        // Call the new function
        loadManageTasksPage(projectId);
    } else {
        showToast('Error: Project ID missing for task management.', 'error');
    }
});
// =========================================================================

    // =========================================================================
    // 🔑 CORE AJAX SECTION LOADER 
    // =========================================================================
   /* window.loadHostSection = function(sectionKey, isInitialLoad = false) { 
        $('.nav-item').removeClass('active');
        const activeSidebarKey = (sectionKey === 'change_password') ? 'profile' : sectionKey;
        $(".nav-item[data-section='" + activeSidebarKey + "']").addClass('active');

        if (!isInitialLoad) {
            let historyUrl = '?section=' + sectionKey;
            // Append room_id if it's a chat section to maintain the view
            if (sectionKey === 'one_to_one_chat' || sectionKey === 'group_chat') {
                const currentUrlParams = new URLSearchParams(window.location.search);
                const currentRoomId = currentUrlParams.get('room_id');
                if (currentRoomId) {
                    historyUrl += '&room_id=' + currentRoomId;
                }
            }
            history.pushState(null, '', historyUrl);
        }
        updateHeader(sectionKey);
        $('#mainContent').html(showLoading(sectionKey)); 
        
        const ajaxData = { section: sectionKey };
        const urlParams = new URLSearchParams(window.location.search);
        
        // Pass room_id for chat sections
        if (sectionKey === 'one_to_one_chat' || sectionKey === 'group_chat') {
            const roomId = urlParams.get('room_id');
            if (roomId) {
                ajaxData.room_id = roomId;
            }
        }
        
        if (sectionKey === 'my_projects') {
            ajaxData.filter_status = urlParams.get('filter_status') || 'all'; 
        }

        $.ajax({
            url: 'ajax_load_host_section.php', 
            type: 'GET',
            data: ajaxData,
            success: function(response){
                $('#mainContent').html(response);
            },
            error: function(xhr){
                $('#mainContent').html('<p class="text-red-600 p-6">❌ Error loading section: ' + (xhr.statusText || 'Unknown Error') + '</p>');
            }
        });
    };*/
    // =========================================================================
    // host_dashboard.php (replace the existing loadHostSection function)
// =========================================================================
// 🔑 CORE AJAX SECTION LOADER (Updated for Chat parameters)
// =========================================================================
window.loadHostSection = function(sectionKey, params = {}, isInitialLoad = false) { 
    
    // Clear the current chat interval if changing sections
    if (typeof window.clearHostChatInterval === 'function' && !isInitialLoad) {
        window.clearHostChatInterval();
    }
    
    $('.nav-item').removeClass('active');
    const activeSidebarKey = (sectionKey === 'change_password') ? 'profile' : sectionKey;
    $(".nav-item[data-section='" + activeSidebarKey + "']").addClass('active');

    const ajaxData = { section: sectionKey, ...params }; // Start with basic data and any explicit parameters

    // URL പാരാമീറ്ററുകൾ എടുക്കാൻ
    const urlParams = new URLSearchParams(window.location.search);
    let historyUrl = '?section=' + sectionKey;

    if (sectionKey === 'one_to_one_chat') {
        // 1:1 ചാറ്റിൽ 'room_id' ഉപയോഗിക്കുന്നു
        const currentRoomId = urlParams.get('room_id') || params.room_id;
        if (currentRoomId) {
            ajaxData.room_id = currentRoomId;
            historyUrl += '&room_id=' + currentRoomId;
        }
    } else if (sectionKey === 'group_chat') {
        // ഗ്രൂപ്പ് ചാറ്റിൽ 'group_id' ഉപയോഗിക്കുന്നു
        const currentGroupId = urlParams.get('group_id') || params.group_id;
        if (currentGroupId) {
            ajaxData.group_id = currentGroupId;
            historyUrl += '&group_id=' + currentGroupId;
        }
    }
    
    if (sectionKey === 'my_projects') {
        const filterStatus = urlParams.get('filter_status') || params.filter_status || 'all';
        ajaxData.filter_status = filterStatus; 
        if (filterStatus !== 'all') historyUrl += '&filter_status=' + filterStatus;
    }

    if (!isInitialLoad) {
        history.pushState(null, '', historyUrl);
    }
    
    updateHeader(sectionKey);
    $('#mainContent').html(showLoading(sectionKey)); 

    $.ajax({
        url: 'ajax_load_host_section.php', 
        type: 'GET',
        data: ajaxData,
        success: function(response){
            $('#mainContent').html(response);
        },
        error: function(xhr){
            $('#mainContent').html('<p class="text-red-600 p-6">❌ Error loading section: ' + (xhr.statusText || 'Unknown Error') + '</p>');
        }
    });
};
// =========================================================================

    // =========================================================================
    // 🚨 Project Details Panel Loader (Manage/Edit Click) - Main fix
    // =========================================================================
    $('#mainContent').on('click', '.edit-project-btn', function(e) { 
        e.preventDefault(); 
        
        const projectId = $(this).data('project-id');
        
        if (!projectId) {
            showToast('Error: Project ID is missing.', 'error');
            return;
        }

        // 1. Show Loading and Panel
        $('#projectDetailsPanel').html(showLoading('project details')); 
        $('#projectDetailsPanel').removeClass('translate-x-full');
        $('#panelOverlay').removeClass('hidden');

        // 2. AJAX Call
        $.ajax({
            url: 'ajax_load_host_section.php', 
            type: 'GET',
            data: { section: 'project_details', project_id: projectId },
            success: function(response) {
                $('#projectDetailsPanel').html(response);
            },
            error: function(xhr) {
                $('#projectDetailsPanel').html('<div class="p-6 text-red-600">❌ Failed to load project details: ' + (xhr.statusText || 'Unknown Error') + '</div>');
            }
        });
    });

    // 🚪 Project Panels Closer (Handles both Project Details and Project Slide/Create Panel)
    $(document).on('click', '#closeDetailsPanelBtn, #cancelDetailsBtn, #panelOverlay', function(e) {
        
        if ($(e.target).is('#panelOverlay') || $(e.target).closest('#closeDetailsPanelBtn').length || $(e.target).closest('#cancelDetailsBtn').length) {
            
            $('#projectDetailsPanel').addClass('translate-x-full');
            $('#projectSlidePanel').addClass('translate-x-full');
            
            $('#panelOverlay').addClass('hidden');

            if ($(e.target).closest('#projectDetailsPanel').length) {
                window.loadHostSection('my_projects');
            }
        }
    });

    // --- Other Handlers ---
    $('#mainContent').on('click', '.ajax-project-filter', function(e) {
        e.preventDefault();
        const status = $(this).data('filter-value'); 
        loadFilteredProjects(status);
    });

    $('.nav-item').click(function(e){ 
        e.preventDefault(); 
        const section = $(this).data('section');
        window.loadHostSection(section);
    });

    // --- Initial Load ---
    window.loadHostSection(initialSection, true);

    // --- Handle browser back/forward buttons (Optional) ---
    window.onpopstate = function() {
        var params = new URLSearchParams(window.location.search);
        var sec = params.get('section') || 'dashboard';
        window.loadHostSection(sec);
    };
    
    // --- Chip Logic (Included as requested, though usually not needed here) ---
    $(document).on('click', '.suggested-chip', function() {
        const chipType = $(this).data('input'); 
        const value = $(this).data('value'); 
        
        let message = ''; 
        let toastType = 'success';

        if (chipType === 'roles') {
            const role = $(this).data('role'); 
            const count = $(this).data('count'); 
            
            let currentRoles = $('#finalRolesInput').val().split(',').map(r => r.trim()).filter(r => r);
            
            if (!currentRoles.includes(role)) {
                currentRoles.push(role);
                $('#finalRolesInput').val(currentRoles.join(', '));
                message = `${role} Added to Required Roles.`;

                let currentTeamSizes = $('#finalTeamSizeInput').val().split(',').map(r => r.trim()).filter(r => r);
                const teamSizeEntry = `${role}: ${count}`;
                
                let newTeamSizes = currentTeamSizes.filter(entry => !entry.startsWith(`${role}:`));
                newTeamSizes.push(teamSizeEntry);
                $('#finalTeamSizeInput').val(newTeamSizes.join(', '));
                message += ` (Default count: ${count} Added into Team Size)`;
            } else {
                message = `${role} Already added to Required Roles.`;
                toastType = 'info';
            }
            
        } else if (chipType === 'skills') {
            let currentSkills = $('#finalSkillsInput').val().split(',').map(s => s.trim()).filter(s => s);
            if (!currentSkills.includes(value)) {
                currentSkills.push(value);
                $('#finalSkillsInput').val(currentSkills.join(', '));
                message = `${value} Added to Required Skills.`;
            } else {
                message = `${value} Already added to Required Skills.`;
                toastType = 'info';
            }
        } else if (chipType === 'team_counts') {
            let currentTeamSizes = $('#finalTeamSizeInput').val().split(',').map(r => r.trim()).filter(r => r);
            
            const [roleName, countValue] = value.split(':').map(s => s.trim());
            
            let newTeamSizes = currentTeamSizes.filter(entry => !entry.startsWith(`${roleName}:`));
            
            newTeamSizes.push(value);
            
            $('#finalTeamSizeInput').val(newTeamSizes.join(', '));
            message = `${value} Team Size per Role updated`;
        }
        
        if (message && typeof window.showToast === 'function') {
            window.showToast(message, toastType);
        }
    });

});
// =========================================================================
// 🗑️ PROJECT DELETE HANDLERS
// =========================================================================

/**
 * Shows a confirmation dialog before proceeding with deletion.
 */
window.confirmDelete = function(projectId) {
    if (confirm("Are you sure you want to permanently delete Project ID " + projectId + "? This action cannot be undone.")) {
        window.deleteProject(projectId); 
    }
};

/**
 * Deletes a project via AJAX and displays a toast message.
 */
window.deleteProject = function(projectId) {
    const formData = new FormData();
    formData.append('project_id', projectId);
    formData.append('action', 'delete');

    const deleteUrl = 'include/host_sections/ajax_delete_project.php'; 
    
    fetch(deleteUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Project successfully deleted.', 'success'); 
            window.loadHostSection('my_projects'); 
        } else {
            showToast('❌ Delete Failed: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Network or Parsing error during delete:', error);
        showToast('❌ Network Error: Failed to connect to server for deletion.', 'error');
    });
};
</script>

<div id="reportModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold mb-4 text-red-600">Report Message</h3>
        <form id="reportForm">
            <input type="hidden" name="message_id" id="report_message_id">
            <input type="hidden" name="reported_user_id" id="reported_user_id">
            <input type="hidden" name="is_group_chat" id="is_group_chat">

            <div class="mb-4">
                <label for="report_reason" class="block text-sm font-medium text-gray-700">Reason for Report:</label>
                <textarea id="report_reason" name="report_reason" rows="4" required
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-red-500 focus:border-red-500"></textarea>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="document.getElementById('reportModal').classList.add('hidden'); document.getElementById('reportModal').classList.remove('flex');" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700">
                    Submit Report
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Global function to open the modal
    function showReportModal(messageId, reportedUserId, isGroup) {
        document.getElementById('report_message_id').value = messageId;
        document.getElementById('reported_user_id').value = reportedUserId;
        document.getElementById('is_group_chat').value = isGroup;
        document.getElementById('reportModal').classList.remove('hidden');
        document.getElementById('reportModal').classList.add('flex');
    }

    // Handle form submission via AJAX
    document.getElementById('reportForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Use jQuery for simplified AJAX
        $.post('ajax/ajax_report_chat.php', $(this).serialize(), function(response) {
            showToast(response.message, response.success ? 'success' : 'error');
            document.getElementById('reportModal').classList.add('hidden');
            document.getElementById('reportModal').classList.remove('flex');
        }, 'json').fail(function() {
            showToast('Report failed: Server error.', 'error');
            document.getElementById('reportModal').classList.add('hidden');
            document.getElementById('reportModal').classList.remove('flex');
        });
    });

    // ഇത് AJAX വഴി ഒരു സെക്ഷൻ ലോഡ് ചെയ്യാൻ ഉപയോഗിക്കുന്ന ഫംഗ്ഷനാണ്.
    function loadHostSection(sectionName, params = {}) {
        // 'content-area' ആണ് നിങ്ങളുടെ പ്രധാന കണ്ടന്റ് ഡിസ്പ്ലേ ഏരിയ എന്ന് സങ്കൽപ്പിക്കുന്നു
        const $contentArea = $('#content-area'); 
        
        let queryString = `section=${sectionName}`;
        for (const key in params) {
            if (params.hasOwnProperty(key)) {
                queryString += `&${key}=${params[key]}`;
            }
        }

        $contentArea.html('<div class="flex items-center justify-center h-full"><p>Loading...</p></div>');

        $.ajax({
            url: `host_dashboard.php?${queryString}&ajax=true`, // 'ajax=true' എങ്കിൽ HTML മാത്രം തിരികെ നൽകുക
            method: 'GET',
            success: function(data) {
                $contentArea.html(data);
            },
            error: function(xhr, status, error) {
                $contentArea.html(`<div class="text-red-500 p-4">Error loading content: ${error}</div>`);
            }
        });
    }

    // ഇത് വിൻഡോ ഒബ്ജക്റ്റിലേക്ക് ഫംഗ്ഷനെ ചേർക്കുന്നു. group_chat_list.php-യിലെ കോഡ് ഇതിനെയാണ് വിളിക്കുന്നത്.
    window.loadHostSection = loadHostSection; 
    </script>
    <div id="reportModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <h3 class="text-xl font-semibold mb-4">Report Message</h3>
        <form id="reportForm">
            <input type="hidden" name="message_id" id="reportMessageId">
            <input type="hidden" name="reported_user_id" id="reportedUserId">
            <input type="hidden" name="is_group_chat" value="1"> 
            
            <div class="mb-4">
                <label for="reportReason" class="block text-sm font-medium text-gray-700">Reason for Report</label>
                <textarea name="report_reason" id="reportReason" rows="3" required placeholder="e.g., Spam, Scam, Abusive content..."
                          class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-red-500 focus:border-red-500"></textarea>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeReportModal()" 
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 transition">Cancel</button>
                <button type="submit" 
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 transition">Submit Report</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Global functions to manage the modal
    function showReportModal(messageId, reportedUserId) {
        if (!messageId || !reportedUserId) return;
        document.getElementById('reportMessageId').value = messageId;
        document.getElementById('reportedUserId').value = reportedUserId;
        document.getElementById('reportReason').value = ''; 
        document.getElementById('reportModal').classList.remove('hidden');
    }

    function closeReportModal() {
        document.getElementById('reportModal').classList.add('hidden');
    }
</script>
<div id="successToast" class="fixed top-5 right-5 z-[100] hidden">
    <div class="bg-green-500 text-white p-4 rounded-lg shadow-lg flex items-center space-x-3 transition-opacity duration-300">
        <i class="fas fa-check-circle text-xl"></i>
        <span id="toastMessage">Task assigned successfully!</span>
    </div>
</div>
</body>
</html>