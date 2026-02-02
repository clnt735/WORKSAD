<?php
$currentPage = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$navItems = [
	[
		'label' => 'Home',
		'href' => 'home.php',
		'aliases' => ['home.php', 'index.php'],
		'icon' => 'fa-solid fa-house',
	],
	[
		'label' => 'Post a Job',
		'href' => 'post_job.php',
		'aliases' => ['post_job.php', 'postjob.php'],
		'icon' => 'fa-solid fa-square-plus',
	],
	[
		'label' => 'Job Posts',
		'href' => 'jobs_posted.php',
		'aliases' => ['jobs_posted.php', 'jobposts.php'],
		'icon' => 'fa-solid fa-briefcase',
	],
	[
		'label' => 'Find Talent',
		'href' => 'find_talent.php',
		'aliases' => ['find_talent.php', 'findtalent.php'],
		'icon' => 'fa-solid fa-user-group',
	],
	[
		'label' => 'Matches',
		'href' => 'matches.php',
		'aliases' => ['matches.php'],
		'icon' => 'fa-regular fa-heart',
	],
	[
		'label' => 'Profile',
		'href' => 'profile.php',
		'aliases' => ['profile.php'],
		'icon' => 'fa-solid fa-circle-user',
	],
];

$actionIcons = [
	'notification' => 'fa-solid fa-bell',
];

$buildLinkClasses = function (array $item, bool $active): string {
	$base = 'nav-link';
	if (!empty($item['class'])) {
		$base .= ' ' . $item['class'];
	}
	if ($active) {
		$base .= ' active';
	}
	return trim($base);
};
?>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
<nav class="employer-navbar">
	<div class="navbar-inner">
		<a class="brand" href="home.php" aria-label="Workmuna Employer home">
			<span class="brand-icon" aria-hidden="true">
				<img src="../images/workmunalogo2-removebg.png" alt="Workmuna logo" />
			</span>
		</a>

		<div class="nav-actions">
			<div class="notification-wrapper">
				<button class="icon-button notification" type="button" id="notificationBtn" aria-label="Notifications" aria-expanded="false">
					<i class="<?php echo htmlspecialchars($actionIcons['notification'], ENT_QUOTES); ?>"></i>
					<span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
				</button>
				<div class="notification-dropdown" id="notification-dropdown" aria-hidden="true">
					<header>
						<div>
							<p class="notification-eyebrow">Updates</p>
							<h2>Notifications</h2>
						</div>
						<button class="dropdown-close" aria-label="Close notifications" id="dropdownClose">
							<i class="fas fa-times"></i>
						</button>
					</header>
					<div class="notification-list" id="notificationList">
						<div class="notification-loading">Loading...</div>
					</div>
				</div>
			</div>

			<div class="profile-wrapper">
				<button class="profile-avatar" id="profileBtn" aria-haspopup="true" aria-expanded="false">
					<i class="fa-solid fa-circle-user"></i>
				</button>

				<div class="profile-dropdown" id="profileDropdown" aria-hidden="true">
					<a href="profile.php" class="dropdown-item">Profile</a>
					<a href="settings.php" class="dropdown-item">Settings</a>
					<form action="logout.php" method="POST">
						<button type="submit" class="dropdown-item logout-btn">Logout</button>
					</form>
				</div>
			</div>
		</div>

		<div class="nav-links" id="employer-nav-links" data-current-page="<?php echo htmlspecialchars($currentPage, ENT_QUOTES); ?>">
			<ul>
				<?php foreach ($navItems as $item):
					$aliases = $item['aliases'] ?? [$item['href']];
					$isActive = in_array($currentPage, $aliases, true);
					$linkClasses = $buildLinkClasses($item, $isActive);
					$targetsAttr = htmlspecialchars(implode(',', $aliases), ENT_QUOTES);
				?>
				<li>
					<a
						class="<?php echo htmlspecialchars($linkClasses, ENT_QUOTES); ?>"
						href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES); ?>"
						data-nav-targets="<?php echo $targetsAttr; ?>"
						<?php if ($isActive): ?>aria-current="page"<?php endif; ?>
					>
						<?php if (!empty($item['icon'])): ?>
							<i class="<?php echo htmlspecialchars($item['icon'], ENT_QUOTES); ?>" aria-hidden="true"></i>
						<?php endif; ?>
						<?php echo htmlspecialchars($item['label']); ?>
					</a>
				</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</nav>

<div class="navbar-spacer" aria-hidden="true"></div>

<!-- ================= MOBILE BOTTOM NAV (EMPLOYER) ================= -->
<nav class="employer-bottom-nav">
	<?php
	$activePage = isset($activePage) ? $activePage : '';
	?>
	<a href="home.php" class="<?= ($activePage === 'home') ? 'active' : '' ?>">
		<span class="material-symbols-outlined">home</span>
		Home
	</a>
	
	<a href="post_job.php" class="<?= ($activePage === 'post_job') ? 'active' : '' ?>">
		<span class="material-symbols-outlined">add_circle</span>
		Post Job
	</a>
	
	<a href="jobs_posted.php" class="<?= ($activePage === 'jobs_posted') ? 'active' : '' ?>">
		<span class="material-symbols-outlined">work</span>
		Job Posts
	</a>
	
	<a href="find_talent.php" class="<?= ($activePage === 'find_talent') ? 'active' : '' ?>">
		<span class="material-symbols-outlined">group</span>
		Talent
	</a>
	
	<a href="matches.php" class="<?= ($activePage === 'matches') ? 'active' : '' ?>">
		<span class="material-symbols-outlined">favorite</span>
		Matches
	</a>
	
	<a href="profile.php" class="<?= ($activePage === 'profile') ? 'active' : '' ?>">
		<span class="material-symbols-outlined">person</span>
		Profile
	</a>
</nav>

<style>

:root {
	--nav-height: 88px;
}

.employer-navbar {
	--nav-active-color: #1f7bff;
	--nav-inline-padding: clamp(1rem, 4vw, 2rem);
	background-color: #fff;
	border-bottom: 1px solid #eee;
	font-family: "Poppins", "Segoe UI", Tahoma, sans-serif;
	padding: 1rem var(--nav-inline-padding);
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	z-index: 1000;
}

.navbar-spacer {
	height: var(--nav-height);
}

.employer-navbar.is-hidden {
	transform: translateY(-110%);
	box-shadow: none;
}

.navbar-inner {
	max-width: 1280px;
	width: 100%;
	margin: 0 auto;
	display: flex;
	align-items: center;
	gap: 1.5rem;
	min-height: 48px;
}

.brand {
	display: flex;
	align-items: center;
	text-decoration: none;
	order: 1;
}

.brand-icon {
	height: 36px;
	width: auto;
	display: inline-flex;
	align-items: center;
	justify-content: center;
}

.brand-icon img {
	height: 32px;
	width: auto;
	color: var(--nav-active-color);
}

.nav-links {
	display: flex;
	align-items: center;
	gap: 1.5rem;
	flex: 1;
	order: 2;
}


.nav-links ul {
	display: flex;
	align-items: center;
	gap: 0.55rem;
	list-style: none;
	margin: 0;
	padding: 0;
}

.nav-links .nav-link {
	text-decoration: none;
	color: #222;
	font-weight: 500;
	font-size: 1rem;
	padding: 0.35rem 0.9rem 0.35rem 0.85rem;
	border-radius: 0.35rem;
	position: relative;
	display: inline-flex;
	align-items: center;
	gap: 0.1rem;
}

.nav-links .nav-link i {
	font-size: 1rem;
	color: inherit;
}

.nav-links .nav-link::before {
	content: '';
	position: absolute;
	left: 0.35rem;
	top: 50%;
	width: 4px;
	height: 60%;
	border-radius: 999px;
	background: var(--nav-active-color);
	transform: translateY(-50%);
	opacity: 0;
}

.nav-links .nav-link:hover,
.nav-links .nav-link:focus-visible {
	color: var(--nav-active-color);
}

.nav-links .nav-link.active {
	color: var(--nav-active-color);
	background: rgba(31, 123, 255, 0.08);
}

.nav-links .nav-link.active::before {
	opacity: 1;
}

.nav-links .nav-link.active:focus-visible {
	outline: 2px solid rgba(31, 123, 255, 0.28);
	outline-offset: 2px;
}

.nav-actions {
	display: flex;
	align-items: center;
	gap: 0.75rem;
	margin-left: auto;
	order: 3;
}

.notification-wrapper {
	position: relative;
}

.icon-button {
	background: #f5f5f5;
	border: none;
	cursor: pointer;
	position: relative;
	padding: 0;
	width: 40px;
	height: 40px;
	border-radius: 50%;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	transition: background 0.2s;
}

.icon-button:hover {
	background: #e5e5e5;
}

.icon-button i {
	font-size: 1.1rem;
	color: #333;
	pointer-events: none;
}

.notification .badge {
	position: absolute;
	top: -4px;
	right: -4px;
	background: #e74c3c;
	color: white;
	font-size: 0.65rem;
	min-width: 18px;
	height: 18px;
	border-radius: 9px;
	display: flex;
	align-items: center;
	justify-content: center;
	font-weight: 600;
	padding: 0 4px;
}

.notification-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  width: 18px;
  height: 18px;
  background: #e74c3c;
  color: #fff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 600;
  line-height: 1;
  transform: translateY(0.5px);
}

.notification-loading,
.notification-empty {
	padding: 2rem 1rem;
	text-align: center;
	color: #6b7280;
	font-size: 0.9rem;
}

.notification-empty i {
	font-size: 2.5rem;
	color: #d1d5db;
	margin-bottom: 0.75rem;
	display: block;
}

.notification-empty p {
	margin: 0;
	color: #6b7280;
}

.sr-only {
	position: absolute;
	width: 1px;
	height: 1px;
	padding: 0;
	margin: -1px;
	overflow: hidden;
	clip: rect(0, 0, 0, 0);
	border: 0;
}

.notification-wrapper {
	position: relative;
	display: inline-block;
}

.notification-dropdown {
	position: fixed;
	top: 60px;
	right: 10px;
	width: 360px;
	max-width: calc(100vw - 20px);
	background: white;
	border-radius: 12px;
	box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
	padding: 0;
	z-index: 9999;
	opacity: 0;
	visibility: hidden;
	transform: translateY(-10px);
	pointer-events: none;
	max-height: 500px;
	display: flex;
	flex-direction: column;
	transition: all 0.2s;
}

.notification-dropdown.show {
	opacity: 1;
	visibility: visible;
	transform: translateY(0);
	pointer-events: auto;
}

.notification-dropdown header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 1rem;
	padding: 16px 20px;
	border-bottom: 1px solid #e5e7eb;
}

.notification-eyebrow {
	text-transform: uppercase;
	font-size: 0.7rem;
	letter-spacing: 0.1em;
	color: #94a3b8;
	margin: 0;
}

.notification-dropdown header h2 {
	margin: 0.25rem 0 0 0;
	font-size: 1.15rem;
	color: #111827;
}

.dropdown-close {
	border: none;
	background: #f4f5f7;
	width: 30px;
	height: 30px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	cursor: pointer;
	transition: background 0.2s;
	color: #6b7280;
}

.dropdown-close:hover {
	background: #e5e7eb;
}

.notification-list {
	overflow-y: auto;
	flex: 1;
}

.notification-list::-webkit-scrollbar {
	width: 6px;
}

.notification-list::-webkit-scrollbar-track {
	background: #f3f4f6;
}

.notification-list::-webkit-scrollbar-thumb {
	background: #d1d5db;
	border-radius: 3px;
}

.notification-item {
	display: flex;
	gap: 12px;
	align-items: flex-start;
	padding: 12px 20px;
	border-bottom: 1px solid #f3f4f6;
	cursor: pointer;
	transition: background 0.2s;
}

.notification-item:hover {
	background: #f9fafb;
}

.notification-item.unread {
	background: #eff6ff;
}

.notification-item.unread:hover {
	background: #dbeafe;
}

.notification-icon {
	width: 36px;
	height: 36px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 0.9rem;
	flex-shrink: 0;
}

.notification-icon.like {
	background: #fce7f3;
	color: #ec4899;
}

.notification-icon.match {
	background: #dcfce7;
	color: #22c55e;
}

.notification-icon.interview {
	background: #dbeafe;
	color: #3b82f6;
}

.notification-content {
	flex: 1;
	min-width: 0;
}

.notification-title {
	margin: 0 0 4px 0;
	font-weight: 600;
	font-size: 0.9rem;
	color: #111827;
}

.notification-message {
	margin: 0 0 4px 0;
	color: #6b7280;
	font-size: 0.85rem;
	line-height: 1.4;
}

.notification-text {
	margin: 0;
	color: #111827;
	font-size: 0.95rem;
}

.notification-text .job-title {
	color: var(--nav-active-color);
	font-weight: 600;
}

.notification-time {
	margin: 0;
	color: #9ca3af;
	font-size: 0.75rem;
}

.profile-wrapper {
    position: relative;
    display: inline-block;
}

.profile-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    border: none;
    background: none;
    padding: 0;
    cursor: pointer;
    display: flex;
    justify-content: center;
    align-items: center;
}

.profile-avatar i {
    font-size: 32px;
    color: #333;
}

.profile-dropdown {
    position: absolute;
    top: 48px;
    right: 0;
    width: 150px;
    background: #fff;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    display: none;
    flex-direction: column;
    padding: 6px 0;
    z-index: 999;
}

.profile-dropdown .dropdown-item {
    padding: 10px 14px;
    font-size: 14px;
    color: #333;
    text-decoration: none;
    display: block;
}

.profile-dropdown .dropdown-item:hover {
    background: #f2f2f2;
}

.logout-btn {
    width: 100%;
    text-align: left;
    border: none;
    background: none;
    padding: 10px 14px;
    cursor: pointer;
    font-size: 14px;
}


@media (max-width: 900px) {
	.navbar-inner {
		flex-wrap: wrap;
	}
}

@media (max-width: 600px) {
	.navbar-inner {
		align-items: center;
	}

	.brand {
		order: 1;
	}

	.nav-actions {
		order: 2;
		margin-left: auto;
	}

	/* Hide desktop navigation on mobile - bottom nav will be used instead */
	.nav-links {
		display: none !important;
	}

	/* Add padding to accommodate bottom navigation bar */
	body {
		padding-bottom: 70px;
	}
}

/* ================= EMPLOYER MOBILE BOTTOM NAVIGATION ================= */
.employer-bottom-nav {
	display: none; /* Hidden by default (desktop) */
}

@media (max-width: 600px) {
	.employer-bottom-nav {
		position: fixed;
		bottom: 0;
		left: 0;
		width: 100%;
		background: white;
		border-top: 1px solid #ddd;
		display: flex;
		justify-content: space-around;
		padding: 8px 0;
		z-index: 999;
		box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
	}

	.employer-bottom-nav a {
		color: #6b7280;
		text-decoration: none;
		font-size: 11px;
		font-weight: 500;
		font-family: 'Roboto', -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		gap: 3px;
		outline: none;
		position: relative;
		overflow: hidden;
		padding: 6px 4px;
		border-radius: 8px;
		transition: all 0.2s ease;
		flex: 1;
		max-width: 70px;
		line-height: 1.1;
	}

	.employer-bottom-nav a:focus,
	.employer-bottom-nav a:active {
		outline: none;
		box-shadow: none;
	}

	.employer-bottom-nav a .material-symbols-outlined {
		font-size: 22px;
		transition: color 0.2s ease;
		line-height: 1;
	}

	.employer-bottom-nav a.active,
	.employer-bottom-nav a.active .material-symbols-outlined {
		color: #1f7bff;
		font-weight: 600;
	}

	.employer-bottom-nav a::after {
		content: "";
		position: absolute;
		top: 50%;
		left: 50%;
		transform: translate(-50%, -50%);
		width: 0;
		height: 0;
		background: rgba(31, 123, 255, 0.15);
		border-radius: 50%;
		opacity: 0;
		transition: width 0.4s ease, height 0.4s ease, opacity 0.4s ease;
	}

	.employer-bottom-nav a:active::after {
		width: 100px;
		height: 100px;
		opacity: 1;
	}
}
</style>












<script>

(function () {
	const navbar = document.querySelector('.employer-navbar');
	const navSpacer = document.querySelector('.navbar-spacer');
	const links = document.getElementById('employer-nav-links');
	const navAnchors = links ? Array.from(links.querySelectorAll('.nav-link')) : [];
	const notificationButton = document.getElementById('notificationBtn');
	const notificationDropdown = document.getElementById('notification-dropdown');
	const dropdownClose = document.getElementById('dropdownClose');
	const notificationList = document.getElementById('notificationList');
	const notificationBadge = document.getElementById('notificationBadge');
	const notificationWrapper = document.querySelector('.notification-wrapper');
	let lastScrollY = window.scrollY;
	let scrollTicking = false;
	let navIsHidden = false;
	const SCROLL_HIDE_THRESHOLD = 40;
	const MIN_SCROLL_DELTA = 5;

	const measureNavHeight = () => {
		if (!navbar) {
			return '0px';
		}
		const measured = `${navbar.offsetHeight}px`;
		document.documentElement.style.setProperty('--nav-height', measured);
		return measured;
	};

	const setSpacerHeight = (value) => {
		if (navSpacer) {
			navSpacer.style.height = value;
		}
	};

	const showNavbar = () => {
		if (!navbar) {
			return;
		}
		if (navIsHidden) {
			navbar.classList.remove('is-hidden');
			navIsHidden = false;
		}
	};

	const hideNavbar = () => {
		if (!navbar || navIsHidden) {
			return;
		}
		navbar.classList.add('is-hidden');
		navIsHidden = true;
	};

	const syncNavHeight = () => {
		const measured = measureNavHeight();
		setSpacerHeight(measured);
	};

	syncNavHeight();

	const parseTargets = (anchor) => (anchor.dataset.navTargets || '')
		.split(',')
		.map((target) => target.trim().toLowerCase())
		.filter(Boolean);

	const setActiveLink = (target) => {
		navAnchors.forEach(anchor => {
			const isActive = anchor === target;
			anchor.classList.toggle('active', isActive);
			if (isActive) {
				anchor.setAttribute('aria-current', 'page');
			} else {
				anchor.removeAttribute('aria-current');
			}
		});
	};

	const activateLinkByPage = (page) => {
		if (!page) {
			return;
		}
		const normalized = page.toLowerCase();
		const match = navAnchors.find(anchor => parseTargets(anchor).includes(normalized));
		if (match) {
			setActiveLink(match);
		}
	};

	const handleScrollVisibility = () => {
		if (!navbar) {
			scrollTicking = false;
			return;
		}
		const currentScroll = window.scrollY;
		const scrollDelta = Math.abs(currentScroll - lastScrollY);
		const isScrollingDown = currentScroll > lastScrollY;
		const isScrollingUp = currentScroll < lastScrollY;
		const nearTop = currentScroll <= SCROLL_HIDE_THRESHOLD;
		const shouldForceShow = nearTop;

		if (shouldForceShow) {
			showNavbar();
		} else if (isScrollingDown && scrollDelta > MIN_SCROLL_DELTA) {
			hideNavbar();
		} else if (isScrollingUp && scrollDelta > MIN_SCROLL_DELTA) {
			showNavbar();
		}

		lastScrollY = Math.max(window.scrollY, 0);
		scrollTicking = false;
	};

	const onScroll = () => {
		if (!scrollTicking) {
			scrollTicking = true;
			window.requestAnimationFrame(handleScrollVisibility);
		}
	};

	const handleResize = () => {
		syncNavHeight();
	};

	window.addEventListener('scroll', onScroll, { passive: true });
	window.addEventListener('resize', handleResize);

	const currentPageFromServer = links ? (links.dataset.currentPage || '') : '';
	const fallbackPage = window.location.pathname.split('/').pop() || '';
	activateLinkByPage(currentPageFromServer || fallbackPage);

	navAnchors.forEach(anchor => {
		anchor.addEventListener('click', function (event) {
			setActiveLink(event.currentTarget);
		});
	});

	const closeDropdown = () => {
		if (!notificationDropdown || !notificationButton) return;
		notificationDropdown.classList.remove('show');
		notificationDropdown.setAttribute('aria-hidden', 'true');
		notificationButton.setAttribute('aria-expanded', 'false');
	};

	const openDropdown = () => {
		if (!notificationDropdown || !notificationButton) return;
		console.log('Opening dropdown');
		notificationDropdown.classList.add('show');
		notificationDropdown.setAttribute('aria-hidden', 'false');
		notificationButton.setAttribute('aria-expanded', 'true');
		loadNotifications();
	};

	const escapeHtml = (text) => {
		const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
		return text.replace(/[&<>"']/g, m => map[m]);
	};

	const loadNotifications = async () => {
		if (!notificationList) return;
		notificationList.innerHTML = '<div class="notification-loading">Loading...</div>';

		try {
			const response = await fetch('api/get_notifications.php');
			const data = await response.json();

			if (data.success) {
				displayNotifications(data.notifications);
				updateBadge(data.unread_count);
			} else {
				notificationList.innerHTML = '<div class="notification-empty">Failed to load notifications</div>';
			}
		} catch (error) {
			console.error('Error loading notifications:', error);
			notificationList.innerHTML = '<div class="notification-empty">Error loading notifications</div>';
		}
	};

	const displayNotifications = (notifications) => {
		if (!notificationList) return;

		if (notifications.length === 0) {
			notificationList.innerHTML = '<div class="notification-empty"><i class="fa-solid fa-bell-slash"></i><p>No notifications yet</p></div>';
			return;
		}

		notificationList.innerHTML = notifications.map(notif => {
			const iconType = notif.type === 'like' ? 'fa-heart' : 
			                 notif.type === 'match' ? 'fa-handshake' : 
			                 'fa-calendar-check';
			const readClass = notif.is_read == 1 ? 'read' : 'unread';

			return `
				<div class="notification-item ${readClass}" data-id="${notif.id}" data-url="${notif.nav_url || '#'}" data-timestamp="${notif.created_at}">
					<div class="notification-icon ${notif.type}">
						<i class="fa-solid ${iconType}"></i>
					</div>
					<div class="notification-content">
						<div class="notification-title">${escapeHtml(notif.title)}</div>
						<div class="notification-message">${escapeHtml(notif.message)}</div>
						<div class="notification-time" data-timestamp="${notif.created_at}">${escapeHtml(notif.time_ago)}</div>
					</div>
				</div>
			`;
		}).join('');

		document.querySelectorAll('.notification-item').forEach(item => {
			item.addEventListener('click', handleNotificationClick);
		});

		// Update times dynamically
		updateNotificationTimes();
	};

	const updateBadge = (count) => {
		if (!notificationBadge) return;

		if (count > 0) {
			notificationBadge.textContent = count > 99 ? '99+' : count;
			notificationBadge.style.display = 'inline-block';
		} else {
			notificationBadge.style.display = 'none';
		}
	};

	const handleNotificationClick = async (event) => {
		const item = event.currentTarget;
		const notificationId = item.getAttribute('data-id');
		const navUrl = item.getAttribute('data-url');

		if (item.classList.contains('unread')) {
			try {
				const response = await fetch('api/mark_notification_read.php', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ notification_id: notificationId })
				});

				const data = await response.json();
				if (data.success) {
					item.classList.remove('unread');
					item.classList.add('read');
					loadNotifications();
				}
			} catch (error) {
				console.error('Error marking notification as read:', error);
			}
		}

		// Navigate if URL is provided
		if (navUrl && navUrl !== '#') {
			window.location.href = navUrl;
		}
	};

	const updateNotificationTimes = () => {
		const timeElements = document.querySelectorAll('.notification-time[data-timestamp]');
		timeElements.forEach(el => {
			const timestamp = el.getAttribute('data-timestamp');
			if (timestamp) {
				el.textContent = formatTimeAgo(timestamp);
			}
		});
	};

	const formatTimeAgo = (datetime) => {
		const time = new Date(datetime).getTime();
		const now = Date.now();
		const diff = Math.floor((now - time) / 1000);

		if (diff < 60) {
			return 'Just now';
		} else if (diff < 3600) {
			const mins = Math.floor(diff / 60);
			return mins + ' min' + (mins > 1 ? 's' : '') + ' ago';
		} else if (diff < 86400) {
			const hours = Math.floor(diff / 3600);
			return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
		} else if (diff < 604800) {
			const days = Math.floor(diff / 86400);
			return days + ' day' + (days > 1 ? 's' : '') + ' ago';
		} else {
			const date = new Date(time);
			return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
		}
	};

	if (notificationButton && notificationDropdown) {
		notificationButton.addEventListener('click', function (e) {
			e.stopPropagation();
			console.log('Notification button clicked');
			if (notificationDropdown.classList.contains('show')) {
				closeDropdown();
			} else {
				openDropdown();
			}
		});
	}

	if (dropdownClose) {
		dropdownClose.addEventListener('click', closeDropdown);
	}

	document.addEventListener('click', function (event) {
		if (!notificationWrapper || !notificationDropdown) return;
		if (!notificationWrapper.contains(event.target)) {
			closeDropdown();
		}
	});

	document.addEventListener('keyup', function (event) {
		if (event.key === 'Escape') {
			closeDropdown();
		}
	});

	loadNotifications();
	setInterval(loadNotifications, 60000);
	// Update notification times every 30 seconds
	setInterval(updateNotificationTimes, 30000);
})();







const profileBtn = document.getElementById("profileBtn");
const profileDropdown = document.getElementById("profileDropdown");

// Toggle dropdown
profileBtn.addEventListener("click", () => {
    const isVisible = profileDropdown.style.display === "block";
    profileDropdown.style.display = isVisible ? "none" : "block";
});

// Close when clicking outside
document.addEventListener("click", (e) => {
    if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
        profileDropdown.style.display = "none";
    }
});

</script> 
