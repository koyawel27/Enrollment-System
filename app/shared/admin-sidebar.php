<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin Sidebar Component
 * app/shared/admin-sidebar.php
 */

if (!isset($_SESSION['admin_name']) || !isset($_SESSION['admin_email'])) {
    die('Unauthorized access to sidebar component.');
}
require_once CONFIG_PATH . '/admin-permissions.php';

$current_page    = basename($_SERVER['PHP_SELF']);
$can_manage_users = admin_can_manage_users();
$can_encode_exams = admin_can_encode_exams();
$can_view_exams   = admin_can_view_exams();
$is_registrar     = ($_SESSION['admin_role'] ?? '') === ADMIN_ROLE_REGISTRAR;
$is_program_head  = ($_SESSION['admin_role'] ?? '') === ADMIN_ROLE_PROGRAM_HEAD;
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <img src="../../assets/cropped-cropped-cropped-cropped-cropped-bpclogo-1-1-1-150x150.png" alt="BPC">
        <div class="sidebar-brand-text">
            <h2>BPC iEnroll</h2>
            <p style="margin:0;">
                <?php echo htmlspecialchars(get_admin_role_label($_SESSION['admin_role'] ?? '')); ?>
            </p>
        </div>
        <button type="button" class="sidebar-toggle" id="sidebarToggle"
                aria-label="Toggle sidebar" title="Toggle sidebar">☰</button>
    </div>

    <nav class="sidebar-nav">
        <!-- MAIN -->
        <p class="nav-label">Main</p>
        <a href="admin-dashboard.php"
           class="<?php echo $current_page === 'admin-dashboard.php' ? 'active' : ''; ?>"
           title="Dashboard">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
            </svg>
            <span class="nav-text">Dashboard</span>
        </a>

        <?php if (!$is_program_head): ?>
        <a href="admin-applications.php"
           class="<?php echo $current_page === 'admin-applications.php' ? 'active' : ''; ?>"
           title="All Applications">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
            </svg>
            <span class="nav-text">Applications</span>
        </a>
        <?php endif; ?>

        <!-- EXAMINATION -->
        <?php if (!$is_registrar): ?>
        <p class="nav-label">Examination</p>

        <?php if ($can_encode_exams): ?>
        <a href="admin-exam-schedule.php"
           class="<?php echo $current_page === 'admin-exam-schedule.php' ? 'active' : ''; ?>"
           title="Exam Schedule">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/>
            </svg>
            <span class="nav-text">Exam Schedule</span>
        </a>
        <a href="admin-exam-results.php"
           class="<?php echo $current_page === 'admin-exam-results.php' ? 'active' : ''; ?>"
           title="Exam Results">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
            </svg>
            <span class="nav-text">Exam Results</span>
        </a>
        <?php elseif ($is_program_head && $can_view_exams): ?>
        <a href="admin-exam-view.php"
           class="<?php echo $current_page === 'admin-exam-view.php' ? 'active' : ''; ?>"
           title="View Exam Scores">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
            </svg>
            <span class="nav-text">Exam Scores</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <!-- INTERVIEW -->
        <p class="nav-label">Interview</p>
        <?php if (!$is_registrar): ?>
        <a href="admin-interview-schedule.php"
           class="<?php echo $current_page === 'admin-interview-schedule.php' ? 'active' : ''; ?>"
           title="Interview Schedule">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
            </svg>
            <span class="nav-text">Interview Schedule</span>
        </a>
        <a href="admin-interview-results.php"
           class="<?php echo $current_page === 'admin-interview-results.php' ? 'active' : ''; ?>"
           title="Interview Results">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
            </svg>
            <span class="nav-text">Interview Results</span>
        </a>
        <?php endif; ?>

        <a href="admin-final-decision.php"
           class="<?php echo $current_page === 'admin-final-decision.php' ? 'active' : ''; ?>"
           title="Final Decision">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/>
            </svg>
            <span class="nav-text">Final Decision</span>
        </a>

        <!-- SETTINGS (Super Admin only) -->
        <?php if ($can_manage_users): ?>
        <p class="nav-label">Settings</p>
        <a href="admin-user-management.php"
           class="<?php echo $current_page === 'admin-user-management.php' ? 'active' : ''; ?>"
           title="User Management">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
            </svg>
            <span class="nav-text">User Management</span>
        </a>
        <a href="admin-manage-programs.php"
           class="<?php echo $current_page === 'admin-manage-programs.php' ? 'active' : ''; ?>"
           title="Manage Programs">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/>
            </svg>
            <span class="nav-text">Manage Programs</span>
        </a>
        <a href="admin-system-settings.php"
           class="<?php echo $current_page === 'admin-system-settings.php' ? 'active' : ''; ?>"
           title="System Settings">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M19.14 12.94c.04-.31.06-.63.06-.94 0-.31-.02-.63-.06-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.04.31-.06.63-.06.94s.02.63.06.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>
            </svg>
            <span class="nav-text">System Settings</span>
        </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="admin-info">
            Logged in as<br>
            <strong><?php echo htmlspecialchars($_SESSION['admin_name']); ?></strong>
            <?php echo htmlspecialchars($_SESSION['admin_email']); ?>
        </div>
        <button type="button" class="btn-logout" onclick="showAdminLogoutModal()">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                <path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5-5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>
            </svg>
            <span class="logout-text">Logout</span>
        </button>
    </div>
    </aside>

<!-- Admin Logout Modal -->
<div class="logout-modal-overlay" id="adminLogoutModal" role="dialog" aria-modal="true"
    style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
            background:rgba(0,0,0,0.6);backdrop-filter:blur(3px);z-index:1100;
            align-items:center;justify-content:center;opacity:0;transition:opacity 0.2s ease;">
    <div style="background:white;border-radius:20px;max-width:420px;width:90%;padding:1.8rem;
                box-shadow:0 20px 35px -10px rgba(0,0,0,0.2);position:relative;
                animation:adminModalPop 0.2s ease forwards;">
        <button type="button" onclick="hideAdminLogoutModal()"
                style="background:transparent;border:none;font-size:1.6rem;line-height:1;
                       cursor:pointer;color:#9ca3af;position:absolute;top:1rem;right:1rem;">×</button>
        <h3 style="color:#004d00;margin-bottom:0.75rem;font-size:1.5rem;">⚠️ Confirm Logout</h3>
        <p style="color:#666;margin-bottom:1.8rem;line-height:1.5;">
            Are you sure you want to logout? You'll need to login again to access the admin panel.
        </p>
        <div style="display:flex;gap:1rem;justify-content:flex-end;">
            <button type="button" onclick="hideAdminLogoutModal()"
                    style="padding:0.7rem 1.5rem;border-radius:40px;font-weight:600;
                           cursor:pointer;border:none;background:#f1f3f4;color:#1f2937;">
                Cancel
            </button>
            <form action="../auth/admin-logout.php" method="POST" id="adminLogoutForm">
                <button type="submit" id="adminLogoutConfirmBtn"
                        style="padding:0.7rem 1.5rem;border-radius:40px;font-weight:600;
                               cursor:pointer;border:none;background:#dc3545;color:white;">
                    Logout
                </button>
            </form>
        </div>
    </div>
</div>

<style>
@keyframes adminModalPop {
    from { transform:scale(0.96); opacity:0; }
    to   { transform:scale(1); opacity:1; }
}
</style>

<script>
(function() {
    const KEY = 'admin_sidebar_collapsed';
    const body = document.body;
    function applyFromStorage() {
        try {
            const collapsed = localStorage.getItem(KEY) === '1';
            body.classList.toggle('sidebar-collapsed', collapsed);
        } catch (e) {}
    }
    function toggleCollapsed() {
        const nowCollapsed = !body.classList.contains('sidebar-collapsed');
        body.classList.toggle('sidebar-collapsed', nowCollapsed);
        try { localStorage.setItem(KEY, nowCollapsed ? '1' : '0'); } catch (e) {}
    }
    applyFromStorage();
    const btn = document.getElementById('sidebarToggle');
    if (btn) btn.addEventListener('click', toggleCollapsed);

    window.showAdminLogoutModal = function() {
        const modal = document.getElementById('adminLogoutModal');
        if (!modal) return;
        modal.style.display = 'flex';
        modal.style.opacity = '1';
        document.body.style.overflow = 'hidden';
    };

    window.hideAdminLogoutModal = function() {
        const modal = document.getElementById('adminLogoutModal');
        if (!modal) return;
        modal.style.display = 'none';
        document.body.style.overflow = '';
    };

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') window.hideAdminLogoutModal();
    });

    document.getElementById('adminLogoutModal')?.addEventListener('click', function(e) {
        if (e.target === this) window.hideAdminLogoutModal();
    });

    const adminLogoutForm = document.getElementById('adminLogoutForm');
    if (adminLogoutForm) {
        let submitting = false;
        adminLogoutForm.addEventListener('submit', function(e) {
            if (submitting) { e.preventDefault(); return; }
            submitting = true;
            const btn = document.getElementById('adminLogoutConfirmBtn');
            if (btn) { btn.disabled = true; btn.textContent = 'Logging out...'; }
        });
    }
})();
</script>