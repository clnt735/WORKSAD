<?php include '../admin/sidebar.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Admin Settings</title>
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
				<h1>Settings</h1>
				<p class="lead">Configure platform defaults and security policies.</p>
			</div>
		</div>

		<section class="panel">
			<div class="panel-header">
				<h2>General Preferences</h2>
			</div>
			<div class="form-grid">
				<div class="form-group">
					<label for="siteName">Site Name</label>
					<input type="text" id="siteName" placeholder="WorkMuna" value="WorkMuna" />
				</div>
				<div class="form-group">
					<label for="supportEmail">Support Email</label>
					<input type="email" id="supportEmail" placeholder="support@workmuna.com" value="support@workmuna.com" />
				</div>
				<div class="form-group">
					<label for="timezone">Timezone</label>
					<select id="timezone">
						<option>UTC+08:00 Manila</option>
						<option>UTC</option>
						<option>UTC+05:30</option>
					</select>
				</div>
			</div>
			<div class="modal-footer" style="justify-content:flex-end;">
				<button class="btn btn-outline" type="button">Discard</button>
				<button class="btn btn-primary" type="button">Save Changes</button>
			</div>
		</section>

		<section class="panel">
			<div class="panel-header">
				<h2>Security</h2>
			</div>
			<div class="form-grid">
				<div class="form-group">
					<label>Two-factor Authentication</label>
					<button class="btn btn-secondary" type="button">Enable</button>
				</div>
				<div class="form-group">
					<label>Password Expiry</label>
					<select>
						<option>Never</option>
						<option>90 Days</option>
						<option>180 Days</option>
					</select>
				</div>
			</div>
		</section>
	</main>
</body>
</html>