<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin - Exam Results
 * admin-exam-results.php
 *
 * Admin encodes attendance + scores after exam day.
 * - Checked + score >= passing → Exam Completed
 * - Checked + score <  passing → Exam Failed
 * - Unchecked                  → Exam No Show
 *
 * FIX: Submission is blocked unless a specific schedule is selected.
 * "All Schedules" view is still available for browsing only.
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/admin-auth-check.php';
require_once CONFIG_PATH . '/admin-permissions.php';
require_admin_role([ADMIN_ROLE_SUPER_ADMIN, ADMIN_ROLE_ADMISSION_OFFICER]);

// ── FETCH EXAM SCHEDULES (for filter dropdown) ─────────────
$schedules = [];
$sc = mysqli_query($conn, "SELECT * FROM exam_schedules ORDER BY exam_date ASC");
while ($row = mysqli_fetch_assoc($sc)) $schedules[] = $row;

// ── ACTIVE FILTER ──────────────────────────────────────────
$filter_sched = isset($_GET['schedule_id']) ? (int)$_GET['schedule_id'] : 0;

// ── FETCH Exam Scheduled APPLICANTS ───────────────────────
$pending = [];
$sql = "SELECT a.id, a.first_name, a.last_name, a.reference_number,
               a.first_choice, a.applicant_type, a.exam_schedule_id,
               es.exam_date, es.exam_time, es.exam_venue,
               u.email
        FROM applications a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN exam_schedules es ON a.exam_schedule_id = es.id
        WHERE a.status = 'Exam Scheduled'
          AND (a.program_category = 'CHED' OR a.program_category IS NULL)";
if ($filter_sched > 0) $sql .= " AND a.exam_schedule_id = {$filter_sched}";
$sql .= " ORDER BY a.last_name ASC, a.first_name ASC";
$pq = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($pq)) $pending[] = $row;

// ── FETCH ALREADY PROCESSED (for history) ─────────────────
$processed = [];
$prsql = "SELECT a.id, a.first_name, a.last_name, a.reference_number,
                 a.first_choice, a.status, a.exam_score,
                 a.exam_marked_by, a.exam_marked_at,
                 es.exam_date
          FROM applications a
          LEFT JOIN exam_schedules es ON a.exam_schedule_id = es.id
          WHERE a.status IN ('Exam Completed','Exam Failed','Exam No Show','Awaiting Applicant Decision','Application Withdrawn')
          ORDER BY a.exam_marked_at DESC, a.last_name ASC";
$prq = mysqli_query($conn, $prsql);
while ($row = mysqli_fetch_assoc($prq)) $processed[] = $row;

// ── SESSION MESSAGES ───────────────────────────────────────
$success = $error = '';
if (isset($_SESSION['admin_success'])) { $success = $_SESSION['admin_success']; unset($_SESSION['admin_success']); }
if (isset($_SESSION['admin_error']))   { $error   = $_SESSION['admin_error'];   unset($_SESSION['admin_error']);   }

// Programs for filter dropdowns
$pending_programs = array_values(array_unique(array_filter(array_column($pending, 'first_choice'))));
sort($pending_programs);
$processed_programs = array_values(array_unique(array_filter(array_column($processed, 'first_choice'))));
sort($processed_programs);

// ── Is a specific schedule selected? ──────────────────────
// Submission is only allowed when exactly one schedule is in scope.
$single_schedule_selected = ($filter_sched > 0);

// Find the selected schedule details for display
$selected_schedule = null;
foreach ($schedules as $s) {
    if ((int)$s['id'] === $filter_sched) {
        $selected_schedule = $s;
        break;
    }
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/admin-styles.css">
    <title>Exam Results - Admin | BPC iEnroll</title>
    <style>
        .top-nav { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem; }
        .top-nav h1 { font-size:1.5rem; color:var(--bpc-green-dark); }
        .top-nav span { font-size:0.82rem; color:var(--text-gray); }
        .alert { margin-bottom:1.25rem; }
        .b-tesda-offer { background:#fff3cd; color:#856404; }

        /* STAT CARDS */
        .stats-row { display:grid; grid-template-columns:repeat(4, 1fr); gap:1rem; margin-bottom:1.5rem; }
        .stat-card { background:var(--white); border-radius:10px; padding:1.25rem 1.5rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); border-top:4px solid var(--border-color); }
        .stat-card.blue   { border-color:#007bff; }
        .stat-card.green  { border-color:var(--bpc-green); }
        .stat-card.red    { border-color:#dc3545; }
        .stat-card.gray   { border-color:#6c757d; }
        .stat-num   { font-size:2rem; font-weight:700; color:var(--text-dark); line-height:1; }
        .stat-label { font-size:0.78rem; color:var(--text-gray); margin-top:0.35rem; }

        /* CARD */
        .card { background:var(--white); border-radius:10px; padding:1.75rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); margin-bottom:1.5rem; }
        .card-title { font-size:0.9rem; font-weight:700; color:var(--bpc-green-dark); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:1.5rem; padding-bottom:0.875rem; border-bottom:2px solid var(--bg-light); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; }
        .card-title span { font-size:0.8rem; font-weight:400; color:var(--text-gray); text-transform:none; letter-spacing:0; }

        /* SCHEDULE SELECTOR BAR */
        .schedule-bar { display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem; background:var(--white); border-radius:10px; padding:1.25rem 1.5rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); flex-wrap:wrap; }
        .schedule-bar label { font-size:0.875rem; font-weight:700; color:var(--text-dark); white-space:nowrap; }
        .schedule-bar select { padding:0.6rem 1rem; border:2px solid var(--border-color); border-radius:6px; font-size:0.875rem; font-family:inherit; background:#fff; min-width:280px; cursor:pointer; }
        .schedule-bar select:focus { outline:none; border-color:var(--bpc-green); }
        .schedule-bar .schedule-hint { font-size:0.78rem; color:var(--text-gray); margin-left:auto; }

        /* SELECTED SCHEDULE BADGE */
        .schedule-badge { display:inline-flex; align-items:center; gap:0.5rem; background:#e8f5e9; border:1px solid #a5d6a7; border-radius:8px; padding:0.5rem 1rem; font-size:0.82rem; color:#2e7d32; font-weight:600; margin-bottom:1rem; }

        /* SUBMIT BLOCKED STATE */
        .submit-blocked-notice { background:#fff3cd; border:1px solid #ffc107; border-radius:8px; padding:1rem 1.25rem; font-size:0.875rem; color:#856404; display:flex; align-items:center; gap:0.75rem; margin-top:1.5rem; }

        /* FILTER */
        .filter-bar { display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap; }
        .filter-bar label { font-size:0.82rem; font-weight:600; color:var(--text-dark); white-space:nowrap; }
        .filter-bar select, .filter-bar input[type="search"] { padding:0.5rem 0.875rem; border:2px solid var(--border-color); border-radius:6px; font-size:0.875rem; font-family:inherit; background:#fff; }
        .filter-bar select:focus, .filter-bar input[type="search"]:focus { outline:none; border-color:var(--bpc-green); }
        .filter-bar input[type="search"] { min-width:200px; }
        .filter-bar .filter-checkbox { display:flex; align-items:center; gap:0.35rem; cursor:pointer; font-weight:600; }
        .filter-bar .filter-checkbox input { cursor:pointer; accent-color:var(--bpc-green); }
        .name-link { color:var(--bpc-green-dark); text-decoration:none; font-weight:700; }
        .name-link:hover { text-decoration:underline; }
        .cell-clickable { cursor:pointer; }
        .cell-clickable:hover { background:#f0fdf4 !important; }

        /* TOOLBAR */
        .toolbar { display:flex; align-items:center; justify-content:space-between; background:var(--bg-light); border-radius:8px; padding:0.75rem 1.25rem; margin-bottom:1rem; flex-wrap:wrap; gap:0.75rem; }
        .toolbar-left  { display:flex; align-items:center; gap:0.75rem; font-size:0.82rem; color:var(--text-gray); flex-wrap:wrap; }
        .toolbar-right { display:flex; gap:0.5rem; }

        /* TABLE */
        tbody tr { cursor:default; }
        th { position:sticky; top:0; z-index:1; background:var(--bg-light); color:var(--text-gray); font-size:0.72rem; text-transform:uppercase; letter-spacing:0.5px; padding:0.75rem 1rem; text-align:left; border-bottom:2px solid var(--border-color); white-space:nowrap; box-shadow:0 2px 0 0 var(--border-color); }
        td { padding:0.75rem 1rem; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
        tr:last-child td { border-bottom:none; }
        tr:hover td { background:#fafafa; }
        tr.row-absent td { opacity:0.5; }
        tr.row-absent td .app-name-main { text-decoration:line-through; }
        /* Results History - Score column font size */
        #processedTable td:nth-child(5) {
            font-size: 0.800rem;   /* adjust as needed: 0.8rem, 1rem, etc. */
            font-weight: 700;    /* optional: make it slightly bolder */
        }

        /* Results History - Result column (badge) smaller font */
        #processedTable td:nth-child(6) {
            font-size: 0.75rem;   /* adjust as needed */
        }

        /* Optional: also adjust the badge inside the column */
        #processedTable td:nth-child(6) .badge {
            font-size: 0.69rem;
            padding: 0.15rem 0.5rem;
        }
                

        .name-main { font-weight:700; display:block; }
        .name-sub  { font-size:0.75rem; color:var(--text-gray); }
        .ref-mono  { font-family:monospace; font-size:0.78rem; color:var(--text-gray); }

        /* ATTENDANCE CHECKBOX */
        .attend-wrap { display:flex; align-items:center; gap:0.5rem; }
        .attend-cb { width:20px; height:20px; cursor:pointer; accent-color:var(--bpc-green); }

        /* SCORE INPUT */
        .score-wrap { position:relative; display:flex; align-items:center; gap:0.5rem; }
        .score-input {
            width:80px; padding:0.45rem 0.625rem; border:2px solid var(--border-color);
            border-radius:6px; font-size:0.875rem; text-align:center;
            transition:border-color 0.2s; font-weight:700;
        }
        .score-input:focus { outline:none; border-color:var(--bpc-green); }
        .score-input.pass  { border-color:var(--bpc-green); color:var(--bpc-green); background:#f0fdf4; }
        .score-input.fail  { border-color:#dc3545;   color:#dc3545;   background:#fff5f5; }
        .score-input:disabled { background:#f0f0f0; color:#aaa; border-color:var(--border-color); }
        .passing-ref { font-size:0.72rem; color:var(--text-gray); white-space:nowrap; }

        /* RESULT PREVIEW BADGE */
        .result-preview { font-size:0.75rem; font-weight:700; padding:0.2rem 0.5rem; border-radius:8px; white-space:nowrap; }
        .rp-pass    { background:#d4edda; color:#155724; }
        .rp-fail    { background:#f8d7da; color:#721c24; }
        .rp-noshow  { background:#e2e3e5; color:#495057; }
        .rp-pending { background:#fff3cd; color:#856404; }

        /* SUBMIT ROW */
        .submit-row { display:flex; align-items:center; justify-content:space-between; margin-top:1.5rem; padding-top:1.25rem; border-top:2px solid var(--bg-light); flex-wrap:wrap; gap:1rem; }
        .submit-summary { font-size:0.875rem; color:var(--text-gray); }
        .submit-summary strong { color:var(--text-dark); }

        /* BUTTONS */
        .btn { display:inline-flex; align-items:center; gap:0.5rem; padding:0.75rem 1.5rem; border:none; border-radius:6px; font-size:0.875rem; font-weight:600; cursor:pointer; transition:all 0.2s; text-decoration:none; }
        .btn-green  { background:var(--bpc-green); color:#fff; }
        .btn-green:hover { background:var(--bpc-green-dark); }
        .btn-green:disabled { background:#9ca3af; cursor:not-allowed; }
        .btn-sm { padding:0.4rem 0.875rem; font-size:0.78rem; }
        .btn-outline { background:transparent; border:2px solid var(--bpc-green); color:var(--bpc-green); }
        .btn-outline:hover { background:var(--bpc-green); color:#fff; }

        /* HISTORY BADGES */
        .badge { display:inline-block; padding:0.2rem 0.6rem; border-radius:10px; font-size:0.72rem; font-weight:700; white-space:nowrap; }
        .b-completed { background:#d4edda; color:#155724; }
        .b-failed    { background:#f8d7da; color:#721c24; }
        .b-noshow    { background:#e2e3e5; color:#383d41; }

        .empty-state { text-align:center; padding:3rem; color:var(--text-gray); font-size:0.9rem; }
        .info-box    { background:#d1ecf1; border:1px solid #bee5eb; border-radius:6px; padding:0.75rem 1rem; font-size:0.82rem; color:#0c5460; margin-bottom:1.25rem; }

        /* MULTI-SCHEDULE WARNING */
        .multi-sched-warning { background:#fff3cd; border-left:4px solid #ffc107; border-radius:0 6px 6px 0; padding:0.875rem 1.25rem; font-size:0.875rem; color:#856404; margin-bottom:1.25rem; }
        .multi-sched-warning strong { display:block; margin-bottom:0.25rem; }

        @media(max-width:960px) { .stats-row { grid-template-columns:1fr 1fr; } }
        @media(max-width:500px) { .stats-row { grid-template-columns:1fr 1fr; } }
    </style>
</head>
<body>

<?php include '../shared/admin-sidebar.php'; ?>

<main class="main">
    <div class="top-nav">
        <h1>Exam Results</h1>
        <span>Logged in as <strong><?php echo htmlspecialchars($_SESSION['admin_name']); ?></strong></span>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <!-- CHED ONLY NOTICE -->
    <div style="display:flex;align-items:center;gap:0.75rem;background:#e8f5e9;border:1px solid #a5d6a7;border-radius:8px;padding:0.875rem 1.25rem;margin-bottom:1.5rem;font-size:0.875rem;color:#2e7d32;">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
        <span>Showing <strong>CHED applicants only</strong> — TESDA applicants skip the exam and proceed directly to interview.</span>
    </div>

    <!-- STAT CARDS -->
    <?php
    $cnt_scheduled = count($pending);
    $cnt_passed  = count(array_filter($processed, fn($r) => $r['status'] === 'Exam Completed'));
    $cnt_failed  = count(array_filter($processed, fn($r) => $r['status'] === 'Exam Failed'));
    $cnt_noshow  = count(array_filter($processed, fn($r) => $r['status'] === 'Exam No Show'));
    ?>
    <div class="stats-row">
        <div class="stat-card blue">
            <div class="stat-num"><?php echo $cnt_scheduled; ?></div>
            <div class="stat-label">Pending Encoding<?php echo $single_schedule_selected ? ' (this schedule)' : ' (all schedules)'; ?></div>
        </div>
        <div class="stat-card green">
            <div class="stat-num"><?php echo $cnt_passed; ?></div>
            <div class="stat-label">Passed</div>
        </div>
        <div class="stat-card red">
            <div class="stat-num"><?php echo $cnt_failed; ?></div>
            <div class="stat-label">Failed</div>
        </div>
        <div class="stat-card gray">
            <div class="stat-num"><?php echo $cnt_noshow; ?></div>
            <div class="stat-label">No Show</div>
        </div>
    </div>

    <!-- SCHEDULE SELECTOR -->
    <div class="schedule-bar">
        <label for="scheduleSelect">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="16" height="16" style="vertical-align:-3px;margin-right:4px;color:var(--bpc-green);"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/></svg>
            Exam Schedule:
        </label>
        <select id="scheduleSelect" onchange="window.location.href='admin-exam-results.php?schedule_id='+this.value">
            <option value="0" <?php echo $filter_sched === 0 ? 'selected' : ''; ?>>— All Schedules (view only) —</option>
            <?php foreach ($schedules as $s): ?>
            <option value="<?php echo $s['id']; ?>" <?php echo $filter_sched === (int)$s['id'] ? 'selected' : ''; ?>>
                <?php echo date('M d, Y (D)', strtotime($s['exam_date'])); ?>
                @ <?php echo date('g:i A', strtotime($s['exam_time'])); ?>
                — <?php echo htmlspecialchars($s['exam_venue']); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <?php if (!$single_schedule_selected): ?>
        <span class="schedule-hint">⚠ Select a specific schedule to encode results</span>
        <?php else: ?>
        <span class="schedule-hint" style="color:var(--bpc-green);font-weight:600;">✓ Ready to encode</span>
        <?php endif; ?>
    </div>

    <!-- ENCODE RESULTS FORM -->
    <?php if (empty($pending) && $single_schedule_selected): ?>
        <div class="card">
            <div class="card-title">Encode Exam Results</div>
            <div class="empty-state">
                No applicants pending result encoding for this schedule.<br>
                <small style="margin-top:0.5rem; display:block;">All applicants in this batch have already been processed.</small>
            </div>
        </div>
    <?php elseif (empty($pending) && !$single_schedule_selected): ?>
        <div class="card">
            <div class="card-title">Encode Exam Results</div>
            <div class="empty-state">
                No applicants are currently pending result encoding across any schedule.<br>
                <small style="margin-top:0.5rem; display:block;">All scheduled applicants have been processed, or no exam has been scheduled yet.</small>
            </div>
        </div>
    <?php else: ?>
    <div class="card">
        <div class="card-title">
            Encode Exam Results
            <span>
                <?php echo count($pending); ?> applicant(s)
                <?php echo $single_schedule_selected ? '— ' . date('M d, Y', strtotime($selected_schedule['exam_date'])) . ' @ ' . htmlspecialchars($selected_schedule['exam_venue']) : '— all schedules (view only)'; ?>
            </span>
        </div>

        <?php if (!$single_schedule_selected): ?>
        <!-- BROWSING ALL SCHEDULES — SUBMISSION BLOCKED -->
        <div class="multi-sched-warning">
            <strong>⚠ You are viewing applicants from all schedules combined.</strong>
            To encode results, select a specific exam schedule from the dropdown above.
            This prevents applicants from future dates from being accidentally marked as No Show.
        </div>
        <?php else: ?>
        <div class="info-box">
            <strong>How to use:</strong>
            Check the box for applicants who <strong>showed up</strong> and enter their score.
            Leave unchecked for <strong>no-shows</strong> — they will be marked automatically.
            Pass/fail is determined by <strong>per-program cutoffs</strong> (e.g. ACT 75, BSIS 85).
        </div>
        <?php endif; ?>

        <!-- INLINE FILTERS -->
        <div class="filter-bar" id="examResultsFilterBar">
            <label for="searchExamResults">Search:</label>
            <input type="search" id="searchExamResults" placeholder="Search by name or email...">
            <label for="filterExamProgram">Program:</label>
            <select id="filterExamProgram">
                <option value="">All Programs</option>
                <?php foreach ($pending_programs as $p): ?>
                <option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($single_schedule_selected): ?>
            <label class="filter-checkbox">
                <input type="checkbox" id="filterPresentOnly">
                Present only
            </label>
            <?php endif; ?>
        </div>

        <form method="POST" action="admin-encode-results.php" id="resultsForm">
            <!-- Pass the selected schedule_id to the handler -->
            <input type="hidden" name="schedule_id" value="<?php echo $filter_sched; ?>">

            <div class="toolbar">
                <div class="toolbar-left">
                    <?php if ($single_schedule_selected): ?>
                    <span>
                        <strong id="presentCount">0</strong> present &nbsp;·&nbsp;
                        <strong id="absentCount"><?php echo count($pending); ?></strong> no show &nbsp;·&nbsp;
                        <strong id="passCount">0</strong> passing &nbsp;·&nbsp;
                        <strong id="failCount">0</strong> failing
                    </span>
                    <?php else: ?>
                    <span style="color:#856404;">Viewing <?php echo count($pending); ?> applicants across all schedules — select a schedule above to encode</span>
                    <?php endif; ?>
                </div>
                <?php if ($single_schedule_selected): ?>
                <div class="toolbar-right">
                    <button type="button" class="btn btn-sm btn-outline" onclick="markAllPresent()">✓ All Present</button>
                    <button type="button" class="btn btn-sm btn-outline" onclick="clearAll()">✕ Clear All</button>
                </div>
                <?php endif; ?>
            </div>

            <div class="table-wrapper">
                <table id="resultsTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Applicant</th>
                            <th>Reference #</th>
                            <th>Program</th>
                            <th>Exam Date</th>
                            <?php if ($single_schedule_selected): ?>
                            <th style="text-align:center;">Present</th>
                            <th style="text-align:center;">Score / 100</th>
                            <th style="text-align:center;">Result</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending as $i => $p):
                            $searchStr = strtolower($p['last_name'].' '.$p['first_name'].' '.($p['email']??'').' '.($p['reference_number']??''));
                        ?>
                        <tr id="row_<?php echo $p['id']; ?>" class="exam-pending-row"
                            data-name="<?php echo htmlspecialchars($searchStr); ?>"
                            data-program="<?php echo htmlspecialchars($p['first_choice'] ?? ''); ?>">
                            <td style="color:var(--gray); font-size:0.78rem;"><?php echo $i + 1; ?></td>
                            <td class="cell-clickable" onclick="window.location='admin-application-detail.php?id=<?php echo (int)$p['id']; ?>';">
                                <span class="name-main app-name-main">
                                    <a href="admin-application-detail.php?id=<?php echo (int)$p['id']; ?>" class="name-link" onclick="event.stopPropagation();">
                                        <?php echo htmlspecialchars($p['last_name'].', '.$p['first_name']); ?>
                                    </a>
                                </span>
                                <span class="name-sub"><?php echo htmlspecialchars($p['email']); ?></span>
                            </td>
                            <td class="ref-mono"><?php echo htmlspecialchars($p['reference_number'] ?? '—'); ?></td>
                            <td>
                                <?php echo htmlspecialchars($p['first_choice'] ?? '—'); ?>
                                <br><small style="color:var(--gray);"><?php echo htmlspecialchars($p['applicant_type'] ?? ''); ?></small>
                            </td>
                            <td style="font-size:0.82rem; white-space:nowrap;">
                                <?php if (!empty($p['exam_date'])): ?>
                                    <?php echo date('M d, Y', strtotime($p['exam_date'])); ?><br>
                                    <small style="color:var(--gray);"><?php echo date('g:i A', strtotime($p['exam_time'])); ?></small>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <?php if ($single_schedule_selected): ?>
                            <td style="text-align:center;">
                                <div class="attend-wrap" style="justify-content:center;">
                                    <input type="checkbox"
                                           class="attend-cb"
                                           name="present[<?php echo $p['id']; ?>]"
                                           value="1"
                                           data-id="<?php echo $p['id']; ?>"
                                           data-passing="75"
                                           onchange="togglePresent(this)">
                                </div>
                            </td>
                            <td style="text-align:center;">
                                <div class="score-wrap" style="justify-content:center;">
                                    <input type="number"
                                           class="score-input"
                                           name="score[<?php echo $p['id']; ?>]"
                                           id="score_<?php echo $p['id']; ?>"
                                           min="0" max="100"
                                           placeholder="—"
                                           disabled
                                           data-id="<?php echo $p['id']; ?>"
                                           data-passing="75"
                                           oninput="updateResult(this)">
                                    <span class="passing-ref">/ 100</span>
                                </div>
                            </td>
                            <td style="text-align:center;">
                                <span class="result-preview rp-noshow" id="result_<?php echo $p['id']; ?>">No Show</span>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($single_schedule_selected): ?>
            <div class="submit-row">
                <div class="submit-summary">
                    Ready to confirm results for
                    <strong id="summaryCount">0</strong> applicant(s) in this schedule.
                    Unprocessed rows will be marked as <strong>Exam No Show</strong>.
                </div>
                <button type="submit" class="btn btn-green" id="confirmExamBtn" disabled>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
                    Confirm & Process Results
                </button>
            </div>
            <?php else: ?>
            <div class="submit-blocked-notice">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                <span>Select a specific exam schedule above to enable result encoding. Submitting without a specific schedule selected is not allowed.</span>
            </div>
            <?php endif; ?>
        </form>
    </div>
    <?php endif; ?>

    <!-- PROCESSED HISTORY -->
    <?php if (!empty($processed)): ?>
    <div class="card">
        <div class="card-title">
            Results History
            <span><?php echo count($processed); ?> processed</span>
        </div>
        <div class="filter-bar" style="margin-bottom:1rem;">
            <label for="searchProcessed">Search:</label>
            <input type="search" id="searchProcessed" placeholder="Search by name or reference...">
            <label for="filterProcessedProgram">Program:</label>
            <select id="filterProcessedProgram">
                <option value="">All Programs</option>
                <?php foreach ($processed_programs as $p): ?>
                <option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="table-wrapper">
            <table id="processedTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Applicant</th>
                        <th>Reference #</th>
                        <th>Program</th>
                        <th style="text-align:center;">Score</th>
                        <th>Result</th>
                        <th>Processed By</th>
                        <th>Date Processed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($processed as $i => $pr):
                        $badge = match($pr['status']) {
                            'Exam Completed'             => ['b-completed',  'Passed'],
                            'Exam Failed'                => ['b-failed',     'Failed'],
                            'Exam No Show'               => ['b-noshow',     'No Show'],
                            'Awaiting Applicant Decision'=> ['b-tesda-offer','TESDA Offer Pending'],
                            'Application Withdrawn'      => ['b-noshow',     'Withdrawn'],
                            default                      => ['b-noshow',     $pr['status']]
                        };
                        $prSearch = strtolower(($pr['last_name'].' '.$pr['first_name']).' '.($pr['reference_number']??'').' '.($pr['first_choice']??''));
                    ?>
                    <tr class="processed-row"
                        data-name="<?php echo htmlspecialchars($prSearch); ?>"
                        data-program="<?php echo htmlspecialchars($pr['first_choice'] ?? ''); ?>"
                        onclick="window.location='admin-application-detail.php?id=<?php echo (int)$pr['id']; ?>'"
                        style="cursor:pointer;">
                        <td style="color:var(--gray); font-size:0.78rem;"><?php echo $i + 1; ?></td>
                        <td>
                            <span class="name-main">
                                <a href="admin-application-detail.php?id=<?php echo (int)$pr['id']; ?>" class="name-link" onclick="event.stopPropagation();">
                                    <?php echo htmlspecialchars($pr['last_name'].', '.$pr['first_name']); ?>
                                </a>
                            </span>
                        </td>
                        <td class="ref-mono"><?php echo htmlspecialchars($pr['reference_number'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($pr['first_choice'] ?? '—'); ?></td>
                        <td style="text-align:center; font-weight:600;">
                            <?php if ($pr['status'] === 'Exam No Show'): ?>
                                <span style="color:var(--gray);">—</span>
                            <?php else: ?>
                                <?php echo $pr['exam_score'] !== null ? $pr['exam_score'] . ' / 100' : '—'; ?>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?php echo $badge[0]; ?>"><?php echo $badge[1]; ?></span></td>
                        <td style="font-size:0.82rem;"><?php echo htmlspecialchars($pr['exam_marked_by'] ?? '—'); ?></td>
                        <td style="font-size:0.82rem; white-space:nowrap;">
                            <?php echo $pr['exam_marked_at'] ? date('M d, Y g:i A', strtotime($pr['exam_marked_at'])) : '—'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($cnt_passed > 0): ?>
        <div style="margin-top:1.25rem; text-align:right;">
            <a href="admin-interview-schedule.php" class="btn btn-outline">
                Schedule Interviews for Passers →
            </a>
        </div>
        <?php endif; ?>
        <div style="margin-top:1rem; font-size:0.82rem;">
            <a href="admin-reevaluate-exam-failed.php" style="color:var(--gray);">Re-evaluate wrongly failed applicants (one-time fix)</a>
        </div>
    </div>
    <?php endif; ?>

</main>

<script>
const totalPending       = <?php echo count($pending); ?>;
const scheduleSelected   = <?php echo $single_schedule_selected ? 'true' : 'false'; ?>;

function togglePresent(checkbox) {
    if (!scheduleSelected) return;
    const id      = checkbox.dataset.id;
    const scoreEl = document.getElementById('score_' + id);
    const row     = document.getElementById('row_' + id);

    if (checkbox.checked) {
        scoreEl.disabled = false;
        scoreEl.focus();
        row.classList.remove('row-absent');
        row.dataset.present = '1';
        updateResult(scoreEl);
    } else {
        scoreEl.disabled = true;
        scoreEl.value = '';
        scoreEl.classList.remove('pass', 'fail');
        row.classList.add('row-absent');
        row.dataset.present = '0';
        setResultBadge(id, 'noshow', 'No Show');
    }
    updateSummary();
    if (typeof filterExamResultsTable === 'function') filterExamResultsTable();
}

function updateResult(scoreInput) {
    const id      = scoreInput.dataset.id;
    const passing = parseInt(scoreInput.dataset.passing) || 75;
    const score   = parseInt(scoreInput.value);

    if (isNaN(score) || scoreInput.value === '') {
        scoreInput.classList.remove('pass', 'fail');
        setResultBadge(id, 'pending', 'Enter Score');
    } else if (score >= passing) {
        scoreInput.classList.add('pass');
        scoreInput.classList.remove('fail');
        setResultBadge(id, 'pass', '✓ Pass (' + score + ')');
    } else {
        scoreInput.classList.add('fail');
        scoreInput.classList.remove('pass');
        setResultBadge(id, 'fail', '✕ Fail (' + score + ')');
    }
    updateSummary();
}

function setResultBadge(id, type, text) {
    const badge = document.getElementById('result_' + id);
    if (!badge) return;
    badge.className = 'result-preview';
    if (type === 'pass')    badge.classList.add('rp-pass');
    if (type === 'fail')    badge.classList.add('rp-fail');
    if (type === 'noshow')  badge.classList.add('rp-noshow');
    if (type === 'pending') badge.classList.add('rp-pending');
    badge.textContent = text;
}

function updateSummary() {
    if (!scheduleSelected) return;
    const checkboxes = document.querySelectorAll('.attend-cb');
    let present = 0, passing = 0, failing = 0;

    checkboxes.forEach(cb => {
        if (!cb.checked) return;
        present++;
        const scoreEl = document.getElementById('score_' + cb.dataset.id);
        const score   = parseInt(scoreEl?.value ?? '');
        const passMark = parseInt(cb.dataset.passing) || 75;
        if (!isNaN(score)) {
            if (score >= passMark) passing++;
            else failing++;
        }
    });

    document.getElementById('presentCount').textContent = present;
    document.getElementById('absentCount').textContent  = totalPending - present;
    document.getElementById('passCount').textContent    = passing;
    document.getElementById('failCount').textContent    = failing;
    document.getElementById('summaryCount').textContent = totalPending;
}

function markAllPresent() {
    if (!scheduleSelected) return;
    document.querySelectorAll('.attend-cb').forEach(cb => {
        cb.checked = true;
        const scoreEl = document.getElementById('score_' + cb.dataset.id);
        if (scoreEl) scoreEl.disabled = false;
        const row = document.getElementById('row_' + cb.dataset.id);
        if (row) { row.classList.remove('row-absent'); row.dataset.present = '1'; }
    });
    updateSummary();
    if (typeof filterExamResultsTable === 'function') filterExamResultsTable();
}

function clearAll() {
    if (!scheduleSelected) return;
    document.querySelectorAll('.attend-cb').forEach(cb => {
        cb.checked = false;
        const scoreEl = document.getElementById('score_' + cb.dataset.id);
        if (scoreEl) { scoreEl.disabled = true; scoreEl.value = ''; scoreEl.classList.remove('pass','fail'); }
        const row = document.getElementById('row_' + cb.dataset.id);
        if (row) { row.classList.add('row-absent'); row.dataset.present = '0'; }
        setResultBadge(cb.dataset.id, 'noshow', 'No Show');
    });
    updateSummary();
    if (typeof filterExamResultsTable === 'function') filterExamResultsTable();
}

function filterExamResultsTable() {
    const searchEl      = document.getElementById('searchExamResults');
    const programEl     = document.getElementById('filterExamProgram');
    const presentOnlyEl = document.getElementById('filterPresentOnly');
    if (!searchEl) return;
    const search      = (searchEl.value || '').toLowerCase().trim();
    const program     = (programEl && programEl.value) ? programEl.value.trim() : '';
    const presentOnly = presentOnlyEl && presentOnlyEl.checked;
    document.querySelectorAll('.exam-pending-row').forEach(function(row) {
        const name       = (row.getAttribute('data-name') || '');
        const rowProgram = (row.getAttribute('data-program') || '');
        const isPresent  = row.querySelector('.attend-cb') && row.querySelector('.attend-cb').checked;
        const show = (!search || name.indexOf(search) !== -1) &&
                     (!program || rowProgram === program) &&
                     (!presentOnly || isPresent);
        row.style.display = show ? '' : 'none';
    });
}

document.addEventListener('DOMContentLoaded', () => {
    if (scheduleSelected) {
        document.querySelectorAll('tr[id^="row_"]').forEach(r => {
            r.classList.add('row-absent');
            r.dataset.present = '0';
        });
        updateSummary();
    }

    const searchEl = document.getElementById('searchExamResults');
    if (searchEl) {
        searchEl.oninput = filterExamResultsTable;
        const progEl = document.getElementById('filterExamProgram');
        if (progEl) progEl.onchange = filterExamResultsTable;
        const presEl = document.getElementById('filterPresentOnly');
        if (presEl) presEl.onchange = filterExamResultsTable;
    }

    const searchProc = document.getElementById('searchProcessed');
    if (searchProc) {
        searchProc.oninput = filterProcessedTable;
        const procProgEl = document.getElementById('filterProcessedProgram');
        if (procProgEl) procProgEl.onchange = filterProcessedTable;
    }

    // Enable Confirm button only after interaction (and only when schedule is selected)
    if (scheduleSelected) {
        const form = document.getElementById('resultsForm');
        const btn  = document.getElementById('confirmExamBtn');
        if (form && btn) {
            const enable = () => { btn.disabled = false; };
            form.addEventListener('change', function(e) {
                if (e.target && e.target.matches('input[name^="present"]')) enable();
            }, { passive: true });
            form.addEventListener('input', function(e) {
                if (e.target && e.target.matches('input[name^="score"]')) enable();
            }, { passive: true });
        }
    }
});

function filterProcessedTable() {
    const search  = (document.getElementById('searchProcessed')?.value || '').toLowerCase().trim();
    const program = (document.getElementById('filterProcessedProgram')?.value || '').trim();
    document.querySelectorAll('.processed-row').forEach(function(row) {
        const name       = (row.getAttribute('data-name') || '');
        const rowProgram = (row.getAttribute('data-program') || '');
        const show = (!search || name.indexOf(search) !== -1) && (!program || rowProgram === program);
        row.style.display = show ? '' : 'none';
    });
}

// Validate before submit
document.getElementById('resultsForm')?.addEventListener('submit', function(e) {
    // Hard block: should never reach here without scheduleSelected, but defensive check
    if (!scheduleSelected) {
        e.preventDefault();
        alert('Please select a specific exam schedule before encoding results.');
        return false;
    }

    const form = e.currentTarget;
    const presentCheckboxes = Array.from(form.querySelectorAll('input[name^="present"]'));
    const anyPresent = presentCheckboxes.some(cb => cb.checked);
    const anyScore   = Array.from(form.querySelectorAll('input[name^="score"]')).some(s => (s.value || '').trim() !== '');

    if (!anyPresent && !anyScore) {
        e.preventDefault();
        alert('No attendance has been recorded.\n\nPlease mark at least one applicant as Present before confirming.\n\nApplicants not marked as Present will be recorded as Exam No Show.');
        return false;
    }

    let missingScore = false;
    document.querySelectorAll('.attend-cb:checked').forEach(cb => {
        const score = document.getElementById('score_' + cb.dataset.id)?.value;
        if (score === '' || score === null) missingScore = true;
    });
    if (missingScore) {
        e.preventDefault();
        alert('Please enter a score for all applicants marked as present.');
        return false;
    }

    const totalRows    = presentCheckboxes.length;
    const presentCount = presentCheckboxes.filter(cb => cb.checked).length;
    const noShowCount  = totalRows - presentCount;
    if (noShowCount > 0) {
        const confirmed = confirm(
            `You are about to confirm exam results.\n\n` +
            `✓ Present: ${presentCount}\n` +
            `— Will be marked Exam No Show: ${noShowCount}\n\n` +
            `Applicants not checked as Present will be recorded as Exam No Show. Proceed?`
        );
        if (!confirmed) { e.preventDefault(); return false; }
    }

    return confirm('Confirm and process exam results? This cannot be undone.');
});
</script>

</body>
</html>