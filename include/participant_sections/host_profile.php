<?php
// host_profile.php - Host Profile Details for Modal (Enhanced Stats)
require __DIR__ . '/../config.php';

// 1. സുരക്ഷാ പരിശോധന
if(!isset($_SESSION['user_id']) || !isset($_GET['host_id'])){
 http_response_code(401);
die("<div class='p-4 bg-red-100 text-red-700 rounded-lg'>Unauthorized or missing host ID.</div>");
}

$host_id = (int)$_GET['host_id'];

// 2. Host വിവരങ്ങൾ Fetch ചെയ്യുന്നു (പുതിയ സ്റ്റാറ്റിസ്റ്റിക്‌സ് ഉൾപ്പെടുത്തി)
$sql_host_details = "
    SELECT 
        u.name, 
        u.email, 
        u.profile_pic_url,
        h.company_name, 
        h.host_type,
        
        -- 1. ആകെ ഹോസ്റ്റ് ചെയ്ത പ്രോജക്റ്റുകൾ
        (SELECT COUNT(*) FROM projects WHERE host_id = ?) AS total_projects_hosted,
        
        -- 2. ആകെ ജോയിൻ ചെയ്ത പാർട്ടിസിപ്പൻ്റ്സ് (Distinct count)
        (
            SELECT COUNT(DISTINCT pp.participant_id) 
            FROM project_participants pp
            JOIN projects p ON pp.project_id = p.project_id
            WHERE p.host_id = ?
        ) AS total_participants_joined
        
    FROM users u
    JOIN hosts h ON u.user_id = h.host_id
    WHERE u.user_id = ?
";

$stmt = $conn->prepare($sql_host_details);

if ($stmt === false) {
    die("SQL Prepare Error (Host Details): " . $conn->error); 
}

// 🛑 ശ്രദ്ധിക്കുക: ഇവിടെ host_id മൂന്ന് തവണ bind ചെയ്യുന്നു!
// 1. total_projects_hosted, 2. total_participants_joined, 3. പ്രധാന WHERE ക്ലോസ്
$stmt->bind_param("iii", $host_id, $host_id, $host_id); 
$stmt->execute();
$host_details = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$host_details) {
 die("<div class='p-4 bg-red-100 text-red-700 rounded-lg'>Host profile not found.</div>");
}

$host_name = htmlspecialchars($host_details['name']);
$host_image_url = !empty($host_details['profile_pic_url']) 
? '/collabuddy/' . htmlspecialchars($host_details['profile_pic_url']) 
: '/collabuddy/assets/default_profile.png';

$total_participants_joined = $host_details['total_participants_joined'];
?>

<script>
    document.getElementById('hostModalName').innerText = "<?= $host_name ?>'s Profile"; 
</script>

<div class="space-y-8 p-1">
    
        <div class="flex items-start space-x-6 pb-5 border-b border-gray-300">
        <div class="w-32 h-32 rounded-full overflow-hidden flex-shrink-0 border-4 border-gray-400 p-1 bg-gray-100 shadow-xl">
            <img src="<?= $host_image_url ?>" alt="<?= $host_name ?>'s Profile" class="w-full h-full object-cover rounded-full">
        </div>
        <div class="pt-4">
            <h2 class="text-4xl font-black text-gray-900 tracking-tight"><?= $host_name ?></h2>
            <span class="inline-block bg-indigo-700 text-gray-100 text-sm font-semibold px-4 py-1 rounded-full mt-2 uppercase tracking-wider shadow-lg">
                <?= htmlspecialchars($host_details['host_type']) ?> Host
            </span>
            <?php if (!empty($host_details['company_name'])): ?>
                <p class="text-base text-gray-700 mt-3 flex items-center">
                    <i class="fas fa-briefcase mr-2 text-indigo-500"></i>
                    Company: **<?= htmlspecialchars($host_details['company_name']) ?>**
                </p>
            <?php endif; ?>
        </div>
    </div>
    
        <div class="grid grid-cols-3 gap-4">
        
        <div class="bg-gray-100 p-4 rounded-xl text-center shadow-inner border-l-4 border-indigo-500 transition duration-300 hover:bg-gray-200">
            <p class="text-3xl font-extrabold text-indigo-800 tracking-tight"><?= $host_details['total_projects_hosted'] ?></p>
            <p class="text-xs text-indigo-600 mt-1 uppercase font-bold">Projects Hosted</p>
        </div>
        
        <div class="bg-gray-100 p-4 rounded-xl text-center shadow-inner border-l-4 border-green-500 transition duration-300 hover:bg-gray-200">
            <p class="text-3xl font-extrabold text-green-700 tracking-tight"><?= $total_participants_joined ?></p>
            <p class="text-xs text-green-600 mt-1 uppercase font-bold">Total Collaborators</p>
        </div>
        
                <div class="bg-gray-100 p-4 rounded-xl shadow-inner border-l-4 border-gray-500 flex flex-col justify-center transition duration-300 hover:bg-gray-200">
            <p class="text-xs font-bold text-gray-700 mb-1 flex items-center justify-center">
                <i class="fas fa-envelope mr-1 text-gray-600"></i> Contact
            </p>
            <p class="text-xs text-gray-800 truncate font-mono bg-gray-200 px-2 py-1 rounded-md border border-gray-300 mt-1"><?= htmlspecialchars($host_details['email']) ?></p>
        </div>
        
    </div>
    
    <div class="h-px bg-gray-300 my-4"></div>

</div>

<?php
// 3. ഹോസ്റ്റ് ചെയ്ത Project List
$projects_stmt = $conn->prepare("SELECT title, project_category, created_at FROM projects WHERE host_id = ? ORDER BY created_at DESC LIMIT 5");
if ($projects_stmt !== false) {
    $projects_stmt->bind_param("i", $host_id);
    $projects_stmt->execute();
    $projects_result = $projects_stmt->get_result();
    $projects_stmt->close();

    if ($projects_result->num_rows > 0):
?>
<div class="mt-4 pt-4 border-t border-gray-300">
    <h3 class="text-xl font-black text-gray-800 mb-4 flex items-center uppercase tracking-wide">
        <i class="fas fa-project-diagram mr-3 text-indigo-600"></i>
        Recent Projects
    </h3>
    <ul class="space-y-3">
        <?php while($proj = $projects_result->fetch_assoc()): ?>
        <li class="p-4 bg-gray-50 rounded-lg border border-gray-300 shadow-sm flex justify-between items-center transition duration-300 transform hover:shadow-lg hover:border-indigo-400 cursor-pointer">
            <span class="font-semibold text-gray-800"><?= htmlspecialchars($proj['title']) ?></span>
            <span class="text-xs text-white bg-indigo-600 px-3 py-1 rounded-full font-bold shadow-md"><?= htmlspecialchars($proj['project_category']) ?></span>
        </li>
        <?php endwhile; ?>
    </ul>
</div>
<?php endif; 
} 
?>