
<?php include 'sidebar.php'; 
include '../database.php';

// Read filter inputs
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';

$flashPayload = null;
if (isset($_GET['flash_status'])) {
  $status = $_GET['flash_status'] === 'success' ? 'success' : 'error';
  $flashMessage = $_GET['flash_message'] ?? ($status === 'success' ? 'Job updated successfully.' : 'Unable to complete the request.');
  $flashPayload = [
    'status' => $status,
    'message' => $flashMessage,
  ];
}

// Fetch categories for filter dropdown
$categories = [];
$categoryResult = $conn->query("SELECT job_category_id, job_category_name FROM job_category ORDER BY job_category_name");
if ($categoryResult) {
  while ($row = $categoryResult->fetch_assoc()) {
    $categories[] = $row;
  }
}

$jobStatuses = [];
$statusResult = $conn->query("SELECT job_status_id, job_status_name FROM job_status ORDER BY job_status_name");
if ($statusResult) {
  while ($row = $statusResult->fetch_assoc()) {
    $jobStatuses[] = $row;
  }
}
$jobTypes = [];
$jobTypeResult = $conn->query("SELECT job_type_id, job_type_name FROM job_type WHERE is_archived = 0 ORDER BY job_type_name");
if ($jobTypeResult) {
  while ($row = $jobTypeResult->fetch_assoc()) {
    $jobTypes[] = $row;
  }
}
$cities = [];
$cityResult = $conn->query("SELECT city_mun_id, city_mun_name FROM city_mun ORDER BY city_mun_name ASC");
if ($cityResult) {
  while ($row = $cityResult->fetch_assoc()) {
    $cities[] = $row;
  }
}
$barangays = [];
$barangayResult = $conn->query("SELECT barangay_id, city_mun_id, barangay_name FROM barangay ORDER BY barangay_name ASC");
if ($barangayResult) {
  while ($row = $barangayResult->fetch_assoc()) {
    $barangays[] = $row;
  }
}
$barangayOptionsByCity = [];
foreach ($barangays as $barangay) {
  $cityKey = (int) $barangay['city_mun_id'];
  if (!isset($barangayOptionsByCity[$cityKey])) {
    $barangayOptionsByCity[$cityKey] = [];
  }
  $barangayOptionsByCity[$cityKey][] = [
    'barangay_id' => (int) $barangay['barangay_id'],
    'barangay_name' => $barangay['barangay_name'],
  ];
}
$barangayMapForJs = (object) $barangayOptionsByCity;
$totalJobs = 0;
$activeJobs = 0;
$inProgressJobs = 0;
$completedJobs = 0;

// Fetch data from the database
$jobCountBase = " FROM job_post jp INNER JOIN user u ON jp.user_id = u.user_id AND u.user_type_id = 3";
$totalJobsQuery = "SELECT COUNT(*) AS total_jobs" . $jobCountBase;
$activeJobsQuery = "SELECT COUNT(*) AS active_jobs" . $jobCountBase . " WHERE jp.job_status_id = '1'";
$inProgressJobsQuery = "SELECT COUNT(*) AS in_progress_jobs" . $jobCountBase . " WHERE jp.job_status_id = '2'";
$completedJobsQuery = "SELECT COUNT(*) AS completed_jobs" . $jobCountBase . " WHERE jp.job_status_id = '3'";
$totalJobsResult = mysqli_query($conn, $totalJobsQuery);
$activeJobsResult = mysqli_query($conn, $activeJobsQuery);
$inProgressJobsResult = mysqli_query($conn, $inProgressJobsQuery);
$completedJobsResult = mysqli_query($conn, $completedJobsQuery);

// Get counts
if ($totalJobsResult) {
    $totalJobs = mysqli_fetch_assoc($totalJobsResult)['total_jobs'];
}
if ($activeJobsResult) {
    $activeJobs = mysqli_fetch_assoc($activeJobsResult)['active_jobs'];
}
if ($inProgressJobsResult) {
    $inProgressJobs = mysqli_fetch_assoc($inProgressJobsResult)['in_progress_jobs'];
}
if ($completedJobsResult) {
    $completedJobs = mysqli_fetch_assoc($completedJobsResult)['completed_jobs'];
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Jobs Management</title>
  <link rel="stylesheet" href="../admin/styles.css">
  <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
  <link rel="stylesheet" href="../assets/vendor/sweetalert2/sweetalert2.min.css">
  <script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
</head>
<body class="admin-page jobs-page">
  <?php renderAdminSidebar(); ?>
  <main class="content">
    <div class="header">
      <div>
        <h1>Jobs Management</h1>
        <p class="lead">Review job listings submitted by employers.</p>
      </div>
    </div>

    <div class="stats">
      <div class="card">
        <h3>Total Jobs</h3>
        <p class="stat-value"><?php echo number_format($totalJobs); ?></p>
      </div>
      <div class="card">
        <h3>Available Jobs</h3>
        <p class="stat-value"><?php echo number_format($activeJobs); ?></p>
      </div>
      <div class="card">
        <h3>In Progress</h3>
        <p class="stat-value"><?php echo number_format($inProgressJobs); ?></p>
      </div>
      <div class="card">
        <h3>Completed</h3>
        <p class="stat-value"><?php echo number_format($completedJobs); ?></p>
      </div>
    </div>

    <div class="filters-section">
      <form class="search-filter-form" method="GET">
        <div class="search-box">
          <input type="text" name="search" placeholder="Search job listings..." 
                 value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <select class="filter-select" name="category">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?php echo $cat['job_category_id']; ?>"
              <?php echo $category == $cat['job_category_id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($cat['job_category_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <select class="filter-select" name="status">
          <option value="">All Status</option>
          <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
          <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
        </select>
        <select class="filter-select" name="sort">
          <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
          <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
          <option value="title" <?php echo $sort === 'title' ? 'selected' : ''; ?>>A-Z</option>
          <option value="most_applications" <?php echo $sort === 'most_applications' ? 'selected' : ''; ?>>Most Applications</option>
        </select>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-filter"></i>
          Apply Filters
        </button>
        <button type="button" class="btn btn-secondary" onclick="openArchiveModal()">
          <i class="fas fa-archive"></i>
           Archived Jobs
        </button>
      </form>
    </div>

    <table class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Category</th>
          <th>Employer</th>
          <th>Status</th>
          <th>Vacancies</th>
          <th>Budget</th>
          <th>Likes</th>
          <th>Actions</th>
        </tr>
      </thead>
      
      <tbody>
        <?php
    // Build dynamic filters
    $where = [];
    $where[] = "u.user_type_id = 3"; // employers
    $where[] = "(jp.job_status_id IS NULL OR jp.job_status_id <> 4)"; // hide archived jobs from main table
    if ($search !== '') {
      $nameExpr = "TRIM(CONCAT_WS(' ', up.user_profile_first_name, up.user_profile_middle_name, up.user_profile_last_name))";
      $terms = preg_split('/\s+/', $search);
      $termClauses = [];
      foreach ($terms as $term) {
        $term = trim($term);
        if ($term === '') {
          continue;
        }
        $like = '%' . $conn->real_escape_string($term) . '%';
        $termClauses[] = "((" . $nameExpr . ") LIKE '" . $like . "' OR up.user_profile_first_name LIKE '" . $like . "' OR up.user_profile_last_name LIKE '" . $like . "' OR jp.job_description LIKE '" . $like . "' OR jp.requirements LIKE '" . $like . "')";
      }
      if ($termClauses) {
        $where[] = '(' . implode(' AND ', $termClauses) . ')';
      }
    }
    if ($category !== '') {
      $where[] = "jp.job_category_id = " . intval($category);
    }
    if ($status === 'active') {
      $where[] = "jp.job_status_id = 1"; // treat status_id 1 as Active
    } elseif ($status === 'inactive') {
      $where[] = "(jp.job_status_id IS NULL OR jp.job_status_id <> 1)";
    }

    $whereSql = count($where) ? (' WHERE ' . implode(' AND ', $where)) : '';

    // Sorting
    switch ($sort) {
      case 'oldest':
        $orderBy = ' ORDER BY jp.created_at ASC';
        break;
      case 'title':
        $orderBy = ' ORDER BY jp.job_description ASC';
        break;
      case 'most_applications':
        $orderBy = ' ORDER BY like_count DESC, jp.created_at DESC';
        break;
      case 'newest':
      default:
        $orderBy = ' ORDER BY jp.created_at DESC';
        break;
    }

    // Main query with filters
        $query = "SELECT jp.*, jc.job_category_name AS job_category, js.job_status_name AS job_status,
          jt.job_type_name AS job_type,
          u.user_id AS poster_user_id, up.user_profile_first_name, up.user_profile_middle_name, up.user_profile_last_name,
          jpl.job_location_id, jpl.city_mun_id AS location_city_id, jpl.barangay_id AS location_barangay_id,
          jpl.address_line AS location_address_line, cm.city_mun_name AS location_city_name, brgy.barangay_name AS location_barangay_name,
          (SELECT COUNT(*) FROM job_likes jl WHERE jl.job_post_id = jp.job_post_id) AS like_count
          FROM job_post jp
          INNER JOIN user u ON jp.user_id = u.user_id
          LEFT JOIN job_category jc ON jp.job_category_id = jc.job_category_id
          LEFT JOIN job_type jt ON jp.job_type_id = jt.job_type_id
          LEFT JOIN user_profile up ON u.user_id = up.user_id
          LEFT JOIN job_status js ON jp.job_status_id = js.job_status_id
          LEFT JOIN job_post_location jpl ON jpl.job_post_id = jp.job_post_id
          LEFT JOIN city_mun cm ON cm.city_mun_id = jpl.city_mun_id
          LEFT JOIN barangay brgy ON brgy.barangay_id = jpl.barangay_id" .
          $whereSql . $orderBy;

    $result = mysqli_query($conn, $query);

    if ($result === false) {
      // Show SQL error for debugging when the query fails
      echo "<tr><td colspan='8'>Query error: " . htmlspecialchars(mysqli_error($conn)) . "</td></tr>";
    } elseif (mysqli_num_rows($result) === 0) {
      echo "<tr><td colspan='8'>No jobs found.</td></tr>";
    } else {
      while ($row = mysqli_fetch_assoc($result)) {
        // Build full name similar to admin/users.php
        $first = isset($row['user_profile_first_name']) ? $row['user_profile_first_name'] : '';
        $middle = isset($row['user_profile_middle_name']) ? $row['user_profile_middle_name'] : '';
        $last = isset($row['user_profile_last_name']) ? $row['user_profile_last_name'] : '';
        $fullName = trim($first . ' ' . ($middle ? $middle . ' ' : '') . $last);
        if ($fullName === '') {
          $companyName = isset($row['company_name']) ? trim((string) $row['company_name']) : '';
          if ($companyName !== '') {
            $fullName = $companyName;
          } elseif (!empty($row['poster_user_id'])) {
            $fullName = 'User #' . $row['poster_user_id'];
          } else {
            $fullName = '—';
          }
        }

        echo "<tr>";
        // job_post_id (note: your job_post_id may start at 5 per DB setup)
        echo "<td>" . htmlspecialchars($row['job_post_id']) . "</td>";
        // category
        echo "<td>" . htmlspecialchars($row['job_category']) . "</td>";
        // full name from user_profile
        echo "<td>" . htmlspecialchars($fullName) . "</td>";
        // status
        $statusClass = isset($row['job_status']) ? strtolower(str_replace(' ', '-', $row['job_status'])) : '';
        echo "<td><span class='status " . $statusClass . "'>" . htmlspecialchars($row['job_status']) . "</span></td>";
        // vacancies, budget, likes (bid_count)
        echo "<td>" . htmlspecialchars($row['vacancies']) . "</td>";
        echo "<td>" . htmlspecialchars($row['budget']) . "</td>";
        echo "<td>" . htmlspecialchars($row['like_count']) . "</td>";
        $locationPieces = array_filter([
          $row['location_address_line'] ?? '',
          $row['location_barangay_name'] ?? '',
          $row['location_city_name'] ?? ''
        ], function ($part) {
          return $part !== null && $part !== '';
        });
        $locationDisplay = implode(', ', $locationPieces);
        $locationCityId = $row['location_city_id'] ?? '';
        $locationBarangayId = $row['location_barangay_id'] ?? '';
        $locationAddressLine = $row['location_address_line'] ?? '';
        $jobLocationId = $row['job_location_id'] ?? '';

        $dataAttrs = sprintf(
          " data-job-id='%s' data-job-title='%s' data-job-description='%s' data-job-type='%s' data-job-budget='%s' data-job-location='%s' data-job-category='%s' data-job-status='%s' data-job-vacancies='%s' data-location-city='%s' data-location-barangay='%s' data-location-address='%s' data-job-location-id='%s'",
          htmlspecialchars($row['job_post_id'], ENT_QUOTES),
          htmlspecialchars($row['job_post_name'] ?? '', ENT_QUOTES),
          htmlspecialchars($row['job_description'] ?? '', ENT_QUOTES),
          htmlspecialchars($row['job_type_id'] ?? '', ENT_QUOTES),
          htmlspecialchars($row['budget'] ?? '', ENT_QUOTES),
          htmlspecialchars($locationDisplay, ENT_QUOTES),
          htmlspecialchars($row['job_category_id'] ?? '', ENT_QUOTES),
          htmlspecialchars($row['job_status_id'] ?? '', ENT_QUOTES),
          htmlspecialchars($row['vacancies'] ?? '', ENT_QUOTES),
          htmlspecialchars($locationCityId, ENT_QUOTES),
          htmlspecialchars($locationBarangayId, ENT_QUOTES),
          htmlspecialchars($locationAddressLine, ENT_QUOTES),
          htmlspecialchars($jobLocationId, ENT_QUOTES)
        );

        echo "<td>
          <div class='action-buttons'>
            <button class='btn btn-icon view-job-btn' title='View Job'" . $dataAttrs . "><i class='fas fa-eye'></i></button>
            <button class='btn btn-icon edit-job-btn' title='Edit Job'" . $dataAttrs . "><i class='fas fa-pen'></i></button>
            <button class='btn btn-icon archive-job-btn' title='Archive Job'" . $dataAttrs . "><i class='fas fa-box-archive'></i></button>
          </div>
          </td>";
        echo "</tr>";
      }
    }  // <-- close the else block that wraps the while loop

    mysqli_close($conn);
        ?>
     
    </table>
  </main>
    

  <!-- Job Details Modal -->
  <div id="addJobModal" class="modal">
    <div class="modal-content">
      <button class="modal-close" type="button" aria-label="Close" onclick="closeJobModal()">&times;</button>
      <div class="modal-header">
        <h2 id="jobModalTitle">Job Details</h2>
      </div>
      <form id="addJobForm" method="POST" action="../adminbackend/update_job.php">
        <input type="hidden" id="jobId" name="jobId">
        <input type="hidden" id="jobLocationId" name="jobLocationId">
        <!-- Job Information -->
        <div class="form-group">
          <label for="jobTitle">Job Title</label>
          <input type="text" id="jobTitle" name="jobTitle" required>
        </div>
        <div class="form-group">
          <label for="jobDescription">Job Description</label>
          <textarea id="jobDescription" name="jobDescription" rows="4" required></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="jobCategory">Category</label>
            <select id="jobCategory" name="jobCategory" required>
              <option value="">Select Category</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['job_category_id']; ?>"><?php echo htmlspecialchars($cat['job_category_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="jobStatus">Status</label>
            <select id="jobStatus" name="jobStatus" required>
              <option value="">Select Status</option>
              <?php foreach ($jobStatuses as $jobStatus): ?>
                <option value="<?php echo $jobStatus['job_status_id']; ?>"><?php echo htmlspecialchars($jobStatus['job_status_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="jobType">Job Type</label>
            <select id="jobType" name="jobType" required>
              <option value="">Select Job Type</option>
              <?php foreach ($jobTypes as $jobType): ?>
                <option value="<?php echo $jobType['job_type_id']; ?>"><?php echo htmlspecialchars($jobType['job_type_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="vacancies">Vacancies</label>
            <input type="number" id="vacancies" name="vacancies" min="0" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="budget">Budget (PHP)</label>
            <input type="number" id="budget" name="budget" min="0" step="0.01" required>
          </div>
          <div class="form-group">
            <label for="cityMun">City / Municipality</label>
            <select id="cityMun" name="cityMun" required>
              <option value="">Select city / municipality</option>
              <?php foreach ($cities as $city): ?>
                <option value="<?php echo (int) $city['city_mun_id']; ?>">
                  <?php echo htmlspecialchars($city['city_mun_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="barangay">Barangay</label>
            <select id="barangay" name="barangay" required disabled>
              <option value="">Select barangay</option>
            </select>
          </div>
          <div class="form-group">
            <label for="addressLine">Address Line</label>
            <input type="text" id="addressLine" name="addressLine" placeholder="Street / landmark" required>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline" onclick="closeJobModal()">Cancel</button>
          <button type="submit" class="btn btn-primary" id="jobModalSubmit">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <div id="archiveJobsModal" class="modal modal-wide">
    <div class="modal-content">
      <button class="modal-close" type="button" aria-label="Close" onclick="closeArchiveModal()">&times;</button>
      <div class="modal-header">
        <h2>Archived Jobs</h2>
      </div>
      <div id="archiveJobsContent" class="modal-body">
        <div class="filters-section">
          <form id="archiveFilterForm" class="search-filter-form">
            <div class="search-box">
              <input type="text" name="search" placeholder="Search job listings...">
            </div>
            <select class="filter-select" name="category">
              <option value="">All Categories</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['job_category_id']; ?>">
                  <?php echo htmlspecialchars($cat['job_category_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <select class="filter-select" name="status">
              <option value="">All Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
            <select class="filter-select" name="sort">
              <option value="newest">Newest First</option>
              <option value="oldest">Oldest First</option>
              <option value="title">A-Z</option>
              <option value="most_applications">Most Applications</option>
            </select>
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-filter"></i>
              Apply Filters
            </button>
            <button type="button" class="btn btn-secondary" id="archiveResetFilters">
              <i class="fas fa-rotate-left"></i>
              Reset Filters
            </button>
          </form>
        </div>
        <div id="archiveJobsResults" class="archive-results">
          <p>Loading archived jobs...</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeArchiveModal()">Close</button>
      </div>
    </div>
  </div>

  <script>
    const addJobModal = document.getElementById('addJobModal');
    const jobModalTitle = document.getElementById('jobModalTitle');
    const jobModalSubmit = document.getElementById('jobModalSubmit');
    const addJobForm = document.getElementById('addJobForm');
    const jobIdInput = document.getElementById('jobId');
    const jobLocationIdInput = document.getElementById('jobLocationId');
    const updateJobAction = '../adminbackend/update_job.php';
    const citySelect = document.getElementById('cityMun');
    const barangaySelect = document.getElementById('barangay');
    const addressLineInput = document.getElementById('addressLine');
    const modalFields = [
      document.getElementById('jobTitle'),
      document.getElementById('jobDescription'),
      document.getElementById('jobCategory'),
      document.getElementById('jobStatus'),
      document.getElementById('jobType'),
      document.getElementById('vacancies'),
      document.getElementById('budget'),
      citySelect,
      barangaySelect,
      addressLineInput
    ].filter(Boolean);
    const barangayMap = <?php echo json_encode($barangayMapForJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const archiveJobsModal = document.getElementById('archiveJobsModal');
    const archiveJobsContent = document.getElementById('archiveJobsContent');
    const archiveFilterForm = document.getElementById('archiveFilterForm');
    const archiveResetFilters = document.getElementById('archiveResetFilters');
    const archiveJobsResults = document.getElementById('archiveJobsResults');

    function populateBarangayOptions(cityId, selectedBarangayId = '') {
      if (!barangaySelect) {
        return;
      }
      barangaySelect.innerHTML = '';
      const placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = 'Select barangay';
      barangaySelect.appendChild(placeholder);
      const options = cityId && barangayMap[cityId] ? barangayMap[cityId] : [];
      if (!options.length) {
        barangaySelect.disabled = true;
        barangaySelect.required = false;
        barangaySelect.value = '';
        return;
      }
      options.forEach(option => {
        const opt = document.createElement('option');
        opt.value = option.barangay_id;
        opt.textContent = option.barangay_name;
        if (String(option.barangay_id) === String(selectedBarangayId)) {
          opt.selected = true;
        }
        barangaySelect.appendChild(opt);
      });
      barangaySelect.disabled = false;
       barangaySelect.required = true;
      if (!selectedBarangayId) {
        barangaySelect.value = '';
      }
    }

    function setModalReadOnly(isReadOnly) {
      modalFields.forEach(field => {
        if (!field) return;
        if (field.tagName === 'SELECT') {
          field.disabled = isReadOnly;
        } else {
          field.readOnly = isReadOnly;
        }
      });
      jobModalSubmit.style.display = isReadOnly ? 'none' : '';
      jobModalSubmit.disabled = isReadOnly;
      if (!isReadOnly && citySelect && !citySelect.value) {
        populateBarangayOptions('');
      }
    }

    function populateJobForm(data) {
      jobIdInput.value = data.jobId || '';
      if (jobLocationIdInput) {
        jobLocationIdInput.value = data.jobLocationId || '';
      }
      document.getElementById('jobTitle').value = data.jobTitle || '';
      document.getElementById('jobDescription').value = data.jobDescription || '';
      document.getElementById('jobCategory').value = data.jobCategory || '';
      document.getElementById('jobStatus').value = data.jobStatus || '';
      document.getElementById('jobType').value = data.jobType || '';
      document.getElementById('vacancies').value = data.jobVacancies || '';
      document.getElementById('budget').value = data.jobBudget || '';
      const cityValue = data.locationCity || '';
      if (citySelect) {
        citySelect.value = cityValue;
      }
      populateBarangayOptions(cityValue, data.locationBarangay || '');
      if (barangaySelect && !cityValue) {
        barangaySelect.value = '';
      }
      if (addressLineInput) {
        addressLineInput.value = data.locationAddress || '';
      }
    }

    function openJobModal(mode = 'view') {
      const normalizedMode = mode === 'edit' ? 'edit' : 'view';
      if (normalizedMode === 'edit') {
        jobModalTitle.textContent = 'Edit Job';
        jobModalSubmit.textContent = 'Save Changes';
        addJobForm.action = updateJobAction;
        setModalReadOnly(false);
      } else {
        jobModalTitle.textContent = 'Job Details';
        addJobForm.action = '#';
        setModalReadOnly(true);
      }
      addJobModal.classList.add('show');
    }

    function closeJobModal() {
      addJobModal.classList.remove('show');
      addJobForm.reset();
      jobIdInput.value = '';
      if (jobLocationIdInput) {
        jobLocationIdInput.value = '';
      }
      if (citySelect) {
        citySelect.value = '';
      }
      populateBarangayOptions('');
      if (barangaySelect) {
        barangaySelect.value = '';
      }
      if (addressLineInput) {
        addressLineInput.value = '';
      }
      setModalReadOnly(false);
      jobModalTitle.textContent = 'Job Details';
      jobModalSubmit.textContent = 'Save Changes';
      addJobForm.action = updateJobAction;
    }

    if (citySelect) {
      citySelect.addEventListener('change', () => {
        populateBarangayOptions(citySelect.value);
        if (!citySelect.value && barangaySelect) {
          barangaySelect.value = '';
        }
      });
    }

    async function loadArchivedJobs(customParams) {
      if (!archiveJobsResults) {
        return;
      }
      let params;
      if (customParams instanceof URLSearchParams) {
        params = customParams;
      } else if (archiveFilterForm) {
        params = new URLSearchParams(new FormData(archiveFilterForm));
      } else {
        params = new URLSearchParams();
      }
      const queryString = params.toString();
      archiveJobsResults.innerHTML = '<p>Loading archived jobs...</p>';
      try {
        const response = await fetch('archive_jobs.php' + (queryString ? `?${queryString}` : ''));
        if (!response.ok) {
          throw new Error('Unable to load archived jobs.');
        }
        const html = await response.text();
        archiveJobsResults.innerHTML = html.trim() ? html : '<p>No archived jobs available.</p>';
        attachRestoreHandlers();
      } catch (error) {
        archiveJobsResults.innerHTML = `<p class="error-message">${error.message}</p>`;
      }
    }

    function openArchiveModal() {
      if (!archiveJobsModal || !archiveJobsContent) {
        return;
      }
      archiveJobsModal.classList.add('show');
      loadArchivedJobs();
    }

    function closeArchiveModal() {
      if (archiveJobsModal) {
        archiveJobsModal.classList.remove('show');
      }
    }

    function attachRestoreHandlers() {
      if (!archiveJobsResults) {
        return;
      }
      archiveJobsResults.querySelectorAll('.restore-job-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
          const jobId = btn.dataset.jobId;
          if (!jobId) {
            return;
          }
          const confirmation = await Swal.fire({
            icon: 'question',
            title: 'Restore job posting?',
            text: 'This will move the job back to the main listings.',
            confirmButtonText: 'Yes, restore',
            confirmButtonColor: '#2563eb',
            showCancelButton: true,
            cancelButtonText: 'Cancel',
            cancelButtonColor: '#6b7280'
          });
          if (!confirmation.isConfirmed) {
            return;
          }

          const originalText = btn.textContent;
          btn.disabled = true;
          btn.textContent = 'Restoring...';
          const row = btn.closest('tr');

          try {
            const response = await fetch('../adminbackend/unarchive_job.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ jobId })
            });
            const result = await response.json().catch(() => ({ success: false, message: 'Invalid server response.' }));
            if (!response.ok || !result.success) {
              throw new Error(result.message || 'Failed to restore job.');
            }
            if (row) {
              row.remove();
            }
            const remainingRows = archiveJobsResults.querySelectorAll('tbody tr');
            if (!remainingRows.length) {
              archiveJobsResults.innerHTML = '<p>No archived jobs available.</p>';
            }
            Swal.fire({
              icon: 'success',
              title: 'Job restored',
              text: result.message || 'The job was moved back to active listings.',
              confirmButtonColor: '#2563eb',
              timer: 2200,
              showConfirmButton: false
            }).then(() => window.location.reload());
          } catch (error) {
            Swal.fire({
              icon: 'error',
              title: 'Restore failed',
              text: error.message || 'Unable to restore this job.',
              confirmButtonColor: '#2563eb'
            });
            btn.disabled = false;
            btn.textContent = originalText;
          }
        });
      });
    }

    if (archiveFilterForm) {
      archiveFilterForm.addEventListener('submit', event => {
        event.preventDefault();
        loadArchivedJobs();
      });
    }

    if (archiveResetFilters && archiveFilterForm) {
      archiveResetFilters.addEventListener('click', () => {
        archiveFilterForm.reset();
        loadArchivedJobs();
      });
    }

    window.addEventListener('click', function(event) {
      if (event.target === addJobModal) {
        closeJobModal();
      }
      if (event.target === archiveJobsModal) {
        closeArchiveModal();
      }
    });

    document.querySelectorAll('.edit-job-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        populateJobForm(btn.dataset);
        openJobModal('edit');
      });
    });

    document.querySelectorAll('.view-job-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        populateJobForm(btn.dataset);
        openJobModal('view');
      });
    });

    document.querySelectorAll('.archive-job-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        const jobId = btn.dataset.jobId;
        if (!jobId) {
          return;
        }
        const confirmation = await Swal.fire({
          icon: 'warning',
          title: 'Archive job posting?',
          text: 'This will move the job to archived listings.',
          confirmButtonText: 'Yes, archive',
          confirmButtonColor: '#dc2626',
          showCancelButton: true,
          cancelButtonText: 'Cancel',
          cancelButtonColor: '#6b7280'
        });
        if (!confirmation.isConfirmed) {
          return;
        }

        const row = btn.closest('tr');
        btn.disabled = true;
        try {
          const response = await fetch('../adminbackend/archive_job.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ jobId })
          });
          const result = await response.json().catch(() => ({ success: false, message: 'Invalid server response.' }));
          if (!response.ok || !result.success) {
            throw new Error(result.message || 'Failed to archive job.');
          }
          if (row) {
            row.remove();
          }
          Swal.fire({
            icon: 'success',
            title: 'Job archived',
            text: result.message || 'The job was moved to archived listings.',
            confirmButtonColor: '#2563eb',
            timer: 2000,
            showConfirmButton: false
          });
        } catch (error) {
          Swal.fire({
            icon: 'error',
            title: 'Archive failed',
            text: error.message || 'Unable to archive this job.',
            confirmButtonColor: '#2563eb'
          });
        } finally {
          btn.disabled = false;
        }
      });
    });
  </script>
  <script>
    (function() {
      const flashPayload = <?php echo json_encode($flashPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
      if (!flashPayload) {
        return;
      }
      const icon = flashPayload.status === 'success' ? 'success' : 'error';
      Swal.fire({
        icon,
        title: icon === 'success' ? 'Success' : 'Error',
        text: flashPayload.message,
        confirmButtonColor: '#2563eb',
        timer: icon === 'success' ? 2600 : undefined,
        showConfirmButton: icon !== 'success',
      });
    })();
  </script>
</body>
</html>