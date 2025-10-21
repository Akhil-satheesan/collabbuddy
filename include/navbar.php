<!-- header.php -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CollabBuddy</title>
  <link rel="stylesheet" href="css/style.css"/>
  <style>
    .logo a {
  text-decoration: none;   
  color: inherit;          /
}

.logo a:hover {
  color: inherit;          
  text-decoration: none;   
}

  </style>
</head>
<body>
<?php
$current_page = basename($_SERVER['PHP_SELF']); // gets current file name like "signup.php"
?>
  <!-- Navigation -->
  <nav class="navbar">
    <div class="container nav-content">
      <div class="logo">
      <div class="logo-circle">
    <svg class="logo-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2
           c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0
           015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857
           m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0
           3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2
           2 0 11-4 0 2 2 0 014 0z"/>
    </svg>
  </div>
        <?php if ($current_page !== 'index.php'): ?>
            <a href="index.php"><h1>CollabBuddy</h1></a>
            <?php else: ?>
              <h1>CollabBuddy</h1>
            <?php endif; ?>
      </div>


      <div class="nav-actions">
        <div class="dropdown">
          <button class="btn-link">Sign In  as▾</button>
          <div class="dropdown-menu">
            <a href="login.php?role=host">🔑 Host</a>
            <a href="login.php?role=participant">👤 Participant</a>
          </div>
        </div>
        <button class="btn-primary">Get Started</button>
      </div>
    </div>
  </nav>
</body>
</html>