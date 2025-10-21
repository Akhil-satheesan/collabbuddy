<?php
// ajax/ajax_update_team_size.php

session_start();
// require '../include/config.php'; // നിങ്ങളുടെ config ഫയൽ ഉൾപ്പെടുത്തുക

// സുരക്ഷാ പരിശോധന - ഇത് വളരെ പ്രധാനമാണ്.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host') {
    http_response_code(401);
    // JSON ഫോർമാറ്റിൽത്തന്നെ മറുപടി നൽകുന്നു
    echo json_encode(['error' => 'Unauthorized or Session Expired. Please log in again.', 'total_team_size' => 0, 'role_options_html' => '<option value="">-- Session Error --</option>']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Client-ൽ നിന്ന് ഡാറ്റ സ്വീകരിക്കുന്നു
    $requiredRolesString = $_POST['required_roles_list'] ?? ''; 
    $teamSizeString = $_POST['team_size_per_role'] ?? ''; 
    $isHostDualRole = filter_var($_POST['is_host_dual_role'], FILTER_VALIDATE_BOOLEAN); 
    $hostSelectedRole = $_POST['host_selected_role'] ?? ''; 

    $totalRequiredCount = 0;
    
    // 1. Total Count കണക്കാക്കുന്നു
    if (!empty($teamSizeString)) {
        $pairs = explode(',', $teamSizeString);
        foreach ($pairs as $pair) {
            $parts = explode(':', $pair);
            if (count($parts) == 2) {
                // Team Size per Role-ലെ കൗണ്ട് കൂട്ടുന്നു
                $count = (int)trim($parts[1]);
                $totalRequiredCount += $count;
            }
        }
    }
    
    $finalTeamSize = $totalRequiredCount;
    
    // 2. Host Dual Role അനുസരിച്ച് അഡ്ജസ്റ്റ് ചെയ്യുന്നു
    if ($isHostDualRole) {
        $isHostRoleListed = false;
        
        // Host റോൾ, Team Size ലിസ്റ്റിൽ ഉണ്ടോ എന്ന് നോക്കുന്നു
        if (!empty($teamSizeString) && !empty($hostSelectedRole)) {
            $pairs = explode(',', $teamSizeString);
            foreach ($pairs as $pair) {
                $parts = explode(':', $pair);
                if (count($parts) == 2 && trim($parts[0]) == $hostSelectedRole) {
                    $isHostRoleListed = true;
                    break;
                }
            }
        }
        
        if ($isHostRoleListed) {
            // Host ഒരു റോൾ എടുക്കുന്നു: ആ റോളിന്റെ എണ്ണം 1 കുറച്ച്, Host-നെ 1 കൂട്ടിച്ചേർക്കുന്നു.
            $finalTeamSize = max(0, $totalRequiredCount - 1) + 1;
        } else {
            // Host ചെക്ക് ചെയ്തു, പക്ഷെ റോൾ സെലക്ട് ചെയ്തില്ല/പുതിയ റോൾ എടുക്കുന്നു.
            $finalTeamSize = $totalRequiredCount + 1;
        }
    }

    // 3. Host Role Dropdown Options ജനറേറ്റ് ചെയ്യുന്നു
    $roleOptionsHtml = '<option value="">-- Select my Role --</option>';
    if (!empty($requiredRolesString)) {
        $roles = explode(',', $requiredRolesString);
        foreach ($roles as $role) {
            $role_trim = trim($role);
            if ($role_trim) {
                $selected = ($role_trim == $hostSelectedRole) ? 'selected' : '';
                $roleOptionsHtml .= "<option value='{$role_trim}' {$selected}>{$role_trim}</option>";
            }
        }
    }
    
    // Total Team Size-ഉം Host Role Options-ഉം ഒരുമിച്ച് തിരികെ അയക്കുന്നു
    echo json_encode([
        'total_team_size' => $finalTeamSize,
        'role_options_html' => $roleOptionsHtml
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid Request']);
?>