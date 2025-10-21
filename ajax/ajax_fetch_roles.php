<?php
// ajax/ajax_fetch_roles.php

session_start();
require '../include/config.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host') {
    http_response_code(401);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['category']) && isset($_POST['complexity'])) {
    $category = htmlspecialchars(trim($_POST['category']));
    $complexity = htmlspecialchars(trim($_POST['complexity']));

    // Fetching data including the new 'suggested_counts'
    $stmt = $conn->prepare("SELECT suggested_roles, suggested_languages, suggested_counts, min_team_size, max_team_size FROM role_configurations 
                            WHERE project_category = ? AND complexity_level = ?");
    $stmt->bind_param("ss", $category, $complexity);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        $roles = explode(',', $row['suggested_roles']);
        $languages = explode(',', $row['suggested_languages']); 
        $counts_string = $row['suggested_counts'];
        $min_size = $row['min_team_size'];
        $max_size = $row['max_team_size'];

        // STYLED OUTPUT
        echo "<h4 class='text-lg font-bold text-gray-800 border-b-2 border-indigo-200 pb-2 mb-3'>✅ Suggested Team Structure</h4>";
        echo "<p class='text-sm text-gray-600 mb-4'>Total Team Size: <span class='font-bold text-indigo-600'>{$min_size} to {$max_size} members</span></p>";
        
        // --- 1. ROLES SECTION (Indigo Chips) ---
       // --- 1. ROLES SECTION (Indigo Chips) ---
echo "<label class='block text-sm font-semibold mb-2 text-indigo-700'>👷 Recommended Roles (Click to add to Roles List):</label>";
echo "<div class='flex flex-wrap gap-2 mb-6'>";
foreach ($roles as $role) {
    $role_trim = trim($role);
    // 🔑 FIX: data-role, data-count എന്നിവ ചേർക്കുന്നു. data-input='roles' നിലനിർത്തിയിരിക്കുന്നു.
    echo "<span class='suggested-chip bg-indigo-100 text-indigo-700 text-sm font-medium px-3 py-1 rounded-full cursor-pointer shadow-sm hover:bg-indigo-200 transition duration-150' 
        data-input='roles' 
        data-value='{$role_trim}'
        data-role='{$role_trim}' 
        data-count='1' >{$role_trim}</span>";
}
echo "</div>";
        // --- 2. LANGUAGE/SKILLS SECTION (Green Chips) ---
        echo "<label class='block text-sm font-semibold mb-2 text-green-700'>💻 Suggested Technologies (Click to add to Skills List):</label>";
        echo "<div class='flex flex-wrap gap-2 mb-6'>";
        foreach ($languages as $lang) {
            $lang_trim = trim($lang);
            // Enhanced Green Chip Style
            echo "<span class='suggested-chip bg-green-100 text-green-700 text-sm font-medium px-3 py-1 rounded-full cursor-pointer shadow-sm hover:bg-green-200 transition duration-150' data-input='skills' data-value='{$lang_trim}'>{$lang_trim}</span>";
        }
        echo "</div>";

        // --- 3. TEAM SIZE PER ROLE SUGGESTION (Orange/Yellow Chips) ---
        if (!empty($counts_string)) {
            $counts = explode(',', $counts_string);
            echo "<label class='block text-sm font-semibold mb-2 text-orange-700'>👥 Suggested Role Counts (Click to add to Team Size per Role List):</label>";
            echo "<div class='flex flex-wrap gap-2'>";
            foreach ($counts as $count_pair) {
                $count_trim = trim($count_pair);
                // Enhanced Orange/Yellow Chip Style
                echo "<span class='suggested-chip bg-amber-100 text-amber-700 text-sm font-medium px-3 py-1 rounded-full cursor-pointer shadow-sm hover:bg-amber-200 transition duration-150' data-input='team_counts' data-value='{$count_trim}'>{$count_trim}</span>";
            }
            echo "</div>";
        }

    } else {
        echo "<p class='text-sm text-gray-600 font-medium'>No tailored suggestions available for the combination '{$category}' and '{$complexity}'. Please list the requirements manually.</p>";
    }
    $stmt->close();
}
?>