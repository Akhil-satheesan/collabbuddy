<?php
// FILE: include/host_sections/project_details.php
// Loads project details for editing/viewing in a slide-over modal.

// 1. സുരക്ഷാ പരിശോധന (Authorization Check)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host' || !isset($_GET['project_id'])) {
    http_response_code(403);
    exit("Unauthorized access or missing Project ID.");
}

$project_id = (int)$_GET['project_id'];
$host_id = $_SESSION['user_id'];

// 2. Project Data Fetch (Assume $conn is available)
$stmt = $conn->prepare("SELECT * FROM projects WHERE project_id = ? AND host_id = ?");
if ($stmt === false) {
    exit("<p class='p-4 text-red-600'>Database Prepare failed: " . htmlspecialchars($conn->error) . "</p>");
}
$stmt->bind_param("ii", $project_id, $host_id);
$stmt->execute();
$result = $stmt->get_result();
$project = $result->fetch_assoc();
$stmt->close();

if (!$project) {
    http_response_code(404);
    echo "<div class='p-8'><p class='text-xl text-red-600'>Project ID: {$project_id} not found or access denied.</p></div>";
    exit;
}

// 3. അനുബന്ധ ഡാറ്റാ (Stats - Simplified)
// (Here you would include your actual queries to get member and application counts)
$members = 0;
$applications = 0;

// 4. ഡിസ്പ്ലേ വേരിയബിളുകൾ
$status_options = ['Active', 'In Progress', 'Completed', 'Cancelled', 'On Hold'];
$required_size = (int)filter_var($project['team_size'], FILTER_SANITIZE_NUMBER_INT);

$duration_display = !empty($project['end_date']) 
    ? "Due Date: " . date('M d, Y', strtotime($project['end_date'])) 
    : "Duration: " . htmlspecialchars($project['duration']);

$current_status_lower = strtolower($project['status']);
?>

<form id="projectDetailsForm" class="flex flex-col h-full bg-white shadow-2xl" onsubmit="handleProjectUpdate(event);">
    <input type="hidden" name="project_id" value="<?= $project_id ?>">
    <input type="hidden" name="action" value="update_details">

    <div class="p-6 border-b border-gray-100 flex justify-between items-center sticky top-0 bg-white z-20 shadow-lg">
        <h2 class="text-3xl font-extrabold text-gray-900 truncate"><?= htmlspecialchars($project['title']) ?></h2>
        <button type="button" onclick="closeDetailsPanel()" class="text-gray-500 hover:text-indigo-600 transition p-2 rounded-full hover:bg-gray-100">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>

    <div class="flex-1 p-8 space-y-10 overflow-y-auto bg-gray-50">
        
        <div class="bg-indigo-600 p-6 rounded-2xl shadow-xl text-white">
            <p class="text-sm font-bold uppercase mb-4 opacity-80">Project Metrics</p>
            <div class="grid grid-cols-3 gap-6 text-center">
                <div>
                    <p class="text-2xl font-extrabold"><?= $members ?></p>
                    <p class="text-sm opacity-90">Team Members</p>
                </div>
                <div>
                    <p class="text-2xl font-extrabold"><?= $required_size > 0 ? $required_size : 'N/A' ?></p>
                    <p class="text-sm opacity-90">Target Size</p>
                </div>
            
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-100">
            <p class="text-lg font-bold text-gray-900 mb-2">Timeline & Status</p>
            <p class="text-base text-gray-700 font-medium"><?= $duration_display ?></p>
            <span class="inline-block text-sm font-bold mt-3 px-4 py-1.5 rounded-full 
                <?= match($current_status_lower) {
                    'active'        => 'bg-green-500 text-white shadow-md',
                    'in progress'   => 'bg-blue-500 text-white shadow-md',
                    'completed'     => 'bg-gray-400 text-white shadow-md',
                    'cancelled'     => 'bg-red-500 text-white shadow-md',
                    'on hold'       => 'bg-yellow-500 text-gray-900 shadow-md',
                    default         => 'bg-gray-400 text-white shadow-md'
                } ?>">
                Status: <?= htmlspecialchars($project['status']) ?>
            </span>
        </div>

        <div class="space-y-8 pt-6 border-t border-gray-200">

            <div>
                <label for="description" class="block text-base font-semibold text-gray-800 mb-2">Project Description</label>
                <textarea name="description" id="description" rows="5" required 
                    class="w-full border-gray-300 rounded-xl shadow-inner focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 p-4 text-gray-900 bg-white/90 transition-all duration-200"><?= htmlspecialchars($project['description']) ?></textarea>
            </div>

            <div>
                <label for="required_roles_list" class="block text-base font-semibold text-gray-800 mb-2">Required Roles (Comma Separated)</label>
                <input type="text" name="required_roles_list" id="required_roles_list" required value="<?= htmlspecialchars($project['required_skills'] ?? '') ?>" 
                    class="w-full border-gray-300 rounded-xl shadow-inner focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 p-4 text-gray-900 bg-white/90 transition-all duration-200">
            </div>

            <div>
                <label for="team_size" class="block text-base font-semibold text-gray-800 mb-2">Target Team Size (People)</label>
                <input type="number" name="team_size" id="team_size" required min="1" value="<?= htmlspecialchars($required_size) ?>" 
                    class="w-full border-gray-300 rounded-xl shadow-inner focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 p-4 text-gray-900 bg-white/90 transition-all duration-200">
            </div>
            
             <div>
                <label for="new_status" class="block text-base font-semibold text-gray-800 mb-2">Change Status To</label>
                <select name="new_status" id="new_status" required 
                    class="w-full border-gray-300 rounded-xl shadow-inner focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 p-4 text-gray-900 bg-white/90 transition-all duration-200">
                    <option value="<?= htmlspecialchars($project['status']) ?>">Current: <?= htmlspecialchars($project['status']) ?></option>
                    <?php foreach ($status_options as $status): ?>
                        <?php if ($status !== $project['status']): ?>
                            <option value="<?= $status ?>"><?= $status ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div id="project-update-message" class="mt-6 text-center hidden p-4 rounded-xl text-base font-semibold transition-all duration-300 shadow-md"></div>

    </div>

    <div class="p-5 border-t border-gray-100 bg-white sticky bottom-0 z-10 flex justify-end space-x-4 shadow-2xl">
        
        <button type="button" onclick="closeDetailsPanel()" class="px-6 py-3 text-base font-semibold text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-100 transition shadow-lg">
            Close
        </button>

        <button type="submit" class="px-6 py-3 text-base font-semibold text-white bg-indigo-600 border border-transparent rounded-xl shadow-lg hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-offset-2 focus:ring-indigo-500 transition">
            <i class="fas fa-save mr-2"></i> Save Updates
        </button>
    </div>
</form>
<script>
 
function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        console.error('Toast container missing! Cannot display message.');
        return;
    }

    const bgColor = type === 'success' ? 'bg-green-500' : 
                    type === 'error' ? 'bg-red-500' : 'bg-yellow-500';
    const icon = type === 'success' ? '✅' : type === 'error' ? '❌' : '⚠️';

    const toast = document.createElement('div');
    toast.className = `fixed top-5 right-5 z-50 p-4 rounded-xl shadow-2xl text-white ${bgColor} transform transition-all duration-500 opacity-0 translate-y-[-50px]`;
    toast.innerHTML = `<div class="flex items-center space-x-3">
                            <span>${icon}</span>
                            <span class="font-semibold text-base">${message}</span>
                        </div>`;
    
    toastContainer.appendChild(toast);

    // Show the toast
    setTimeout(() => {
        toast.classList.remove('opacity-0', 'translate-y-[-50px]');
    }, 10);

    // Hide the toast after 4 seconds
    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-[-50px]');
        setTimeout(() => toast.remove(), 500); // Remove after transition
    }, 4000);
}

/**
 * Handles the AJAX submission of the project details form.
 */
function handleProjectUpdate(event) {
    event.preventDefault(); 
    
    const form = document.getElementById('projectDetailsForm');
    const formData = new FormData(form);
    const messageDiv = document.getElementById('project-update-message');
    
    // 1. Loading State
    const saveButton = form.querySelector('button[type="submit"]');
    saveButton.disabled = true;
    saveButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
    messageDiv.classList.add('hidden');

    // 2. AJAX Fetch
    // 💡 IMPORTANT: Verify the path to your backend script here. 
    // This assumes ajax_update_project.php is in the same directory or accessible via a relative path.
    const updateUrl = 'include/host_sections/ajax_update_project.php'; 

    fetch(updateUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // If the server response is an error (e.g., 400, 500), throw an error
        // Note: This needs to handle the 404 HTML response we discussed. 
        // If the server sends a 200 but the body is 404 HTML, response.ok is true, but .json() will fail.
        return response.text(); // Read as raw text first
    })
    .then(text => {
        try {
            const data = JSON.parse(text);
            
            // 3. Success/Error Handling
            if (data.success) {
                showToast('✅ Project details updated successfully!', 'success');
                // Optional: Reload the section if necessary
                if (window.loadSection) {
                    // Assuming 'my_projects' is the host's project list section
                    window.loadSection('my_projects'); 
                }
            } else {
                // Display internal server errors or validation messages
                messageDiv.textContent = '❌ Update Failed: ' + data.message;
                messageDiv.className = 'mt-6 text-center p-4 rounded-xl text-base font-semibold transition-all duration-300 shadow-md bg-red-100 text-red-700';
                messageDiv.classList.remove('hidden');
            }
        } catch (e) {
            // This Catch runs if the response is NOT clean JSON (i.e., the 404 HTML page)
            console.error("Failed to parse JSON response:", text);
            
            // Check if the response contains the 404 signature
            let errorMessage = "An unknown error occurred.";
            if (text.includes("404 Not Found") || text.includes("Database Prepare failed")) {
                 errorMessage = "Server Error: The request was successful, but the server returned an HTML error instead of JSON. Check the backend script's output buffering or file paths.";
            } else {
                 errorMessage = "Response was not JSON. Server outputted unexpected data.";
            }

            showToast('❌ ' + errorMessage, 'error');
            messageDiv.textContent = errorMessage;
            messageDiv.className = 'mt-6 text-center p-4 rounded-xl text-base font-semibold transition-all duration-300 shadow-md bg-red-100 text-red-700';
            messageDiv.classList.remove('hidden');
        }
    })
    .catch(error => {
        showToast('❌ Failed to connect to server: ' + error.message, 'error');
        messageDiv.textContent = 'Network Error: ' + error.message;
        messageDiv.className = 'mt-6 text-center p-4 rounded-xl text-base font-semibold transition-all duration-300 shadow-md bg-red-100 text-red-700';
        messageDiv.classList.remove('hidden');
    })
    .finally(() => {
        // 4. Reset Button State
        saveButton.disabled = false;
        saveButton.innerHTML = '<i class="fas fa-save mr-2"></i> Save Updates';
    });
}
</script>