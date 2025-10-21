<div class="w-64 bg-white border-r border-gray-200 flex flex-col">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900 mb-2">Welcome, <?= $_SESSION['name']; ?>!</h2>
        <div class="text-sm text-gray-600">Role: Participant</div>
    </div>
    <div class="flex-1 overflow-y-auto">
        <nav class="p-4 space-y-2">
            <?php
            $menu = [
                'dashboard'=>'Dashboard',
                'browse_projects'=>'Browse Projects',
                'my_applications'=>'My Applications',
                'joined_projects'=>'Joined Projects',
                'bookmarks'=>'Bookmarked',
                'chat'=>'Messages',
                'profile'=>'My Profile',
                'analytics'=>'My Stats'
            ];
            $section = $_GET['section'] ?? 'dashboard';
            foreach($menu as $key => $title){
                $active = ($section === $key) ? 'bg-blue-500 text-white' : 'hover:bg-gray-100';
                echo "<a href='participate_dashboard.php?section=$key' class='block px-4 py-2 rounded-lg $active'>$title</a>";
            }
            ?>
        </nav>
    </div>
</div>
