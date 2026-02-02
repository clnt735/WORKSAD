<?php
require_once '../database.php';
require_once '../admin/sidebar.php';

$statusOptions = [
    'all' => 'All Statuses',
    'pending' => 'Pending',
    'reviewed' => 'Reviewed',
    'resolved' => 'Resolved',
];

$typeOptions = [
    'all' => 'All Types',
    'spam' => 'Spam',
    'harassment' => 'Harassment',
    'other' => 'Other',
];

$selectedStatus = isset($_GET['status']) && array_key_exists($_GET['status'], $statusOptions)
    ? $_GET['status']
    : 'all';
$selectedType = isset($_GET['type']) && array_key_exists($_GET['type'], $typeOptions)
    ? $_GET['type']
    : 'all';
$searchTerm = trim((string) ($_GET['search'] ?? ''));
$sort = isset($_GET['sort']) && in_array($_GET['sort'], ['newest', 'oldest', 'status', 'reporter'], true)
  ? $_GET['sort']
  : 'newest';

function fetch_single_value(mysqli $conn, string $sql): int
{
    $value = 0;
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_row();
        if ($row) {
            $value = (int) $row[0];
        }
        $result->free();
    }
    return $value;
}

function bind_statement_params(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types === '' || empty($params)) {
        return;
    }
    $stmt->bind_param($types, ...$params);
}

$totalReports = fetch_single_value($conn, 'SELECT COUNT(*) FROM report');
$pendingReports = fetch_single_value($conn, "SELECT COUNT(*) FROM report WHERE status = 'pending'");
$resolvedReports = fetch_single_value($conn, "SELECT COUNT(*) FROM report WHERE status = 'resolved'");
$bannedUsers = fetch_single_value($conn, "SELECT COUNT(DISTINCT reported_user) FROM report WHERE status = 'resolved'");

$reports = [];
$tableError = null;
$filterClauses = [];
$filterTypes = '';
$filterParams = [];

if ($selectedStatus !== 'all') {
    $filterClauses[] = 'r.status = ?';
    $filterTypes .= 's';
    $filterParams[] = $selectedStatus;
}

if ($selectedType !== 'all') {
    $filterClauses[] = 'LOWER(r.reason) LIKE ?';
    $filterTypes .= 's';
    $filterParams[] = '%' . strtolower($selectedType) . '%';
}

if ($searchTerm !== '') {
  $filterClauses[] = '(
    r.reason LIKE ?
    OR COALESCE(CONCAT_WS(" ", reporter.user_profile_first_name, reporter.user_profile_last_name), "") LIKE ?
    OR COALESCE(CONCAT_WS(" ", reported.user_profile_first_name, reported.user_profile_last_name), "") LIKE ?
  )';
    $likeTerm = '%' . $searchTerm . '%';
    $filterTypes .= 'sss';
    $filterParams[] = $likeTerm;
    $filterParams[] = $likeTerm;
    $filterParams[] = $likeTerm;
}

$whereSql = $filterClauses ? 'WHERE ' . implode(' AND ', $filterClauses) : '';

switch ($sort) {
  case 'oldest':
    $orderClause = 'ORDER BY r.created_at ASC';
    break;
  case 'status':
    $orderClause = 'ORDER BY r.status ASC, r.created_at DESC';
    break;
  case 'reporter':
    $orderClause = 'ORDER BY reporter_name ASC, r.created_at DESC';
    break;
  case 'newest':
  default:
    $orderClause = 'ORDER BY r.created_at DESC';
    break;
}

$reportSql = 'SELECT
        r.report_id,
        r.reason,
        r.status,
        r.created_at,
        CONCAT_WS(" ", reporter.user_profile_first_name, reporter.user_profile_last_name) AS reporter_name,
        CONCAT_WS(" ", reported.user_profile_first_name, reported.user_profile_last_name) AS reported_name
    FROM report r
    LEFT JOIN user_profile reporter ON reporter.user_id = r.reported_by
    LEFT JOIN user_profile reported ON reported.user_id = r.reported_user
    ' . $whereSql . '
  ' . $orderClause;

$stmt = $conn->prepare($reportSql);
if ($stmt) {
    bind_statement_params($stmt, $filterTypes, $filterParams);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $reports[] = $row;
        }
        $result->free();
    } else {
        $tableError = 'Unable to load reports.';
    }
    $stmt->close();
} else {
    $tableError = 'Unable to load reports.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reported Content</title>
  <link rel="stylesheet" href="../admin/styles.css">
  <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
</head>
<body class="admin-page reports-page">
  <?php renderAdminSidebar(); ?>
  <main class="content">
    <div class="header">
      <div>
        <h1>Reported Content</h1>
        <p class="lead">Monitor and resolve user-generated reports.</p>
      </div>
    </div>

    <div class="stats">
      <div class="card">
        <h3>Total Reports</h3>
        <p><?php echo number_format($totalReports); ?></p>
      </div>
      <div class="card">
        <h3>Pending Reports</h3>
        <p><?php echo number_format($pendingReports); ?></p>
      </div>
      <div class="card">
        <h3>Removed Content</h3>
        <p><?php echo number_format($resolvedReports); ?></p>
      </div>
      <div class="card">
        <h3>Banned Users</h3>
        <p><?php echo number_format($bannedUsers); ?></p>
      </div>
    </div>

    <div class="filters-section">
      <form class="search-filter-form reports-filter-form" method="GET">
        <div class="search-box">
          <input
            type="text"
            name="search"
            placeholder="Search reports by keyword"
            value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>"
          >
        </div>
        <select class="filter-select" name="type">
          <?php foreach ($typeOptions as $key => $label): ?>
            <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $key === $selectedType ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <select class="filter-select" name="status">
          <?php foreach ($statusOptions as $key => $label): ?>
            <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $key === $selectedStatus ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <select class="filter-select" name="sort">
          <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
          <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
          <option value="status" <?php echo $sort === 'status' ? 'selected' : ''; ?>>Status</option>
          <option value="reporter" <?php echo $sort === 'reporter' ? 'selected' : ''; ?>>Reporter A-Z</option>
        </select>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-filter"></i>
          Apply Filters
        </button>
      </form>
    </div>

    <?php if ($tableError): ?>
      <div class="empty-state">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <p><?php echo htmlspecialchars($tableError, ENT_QUOTES, 'UTF-8'); ?></p>
      </div>
    <?php elseif (empty($reports)): ?>
      <div class="empty-state">
        <i class="fa-solid fa-circle-check"></i>
        <p>No Reports Found</p>
        <p>There are no reports matching your filters.</p>
      </div>
    <?php else: ?>
      <div class="table-card">
        <table class="table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Reporter</th>
              <th>Reported User</th>
              <th>Reason</th>
              <th>Status</th>
              <th>Created At</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($reports as $report): ?>
              <tr>
                <td>#<?php echo htmlspecialchars($report['report_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($report['reporter_name'] ?: 'Unknown user', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($report['reported_name'] ?: 'Unknown user', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($report['reason'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><span class="badge"><?php echo htmlspecialchars(ucfirst($report['status']), ENT_QUOTES, 'UTF-8'); ?></span></td>
                <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($report['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>