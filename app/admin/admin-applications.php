<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin Applications - Full applications list with filters, search, bulk actions
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
            a.submitted_at ASC";

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
            thead th:nth-child(7) { display:none; }
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
        <a href="admin-dashboard.php" class="btn-clear">← Back to Dashboard</a>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <!-- WORK QUEUE CHIPS + SEARCH -->
    <div class="queue-card">
        <div class="queue-title">Quick filters</div>
        <div class="queue-chips">
            <?php
            $track_param = $track_filter !== 'all' ? '&track=' . urlencode($track_filter) : '';
            $q_param     = $q !== '' ? '&q=' . urlencode($q) : '';
            $chips = [
                ['label' => 'All Submitted',      'status' => 'all',                    'count' => $stats['total']],
                ['label' => 'Re-submitted',        'status' => 'Documents Re-submitted', 'count' => $stats['resubmitted']],
                ['label' => 'New',                 'status' => 'Application Submitted',  'count' => $stats['submitted']],
                ['label' => 'Under Review',        'status' => 'Documents Under Review', 'count' => $stats['under_review']],
                ['label' => 'Interview Completed', 'status' => 'Interview Completed',    'count' => $stats['interview_completed']],
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
        <form class="filter-form" method="GET" action="admin-applications.php" style="margin-top:1rem;">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter); ?>">
            <div class="search-box">
                <label for="q">Search</label>
                <input id="q" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Name, email, or reference #">
            </div>
            <div>
                <label for="track-filter">Track:</label>
                <select id="track-filter" name="track" onchange="this.form.submit()">
                    <option value="all"  <?php echo $track_filter === 'all'   ? 'selected' : ''; ?>>All Tracks</option>
                    <option value="CHED" <?php echo $track_filter === 'CHED'  ? 'selected' : ''; ?>>CHED</option>
                    <option value="TESDA"<?php echo $track_filter === 'TESDA' ? 'selected' : ''; ?>>TESDA</option>
                </select>
            </div>
            <button type="submit" class="btn-compact primary">Apply</button>
            <?php if ($q !== '' || $track_filter !== 'all' || $filter !== 'all'): ?>
                <a class="btn-clear" href="admin-applications.php?status=all">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- APPLICATIONS TABLE -->
    <div class="table-card">
        <div class="table-header">
            <h2>
                Applications
                <?php if ($filter !== 'all'): ?>
                    <span style="font-weight:400;color:var(--text-gray);font-size:0.9rem;">— <?php echo htmlspecialchars($filter); ?></span>
                <?php endif; ?>
            </h2>
            <div class="table-actions">
                <form class="filter-form" method="GET" action="admin-applications.php">
                    <input type="hidden" name="q"     value="<?php echo htmlspecialchars($q); ?>">
                    <input type="hidden" name="track" value="<?php echo htmlspecialchars($track_filter); ?>">
                    <label for="status-filter">Status:</label>
                    <select id="status-filter" name="status" onchange="this.form.submit()">
                        <option value="all"                         <?php echo $filter==='all'                         ?'selected':'';?>>All Submitted</option>
                        <option value="Application Submitted"       <?php echo $filter==='Application Submitted'       ?'selected':'';?>>Newly Submitted</option>
                        <option value="Documents Under Review"      <?php echo $filter==='Documents Under Review'      ?'selected':'';?>>Under Review</option>
                        <option value="Documents Re-submitted"      <?php echo $filter==='Documents Re-submitted'      ?'selected':'';?>>Re-submitted</option>
                        <option value="Documents Verified"          <?php echo $filter==='Documents Verified'          ?'selected':'';?>>Verified</option>
                        <option value="Documents Rejected"          <?php echo $filter==='Documents Rejected'          ?'selected':'';?>>Rejected</option>
                        <option value="Exam Scheduled"              <?php echo $filter==='Exam Scheduled'              ?'selected':'';?>>Exam Scheduled</option>
                        <option value="Exam Completed"              <?php echo $filter==='Exam Completed'              ?'selected':'';?>>Exam Passed</option>
                        <option value="Exam Failed"                 <?php echo $filter==='Exam Failed'                 ?'selected':'';?>>Exam Failed</option>
                        <option value="Exam No Show"                <?php echo $filter==='Exam No Show'                ?'selected':'';?>>Exam No Show</option>
                        <option value="Interview Scheduled"         <?php echo $filter==='Interview Scheduled'         ?'selected':'';?>>Interview Scheduled</option>
                        <option value="Interview Completed"         <?php echo $filter==='Interview Completed'         ?'selected':'';?>>Interview Completed</option>
                        <option value="Interview No Show"           <?php echo $filter==='Interview No Show'           ?'selected':'';?>>Interview No Show</option>
                        <option value="Awaiting Applicant Decision" <?php echo $filter==='Awaiting Applicant Decision' ?'selected':'';?>>Awaiting Decision</option>
                        <option value="Admitted/Enrolled"           <?php echo $filter==='Admitted/Enrolled'           ?'selected':'';?>>Admitted</option>
                        <option value="Rejected"                    <?php echo $filter==='Rejected'                    ?'selected':'';?>>Rejected</option>
                        <option value="Application Withdrawn"       <?php echo $filter==='Application Withdrawn'       ?'selected':'';?>>Withdrawn</option>
                    </select>
                </form>
                <a class="btn-export" href="admin-export-applications.php?status=<?php echo urlencode($filter); ?>&track=<?php echo urlencode($track_filter); ?>&q=<?php echo urlencode($q); ?>">
                    Export CSV
                </a>
            </div>
        </div>

        <!-- BULK TOOLBAR: notify only — bulk status update removed -->
        <div class="table-toolbar" id="bulk-toolbar" style="display:none;">
            <div class="table-toolbar-count"><span id="bulk-count">0</span> selected</div>
            <div class="table-toolbar-actions">
                <button type="button" class="btn-compact" onclick="openBulkNotifyModal()">Send notification</button>
            </div>
        </div>

        <div class="table-wrapper">
            <?php if (empty($applications)): ?>
                <div class="no-data"><p>No applications found.</p></div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all"></th>
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
                        ?>
                        <tr class="<?php echo $is_priority ? 'priority' : ''; ?>">
                            <td onclick="event.stopPropagation();">
                                <input type="checkbox" class="row-check" value="<?php echo (int)$app['id']; ?>">
                            </td>
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

<!-- BULK NOTIFY MODAL — bulk status update intentionally removed -->
<div id="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.45);z-index:40;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;max-width:480px;width:90%;padding:1.5rem;box-shadow:0 20px 45px rgba(15,23,42,0.35);">
        <h2 style="font-size:1.1rem;margin-bottom:0.75rem;color:#111827;">Send Notification</h2>
        <form id="bulk-form" method="POST" action="admin-bulk-notify.php">
            <input type="hidden" name="ids" id="bulk-ids">
            <div style="margin-bottom:1rem;">
                <label for="bulk-subject" style="display:block;font-size:0.8rem;font-weight:600;margin-bottom:0.25rem;color:#4b5563;">Subject</label>
                <input id="bulk-subject" name="subject" style="width:100%;padding:0.45rem 0.6rem;border-radius:6px;border:1px solid #d1d5db;font-size:0.85rem;margin-bottom:0.6rem;">
                <label for="bulk-template" style="display:block;font-size:0.8rem;font-weight:600;margin-bottom:0.25rem;color:#4b5563;">Quick reason</label>
                <select id="bulk-template" onchange="applyTemplate()" style="width:100%;padding:0.45rem 0.6rem;border-radius:6px;border:1px solid #d1d5db;font-size:0.85rem;margin-bottom:0.6rem;">
                    <option value="">Select a quick reason (optional)</option>
                    <option value="Blurry ID">Blurry ID</option>
                    <option value="Missing document">Missing document</option>
                    <option value="Incorrect information">Incorrect information</option>
                </select>
                <label for="bulk-message" style="display:block;font-size:0.8rem;font-weight:600;margin-bottom:0.25rem;color:#4b5563;">Message</label>
                <textarea id="bulk-message" name="message" rows="4" style="width:100%;padding:0.45rem 0.6rem;border-radius:6px;border:1px solid #d1d5db;font-size:0.85rem;resize:vertical;"></textarea>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:0.5rem;margin-top:0.75rem;">
                <button type="button" class="btn-compact" onclick="closeBulkModal()">Cancel</button>
                <button type="submit" class="btn-compact primary">Send</button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const selectAll = document.getElementById('select-all');
    const rowChecks = document.querySelectorAll('.row-check');
    const toolbar   = document.getElementById('bulk-toolbar');
    const countSpan = document.getElementById('bulk-count');
    const overlay   = document.getElementById('modal-overlay');
    const bulkIds   = document.getElementById('bulk-ids');

    function getSelectedIds() {
        const ids = [];
        document.querySelectorAll('.row-check:checked').forEach(chk => ids.push(chk.value));
        return ids;
    }

    function refreshToolbar() {
        const ids   = getSelectedIds();
        const count = ids.length;
        if (countSpan) countSpan.textContent = count;
        if (toolbar)   toolbar.style.display = count > 0 ? 'flex' : 'none';
        if (selectAll) {
            const total         = document.querySelectorAll('.row-check').length;
            selectAll.checked       = count > 0 && count === total;
            selectAll.indeterminate = count > 0 && count < total;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.row-check').forEach(chk => { chk.checked = this.checked; });
            refreshToolbar();
        });
    }
    rowChecks.forEach(chk => chk.addEventListener('change', refreshToolbar));

    window.openBulkNotifyModal = function() {
        const ids = getSelectedIds();
        if (!ids.length) return;
        bulkIds.value = ids.join(',');
        overlay.style.display = 'flex';
    };

    window.closeBulkModal = function() {
        overlay.style.display = 'none';
    };

    window.applyTemplate = function() {
        const select  = document.getElementById('bulk-template');
        const message = document.getElementById('bulk-message');
        if (!select || !message || !select.value) return;
        message.value = (message.value ? message.value + '\n\n' : '') + 'Reason: ' + select.value + '.';
    };

    overlay?.addEventListener('click', function(e) {
        if (e.target === this) closeBulkModal();
    });
})();
</script>

</body>
</html>