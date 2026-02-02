<?php
session_start();
include "navbar.php";
require_once '../database.php';

$activePage = 'home';


// Get the logged-in employer's user_id
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// Get employer's first name
$employerFirstName = 'Employer';
if ($userId > 0) {
	$stmt = $conn->prepare("SELECT user_profile_first_name FROM user_profile WHERE user_id = ?");
	$stmt->bind_param('i', $userId);
	$stmt->execute();
	$stmt->bind_result($firstName);
	if ($stmt->fetch()) {
		$employerFirstName = $firstName;
	}
	$stmt->close();
}

// Get the number of jobs posted by this employer
$jobsPosted = 0;
$activeJobs = 0;
if ($userId > 0) {
	$stmt = $conn->prepare("SELECT COUNT(*) FROM job_post WHERE user_id = ?");
	$stmt->bind_param('i', $userId);
	$stmt->execute();
	$stmt->bind_result($jobsPosted);
	$stmt->fetch();
	$stmt->close();

	// Count active jobs (job_status_id = 1)
	$stmt = $conn->prepare("SELECT COUNT(*) FROM job_post WHERE user_id = ? AND job_status_id = 1");
	$stmt->bind_param('i', $userId);
	$stmt->execute();
	$stmt->bind_result($activeJobs);
	$stmt->fetch();
	$stmt->close();
}
?>

<main class="dashboard-wrapper">
	<div class="dashboard-content">
		<section class="dashboard-hero">
			<h1 class="welcome-title">Welcome back, <span class="accent-green"><?php echo htmlspecialchars($employerFirstName); ?></span>!</h1>
		</section>


		<section class="dashboard-stats">
			<div class="stat-card">
				<div class="stat-label">Jobs Posted</div>
				<div class="stat-value"><?php echo $jobsPosted; ?></div>
			</div>
			<div class="stat-card">
				<div class="stat-label">Active Jobs</div>
				<div class="stat-value"><?php echo $activeJobs; ?></div>
			</div>
			<div class="stat-card">
				<div class="stat-label">Applications</div>
				<div class="stat-value">25</div>
			</div>
			<div class="stat-card">
				<div class="stat-label">Matches</div>
				<div class="stat-value">3</div>
			</div>
		</section>

		<!-- Floating Action Button for Post Job -->
		<a href="post_job.php" class="fab-button" title="Post a Job">
			<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
				<line x1="12" y1="5" x2="12" y2="19"></line>
				<line x1="5" y1="12" x2="19" y2="12"></line>
			</svg>
		</a>

		<section class="quick-actions">
			<!-- Desktop: Show Post a Job card -->
			<article class="action-card action-primary desktop-only">
				<div>
					<h2>Post a Job</h2>
					<p>Create a new job posting</p>
				</div>
				<button type="button" onclick="window.location.href='post_job.php'">Create</button>
			</article>
			<article class="action-card">
				<div>
					<h3>My Jobs</h3>
					<p class="action-description">Manage job postings</p>
				</div>
				<button type="button" onclick="window.location.href='jobs_posted.php'">Open</button>
			</article>
			<article class="action-card">
				<div>
					<h3>Browse Candidates</h3>
					<p class="action-description">Find qualified talent</p>
				</div>
				<button type="button" onclick="window.location.href='find_talent.php'">Browse</button>
			</article>
			<article class="action-card">
				<div>
					<h3>Matches</h3>
					<p class="action-description">Review candidate matches</p>
				</div>
				<button type="button" onclick="window.location.href='matches.php'">View</button>
			</article>
		</section>

		<!-- Key Features Section -->
		<section class="wm-features">
			<h2 class="wm-features-title">Key Features</h2>
			<div class="wm-features-grid">
				<div class="wm-feature-card accent1">
					<div class="feature-visual">
						<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#1e40af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
							<circle cx="9" cy="7" r="4"></circle>
							<path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
							<path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
						</svg>
					</div>
					<div>
						<h3>Smart Candidate Matching</h3>
						<p>Curated applicant suggestions based on open roles.</p>
					</div>
				</div>
				<div class="wm-feature-card accent2">
					<div class="feature-visual">
						<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#166534" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
							<polyline points="14 2 14 8 20 8"></polyline>
							<line x1="12" y1="18" x2="12" y2="12"></line>
							<line x1="9" y1="15" x2="15" y2="15"></line>
						</svg>
					</div>
					<div>
						<h3>Easy Job Posting Tools</h3>
						<p>Create professional job listings quickly.</p>
					</div>
				</div>
				<div class="wm-feature-card accent3">
					<div class="feature-visual">
						<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#854d0e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
							<line x1="3" y1="9" x2="21" y2="9"></line>
							<line x1="9" y1="21" x2="9" y2="9"></line>
						</svg>
					</div>
					<div>
						<h3>Employer Dashboard</h3>
						<p>Track applications, posts, matches.</p>
					</div>
				</div>
				<div class="wm-feature-card accent4">
					<div class="feature-visual">
						<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#9f1239" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
							<line x1="12" y1="18" x2="12.01" y2="18"></line>
						</svg>
					</div>
					<div>
						<h3>Mobile-Friendly Hiring</h3>
						<p>Manage candidates anywhere.</p>
					</div>
				</div>
			</div>
		</section>

		<!-- How It Works Section -->
		<section class="wm-how">
			<h2 class="wm-how-title">How It Works</h2>
			<div class="wm-how-steps">
				<div class="wm-how-step accent1">
					<div class="how-visual">1</div>
					<div>
						<h3>Create an account</h3>
						<p>Sign up and set up your employer profile in minutes.</p>
					</div>
				</div>
				<div class="wm-how-step accent2">
					<div class="how-visual">2</div>
					<div>
						<h3>Post your job</h3>
						<p>Add job details, requirements, and budget.</p>
					</div>
				</div>
				<div class="wm-how-step accent3">
					<div class="how-visual">3</div>
					<div>
						<h3>Browse candidates & get matches</h3>
						<p>Review applications and get smart matches instantly.</p>
					</div>
				</div>
			</div>
		</section>

		<!-- Recent activity section removed as requested -->
	</div>
</main>

<style>
:root {
	--wm-blue: #1f7bff;
	--wm-green: #34a853;
	--wm-bg: #f8f9fc;
	--wm-card-bg: #fff;
	--wm-muted: #6b7280;
	--wm-border: #e5e7eb;
	--wm-font: 'Roboto', 'Poppins', 'Segoe UI', Tahoma, sans-serif;
	--dashboard-bg: #f8f9fc;
	--card-bg: #fff;
	--text-dark: #141c2c;
	--muted: #6b7280;
	--soft-border: #e5e7eb;
	--accent-green: #34a853;
	--accent-blue: #1f7bff;
	--shadow-sm: 0 2px 8px rgba(47, 62, 89, 0.08);
	--shadow-md: 0 4px 16px rgba(47, 62, 89, 0.1);
	--shadow-lg: 0 8px 24px rgba(47, 62, 89, 0.12);
}

body {
	background: var(--wm-bg);
	font-family: var(--wm-font);
	margin: 0;
	padding: 0;
}

.dashboard-wrapper {
	background: var(--wm-bg);
	min-height: calc(100vh - 80px);
	padding: 2rem clamp(1rem, 5vw, 3rem) 3rem;
	font-family: var(--wm-font);
}

.dashboard-content {
	width: min(1200px, 100%);
	margin: 0 auto;
	display: flex;
	flex-direction: column;
	gap: 2rem;
}

.dashboard-hero {
	margin-bottom: 0.5rem;
	text-align: center;
	padding: 1rem 0;
}

.welcome-title {
	margin: 0;
	font-size: clamp(1.8rem, 4vw, 2.5rem);
	color: var(--text-dark);
	font-weight: 700;
	letter-spacing: -0.02em;
}

.accent-green {
	color: var(--accent-green);
	background: linear-gradient(135deg, #34a853 0%, #22c55e 100%);
	-webkit-background-clip: text;
	-webkit-text-fill-color: transparent;
	background-clip: text;
}

.dashboard-stats {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 1.5rem;
	margin-bottom: 0.5rem;
}

/* Floating Action Button */
.fab-button {
	position: fixed;
	bottom: 90px;
	right: 1.5rem;
	width: 56px;
	height: 56px;
	border-radius: 50%;
	background: linear-gradient(135deg, #34a853 0%, #2d9249 100%);
	box-shadow: 0 4px 16px rgba(52, 168, 83, 0.4);
	display: none;
	align-items: center;
	justify-content: center;
	z-index: 1000;
	transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
	text-decoration: none;
	cursor: pointer;
}

.fab-button:hover {
	transform: scale(1.1);
	box-shadow: 0 6px 20px rgba(52, 168, 83, 0.5);
}

.fab-button:active {
	transform: scale(0.95);
}

.desktop-only {
	display: flex;
}

.stat-card {
	background: linear-gradient(135deg, #fff 0%, #f9fafb 100%);
	border-radius: 1.25rem;
	padding: 1.75rem 2rem;
	box-shadow: var(--shadow-md);
	position: relative;
	overflow: hidden;
	transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
	border: 1px solid rgba(31, 123, 255, 0.08);
}

.stat-card::before {
	content: '';
	position: absolute;
	top: 0;
	right: 0;
	width: 100px;
	height: 100px;
	background: radial-gradient(circle, rgba(31, 123, 255, 0.1) 0%, transparent 70%);
	border-radius: 50%;
	transform: translate(40%, -40%);
	transition: all 0.3s ease;
}

.stat-card:hover {
	transform: translateY(-4px);
	box-shadow: var(--shadow-lg);
	border-color: rgba(31, 123, 255, 0.15);
}

.stat-card:hover::before {
	transform: translate(30%, -30%) scale(1.2);
}

.stat-label {
	color: var(--muted);
	font-size: 0.95rem;
	font-weight: 500;
	margin-bottom: 0.5rem;
	letter-spacing: 0.01em;
}

.stat-value {
	font-size: 2rem;
	font-weight: 800;
	background: linear-gradient(135deg, #1f7bff 0%, #34a853 100%);
	-webkit-background-clip: text;
	-webkit-text-fill-color: transparent;
	background-clip: text;
	line-height: 1;
}

.quick-actions {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
	gap: 1.5rem;
}

.action-card {
	background: var(--card-bg);
	border-radius: 1.5rem;
	padding: 1.75rem 1.75rem;
	box-shadow: var(--shadow-md);
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 1.25rem;
	min-width: 0;
	transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
	border: 1px solid var(--soft-border);
	position: relative;
	overflow: hidden;
}

.action-card::after {
	content: '';
	position: absolute;
	bottom: 0;
	left: 0;
	width: 100%;
	height: 3px;
	background: linear-gradient(90deg, var(--accent-blue), var(--accent-green));
	transform: scaleX(0);
	transform-origin: left;
	transition: transform 0.3s ease;
}

.action-card:hover {
	transform: translateY(-4px);
	box-shadow: var(--shadow-lg);
	border-color: rgba(31, 123, 255, 0.2);
}

.action-card:hover::after {
	transform: scaleX(1);
}

.action-card h2,
.action-card h3 {
	margin: 0 0 0.4rem;
	color: var(--text-dark);
	font-size: 1.15rem;
	font-weight: 700;
	letter-spacing: -0.01em;
}

.action-card p {
	margin: 0;
	color: var(--muted);
	font-size: 0.9rem;
	line-height: 1.4;
}

.action-card button {
	background: var(--accent-green);
	color: #fff;
	border: none;
	border-radius: 999px;
	padding: 0.6rem 1.5rem;
	font-weight: 600;
	cursor: pointer;
	font-size: 0.95rem;
	transition: all 0.2s ease;
	white-space: nowrap;
	box-shadow: 0 2px 8px rgba(52, 168, 83, 0.25);
}

.action-card button:hover {
	background: #2d9249;
	transform: scale(1.05);
	box-shadow: 0 4px 12px rgba(52, 168, 83, 0.35);
}

.action-primary {
	background: linear-gradient(135deg, #22c55e 0%, #34a853 100%);
	color: #fff;
	border: none;
}

.action-primary h2,
.action-primary p {
	color: #fff;
}

.action-primary button {
	background: rgba(255, 255, 255, 0.25);
	border: 1px solid rgba(255, 255, 255, 0.4);
	color: #fff;
	backdrop-filter: blur(10px);
}

.action-primary button:hover {
	background: rgba(255, 255, 255, 0.35);
	border-color: rgba(255, 255, 255, 0.6);
}

/* Key Features Section */
.wm-features {
	background: var(--wm-card-bg);
	border-radius: 1.5rem;
	padding: 2.5rem 2rem;
	margin-bottom: 1.5rem;
	box-shadow: var(--shadow-md);
	border: 1px solid var(--soft-border);
}

.wm-features-title {
	font-size: 1.6rem;
	color: var(--text-dark);
	font-weight: 800;
	margin-bottom: 1.5rem;
	letter-spacing: -0.02em;
	text-align: center;
}

.wm-features-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
	gap: 1.5rem;
}

.wm-feature-card {
	background: linear-gradient(135deg, #fff 0%, #f9fafb 100%);
	border-radius: 1.25rem;
	box-shadow: var(--shadow-sm);
	display: flex;
	align-items: flex-start;
	gap: 1.25rem;
	padding: 1.5rem 1.75rem;
	min-width: 0;
	transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
	border: 1px solid rgba(0, 0, 0, 0.04);
}

.wm-feature-card:hover {
	transform: translateY(-4px);
	box-shadow: var(--shadow-md);
	border-color: rgba(31, 123, 255, 0.15);
}

.feature-visual {
	width: 3rem;
	height: 3rem;
	border-radius: 1rem;
	background: linear-gradient(135deg, #e0f7fa 0%, #b2ebf2 100%);
	flex-shrink: 0;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 1.5rem;
	transition: transform 0.3s ease;
}

.wm-feature-card:hover .feature-visual {
	transform: scale(1.1) rotate(5deg);
}

.wm-feature-card.accent1 .feature-visual {
	background: linear-gradient(135deg, #dbeafe 0%, #93c5fd 100%);
}

.wm-feature-card.accent2 .feature-visual {
	background: linear-gradient(135deg, #dcfce7 0%, #86efac 100%);
}

.wm-feature-card.accent3 .feature-visual {
	background: linear-gradient(135deg, #fef3c7 0%, #fcd34d 100%);
}

.wm-feature-card.accent4 .feature-visual {
	background: linear-gradient(135deg, #fce7f3 0%, #f9a8d4 100%);
}

.wm-feature-card h3 {
	margin: 0 0 0.4rem;
	font-size: 1.1rem;
	color: var(--text-dark);
	font-weight: 700;
	letter-spacing: -0.01em;
}

.wm-feature-card p {
	margin: 0;
	color: var(--wm-muted);
	font-size: 0.95rem;
	line-height: 1.5;
}

/* How It Works Section */
.wm-how {
	background: var(--wm-card-bg);
	border-radius: 1.5rem;
	padding: 2.5rem 2rem;
	margin-bottom: 1.5rem;
	box-shadow: var(--shadow-md);
	border: 1px solid var(--soft-border);
}

.wm-how-title {
	font-size: 1.6rem;
	color: var(--text-dark);
	font-weight: 800;
	margin-bottom: 1.5rem;
	letter-spacing: -0.02em;
	text-align: center;
}

.wm-how-steps {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
	gap: 1.5rem;
}

.wm-how-step {
	background: linear-gradient(135deg, #fff 0%, #f9fafb 100%);
	border-radius: 1.25rem;
	box-shadow: var(--shadow-sm);
	display: flex;
	align-items: flex-start;
	gap: 1.25rem;
	padding: 1.5rem 1.75rem;
	min-width: 0;
	transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
	border: 1px solid rgba(0, 0, 0, 0.04);
	position: relative;
}

.wm-how-step:hover {
	transform: translateY(-4px);
	box-shadow: var(--shadow-md);
	border-color: rgba(31, 123, 255, 0.15);
}

.how-visual {
	width: 2.75rem;
	height: 2.75rem;
	border-radius: 1rem;
	background: linear-gradient(135deg, #dbeafe 0%, #93c5fd 100%);
	color: #1e40af;
	font-size: 1.3rem;
	font-weight: 800;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
	transition: transform 0.3s ease;
}

.wm-how-step:hover .how-visual {
	transform: scale(1.1);
}

.wm-how-step.accent1 .how-visual {
	background: linear-gradient(135deg, #dbeafe 0%, #93c5fd 100%);
	color: #1e40af;
}

.wm-how-step.accent2 .how-visual {
	background: linear-gradient(135deg, #dcfce7 0%, #86efac 100%);
	color: #166534;
}

.wm-how-step.accent3 .how-visual {
	background: linear-gradient(135deg, #fef3c7 0%, #fcd34d 100%);
	color: #854d0e;
}

.wm-how-step h3 {
	margin: 0 0 0.4rem;
	font-size: 1.1rem;
	color: var(--text-dark);
	font-weight: 700;
	letter-spacing: -0.01em;
}

.wm-how-step p {
	margin: 0;
	color: var(--wm-muted);
	font-size: 0.95rem;
	line-height: 1.5;
}

/* Responsive Design */
@media (max-width: 1024px) {
	.dashboard-wrapper {
		padding: 1.5rem 1rem 2rem;
	}
	
	.dashboard-stats {
		grid-template-columns: repeat(2, 1fr);
		gap: 1rem;
	}
	
	.quick-actions {
		grid-template-columns: repeat(2, 1fr);
		gap: 1rem;
	}
	
	.wm-features-grid,
	.wm-how-steps {
		grid-template-columns: repeat(2, 1fr);
		gap: 1rem;
	}
}

@media (max-width: 768px) {
	.dashboard-wrapper {
		padding: 0.75rem 0.75rem 5rem;
	}
	
	.dashboard-content {
		gap: 0.75rem;
	}
	
	.dashboard-hero {
		padding: 0.5rem 0;
		margin-bottom: 0;
	}
	
	.welcome-title {
		font-size: 1.35rem;
	}
	
	/* Hide desktop stats grid on mobile */
	.dashboard-stats {
		display: none;
	}
	
	/* Show FAB on mobile */
	.fab-button {
		display: flex;
	}
	
	/* Hide Post a Job card on mobile */
	.desktop-only {
		display: none !important;
	}
	
	.quick-actions {
		grid-template-columns: 1fr;
		gap: 0.65rem;
	}
	
	.action-card {
		padding: 0.85rem 1rem;
		border-radius: 0.75rem;
		min-height: unset;
	}
	
	.action-card h2,
	.action-card h3 {
		font-size: 0.95rem;
		margin: 0;
	}
	
	/* Hide descriptions on mobile */
	.action-description {
		display: none;
	}
	
	.action-card button {
		padding: 0.45rem 1rem;
		font-size: 0.85rem;
	}
	
	/* Collapse sections on mobile */
	.wm-features,
	.wm-how {
		padding: 1.25rem 0.85rem;
		border-radius: 0.75rem;
		margin-bottom: 0.75rem;
	}
	
	.wm-features-title,
	.wm-how-title {
		font-size: 1.15rem;
		margin-bottom: 0.85rem;
	}
	
	.wm-features-grid,
	.wm-how-steps {
		grid-template-columns: 1fr;
		gap: 0.75rem;
	}
	
	.wm-feature-card,
	.wm-how-step {
		padding: 0.85rem 0.85rem;
		border-radius: 0.75rem;
		gap: 0.85rem;
	}
	
	.feature-visual,
	.how-visual {
		width: 2.25rem;
		height: 2.25rem;
		font-size: 1.1rem;
	}
	
	.wm-feature-card h3,
	.wm-how-step h3 {
		font-size: 0.9rem;
		margin-bottom: 0.2rem;
	}
	
	.wm-feature-card p,
	.wm-how-step p {
		font-size: 0.8rem;
		line-height: 1.4;
	}
}

@media (max-width: 480px) {
	.dashboard-wrapper {
		padding: 0.65rem 0.65rem 5rem;
	}
	
	.dashboard-hero {
		margin-bottom: 0;
		padding: 0.35rem 0;
	}
	
	.welcome-title {
		font-size: 1.15rem;
	}
	
	.fab-button {
		bottom: 85px;
		right: 1.25rem;
		width: 52px;
		height: 52px;
	}
	
	.quick-actions {
		gap: 0.6rem;
	}
	
	.action-card {
		padding: 0.75rem 0.85rem;
		border-radius: 0.65rem;
	}
	
	.action-card h3 {
		font-size: 0.9rem;
	}
	
	.action-card button {
		padding: 0.4rem 0.85rem;
		font-size: 0.8rem;
	}
	
	/* Further collapse feature sections */
	.wm-features,
	.wm-how {
		padding: 1rem 0.65rem;
		border-radius: 0.65rem;
	}
	
	.wm-features-title,
	.wm-how-title {
		font-size: 1.05rem;
	}
	
	.wm-feature-card,
	.wm-how-step {
		padding: 0.75rem 0.75rem;
		gap: 0.75rem;
		border-radius: 0.65rem;
	}
	
	.feature-visual,
	.how-visual {
		width: 2rem;
		height: 2rem;
		font-size: 1rem;
	}
	
	.wm-feature-card h3,
	.wm-how-step h3 {
		font-size: 0.85rem;
	}
	
	.wm-feature-card p,
	.wm-how-step p {
		font-size: 0.75rem;
	}
}
</style>