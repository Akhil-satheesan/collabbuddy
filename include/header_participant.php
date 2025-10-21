<?php
// Ensure session is started and user is logged in (already checked in the main dashboard file)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'participant') {
    header("Location: login.php");
    exit;
}

// NOTE: If participate_dashboard.php includes config.php, you can remove the next line.
if (!isset($conn)) {
    require_once 'config.php'; 
}

$participant_id = $_SESSION['user_id'];
$unread_count = 0;
$profile_pic_url = 'assets/default_profile.png';
$display_name = 'Participant'; // Default name
$initials = 'P';

try {
    // --- FETCH USER DETAILS (Name, Profile Pic URL) AND UNREAD NOTIFICATION COUNT ---
    
    // Fetch Name and profile picture URL from the database
    $stmt_user = $conn->prepare("SELECT name, profile_pic_url FROM users WHERE user_id = ?");
    $stmt_user->bind_param("i", $participant_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    
    if ($row_user = $result_user->fetch_assoc()) {
        $full_name = $row_user['name'] ?? 'Participant';
        $display_name = ucwords(strtolower($full_name)); 
        
        $parts = explode(" ", $full_name);
        $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ""));

        if (!empty($row_user['profile_pic_url'])) {
             $profile_pic_url = $row_user['profile_pic_url']; 
        }
    }
    $stmt_user->close();

    // Fetch unread notification count
    $stmt_count = $conn->prepare("SELECT COUNT(id) AS unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt_count->bind_param("i", $participant_id);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    if ($row = $result_count->fetch_assoc()) {
        $unread_count = $row['unread_count'];
    }
    $stmt_count->close();
    
} catch (Exception $e) {
    error_log("Header DB error: " . $e->getMessage());
    $unread_count = 0; 
    // Defaults are used: $display_name = 'Participant', $initials = 'P'
}
?>

<header class="bg-white shadow-lg border-b-2 border-indigo-100 sticky top-0 z-50 transition-smooth"> 
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center space-x-4">
                <div class="text-2xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent transition-smooth">
                    CollabBuddy
                </div>
                <span class="bg-indigo-100 text-indigo-800 px-3 py-1 rounded-full text-sm font-medium transition-smooth">
                    Participant Dashboard
                </span>
            </div>

            <div class="flex items-center space-x-4">
                
                <div class="relative inline-block text-left" id="notificationDropdownContainer">
                    <button class="relative p-2 text-gray-600 hover:text-indigo-600 transition-smooth" id="notificationBtn">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <?php if ($unread_count > 0): ?>
                        <span id="notificationBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center notification-badge transition-smooth">
                            <?= $unread_count > 99 ? '99+' : $unread_count ?>
                        </span>
                        <?php endif; ?>
                    </button>
                    
                    <div id="notificationDropdownMenu" class="origin-top-right absolute right-0 mt-2 w-80 rounded-xl shadow-2xl bg-white ring-1 ring-black ring-opacity-5 focus:outline-none opacity-0 scale-95 transition-all duration-200 transform pointer-events-none z-40" role="menu">
                        <div class="py-1" id="notificationList">
                            <p class="text-gray-500 text-sm p-4 text-center">Loading notifications...</p>
                        </div>
                        <div class="border-t border-gray-100"></div>
                        <a href="#" id="markAllReadBtn" class="block px-4 py-2 text-sm text-center text-indigo-600 hover:bg-indigo-50 rounded-b-xl transition-smooth">Mark all as read</a>
                    </div>
                </div>

                <div class="relative inline-block text-left" id="profileDropdownContainer">
                    <div class="flex items-center space-x-2 cursor-pointer transition-smooth" id="profileBtn">
                        
                        <div class="w-9 h-9 rounded-full flex items-center justify-center text-white font-semibold shadow-md transition-smooth overflow-hidden border-2 border-indigo-200">
                            <?php if ($profile_pic_url != 'assets/default_profile.png'): ?>
                                <img src="<?= htmlspecialchars($profile_pic_url) ?>" alt="<?= htmlspecialchars($display_name) ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full bg-gradient-to-r from-indigo-500 to-purple-500 flex items-center justify-center">
                                    <?= $initials ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <span class="text-sm font-medium text-gray-700 hidden sm:inline transition-smooth">
                            <?= htmlspecialchars($display_name) ?> </span>
                        <svg id="dropdownArrow" class="w-4 h-4 ml-1 text-gray-400 transform transition-transform duration-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </div>

                    <div id="profileDropdownMenu" class="origin-top-right absolute right-0 mt-2 w-56 rounded-xl shadow-2xl bg-white ring-1 ring-black ring-opacity-5 focus:outline-none opacity-0 scale-95 transition-all duration-200 transform pointer-events-none" role="menu" aria-orientation="vertical" aria-labelledby="profileBtn" tabindex="-1">
                        <div class="py-1" role="none">
                            <div class="px-4 py-2 text-sm text-gray-700 border-b border-gray-100 font-bold">
                                <?= htmlspecialchars($display_name) ?>
                            </div>
                            <a href="#" data-section="profile" class="dropdown-item block px-4 py-3 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 transition-smooth rounded-t-xl" role="menuitem" tabindex="-1">
                                👤 View Profile
                            </a>
                            <a href="#" data-section="change_password" class="dropdown-item block px-4 py-3 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 transition-smooth" role="menuitem" tabindex="-1">
                                🔑 Change Password
                            </a>
                            <div class="border-t border-gray-100"></div>
                            <a href="logout.php" class="block px-4 py-3 text-sm text-red-600 hover:bg-red-50 hover:text-red-700 transition-smooth rounded-b-xl" role="menuitem" tabindex="-1">
                                ➡️ Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
<script>
$(document).ready(function(){
    // --- ELEMENT REFERENCES ---
    const profileBtn = $('#profileBtn');
    const profileMenu = $('#profileDropdownMenu');
    const profileArrow = $('#dropdownArrow');
    
    const notificationBtn = $('#notificationBtn');
    const notifMenu = $('#notificationDropdownMenu');
    const notifList = $('#notificationList');
    const notifBadge = $('#notificationBadge');
    const markAllReadBtn = $('#markAllReadBtn');
    
    // --- UTILITY FUNCTIONS ---
    
    // Toggle Profile Dropdown
    function toggleProfileDropdown() {
        const isMenuVisible = profileMenu.hasClass('opacity-100');
        
        if (isMenuVisible) {
            profileMenu.removeClass('opacity-100 scale-100 pointer-events-auto').addClass('opacity-0 scale-95 pointer-events-none');
            profileArrow.removeClass('rotate-180');
        } else {
            // Close notification menu if open
            if (notifMenu.hasClass('opacity-100')) {
                toggleNotificationDropdown();
            }
            profileMenu.removeClass('opacity-0 scale-95 pointer-events-none').addClass('opacity-100 scale-100 pointer-events-auto');
            profileArrow.addClass('rotate-180');
        }
    }
    
    // Toggle Notification Dropdown
    function toggleNotificationDropdown() {
        const isMenuVisible = notifMenu.hasClass('opacity-100');
        
        if (isMenuVisible) {
            notifMenu.removeClass('opacity-100 scale-100 pointer-events-auto').addClass('opacity-0 scale-95 pointer-events-none');
        } else {
             // Close profile menu if open
            if (profileMenu.hasClass('opacity-100')) {
                toggleProfileDropdown();
            }
            loadNotifications(); // Load content when opening
            notifMenu.removeClass('opacity-0 scale-95 pointer-events-none').addClass('opacity-100 scale-100 pointer-events-auto');
        }
    }
    
    // --- NOTIFICATION AJAX LOGIC ---
    
    function loadNotifications() {
        notifList.html('<p class="text-gray-500 text-sm p-4 text-center">Loading notifications...</p>');
        $.ajax({
            url: 'ajax_load_notifications.php', // Ensure this path is correct
            type: 'GET',
            success: function(response){
                notifList.html(response);
            },
            error: function(){
                notifList.html('<p class="text-red-500 text-sm p-4 text-center">Could not load notifications.</p>');
            }
        });
    }

    // --- EVENT HANDLERS ---
    
    // Profile Button Click (Click logic ensures better behavior on all devices)
    profileBtn.on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        toggleProfileDropdown();
    });
    
    // Profile Dropdown Item Click (to load section content)
    $('.dropdown-item').on('click', function(e) {
        e.preventDefault();
        const section = $(this).data('section');
        toggleProfileDropdown(); 

        if (typeof window.loadSection === 'function') {
            window.loadSection(section);
        } else {
            console.error("loadSection function not found. Cannot load content via AJAX.");
        }
    });

    // Notification Button Click
    notificationBtn.on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        toggleNotificationDropdown();
    });

    // Mark All As Read functionality
    markAllReadBtn.on('click', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'ajax_mark_notifications_read.php', // Ensure this path is correct
            type: 'POST',
            success: function(){
                // Remove badge and close dropdown
                if (notifBadge.length) {
                    notifBadge.remove();
                    // Manually update the unread count in the global state if you have one
                }
                toggleNotificationDropdown(); 
                // Reload list to show items as read
                setTimeout(loadNotifications, 300); 
            },
            error: function(){
                // Replaced alert with modern notification function if available
                if (typeof showNotification === 'function') {
                    showNotification("Error marking notifications as read.", 'error');
                } else {
                    alert("Error marking notifications as read.");
                }
            }
        });
    });

    // Close all dropdowns when clicking outside
    $(document).on('click', function(e) {
        // Close Profile Menu
        if (profileMenu.hasClass('opacity-100') && !$(e.target).closest('#profileDropdownContainer').length) {
            toggleProfileDropdown();
        }
        // Close Notification Menu
        if (notifMenu.hasClass('opacity-100') && !$(e.target).closest('#notificationDropdownContainer').length) {
            toggleNotificationDropdown();
        }
    });
});
</script>