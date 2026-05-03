<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin - Exam Schedule
 * admin-exam-schedule.php
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/admin-auth-check.php';
require_once CONFIG_PATH . '/admin-permissions.php';
require_admin_role([ADMIN_ROLE_SUPER_ADMIN, ADMIN_ROLE_ADMISSION_OFFICER]);

// ── FETCH ALL EXAM SCHEDULES ───────────────────────────────
$schedules = [];
$sc = mysqli_query($conn, "SELECT * FROM exam_schedules ORDER BY exam_date ASC");
while ($row = mysqli_fetch_assoc($sc)) $schedules[] = $row;

// ── FETCH UNSCHEDULED CHED ONLY (Documents Verified) ──────
$unscheduled = [];
$us = mysqli_query($conn,
    "SELECT a.id, a.first_name, a.last_name, a.reference_number,
            a.first_choice, a.applicant_type, u.email
     FROM applications a
     JOIN users u ON a.user_id = u.id
     WHERE a.status = 'Documents Verified'
       AND (a.program_category = 'CHED' OR a.program_category IS NULL)
     ORDER BY a.last_name ASC, a.first_name ASC"
);
while ($row = mysqli_fetch_assoc($us)) $unscheduled[] = $row;

// ── FETCH ALREADY SCHEDULED CHED ONLY ─────────────────────
$already = [];
$as_q = mysqli_query($conn,
    "SELECT a.id, a.first_name, a.last_name, a.reference_number,
            a.first_choice, a.status, a.exam_schedule_id,
            es.exam_date, es.exam_time, es.exam_venue, es.schedule_type,
            u.email
     FROM applications a
     LEFT JOIN exam_schedules es ON a.exam_schedule_id = es.id
     JOIN users u ON a.user_id = u.id
     WHERE a.status IN ('Exam Scheduled','Exam Completed','Exam Failed','Exam No Show')
       AND (a.program_category = 'CHED' OR a.program_category IS NULL)
     ORDER BY es.exam_date ASC, a.last_name ASC"
);
while ($row = mysqli_fetch_assoc($as_q)) $already[] = $row;

// ── SESSION MESSAGES ───────────────────────────────────────
$success = $error = '';
if (isset($_SESSION['admin_success'])) { $success = $_SESSION['admin_success']; unset($_SESSION['admin_success']); }
if (isset($_SESSION['admin_error']))   { $error   = $_SESSION['admin_error'];   unset($_SESSION['admin_error']);   }

$programs_for_filter = array_values(array_unique(array_filter(array_column($unscheduled, 'first_choice'))));
sort($programs_for_filter);
$already_programs = array_values(array_unique(array_filter(array_column($already, 'first_choice'))));
sort($already_programs);

$default_instructions = "DO's:\n• Bring a printed screenshot of your scheduled exam along with a valid identification card bearing your name.\n• Arrive at least 30 minutes before the exam. Late arrivals may not be accommodated.\n• Prepare a Mongol #2 pencil for shading your answers.\n• Wear proper attire (no sleeveless, shorts, ripped jeans, or slippers).\n\nDON'Ts:\n• Do not bring electronic devices (phones, smartwatches) unless specified.\n• Do not bring notes or reference materials.\n• No eating or drinking inside the exam room.";

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Schedule - Admin | BPC iEnroll</title>
    <link rel="stylesheet" href="../../assets/admin-styles.css">
    <style>
        .top-nav { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem; }
        .top-nav h1 { font-size:1.5rem; color:var(--bpc-green-dark); }
        .top-nav span { font-size:0.82rem; color:var(--text-gray); }
        .alert { margin-bottom:1.25rem; }

        .ched-notice { display:flex; align-items:center; gap:0.75rem; background:#e8f5e9; border:1px solid #a5d6a7; border-radius:8px; padding:0.875rem 1.25rem; margin-bottom:1.5rem; font-size:0.875rem; color:#2e7d32; }

        .card { background:var(--white); border-radius:10px; padding:1.75rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); margin-bottom:1.5rem; }
        .card-title { font-size:0.9rem; font-weight:700; color:var(--bpc-green-dark); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:1.5rem; padding-bottom:0.875rem; border-bottom:2px solid var(--bg-light); display:flex; justify-content:space-between; align-items:center; }
        .card-title span { font-size:0.8rem; font-weight:400; color:var(--text-gray); text-transform:none; letter-spacing:0; }

        /* PREVIOUS SCHEDULES — compact table */
        .sched-table { width:100%; border-collapse:collapse; font-size:0.875rem; }
        .sched-table th { background:var(--bg-light); color:var(--text-gray); font-size:0.72rem; text-transform:uppercase; letter-spacing:0.5px; padding:0.6rem 1rem; text-align:left; border-bottom:2px solid var(--border-color); white-space:nowrap; }
        .sched-table td { padding:0.65rem 1rem; border-bottom:1px solid #f0f0f0; vertical-align:middle; font-size:0.875rem; }
        .sched-table tr:last-child td { border-bottom:none; }
        .sched-table tr:hover td { background:#fafafa; }
        .sched-type-badge { display:inline-block; padding:0.15rem 0.5rem; border-radius:10px; font-size:0.7rem; font-weight:700; }
        .sched-type-regular { background:#e8f5e9; color:var(--bpc-green-dark); }
        .sched-type-makeup  { background:#fff3cd; color:#856404; }

        /* FORM GRID */
        .form-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:1rem; }
        .form-grid .span-2 { grid-column:span 2; }
        .form-grid .span-3 { grid-column:span 3; }
        .form-group { display:flex; flex-direction:column; gap:0.35rem; }
        .form-group label { font-size:0.82rem; font-weight:600; color:var(--text-dark); }
        .form-group input, .form-group select, .form-group textarea {
            padding:0.65rem 0.875rem; border:2px solid var(--border-color);
            border-radius:6px; font-size:0.875rem; font-family:inherit;
            background:#fff; transition:border-color 0.2s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline:none; border-color:var(--bpc-green); box-shadow:0 0 0 3px rgba(0,100,0,0.1);
        }
        .form-group textarea { resize:vertical; min-height:70px; }

        /* CHECKLIST */
        .checklist-toolbar { display:flex; align-items:center; justify-content:space-between; background:var(--bg-light); border-radius:8px; padding:0.75rem 1.25rem; margin-bottom:1rem; flex-wrap:wrap; gap:0.75rem; }
        .checklist-toolbar-left { display:flex; align-items:center; gap:1rem; font-size:0.875rem; color:var(--text-gray); }
        .checklist-toolbar-right { display:flex; gap:0.5rem; }
        .selected-badge { display:inline-flex; align-items:center; gap:0.375rem; background:var(--bpc-green); color:#fff; border-radius:20px; padding:0.25rem 0.75rem; font-size:0.8rem; font-weight:700; }

        /* APPLICANT GRID */
        .applicant-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:0.75rem; max-height:480px; overflow-y:auto; padding:0.25rem; }
        .applicant-card { border:2px solid var(--border-color); border-radius:8px; padding:0.875rem 1rem; cursor:pointer; transition:all 0.15s; background:#fff; display:flex; align-items:flex-start; gap:0.75rem; }
        .applicant-card:hover { border-color:var(--bpc-green); background:#f8fffe; }
        .applicant-card.selected { border-color:var(--bpc-green); background:#f0fdf4; }
        .applicant-card input[type="checkbox"] { width:18px; height:18px; cursor:pointer; accent-color:var(--bpc-green); flex-shrink:0; margin-top:2px; }
        .applicant-card.hidden-by-filter { display:none !important; }
        .applicant-card.hidden-by-page { display:none !important; }
        .app-info { flex:1; min-width:0; }
        .app-name { font-size:0.875rem; font-weight:700; color:var(--text-dark); margin-bottom:0.2rem; }
        .app-ref  { font-size:0.75rem; color:var(--text-gray); font-family:monospace; }
        .app-prog { display:inline-block; margin-top:0.3rem; background:#e8f5e9; color:var(--bpc-green-dark); font-size:0.72rem; font-weight:700; padding:0.15rem 0.5rem; border-radius:10px; }
        .app-type { display:inline-block; margin-top:0.3rem; margin-left:0.3rem; background:#e3f2fd; color:#0d47a1; font-size:0.72rem; font-weight:600; padding:0.15rem 0.5rem; border-radius:10px; }
        .empty-check { padding:3rem; text-align:center; color:var(--text-gray); font-size:0.9rem; border:2px dashed var(--border-color); border-radius:8px; }

        .filter-row { display:flex; align-items:center; gap:0.75rem; margin-bottom:1rem; flex-wrap:wrap; }
        .filter-row input[type="search"], .filter-row select { padding:0.5rem 0.75rem; border:2px solid var(--border-color); border-radius:6px; font-size:0.875rem; }
        .filter-row input[type="search"] { min-width:200px; }
        .filter-row label { font-size:0.8rem; font-weight:600; color:var(--text-gray); }
        .filter-row .filter-checkbox { display:flex; align-items:center; gap:0.35rem; cursor:pointer; }
        .filter-row .filter-checkbox input { cursor:pointer; accent-color:var(--bpc-green); }
        .app-name-link { color:var(--bpc-green-dark); text-decoration:none; font-weight:700; }
        .app-name-link:hover { text-decoration:underline; }
        .row-clickable { cursor:pointer; }
        .row-clickable:hover td { background:#f0fdf4 !important; }
        .pagination-bar { display:flex; align-items:center; justify-content:space-between; margin-top:0.75rem; flex-wrap:wrap; gap:0.5rem; font-size:0.82rem; color:var(--text-gray); }
        .pagination-bar button { padding:0.35rem 0.75rem; border:1px solid var(--border-color); border-radius:6px; background:var(--white); cursor:pointer; font-size:0.82rem; }
        .pagination-bar button:hover:not(:disabled) { border-color:var(--bpc-green); color:var(--bpc-green); }
        .pagination-bar button:disabled { opacity:0.5; cursor:not-allowed; }

        .submit-row { display:flex; align-items:center; justify-content:flex-end; gap:1rem; margin-top:1.5rem; padding-top:1.25rem; border-top:2px solid var(--bg-light); flex-wrap:wrap; }
        .submit-info { font-size:0.82rem; color:var(--text-gray); flex:1; }

        .btn { display:inline-flex; align-items:center; gap:0.5rem; padding:0.75rem 1.5rem; border:none; border-radius:6px; font-size:0.875rem; font-weight:600; cursor:pointer; transition:all 0.2s; text-decoration:none; }
        .btn-green  { background:var(--bpc-green); color:#fff; }
        .btn-green:hover { background:var(--bpc-green-dark); }
        .btn-green:disabled { background:#9ca3af; cursor:not-allowed; }
        .btn-sm { padding:0.4rem 0.875rem; font-size:0.78rem; }
        .btn-outline { background:transparent; border:2px solid var(--bpc-green); color:var(--bpc-green); }
        .btn-outline:hover { background:var(--bpc-green); color:#fff; }

        /* ALREADY SCHEDULED TABLE */
        th { background:var(--bg-light); color:var(--text-gray); font-size:0.72rem; text-transform:uppercase; letter-spacing:0.5px; padding:0.75rem 1rem; text-align:left; border-bottom:2px solid var(--border-color); white-space:nowrap; }
        td { padding:0.875rem 1rem; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
        tr:last-child td { border-bottom:none; }
        tr:hover td { background:#fafafa; }
        .name-strong { font-weight:700; display:block; }
        .name-sub    { font-size:0.75rem; color:var(--text-gray); }
        .ref-mono    { font-family:monospace; font-size:0.78rem; color:var(--text-gray); }
        .date-col    { font-size:0.82rem; white-space:nowrap; }
        .date-col small { color:var(--text-gray); display:block; }
        .badge { display:inline-block; padding:0.2rem 0.6rem; border-radius:10px; font-size:0.72rem; font-weight:700; white-space:nowrap; }
        .b-scheduled { background:#d1ecf1; color:#0c5460; }
        .b-completed { background:#d4edda; color:#155724; }
        .b-failed    { background:#f8d7da; color:#721c24; }
        .b-noshow    { background:#e2e3e5; color:#383d41; }
        .empty-state { text-align:center; padding:3rem; color:var(--text-gray); }
        .warning-box { background:#fff3cd; border:1px solid #ffc107; border-radius:6px; padding:0.75rem 1rem; font-size:0.82rem; color:#856404; }

        @media(max-width:960px) {
            .sidebar { display:none; }
            .form-grid { grid-template-columns:1fr 1fr; }
            .form-grid .span-3 { grid-column:span 2; }
            .applicant-grid { grid-template-columns:1fr 1fr; }
        }
        @media(max-width:600px) {
            .form-grid, .form-grid .span-2, .form-grid .span-3 { grid-template-columns:1fr; grid-column:span 1; }
            .applicant-grid { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>

<?php include '../shared/admin-sidebar.php'; ?>

<main class="main">
    <div class="top-nav">
        <h1>Exam Scheduling</h1>
        <span>Logged in as <strong><?php echo htmlspecialchars($_SESSION['admin_name']); ?></strong></span>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="ched-notice">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
        <span>Showing <strong>CHED applicants only</strong> — TESDA applicants skip the exam and go directly to interview scheduling.</span>
    </div>

    <form method="POST" action="admin-set-exam.php" id="scheduleForm">

        <!-- ── CARD 1: Previous Schedules ── -->
        <?php if (!empty($schedules)): ?>
        <div class="card">
            <div class="card-title">
                Previous Exam Schedules
                <span><?php echo count($schedules); ?> schedule(s)</span>
            </div>
            <div class="table-wrapper">
                <table class="sched-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Time</th>
                            <th>Venue</th>
                            <th>Type</th>
                            <th>Exam Mode</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $i => $sc): ?>
                        <tr>
                            <td style="color:var(--text-gray);font-size:0.78rem;"><?php echo $i + 1; ?></td>
                            <td style="font-weight:600;white-space:nowrap;"><?php echo date('M d, Y', strtotime($sc['exam_date'])); ?></td>
                            <td style="color:var(--text-gray);"><?php echo date('l', strtotime($sc['exam_date'])); ?></td>
                            <td style="white-space:nowrap;"><?php echo date('g:i A', strtotime($sc['exam_time'])); ?></td>
                            <td><?php echo htmlspecialchars($sc['exam_venue']); ?></td>
                            <td>
                                <span class="sched-type-badge <?php echo ($sc['schedule_type'] ?? 'Regular') === 'Makeup' ? 'sched-type-makeup' : 'sched-type-regular'; ?>">
                                    <?php echo ($sc['schedule_type'] ?? 'Regular') === 'Makeup' ? 'Makeup' : 'Regular'; ?>
                                </span>
                            </td>
                            <td style="font-size:0.82rem;"><?php echo htmlspecialchars($sc['exam_type'] ?? '—'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── CARD 2: Schedule Form ── -->
        <div class="card">
            <div class="card-title">
                Set New Exam Schedule
                <span><?php echo count($unscheduled); ?> CHED applicant(s) available</span>
            </div>

            <?php if (empty($unscheduled)): ?>
                <div class="warning-box" style="margin-bottom:1rem;">
                    ℹ No CHED applicants with <strong>Documents Verified</strong> status available right now.
                </div>
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-group">
                    <label>Exam Date *</label>
                    <input type="date" name="exam_date" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                </div>
                <div class="form-group">
                    <label>Exam Time *</label>
                    <input type="time" name="exam_time" required>
                </div>
                <div class="form-group">
                    <label>Schedule Type</label>
                    <select name="schedule_type">
                        <option value="Regular">Regular</option>
                        <option value="Makeup">Makeup Exam</option>
                    </select>
                </div>
                <div class="form-group span-2">
                    <label>Venue / Location *</label>
                    <input type="text" name="exam_venue" placeholder="e.g. BPC Main Building, Room 201" required>
                </div>
                <div class="form-group">
                    <label>Exam Type</label>
                    <select name="exam_type">
                        <option value="Physical">Physical (On-site)</option>
                        <option value="Online">Online</option>
                    </select>
                </div>
                <div class="form-group span-3"><!-- spacer --></div>
                <div class="form-group span-3">
                    <label>Instructions for Applicants</label>
                    <textarea name="instructions" rows="8" placeholder="Edit or add to the default instructions below."><?php echo htmlspecialchars($default_instructions); ?></textarea>
                </div>
            </div>
        </div>

        <!-- ── CARD 3: Applicant Selection ── -->
        <div class="card">
            <div class="card-title">
                Select CHED Applicants for This Schedule
                <span class="selected-badge" id="selectedBadge">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                    <span id="countText">0 selected</span>
                </span>
            </div>

            <?php if (empty($unscheduled)): ?>
                <div class="empty-check">No CHED applicants available to schedule.</div>
            <?php else: ?>
                <div class="checklist-toolbar">
                    <div class="checklist-toolbar-left">
                        <span><?php echo count($unscheduled); ?> CHED applicant(s) with <strong>Documents Verified</strong></span>
                    </div>
                    <div class="checklist-toolbar-right">
                        <button type="button" class="btn btn-sm btn-outline" onclick="selectAllVisible()">✓ Select All (this page)</button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="deselectAll()">✕ Deselect All</button>
                    </div>
                </div>

                <div class="filter-row">
                    <label for="searchApplicants">Search:</label>
                    <input type="search" id="searchApplicants" placeholder="Search by name or email..." oninput="applyFilters()">
                    <label for="filterProgram">Program:</label>
                    <select id="filterProgram" onchange="applyFilters()">
                        <option value="">All Programs</option>
                        <?php foreach ($programs_for_filter as $p): ?>
                        <option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="filterType">Type:</label>
                    <select id="filterType" onchange="applyFilters()">
                        <option value="">All</option>
                        <option value="Freshmen">Freshmen</option>
                        <option value="Transferee">Transferee</option>
                    </select>
                    <label class="filter-checkbox">
                        <input type="checkbox" id="filterSelectedOnly" onchange="applyFilters()">
                        Selected only
                    </label>
                </div>

                <div class="pagination-bar" id="paginationBar" style="display:none;">
                    <span id="paginationText">Showing 1–30 of 0</span>
                    <span>
                        <button type="button" id="prevPage" onclick="goToPage(currentPage - 1)">← Previous</button>
                        <button type="button" id="nextPage" onclick="goToPage(currentPage + 1)" style="margin-left:0.35rem;">Next →</button>
                    </span>
                </div>

                <div class="applicant-grid" id="applicantGrid">
                    <?php foreach ($unscheduled as $u): ?>
                    <label class="applicant-card" id="card_<?php echo $u['id']; ?>"
                           data-name="<?php echo htmlspecialchars(strtolower($u['last_name'].' '.$u['first_name'])); ?>"
                           data-email="<?php echo htmlspecialchars(strtolower($u['email'] ?? '')); ?>"
                           data-ref="<?php echo htmlspecialchars(strtolower($u['reference_number'] ?? '')); ?>"
                           data-program="<?php echo htmlspecialchars($u['first_choice'] ?? ''); ?>"
                           data-type="<?php echo htmlspecialchars($u['applicant_type'] ?? ''); ?>">
                        <input type="checkbox" name="applicant_ids[]" value="<?php echo $u['id']; ?>" onchange="toggleCard(this)">
                        <div class="app-info">
                            <div class="app-name">
                                <a href="admin-application-detail.php?id=<?php echo (int)$u['id']; ?>" class="app-name-link"
                                   onclick="event.preventDefault();event.stopPropagation();window.location=this.href;">
                                    <?php echo htmlspecialchars($u['last_name'].', '.$u['first_name']); ?>
                                </a>
                            </div>
                            <div class="app-ref"><?php echo htmlspecialchars($u['reference_number'] ?? '—'); ?></div>
                            <div>
                                <span class="app-prog"><?php echo htmlspecialchars($u['first_choice'] ?? '—'); ?></span>
                                <span class="app-type"><?php echo htmlspecialchars($u['applicant_type'] ?? ''); ?></span>
                            </div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="submit-row">
                <div class="submit-info">
                    Selected applicants will be moved to <strong>Exam Scheduled</strong> status
                    and will see the exam details on their dashboard.
                </div>
                <button type="submit" class="btn btn-green" id="submitBtn" <?php echo empty($unscheduled) ? 'disabled' : ''; ?>>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
                    Schedule Selected CHED Applicants
                </button>
            </div>
        </div>

    </form>

    <!-- ── CARD 4: Already Scheduled Table ── -->
    <div class="card">
        <div class="card-title">
            Scheduled CHED Applicants
            <span><?php echo count($already); ?> total</span>
        </div>

        <?php if (empty($already)): ?>
            <div class="empty-state">📋 No CHED applicants scheduled yet.</div>
        <?php else: ?>
            <div class="filter-row" style="margin-bottom:1rem;">
                <label for="searchAlready">Search:</label>
                <input type="search" id="searchAlready" placeholder="Search by name or email..." style="min-width:200px;">
                <label for="filterAlreadyProgram">Program:</label>
                <select id="filterAlreadyProgram">
                    <option value="">All Programs</option>
                    <?php foreach ($already_programs as $p): ?>
                    <option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="table-wrapper">
                <table id="alreadyTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Applicant</th>
                            <th>Reference #</th>
                            <th>Program</th>
                            <th>Exam Date</th>
                            <th>Schedule</th>
                            <th>Venue</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($already as $i => $a):
                            $badge = match($a['status']) {
                                'Exam Scheduled' => ['b-scheduled', 'Scheduled'],
                                'Exam Completed' => ['b-completed', 'Passed'],
                                'Exam Failed'    => ['b-failed',    'Failed'],
                                'Exam No Show'   => ['b-noshow',    'No Show'],
                                default          => ['b-scheduled',  $a['status']]
                            };
                            $nameSearch = strtolower(($a['last_name'].' '.$a['first_name'].' '.($a['email']??'')).' '.($a['reference_number']??''));
                        ?>
                        <tr class="row-clickable already-row"
                            data-name="<?php echo htmlspecialchars($nameSearch); ?>"
                            data-program="<?php echo htmlspecialchars($a['first_choice'] ?? ''); ?>"
                            onclick="window.location='admin-application-detail.php?id=<?php echo (int)$a['id']; ?>'">
                            <td style="color:var(--gray);font-size:0.78rem;"><?php echo $i + 1; ?></td>
                            <td>
                                <span class="name-strong"><?php echo htmlspecialchars($a['last_name'].', '.$a['first_name']); ?></span>
                                <?php if (!empty($a['email'])): ?><span class="name-sub"><?php echo htmlspecialchars($a['email']); ?></span><?php endif; ?>
                            </td>
                            <td class="ref-mono"><?php echo htmlspecialchars($a['reference_number'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($a['first_choice'] ?? '—'); ?></td>
                            <td class="date-col">
                                <?php if (!empty($a['exam_date'])): ?>
                                    <?php echo date('M d, Y', strtotime($a['exam_date'])); ?>
                                    <small><?php echo date('g:i A', strtotime($a['exam_time'])); ?></small>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td style="font-size:0.82rem;"><?php echo (($a['schedule_type'] ?? 'Regular') === 'Makeup' ? 'Makeup' : 'Regular'); ?></td>
                            <td style="font-size:0.82rem;"><?php echo htmlspecialchars($a['exam_venue'] ?? '—'); ?></td>
                            <td><span class="badge <?php echo $badge[0]; ?>"><?php echo $badge[1]; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top:1.25rem;text-align:right;">
                <a href="admin-exam-results.php" class="btn btn-outline">Go to Exam Results →</a>
            </div>
        <?php endif; ?>
    </div>

</main>

<script>
var PAGE_SIZE = 30;
var currentPage = 1;
var filteredCards = [];

function toggleCard(checkbox) {
    var card = checkbox.closest('.applicant-card');
    if (checkbox.checked) card.classList.add('selected');
    else card.classList.remove('selected');
    updateCount();
}

function updateCount() {
    var count = document.querySelectorAll('input[name="applicant_ids[]"]:checked').length;
    document.getElementById('countText').textContent = count + ' selected';
    document.getElementById('submitBtn').disabled = count === 0;
}

function applyFilters() {
    var search       = (document.getElementById('searchApplicants').value || '').toLowerCase().trim();
    var program      = (document.getElementById('filterProgram').value || '').trim();
    var type         = (document.getElementById('filterType').value || '').trim();
    var selectedOnly = document.getElementById('filterSelectedOnly').checked;
    var cards        = document.querySelectorAll('#applicantGrid .applicant-card');
    filteredCards    = [];

    cards.forEach(function(card) {
        var name       = (card.getAttribute('data-name')    || '');
        var email      = (card.getAttribute('data-email')   || '');
        var ref        = (card.getAttribute('data-ref')     || '');
        var cardProg   = (card.getAttribute('data-program') || '');
        var cardType   = (card.getAttribute('data-type')    || '');
        var isSelected = card.classList.contains('selected');

        var match = (!search || name.indexOf(search) !== -1 || email.indexOf(search) !== -1 || ref.indexOf(search) !== -1)
                 && (!program || cardProg === program)
                 && (!type    || cardType === type)
                 && (!selectedOnly || isSelected);

        card.classList.toggle('hidden-by-filter', !match);
        if (match) filteredCards.push(card);
    });

    currentPage = 1;
    showPage();
}

function showPage() {
    var total      = filteredCards.length;
    var totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
    currentPage    = Math.max(1, Math.min(currentPage, totalPages));
    var start      = (currentPage - 1) * PAGE_SIZE;
    var end        = Math.min(start + PAGE_SIZE, total);

    filteredCards.forEach(function(card, i) {
        card.classList.toggle('hidden-by-page', i < start || i >= end);
    });

    var bar     = document.getElementById('paginationBar');
    var text    = document.getElementById('paginationText');
    var prevBtn = document.getElementById('prevPage');
    var nextBtn = document.getElementById('nextPage');

    if (total === 0) { bar.style.display = 'none'; return; }
    bar.style.display = 'flex';
    text.textContent  = 'Showing ' + (start + 1) + '–' + end + ' of ' + total;
    prevBtn.disabled  = currentPage <= 1;
    nextBtn.disabled  = currentPage >= totalPages;
}

function goToPage(page) {
    var totalPages = Math.max(1, Math.ceil(filteredCards.length / PAGE_SIZE));
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    showPage();
}

function selectAllVisible() {
    document.querySelectorAll('#applicantGrid .applicant-card:not(.hidden-by-filter):not(.hidden-by-page)').forEach(function(card) {
        var cb = card.querySelector('input[name="applicant_ids[]"]');
        if (cb) { cb.checked = true; card.classList.add('selected'); }
    });
    updateCount();
}

function deselectAll() {
    document.querySelectorAll('input[name="applicant_ids[]"]').forEach(function(cb) { cb.checked = false; });
    document.querySelectorAll('.applicant-card').forEach(function(card) { card.classList.remove('selected'); });
    updateCount();
}

function filterAlreadyTable() {
    var search  = (document.getElementById('searchAlready').value || '').toLowerCase().trim();
    var program = (document.getElementById('filterAlreadyProgram').value || '').trim();
    document.querySelectorAll('#alreadyTable .already-row').forEach(function(row) {
        var name       = (row.getAttribute('data-name')    || '');
        var rowProgram = (row.getAttribute('data-program') || '');
        var show = (!search || name.indexOf(search) !== -1) && (!program || rowProgram === program);
        row.style.display = show ? '' : 'none';
    });
}

document.getElementById('searchAlready')?.addEventListener('input', filterAlreadyTable);
document.getElementById('filterAlreadyProgram')?.addEventListener('change', filterAlreadyTable);

document.addEventListener('DOMContentLoaded', function() {
    applyFilters();
    filterAlreadyTable();
});

document.getElementById('scheduleForm').addEventListener('submit', function(e) {
    var count = document.querySelectorAll('input[name="applicant_ids[]"]:checked').length;
    if (count === 0) {
        e.preventDefault();
        alert('Please select at least one applicant to schedule.');
        return false;
    }
    return confirm('Schedule exam for ' + count + ' CHED applicant(s)? They will see the exam details on their dashboard.');
});
</script>

</body>
</html>