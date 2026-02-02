<?php include '../database.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Job Vacancies</title>
  <link rel="stylesheet" href="../styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class= about-page>
<!-- ======= Header ======= -->
<header>
    <div class="navbar top">
            <div class="nav-left">
        <a href="../index.php" class="logo">
            <img src="../images/workmunalogo2-removebg.png" alt="WorkMuna Logo">
        </a>

        <ul class="nav-links">
            <li><a href="../index.php">Home </a></li>
            <li><a href="#">About Us</a></li>
            <li><a href="../index.php#top-employers">Explore Company</a></li>
            <li><a href="/WORKSAD/employer/index.php">Employer Site</a></li>
        </ul>
            </div>

        <div class="nav-right">
            <a href="login.php" class="btn-log-in">Log In</a>
            <a href="../employer/login.php" class="btn-sign-up">Sign Up Free</a>
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
            <li><a href="/WORKSAD/index.php #top-employers">Explore Company</a></li>
            <li><a href="/WORKSAD/applicant/register.php">Register</a></li>
            <li><a href="/WORKSAD/applicant/login.php">Login</a></li>
            <li><a href="/WORKSAD/employer/index.php">Employer Site</a></li>
        </ul>
    </aside>

    <div class="overlay" id="overlay"></div>
</header>
    <!-- End Header -->



    <!-- ======= Hero Section ======= -->
<section class="about-hero">
  <div class="about-container">

    <!-- Left Content -->
    <div class="about-text">
      <h1>Hire the Best Talent Faster with WorkMuna</h1>
      <p>Find qualified applicants fast. Post jobs and manage applications easily all in one place. No more sorting through unrelated resumes.</p>
      
      <div class="about-buttons">
        <a href="#" class="btn-primary">Post Your First Job Free</a>
       
      </div>
    </div>

        <!-- Right Image -->
    <div class="about-image">
      <img src="../images/jobfair.jpg" alt="Team of professionals">
    </div>
  </div>
</section>
<!--End Hero Section -->





<!-- ======= Mission Vision Section ======= -->
<section class="mission-vision">
  <div class="mission-vision-container">
    <div class="card mission">
      <div class="card-header">
        <div class="icon">
          <i class="fas fa-bullseye"></i>
        </div>
        <h3>Our Mission</h3>
      </div>
      <p>
To help every job seeker find opportunities that match their skills, and to support employers in hiring efficiently and fairly. WorkMuna aims to make job searching simple and accessible for everyone.
      </p>
    </div>

    <div class="card vision">
      <div class="card-header">
        <div class="icon">
          <i class="fas fa-eye"></i>
        </div>
        <h3>Our Vision</h3>
      </div>
      <p>
        A world where career matching is personalized, efficient, and built on trust.
        Where technology amplifies human potential rather than replacing it.
      </p>
    </div>
  </div>
</section>
<!-- End Mission Vision Section -->








<!-- ======= Our Story Section ======= -->
<!-- <section class="our-story">
  <div class= our-story-container>
    <h2>Our Story</h2>
    <p class="subtitle">From frustration to innovation</p>

    <div class="timeline">
      <div class="timeline-item">
        <div class="year">2022</div>
        <div class="content">
          <h3>The Spark</h3>
          <p>Frustrated by traditional job boards that favored employers, the idea of creating WorkMuna began — a platform focused on helping job seekers first.</p>
        </div>
      </div>

      <div class="timeline-item">
        <div class="year">2023</div>
        <div class="content">
          <h3>Building the Foundation</h3>
          <p>Launched beta with 500 users and developed our matching algorithm for local job seekers in Pangasinan.</p>
        </div>
      </div>

      <div class="timeline-item">
        <div class="year">2024</div>
        <div class="content">
          <h3>Rapid Growth</h3>
          <p>Expanded to more towns in Pangasinan, connecting thousands of employers and job seekers.</p>
        </div>
      </div>

      <div class="timeline-item">
        <div class="year">2025</div>
        <div class="content">
          <h3>The Future is Now</h3>
          <p>Continuing to innovate with verified profiles, skill assessments, and career guidance for all Bayambangueños and Pangasinenses.</p>
        </div>
      </div>
    </div>
  </div>
</section> -->
<!--End Our Story Section -->




<!-- ======= Our Team Section ======= -->
<section id="our-team" class="our-team">
  <div class="container">
    
    <h2>Meet Our Team</h2>
    <p class="subtitle">Passionate professionals building the future of work</p>

    <div class="team-grid">

      <div class="team-card">
        <div class="team-image">
          <img src="../images/clint1.jpg" alt="Clint John Maslog">
        </div>
      <h3>Clint John Maslog</h3>
        <p class="role"> Lorem ipsum dolor</p>
        <p> Lorem ipsum dolor sit amet consectetur adipiscing elit. Sit amet consectetur adipiscing elit quisque faucibus ex. Adipiscing elit quisque faucibus ex sapien vitae pellentesque. </p>
      </div>


      <div class="team-card">
        <div class="team-image">
          <img src="../images/marc.png" alt="Marc Mendoza">
        </div>
        <h3>Marc Mendoza</h3>
        <p class="role"> Lorem ipsum dolor</p>
        <p> Lorem ipsum dolor sit amet consectetur adipiscing elit. Sit amet consectetur adipiscing elit quisque faucibus ex. Adipiscing elit quisque faucibus ex sapien vitae pellentesque. </p>
      </div>
      

      <div class="team-card">
        <div class="team-image">
          <img src="../images/dranrev.jpg" alt="Dranrev Catalan">
        </div>
        <h3>Dranrev Catalan</h3>
        <p class="role"> Lorem ipsum dolor</p>
        <p> Lorem ipsum dolor sit amet consectetur adipiscing elit. Sit amet consectetur adipiscing elit quisque faucibus ex. Adipiscing elit quisque faucibus ex sapien vitae pellentesque. </p>
      </div>


      <div class="team-card">
        <div class="team-image">
          <img src="../images/dan.jpg" alt="Dan Caritativo">
        </div>
        <h3>Dan Caritativo</h3>
        <p class="role"> Lorem ipsum dolor</p>
        <p> Lorem ipsum dolor sit amet consectetur adipiscing elit. Sit amet consectetur adipiscing elit quisque faucibus ex. Adipiscing elit quisque faucibus ex sapien vitae pellentesque. </p>
      </div>

    </div>   
  </div>
</section>
<!-- End Our Team Section -->





    <!-- ======= About Section ======= -->
 <!-- <section id="about" class="about section-bg">
        <div class="about-section">
            <div class="section-title">
                <h2>About WorkMuna</h2>
                <p>
                    “WorkMuna is a job-matching platform for Pangasinan. We connect job seekers and employers through a simple swiping system, making it fast and easy to find the right match.”
                </p>
            </div>
            
            <div class="about-column">
                <div class="about-first">
                    <p>
                        WorkMuna is an online job-matching platform dedicated to Bayambang, Pangasinan, designed to connect local employers and jobseekers through a simple and innovative swiping system. It serves as a one-stop hub for employment opportunities and labor market information in the province.
                    </p>

                    <p>
                        WorkMuna fosters collaboration between businesses and talent, helping employers find the right people for their manpower needs while giving jobseeker access to local opportunities that match their skills and preferences. It also works to complement community efforts by providing a modern alternative to traditional hiring processes.
                    </p>

                    <p>
                        In addition to job postings, WorkMuna offers tools that make the hiring and application process easier such as preference-based matching, profile building, and employer-applicant messaging.
                    </p>  

                    <p>
                        Using WorkMuna is quick and free both jobseekers and employers can create an account and enjoy the following features:
                    </p>

                    <ul>
                        <li>Easy-to-use swiping system</li>
                        <li>Local jobs focused on Pangasinan</li>
                        <li>Direct connection between employers and jobseeker</li>
                    </ul>

                </div>
                <div class="about-jobseeker">
                    <h2>For Jobseekers</h2>
                    <ul class="jobseeker-content">
                        <li>Search job opportunities</li>
                        <li>Run job matching process</li>
                        <li>Get a list of job vacancies with the employer's contact information</li>
                        <li>Receive job invitations from accredited employers</li>
                    </ul>
                </div>

                <div class="about-employer">
                    <h2>For Employers</h2>
                    <ul class="employers-content">
                        <li>Post job vacancies for free</li>
                        <li>View list and send job invitations to jobseeker matched to job vacancies</li>
                        <li>Manage job applications and automate submission of job placement reports</li>
                        <li>Participate in Job Fairs authorized by PESO Bayambang</li>
                    </ul>
                </div>
            </div>
        </div>
</section> -->
    <!-- End About Section -->


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