<?php
// PHP BLOCK START 
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (session_status() == PHP_SESSION_NONE) session_start();
// ** PATH CHECK **: config.php പാത്ത് ശരിയാണെന്ന് ഉറപ്പാക്കുക.
// നിങ്ങളുടെ ഫയൽ സിസ്റ്റം അനുസരിച്ച് ഈ പാത്ത് ശരിയാണെങ്കിൽ നിലനിർത്തുക.
include_once __DIR__ . '/../../include/config.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'participant') {
    header("Location: ../login.php");
    exit();
}

$current_user_name = $_SESSION['name'] ?? 'User'; 
// PHP BLOCK END
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script> 
</head>
<body class="bg-gray-50">

<div class="flex justify-center py-8"> 
    <div class="w-full max-w-lg bg-white p-8 rounded-xl shadow-2xl border border-gray-100">
        
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Hello <?php echo htmlspecialchars($current_user_name); ?>!</h1>
        <p class="text-xl text-indigo-600 mb-6 border-b pb-2">Change Your Password via OTP</p>

        <div id="status-message" class="hidden mb-4 p-3 rounded" role="alert"></div>

        <div class="mb-6">
            <div class="flex justify-between text-sm font-medium text-gray-600">
                <span>Step 1: New Password</span>
                <span>Step 2: OTP Verification</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2.5 mt-1">
                <div id="progress-bar" class="bg-indigo-600 h-2.5 rounded-full transition-all duration-300" style="width: 33%"></div>
            </div>
        </div>


        <form id="step1-form" class="space-y-4">
            
            <div class="mb-4">
                <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password (Min 8 chars)</label>
                <div class="relative">
                    <input type="password" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 pr-10" id="new_password" name="new_password" required minlength="8">
                    <button type="button" class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-600 text-lg" data-target="new_password" id="toggle-new-password">
                        <i class="fas fa-eye"></i> </button>
                </div>
            </div>

            <div class="mb-6">
                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                <div class="relative">
                    <input type="password" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 pr-10" id="confirm_password" name="confirm_password" required minlength="8">
                    <button type="button" class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-600 text-lg" data-target="confirm_password" id="toggle-confirm-password">
                        <i class="fas fa-eye"></i> </button>
                </div>
                <div id="password-validation-status" class="mt-2 text-sm space-y-1 font-semibold">
                    <p id="len-check" class="text-red-500"><i class="fas fa-times-circle"></i> At least 8 characters long</p>
                    <p id="upper-check" class="text-red-500"><i class="fas fa-times-circle"></i> Capital Letter (A-Z)</p>
                    <p id="lower-check" class="text-red-500"><i class="fas fa-times-circle"></i> Lowercase Letter (a-z)</p>
                    <p id="digit-check" class="text-red-500"><i class="fas fa-times-circle"></i> Number or Special Character</p>
                </div>
                <div id="confirm-match-status" class="mt-2 text-sm font-semibold"></div>
            </div>
            
            <button type="submit" class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg shadow-md transition duration-200" id="step1-submit-btn">
                Continue & Send OTP
            </button>
        </form>

        <form id="step2-form" class="space-y-4 hidden">
            <p class="text-gray-600 mb-4">Please enter the 6-digit verification code sent to your registered email address.</p>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Verification Code (OTP)</label>
                
                <div class="flex justify-between space-x-2" id="otp-input-boxes">
                    <input type="text" class="otp-input w-1/6 p-3 border border-gray-300 rounded-lg text-center text-2xl font-bold focus:ring-indigo-500 focus:border-indigo-500" maxlength="1" inputmode="numeric" required data-index="0">
                    <input type="text" class="otp-input w-1/6 p-3 border border-gray-300 rounded-lg text-center text-2xl font-bold focus:ring-indigo-500 focus:border-indigo-500" maxlength="1" inputmode="numeric" required data-index="1">
                    <input type="text" class="otp-input w-1/6 p-3 border border-gray-300 rounded-lg text-center text-2xl font-bold focus:ring-indigo-500 focus:border-indigo-500" maxlength="1" inputmode="numeric" required data-index="2">
                    <input type="text" class="otp-input w-1/6 p-3 border border-gray-300 rounded-lg text-center text-2xl font-bold focus:ring-indigo-500 focus:border-indigo-500" maxlength="1" inputmode="numeric" required data-index="3">
                    <input type="text" class="otp-input w-1/6 p-3 border border-gray-300 rounded-lg text-center text-2xl font-bold focus:ring-indigo-500 focus:border-indigo-500" maxlength="1" inputmode="numeric" required data-index="4">
                    <input type="text" class="otp-input w-1/6 p-3 border border-gray-300 rounded-lg text-center text-2xl font-bold focus:ring-indigo-500 focus:border-indigo-500" maxlength="1" inputmode="numeric" required data-index="5">
                </div>
                <input type="hidden" id="combined_otp_code" name="otp_code">
            </div>
            
            <button type="submit" class="w-full py-3 px-4 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg shadow-md transition duration-200" id="step2-submit-btn">
                Verify OTP & Change Password
            </button>
            <button type="button" id="restart-process" class="w-full text-sm mt-3 text-red-500 hover:text-red-700">
                Cancel / Restart Process
            </button>
        </form>

    </div>
</div>
<div id="cancelModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden flex items-center justify-center z-50 transition-opacity duration-300">
    <div class="bg-white rounded-lg shadow-xl overflow-hidden w-full max-w-sm m-4 transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                        Confirm Cancellation
                    </h3>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500">
                            Are you sure you want to **cancel and restart** the password change process? Your current input will be lost.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
            <button type="button" id="confirmCancelBtn" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                Yes, Restart Now
            </button>
            <button type="button" id="closeModalBtn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm">
                No, Continue
            </button>
        </div>
    </div>
</div>
<script>
    $(document).ready(function() {
        
        // 💡 AJAX URL FIX: നിങ്ങളുടെ മുമ്പത്തെ കോഡിൽ ഉണ്ടായിരുന്ന പാത്ത് തന്നെ ഉപയോഗിക്കുന്നു.
        const processUrl = 'include/participant_sections/process_password_change_otp_only.php'; 
        
        console.log("AJAX Target URL:", processUrl);
        
        // Font Awesome CSS ലിങ്ക് ഇതിനകം HTML-ൽ ചേർത്തിട്ടുണ്ട് എന്ന് കരുതുന്നു.

        // ===================================
        // Helper Functions
        // ===================================
        function showMessage(success, message) {
            const statusDiv = $('#status-message');
            statusDiv.removeClass('hidden bg-red-100 border-red-400 text-red-700 bg-green-100 border-green-400 text-green-700')
                     .addClass(success ? 'bg-green-100 border-green-400 text-green-700 border' : 'bg-red-100 border-red-400 text-red-700 border')
                     .html(`<span class="block sm:inline">${success ? '✅ Success' : '⚠️ Error'}: ${message}</span>`);
        }

        function updateUI(step) {
            const progressBar = $('#progress-bar');
            $('#step1-form').toggleClass('hidden', step !== 1);
            $('#step2-form').toggleClass('hidden', step !== 2);
            $('#status-message').addClass('hidden'); 
            
            if (step === 1) {
                progressBar.css('width', '33%').removeClass('bg-green-600').addClass('bg-indigo-600');
                $('#step1-submit-btn').prop('disabled', false).text('Continue & Send OTP');
            } else if (step === 2) {
                progressBar.css('width', '66%').removeClass('bg-green-600').addClass('bg-indigo-600');
                $('.otp-input').val(''); // Clear OTP inputs
                $('#combined_otp_code').val(''); // Clear combined OTP
                $('.otp-input[data-index="0"]').focus(); // Focus first input
            } else if (step === 3) {
                progressBar.css('width', '100%').addClass('bg-green-600').removeClass('bg-indigo-600');
                $('#step1-form, #step2-form').addClass('hidden');
                showMessage(true, "Password successfully changed!");
            }
        }
        
        // Initial state
        updateUI(1);
        
        // ===================================
        // 1. Show/Hide Password Logic
        // ===================================
        $('[id^=toggle-]').on('click', function() {
            const $btn = $(this);
            const targetId = $btn.data('target');
            const targetInput = $('#' + targetId);
            const type = targetInput.attr('type') === 'password' ? 'text' : 'password';
            targetInput.attr('type', type);
            
            $btn.find('i').toggleClass('fa-eye fa-eye-slash');
        });

        // ===================================
        // 2. Live Password Validation Logic
        // ===================================
        function checkPasswordStrength(password) {
            const checks = {
                len: { passed: password.length >= 8, id: '#len-check', text: 'At least 8 characters long' },
                upper: { passed: /[A-Z]/.test(password), id: '#upper-check', text: 'Capital Letter (A-Z)' },
                lower: { passed: /[a-z]/.test(password), id: '#lower-check', text: 'Lowercase Letter (a-z)' },
                digit: { passed: /[\d\W]/.test(password), id: '#digit-check', text: 'Number or Special Character' } 
            };
            
            let allPassed = true;

            for (const key in checks) {
                const check = checks[key];
                const element = $(check.id);

                if (check.passed) {
                    element.removeClass('text-red-500').addClass('text-green-600')
                           .html('<i class="fas fa-check-circle"></i> ' + check.text);
                } else {
                    element.removeClass('text-green-600').addClass('text-red-500')
                           .html('<i class="fas fa-times-circle"></i> ' + check.text);
                    allPassed = false;
                }
            }
            
            return allPassed;
        }

        $('#new_password').on('input', function() {
            checkPasswordStrength($(this).val());
        });

        $('#confirm_password, #new_password').on('input', function() {
            const newPass = $('#new_password').val();
            const confPass = $('#confirm_password').val();
            const matchStatus = $('#confirm-match-status');
            
            const newPassValid = checkPasswordStrength(newPass);

            if (newPass.length === 0 || confPass.length === 0) {
                matchStatus.text('');
            } else if (newPassValid && newPass === confPass) {
                matchStatus.html('<span class="text-green-600">✅ Passwords match.</span>');
            } else if (newPassValid && newPass !== confPass) {
                 matchStatus.html('<span class="text-red-500">❌ Passwords do not match.</span>');
            }
        });


        // ===================================
        // 3. OTP Box Input Logic (Auto-Focus & Backspace)
        // ===================================
        $('#otp-input-boxes').on('input', '.otp-input', function(e) {
            const $this = $(this);
            const index = parseInt($this.data('index'));
            
            // Ensure only numbers are entered
            $this.val($this.val().replace(/[^0-9]/g, ''));

            // Auto-advance logic: Focus the next input
            if ($this.val().length === $this.attr('maxlength')) {
                const nextInput = $(`[data-index='${index + 1}']`);
                if (nextInput.length) {
                    nextInput.focus();
                }
            }
            
            // Combine OTP and update hidden field
            let combinedOtp = '';
            $('.otp-input').each(function() {
                combinedOtp += $(this).val();
            });
            $('#combined_otp_code').val(combinedOtp);
        });

        $('#otp-input-boxes').on('keydown', '.otp-input', function(e) {
            const $this = $(this);
            const index = parseInt($this.data('index'));

            // Backspace key press
            if (e.key === 'Backspace') {
                // If current input is empty, move focus to the previous one
                if ($this.val() === '') {
                    e.preventDefault();
                    const prevInput = $(`[data-index='${index - 1}']`);
                    if (prevInput.length) {
                        prevInput.focus();
                    }
                }
            }
        });


        // ===================================
        // STEP 1 Submission (OTP Request)
        // ===================================
        $('#step1-form').on('submit', function(e) {
            e.preventDefault();
            const newPass = $('#new_password').val();
            const confPass = $('#confirm_password').val();
            
            if (!checkPasswordStrength(newPass) || newPass !== confPass) {
                showMessage(false, "Validation failed: Please ensure passwords match and meet all security criteria.");
                return;
            }

            $('#step1-submit-btn').prop('disabled', true).text('Processing...');

            $.ajax({
                url: processUrl, 
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showMessage(true, response.message);
                        if (response.next_step === 2) {
                            updateUI(2); 
                        }
                    } else {
                        showMessage(false, response.message);
                        $('#step1-submit-btn').prop('disabled', false).text('Continue & Send OTP');
                    }
                },
                error: function(xhr, status, error) {
                    showMessage(false, "Network or Server Error during Step 1. Please check console.");
                    console.error("AJAX Error:", status, error, xhr.responseText);
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
                return;
            }
            
            $('#step2-submit-btn').prop('disabled', true).text('Verifying...');

            $.ajax({
                url: processUrl,
                type: 'POST',
                data: $(this).serialize(), 
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
                error: function(xhr, status, error) {
                    showMessage(false, "Network or Server Error during OTP verification. Please check console.");
                    console.error("AJAX Error:", status, error, xhr.responseText);
                    $('#step2-submit-btn').prop('disabled', false).text('Verify OTP & Change Password');
                }
            });
        });

        // ===================================
        // 4. Restart Process (Custom Modal Logic)
        // ===================================
        
        // Function to show the modal
        function showCancelModal() {
            $('#cancelModal').removeClass('hidden');
        }

        // Function to hide the modal
        function hideCancelModal() {
            $('#cancelModal').addClass('hidden');
        }

        // 4a. Trigger the modal when 'Cancel / Restart Process' is clicked
        $('#restart-process').on('click', function() {
            showCancelModal();
        });

        // 4b. Handle 'No, Continue' button click
        $('#closeModalBtn').on('click', function() {
            hideCancelModal();
        });

        // 4c. Handle 'Yes, Restart Now' button click
        $('#confirmCancelBtn').on('click', function() {
            window.location.reload(); // Restart the page
        });

        // Close modal when clicking outside (on the backdrop)
        $('#cancelModal').on('click', function(e) {
            if (e.target.id === 'cancelModal') {
                hideCancelModal();
            }
        });

    });
</script>
</body>
</html>