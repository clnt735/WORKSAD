<?php 
include '../database.php';
include '../admin/sidebar.php';

// SUPER ADMIN ONLY ACCESS CHECK
if (!isSuperAdmin()) {
    header('Location: dashboard.php?error=access_denied');
    exit();
}

$searchAdmin = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';

$flashPayload = null;
if (isset($_GET['flash_status'])) {
    $status = $_GET['flash_status'] === 'success' ? 'success' : 'error';
    $flashMessage = $_GET['flash_message'] ?? ($status === 'success' ? 'Admin updated successfully.' : 'Unable to complete the request.');
    $flashPayload = [
        'status' => $status,
        'message' => $flashMessage,
    ];
}

$statusOptions = [];
if ($conn) {
    if ($statusResult = mysqli_query($conn, "SELECT user_status_id, user_status_description FROM user_status ORDER BY user_status_description")) {
        while ($row = mysqli_fetch_assoc($statusResult)) {
            $statusOptions[] = $row;
        }
    }
}

function buildAdminPageUrl($page) {
    $params = $_GET;
    unset($params['flash_status'], $params['flash_message']);
    $params['page'] = $page;
    return '?' . http_build_query($params) . '#adminsTable';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management</title>
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
        <h1>Admin Management</h1>
        <p class="lead">View and manage administrator accounts. Only accessible by Super Admins.</p>
      </div>
    </div>

    <?php
    // Initialize counts
    $totalAdmins = 0;
    $activeAdmins = 0;
    $disabledAdmins = 0;

    if ($conn) {
        // Total admins (user_type_id = 1)
        if ($result = mysqli_query($conn, "SELECT COUNT(*) as total FROM user WHERE user_type_id = 1")) {
            $totalAdmins = mysqli_fetch_assoc($result)['total'];
        }
        
        // Active admins (status = 1)
        if ($result = mysqli_query($conn, "SELECT COUNT(*) as active FROM user WHERE user_type_id = 1 AND user_status_id = 1")) {
            $activeAdmins = mysqli_fetch_assoc($result)['active'];
        }
        
        // Disabled admins (status = 2)
        if ($result = mysqli_query($conn, "SELECT COUNT(*) as disabled FROM user WHERE user_type_id = 1 AND user_status_id = 2")) {
            $disabledAdmins = mysqli_fetch_assoc($result)['disabled'];
        }
    }
    ?>

    <div class="dashboard-cards">
      <div class="card">
        <h3>Total Admins</h3>
        <p><?php echo $totalAdmins; ?></p>
      </div>
      <div class="card">
        <h3>Active Admins</h3>
        <p><?php echo $activeAdmins; ?></p>
      </div>
      <div class="card">
        <h3>Disabled Admins</h3>
        <p><?php echo $disabledAdmins; ?></p>
      </div>
    </div>

    <div class="panel">
      <div class="panel-header">
        <h2>Admin Directory</h2>
      </div>
      <div class="filters-section">
        <form class="search-filter-form" method="GET">
          <div class="search-box">
            <input type="text" name="search" placeholder="Search admins by email" value="<?php echo htmlspecialchars($searchAdmin); ?>">
          </div>
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
            <option value="email" <?php echo $sort === 'email' ? 'selected' : ''; ?>>Email A-Z</option>
          </select>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-filter"></i>
            Apply Filters
          </button>
        </form>
      </div>
      <div class="table-wrapper" id="adminsTable">
        <table class="table">
          <thead>
            <tr>
              <th>User ID</th>
              <th>Email</th>
              <th>Status</th>
              <th>Created At</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            if ($conn) {
                $where = ["u.user_type_id = 1"]; // Only admin users
                
                if ($searchAdmin !== '') {
                    $like = '%' . $conn->real_escape_string($searchAdmin) . '%';
                    $where[] = "u.user_email LIKE '" . $like . "'";
                }
                if ($statusFilter !== '') {
                    $where[] = 'u.user_status_id = ' . intval($statusFilter);
                }

                $whereSql = ' WHERE ' . implode(' AND ', $where);

                switch ($sort) {
                    case 'oldest':
                        $orderBy = ' ORDER BY u.user_created_at ASC';
                        break;
                    case 'email':
                        $orderBy = ' ORDER BY u.user_email ASC';
                        break;
                    default:
                        $orderBy = ' ORDER BY u.user_created_at DESC';
                        break;
                }

                // Pagination
                $perPage = 10;
                $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                $offset = ($currentPage - 1) * $perPage;

                // Count total
                $countSql = "SELECT COUNT(*) as total FROM user u $whereSql";
                $countResult = mysqli_query($conn, $countSql);
                $totalRows = $countResult ? mysqli_fetch_assoc($countResult)['total'] : 0;
                $totalPages = max(1, ceil($totalRows / $perPage));

                // Fetch admins
                $sql = "SELECT 
                    u.user_id,
                    u.user_email,
                    u.user_status_id,
                    us.user_status_description,
                    u.user_created_at
                FROM user u
                LEFT JOIN user_status us ON u.user_status_id = us.user_status_id
                $whereSql
                $orderBy
                LIMIT $perPage OFFSET $offset";

                $result = mysqli_query($conn, $sql);

                if ($result && mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $userId = htmlspecialchars($row['user_id']);
                        $email = htmlspecialchars($row['user_email']);
                        $statusId = (int) $row['user_status_id'];
                        $statusDesc = htmlspecialchars($row['user_status_description'] ?? 'Unknown');
                        $createdAt = $row['user_created_at'] ? date('M d, Y', strtotime($row['user_created_at'])) : '—';
                        
                        $statusClass = '';
                        switch ($statusId) {
                            case 1: $statusClass = 'status-active'; break;
                            case 2: $statusClass = 'status-deactivated'; break;
                            case 3: $statusClass = 'status-blocked'; break;
                            default: $statusClass = 'status-pending'; break;
                        }

                        $isDisabled = $statusId === 2;
                        ?>
                        <tr>
                          <td><?php echo $userId; ?></td>
                          <td><?php echo $email; ?></td>
                          <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusDesc; ?></span></td>
                          <td><?php echo $createdAt; ?></td>
                          <td class="actions-cell">
                            <button type="button" class="btn btn-sm btn-outline edit-admin-btn"
                              data-user-id="<?php echo $userId; ?>"
                              data-user-email="<?php echo $email; ?>"
                              data-user-status="<?php echo $statusId; ?>"
                              title="Edit Admin">
                              <i class="fas fa-edit"></i> Edit
                            </button>
                            <?php if (!$isDisabled): ?>
                            <button type="button" class="btn btn-sm btn-danger disable-admin-btn"
                              data-user-id="<?php echo $userId; ?>"
                              data-user-email="<?php echo $email; ?>"
                              title="Disable Admin">
                              <i class="fas fa-ban"></i> Disable
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn btn-sm btn-success enable-admin-btn"
                              data-user-id="<?php echo $userId; ?>"
                              data-user-email="<?php echo $email; ?>"
                              title="Enable Admin">
                              <i class="fas fa-check"></i> Enable
                            </button>
                            <?php endif; ?>
                          </td>
                        </tr>
                        <?php
                    }
                } else {
                    echo '<tr><td colspan="5" class="text-center">No admin accounts found.</td></tr>';
                }
            }
            ?>
          </tbody>
        </table>

        <?php if (isset($totalPages) && $totalPages > 1): ?>
        <?php
            $maxVisiblePages = 5;
            $startPage = max(1, $currentPage - floor($maxVisiblePages / 2));
            $endPage = min($totalPages, $startPage + $maxVisiblePages - 1);
            if (($endPage - $startPage + 1) < $maxVisiblePages) {
                $startPage = max(1, $endPage - $maxVisiblePages + 1);
            }
        ?>
        <nav class="pagination-nav" aria-label="Admins pagination">
          <ul class="pagination-list">
            <li class="page-item<?php echo $currentPage === 1 ? ' disabled' : ''; ?>">
              <a class="page-link" href="<?php echo $currentPage === 1 ? '#' : buildAdminPageUrl(1); ?>" aria-label="First page">&lt;&lt;</a>
            </li>
            <li class="page-item<?php echo $currentPage === 1 ? ' disabled' : ''; ?>">
              <a class="page-link" href="<?php echo $currentPage === 1 ? '#' : buildAdminPageUrl($currentPage - 1); ?>" aria-label="Previous page">&lt;</a>
            </li>
            <?php for ($page = $startPage; $page <= $endPage; $page++): ?>
            <li class="page-item<?php echo $page === $currentPage ? ' active' : ''; ?>">
              <a class="page-link" href="<?php echo buildAdminPageUrl($page); ?>"><?php echo $page; ?></a>
            </li>
            <?php endfor; ?>
            <li class="page-item<?php echo $currentPage === $totalPages ? ' disabled' : ''; ?>">
              <a class="page-link" href="<?php echo $currentPage === $totalPages ? '#' : buildAdminPageUrl($currentPage + 1); ?>" aria-label="Next page">&gt;</a>
            </li>
            <li class="page-item<?php echo $currentPage === $totalPages ? ' disabled' : ''; ?>">
              <a class="page-link" href="<?php echo $currentPage === $totalPages ? '#' : buildAdminPageUrl($totalPages); ?>" aria-label="Last page">&gt;&gt;</a>
            </li>
          </ul>
        </nav>
        <?php endif; ?>
      </div>
    </div>

    <!-- Edit Admin Modal -->
    <div id="editAdminModal" class="modal">
      <div class="modal-content">
        <button class="modal-close" type="button" aria-label="Close" onclick="closeEditModal()">&times;</button>
        <div class="modal-header">
          <h2>Edit Admin</h2>
        </div>
        <form id="editAdminForm" method="POST" action="../adminbackend/update_admin.php">
          <input type="hidden" name="userId" id="editUserId">
          <input type="hidden" name="action" value="edit">
          
          <div class="form-group">
            <label for="editEmail">Email</label>
            <input type="email" id="editEmail" name="email" required>
          </div>
          
          <div class="form-group">
            <label for="editPassword">New Password (leave blank to keep current)</label>
            <input type="password" id="editPassword" name="password" placeholder="Enter new password">
          </div>
          
          <div class="form-group">
            <label for="editStatus">Status</label>
            <select id="editStatus" name="status" required>
              <?php foreach ($statusOptions as $statusRow): ?>
                <option value="<?php echo $statusRow['user_status_id']; ?>">
                  <?php echo htmlspecialchars($statusRow['user_status_description']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <p class="form-note"><i class="fas fa-info-circle"></i> Note: User type cannot be changed from this page.</p>

          <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeEditModal()">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </main>

  <script>
    // Edit Modal
    const editModal = document.getElementById('editAdminModal');
    const editForm = document.getElementById('editAdminForm');

    function openEditModal(userId, email, status) {
      document.getElementById('editUserId').value = userId;
      document.getElementById('editEmail').value = email;
      document.getElementById('editStatus').value = status;
      document.getElementById('editPassword').value = '';
      editModal.classList.add('show');
    }

    function closeEditModal() {
      editModal.classList.remove('show');
      editForm.reset();
    }

    window.addEventListener('click', function(event) {
      if (event.target === editModal) {
        closeEditModal();
      }
    });

    // Edit button handlers
    document.querySelectorAll('.edit-admin-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        openEditModal(
          btn.dataset.userId,
          btn.dataset.userEmail,
          btn.dataset.userStatus
        );
      });
    });

    // Disable button handlers
    document.querySelectorAll('.disable-admin-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const userId = btn.dataset.userId;
        const email = btn.dataset.userEmail;
        
        Swal.fire({
          title: 'Disable Admin Account?',
          html: `Are you sure you want to disable the admin account:<br><strong>${email}</strong>?<br><br>This admin will no longer be able to log in.`,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#dc2626',
          cancelButtonColor: '#6b7280',
          confirmButtonText: 'Yes, Disable',
          cancelButtonText: 'Cancel'
        }).then((result) => {
          if (result.isConfirmed) {
            // Submit disable action
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../adminbackend/update_admin.php';
            
            const userIdInput = document.createElement('input');
            userIdInput.type = 'hidden';
            userIdInput.name = 'userId';
            userIdInput.value = userId;
            form.appendChild(userIdInput);
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'disable';
            form.appendChild(actionInput);
            
            document.body.appendChild(form);
            form.submit();
          }
        });
      });
    });

    // Enable button handlers
    document.querySelectorAll('.enable-admin-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const userId = btn.dataset.userId;
        const email = btn.dataset.userEmail;
        
        Swal.fire({
          title: 'Enable Admin Account?',
          html: `Are you sure you want to enable the admin account:<br><strong>${email}</strong>?`,
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#16a34a',
          cancelButtonColor: '#6b7280',
          confirmButtonText: 'Yes, Enable',
          cancelButtonText: 'Cancel'
        }).then((result) => {
          if (result.isConfirmed) {
            // Submit enable action
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../adminbackend/update_admin.php';
            
            const userIdInput = document.createElement('input');
            userIdInput.type = 'hidden';
            userIdInput.name = 'userId';
            userIdInput.value = userId;
            form.appendChild(userIdInput);
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'enable';
            form.appendChild(actionInput);
            
            document.body.appendChild(form);
            form.submit();
          }
        });
      });
    });

    // Flash messages
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

  <style>
    .form-note {
      font-size: 0.875rem;
      color: #6b7280;
      margin: 1rem 0;
      padding: 0.75rem;
      background: #f3f4f6;
      border-radius: 6px;
    }
    .form-note i {
      margin-right: 0.5rem;
      color: #3b82f6;
    }
    .btn-danger {
      background-color: #dc2626;
      color: white;
      border: none;
    }
    .btn-danger:hover {
      background-color: #b91c1c;
    }
    .btn-success {
      background-color: #16a34a;
      color: white;
      border: none;
    }
    .btn-success:hover {
      background-color: #15803d;
    }
    .status-active {
      background-color: #dcfce7;
      color: #166534;
    }
    .status-deactivated {
      background-color: #fee2e2;
      color: #991b1b;
    }
    .status-blocked {
      background-color: #fef3c7;
      color: #92400e;
    }
    .status-pending {
      background-color: #e0e7ff;
      color: #3730a3;
    }
  </style>
</body>
</html>
