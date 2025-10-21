<?php
// FILE: include/host_sections/change_password.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) session_start();

// Host Authorization Check (Safety check)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host') {
    http_response_code(403);
    echo "<div class='p-6 text-red-600'>❌ Access Denied: Invalid Host Session. Please log in again.</div>";
    exit;
}

if (!isset($conn)) {
    if (file_exists(__DIR__ . '/../../config.php')) {
         include_once __DIR__ . '/../../config.php'; 
    }
}

$current_user_name = $_SESSION['name'] ?? 'Host'; 
?>

<div class="flex flex-col items-center p-4 md:p-10 min-h-[70vh]">
    <div class="w-full max-w-lg bg-white p-8 rounded-xl shadow-2xl border-2 border-purple-600">
        
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Hello <?php echo htmlspecialchars($current_user_name); ?>!</h1>
        <p class="text-xl text-purple-600 mb-6 border-b pb-2">Change Your Password via OTP (Host)</p>
        
        <div id="message-container" class="hidden p-3 mb-4 rounded border-l-4 font-medium" role="alert"></div>

        <div id="step1-container">
            <h2 class="text-2xl font-semibold mb-4 text-gray-700">Step 1: Set New Password</h2>
            <form id="step1-form">
                
                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($_SESSION['user_id'] ?? ''); ?>">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>">
                
                <div class="mb-4">
                    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500" required>
                    <div id="password-feedback" class="text-xs mt-1 text-gray-500">
                        Min 8 chars, including Upper, Lower, Number, and Special character.
                    </div>
                </div>

                <div class="mb-6">
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500" required>
                    <div id="confirm-feedback" class="text-xs mt-1 text-gray-500">Must match the new password.</div>
                </div>

                <button type="submit" id="step1-submit-btn" class="w-full bg-purple-600 text-white p-3 rounded-lg hover:bg-purple-700 transition duration-150 font-semibold disabled:bg-gray-400" disabled>
                    Continue & Send OTP
                </button>
            </form>
        </div>

        <div id="step2-container" class="hidden">
    <form id="step2-form">
        <div class="mb-6">
            <label for="otp_inputs_container" class="block text-sm font-medium text-gray-700 mb-2">Enter 6-Digit OTP</label>
            
            <div id="otp_inputs_container" class="flex justify-between space-x-2 md:space-x-4 max-w-sm mx-auto">
                <input type="text" maxlength="1" class="otp-input w-full h-12 text-center text-2xl font-bold border-2 border-gray-300 rounded-lg focus:border-purple-500 focus:ring-purple-500 transition duration-150" data-index="0" required>
                <input type="text" maxlength="1" class="otp-input w-full h-12 text-center text-2xl font-bold border-2 border-gray-300 rounded-lg focus:border-purple-500 focus:ring-purple-500 transition duration-150" data-index="1" required disabled>
                <input type="text" maxlength="1" class="otp-input w-full h-12 text-center text-2xl font-bold border-2 border-gray-300 rounded-lg focus:border-purple-500 focus:ring-purple-500 transition duration-150" data-index="2" required disabled>
                <input type="text" maxlength="1" class="otp-input w-full h-12 text-center text-2xl font-bold border-2 border-gray-300 rounded-lg focus:border-purple-500 focus:ring-purple-500 transition duration-150" data-index="3" required disabled>
                <input type="text" maxlength="1" class="otp-input w-full h-12 text-center text-2xl font-bold border-2 border-gray-300 rounded-lg focus:border-purple-500 focus:ring-purple-500 transition duration-150" data-index="4" required disabled>
                <input type="text" maxlength="1" class="otp-input w-full h-12 text-center text-2xl font-bold border-2 border-gray-300 rounded-lg focus:border-purple-500 focus:ring-purple-500 transition duration-150" data-index="5" required disabled>
                
                <input type="hidden" name="otp_code" id="combined_otp_code"> 
            </div>
        </div>

        <button type="submit" id="step2-submit-btn" class="w-full bg-green-600 text-white p-3 rounded-lg hover:bg-green-700 transition duration-150 font-semibold disabled:bg-gray-400" disabled>
            Verify OTP & Change Password
        </button>
    </form>
    <div id="cancelModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden items-center justify-center z-50 transition-opacity duration-300">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-sm">
        <h3 class="text-xl font-bold mb-4 text-red-600">Confirm Cancellation</h3>
        <p class="mb-6 text-gray-700">Are you sure you want to cancel the password change process and return to the profile section?</p>
        <div class="flex justify-end space-x-3">
            <button id="closeCancelBtn" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100 transition">No, Stay Here</button>
            <button id="confirmCancelBtn" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">Yes, Cancel</button>
        </div>
    </div>
</div>
        
    </div>

        <div id="step3-container" class="hidden text-center p-10 bg-green-50 border border-green-300 rounded-lg">
            <i class="fas fa-check-circle text-6xl text-green-600 mb-4"></i>
            <h2 class="text-2xl font-bold text-green-700 mb-2">Password Successfully Changed!</h2>
            <p class="text-gray-600">You will be redirected back to your profile shortly.</p>
        </div>
        
    </div>
</div>


<script>
    $(document).ready(function() {
        
        const processUrl = 'include/host_sections/host_process_password_change_otp.php'; 
        
        // ===================================
        // 🛠️ UTILITY FUNCTIONS
        // ===================================

        function showMessage(success, message) {
            const container = $('#message-container');
            container.text(message);
            container.removeClass('hidden bg-green-100 text-green-700 border-green-500 bg-red-100 text-red-700 border-red-500');
            
            if (success) {
                container.addClass('bg-green-100 text-green-700 border-green-500');
            } else {
                container.addClass('bg-red-100 text-red-700 border-red-500');
            }
            container.fadeIn().delay(5000).fadeOut();
        }

        // Live Password Strength Check
        function checkPasswordStrength(password) {
            const minLength = 8;
            const hasUpper = /[A-Z]/.test(password);
            const hasLower = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[!@#$%^&*()]/.test(password); 

            const feedback = $('#password-feedback');
            feedback.empty(); 

            let allChecksPass = true;

            const checks = [
                { condition: password.length >= minLength, message: "Min 8 characters" },
                { condition: hasUpper, message: "An Uppercase letter" },
                { condition: hasLower, message: "A Lowercase letter" },
                { condition: hasNumber, message: "A Number (0-9)" },
                { condition: hasSpecial, message: "A Special character" }
            ];

            checks.forEach(check => {
                const icon = check.condition 
                    ? '<i class="fas fa-check-circle text-green-500 mr-1"></i>' 
                    : '<i class="fas fa-times-circle text-red-500 mr-1"></i>';
                
                if (!check.condition) {
                    allChecksPass = false;
                }

                feedback.append(`<span class="inline-block text-xs mr-3 ${check.condition ? 'text-green-600' : 'text-red-600'}">${icon}${check.message}</span>`);
            });

            return allChecksPass;
        }

        // Live Password Match Check
        function checkPasswordMatch() {
            const newPass = $('#new_password').val();
            const confPass = $('#confirm_password').val();
            const matchFeedback = $('#confirm-feedback');
            
            matchFeedback.empty();

            if (newPass.length === 0) {
                matchFeedback.text("Must match the new password.").removeClass('text-green-600 text-red-600').addClass('text-gray-500');
                return false;
            } else if (newPass === confPass && newPass.length > 0) {
                matchFeedback.html('<i class="fas fa-check-circle text-green-500 mr-1"></i> Passwords Match!').removeClass('text-red-600').addClass('text-green-600');
                return true;
            } else if (newPass.length > 0 && confPass.length > 0) {
                matchFeedback.html('<i class="fas fa-times-circle text-red-500 mr-1"></i> Passwords DO NOT Match!').removeClass('text-green-600').addClass('text-red-600');
                return false;
            } else {
                 matchFeedback.text("Must match the new password.").removeClass('text-green-600 text-red-600').addClass('text-gray-500');
                 return false;
            }
        }

        // ===================================
        // 🔑 IMPROVED OTP INPUT LOGIC (Auto-focus & Combination)
        // ===================================
        function setupOtpInputs() {
            const inputs = $('.otp-input');
            const submitBtn = $('#step2-submit-btn');

            // Initial State
            inputs.val(''); // Clear any remnants
            inputs.eq(0).prop('disabled', false).focus();
            inputs.slice(1).prop('disabled', true);
            submitBtn.prop('disabled', true);

            // Function to combine OTP and check completion
            function checkOtpCompletion() {
                let otpCode = '';
                let isComplete = true;

                inputs.each(function() {
                    const val = $(this).val();
                    if (val.length === 0 || !/^\d$/.test(val)) {
                        isComplete = false;
                    }
                    otpCode += val;
                });

                $('#combined_otp_code').val(otpCode);
                submitBtn.prop('disabled', !isComplete);
                return isComplete;
            }

            // Keyup/Input Handler for auto-move and validation
            inputs.off('input keydown paste').on('input', function() {
                const currentInput = $(this);
                let currentIndex = parseInt(currentInput.data('index'));
                const value = currentInput.val();
                
                // 1. Ensure only one digit and it's a number
                if (!/^\d$/.test(value) && value.length > 0) {
                    currentInput.val(value.replace(/\D/g, ''));
                    return;
                }
                
                // 2. Auto-move Forward
                if (value.length === 1 && currentIndex < inputs.length - 1) {
                    inputs.eq(currentIndex + 1).prop('disabled', false).focus();
                }

                checkOtpCompletion();
            });

            // Keydown Handler for Backspace
            inputs.on('keydown', function(e) {
                const currentInput = $(this);
                let currentIndex = parseInt(currentInput.data('index'));
                
                // If Backspace is pressed and the current box is empty
                if (e.keyCode === 8 && currentInput.val().length === 0 && currentIndex > 0) {
                    e.preventDefault(); // Prevent default backspace action
                    // Move focus to the previous box and clear it
                    inputs.eq(currentIndex - 1).val('').focus();
                    checkOtpCompletion();
                }
            });

            // Paste Handler (For 6-digit paste)
            inputs.on('paste', function(e) {
                const pasteData = e.originalEvent.clipboardData.getData('text').trim();
                if (pasteData.match(/^\d{6}$/)) {
                    e.preventDefault();
                    for (let i = 0; i < inputs.length; i++) {
                        inputs.eq(i).val(pasteData.charAt(i)).prop('disabled', false);
                    }
                    inputs.eq(inputs.length - 1).focus();
                } else {
                    // If non-6-digit, let the input handler deal with the first char
                }
                checkOtpCompletion();
            });
        }
        
        function updateUI(step) {
            $('#step1-container, #step2-container, #step3-container').addClass('hidden');
            $('#cancelModal').removeClass('flex').addClass('hidden'); 

            if (step === 1) {
                $('#step1-container').removeClass('hidden');
            } else if (step === 2) {
                $('#step2-container').removeClass('hidden');
                setupOtpInputs(); // ⬅️ IMPROVED OTP Setup
            } else if (step === 3) {
                $('#step3-container').removeClass('hidden');
                setTimeout(() => {
                    if (typeof window.loadHostSection === 'function') {
                        window.loadHostSection('profile');
                        if (typeof window.showToast === 'function') {
                             window.showToast("Your password has been successfully updated!", 'success');
                        }
                    } else {
                         window.location.href = 'host_dashboard.php?section=profile'; 
                    }
                }, 3000);
            }
        }

        // ===================================
        // 🔄 LIVE VALIDATION EVENT HANDLERS (Step 1)
        // ===================================

        function validateAndToggleSubmit() {
            const isStrong = checkPasswordStrength($('#new_password').val());
            const isMatch = checkPasswordMatch();
            
            const canSubmit = isStrong && isMatch;
            $('#step1-submit-btn').prop('disabled', !canSubmit);
        }

        $('#new_password').on('keyup', validateAndToggleSubmit);
        $('#confirm_password').on('keyup', validateAndToggleSubmit);
        
        // Initial setup
        $('#step1-submit-btn').prop('disabled', true);
        
        // ===================================
        // STEP 1 Submission (OTP Request)
        // ===================================
        $('#step1-form').on('submit', function(e) {
            e.preventDefault();
            
            if ($('#step1-submit-btn').prop('disabled')) {
                 showMessage(false, "Please fix the password requirements before continuing.");
                 return;
            }
            
            $('#step1-submit-btn').prop('disabled', true).text('Processing...');

            $.ajax({
                url: processUrl, 
                type: 'POST',
                data: $(this).serialize() + '&action=send_otp', 
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showMessage(true, response.message);
                        if (response.next_step === 2) {
                            updateUI(2); 
                        }
                    } else {
                        showMessage(false, response.message);
                        $('#step1-submit-btn').prop('disabled', !checkPasswordStrength($('#new_password').val()) || !checkPasswordMatch()).text('Continue & Send OTP');
                    }
                },
                error: function(xhr) {
                    showMessage(false, "Network or Server Error during Step 1. Status: " + xhr.status);
                    console.error("AJAX Error:", xhr.responseText);
                    $('#step1-submit-btn').prop('disabled', false).text('Continue & Send OTP');
                }
            });
        });

        // ===================================
        // STEP 2 Submission (OTP Verification)
        // ===================================
        $('#step2-form').on('submit', function(e) {
            e.preventDefault();
            
            const combinedOtp = $('#combined_otp_code').val();
            
            if (combinedOtp.length !== 6) {
                showMessage(false, "Please enter the complete 6-digit OTP code.");
                // Ensure submit button is re-enabled if validation fails here
                $('#step2-submit-btn').prop('disabled', false).text('Verify OTP & Change Password');
                return;
            }
            
            $('#step2-submit-btn').prop('disabled', true).text('Verifying...');

            const passwordData = $('#step1-form').serializeArray(); 
            const otpData = $(this).serializeArray();
            
            let allData = [...passwordData, ...otpData, {name: 'action', value: 'verify_and_change'}]; 

            $.ajax({
                url: processUrl, 
                type: 'POST',
                data: allData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showMessage(true, response.message);
                        if (response.next_step === 3) {
                            updateUI(3); 
                        }
                    } else {
                        showMessage(false, response.message);
                        $('#step2-submit-btn').prop('disabled', false).text('Verify OTP & Change Password');
                    }
                },
                error: function(xhr) {
                    showMessage(false, "Network or Server Error during OTP verification. Status: " + xhr.status);
                    console.error("AJAX Error:", xhr.responseText);
                    $('#step2-submit-btn').prop('disabled', false).text('Verify OTP & Change Password');
                }
            });
        });

        // ===================================
        // Cancel/Restart Modal Logic
        // ===================================
        
        $('#cancelBtn').on('click', function() {
            $('#cancelModal').removeClass('hidden').addClass('flex');
        });

        $('#confirmCancelBtn').on('click', function() {
             if (typeof window.loadHostSection === 'function') {
                 window.loadHostSection('change_password'); 
             } else {
                 window.location.reload(); 
             }
        });

        $('#closeCancelBtn, #cancelModal').on('click', function(e) {
            if ($(e.target).is('#cancelModal') || $(e.target).is('#closeCancelBtn') || $(e.target).closest('#closeCancelBtn').length) {
                $('#cancelModal').removeClass('flex').addClass('hidden');
            }
        });
        
        // Initial setup
        updateUI(1);

    });
</script>