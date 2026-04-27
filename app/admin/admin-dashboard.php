<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin Dashboard
 * admin-dashboard.php
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/admin-auth-check.php';
require_once CONFIG_PATH . '/admin-permissions.php';
require_once CONFIG_PATH . '/programs.php';

require_admin_role([
    ADMIN_ROLE_SUPER_ADMIN,
    ADMIN_ROLE_ADMISSION_OFFICER,
    ADMIN_ROLE_REGISTRAR,
    ADMIN_ROLE_PROGRAM_HEAD,
]);

$is_program_head = ($_SESSION['admin_role'] ?? '') === ADMIN_ROLE_PROGRAM_HEAD;

// ── DEPARTMENT FILTER (program heads only) ─────────────────
[$head_filter, $head_params] = get_head_program_filter($conn);
[$filter_clause, $filter_params] = get_head_program_filter($conn);

// Fetch assigned program names for display (program heads only)
$head_program_labels = [];
if ($is_program_head) {
    $codes = get_head_program_codes($conn);
    $all_programs = get_all_programs($conn);
    foreach ($codes as $code) {
        $head_program_labels[] = $all_programs[$code]['name'] ?? $code;
    }
}


// ── STATS ──────────────────────────────────────────────────
function get_count($conn, $where = '', array $extra_params = []): int {
    // Alias `a` must match get_head_program_filter() (a.first_choice, etc.)
    $sql = 'SELECT COUNT(*) as count FROM applications a';
    if ($where) $sql .= ' WHERE ' . $where;
    if (!empty($extra_params)) {
        $stmt = mysqli_prepare($conn, $sql);
        $types = str_repeat('s', count($extra_params));
        mysqli_stmt_bind_param($stmt, $types, ...$extra_params);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return (int)($row['count'] ?? 0);
    }
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return (int)($row['count'] ?? 0);
}

// Build a WHERE fragment that appends the head filter
function w(string $base, string $head_filter): string {
    return $base . $head_filter;
}

$hf = $head_filter; // shorthand
$hp = $head_params;

$stats = [
    'total'               => get_count($conn, "submitted = 1 AND status != 'Draft'" . $hf, $hp),
    'submitted'           => get_count($conn, "status = 'Application Submitted'" . $hf, $hp),
    'under_review'        => get_count($conn, "status = 'Documents Under Review'" . $hf, $hp),
    'verified'            => get_count($conn, "status = 'Documents Verified'" . $hf, $hp),
    'rejected_docs'       => get_count($conn, "status = 'Documents Rejected'" . $hf, $hp),
    'resubmitted'         => get_count($conn, "status = 'Documents Re-submitted'" . $hf, $hp),
    'exam_scheduled'      => get_count($conn, "status = 'Exam Scheduled'" . $hf, $hp),
    'exam_completed'      => get_count($conn, "status = 'Exam Completed'" . $hf, $hp),
    'exam_failed'         => get_count($conn, "status = 'Exam Failed'" . $hf, $hp),
    'no_show_exam'        => get_count($conn, "status = 'Exam No Show'" . $hf, $hp),
    'no_show_interview'   => get_count($conn, "status = 'Interview No Show'" . $hf, $hp),
    'interview_scheduled' => get_count($conn, "status = 'Interview Scheduled'" . $hf, $hp),
    'interview_completed' => get_count($conn, "status = 'Interview Completed'" . $hf, $hp),
    'admitted'            => get_count($conn, "(status = 'Admitted/Enrolled' OR status = 'Enrolled')" . $hf, $hp),
    'rejected_final'      => get_count($conn, "status = 'Rejected'" . $hf, $hp),
    'withdrawn'           => get_count($conn, "status = 'Application Withdrawn'" . $hf, $hp),
    'ched'                => get_count($conn, "program_category = 'CHED'" . $hf, $hp),
    'tesda'               => get_count($conn, "program_category = 'TESDA'" . $hf, $hp),
];

// ── FETCH WORK QUEUE ───────────────────────────────────────
$allowed_filters = [
    'all', 'Application Submitted', 'Documents Under Review',
    'Documents Verified', 'Documents Rejected', 'Documents Re-submitted',
    'Exam Scheduled', 'Exam Completed', 'Exam Failed', 'Exam No Show',
    'Interview Scheduled', 'Interview Completed', 'Interview No Show',
    'Admitted/Enrolled', 'Rejected', 'Application Withdrawn'
];

$filter       = trim($_GET['status'] ?? 'all');
$q            = trim($_GET['q'] ?? '');
$track_filter = trim($_GET['track'] ?? 'all');

if (!in_array($filter, $allowed_filters)) $filter = 'all';
if (!in_array($track_filter, ['all','CHED','TESDA'], true)) $track_filter = 'all';

// Program heads only see interview-stage applicants
$base_status_filter = $is_program_head
    ? "a.submitted = 1 AND a.status IN (
           'Documents Verified',
           'Exam Completed',
           'Awaiting Applicant Decision',
           'Interview Scheduled',
           'Interview Completed',
           'Interview No Show',
           'Admitted/Enrolled',
           'Rejected'
       )"
    : "a.submitted = 1 AND a.status != 'Draft'";

$sql = "SELECT a.id, a.reference_number, a.first_name, a.last_name,
               a.first_choice, a.status, a.program_category,
               a.exam_score, a.submitted_at, a.updated_at, u.email
        FROM applications a
        JOIN users u ON a.user_id = u.id
        WHERE {$base_status_filter}";

if ($filter !== 'all') {
    $filter_esc = mysqli_real_escape_string($conn, $filter);
    $sql .= " AND a.status = '$filter_esc'";
}
if ($track_filter !== 'all') {
    $track_esc = mysqli_real_escape_string($conn, $track_filter);
    $sql .= " AND a.program_category = '$track_esc'";
}
if ($q !== '') {
    $q_esc = mysqli_real_escape_string($conn, $q);
    $like = '%' . $q_esc . '%';
    $sql .= " AND (a.reference_number LIKE '$like'
                OR a.first_name LIKE '$like'
                OR a.last_name LIKE '$like'
                OR u.email LIKE '$like')";
}

// Append department filter for program heads
$sql .= $head_filter;

$sql .= " ORDER BY
            CASE a.status
                WHEN 'Documents Re-submitted' THEN 1
                WHEN 'Application Submitted'  THEN 2
                WHEN 'Documents Under Review' THEN 3
                WHEN 'Interview Scheduled'    THEN 4
                ELSE 5
            END ASC,
            a.submitted_at ASC
          LIMIT 15";

$applications = [];
if (!empty($head_params)) {
    $stmt = mysqli_prepare($conn, $sql);
    $types = str_repeat('s', count($head_params));
    mysqli_stmt_bind_param($stmt, $types, ...$head_params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) $applications[] = $row;
    mysqli_stmt_close($stmt);
} else {
    $res = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($res)) $applications[] = $row;
}

$status_colors = [
    'Draft'                  => '#6c757d',
    'Application Submitted'  => '#0d6efd',
    'Documents Under Review' => '#fd7e14',
    'Documents Verified'     => '#198754',
    'Documents Rejected'     => '#dc3545',
    'Documents Re-submitted' => '#6f42c1',
    'Exam Scheduled'         => '#1565c0',
    'Exam Completed'         => '#198754',
    'Exam Failed'            => '#dc3545',
    'Exam No Show'           => '#6c757d',
    'Interview No Show'      => '#6c757d',
    'Interview Scheduled'    => '#6f42c1',
    'Interview Completed'    => '#0b7285',
    'Admitted/Enrolled'      => '#006400',
    'Rejected'               => '#dc3545',
    'Awaiting Applicant Decision' => '#f9a825',
    'Application Withdrawn'  => '#6c757d',
];

$success = $error = '';
if (isset($_SESSION['admin_success'])) { $success = $_SESSION['admin_success']; unset($_SESSION['admin_success']); }
if (isset($_SESSION['admin_error']))   { $error   = $_SESSION['admin_error'];   unset($_SESSION['admin_error']); }

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/admin-styles.css">
    <title>Admin Dashboard - BPC iEnroll</title>
    <style>
        .main { padding:1.25rem 1.5rem; }
        .page-header { margin-bottom:1.5rem; }

        /* KPI RIBBON */
        .kpi-row {
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(190px, 1fr));
            gap:1rem;
            margin-bottom:1.5rem;
        }
        .kpi-card {
            background:var(--white);
            border-radius:12px;
            padding:0.9rem 1.1rem;
            box-shadow:0 2px 8px rgba(0,0,0,0.06);
            border:1px solid var(--border-color);
            display:flex;
            flex-direction:column;
            justify-content:space-between;
            min-height:82px;
        }
        .kpi-label {
            font-size:0.78rem;
            text-transform:uppercase;
            letter-spacing:0.08em;
            color:var(--text-gray);
            font-weight:800;
        }
        .kpi-value {
            margin-top:0.35rem;
            font-size:1.4rem;
            font-weight:900;
            color:var(--bpc-green-dark);
        }
        .kpi-footer {
            margin-top:0.25rem;
            font-size:0.75rem;
            color:var(--text-gray);
            display:flex;
            justify-content:space-between;
            align-items:center;
        }
        .kpi-link {
            font-weight:700;
            color:var(--bpc-green-dark);
            text-decoration:none;
            font-size:0.75rem;
        }
        .kpi-link:hover { text-decoration:underline; }

        details.metrics { margin-top:0.9rem; border-top:1px solid var(--border-color); padding-top:0.9rem; }
        details.metrics summary { cursor:pointer; list-style:none; font-weight:800; font-size:0.85rem; color:var(--text-dark); }
        details.metrics summary::-webkit-details-marker { display:none; }
        .metrics-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:0.75rem; margin-top:0.85rem; }
        .metric { border:1px solid var(--border-color); border-radius:10px; padding:0.85rem 1rem; background:#fafafa; }
        .metric .num { font-size:1.25rem; font-weight:900; line-height:1; }
        .metric .lbl { font-size:0.78rem; color:var(--text-gray); margin-top:0.25rem; font-weight:700; }

        /* QUICK ACTIONS */
        .quick-actions { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px,1fr)); gap:1rem; margin-bottom:1.5rem; }
        .action-btn { display:flex; align-items:center; gap:0.75rem; padding:1rem 1.25rem; background:var(--white); border:2px solid var(--border-color); border-radius:10px; text-decoration:none; color:var(--text-dark); font-weight:600; font-size:0.875rem; transition:all 0.2s; }
        .action-btn:hover { border-color:var(--bpc-green); background:#f0fdf4; }
        .action-btn svg { width:20px; height:20px; flex-shrink:0; }

        /* Program head scope banner */
        .scope-banner {
            background:#f3e5f5;
            border:1px solid #ce93d8;
            border-radius:8px;
            padding:0.75rem 1.25rem;
            margin-bottom:1.25rem;
            font-size:0.875rem;
            color:#4a148c;
            display:flex;
            align-items:center;
            gap:0.75rem;
        }
        .scope-banner strong { font-weight:700; }

        @media(max-width:768px) {
            .kpi-row { grid-template-columns:repeat(2,1fr); }
        }
    </style>
</head>
<body>
<?php include '../shared/admin-sidebar.php'; ?>

<main class="main">
    <div class="page-header">
        <h1>
            <?php
            echo match($_SESSION['admin_role'] ?? '') {
                ADMIN_ROLE_SUPER_ADMIN       => 'Admission Management Console',
                ADMIN_ROLE_ADMISSION_OFFICER => 'Admission Officer Dashboard',
                ADMIN_ROLE_REGISTRAR         => 'Registrar Dashboard',
                ADMIN_ROLE_PROGRAM_HEAD      => 'Program Head Console',
                default                      => 'Admin Dashboard'
            };
            ?>
        </h1>
        <p>
            <?php echo htmlspecialchars($_SESSION['admin_name']); ?>
            <?php if ($is_program_head && !empty($head_program_labels)): ?>
                &nbsp;|&nbsp; <?php echo htmlspecialchars(implode(' / ', $head_program_labels)); ?>
            <?php endif; ?>
        </p>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <?php if ($is_program_head): ?>
    <!-- PROGRAM HEAD SCOPE BANNER -->
    <div class="scope-banner">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
        </svg>
        You are viewing applicants assigned to your programs only.
        Exam scheduling and document review are handled by the Admission Officer.
    </div>

    <!-- PROGRAM HEAD KPI ROW -->
    <div class="kpi-row">
        <div class="kpi-card">
            <div class="kpi-label">Ready for Interview</div>
            <div class="kpi-value"><?php echo (int)$stats['interview_scheduled']; ?></div>
            <div class="kpi-footer">
                <span>Interview scheduled</span>
                <a class="kpi-link" href="admin-interview-schedule.php">Schedule</a>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Pending Decision</div>
            <div class="kpi-value"><?php echo (int)$stats['interview_completed']; ?></div>
            <div class="kpi-footer">
                <span>Interview completed</span>
                <a class="kpi-link" href="admin-final-decision.php">Decide</a>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Admitted</div>
            <div class="kpi-value"><?php echo (int)$stats['admitted']; ?></div>
            <div class="kpi-footer">
                <span>From your programs</span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Rejected</div>
            <div class="kpi-value"><?php echo (int)$stats['rejected_final']; ?></div>
            <div class="kpi-footer">
                <span>Final rejections</span>
            </div>
        </div>
    </div>

    <!-- PROGRAM HEAD QUICK ACTIONS -->
    <div class="quick-actions">
        <a href="admin-interview-schedule.php" class="action-btn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
            </svg>
            Schedule Interview
        </a>
        <a href="admin-interview-results.php" class="action-btn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
            </svg>
            Interview Results
        </a>
        <a href="admin-final-decision.php" class="action-btn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/>
            </svg>
            Final Decision
        </a>
        <a href="admin-exam-view.php" class="action-btn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
            </svg>
            View Exam Scores
        </a>
    </div>

    <?php else: ?>
    <!-- FULL ADMIN KPI ROW -->
    <?php
    $needs_decision_total    = $stats['submitted'] + $stats['under_review'];
    $requires_followup_total = $stats['resubmitted'] + $stats['rejected_docs'];
    $assessments_total       = $stats['exam_scheduled'] + $stats['interview_scheduled'];
    ?>
    <div class="kpi-row">
        <div class="kpi-card">
            <div class="kpi-label">Total Applications</div>
            <div class="kpi-value"><?php echo (int)$stats['total']; ?></div>
            <div class="kpi-footer">
                <span>All submissions</span>
                <a class="kpi-link" href="admin-applications.php">View</a>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Needs Decision</div>
            <div class="kpi-value"><?php echo (int)$needs_decision_total; ?></div>
            <div class="kpi-footer">
                <span>New + under review</span>
                <a class="kpi-link" href="admin-applications.php?status=Documents%20Under%20Review">Focus</a>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Requires Follow-up</div>
            <div class="kpi-value"><?php echo (int)$requires_followup_total; ?></div>
            <div class="kpi-footer">
                <span>Re-submitted or rejected</span>
                <a class="kpi-link" href="admin-applications.php?status=Documents%20Re-submitted">View</a>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Upcoming Assessments</div>
            <div class="kpi-value"><?php echo (int)$assessments_total; ?></div>
            <div class="kpi-footer">
                <span>Exam + interview scheduled</span>
                <a class="kpi-link" href="admin-applications.php?status=Exam%20Scheduled">Go</a>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Admitted / Enrolled</div>
            <div class="kpi-value"><?php echo (int)$stats['admitted']; ?></div>
            <div class="kpi-footer">
                <span>Completed admission</span>
                <a class="kpi-link" href="admin-applications.php?status=Admitted%2FEnrolled">View</a>
            </div>
        </div>
    </div>

    <!-- FULL ADMIN WORK QUEUE CHIPS -->
    <div class="queue-card">
        <div class="queue-top">
            <div>
                <div class="queue-title">Work Queue</div>
                <div class="queue-chips">
                    <?php
                    $track_param = $track_filter !== 'all' ? '&track=' . urlencode($track_filter) : '';
                    $q_param     = $q !== '' ? '&q=' . urlencode($q) : '';
                    $chips = [
                        ['label'=>'All Submitted',       'status'=>'all',                      'count'=>$stats['total']],
                        ['label'=>'Re-submitted',         'status'=>'Documents Re-submitted',   'count'=>$stats['resubmitted']],
                        ['label'=>'New',                  'status'=>'Application Submitted',    'count'=>$stats['submitted']],
                        ['label'=>'Under Review',         'status'=>'Documents Under Review',   'count'=>$stats['under_review']],
                        ['label'=>'Interview Completed',  'status'=>'Interview Completed',      'count'=>$stats['interview_completed']],
                    ];
                    foreach ($chips as $c) {
                        $is_active = ($filter === $c['status']);
                        $href = 'admin-applications.php?status=' . ($c['status'] === 'all' ? 'all' : urlencode($c['status'])) . $track_param . $q_param;
                        echo '<a class="chip' . ($is_active ? ' active' : '') . '" href="' . htmlspecialchars($href) . '">'
                            . htmlspecialchars($c['label'])
                            . ' <span class="chip-count">(' . (int)$c['count'] . ')</span>'
                            . '</a>';
                    }
                    ?>
                    <a class="chip" href="admin-final-decision.php">
                        Final Decision <span class="chip-count">(<?php echo (int)$stats['interview_completed']; ?>)</span>
                    </a>
                </div>
            </div>
            <div class="queue-controls">
                <a href="admin-applications.php" class="btn-clear">Search & filter all →</a>
            </div>
        </div>

        <details class="metrics">
            <summary>View metrics</summary>
            <div class="metrics-grid">
                <div class="metric"><div class="num"><?php echo (int)$stats['verified']; ?></div><div class="lbl">Documents Verified</div></div>
                <div class="metric"><div class="num"><?php echo (int)$stats['rejected_docs']; ?></div><div class="lbl">Documents Rejected</div></div>
                <div class="metric"><div class="num"><?php echo (int)$stats['exam_scheduled']; ?></div><div class="lbl">Exam Scheduled</div></div>
                <div class="metric"><div class="num"><?php echo (int)$stats['exam_completed']; ?></div><div class="lbl">Exam Passed</div></div>
                <div class="metric"><div class="num"><?php echo (int)$stats['exam_failed']; ?></div><div class="lbl">Exam Failed</div></div>
                <div class="metric">
//         <div class="num"><?php echo (int)($stats['no_show_exam'] + $stats['no_show_interview']); ?></div>
//         <div class="lbl">No Show</div>
//         <div style="font-size:0.72rem; color:var(--text-gray); margin-top:0.25rem;">
//             <?php echo (int)$stats['no_show_exam']; ?> exam
//             &nbsp;·&nbsp;
//             <?php echo (int)$stats['no_show_interview']; ?> interview
//         </div>
//     </div>
                <div class="metric"><div class="num"><?php echo (int)$stats['interview_scheduled']; ?></div><div class="lbl">Interview Scheduled</div></div>
                <div class="metric"><div class="num"><?php echo (int)$stats['interview_completed']; ?></div><div class="lbl">Interview Completed</div></div>
                <div class="metric"><div class="num"><?php echo (int)$stats['admitted']; ?></div><div class="lbl">Admitted/Enrolled</div></div>
                <div class="metric"><div class="num"><?php echo (int)$stats['rejected_final']; ?></div><div class="lbl">Rejected (Final)</div></div>
                <div class="metric">
                    <div class="num"><?php echo (int)$stats['withdrawn']; ?></div>
                    <div class="lbl">Withdrawn by Applicant</div>
                </div>
                <div class="metric"><div class="num"><?php echo (int)$stats['ched']; ?></div><div class="lbl">CHED Applicants</div></div>
                <div class="metric"><div class="num"><?php echo (int)$stats['tesda']; ?></div><div class="lbl">TESDA Applicants</div></div>
            </div>
        </details>
    </div>

    <!-- FULL ADMIN QUICK ACTIONS -->
    <div class="quick-actions">
        <a href="admin-exam-schedule.php" class="action-btn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
            Schedule Exam
        </a>
        <a href="admin-exam-results.php" class="action-btn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
            Encode Exam Results
        </a>
        <a href="admin-interview-schedule.php" class="action-btn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            Schedule Interview
        </a>
        <a href="admin-final-decision.php" class="action-btn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
            Final Decision
        </a>
    </div>
    <?php endif; ?>

    <!-- WORK QUEUE TABLE (both roles, filtered appropriately) -->
    <div class="table-card">
        <div class="table-header">
            <h2>
                <?php echo $is_program_head ? 'Your Applicants' : 'Work queue preview'; ?>
                <span style="font-weight:400;color:var(--text-gray);font-size:0.9rem;">
                    (top 15<?php echo $is_program_head ? ', interview stage' : ' by priority'; ?>)
                </span>
            </h2>
            <?php if (!$is_program_head): ?>
            <a href="admin-applications.php" class="btn-export">View all applications →</a>
            <?php endif; ?>
        </div>
        <div class="table-wrapper">
            <?php if (empty($applications)): ?>
            <div class="no-data"><p>No applicants found for your assigned programs.</p></div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Program</th>
                        <th>Track</th>
                        <th>Reference #</th>
                        <?php if ($is_program_head): ?>
                        <th>Exam Score</th>
                        <?php endif; ?>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $needs_review = ['Application Submitted','Documents Under Review','Documents Re-submitted'];
                foreach ($applications as $app):
                    $is_priority = $app['status'] === 'Documents Re-submitted';
                    $color       = $status_colors[$app['status']] ?? '#6c757d';
                    $program     = get_program_label($app['first_choice'] ?? '');
                    $track       = $app['program_category'] ?? '—';
                    $show_review = in_array($app['status'], $needs_review);
                ?>
                <tr class="<?php echo $is_priority ? 'priority' : ''; ?>">
                    <td onclick="window.location='admin-application-detail.php?id=<?php echo $app['id']; ?>'" style="cursor:pointer;">
                        <div class="applicant-name">
                            <?php echo htmlspecialchars($app['last_name'] . ', ' . $app['first_name']); ?>
                            <?php if ($is_priority): ?><span class="priority-flag">Re-submitted</span><?php endif; ?>
                        </div>
                        <div class="applicant-email"><?php echo htmlspecialchars($app['email']); ?></div>
                    </td>
                    <td onclick="window.location='admin-application-detail.php?id=<?php echo $app['id']; ?>'" style="cursor:pointer;">
                        <?php echo htmlspecialchars($program); ?>
                    </td>
                    <td onclick="window.location='admin-application-detail.php?id=<?php echo $app['id']; ?>'" style="cursor:pointer;">
                        <?php if ($track === 'CHED'): ?>
                            <span class="track-badge track-ched">CHED</span>
                        <?php elseif ($track === 'TESDA'): ?>
                            <span class="track-badge track-tesda">TESDA</span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td onclick="window.location='admin-application-detail.php?id=<?php echo $app['id']; ?>'" style="cursor:pointer;">
                        <span class="ref-number">
                            <?php echo $app['reference_number'] ? htmlspecialchars($app['reference_number']) : '—'; ?>
                        </span>
                    </td>
                    <?php if ($is_program_head): ?>
                    <td onclick="window.location='admin-application-detail.php?id=<?php echo $app['id']; ?>'" style="cursor:pointer;">
                        <?php if ($app['exam_score'] !== null): ?>
                            <strong style="color:var(--bpc-green-dark);"><?php echo (int)$app['exam_score']; ?>/100</strong>
                        <?php else: ?>
                            <span style="color:var(--text-gray);">—</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td onclick="window.location='admin-application-detail.php?id=<?php echo $app['id']; ?>'" style="cursor:pointer;">
                        <span class="status-badge" style="background:<?php echo $color; ?>;">
                            <?php echo htmlspecialchars($app['status']); ?>
                        </span>
                    </td>
                    <td onclick="event.stopPropagation();">
                        <?php if (!$is_program_head && $show_review): ?>
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
</html>