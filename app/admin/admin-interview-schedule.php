<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin - Interview Schedule
 * admin-interview-schedule.php
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

// ── FETCH CHED ELIGIBLE ────────────────────────────────────
$ched_eligible = [];
$cq_sql =
    "SELECT a.id, a.first_name, a.last_name, a.reference_number,
            COALESCE(a.assigned_program, a.first_choice) AS display_program,
            a.applicant_type, a.exam_score, u.email
     FROM applications a
     JOIN users u ON a.user_id = u.id
     WHERE a.status = 'Exam Completed'
       AND (a.program_category = 'CHED' OR a.program_category IS NULL)
       {$head_filter}
     ORDER BY a.last_name ASC, a.first_name ASC";
if (!empty($head_params)) {
    $cq = mysqli_prepare($conn, $cq_sql);
    $types = str_repeat('s', count($head_params));
    mysqli_stmt_bind_param($cq, $types, ...$head_params);
    mysqli_stmt_execute($cq);
    $cq_res = mysqli_stmt_get_result($cq);
    while ($row = mysqli_fetch_assoc($cq_res)) $ched_eligible[] = $row;
    mysqli_stmt_close($cq);
} else {
    $cq_res = mysqli_query($conn, $cq_sql);
    while ($row = mysqli_fetch_assoc($cq_res)) $ched_eligible[] = $row;
}

// ── FETCH TESDA ELIGIBLE ───────────────────────────────────
$tesda_eligible = [];
$tq_sql =
    "SELECT a.id, a.first_name, a.last_name, a.reference_number,
            COALESCE(a.assigned_program, a.first_choice) AS display_program,
            a.applicant_type, u.email
     FROM applications a
     JOIN users u ON a.user_id = u.id
     WHERE a.status = 'Documents Verified'
       AND a.program_category = 'TESDA'
       {$head_filter}
     ORDER BY a.last_name ASC, a.first_name ASC";
if (!empty($head_params)) {
    $tq = mysqli_prepare($conn, $tq_sql);
    $types = str_repeat('s', count($head_params));
    mysqli_stmt_bind_param($tq, $types, ...$head_params);
    mysqli_stmt_execute($tq);
    $tq_res = mysqli_stmt_get_result($tq);
    while ($row = mysqli_fetch_assoc($tq_res)) $tesda_eligible[] = $row;
    mysqli_stmt_close($tq);
} else {
    $tq_res = mysqli_query($conn, $tq_sql);
    while ($row = mysqli_fetch_assoc($tq_res)) $tesda_eligible[] = $row;
}

// ── FETCH ALREADY SCHEDULED ───────────────────────────────
$already = [];
$aq_sql =
    "SELECT a.id, a.first_name, a.last_name, a.reference_number,
            a.first_choice, a.status, a.program_category,
            a.interview_date, a.interview_time, a.interview_venue, a.interview_type
     FROM applications a
     WHERE a.status = 'Interview Scheduled'
       {$head_filter}
     ORDER BY a.interview_date ASC, a.interview_time ASC, a.last_name ASC";
if (!empty($head_params)) {
    $aq = mysqli_prepare($conn, $aq_sql);
    $types = str_repeat('s', count($head_params));
    mysqli_stmt_bind_param($aq, $types, ...$head_params);
    mysqli_stmt_execute($aq);
    $aq_res = mysqli_stmt_get_result($aq);
    while ($row = mysqli_fetch_assoc($aq_res)) $already[] = $row;
    mysqli_stmt_close($aq);
} else {
    $aq_res = mysqli_query($conn, $aq_sql);
    while ($row = mysqli_fetch_assoc($aq_res)) $already[] = $row;
}

$success = $error = '';
if (isset($_SESSION['admin_success'])) { $success = $_SESSION['admin_success']; unset($_SESSION['admin_success']); }
if (isset($_SESSION['admin_error']))   { $error   = $_SESSION['admin_error'];   unset($_SESSION['admin_error']);   }

$total_eligible = count($ched_eligible) + count($tesda_eligible);
$all_eligible   = array_merge($ched_eligible, $tesda_eligible);
$programs_for_filter = array_values(array_unique(array_filter(array_column($all_eligible, 'display_program'))));
sort($programs_for_filter);

$default_interview_notes = "DO's: Bring 2 valid IDs. Arrive 15 minutes early. Dress formally (no sleeveless, shorts, or slippers). Bring original documents for verification.\n\nDON'Ts: Do not bring electronic devices into the interview room. No eating or drinking during the interview.";

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/admin-styles.css">
    <title>Interview Schedule - Admin | BPC iEnroll</title>
    <style>
        .top-nav { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem; }
        .top-nav h1 { font-size:1.5rem; color:var(--bpc-green-dark); }
        .top-nav span { font-size:0.82rem; color:var(--text-gray); }
        .alert { margin-bottom:1.25rem; }

        .stat-row { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1.5rem; }
        .stat-card { background:var(--white); border-radius:10px; padding:1.25rem 1.5rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); border-top:4px solid var(--border-color); }
        .stat-card.blue   { border-color:#1565c0; }
        .stat-card.purple { border-color:#6a1b9a; }
        .stat-card.green  { border-color:var(--bpc-green); }
        .stat-num   { font-size:2rem; font-weight:700; line-height:1; }
        .stat-label { font-size:0.78rem; color:var(--text-gray); margin-top:0.35rem; }

        .card { background:var(--white); border-radius:10px; padding:1.75rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); margin-bottom:1.5rem; }
        .card-title { font-size:0.9rem; font-weight:700; color:var(--bpc-green-dark); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:1.5rem; padding-bottom:0.875rem; border-bottom:2px solid var(--bg-light); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; }
        .card-title span { font-size:0.8rem; font-weight:400; color:var(--text-gray); text-transform:none; letter-spacing:0; }

        .mode-toggle { display:flex; gap:0; border:2px solid var(--bpc-green); border-radius:8px; overflow:hidden; margin-bottom:1.75rem; width:fit-content; }
        .mode-btn { padding:0.75rem 2rem; font-size:0.875rem; font-weight:600; border:none; cursor:pointer; background:transparent; color:var(--bpc-green); transition:all 0.2s; }
        .mode-btn.active { background:var(--bpc-green); color:#fff; }
        .mode-btn:hover:not(.active) { background:#f0fdf4; }

        .section-header { display:flex; align-items:center; gap:0.75rem; padding:0.75rem 1rem; border-radius:8px; margin-bottom:1rem; font-size:0.875rem; font-weight:600; }
        .section-ched  { background:#e3f2fd; border:1px solid #90caf9; color:#1565c0; }
        .section-tesda { background:#f3e5f5; border:1px solid #ce93d8; color:#6a1b9a; }
        .section-count { display:inline-block; padding:0.15rem 0.6rem; border-radius:10px; font-size:0.75rem; font-weight:700; margin-left:0.5rem; }
        .section-ched  .section-count { background:#1565c0; color:#fff; }
        .section-tesda .section-count { background:#6a1b9a; color:#fff; }

        .form-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1.5rem; }
        .form-grid .span-2 { grid-column:span 2; }
        .form-grid .span-3 { grid-column:span 3; }
        .form-group { display:flex; flex-direction:column; gap:0.35rem; }
        .form-group label { font-size:0.82rem; font-weight:600; color:var(--text-dark); }
        .form-group input, .form-group select, .form-group textarea { padding:0.65rem 0.875rem; border:2px solid var(--border-color); border-radius:6px; font-size:0.875rem; font-family:inherit; background:#fff; transition:border-color 0.2s; }
        .form-group input:focus, .form-group select:focus { outline:none; border-color:var(--bpc-green); box-shadow:0 0 0 3px rgba(0,100,0,0.1); }
        .form-group textarea { resize:vertical; min-height:80px; }

        .filter-row { display:flex; align-items:center; gap:0.75rem; margin-bottom:0.75rem; flex-wrap:wrap; }
        .filter-row input[type="search"], .filter-row select { padding:0.5rem 0.75rem; border:2px solid var(--border-color); border-radius:6px; font-size:0.875rem; }
        .filter-row input[type="search"] { min-width:200px; }
        .filter-row label { font-size:0.8rem; font-weight:600; color:var(--text-gray); }
        .pagination-bar { display:flex; align-items:center; justify-content:space-between; margin-top:0.75rem; flex-wrap:wrap; gap:0.5rem; font-size:0.82rem; color:var(--text-gray); }
        .pagination-bar button { padding:0.35rem 0.75rem; border:1px solid var(--border-color); border-radius:6px; background:var(--white); cursor:pointer; font-size:0.82rem; }
        .pagination-bar button:hover:not(:disabled) { border-color:var(--bpc-green); color:var(--bpc-green); }
        .pagination-bar button:disabled { opacity:0.5; cursor:not-allowed; }
        .applicant-card.hidden-by-filter { display:none !important; }
        .applicant-card.hidden-by-page { display:none !important; }
        .ind-row.hidden-by-filter { display:none !important; }
        .ind-row.hidden-by-page { display:none !important; }

        .applicant-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(250px,1fr)); gap:0.75rem; max-height:420px; overflow-y:auto; padding:0.25rem; }
        .applicant-card { border:2px solid var(--border-color); border-radius:8px; padding:0.875rem 1rem; cursor:pointer; transition:all 0.15s; background:#fff; display:flex; align-items:flex-start; gap:0.75rem; }
        .applicant-card:hover { border-color:var(--bpc-green); background:#f8fffe; }
        .applicant-card.selected { border-color:var(--bpc-green); background:#f0fdf4; }
        .applicant-card.ched-card:hover   { border-color:#1565c0; background:#f0f7ff; }
        .applicant-card.ched-card.selected  { border-color:#1565c0; background:#e3f2fd; }
        .applicant-card.tesda-card:hover  { border-color:#6a1b9a; background:#faf0ff; }
        .applicant-card.tesda-card.selected { border-color:#6a1b9a; background:#f3e5f5; }
        .applicant-card input[type="checkbox"] { width:18px; height:18px; cursor:pointer; accent-color:var(--bpc-green); flex-shrink:0; margin-top:2px; }
        .app-info { flex:1; min-width:0; }
        .app-name { font-size:0.875rem; font-weight:700; color:var(--text-dark); }
        .app-ref  { font-size:0.75rem; color:var(--text-gray); font-family:monospace; }
        .app-tags { margin-top:0.3rem; display:flex; gap:0.3rem; flex-wrap:wrap; }
        .tag { display:inline-block; font-size:0.7rem; font-weight:700; padding:0.15rem 0.5rem; border-radius:10px; }
        .tag-prog  { background:#e8f5e9; color:var(--bpc-green-dark); }
        .tag-ched  { background:#e3f2fd; color:#1565c0; }
        .tag-tesda { background:#f3e5f5; color:#6a1b9a; }
        .tag-score { background:#fff3cd; color:#856404; }
        .tag-type  { background:#e3f2fd; color:#0d47a1; }

        .checklist-toolbar { display:flex; align-items:center; justify-content:space-between; background:var(--bg-light); border-radius:8px; padding:0.625rem 1rem; margin-bottom:0.75rem; flex-wrap:wrap; gap:0.5rem; }
        .selected-badge { display:inline-flex; align-items:center; gap:0.375rem; background:var(--bpc-green); color:#fff; border-radius:20px; padding:0.25rem 0.75rem; font-size:0.8rem; font-weight:700; }
        .empty-check { padding:2rem; text-align:center; color:var(--text-gray); font-size:0.875rem; border:2px dashed var(--border-color); border-radius:8px; }

        /* INDIVIDUAL TABLE */
        .ind-table-wrapper { overflow-x:auto; }
        .ind-table { width:100%; border-collapse:collapse; font-size:0.875rem; }
        .ind-table th { position:sticky; top:0; z-index:2; background:var(--bg-light); color:var(--text-gray); font-size:0.72rem; text-transform:uppercase; letter-spacing:0.5px; padding:0.625rem 0.875rem; text-align:left; border-bottom:2px solid var(--border-color); white-space:nowrap; box-shadow:0 2px 0 0 var(--border-color); }
        .ind-table td { padding:0.625rem 0.875rem; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
        .ind-table tr:last-child td { border-bottom:none; }
        .ind-table tr:hover td { background:#fafafa; }
        .ind-input { padding:0.45rem 0.625rem; border:2px solid var(--border-color); border-radius:6px; font-size:0.82rem; font-family:inherit; background:#fff; transition:border-color 0.2s; }
        .ind-input:focus { outline:none; border-color:var(--bpc-green); }
        .ind-input.date-inp  { width:145px; }
        .ind-input.time-inp  { width:115px; }
        .ind-input.venue-inp { width:200px; }
        .ind-input.type-sel  { width:120px; }
        .name-main { font-weight:700; display:block; font-size:0.875rem; }
        .name-sub  { font-size:0.75rem; color:var(--text-gray); }
        .ref-mono  { font-family:monospace; font-size:0.78rem; color:var(--text-gray); }

        /* ALREADY SCHEDULED TABLE — tighter columns */
        .sched-table { width:100%; border-collapse:collapse; font-size:0.875rem; }
        .sched-table th { position:sticky; top:0; z-index:2; background:var(--bg-light); color:var(--text-gray); font-size:0.72rem; text-transform:uppercase; letter-spacing:0.5px; padding:0.625rem 0.875rem; text-align:left; border-bottom:2px solid var(--border-color); white-space:nowrap; box-shadow:0 2px 0 0 var(--border-color); }
        .sched-table td { padding:0.625rem 0.875rem; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
        .sched-table tr:last-child td { border-bottom:none; }
        .sched-table tr:hover td { background:#fafafa; }
        /* Venue column: truncate long text */
        .venue-cell { max-width:160px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-size:0.82rem; }

        .submit-row { display:flex; align-items:center; justify-content:space-between; margin-top:1.5rem; padding-top:1.25rem; border-top:2px solid var(--bg-light); flex-wrap:wrap; gap:1rem; }
        .submit-info { font-size:0.82rem; color:var(--text-gray); flex:1; }

        .btn { display:inline-flex; align-items:center; gap:0.5rem; padding:0.75rem 1.5rem; border:none; border-radius:6px; font-size:0.875rem; font-weight:600; cursor:pointer; transition:all 0.2s; text-decoration:none; }
        .btn-green  { background:var(--bpc-green); color:#fff; }
        .btn-green:hover { background:var(--bpc-green-dark); }
        .btn-green:disabled { background:#9ca3af; cursor:not-allowed; }
        .btn-sm { padding:0.35rem 0.75rem; font-size:0.75rem; }
        .btn-outline { background:transparent; border:2px solid var(--bpc-green); color:var(--bpc-green); }
        .btn-outline:hover { background:var(--bpc-green); color:#fff; }

        .badge { display:inline-block; padding:0.2rem 0.6rem; border-radius:10px; font-size:0.72rem; font-weight:700; white-space:nowrap; }
        .b-ched  { background:#e3f2fd; color:#1565c0; }
        .b-tesda { background:#f3e5f5; color:#6a1b9a; }
        .b-batch { background:#e8f5e9; color:var(--bpc-green-dark); }
        .b-ind   { background:#fff3cd; color:#856404; }
        .empty-state { text-align:center; padding:3rem; color:var(--text-gray); }
        .warning-box { background:#fff3cd; border:1px solid #ffc107; border-radius:6px; padding:0.75rem 1rem; font-size:0.82rem; color:#856404; margin-bottom:1rem; }

        @media(max-width:960px) { .sidebar { display:none; } .stat-row { grid-template-columns:1fr 1fr; } .form-grid { grid-template-columns:1fr 1fr; } .form-grid .span-3 { grid-column:span 2; } }
    </style>
</head>
<body>

<?php include '../shared/admin-sidebar.php'; ?>

<main class="main">
    <div class="top-nav">
        <h1>Interview Scheduling</h1>
        <span>Logged in as <strong><?php echo htmlspecialchars($_SESSION['admin_name']); ?></strong></span>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="stat-row">
        <div class="stat-card blue">
            <div class="stat-num" style="color:#1565c0;"><?php echo count($ched_eligible); ?></div>
            <div class="stat-label">CHED Passers Ready</div>
        </div>
        <div class="stat-card purple">
            <div class="stat-num" style="color:#6a1b9a;"><?php echo count($tesda_eligible); ?></div>
            <div class="stat-label">TESDA Applicants Ready</div>
        </div>
        <div class="stat-card green">
            <div class="stat-num" style="color:var(--bpc-green);"><?php echo count($already); ?></div>
            <div class="stat-label">Interviews Scheduled</div>
        </div>
    </div>

    <div style="margin-bottom:1.5rem;">
        <div style="font-size:0.82rem;color:var(--text-gray);margin-bottom:0.75rem;font-weight:600;">SCHEDULING MODE</div>
        <div class="mode-toggle">
            <button type="button" class="mode-btn active" id="modeBatchBtn" onclick="setMode('batch')">Batch — One slot, multiple applicants</button>
            <button type="button" class="mode-btn" id="modeIndBtn" onclick="setMode('individual')">Individual — Per-applicant date/time</button>
        </div>
    </div>

    <!-- BATCH MODE -->
    <div id="batchMode">
        <?php if ($total_eligible === 0): ?>
        <div class="card">
            <div class="card-title">Batch Interview Scheduling</div>
            <div class="warning-box">
                ℹ No applicants are eligible for interview scheduling yet.<br>
                • CHED applicants must pass the exam first (status: <strong>Exam Completed</strong>)<br>
                • TESDA applicants need <strong>Documents Verified</strong> status
            </div>
        </div>
        <?php else: ?>
        <form method="POST" action="admin-set-interview.php" id="batchForm">
            <input type="hidden" name="mode" value="batch">
            <div class="card">
                <div class="card-title">Set Interview Slot <span><?php echo $total_eligible; ?> applicant(s) eligible</span></div>
                <div class="form-grid">
                    <div class="form-group"><label>Interview Date *</label><input type="date" name="batch_date" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required></div>
                    <div class="form-group"><label>Start Time *</label><input type="time" name="batch_time" required></div>
                    <div class="form-group"><label>Interview Type</label><select name="batch_type"><option value="Batch">Batch (Group)</option><option value="Individual">Individual (One-on-one)</option></select></div>
                    <div class="form-group span-3"><label>Venue / Location *</label><input type="text" name="batch_venue" placeholder="e.g. BPC Conference Room, Admin Building" required></div>
                    <div class="form-group span-3"><label>Notes for Applicants (optional)</label><textarea name="batch_notes" rows="4"><?php echo htmlspecialchars($default_interview_notes); ?></textarea></div>
                </div>
            </div>

            <div class="card">
                <div class="card-title">
                    Select Applicants for This Slot
                    <span class="selected-badge"><span id="batchCountText">0 selected</span></span>
                </div>
                <div class="filter-row">
                    <label for="batchSearch">Search:</label>
                    <input type="search" id="batchSearch" placeholder="Name or reference #" oninput="applyBatchFilters()">
                    <label for="batchFilterProgram">Program:</label>
                    <select id="batchFilterProgram" onchange="applyBatchFilters()">
                        <option value="">All programs</option>
                        <?php foreach ($programs_for_filter as $p): ?><option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option><?php endforeach; ?>
                    </select>
                    <label for="batchFilterType">Type:</label>
                    <select id="batchFilterType" onchange="applyBatchFilters()">
                        <option value="">All</option><option value="Freshmen">Freshmen</option><option value="Transferee">Transferee</option>
                    </select>
                </div>
                <div class="pagination-bar" id="batchPaginationBar" style="display:none;">
                    <span id="batchPaginationText"></span>
                    <span>
                        <button type="button" id="batchPrevPage" onclick="batchGoToPage(batchCurrentPage - 1)">← Previous</button>
                        <button type="button" id="batchNextPage" onclick="batchGoToPage(batchCurrentPage + 1)" style="margin-left:0.35rem;">Next →</button>
                    </span>
                </div>

                <?php if (!empty($ched_eligible)): ?>
                <div class="section-header section-ched">CHED Applicants — Exam Passers <span class="section-count"><?php echo count($ched_eligible); ?></span></div>
                <div class="checklist-toolbar">
                    <span style="font-size:0.82rem;color:var(--text-gray);"><?php echo count($ched_eligible); ?> CHED passer(s)</span>
                    <div style="display:flex;gap:0.5rem;">
                        <button type="button" class="btn btn-sm btn-outline" onclick="selectGroup('ched')">✓ All CHED</button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="selectAllBatchVisible()">✓ This page</button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="deselectGroup('ched')">✕ Clear</button>
                    </div>
                </div>
                <div class="applicant-grid batch-app-grid" id="batchChedGrid" style="margin-bottom:1.5rem;">
                    <?php foreach ($ched_eligible as $a): ?>
                    <label class="applicant-card ched-card batch-app-card" id="bcard_<?php echo $a['id']; ?>"
                           data-name="<?php echo htmlspecialchars(strtolower($a['last_name'].' '.$a['first_name'])); ?>"
                           data-ref="<?php echo htmlspecialchars(strtolower($a['reference_number'] ?? '')); ?>"
                           data-program="<?php echo htmlspecialchars($a['first_choice'] ?? ''); ?>"
                           data-type="<?php echo htmlspecialchars($a['applicant_type'] ?? ''); ?>"
                           data-track="ched">
                        <input type="checkbox" name="applicant_ids[]" value="<?php echo $a['id']; ?>" class="batch-cb ched-cb" onchange="batchToggleCard(this)">
                        <div class="app-info">
                            <div class="app-name"><?php echo htmlspecialchars($a['last_name'].', '.$a['first_name']); ?></div>
                            <div class="app-ref"><?php echo htmlspecialchars($a['reference_number'] ?? '—'); ?></div>
                            <div class="app-tags">
                                <span class="tag tag-prog"><?php echo htmlspecialchars($a['first_choice'] ?? '—'); ?></span>
                                <span class="tag tag-ched">CHED</span>
                                <?php if ($a['exam_score'] !== null): ?><span class="tag tag-score">Score: <?php echo $a['exam_score']; ?></span><?php endif; ?>
                            </div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($tesda_eligible)): ?>
                <div class="section-header section-tesda">TESDA Applicants — Direct to Interview <span class="section-count"><?php echo count($tesda_eligible); ?></span></div>
                <div class="checklist-toolbar">
                    <span style="font-size:0.82rem;color:var(--text-gray);"><?php echo count($tesda_eligible); ?> TESDA applicant(s)</span>
                    <div style="display:flex;gap:0.5rem;">
                        <button type="button" class="btn btn-sm btn-outline" onclick="selectGroup('tesda')">✓ All TESDA</button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="deselectGroup('tesda')">✕ Clear</button>
                    </div>
                </div>
                <div class="applicant-grid batch-app-grid" id="batchTesdaGrid">
                    <?php foreach ($tesda_eligible as $a): ?>
                    <label class="applicant-card tesda-card batch-app-card" id="bcard_<?php echo $a['id']; ?>"
                           data-name="<?php echo htmlspecialchars(strtolower($a['last_name'].' '.$a['first_name'])); ?>"
                           data-ref="<?php echo htmlspecialchars(strtolower($a['reference_number'] ?? '')); ?>"
                           data-program="<?php echo htmlspecialchars($a['first_choice'] ?? ''); ?>"
                           data-type="<?php echo htmlspecialchars($a['applicant_type'] ?? ''); ?>"
                           data-track="tesda">
                        <input type="checkbox" name="applicant_ids[]" value="<?php echo $a['id']; ?>" class="batch-cb tesda-cb" onchange="batchToggleCard(this)">
                        <div class="app-info">
                            <div class="app-name"><?php echo htmlspecialchars($a['last_name'].', '.$a['first_name']); ?></div>
                            <div class="app-ref"><?php echo htmlspecialchars($a['reference_number'] ?? '—'); ?></div>
                            <div class="app-tags">
                                <span class="tag tag-prog"><?php echo htmlspecialchars($a['first_choice'] ?? '—'); ?></span>
                                <span class="tag tag-tesda">TESDA</span>
                                <span class="tag tag-type"><?php echo htmlspecialchars($a['applicant_type'] ?? ''); ?></span>
                            </div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="submit-row">
                    <div class="submit-info">Selected applicants will be moved to <strong>Interview Scheduled</strong> status.</div>
                    <button type="submit" class="btn btn-green" id="batchSubmitBtn" disabled>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                        Schedule Selected Applicants
                    </button>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <!-- INDIVIDUAL MODE -->
    <div id="individualMode" style="display:none;">
        <?php if ($total_eligible === 0): ?>
        <div class="card"><div class="card-title">Individual Interview Scheduling</div><div class="warning-box">ℹ No applicants are eligible for interview scheduling yet.</div></div>
        <?php else: ?>
        <form method="POST" action="admin-set-interview.php" id="individualForm">
            <input type="hidden" name="mode" value="individual">
            <div class="card">
                <div class="card-title">Set Individual Interview Schedules <span><?php echo $total_eligible; ?> applicant(s)</span></div>
                <div style="background:#fffbeb;border:1px solid var(--gold);border-radius:6px;padding:0.75rem 1rem;margin-bottom:1.25rem;font-size:0.82rem;color:#856404;">
                    Fill in date/time/venue for each applicant. Leave blank to skip. Use <strong>Apply same slot to visible</strong> to copy the first row's slot to all filtered rows.
                </div>
                <div class="filter-row">
                    <label for="indSearch">Search:</label>
                    <input type="search" id="indSearch" placeholder="Name or reference #" oninput="applyIndFilters()">
                    <label for="indFilterProgram">Program:</label>
                    <select id="indFilterProgram" onchange="applyIndFilters()">
                        <option value="">All programs</option>
                        <?php foreach ($programs_for_filter as $p): ?><option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option><?php endforeach; ?>
                    </select>
                    <label for="indFilterType">Type:</label>
                    <select id="indFilterType" onchange="applyIndFilters()"><option value="">All</option><option value="Freshmen">Freshmen</option><option value="Transferee">Transferee</option></select>
                    <label for="indFilterTrack">Track:</label>
                    <select id="indFilterTrack" onchange="applyIndFilters()"><option value="">All</option><option value="CHED">CHED</option><option value="TESDA">TESDA</option></select>
                    <button type="button" class="btn btn-sm btn-outline" onclick="indFillSameSlot()">Apply same slot to visible</button>
                </div>
                <div class="pagination-bar" id="indPaginationBar" style="display:none;">
                    <span id="indPaginationText"></span>
                    <span>
                        <button type="button" id="indPrevPage" onclick="indGoToPage(indCurrentPage - 1)">← Previous</button>
                        <button type="button" id="indNextPage" onclick="indGoToPage(indCurrentPage + 1)" style="margin-left:0.35rem;">Next →</button>
                    </span>
                </div>

                <?php if (!empty($ched_eligible)): ?>
                <div class="section-header section-ched" style="margin-bottom:0.75rem;">CHED Applicants — Exam Passers <span class="section-count"><?php echo count($ched_eligible); ?></span></div>
                <div class="ind-table-wrapper" style="margin-bottom:1.75rem;">
                    <table class="ind-table">
                        <thead><tr><th>#</th><th>Applicant</th><th>Program</th><th>Score</th><th>Date *</th><th>Time *</th><th>Venue *</th><th>Type</th></tr></thead>
                        <tbody>
                            <?php foreach ($ched_eligible as $i => $a): ?>
                            <tr class="ind-row" data-name="<?php echo htmlspecialchars(strtolower($a['last_name'].' '.$a['first_name'])); ?>" data-ref="<?php echo htmlspecialchars(strtolower($a['reference_number'] ?? '')); ?>" data-program="<?php echo htmlspecialchars($a['first_choice'] ?? ''); ?>" data-type="<?php echo htmlspecialchars($a['applicant_type'] ?? ''); ?>" data-track="CHED">
                                <td style="color:var(--gray);font-size:0.78rem;"><?php echo $i + 1; ?></td>
                                <td><span class="name-main"><?php echo htmlspecialchars($a['last_name'].', '.$a['first_name']); ?></span><span class="name-sub"><?php echo htmlspecialchars($a['reference_number'] ?? '—'); ?></span></td>
                                <td><?php echo htmlspecialchars($a['first_choice'] ?? '—'); ?></td>
                                <td style="font-weight:700;color:var(--bpc-green);"><?php echo $a['exam_score'] ?? '—'; ?></td>
                                <td><input type="date" name="ind_date[<?php echo $a['id']; ?>]" class="ind-input date-inp" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"></td>
                                <td><input type="time" name="ind_time[<?php echo $a['id']; ?>]" class="ind-input time-inp"></td>
                                <td><input type="text" name="ind_venue[<?php echo $a['id']; ?>]" class="ind-input venue-inp" placeholder="Venue / Room"></td>
                                <td><select name="ind_type[<?php echo $a['id']; ?>]" class="ind-input type-sel"><option value="Individual">Individual</option><option value="Batch">Batch</option></select></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <?php if (!empty($tesda_eligible)): ?>
                <div class="section-header section-tesda" style="margin-bottom:0.75rem;">TESDA Applicants — Direct to Interview <span class="section-count"><?php echo count($tesda_eligible); ?></span></div>
                <div class="ind-table-wrapper">
                    <table class="ind-table">
                        <thead><tr><th>#</th><th>Applicant</th><th>Program</th><th>Type</th><th>Date *</th><th>Time *</th><th>Venue *</th><th>Interview Type</th></tr></thead>
                        <tbody>
                            <?php foreach ($tesda_eligible as $i => $a): ?>
                            <tr class="ind-row" data-name="<?php echo htmlspecialchars(strtolower($a['last_name'].' '.$a['first_name'])); ?>" data-ref="<?php echo htmlspecialchars(strtolower($a['reference_number'] ?? '')); ?>" data-program="<?php echo htmlspecialchars($a['first_choice'] ?? ''); ?>" data-type="<?php echo htmlspecialchars($a['applicant_type'] ?? ''); ?>" data-track="TESDA">
                                <td style="color:var(--gray);font-size:0.78rem;"><?php echo $i + 1; ?></td>
                                <td><span class="name-main"><?php echo htmlspecialchars($a['last_name'].', '.$a['first_name']); ?></span><span class="name-sub"><?php echo htmlspecialchars($a['reference_number'] ?? '—'); ?></span></td>
                                <td><?php echo htmlspecialchars($a['first_choice'] ?? '—'); ?></td>
                                <td><span class="tag tag-tesda">TESDA</span></td>
                                <td><input type="date" name="ind_date[<?php echo $a['id']; ?>]" class="ind-input date-inp" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"></td>
                                <td><input type="time" name="ind_time[<?php echo $a['id']; ?>]" class="ind-input time-inp"></td>
                                <td><input type="text" name="ind_venue[<?php echo $a['id']; ?>]" class="ind-input venue-inp" placeholder="Venue / Room"></td>
                                <td><select name="ind_type[<?php echo $a['id']; ?>]" class="ind-input type-sel"><option value="Individual">Individual</option><option value="Batch">Batch</option></select></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <div class="submit-row">
                    <div class="submit-info">Only rows with a <strong>date, time, and venue</strong> filled in will be scheduled.</div>
                    <button type="submit" class="btn btn-green" onclick="return confirmIndividual()">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
                        Save Individual Schedules
                    </button>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <!-- ALREADY SCHEDULED -->
    <div class="card">
        <div class="card-title">
            Scheduled for Interview
            <span><?php echo count($already); ?> total</span>
        </div>

        <?php if (empty($already)): ?>
            <div class="empty-state">No interviews scheduled yet.</div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="sched-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Applicant</th>
                            <th>Reference #</th>
                            <th>Program</th>
                            <th>Track</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Venue</th>
                            <th>Type</th>
                            <th>Reschedule</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($already as $i => $a):
                            $track_badge = ($a['program_category'] === 'TESDA') ? ['b-tesda','TESDA'] : ['b-ched','CHED'];
                            $type_badge  = ($a['interview_type'] === 'Individual') ? ['b-ind','Individual'] : ['b-batch','Batch'];
                        ?>
                        <tr>
                            <td style="color:var(--gray);font-size:0.78rem;"><?php echo $i + 1; ?></td>
                            <td>
                                <span style="font-weight:700;display:block;font-size:0.875rem;">
                                    <?php echo htmlspecialchars($a['last_name'].', '.$a['first_name']); ?>
                                </span>
                            </td>
                            <td class="ref-mono"><?php echo htmlspecialchars($a['reference_number'] ?? '—'); ?></td>
                            <td style="font-size:0.82rem;"><?php echo htmlspecialchars($a['first_choice'] ?? '—'); ?></td>
                            <td><span class="badge <?php echo $track_badge[0]; ?>"><?php echo $track_badge[1]; ?></span></td>
                            <td style="font-size:0.82rem;white-space:nowrap;">
                                <?php echo $a['interview_date'] ? date('M d, Y', strtotime($a['interview_date'])) : '—'; ?>
                            </td>
                            <td style="font-size:0.82rem;white-space:nowrap;">
                                <?php echo $a['interview_time'] ? date('g:i A', strtotime($a['interview_time'])) : '—'; ?>
                            </td>
                            <td class="venue-cell" title="<?php echo htmlspecialchars($a['interview_venue'] ?? ''); ?>">
                                <?php echo htmlspecialchars($a['interview_venue'] ?? '—'); ?>
                            </td>
                            <td><span class="badge <?php echo $type_badge[0]; ?>"><?php echo $type_badge[1]; ?></span></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline"
                                    onclick="openReschedule(
                                        <?php echo $a['id']; ?>,
                                        '<?php echo htmlspecialchars(addslashes($a['last_name'].', '.$a['first_name'])); ?>',
                                        '<?php echo $a['interview_date'] ?? ''; ?>',
                                        '<?php echo $a['interview_time'] ?? ''; ?>',
                                        '<?php echo htmlspecialchars(addslashes($a['interview_venue'] ?? '')); ?>',
                                        '<?php echo htmlspecialchars(addslashes($a['interview_type'] ?? 'Individual')); ?>'
                                    )">
                                    Reschedule
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</main>

<!-- RESCHEDULE MODAL -->
<div id="rescheduleModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:12px;padding:2rem;width:100%;max-width:480px;box-shadow:0 8px 32px rgba(0,0,0,0.2);animation:modalIn 0.2s ease;">
        <h3 style="font-size:1.1rem;color:var(--bpc-green-dark);margin-bottom:0.25rem;">Reschedule Interview</h3>
        <p id="rescheduleApplicantName" style="font-size:0.85rem;color:var(--text-gray);margin-bottom:1.5rem;"></p>
        <form method="POST" action="admin-set-interview.php">
            <input type="hidden" name="mode" value="reschedule">
            <input type="hidden" name="applicant_id" id="rescheduleId">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                <div class="form-group"><label>New Date *</label><input type="date" name="reschedule_date" id="rescheduleDate" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required></div>
                <div class="form-group"><label>New Time *</label><input type="time" name="reschedule_time" id="rescheduleTime" required></div>
                <div class="form-group" style="grid-column:span 2;"><label>Venue *</label><input type="text" name="reschedule_venue" id="rescheduleVenue" placeholder="e.g. BPC Conference Room" required></div>
                <div class="form-group" style="grid-column:span 2;"><label>Interview Type</label><select name="reschedule_type" id="rescheduleType"><option value="Individual">Individual</option><option value="Batch">Batch</option></select></div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:0.75rem;padding-top:1rem;border-top:1px solid var(--border-color);">
                <button type="button" onclick="closeReschedule()" style="padding:0.6rem 1.25rem;background:#6c757d;color:white;border:none;border-radius:6px;font-weight:600;cursor:pointer;">Cancel</button>
                <button type="submit" style="padding:0.6rem 1.25rem;background:var(--bpc-green);color:white;border:none;border-radius:6px;font-weight:600;cursor:pointer;">Save Reschedule</button>
            </div>
        </form>
    </div>
</div>

<style>
@keyframes modalIn { from { opacity:0; transform:translateY(-12px); } to { opacity:1; transform:translateY(0); } }
</style>

<script>
function setMode(mode) {
    const isBatch = mode === 'batch';
    document.getElementById('batchMode').style.display      = isBatch ? 'block' : 'none';
    document.getElementById('individualMode').style.display = isBatch ? 'none'  : 'block';
    document.getElementById('modeBatchBtn').classList.toggle('active', isBatch);
    document.getElementById('modeIndBtn').classList.toggle('active', !isBatch);
}

const BATCH_PAGE_SIZE = 30;
let batchCurrentPage = 1;
let batchFilteredCards = [];

function applyBatchFilters() {
    const search  = (document.getElementById('batchSearch')?.value || '').trim().toLowerCase();
    const program = (document.getElementById('batchFilterProgram')?.value || '').toLowerCase();
    const type    = (document.getElementById('batchFilterType')?.value || '').toLowerCase();
    const cards   = document.querySelectorAll('.batch-app-card');
    cards.forEach(card => {
        const name = (card.getAttribute('data-name') || '').toLowerCase();
        const ref  = (card.getAttribute('data-ref')  || '').toLowerCase();
        const prog = (card.getAttribute('data-program') || '').toLowerCase();
        const typ  = (card.getAttribute('data-type')   || '').toLowerCase();
        const show = (!search || name.includes(search) || ref.includes(search)) && (!program || prog === program) && (!type || typ === type);
        card.classList.toggle('hidden-by-filter', !show);
    });
    batchFilteredCards = Array.from(cards).filter(c => !c.classList.contains('hidden-by-filter'));
    batchCurrentPage = 1;
    showBatchPage();
}

function showBatchPage() {
    const start = (batchCurrentPage - 1) * BATCH_PAGE_SIZE;
    const end   = start + BATCH_PAGE_SIZE;
    batchFilteredCards.forEach((card, i) => { card.classList.toggle('hidden-by-page', i < start || i >= end); });
    const total = batchFilteredCards.length;
    const bar = document.getElementById('batchPaginationBar');
    if (!bar) return;
    if (total === 0) { bar.style.display = 'none'; return; }
    bar.style.display = 'flex';
    document.getElementById('batchPaginationText').textContent = 'Showing ' + (start+1) + '–' + Math.min(end,total) + ' of ' + total;
    document.getElementById('batchPrevPage').disabled = batchCurrentPage <= 1;
    document.getElementById('batchNextPage').disabled = batchCurrentPage >= Math.ceil(total / BATCH_PAGE_SIZE);
}

function batchGoToPage(n) {
    const totalPages = Math.ceil(batchFilteredCards.length / BATCH_PAGE_SIZE);
    if (n < 1 || n > totalPages) return;
    batchCurrentPage = n;
    showBatchPage();
}

function selectAllBatchVisible() {
    document.querySelectorAll('.batch-app-card:not(.hidden-by-filter):not(.hidden-by-page)').forEach(card => {
        const cb = card.querySelector('.batch-cb');
        if (cb) { cb.checked = true; card.classList.add('selected'); }
    });
    updateBatchCount();
}

function batchToggleCard(cb) {
    cb.closest('.applicant-card').classList.toggle('selected', cb.checked);
    updateBatchCount();
}

function updateBatchCount() {
    const count = document.querySelectorAll('.batch-cb:checked').length;
    document.getElementById('batchCountText').textContent = count + ' selected';
    document.getElementById('batchSubmitBtn').disabled = count === 0;
}

function selectGroup(group) {
    document.querySelectorAll('.' + group + '-cb').forEach(cb => { cb.checked = true; cb.closest('.applicant-card').classList.add('selected'); });
    updateBatchCount();
}

function deselectGroup(group) {
    document.querySelectorAll('.' + group + '-cb').forEach(cb => { cb.checked = false; cb.closest('.applicant-card').classList.remove('selected'); });
    updateBatchCount();
}

document.getElementById('batchForm')?.addEventListener('submit', function(e) {
    const count = document.querySelectorAll('.batch-cb:checked').length;
    if (count === 0) { e.preventDefault(); alert('Please select at least one applicant.'); return false; }
    return confirm(`Schedule interview for ${count} applicant(s)?`);
});

const IND_PAGE_SIZE = 25;
let indCurrentPage = 1;
let indFilteredRows = [];

function applyIndFilters() {
    const search  = (document.getElementById('indSearch')?.value || '').trim().toLowerCase();
    const program = (document.getElementById('indFilterProgram')?.value || '').toLowerCase();
    const type    = (document.getElementById('indFilterType')?.value || '').toLowerCase();
    const track   = (document.getElementById('indFilterTrack')?.value || '').toLowerCase();
    const rows    = document.querySelectorAll('.ind-row');
    rows.forEach(row => {
        const name = (row.getAttribute('data-name') || '').toLowerCase();
        const ref  = (row.getAttribute('data-ref')  || '').toLowerCase();
        const prog = (row.getAttribute('data-program') || '').toLowerCase();
        const typ  = (row.getAttribute('data-type')   || '').toLowerCase();
        const trk  = (row.getAttribute('data-track')  || '').toLowerCase();
        const show = (!search || name.includes(search) || ref.includes(search)) && (!program || prog === program) && (!type || typ === type) && (!track || trk === track);
        row.classList.toggle('hidden-by-filter', !show);
    });
    indFilteredRows = Array.from(rows).filter(r => !r.classList.contains('hidden-by-filter'));
    indCurrentPage = 1;
    showIndPage();
}

function showIndPage() {
    const start = (indCurrentPage - 1) * IND_PAGE_SIZE;
    const end   = start + IND_PAGE_SIZE;
    indFilteredRows.forEach((row, i) => { row.classList.toggle('hidden-by-page', i < start || i >= end); });
    const total = indFilteredRows.length;
    const bar = document.getElementById('indPaginationBar');
    if (!bar) return;
    if (total === 0) { bar.style.display = 'none'; return; }
    bar.style.display = 'flex';
    document.getElementById('indPaginationText').textContent = 'Showing ' + (start+1) + '–' + Math.min(end,total) + ' of ' + total;
    document.getElementById('indPrevPage').disabled = indCurrentPage <= 1;
    document.getElementById('indNextPage').disabled = indCurrentPage >= Math.ceil(total / IND_PAGE_SIZE);
}

function indGoToPage(n) {
    const totalPages = Math.ceil(indFilteredRows.length / IND_PAGE_SIZE);
    if (n < 1 || n > totalPages) return;
    indCurrentPage = n;
    showIndPage();
}

function indFillSameSlot() {
    const visible = document.querySelectorAll('.ind-row:not(.hidden-by-filter):not(.hidden-by-page)');
    if (visible.length === 0) return;
    const first = visible[0];
    const date  = first.querySelector('.date-inp')?.value || '';
    const time  = first.querySelector('.time-inp')?.value || '';
    const venue = first.querySelector('.venue-inp')?.value || '';
    const type  = first.querySelector('.type-sel')?.value || 'Individual';
    for (let i = 1; i < visible.length; i++) {
        const row = visible[i];
        const d = row.querySelector('.date-inp');  if (d)  d.value  = date;
        const t = row.querySelector('.time-inp');  if (t)  t.value  = time;
        const v = row.querySelector('.venue-inp'); if (v)  v.value  = venue;
        const ty = row.querySelector('.type-sel'); if (ty) ty.value = type;
    }
}

function confirmIndividual() {
    const dates = document.querySelectorAll('[name^="ind_date"]');
    let filled = 0;
    dates.forEach(dateEl => {
        const id = dateEl.name.match(/\[(\d+)\]/)[1];
        const d  = dateEl.value;
        const t  = document.querySelector(`[name="ind_time[${id}]"]`)?.value;
        const v  = document.querySelector(`[name="ind_venue[${id}]"]`)?.value?.trim();
        if (d && t && v) filled++;
    });
    if (filled === 0) { alert('Please fill in at least one applicant\'s date, time, and venue.'); return false; }
    return confirm(`Schedule interviews for ${filled} applicant(s)?`);
}

document.addEventListener('DOMContentLoaded', function() {
    applyBatchFilters();
    applyIndFilters();
});

function openReschedule(id, name, date, time, venue, type) {
    document.getElementById('rescheduleId').value   = id;
    document.getElementById('rescheduleApplicantName').textContent = name;
    document.getElementById('rescheduleDate').value = date;
    document.getElementById('rescheduleTime').value = time;
    document.getElementById('rescheduleVenue').value = venue;
    document.getElementById('rescheduleType').value = type;
    const modal = document.getElementById('rescheduleModal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeReschedule() {
    document.getElementById('rescheduleModal').style.display = 'none';
    document.body.style.overflow = '';
}

document.getElementById('rescheduleModal')?.addEventListener('click', function(e) { if (e.target === this) closeReschedule(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeReschedule(); });
</script>
</body>
</html>