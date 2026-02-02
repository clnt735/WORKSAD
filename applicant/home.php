<?php
include '../database.php';
session_start();




$user_id = $_SESSION['user_id'] ?? 0; // ensure session sets user_id after login

// Fetch job posts joined with precomputed match_scores, exclude interacted jobs
$query = "
SELECT 
    jp.job_post_id,
    jp.job_post_name,
    jp.job_description,
    jp.requirements,
    jp.benefits,
    jp.budget,
    jp.vacancies,
    jp.job_location_id,
    jp.job_type_id,
    jp.experience_level_id,
    jp.education_level_id,
    jp.work_setup_id,
    jp.created_at,
    jp.updated_at,
    c.company_id,
    c.company_name,
    c.industry,
    c.location AS company_location,
    c.logo AS company_logo,
    jt.job_type_name AS job_type_name
FROM job_post jp
LEFT JOIN company c ON jp.company_id = c.company_id
LEFT JOIN job_type jt ON jt.job_type_id = jp.job_type_id
LEFT JOIN interactions i ON i.job_post_id = jp.job_post_id AND i.user_id = ?
WHERE (jp.job_status_id = 1 OR jp.job_status_id IS NULL)
  AND (i.interaction_id IS NULL)
ORDER BY jp.created_at DESC
LIMIT 200
";

$stmt = $conn->prepare($query);

if (!$stmt) {
    die("Query preparation failed: " . $conn->error . "<br><br>Query: " . $query);
}

$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if query failed
if (!$result) {
    die("Database query failed: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Job Vacancies</title>
  <link rel="stylesheet" href="../styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="applicant-home">

<!-- ================= MOBILE HEADER ================= -->
<header class="mobile-header">
    <div class="wm-logo">
        <img src="../images/workmunalogo2-removebg.png" alt="WorkMuna Logo">
    </div>
    
    <div class="header-actions">
      
      <a class="search-bar">
        <i class="fa-solid fa-magnifying-glass"></i>
      </a>

      <a href="/WORKSAD/applicant/notifications.php" class="notification-bell">
        <i class="fa-regular fa-bell"></i>
        <span class="badge">3</span> <!-- optional unread count -->
      </a>

      <!-- Hamburger icon -->
        <div class="menu-toggle" id="menu-toggle">☰</div>
    </div>

    <!-- Sidebar mobile only -->
    <aside class="sidebar" id="sidebar">
    <!-- Close Button -->
    <button class="close-btn" id="closeSidebar">&times;</button>
        <ul class="mobnav-links">
            <li><a href="home.php">Home</a></li>
            <li><a href="search_jobs.php">Jobs</a></li>
            <li><a href="companies.php">Companies</a></li>
            <li><a href="application.php">My Applications</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </aside>

    <div class="overlay" id="overlay"></div>
</header>

<div class="mobile-search-bar" id="mobileSearchBar">
    <input type="text" placeholder="Search jobs, companies..." />
</div>


  <!-- ======= DESKTOP HEADER ======= -->
<?php include 'header.php'; ?>

   <!-- ======= DESKTOP VIEW OPTIONS  ======= -->
   <!-- <div class="view-controls">
    <div class="view-buttons" role="tablist" aria-label="View options">
      <button class="view-btn active" data-view="grid" title="Grid view"><i class="fa-solid fa-table-cells"></i></button>
      <button class="view-btn" data-view="list" title="List view"><i class="fa-solid fa-list"></i></button>
      <button class="view-btn" data-view="swipe" title="Swipe view"><i class="fa-solid fa-layer-group"></i></button>
    </div>
    <a class="view-all" href="search_jobs.php">View All →</a>
  </div> -->


<!-- ======= JOB LIST HEADER ======= -->
<section class="jobs-header-row">

  <div class="header-left">
     <div class="header-text">
      <h2>Jobs Matched For You</h2>
      <p>Based on your profile and preferences.</p>
    </div>

    <div class="view-controls">
      <div class="view-buttons" role="tablist" aria-label="View options">
        <button class="view-btn active" data-view="grid" title="Grid view"><i class="fa-solid fa-table-cells"></i></button>
        <button class="view-btn" data-view="list" title="List view"><i class="fa-solid fa-list"></i></button>
        <button class="view-btn" data-view="swipe" title="Swipe view"><i class="fa-solid fa-layer-group"></i></button>
      </div>
    </div>
  </div>

</section>


 
<!-- ======= JOB LIST CONTAINER ======= -->
<section class="jobs-wrapper">

  <div class="job-container grid-view">

    <?php if ($result->num_rows > 0): ?>
      <?php while ($row = $result->fetch_assoc()): ?>

        <div class="job-card" 
             data-job-id="<?= (int)$row['job_post_id']; ?>"
             data-score="<?= htmlspecialchars($row['score']); ?>">

         

          <div class="score-badge">
            <?= htmlspecialchars(round($row['score'], 2)); ?>% Match
          </div>

          <div class="left">

            <h3><?= htmlspecialchars($row['job_post_name']); ?></h3>

            <div class="company">
              <i class="fa-solid fa-seedling"></i>
              <?= htmlspecialchars($row['company_name'] ?? 'Company'); ?>
            </div>

            <?php if (!empty($row['job_location_id'])): ?>
              <p class="location">
                <i class="fa-solid fa-location-dot"></i>
                Location ID: <?= htmlspecialchars($row['job_location_id']); ?>
              </p>
            <?php elseif (!empty($row['company_location'])): ?>
              <p class="location">
                <i class="fa-solid fa-location-dot"></i>
                <?= htmlspecialchars($row['company_location']); ?>
              </p>
            <?php endif; ?>

            <p class="desc">
              <?= htmlspecialchars(substr($row['job_description'], 0, 140)); ?>...
            </p>

            <?php
              // Use budget column from job_post table
              $budget = null;
              if (isset($row['budget']) && $row['budget'] !== null && $row['budget'] !== '') {
                $budget = number_format((float)$row['budget'], 2);
              }
              $salaryLabel = $budget ? "₱" . $budget : 'Negotiable';

              // job type: display friendly label if available
              $jobTypeLabel = $row['job_type_name'] ?? '';
              // vacancies from job_post table
              $vacancies = isset($row['vacancies']) ? (int)$row['vacancies'] : null;
            ?>

            <p class="salary"><?= $salaryLabel; ?></p>

            <?php if ($jobTypeLabel): ?>
              <p class="job-type"><i class="fa-solid fa-briefcase"></i> <?= htmlspecialchars($jobTypeLabel); ?></p>
            <?php endif; ?>

            <?php if ($vacancies): ?>
              <p class="vacancies"><i class="fa-solid fa-users"></i> <?= (int)$vacancies; ?> <?= ((int)$vacancies === 1) ? 'vacancy' : 'vacancies'; ?></p>
            <?php endif; ?>

            <div class="meta">
              <small>Posted: <?= date('M d, Y', strtotime($row['created_at'])); ?></small>
            </div>

          </div>

          <div class="card-actions">
            <button class="btn-apply"
                    onclick="location.href='job_details.php?id=<?= (int)$row['job_post_id']; ?>'">
              Apply Now
            </button>
            <button class="btn-bookmark" data-action="bookmark" title="Bookmark">🔖</button>
            <button class="btn-like" data-action="like" title="Like">❤</button>
            <button class="btn-dislike" data-action="dislike" title="Dismiss">✖</button>
          </div>

        </div>

      <?php endwhile; ?>
    <?php else: ?>

      <div class="no-jobs">
        <p>No available jobs.</p>
      </div>

    <?php endif; ?>

  </div>

</section>

  <!-- ================= MOBILE BOTTOM NAV ================= -->
<!-- <nav class="bottom-nav">
    <a href="#" class="active">
        <i class="fa-solid fa-house"></i>Home
    </a>

    <a href="/WORKSAD/applicant/search.php">
        <i class="fa-solid fa-magnifying-glass"></i>Search
    </a>

    <a href="profile.php">
        <i class="fa-solid fa-user"></i>Profile
    </a>
</nav> -->




<!-- ================= JS ================= -->

<!-- View Grid -->
<script>
const container = document.querySelector('.job-container');
const viewButtons = document.querySelectorAll('.view-controls .view-btn');

function applyView(view) {
  container.className = 'job-container'; // reset
  if (view === 'grid') container.classList.add('grid-view');
  if (view === 'list') container.classList.add('list-view');
  if (view === 'swipe') container.classList.add('swipe-view');

  viewButtons.forEach(b => b.classList.toggle('active', b.dataset.view === view));
  localStorage.setItem('job_view', view);

  // handle swipe display fallback
  if (view === 'swipe') {
    const cards = Array.from(container.querySelectorAll('.job-card'));
    cards.forEach((c, i) => c.style.display = i === 0 ? 'block' : 'none');
  } else {
    container.querySelectorAll('.job-card').forEach(c => c.style.display = '');
  }
}

viewButtons.forEach(btn => {
  btn.addEventListener('click', () => applyView(btn.dataset.view));
});

// initialize view from previous choice or default to grid
const savedView = localStorage.getItem('job_view') || 'grid';
applyView(savedView);






// Sidebar toggle
const menuToggle = document.getElementById("menu-toggle");
const sidebar = document.getElementById("sidebar");
const overlay = document.getElementById("overlay");
const closeBtn = document.getElementById("closeSidebar");

if (menuToggle && sidebar) {
  // Toggle sidebar when hamburger is clicked
  menuToggle.addEventListener("click", (e) => {
    e.stopPropagation(); // prevent click from bubbling to document
    sidebar.classList.toggle("active");
    if (overlay) overlay.classList.toggle("active"); // toggle overlay
  });

  // Close sidebar if clicked outside
  document.addEventListener("click", (e) => {
    if (
      sidebar.classList.contains("active") &&
        !sidebar.contains(e.target) &&
        !menuToggle.contains(e.target)
      )
       {
      sidebar.classList.remove("active");
    }
  });

   // Close sidebar when clicking overlay directly
  if (overlay) {
    overlay.addEventListener("click", () => {
      sidebar.classList.remove("active");
      overlay.classList.remove("active");
    });
  }


  // Close sidebar with ESC key
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && sidebar.classList.contains("active")) {
      sidebar.classList.remove("active");
      if (overlay) overlay.classList.remove("active"); // hide overlay
    }
  });
}


  // Close sidebar with x
  closeBtn.addEventListener("click", () => {
    sidebar.classList.remove("active");
    overlay.style.display = "none";
  });


  //Open search bar on mobile
document.querySelector(".search-bar").addEventListener("click", function (e) {
    if (window.innerWidth <= 600) {
        e.preventDefault(); 
        
        const bar = document.getElementById("mobileSearchBar");
        bar.classList.toggle("open");
    }
});

// Interaction JS
async function sendInteraction(jobId, action) {
  try {
    const res = await fetch('/WORKSAD/applicant/api/interactions.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ job_post_id: jobId, action: action })
    });
    const data = await res.json();
    if (data.success) return data;
    throw new Error(data.message || 'Interaction failed');
  } catch (err) {
    console.error(err);
    return { success:false, message: err.message };
  }
}

// Delegated interaction handler (replaces per-card listeners)
container.addEventListener('click', (e) => {
  const btn = e.target.closest('button[data-action]');
  if (!btn) return;
  const card = btn.closest('.job-card');
  if (!card) return;
  const jobId = card.dataset.jobId;
  const action = btn.dataset.action;

  if (action === 'like' || action === 'dislike') {
    // optimistic: remove card immediately
    sendInteraction(jobId, action).then(resp => {
      if (resp.success) card.remove();
      else console.error('Interaction failed', resp.message);
    }).catch(err => console.error(err));
    return;
  }

  if (action === 'bookmark') {
    // toggle visual bookmark state and persist
    const toggled = card.classList.toggle('bookmarked');
    sendInteraction(jobId, action).then(resp => {
      if (!resp.success) {
        card.classList.toggle('bookmarked');
        console.error('Bookmark failed', resp.message);
      }
    }).catch(err => {
      card.classList.toggle('bookmarked');
      console.error(err);
    });
    return;
  }

});

</script>

</body>
</html>
