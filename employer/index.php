<?php include '../database.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorkMuna - Homepage</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <!-- ======= Header ======= -->
<header>
    <div class="navbar top">
            <div class="nav-left">
        <a href="#" class="logo">
            <img src="../images/workmunalogo2-removebg.png" alt="WorkMuna Logo">
        </a>

        <ul class="nav-links">
            <li><a href="#">Home </a></li>
            <li><a href="#post-job-section">Post A Job</a></li>
            <li><a href="../applicant/aboutus.php">About Us</a></li>
           
            <li><a href="#find-talents-section">Find Talents</a></li>
            <li><a href="/WORKSAD/index.php">Job Seeker</a></li>
        </ul>
            </div>

        <div class="nav-right">
            <a href="../employercontent/login.php" class="btn-log-in">Log In</a>
            <a href="../employercontent/register.php" class="btn-sign-up">Sign Up Free</a>
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
            <li><a href="/WORKSAD/applicant/jobs.php">Jobs</a></li>
            <li><a href="/WORKSAD/applicant/profile.php">Profile</a></li>
            <li><a href="/WORKSAD/applicant/company.php">Explore Company</a></li>
            <li><a href="/WORKSAD/applicant/register.php">Register</a></li>
            <li><a href="/WORKSAD/employercontent/login.php">Login</a></li>
            <li><a href="/WORKSAD/index.php">Job Seeker</a></li>
        </ul>
    </aside>

    <div class="overlay" id="overlay"></div>
</header>
    <!-- End Header -->



<!-- ======= Hero Section ======= -->
<section class="employer-hero">
  <div class="hero-container">

    <!-- Left Content -->
    <div class="hero-text">
      <h1>Hire the Best Talent Faster with WorkMuna</h1>
      <p>Access a verified pool of 500K+ skilled professionals. Post jobs, and track hires—all in one intuitive platform. No more endless scrolling through unqualified resumes.</p>
      
      <div class="hero-buttons">
        <a href="#" class="btn-primary">Post Your First Job Free</a>
       
      </div>
    </div>

        <!-- Right Image -->
    <div class="hero-image">
      <img src="../images/employerhero.jpg" alt="Team of professionals">
    </div>
  </div>
</section>
<!--End Hero Section -->







<!-- ======= Post a Job Section ======= -->
<section id="post-job-section" class="post-job-section">
  <div class="post-job-container">
    <!-- LEFT SIDE -->
<div class="post-job-left">
  <h2>Post a Job Easily and Connect with Local Talent</h2>
  <p>
    Create job posts in just a few steps. Define the role, requirements, and work setup, 
    then start receiving applications from nearby job seekers using WorkMuna.
  </p>

  <ul class="job-benefits">
    <li>
      <i class="fa-solid fa-list-check"></i>
      <b>Simple job posting flow</b><br>
      Enter job details, set requirements, and publish your post in minutes.
    </li>
    <li>
      <i class="fa-solid fa-users"></i>
      <b>Reach local applicants</b><br>
      Your job posts are shown to job seekers searching by location, category, and job type.
    </li>
    <li>
      <i class="fa-solid fa-briefcase"></i>
      <b>Manage applications in one place</b><br>
      View applicants, track application status, and schedule interviews directly on WorkMuna.
    </li>
  </ul>

    <a href="../employercontent/register.php" class="btn-dark">Post Your First Job Free</a>
    </div>

    <!-- RIGHT SIDE -->
    <div class="post-job-right">
      <div class="job-form-card">
        <div class="form-header">
          <h3>Create Job Posting</h3>
          <!-- <button class="badge">5x more applications</button> -->
        </div>

        <form>
          <label>Job Title</label>
          <input type="text" placeholder="e.g., Senior Frontend Developer">

          <label>Description</label>
          <textarea placeholder="Describe the role, responsibilities, and requirements..."></textarea>

          <label>Skills Required</label>
          <div class="skills-box">
            <span class="skill">React</span>
            <span class="skill">TypeScript</span>
            <span class="skill">Tailwind</span>
            <button type="button" class="add-skill">+ Add skill</button>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Salary Range</label>
              <input type="text" value="₱80k - ₱120k">
            </div>
            <div class="form-group">
              <label>Work Type</label>
              <select>
                <option>Remote</option>
                <option>On-site</option>
                <option>Hybrid</option>
              </select>
            </div>
          </div>

          <a href="../employercontent/register.php" class="btn-green">Publish Job Posting</a>
        </form>
      </div>
    </div>
  </div>
</section>
<!-- End Post a Job Section -->











 <!-- ======= Employer Find Talents Section ======= -->
<section id="find-talents-section" class="find-talents-section">
  <div class="find-talents-container">

    <!-- TITLE & DESCRIPTION -->
    <div class="find-talents-header">
      <h2>Discover and Hire Top Talent Effortlessly</h2>
      <p>
        Search our verified talent pool by skills, experience, location, or availability. 
        Connect directly with candidates ready to join your team—no endless scrolling.
      </p>
    </div>

    <!-- SEARCH CARD -->
    <!-- <div class="talent-search-card">
      <div class="search-bar">
        <input
        type="text" 
        name="keyword" 
        placeholder="Search by skills, job title, or keywords..."
        value="<?php echo isset($_GET['keyword']) ? htmlspecialchars($_GET['keyword']) : ''; ?>">

              <button type="submit" class="filter-btn">
            <i class="fa-solid fa-filter"></i> Search
          </button>
        </div>

        <div class="filter-row">
          <div class="filter-group">
            <label>Skills:</label>
            <select name="skill">
              <option value="">Any</option>
              <option value="Marketing">Marketing</option>
              <option value="Frontend">Frontend</option>
              <option value="Backend">Backend</option>
              <option value="Design">Design</option>
            </select>
          </div>

          <div class="filter-group">
            <label>Experience:</label>
            <select name="experience">
              <option value="">Any</option>
              <option value="1">1+ year</option>
              <option value="3">3+ years</option>
              <option value="5">5+ years</option>
            </select>
          </div>

          <div class="filter-group">
            <label>Location:</label>
            <select name="location">
              <option value="">Any</option>
              <option value="On-site">On-site</option>
              <option value="Hybrid">Hybrid</option>
              <option value="Remote">Remote</option>
            </select>
          </div>
        </div>

        <div class="results-bar">
          <span>Showing matched professionals</span>
        </div>
      </form>
    </div> -->

    <!-- FEATURES GRID -->
    <div class="find-talents-features">
      <div class="feature">
        <i class="fa-solid fa-magnifying-glass"></i>
        <h4>Advanced Search</h4>
        <p>Filter by required skills, job type, and location </p>
      </div>

      <div class="feature">
        <i class="fa-solid fa-bolt"></i>
        <h4>Talent Matching</h4>
        <p>Recommendations based on your job requirements.</p>
      </div>

      <div class="feature">
        <i class="fa-solid fa-envelope-open-text"></i>
        <h4>Engagement Tools</h4>
        <p>View resumes, portfolios, and send personalized invites.</p>
      </div>

      <!-- <div class="feature">
        <i class="fa-solid fa-users"></i>
        <h4>Pool Stats</h4>
        <p>Access 50,000+ pre-vetted professionals across industries.</p>
      </div> -->
    </div>

  

  </div>
</section>
<!-- End Employer Find Talents Section -->












<!-- ======= Employer Profile Section ======= -->
<section id="build-profile-section" class="build-profile-section">
  <div class="build-profile-container">
    <!-- LEFT: Profile Preview Card -->
    <div class="build-left">
      <div class="tag">
        <i class="fa-solid fa-sparkles"></i> Build Your Brand
      </div>
      <h2>Build Your Employer Brand with a Custom Profile</h2>
      <p>
        Showcase your company story, culture, and open roles in one place. 
        Attract top talents who align with your values—without extra marketing costs.
      </p>

      <ul class="profile-benefits">
        <li><i class="fa-solid fa-circle-check"></i> Highlight your company culture and perks</li>
        <li><i class="fa-solid fa-circle-check"></i> Engage job seekers with authentic storytelling</li>
        <li><i class="fa-solid fa-circle-check"></i> Gain more qualified applications faster</li>
      </ul>
      
      <a href= "../employercontent/register.php" class="btn-dark">Start Building Your Profile</a>
    </div>

    <!-- RIGHT: Company Profile Mockup -->
    <div class="build-right">
      <div class="company-profile-card">
        <div class="profile-header">
          <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" alt="company-logo">
          <div>
            <h3>InnovateTech Solutions</h3>
            <p><i class="fa-solid fa-location-dot"></i> San Francisco, CA</p>
          </div>
        </div>

        <p class="profile-desc">
          We’re building the future of productivity tools. 
          Join our diverse team and make an impact that matters.
        </p>

        <div class="profile-tags">
          <span class="tag-blue">Flexible Hours</span>
          <span class="tag-green">Remote First</span>
          <span class="tag-pink">Equity</span>
        </div>

        <div class="profile-stats">
          <div><i class="fa-solid fa-users"></i> 50–200 employees</div>
          <div><i class="fa-solid fa-star"></i> 4.8 (120 reviews)</div>
        </div>

        <button class="btn-green">View 5 Open Roles</button>
      </div>

      <blockquote class="testimonial">
        “Our profile turned browsers into applicants—game changer for our hiring pipeline!”<br>
        <span>— Maria Rodriguez, HR Manager at ScaleUp Inc.</span>
      </blockquote>
    </div>
  </div>
</section>
<!-- End Employer Profile Section -->







    <!-- ======= Employer Why Choose Section ======= -->
<!-- <section id="employer-why-choose" class="employer-why-choose">
  <div class="employer-container">
    
    <h2>Why Choose WorkMuna?</h2>

    <div class="employer-why-grid">

      <div class="employer-why-card">
        <div class="employer-icon blue"><i class="fa-solid fa-briefcase"></i></div>
        <h3>Easy Job Search</h3>
        <ul>
          <li>From searching jobs to building your profile, WorkMuna helps you stay ahead with smart alerts and career tools.</li>
        </ul>
      </div>

      <div class="employer-why-card">
        <div class="employer-icon green"><i class="fa-regular fa-id-card"></i></div>
        <h3>Build Your Profile</h3>
        <ul>
          <li>Discover jobs that fit you, grow your profile, and get updates and resources that guide your career forward.</li>
        </ul>
      </div>

      <div class="employer-why-card">
        <div class="employer-icon yellow"><i class="fa-regular fa-bell"></i></div>
        <h3>Job Alerts & Matches</h3>
        <ul>
          <li>WorkMuna connects your profile, job searches, and alerts in one place—plus tools to boost your career growth.</li>
        </ul>
      </div>

      <div class="employer-why-card">
        <div class="employer-icon purple"><i class="fa-regular fa-lightbulb"></i></div>
        <h3>Career Resources</h3>
        <ul>
          <li>Search easily, get matched instantly, and access the tools you need to advance your career.</li>
        </ul>
      </div>

    </div>  

  
  </div>
</section> -->
<!-- End Employer Why Choose Section -->






    <!-- ======= How It Works Section ======= --> 
<!-- <section id="employer-how-it-works" class="employer-how-it-works">

  <div class="container">
        <h2>Hire Effortlessly in 3 Simple Steps</h2>

    <div class="steps">
            <div class="step">
                <div class="icon swipe"><i class="fa-solid fa-laptop"></i></div>
                <h3>Sign Up & Post a Job</h3>
                <p>Create a free account and post detailed job listings with custom requirements. Takes 5 minutes.</p>
            </div>

        <div class="step">
            <div class="icon match"><i class="fa-solid fa-handshake"></i></div>
            <h3>Review Matches & Connect</h3>
            <p>Get instant recommendations. Message candidates directly via in-app chat</p>
        </div>

        
        <div class="step">
            <div class="icon work"><i class="fa-solid fa-chart-line"></i></div>
            <h3>Hire & Track Success</h3>
            <p>Schedule interviews, make offers, and monitor hires with built-in tools. Upgrade for premium features like priority visibility.</p>
        </div>

    </div>
  </div>
</section> -->
<!-- End How It Works Section -->





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





<!-- ======= CTA (Call-to-Action) Section ======= -->
<!-- <section id="cta" class="cta-section">
  <div class="cta-container">
    <h2>Start Hiring Top Talent Today</h2>
    <p>No credit card required. Post your first job and see matches instantly.</p>

    <form class="cta-form">
      <input type="email" placeholder="Enter your email" required>
      <button type="submit" class="cta-btn">Register</button>
    </form>

    <div class="cta-buttons">
      <a href="#" class="btn-primary">Post a Job</a>
      <a href="#" class="btn-outline">Find Talents</a>
    </div>
  </div>
</section> -->
   <!-- End CTA Section -->






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


    <!-- links script.js --> 
    <script src="../script.js"></script>


</body>
</html>


