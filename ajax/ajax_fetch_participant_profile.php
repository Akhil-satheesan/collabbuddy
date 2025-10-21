<?php
// FILE: ajax/ajax_fetch_participant_profile.php
// ----------------------------------------------------------------------
require_once __DIR__ . '/../include/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// 1. Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host') {
    http_response_code(401);
    echo "<p class='text-red-600 p-4'>Unauthorized access.</p>";
    exit;
}

$participant_id = (int) ($_GET['user_id'] ?? 0);
$request_id = (int) ($_GET['request_id'] ?? 0);

if ($participant_id === 0 || $request_id === 0) {
    echo "<p class='text-red-600 p-4'>Invalid parameters provided.</p>";
    exit;
}

$conn = get_db_connection();

if (!$conn) {
    echo "<p class='text-red-600 font-bold'>Database Connection Failed!</p>";
    echo "<p class='text-sm text-gray-700 bg-yellow-100 p-2 rounded-lg mt-1'>Please verify your database credentials and connection logic in 'include/config.php'.</p>";
    exit;
}

// 2. Fetch User, Participant and Request Data
$sql = "SELECT 
            u.user_id, u.name, u.email, u.github_url,
            part.preferred_role, part.skills, 
            a.resume_path,  
            pr.created_at AS request_date, pr.project_id,
            p.title AS project_title,
            a.cover_message
        FROM users u
        LEFT JOIN participants part ON u.user_id = part.participant_id
        INNER JOIN project_requests pr ON pr.participant_id = u.user_id AND pr.request_id = ?
        INNER JOIN projects p ON pr.project_id = p.project_id
        LEFT JOIN applications a ON a.project_id = pr.project_id AND a.participant_id = u.user_id
        WHERE u.user_id = ?";

$stmt = $conn->prepare($sql);

if ($stmt === false) { 
    error_log("Profile Fetch Prepare Error: " . $conn->error);
    $error_msg = $conn->error ? htmlspecialchars($conn->error) : 'Unknown SQL preparation error.';
    echo "<p class='text-red-600'>SQL Error: $error_msg</p>";
    $conn->close();
    exit;
}

$stmt->bind_param("ii", $request_id, $participant_id); 
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$data) {
    echo "<p class='text-red-600 p-4'>Participant or request details not found.</p>";
    exit;
}

// 3. Data Processing for Display
$skills = !empty($data['skills']) ? array_filter(array_map('trim', explode(',', $data['skills']))) : [];
$initials = strtoupper(substr($data['name'] ?? '??', 0, 2));
$role = !empty($data['preferred_role']) ? $data['preferred_role'] : 'General Participant';
$resumePath = $data['resume_path'] ?? ''; 
$githubUrl = $data['github_url'] ?? ''; 

// 🎯 FIX: Correct Base URL. This ensures the path starts correctly with http://localhost/collabuddy/
$base_url = 'http://localhost/collabuddy/'; 
?>

<div class="p-4 space-y-6">
    
    <div class="flex items-center space-x-4 border-b pb-4">
        <div class="w-16 h-16 bg-indigo-600 rounded-full flex items-center justify-center text-white text-2xl font-bold flex-shrink-0">
            <?= htmlspecialchars($initials) ?>
        </div>
        <div>
            <h2 class="text-2xl font-extrabold text-gray-900"><?= htmlspecialchars($data['name']) ?></h2>
            <p class="text-indigo-600 font-semibold text-lg"><?= htmlspecialchars($role) ?></p>
            <p class="text-sm text-gray-500">
                Applied for: <span class="font-medium text-indigo-700"><?= htmlspecialchars($data['project_title']) ?></span> 
                on <?= date('M j, Y', strtotime($data['request_date'])) ?>
            </p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-6">
        <div class="space-y-3">
            <h4 class="text-lg font-semibold text-gray-800 border-b pb-1">Contact & Basic Info</h4>
            <p class="text-sm text-gray-700"><strong>Email:</strong> <?= htmlspecialchars($data['email']) ?></p>
            <p class="text-sm text-gray-700"><strong>Phone:</strong> <span class="text-gray-500 italic">Not available in database</span></p>
            <p class="text-sm text-gray-700"><strong>Experience:</strong> <span class="text-gray-500 italic">Not available in database</span></p>
            
            <div class="pt-3 space-y-2">
                <h5 class="text-sm font-semibold text-gray-800 mt-2">Resume / CV:</h5>
                <?php if (!empty($resumePath)): ?>
                    <a href="<?= htmlspecialchars($base_url . $resumePath) ?>" 
                       class="inline-flex items-center text-green-700 bg-green-100 hover:bg-green-200 px-3 py-1 rounded-lg text-sm font-medium transition duration-150">
                        📄 **View Uploaded Resume File**
                    </a>
                <?php else: ?>
                    <p class="text-sm text-red-600 bg-red-50 p-2 rounded-lg">
                        ⚠️ **Resume not available** (File upload path is empty).
                    </p>
                <?php endif; ?>
            </div>

            <div class="pt-2 space-x-3">
                <?php if (!empty($githubUrl)): ?>
                    <a href="<?= htmlspecialchars($githubUrl) ?>" target="_blank" class="text-blue-700 hover:text-blue-900 text-sm font-medium">GitHub Profile</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="space-y-3">
            <h4 class="text-lg font-semibold text-gray-800 border-b pb-1">Bio/Summary</h4>
            <div class="bg-gray-100 p-3 rounded-lg h-full overflow-auto">
                <p class="text-sm text-gray-600 whitespace-pre-line">The participant's **Bio**, **Location**, and **Timezone** details are not available in the current database schema.</p>
            </div>
        </div>
    </div>

    ---
    <div class="border-t pt-4">
        <h4 class="text-lg font-semibold text-gray-800 mb-2">Skills & Technologies</h4>
        <?php if (!empty($skills)): ?>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($skills as $skill): ?>
                    <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium shadow-sm"><?= htmlspecialchars($skill) ?></span>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-gray-500 text-sm">No skills listed in the participant profile.</p>
        <?php endif; ?>
    </div>

    ---
    <div class="border-t pt-4">
        <h4 class="text-lg font-semibold text-gray-800 mb-2">Cover Message (Application)</h4>
        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
            <p class="text-gray-700 text-sm italic whitespace-pre-line">
                "<?= nl2br(htmlspecialchars($data['cover_message'] ?? "No specific cover message was provided with this request.")) ?>"
            </p>
        </div>
    </div>
    
</div>