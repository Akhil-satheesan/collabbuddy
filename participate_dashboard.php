<?php
session_start();
require 'include/config.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'participant') {
    header("Location: login.php?role=participant");
    exit;
}

$menu = [
    'browse_projects'   => ['icon'=>'🔍','title'=>'Browse Projects','desc'=>'Find projects to join'],
    'my_applications'   => ['icon'=>'📄','title'=>'My Applications','desc'=>'Track your applications'],
    'joined_projects'   => ['icon'=>'✅','title'=>'Joined Projects','desc'=>'Projects you are part of'],
    'bookmarks'         => ['icon'=>'🔖','title'=>'Bookmarks','desc'=>'Saved projects'],
    'group_chat' => ['icon'=>'👥','title'=>'Project Groups','desc'=>'Team communication'],
    'one_to_one_chat' => ['icon'=>'💬','title'=>'Hosts Chats','desc'=>'1:1 Application Q&A'],
    'view_my_tasks'=> ['icon'=>'🗂️','title'=>'My Tasks','desc'=>'Your assigned tasks','badge'=>0], 
    'profile'           => ['icon'=>'👤','title'=>'Profile','desc'=>'Manage your account'],
    'change_password'   => ['icon'=>'🔑','title'=>'Change Password','desc'=>'Update your account security'] 
];

$section = $_GET['section'] ?? 'browse_projects';

$allowed_sections = array_keys($menu); 
if (!in_array($section, $allowed_sections)) {
    $section = 'browse_projects';
}

$flashMessage = null;
$flashType = null;

if (isset($_SESSION['success'])) {
    $flashMessage = $_SESSION['success'];
    $flashType = 'success';
    unset($_SESSION['success']);
} elseif (isset($_SESSION['error'])) {
    $flashMessage = $_SESSION['error'];
    $flashType = 'error';
    unset($_SESSION['error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CollabBuddy - Participant Dashboard</title>
<link rel="stylesheet" href="css/style.css">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="..." crossorigin="anonymous" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">

<?php include 'include/header_participant.php'; ?>

<div class="flex h-screen overflow-hidden"> 
    
    <aside class="w-72 bg-white border-r border-gray-200 flex flex-col shadow-lg overflow-y-auto z-10 transition-smooth">
        
          <div class="p-5 border-b border-gray-100 bg-white transition-smooth">
               <h2 class="text-xl font-extrabold text-gray-900 mb-1"> <?= ucfirst(htmlspecialchars($display_name)) ?></h2>
                 <div class="flex items-center space-x-2 text-xs">
                 <span class="bg-green-100 text-green-800 px-2 py-0.5 rounded-full font-medium shadow-sm">🟢 Online</span>
                 <span class="text-gray-500 font-semibold"><?= htmlspecialchars(ucfirst($_SESSION['role'])) ?></span>
             </div>
        </div>
        <div class="flex-1 p-3 space-y-1">
            <nav>
                <?php
                foreach ($menu as $key => $item) {
                    
                    if ($key === 'change_password') {
                        continue;
                    }

                    $badge = '';
                    if (isset($item['badge']) && $item['badge'] > 0) {
                        $badge = "<span class='bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full shadow-md'>{$item['badge']}</span>";
                    }
                    
                    $isActive = ($section === $key || ($section === 'change_password' && $key === 'profile')) ? 'active' : 'group hover:bg-gray-100';
                    $textColor = ($section === $key || ($section === 'change_password' && $key === 'profile')) ? 'text-indigo-600' : 'text-gray-600'; 
                    
                    echo "<a href='#' class='nav-item block w-full flex items-center space-x-3 p-3 rounded-xl transition-smooth text-left $isActive' data-section='$key'>
                                <span class='text-xl group-hover:text-indigo-600 {$textColor} transition-smooth'>{$item['icon']}</span>
                                <div class='flex-1 pl-2'>
                                    <p class='font-semibold group-hover:text-indigo-600 text-gray-800 transition-smooth'>{$item['title']}</p>
                                    <p class='text-xs text-gray-500 transition-smooth'>{$item['desc']}</p>
                                </div>
                                {$badge}
                            </a>";
                }
                ?>
            </nav>
        </div>
    </aside>

    <div class="flex-1 flex flex-col overflow-hidden">
        
        <div id="sectionHeader" class="p-6 border-b border-gray-200 active-header sticky top-0 z-0 transition-smooth">
            <h1 class="text-3xl font-extrabold tracking-tight gradient-text transition-smooth">
                <?= htmlspecialchars($menu[$section]['title'] ?? 'Browse Projects') ?>
            </h1>
            <p class="text-sm text-gray-500 mt-1 transition-smooth">
                <?= htmlspecialchars($menu[$section]['desc'] ?? 'Find your next collaboration project.') ?>
            </p>
        </div>

        <div id="sectionContentArea" class="p-6 flex-1 overflow-y-auto bg-gray-50 transition-smooth"> 
            <div class="flex justify-center items-center h-full min-h-40">
                <div class="flex flex-col items-center space-y-4">
                    <div class="animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-indigo-600"></div>
                    <p class="text-gray-600 font-medium text-lg">Loading content...</p>
                </div>
            </div>
        </div>
    </div>
</div>


<?php if ($flashMessage): ?>
<div id="flashToast" 
     class="fixed top-5 right-5 px-6 py-3 rounded-xl shadow-2xl text-white font-medium 
             <?= $flashType === 'success' ? 'bg-green-500' : 'bg-red-500' ?> z-50 transition-smooth">
    <?= $flashMessage ?>
</div>

<script>
setTimeout(() => {
    const toast = document.getElementById('flashToast');
    if (toast) {
        toast.style.opacity = "0";
        setTimeout(() => toast.remove(), 500);
    }
}, 4000); 
</script>
<?php endif; ?>
</script>

<script>

window.clearChatInterval = function() {
    if (typeof window.chatInterval !== 'undefined' && window.chatInterval !== null) { 
        clearInterval(window.chatInterval);
        window.chatInterval = null;
    }
    if (typeof window.privateChatInterval !== 'undefined' && window.privateChatInterval !== null) { 
        clearInterval(window.privateChatInterval);
        window.privateChatInterval = null;
    }
};


$(document).ready(function(){
    
    setTimeout(() => {
        const toast = document.getElementById('flashToast');
        if (toast) {
            toast.style.opacity = "0";
            setTimeout(() => toast.remove(), 500);
        }
    }, 4000); 

    var initialSection = '<?= $section ?>';
    const menuData = <?= json_encode($menu) ?>;
    
    function updateHeader(sectionKey) {
        const item = menuData[sectionKey];
        if (item) {
            $('#sectionHeader h1').text(item.title);
            $('#sectionHeader p').text(item.desc);
        }
    }

    window.loadSection = function(sectionKey, params = {}, isInitialLoad = false) { 
        
        if (typeof window.clearChatInterval === 'function') {
            window.clearChatInterval();
        }
        
        $('.nav-item').removeClass('active');
        const activeSidebarKey = (sectionKey === 'change_password') ? 'profile' : sectionKey;
        $(".nav-item[data-section='" + activeSidebarKey + "']").addClass('active');

        const ajaxData = { section: sectionKey, ...params }; 
        let historyUrl = '?section=' + sectionKey;
        
        const currentUrlParams = new URLSearchParams(window.location.search);
        
        if (sectionKey === 'one_to_one_chat' || sectionKey === 'group_chat') {
            
            const groupId = params.group_id || currentUrlParams.get('group_id'); 
            if (groupId) {
                ajaxData.group_id = groupId;
                historyUrl += '&group_id=' + groupId;
            }
            
            const roomId = params.room_id || currentUrlParams.get('room_id'); 
            if (roomId) {
                ajaxData.room_id = roomId;
                historyUrl += '&room_id=' + roomId;
            }
        }

        if (!isInitialLoad) {
              history.pushState(null, '', historyUrl);
        }
        
        updateHeader(sectionKey);

        $('#sectionContentArea').html(`
            <div class="flex justify-center items-center h-64">
                <div class="flex flex-col items-center space-y-4">
                    <div class="animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-indigo-600"></div>
                    <p class="text-gray-600 font-medium text-lg">Loading ${sectionKey.replace(/_/g, ' ')}...</p>
                </div>
            </div>
        `);
        
        $.ajax({
            url: 'ajax_load_participant_section.php', 
            type: 'GET',
            data: ajaxData,
            success: function(response){
                $('#sectionContentArea').html(response);
            },
            error: function(xhr){
                $('#sectionContentArea').html('<p class="text-red-600 p-6">❌ Error loading section: ' + (xhr.statusText || 'Unknown Error') + ' (Status: ' + xhr.status + ')</p>');
            }
        });
    };

    $('.nav-item').click(function(e){
        e.preventDefault(); 
        var section = $(this).data('section');
        
        if (typeof window.clearChatInterval === 'function') {
            window.clearChatInterval();
        }

        window.loadSection(section); 
    });
    
    const initialParams = {};
    const pageUrlParams = new URLSearchParams(window.location.search);
    if (pageUrlParams.get('group_id')) initialParams.group_id = pageUrlParams.get('group_id');
    if (pageUrlParams.get('room_id')) initialParams.room_id = pageUrlParams.get('room_id');
    
    window.loadSection(initialSection, initialParams, true);

    window.onpopstate = function() {
        var params = new URLSearchParams(window.location.search);
        var sec = params.get('section') || 'browse_projects';
        
        const backParams = {};
        if (params.get('group_id')) backParams.group_id = params.get('group_id');
        if (params.get('room_id')) backParams.room_id = params.get('room_id');
        
        window.loadSection(sec, backParams, true); 
    };
    
    
    const removeModal = $('#removeConfirmModal');
    const removeDialog = $('#removeConfirmDialog');
    const removeCancelBtn = $('#removeCancelBtn');
    const removeConfirmBtn = $('#removeConfirmBtn');
    
    window.openRemoveModal = function() {
        removeModal.removeClass('hidden').addClass('flex');
        setTimeout(() => {
            removeDialog.removeClass('scale-90 opacity-0').addClass('scale-100 opacity-100');
        }, 10);
    }
    
    function closeRemoveModal() {
        removeDialog.removeClass('scale-100 opacity-100').addClass('scale-90 opacity-0');
        setTimeout(() => {
            removeModal.removeClass('flex').addClass('hidden');
        }, 300);
    }
    
    removeCancelBtn.on('click', closeRemoveModal);
    
    removeModal.on('click', function(e) {
        if (e.target.id === 'removeConfirmModal') {
            closeRemoveModal();
        }
    });

    removeConfirmBtn.on('click', function() {
        console.log("Profile picture removal confirmed!");
        alert("Picture removal initiated (Check console for AJAX placeholder).");
        closeRemoveModal(); 
    });
    
});
function closeHostProfileModal() {
    const modal = document.getElementById('hostProfileModal');
    const content = document.getElementById('hostProfileContent');

    content.classList.add('scale-95', 'opacity-0');
    content.classList.remove('scale-100', 'opacity-100');

    setTimeout(() => {
        modal.classList.add('hidden');
        document.getElementById('hostProfileDetailsArea').innerHTML = '';
        document.getElementById('hostModalName').innerText = 'Host Profile'; 
    }, 300); 
}

function showReportModal(messageId, reportedUserId, isGroup) {
    document.getElementById('report_message_id').value = messageId;
    document.getElementById('reported_user_id').value = reportedUserId;
    document.getElementById('is_group_chat').value = isGroup;
    document.getElementById('reportModal').classList.remove('hidden');
    document.getElementById('reportModal').classList.add('flex');
}

document.getElementById('reportForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    $.post('ajax/ajax_report_chat.php', $(this).serialize(), function(response) {
        alert(response.message);
        document.getElementById('reportModal').classList.add('hidden');
        document.getElementById('reportModal').classList.remove('flex');
    }, 'json');
});
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>

<script src="path/to/your/custom.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>

<script src="path/to/your/custom.js"></script> 

<div id="notification-container" class="fixed bottom-5 right-5 space-y-3 z-[9999]">
</div>

<div id="removeConfirmModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 hidden items-center justify-center z-[9999]">
    <div class="bg-white rounded-lg shadow-2xl p-6 w-full max-w-sm transform transition-all duration-300 scale-90 opacity-0" id="removeConfirmDialog">
        <h3 class="text-lg font-bold text-red-600 mb-4">Confirm Removal</h3>
        <p class="text-gray-600 mb-6">Are you sure you want to remove your profile picture? This action cannot be undone.</p>
        
        <div class="flex justify-end space-x-3">
            <button id="removeCancelBtn" type="button" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors">
                Cancel
            </button>
            <button id="removeConfirmBtn" type="button" class="px-4 py-2 text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors">
                Remove Photo
            </button>
        </div>
    </div>
</div>
<div id="hostProfileModal" class="hidden fixed inset-0 z-[60] bg-black bg-opacity-60 flex items-center justify-center p-4 transition-opacity duration-300">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl transform scale-95 opacity-0 transition-transform duration-300 ease-out" id="hostProfileContent">
        
        <div class="p-5 border-b border-gray-200 flex justify-between items-center bg-gray-50 rounded-t-xl">
            <h2 class="text-2xl font-bold text-gray-800" id="hostModalName">Host Profile</h2> 
            
            <button type="button" onclick="closeHostProfileModal()" class="text-gray-500 hover:text-gray-900 transition duration-150 focus:outline-none">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <div class="p-6 overflow-y-auto max-h-[80vh] bg-gray-50" id="hostProfileDetailsArea">
            </div>

    </div>
</div>
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
                <button type="button" onclick="document.getElementById('reportModal').classList.add('hidden')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
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
    function showReportModal(messageId, reportedUserId, isGroup) {
        document.getElementById('report_message_id').value = messageId;
        document.getElementById('reported_user_id').value = reportedUserId;
        document.getElementById('is_group_chat').value = isGroup;
        document.getElementById('reportModal').classList.remove('hidden');
        document.getElementById('reportModal').classList.add('flex');
    }

    document.getElementById('reportForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        $.post('ajax/ajax_report_chat.php', $(this).serialize(), function(response) {
            alert(response.message);
            document.getElementById('reportModal').classList.add('hidden');
            document.getElementById('reportModal').classList.remove('flex');
        }, 'json');
    }); 
    function loadParticipantSection(sectionName, params = {}) {
        const $contentArea = $('#content-area'); 

        let queryString = `section=${sectionName}`;
        for (const key in params) {
            if (params.hasOwnProperty(key)) {
                queryString += `&${key}=${params[key]}`;
            }
        }

        $contentArea.html('<div class="flex items-center justify-center h-full"><p>Loading...</p></div>');

        $.ajax({
            url: `participant_dashboard.php?${queryString}&ajax=true`, 
            method: 'GET',
            success: function(data) {
                $contentArea.html(data);
            },
            error: function(xhr, status, error) {
                $contentArea.html(`<div class="text-red-500 p-4">Error loading content: ${error}</div>`);
            }
        });
    }

    window.loadParticipantSection = loadParticipantSection;

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
</body>
</html>