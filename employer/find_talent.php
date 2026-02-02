<?php
include '../database.php'; // make sure you have this

// Get search inputs
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$skill = isset($_GET['skill']) ? trim($_GET['skill']) : '';
$experience = isset($_GET['experience']) ? trim($_GET['experience']) : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';

// Base SQL
$sql = "SELECT u.user_id, up.user_profile_first_name, up.user_profile_last_name, 
               up.user_profile_photo, up.user_profile_email_address, up.user_profile_contact_no
        FROM user u
        INNER JOIN user_profile up ON u.user_id = up.user_id
        WHERE u.user_type_id = 2"; // assuming user_type_id 2 = employee

$params = [];
$types = "";

// Apply filters dynamically
if (!empty($keyword)) {
    $sql .= " AND (up.user_profile_first_name LIKE ? OR up.user_profile_last_name LIKE ? OR up.user_profile_email_address LIKE ?)";
    $like = "%$keyword%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

if (!empty($skill)) {
    $sql .= " AND up.user_profile_skills LIKE ?";
    $params[] = "%$skill%";
    $types .= "s";
}

if (!empty($experience)) {
    $sql .= " AND up.user_profile_experience >= ?";
    $params[] = (int)$experience;
    $types .= "i";
}

if (!empty($location)) {
    $sql .= " AND up.user_profile_location LIKE ?";
    $params[] = "%$location%";
    $types .= "s";
}

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<h2>Preview Talent Pool</h2>

<div class="talent-grid">
<?php if ($result->num_rows > 0): ?>
  <?php while ($row = $result->fetch_assoc()): 
    $firstName = $row['user_profile_first_name'] ?? '';
    $photoPath = $row['user_profile_photo'] ?? '';
    if (!empty($photoPath) && file_exists('../uploads/profile_pics/' . $photoPath)) {
      $avatarSrc = '../uploads/profile_pics/' . htmlspecialchars($photoPath);
    } else {
      // Default avatar with person silhouette (Facebook-style)
      $avatarSrc = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 150 150'%3E%3Crect fill='%23e4e6eb' width='150' height='150'/%3E%3Ccircle cx='75' cy='52' r='30' fill='%23bcc0c4'/%3E%3Cellipse cx='75' cy='127' rx='49' ry='38' fill='%23bcc0c4'/%3E%3C/svg%3E";
    }
  ?>
    <div class="talent-card">
      <img src="<?php echo $avatarSrc; ?>" alt="Profile" class="avatar">
      <h4><?php echo htmlspecialchars($row['user_profile_first_name'] . ' ' . $row['user_profile_last_name']); ?></h4>
      <p><i class="fa-solid fa-briefcase"></i> 5+ years experience</p>
      <p><i class="fa-solid fa-location-dot"></i> Remote</p>
      <div class="skills">
        <span>React</span> <span>UI/UX</span> <span>Analytics</span>
      </div>
    </div>
  <?php endwhile; ?>
<?php else: ?>
  <div class="no-results">
    <p>No matching talents found. Try adjusting your filters.</p>
  </div>
<?php endif; ?>
</div>
