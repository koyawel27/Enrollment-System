<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin Applications - Full applications list with filters, search
 * admin-applications.php
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/admin-auth-check.php';
require_once CONFIG_PATH . '/admin-permissions.php';
require_admin_role([ADMIN_ROLE_SUPER_ADMIN, ADMIN_ROLE_ADMISSION_OFFICER, ADMIN_ROLE_REGISTRAR]);

// ── FETCH STATS (for work queue chips) ─────────────────────
function get_count($conn, $where = '') {
    $sql = 'SELECT COUNT(*) as count FROM applications';
    if ($where) $sql .= ' WHERE ' . $where;
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['count'] ?? 0;
}

$stats = [
    'total'               => get_count($conn),
    'submitted'           => get_count($conn, "status = 'Application Submitted'"),
    'under_review'        => get_count($conn, "status = 'Documents Under Review'"),
    'resubmitted'         => get_count($conn, "status = 'Documents Re-submitted'"),
    'interview_completed' => get_count($conn, "status = 'Interview Completed'"),
];

// ── ALLOWED STATUS FILTERS — must match current ENUM ──────
$allowed_filters = [
    'all',
    'Application Submitted',
    'Documents Under Review',
    'Documents Verified',
    'Documents Rejected',
    'Documents Re-submitted',
    'Exam Scheduled',
    'Exam Completed',
    'Exam Failed',
    'Exam No Show',
    'Interview Scheduled',
    'Interview Completed',
    'Interview No Show',
    'Awaiting Applicant Decision',
    'Admitted/Enrolled',
    'Rejected',
    'Application Withdrawn',
];

$filter = trim($_GET['status'] ?? 'all');
if (!in_array($filter, $allowed_filters)) $filter = 'all';

$q = trim($_GET['q'] ?? '');
$track_filter = trim($_GET['track'] ?? 'all');
if (!in_array($track_filter, ['all', 'CHED', 'TESDA'], true)) $track_filter = 'all';

$sql = "SELECT a.id, a.reference_number, a.first_name, a.last_name,
               a.first_choice, a.status, a.program_category,
               a.submitted_at, a.updated_at, u.email
        FROM applications a
        JOIN users u ON a.user_id = u.id
        WHERE a.submitted = 1 AND a.status != 'Draft'";

if ($filter !== 'all') {
    $filter_escaped = mysqli_real_escape_string($conn, $filter);
    $sql .= " AND a.status = '$filter_escaped'";
}

if ($track_filter !== 'all') {
    $track_escaped = mysqli_real_escape_string($conn, $track_filter);
    $sql .= " AND a.program_category = '$track_escaped'";
}

if ($q !== '') {
    $q_escaped = mysqli_real_escape_string($conn, $q);
    $like = '%' . $q_escaped . '%';
    $sql .= " AND (
                a.reference_number LIKE '$like'
                OR a.first_name    LIKE '$like'
                OR a.last_name     LIKE '$like'
                OR u.email         LIKE '$like'
             )";
}

$sql .= " ORDER BY
            CASE a.status
                WHEN 'Documents Re-submitted' THEN 1
                WHEN 'Application Submitted'  THEN 2
                WHEN 'Documents Under Review' THEN 3
                ELSE 4
            END ASC,
            a.submitted_at DESC";

$applications_result = mysqli_query($conn, $sql);
$applications = [];
while ($row = mysqli_fetch_assoc($applications_result)) $applications[] = $row;

$program_labels = [
    'BSIS'   => 'BS Information Systems',
    'ACT'    => 'Associate in Computer Technology',
    'BSOM'   => 'BS Office Management',
    'BSAIS'  => 'BS Accounting Information System',
    'BSCA'   => 'BS Customs Administration',
    'BTVTED' => 'Bachelor in Tech-Voc Teacher Education',
    'DHRMT'  => 'Diploma in Hotel & Restaurant Mgmt Tech',
    'HRS'    => 'Hotel and Restaurant Services',
    'CCS'    => 'Contact Center Services NCII',
    'BK'     => 'Bookkeeping NCIII',
    'EIM'    => 'Electrical Installation & Maintenance NCII',
    'SMAW'   => 'Shield Metal Arc Welding NCI/NCII',
];

$status_colors = [
    'Draft'                       => '#6c757d',
    'Application Submitted'       => '#0d6efd',
    'Documents Under Review'      => '#fd7e14',
    'Documents Verified'          => '#198754',
    'Documents Rejected'          => '#dc3545',
    'Documents Re-submitted'      => '#6f42c1',
    'Exam Scheduled'              => '#1565c0',
    'Exam Completed'              => '#198754',
    'Exam Failed'                 => '#dc3545',
    'Exam No Show'                => '#6c757d',
    'Interview Scheduled'         => '#6f42c1',
    'Interview Completed'         => '#0b7285',
    'Interview No Show'           => '#6c757d',
    'Awaiting Applicant Decision' => '#f9a825',
    'Admitted/Enrolled'           => '#006400',
    'Rejected'                    => '#dc3545',
    'Application Withdrawn'       => '#6c757d',
];

$success = $error = '';
if (isset($_SESSION['admin_success'])) { $success = $_SESSION['admin_success']; unset($_SESSION['admin_success']); }
if (isset($_SESSION['admin_error']))   { $error   = $_SESSION['admin_error'];   unset($_SESSION['admin_error']);   }

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/admin-styles.css">
    <title>All Applications - BPC iEnroll Admin</title>
    <style>
        .page-header { margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; }
        thead th { padding:0.5rem 0.6rem; font-size:0.72rem; }
        td { padding:0.5rem 0.6rem; }
        .main { padding:1.25rem 1.5rem; }
        @media(max-width:1280px) {
            .timeline-cell { display:none; }
            thead th:nth-child(6) { display:none; } /* timeline column */
        }

        /* Smaller action buttons */
        .btn-review, .btn-view {
            display:inline-block;
            padding:0.2rem 0.6rem;
            font-size:0.7rem;
            border-radius:6px;
            text-decoration:none;
            font-weight:600;
            transition:0.15s ease;
            line-height:1.4;
        }
        .btn-review {
            background:var(--bpc-green);
            color: white;
            border:1px solid var(--bpc-green);
        }
        .btn-review:hover {
            background:#005000;
        }
        .btn-view {
            background:#6c757d;
            color: white;
            border:1px solid #cbd5e1;
        }
        .btn-view:hover {
            background:#e0e7ff;
        }

        /* Improved filter bar */
        .filter-bar {
            background:#f9fafb;
            border-radius:12px;
            padding:1rem;
            margin-bottom:1.5rem;
            border:1px solid #e5e7eb;
        }
        .filter-row {
            display:flex;
            flex-wrap:wrap;
            gap:0.75rem;
            align-items:flex-end;
            margin-top:0.75rem;
        }
        .filter-group {
            flex: 0 1 auto;
            min-width:140px;
        }
        .filter-group label {
            display:block;
            font-size:0.7rem;
            font-weight:700;
            text-transform:uppercase;
            letter-spacing:0.05em;
            color:#4b5563;
            margin-bottom:0.2rem;
        }
        .filter-group select, .filter-group input {
            width:100%;
            padding:0.45rem 0.6rem;
            border-radius:8px;
            border:1px solid #d1d5db;
            font-size:0.85rem;
            background:#fff;
        }
        .btn-clear {
            background:#fff;
            border:1px solid #d1d5db;
            padding:0.45rem 1rem;
            border-radius:8px;
            font-size:0.8rem;
            text-decoration:none;
            color:#374151;
            display:inline-block;
        }
        .btn-clear:hover {
            background:#f3f4f6;
        }
        .btn-apply {
            background: none;
            color: black;
            border: 1px solid black;
            padding:0.45rem 1.2rem;
            border-radius:8px;
            font-size:0.8rem;
            font-weight:600;
            cursor:pointer;
        }
        .btn-apply:hover {
            background: grey;
            color: white;
        }
    </style>
</head>
<body>

<?php include '../shared/admin-sidebar.php'; ?>

<main class="main">
    <div class="page-header">
        <div>
            <h1>All Applications</h1>
            <p>Search, filter, and manage all submitted applications.</p>
        </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <!-- IMPROVED FILTER SECTION (no duplicate status dropdown) -->
    <div class="filter-bar">
        <div class="queue-title" style="margin-bottom:0.5rem;">Quick filters</div>
        <div class="queue-chips">
            <?php
            $track_param = $track_filter !== 'all' ? '&track=' . urlencode($track_filter) : '';
            $q_param     = $q !== '' ? '&q=' . urlencode($q) : '';
            $chips = [
                ['label' => 'All Submitted',      'status' => 'all',                    'count' => $stats['total']],
                ['label' => 'Re-submitted',       'status' => 'Documents Re-submitted', 'count' => $stats['resubmitted']],
                ['label' => 'New',                'status' => 'Application Submitted',  'count' => $stats['submitted']],
                ['label' => 'Under Review',       'status' => 'Documents Under Review', 'count' => $stats['under_review']],
                ['label' => 'Interview Completed','status' => 'Interview Completed',    'count' => $stats['interview_completed']],
            ];
            foreach ($chips as $c) {
                $is_active = ($filter === $c['status']);
                $href = 'admin-applications.php?status=' . ($c['status'] === 'all' ? 'all' : urlencode($c['status'])) . $track_param . $q_param;
                echo '<a class="chip' . ($is_active ? ' active' : '') . '" href="' . htmlspecialchars($href) . '">'
                   . htmlspecialchars($c['label']) . ' <span class="chip-count">(' . (int)$c['count'] . ')</span></a>';
            }
            ?>
            <a class="chip" href="admin-final-decision.php" title="Go to final decision page">
                Final Decision <span class="chip-count">(<?php echo (int)$stats['interview_completed']; ?>)</span>
            </a>
        </div>

        <!-- Single filter row: track + search (live) + clear -->
        <form method="GET" action="admin-applications.php" class="filter-row" id="applicationsFilterForm">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter); ?>">
            <div class="filter-group">
                <label for="track-filter">Track</label>
                <select id="track-filter" name="track">
                    <option value="all"  <?php echo $track_filter === 'all'   ? 'selected' : ''; ?>>All Tracks</option>
                    <option value="CHED" <?php echo $track_filter === 'CHED'  ? 'selected' : ''; ?>>CHED</option>
                    <option value="TESDA"<?php echo $track_filter === 'TESDA' ? 'selected' : ''; ?>>TESDA</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="q">Search</label>
                <input id="q" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Name, email, or reference #">
            </div>
            <?php if ($q !== '' || $track_filter !== 'all' || $filter !== 'all'): ?>
                <div>
                    <label>&nbsp;</label>
                    <a class="btn-clear" href="admin-applications.php?status=all">Clear all</a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- APPLICATIONS TABLE (bulk toolbar and checkboxes removed) -->
    <div class="table-card">
        <div class="table-header">
            <h2>
                Applications
                <?php if ($filter !== 'all'): ?>
                    <span style="font-weight:400;color:var(--text-gray);font-size:0.9rem;">— <?php echo htmlspecialchars($filter); ?></span>
                <?php endif; ?>
            </h2>
            <div class="table-actions">
                <a class="btn-export" id="exportCsvBtn" href="admin-export-applications.php?status=<?php echo urlencode($filter); ?>&track=<?php echo urlencode($track_filter); ?>&q=<?php echo urlencode($q); ?>">
                    Export CSV
                </a>
            </div>
        </div>

        <div class="table-wrapper">
            <?php if (empty($applications)): ?>
                <div class="no-data"><p>No applications found.</p></div>
            <?php else: ?>
                <table id="applicationsTable">
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>Program</th>
                            <th>Track</th>
                            <th>Reference #</th>
                            <th>Status</th>
                            <th class="timeline-cell">Timeline</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $needs_review = ['Application Submitted', 'Documents Under Review', 'Documents Re-submitted'];
                        foreach ($applications as $app):
                            $is_priority    = $app['status'] === 'Documents Re-submitted';
                            $color          = $status_colors[$app['status']] ?? '#6c757d';
                            $program        = $program_labels[$app['first_choice']] ?? $app['first_choice'];
                            $submitted_date = $app['submitted_at'] ? date('M d, Y', strtotime($app['submitted_at'])) : '—';
                            $updated_date   = $app['updated_at']   ? date('M d',    strtotime($app['updated_at']))   : '—';
                            $timeline_text  = $submitted_date . ($updated_date !== '—' ? ' • Updated ' . $updated_date : '');
                            $track          = $app['program_category'] ?? '—';
                            $show_review    = in_array($app['status'], $needs_review);
                            $search_blob    = strtolower(
                                ($app['last_name'] ?? '') . ' ' . ($app['first_name'] ?? '') . ' ' .
                                ($app['email'] ?? '') . ' ' . ($app['reference_number'] ?? '')
                            );
                        ?>
                        <tr class="application-row <?php echo $is_priority ? 'priority' : ''; ?>"
                            data-search="<?php echo htmlspecialchars($search_blob); ?>"
                            data-track="<?php echo htmlspecialchars((string)($track ?? '')); ?>">
                            <td onclick="window.location='admin-application-detail.php?id=<?php echo $app['id']; ?>'" style="cursor:pointer;">
                                <div class="applicant-name">
                                    <?php echo htmlspecialchars($app['last_name'].', '.$app['first_name']); ?>
                                    <?php if ($is_priority): ?><span class="priority-flag">Re-submitted</span><?php endif; ?>
                                </div>
                                <div class="applicant-email"><?php echo htmlspecialchars($app['email']); ?></div>
                            </td>
                            <td onclick="window.location='admin-application-detail.php?id=<?php echo $app['id']; ?>'" style="cursor:pointer;">
                                <?php echo htmlspecialchars($program); ?>
                            </td>
                            <td onclick="window.location='admin-application-detail.php?id=<?php echo $app['id']; ?>'" style="cursor:pointer;">
                                <?php if ($track === 'CHED'):  ?>
                                    <span class="track-badge track-ched">CHED</span>
                                <?php elseif ($track === 'TESDA'): ?>
                                    <span class="track-badge track-tesda">TESDA</span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td onclick="window.location='admin-application-detail.php?id=<?php echo $app['id']; ?>'" style="cursor:pointer;">
                                <span class="ref-number"><?php echo $app['reference_number'] ? htmlspecialchars($app['reference_number']) : '—'; ?></span>
                            </td>
                            <td onclick="window.location='admin-application-detail.php?id=<?php echo $app['id']; ?>'" style="cursor:pointer;">
                                <span class="status-badge" style="background:<?php echo $color; ?>;">
                                    <?php echo htmlspecialchars($app['status']); ?>
                                </span>
                            </td>
                            <td onclick="window.location='admin-application-detail.php?id=<?php echo $app['id']; ?>'" style="cursor:pointer;" class="timeline-cell">
                                <?php echo htmlspecialchars($timeline_text); ?>
                            </td>
                            <td onclick="event.stopPropagation();">
                                <?php if ($show_review): ?>
                                    <a href="admin-review-documents.php?id=<?php echo $app['id']; ?>" class="btn-review">Review</a>
                                <?php else: ?>
                                    <a href="admin-application-detail.php?id=<?php echo $app['id']; ?>" class="btn-view">View</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
<script>
function applyApplicationsFilters() {
    const q = (document.getElementById('q')?.value || '').trim().toLowerCase();
    const track = (document.getElementById('track-filter')?.value || 'all').trim().toLowerCase();
    const rows = document.querySelectorAll('#applicationsTable .application-row');
    rows.forEach(row => {
        const s = (row.getAttribute('data-search') || '').toLowerCase();
        const t = (row.getAttribute('data-track') || '').toLowerCase();
        const trackOk = (track === 'all') || (t === track);
        const searchOk = !q || s.includes(q);
        row.style.display = (trackOk && searchOk) ? '' : 'none';
    });

    // Keep Export CSV in sync with current live filters.
    const status = (document.querySelector('#applicationsFilterForm input[name="status"]')?.value || 'all').trim();
    const exportBtn = document.getElementById('exportCsvBtn');
    if (exportBtn) {
        const params = new URLSearchParams();
        params.set('status', status);
        params.set('track', track || 'all');
        // Use raw input (not lowercased) for export query
        const rawQ = (document.getElementById('q')?.value || '').trim();
        params.set('q', rawQ);
        exportBtn.href = 'admin-export-applications.php?' + params.toString();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Prevent form submit; filtering is live on the client.
    document.getElementById('applicationsFilterForm')?.addEventListener('submit', (e) => e.preventDefault());

    // Live filter on type/change
    document.getElementById('q')?.addEventListener('input', applyApplicationsFilters);
    document.getElementById('track-filter')?.addEventListener('change', applyApplicationsFilters);

    applyApplicationsFilters();
});
</script>
</body>
</html>