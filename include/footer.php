<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CollabBuddy</title>
  <link rel="stylesheet" href="css/style.css"/>
</head>
<body>
  <!-- Footer -->
  <footer id="contact" class="footer">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-brand">
          <div class="brand-row">
            <div class="logo-circle m-r-12">
              <svg class="logo-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
              </svg>
            </div>
            <h3>CollabBuddy</h3>
          </div>
          <p class="muted max-w">Connecting creators, developers, and innovators to build amazing projects together. Join the collaboration revolution today.</p>
          <div class="socials">
            <a href="#" class="social">
              <svg viewBox="0 0 24 24"><path d="M22.675 0H1.325C.593 0 0 .593 0 1.325v21.351C0 23.407.593 24 1.325 
                24h11.495v-9.294H9.691v-3.622h3.129V8.413c0-3.1 
                1.893-4.788 4.659-4.788 1.325 0 
                2.464.099 2.796.143v3.24l-1.918.001c-1.504 
                0-1.795.715-1.795 1.763v2.313h3.587l-.467 
                3.622h-3.12V24h6.116C23.407 24 24 23.407 
                24 22.676V1.325C24 .593 23.407 0 22.675 0z
                "/></svg>
            </a>
            <a href="#" class="social">
              <svg viewBox="0 0 24 24"><path d="M13.172 10.577 21.173 2h-1.885l-7.052 7.604L6.84 2H2l8.375 11.723L2 22h1.885l7.495-8.083L17.16 22H22l-8.828-11.423Z"/></svg>
            </a>
            
            <a href="#" class="social">
              <svg viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
            </a>
          </div>
        </div>

        <div class="footer-col">
          <h4>Platform</h4>
          <ul>
            <li><a href="#">For Hosts</a></li>
            <li><a href="#">For Participants</a></li>
            <li><a href="#">Features</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4>Support</h4>
          <ul>
            <li><a href="#">Help Center</a></li>
            <li><a href="#">Contact Us</a></li>
            <li><a href="#">Privacy Policy</a></li>
            <li><a href="#">Terms of Service</a></li>
          </ul>
        </div>
      </div>

      <div class="footer-bottom">
        <p>&copy; 2025 CollabBuddy. All rights reserved. Built with ❤️ for creators worldwide.</p>
      </div>
    </div>
  </footer>

  <!-- Scripts -->
  <script>
    document.querySelectorAll('a[href^="#"]').forEach(a=>{
      a.addEventListener('click',e=>{
        e.preventDefault();
        const t=document.querySelector(a.getAttribute('href'));
        if(t){ t.scrollIntoView({behavior:'smooth'}); }
      });
    });

    window.addEventListener('scroll',()=>{
      const nav=document.querySelector('.navbar');
      if(window.scrollY>100){ nav.classList.add('nav-scrolled'); }
      else{ nav.classList.remove('nav-scrolled'); }
    });
  </script>
</body>
</html>
