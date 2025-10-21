<?php
// FILE: include/participant_sections/my_applications.php (Example location)

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) session_start();

// Ensure path to config.php is correct
require __DIR__ . '/../../include/config.php'; 

$conn = get_db_connection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'participant') {
    die("Unauthorized access. Please log in as a participant.");
}

$participant_id = $_SESSION['user_id'];

// SQL Query to fetch all applications along with related project and host details
$sql = "SELECT a.*, p.title AS project_title, p.description, u.name AS host_name 
        FROM applications a
        JOIN projects p ON a.project_id = p.project_id
        JOIN users u ON p.host_id = u.user_id
        WHERE a.participant_id = ?
        ORDER BY a.applied_at DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("i", $participant_id);
$stmt->execute();
$result = $stmt->get_result();

$all_applications = [];
$status_counts = ['All' => 0, 'Pending' => 0, 'Accepted' => 0, 'Rejected' => 0, 'Withdrawn' => 0];

while ($row = $result->fetch_assoc()) {
    $all_applications[] = $row;
    $status = $row['status'];
    $status_counts['All']++;
    if (isset($status_counts[$status])) {
        $status_counts[$status]++;
    }
}
$stmt->close();


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Applications - Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
    body { box-sizing: border-box; background: linear-gradient(135deg,#f8fafc 0%,#e2e8f0 100%); }
    .application-card { transition: all 0.3s cubic-bezier(0.4,0,0.2,1); background: linear-gradient(145deg,#ffffff 0%,#f8fafc 100%); }
    .application-card:hover { transform: translateY(-4px) scale(1.01); box-shadow: 0 20px 40px rgba(0,0,0,0.1), 0 0 0 1px rgba(59,130,246,0.1); }
    .status-badge { display: inline-flex; align-items: center; gap:0.5rem; box-shadow:0 2px 4px rgba(0,0,0,0.1); }
    .status-dot { width:8px;height:8px;border-radius:50%;animation:pulse 2s infinite; }
    @keyframes pulse { 0%,100%{opacity:1;}50%{opacity:0.7;} }
    .gradient-header { background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); }
    .card-shadow { box-shadow:0 10px 25px rgba(0,0,0,0.1),0 4px 10px rgba(0,0,0,0.05); }
    .btn-primary{ background:linear-gradient(135deg,#3b82f6 0%,#1d4ed8 100%); box-shadow:0 4px 14px rgba(59,130,246,0.3); color: white; }
    .btn-primary:hover{ background:linear-gradient(135deg,#2563eb 0%,#1e40af 100%); box-shadow:0 6px 20px rgba(59,130,246,0.4); transform:translateY(-1px); }
    .btn-success{ background:linear-gradient(135deg,#10b981 0%,#059669 100%); box-shadow:0 4px 14px rgba(16,185,129,0.3); color: white; }
    .btn-success:hover{ background:linear-gradient(135deg,#059669 0%,#047857 100%); box-shadow:0 6px 20px rgba(16,185,129,0.4); transform:translateY(-1px); }
    .btn-danger{ background:linear-gradient(135deg,#ef4444 0%,#dc2626 100%); box-shadow:0 4px 14px rgba(239,68,68,0.3); color: white; }
    .btn-danger:hover{ background:linear-gradient(135deg,#dc2626 0%,#b91c1c 100%); box-shadow:0 6px 20px rgba(239,68,68,0.4); transform:translateY(-1px); }
    .content-section{ background: linear-gradient(135deg,rgba(255,255,255,0.1) 0%,rgba(255,255,255,0.05) 100%); }
    .icon-container{ background: linear-gradient(135deg,#ddd6fe 0%,#c4b5fd 100%); }
    .slide-in{ animation: slideIn 0.6s ease-out forwards; }
    @keyframes slideIn{ from{opacity:0;transform:translateX(-30px);} to{opacity:1;transform:translateX(0);} }
    
    /* Style for the active filter button */
    .filter-btn { padding: 0.5rem 1rem; border-radius: 9999px; font-weight: 500; transition: all 0.2s ease-in-out; }
    .filter-btn.active {
        background-color: #4f46e5; /* indigo-600 */
        color: white;
        box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);
        transform: translateY(-1px);
    }
</style>
</head>
<body class="bg-gray-50 min-h-screen">

<div id="applications-content" class="content-section p-6">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-xl card-shadow border border-gray-100 mb-8 overflow-hidden">
            <div class="gradient-header p-6 text-white">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="w-16 h-16 icon-container rounded-xl flex items-center justify-center shadow-lg">
                            <span class="text-3xl">📋</span>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-white mb-1">My Applications</h3>
                            <p class="text-purple-100 text-base">Track and manage your project applications</p>
                        </div>
                    </div>
                    <span class="bg-white/20 backdrop-blur-sm text-white px-3 py-2 rounded-full text-sm font-medium shadow-lg">🟢 Active Dashboard</span>
                </div>
            </div>

            <div class="p-6 border-b border-gray-100 bg-gray-50">
                <h4 class="text-lg font-semibold text-gray-700 mb-3">Filter Applications by Status:</h4>
                <div id="filter-container" class="flex space-x-3 overflow-x-auto pb-2">
                    <button data-filter="all" class="filter-btn active bg-indigo-50 text-indigo-700 hover:bg-indigo-600" id="filter-all">All (<?= $status_counts['All'] ?>)</button>
                    <button data-filter="Pending" class="filter-btn bg-gray-200 text-gray-700 hover:bg-indigo-600">⏳ Pending (<?= $status_counts['Pending'] ?>)</button>
                    <button data-filter="Accepted" class="filter-btn bg-gray-200 text-gray-700 hover:bg-indigo-600">✅ Accepted (<?= $status_counts['Accepted'] ?>)</button>
                    <button data-filter="Rejected" class="filter-btn bg-gray-200 text-gray-700 hover:bg-indigo-600">❌ Rejected (<?= $status_counts['Rejected'] ?>)</button>
                    <button data-filter="Withdrawn" class="filter-btn bg-gray-200 text-gray-700 hover:bg-indigo-600">🛑 Withdrawn (<?= $status_counts['Withdrawn'] ?>)</button>
                </div>
            </div>
            <div id="application-list" class="p-6 space-y-6">
            <?php if (!empty($all_applications)): ?>
                <?php $index=0; foreach($all_applications as $row): ?>
                <?php 
                    $status = $row['status'];
                    $statusConfig = [
                        "Pending" => ["bg"=>"bg-blue-100","text"=>"text-blue-800","dot"=>"bg-blue-500", "icon"=>"⏳"],
                        "Accepted"=> ["bg"=>"bg-green-100","text"=>"text-green-800","dot"=>"bg-green-500", "icon"=>"✅"],
                        "Rejected"=> ["bg"=>"bg-red-100","text"=>"text-red-800","dot"=>"bg-red-500", "icon"=>"❌"],
                        "Withdrawn"=> ["bg"=>"bg-gray-200","text"=>"text-gray-700","dot"=>"bg-gray-500", "icon"=>"🛑"],
                    ];
                    $config = $statusConfig[$status] ?? $statusConfig["Pending"];
                    $project_id = (int)$row['project_id'];
                    $application_id = (int)$row['application_id'];
                ?>
                <div class="application-card card-shadow border border-gray-100 rounded-xl overflow-hidden slide-in status-<?= strtolower($status) ?>" data-status="<?= $status ?>" style="animation-delay: <?= $index*0.1 ?>s">
                    <div class="flex items-start justify-between mb-6 p-6 bg-gradient-to-r from-gray-50 to-gray-100">
                        <div class="flex-1">
                            <div class="flex items-center space-x-4 mb-3">
                                <h4 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($row['project_title']) ?></h4>
                                <span class="status-badge <?= $config['bg'] ?> <?= $config['text'] ?> px-3 py-2 rounded-full text-sm font-medium status-text">
                                    <span class="status-dot <?= $config['dot'] ?>"></span><?= $config['icon'] ?> <span class="status-display-text"><?= $status ?: 'Pending' ?></span>
                                </span>
                            </div>
                            <div class="flex items-center space-x-6 text-base text-gray-600">
                                <span class="flex items-center space-x-2 bg-gray-50 px-3 py-2 rounded-lg">
                                    <span class="text-lg">👤</span><span class="font-medium"><?= htmlspecialchars($row['host_name']) ?></span>
                                </span>
                                <span class="flex items-center space-x-2 bg-gray-50 px-3 py-2 rounded-lg">
                                    <span class="text-lg">📅</span><span><?= date("M d, Y",strtotime($row['applied_at'])) ?></span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-6 border border-blue-100 shadow-sm mx-6 mb-6">
                        <div class="flex items-start space-x-4">
                            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                                <span class="text-xl">💌</span>
                            </div>
                            <div class="flex-1">
                                <h5 class="font-bold text-gray-900 mb-2 text-lg">Cover Message</h5>
                                <p class="text-gray-700 leading-relaxed text-base line-clamp-3">"<?= htmlspecialchars($row['cover_message']) ?>"</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl p-6 border border-purple-100 shadow-sm mx-6 mb-6">
                        <div class="flex items-start space-x-4">
                            <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                                <span class="text-xl">📖</span>
                            </div>
                            <div class="flex-1">
                                <h5 class="font-bold text-gray-900 mb-2 text-lg">Project Description</h5>
                                <p class="text-gray-700 leading-relaxed text-base line-clamp-3"><?= htmlspecialchars($row['description']) ?></p>
                            </div>
                        </div>
                    </div>


                    <div class="flex items-center space-x-4 pt-6 border-t border-gray-100 p-6">
    <div class="flex items-center space-x-4 action-buttons-container" data-project-id="<?= (int)$row['project_id'] ?>">
    
    <?php if($status==='Pending'): ?>
        <button class="withdraw-btn btn-danger text-white px-4 py-2 rounded-lg font-semibold transition duration-150" data-application-id="<?= (int)$row['application_id'] ?>">🛑 Withdraw Application</button>
    <?php endif; ?>
   
    
    </div>
</div>

                </div>
                <?php $index++; endforeach; ?>
            <?php else: ?>
                <div class="text-center py-16 text-gray-600 bg-gray-50 rounded-lg border border-gray-100">
                    <span class="text-4xl mb-4 block">😔</span>
                    <p class="text-lg">You haven't applied for any projects yet.</p>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    
   
    $('.filter-btn').on('click', function() {
        const filterStatus = $(this).data('filter');
        
        // Active class അപ്ഡേറ്റ് ചെയ്യുന്നു
        $('.filter-btn').removeClass('active');
        $(this).addClass('active');
        
        
        $('.application-card').hide().removeClass('slide-in');
        
        if (filterStatus === 'all') {
            $('.application-card').show().addClass('slide-in');
        } else {
            // data-status 
            $(`.application-card[data-status="${filterStatus}"]`).show().addClass('slide-in');
        }
        
       
        let delay = 0;
        $('.application-card:visible').each(function() {
            $(this).css('animation-delay', (delay * 0.1) + 's');
            delay++;
        });
    });

  
    $(document).on('click', '.withdraw-btn', function(){
        let appId = $(this).data('application-id');
        const $card = $(this).closest('.application-card');

        if(!appId){ 
            Swal.fire('Error','Invalid application ID','error'); 
            return; 
        }

        Swal.fire({
            title: 'Are you sure?',
            text: "Do you want to withdraw this application?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Yes, withdraw it!',
            cancelButtonText: 'Cancel',
        }).then((result)=>{
            if(result.isConfirmed){
                $.ajax({
                    
                    url:'include/participant_sections/withdraw_application.php', 
                    type:'POST',
                    data:{application_id:appId},
                    dataType:'json',
                    success:function(res){
                        if(res.success){
                            Swal.fire('Withdrawn!',res.message,'success');
                            
                            const newConfig = { "bg":"bg-gray-200","text":"text-gray-700","dot":"bg-gray-500", "icon":"🛑" };
                            const $badge = $card.find('.status-badge');
                            
                            $badge.removeClass('bg-blue-100 bg-green-100 bg-red-100 text-blue-800 text-green-800 text-red-800');
                            $badge.find('.status-dot').removeClass('bg-blue-500 bg-green-500 bg-red-500').addClass(newConfig.dot);
                            $badge.addClass(`${newConfig.bg} ${newConfig.text}`);
                            $badge.html(`<span class="status-dot ${newConfig.dot}"></span>${newConfig.icon} Withdrawn`);
                            
                            $card.attr('data-status', 'Withdrawn');
                            $card.removeClass('status-pending').addClass('status-withdrawn');
                            
                       
                            const projectId = $card.find('.action-buttons-container').data('projectId');
                      
                            const reapplyUrl = `/collabuddy/include/participant_sections/apply_project.php?project_id=${projectId}&reapply=true`;
                            const viewDetailsUrl = `/collabuddy/include/participant_sections/project_details.php?id=${projectId}&app_id=${appId}`;
                            
                            $card.find('.action-buttons-container').empty().html(
                                `<a href="${reapplyUrl}" class="btn-primary px-4 py-2 rounded-lg font-semibold transition duration-150">🔄 Reapply</a>
                                <a href="${viewDetailsUrl}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-150">View Details</a>`
                            );
                            
                            const currentFilter = $('.filter-btn.active').data('filter');
                            if (currentFilter === 'Pending') {
                                $card.fadeOut(300);
                            }
                            
                        } else {
                            Swal.fire('Error',res.message,'error');
                        }
                    },
                    error:function(xhr){
                        Swal.fire('Error','Failed to communicate with server','error');
                        console.error("AJAX Error Response:", xhr.responseText);
                    }
                });
            }
        });
    });
});
</script>
</body>
</html>