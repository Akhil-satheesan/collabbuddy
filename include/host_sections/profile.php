<?php
// FILE: include/host_sections/host_profile.php

if (session_status() == PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host') {
    echo "Unauthorized";
    exit;
}

// FIX: Adjusted path for config.php. Assuming config.php is one level up (in the project root).
require_once 'include/config.php'; 

$userId = $_SESSION['user_id'];

// Fetch user info (from users table) and host info (from hosts table)
$stmt = $conn->prepare("
    SELECT 
        u.name, 
        u.email, 
        u.profile_pic_url, 
        h.host_type, 
        h.about_host, 
        h.company_name, 
        h.product_name,
        h.website_url
    FROM users u 
    LEFT JOIN hosts h ON u.user_id = h.host_id
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Initial for avatar fallback
$initials = strtoupper(substr($user['name'],0,1) . (isset(explode(" ", $user['name'])[1]) ? substr(explode(" ", $user['name'])[1], 0, 1) : ""));

// Check if a profile picture exists, otherwise use initials avatar (add cache buster)
$profile_image_src = $user['profile_pic_url'] ? $user['profile_pic_url'] . '?t=' . time() : '';
?>

<div class="max-w-4xl mx-auto my-10 p-8 bg-white rounded-2xl shadow-2xl border-t-4 border-purple-600 animate-fadeIn">
    <h2 class="text-4xl font-extrabold mb-8 text-purple-700 border-b pb-3">My Host Profile 🏢</h2>
    
    <div class="flex flex-col md:flex-row gap-10">
        
        <div class="flex flex-col items-center space-y-5 md:w-1/3 bg-purple-50 p-6 rounded-xl shadow-lg transition-all duration-300 hover:shadow-xl">
            
            <?php if ($profile_image_src): ?>
                <img src="<?= htmlspecialchars($profile_image_src) ?>" alt="Profile Picture" class="w-28 h-28 rounded-full object-cover border-4 border-white shadow-xl" id="currentProfilePic">
            <?php else: ?>
                <div class="w-28 h-28 rounded-full bg-gradient-to-br from-purple-600 to-indigo-600 flex items-center justify-center text-white text-4xl font-bold shadow-xl" id="currentProfilePicPlaceholder">
                    <?= $initials ?>
                </div>
            <?php endif; ?>

            <h3 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($user['name']) ?></h3>
            <p class="text-purple-500 font-medium"><?= htmlspecialchars($user['email']) ?></p>
            <p class="text-sm text-gray-600 mt-2 p-2 bg-purple-200 rounded-full px-4">Host Role</p>

            <div class="w-full mt-4 space-y-2">
                <h4 class="text-base font-semibold text-gray-700 mt-3 border-t pt-3">Update Picture</h4>
                <input type="file" id="profilePicInput" accept="image/*" class="block w-full text-sm text-gray-500
                    file:mr-4 file:py-2 file:px-4
                    file:rounded-full file:border-0
                    file:text-sm file:font-semibold
                    file:bg-purple-100 file:text-purple-700
                    hover:file:bg-purple-200"
                />
                <button type="button" id="uploadPhotoButton" class="w-full bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-full font-medium transition duration-150 transform hover:scale-[1.02] text-sm shadow-md" style="display: none;">
                    Upload & Crop
                </button>
                    <?php if ($user['profile_pic_url']): ?>
                        <button type="button" id="removePhotoButton" class="w-full bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-full font-medium transition duration-150 transform hover:scale-[1.02] text-sm shadow-md mt-2">
                            Remove Photo
                        </button>
                    <?php endif; ?>
            </div>
            
        </div>

        <div class="md:w-2/3 space-y-6">
            <form id="profileUpdateForm" method="post" class="p-6 bg-white rounded-xl shadow-lg border border-gray-100 space-y-5">
                <h3 class="text-xl font-bold mb-4 text-purple-600 border-b pb-2">Edit Host Details</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" class="mt-1 block w-full border border-gray-300 bg-gray-50 text-gray-500 rounded-lg px-4 py-2" readonly>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Host Type</label>
                        <select name="host_type" class="mt-1 block w-full border border-gray-300 focus:border-purple-500 focus:ring-purple-500 rounded-lg px-4 py-2 transition duration-150">
                            <?php $selected_type = $user['host_type'] ?? ''; ?>
                            <option value="Student" <?= $selected_type == 'Student' ? 'selected' : '' ?>>Student</option>
                            <option value="Freelancer" <?= $selected_type == 'Freelancer' ? 'selected' : '' ?>>Freelancer</option>
                            <option value="Business" <?= $selected_type == 'Business' ? 'selected' : '' ?>>Business/Company</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Company/Organization Name</label>
                        <input type="text" name="company_name" value="<?= htmlspecialchars($user['company_name'] ?? '') ?>" placeholder="e.g., Tech Solutions Inc." class="mt-1 block w-full border border-gray-300 focus:border-purple-500 focus:ring-purple-500 rounded-lg px-4 py-2 transition duration-150">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Product/Service Name</label>
                        <input type="text" name="product_name" value="<?= htmlspecialchars($user['product_name'] ?? '') ?>" placeholder="e.g., ProjectFinder App" class="mt-1 block w-full border border-gray-300 focus:border-purple-500 focus:ring-purple-500 rounded-lg px-4 py-2 transition duration-150">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Website URL</label>
                        <input type="url" name="website_url" value="<?= htmlspecialchars($user['website_url'] ?? '') ?>" placeholder="https://www.yourwebsite.com" class="mt-1 block w-full border border-gray-300 focus:border-purple-500 focus:ring-purple-500 rounded-lg px-4 py-2 transition duration-150">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">About Me/Company</label>
                        <textarea name="about_host" rows="4" placeholder="Brief description about your company, mission, or yourself." class="mt-1 block w-full border border-gray-300 focus:border-purple-500 focus:ring-purple-500 rounded-lg px-4 py-2 transition duration-150"><?= htmlspecialchars($user['about_host'] ?? '') ?></textarea>
                    </div>

                </div>
                
                <button type="submit" id="saveProfileBtn" 
                class="w-full bg-purple-600 hover:bg-purple-700 text-white px-4 py-3 rounded-xl font-semibold shadow-md mt-4 transition-all duration-300 transform hover:scale-[1.01] flex items-center justify-center space-x-2">
                    🚀 Save Changes
                </button> 
            </form>
        </div>
    </div>
</div>

<div id="cropperModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl p-6 w-11/12 max-w-2xl">
        <h3 class="text-2xl font-bold mb-4 text-purple-700">Crop Profile Picture</h3>
        <div class="relative w-full h-80 bg-gray-100 flex items-center justify-center overflow-hidden">
            <img id="imageToCrop" src="" alt="Image to Crop" class="max-w-full max-h-full block">
        </div>
        <div class="flex justify-between mt-4 space-x-3">
            <button type="button" id="cropCancelBtn" class="px-5 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition">Cancel</button>
            <button type="button" id="cropSaveBtn" class="px-5 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">Save Cropped Image</button>
        </div>
    </div>
</div>

<div id="removeConfirmModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div id="removeConfirmDialog" class="bg-white rounded-lg shadow-xl p-6 w-96 transform transition-all duration-300 scale-90 opacity-0">
        <h3 class="text-xl font-bold mb-3 text-red-600">Confirm Removal</h3>
        <p class="text-gray-700 mb-6">Are you sure you want to remove your current profile picture?</p>
        <div class="flex justify-end space-x-3">
            <button type="button" id="removeCancelBtn" class="px-5 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition">Cancel</button>
            <button type="button" id="removeConfirmBtn" class="px-5 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">Yes, Remove</button>
        </div>
    </div>
</div>

<script>
// --- 1. MODERN NOTIFICATION (TOAST) FUNCTION ---
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

    setTimeout(() => {
        toast.classList.remove('translate-x-full', 'opacity-0');
        toast.classList.add('translate-x-0', 'opacity-100');
    }, 50);

    setTimeout(() => {
        toast.classList.remove('translate-x-0', 'opacity-100');
        toast.classList.add('translate-x-full', 'opacity-0');
        
        setTimeout(() => {
            container.removeChild(toast);
        }, 300);
    }, 4000); 
}

// --- 2. CROPPING & AJAX LOGIC (Photo Upload/Remove) ---
function initializeCropperLogic() {
    let cropper;
    const profilePicInput = $('#profilePicInput');
    const uploadPhotoButton = $('#uploadPhotoButton');
    const cropperModal = $('#cropperModal');
    const imageToCrop = document.getElementById('imageToCrop'); 
    const cropCancelBtn = $('#cropCancelBtn');
    const cropSaveBtn = $('#cropSaveBtn');

    const removeConfirmModal = $('#removeConfirmModal');
    const removeConfirmDialog = $('#removeConfirmDialog');
    const removeCancelBtn = $('#removeCancelBtn');
    const removeConfirmBtn = $('#removeConfirmBtn');


    function showConfirmationModal() {
        removeConfirmModal.removeClass('hidden').css('display', 'flex'); 
        setTimeout(() => {
            removeConfirmDialog.removeClass('scale-90 opacity-0');
            removeConfirmDialog.addClass('scale-100 opacity-100');
        }, 10);
    }
    
    function hideConfirmationModal() {
        removeConfirmDialog.removeClass('scale-100 opacity-100');
        removeConfirmDialog.addClass('scale-90 opacity-0');
        setTimeout(() => {
            removeConfirmModal.addClass('hidden').css('display', 'none'); 
        }, 300);
    }


    // 1. Handle file input change (Opens Cropper Modal)
    profilePicInput.off('change').on('change', function(e) {
        const files = e.target.files;
        if (files && files.length > 0) {
            const reader = new FileReader();
            
            reader.onload = function(event) {
                imageToCrop.src = event.target.result;
                cropperModal.removeClass('hidden').css('display', 'flex'); // Show modal

                // FIX: Wait for image to load before initializing Cropper
                imageToCrop.onload = function() {
                    if (cropper) cropper.destroy();
                    
                    if (typeof Cropper !== 'undefined') { 
                        cropper = new Cropper(imageToCrop, { 
                            aspectRatio: 1, 
                            viewMode: 1, // Restrict crop box to canvas
                            responsive: true, // Key for good modal behavior
                            autoCropArea: 0.8, // Start with 80% crop area
                        });
                    } else {
                        showNotification("Image cropping feature failed to load.", 'error');
                        cropperModal.addClass('hidden').css('display', 'none');
                    }
                };
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

        cropper.getCroppedCanvas({ width: 256, height: 256 }).toBlob(function(blob) {
            
            if (!blob) {
                 showNotification("Error: Could not process image data.", 'error');
                 cropSaveBtn.text('Save Cropped Image').prop('disabled', false);
                 return;
            }

            const formData = new FormData();
            formData.append('profile_pic', blob, 'profile_pic.png'); 

            $.ajax({
                url: 'include/host_sections/host_upload_profile_pic.php', 
                method: 'POST',
                data: formData,
                processData: false, 
                contentType: false, 
                dataType: 'json', 
                
                success: function(response) {
                    const result = response; 
                    
                    if (result.success) {
                        showNotification("Profile photo updated successfully!", 'success');
                        
                        if (typeof window.loadHostSection === 'function') {
                            window.loadHostSection('profile'); 
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
                    cropperModal.addClass('hidden').css('display', 'none');
                    cropSaveBtn.text('Save Cropped Image').prop('disabled', false);
                    if (cropper) cropper.destroy();
                    profilePicInput.val('');
                }
            });
        }, 'image/png');
    });

    // 3. Handle Cropper "Cancel" button click
    cropCancelBtn.off('click').on('click', function() {
        cropperModal.addClass('hidden').css('display', 'none');
        profilePicInput.val('');
        uploadPhotoButton.hide();
        if (cropper) cropper.destroy();
    });
    
    // --- REMOVE PICTURE LOGIC ---
    
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
        hideConfirmationModal(); 

        $.ajax({
            url: 'include/host_sections/host_remove_profile_pic.php', 
            method: 'POST',
            dataType: 'json', 
            
            success: function(response) {
                if (response.success) {
                    showNotification("Profile photo removed successfully.", 'success');
                    
                    if (typeof window.loadHostSection === 'function') {
                        window.loadHostSection('profile');
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

// Run the initialization logic after DOM ready and Cropper library is loaded
$(document).ready(function() {
    if (typeof Cropper !== 'undefined') {
        initializeCropperLogic();
    } else {
        setTimeout(function() {
            if (typeof Cropper !== 'undefined') {
                initializeCropperLogic();
            } else {
                console.error("Cropper.js is NOT loaded. Cropping will fail.");
            }
        }, 300); 
    }
});

// --- 3. PROFILE DETAILS UPDATE LOGIC ---
// --- 3. PROFILE DETAILS UPDATE LOGIC ---
$(document).ready(function() {
    const profileForm = $('#profileUpdateForm');
    const saveProfileBtn = $('#saveProfileBtn');

    profileForm.off('submit').on('submit', function(e) {
        e.preventDefault(); 
        
        const formData = profileForm.serialize();
        
        saveProfileBtn.prop('disabled', true).html('Saving... <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white ml-2"></div>'); // Loading state

        $.ajax({
            url: 'include/host_sections/host_update_profile.php', 
            method: 'POST',
            data: formData,
            dataType: 'json', 
            
            success: function(response) {
                const result = response; 
                
                if (result.success) {
                    // 🚀 FIX 2A: വിജയകരമായ നോട്ടിഫിക്കേഷൻ കാണിക്കുന്നു
                    showNotification(result.message || "Host profile updated successfully!", 'success');
                    
                    if (typeof window.loadHostSection === 'function') {
                        // 🚀 FIX 2B: മാറ്റങ്ങൾ കാണിക്കാൻ പേജ് റീലോഡ് ചെയ്യുന്നു
                        window.loadHostSection('profile');
                    } else {
                        // Fallback: If section loading function is not available
                        setTimeout(() => location.reload(), 1000); 
                    }
                } else {
                    // ❌ FIX 2C: പരാജയപ്പെട്ട നോട്ടിഫിക്കേഷൻ കാണിക്കുന്നു
                    showNotification("Update Failed: " + (result.message || "Unknown error."), 'error');
                }
            },
            
            error: function(xhr) {
                let errorMsg = "An internal server error occurred. Please check network logs.";
                showNotification(errorMsg, 'error');
                console.error("Profile Update AJAX Error:", xhr);
            },
            
            complete: function() {
                saveProfileBtn.prop('disabled', false).html('🚀 Save Changes');
            }
        });
    });
});

// NOTE: showNotification(message, type) function must be present in the full script.
</script>