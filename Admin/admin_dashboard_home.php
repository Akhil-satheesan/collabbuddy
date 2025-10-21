<?php
// FILE: admin_dashboard_home.php
// ഇത് admin_dashboard.php-യിൽ ഉൾപ്പെടുത്തുന്ന ഫയലാണ്, അതിനാൽ സെഷനും കണക്ഷനും അവിടെ ചെക്ക് ചെയ്തിട്ടുണ്ട്.
// $user_count, $project_count, $pending_requests, $reported_chats എന്നിവ main ഫയലിൽ നിന്ന് ലഭ്യമാകും.

// സെഷൻ ചെക്ക് വീണ്ടും ആവശ്യമില്ല, പക്ഷേ സുരക്ഷക്കായി ചേർക്കാം
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_logged_in'])) return; 
?>

<h2 class="text-3xl font-bold text-gray-800 mb-6">System Overview</h2>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
    
    <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-indigo-100 text-indigo-600 mr-4">
                <i class="fas fa-users text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500">Total Users</p>
                <p class="text-2xl font-bold text-gray-900"><?= number_format($user_count) ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                <i class="fas fa-cogs text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500">Active Projects</p>
                <p class="text-2xl font-bold text-gray-900"><?= number_format($project_count) ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4">
                <i class="fas fa-hourglass-half text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500">Pending Requests</p>
                <p class="text-2xl font-bold text-gray-900"><?= number_format($pending_requests) ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-red-100 text-red-600 mr-4">
                <i class="fas fa-gavel text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500">Reports to Review</p>
                <p class="text-2xl font-bold text-gray-900"><?= number_format($reported_chats) ?></p>
            </div>
        </div>
    </div>

</div>

<h3 class="text-xl font-semibold text-gray-800 mt-10 mb-4">Quick Actions</h3>
<div class="flex space-x-4">
    <a href="?section=manage_users" class="px-4 py-2 bg-indigo-600 text-white rounded-lg shadow-md hover:bg-indigo-700 transition">
        Manage Users
    </a>
    <a href="?section=review_reports" class="px-4 py-2 bg-red-600 text-white rounded-lg shadow-md hover:bg-red-700 transition">
        Review Reports (<?= $reported_chats ?>)
    </a>
</div>