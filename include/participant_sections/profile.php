<?php
// include/participant_sections/profile.php

// 1. Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Check for unauthorized access (Participant role required)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'participant') {
    // If unauthorized, stop execution here
    http_response_code(403);
    echo "<div class='p-6 text-red-600'>❌ Unauthorized access or not logged in as a Participant.</div>";
    exit;
}

// 3. Include configuration and database connection
require_once 'include/config.php';

$userId = $_SESSION['user_id'];

// Fetch user info (Including the new profile_pic_url)
$stmt = $conn->prepare("
    SELECT u.name, u.email, u.profile_pic_url, p.preferred_role, p.skills, p.languages
    FROM users u 
    LEFT JOIN participants p ON u.user_id = p.participant_id
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Initial for avatar fallback
$initials = strtoupper(substr($user['name'],0,1) . (isset(explode(" ", $user['name'])[1]) ? substr(explode(" ", $user['name'])[1], 0, 1) : ""));

// Check if a profile picture exists, otherwise use initials avatar
$profile_image_src = $user['profile_pic_url'] ? $user['profile_pic_url'] : '';
?>

<div class="max-w-4xl mx-auto my-10 p-8 bg-white rounded-2xl shadow-2xl border-t-4 border-indigo-600 animate-fadeIn">
    <h2 class="text-4xl font-extrabold mb-8 text-indigo-700 border-b pb-3">My Profile 👤</h2>
    
    <div class="flex flex-col md:flex-row gap-10">
        
        <div class="flex flex-col items-center space-y-5 md:w-1/3 bg-indigo-50 p-6 rounded-xl shadow-lg transition-all duration-300 hover:shadow-xl">
            
            <?php if ($profile_image_src): ?>
                <img src="<?= htmlspecialchars($profile_image_src) ?>" alt="Profile Picture" class="w-28 h-28 rounded-full object-cover border-4 border-white shadow-xl" id="currentProfilePic">
            <?php else: ?>
                <div class="w-28 h-28 rounded-full bg-gradient-to-br from-indigo-600 to-purple-600 flex items-center justify-center text-white text-4xl font-bold shadow-xl" id="currentProfilePicPlaceholder">
                    <?= $initials ?>
                </div>
            <?php endif; ?>

            <h3 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($user['name']) ?></h3>
            <p class="text-indigo-500 font-medium"><?= htmlspecialchars($user['email']) ?></p>
            <p class="text-sm text-gray-600 mt-2 p-2 bg-indigo-200 rounded-full px-4">Participant Role</p>

            <div class="w-full mt-4 space-y-2">
                <h4 class="text-base font-semibold text-gray-700 mt-3 border-t pt-3">Update Picture</h4>
                <input type="file" id="profilePicInput" accept="image/*" class="block w-full text-sm text-gray-500
                    file:mr-4 file:py-2 file:px-4
                    file:rounded-full file:border-0
                    file:text-sm file:font-semibold
                    file:bg-indigo-100 file:text-indigo-700
                    hover:file:bg-indigo-200"
                />
                <button type="button" id="uploadPhotoButton" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-full font-medium transition duration-150 transform hover:scale-[1.02] text-sm shadow-md" style="display: none;">
                    Upload & Crop
                </button>
                <?php if ($profile_image_src): ?>
                    <button type="button" id="removePhotoButton" class="w-full bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-full font-medium transition duration-150 transform hover:scale-[1.02] text-sm shadow-md mt-2">
                        Remove Photo
                    </button>
                <?php endif; ?>
            </div>
            
        </div>

        <div class="md:w-2/3 space-y-6">
            <form id="profileUpdateForm" method="post" class="p-6 bg-white rounded-xl shadow-lg border border-gray-100 space-y-5">
                <h3 class="text-xl font-bold mb-4 text-indigo-600 border-b pb-2">Edit Details</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" class="mt-1 block w-full border border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg px-4 py-2 transition duration-150">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Preferred Role</label>
                        <input type="text" name="preferred_role" value="<?= htmlspecialchars($user['preferred_role'] ?? '') ?>" placeholder="e.g., Frontend Developer" class="mt-1 block w-full border border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg px-4 py-2 transition duration-150">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Skills (Comma Separated)</label>
                        <textarea name="skills" rows="2" placeholder="" class="mt-1 block w-full border border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg px-4 py-2 transition duration-150"><?= htmlspecialchars($user['skills'] ?? '') ?></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Languages (Programming/Human)</label>
                        <input type="text" name="languages" value="<?= htmlspecialchars($user['languages'] ?? '') ?>" placeholder="Python, Java, Malayalam" class="mt-1 block w-full border border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg px-4 py-2 transition duration-150">
                    </div>
                </div>
                
                <button type="submit" id="saveProfileBtn" 
        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-3 rounded-xl font-semibold shadow-md mt-4 transition-all duration-300 transform hover:scale-[1.01] flex items-center justify-center space-x-2">
        🚀 Save Changes
    </button> 
            </form>
        </div>
    </div>
</div>

<div id="cropperModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl p-6 w-11/12 max-w-2xl">
        <h3 class="text-2xl font-bold mb-4 text-indigo-700">Crop Profile Picture</h3>
        <div class="relative w-full h-80 bg-gray-100 flex items-center justify-center overflow-hidden">
            <img id="imageToCrop" src="" alt="Image to Crop" class="max-w-full max-h-full block">
        </div>
        <div class="flex justify-between mt-4 space-x-3">
            <button type="button" id="cropCancelBtn" class="px-5 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition">Cancel</button>
            <button type="button" id="cropSaveBtn" class="px-5 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">Save Cropped Image</button>
        </div>
    </div>
</div>

<div id="removeConfirmModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 hidden transition-opacity duration-300 opacity-0" style="display: none;">
    <div id="removeConfirmDialog" class="bg-white rounded-lg shadow-2xl p-8 w-11/12 max-w-sm transform transition-all duration-300 scale-90 opacity-0">
        <h3 class="text-xl font-bold mb-4 text-red-600">Confirm Photo Removal</h3>
        <p class="text-gray-700 mb-6">Are you sure you want to remove your profile picture? This action cannot be undone.</p>
        <div class="flex justify-end space-x-3">
            <button type="button" id="removeCancelBtn" class="px-5 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition">
                Cancel
            </button>
            <button type="button" id="removeConfirmBtn" class="px-5 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                Yes, Remove Photo
            </button>
        </div>
    </div>
</div>

<script>
// NOTE: For this script to work, the Cropper.js library must be included in your main dashboard page.

// --- 1. MODERN NOTIFICATION (TOAST) FUNCTION ---
// This function needs to be available globally in the parent dashboard file (e.g., participant_dashboard.php)
// It is defined here just in case, but it relies on a container element: #notification-container
function showNotification(message, type) {
    const container = document.getElementById('notification-container');
    if (!container) return; 

    let bgColor = (type === 'success') ? 'bg-green-500' : (type === 'error' ? 'bg-red-600' : 'bg-gray-700');
    let icon = (type === 'success') ? '✅' : (type === 'error' ? '❌' : 'ℹ️');

    const toast = document.createElement('div');
    toast.className = `p-4 rounded-xl shadow-2xl text-white max-w-sm transform transition-all duration-300 translate-x-full opacity-0 ${bgColor}`;
    toast.innerHTML = `
        <div class="flex items-center space-x-3">
            <span class="text-xl">${icon}</span>
            <span class="font-medium text-sm">${message}</span>
        </div>
    `;

    container.appendChild(toast);

    // Animate in
    setTimeout(() => {
        toast.classList.remove('translate-x-full', 'opacity-0');
        toast.classList.add('translate-x-0', 'opacity-100');
    }, 50);

    // Animate out and remove after 4 seconds
    setTimeout(() => {
        toast.classList.remove('translate-x-0', 'opacity-100');
        toast.classList.add('translate-x-full', 'opacity-0');
        
        setTimeout(() => {
            container.removeChild(toast);
        }, 300);
    }, 4000); 
}

// --- 2. CROPPING & AJAX LOGIC ---
function initializeCropperLogic() {
    let cropper;
    const profilePicInput = $('#profilePicInput');
    const uploadPhotoButton = $('#uploadPhotoButton');
    const cropperModal = $('#cropperModal');
    const imageToCrop = document.getElementById('imageToCrop'); 
    const cropCancelBtn = $('#cropCancelBtn');
    const cropSaveBtn = $('#cropSaveBtn');

    // Remove Confirmation Modal Elements
    const removeConfirmModal = $('#removeConfirmModal');
    const removeConfirmDialog = $('#removeConfirmDialog');
    const removeCancelBtn = $('#removeCancelBtn');
    const removeConfirmBtn = $('#removeConfirmBtn');


    // Helper function to show the removal modal
    function showConfirmationModal() {
        removeConfirmModal.removeClass('hidden');
        removeConfirmModal.css('display', 'flex').css('opacity', 1); // Ensure flex is set for centering and show container
        // Animate in
        setTimeout(() => {
            removeConfirmDialog.removeClass('scale-90 opacity-0');
            removeConfirmDialog.addClass('scale-100 opacity-100');
        }, 10);
    }
    
    // Helper function to hide the modal
    function hideConfirmationModal() {
        removeConfirmDialog.removeClass('scale-100 opacity-100');
        removeConfirmDialog.addClass('scale-90 opacity-0');
        
        // Hide container after transition
        setTimeout(() => {
            removeConfirmModal.addClass('hidden');
            removeConfirmModal.css('display', 'none'); // Set display to none
        }, 300);
    }


    // 1. Handle file input change (Opens Cropper Modal)
    profilePicInput.off('change').on('change', function(e) {
        const files = e.target.files;
        if (files && files.length > 0) {
            const reader = new FileReader();
            reader.onload = function(event) {
                imageToCrop.src = event.target.result;
                cropperModal.removeClass('hidden');

                if (cropper) cropper.destroy();
                
                if (typeof Cropper !== 'undefined') { 
                     // Initialize Cropper instance
                     cropper = new Cropper(imageToCrop, { aspectRatio: 1, viewMode: 1 });
                } else {
                    showNotification("Image cropping feature failed to load.", 'error');
                    return;
                }
            };
            reader.readAsDataURL(files[0]);
            uploadPhotoButton.show();
        } else {
            uploadPhotoButton.hide();
        }
    });

    // 2. Handle "Save Cropped Image" button click (AJAX Upload)
    cropSaveBtn.off('click').on('click', function() {
        if (!cropper) return;
        
        cropSaveBtn.text('Uploading...').prop('disabled', true);

        // Get cropped image data as a Blob
        cropper.getCroppedCanvas({ width: 256, height: 256 }).toBlob(function(blob) {
            
            if (!blob) {
                 showNotification("Error: Could not process image data.", 'error');
                 cropSaveBtn.text('Save Cropped Image').prop('disabled', false);
                 return;
            }

            const formData = new FormData();
            formData.append('profile_pic', blob, 'profile_pic.png'); 

            $.ajax({
                url: 'include/participant_sections/participant_upload_profile_pic.php', 
                method: 'POST',
                data: formData,
                processData: false, 
                contentType: false, 
                dataType: 'json', 
                
                success: function(response) {
                    const result = response; 
                    
                    if (result.success) {
                        showNotification("Profile photo updated successfully!", 'success');
                        
                        // Reload the current section (profile) via AJAX for smooth update
                        if (typeof window.loadSection === 'function') {
                            window.loadSection('profile');
                        } else {
                            location.reload();
                        }
                    } else {
                        showNotification("Upload Error: " + result.message, 'error');
                    }
                },
                error: function(xhr) {
                    let errorMsg = "AJAX Error. Check Network tab for server response.";
                    showNotification(errorMsg, 'error');
                    console.error("AJAX Error:", xhr);
                },
                complete: function() {
                    // Reset button and destroy cropper
                    cropSaveBtn.text('Save Cropped Image').prop('disabled', false);
                    cropperModal.addClass('hidden');
                    if (cropper) cropper.destroy();
                }
            });
        }, 'image/png'); // Format of the output blob
    });

    // 3. Handle Cropper "Cancel" button click
    cropCancelBtn.off('click').on('click', function() {
        cropperModal.addClass('hidden');
        profilePicInput.val(''); // Clear file input
        uploadPhotoButton.hide();
        if (cropper) cropper.destroy();
    });
    
    // --- REMOVE PICTURE MODERN CONFIRMATION LOGIC ---
    
    // 4. Handle "Remove Photo" button click (Triggers Modern Modal)
    $(document).off('click', '#removePhotoButton').on('click', '#removePhotoButton', function() {
        showConfirmationModal();
    });

    // 5. Handle "Cancel" button click in the modal
    removeCancelBtn.off('click').on('click', function() {
        hideConfirmationModal();
    });

    // 6. Handle "Confirm Remove" button click (Triggers AJAX)
    removeConfirmBtn.off('click').on('click', function() {
        // 1. Hide the modern modal immediately
        hideConfirmationModal(); 

        // 2. Perform the AJAX removal request
        $.ajax({
            url: 'include/participant_sections/participant_remove_profile_pic.php',
            method: 'POST',
            dataType: 'json', 
            
            success: function(response) {
                if (response.success) {
                    showNotification("Profile photo removed successfully.", 'success');
                    
                    // Reload the current section (profile) via AJAX for smooth update
                    if (typeof window.loadSection === 'function') {
                        window.loadSection('profile');
                    } else {
                        location.reload();
                    }
                } else {
                    showNotification("Error removing photo: " + response.message, 'error');
                }
            },
            
            error: function(xhr) {
                showNotification("An error occurred during removal. Check console.", 'error');
                console.error("Remove Error Details:", xhr);
            }
        });
    });
}

// --- 3. PROFILE DETAILS UPDATE LOGIC ---
function initializeProfileUpdateLogic() {
    const profileForm = $('#profileUpdateForm');
    const saveProfileBtn = $('#saveProfileBtn');
    
    profileForm.off('submit').on('submit', function(e) {
        e.preventDefault(); 
        
        const formData = profileForm.serialize();
        
        saveProfileBtn.prop('disabled', true).html('Saving... <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white ml-2"></div>');

        $.ajax({
            url: 'include/participant_sections/participant_update_profile.php', 
            method: 'POST',
            data: formData,
            dataType: 'json', 
            
            success: function(response) {
                const result = response; 
                
                if (result.success) {
                    showNotification(result.message || "Profile updated successfully!", 'success');
                    
                    // Reload the profile section via AJAX to update name/initials
                    if (typeof window.loadSection === 'function') {
                        window.loadSection('profile');
                    } 
                } else {
                    showNotification("Update Failed: " + (result.message || "Unknown error."), 'error');
                }
            },
            
            error: function(xhr) {
                let errorMsg = "An internal server error occurred. Check network logs.";
                showNotification(errorMsg, 'error');
                console.error("Profile Update AJAX Error:", xhr);
            },
            
            complete: function() {
                // Re-enable the button regardless of success or failure
                saveProfileBtn.prop('disabled', false).html('🚀 Save Changes');
            }
        });
    });
}


// Run all initialization logic after DOM ready and ensure Cropper is loaded
$(document).ready(function() {
    // Wait slightly longer to ensure Cropper.js is available if loaded async
    setTimeout(function() {
        if (typeof Cropper !== 'undefined') {
            initializeCropperLogic();
        } else {
            console.error("Cropper.js library not found. Profile picture cropping will be disabled.");
        }
        // Initialize the non-cropping related logic always
        initializeProfileUpdateLogic();
    }, 50); 
});
</script>