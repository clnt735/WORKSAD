<?php 
include '../database.php';
include '../admin/sidebar.php';

// Only Admins (not Super Admins) can access employer verification
// Per requirement: "Only an admin (not superadmin) can approve or reject the request"
if (!isAdmin()) {
    $_SESSION['error'] = 'Only Admins can access the Employer Verification page.';
    header('Location: dashboard.php');
    exit();
}

$searchEmployer = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'pending';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';

$flashPayload = null;
if (isset($_GET['flash_status'])) {
    $status = $_GET['flash_status'] === 'success' ? 'success' : 'error';
    $flashMessage = $_GET['flash_message'] ?? ($status === 'success' ? 'Verification request processed successfully.' : 'Unable to complete the request.');
    $flashPayload = [
        'status' => $status,
        'message' => $flashMessage,
    ];
}

function buildVerificationPageUrl($page) {
    $params = $_GET;
    unset($params['flash_status'], $params['flash_message']);
    $params['page'] = $page;
    return '?' . http_build_query($params) . '#verificationsTable';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Verification</title>
    <link rel="stylesheet" href="../admin/styles.css">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/vendor/sweetalert2/sweetalert2.min.css">
    <script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
    <style>
        .document-preview {
            max-width: 100%;
            max-height: 400px;
            border-radius: 8px;
            border: 1px solid #e4e7ec;
        }
        .document-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #f3f4f6;
            border-radius: 6px;
            color: #1f7bff;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .document-link:hover {
            background: #e0e7ff;
        }
        .badge-document-type {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-national_id {
            background: #dbeafe;
            color: #1e40af;
        }
        .badge-business_permit {
            background: #fef3c7;
            color: #92400e;
        }
        .email-verified-badge {
            display: inline-block;
            font-size: 0.7rem;
            color: #16a34a;
            margin-top: 4px;
        }
        .email-pending-badge {
            display: inline-block;
            font-size: 0.7rem;
            color: #d97706;
            margin-top: 4px;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-approved {
            background: #dcfce7;
            color: #166534;
        }
        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        .btn-approve {
            background-color: #16a34a;
            color: white;
            border: none;
        }
        .btn-approve:hover {
            background-color: #15803d;
        }
        .btn-reject {
            background-color: #dc2626;
            color: white;
            border: none;
        }
        .btn-reject:hover {
            background-color: #b91c1c;
        }
        .company-info {
            font-size: 0.85rem;
            color: #6b7280;
        }
        .reviewer-info {
            font-size: 0.8rem;
            color: #9ca3af;
        }
        .rejection-reason {
            font-size: 0.85rem;
            color: #991b1b;
            font-style: italic;
            margin-top: 0.25rem;
        }
        .modal-document-preview {
            text-align: center;
            margin: 1rem 0;
        }
        .modal-document-preview img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
        }
        .modal-document-preview iframe {
            width: 100%;
            height: 400px;
            border: 1px solid #e4e7ec;
            border-radius: 8px;
        }
        .employer-details {
            background: #f9fafb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .employer-details h4 {
            margin: 0 0 0.75rem 0;
            font-size: 1rem;
        }
        .employer-details p {
            margin: 0.25rem 0;
            font-size: 0.9rem;
        }
    </style>
</head>
<body class="admin-page users-page">
  <?php renderAdminSidebar(); ?>
  <main class="content">
    <div class="content-header">
      <div>
        <h1>Employer Verification</h1>
        <p class="lead">Review and process employer verification requests.</p>
      </div>
    </div>

    <?php
    // Initialize counts
    $pendingCount = 0;
    $approvedCount = 0;
    $rejectedCount = 0;

    if ($conn) {
        if ($result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM employer_verification_requests WHERE status = 'pending'")) {
            $pendingCount = mysqli_fetch_assoc($result)['cnt'];
        }
        if ($result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM employer_verification_requests WHERE status = 'approved'")) {
            $approvedCount = mysqli_fetch_assoc($result)['cnt'];
        }
        if ($result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM employer_verification_requests WHERE status = 'rejected'")) {
            $rejectedCount = mysqli_fetch_assoc($result)['cnt'];
        }
    }
    ?>

    <div class="dashboard-cards">
      <div class="card">
        <h3>Pending Review</h3>
        <p><?php echo $pendingCount; ?></p>
      </div>
      <div class="card">
        <h3>Approved</h3>
        <p><?php echo $approvedCount; ?></p>
      </div>
      <div class="card">
        <h3>Rejected</h3>
        <p><?php echo $rejectedCount; ?></p>
      </div>
    </div>

    <div class="panel">
      <div class="panel-header">
        <h2>Verification Requests</h2>
      </div>
      <div class="filters-section">
        <form class="search-filter-form" method="GET">
          <div class="search-box">
            <input type="text" name="search" placeholder="Search by email or company name" value="<?php echo htmlspecialchars($searchEmployer); ?>">
          </div>
          <select class="filter-select" name="status">
            <option value="">All Status</option>
            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
            <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
          </select>
          <select class="filter-select" name="sort">
            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
            <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
          </select>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-filter"></i>
            Apply Filters
          </button>
        </form>
      </div>
      <div class="table-wrapper" id="verificationsTable">
        <table class="table">
          <thead>
            <tr>
              <th>Employer</th>
              <th>Company</th>
              <th>Document Type</th>
              <th>Status</th>
              <th>Submitted</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            if ($conn) {
                $where = [];
                
                if ($searchEmployer !== '') {
                    $like = '%' . $conn->real_escape_string($searchEmployer) . '%';
                    $where[] = "(u.user_email LIKE '$like' OR c.company_name LIKE '$like')";
                }
                if ($statusFilter !== '') {
                    $where[] = "evr.status = '" . $conn->real_escape_string($statusFilter) . "'";
                }

                $whereSql = count($where) ? ' WHERE ' . implode(' AND ', $where) : '';

                switch ($sort) {
                    case 'oldest':
                        $orderBy = ' ORDER BY evr.submitted_at ASC';
                        break;
                    default:
                        $orderBy = ' ORDER BY evr.submitted_at DESC';
                        break;
                }

                // Pagination
                $perPage = 10;
                $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                $offset = ($currentPage - 1) * $perPage;

                // Count total
                $countSql = "SELECT COUNT(*) as total 
                    FROM employer_verification_requests evr
                    INNER JOIN employer_profiles ep ON evr.employer_id = ep.employer_id
                    LEFT JOIN user u ON ep.user_id = u.user_id
                    LEFT JOIN company c ON ep.user_id = c.user_id
                    $whereSql";
                $countResult = mysqli_query($conn, $countSql);
                $totalRows = $countResult ? mysqli_fetch_assoc($countResult)['total'] : 0;
                $totalPages = max(1, ceil($totalRows / $perPage));

                // Fetch verification requests
                $sql = "SELECT 
                    evr.verification_id,
                    evr.employer_id,
                    evr.document_path,
                    evr.document_type,
                    evr.status,
                    evr.submitted_at,
                    evr.reviewed_at,
                    evr.reviewed_by,
                    evr.rejection_reason,
                    ep.user_id,
                    u.user_email,
                    u.activation_token,
                    c.company_name,
                    up.user_profile_first_name,
                    up.user_profile_last_name,
                    reviewer.user_email as reviewer_email
                FROM employer_verification_requests evr
                INNER JOIN employer_profiles ep ON evr.employer_id = ep.employer_id
                INNER JOIN user u ON ep.user_id = u.user_id
                LEFT JOIN company c ON ep.user_id = c.user_id
                LEFT JOIN user_profile up ON ep.user_id = up.user_id
                LEFT JOIN user reviewer ON evr.reviewed_by = reviewer.user_id
                $whereSql
                $orderBy
                LIMIT $perPage OFFSET $offset";

                $result = mysqli_query($conn, $sql);

                if ($result && mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $verificationId = (int) $row['verification_id'];
                        $employerId = (int) $row['employer_id'];
                        $email = htmlspecialchars($row['user_email'] ?? '');
                        $companyName = htmlspecialchars($row['company_name'] ?? 'N/A');
                        $firstName = htmlspecialchars($row['user_profile_first_name'] ?? '');
                        $lastName = htmlspecialchars($row['user_profile_last_name'] ?? '');
                        $fullName = trim("$firstName $lastName") ?: 'N/A';
                        $documentPath = $row['document_path'];
                        $documentType = $row['document_type'];
                        $status = $row['status'];
                        $submittedAt = $row['submitted_at'] ? date('M d, Y H:i', strtotime($row['submitted_at'])) : '—';
                        $reviewedAt = $row['reviewed_at'] ? date('M d, Y H:i', strtotime($row['reviewed_at'])) : null;
                        $reviewerEmail = $row['reviewer_email'] ?? null;
                        $rejectionReason = htmlspecialchars($row['rejection_reason'] ?? '');
                        
                        // Email verification status
                        $emailVerified = ($row['activation_token'] === null || $row['activation_token'] === '');
                        
                        $documentTypeLabel = $documentType === 'national_id' ? 'National ID' : 'Business Permit';
                        $documentTypeClass = 'badge-' . $documentType;
                        $statusClass = 'status-' . $status;
                        
                        // Get file extension for preview
                        $fileExt = strtolower(pathinfo($documentPath, PATHINFO_EXTENSION));
                        $isPdf = $fileExt === 'pdf';
                        ?>
                        <tr>
                          <td>
                            <strong><?php echo $fullName; ?></strong><br>
                            <span class="company-info"><?php echo $email; ?></span>
                            <?php if ($emailVerified): ?>
                              <span class="email-verified-badge" title="Email Verified">✅ Email Verified</span>
                            <?php else: ?>
                              <span class="email-pending-badge" title="Email Not Verified">⏳ Email Pending</span>
                            <?php endif; ?>
                          </td>
                          <td><?php echo $companyName; ?></td>
                          <td><span class="badge-document-type <?php echo $documentTypeClass; ?>"><?php echo $documentTypeLabel; ?></span></td>
                          <td>
                            <span class="status-badge <?php echo $statusClass; ?>"><?php echo ucfirst($status); ?></span>
                            <?php if ($status !== 'pending' && $reviewerEmail): ?>
                              <br><span class="reviewer-info">by <?php echo htmlspecialchars($reviewerEmail); ?></span>
                            <?php endif; ?>
                            <?php if ($status === 'rejected' && $rejectionReason): ?>
                              <p class="rejection-reason">"<?php echo $rejectionReason; ?>"</p>
                            <?php endif; ?>
                          </td>
                          <td><?php echo $submittedAt; ?></td>
                          <td class="actions-cell">
                            <button type="button" class="btn btn-sm btn-outline view-verification-btn"
                              data-verification-id="<?php echo $verificationId; ?>"
                              data-employer-id="<?php echo $employerId; ?>"
                              data-email="<?php echo $email; ?>"
                              data-email-verified="<?php echo $emailVerified ? '1' : '0'; ?>"
                              data-full-name="<?php echo htmlspecialchars($fullName); ?>"
                              data-company-name="<?php echo $companyName; ?>"
                              data-document-path="<?php echo htmlspecialchars($documentPath); ?>"
                              data-document-type="<?php echo $documentType; ?>"
                              data-document-type-label="<?php echo $documentTypeLabel; ?>"
                              data-is-pdf="<?php echo $isPdf ? '1' : '0'; ?>"
                              data-status="<?php echo $status; ?>"
                              data-submitted-at="<?php echo $submittedAt; ?>"
                              title="View Details">
                              <i class="fas fa-eye"></i> View
                            </button>
                            <?php if ($status === 'pending'): ?>
                            <button type="button" class="btn btn-sm btn-approve approve-btn"
                              data-verification-id="<?php echo $verificationId; ?>"
                              data-employer-id="<?php echo $employerId; ?>"
                              data-email="<?php echo $email; ?>"
                              data-company-name="<?php echo $companyName; ?>"
                              title="Approve">
                              <i class="fas fa-check"></i> Approve
                            </button>
                            <button type="button" class="btn btn-sm btn-reject reject-btn"
                              data-verification-id="<?php echo $verificationId; ?>"
                              data-employer-id="<?php echo $employerId; ?>"
                              data-email="<?php echo $email; ?>"
                              data-company-name="<?php echo $companyName; ?>"
                              title="Reject">
                              <i class="fas fa-times"></i> Reject
                            </button>
                            <?php endif; ?>
                          </td>
                        </tr>
                        <?php
                    }
                } else {
                    echo '<tr><td colspan="6" class="text-center">No verification requests found.</td></tr>';
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
        <nav class="pagination-nav" aria-label="Verifications pagination">
          <ul class="pagination-list">
            <li class="page-item<?php echo $currentPage === 1 ? ' disabled' : ''; ?>">
              <a class="page-link" href="<?php echo $currentPage === 1 ? '#' : buildVerificationPageUrl(1); ?>">&lt;&lt;</a>
            </li>
            <li class="page-item<?php echo $currentPage === 1 ? ' disabled' : ''; ?>">
              <a class="page-link" href="<?php echo $currentPage === 1 ? '#' : buildVerificationPageUrl($currentPage - 1); ?>">&lt;</a>
            </li>
            <?php for ($page = $startPage; $page <= $endPage; $page++): ?>
            <li class="page-item<?php echo $page === $currentPage ? ' active' : ''; ?>">
              <a class="page-link" href="<?php echo buildVerificationPageUrl($page); ?>"><?php echo $page; ?></a>
            </li>
            <?php endfor; ?>
            <li class="page-item<?php echo $currentPage === $totalPages ? ' disabled' : ''; ?>">
              <a class="page-link" href="<?php echo $currentPage === $totalPages ? '#' : buildVerificationPageUrl($currentPage + 1); ?>">&gt;</a>
            </li>
            <li class="page-item<?php echo $currentPage === $totalPages ? ' disabled' : ''; ?>">
              <a class="page-link" href="<?php echo $currentPage === $totalPages ? '#' : buildVerificationPageUrl($totalPages); ?>">&gt;&gt;</a>
            </li>
          </ul>
        </nav>
        <?php endif; ?>
      </div>
    </div>

    <!-- View Verification Modal -->
    <div id="viewVerificationModal" class="modal">
      <div class="modal-content" style="max-width: 700px;">
        <button class="modal-close" type="button" aria-label="Close" onclick="closeViewModal()">&times;</button>
        <div class="modal-header">
          <h2>Verification Details</h2>
        </div>
        <div class="modal-body">
          <div class="employer-details">
            <h4>Employer Information</h4>
            <p><strong>Name:</strong> <span id="modal-fullname"></span></p>
            <p><strong>Email:</strong> <span id="modal-email"></span></p>
            <p><strong>Company:</strong> <span id="modal-company"></span></p>
            <p><strong>Document Type:</strong> <span id="modal-document-type"></span></p>
            <p><strong>Submitted:</strong> <span id="modal-submitted"></span></p>
          </div>
          <div class="modal-document-preview" id="modal-document-preview">
            <!-- Document preview will be inserted here -->
          </div>
          <div style="text-align: center;">
            <a href="#" id="modal-document-link" class="document-link" target="_blank">
              <i class="fas fa-external-link-alt"></i> Open Document in New Tab
            </a>
          </div>
        </div>
        <div class="modal-footer" id="modal-actions">
          <button type="button" class="btn btn-outline" onclick="closeViewModal()">Close</button>
        </div>
      </div>
    </div>
  </main>

  <script>
    const viewModal = document.getElementById('viewVerificationModal');

    function closeViewModal() {
      viewModal.classList.remove('show');
    }

    window.addEventListener('click', function(event) {
      if (event.target === viewModal) {
        closeViewModal();
      }
    });

    // View button handlers
    document.querySelectorAll('.view-verification-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const data = btn.dataset;
        
        document.getElementById('modal-fullname').textContent = data.fullName;
        document.getElementById('modal-email').textContent = data.email;
        document.getElementById('modal-company').textContent = data.companyName;
        document.getElementById('modal-document-type').textContent = data.documentTypeLabel;
        document.getElementById('modal-submitted').textContent = data.submittedAt;
        
        const documentUrl = '../' + data.documentPath;
        document.getElementById('modal-document-link').href = documentUrl;
        
        const previewContainer = document.getElementById('modal-document-preview');
        if (data.isPdf === '1') {
          previewContainer.innerHTML = `<iframe src="${documentUrl}" title="Document Preview"></iframe>`;
        } else {
          previewContainer.innerHTML = `<img src="${documentUrl}" alt="Document Preview" class="document-preview">`;
        }
        
        // Show/hide action buttons based on status
        const actionsDiv = document.getElementById('modal-actions');
        if (data.status === 'pending') {
          actionsDiv.innerHTML = `
            <button type="button" class="btn btn-outline" onclick="closeViewModal()">Close</button>
            <button type="button" class="btn btn-reject" onclick="rejectVerification(${data.verificationId}, ${data.employerId}, '${data.email}', '${data.companyName}')">
              <i class="fas fa-times"></i> Reject
            </button>
            <button type="button" class="btn btn-approve" onclick="approveVerification(${data.verificationId}, ${data.employerId}, '${data.email}', '${data.companyName}')">
              <i class="fas fa-check"></i> Approve
            </button>
          `;
        } else {
          actionsDiv.innerHTML = `<button type="button" class="btn btn-outline" onclick="closeViewModal()">Close</button>`;
        }
        
        viewModal.classList.add('show');
      });
    });

    // Approve handlers
    function approveVerification(verificationId, employerId, email, companyName) {
      Swal.fire({
        title: 'Approve Verification?',
        html: `Are you sure you want to approve the verification for:<br><strong>${companyName}</strong><br>(${email})`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#16a34a',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, Approve',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (result.isConfirmed) {
          const form = document.createElement('form');
          form.method = 'POST';
          form.action = '../adminbackend/process_employer_verification.php';
          
          const fields = [
            { name: 'verification_id', value: verificationId },
            { name: 'employer_id', value: employerId },
            { name: 'action', value: 'approve' }
          ];
          
          fields.forEach(field => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = field.name;
            input.value = field.value;
            form.appendChild(input);
          });
          
          document.body.appendChild(form);
          form.submit();
        }
      });
    }

    document.querySelectorAll('.approve-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const data = btn.dataset;
        approveVerification(data.verificationId, data.employerId, data.email, data.companyName);
      });
    });

    // Reject handlers
    function rejectVerification(verificationId, employerId, email, companyName) {
      Swal.fire({
        title: 'Reject Verification?',
        html: `Please provide a reason for rejecting:<br><strong>${companyName}</strong><br>(${email})`,
        icon: 'warning',
        input: 'textarea',
        inputLabel: 'Rejection Reason',
        inputPlaceholder: 'Enter reason for rejection...',
        inputAttributes: {
          'aria-label': 'Rejection reason'
        },
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Reject',
        cancelButtonText: 'Cancel',
        inputValidator: (value) => {
          if (!value || value.trim() === '') {
            return 'Please provide a rejection reason';
          }
        }
      }).then((result) => {
        if (result.isConfirmed) {
          const form = document.createElement('form');
          form.method = 'POST';
          form.action = '../adminbackend/process_employer_verification.php';
          
          const fields = [
            { name: 'verification_id', value: verificationId },
            { name: 'employer_id', value: employerId },
            { name: 'action', value: 'reject' },
            { name: 'rejection_reason', value: result.value }
          ];
          
          fields.forEach(field => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = field.name;
            input.value = field.value;
            form.appendChild(input);
          });
          
          document.body.appendChild(form);
          form.submit();
        }
      });
    }

    document.querySelectorAll('.reject-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const data = btn.dataset;
        rejectVerification(data.verificationId, data.employerId, data.email, data.companyName);
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
</body>
</html>
