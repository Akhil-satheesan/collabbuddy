<?php
// FILE: include/host_sections/team_members.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


require_once __DIR__ . '/../config.php'; 


$current_user_id = $_SESSION['user_id'] ?? 0;
$current_user_role = $_SESSION['role'] ?? 'guest';

// Host റോൾ ചെക്ക്
if ($current_user_id <= 0 || $current_user_role !== 'host') {
    echo '<p class="p-4 text-red-500">Authentication error. Access denied for this role.</p>';
    exit;
}


if (isset($_GET['action']) && $_GET['action'] === 'fetch_all_team_members_data') {
    header('Content-Type: application/json');
    $conn = get_db_connection();
    $host_id = $_SESSION['user_id'];

    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
        exit;
    }
    
    // Host-ൻ്റെ എല്ലാ പ്രോജക്റ്റുകളിലെയും Participant-ൻ്റെ വിവരങ്ങൾ ഫെച്ച് ചെയ്യുന്നു.
    $sql_members = "
        SELECT 
            u.user_id,
            u.name AS participant_name,
            u.email AS participant_email,
            GROUP_CONCAT(p.title SEPARATOR ' | ') AS project_list
        FROM project_participants pp
        JOIN projects p ON pp.project_id = p.project_id
        JOIN users u ON pp.participant_id = u.user_id
        WHERE p.host_id = ? 
        GROUP BY u.user_id, u.name, u.email
        ORDER BY u.name";

    $stmt_members = $conn->prepare($sql_members);
    
    if (!$stmt_members) {
         http_response_code(500);
         echo json_encode(['success' => false, 'error' => 'SQL prepare failed: ' . $conn->error]);
         $conn->close();
         exit;
    }
    
    $stmt_members->bind_param("i", $host_id);
    $stmt_members->execute();
    $result_members = $stmt_members->get_result();
    $team_members = $result_members->fetch_all(MYSQLI_ASSOC);
    $stmt_members->close();
    $conn->close();
    
    echo json_encode(['success' => true, 'team_members' => $team_members]);
    exit; // AJAX റിക്വസ്റ്റ് ഇവിടെ പൂർത്തിയാക്കുന്നു
}

// =================================================================
// HTML DISPLAY SECTION
// =================================================================
?>
<div class="p-6 bg-gray-50 min-h-screen">
    <h1 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-2">My Extended Team Members 👥</h1>
    <p class="text-gray-600 mb-6">This list shows all participants currently enrolled in any project you are hosting.</p>

    <div id="team-members-list-container" class="bg-white p-6 rounded-lg shadow-md">
        <div class="text-center p-8 text-indigo-600" id="members-loading-message">
            <i class="fas fa-spinner fa-spin mr-2"></i> Team members list is loading...
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    
    // ⭐️ AJAX പാത: ഇതേ ഫയലിലേക്ക് തന്നെ action പരാമീറ്റർ സഹിതം
    // host_dashboard.php ലോഡ് ചെയ്യുന്നതിലൂടെ റൂട്ട് പാത്തുകൾ ശരിയാവില്ല.
    const API_URL = 'include/host_sections/team_members.php?action=fetch_all_team_members_data'; // നിങ്ങളുടെ റൂട്ടിന് അനുസരിച്ച് ഈ പാത്ത് ശരിയാക്കേണ്ടിവരും
    
    // 🚨 ശ്രദ്ധിക്കുക: ഈ പാത്ത് പ്രവർത്തിക്കുന്നില്ലെങ്കിൽ, 
    // നിങ്ങളുടെ പ്രധാന ഡാഷ്‌ബോർഡ് പേജിൽ നിന്നും AJAX കോൾ ചെയ്യാൻ കഴിയുന്ന പാത്ത് ഉപയോഗിക്കുക.
    // ഉദാഹരണത്തിന്: ajax/ajax_team_members.php?action=... (പുതിയ AJAX ഫയലാണ് നല്ലത്)

    /**
     * Fetch and render the list of all team members.
     */
    function fetchAllTeamMembers() {
        const $container = $('#team-members-list-container');
        
        $container.html('<div class="text-center p-8 text-indigo-600"><i class="fas fa-spinner fa-spin mr-2"></i> Team members list is loading...</div>');

        $.ajax({
            url: API_URL, 
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.team_members.length > 0) {
                    renderTeamMembersTable(response.team_members);
                } else {
                    $container.html('<div class="text-center p-8 text-lg text-gray-500"><i class="fas fa-users-slash mr-2"></i> No participants are currently enrolled in any of your projects.</div>');
                }
            },
            error: function(xhr) {
                console.error("Error fetching team members:", xhr.responseText);
                $container.html('<div class="text-center p-8 text-red-500">AJAX Request Failed. Could not load team member data. Status: ' + xhr.status + '</div>');
            }
        });
    }

    /**
     * Renders the fetched member data into an HTML table.
     */
    function renderTeamMembersTable(membersData) {
        let tableHtml = `
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-indigo-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Projects Enrolled In</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
            `;

        membersData.forEach(member => {
            const projectsFormatted = member.project_list.split(' | ').map(p => 
                `<span class="bg-gray-100 px-2 py-0.5 rounded text-xs text-indigo-700 font-medium">${p}</span>`
            ).join(' ');
            
            tableHtml += `
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${member.participant_name}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${member.participant_email}</td>
                    <td class="px-6 py-4 text-sm text-gray-700">
                        <div class="flex flex-wrap gap-2">${projectsFormatted}</div>
                    </td>
                </tr>
            `;
        });

        tableHtml += `</tbody></table></div>`;
        $('#team-members-list-container').html(tableHtml);
    }

    // Load team members on page load
    fetchAllTeamMembers();
});
</script>