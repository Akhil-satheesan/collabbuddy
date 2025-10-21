<?php
// FILE: Project Listing Page (Full Code)

session_start();
// ⭐️ config.php-ലേക്ക് ശരിയായ പാത്ത് ഉപയോഗിക്കുക
require '../config.php'; 

// 1. സുരക്ഷാ പരിശോധന
if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'participant'){
    http_response_code(401);
    die("Unauthorized Access.");
}

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$participant_id = $_SESSION['user_id'];

// 2. Participant preferences fetch ചെയ്യുക
$conn = get_db_connection();
$stmt = $conn->prepare("SELECT preferred_role, skills, languages FROM participants WHERE participant_id = ?");
$stmt->bind_param("i", $participant_id);
$stmt->execute();
$participant = $stmt->get_result()->fetch_assoc();
$preferred_role = $participant['preferred_role'] ?? '';
$languages = $participant['languages'] ?? '';
$stmt->close();

// 3. Base SQL Query: Host Name, Profile Pic ഉൾപ്പെടുത്തി
$sql = "SELECT p.*, u.name AS host_name, u.profile_pic_url AS host_image 
        FROM projects p 
        JOIN users u ON u.user_id = p.host_id 
        WHERE 1 ";

$params = [];
$types = "";

// 4. Search filter
if($search){
    $sql .= " AND (p.title LIKE ? OR p.required_skills LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "sss";
}

// 5. Category filter
if($category){
    $sql .= " AND p.project_category = ?";
    $params[] = $category;
    $types .= "s";
}

// 6. Prioritization based on Participant preferences
$sql .= " ORDER BY 
    CASE 
        WHEN p.required_skills LIKE ? THEN 1    
        WHEN p.required_skills LIKE ? THEN 2    
        WHEN p.required_roles_list LIKE ? THEN 3 
        ELSE 4
    END, p.created_at DESC";

// Data for P1, P2, P3
$params[] = "%$preferred_role%"; 
$params[] = "%$languages%";      
$params[] = "%$preferred_role%"; 
$types .= "sss"; 

// 7. Prepare and execute (Error Handling Included)
$stmt = $conn->prepare($sql); 

if ($stmt === false) {
    die("SQL Prepare Error: " . $conn->error . "<br>Query: " . htmlspecialchars($sql)); 
}

// Parameter Binding
if(!empty($types)) { 
    $stmt->bind_param($types, ...$params); 
}
$stmt->execute();
$result = $stmt->get_result();

// 8. Display projects
if($result->num_rows > 0){
    while($row = $result->fetch_assoc()){

        $project_id = $row['project_id'];

        // --- Check Statuses ---
        $acceptedStmt = $conn->prepare("SELECT 1 FROM project_participants WHERE project_id=? AND participant_id=?");
        $acceptedStmt->bind_param("ii", $project_id, $participant_id);
        $acceptedStmt->execute();
        $acceptedStmt->store_result();
        $isAccepted = $acceptedStmt->num_rows > 0;
        $acceptedStmt->close();

        // Check Application Status
        $appStmt = $conn->prepare("SELECT status FROM applications WHERE project_id=? AND participant_id=? ORDER BY applied_at DESC LIMIT 1");
        $appStmt->bind_param("ii", $project_id, $participant_id);
        $appStmt->execute();
        $appResult = $appStmt->get_result();
        $applicationStatus = $appResult->num_rows > 0 ? $appResult->fetch_assoc()['status'] : null;
        $appStmt->close();
        
        $hasApplied = $applicationStatus !== null;
        $isPending = $applicationStatus === 'Pending'; 

        $bookmarkStmt = $conn->prepare("SELECT 1 FROM bookmarks WHERE project_id=? AND participant_id=?");
        $bookmarkStmt->bind_param("ii", $project_id, $participant_id);
        $bookmarkStmt->execute();
        $bookmarkStmt->store_result();
        $alreadyBookmarked = $bookmarkStmt->num_rows > 0;
        $bookmarkStmt->close();

        // --- Host Image Path Logic (For Display) ---
        $host_image_url = !empty($row['host_image']) 
        ? '/collabuddy/' . htmlspecialchars($row['host_image']) 
        : '/collabuddy/assets/Screenshot_5-10-2025_172430_www.freepik.com.jpeg';
        
        ?> 

        <div class="bg-white rounded-lg border border-gray-200 p-6 card-hover mb-6" data-project-id="<?= $project_id ?>">
            <div class="flex justify-between items-start mb-4">
                <div class="flex items-center space-x-4">
                    
                    <div class="w-12 h-12 rounded-full overflow-hidden flex-shrink-0">
                        <img src="<?= $host_image_url ?>" 
                             alt="<?= htmlspecialchars($row['host_name']) ?>'s Profile" 
                             class="w-full h-full object-cover">
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-600">Hosted By</p>
                        <h3 class="text-lg font-semibold text-gray-900">
                            <a href="javascript:void(0);" 
                               onclick="showHostProfileModal(<?= $row['host_id'] ?>, '<?= htmlspecialchars(addslashes($row['host_name'])) ?>')"
                               class="hover:underline cursor-pointer">
                                <?= htmlspecialchars($row['host_name']) ?>
                            </a>
                        </h3>
                        <p class="text-sm text-gray-600">Category: <?= htmlspecialchars($row['project_category']) ?></p>
                        <p class="text-xs text-gray-500">Posted on <?= date("M d, Y", strtotime($row['created_at'])) ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    
                    <button class="report-icon-btn text-gray-400 hover:text-red-500 p-2 rounded-full transition duration-150" 
                            title="Report Host"
                            onclick="openReportModal(<?= $row['host_id'] ?>, '<?= htmlspecialchars(addslashes($row['host_name'])) ?>', <?= $project_id ?>)">
                        <i class="fas fa-flag w-5 h-5"></i>
                    </button>
                    <button class="bookmark-btn px-3 py-2 rounded-lg font-medium
    <?= $alreadyBookmarked ? 'text-yellow-500' : 'text-gray-400 hover:text-yellow-500' ?>"
    data-project="<?= $project_id ?>">
    <svg class="w-5 h-5 inline-block mr-1" fill="<?= $alreadyBookmarked ? 'currentColor' : 'none' ?>" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
    </svg>
    <?= $alreadyBookmarked ? 'Bookmarked' : 'Bookmark' ?>
</button>
                    <span class="status-open text-white px-3 py-1 rounded-full text-xs font-medium bg-indigo-500"><?= $row['status'] ?></span>
                </div>
            </div>

            <h2 class="text-xl font-semibold text-gray-900 mb-2"><?= htmlspecialchars($row['title']) ?></h2>
            <p class="text-gray-700 leading-relaxed mb-4"><?= htmlspecialchars($row['description']) ?></p>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                <div class="text-center bg-gray-50 rounded-lg p-3">
                    <p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($row['duration']) ?></p>
                    <p class="text-xs text-gray-600">Duration</p>
                </div>
                <div class="text-center bg-gray-50 rounded-lg p-3">
                    <p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($row['team_size']) ?></p>
                    <p class="text-xs text-gray-600">Team Size</p>
                </div>
            </div>

            <div class="mb-4">
                <p class="text-sm font-medium text-gray-700 mb-2">Required Skills:</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach(explode(",", $row['required_skills']) as $skill): ?>
                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs font-medium"><?= trim($skill) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex space-x-3">
                <?php
                    if($isAccepted){
                        echo '<button type="button" disabled class="bg-green-100 text-green-700 px-6 py-2 rounded-lg font-medium cursor-not-allowed">✅ Accepted</button>';
                    } elseif($isPending){ 
                        echo '<button type="button" disabled class="bg-yellow-100 text-yellow-700 px-6 py-2 rounded-lg font-medium cursor-not-allowed">⏳ Pending</button>';
                    } elseif($hasApplied){
                        echo '<button type="button" onclick="openModal('.$project_id.')" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium">Apply Again</button>';
                    } else {
                        echo '<button type="button" onclick="openModal('.$project_id.')" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium">Apply Now</button>';
                    }
                ?>
        
<button class="border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg font-medium" 
    onclick="showProjectDetailsModal(<?= $project_id ?>)">
    View Details
</button>
            </div>
        </div>

    <?php }
}else{
    echo "<p class='text-gray-600'>No projects found matching your criteria.</p>";
}
?>
<div id="applyModal" class="hidden fixed inset-0 z-50 bg-black bg-opacity-50 flex items-center justify-center">
    <div class="bg-white/95 p-6 rounded-2xl shadow-2xl w-full max-w-lg">
        <h2 class="text-2xl font-bold text-gray-900 mb-4">Apply for <span class="text-blue-600" id="modalTitle"></span></h2>
        <form method="POST" action="include/participant_sections/apply_project.php" enctype="multipart/form-data">
            <input type="hidden" name="project_id" id="modal_project_id">
            <textarea name="cover_message" rows="4" required class="w-full border border-gray-300 rounded-xl px-4 py-3 mb-3" placeholder="Cover message"></textarea>
            <select name="availability" required class="w-full border border-gray-300 rounded-xl px-4 py-3 mb-3">
                <option value="">--Select--</option>
                <option value="Full-time">Full-time</option>
                <option value="Part-time">Part-time</option>
                <option value="Flexible">Flexible</option>
                <option value="Weekend only">Weekend only</option>
            </select>
            <div>
                <label for="resume" class="block text-sm font-semibold text-gray-700 mb-2">Resume/CV (Optional)</label>
                <input type="file" name="resume" id="resume" accept=".pdf,.doc,.docx" 
                        class="w-full file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 
                                     border border-gray-300 rounded-xl px-4 py-3 text-sm text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div class="flex space-x-3 mt-5">
                <button type="submit" name="apply_project" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-medium">🚀 Submit Application</button>
                <button type="button" onclick="closeModal()" class="px-6 py-3 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-100">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div id="bookmarkToast" class="fixed top-5 right-5 px-4 py-3 rounded-lg shadow-lg text-white hidden z-50"></div>

<div id="hostProfileModal" class="hidden fixed inset-0 z-[60] bg-black bg-opacity-50 flex items-center justify-center p-4 transition-opacity duration-300">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl transform scale-95 opacity-0 transition-transform duration-300 ease-out" id="hostProfileContent">
        <div class="p-5 border-b flex justify-between items-center">
            <h2 class="text-2xl font-bold text-gray-900" id="hostModalName">Host Profile Loading...</h2>
            <button id="reportHostBtn" 
                    class="bg-red-500 hover:bg-red-600 text-white text-sm font-medium py-1 px-3 rounded-full transition duration-150 hidden ml-auto mr-4" 
                    onclick="openReportModal(this.getAttribute('data-host-id'), this.getAttribute('data-host-name'), null)">
                <i class="fas fa-flag mr-1"></i> Report Host
            </button>
            <button type="button" onclick="closeHostProfileModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto max-h-[80vh]">
            <div id="hostProfileDetailsArea">
                <p class="text-indigo-500 font-medium">Loading host details...</p>
            </div>
        </div>
    </div>
</div>

<div id="projectDetailsModal" class="hidden fixed inset-0 z-[60] bg-black bg-opacity-60 flex items-center justify-center p-4 transition-opacity duration-300">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl transform scale-95 opacity-0 transition-transform duration-300 ease-out" id="projectDetailsContent">
        
        <div class="p-5 border-b border-gray-200 flex justify-between items-center bg-gray-50 rounded-t-xl">
            <h2 class="text-2xl font-bold text-gray-800" id="projectModalTitle">Project Details Loading...</h2>
            
            <button type="button" onclick="closeProjectDetailsModal()" class="text-gray-500 hover:text-gray-900 transition duration-150 focus:outline-none">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <div class="p-6 overflow-y-auto max-h-[80vh]" id="projectDetailsArea">
            <div class="text-center p-10 text-gray-500">
                <i class="fas fa-spinner fa-spin mr-2"></i> Loading project details...
            </div>
        </div>

    </div>
</div>

<div id="reportModal" class="hidden fixed inset-0 z-[70] bg-black bg-opacity-70 flex items-center justify-center p-4">
    <div class="bg-white p-6 rounded-xl shadow-2xl w-full max-w-md">
        <h2 class="text-2xl font-bold text-red-600 mb-4 flex items-center">
            <i class="fas fa-flag mr-2"></i> Report Abuse
        </h2>
        <p class="text-gray-700 mb-4">You are reporting <span class="font-semibold text-gray-900" id="reportTargetName"></span>.</p>
        
        <form id="reportForm">
            <input type="hidden" name="host_id" id="report_host_id">
            <input type="hidden" name="project_id" id="report_project_id">
            
            <div class="mb-4">
                <label for="report_reason" class="block text-sm font-medium text-gray-700 mb-2">Reason for Report</label>
                <select name="reason" id="report_reason" required class="w-full border border-gray-300 rounded-lg p-3 focus:border-red-500 focus:ring-red-500">
                    <option value="">-- Select Reason --</option>
                    <option value="Inappropriate Content">Inappropriate Content</option>
                    <option value="Harassment/Abuse">Harassment/Abuse</option>
                    <option value="Spam/Scam">Spam/Scam</option>
                    <option value="Misleading Project">Misleading Project (Project Report)</option>
                    <option value="Other">Other (Please specify below)</option>
                </select>
            </div>
            
            <div class="mb-4">
                <label for="report_details" class="block text-sm font-medium text-gray-700 mb-2">Details (Describe the issue)</label>
                <textarea name="details" id="report_details" rows="4" required class="w-full border border-gray-300 rounded-lg p-3" placeholder="Provide details about the incident..."></textarea>
            </div>
            
            <div id="reportMessage" class="mt-4 hidden text-sm font-medium p-3 rounded-lg"></div>
            
            <div class="flex justify-end space-x-3 mt-5">
                <button type="button" onclick="closeReportModal()" class="px-5 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium" id="submitReportBtn">Submit Report</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
    
// Modal Functions
function openModal(projectId){
    document.getElementById('modal_project_id').value = projectId;
    // ... (Code to get title remains the same)
    const card = document.querySelector('.card-hover [data-project="'+projectId+'"]').closest('.card-hover');
    const title = card.querySelector('h2').innerText;
    document.getElementById('modalTitle').innerText = title;
    document.getElementById('applyModal').classList.remove('hidden');
}
function closeModal(){ document.getElementById('applyModal').classList.add('hidden'); }

// Bookmark toggle - jQuery/AJAX (Code remains the same)
$(document).ready(function () {
    $(document).on("click", ".bookmark-btn", function () {
        let btn = $(this);
        let projectId = btn.data("project");

        if (btn.data("loading")) return; // prevent double click
        btn.data("loading", true);

        $.ajax({
            url: "include/participant_sections/bookmark_project.php", 
            type: "POST",
            data: { project_id: projectId },
            dataType: "json",
            success: function (resp) {
                btn.data("loading", false);
                let toast = $("#bookmarkToast");

                if (!resp.success) {
                    alert(resp.message || "Something went wrong!");
                    return;
                }

                if (resp.action === "added") {
                    btn.html(`
                        <svg class="w-5 h-5 inline-block mr-1" fill="currentColor" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                        </svg> Bookmarked
                    `).addClass("text-yellow-500")
                     .removeClass("text-gray-400 hover:text-yellow-500");

                    toast.removeClass("hidden bg-red-600")
                          .addClass("bg-green-600")
                          .text("✅ Project added to bookmarks")
                          .fadeIn();
                } else {
                    btn.html(`
                        <svg class="w-5 h-5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                        </svg> Bookmark
                    `).removeClass("text-yellow-500")
                     .addClass("text-gray-400 hover:text-yellow-500");

                    toast.removeClass("hidden bg-green-600")
                          .addClass("bg-red-600")
                          .text("❌ Project removed from bookmarks")
                          .fadeIn();
                }

                setTimeout(() => { toast.fadeOut(); }, 2000);
            },
            error: function (xhr) {
                btn.data("loading", false);
                console.error("AJAX error:", xhr.responseText);
                alert("AJAX request failed. Check console and PHP error logs.");
            }
        });
    });
});


// Host Profile Modal Functions (Code remains the same)
window.showHostProfileModal = function(hostId) {
    const modal = $('#hostProfileModal');
    const contentArea = $('#hostProfileDetailsArea');
    const modalContent = $('#hostProfileContent');

    // 1. Loading State
    contentArea.html(
        `<div class="flex flex-col items-center py-10 space-y-3">
            <i class="fas fa-spinner fa-spin text-4xl text-indigo-600"></i>
            <p class="text-gray-600 font-medium">Fetching Host's Profile and Project Stats...</p>
        </div>`
    );
    $('#hostModalName').text('Loading Host Profile...');
    
    // 2. Show Modal
    modal.removeClass('hidden');
    setTimeout(() => {
        modalContent.removeClass('scale-95 opacity-0');
    }, 50);


    // 3. AJAX Call to fetch details
    $.ajax({
        url: 'ajax_load_participant_section.php', 
        type: 'GET',
        data: { section: 'host_profile', host_id: hostId },
        success: function(response){
            contentArea.html(response);
            const fetchedName = contentArea.find('h1:first').text() || 'Host Profile';
            $('#hostModalName').text(fetchedName);
        },
        error: function(xhr){
            contentArea.html(
                `<div class="p-4 bg-red-100 text-red-700 rounded-lg">
                    ❌ Failed to load Host Profile. Error: ${xhr.statusText || 'Unknown'}
                </div>`
            );
            $('#hostModalName').text('Error Loading Profile');
        }
    });
}

// Modal ക്ലോസ് ചെയ്യാനുള്ള ഫംഗ്ഷൻ (Code remains the same)
window.closeHostProfileModal = function() {
    const modal = $('#hostProfileModal');
    const modalContent = $('#hostProfileContent');

    // 1. Transition Reverse
    modalContent.addClass('scale-95 opacity-0');
    
    // 2. Hide Modal after transition
    setTimeout(() => {
        modal.addClass('hidden');
        $('#hostProfileDetailsArea').empty(); 
    }, 300);
}
// Esc key listener (Code remains the same)
$(document).keydown(function(e) {
    if (e.key === "Escape" && !$('#hostProfileModal').hasClass('hidden')) {
        closeHostProfileModal();
    }
});
    // Chat button (Code remains the same)
    $(document).on('click', '.viewChatBtn', function() {
        let projectId = $(this).data('project-id');
        window.loadParticipantSection('chat', projectId); // assumed external function for navigation
    });
    // ... (നിലവിലുള്ള JavaScript കോഡിന് ശേഷം ഇത് ചേർക്കുക)

// --- Project Details Modal Functions ---

window.showProjectDetailsModal = function(projectId) {
    const modal = $('#projectDetailsModal');
    const contentArea = $('#projectDetailsArea');
    const modalContent = $('#projectDetailsContent');
    const projectTitle = $('.card-hover [data-project-id="' + projectId + '"]').closest('.card-hover').find('h2').text();

    // 1. Loading State and Title
    contentArea.html(
        `<div class="flex flex-col items-center py-10 space-y-3">
            <i class="fas fa-spinner fa-spin text-4xl text-blue-600"></i>
            <p class="text-gray-600 font-medium">Loading project details...</p>
        </div>`
    );
    $('#projectModalTitle').text(projectTitle || 'Project Details');

    // 2. Show Modal (Pop Up Effect)
    modal.removeClass('hidden');
    setTimeout(() => {
        modalContent.removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100');
    }, 50);

    // 3. AJAX Call to fetch details
    $.ajax({
        url: 'ajax_load_participant_section.php', 
        type: 'GET',
        data: { section: 'project_details', project_id: projectId, modal_view: 1 }, // modal_view: 1 ചേർക്കുന്നത്, ഭാവിയിൽ Details പേജ് മോഡലിനായി optimize ചെയ്യാൻ സഹായിക്കും
        success: function(response){
            contentArea.html(response);
        },
        error: function(xhr){
            contentArea.html(
                `<div class="p-4 bg-red-100 text-red-700 rounded-lg">
                    ❌ Failed to load Project Details. Error: ${xhr.statusText || 'Unknown'}
                </div>`
            );
            $('#projectModalTitle').text('Error Loading Details');
        }
    });
}

window.closeProjectDetailsModal = function() {
    const modal = $('#projectDetailsModal');
    const modalContent = $('#projectDetailsContent');

    // 1. Transition Reverse
    modalContent.removeClass('scale-100 opacity-100').addClass('scale-95 opacity-0');
    
    // 2. Hide Modal after transition
    setTimeout(() => {
        modal.addClass('hidden');
        $('#projectDetailsArea').empty(); 
        $('#projectModalTitle').text('Project Details Loading...');
    }, 300);
}

// Esc key listener: Project Details Modal-നും കൂടി ചേർക്കുന്നു
$(document).keydown(function(e) {
    if (e.key === "Escape" && !$('#projectDetailsModal').hasClass('hidden')) {
        closeProjectDetailsModal();
    }
});

</script>
<script>
// jQuery (Needed for AJAX)
if (typeof jQuery == 'undefined') {
    document.write('<script src="https://code.jquery.com/jquery-3.7.0.min.js"></'+'script>');
}
    
// Modal Functions
function openModal(projectId){
    document.getElementById('modal_project_id').value = projectId;
    // ... (Code to get title remains the same)
    const card = document.querySelector('.card-hover [data-project="'+projectId+'"]').closest('.card-hover');
    const title = card.querySelector('h2').innerText;
    document.getElementById('modalTitle').innerText = title;
    document.getElementById('applyModal').classList.remove('hidden');
}
function closeModal(){ document.getElementById('applyModal').classList.add('hidden'); }

// Bookmark toggle - jQuery/AJAX (Existing Code)
$(document).ready(function () {
    $(document).on("click", ".bookmark-btn", function () {
        let btn = $(this);
        let projectId = btn.data("project");

        if (btn.data("loading")) return; 
        btn.data("loading", true);

        $.ajax({
            url: "include/participant_sections/bookmark_project.php", 
            type: "POST",
            data: { project_id: projectId },
            dataType: "json",
            success: function (resp) {
                btn.data("loading", false);
                let toast = $("#bookmarkToast");

                if (!resp.success) {
                    alert(resp.message || "Something went wrong!");
                    return;
                }

                if (resp.action === "added") {
                    btn.html(`
                        <svg class="w-5 h-5 inline-block mr-1" fill="currentColor" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                        </svg> Bookmarked
                    `).addClass("text-yellow-500")
                     .removeClass("text-gray-400 hover:text-yellow-500");

                    toast.removeClass("hidden bg-red-600")
                          .addClass("bg-green-600")
                          .text("✅ Project added to bookmarks")
                          .fadeIn();
                } else {
                    btn.html(`
                        <svg class="w-5 h-5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                        </svg> Bookmark
                    `).removeClass("text-yellow-500")
                     .addClass("text-gray-400 hover:text-yellow-500");

                    toast.removeClass("hidden bg-green-600")
                          .addClass("bg-red-600")
                          .text("❌ Project removed from bookmarks")
                          .fadeIn();
                }

                setTimeout(() => { toast.fadeOut(); }, 2000);
            },
            error: function (xhr) {
                btn.data("loading", false);
                console.error("AJAX error:", xhr.responseText);
                alert("AJAX request failed. Check console and PHP error logs.");
            }
        });
    });
});


// Host Profile Modal Functions (Modified)
window.showHostProfileModal = function(hostId, hostName) { 
    const modal = $('#hostProfileModal');
    const contentArea = $('#hostProfileDetailsArea');
    const modalContent = $('#hostProfileContent');
    const reportBtn = $('#reportHostBtn'); 

    // 1. Loading State
    contentArea.html(
        `<div class="flex flex-col items-center py-10 space-y-3">
            <i class="fas fa-spinner fa-spin text-4xl text-indigo-600"></i>
            <p class="text-gray-600 font-medium">Fetching Host's Profile and Project Stats...</p>
        </div>`
    );
    $('#hostModalName').text('Loading Host Profile...');
    
    // ⭐️ Report ബട്ടൺ ഡാറ്റ അപ്ഡേറ്റ് ചെയ്യുന്നു
    reportBtn.attr('data-host-id', hostId);
    reportBtn.attr('data-host-name', hostName);
    reportBtn.removeClass('hidden'); 

    // 2. Show Modal
    modal.removeClass('hidden');
    setTimeout(() => {
        modalContent.removeClass('scale-95 opacity-0');
    }, 50);


    // 3. AJAX Call to fetch details
    $.ajax({
        url: 'ajax_load_participant_section.php', // നിങ്ങളുടെ AJAX ഫയൽ
        type: 'GET',
        data: { section: 'host_profile', host_id: hostId },
        success: function(response){
            contentArea.html(response);
            const fetchedName = contentArea.find('h1:first').text() || hostName || 'Host Profile';
            $('#hostModalName').text(fetchedName);
            reportBtn.attr('data-host-name', fetchedName); // ഫെച്ച് ചെയ്ത പേര് അപ്ഡേറ്റ് ചെയ്യുന്നു
        },
        error: function(xhr){
            contentArea.html(
                `<div class="p-4 bg-red-100 text-red-700 rounded-lg">
                    ❌ Failed to load Host Profile. Error: ${xhr.statusText || 'Unknown'}
                </div>`
            );
            $('#hostModalName').text('Error Loading Profile');
        }
    });
}

// Modal ക്ലോസ് ചെയ്യാനുള്ള ഫംഗ്ഷൻ (Existing Code)
window.closeHostProfileModal = function() {
    const modal = $('#hostProfileModal');
    const modalContent = $('#hostProfileContent');
    const reportBtn = $('#reportHostBtn'); 

    // 1. Transition Reverse
    modalContent.addClass('scale-95 opacity-0');
    
    // 2. Hide Modal after transition
    setTimeout(() => {
        modal.addClass('hidden');
        $('#hostProfileDetailsArea').empty(); 
        reportBtn.addClass('hidden'); // Report button ഹൈഡ് ചെയ്യുന്നു
    }, 300);
}

// Project Details Modal Functions (Existing Code)
window.showProjectDetailsModal = function(projectId) {
    const modal = $('#projectDetailsModal');
    const contentArea = $('#projectDetailsArea');
    const modalContent = $('#projectDetailsContent');
    const projectTitle = $(`[data-project-id="${projectId}"]`).find('h2').text();

    // 1. Loading State and Title
    contentArea.html(
        `<div class="flex flex-col items-center py-10 space-y-3">
            <i class="fas fa-spinner fa-spin text-4xl text-blue-600"></i>
            <p class="text-gray-600 font-medium">Loading project details...</p>
        </div>`
    );
    $('#projectModalTitle').text(projectTitle || 'Project Details');

    // 2. Show Modal (Pop Up Effect)
    modal.removeClass('hidden');
    setTimeout(() => {
        modalContent.removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100');
    }, 50);

    // 3. AJAX Call to fetch details
    $.ajax({
        url: 'ajax_load_participant_section.php', 
        type: 'GET',
        data: { section: 'project_details', project_id: projectId, modal_view: 1 }, 
        success: function(response){
            contentArea.html(response);
        },
        error: function(xhr){
            contentArea.html(
                `<div class="p-4 bg-red-100 text-red-700 rounded-lg">
                    ❌ Failed to load Project Details. Error: ${xhr.statusText || 'Unknown'}
                </div>`
            );
            $('#projectModalTitle').text('Error Loading Details');
        }
    });
}

window.closeProjectDetailsModal = function() {
    const modal = $('#projectDetailsModal');
    const modalContent = $('#projectDetailsContent');

    // 1. Transition Reverse
    modalContent.removeClass('scale-100 opacity-100').addClass('scale-95 opacity-0');
    
    // 2. Hide Modal after transition
    setTimeout(() => {
        modal.addClass('hidden');
        $('#projectDetailsArea').empty(); 
        $('#projectModalTitle').text('Project Details Loading...');
    }, 300);
}

// Esc key listener for all modals (Existing Code)
$(document).keydown(function(e) {
    if (e.key === "Escape") {
        if (!$('#hostProfileModal').hasClass('hidden')) {
            closeHostProfileModal();
        } else if (!$('#projectDetailsModal').hasClass('hidden')) {
            closeProjectDetailsModal();
        } else if (!document.getElementById('applyModal').classList.contains('hidden')) {
            closeModal();
        } else if (!$('#reportModal').hasClass('hidden')) {
            closeReportModal();
        }
    }
});
    

// --- ⭐️ NEW: Report Modal Functions ---

window.openReportModal = function(hostId, hostName, projectId = null) {
    // 1. മോഡലിലെ ഇൻപുട്ടുകൾ സജ്ജമാക്കുന്നു
    $('#report_host_id').val(hostId);
    $('#report_project_id').val(projectId || ''); 
    
    // 2. റിപ്പോർട്ട് ചെയ്യപ്പെടുന്ന വ്യക്തി/വിവരം പ്രദർശിപ്പിക്കുന്നു
    let target = hostName;
    if (projectId) {
        // Project card ൽ നിന്ന് project name എടുക്കുന്നു
        const projectCard = $(`[data-project-id="${projectId}"]`);
        const projectName = projectCard.find('h2:first').text();
        target = hostName + (projectName ? ` (Project: ${projectName})` : '');
    }
    $('#reportTargetName').text(target);

    // 3. മോഡൽ പ്രദർശിപ്പിക്കുന്നു
    $('#reportModal').removeClass('hidden');

    // Host Profile Modal തുറന്നിരുന്നെങ്കിൽ അത് അടയ്ക്കുന്നു
    closeHostProfileModal();
}

window.closeReportModal = function() {
    $('#reportModal').addClass('hidden');
    $('#reportForm')[0].reset(); 
    $('#reportMessage').empty().addClass('hidden'); 
    $('#submitReportBtn').prop('disabled', false).text('Submit Report'); // Submit button റീസെറ്റ് ചെയ്യുന്നു
}

// ⭐️ റിപ്പോർട്ട് ഫോം സമർപ്പിക്കാനുള്ള AJAX
$(document).on('submit', '#reportForm', function(e) {
    e.preventDefault();
    const form = $(this);
    const submitBtn = $('#submitReportBtn');
    const messageArea = $('#reportMessage');

    submitBtn.prop('disabled', true).text('Submitting...');
    messageArea.removeClass('hidden bg-red-100 bg-green-100').addClass('bg-gray-100').text('Processing report...');

    $.ajax({
        url: 'include/participant_sections/submit_report.php', 
        type: 'POST',
        data: form.serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                messageArea.removeClass('bg-gray-100 bg-red-100').addClass('bg-green-100 text-green-700').text('✅ Report submitted successfully! Thank you for helping us keep the platform safe.');
                // form[0].reset(); // Successful ആകുമ്പോൾ മെസ്സേജ് കണ്ടതിനു ശേഷം ക്ലോസ് ചെയ്യാം
                setTimeout(closeReportModal, 3000);
            } else {
                messageArea.removeClass('bg-gray-100 bg-green-100').addClass('bg-red-100 text-red-700').text('❌ Submission failed: ' + (response.error || 'Unknown error.'));
            }
            submitBtn.prop('disabled', false).text('Submit Report');
        },
        error: function(xhr) {
            messageArea.removeClass('bg-gray-100 bg-green-100').addClass('bg-red-100 text-red-700').text('❌ Server error during submission.');
            submitBtn.prop('disabled', false).text('Submit Report');
            console.error("Report AJAX error:", xhr.responseText);
        }
    });
});
</script>