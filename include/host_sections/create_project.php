<?php
// include/host_sections/create_project.php

if (!isset($panel_html_included)) : 
$panel_html_included = true;
?>

<div class="p-8 h-full flex flex-col">

    <div class="flex justify-between items-center border-b pb-4 mb-6">
        <h3 class="text-3xl font-extrabold text-indigo-700 flex items-center">
            ✨ Post a New Project Idea
        </h3>
        <button id="closePanelBtn" type="button" onclick="closeProjectPanel()" class="text-gray-400 hover:text-gray-900 text-4xl leading-none transition duration-150 p-1 rounded-full hover:bg-gray-100">&times;</button>
    </div>

    <form id="projectPostForm" action="include/host_sections/actions/process_project_post.php" method="POST" class="flex-1 overflow-y-auto space-y-5">
        
        <!-- Project Title -->
        <div class="form-group">
            <label class="block text-sm font-semibold text-gray-700 mb-1">Project Title</label>
            <input type="text" name="title" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 transition duration-150">
        </div>

        <div class="flex space-x-4">
            <!-- Project Category -->
            <div class="form-group flex-1">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Project Category</label>
                <select id="projectCategory" name="project_category" required class="w-full p-3 border border-gray-300 rounded-lg bg-white focus:ring-indigo-500 focus:border-indigo-500 transition duration-150">
                    <option value="">-- Select Category --</option>
                    <option value="Web App">Web Application 🌐</option>
                    <option value="Mobile App">Mobile Application 📱</option>
                    <option value="Data Analytics">Data Analytics / ML 📊</option>
                    <option value="Design & Branding">Design & Branding 🎨</option>
                    <option value="Game Dev">Game Development 🎮</option>
                    <option value="Hardware/IoT">Hardware/IoT 💡</option>
                    <option value="Other">Other / General</option>
                </select>
            </div>

            <!-- Complexity -->
            <div class="form-group flex-1">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Complexity</label>
                <select id="complexityLevel" name="complexity_level" required class="w-full p-3 border border-gray-300 rounded-lg bg-white focus:ring-indigo-500 focus:border-indigo-500 transition duration-150">
                    <option value="">-- Select Complexity --</option>
                    <option value="Simple">Simple (1-2 Weeks)</option>
                    <option value="Medium">Medium (1-2 Months)</option>
                    <option value="Complex">Complex (3+ Months)</option>
                </select>
            </div>
        </div>

        <!-- Suggestion Area for Category+Complexity -->
        <div id="roleSuggestionArea" class="p-4 bg-gray-50 border border-dashed border-gray-300 rounded-xl text-sm transition duration-300">
            <p class="text-gray-600 font-medium">✨ Suggestions appear here after selecting Category & Complexity.</p>
        </div>
        
        <!-- Required Roles -->
        <div class="form-group relative">
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Required Roles <small class="text-gray-500">(e.g., Frontend Dev, QA Tester)</small>
            </label>
            <input type="text" id="finalRolesInput" name="required_roles_list" required 
                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                placeholder="e.g., Frontend Developer, Backend Developer, UI Designer">

            <!-- Container for live suggestions -->
            <ul id="roleLiveSuggestions" 
                class="absolute z-50 w-full bg-white border border-gray-300 rounded shadow-md mt-1 hidden max-h-40 overflow-y-auto text-sm">
            </ul>

            <small class="text-gray-500 text-xs">💡 After entering roles, “Team Size per Role” will auto-fill below.</small>
        </div>

        <!-- Team Size per Role (auto-filled) -->
        <div class="form-group">
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Team Size per Role <small class="text-gray-500">(e.g., Coder: 2, Designer: 1)</small>
            </label>
            <input type="text" id="finalTeamSizeInput" name="team_size_per_role" 
                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                placeholder="Auto-filled based on roles above">
        </div>
        
        <!-- Required Skills -->
        <div class="form-group">
            <label class="block text-sm font-semibold text-gray-700 mb-1">Required Technology Stack / Languages</label>
            <input type="text" id="finalSkillsInput" name="required_skills" 
                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                placeholder="e.g., Python, ReactJS">
        </div>

        <!-- Total Team Size -->
        <div class="form-group">
            <label class="block text-sm font-semibold text-gray-700 mb-1">Total Team Size</label>
            <input type="text" name="team_size" placeholder="Total number of members" 
                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
        </div>

        <!-- Host Participation -->
        <div class="form-group border-t pt-5">
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                Host Participation
            </label>
            <div class="flex items-center p-3 border border-gray-300 rounded-lg bg-white">
                <input type="checkbox" id="hostIsMember" name="host_is_member" value="1" 
                    class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                <label for="hostIsMember" class="ml-2 block text-sm text-gray-900 font-medium">
                    I will also be a team member (participant) in this project.
                </label>
            </div>
        </div>

        <!-- Host Role (if participating) -->
        <div class="form-group hidden" id="hostRoleField">
            <label class="block text-sm font-semibold text-gray-700 mb-1">My Role in the Team</label>
            <input type="text" id="hostRoleInput" name="host_role" 
                placeholder="e.g., Lead Backend Developer" 
                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
        </div>

        <!-- Description -->
        <div class="form-group">
            <label class="block text-sm font-semibold text-gray-700 mb-1">Project Description</label>
            <textarea name="description" required 
                class="w-full p-3 border border-gray-300 rounded-lg h-28 focus:ring-indigo-500 focus:border-indigo-500"
                placeholder="Briefly describe your project..."></textarea>
        </div>

        <!-- Duration -->
        <div class="form-group">
            <label class="block text-sm font-semibold text-gray-700 mb-1">Estimated Duration</label>
            <input type="text" name="duration" placeholder="e.g., 2 weeks or 3 months" 
                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
        </div>

        <div id="project-post-message" class="hidden p-3 rounded-lg text-sm font-medium"></div>

        <!-- Buttons -->
        <div class="pt-6 border-t mt-auto space-y-3">
            <button type="submit" id="postProjectSubmitBtn" 
                class="bg-indigo-600 hover:bg-indigo-700 text-white p-3 rounded-xl w-full font-bold shadow-md hover:shadow-lg transition duration-150">
                🚀 Post Project
            </button>
            <button type="button" id="cancelProjectBtn" onclick="closeProjectPanel()" 
                class="bg-gray-200 hover:bg-gray-300 text-gray-800 p-3 rounded-xl w-full font-medium transition duration-150">
                Cancel
            </button>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {

    const rolesInput = $('#finalRolesInput');
    const teamSizeInput = $('#finalTeamSizeInput');
    const totalSizeInput = $('input[name="team_size"]');
    const suggestionList = $('#roleLiveSuggestions');

    // CATEGORY + COMPLEXITY SUGGESTIONS
    function fetchRoleSuggestions() {
        const category = $('#projectCategory').val();
        const complexity = $('#complexityLevel').val();
        const area = $('#roleSuggestionArea');

        if (!category || !complexity) {
            area.html('<p class="text-gray-600 font-medium">✨ Suggestions appear here after selecting Category & Complexity.</p>');
            return;
        }

        area.html('<p class="text-indigo-500 font-medium">Loading suggestions...</p>');

        $.ajax({
            url: 'ajax/ajax_fetch_roles.php',
            type: 'POST',
            data: {category, complexity},
            success: function(res){
                area.html(res);
            },
            error: function(){
                area.html("<p class='text-sm text-red-600'>❌ Error loading suggestions.</p>");
            }
        });
    }

    $('#projectCategory, #complexityLevel').on('change', fetchRoleSuggestions);

    // HOST TOGGLE
    $('#hostIsMember').on('change', function() {
        if ($(this).is(':checked')) {
            $('#hostRoleField').slideDown(200);
            $('#hostRoleInput').prop('required', true);
        } else {
            $('#hostRoleField').slideUp(200);
            $('#hostRoleInput').prop('required', false).val('');
        }
    });

    // LIVE ROLE AUTOCOMPLETE (Google style)
    rolesInput.on('input', function() {
        const term = $(this).val().trim();

        if (!term) {
            suggestionList.empty().hide();
            return;
        }

        $.ajax({
            url: 'ajax/role_master.php',
            method: 'GET',
            data: { query: term },
            success: function(response) {
                const roles = JSON.parse(response);
                suggestionList.empty();

                if (roles.length === 0) {
                    suggestionList.hide();
                    return;
                }

                roles.forEach(role => {
                    suggestionList.append(`<li class="px-3 py-2 hover:bg-indigo-100 cursor-pointer suggestion-item">${role}</li>`);
                });

                suggestionList.show();
            }
        });
    });

    suggestionList.on('click', '.suggestion-item', function() {
        const role = $(this).text();
        let current = rolesInput.val().trim();

        if (current) {
            const arr = current.split(',').map(r => r.trim());
            if (!arr.includes(role)) current += ', ' + role;
        } else {
            current = role;
        }

        rolesInput.val(current).trigger('input');
        suggestionList.empty().hide();
    });

    $(document).on('click', function(e) {
        if (!rolesInput.is(e.target) && !suggestionList.is(e.target) && suggestionList.has(e.target).length === 0) {
            suggestionList.hide();
        }
    });

    // AUTO-FILL TEAM SIZE
    function updateTeamSizePerRole() {
        const rolesText = rolesInput.val().trim();
        if (!rolesText) {
            teamSizeInput.val('');
            totalSizeInput.val('');
            return;
        }

        const roles = rolesText.split(',').map(r => r.trim()).filter(r => r.length > 0);
        teamSizeInput.val(roles.map(r => `${r}: `).join(', '));
        calculateTotalTeamSize();
    }

    function calculateTotalTeamSize() {
        const text = teamSizeInput.val();
        if (!text) {
            totalSizeInput.val('');
            return;
        }

        let total = 0;
        text.split(',').forEach(pair => {
            const num = parseInt(pair.split(':')[1]);
            if (!isNaN(num)) total += num;
        });

        totalSizeInput.val(total || '');
    }

    rolesInput.on('input', updateTeamSizePerRole);
    teamSizeInput.on('input', calculateTotalTeamSize);

    const originalVal = $.fn.val;
    $.fn.val = function(value) {
        const result = originalVal.apply(this, arguments);
        if (arguments.length) {
            if (this.is('#finalRolesInput')) updateTeamSizePerRole();
            if (this.is('#finalTeamSizeInput')) calculateTotalTeamSize();
        }
        return result;
    };
});
</script>

<?php endif; ?>
