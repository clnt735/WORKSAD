<?php
require_once '../database.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';

$where = [];

// Archived by default; allow switching if explicitly requested
if ($status === 'active') {
    $where[] = "jp.job_status_id = 1";
} elseif ($status === 'inactive' || $status === '') {
    $where[] = "jp.job_status_id = 4";
} else {
    $where[] = "jp.job_status_id = 4";
}

if ($search !== '') {
    $like = '%' . $conn->real_escape_string($search) . '%';
    $where[] = "(jp.job_description LIKE '" . $like . "' OR jp.requirements LIKE '" . $like . "')";
}

if ($category !== '') {
    $where[] = 'jp.job_category_id = ' . intval($category);
}

$whereSql = count($where) ? ' WHERE ' . implode(' AND ', $where) : '';

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

$query = "SELECT jp.job_post_id, jp.job_description, jp.vacancies, jp.budget, jp.created_at,
                 jc.job_category_name AS job_category,
                 js.job_status_name AS job_status,
                 up.user_profile_first_name, up.user_profile_middle_name, up.user_profile_last_name,
                 (SELECT COUNT(*) FROM job_likes jl WHERE jl.job_post_id = jp.job_post_id) AS like_count
          FROM job_post jp
          LEFT JOIN job_category jc ON jp.job_category_id = jc.job_category_id
          LEFT JOIN job_status js ON jp.job_status_id = js.job_status_id
          LEFT JOIN user u ON jp.user_id = u.user_id
          LEFT JOIN user_profile up ON u.user_id = up.user_id" .
          $whereSql . $orderBy;

$result = $conn->query($query);

if ($result === false) {
    echo "<p class='error-message'>Unable to load archived jobs.</p>";
} elseif ($result->num_rows === 0) {
    echo "<p>No archived jobs available.</p>";
} else {
    echo "<div class='table-responsive'>";
    echo "<table class='table'>";
    echo "<thead><tr><th>ID</th><th>Category</th><th>Description</th><th>Posted By</th><th>Archived On</th><th>Actions</th></tr></thead>";
    echo "<tbody>";
    while ($row = $result->fetch_assoc()) {
        $first = $row['user_profile_first_name'] ?? '';
        $middle = $row['user_profile_middle_name'] ?? '';
        $last = $row['user_profile_last_name'] ?? '';
        $fullName = trim($first . ' ' . ($middle ? $middle . ' ' : '') . $last);
        $archivedDate = $row['created_at'] ? date('M d, Y', strtotime($row['created_at'])) : '—';
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['job_post_id']) . '</td>';
        echo '<td>' . htmlspecialchars($row['job_category']) . '</td>';
        echo '<td>' . htmlspecialchars($row['job_description']) . '</td>';
        echo '<td>' . htmlspecialchars($fullName) . '</td>';
        echo '<td>' . htmlspecialchars($archivedDate) . '</td>';
        echo '<td><button type="button" class="btn btn-primary restore-job-btn" data-job-id="' . htmlspecialchars($row['job_post_id']) . '">Restore</button></td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

$conn->close();
?>
