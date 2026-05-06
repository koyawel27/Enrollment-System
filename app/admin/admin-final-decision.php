<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin - Final Decision
 * admin-final-decision.php
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/admin-auth-check.php';
require_once CONFIG_PATH . '/admin-permissions.php';
require_admin_role([
    ADMIN_ROLE_SUPER_ADMIN,
    ADMIN_ROLE_ADMISSION_OFFICER,
]);
require_once CONFIG_PATH . '/programs.php';

[$head_filter, $head_params] = get_head_program_filter($conn);

$q = trim($_GET['q'] ?? '');
$track_filter = trim($_GET['track'] ?? 'all');
if (!in_array($track_filter, ['all', 'CHED', 'TESDA'], true)) $track_filter = 'all';

// ── FETCH PENDING ──────────────────────────────────────────
$pending = [];
$pending_sql =
    "SELECT a.id, a.first_name, a.last_name, a.reference_number,
            a.first_choice, a.second_choice, a.third_choice,
            COALESCE(a.assigned_program, a.first_choice) AS display_program,
            a.applicant_type, a.program_category,
            a.exam_score, a.interview_date, u.email,
            a.interview_score, a.interview_remarks
     FROM applications a
     JOIN users u ON a.user_id = u.id
     WHERE a.status = 'Interview Completed'";

if ($track_filter !== 'all') {
    $te = mysqli_real_escape_string($conn, $track_filter);
    $pending_sql .= " AND a.program_category = '$te'";
}
if ($q !== '') {
    $qe = mysqli_real_escape_string($conn, $q);
    $like = '%' . $qe . '%';
    $pending_sql .= " AND (a.reference_number LIKE '$like' OR a.first_name LIKE '$like' OR a.last_name LIKE '$like' OR u.email LIKE '$like')";
}
$pending_sql .= " {$head_filter} ORDER BY a.last_name ASC, a.first_name ASC";

if (!empty($head_params)) {
    $pq = mysqli_prepare($conn, $pending_sql);
    $types = str_repeat('s', count($head_params));
    mysqli_stmt_bind_param($pq, $types, ...$head_params);
    mysqli_stmt_execute($pq);
    $pq_res = mysqli_stmt_get_result($pq);
    while ($row = mysqli_fetch_assoc($pq_res)) $pending[] = $row;
    mysqli_stmt_close($pq);
} else {
    $pq_res = mysqli_query($conn, $pending_sql);
    while ($row = mysqli_fetch_assoc($pq_res)) $pending[] = $row;
}

// ── FETCH DECIDED ──────────────────────────────────────────
$decided = [];
$dq_sql =
    "SELECT a.id, a.first_name, a.last_name, a.reference_number,
            COALESCE(a.assigned_program, a.first_choice) AS display_program,
            a.program_category, a.status, a.updated_at
     FROM applications a
     WHERE a.status IN ('Admitted/Enrolled','Rejected')
       {$head_filter}
     ORDER BY a.updated_at DESC";
if (!empty($head_params)) {
    $dq = mysqli_prepare($conn, $dq_sql);
    $types = str_repeat('s', count($head_params));
    mysqli_stmt_bind_param($dq, $types, ...$head_params);
    mysqli_stmt_execute($dq);
    $dq_res = mysqli_stmt_get_result($dq);
    while ($row = mysqli_fetch_assoc($dq_res)) $decided[] = $row;
    mysqli_stmt_close($dq);
} else {
    $dq_res = mysqli_query($conn, $dq_sql);
    while ($row = mysqli_fetch_assoc($dq_res)) $decided[] = $row;
}

$cnt_pending  = count($pending);
$cnt_admitted = count(array_filter($decided, fn($r) => $r['status'] === 'Admitted/Enrolled'));
$cnt_rejected = count(array_filter($decided, fn($r) => $r['status'] === 'Rejected'));

$success = $error = '';
if (isset($_SESSION['admin_success'])) { $success = $_SESSION['admin_success']; unset($_SESSION['admin_success']); }
if (isset($_SESSION['admin_error']))   { $error   = $_SESSION['admin_error'];   unset($_SESSION['admin_error']); }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/admin-styles.css">
    <title>Final Decision - Admin | BPC iEnroll</title>
    <style>
        /* ----- VARIABLES ----- */
        :root {
            --bpc-green: #2e7d32;
            --bpc-green-dark: #1b5e20;
            --bg-light: #f8f9fa;
            --text-gray: #6c757d;
            --border-color: #dee2e6;
            --white: #fff;
        }

        /* ----- GLOBAL / EXISTING STYLES (kept) ----- */
        .top-nav { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem; }
        .top-nav h1 { font-size:1.5rem; color:var(--bpc-green-dark); }
        .top-nav span { font-size:0.82rem; color:var(--text-gray); }
        .stats-row { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1.5rem; }
        .stat-card { background:var(--white); border-radius:10px; padding:1.25rem 1.5rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); border-top:4px solid var(--border-color); }
        .stat-card.blue  { border-color:#007bff; }
        .stat-card.green { border-color:var(--bpc-green); }
        .stat-card.red   { border-color:#dc3545; }
        .stat-num   { font-size:2rem; font-weight:700; line-height:1; }
        .stat-label { font-size: 0.82rem; color:var(--text-gray); margin-top:0.35rem; }
        .card { background:var(--white); border-radius:10px; padding:1.75rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); margin-bottom:1.5rem; }
        .card-title { font-size:0.9rem; font-weight:700; color:var(--bpc-green-dark); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:1.25rem; padding-bottom:0.75rem; border-bottom:2px solid var(--bg-light); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; }
        .card-title span { font-size:0.8rem; font-weight:400; color:var(--text-gray); text-transform:none; letter-spacing:0; }
        .filters { display:flex; gap:0.75rem; align-items:flex-end; flex-wrap:wrap; margin-bottom:1rem; }
        .filter-group { display:flex; flex-direction:column; gap:0.3rem; }
        .filter-group label { font-size:0.72rem; font-weight:700; color:var(--text-gray); text-transform:uppercase; letter-spacing:0.05em; }
        .filter-group input, .filter-group select { padding:0.5rem 0.75rem; border:2px solid var(--border-color); border-radius:6px; font-size:0.875rem; background:#fff; }
        .filter-group input:focus, .filter-group select:focus { outline:none; border-color:var(--bpc-green); }

        .name-link  { color:var(--bpc-green-dark); text-decoration:none; font-weight:700; font-size: 0.875rem; }
        .name-link:hover { text-decoration:underline; }
        .tag { display:inline-block; font-size:0.72rem; font-weight:700; padding:0.15rem 0.45rem; border-radius:10px; white-space:nowrap; }
        .tag-ched  { background:#e3f2fd; color:#1565c0; }
        .tag-tesda { background:#f3e5f5; color:#6a1b9a; }
        .tag-prog  { background:#e8f5e9; color:var(--bpc-green-dark); }
        .btn:disabled { opacity:0.6; cursor:not-allowed; }
        .badge { display:inline-block; padding:0.2rem 0.5rem; border-radius:10px; font-size:0.75rem; font-weight:700; white-space:nowrap; }
        .b-admitted { background:#d4edda; color:#155724; }
        .b-rejected { background:#f8d7da; color:#721c24; }
        /* Filter chips */
        .filter-chips { display:flex; flex-wrap:wrap; gap:0.5rem; margin-bottom:1rem; }
        .chip { background:#e9ecef; border-radius:20px; padding:0.2rem 0.6rem; font-size:0.75rem; display:inline-flex; align-items:center; gap:6px; }
        .chip-remove { cursor:pointer; font-weight:bold; color:var(--text-gray); }
        .chip-remove:hover { color:#dc3545; }
        /* Modal styles */
        .modal-overlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:1000; visibility:hidden; opacity:0; transition:0.2s; }
        .modal-overlay.active { visibility:visible; opacity:1; }
        .modal-container { background:white; border-radius:12px; max-width:500px; width:90%; padding:1.5rem; box-shadow:0 10px 25px rgba(0,0,0,0.2); }
        .modal-header { font-size:1.25rem; font-weight:bold; margin-bottom:1rem; padding-bottom:0.5rem; border-bottom:2px solid #eee; }
        .modal-body { margin-bottom:1.5rem; }
        .modal-footer { display:flex; justify-content:flex-end; gap:0.75rem; }
        .reject-confirm-input { width:100%; padding:0.5rem; margin-top:0.5rem; border:2px solid #ddd; border-radius:6px; }
        .empty-state { text-align:center; padding:3rem; color:var(--text-gray); font-size:0.9rem; }
        .empty-state svg { width:60px; height:60px; margin-bottom:1rem; opacity:0.5; }
        /* Sorting indicators */
        .sortable { cursor:pointer; user-select:none; }
        .sortable::after { content:'⇅'; display:inline-block; margin-left:5px; font-size:0.7rem; opacity:0.5; }
        .sortable.asc::after { content:'↑'; opacity:1; }
        .sortable.desc::after { content:'↓'; opacity:1; }
        /* Alert enhancements */
        .alert { position:relative; padding:0.75rem 2rem 0.75rem 1rem; margin-bottom:1rem; border-radius:6px; animation:slideIn 0.3s ease; }
        .alert-success { background:#d4edda; color:#155724; border-left:4px solid #155724; }
        .alert-error { background:#f8d7da; color:#721c24; border-left:4px solid #721c24; }
        .alert-close { position:absolute; top:0.5rem; right:0.75rem; cursor:pointer; font-weight:bold; font-size:1rem; }
        @keyframes slideIn { from { transform:translateY(-20px); opacity:0; } to { transform:translateY(0); opacity:1; } }

        /* ============================================================
           TABLE WRAPPER — horizontal scroll container
           ============================================================ */
        .table-wrapper {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--white);
            -webkit-overflow-scrolling: touch;
        }

        /* ============================================================
           PENDING TABLE
           /* ========== FIXED COLUMN WIDTHS – NO TRUNCATED HEADERS ========== */
        #pendingTable {
            table-layout: fixed !important;
            width: 100% !important;
            min-width: 0 !important;
        }

        /* Column widths (sum to 100%) — keep Program/Exam tighter, leave room for Remarks */
        #pendingTable th:nth-child(1),
        #pendingTable td:nth-child(1) { width: 18%; }  /* Applicant */
        #pendingTable th:nth-child(2),
        #pendingTable td:nth-child(2) { width: 11%; }  /* Ref # */
        #pendingTable th:nth-child(3),
        #pendingTable td:nth-child(3) { width:  8%; }  /* Track */
        #pendingTable th:nth-child(4),
        #pendingTable td:nth-child(4) { width: 11%; }  /* Program – tighter */
        #pendingTable th:nth-child(5),
        #pendingTable td:nth-child(5) { width:  6%; }  /* Exam */
        #pendingTable th:nth-child(6),
        #pendingTable td:nth-child(6) { width:  9%; }  /* Interview */
        #pendingTable th:nth-child(7),
        #pendingTable td:nth-child(7) { width: 21%; }  /* Remarks – more flexible */
        #pendingTable th:nth-child(8),
        #pendingTable td:nth-child(8) { width: 16%; }  /* Actions */

        /* Reduce padding between Program and Exam, but keep others normal */
        #pendingTable td:nth-child(4) { padding-right: 0.25rem; }
        #pendingTable td:nth-child(5) { padding-left: 0.25rem; }
        #pendingTable th:nth-child(4) { padding-right: 0.25rem; }
        #pendingTable th:nth-child(5) { padding-left: 0.25rem; }

        /* Ensure Interview header never wraps/clips */
        #pendingTable th:nth-child(6) {
            white-space: nowrap;
            overflow: visible;
        }
        #pendingTable th:nth-child(6) span {
            display: inline-block;
            min-width: max-content;
        }

        /* Allow remarks to wrap properly */
        #pendingTable td:nth-child(7) .remarks-cell {
            white-space: normal;
            word-break: break-word;
            display: block;
        }

        

        #historyTable {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        /* ============================================================
           TABLE HEADERS
           ============================================================ */
        thead th {
            position: sticky;
            top: 0;
            background: var(--bg-light);
            z-index: 10;
            color: var(--text-gray);
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.65rem 0.6rem;
            text-align: left;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }

        /* ============================================================
           TABLE CELLS
           ============================================================ */
        td {
            padding: 0.6rem 0.6rem;    /* reduced from 0.75rem */
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        tr:last-child td { border-bottom: none; }
        tbody tr:nth-child(even) td { background: #fafafa; }
        tbody tr:hover td { background: #f5faf5; }

        /* ============================================================
           CELL CONTENT STYLES
           ============================================================ */
        .name-main {
            font-weight: 700;
            display: block;
            color: #333;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .name-sub {
            font-size: 0.72rem;
            color: var(--text-gray);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
        }
        .ref-mono { font-family: monospace; font-size: 0.78rem; color: var(--text-gray); }

        /* Score Pills */
        .score-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.15rem 0.4rem;
            border-radius: 12px;
            white-space: nowrap;
        }
        .score-high      { background:#e8f5e9; color:#1b5e20; }
        .score-mid       { background:#fff8e1; color:#795548; }
        .score-low       { background:#fce4ec; color:#880e4f; }
        .score-exam      { background:#fff3cd; color:#856404; }
        .score-interview { background:#e3f2fd; color:#1565c0; }
        .score-none      { color:var(--text-gray); font-size:0.75rem; }
        .interview-stack {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.3rem;
        }
        .interview-date-line {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--text-gray);
            line-height: 1.2;
        }

        /* Remarks — clamp to 2 lines, tooltip on hover */
        .remarks-cell {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            font-size: 0.78rem;
            color: var(--text-gray);
            line-height: 1.4;
            cursor: help;
            word-break: break-word;
        }

        /* Program tag — allow wrapping inside cell */
        .tag-prog {
            white-space: normal;
            word-break: break-word;
            display: inline-block;
            max-width: 100%;
            vertical-align: middle;
        }

        /* ============================================================
           ACTION BUTTONS
           — two buttons side-by-side, never wrap, compress gracefully
           ============================================================ */
        #pendingTable td:nth-child(8) {
            overflow: visible;         /* prevent button clipping */
            padding: 0.4rem 0.5rem;
        }

        .decision-btns {
            display: flex;
            gap: 0.35rem;
            flex-wrap: nowrap;         /* keep on one line always */
            align-items: center;
            width: 100%;
        }

        .btn-admit,
        .btn-reject-d {
            flex: 1;
            min-width: 0;              /* allow flex shrink below content size */
            padding: 0.35rem 0.4rem;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
            text-align: center;
            overflow: hidden;
            text-overflow: ellipsis;
            border: none;
        }

        .btn-admit { background: var(--bpc-green); color: #fff; }
        .btn-admit:hover { background: var(--bpc-green-dark); }

        .btn-reject-d {
            background: transparent;
            color: #dc3545;
            border: 1.5px solid #ffcdd2;
        }
        .btn-reject-d:hover {
            background: #dc3545;
            color: #fff;
            border-color: #dc3545;
        }

        /* ============================================================
           MOBILE — card-style stacked rows
           ============================================================ */
        @media (max-width: 768px) {
            #pendingTable { min-width: 100%; table-layout: auto; }

            .table-wrapper table,
            .table-wrapper thead,
            .table-wrapper tbody,
            .table-wrapper tr,
            .table-wrapper td {
                display: block;
            }

            thead { display: none; }

            tr {
                border: 1px solid var(--border-color);
                border-radius: 10px;
                margin-bottom: 1rem;
                padding: 0.5rem;
            }

            td {
                display: flex;
                justify-content: space-between;
                padding: 0.5rem;
                border: none;
                overflow: visible;
                text-overflow: unset;
            }

            td::before {
                content: attr(data-label);
                font-weight: 700;
                color: var(--text-gray);
                font-size: 0.7rem;
                text-transform: uppercase;
                flex-shrink: 0;
                margin-right: 0.5rem;
            }

            .decision-btns { flex-wrap: wrap; }
        }
    </style>
</head>
<body>

<?php include '../shared/admin-sidebar.php'; ?>

<main class="main">
    <div class="top-nav">
        <h1>Final Admission Decision</h1>
        <div>
            <span id="lastUpdated" style="margin-right:1rem;"></span>
            <span>Logged in as <strong><?php echo htmlspecialchars($_SESSION['admin_name']); ?></strong></span>
        </div>
    </div>

    <!-- Enhanced Alerts -->
    <?php if ($success): ?>
    <div class="alert alert-success" role="alert">
        <?php echo htmlspecialchars($success); ?>
        <span class="alert-close">&times;</span>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-error" role="alert">
        <?php echo htmlspecialchars($error); ?>
        <span class="alert-close">&times;</span>
    </div>
    <?php endif; ?>

    <div class="stats-row">
        <div class="stat-card blue">
            <div class="stat-num"><?php echo $cnt_pending; ?></div>
            <div class="stat-label">Awaiting Decision</div>
        </div>
        <div class="stat-card green">
            <div class="stat-num"><?php echo $cnt_admitted; ?></div>
            <div class="stat-label">Admitted/Enrolled</div>
        </div>
        <div class="stat-card red">
            <div class="stat-num"><?php echo $cnt_rejected; ?></div>
            <div class="stat-label">Rejected</div>
        </div>
    </div>

    <!-- PENDING SECTION -->
    <div class="card">
        <div class="card-title">
            Applicants Awaiting Final Decision
            <span id="pendingCount"><?php echo $cnt_pending; ?> pending</span>
        </div>

        <form class="filters" method="GET" action="admin-final-decision.php" id="filterForm">
            <div class="filter-group">
                <label for="q">Search</label>
                <input id="q" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Name, email, or reference #" autocomplete="off">
            </div>
            <div class="filter-group">
                <label for="track">Track</label>
                <select id="track" name="track">
                    <option value="all"  <?php echo $track_filter==='all'   ?'selected':''; ?>>All</option>
                    <option value="CHED" <?php echo $track_filter==='CHED'  ?'selected':''; ?>>CHED</option>
                    <option value="TESDA"<?php echo $track_filter==='TESDA' ?'selected':''; ?>>TESDA</option>
                </select>
            </div>
            <button type="submit" class="btn-submit-filters" style="padding:0.5rem 1rem;background:var(--bpc-green);color:#fff;border:none;border-radius:6px;font-size:0.875rem;font-weight:600;cursor:pointer;align-self:flex-end;">Search</button>
            <div id="filterChipsContainer" class="filter-chips" style="flex:1 1 100%; margin-top:0.5rem;"></div>
        </form>

        <?php if (empty($pending)): ?>
        <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" stroke-linecap="round"/>
            </svg>
            <div>No applicants awaiting final decision right now.</div>
            <div style="font-size:0.8rem; margin-top:0.5rem;">New applicants will appear here after interviews are completed.</div>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
            <table id="pendingTable">
                <!-- colgroup gives precise, maintainable column widths -->
                <colgroup>
                    <col> <!-- Applicant -->
                    <col> <!-- Ref # -->
                    <col> <!-- Track -->
                    <col> <!-- Program -->
                    <col> <!-- Exam -->
                    <col> <!-- Interview -->
                    <col> <!-- Remarks -->
                    <col> <!-- Actions -->
                </colgroup>
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Ref #</th>
                        <th>Track</th>
                        <th>Program</th>
                        <th>Exam</th>
                        <th>Interview</th>
                        <th>Remarks</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $a):
                        $cat = $a['program_category'] ?? 'CHED';
                        $prog_label = get_program_label($a['display_program'] ?? null, $conn);
                    ?>
                    <tr data-searchable="<?php echo strtolower($a['first_name'].' '.$a['last_name'].' '.$a['email'].' '.$a['reference_number']); ?>">
                        <td data-label="Applicant">
                            <a href="admin-application-detail.php?id=<?php echo (int)$a['id']; ?>" class="name-link">
                                <span class="name-main"><?php echo htmlspecialchars($a['last_name'].', '.$a['first_name']); ?></span>
                            </a>
                            <span class="name-sub"><?php echo htmlspecialchars($a['email']); ?></span>
                        </td>
                        <td data-label="Ref #" class="ref-mono"><?php echo htmlspecialchars($a['reference_number'] ?? '—'); ?></td>
                        <td data-label="Track"><span class="tag tag-<?php echo strtolower($cat); ?>"><?php echo $cat; ?></span></td>
                        <td data-label="Program">
                            <span class="tag tag-prog" title="<?php echo htmlspecialchars($prog_label); ?>">
                                <?php echo htmlspecialchars($a['display_program'] ?? '—'); ?>
                            </span>
                        </td>
                        <td data-label="Exam">
                            <?php if ($a['exam_score'] !== null): ?>
                                <span class="score-pill score-exam"><?php echo (int)$a['exam_score']; ?>/100</span>
                            <?php else: ?>
                                <span class="score-none">—</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Interview">
                            <div class="interview-stack">
                                <?php if (!empty($a['interview_score'])): ?>
                                    <span class="score-pill score-interview"><?php echo (int)$a['interview_score']; ?>/100</span>
                                <?php endif; ?>
                                <?php if (!empty($a['interview_date'])): ?>
                                    <span class="interview-date-line"><?php echo date('M d, Y', strtotime($a['interview_date'])); ?></span>
                                <?php endif; ?>
                                <?php if (empty($a['interview_score']) && empty($a['interview_date'])): ?>
                                    <span class="score-none">—</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Remarks">
                            <?php if (!empty($a['interview_remarks'])): ?>
                                <span class="remarks-cell" title="<?php echo htmlspecialchars($a['interview_remarks']); ?>">
                                    <?php echo htmlspecialchars($a['interview_remarks']); ?>
                                </span>
                            <?php else: ?>
                                <span class="score-none">—</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Actions">
                            <div style="display:flex;flex-direction:column;gap:0.35rem;">
                                <a href="admin-application-detail.php?id=<?php echo (int)$a['id']; ?>"
                                class="btn-view"
                                style="text-align:center;padding:0.3rem 0.4rem;font-size:0.72rem;font-weight:700;border-radius:6px;text-decoration:none;display:block;">
                                    View Profile
                                </a>
                                <div class="decision-btns">
                                    <form method="POST" action="admin-set-final-decision.php" class="decision-form" data-decision="admit" style="flex:1;min-width:0;">
                                        <input type="hidden" name="app_id" value="<?php echo (int)$a['id']; ?>">
                                        <input type="hidden" name="decision" value="admit">
                                        <button type="submit" class="btn-admit"
                                            data-name="<?php echo htmlspecialchars($a['first_name'].' '.$a['last_name']); ?>"
                                            data-ref="<?php echo htmlspecialchars($a['reference_number']); ?>"
                                            data-program="<?php echo htmlspecialchars($prog_label); ?>"
                                            data-exam="<?php echo (int)$a['exam_score']; ?>"
                                            data-interview="<?php echo (int)$a['interview_score']; ?>"
                                            style="width:100%;">
                                            Admit
                                        </button>
                                    </form>
                                    <form method="POST" action="admin-set-final-decision.php" class="decision-form" data-decision="reject" style="flex:1;min-width:0;">
                                        <input type="hidden" name="app_id" value="<?php echo (int)$a['id']; ?>">
                                        <input type="hidden" name="decision" value="reject">
                                        <button type="submit" class="btn-reject-d"
                                            data-name="<?php echo htmlspecialchars($a['first_name'].' '.$a['last_name']); ?>"
                                            data-ref="<?php echo htmlspecialchars($a['reference_number']); ?>"
                                            data-program="<?php echo htmlspecialchars($prog_label); ?>"
                                            style="width:100%;">
                                            Reject
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- HISTORY SECTION -->
    <?php if (!empty($decided)): ?>
    <div class="card">
        <div class="card-title">
            Decision History
            <span><?php echo count($decided); ?> total</span>
        </div>
        <!-- Filter pills for history -->
        <div class="filter-chips" id="historyFilterPills">
            <span class="chip" data-filter="all">All</span>
            <span class="chip" data-filter="Admitted/Enrolled">Admitted</span>
            <span class="chip" data-filter="Rejected">Rejected</span>
        </div>
        <div class="table-wrapper">
            <table id="historyTable">
                <thead>
                    <tr>
                        <th class="sortable" data-sort="index">#</th>
                        <th class="sortable" data-sort="name">Applicant</th>
                        <th class="sortable" data-sort="ref">Ref #</th>
                        <th class="sortable" data-sort="track">Track</th>
                        <th class="sortable" data-sort="program">Program</th>
                        <th class="sortable" data-sort="decision">Decision</th>
                        <th class="sortable" data-sort="date">Date Decided</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($decided as $i => $d):
                        $badge = $d['status'] === 'Admitted/Enrolled'
                            ? ['b-admitted', 'Admitted']
                            : ['b-rejected', 'Rejected'];
                        $cat = $d['program_category'] ?? 'CHED';
                    ?>
                    <tr data-status="<?php echo htmlspecialchars($d['status']); ?>">
                        <td data-label="#"><?php echo $i+1; ?></td>
                        <td data-label="Applicant">
                            <a href="admin-application-detail.php?id=<?php echo (int)$d['id']; ?>" class="name-link">
                                <?php echo htmlspecialchars($d['last_name'].', '.$d['first_name']); ?>
                            </a>
                        </td>
                        <td data-label="Ref #" class="ref-mono"><?php echo htmlspecialchars($d['reference_number'] ?? '—'); ?></td>
                        <td data-label="Track"><span class="tag tag-<?php echo strtolower($cat); ?>"><?php echo $cat; ?></span></td>
                        <td data-label="Program">
                            <span class="tag tag-prog" title="<?php echo htmlspecialchars(get_program_label($d['display_program'] ?? null, $conn)); ?>">
                                <?php echo htmlspecialchars($d['display_program'] ?? '—'); ?>
                            </span>
                        </td>
                        <td data-label="Decision"><span class="badge <?php echo $badge[0]; ?>"><?php echo $badge[1]; ?></span></td>
                        <td data-label="Date Decided"><?php echo $d['updated_at'] ? date('M d, Y', strtotime($d['updated_at'])) : '—'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</main>

<!-- Custom Confirmation Modal -->
<div id="decisionModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header" id="modalHeader">Confirm Decision</div>
        <div class="modal-body" id="modalBody"></div>
        <div class="modal-footer">
            <button class="btn-cancel" style="background:#e9ecef; border:none; padding:0.4rem 1rem; border-radius:6px; hover:background:#e9ecef; cursor:pointer;">Cancel</button>
            <button class="btn-confirm" style="background:var(--bpc-green); color:white; border:none; padding:0.4rem 1rem; border-radius:6px; hover:background:var(--bpc-green-dark); cursor:pointer;">Confirm</button>
        </div>
    </div>
</div>

<script>
    (function() {
        // ---- Helper: update pending count display ----
        function updatePendingCount() {
            const rows = document.querySelectorAll('#pendingTable tbody tr');
            const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
            const countSpan = document.getElementById('pendingCount');
            if (countSpan) countSpan.innerText = visibleRows.length + ' pending';
        }

        // ---- LIVE SEARCH (client-side, debounced) ----
        const searchInput = document.getElementById('q');
        if (searchInput) {
            let debounceTimer;
            searchInput.addEventListener('input', function(e) {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    const term = e.target.value.toLowerCase().trim();
                    const rows = document.querySelectorAll('#pendingTable tbody tr');
                    rows.forEach(row => {
                        const searchable = row.getAttribute('data-searchable') || '';
                        if (term === '' || searchable.includes(term)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    updatePendingCount();
                }, 300);
            });
        }

        // ---- SORTABLE COLUMNS (History table) ----
        const historyTable = document.getElementById('historyTable');
        if (historyTable) {
            const headers = historyTable.querySelectorAll('.sortable');
            let sortDirection = {};
            headers.forEach(header => {
                header.addEventListener('click', () => {
                    const sortKey = header.getAttribute('data-sort');
                    const tbody = historyTable.querySelector('tbody');
                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    const currentDir = sortDirection[sortKey] || 'asc';
                    const newDir = currentDir === 'asc' ? 'desc' : 'asc';
                    sortDirection = { [sortKey]: newDir };
                    headers.forEach(h => h.classList.remove('asc', 'desc'));
                    header.classList.add(newDir);
                    rows.sort((a,b) => {
                        let aVal, bVal;
                        if (sortKey === 'index') {
                            aVal = parseInt(a.cells[0].innerText);
                            bVal = parseInt(b.cells[0].innerText);
                        } else if (sortKey === 'name') {
                            aVal = a.cells[1].innerText.trim();
                            bVal = b.cells[1].innerText.trim();
                        } else if (sortKey === 'ref') {
                            aVal = a.cells[2].innerText.trim();
                            bVal = b.cells[2].innerText.trim();
                        } else if (sortKey === 'track') {
                            aVal = a.cells[3].innerText.trim();
                            bVal = b.cells[3].innerText.trim();
                        } else if (sortKey === 'program') {
                            aVal = a.cells[4].innerText.trim();
                            bVal = b.cells[4].innerText.trim();
                        } else if (sortKey === 'decision') {
                            aVal = a.cells[5].innerText.trim();
                            bVal = b.cells[5].innerText.trim();
                        } else if (sortKey === 'date') {
                            aVal = new Date(a.cells[6].innerText);
                            bVal = new Date(b.cells[6].innerText);
                        } else {
                            aVal = a.cells[0].innerText;
                            bVal = b.cells[0].innerText;
                        }
                        if (aVal < bVal) return newDir === 'asc' ? -1 : 1;
                        if (aVal > bVal) return newDir === 'asc' ? 1 : -1;
                        return 0;
                    });
                    rows.forEach(row => tbody.appendChild(row));
                });
            });
        }

        // ---- HISTORY FILTER BY DECISION ----
        const filterPills = document.querySelectorAll('#historyFilterPills .chip');
        if (filterPills.length) {
            filterPills.forEach(pill => {
                pill.addEventListener('click', () => {
                    const filter = pill.getAttribute('data-filter');
                    const rows = document.querySelectorAll('#historyTable tbody tr');
                    rows.forEach(row => {
                        const status = row.getAttribute('data-status');
                        if (filter === 'all' || status === filter) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    filterPills.forEach(p => p.style.background = '#e9ecef');
                    pill.style.background = '#cfe2ff';
                });
            });
            if (filterPills[0]) filterPills[0].style.background = '#cfe2ff';
        }

        // ---- CUSTOM MODAL LOGIC ----
        let activeForm = null;
        let activeDecision = null;
        const modal = document.getElementById('decisionModal');
        const modalHeader = document.getElementById('modalHeader');
        const modalBody = document.getElementById('modalBody');
        const confirmBtn = modal.querySelector('.btn-confirm');
        const cancelBtn = modal.querySelector('.btn-cancel');

        function showModal(decision, button, form) {
            activeForm = form;
            activeDecision = decision;
            const name = button.getAttribute('data-name');
            const ref = button.getAttribute('data-ref');
            const program = button.getAttribute('data-program');
            const exam = button.getAttribute('data-exam');
            const interview = button.getAttribute('data-interview');
            if (decision === 'admit') {
                modalHeader.innerHTML = 'Confirm Admission';
                modalHeader.style.color = 'green';
                modalBody.innerHTML = `
                    <p><strong>${escapeHtml(name)}</strong><br>
                    Reference: ${escapeHtml(ref)}<br>
                    Program: ${escapeHtml(program)}<br>
                    <p>Are you sure you want to <span style="color:green;font-weight:bold">ADMIT</span> this applicant?</p>
                `;
                confirmBtn.style.background = 'var(--bpc-green)';
            } else {
                modalHeader.innerHTML = 'Confirm Rejection';
                modalHeader.style.color = 'red';
                modalBody.innerHTML = `
                    <p><strong>${escapeHtml(name)}</strong><br>
                    Reference: ${escapeHtml(ref)}<br>
                    Program: ${escapeHtml(program)}</p>
                    <p style="color:red;font-weight:bold">This action is irreversible.</p>
                    <label>Type <strong>REJECT</strong> to confirm:</label>
                    <input type="text" id="rejectConfirmInput" class="reject-confirm-input" placeholder="REJECT">
                `;
                confirmBtn.style.background = '#dc3545';
            }
            modal.classList.add('active');
        }

        function closeModal() { modal.classList.remove('active'); activeForm = null; }

        confirmBtn.addEventListener('click', () => {
            if (activeDecision === 'reject') {
                const input = document.getElementById('rejectConfirmInput');
                if (!input || input.value.toUpperCase() !== 'REJECT') {
                    alert('Please type REJECT to confirm rejection.');
                    return;
                }
            }
            if (activeForm) {
                const btn = activeForm.querySelector('button');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = 'Processing...';
                }
                activeForm.submit();
            }
            closeModal();
        });
        cancelBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

        document.querySelectorAll('.decision-form button').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const form = btn.closest('.decision-form');
                const decision = form.getAttribute('data-decision');
                showModal(decision, btn, form);
            });
        });

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[m]));
        }

        // ---- FILTER SUMMARY CHIPS ----
        const filterForm = document.getElementById('filterForm');
        const chipsContainer = document.getElementById('filterChipsContainer');
        function updateFilterChips() {
            if (!chipsContainer) return;
            const urlParams = new URLSearchParams(window.location.search);
            const qVal = urlParams.get('q') || '';
            const trackVal = urlParams.get('track') || 'all';
            chipsContainer.innerHTML = '';
            if (qVal) addChip(`Search: "${qVal}"`, () => removeFilter('q'));
            if (trackVal !== 'all') addChip(`Track: ${trackVal}`, () => removeFilter('track'));
            if (!qVal && trackVal === 'all') {
                chipsContainer.innerHTML = '<div style="font-size:0.75rem; color:#6c757d;">No active filters</div>';
            }
        }
        function addChip(text, onRemove) {
            const chip = document.createElement('div');
            chip.className = 'chip';
            chip.innerHTML = `${text} <span class="chip-remove">✕</span>`;
            chip.querySelector('.chip-remove').addEventListener('click', onRemove);
            chipsContainer.appendChild(chip);
        }
        function removeFilter(param) {
            const url = new URL(window.location.href);
            url.searchParams.delete(param);
            window.location.href = url.toString();
        }
        updateFilterChips();

        // ---- LAST UPDATED TIMESTAMP ----
        const lastUpdatedSpan = document.getElementById('lastUpdated');
        if (lastUpdatedSpan) {
            lastUpdatedSpan.innerText = `Last refreshed: ${new Date().toLocaleTimeString()}`;
        }

        // ---- AUTO-DISMISS ALERTS ----
        document.querySelectorAll('.alert').forEach(alert => {
            const closeBtn = alert.querySelector('.alert-close');
            if (closeBtn) closeBtn.addEventListener('click', () => alert.remove());
            setTimeout(() => { alert.style.opacity='0'; setTimeout(()=>alert.remove(),300); }, 6000);
        });

        // ---- HISTORY ROW CLICK ----
        document.querySelectorAll('#historyTable tbody tr').forEach(row => {
            const link = row.querySelector('.name-link');
            if (link) {
                row.style.cursor = 'pointer';
                row.addEventListener('click', (e) => { if (e.target.tagName !== 'A') window.location.href = link.href; });
                row.setAttribute('tabindex', '0');
                row.addEventListener('keydown', (e) => { if (e.key === 'Enter') window.location.href = link.href; });
            }
        });
    })();
</script>
<?php mysqli_close($conn); ?>
</body>
</html>