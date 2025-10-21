<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");
require 'include/config.php'; 

if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    if (isset($_SESSION['participant_profile_completed']) && $_SESSION['participant_profile_completed'] === true) {
        header("Location: participate_dashboard.php");
        exit;
    }
} else {
    if (!isset($_SESSION['email'])) {
        header("Location: login.php");
        exit;
    }
}

$error = '';
$success = '';

if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

$db_languages = [];
try {
    $lang_stmt = $conn->prepare("SELECT language_name FROM languages ORDER BY language_name ASC");
    $lang_stmt->execute();
    $lang_result = $lang_stmt->get_result();
    while ($row = $lang_result->fetch_assoc()) {
        $db_languages[] = '#' . $row['language_name']; 
    }
    $lang_stmt->close();
} catch (Exception $e) {
    error_log("Database error fetching languages: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_SESSION['email'] ?? null;
    $preferred_role = $_POST['preferred_role'] ?? ''; 
    $skills = $_POST['skills'] ?? ''; 
    $languages = $_POST['languages'] ?? ''; 
    $github_username = $_POST['github_username'] ?? null;

    $github_url = $github_username ? "https://github.com/" . $github_username : null;

    if ($email && $preferred_role) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($user_id);
        $stmt->fetch();
        $stmt->close();

        if ($user_id) {
            try {
                $conn->begin_transaction(); 

                $update_user_stmt = $conn->prepare("UPDATE users SET role = 'participant', github_url = ? WHERE user_id = ?");
                $update_user_stmt->bind_param("si", $github_url, $user_id);
                $update_user_stmt->execute();
                $update_user_stmt->close();

                $insert_participant_stmt = $conn->prepare("INSERT INTO participants (participant_id, preferred_role, skills, languages, verified) 
                                                        VALUES (?, ?, ?, ?, 1)
                                                        ON DUPLICATE KEY UPDATE preferred_role=VALUES(preferred_role), skills=VALUES(skills), languages=VALUES(languages)");
                $insert_participant_stmt->bind_param("isss", $user_id, $preferred_role, $skills, $languages);

                if ($insert_participant_stmt->execute()) {
                    $insert_participant_stmt->close();
                    $conn->commit(); 
                    $_SESSION['participant_profile_completed'] = true;
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['role'] = 'participant'; 
                    $_SESSION['success'] = "🎉 Successfully registered! Welcome to CollabBuddy. Redirecting to your dashboard...";
                    $role_for_redirect = $_SESSION['role'];
                    
                    header("Location: participate_dashboard.php?role=" . urlencode($role_for_redirect));
                    exit;
                } else {
                    throw new Exception("Error inserting participant details: " . $insert_participant_stmt->error);
                }
            } catch (Exception $e) {
                $conn->rollback(); 
                $error = "❌ Database Error: Could not save your details. Please try again.";
            }
        } else {
            $error = "🚫 No user found for this email. Please ensure you completed the first signup step.";
        }
    } else {
        $error = "⚠️ Required fields (Email and Preferred Role) are missing or session expired. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CollabBuddy - Participant Signup</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"> 
  <style>
    :root {
        --primary-color: #4f46e5; 
        --primary-light: #eef2ff;
        --border-color: #d1d5db;
        --success-color: #059669;
        --error-color: #dc2626;
        --role-display-bg: #f7f7f7; 
    }
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f3f4f6;
    }
    .signup-container {
        max-width: 1000px; 
        width: 95%; 
        margin: 50px auto;
        padding: 0; 
        background-color: #ffffff;
        border-radius: 16px; 
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.1); 
    }
    .form-columns {
        display: grid;
        grid-template-columns: 1fr 1fr; 
        gap: 0; 
        align-items: stretch; 
    }
    .column-left {
        padding: 50px 40px; 
        background-color: #ffffff;
        border-right: 1px solid #f3f4f6; 
    }
    .column-right {
        padding: 50px 40px; 
        background-color: var(--primary-light); 
        color: #1f2937; 
        display: flex;
        flex-direction: column;
        justify-content: flex-start; 
    }
    #github-status {
        font-size: 14px; 
        margin-top: 5px; 
        font-weight: 500;
        min-height: 20px;
    }
    .status-ok { color: var(--success-color); }
    .status-error { color: var(--error-color); }
    .btn-primary {
        background-color: var(--primary-color);
        color: white;
        padding: 14px 30px;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 700;
        font-size: 17px;
        transition: all 0.3s;
        box-shadow: 0 4px 15px rgba(79, 70, 229, 0.4);
    }
    .btn-primary[disabled] {
        background-color: #9ca3af;
        cursor: not-allowed;
        box-shadow: none;
    }
    .role-helper {
        background: #ffffff; 
        border: 1px solid #d1d5db;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px; 
        box-shadow: 0 10px 30px rgba(79, 70, 229, 0.08); 
        transition: all 0.3s ease;
    }
    .role-helper:hover {
        box-shadow: 0 15px 40px rgba(79, 70, 229, 0.15); 
    }
    .role-options {
        display: flex;
        flex-wrap: wrap;
        gap: 10px; 
        margin-top: 10px;
    }
    .role-options label {
        flex-grow: 1; 
        text-align: center;
        padding: 12px 15px;
        border: 2px solid #e0e7ff; 
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        font-size: 14px;
        color: #374151; 
        background-color: #f9fafb; 
        transition: all 0.2s ease;
        line-height: 1.3;
    }
    .role-options label:hover:not(.selected) {
        background-color: #eff6ff; 
        border-color: #93c5fd; 
        color: #1e40af;
    }
    .role-options label.selected { 
        background-color: var(--primary-light); 
        border-color: var(--primary-color); 
        color: var(--primary-color);
        box-shadow: 0 0 0 2px var(--primary-light);
        transform: translateY(-1px); 
    }
    #current-roles-display {
        padding: 15px;
        border-radius: 10px;
        background: #ffffff; 
        border: 1px solid #e5e7eb;
        min-height: 50px;
        box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05); 
        margin-top: 10px;
    }
    .selected-role-tag {
        display: inline-flex;
        align-items: center;
        background-color: #6366f1; 
        color: white;
        padding: 7px 14px;
        border-radius: 25px; 
        margin-right: 8px;
        margin-bottom: 8px;
        font-size: 14px;
        font-weight: 500;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
    }
    .selected-role-tag span {
        margin-left: 8px;
        font-weight: bold;
        cursor: pointer;
        opacity: 0.8;
        transition: opacity 0.2s;
    }
    .selected-role-tag span:hover {
        opacity: 1;
    }
    #role_add {
        width: 100%;
        padding: 12px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        background-color: white;
        appearance: none; 
        background-image: url('data:image/svg+xml;utf8,<svg fill="%234f46e5" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>');
        background-repeat: no-repeat;
        background-position-x: 98%;
        background-position-y: 50%;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    }
    .tags-input {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        width: 100%;
        min-height: 48px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        padding: 5px;
        box-sizing: border-box;
        background-color: #fff;
    }
    .tag {
        display: inline-flex;
        align-items: center;
        background-color: #059669; 
        color: white;
        padding: 5px 10px;
        border-radius: 20px;
        margin: 3px;
        font-size: 14px;
        font-weight: 500;
    }
    .suggestion-container {
        min-height: 40px; 
        padding-top: 10px;
        margin-top: 5px;
        border-top: 1px solid #f3f4f6;
    }
    .suggestion-tag {
        display: inline-block;
        background-color: #dbeafe; 
        color: #1d4ed8;
        border: 1px solid #93c5fd;
        padding: 5px 10px;
        border-radius: 20px;
        margin: 3px;
        font-size: 13px;
        cursor: pointer;
    }
    @media (max-width: 768px) { 
        .form-columns {
            grid-template-columns: 1fr;
            min-height: auto; 
        }
        .column-left {
            padding: 30px;
            border-right: none;
            border-bottom: 1px solid #e0e0e0; 
        }
        .column-right {
            padding: 30px;
        }
    }
  </style>
</head>
<body>
<header style="background-color:white; padding:15px 50px; border-bottom:1px solid #e5e7eb;">
    <h1 style="color:var(--primary-color); font-size:24px; margin:0;">CollabBuddy</h1>
</header>
<div class="signup-container">
    <?php if (!empty($success)): ?>
        <div class="message-box success-message" style="margin: 20px 40px 0 40px;">
            <?php echo $success; ?>
        </div>
    <?php elseif (!empty($error)): ?>
        <div class="message-box error-message" style="margin: 20px 40px 0 40px;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    <form action="" method="POST" class="signup-form" id="participant-signup-form">
        <div class="form-columns">
            <div class="column-left">
                <h2 class="text-2xl font-bold mb-3" style="color:#1f2937;">Add Your Expertise</h2>
                <p class="text-sm text-gray-500 mb-8">List your skills and languages for project matching.</p>
                <div class="form-group">
                  <label>GitHub Username (optional)</label>
                  <input type="text" name="github_username" id="github_username" placeholder="e.g., torvalds">
                  <p id="github-status" style="color:#4b5563;">Enter your username to verify your profile.</p>
                </div>
                <div class="form-group">
                  <label>Languages Known (Programming & Natural)</label>
                  <div class="tags-input" id="languages-container">
                    <input type="text" id="language-input" placeholder="Type a language or select suggestion">
                  </div>
                  <div id="language-suggestions" class="suggestion-container">
                    </div>
                  <input type="hidden" name="languages" id="hidden-languages">
                  <small style="color:#6b7280;">Click on suggestions to add instantly. Programming languages will have a # prefix.</small>
                </div>
                <div class="form-group">
                  <label>Skills / Expertise</label>
                  <div class="tags-input" id="skills-container">
                    <input type="text" id="skill-input" placeholder="Type a skill and press Enter or comma">
                  </div>
                  <input type="hidden" name="skills" id="hidden-skills">
                  <small style="color:#6b7280;">Example: UI/UX, Marketing, Testing</small>
                </div>
            </div>
            <div class="column-right">
                <h3 class="text-2xl font-bold mb-6" style="color:var(--primary-color);">Select Your Preferred Role(s)</h3>
                <div id="role-selection-section" style="display:none;">
                    <div class="role-helper">
                        <p class="font-medium mb-3">1. Which project area interests you the most?</p>
                        <div class="role-options mb-4">
                            <label><input type="radio" name="area_interest" value="code" hidden> <span><i class="fas fa-laptop-code mr-2"></i> Software Development</span></label>
                            <label><input type="radio" name="area_interest" value="data" hidden> <span><i class="fas fa-chart-line mr-2"></i> Data/AI</span></label>
                            <label><input type="radio" name="area_interest" value="design" hidden> <span><i class="fas fa-pencil-ruler mr-2"></i> Design/Content</span></label>
                            <label><input type="radio" name="area_interest" value="manage" hidden> <span><i class="fas fa-users-cog mr-2"></i> Management/QA</span></label>
                        </div>
                        <div id="role-q2" style="display:none;">
                            <p class="font-medium mb-3 mt-4">2. Choose your specific focus (Select multiple):</p>
                            <div class="role-options" id="specific-focus-options">
                                </div>
                        </div>
                        <div id="suggested-role-display" class="suggested-role-box mt-4">
                            <p class="font-bold text-sm">Roles Selected:</p>
                            <div id="current-roles-display">
                                <small style="color: #6b7280;">No roles selected yet.</small>
                            </div>
                            <input type="hidden" name="preferred_role" id="hidden-preferred-role" value="">
                        </div>
                    </div>
                    <div class="form-group" style="margin-top: 15px;">
                        <label>Manual Role Override/Addition (Optional)</label>
                        <select name="role_add" id="role_add" class="styled-select" style="padding: 10px;">
                            <option value="">-- Add another role manually --</option>
                            <option value="Project Manager">📋 Project Manager</option>
                            <option value="Researcher">🔬 Researcher</option>
                            <option value="UI/UX Designer">🎨 UI/UX Designer</option>
                            <option value="Graphic Designer">🖌️ Graphic Designer</option>
                            <option value="Frontend Developer">💻 Frontend Developer</option>
                            <option value="Backend Developer">🖥️ Backend Developer</option>
                            <option value="Fullstack Developer">🌐 Fullstack Developer</option>
                            <option value="QA Tester">🧪 QA Tester</option>
                            <option value="DevOps Engineer">⚙️ DevOps Engineer</option>
                        </select>
                    </div>
                </div>
                <div id="role-initial-prompt" style="padding: 20px; text-align: center; color: #4b5563; background: #fff; border-radius: 8px; border: 1px dashed #d1d5db;">
                    <i class="fas fa-arrow-left mr-2"></i> Please add at least one language (on the left) to select your preferred role.
                </div>
            </div>
        </div>
        <div class="mt-8 text-center" style="padding: 20px 0 40px;"> 
            <button type="submit" class="btn-primary" id="submit-btn" disabled>
                <i class="fas fa-check-circle mr-2"></i> Sign Up as Participant
            </button>
            <p class="text-sm text-gray-500 mt-3" id="submit-help-text">Please add a language and select a role to proceed.</p>
        </div>
    </form>
</div>
<script>
const githubInput = document.getElementById("github_username");
const githubStatus = document.getElementById("github-status");
const submitBtn = document.getElementById("submit-btn");
const submitHelpText = document.getElementById("submit-help-text");
const currentRolesDisplay = document.getElementById("current-roles-display");
const hiddenPreferredRole = document.getElementById("hidden-preferred-role");
const roleInterestRadios = document.querySelectorAll('input[name="area_interest"]');
const roleQ2 = document.getElementById("role-q2");
const specificFocusOptions = document.getElementById("specific-focus-options");
const roleAddSelect = document.getElementById("role_add");
const languageSuggestionsContainer = document.getElementById("language-suggestions");
const languageInput = document.getElementById("language-input");
const hiddenLanguages = document.getElementById("hidden-languages");
const languagesContainer = document.getElementById("languages-container");
const skillsContainer = document.getElementById("skills-container");
const skillInput = document.getElementById("skill-input");
const hiddenSkills = document.getElementById("hidden-skills");
const roleSelectionSection = document.getElementById("role-selection-section");
const roleInitialPrompt = document.getElementById("role-initial-prompt");

let selectedRoles = [];
let skills = [];
let languages = [];
let isGithubValid = true; 

const DB_PROGRAMMING_LANGUAGES = <?php echo json_encode($db_languages); ?>; 
const OTHER_LANGUAGES = ['English', 'Malayalam', 'Hindi', 'SEO Principles', 'Agile'];
const SUGGESTED_LANGUAGES = [...DB_PROGRAMMING_LANGUAGES, ...OTHER_LANGUAGES];

const ROLE_MAP = {
    'code': { 'Frontend': 'Frontend Developer', 'Backend': 'Backend Developer', 'Fullstack': 'Fullstack Developer', 'Mobile (iOS/Android)': 'Mobile Developer', 'QA/Testing': 'QA Tester', 'Deployment/Ops': 'DevOps Engineer' },
    'data': { 'Analysis/Viz': 'Data Analyst', 'Science/ML': 'Data Scientist', 'Engineering/ETL': 'Data Engineer', 'Research': 'Researcher' },
    'design': { 'UI/UX': 'UI/UX Designer', 'Graphic/Branding': 'Graphic Designer', 'Content/Writing': 'Content Creator' },
    'manage': { 'Project Leadership': 'Project Manager', 'QA Leadership': 'QA Tester', 'Business Analysis': 'Researcher' }
};

function verifyGithubUsername(username) {
    if (username.length === 0) {
        githubStatus.textContent = "Link your coding profile.";
        githubStatus.className = "";
        isGithubValid = true;
        checkFormValidity();
        return;
    }
    
    githubStatus.textContent = "Verifying username...";
    githubStatus.className = "";
    isGithubValid = false;
    checkFormValidity();

    fetch(`verify_github.php?username=${encodeURIComponent(username)}`)
        .then(response => {
            return response.json().then(data => ({
                status: response.status,
                body: data
            }));
        })
        .then(result => {
            const data = result.body;
            
            githubStatus.textContent = data.message;

            if (data.valid) {
                githubStatus.className = "status-ok";
                isGithubValid = true;
            } else {
                githubStatus.className = "status-error";
                isGithubValid = false;
            }
            checkFormValidity();
        })
        .catch(error => {
            console.error('Fetch error:', error);
            githubStatus.textContent = "❌ Network error or server issue.";
            githubStatus.className = "status-error";
            isGithubValid = false;
            checkFormValidity();
        });
    
}

githubInput.addEventListener('blur', function() {
    verifyGithubUsername(this.value.trim());
});

function addRole(role) {
    if (role && !selectedRoles.includes(role)) { 
        selectedRoles.push(role); 
        updateRoleDisplay(); 
        document.querySelectorAll(`#specific-focus-options input[data-role='${role.replace(/'/g, "\\'")}']`).forEach(input => {
            input.checked = true;
            input.closest('label').classList.add('selected');
        });
    }
}

function removeRole(roleToRemove) {
    selectedRoles = selectedRoles.filter(role => role !== roleToRemove); 
    updateRoleDisplay();
    document.querySelectorAll(`#specific-focus-options input[data-role='${roleToRemove.replace(/'/g, "\\'")}']`).forEach(input => {
        input.checked = false;
        input.closest('label').classList.remove('selected');
    });
}

function updateRoleDisplay() {
    currentRolesDisplay.innerHTML = '';
    const hasRoles = selectedRoles.length > 0;
    
    if (hasRoles) {
        selectedRoles.forEach(role => {
            const tagEl = document.createElement("div");
            tagEl.classList.add("selected-role-tag");
            const sanitizedRole = role.replace(/'/g, "\\'"); 
            tagEl.innerHTML = `${role} <span onclick="removeRole('${sanitizedRole}')">&times;</span>`;
            currentRolesDisplay.appendChild(tagEl);
        });
    } else { 
        currentRolesDisplay.innerHTML = '<small style="color: #6b7280;">No roles selected yet.</small>'; 
    }
    
    hiddenPreferredRole.value = selectedRoles.join(',');
    checkFormValidity();
}

roleInterestRadios.forEach(radio => {
    radio.addEventListener('change', function() {
        const area = this.value;
        const specificRoles = ROLE_MAP[area];
        
        roleInterestRadios.forEach(r => { r.closest('label').classList.remove('selected'); });
        this.closest('label').classList.add('selected');
        
        specificFocusOptions.innerHTML = '';
        if (specificRoles) {
            roleQ2.style.display = 'block';
            for (const key in specificRoles) {
                const role = specificRoles[key];
                const label = document.createElement('label');
                label.innerHTML = `<input type="checkbox" data-role="${role}" hidden> <span>${key} (${role.split(' ')[0]})</span>`; 
                
                if(selectedRoles.includes(role)) { label.classList.add('selected'); label.querySelector('input').checked = true; }
                
                label.querySelector('input').addEventListener('change', function() {
                    if (this.checked) { addRole(this.getAttribute('data-role')); } 
                    else { removeRole(this.getAttribute('data-role')); }
                });
                specificFocusOptions.appendChild(label);
            }
        } else { roleQ2.style.display = 'none'; }
    });
});
roleAddSelect.addEventListener('change', function() { addRole(this.value); this.value = ""; });

const updateTags = (container, inputEl, hiddenInputEl, tagsArray) => {
    container.querySelectorAll(".tag").forEach(tag => tag.remove());
    
    tagsArray.forEach((tagText) => {
        const tagEl = document.createElement("div");
        tagEl.classList.add("tag");
        const sanitizedTag = tagText.replace(/'/g, "\\'"); 
        tagEl.innerHTML = `${tagText} <span class="remove-tag" onclick="removeTag('${sanitizedTag}', '${container.id}')">&times;</span>`;
        container.insertBefore(tagEl, inputEl);
    });

    hiddenInputEl.value = tagsArray.join(',');
    
    if(container.id === 'languages-container') { 
        renderLanguageSuggestions(); 
        checkLanguageCount(); 
    }
    checkFormValidity(); 
}

function removeTag(tagToRemove, containerId) {
    if (containerId === 'languages-container') {
        languages = languages.filter(tag => tag !== tagToRemove);
        updateTags(languagesContainer, languageInput, hiddenLanguages, languages);
    } else if (containerId === 'skills-container') {
        skills = skills.filter(tag => tag !== tagToRemove);
        updateTags(skillsContainer, skillInput, hiddenSkills, skills);
    }
}

function createTagLogic(container, inputEl, hiddenInputEl, tagsArray) {
    inputEl.addEventListener("keydown", function(e) {
        if ((e.key === "Enter" || e.keyCode === 188) && inputEl.value.trim() !== "") {
            e.preventDefault();
            let newTag = inputEl.value.trim().replace(/,/g, ''); 
            
            if (container.id === 'languages-container' && !newTag.startsWith('#')) {
                const normalizedNewTag = newTag.toUpperCase().replace(/\s/g, '');
                const isProgrammingLanguage = DB_PROGRAMMING_LANGUAGES.some(db_lang => db_lang.toUpperCase().replace('#', '').includes(normalizedNewTag));
                if (isProgrammingLanguage) { newTag = `#${newTag}`; }
            }

            if (newTag.length > 0 && !tagsArray.includes(newTag)) { 
                tagsArray.push(newTag); 
                updateTags(container, inputEl, hiddenInputEl, tagsArray);
            }
            inputEl.value = "";
        }
    });
    updateTags(container, inputEl, hiddenInputEl, tagsArray);
}

createTagLogic(skillsContainer, skillInput, hiddenSkills, skills);
createTagLogic(languagesContainer, languageInput, hiddenLanguages, languages);

function renderLanguageSuggestions() {
    languageSuggestionsContainer.innerHTML = '';
    
    SUGGESTED_LANGUAGES.sort((a, b) => (b.startsWith('#') - a.startsWith('#')) || a.localeCompare(b));
    
    if (SUGGESTED_LANGUAGES.length === 0 && DB_PROGRAMMING_LANGUAGES.length === 0) {
         languageSuggestionsContainer.innerHTML = '<small style="color:var(--error-color);">Could not load suggestions. Check DB connection.</small>';
         return;
    }

    SUGGESTED_LANGUAGES.forEach(lang => {
        if (!languages.includes(lang)) { 
            const tagEl = document.createElement("span");
            tagEl.classList.add("suggestion-tag");
            tagEl.textContent = lang;
            tagEl.addEventListener('click', () => {
                languages.push(lang);
                updateTags(languagesContainer, languageInput, hiddenLanguages, languages); 
            });
            languageSuggestionsContainer.appendChild(tagEl);
        }
    });
}

function checkLanguageCount() {
    if (languages.length > 0) {
        roleSelectionSection.style.display = 'block'; 
        roleInitialPrompt.style.display = 'none';
    } else {
        selectedRoles = [];
        updateRoleDisplay();
        
        roleSelectionSection.style.display = 'none';
        roleInitialPrompt.style.display = 'block';
    }
}

function checkFormValidity() {
    const isLanguageSelected = languages.length > 0;
    const isRoleSelected = selectedRoles.length > 0;
    
    const canSubmit = isRoleSelected && isGithubValid && isLanguageSelected;
    
    submitBtn.disabled = !canSubmit;
    
    if (!isLanguageSelected) {
        submitHelpText.textContent = "⚠️ Please add at least one language.";
        submitHelpText.style.color = 'var(--error-color)';
    } else if (!isRoleSelected) {
        submitHelpText.textContent = "⚠️ Please select at least one preferred role.";
        submitHelpText.style.color = 'var(--error-color)';
    } else if (!isGithubValid) {
         submitHelpText.textContent = "⚠️ GitHub username is being verified or is invalid.";
         submitHelpText.style.color = 'var(--error-color)';
    } else {
        submitHelpText.textContent = "🎉 Ready to sign up!";
        submitHelpText.style.color = 'var(--success-color)';
    }
}

updateRoleDisplay(); 
renderLanguageSuggestions(); 
checkLanguageCount(); 
</script>
</body>
</html>