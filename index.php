<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CollabBuddy - Connect, Collaborate, Create</title>
  <link rel="stylesheet" href="css/style.css"/>
</head>
<body>
  <!-- Navigation -->
  <nav class="navbar">
    <div class="container nav-content">
      <div class="logo">
        <div class="logo-circle">
          <svg class="logo-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
          </svg>
        </div>
        <h1>CollabBuddy</h1>
      </div>

      <ul class="nav-links">
        <li><a href="#features">Features</a></li>
        <li><a href="#how-it-works">How It Works</a></li>
        <li><a href="#contact">Contact</a></li>
      </ul>

      <div class="nav-actions">
        <!-- Sign In Dropdown -->
        <div class="dropdown">
          <button class="btn-link">Sign In ▾</button>
          <div class="dropdown-menu">
            <a href="login.php?role=host">🔑 Sign In as Host</a>
            <a href="login.php?role=participant">👤 Sign In as Participant</a>
          </div>
        </div>
        <!-- Get Started Button -->
        <button class="btn-primary" >Get Started</button>
      </div>
      
      
    </div>
  </nav>

  <!-- Hero -->
  <section class="hero">
    <div class="container hero-inner">
      <h1 class="hero-title">
        Connect, Collaborate,<br/>
        <span class="highlight">Create Together</span>
      </h1>
      <p class="hero-subtitle">
        The ultimate platform for building dream teams, managing projects, and turning ideas into reality through real-time collaboration
      </p>

      <div class="cta-cards">
        <div class="card card-hover glass">
          <div class="round grad-pink"><svg class="ico" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg></div>
          <h3>I'm a Host</h3>
          <p>Lead projects, build teams, and bring your vision to life</p>
          <button class="btn-lg grad-pink">Start Hosting</button>
        </div>

        <div class="card card-hover glass">
          <div class="round grad-indigo"><svg class="ico" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg></div>
          <h3>I'm a Participant</h3>
          <p>Join exciting projects, learn new skills, and collaborate</p>
          <button class="btn-lg grad-indigo">Join Projects</button>
        </div>
      </div>

      <div class="stats">
        <div><span>10K+</span><p>Active Users</p></div>
        <div><span>2.5K+</span><p>Projects Completed</p></div>
        <div><span>500+</span><p>Teams Formed</p></div>
        <div><span>98%</span><p>Success Rate</p></div>
      </div>
    </div>
  </section>

  <!-- Features Section -->
  <section id="features" class="section section-light">
    <div class="container">
      <div class="section-head center">
        <h2>Why Choose CollabBuddy?</h2>
        <p class="lead">Everything you need to build successful teams and complete amazing projects</p>
      </div>

      <div class="grid g-3 g-md-3">
        <!-- 1 -->
        <div class="feature-card card-hover">
          <div class="icon-box bg-blue-100">
            <svg class="icon text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
          </div>
          <h3>Real-Time Chat</h3>
          <p>Connect instantly with team members and potential collaborators through our integrated chat system</p>
        </div>
        <!-- 2 -->
        <div class="feature-card card-hover">
          <div class="icon-box bg-green-100">
            <svg class="icon text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
          </div>
          <h3>Smart Matching</h3>
          <p>Our system matches you with the perfect team members based on skills and project needs</p>
        </div>
        <!-- 3 -->
        <div class="feature-card card-hover">
          <div class="icon-box bg-purple-100">
            <svg class="icon text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
          </div>
          <h3>Project Management</h3>
          <p>Built-in tools to track progress, manage deadlines, and keep your team organized and productive</p>
        </div>
        <!-- 4 -->
        <div class="feature-card card-hover">
          <div class="icon-box bg-yellow-100">
            <svg class="icon text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
          </div>
          <h3>Skill Development</h3>
          <p>Learn from experienced mentors and grow your skills while working on real-world projects</p>
        </div>
        <!-- 5 -->
        <div class="feature-card card-hover">
          <div class="icon-box bg-red-100">
            <svg class="icon text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
          </div>
          <h3>Secure Platform</h3>
          <p>Your projects and data are protected with enterprise-grade security and privacy controls</p>
        </div>
        <!-- 6 -->
        <div class="feature-card card-hover">
          <div class="icon-box bg-indigo-100">
            <svg class="icon text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
          </div>
          <h3>Lightning Fast</h3>
          <p>Built for speed and efficiency, so you can focus on what matters most - creating amazing projects</p>
        </div>
      </div>
    </div>
  </section>

  <!-- How It Works -->
  <section id="how-it-works" class="section">
    <div class="container">
      <div class="section-head center">
        <h2>How CollabBuddy Works</h2>
        <p class="lead">Get started in minutes and build your dream team today</p>
      </div>

      <div class="grid g-2 align-center">
        <!-- Hosts -->
        <div>
          <h3 class="center m-b-32">For Project Hosts</h3>
          <div class="steps">
            <div class="step">
              <div class="badge pink">1</div>
              <div>
                <h4>Post Your Project</h4>
                <p>Describe your project, required skills, and timeline</p>
              </div>
            </div>
            <div class="step">
              <div class="badge pink">2</div>
              <div>
                <h4>Review Applications</h4>
                <p>Browse profiles and chat with potential team members</p>
              </div>
            </div>
            <div class="step">
              <div class="badge pink">3</div>
              <div>
                <h4>Build Your Team</h4>
                <p>Select the best candidates and start collaborating</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Participants -->
        <div>
          <h3 class="center m-b-32">For Participants</h3>
          <div class="steps">
            <div class="step">
              <div class="badge indigo">1</div>
              <div>
                <h4>Browse Projects</h4>
                <p>Discover exciting projects that match your skills</p>
              </div>
            </div>
            <div class="step">
              <div class="badge indigo">2</div>
              <div>
                <h4>Apply & Chat</h4>
                <p>Submit applications and chat with project hosts</p>
              </div>
            </div>
            <div class="step">
              <div class="badge indigo">3</div>
              <div>
                <h4>Join Teams</h4>
                <p>Get selected and become part of amazing teams</p>
              </div>
            </div>
            <div class="step">
              <div class="badge indigo">4</div>
              <div>
                <h4>Learn & Grow</h4>
                <p>Develop skills while working on real projects</p>
              </div>
            </div>
          </div>
        </div>
      </div>      
    </div>
  </section>
<!-- CTA Section -->
<section class="section cta gradient-bg">
    <div class="container center">
      <h2 class="cta-title">Ready to Start Your Journey?</h2>
      <p class="cta-subtitle">
        Join thousands of creators, developers, and innovators building the future together
      </p>
  
      <div class="cta-actions">
        <button class="btn-xl grad-pink">🚀 Start Hosting Projects</button>
        <button class="btn-xl btn-white">⚡ Join as Participant</button>
      </div>
    </div>
  </section>
  

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
    // Smooth scrolling
    document.querySelectorAll('a[href^="#"]').forEach(a=>{
      a.addEventListener('click',e=>{
        e.preventDefault();
        const t=document.querySelector(a.getAttribute('href'));
        if(t){ t.scrollIntoView({behavior:'smooth',block:'start'}); }
      });
    });

    // Sticky nav blur on scroll
    window.addEventListener('scroll',()=>{
      const nav=document.querySelector('.navbar');
      if(window.scrollY>100){ nav.classList.add('nav-scrolled'); }
      else{ nav.classList.remove('nav-scrolled'); }
    });

  

    // Stagger animation for cards
    document.querySelectorAll('.card-hover').forEach((el,i)=>{
      el.style.animationDelay=`${i*0.2}s`;
    });

  </script>


</body>
</html>
