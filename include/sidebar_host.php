<div class="w-64 bg-white border-r border-gray-200 flex flex-col">
   
    <!-- Sidebar Navigation -->
    <div class="flex h-screen">
  <!-- Sidebar -->
  <aside class="w-80 bg-white border-r border-gray-200 flex flex-col">
    <!-- User Info -->
    <div class="p-6 border-b">
      <h2 class="text-xl font-bold text-gray-900 mb-1">
        <?= htmlspecialchars($name); ?>
      </h2>
      <div class="flex items-center space-x-2 text-sm">
        <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">🟢 Online</span>
        <span class="text-gray-600"><?= htmlspecialchars(ucfirst($role)); ?></span>
      </div>
    </div>

    <!-- Logo -->
    <div class="p-4 border-b">
      <h2 class="text-2xl font-bold text-indigo-600">CollabBuddy</h2>
    </div>

    <!-- Navigation -->
    <div class="flex-1 overflow-y-auto bg-white">
      <nav class="p-4 space-y-2">
        <?php
        // Define menu items
        $menu = [
          'dashboard' => [
            'icon' => '📊',
            'title' => 'Dashboard',
            'desc'  => 'Overview & stats'
          ],
          'projects' => [
            'icon' => '📂',
            'title' => 'My Projects',
            'desc'  => 'Manage posted projects',
            'badge' => ''
          ],
          'tasks' => [
            'icon' => '✅',
            'title' => 'Recent Tasks',
            'desc'  => 'Track progress',
            'badge' => ''
          ],
          'requests' => [
            'icon' => '⏳',
            'title' => 'Pending Requests',
            'desc'  => 'Applications & invites',
            'badge' => '' // dynamically fill with count if needed
          ],
          'chat' => [
            'icon' => '💬',
            'title' => 'Project Chat',
            'desc'  => 'Team communication',
            'badge' => 3
          ],
          'team' => [
            'icon' => '👥',
            'title' => 'Team Members',
            'desc'  => 'Manage your team'
          ],
          'reports' => [
            'icon' => '📈',
            'title' => 'Reports',
            'desc'  => 'Analytics & insights'
          ]
        ];

        $section = $_GET['section'] ?? 'dashboard';
        foreach ($menu as $key => $item) {
            $isActive = $section === $key ? 'bg-blue-50 border border-blue-200' : 'hover:bg-gray-100';
            $badge = '';
        
            if (isset($item['badge'])) {
                if ($item['badge'] !== '') {
                    $badge = "<span class='bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs notification-badge'>{$item['badge']}</span>";
                } else {
                    $badge = "<span class='bg-gray-100 text-gray-600 px-2 py-1 rounded-full text-xs'>&nbsp;</span>";
                }
            }
        
            echo "
                <div class='nav-item w-full flex items-center space-x-3 p-3 rounded-lg transition-colors text-left $isActive' 
                     data-section='$key'>
                    <span class='text-xl'>{$item['icon']}</span>
                    <div class='flex-1'>
                        <p class='font-medium text-gray-900'>{$item['title']}</p>
                        <p class='text-sm text-gray-600'>{$item['desc']}</p>
                    </div>
                    $badge
                </div>
            ";
        }
        
        ?>
      </nav>
    </div>
  </aside>
</div>

</div>
