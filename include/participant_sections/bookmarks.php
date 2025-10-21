<?php
// my_bookmarks.php - Participant's Bookmarked Projects Vie
require __DIR__ . '/../config.php'; 

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'participant'){
    echo "Unauthorized";
    exit;
}

$participant_id = $_SESSION['user_id'];
// AJAX വഴി ഫിൽട്ടർ ചെയ്യുന്നതിനാൽ ഇവിടെ $selected_status ഉപയോഗിക്കേണ്ടതില്ല.
// എങ്കിലും, ആദ്യമായി പേജ് ലോഡ് ചെയ്യുമ്പോൾ 'All' കാണിക്കാനായി ഫിൽട്ടർ സ്റ്റാറ്റസ് വേണമെങ്കിൽ സെറ്റ് ചെയ്യാം.
$selected_status = isset($_GET['status']) ? $_GET['status'] : 'All'; 

// --- SQL: Initial Load (All Bookmarks) ---
$stmt = $conn->prepare("
    SELECT 
        p.*, 
        b.created_at AS bookmarked_at, 
        u.name AS host_name,
        (
            SELECT status F
            FROM applications a 
            WHERE a.project_id = p.project_id 
            AND a.participant_id = ? 
            LIMIT 1
        ) AS application_status
    FROM projects p
    JOIN bookmarks b ON p.project_id = b.project_id
    JOIN users u ON p.host_id = u.user_id
    WHERE b.participant_id=?
    ORDER BY b.created_at DESC
");
$stmt->bind_param("ii", $participant_id, $participant_id);
$stmt->execute();
$result = $stmt->get_result();

// Statuses list for the modern filter buttons
$statuses = [
    'All' => ['label' => 'All', 'icon' => '⭐', 'class' => 'text-indigo-600 border-indigo-600'],
    'Pending' => ['label' => 'Pending', 'icon' => '⏳', 'class' => 'text-yellow-600 border-yellow-600'],
    'Accepted' => ['label' => 'Accepted', 'icon' => '✅', 'class' => 'text-green-600 border-green-600'],
    'Rejected' => ['label' => 'Rejected', 'icon' => '❌', 'class' => 'text-red-600 border-red-600'],
    'Withdrawn' => ['label' => 'Withdrawn', 'icon' => '↩️', 'class' => 'text-gray-600 border-gray-600'],
];
?>

<div id="bookmarks-content" class="p-8 bg-gray-50 min-h-screen">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 border-b pb-4 border-gray-200">
        <h2 class="text-4xl font-extrabold text-gray-900 tracking-tight mb-4 md:mb-0">Your Bookmarked Projects</h2>
        <p id="project-count" class="text-xl text-gray-600 font-semibold"><?= $result->num_rows ?> Project<?= $result->num_rows != 1 ? 's' : '' ?></p>
    </div>
    
    <div class="flex flex-wrap gap-3 mb-8 bg-white p-4 rounded-xl shadow-inner border border-gray-100">
        <span class="text-lg font-semibold text-gray-800 mr-2 flex items-center">Filter:</span>
        <?php foreach ($statuses as $status_key => $data): 
            $isActive = ($selected_status === $status_key) ? 'bg-indigo-50 border-2 shadow-md' : 'border-gray-200 hover:bg-gray-50';
        ?>
            <button 
                type="button" 
                class="filter-btn <?= $isActive ?> <?= $data['class'] ?> transition duration-300 rounded-full px-5 py-2 text-sm font-bold border" 
                data-status="<?= $status_key ?>">
                <?= $data['icon'] ?> <?= $data['label'] ?>
            </button>
        <?php endforeach; ?>
    </div>
    
    <div id="bookmarkToast" class="hidden fixed top-5 right-5 z-50 p-4 rounded-lg text-white font-semibold shadow-xl"></div>

    <div id="project-list-container">
        <?php if($result->num_rows > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php while($row = $result->fetch_assoc()): 
                    // Card display logic remains the same (as per your previous stylish code)
                    $projectId = $row['project_id'];
                    $projectTitle = htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'); 
                    $application_status = $row['application_status'];
                    $has_applied = !empty($application_status);
                    $is_pending = ($application_status === 'Pending');
                    
                    $borderColor = 'border-l-indigo-500';
                    if ($is_pending) $borderColor = 'border-l-yellow-500';
                    elseif ($application_status === 'Accepted') $borderColor = 'border-l-green-600';
                ?>
                    <div class="bg-white shadow-2xl hover:shadow-3xl transition-all duration-500 rounded-xl border border-gray-100 project-card overflow-hidden cursor-pointer <?= $borderColor ?> border-l-4" data-project-id="<?= $projectId ?>">
                        <div class="flex items-start p-5 border-b border-gray-100">
                            <div class="w-12 h-12 bg-indigo-600 rounded-full flex items-center justify-center text-white font-bold text-xl flex-shrink-0 shadow-lg mr-4">
                                <?= strtoupper(substr($row['title'],0,2)) ?>
                            </div>
                            <div class="flex-1 overflow-hidden">
                                <h3 class="text-xl font-bold text-gray-900 truncate mb-1"><?= htmlspecialchars($row['title']) ?></h3>
                                <p class="text-sm text-gray-500"><?= htmlspecialchars($row['project_category'].' • '.$row['host_name']) ?></p>
                            </div>
                            <button class="text-red-500/70 hover:text-red-600 transition-colors ml-4 bookmark-btn" data-project="<?= $projectId ?>" title="Remove Bookmark">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                        <div class="p-5 space-y-4">
                            <p class="text-gray-700 text-base leading-snug"><?= htmlspecialchars(substr($row['description'], 0, 120)) ?>...</p>
                            <div class="flex justify-between items-center pt-2">
                                <?php if (!$has_applied): ?>
                                    <button onclick="openApplyModal('<?= $projectId ?>', '<?= $projectTitle ?>')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg font-semibold transition shadow-md hover:shadow-xl transform hover:scale-105">
                                        Apply Now
                                    </button>
                                <?php elseif ($is_pending): ?>
                                    <button class="bg-gray-400 text-white px-5 py-2 rounded-lg font-medium cursor-default opacity-80" disabled>
                                        Applied (Pending)
                                    </button>
                                <?php elseif ($application_status === 'Rejected' || $application_status === 'Withdrawn'): 
                                    $reapply_reason = ($application_status === 'Rejected') ? 'Rejected' : 'Withdrawn';
                                ?>
                                    <button onclick="openApplyModal('<?= $projectId ?>', '<?= $projectTitle ?>', true)" class="bg-yellow-500 hover:bg-yellow-600 text-gray-900 px-5 py-2 rounded-lg font-bold transition shadow-md hover:shadow-xl transform hover:scale-105">
                                        Reapply (<?= $reapply_reason ?>)
                                    </button>
                                <?php else: ?>
                                
                                <?php endif; ?>
                                <span class="text-gray-400 text-xs italic">Posted: <?= date("M d, Y", strtotime($row['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div id="no-items-message">
                <p class="text-gray-500 text-center mt-10 p-12 bg-white rounded-2xl shadow-lg border border-dashed border-gray-300">
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path></svg>
                    You have no bookmarked projects yet. Start browsing to find projects you like!
                </p>
            </div>
        <?php endif; ?>
    </div>
    
    <div id="no-filtered-results" class="hidden text-gray-500 text-center mt-10 p-12 bg-white rounded-2xl shadow-lg border border-dashed border-gray-300">
        <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"></path></svg>
        <p class="text-lg font-semibold">No Bookmarked Projects Found</p>
        <p>There are no projects with the selected application status in your bookmarks.</p>
    </div>
</div>

<?php 
$stmt->close(); 
?>

<div id="applyModal" class="hidden fixed inset-0 z-50 bg-black bg-opacity-80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white p-10 rounded-3xl shadow-3xl w-full max-w-lg transform transition-all duration-300 scale-100">
        
        <h2 class="text-3xl font-extrabold text-gray-900 mb-6 border-b pb-3" id="modalHeader"></h2> 
        
        <form class="space-y-5" id="applicationForm" action="include/participant_sections/apply_project.php" method="POST" enctype="multipart/form-data">
            
            <input type="hidden" name="project_id" id="modal_project_id">
            <input type="hidden" name="reapply_status" id="modal_reapply_status" value="false"> 

            <div>
                <label for="cover_message" class="block text-sm font-semibold text-gray-700 mb-2">Cover Message *</label>
                <textarea name="cover_message" id="cover_message" rows="5" 
                          class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-200 resize-none shadow-inner" 
                          placeholder="Tell the host why you're interested and what you can contribute..." required></textarea>
            </div>

            <div>
                <label for="resume" class="block text-sm font-semibold text-gray-700 mb-2">Resume/CV (Optional)</label>
                <input type="file" name="resume" id="resume" accept=".pdf,.doc,.docx" 
                        class="w-full file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 
                              border border-gray-300 rounded-xl px-4 py-3 text-sm text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div>
                <label for="availability" class="block text-sm font-semibold text-gray-700 mb-2">Availability</label>
                <select name="availability" id="availability" required 
                        class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-200 shadow-inner">
                    <option value="">-- Select Your Availability --</option>
                    <option value="Full-time">Full-time (40+ hours/week)</option>
                    <option value="Part-time">Part-time (20-30 hours/week)</option>
                    <option value="Flexible">Flexible (10-20 hours/week)</option>
                    <option value="Weekend only">Weekend only</option>
                </select>
            </div>

            <div class="flex space-x-4 pt-4">
                <button type="submit" name="apply_project" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-xl font-bold transition duration-200 shadow-lg hover:shadow-xl transform hover:scale-[1.02]">
                    🚀 Submit Application
                </button>
                <button type="button" onclick="closeApplyModal()" class="px-6 py-3 border-2 border-gray-300 rounded-xl text-gray-700 hover:bg-gray-100 transition duration-200 font-medium">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>
<script>
// --- Modal Functions (unchanged) ---

function closeApplyModal() {
    $('#applyModal').addClass('hidden');
    $('#modal_reapply_status').val('false'); 
    $('form', '#applyModal')[0].reset();
}

function openApplyModal(projectId, projectTitle, isReapply = false) {
    $('#modal_project_id').val(projectId); 
    const decodedTitle = projectTitle.replace(/&quot;/g, '"').replace(/&#039;/g, "'").replace(/&amp;/g, '&');
    
    if (isReapply) {
        $('#modal_reapply_status').val('true'); 
        $('#modalHeader').html(`Reapply for <span class="text-orange-600">${decodedTitle}</span>`); 
    } else {
        $('#modal_reapply_status').val('false'); 
        $('#modalHeader').html(`Apply for <span class="text-indigo-600">${decodedTitle}</span>`);
    }

    $('#applyModal').removeClass('hidden'); 
}

// --- Main Document Ready Logic ---

$(document).ready(function() {
    // 1. AJAX URL Paths (Adjust as needed based on your file structure!)
    // If my_bookmarks.php is in 'participant_sections/' and bookmark_project.php is also in 'participant_sections/'
    const removeBookmarkUrl = 'include/participant_sections/bookmark_project.php'; 
    // AJAX Path to fetch filtered content (assuming you will create this file)
    const fetchContentUrl = 'include/participant_sections/fetch_bookmarks_ajax.php'; 

    // --- 2. BOOKMARK REMOVAL (Delete FIX) ---
    $(document).on('click', '.bookmark-btn', function(e){
        if ($(this).attr('title') !== 'Remove Bookmark') return;
        
        e.preventDefault(); 

        let btn = $(this);
        let projectId = btn.data('project');

        if (btn.data("loading")) return;
        btn.data("loading", true);

        // Execute the AJAX call
        $.post(removeBookmarkUrl, {project_id: projectId}, function(resp){
            btn.data("loading", false);
            
            if(resp.success && resp.action === 'removed'){
                // FIX: Instantly fade out and remove the card
                $(`.project-card[data-project-id="${projectId}"]`).fadeOut(300, function() {
                    $(this).remove();
                    
                    // Update project count (Simple count logic)
                    var currentCount = $('#project-list-container .project-card').length;
                    $('#project-count').text(`${currentCount} Project${currentCount !== 1 ? 's' : ''}`);

                    // If all cards are removed after filter, show No Items message
                    if (currentCount === 0) {
                        $('#no-filtered-results').removeClass('hidden');
                    }
                });

                // Show Toast notification
                let toast = $("#bookmarkToast");
                toast.removeClass("hidden bg-green-600")
                     .addClass("bg-red-600")
                     .text("❌ Project removed from bookmarks")
                     .fadeIn();
                setTimeout(() => { toast.fadeOut(); }, 2000);
                
            } else {
                alert(resp.message || 'Failed to remove bookmark. Server error.');
            }
        }, 'json').fail(function(xhr) {
            btn.data("loading", false);
            console.error("AJAX Error. Check Network Tab for 404/500 errors. Response:", xhr.responseText); 
            alert('Server request failed. Please check the console.');
        });
    });

    // --- 3. MODERN FILTER LOGIC (AJAX) ---
    $(document).on('click', '.filter-btn', function() {
        const selectedStatus = $(this).data('status');
        
        // Update active class on buttons
        $('.filter-btn').removeClass('bg-indigo-50 border-2 shadow-md').addClass('border-gray-200 hover:bg-gray-50');
        $(this).addClass('bg-indigo-50 border-2 shadow-md').removeClass('border-gray-200 hover:bg-gray-50');

        // Show loading indicator
        $('#project-list-container').html('<div class="text-center p-10 text-xl text-indigo-600"><p>Loading projects...</p></div>');
        $('#no-filtered-results').addClass('hidden');

        // AJAX Request to fetch filtered content
        $.post(fetchContentUrl, {status: selectedStatus}, function(response) {
            
            $('#project-list-container').html(response.html);
            $('#project-count').text(`${response.count} Project${response.count !== 1 ? 's' : ''}`);

            // Show 'No Items' message if count is 0
            if (response.count === 0) {
                $('#no-filtered-results').removeClass('hidden');
            } else {
                $('#no-filtered-results').addClass('hidden');
            }

        }, 'json').fail(function(xhr) {
            $('#project-list-container').html('<div class="text-center p-10 text-xl text-red-600">Error loading data. Please try again.</div>');
            console.error("Filter AJAX Error:", xhr.responseText);
        });
    });
});
</script>