<?php include '../database.php'; ?>



<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Job Vacancies</title>
  <link rel="stylesheet" href="../styles.css">
</head>


<body class="jobs-page">
<!-- ======= Header ======= -->
<header>
    <div class="navbar top">
            <div class="nav-left">
        <a href="../index.php" class="logo">
            <img src="../images/workmunalogo2-removebg.png" alt="WorkMuna Logo">
        </a>

        <ul class="nav-links">
            <li><a href="../index.php">Home </a></li>
            <li><a href="#">Jobs </a></li>
            <li><a href="aboutus.php">About Us</a></li>
            <li><a href="profile.php">Profile</a></li>
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
            <li><a href="#">Jobs </a></li>
            <li><a href="/WORKSAD/applicant/profile.php">Profile</a></li>
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



<main class=job-content>
    <div class="job-container">
        <h1>Job Vacancies</h1>
        <p>Find jobs here</p>

        <!-- Search bar -->
        <form method="GET" action="jobs.php" class="search-bar">
            <input type="text" name="keyword" placeholder="Search jobs...">
            <button type="submit">Search</button>
        </form>

        <!-- Filters -->
        <div class="filters">
            <label><input type="checkbox" name="pwd"> PWDs</label>
            <label><input type="checkbox" name="displaced"> Displaced workers</label>
            <label><input type="checkbox" name="hsg"> High school graduates</label>
            <label><input type="checkbox" name="gov"> Government jobs</label>
        </div>

        <h3>Available Job Openings</h3>

        <div class="job-list">
            <?php
            // Fetch jobs from DB with company details
            $sql = "
            SELECT 
                jp.job_post_id,
                jp.job_post_name,
                jp.job_description,
                jp.requirements,
                jp.budget,
                jp.vacancies,
                jp.job_status_id,
                jp.created_at,
                c.company_id,
                c.company_name,
                c.industry,
                c.location AS company_location
            FROM job_post jp
            LEFT JOIN company c ON jp.company_id = c.company_id
            ORDER BY jp.created_at DESC
            ";
            $result = $conn->query($sql);
            
            if ($result === false) {
                echo "<p style='color:red;'>Error in query: " . $conn->error . "</p>";
                } 
            elseif ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $budget_display = isset($row['budget']) ? '₱' . number_format($row['budget'], 2) : 'Negotiable';
                $company_name = htmlspecialchars($row['company_name'] ?? 'Unknown Company');
                echo "
                <div class='job-card'>
                    <div class='job-info'>
                        <h2><a href='job-details.php?id={$row['job_post_id']}'>{$row['job_post_name']}</a></h2>
                        <p class='company'>Company: {$company_name}</p>
                        <p class='location'>Vacancies: {$row['vacancies']}</p>
                        <p class='requirements'>Requirements: {$row['requirements']}</p>
                        <p class='salary'>{$budget_display}</p>
                    </div>
                    <div class='job-date'>
                        <p>Posted on " . date("M d, Y", strtotime($row['created_at'])) . "</p>
                    </div>
                </div>
                ";
                    }
                } else {
                    echo "<p>No jobs available right now.</p>";
                }
            ?>
        </div>
    </div>
</main>




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
                <a href="#">Facebook</a> | 
                <a href="#">Twitter</a> | 
                <a href="#">OnlyFans</a>
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
