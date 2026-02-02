<?php
require_once __DIR__ . '/session_guard.php';

if (!function_exists('renderAdminSidebar')) {
  function renderAdminSidebar(): void {
    ?>
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-header">
        <button id="toggle-btn" class="toggle-btn" type="button">
          <i class="fa-solid fa-bars"></i>
        </button>
        <h2>
          <img src="../images/workmunalogo2-removebg.png" alt="WorkMuna Logo">
        </h2>
      </div>

      <ul class="sidebar-menu">
        <li><a href="dashboard.php" data-tooltip="Dashboard"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a></li>



<!-- MANAGEMENT DROPDOWN -->
<li class="sidebar-dropdown">
  <button type="button" class="dropdown-btn" id="managementDropdownBtn" data-tooltip="Managements">
    <i class="fa-solid fa-sitemap"></i> <span>Managements</span> <i class="fa-solid fa-chevron-down dropdown-caret"></i>
  </button>
  <ul class="dropdown-menu" id="managementDropdownMenu">
    <?php if (isSuperAdmin()): ?>
    <li><a href="admin_management.php" data-tooltip="Admin Management"><i class="fa-solid fa-user-shield"></i> <span>Admin Management</span></a></li>
    <?php endif; ?>
    <!-- TODO: Uncomment when ready to implement admin-side employer verification -->
    <!-- <li><a href="employer_verification.php" data-tooltip="Employer Verification"><i class="fa-solid fa-clipboard-check"></i> <span>Employer Verification</span></a></li> -->
    <li><a href="users.php" data-tooltip="User Management"><i class="fa-solid fa-users"></i> <span>User Management</span></a></li>
    <li><a href="jobs.php" data-tooltip="Jobs Management"><i class="fa-solid fa-briefcase"></i> <span>Jobs Management</span></a></li>
    <li><a href="category_dropdowns.php" data-tooltip="Dropdown Categories"><i class="fa-solid fa-table-list"></i> <span>Dropdown Categories</span></a></li>
    <li><a href="categories.php" data-tooltip="Categories Management"><i class="fa-solid fa-layer-group"></i> <span>Categories Management</span></a></li>
  </ul>
</li>
<!-- END MANAGEMENT DROPDOWN -->

<!-- <li><a href="reports.php" data-tooltip="Reported Content"><i class="fa-solid fa-chart-line"></i> <span>Reported Content</span></a></li> -->
<li><a href="logs.php" data-tooltip="Logs/Audit"><i class="fa-solid fa-file-lines"></i> <span>Logs/Audit</span></a></li>
<li><a href="settings.php" data-tooltip="Settings"><i class="fa-solid fa-gear"></i> <span>Settings</span></a></li>

      </ul>

      <div class="sidebar-footer">
        <form id="logoutForm" method="POST" action="logout.php" style="margin:0;">
          <button id="logoutBtn" type="submit" class="logout-btn" data-tooltip="Logout">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logout</span>
          </button>
        </form>
      </div>
    </aside>

    <script>
      (function () {
        const sidebarLinks = document.querySelectorAll('.sidebar-menu li a');
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('toggle-btn');

        const applySidebarState = (collapsed) => {
          sidebar.classList.toggle('collapsed', collapsed);
          document.body.classList.toggle('sidebar-collapsed', collapsed);
          try {
            localStorage.setItem('sidebarCollapsed', collapsed);
          } catch (err) {
            // ignore storage failures
          }
        };

        const storedState = (() => {
          try {
            return localStorage.getItem('sidebarCollapsed');
          } catch (err) {
            return null;
          }
        })();

        applySidebarState(storedState === 'true');

        if (toggleBtn) {
          toggleBtn.addEventListener('click', () => {
            const collapsed = !sidebar.classList.contains('collapsed');
            applySidebarState(collapsed);
          });
        }

        const dropdownBtn = document.getElementById('managementDropdownBtn');
        const dropdownMenu = document.getElementById('managementDropdownMenu');
        const dropdownLi = dropdownBtn ? dropdownBtn.closest('.sidebar-dropdown') : null;
        const dropdownLinks = dropdownMenu ? Array.from(dropdownMenu.querySelectorAll('a')) : [];

        const hasDropdown = Boolean(dropdownBtn && dropdownMenu && dropdownLi);
        const dropdownStorageKey = 'adminManagementDropdownOpen';
        let sessionStore = null;

        try {
          sessionStore = window.sessionStorage;
        } catch (err) {
          sessionStore = null;
        }

        const persistDropdownPreference = (open) => {
          if (!sessionStore) return;
          try {
            if (open) {
              sessionStore.setItem(dropdownStorageKey, 'true');
            } else {
              sessionStore.removeItem(dropdownStorageKey);
            }
          } catch (err) {
            // ignore storage failures
          }
        };

        const shouldRestoreDropdownOpen = () => {
          if (!sessionStore) return false;
          try {
            const navEntries = typeof performance !== 'undefined' && typeof performance.getEntriesByType === 'function'
              ? performance.getEntriesByType('navigation')
              : null;
            const navType = navEntries && navEntries.length ? navEntries[0].type : null;

            const legacyNavType = typeof performance !== 'undefined' && performance.navigation
              ? performance.navigation.type
              : null;

            const isReload = navType === 'reload' || legacyNavType === 1;

            if (isReload) {
              sessionStore.removeItem(dropdownStorageKey);
              return false;
            }

            return sessionStore.getItem(dropdownStorageKey) === 'true';
          } catch (err) {
            return false;
          }
        };

        const clearDropdownActiveLinks = () => {
          if (!hasDropdown) return;
          dropdownMenu.querySelectorAll('li').forEach(li => li.classList.remove('active'));
        };

        const updateDropdownVisualState = () => {
          if (!hasDropdown) return;
          const hasActiveChild = dropdownMenu.querySelector('li.active');
          dropdownLi.classList.toggle('active', dropdownLi.classList.contains('open') || Boolean(hasActiveChild));
        };

        const setDropdownState = (open) => {
          if (!hasDropdown) return;
          dropdownLi.classList.toggle('open', open);
          dropdownBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
          updateDropdownVisualState();
        };

        const restoreDropdownOpen = hasDropdown ? shouldRestoreDropdownOpen() : false;

        if (hasDropdown) {
          dropdownBtn.setAttribute('aria-expanded', restoreDropdownOpen ? 'true' : 'false');
          setDropdownState(restoreDropdownOpen);
        }

        const currentPage = window.location.pathname;
        const currentFile = currentPage.split('/').pop();

        const clearActiveLinks = () => {
          sidebarLinks.forEach(item => item.parentElement.classList.remove('active'));
        };

        let dropdownActivatedByRoute = false;

        sidebarLinks.forEach(link => {
          const href = (link.getAttribute('href') || '').split('?')[0];
          if (!href) {
            return;
          }

          if (href === currentFile) {
            link.parentElement.classList.add('active');
            const parentDropdown = link.closest('.sidebar-dropdown');
            if (hasDropdown && parentDropdown === dropdownLi) {
              dropdownActivatedByRoute = true;
              clearDropdownActiveLinks();
              link.parentElement.classList.add('active');
              if (restoreDropdownOpen) {
                setDropdownState(true);
              } else {
                updateDropdownVisualState();
              }
            }
          }
        });

        if (hasDropdown && !dropdownActivatedByRoute && !restoreDropdownOpen) {
          setDropdownState(false);
        }

        sidebarLinks.forEach(link => {
          link.addEventListener('click', () => {
            clearActiveLinks();
            link.parentElement.classList.add('active');

            if (hasDropdown) {
              const inDropdown = link.closest('.sidebar-dropdown') === dropdownLi;
              if (inDropdown) {
                clearDropdownActiveLinks();
                link.parentElement.classList.add('active');
                setDropdownState(true);
              } else {
                clearDropdownActiveLinks();
                dropdownLi.classList.remove('active');
              }
            }
          });
        });

        const ensureSweetAlert = () => new Promise((resolve, reject) => {
          if (window.Swal) {
            resolve();
            return;
          }
          const existing = document.querySelector('script[data-swal-loader]');
          if (existing) {
            existing.addEventListener('load', () => resolve());
            existing.addEventListener('error', () => reject(new Error('Failed to load SweetAlert2.')));
            return;
          }
          const script = document.createElement('script');
          script.src = '../assets/vendor/sweetalert2/sweetalert2.all.min.js';
          script.async = true;
          script.defer = true;
          script.dataset.swalLoader = 'true';
          script.onload = () => resolve();
          script.onerror = () => reject(new Error('Failed to load SweetAlert2.'));
          document.head.appendChild(script);
        });

        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
          logoutBtn.addEventListener('click', async (e) => {
            e.preventDefault();

            try {
              await ensureSweetAlert();
            } catch (loaderError) {
              console.error(loaderError);
              if (!window.confirm('Are you sure you want to logout?')) {
                return;
              }
            }

            let confirmation = { isConfirmed: true };
            if (window.Swal) {
              confirmation = await Swal.fire({
                icon: 'warning',
                title: 'Sign out?',
                text: 'You will need to log in again to access the admin tools.',
                confirmButtonText: 'Yes, log me out',
                confirmButtonColor: '#dc2626',
                showCancelButton: true,
                cancelButtonText: 'Stay signed in',
                cancelButtonColor: '#6b7280',
              });
            }

            if (!confirmation.isConfirmed) {
              return;
            }

            let token = null;
            try {
              token = localStorage.getItem('token');
            } catch (err) {
              // ignore
            }

            if (!token) {
              const match = document.cookie.match(/(?:^|; )token=([^;]+)/);
              if (match) token = decodeURIComponent(match[1]);
            }

            const form = new FormData();
            if (token) form.append('token', token);

            if (window.Swal) {
              Swal.fire({
                title: 'Signing out…',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                  Swal.showLoading();
                }
              });
            }

            try {
              const response = await fetch('logout.php', {
                method: 'POST',
                body: form,
                credentials: 'same-origin'
              });

              if (!response.ok) {
                throw new Error('Logout request failed.');
              }

              try { localStorage.removeItem('token'); } catch (err) {}
              document.cookie = 'token=; Max-Age=0; path=/';

              if (window.Swal) {
                Swal.close();
              }

              window.location.href = 'login.php';
            } catch (err) {
              console.error('Logout failed', err);
              if (window.Swal) {
                Swal.fire({
                  icon: 'error',
                  title: 'Logout failed',
                  text: 'Something went wrong while signing out. Please try again.',
                  confirmButtonColor: '#2563eb'
                });
              } else {
                window.alert('Logout failed. Please try again.');
              }
            }
          });
        }

        if (hasDropdown) {
          dropdownBtn.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const willOpen = !dropdownLi.classList.contains('open');
            setDropdownState(willOpen);
            persistDropdownPreference(willOpen);
          });

          dropdownMenu.addEventListener('click', (event) => {
            event.stopPropagation();
          });

          dropdownLinks.forEach(link => {
            link.addEventListener('click', () => {
              clearDropdownActiveLinks();
              link.parentElement.classList.add('active');
              setDropdownState(true);
            });
          });
        }
      })();
    </script>
    <?php
  }
}
