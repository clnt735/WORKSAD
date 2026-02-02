<?php
session_start();
include '../database.php';
require_once __DIR__ . '/../adminbackend/log_admin_action.php';

$alreadyLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

$isPost = $_SERVER["REQUEST_METHOD"] === "POST";
if (!$isPost && $alreadyLoggedIn) {
	header("Location: dashboard.php");
	exit();
}

$error = "";
$flashPayload = null;
if ($isPost) {
	$email = trim($_POST['email']);
	$password = trim($_POST['password']);

	$sql = "SELECT * FROM user WHERE user_email = ?";
	$stmt = $conn->prepare($sql);
	$stmt->bind_param("s", $email);
	$stmt->execute();
	$result = $stmt->get_result();

	if ($result->num_rows === 1) {
		$row = $result->fetch_assoc();

		if (password_verify($password, $row['user_password'])) {
			// ROLE_ADMIN = 1 (reserved for future limited features)
			// ROLE_SUPER_ADMIN = 4 (full admin access)
			if ($row['user_type_id'] == 4) {
				// Super Admin - full access to admin panel
				session_regenerate_id(true);
				$_SESSION['user_id'] = $row['user_id'];
				$_SESSION['user_type_id'] = $row['user_type_id'];
				$_SESSION['admin_logged_in'] = true;
				log_admin_action($conn, (int)$row['user_id'], 'Login: Signed in successfully');
				header("Location: dashboard.php");
				exit();
			} elseif ($row['user_type_id'] == 1) {
				// Regular Admin - currently restricted, reserved for future features
				$error = "Admin access is currently restricted. Please contact the Super Admin for assistance.";
			} elseif ($row['user_type_id'] == 2) {
				$error = "This login page is for admins only. Please use the Applicant Login.";
			} elseif ($row['user_type_id'] == 3) {
				$error = "This login page is for admins only. Please use the Employer Login.";
			} else {
				$error = "Unknown user type.";
			}
		} else {
			$error = "Invalid email or password.";
		}
	} else {
		$error = "Invalid email or password.";
	}
}

if (isset($_GET['registered']) && $_GET['registered'] == 1) {
	$flashPayload = [
		'status' => 'success',
		'message' => 'Registration successful! Please sign in with your new credentials.',
	];
}

if ($error !== '') {
	$flashPayload = [
		'status' => 'error',
		'message' => $error,
	];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Admin Login</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="../styles.css">
	<link rel="stylesheet" href="../assets/vendor/sweetalert2/sweetalert2.min.css">
	<script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
	<style>
		body {
			font-family: "Nunito", "Poppins", sans-serif;
			min-height: 100vh;
			position: relative;
			color: #f8fafc;
			background: #bbb8ccff;
			color: #0f172a;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 4rem 1.5rem;
		}

        .btn-outline-secondary {
            color: #4338ca;
            border-color: #4338ca;
        }

		.hero-card {
			position: relative;
			z-index: 1;
			backdrop-filter: blur(6px);
			background: #102041;
			border-radius: 20px;
			border: 1px solid rgba(99, 102, 241, 0.25);
			padding: 3rem;
			max-width: 640px;
			width: 100%;
			box-shadow: 0 35px 75px -25px rgba(15, 23, 42, 0.65);
		}

		.hero-card h1 {
			font-size: clamp(2.25rem, 4vw, 3rem);
			font-weight: 700;
			margin-bottom: 1rem;
			color: #f8fafc;
		}

		.hero-card p {
			font-size: 1rem;
			color: #cbd5f5;
			margin-bottom: 2.5rem;
		}

		.ghost-button {
			border-radius: 999px;
			padding: 0.85rem 2.5rem;
			font-weight: 600;
			letter-spacing: 0.02em;
			box-shadow: 0 18px 35px -20px rgba(15, 23, 42, 0.2);
			transition: transform 0.25s ease, box-shadow 0.25s ease;
		}

		.ghost-button:hover {
			transform: translateY(-2px);
			box-shadow: 0 22px 50px -20px rgba(15, 23, 42, 0.25);
		}

		.login-modal .modal-content {
			border-radius: 18px;
			overflow: hidden;
			border: none;
			box-shadow: 0 35px 75px -25px rgba(15, 23, 42, 0.65);
		}

		.login-modal .modal-body {
			padding: 0;
		}

		.login-modal .modal-left {
			padding: 3.5rem 3rem;
			background: #ffffff;
			color: #0f172a;
		}

		.login-modal .modal-left .brand-pill {
			display: inline-flex;
			align-items: center;
			gap: 0.75rem;
			padding: 0.5rem 1.25rem;
			border-radius: 999px;
			background: rgba(79, 70, 229, 0.12);
			color: #4338ca;
			font-weight: 600;
			margin-bottom: 1.75rem;
		}

		.login-modal .modal-left h1 {
			font-size: 2rem;
			font-weight: 700;
			margin-bottom: 0.85rem;
		}

		.login-modal .modal-left p.lead {
			color: #475569;
			margin-bottom: 2rem;
		}

		.login-modal .login-field {
			display: block;
			width: 100%;
			border: 1px solid #e2e8f0;
			border-radius: 12px;
			background: #f8fafc;
			padding: 0.95rem 1rem;
			font-weight: 500;
			color: #0f172a;
			transition: border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
		}

		.login-modal .login-field:focus {
			background: #ffffff;
			border-color: #6366f1;
			box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
			outline: none;
		}

		.login-modal .login-field::placeholder {
			color: #9ca3af;
		}

		.login-modal .btn-primary {
			background: linear-gradient(135deg, #6366f1, #4338ca);
			border: none;
			padding: 0.85rem 1rem;
			box-shadow: 0 18px 35px -20px rgba(99, 102, 241, 0.9);
		}

		.btn-primary:hover {
			color: black;
		}

		.login-modal .btn-primary:hover {
			background: linear-gradient(135deg, #4f46e5, #3730a3);
		}

		.login-modal .modal-right {
			background: url("https://images.unsplash.com/photo-1512486130939-2c4f79935e4f?auto=format&fit=crop&w=1200&q=80") center/cover no-repeat;
			position: relative;
			min-height: 100%;
		}

		.login-modal .modal-right::before {
			content: "";
			position: absolute;
			inset: 0;
			background: linear-gradient(135deg, rgba(17, 24, 39, 0.35), rgba(17, 24, 39, 0.6));
		}

		.login-modal .helper-links a {
			color: #4338ca;
			text-decoration: none;
			font-weight: 600;
		}

		.login-modal .helper-links a:hover {
			text-decoration: underline;
		}

		.portal-switch a {
			color: #64748b;
			text-decoration: none;
			font-weight: 500;
		}

		.portal-switch a:hover {
			color: #4338ca;
		}

		.btn-icon {
			border-radius: 50%;
			width: 44px;
			height: 44px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			border: none;
			color: #4338ca;
			background: rgba(79, 70, 229, 0.12);
			transition: transform 0.2s ease;
		}

		.btn-icon:hover {
			transform: scale(1.05);
		}

		.remember-me {
			display: inline-flex;
			align-items: center;
			gap: 10px;
			padding-left: 0;
			margin-bottom: 0;
		}

		.remember-me .form-check-input {
			position: static;
			float: none;
			border-radius: 6px;
			margin: 0;
			width: 1.1rem;
			height: 1.1rem;
			flex-shrink: 0;
		}

		.remember-me .form-check-label {
			margin-top: 0px;
			margin-bottom: 0;
			line-height: 1.4;
		}

		@media (max-width: 992px) {
			.hero-card {
				padding: 2.5rem 2rem;
			}

			.login-modal .modal-left {
				padding: 2.5rem 1.75rem;
			}
		}

		@media (max-width: 576px) {
			.ghost-button {
				width: 100%;
				text-align: center;
			}
		}

		.login-modal .login-field:-webkit-autofill,
		.login-modal .login-field:-webkit-autofill:hover,
		.login-modal .login-field:-webkit-autofill:focus,
		.login-modal .login-field:-webkit-autofill:active {
			transition: background-color 9999s ease-in-out 0s;
			-webkit-text-fill-color: #0f172a;
			caret-color: #0f172a;
			box-shadow: 0 0 0px 1000px #ffffff inset;
		}

	</style>
</head>
<body>
	<div class="hero-card text-center text-lg-start">
		<div class="d-inline-flex align-items-center gap-2 text-uppercase text-secondary small mb-3">
			<span class="badge bg-primary-subtle text-primary">Secure Access</span>
			<span class="opacity-75">Admin Portal</span>
		</div>
		<h1 class="mb-3">Empower your hiring decisions with Work Muna Insights.</h1>
		<p>Log in to administer job postings, review applicants, and drive data-backed workforce strategies within a single, secure dashboard.</p>
		<div class="d-flex flex-column flex-sm-row align-items-center gap-3">
			<button type="button" class="btn btn-primary ghost-button d-inline-flex align-items-center gap-2" data-open-login>
				<i class="fa-solid fa-right-to-bracket"></i>
				Click here to login
			</button>
			<a href="../" class="btn btn-outline-light rounded-pill px-4">Return to site</a>
		</div>
	</div>

	<div class="modal fade login-modal" id="adminLoginModal" tabindex="-1" aria-labelledby="adminLoginModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-xl">
			<div class="modal-content">
				<div class="modal-body">
					<div class="row g-0">
						<div class="col-lg-7 modal-left position-relative">
							<button type="button" class="btn-icon position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close">
								<i class="fa-solid fa-xmark"></i>
							</button>
							<div class="brand-pill">
								<i class="fa-solid fa-shield-halved"></i>
								Work Muna Admin
							</div>
							<h1 id="adminLoginModalLabel">Welcome back, Administrator</h1>
							<p class="lead">Authenticate to access dashboards, audit logs, and critical platform settings.</p>

							<form method="POST" action="" class="needs-validation login-form" novalidate>
								<div class="mb-3">
									<label for="email" class="form-label fw-semibold text-uppercase small text-secondary">Email</label>
									<input type="email" class="form-control login-field" id="email" name="email" placeholder="Email" required autocomplete="username">
									<div class="invalid-feedback">Please enter a valid admin email.</div>
								</div>

								<div class="mb-3">
									<label for="password" class="form-label fw-semibold text-uppercase small text-secondary">Password</label>
									<input type="password" class="form-control login-field" id="password" name="password" placeholder="Password" required autocomplete="current-password">
									<div class="invalid-feedback">Password is required to continue.</div>
								</div>

								<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
									<!-- <div class="form-check remember-me">
										<input class="form-check-input" type="checkbox" id="rememberMe">
										<label class="form-check-label" for="rememberMe">Remember me</label>
									</div> -->
									<!-- <div class="helper-links">
										<a href="../adminbackend/password/request_reset.php" class="small">Forgot Password?</a>
									</div> -->
								</div>

								<div class="d-grid mb-4">
									<button type="submit" class="btn btn-primary btn-lg">Sign in securely</button>
								</div>
							</form>

							<div class="portal-switch d-flex flex-wrap gap-2">
								<span class="text-muted">Need a different portal?</span>
								<a href="../applicant/login.php">Applicant Login</a>
								<span class="text-muted">|</span>
								<a href="../employercontent/login.php">Employer Login</a>
							</div>
						</div>
						<div class="col-lg-5 d-none d-lg-block modal-right"></div>
					</div>
				</div>
				<div class="text-center py-3 bg-light">
					<small class="text-muted">© <?= date('Y') ?> Work Muna Admin. Secure &amp; Confidential Access.</small>
				</div>
			</div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
	<script>
		(function() {
			'use strict';
			const forms = document.querySelectorAll('.needs-validation');
			Array.prototype.slice.call(forms).forEach(function(form) {
				form.addEventListener('submit', function(event) {
					if (!form.checkValidity()) {
						event.preventDefault();
						event.stopPropagation();
					}
					form.classList.add('was-validated');
				}, false);
			});
		})();

	document.addEventListener('DOMContentLoaded', function() {
			const modalElement = document.getElementById('adminLoginModal');
			if (!modalElement) {
				return;
			}
			const loginModal = new bootstrap.Modal(modalElement);
			const openButtons = document.querySelectorAll('[data-open-login]');

			openButtons.forEach(function(button) {
				button.addEventListener('click', function() {
					loginModal.show();
				});
			});

			<?php if ($error !== ""): ?>
			loginModal.show();
			<?php endif; ?>
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
					title: icon === 'success' ? 'Welcome' : 'Login error',
					text: flashPayload.message,
					confirmButtonColor: '#2563eb',
					showConfirmButton: true,
				});
			})();
		</script>
</body>
</html>
