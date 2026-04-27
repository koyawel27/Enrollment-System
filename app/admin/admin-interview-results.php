<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin - Interview Results
 * admin-interview-results.php
 *
 * FIX: Date selector is now inline in the filter bar (not a separate card).
 * Browsing all dates is still allowed. Submission is blocked unless a
 * specific date is selected.
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/admin-auth-check.php';
require_once CONFIG_PATH . '/admin-permissions.php';
require_admin_role([
    ADMIN_ROLE_SUPER_ADMIN,
    ADMIN_ROLE_ADMISSION_OFFICER,
    ADMIN_ROLE_PROGRAM_HEAD,
]);

[$head_filter, $head_params] = get_head_program_filter($conn);

// ── FETCH DISTINCT INTERVIEW DATES ─────────────────────────
$date_sql = "SELECT DISTINCT a.interview_date
             FROM applications a
             WHERE a.status = 'Interview Scheduled'
               AND a.interview_date IS NOT NULL
               {$head_filter}
             ORDER BY a.interview_date ASC";

$available_dates = [];
if (!empty($head_params)) {
    $dq = mysqli_prepare($conn, $date_sql);
    $types = str_repeat('s', count($head_params));
    mysqli_stmt_bind_param($dq, $types, ...$head_params);
    mysqli_stmt_execute($dq);
    $dq_res = mysqli_stmt_get_result($dq);
    while ($row = mysqli_fetch_assoc($dq_res)) $available_dates[] = $row['interview_date'];
    mysqli_stmt_close($dq);
} else {
    $dq_res = mysqli_query($conn, $date_sql);
    while ($row = mysqli_fetch_assoc($dq_res)) $available_dates[] = $row['interview_date'];
}

// ── ACTIVE DATE FILTER ─────────────────────────────────────
$filter_date = isset($_GET['interview_date']) ? trim($_GET['interview_date']) : '';
if ($filter_date && !in_array($filter_date, $available_dates)) $filter_date = '';
$single_date_selected = ($filter_date !== '');

// ── FETCH PENDING ──────────────────────────────────────────
$pending = [];
$date_condition = $single_date_selected ? " AND a.interview_date = ?" : "";
$pq_sql =
    "SELECT a.id, a.first_name, a.last_name, a.reference_number,
            COALESCE(a.assigned_program, a.first_choice) AS display_program,
            a.applicant_type, a.program_category,
            a.interview_date, a.interview_time, a.interview_venue,
            a.interview_type, u.email,
            a.interview_score, a.interview_remarks
     FROM applications a
     JOIN users u ON a.user_id = u.id
     WHERE a.status = 'Interview Scheduled'
       {$head_filter}
       {$date_condition}
     ORDER BY a.interview_date ASC, a.interview_time ASC, a.last_name ASC";

$combined_params = $head_params;
if ($single_date_selected) $combined_params[] = $filter_date;

if (!empty($combined_params)) {
    $pq = mysqli_prepare($conn, $pq_sql);
    $types = str_repeat('s', count($combined_params));
    mysqli_stmt_bind_param($pq, $types, ...$combined_params);
    mysqli_stmt_execute($pq);
    $pq_res = mysqli_stmt_get_result($pq);
    while ($row = mysqli_fetch_assoc($pq_res)) $pending[] = $row;
    mysqli_stmt_close($pq);
} else {
    $pq_res = mysqli_query($conn, $pq_sql);
    while ($row = mysqli_fetch_assoc($pq_res)) $pending[] = $row;
}

// ── FETCH PROCESSED HISTORY ────────────────────────────────
$processed = [];
$prq_sql =
    "SELECT a.id, a.first_name, a.last_name, a.reference_number,
            COALESCE(a.assigned_program, a.first_choice) AS display_program,
            a.program_category, a.status,
            a.interview_date, a.interview_marked_by, a.interview_marked_at,
            a.interview_notes
     FROM applications a
     WHERE a.status IN ('Interview Completed','Admitted/Enrolled','Rejected','Interview No Show')
       {$head_filter}
     ORDER BY a.interview_marked_at DESC";
if (!empty($head_params)) {
    $prq = mysqli_prepare($conn, $prq_sql);
    $types = str_repeat('s', count($head_params));
    mysqli_stmt_bind_param($prq, $types, ...$head_params);
    mysqli_stmt_execute($prq);
    $prq_res = mysqli_stmt_get_result($prq);
    while ($row = mysqli_fetch_assoc($prq_res)) $processed[] = $row;
    mysqli_stmt_close($prq);
} else {
    $prq_res = mysqli_query($conn, $prq_sql);
    while ($row = mysqli_fetch_assoc($prq_res)) $processed[] = $row;
}

// ── SESSION MESSAGES ───────────────────────────────────────
$success = $error = '';
if (isset($_SESSION['admin_success'])) { $success = $_SESSION['admin_success']; unset($_SESSION['admin_success']); }
if (isset($_SESSION['admin_error']))   { $error   = $_SESSION['admin_error'];   unset($_SESSION['admin_error']); }

$pending_programs   = array_values(array_unique(array_filter(array_column($pending,   'display_program'))));
$processed_programs = array_values(array_unique(array_filter(array_column($processed, 'display_program'))));
sort($pending_programs);
sort($processed_programs);

$cnt_pending  = count($pending);
$cnt_passed   = count(array_filter($processed, fn($r) => $r['status'] === 'Interview Completed'));
$cnt_admitted = count(array_filter($processed, fn($r) => $r['status'] === 'Admitted/Enrolled'));
$cnt_rejected = count(array_filter($processed, fn($r) => $r['status'] === 'Rejected'));

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/admin-styles.css">
    <title>Interview Results - Admin | BPC iEnroll</title>
    <style>
        .top-nav { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem; }
        .top-nav h1 { font-size:1.5rem; color:var(--bpc-green-dark); }
        .top-nav span { font-size:0.82rem; color:var(--text-gray); }
        .alert { margin-bottom:1.25rem; }

        .stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:1.5rem; }
        .stat-card { background:var(--white); border-radius:10px; padding:1.25rem 1.5rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); border-top:4px solid var(--border-color); }
        .stat-card.blue  { border-color:#007bff; }
        .stat-card.green { border-color:var(--bpc-green); }
        .stat-card.gold  { border-color:var(--bpc-gold); }
        .stat-card.red   { border-color:#dc3545; }
        .stat-num   { font-size:2rem; font-weight:700; line-height:1; }
        .stat-label { font-size:0.78rem; color:var(--text-gray); margin-top:0.35rem; }

        .card { background:var(--white); border-radius:10px; padding:1.75rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); margin-bottom:1.5rem; }
        .card-title { font-size:0.9rem; font-weight:700; color:var(--bpc-green-dark); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:1.5rem; padding-bottom:0.875rem; border-bottom:2px solid var(--bg-light); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; }
        .card-title span { font-size:0.8rem; font-weight:400; color:var(--text-gray); text-transform:none; letter-spacing:0; }

        .info-box { background:#d1ecf1; border:1px solid #bee5eb; border-radius:6px; padding:0.75rem 1rem; font-size:0.82rem; color:#0c5460; margin-bottom:1.25rem; }
        .multi-sched-warning { background:#fff3cd; border-left:4px solid #ffc107; border-radius:0 6px 6px 0; padding:0.875rem 1.25rem; font-size:0.875rem; color:#856404; margin-bottom:1.25rem; }
        .multi-sched-warning strong { display:block; margin-bottom:0.25rem; }
        .submit-blocked-notice { background:#fff3cd; border:1px solid #ffc107; border-radius:8px; padding:1rem 1.25rem; font-size:0.875rem; color:#856404; display:flex; align-items:center; gap:0.75rem; margin-top:1.5rem; }

        /*
         * UNIFIED FILTER BAR
         * The date selector lives here alongside Search, Program, and Present-only.
         * No separate card above the table.
         */
        .filter-bar { display:flex; align-items:center; gap:0.75rem; margin-bottom:1rem; flex-wrap:wrap; }
        .filter-bar label { font-size:0.82rem; font-weight:600; white-space:nowrap; color:var(--text-dark); }
        .filter-bar input[type="search"],
        .filter-bar select { padding:0.5rem 0.875rem; border:2px solid var(--border-color); border-radius:6px; font-size:0.875rem; background:#fff; font-family:inherit; }
        .filter-bar input[type="search"] { min-width:180px; }
        .filter-bar select:focus,
        .filter-bar input[type="search"]:focus { outline:none; border-color:var(--bpc-green); }
        .filter-bar .filter-checkbox { display:flex; align-items:center; gap:0.35rem; cursor:pointer; font-weight:600; font-size:0.82rem; }
        .filter-bar .filter-checkbox input { cursor:pointer; accent-color:var(--bpc-green); }

        /* Date select gets a green border + bg when a date is chosen */
        select.date-active { border-color:var(--bpc-green) !important; background:#f0fdf4 !important; color:var(--bpc-green-dark); font-weight:600; }

        /* Small inline hint next to the date select */
        .date-hint        { font-size:0.75rem; color:var(--text-gray); white-space:nowrap; }
        .date-hint.ready  { color:var(--bpc-green); font-weight:600; }

        /* TOOLBAR */
        .toolbar { display:flex; align-items:center; justify-content:space-between; background:var(--bg-light); border-radius:8px; padding:0.75rem 1.25rem; margin-bottom:1rem; flex-wrap:wrap; gap:0.75rem; }
        .toolbar-counts { font-size:0.82rem; color:var(--text-gray); }
        .toolbar-counts strong { color:var(--text-dark); }

        /* TABLE */
        .table-wrapper { overflow-x:auto; }
        table { min-width:unset; width:100%; border-collapse:collapse; }
        tbody tr { cursor:default; }
        th { background:var(--bg-light); color:var(--text-gray); font-size:0.72rem; text-transform:uppercase; letter-spacing:0.5px; padding:0.75rem 1rem; text-align:left; border-bottom:2px solid var(--border-color); white-space:nowrap; }
        td { padding:0.75rem 1rem; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
        tr:last-child td { border-bottom:none; }
        tr:hover td { background:#fafafa; }
        tr.row-absent td { opacity:0.5; }
        tr.row-absent .name-main { text-decoration:line-through; }

        .name-main { font-weight:700; display:block; }
        .name-sub  { font-size:0.75rem; color:var(--text-gray); }
        .ref-mono  { font-family:monospace; font-size:0.78rem; color:var(--text-gray); }
        .name-link { color:var(--bpc-green-dark); text-decoration:none; font-weight:700; }
        .name-link:hover { text-decoration:underline; }
        .cell-clickable { cursor:pointer; }
        .cell-clickable:hover { background:#f0fdf4 !important; }
        .date-col { font-size:0.82rem; white-space:nowrap; }
        .date-col small { color:var(--text-gray); display:block; }
        .track { display:inline-block; padding:0.15rem 0.5rem; border-radius:10px; font-size:0.7rem; font-weight:700; }

        .attend-cb { width:20px; height:20px; cursor:pointer; accent-color:var(--bpc-green); }

        .result-select { padding:0.4rem 0.625rem; border:2px solid var(--border-color); border-radius:6px; font-size:0.82rem; font-family:inherit; background:#fff; cursor:pointer; transition:border-color 0.2s; min-width:90px; }
        .result-select:focus   { outline:none; border-color:var(--bpc-green); }
        .result-select:disabled { background:#f0f0f0; color:#aaa; cursor:not-allowed; }
        .result-select.pass { border-color:var(--bpc-green); background:#f0fdf4; color:var(--bpc-green); font-weight:700; }
        .result-select.fail { border-color:#dc3545; background:#fff5f5; color:#dc3545; font-weight:700; }

        /* Preview badge — smaller than before */
        .result-preview { font-size:0.68rem; font-weight:700; padding:0.15rem 0.4rem; border-radius:6px; white-space:nowrap; }
        .rp-pass    { background:#d4edda; color:#155724; }
        .rp-fail    { background:#f8d7da; color:#721c24; }
        .rp-noshow  { background:#e2e3e5; color:#495057; }
        .rp-pending { background:#fff3cd; color:#856404; }

        .submit-row { display:flex; align-items:center; justify-content:space-between; margin-top:1.5rem; padding-top:1.25rem; border-top:2px solid var(--bg-light); flex-wrap:wrap; gap:1rem; }
        .submit-info { font-size:0.82rem; color:var(--text-gray); }

        .btn { display:inline-flex; align-items:center; gap:0.5rem; padding:0.75rem 1.5rem; border:none; border-radius:6px; font-size:0.875rem; font-weight:600; cursor:pointer; transition:all 0.2s; text-decoration:none; }
        .btn-green  { background:var(--bpc-green); color:#fff; }
        .btn-green:hover { background:var(--bpc-green-dark); }
        .btn-green:disabled { background:#9ca3af; cursor:not-allowed; }
        .btn-sm { padding:0.4rem 0.875rem; font-size:0.78rem; }
        .btn-outline { background:transparent; border:2px solid var(--bpc-green); color:var(--bpc-green); }
        .btn-outline:hover { background:var(--bpc-green); color:#fff; }

        .badge { display:inline-block; padding:0.2rem 0.6rem; border-radius:10px; font-size:0.72rem; font-weight:700; white-space:nowrap; }
        .b-pass     { background:#d4edda; color:#155724; }
        .b-fail     { background:#f8d7da; color:#721c24; }
        .b-noshow   { background:#e2e3e5; color:#383d41; }
        .b-admitted { background:#d4edda; color:#155724; }
        .b-rejected { background:#f8d7da; color:#721c24; }

        .empty-state { text-align:center; padding:3rem; color:var(--text-gray); font-size:0.9rem; }

        @media(max-width:960px) { .sidebar { display:none; } .stats-row { grid-template-columns:1fr 1fr; } }
    </style>
</head>
<body>

<?php include '../shared/admin-sidebar.php'; ?>

<main class="main">
    <div class="top-nav">
        <h1>Interview Results</h1>
        <span>Logged in as <strong><?php echo htmlspecialchars($_SESSION['admin_name']); ?></strong></span>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <!-- STATS -->
    <div class="stats-row">
        <div class="stat-card blue">
            <div class="stat-num"><?php echo $cnt_pending; ?></div>
            <div class="stat-label">Pending Encoding<?php echo $single_date_selected ? ' (this date)' : ' (all dates)'; ?></div>
        </div>
        <div class="stat-card green">
            <div class="stat-num"><?php echo $cnt_passed; ?></div>
            <div class="stat-label">Interview Completed</div>
        </div>
        <div class="stat-card gold">
            <div class="stat-num"><?php echo $cnt_admitted; ?></div>
            <div class="stat-label">Admitted/Enrolled</div>
        </div>
        <div class="stat-card red">
            <div class="stat-num"><?php echo $cnt_rejected; ?></div>
            <div class="stat-label">Rejected</div>
        </div>
    </div>

    <!-- ENCODE FORM -->
    <?php if (empty($pending) && $single_date_selected): ?>
    <div class="card">
        <div class="card-title">Encode Interview Results</div>
        <div class="empty-state">
            No applicants pending encoding for <?php echo date('F d, Y', strtotime($filter_date)); ?>.<br>
            <small style="margin-top:0.5rem;display:block;">All applicants scheduled for this date have already been processed.</small>
        </div>
    </div>
    <?php elseif (empty($pending)): ?>
    <div class="card">
        <div class="card-title">Encode Interview Results</div>
        <div class="empty-state">
            No applicants pending interview result encoding.<br>
            <small style="margin-top:0.5rem;display:block;">All scheduled interviews have been processed, or none have been scheduled yet.</small>
        </div>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-title">
            Encode Interview Results
            <span>
                <?php echo $cnt_pending; ?> applicant(s)
                <?php echo $single_date_selected
                    ? '— ' . date('F d, Y', strtotime($filter_date))
                    : '— all dates (view only)'; ?>
            </span>
        </div>

        <?php if (!$single_date_selected): ?>
        <div class="multi-sched-warning">
            <strong>⚠ You are viewing applicants from all interview dates combined.</strong>
            Select a specific date from the dropdown in the filter bar below to enable encoding and prevent future-date applicants from being accidentally marked No Show.
        </div>
        <?php else: ?>
        <div class="info-box">
            <strong>How to use:</strong> Check the box for applicants who <strong>showed up</strong>,
            then select their result (Pass/Fail). Leave unchecked for <strong>no-shows</strong>.
            After submitting, make the final Admit/Reject call per applicant.
        </div>
        <?php endif; ?>

        <!--
            UNIFIED FILTER BAR
            Date selector is the first control here — no separate card above.
        -->
        <div class="filter-bar">

            <!-- DATE SELECTOR — inline, first in bar -->
            <label for="interviewDateSelect">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="14" height="14" style="vertical-align:-2px;margin-right:3px;color:var(--bpc-green);"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg>
                Date:
            </label>
            <?php if (empty($available_dates)): ?>
                <span style="font-size:0.82rem;color:var(--text-gray);">No dates scheduled</span>
            <?php else: ?>
            <select id="interviewDateSelect"
                    class="<?php echo $single_date_selected ? 'date-active' : ''; ?>"
                    onchange="const v=this.value; window.location.href = v
                        ? 'admin-interview-results.php?interview_date=' + encodeURIComponent(v)
                        : 'admin-interview-results.php';">
                <option value="" <?php echo !$single_date_selected ? 'selected' : ''; ?>>— All Dates (view only) —</option>
                <?php foreach ($available_dates as $d): ?>
                <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $filter_date === $d ? 'selected' : ''; ?>>
                    <?php echo date('M d, Y (D)', strtotime($d)); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <span class="date-hint <?php echo $single_date_selected ? 'ready' : ''; ?>">
                <?php echo $single_date_selected ? '✓ Ready to encode' : '⚠ Select a date to encode'; ?>
            </span>
            <?php endif; ?>

            <!-- SEARCH -->
            <label for="searchInterviewResults">Search:</label>
            <input type="search" id="searchInterviewResults" placeholder="Name or email...">

            <!-- PROGRAM FILTER -->
            <label for="filterInterviewProgram">Program:</label>
            <select id="filterInterviewProgram">
                <option value="">All Programs</option>
                <?php foreach ($pending_programs as $p): ?>
                <option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
                <?php endforeach; ?>
            </select>

            <?php if ($single_date_selected): ?>
            <label class="filter-checkbox">
                <input type="checkbox" id="filterInterviewPresentOnly">
                Present only
            </label>
            <?php endif; ?>
        </div>

        <form method="POST" action="admin-encode-interview-results.php" id="interviewForm">
            <input type="hidden" name="interview_date" value="<?php echo htmlspecialchars($filter_date); ?>">

            <div class="toolbar">
                <div class="toolbar-counts">
                    <?php if ($single_date_selected): ?>
                    <strong id="presentCount">0</strong> present &nbsp;·&nbsp;
                    <strong id="absentCount"><?php echo $cnt_pending; ?></strong> no show &nbsp;·&nbsp;
                    <strong id="passCount">0</strong> pass &nbsp;·&nbsp;
                    <strong id="failCount">0</strong> fail
                    <?php else: ?>
                    <span style="color:#856404;">Viewing <?php echo $cnt_pending; ?> applicants across all dates — select a date above to encode</span>
                    <?php endif; ?>
                </div>
                <?php if ($single_date_selected): ?>
                <div style="display:flex;gap:0.5rem;">
                    <button type="button" class="btn btn-sm btn-outline" onclick="markAllPresent()">✓ All Present</button>
                    <button type="button" class="btn btn-sm btn-outline" onclick="clearAll()">✕ Clear All</button>
                </div>
                <?php endif; ?>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Applicant</th>
                            <th>Track</th>
                            <th>Program</th>
                            <th>Interview Date</th>
                            <?php if ($single_date_selected): ?>
                            <th style="text-align:center;">Present</th>
                            <th style="text-align:center;">Result</th>
                            <th>Remarks</th>
                            <th style="text-align:center;">Preview</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending as $i => $p):
                            $searchStr = strtolower($p['last_name'].' '.$p['first_name'].' '.($p['email']??'').' '.($p['reference_number']??'').' '.($p['display_program']??''));
                        ?>
                        <tr id="irow_<?php echo $p['id']; ?>"
                            class="<?php echo $single_date_selected ? 'row-absent ' : ''; ?>interview-pending-row"
                            data-name="<?php echo htmlspecialchars($searchStr); ?>"
                            data-program="<?php echo htmlspecialchars($p['display_program'] ?? ''); ?>">
                            <td style="color:var(--gray);font-size:0.78rem;"><?php echo $i+1; ?></td>
                            <td class="cell-clickable" onclick="window.location='admin-application-detail.php?id=<?php echo (int)$p['id']; ?>';">
                                <span class="name-main">
                                    <a href="admin-application-detail.php?id=<?php echo (int)$p['id']; ?>" class="name-link" onclick="event.stopPropagation();">
                                        <?php echo htmlspecialchars($p['last_name'].', '.$p['first_name']); ?>
                                    </a>
                                </span>
                                <span class="name-sub"><?php echo htmlspecialchars($p['email']); ?></span>
                            </td>
                            <td>
                                <?php $cat = $p['program_category'] ?? 'CHED'; ?>
                                <span class="track track-<?php echo strtolower($cat); ?>"><?php echo $cat; ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($p['display_program'] ?? '—'); ?></td>
                            <td class="date-col">
                                <?php if (!empty($p['interview_date'])): ?>
                                    <?php echo date('M d, Y', strtotime($p['interview_date'])); ?>
                                    <small><?php echo date('g:i A', strtotime($p['interview_time'])); ?></small>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <?php if ($single_date_selected): ?>
                            <td style="text-align:center;">
                                <input type="checkbox" class="attend-cb"
                                       name="present[<?php echo $p['id']; ?>]"
                                       value="1" data-id="<?php echo $p['id']; ?>"
                                       onchange="togglePresent(this)">
                            </td>
                            <td style="text-align:center;">
                                <select class="result-select"
                                        name="result[<?php echo $p['id']; ?>]"
                                        id="result_<?php echo $p['id']; ?>"
                                        data-id="<?php echo $p['id']; ?>"
                                        disabled onchange="updatePreview(this)">
                                    <option value="">— Select —</option>
                                    <option value="pass">✓ Pass</option>
                                    <option value="fail">✕ Fail</option>
                                </select>
                            </td>
                            <td>
                                <button type="button"
                                        onclick="toggleRemarks(<?php echo $p['id']; ?>)"
                                        style="background:none;border:1px solid #ccc;border-radius:6px;padding:0.3rem 0.6rem;font-size:0.75rem;color:var(--text-gray);cursor:pointer;">
                                    + Remarks
                                </button>
                                <div id="remarks_<?php echo $p['id']; ?>" style="display:none;margin-top:0.5rem;">
                                    <textarea name="interview_remarks[<?php echo $p['id']; ?>]"
                                              placeholder="Optional notes..." rows="2"
                                              style="width:180px;padding:0.35rem 0.5rem;border:1px solid #ccc;border-radius:4px;font-size:0.82rem;font-family:inherit;resize:vertical;"><?php echo htmlspecialchars($p['interview_remarks'] ?? ''); ?></textarea>
                                </div>
                            </td>
                            <td style="text-align:center;">
                                <span class="result-preview rp-noshow" id="preview_<?php echo $p['id']; ?>">No Show</span>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($single_date_selected): ?>
            <div class="submit-row">
                <div class="submit-info">
                    Unselected applicants will be marked <strong>Interview No Show</strong>.
                    After submitting, make the final Admit/Reject call per applicant.
                </div>
                <button type="submit" class="btn btn-green" id="confirmInterviewBtn" disabled>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
                    Confirm Interview Results
                </button>
            </div>
            <?php else: ?>
            <div class="submit-blocked-notice">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                <span>Select a specific interview date in the filter bar above to enable result encoding.</span>
            </div>
            <?php endif; ?>
        </form>
    </div>
    <?php endif; ?>

    <!-- HISTORY -->
    <?php if (!empty($processed)): ?>
    <div class="card">
        <div class="card-title">
            Processed History
            <span><?php echo count($processed); ?> total</span>
        </div>
        <div class="filter-bar" style="margin-bottom:1rem;">
            <label for="searchInterviewProcessed">Search:</label>
            <input type="search" id="searchInterviewProcessed" placeholder="Name or reference...">
            <label for="filterInterviewProcessedProgram">Program:</label>
            <select id="filterInterviewProcessedProgram">
                <option value="">All Programs</option>
                <?php foreach ($processed_programs as $p): ?>
                <option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="table-wrapper">
            <table id="interviewProcessedTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Applicant</th>
                        <th>Track</th>
                        <th>Program</th>
                        <th>Interview Date</th>
                        <th>Status</th>
                        <th>Processed By</th>
                        <th>Date Processed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($processed as $i => $pr):
                        $badge = match($pr['status']) {
                            'Interview Completed' => ['b-pass',     'Passed'],
                            'Interview No Show'   => ['b-noshow',   'No Show'],
                            'Admitted/Enrolled'   => ['b-admitted', 'Admitted'],
                            'Rejected'            => ['b-rejected', 'Rejected'],
                            default               => ['b-noshow',   $pr['status']]
                        };
                        $cat = $pr['program_category'] ?? 'CHED';
                        $prSearch = strtolower(($pr['last_name'].' '.$pr['first_name']).' '.($pr['reference_number']??'').' '.($pr['display_program']??''));
                    ?>
                    <tr class="interview-processed-row"
                        data-name="<?php echo htmlspecialchars($prSearch); ?>"
                        data-program="<?php echo htmlspecialchars($pr['display_program'] ?? ''); ?>"
                        onclick="window.location='admin-application-detail.php?id=<?php echo (int)$pr['id']; ?>';"
                        style="cursor:pointer;">
                        <td style="color:var(--gray);font-size:0.78rem;"><?php echo $i+1; ?></td>
                        <td>
                            <span class="name-main">
                                <a href="admin-application-detail.php?id=<?php echo (int)$pr['id']; ?>" class="name-link" onclick="event.stopPropagation();">
                                    <?php echo htmlspecialchars($pr['last_name'].', '.$pr['first_name']); ?>
                                </a>
                            </span>
                        </td>
                        <td><span class="track track-<?php echo strtolower($cat); ?>"><?php echo $cat; ?></span></td>
                        <td><?php echo htmlspecialchars($pr['display_program'] ?? '—'); ?></td>
                        <td style="font-size:0.82rem;"><?php echo $pr['interview_date'] ? date('M d, Y', strtotime($pr['interview_date'])) : '—'; ?></td>
                        <td><span class="badge <?php echo $badge[0]; ?>"><?php echo $badge[1]; ?></span></td>
                        <td style="font-size:0.82rem;"><?php echo htmlspecialchars($pr['interview_marked_by'] ?? '—'); ?></td>
                        <td style="font-size:0.82rem;white-space:nowrap;"><?php echo $pr['interview_marked_at'] ? date('M d, Y g:i A', strtotime($pr['interview_marked_at'])) : '—'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($cnt_passed > 0): ?>
        <div style="margin-top:1.25rem;text-align:right;">
            <a href="admin-final-decision.php" class="btn btn-outline">Make Final Admission Decisions →</a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</main>

<script>
const totalPending = <?php echo $cnt_pending; ?>;
const dateSelected = <?php echo $single_date_selected ? 'true' : 'false'; ?>;

function togglePresent(cb) {
    if (!dateSelected) return;
    const id  = cb.dataset.id;
    const sel = document.getElementById('result_' + id);
    const row = document.getElementById('irow_' + id);
    if (cb.checked) {
        sel.disabled = false;
        row.classList.remove('row-absent');
        row.dataset.present = '1';
        updatePreview(sel);
    } else {
        sel.disabled = true; sel.value = ''; sel.className = 'result-select';
        row.classList.add('row-absent'); row.dataset.present = '0';
        setPreview(id, 'noshow', 'No Show');
    }
    updateCounts();
    if (typeof filterInterviewTable === 'function') filterInterviewTable();
}

function updatePreview(sel) {
    const id = sel.dataset.id;
    sel.className = 'result-select';
    if (sel.value === 'pass')      { sel.classList.add('pass'); setPreview(id, 'pass', '✓ Pass'); }
    else if (sel.value === 'fail') { sel.classList.add('fail'); setPreview(id, 'fail', '✕ Fail'); }
    else                           { setPreview(id, 'pending', 'Select'); }
    updateCounts();
}

function setPreview(id, type, text) {
    const el = document.getElementById('preview_' + id);
    if (!el) return;
    el.className = 'result-preview rp-' + type;
    el.textContent = text;
}

function toggleRemarks(id) {
    const div = document.getElementById('remarks_' + id);
    const btn = div?.previousElementSibling;
    if (!div) return;
    const vis = div.style.display !== 'none';
    div.style.display = vis ? 'none' : 'block';
    if (btn) btn.textContent = vis ? '+ Remarks' : '− Remarks';
}

function updateCounts() {
    if (!dateSelected) return;
    let present = 0, pass = 0, fail = 0;
    document.querySelectorAll('.attend-cb:checked').forEach(cb => {
        present++;
        const sel = document.getElementById('result_' + cb.dataset.id);
        if (sel?.value === 'pass') pass++;
        if (sel?.value === 'fail') fail++;
    });
    document.getElementById('presentCount').textContent = present;
    document.getElementById('absentCount').textContent  = totalPending - present;
    document.getElementById('passCount').textContent    = pass;
    document.getElementById('failCount').textContent    = fail;
}

function markAllPresent() {
    if (!dateSelected) return;
    document.querySelectorAll('.attend-cb').forEach(cb => {
        cb.checked = true;
        const sel = document.getElementById('result_' + cb.dataset.id);
        if (sel) sel.disabled = false;
        const row = document.getElementById('irow_' + cb.dataset.id);
        if (row) { row.classList.remove('row-absent'); row.dataset.present = '1'; }
    });
    updateCounts();
    if (typeof filterInterviewTable === 'function') filterInterviewTable();
}

function clearAll() {
    if (!dateSelected) return;
    document.querySelectorAll('.attend-cb').forEach(cb => {
        cb.checked = false;
        const id  = cb.dataset.id;
        const sel = document.getElementById('result_' + id);
        if (sel) { sel.disabled = true; sel.value = ''; sel.className = 'result-select'; }
        const row = document.getElementById('irow_' + id);
        if (row) { row.classList.add('row-absent'); row.dataset.present = '0'; }
        setPreview(id, 'noshow', 'No Show');
    });
    updateCounts();
    if (typeof filterInterviewTable === 'function') filterInterviewTable();
}

function filterInterviewTable() {
    const search      = (document.getElementById('searchInterviewResults')?.value || '').toLowerCase().trim();
    const program     = (document.getElementById('filterInterviewProgram')?.value || '').trim();
    const presentOnly = document.getElementById('filterInterviewPresentOnly')?.checked || false;
    document.querySelectorAll('.interview-pending-row').forEach(function(row) {
        const show = (!search  || (row.getAttribute('data-name')    || '').indexOf(search)  !== -1)
                  && (!program || (row.getAttribute('data-program') || '') === program)
                  && (!presentOnly || row.querySelector('.attend-cb')?.checked);
        row.style.display = show ? '' : 'none';
    });
}

function filterInterviewProcessedTable() {
    const search  = (document.getElementById('searchInterviewProcessed')?.value || '').toLowerCase().trim();
    const program = (document.getElementById('filterInterviewProcessedProgram')?.value || '').trim();
    document.querySelectorAll('.interview-processed-row').forEach(function(row) {
        const show = (!search  || (row.getAttribute('data-name')    || '').indexOf(search)  !== -1)
                  && (!program || (row.getAttribute('data-program') || '') === program);
        row.style.display = show ? '' : 'none';
    });
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('searchInterviewResults')?.addEventListener('input', filterInterviewTable);
    document.getElementById('filterInterviewProgram')?.addEventListener('change', filterInterviewTable);
    document.getElementById('filterInterviewPresentOnly')?.addEventListener('change', filterInterviewTable);
    document.getElementById('searchInterviewProcessed')?.addEventListener('input', filterInterviewProcessedTable);
    document.getElementById('filterInterviewProcessedProgram')?.addEventListener('change', filterInterviewProcessedTable);

    if (dateSelected) {
        const form = document.getElementById('interviewForm');
        const btn  = document.getElementById('confirmInterviewBtn');
        if (form && btn) {
            const enable = () => { btn.disabled = false; };
            form.addEventListener('change', function(e) {
                const t = e.target;
                if (t?.matches('input[name^="present"]') || t?.matches('select[name^="result"]')) enable();
            }, { passive: true });
        }
    }
});

document.getElementById('interviewForm')?.addEventListener('submit', function(e) {
    if (!dateSelected) {
        e.preventDefault();
        alert('Please select a specific interview date before encoding results.');
        return false;
    }
    const form = e.currentTarget;
    const anyPresent = Array.from(form.querySelectorAll('input[name^="present"]')).some(cb => cb.checked);
    const anyResult  = Array.from(form.querySelectorAll('select[name^="result"]')).some(r => r.value?.trim());
    if (!anyPresent && !anyResult) {
        e.preventDefault();
        alert('No attendance has been recorded.\n\nPlease mark at least one applicant as Present before confirming.\n\nApplicants not marked as Present will be recorded as Interview No Show.');
        return false;
    }
    let missing = false;
    document.querySelectorAll('.attend-cb:checked').forEach(cb => {
        if (!document.getElementById('result_' + cb.dataset.id)?.value) missing = true;
    });
    if (missing) {
        e.preventDefault();
        alert('Please select Pass or Fail for all applicants marked as present.');
        return false;
    }
    const totalRows    = form.querySelectorAll('input[name^="present"]').length;
    const presentCount = Array.from(form.querySelectorAll('input[name^="present"]')).filter(cb => cb.checked).length;
    const noShowCount  = totalRows - presentCount;
    if (noShowCount > 0) {
        if (!confirm(`You are about to confirm interview results.\n\n✓ Present: ${presentCount}\n— Will be marked Interview No Show: ${noShowCount}\n\nApplicants not checked as Present will be recorded as Interview No Show. Proceed?`)) {
            e.preventDefault();
            return false;
        }
    }
    return confirm('Confirm and process interview results? This cannot be undone.');
});
</script>

</body>
</html>