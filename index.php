<?php include 'database.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorkMuna - Homepage</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>



<body class>
    <!-- ======= Header ======= -->
<header>
    <div class="navbar top">
            <div class="nav-left">
        <a href="#" class="logo">
            <img src="images/workmunalogo2-removebg.png" alt="WorkMuna Logo">
        </a>

        <ul class="nav-links">
            <li><a href="#">Home </a></li>
            <!-- <li><a href="applicant/jobs.php">Jobs </a></li> -->
            <li><a href="applicant/aboutus.php">About Us</a></li>
            <!-- <li><a href="#profile">Profile</a></li> -->
            <li><a href="#top-employers">Explore Company</a></li>
            <li><a href="/WORKSAD/employer/index.php">Employer Site</a></li>
        </ul>
            </div>

        <div class="nav-right">
            <a href="applicant/login.php" class="btn-log-in">Log In</a>
            <a href="applicant/register.php" class="btn-sign-up">Sign Up Free</a>
        </div>


          <!-- Hamburger icon -->
        <div class="menu-toggle" id="menu-toggle">☰</div>
    </div>

   

    <!-- Sidebar mobile only -->
    <aside class="sidebar" id="sidebar">
    <!-- Close Button -->
    <button class="close-btn" id="closeSidebar">&times;</button>
        <ul class="mobnav-links">
            <li><a href="/WORKSAD/index.php">Home</a></li>
            <li><a href="/WORKSAD/applicant/aboutus.php">About Us</a></li>
            <li><a href="#top-employers">Explore Company</a></li>
            <li><a href="/WORKSAD/applicant/register.php">Register</a></li>
            <li><a href="/WORKSAD/applicant/login.php">Login</a></li>
            <li><a href="/WORKSAD/employer/index.php">Employer Site</a></li>
        </ul>
    </aside>

    <div class="overlay" id="overlay"></div>
</header>
    <!-- End Header -->




<!-- ======= Hero Section ======= -->
<section class="hero-section">
  <div class="hero-overlay"></div>

  <div class="hero-content">
    <h1>Connecting Bayambang Talent with Opportunities</h1>
    <p>Discover local opportunities that match your skills and passion. WorkMuna connects you directly with employers in your community.</p>

    <div class="hero-buttons">
      <a href="applicant/register.php" class="btn btn-primary">Start Searching Jobs</a>
      <a href="applicant/register.php" class="btn btn-outline">Create Your Profile</a>
    </div>

    <!-- Search Bar -->
    <!-- <div class="search-box">
      <input type="text" placeholder="Enter keywords (e.g., teacher, driver)" class="search-input">
      <select class="search-select">
        <option value="">Category</option>
        <option value="IT">IT</option>
        <option value="healthcare">Healthcare</option>
        <option value="education">Education</option>
        <option value="services">Services</option>
      </select>
      <button class="btn-search">Search  <i class="fa-solid fa-magnifying-glass"></i></button>
    </div> -->
  </div>
</section>
<!--End Hero Section -->





    <!-- ======= How It Works Section ======= --> 
<section id="how-it-works" class="how-it-works">

  <div class="container">
        <h2>How It Works</h2>
         <p class="subtitle">Finding your dream job is just a few steps away</p>  

    <div class="steps">
            <div class="step">
                <div class="icon swipe"><i class="fa-solid fa-hand-pointer"></i></div>
                <h3>Swipe</h3>
                <p>Browse through employers looking for talent. Swipe right to show interest or explore other opportunities.</p>
            </div>

        <div class="step">
            <div class="icon match"><i class="fa-solid fa-heart"></i></div>
            <h3>Match</h3>
            <p>When an employer swipes right on you too, it's a match! You can now connect and start chatting instantly.</p>
        </div>

        
        <div class="step">
            <div class="icon work"><i class="fa-solid fa-folder-open"></i></div>
            <h3>Work</h3>
            <p>Finalize the details, start your new job, and take the next step in your career with WorkMuna.</p>
        </div>

    </div>
  </div>
</section>
<!-- End How It Works Section -->







    <!-- ======= Top Employers Section ======= --> 
<!-- 
<section id="top-employers" class="top-employers">
  <div class="container">
        <h2>Top Employers in Bayambang</h2>
        <p class="subtitle">Discover the most active employers hiring in Bayambang</p>


    <div class="employers-wrapper">

      <button class="scroll-btn left">&#10094;</button>

        <div class="employers-list">
            <div class="employer-card">
                <img src="images/mcdonalds.jpg" alt="McDonald's">
                <h3>McDonald's</h3>
                <span class="jobs">14 Jobs</span>
            </div>

            <div class="employer-card">
                <img src="images/jollibee.jpg" alt="Jollibee">
                <h3>Jollibee</h3>
                <span class="jobs">10 Jobs</span>
            </div>

            <div class="employer-card">
                <img src="images/csi.jpg" alt="CSI">
                <h3>CSI</h3>
                <span class="jobs">17 Jobs</span>
            </div>

            <div class="employer-card">
                <img src="images/mrdiylogo.png" alt="MR. DIY">
                <h3>MR. DIY</h3>
                <span class="jobs">19 Jobs</span>
            </div>

            <div class="employer-card">
                <img src="images/shell.png" alt="Shell">
                <h3>Shell</h3>
                <span class="jobs">16 Jobs</span>
            </div>

            <div class="employer-card">
                <img src="images/perfectcut.jpg" alt="Perfect Cut">
                <h3>Perfect Cut</h3>
                <span class="jobs">16 Jobs</span>
            </div>

            <div class="employer-card">
                <img src="images/perfectcut.jpg" alt="Perfect Cut">
                <h3>Perfect Cut</h3>
                <span class="jobs">16 Jobs</span>
            </div>

            <div class="employer-card">
                <img src="images/perfectcut.jpg" alt="Perfect Cut">
                <h3>Perfect Cut</h3>
                <span class="jobs">16 Jobs</span>
            </div>

            <div class="employer-card">
                <img src="images/perfectcut.jpg" alt="Perfect Cut">
                <h3>Perfect Cut</h3>
                <span class="jobs">16 Jobs</span>
            </div>

            <div class="employer-card">
                <img src="images/perfectcut.jpg" alt="Perfect Cut">
                <h3>Perfect Cut</h3>
                <span class="jobs">16 Jobs</span>
            </div>

            <div class="employer-card">
                <img src="images/perfectcut.jpg" alt="Perfect Cut">
                <h3>Perfect Cut</h3>
                <span class="jobs">16 Jobs</span>
            </div>

            <div class="employer-card">
                <img src="images/perfectcut.jpg" alt="Perfect Cut">
                <h3>Perfect Cut</h3>
                <span class="jobs">16 Jobs</span>
            </div>

             <div class="employer-card">
                <img src="images/perfectcut.jpg" alt="Perfect Cut">
                <h3>Perfect Cut</h3>
                <span class="jobs">16 Jobs</span>
            </div>

             <div class="employer-card">
                <img src="images/perfectcut.jpg" alt="Perfect Cut">
                <h3>Perfect Cut</h3>
                <span class="jobs">16 Jobs</span>
            </div>

             <div class="employer-card">
                <img src="images/perfectcut.jpg" alt="Perfect Cut">
                <h3>Perfect Cut</h3>
                <span class="jobs">16 Jobs</span>
            </div>


        </div>
      <button class="scroll-btn right">&#10095;</button>
    </div>
        <div class="pagination"></div>
  </div>
</section> -->
<!-- End Top Employers Section -->






<!-- ======= Why Choose WorkMuna Section ======= -->
<section id="why-choose" class="why-choose">
  <div class="container">
    
    <h2>Why Choose WorkMuna?</h2>

    <div class="why-grid">

      <div class="why-card">
        <div class="icon blue"><i class="fa-solid fa-briefcase"></i></div>
        <h3>Easy Job Search</h3>
        <ul>
            <li>• Browse verified job postings from local employers</li>
            <li>• Filter jobs by category, job type, and work setup</li>
            <li>• View detailed job descriptions and requirements</li>
        </ul>
        
      </div>


      <div class="why-card">
        <div class="icon green"><i class="fa-solid fa-id-card"></i></div>
        <h3>Build Your Profile</h3>
        <ul>
            <li>• Build your resume directly on WorkMuna or upload your own</li>
            <li>• Highlight skills, experience, and preferred job types</li>
            <li>• Showcase work experience relevant to local employers</li>
        </ul>
        
      </div>
      

      <div class="why-card">
        <div class="icon yellow"><i class="fa-solid fa-bell"></i></div>
        <h3>Job Alerts & Matches</h3>
        <ul>
            <li>• Receive in-app notifications for likes, matches, and interview updates</li>
            <li>• See matched job opportunities based on your profile</li>
            <li>• Apply to jobs with a single click</li>    
        </ul>
        
      </div>


      <div class="why-card">
        <div class="icon purple"><i class="fa-solid fa-lightbulb"></i></div>
        <h3>Career Resources</h3>
        <ul>
            <li>• Track application status (pending, interview, accepted, rejected)</li>
            <li>• View scheduled interviews and employer details</li>
            <li>• Manage your job applications in one place</li>
        </ul>
        
      </div>
    </div>  

        <p class="why-note">
            <em>Perfect for Bayambanguenos — no commuting to Manila needed!</em>
        </p>
  </div>
</section>
<!-- End Why Choose WorkMuna Section -->








        <!-- ======= Testimony Section ======= --> 
<!-- <section id="testimony" class="testimony">

  <div class="container">
        <h2>What Job Seeker Say</h2>

    <div class="testimonies">

            <div class="testimony-card">
                <div class="stars">★★★★★</div>
                <p>"Taena tinanggal ako sa LA eh kaya ito sideline muna tayo para makasabay sa paglipad ng eroplano, kung di ka naniniwala ekis ka na sakin G kailangan ako ng kalsada matsalab"</p>
                 <div class="user">
                    <img src="images/lebron.jpg" alt="King James">
                    <div class="user-info">
                        <h4>King James</h4>
                        <span>Local Teacher</span>
                    </div>
                </div>
            </div>

            <div class="testimony-card">
                <div class="stars">★★★★★</div>
                <p>"So far so good kaso lang walang taga balance ng kung ano ano sa dila na work pala dito sa iba nalang sguro"</p>
                 <div class="user">
                    <img src="images/oleg.jpg" alt="Dranrev Catalan">
                    <div class="user-info">
                        <h4>Juan Santos</h4>
                        <span>Local Gooner</span>
                    </div>
                </div>
            </div>
            
            <div class="testimony-card">
                <div class="stars">★★★★★</div>
                <p>F"As a fresh graduate, I was worried about finding work locally. WorkMuna connected me with a small business that valued my skills."</p>
                <div class="user">
                    <img src="images/user1.jpg" alt="Juan Santos">
                    <div class="user-info">
                        <h4>Juan Santos</h4>
                        <span>Local Teacher</span>
                    </div>
                </div>
            </div>
    </div>
  </div>
</section> -->
        <!-- Testimony Section -->









    <!-- Footer -->
<footer class="footer">
        <div class="container">
            <div class="footer-content">
            
            <!-- About / Logo -->
            <div class="footer-section about">
                <h2>WorkMuna</h2>
                <p>Your trusted job-matching platform in Bayambang, Pangasinan. 
                Helping jobseeker and employers connect smarter.</p>
            </div>
            
            <!-- Contact Info -->
            <div class="footer-section contact">
                <h3>Contact Us</h3>
                <p>Email: support@workmuna.com</p>
                <p>Phone: +63 912 345 6789</p>
                <p>Location: Bayambang Pangasinan, Philippines</p>
            </div>
            
            <!-- Social Media -->
            <div class="footer-section social">
                <h3>Follow Us</h3>
                <a href="https://www.facebook.com/ihhlla" target="_blank">Facebook</a> | 
                <a href="https://twitter.com" target="_blank">Twitter</a> | 
                <a href="https://www.instagram.com" target="_blank">Instagram</a>
            </div>
            
            <!-- Policies -->
            <div class="footer-section policies">
                <h3>Legal</h3>
                <a href="#">Privacy Policy</a><br>
                <a href="#">Terms of Service</a>
            </div>
            
            </div>
            
            <!-- Bottom line -->
            <div class="footer-bottom">
            <p>&copy; 2025 WorkMuna. All Rights Reserved.</p>
            </div>
        </div>
</footer>












 <!-- ======= Popular Industries ======= -->
<!-- <section id="job-categories" class="job-categories">

    <div class="container">
            <div class="section-title">
                <h2>Popular Industries</h2>
            </div>

        <div class="cards-grid">

            
            <div class="card-wrapper">
                <a href="applicant/jobs.php" class="card" aria-label="Healthcare Jobs" tabindex="0">
                <img src="images/nurse.jpg" alt="Healthcare Jobs">
                </a>
                <div class="caption">Healthcare Jobs</div>
            </div>
                

            
            <div class="card-wrapper">
                <a href="applicant/jobs.php" class="card" aria-label="IT & Digital" tabindex="0">
                <img src="images/IT.jpg" alt="IT & Digital">
                </a>
                <div class="caption">IT & Digital</div>
            </div>
                

            
            <div class="card-wrapper">
                <a href="applicant/jobs.php" class="card" aria-label="Construction & Skilled Worker" tabindex="0">
                <img src="images/construction.jpg" alt="Construction & Skilled Worker">
                </a>
                <div class="caption">Construction & Skilled Worker</div>
            </div>
                

             
            <div class="card-wrapper">
                <a href="applicant/jobs.php" class="card" aria-label="Education" tabindex="0">
                <img src="images/teacher.jpg" alt="Education">
                </a>
                <div class="caption">Education</div>
            </div>
                
        </div>
  </div>
</section> -->
    <!-- End Popular Industries  -->


    <!-- links script.js --> 
    <script src="script.js"></script>


</body>
</html>


