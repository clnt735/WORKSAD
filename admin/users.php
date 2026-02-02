<?php 
include '../database.php';
include '../admin/sidebar.php';

$searchUser = isset($_GET['search']) ? trim($_GET['search']) : '';
$userTypeFilter = isset($_GET['user_type']) ? trim($_GET['user_type']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';

$flashPayload = null;
if (isset($_GET['flash_status'])) {
    $status = $_GET['flash_status'] === 'success' ? 'success' : 'error';
    $flashMessage = $_GET['flash_message'] ?? ($status === 'success' ? 'User updated successfully.' : 'Unable to complete the request.');
    $flashPayload = [
        'status' => $status,
        'message' => $flashMessage,
    ];
}

$userTypeOptions = [];
$statusOptions = [];
$pendingStatusId = null;

if ($conn) {
    if ($typeResult = mysqli_query($conn, "SELECT user_type_id, user_type_name FROM user_type ORDER BY user_type_name")) {
        while ($row = mysqli_fetch_assoc($typeResult)) {
            $userTypeOptions[] = $row;
        }
    }

    if ($statusResult = mysqli_query($conn, "SELECT user_status_id, user_status_description FROM user_status ORDER BY user_status_description")) {
        while ($row = mysqli_fetch_assoc($statusResult)) {
            $statusOptions[] = $row;
            $desc = strtolower(trim($row['user_status_description'] ?? ''));
            if ($pendingStatusId === null && in_array($desc, ['pending', 'pending approval', 'pending verification', 'unverified'], true)) {
                $pendingStatusId = (int) $row['user_status_id'];
            }
        }
    }
}

function buildUsersPageUrl($page) {
    $params = $_GET;
    unset($params['flash_status'], $params['flash_message']);
    $params['page'] = $page;
    return '?' . http_build_query($params) . '#usersTable';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management</title>
    <link rel="stylesheet" href="../admin/styles.css">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/vendor/sweetalert2/sweetalert2.min.css">
    <script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
</head>
<body class="admin-page users-page">
  <?php renderAdminSidebar(); ?>
  <main class="content">
                <div class="content-header">
                        <div>
                            <h1>Users Management</h1>
                            <p class="lead">Monitor platform members and update existing accounts.</p>
                        </div>
                </div>

        <?php
        // Initialize counts
        $totalCount = 0;
        $activeCount = 0;
        $freelancerCount = 0;
        $clientCount = 0;
        $pendingCount = 0;

        // Check if connection exists
        if ($conn) {
            // Fetch user statistics safely
            if ($totalUsers = mysqli_query($conn, "SELECT COUNT(*) as total FROM user")) {
                $totalCount = mysqli_fetch_assoc($totalUsers)['total'];
            }
            
            if ($activeUsers = mysqli_query($conn, "SELECT COUNT(*) as active FROM user WHERE user_status_id = 1")) {
                $activeCount = mysqli_fetch_assoc($activeUsers)['active'];
            }
            
            if ($freelancers = mysqli_query($conn, "SELECT COUNT(*) as freelancers FROM user WHERE user_type_id = 2")) {
                $freelancerCount = mysqli_fetch_assoc($freelancers)['freelancers'];
            }
            
            if ($clients = mysqli_query($conn, "SELECT COUNT(*) as clients FROM user WHERE user_type_id = 3")) {
                $clientCount = mysqli_fetch_assoc($clients)['clients'];
            }

            if ($pendingStatusId !== null && $pending = mysqli_query($conn, "SELECT COUNT(*) as pending FROM user WHERE user_status_id = " . (int) $pendingStatusId)) {
                $pendingCount = mysqli_fetch_assoc($pending)['pending'];
            }
        }
        ?>

        <div class="dashboard-cards">
            <div class="card">
                <h3>Total Users</h3>
                <p><?php echo $totalCount; ?></p>
            </div>
            <div class="card">
                <h3>Active Users</h3>
                <p><?php echo $activeCount; ?></p>
            </div>
            <div class="card">
                <h3>Pending Verification</h3>
                <p><?php echo $pendingCount; ?></p>
            </div>
            <div class="card">
                <h3>Applicants</h3>
                <p><?php echo $freelancerCount; ?></p>
            </div>
            <div class="card">
                <h3>Employers</h3>
                <p><?php echo $clientCount; ?></p>
            </div>
        </div>

        <div class="panel">
          <div class="panel-header">
            <h2>Directory</h2>
          </div>
                    <div class="filters-section">
                        <form class="search-filter-form" method="GET">
                            <div class="search-box">
                                <input type="text" name="search" placeholder="Search users by name or email" value="<?php echo htmlspecialchars($searchUser); ?>">
                            </div>
                            <select class="filter-select" name="user_type">
                                <option value="">All Roles</option>
                                <?php foreach ($userTypeOptions as $type): ?>
                                    <option value="<?php echo $type['user_type_id']; ?>" <?php echo $userTypeFilter == $type['user_type_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['user_type_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select class="filter-select" name="status">
                                <option value="">All Status</option>
                                <?php foreach ($statusOptions as $statusRow): ?>
                                    <option value="<?php echo $statusRow['user_status_id']; ?>" <?php echo $statusFilter == $statusRow['user_status_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($statusRow['user_status_description']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select class="filter-select" name="sort">
                                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name A-Z</option>
                                <option value="status" <?php echo $sort === 'status' ? 'selected' : ''; ?>>Status</option>
                            </select>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i>
                                Apply Filters
                            </button>
                        </form>
                    </div>
            <div class="table-wrapper" id="usersTable">
                <table class="table">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Full Name</th>
                    <th>Email Address</th>
                    <th>User Type</th>
                    <th>Status</th>
                    <th>User Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($conn) {
                    $where = [];
                    if ($searchUser !== '') {
                        $like = '%' . $conn->real_escape_string($searchUser) . '%';
                        $fullNameExpr = "TRIM(CONCAT_WS(' ', COALESCE(up.user_profile_first_name, ''), COALESCE(up.user_profile_middle_name, ''), COALESCE(up.user_profile_last_name, '')) )";
                        $where[] = "( $fullNameExpr LIKE '" . $like . "' OR u.user_email LIKE '" . $like . "' OR up.user_profile_first_name LIKE '" . $like . "' OR up.user_profile_middle_name LIKE '" . $like . "' OR up.user_profile_last_name LIKE '" . $like . "')";
                    }
                    if ($userTypeFilter !== '') {
                        $where[] = 'u.user_type_id = ' . intval($userTypeFilter);
                    }
                    if ($statusFilter !== '') {
                        $where[] = 'u.user_status_id = ' . intval($statusFilter);
                    }

                    $whereSql = count($where) ? (' WHERE ' . implode(' AND ', $where)) : '';

                    switch ($sort) {
                        case 'oldest':
                            $orderBy = ' ORDER BY u.user_created_at ASC';
                            break;
                        case 'name':
                            $orderBy = ' ORDER BY up.user_profile_first_name ASC, up.user_profile_last_name ASC';
                            break;
                        case 'status':
                            $orderBy = ' ORDER BY us.user_status_description ASC';
                            break;
                        case 'newest':
                        default:
                            $orderBy = ' ORDER BY u.user_created_at DESC';
                            break;
                    }

                    $query = "SELECT 
                                u.user_id, 
                                u.user_email, 
                                u.user_created_at,
                                u.user_type_id,
                                u.user_status_id,
                                up.user_profile_first_name, 
                                up.user_profile_middle_name, 
                                up.user_profile_last_name,
                                up.user_profile_dob,
                                up.user_profile_gender,
                                up.user_profile_contact_no,
                                ut.user_type_name,
                                us.user_status_description
                            FROM user u
                            LEFT JOIN user_profile up ON u.user_id = up.user_id
                            LEFT JOIN user_type ut ON u.user_type_id = ut.user_type_id
                            LEFT JOIN user_status us ON u.user_status_id = us.user_status_id" .
                            $whereSql . $orderBy;
                    
                    $perPage = 10;
                    $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                    $offset = ($currentPage - 1) * $perPage;

                    $countQuery = "SELECT COUNT(*) AS total FROM user u"
                               . " LEFT JOIN user_profile up ON u.user_id = up.user_id"
                               . " LEFT JOIN user_type ut ON u.user_type_id = ut.user_type_id"
                               . " LEFT JOIN user_status us ON u.user_status_id = us.user_status_id"
                               . $whereSql;
                    $totalUsersFiltered = 0;
                    $totalPages = 1;
                    if ($countResult = mysqli_query($conn, $countQuery)) {
                        $totalUsersFiltered = (int) mysqli_fetch_assoc($countResult)['total'];
                        $totalPages = max(1, (int) ceil($totalUsersFiltered / $perPage));
                    }

                    $query .= " LIMIT $perPage OFFSET $offset";

                    $result = mysqli_query($conn, $query);

                    if ($result) {
                        while($row = mysqli_fetch_assoc($result)) {
                            $fullName = trim($row['user_profile_first_name'] . ' ' . 
                                      ($row['user_profile_middle_name'] ? $row['user_profile_middle_name'] . ' ' : '') . 
                                      $row['user_profile_last_name']);
                            
                            $statusClass = strtolower($row['user_status_description']) === 'active' ? 'active' : 'inactive';
                            ?>
                            <tr>
                                <td><?php echo $row['user_id']; ?></td>
                                <td><?php echo htmlspecialchars($fullName); ?></td>
                                <td><?php echo htmlspecialchars($row['user_email']); ?></td>
                                <td><?php echo htmlspecialchars($row['user_type_name']); ?></td>
                                <td><span class="status-pill <?php echo $statusClass === 'active' ? 'success' : 'warning'; ?>"><?php echo htmlspecialchars($row['user_status_description']); ?></span></td>
                                                                <?php
                                                                    $dataAttrs = sprintf(
                                                                        " data-user-id='%s' data-user-email='%s' data-user-type='%s' data-user-status='%s' data-first-name='%s' data-middle-name='%s' data-last-name='%s' data-dob='%s' data-gender='%s' data-contact='%s'",
                                                                        htmlspecialchars($row['user_id'], ENT_QUOTES),
                                                                        htmlspecialchars($row['user_email'], ENT_QUOTES),
                                                                        htmlspecialchars($row['user_type_id'], ENT_QUOTES),
                                                                        htmlspecialchars($row['user_status_id'], ENT_QUOTES),
                                                                        htmlspecialchars($row['user_profile_first_name'] ?? '', ENT_QUOTES),
                                                                        htmlspecialchars($row['user_profile_middle_name'] ?? '', ENT_QUOTES),
                                                                        htmlspecialchars($row['user_profile_last_name'] ?? '', ENT_QUOTES),
                                                                        htmlspecialchars($row['user_profile_dob'] ?? '', ENT_QUOTES),
                                                                        htmlspecialchars($row['user_profile_gender'] ?? '', ENT_QUOTES),
                                                                        htmlspecialchars($row['user_profile_contact_no'] ?? '', ENT_QUOTES)
                                                                    );
                                                                ?>
                                                                <td><?php echo $row['user_created_at']; ?></td>
                                                                <td class="table-actions">
                                                                    <button class="btn btn-outline view-user-btn"<?php echo $dataAttrs; ?>>View</button>
                                                                    <button class="btn btn-secondary edit-user-btn"<?php echo $dataAttrs; ?>>Edit</button>
                                                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo "<tr><td colspan='7'>Error: " . mysqli_error($conn) . "</td></tr>";
                    }
                } else {
                    echo "<tr><td colspan='7'>Database connection error</td></tr>";
                }
                ?>
            </tbody>
                </table>
                <?php if (isset($totalPages) && $totalPages > 1): ?>
                <?php
                    $maxVisiblePages = 3;
                    $startPage = max(1, $currentPage - intdiv($maxVisiblePages, 2));
                    $endPage = min($totalPages, $startPage + $maxVisiblePages - 1);
                    if (($endPage - $startPage + 1) < $maxVisiblePages) {
                        $startPage = max(1, $endPage - $maxVisiblePages + 1);
                    }
                ?>
                <nav class="pagination-nav" aria-label="Users pagination">
                    <ul class="pagination-list">
                        <li class="page-item<?php echo $currentPage === 1 ? ' disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $currentPage === 1 ? '#' : buildUsersPageUrl(1); ?>" aria-label="First page">&lt;&lt;</a>
                        </li>
                        <li class="page-item<?php echo $currentPage === 1 ? ' disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $currentPage === 1 ? '#' : buildUsersPageUrl($currentPage - 1); ?>" aria-label="Previous page">&lt;</a>
                        </li>
                        <?php for ($page = $startPage; $page <= $endPage; $page++): ?>
                        <li class="page-item<?php echo $page === $currentPage ? ' active' : ''; ?>">
                            <a class="page-link" href="<?php echo buildUsersPageUrl($page); ?>"><?php echo $page; ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item<?php echo $currentPage === $totalPages ? ' disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $currentPage === $totalPages ? '#' : buildUsersPageUrl($currentPage + 1); ?>" aria-label="Next page">&gt;</a>
                        </li>
                        <li class="page-item<?php echo $currentPage === $totalPages ? ' disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $currentPage === $totalPages ? '#' : buildUsersPageUrl($totalPages); ?>" aria-label="Last page">&gt;&gt;</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
          </div>
        </div>

                <!-- User Modal -->
        <div id="addUserModal" class="modal">
          <div class="modal-content">
            <button class="modal-close" type="button" aria-label="Close" onclick="closeModal()">&times;</button>
            <div class="modal-header">
                            <h2>User Details</h2>
            </div>
                                <form id="addUserForm" method="POST" action="../adminbackend/update_user.php">
                    <input type="hidden" name="userId" id="userId">
                    <!-- Account Information -->
                    <div class="form-group">
                        <label for="email">Email or Username</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                                                <input type="password" id="password" name="password">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="userType">User Type</label>
                            <select id="userType" name="userType" required>
                                <option value="">Select User Type</option>
                                <?php foreach ($userTypeOptions as $type): ?>
                                    <?php $typeId = isset($type['user_type_id']) ? (int) $type['user_type_id'] : 0; ?>
                                    <option value="<?php echo $typeId; ?>"><?php echo htmlspecialchars($type['user_type_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="userStatus">Status</label>
                            <select id="userStatus" name="userStatus" required>
                                <option value="">Select Status</option>
                                <?php foreach ($statusOptions as $statusRow): ?>
                                    <?php $statusId = isset($statusRow['user_status_id']) ? (int) $statusRow['user_status_id'] : 0; ?>
                                    <option value="<?php echo $statusId; ?>"><?php echo htmlspecialchars($statusRow['user_status_description']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Personal Information -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="firstName">First Name</label>
                            <input type="text" id="firstName" name="firstName" required>
                        </div>
                        <div class="form-group">
                            <label for="middleName">Middle Name</label>
                            <input type="text" id="middleName" name="middleName">
                        </div>
                        <div class="form-group">
                            <label for="lastName">Last Name</label>
                            <input type="text" id="lastName" name="lastName" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="dob">Date of Birth</label>
                            <input type="date" id="dob" name="dob">
                        </div>
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender">
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="contactNo">Contact Number</label>
                            <input type="tel" id="contactNo" name="contactNo">
                        </div>
                    </div>

                    <div class="modal-footer">
                      <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                                            <button type="submit" class="btn btn-primary" id="userModalSubmit" style="display:none;">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
            </main>

            <script>
                const modal = document.getElementById('addUserModal');
                const addUserForm = document.getElementById('addUserForm');
                const userModalSubmit = document.getElementById('userModalSubmit');
                const userIdInput = document.getElementById('userId');
                const modalHeader = modal.querySelector('.modal-header h2');
                const passwordInput = document.getElementById('password');

                const fieldRefs = {
                    email: document.getElementById('email'),
                    userType: document.getElementById('userType'),
                    userStatus: document.getElementById('userStatus'),
                    firstName: document.getElementById('firstName'),
                    middleName: document.getElementById('middleName'),
                    lastName: document.getElementById('lastName'),
                    dob: document.getElementById('dob'),
                    gender: document.getElementById('gender'),
                    contactNo: document.getElementById('contactNo')
                };

                const readonlyInputs = [
                    fieldRefs.email,
                    passwordInput,
                    fieldRefs.firstName,
                    fieldRefs.middleName,
                    fieldRefs.lastName,
                    fieldRefs.dob,
                    fieldRefs.contactNo
                ];

                const readonlySelects = [fieldRefs.userType, fieldRefs.userStatus, fieldRefs.gender];

                function setFormReadOnly(isReadOnly) {
                    readonlyInputs.forEach(input => {
                        if (!input) return;
                        input.readOnly = isReadOnly;
                    });
                    readonlySelects.forEach(select => {
                        if (!select) return;
                        select.disabled = isReadOnly;
                    });
                }

                function setPasswordRequirement(isRequired) {
                    if (passwordInput) {
                        passwordInput.required = isRequired;
                    }
                }

                function populateUserForm(data = {}) {
                    userIdInput.value = data.userId || '';
                    fieldRefs.email.value = data.userEmail || '';
                    fieldRefs.userType.value = data.userType || '';
                    fieldRefs.userStatus.value = data.userStatus || '';
                    fieldRefs.firstName.value = data.firstName || '';
                    fieldRefs.middleName.value = data.middleName || '';
                    fieldRefs.lastName.value = data.lastName || '';
                    fieldRefs.dob.value = data.dob || '';
                    fieldRefs.gender.value = data.gender || '';
                    fieldRefs.contactNo.value = data.contact || '';
                }

                function openModal(mode = 'view') {
                    modal.dataset.mode = mode;
                    if (mode === 'edit') {
                        modalHeader.textContent = 'Edit User';
                        userModalSubmit.textContent = 'Save Changes';
                        userModalSubmit.style.display = '';
                        addUserForm.action = '../adminbackend/update_user.php';
                        setFormReadOnly(false);
                        setPasswordRequirement(false);
                    } else {
                        modalHeader.textContent = 'View User';
                        userModalSubmit.style.display = 'none';
                        addUserForm.action = '#';
                        setFormReadOnly(true);
                        setPasswordRequirement(false);
                    }
                    modal.classList.add('show');
                }

                function closeModal() {
                    modal.classList.remove('show');
                    addUserForm.reset();
                    userIdInput.value = '';
                    addUserForm.action = '#';
                    modalHeader.textContent = 'User Details';
                    userModalSubmit.textContent = 'Save Changes';
                    userModalSubmit.style.display = 'none';
                    setFormReadOnly(true);
                    setPasswordRequirement(false);
                    if (passwordInput) {
                        passwordInput.value = '';
                    }
                }

                window.addEventListener('click', function(event) {
                    if (event.target === modal) {
                        closeModal();
                    }
                });

                document.querySelectorAll('.edit-user-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        populateUserForm(btn.dataset);
                        if (passwordInput) {
                            passwordInput.value = '';
                        }
                        openModal('edit');
                    });
                });

                document.querySelectorAll('.view-user-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        populateUserForm(btn.dataset);
                        if (passwordInput) {
                            passwordInput.value = '********';
                        }
                        openModal('view');
                    });
                });
            </script>
        </body>
        <script>
            (function() {
                const flashPayload = <?php echo json_encode($flashPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
                if (flashPayload) {
                    const icon = flashPayload.status === 'success' ? 'success' : 'error';
                    Swal.fire({
                        icon,
                        title: icon === 'success' ? 'Success' : 'Error',
                        text: flashPayload.message,
                        confirmButtonColor: '#2563eb',
                        timer: icon === 'success' ? 2600 : undefined,
                        showConfirmButton: icon !== 'success',
                    });
                }
            })();
        </script>
        </html>