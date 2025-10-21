<?php
require './include/config.php';

$participant_id = $_SESSION['user_id'];
$participant = $conn->query("SELECT preferred_role, skills, languages FROM participants WHERE participant_id=$participant_id")->fetch_assoc();
$preferred_role = $participant['preferred_role'];
$languages = $participant['languages'];
?>

<div class="p-6 bg-white border-b border-gray-200 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Browse Projects</h1>
        <p class="text-gray-600 mt-1">Discover collaboration opportunities</p>
    </div>

    <div class="flex items-center space-x-3 mb-6">
    <select id="categoryFilter" class="border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500">
        <option value="">All Categories</option>
        <option value="Web Development">Web App</option>
        <option value="Mobile App">Mobile App</option>
        <option value="Data Analytics">Data Analytics</option>
        <option value="UI/UX Design">UI/UX Design</option>
        <option value="Marketing">Marketing</option>
    </select>

    <div class="p-6 bg-white border-b border-gray-200 flex flex-col md:flex-row items-center justify-between space-y-3 md:space-y-0">
    <div class="flex-1 w-full md:mr-4 relative">
        <input 
            type="text" 
            id="searchInput" 
            placeholder="Search projects, skills, or languages..." 
            class="w-full border border-gray-300 rounded-full px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
            autocomplete="off">

        <!-- Suggestions dropdown -->
        <ul id="suggestions" class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-72 overflow-y-auto hidden"></ul>
    </div>

    <button id="searchBtn" class="flex items-center bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white font-semibold px-6 py-3 rounded-full shadow-md transition-all duration-300 transform hover:scale-105">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1110.5 3a7.5 7.5 0 016.15 13.65z" />
        </svg>
        Search
    </button>
</div>

</div>

</div>

<div id="projectFeed" class="p-6 space-y-6">
    <!-- Projects loaded via AJAX -->
</div>

<script>
function initBrowseProjects() {
    function loadProjects() {
        let search = $('#searchInput').val();
        let category = $('#categoryFilter').val();

        $.ajax({
    url: 'include/participant_sections/fetch_projects.php', // correct path
    method: 'GET',
    data: { search: search, category: category },
    success: function(data) {
        $('#projectFeed').html(data);
    },
    error: function(xhr, status, error) {
        $('#projectFeed').html('<p class="text-red-600">Error loading projects: ' + error + '</p>');
        console.error(xhr.responseText);
    }
});

    }

    // Load projects by default
    loadProjects();

    $('#searchBtn').off('click').on('click', loadProjects);
    $('#searchInput').off('keypress').on('keypress', function(e){ if(e.which==13) loadProjects(); });
    $('#categoryFilter').off('change').on('change', loadProjects);
}
$(document).ready(function(){

function loadProjects(search = '') {
    $.ajax({
        url: 'include/participant_sections/fetch_projects.php',
        method: 'GET',
        data: { search: search },
        success: function(data) {
            $('#projectFeed').html(data);
        },
        error: function(xhr){
            console.error(xhr.responseText);
        }
    });
}

// Initial load
loadProjects();

// Live suggestions
$('#searchInput').on('input', function(){
    let query = $(this).val().trim();
    let $suggestions = $('#suggestions');

    if(query.length < 1){
        $suggestions.hide();
        return;
    }

    $.ajax({
        url: 'include/participant_sections/search_suggestions.php',
        method: 'GET',
        data: { term: query },
        success: function(data){
            let items = JSON.parse(data);
            let html = '';

            if(items.length > 0){
                items.forEach(item => {
                    html += `<li class="px-4 py-2 hover:bg-blue-100 cursor-pointer suggestion-item">
                                <span class="font-semibold">${item.name}</span> <span class="text-gray-500 text-sm">(${item.type})</span>
                             </li>`;
                });
            } else {
                html = `<li class="px-4 py-2 text-gray-500 cursor-default">No items found</li>`;
            }

            $suggestions.html(html).show();

            // Click on suggestion
            $('.suggestion-item').off('click').on('click', function(){
                let text = $(this).find('span:first').text();
                $('#searchInput').val(text);
                $suggestions.hide();
                loadProjects(text); // load projects based on selection
            });
        }
    });
});

// Hide dropdown when clicking outside
$(document).click(function(e){
    if(!$(e.target).closest('#searchInput, #suggestions').length){
        $('#suggestions').hide();
    }
});

// Trigger search on button click or Enter key
$('#searchBtn').on('click', function(){ loadProjects($('#searchInput').val()); });
$('#searchInput').on('keypress', function(e){ if(e.which == 13) loadProjects($(this).val()); });

});

// ഇത് നിങ്ങളുടെ .js ഫയലിൽ ഉണ്ടായിരിക്കണം:

function closeHostProfileModal() {
    const modal = document.getElementById('hostProfileModal');
    const content = document.getElementById('hostProfileContent');

    // Pop-up effect തിരിച്ചു കളയുന്നു
    content.classList.add('scale-95', 'opacity-0');
    content.classList.remove('scale-100', 'opacity-100');

    // Animation കഴിഞ്ഞ ശേഷം modal മറയ്ക്കുന്നു
    setTimeout(() => {
        modal.classList.add('hidden');
        document.getElementById('hostProfileDetailsArea').innerHTML = '';
        document.getElementById('hostModalName').innerText = 'Host Profile'; // പേര് റീസെറ്റ് ചെയ്യുന്നു
    }, 300); // CSS transition time (300ms)
}

// Call this function after the section is loaded
initBrowseProjects();
</script>
