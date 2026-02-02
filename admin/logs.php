<?php
include '../admin/sidebar.php';
require_once __DIR__ . '/../database.php';

$allowedRanges = ['7', '30', 'year', 'all'];
$selectedRange = isset($_GET['range']) ? $_GET['range'] : '7';
if (!in_array($selectedRange, $allowedRanges, true)) {
    $selectedRange = '7';
}

$searchQuery = trim($_GET['search'] ?? '');
$logs = [];
$logError = '';
$flashPayload = null;

$perPage = 10;
$currentPage = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($currentPage - 1) * $perPage;

function determineRangeStart(string $range): string
{
    if ($range === 'year') {
        $date = new DateTime('first day of January ' . date('Y'));
        $date->setTime(0, 0, 0);
        return $date->format('Y-m-d H:i:s');
    }
    if ($range === 'all') {
        return '';
    }
    $days = (int) $range;
    $date = new DateTime("-{$days} days");
    return $date->format('Y-m-d H:i:s');
}

function resolveActionLabel(string $action): string
{
    $action = trim($action);
    if ($action === '') {
        return 'Action';
    }
    $firstWord = strtok($action, ' ');
    return $firstWord ? ucfirst(strtolower($firstWord)) : 'Action';
}

function actionContains(string $action, string $needle): bool
{
    return stripos($action, $needle) !== false;
}

function resolveActionClass(string $action): string
{
    $lower = strtolower($action);
    if (actionContains($lower, 'login') || actionContains($lower, 'created') || actionContains($lower, 'restored')) {
        return 'success';
    }
    if (actionContains($lower, 'logout') || actionContains($lower, 'deleted')) {
        return 'danger';
    }
    if (actionContains($lower, 'update') || actionContains($lower, 'archived')) {
        return 'warning';
    }
    return 'neutral';
}

function formatAdminName(array $row): string
{
    $first = $row['user_profile_first_name'] ?? '';
    $middle = $row['user_profile_middle_name'] ?? '';
    $last = $row['user_profile_last_name'] ?? '';
    $fullName = trim($first . ' ' . ($middle ? $middle . ' ' : '') . $last);
    if ($fullName !== '') {
        return $fullName;
    }
    if (!empty($row['user_email'])) {
        return $row['user_email'];
    }
    return 'Admin #' . ($row['admin_id'] ?? '—');
}

$whereClauses = [];
$params = [];
$types = '';
$rangeStart = determineRangeStart($selectedRange);
if ($rangeStart !== '') {
    $whereClauses[] = 'al.created_at >= ?';
    $params[] = $rangeStart;
    $types .= 's';
}

if ($searchQuery !== '') {
    $whereClauses[] = '(al.action LIKE ? OR u.user_email LIKE ? OR up.user_profile_first_name LIKE ? OR up.user_profile_last_name LIKE ?)';
    $like = '%' . $searchQuery . '%';
    for ($i = 0; $i < 4; $i++) {
        $params[] = $like;
        $types .= 's';
    }
}

$logSql = "SELECT
            al.log_id,
            al.admin_id,
            al.action,
            al.created_at,
            u.user_email,
            up.user_profile_first_name,
            up.user_profile_middle_name,
            up.user_profile_last_name
        FROM admin_logs al
        LEFT JOIN user u ON al.admin_id = u.user_id
        LEFT JOIN user_profile up ON u.user_id = up.user_id";

if ($whereClauses) {
    $logSql .= ' WHERE ' . implode(' AND ', $whereClauses);
}

$logSqlCount = "SELECT COUNT(*) AS total FROM admin_logs al LEFT JOIN user u ON al.admin_id = u.user_id LEFT JOIN user_profile up ON u.user_id = up.user_id";
if ($whereClauses) {
    $logSqlCount .= ' WHERE ' . implode(' AND ', $whereClauses);
}

$totalRows = 0;
$totalPages = 1;
$countStmt = $conn->prepare($logSqlCount);
if ($countStmt) {
    if ($types !== '') {
        $countStmt->bind_param($types, ...$params);
    }
    if ($countStmt->execute()) {
        $countResult = $countStmt->get_result();
        if ($countResult) {
            $totalRows = (int) $countResult->fetch_assoc()['total'];
            $totalPages = max(1, (int) ceil($totalRows / $perPage));
        }
    }
    $countStmt->close();
}

$logSql .= ' ORDER BY al.created_at DESC LIMIT ? OFFSET ?';

$logStmt = $conn->prepare($logSql);
if ($logStmt) {
    $bindTypes = $types . 'ii';
    $bindValues = $params;
    $bindValues[] = $perPage;
    $bindValues[] = $offset;
    $logStmt->bind_param($bindTypes, ...$bindValues);

    if ($logStmt->execute()) {
        $result = $logStmt->get_result();
        if ($result) {
            $logs = $result->fetch_all(MYSQLI_ASSOC);
        } else {
            $logError = 'Unable to fetch audit logs.';
        }
    } else {
        $logError = 'Failed to execute audit log query.';
    }
    $logStmt->close();
} else {
    $logError = 'Failed to prepare audit log query.';
}

if ($logError !== '') {
    $flashPayload = [
        'status' => 'error',
        'message' => $logError,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Audit Logs</title>
    <link rel="stylesheet" href="../admin/styles.css">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/vendor/sweetalert2/sweetalert2.min.css">
    <script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
</head>
<body class="admin-page">
	<?php renderAdminSidebar(); ?>
	<main class="content">
		<div class="content-header">
			<div>
				<h1>Audit Logs</h1>
				<p class="lead">Review recent admin activity and security events.</p>
			</div>
		</div>

		<section class="panel">
            <div class="panel-header">
                <h2>Recent Activity</h2>
                <form class="filter-bar filter-bar-inline" style="margin:0;" method="GET">
					<div class="filter-group">
						<label for="logRange">Date Range</label>
						<select id="logRange" name="range">
							<option value="7" <?php echo $selectedRange === '7' ? 'selected' : ''; ?>>Last 7 days</option>
							<option value="30" <?php echo $selectedRange === '30' ? 'selected' : ''; ?>>Last 30 days</option>
							<option value="year" <?php echo $selectedRange === 'year' ? 'selected' : ''; ?>>This year</option>
							<option value="all" <?php echo $selectedRange === 'all' ? 'selected' : ''; ?>>All time</option>
						</select>
					</div>
					<div class="filter-group">
						<label for="logSearch">Search</label>
						<input type="text" id="logSearch" name="search" placeholder="Find by user or action" value="<?php echo htmlspecialchars($searchQuery); ?>" />
					</div>
					<div class="filter-actions">
						<button type="submit" class="btn btn-primary">Apply</button>
						<a class="btn btn-outline" href="logs.php">Reset</a>
					</div>
				</form>
			</div>

			<div class="table-wrapper">
                <table>
					<thead>
						<tr>
							<th>Timestamp</th>
							<th>User</th>
							<th>Action</th>
							<th>Details</th>
						</tr>
					</thead>
					<tbody>
                        <?php if (count($logs) === 0): ?>
							<tr>
								<td colspan="4" class="empty-state">
									<p>No audit logs match the selected filters.</p>
								</td>
							</tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
								<tr>
									<td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($log['created_at']))); ?></td>
									<td><?php echo htmlspecialchars(formatAdminName($log)); ?></td>
									<td>
										<span class="status-pill <?php echo resolveActionClass($log['action']); ?>">
											<?php echo htmlspecialchars(resolveActionLabel($log['action'])); ?>
										</span>
									</td>
									<td><?php echo htmlspecialchars($log['action']); ?></td>
								</tr>
							<?php endforeach; ?>
                        <?php endif; ?>
					</tbody>
				</table>
			</div>
            <?php if ($totalPages > 1): ?>
            <?php
                $maxVisiblePages = 3;
                $startPage = max(1, $currentPage - intdiv($maxVisiblePages, 2));
                $endPage = min($totalPages, $startPage + $maxVisiblePages - 1);
                if (($endPage - $startPage + 1) < $maxVisiblePages) {
                    $startPage = max(1, $endPage - $maxVisiblePages + 1);
                }
                $queryParams = $_GET;
                $queryParams['range'] = $selectedRange;
                if ($searchQuery !== '') {
                    $queryParams['search'] = $searchQuery;
                }
                $buildLogsUrl = function(int $page) use ($queryParams) {
                    $params = $queryParams;
                    $params['page'] = $page;
                    return '?' . http_build_query($params);
                };
            ?>
            <nav class="pagination-nav" aria-label="Logs pagination">
                <ul class="pagination-list">
                    <li class="page-item<?php echo $currentPage === 1 ? ' disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo $currentPage === 1 ? '#' : $buildLogsUrl(1); ?>" aria-label="First page">&lt;&lt;</a>
                    </li>
                    <li class="page-item<?php echo $currentPage === 1 ? ' disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo $currentPage === 1 ? '#' : $buildLogsUrl($currentPage - 1); ?>" aria-label="Previous page">&lt;</a>
                    </li>
                    <?php for ($page = $startPage; $page <= $endPage; $page++): ?>
                    <li class="page-item<?php echo $page === $currentPage ? ' active' : ''; ?>">
                        <a class="page-link" href="<?php echo $buildLogsUrl($page); ?>"><?php echo $page; ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item<?php echo $currentPage === $totalPages ? ' disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo $currentPage === $totalPages ? '#' : $buildLogsUrl($currentPage + 1); ?>" aria-label="Next page">&gt;</a>
                    </li>
                    <li class="page-item<?php echo $currentPage === $totalPages ? ' disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo $currentPage === $totalPages ? '#' : $buildLogsUrl($totalPages); ?>" aria-label="Last page">&gt;&gt;</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
		</section>
    </main>
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
            });
        })();
    </script>
</body>
</html>