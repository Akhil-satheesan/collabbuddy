<?php
// FILE: include/participant_sections/project_details.php
// Note: This file assumes $conn (database connection) and $_SESSION['user_id'] are available 
// because it is included via ajax_load_participant_section.php, which handles the session check.

// 1. സുരക്ഷാ പരിശോധന & വേരിയബിൾ സജ്ജീകരണം
if(!isset($_SESSION['user_id']) || !isset($_GET['project_id'])){
    http_response_code(401);
    die("<div class='p-4 bg-red-100 text-red-700 rounded-lg'>Unauthorized or missing project ID.</div>");
}

// 🔑 require __DIR__ . '../../config.php'; ഇത് ajax_load_participant_section.php-ൽ ഉൾപ്പെടുത്തിയിട്ടുണ്ട്.

$project_id = (int)$_GET['project_id'];
$participant_id = $_SESSION['user_id'];

// 2. Project, Host വിവരങ്ങൾ Fetch ചെയ്യുന്നു
$sql_project_details = "
    SELECT 
        p.*, 
        u.name AS host_name,
        u.profile_pic_url AS host_image
    FROM projects p
    JOIN users u ON u.user_id = p.host_id
    WHERE p.project_id = ?
";

$stmt = $conn->prepare($sql_project_details);
if ($stmt === false) {
    die("<div class='p-4 bg-red-100 text-red-700 rounded-lg'>SQL Prepare Error (Project Details): " . $conn->error . "</div>"); 
}

$stmt->bind_param("i", $project_id); 
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$project) {
    die("<div class='p-4 bg-red-100 text-red-700 rounded-lg'>Project not found.</div>");
}

$host_image_url = !empty($project['host_image']) 
    ? '/collabuddy/' . htmlspecialchars($project['host_image']) 
    : '/collabuddy/assets/default_profile.png';

// 3. Statuses പരിശോധിക്കുന്നു (Apply, Accepted)
$appStmt = $conn->prepare("SELECT status FROM applications WHERE project_id=? AND participant_id=? ORDER BY applied_at DESC LIMIT 1");
$appStmt->bind_param("ii", $project_id, $participant_id);
$appStmt->execute();
$appResult = $appStmt->get_result();
$applicationStatus = $appResult->num_rows > 0 ? $appResult->fetch_assoc()['status'] : null;
$appStmt->close();

$isAccepted = $applicationStatus === 'Accepted';
$isPending = $applicationStatus === 'Pending';
$hasApplied = $applicationStatus !== null;


// 4. Role Analysis Logic (ശരിയായ കോളം നാമം ഉപയോഗിച്ച് പരിഹരിച്ചത്)
$required_roles_csv = $project['required_roles_list'] ?? '';
$required_roles_array = explode(',', $required_roles_csv);
$final_roles_to_display = [];
$total_roles_required = 0;
$total_roles_filled = 0;

// a. നിലവിലുള്ള അംഗങ്ങളെ Role അനുസരിച്ച് എണ്ണുക
// 🔑 മാറ്റം: role_name എന്നതിന് പകരം role_taken ഉപയോഗിക്കുന്നു
$current_members_stmt = $conn->prepare("
    SELECT role_taken, COUNT(participant_id) as current_count
    FROM project_participants
    WHERE project_id = ?
    GROUP BY role_taken
");

// 🔑 എറർ പരിഹാര ചെക്ക്
if ($current_members_stmt === false) {
    die("<div class='p-4 bg-red-100 text-red-700 rounded-lg'>SQL Prepare Error (Role Count): " . $conn->error . "</div>"); 
}

$current_members_stmt->bind_param("i", $project_id); 
$current_members_stmt->execute();
$member_counts_result = $current_members_stmt->get_result();
$current_member_counts = [];
while ($row = $member_counts_result->fetch_assoc()) {
    // 🔑 മാറ്റം: ഇവിടെയും role_taken ഉപയോഗിക്കുന്നു
    $current_member_counts[trim($row['role_taken'])] = (int)$row['current_count'];
    $total_roles_filled += (int)$row['current_count']; 
}
$current_members_stmt->close();


// b. ആവശ്യകത പൂർത്തിയാകാത്ത Role-കൾ ഫിൽട്ടർ ചെയ്യുക
foreach ($required_roles_array as $role_entry) {
    $role_entry = trim($role_entry);
    if (empty($role_entry)) continue;

    // 'RoleName:Count' ഫോർമാറ്റ് വേർതിരിക്കുന്നു (ഉദാ: 'Developer:2')
    $parts = explode(':', $role_entry);
    $role_name = trim($parts[0]);
    $required_count = isset($parts[1]) ? (int)trim($parts[1]) : 1; 
    
    $total_roles_required += $required_count;

    $current_count = $current_member_counts[$role_name] ?? 0;

    // ആവശ്യകത പൂർത്തിയാകാത്ത Roles മാത്രം ലിസ്റ്റിൽ ചേർക്കുന്നു
    if ($current_count < $required_count) {
        $final_roles_to_display[] = [
            'name' => $role_name,
            'required' => $required_count,
            'current' => $current_count,
            'remaining' => $required_count - $current_count
        ];
    }
}

// 5. Slots Available calculation
$slotsAvailable = $total_roles_required - $total_roles_filled; 

// 6. JavaScript: Modal Header Title സെറ്റ് ചെയ്യുന്നു
?>
<script>
    // Project Name നെ Modal Header ലേക്ക് സെറ്റ് ചെയ്യുന്നു
    document.getElementById('projectModalTitle').innerText = "<?= htmlspecialchars($project['title']) ?>"; 
</script>
---
<div class="space-y-6">
    
    <div class="pb-4 border-b border-gray-200">
        <h1 class="text-3xl font-extrabold text-gray-900"><?= htmlspecialchars($project['title']) ?></h1>
        <div class="flex items-center mt-2 space-x-3 text-sm">
            <span class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full font-semibold">
                <?= htmlspecialchars($project['project_category']) ?>
            </span>
            <span class="text-gray-600 flex items-center">
                <i class="fas fa-calendar-alt mr-1 text-gray-500"></i> Posted on <?= date("M d, Y", strtotime($project['created_at'])) ?>
            </span>
        </div>
    </div>
    
    <div>
        <h2 class="text-xl font-bold text-gray-800 mb-2">About the Project</h2>
        <p class="text-gray-700 leading-relaxed bg-gray-50 p-4 rounded-lg border border-gray-200 shadow-inner max-h-40 overflow-y-auto">
            <?= nl2br(htmlspecialchars($project['description'])) ?>
        </p>
    </div>

    <div class="grid grid-cols-3 gap-4"> 
        
        <div class="bg-gray-100 p-4 rounded-xl text-center border-l-4 border-blue-500 shadow-md">
            <p class="text-2xl font-extrabold text-blue-700"><?= htmlspecialchars($project['duration']) ?></p>
            <p class="text-xs text-gray-600 mt-1">Duration</p>
        </div>
        
        <div class="bg-gray-100 p-4 rounded-xl text-center border-l-4 border-pink-500 shadow-md">
             <p class="text-2xl font-extrabold text-pink-700"><?= $total_roles_filled ?> / <?= $total_roles_required ?></p>
            <p class="text-xs text-gray-600 mt-1">Team Capacity (Filled/Req)</p>
        </div>
        
        <div class="bg-gray-100 p-4 rounded-xl text-center border-l-4 border-green-500 shadow-md">
            <p class="text-2xl font-extrabold <?= $slotsAvailable > 0 ? 'text-green-700' : 'text-red-700' ?>">
                <?= $slotsAvailable ?>
            </p>
            <p class="text-xs text-gray-600 mt-1">Slots Left</p>
        </div>
        
        </div>
---
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2 border-t border-gray-200">
        
        <div>
            <h3 class="text-xl font-bold text-gray-800 mb-2 flex items-center"><i class="fas fa-user-tag mr-2 text-indigo-500"></i> Required Skills</h3>
            <div class="flex flex-wrap gap-2 max-h-20 overflow-y-auto">
                <?php foreach(explode(",", $project['required_skills']) as $skill): ?>
                    <span class="bg-indigo-100 text-indigo-800 px-3 py-1 rounded-full text-sm font-medium border border-indigo-300"><?= trim($skill) ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <div>
            <h3 class="text-xl font-bold text-gray-800 mb-2 flex items-center"><i class="fas fa-code mr-2 text-purple-500"></i> Open Roles</h3>
            <div class="flex flex-wrap gap-2 max-h-20 overflow-y-auto">
                <?php if (empty($final_roles_to_display)): ?>
                    <span class="text-green-600 font-medium p-2 bg-green-50 rounded-lg w-full">
                        ✅ All roles have been filled!
                    </span>
                <?php else: ?>
                    <?php foreach ($final_roles_to_display as $role): ?>
                        <span class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm font-medium border border-purple-300">
                            <?= htmlspecialchars($role['name']) ?> 
                            (Need: **<?= $role['remaining'] ?>**)
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="pt-4 border-t border-gray-200">
        <h3 class="text-xl font-bold text-gray-800 mb-3 flex items-center"><i class="fas fa-handshake mr-2 text-gray-600"></i> Hosted By</h3>
        <div class="flex items-center space-x-4 bg-gray-100 p-4 rounded-xl border border-gray-300 shadow-sm">
            <div class="w-10 h-10 rounded-full overflow-hidden flex-shrink-0">
                <img src="<?= $host_image_url ?>" alt="<?= htmlspecialchars($project['host_name']) ?>'s Profile" class="w-full h-full object-cover">
            </div>
            <h4 class="text-lg font-semibold text-gray-900">
                <a href="javascript:void(0);" 
                    onclick="closeProjectDetailsModal(); showHostProfileModal(<?= $project['host_id'] ?>)" 
                    class="hover:text-indigo-600 hover:underline transition duration-150">
                    <?= htmlspecialchars($project['host_name']) ?>
                </a>
            </h4>
        </div>
    </div>
    
    <div class="pt-6 flex space-x-4 justify-end border-t border-gray-200 mt-6">
        
        <?php
            if($isAccepted){
                echo '<button type="button" disabled class="bg-green-600 text-white px-6 py-2 rounded-lg font-medium cursor-not-allowed shadow-lg">✅ You are a member!</button>';
            } elseif($isPending){
                echo '<button type="button" disabled class="bg-yellow-600 text-white px-6 py-2 rounded-lg font-medium cursor-not-allowed shadow-lg">⏳ Application Pending</button>';
            } else {
                // Apply Button 
                echo '<button type="button" onclick="closeProjectDetailsModal(); openModal('.$project_id.')" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-bold shadow-xl transition duration-200 transform hover:scale-[1.02]">Apply Now</button>';
            }
        ?>
        
        <button type="button" onclick="closeProjectDetailsModal()" class="border border-gray-300 hover:bg-gray-100 text-gray-700 px-6 py-3 rounded-lg font-medium">Close View</button>
    </div>

</div>