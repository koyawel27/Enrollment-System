<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Student - My Documents
 * documents.php
 */

session_start();
require_once CONFIG_PATH . '/db.php';

if (!isset($_SESSION['user_id'])) {
    redirect('index.php');
}

$user_id    = (int)$_SESSION['user_id'];
$user_email = $_SESSION['user_email'];

$query = "SELECT * FROM applications WHERE user_id = ? LIMIT 1";
$stmt  = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$app = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

mysqli_close($conn);

function doc_status($app, string $field): string {
    // Check if $app is an array and the specific field is not empty
    if (!is_array($app) || empty($app[$field])) {
        return 'Not Uploaded';
    }
    
    $status = $app['status'] ?? 'Draft';
    
    if ($status === 'Documents Rejected') {
        return 'Uploaded (Pending Re-upload)';
    }
    
    $verified_statuses = [
        'Documents Verified','Exam Scheduled','Exam Completed','Exam Failed',
        'No Show','Interview Scheduled','Interview Completed',
        'Admitted/Enrolled','Enrolled','Rejected'
    ];

    if (in_array($status, $verified_statuses, true)) {
        return 'Verified';
    }
    
    return 'Uploaded';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>My Documents - BPC iEnroll</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        :root {
            --bpc-green:#006400; --bpc-green-dark:#004d00;
            --text-dark:#1a1a1a; --text-gray:#666;
            --bg-light:#f8f9fa; --border-color:#e0e0e0; --white:#ffffff;
        }
        body { font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:var(--bg-light); color:var(--text-dark); }
        .dashboard-container { display:flex; min-height:100vh; }

        /* ---------- SIDEBAR (DESKTOP) ---------- */
        .sidebar {
            width: 260px;
            background-color: #003300;
            color: #fff;
            padding: 1.25rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .sidebar::-webkit-scrollbar { display: none; }
        .sidebar-header {
            padding: 0 1.5rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }
        .sidebar-logo img {
            width: 56px;
            height: 56px;
            object-fit: contain;
        }
        .sidebar-logo h2 { font-size: 1rem; }
        .sidebar-user { font-size: 0.85rem; color: rgba(255,255,255,0.6); word-break: break-all; }
        .sidebar-menu { margin-top: 1rem; list-style: none; }
        .sidebar-menu li { margin-bottom: 0.1rem; }
        .sidebar-menu a {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.65rem 1.5rem;
            font-size: 0.875rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background-color: rgba(255,255,255,0.1);
            color: white;
        }
        .sidebar-menu svg { width: 20px; height: 20px; }
        .sidebar-section-title {
            padding: 0.5rem 1.5rem 0.25rem;
            font-size: 0.7rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.4);
        }
        .sidebar-logout {
            position: absolute;
            bottom: 1rem;
            width: 100%;
            padding: 0 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 1rem;
        }
        .btn-logout {
            width: 100%;
            padding: 0.625rem;
            font-size: 0.875rem;
            background-color: #dc3545;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-logout:hover { background-color: #c82333; }

        /* ---------- LOGOUT MODAL ---------- */
        .logout-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            backdrop-filter: blur(3px);
            z-index: 1100;
            align-items: center;
            justify-content: center;
        }
        .logout-modal-overlay.active { display: flex; }
        .logout-modal {
            background-color: white;
            border-radius: 20px;
            padding: 2rem;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 20px 35px -10px rgba(0,0,0,0.2);
            animation: modalPop 0.2s ease;
        }
        @keyframes modalPop {
            from { transform: scale(0.96); opacity: 0; }
            to   { transform: scale(1); opacity: 1; }
        }
        .logout-modal h3 {
            color: #004d00;
            margin-bottom: 1rem;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .logout-modal p {
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        .logout-modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        .btn-cancel {
            padding: 0.75rem 1.5rem;
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-cancel:hover { background-color: #5a6268; }
        .btn-confirm-logout {
            padding: 0.75rem 1.5rem;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-confirm-logout:hover { background-color: #b91c2c; }

        /* ---------- MAIN CONTENT ---------- */
        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 2rem;
        }
        .page-header {
            margin-bottom: 1.5rem;
        }
        .page-header h1 {
            font-size: 1.8rem;
            color: var(--bpc-green-dark);
            margin-bottom: 0.35rem;
        }
        .page-header p {
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        /* ---------- CARD & TABLE ---------- */
        .card {
            background: var(--white);
            border-radius: 12px;
            padding: 1.75rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 1.5rem;
        }
        .card-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--bpc-green-dark);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--bg-light);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        th, td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f0f0f0;
            text-align: left;
        }
        th {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-gray);
            background: #f5f5f5;
        }
        .status-pill {
            display: inline-block;
            padding: 0.25rem 0.7rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .status-uploaded { background:#e3f2fd; color:#1565c0; }
        .status-verified { background:#d4edda; color:#155724; }
        .status-missing  { background:#f8d7da; color:#721c24; }
        .status-pending  { background:#fff3cd; color:#856404; }
        .doc-link {
            color: var(--bpc-green);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .doc-link:hover { text-decoration: underline; }

        /* ---------- MOBILE ELEMENTS (hidden on desktop) ---------- */
        .mobile-header { display: none; }
        .sidebar-overlay { display: none; }

        /* ========== RESPONSIVE (max-width: 768px) ========== */
        /* ========== RESPONSIVE (max-width: 768px) ========== */
@media (max-width: 768px) {
    /* Mobile header (same as before) */
    .mobile-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 1.5rem;
        background-color: var(--bpc-green-dark);
        color: white;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        z-index: 999;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }
    .mobile-logo {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: 600;
        font-size: 1rem;
    }
    .mobile-logo img { width: 36px; height: 36px; object-fit: contain; }
    .hamburger-btn {
        background: transparent;
        border: none;
        color: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.25rem;
    }
    .hamburger-btn svg { width: 28px; height: 28px; fill: white; }

    /* Off-canvas sidebar */
    .sidebar {
        position: fixed;
        top: 0;
        left: -300px;
        width: 260px;
        height: 100vh;
        z-index: 1001;
        transition: left 0.3s ease;
        background-color: #003300;
    }
    .sidebar.active { left: 0; }
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100vh;
        background-color: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(2px);
        z-index: 1000;
    }
    .sidebar-overlay.active { display: block; }

    /* Main content */
    .main-content {
        margin-left: 0;
        padding: 85px 1rem 1rem 1rem;
    }

    /* ---------- TABLE → CARDS (no horizontal scroll) ---------- */
    .table-wrapper {
        overflow-x: visible;  /* no horizontal scroll */
        margin: 0;
        padding: 0;
    }
    table, thead, tbody, tr, th, td {
        display: block;
    }
    thead {
        display: none;  /* hide table headers */
    }
    tr {
        border: 1px solid #ddd;
        border-radius: 8px;
        margin-bottom: 1rem;
        padding: 0.5rem;
        background: white;
    }
    td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.8rem 0.75rem;
        border-bottom: 1px solid #eee;
        white-space: normal;
    }
    td:last-child {
        border-bottom: none;
    }
    td::before {
        content: attr(data-label);
        font-weight: 600;
        color: var(--bpc-green);
        flex: 0 0 40%;
    }
    /* Style the content inside each cell */
    td > span, td > a, td > .td-val {
        flex: 1;
        text-align: right;
        word-break: break-word;
        padding-left: 10px;
    }
    .td-val {
        display: inline;
        text-align: inherit;
    }

    /* Move logout button higher on mobile */
    .sidebar-logout {
        position: relative;
        bottom: auto;
        margin-top: 2rem;
        margin-bottom: 1rem;
    }
}
    </style>
</head>
<body>
<div class="dashboard-container">

    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="mobile-logo">
            <img src="../../assets/cropped-cropped-cropped-cropped-cropped-bpclogo-1-1-1-150x150.png" alt="BPC Logo">
            <span>BPC iEnroll</span>
        </div>
        <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle Menu">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M3 4H21V6H3V4ZM3 11H21V13H3V11ZM3 18H21V20H3V18Z"/>
            </svg>
        </button>
    </div>

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <img src="../../assets/cropped-cropped-cropped-cropped-cropped-bpclogo-1-1-1-150x150.png" alt="BPC Logo">
                <h2>BPC iEnroll</h2>
            </div>
            <p class="sidebar-user"><?php echo htmlspecialchars($user_email); ?></p>
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard.php">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
                    Dashboard
                </a>
            </li>
            <li>
                <a href="application-form.php">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 18H8v-2h8v2zm0-4H8v-2h8v2zm-3-7V3.5L18.5 9H13z"/></svg>
                    My Application
                </a>
            </li>
            <li>
                <a href="profile.php">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    My Profile
                </a>
            </li>
            <li>
                <a href="documents.php" class="active">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm0 7V3.5L18.5 9H14z"/></svg>
                    My Documents
                </a>
            </li>
            <li>
                <a href="dashboard.php#updates">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M4 6h16v12H5.17L4 19.17V6zm2 2v8h12V8H6zm1 1h10v2H7V9zm0 3h7v2H7v-2z"/></svg>
                    Updates
                </a>
            </li>
            <li class="sidebar-section-title">Account</li>
            <li>
                <a href="../auth/change-password.php">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 17a2 2 0 0 0 2-2v-2a2 2 0 0 0-4 0v2a2 2 0 0 0 2 2zm6-7h-1V8a5 5 0 0 0-10 0v2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2zm-3 0H9V8a3 3 0 0 1 6 0v2z"/></svg>
                    Change Password
                </a>
            </li>
        </ul>
        <div class="sidebar-logout">
            <button type="button" class="btn-logout" onclick="showLogoutModal()">Logout</button>
        </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">
        <div class="page-header">
            <h1>My Documents</h1>
            <p style="font-size:0.9rem; color:var(--text-gray);">See the documents you have uploaded for your application.</p>
        </div>

        <div class="card">
            <div class="card-title">Uploaded Files</div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Document</th>
                            <th>Status</th>
                            <th>File</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rows = [
                            ['label' => '2x2 ID Photo',        'field' => 'id_photo_path'],
                            ['label' => 'Report Card / TOR',   'field' => 'grades_path'],
                            ['label' => 'PSA Birth Certificate','field' => 'birth_cert_path'],
                        ];
                        if ($app && ($app['applicant_type'] ?? '') === 'Transferee') {
                            $rows[] = ['label' => 'Transfer Credential', 'field' => 'transfer_cred_path'];
                            $rows[] = ['label' => 'Transcript of Records (TOR)', 'field' => 'tor_path'];
                        }

                        foreach ($rows as $row): 
                            $path = $app[$row['field']] ?? null;
                            $status_text = doc_status($app, $row['field']);
                            
                            // Status color logic
                            $status_class = 'status-missing';
                            if ($status_text === 'Uploaded') $status_class = 'status-uploaded';
                            if ($status_text === 'Verified') $status_class = 'status-verified';
                            if ($status_text === 'Uploaded (Pending Re-upload)') $status_class = 'status-pending';
                        ?>
                        <tr>
                            <td data-label="Document">
                                <div class="td-val"><?php echo htmlspecialchars($row['label']); ?></div>
                            </td>
                            <td data-label="Status">
                                <div class="td-val">
                                    <span class="status-pill <?php echo $status_class; ?>">
                                        <?php echo htmlspecialchars($status_text); ?>
                                    </span>
                                </div>
                            </td>
                            <td data-label="File">
                                <div class="td-val">
                                    <?php if ($path): ?>
                                        <a href="<?php echo htmlspecialchars(BASE_URL . '/uploads/' . basename((string)$path)); ?>" 
                                           target="_blank" class="doc-link">View File</a>
                                    <?php else: ?>
                                        <span style="color:#aaa;">No file</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                    </tbody>
                </table>
            </div>
        </div>

        <p style="font-size:0.85rem; color:var(--text-gray); margin-top:0.5rem; text-align: center;">
            To upload or replace documents, go to
            <a href="application-form.php?step=6" style="color:var(--bpc-green); text-decoration:none;"><strong>My Application &raquo; Step 6</strong></a>.
        </p>
    </main>
</div>

<!-- Logout Modal -->
<div class="logout-modal-overlay" id="logoutModal" onclick="if(event.target===this) hideLogoutModal()">
    <div class="logout-modal">
        <h3>⚠️ Confirm Logout</h3>
        <p>Are you sure you want to logout? You'll need to login again to access your dashboard.</p>
        <div class="logout-modal-actions">
            <button type="button" class="btn-cancel" onclick="hideLogoutModal()">Cancel</button>
            <form action="../auth/logout.php" method="POST" style="display:inline;">
                <button type="submit" class="btn-confirm-logout">Logout</button>
            </form>
        </div>
    </div>
</div>

<script>
// Sidebar toggle
document.addEventListener('DOMContentLoaded', function() {
    const hamburger = document.getElementById('hamburgerBtn');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    function toggleSidebar() {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
    }

    if (hamburger) hamburger.addEventListener('click', toggleSidebar);
    if (overlay) overlay.addEventListener('click', toggleSidebar);
});

// Logout modal
function showLogoutModal() {
    // Close mobile sidebar if open
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar && sidebar.classList.contains('active')) {
        sidebar.classList.remove('active');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
    const modal = document.getElementById('logoutModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function hideLogoutModal() {
    const modal = document.getElementById('logoutModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideLogoutModal();
    }
});
</script>
</body>
</html>