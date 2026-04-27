<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Exam Scores View (Program Heads — read only)
 * app/admin/admin-exam-view.php
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/admin-auth-check.php';
require_once CONFIG_PATH . '/admin-permissions.php';
require_once CONFIG_PATH . '/programs.php';

// Program heads and above can view; only officers/super_admin can encode
if (!admin_can_view_exams()) {
    $_SESSION['admin_error'] = 'You do not have permission to view exam scores.';
    header('Location: ' . BASE_URL . '/app/admin/admin-dashboard.php');
    exit;
}

// Build department filter for program heads
[$filter_clause, $filter_params] = get_head_program_filter($conn);

// Fetch applicants who have completed the exam, filtered by assigned programs
$sql = "SELECT a.id, a.reference_number,
               CONCAT(a.first_name, ' ', a.last_name) AS full_name,
               a.first_choice, a.second_choice, a.program_category,
               a.exam_score, a.status,
               es.exam_date, es.exam_time
        FROM applications a
        LEFT JOIN exam_schedules es ON a.exam_schedule_id = es.id
        WHERE a.status IN ('Exam Completed','Exam Failed','Exam No Show','No Show',
                   'Interview Scheduled','Interview Completed',
                   'Admitted/Enrolled','Rejected',
                   'Awaiting Applicant Decision','Application Withdrawn')
        AND a.program_category = 'CHED'
        {$filter_clause}
        ORDER BY a.last_name ASC, a.first_name ASC";

$applicants = [];

if (!empty($filter_params)) {
    $stmt = mysqli_prepare($conn, $sql);
    // Build bind types: all strings
    $types = str_repeat('s', count($filter_params));
    mysqli_stmt_bind_param($stmt, $types, ...$filter_params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) $applicants[] = $row;
    mysqli_stmt_close($stmt);
} else {
    $res = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($res)) $applicants[] = $row;
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/admin-styles.css">
    <title>Exam Scores - Admin | BPC iEnroll</title>
    <style>
        .page-header h1 { font-size:1.5rem; }
        .page-header p  { color:var(--text-gray); }
        .card { background:var(--white); border-radius:10px; padding:1.75rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); margin-bottom:1.5rem; }
        table { width:100%; border-collapse:collapse; font-size:0.875rem; }
        thead th { background:var(--bg-light); color:var(--text-gray); font-size:0.72rem; text-transform:uppercase; letter-spacing:0.04em; padding:0.75rem 1rem; text-align:left; border-bottom:2px solid var(--border-color); white-space:nowrap; }
        tbody td { padding:0.875rem 1rem; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
        tbody tr:hover td { background:#fafafa; }
        .badge { font-size:0.72rem; font-weight:700; padding:0.2rem 0.6rem; border-radius:20px; white-space:nowrap; }
        .badge-pass { background:#d4edda; color:#155724; }
        .badge-fail { background:#f8d7da; color:#721c24; }
        .badge-noshow { background:#fff3cd; color:#856404; }
        .score-cell { font-weight:700; font-size:1rem; }
        .score-pass { color:var(--bpc-green); }
        .score-fail { color:#dc3545; }
        .empty-state { text-align:center; padding:2.5rem; color:var(--text-gray); }
        .info-banner { background:#e3f2fd; border:1px solid #90caf9; border-radius:8px; padding:0.875rem 1.25rem; margin-bottom:1.25rem; font-size:0.875rem; color:#1565c0; }

        /* STAT STRIP */
        .stats-strip { display:grid; grid-template-columns:repeat(5,1fr); gap:0.875rem; margin-bottom:1.5rem; }
        .stat-chip { background:var(--white); border-radius:10px; padding:1rem 1.25rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); border-left:4px solid var(--border-color); }
        .stat-chip .num { font-size:1.75rem; font-weight:800; line-height:1; }
        .stat-chip .lbl { font-size:0.72rem; color:var(--text-gray); margin-top:0.3rem; }
        .stat-chip.green  { border-color:#198754; } .stat-chip.green .num  { color:#198754; }
        .stat-chip.red    { border-color:#dc3545; } .stat-chip.red .num    { color:#dc3545; }
        .stat-chip.gray   { border-color:#6c757d; } .stat-chip.gray .num   { color:#6c757d; }
        .stat-chip.amber  { border-color:#f9a825; } .stat-chip.amber .num  { color:#f9a825; }
        .stat-chip.muted  { border-color:#adb5bd; } .stat-chip.muted .num  { color:#adb5bd; }

        /* FILTER BAR */
        .filter-bar { display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap; margin-bottom:1.25rem; }
        .filter-bar input[type="search"],
        .filter-bar select { padding:0.5rem 0.875rem; border:2px solid var(--border-color); border-radius:6px; font-size:0.875rem; font-family:inherit; background:#fff; }
        .filter-bar input[type="search"] { min-width:220px; }
        .filter-bar input[type="search"]:focus,
        .filter-bar select:focus { outline:none; border-color:var(--bpc-green); }
        .filter-bar label { font-size:0.82rem; font-weight:600; color:var(--text-dark); white-space:nowrap; }

        

        /* STATUS BADGE COLORS */
        .badge-status { display:inline-block; padding:0.2rem 0.6rem; border-radius:10px; font-size:0.72rem; font-weight:700; white-space:nowrap; color:#fff; }
        .bs-exam-completed   { background:#198754; }
        .bs-exam-failed      { background:#dc3545; }
        .bs-noshow           { background:#6c757d; }
        .bs-tesda-pending    { background:#f9a825; color:#1a1a1a; }
        .bs-withdrawn        { background:#adb5bd; color:#1a1a1a; }
        .bs-interview-sched  { background:#6f42c1; }
        .bs-interview-done   { background:#0b7285; }
        .bs-admitted         { background:#198754; }
        .bs-rejected         { background:#dc3545; }
        .bs-default          { background:#6c757d; }

        /* DATE DIVIDER */
        .date-divider td { background:#f4f6f8; font-size:0.72rem; font-weight:700; color:var(--text-gray); text-transform:uppercase; letter-spacing:0.5px; padding:0.4rem 1rem; border-bottom:1px solid var(--border-color); }

        @media(max-width:900px) { .stats-strip { grid-template-columns:repeat(3,1fr); } }
        @media(max-width:580px) { .stats-strip { grid-template-columns:1fr 1fr; } }
    </style>
</head>
<body>
    <?php include '../shared/admin-sidebar.php'; ?>

    <main class="main">
        <div class="page-header">
            <h1>Exam Scores</h1>
            <p>View entrance exam results for applicants in your assigned programs.</p>
        </div>

        <div class="info-banner">
            This is a <strong>read-only</strong> view. Exam scores are encoded by the Admission Officer.
            You can use these scores to inform your interview decisions.
        </div>

        <?php
            /* ── Compute stats ── */
            $cnt_passed   = 0; $cnt_failed  = 0; $cnt_noshow = 0;
            $cnt_tesda    = 0; $cnt_withdrawn = 0;
            foreach ($applicants as $a) {
                if ($a['status'] === 'Exam Completed')              $cnt_passed++;
                elseif ($a['status'] === 'Exam Failed')             $cnt_failed++;
                elseif (in_array($a['status'], ['Exam No Show','No Show'])) $cnt_noshow++;
                elseif ($a['status'] === 'Awaiting Applicant Decision')     $cnt_tesda++;
                elseif ($a['status'] === 'Application Withdrawn')           $cnt_withdrawn++;
            }

            /* ── Build program list for filter ── */
            $program_options = array_values(array_unique(array_filter(
                array_map(fn($a) => get_program_label($a['first_choice'], null), $applicants)
            )));
            sort($program_options);
            ?>

            <!-- STAT STRIP -->
            <div class="stats-strip">
                <div class="stat-chip green">
                    <div class="num"><?php echo $cnt_passed; ?></div>
                    <div class="lbl">Passed</div>
                </div>
                <div class="stat-chip red">
                    <div class="num"><?php echo $cnt_failed; ?></div>
                    <div class="lbl">Failed</div>
                </div>
                <div class="stat-chip gray">
                    <div class="num"><?php echo $cnt_noshow; ?></div>
                    <div class="lbl">No Show</div>
                </div>
                <div class="stat-chip amber">
                    <div class="num"><?php echo $cnt_tesda; ?></div>
                    <div class="lbl">TESDA Offer Pending</div>
                </div>
                <div class="stat-chip muted">
                    <div class="num"><?php echo $cnt_withdrawn; ?></div>
                    <div class="lbl">Withdrawn</div>
                </div>
            </div>

            <div class="card">
                <!-- FILTER BAR -->
                <div class="filter-bar">
                    <label for="examSearch">Search:</label>
                    <input type="search" id="examSearch" placeholder="Name or reference #...">
                    <label for="examProgram">Program:</label>
                    <select id="examProgram">
                        <option value="">All Programs</option>
                        <?php foreach ($program_options as $p): ?>
                        <option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="examResult">Result:</label>
                    <select id="examResult">
                        <option value="">All Results</option>
                        <option value="passed">Passed</option>
                        <option value="failed">Failed</option>
                        <option value="noshow">No Show</option>
                        <option value="tesda">TESDA Offer Pending</option>
                        <option value="withdrawn">Withdrawn</option>
                    </select>
                </div>

                <?php if (empty($applicants)): ?>
                <div class="empty-state">No exam results available for your assigned programs yet.</div>
                <?php else: ?>
                <div class="table-wrapper">
                    <table id="examViewTable">
                        <thead>
                            <tr>
                                <th>Reference #</th>
                                <th>Applicant</th>
                                <th>Program</th>
                                <th>Exam Date</th>
                                <th>Score</th>
                                <th>Result</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $last_date = null;
                        foreach ($applicants as $a):
                            $score     = $a['exam_score'];
                            $is_noshow = in_array($a['status'], ['No Show','Exam No Show']);
                            $is_tesda  = ($a['status'] === 'Awaiting Applicant Decision');
                            $is_withdrawn = ($a['status'] === 'Application Withdrawn');

                            // Determine result key for JS filtering
                            if ($is_noshow)      $result_key = 'noshow';
                            elseif ($is_tesda)   $result_key = 'tesda';
                            elseif ($is_withdrawn) $result_key = 'withdrawn';
                            elseif ($a['status'] === 'Exam Completed') $result_key = 'passed';
                            else                 $result_key = 'failed';

                            // Score bar color
                            $bar_color = '#6c757d';
                            $score_color = 'var(--text-gray)';
                            if (!$is_noshow && $score !== null) {
                                if ($a['status'] === 'Exam Completed') {
                                    $bar_color = '#198754'; $score_color = '#198754';
                                } else {
                                    $bar_color = '#dc3545'; $score_color = '#dc3545';
                                }
                            }

                            // Status badge
                            $status_badge = match($a['status']) {
                                'Exam Completed'             => ['bs-exam-completed',  'Exam Passed'],
                                'Exam Failed'                => ['bs-exam-failed',     'Exam Failed'],
                                'Exam No Show','No Show'     => ['bs-noshow',          'No Show'],
                                'Awaiting Applicant Decision'=> ['bs-tesda-pending',   'TESDA Offer'],
                                'Application Withdrawn'      => ['bs-withdrawn',       'Withdrawn'],
                                'Interview Scheduled'        => ['bs-interview-sched', 'Interview Scheduled'],
                                'Interview Completed'        => ['bs-interview-done',  'Interview Completed'],
                                'Admitted/Enrolled'          => ['bs-admitted',        'Admitted'],
                                'Rejected'                   => ['bs-rejected',        'Rejected'],
                                default                      => ['bs-default',         $a['status']]
                            };

                            // Date divider
                            $exam_date_str = !empty($a['exam_date']) ? date('M d, Y', strtotime($a['exam_date'])) : null;
                            $search_str = strtolower(
                                ($a['full_name'] ?? '') . ' ' .
                                ($a['reference_number'] ?? '') . ' ' .
                                get_program_label($a['first_choice'], null)
                            );
                            $program_label = get_program_label($a['first_choice'], null);
                        ?>
                        <?php if ($exam_date_str && $exam_date_str !== $last_date): $last_date = $exam_date_str; ?>
                        <tr class="date-divider exam-divider-row">
                            <td colspan="7"><?php echo $exam_date_str; ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="exam-view-row"
                            data-search="<?php echo htmlspecialchars($search_str); ?>"
                            data-program="<?php echo htmlspecialchars($program_label); ?>"
                            data-result="<?php echo $result_key; ?>"
                            onclick="window.location='admin-application-detail.php?id=<?php echo (int)$a['id']; ?>'">
                            <td><code class="ref-number"><?php echo htmlspecialchars($a['reference_number'] ?? '—'); ?></code></td>
                            <td><?php echo htmlspecialchars($a['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($program_label); ?></td>
                            <td style="font-size:0.82rem;white-space:nowrap;"><?php echo $exam_date_str ?? '—'; ?></td>
                            <td>
                                <?php if ($is_noshow || $score === null): ?>
                                <span style="color:var(--text-gray);">—</span>
                                <?php else: ?>
                                    <span style="font-weight:800;color:<?php echo $score_color; ?>"><?php echo (int)$score; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_noshow): ?>
                                <span class="badge badge-noshow">No Show</span>
                                <?php elseif ($score === null && !$is_tesda && !$is_withdrawn): ?>
                                <span style="color:var(--text-gray);font-size:0.8rem;">Pending</span>
                                <?php elseif ($a['status'] === 'Exam Completed'): ?>
                                <span class="badge badge-pass">Passed</span>
                                <?php elseif ($is_tesda): ?>
                                <span class="badge badge-noshow" style="background:#fff3cd;color:#856404;">TESDA Offer Pending</span>
                                <?php elseif ($is_withdrawn): ?>
                                <span class="badge" style="background:#e2e3e5;color:#383d41;">Withdrawn</span>
                                <?php else: ?>
                                <span class="badge badge-fail">Failed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge-status <?php echo $status_badge[0]; ?>">
                                    <?php echo htmlspecialchars($status_badge[1]); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
    </main>

    <script>
        function filterExamView() {
            const search  = (document.getElementById('examSearch')?.value  || '').toLowerCase().trim();
            const program = (document.getElementById('examProgram')?.value || '').trim();
            const result  = (document.getElementById('examResult')?.value  || '').trim();

            let visibleCount = 0;
            let lastVisibleDate = null;

            // First pass: determine which data rows are visible
            const rows = document.querySelectorAll('.exam-view-row');
            rows.forEach(row => {
                const rowSearch  = (row.dataset.search  || '');
                const rowProgram = (row.dataset.program || '');
                const rowResult  = (row.dataset.result  || '');
                const show = (!search  || rowSearch.includes(search)) &&
                            (!program || rowProgram === program) &&
                            (!result  || rowResult  === result);
                row.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            });

            // Second pass: show/hide date dividers based on whether any row below them is visible
            const allRows = document.querySelectorAll('#examViewTable tbody tr');
            let currentDivider = null;
            let dividerHasVisible = false;

            allRows.forEach(row => {
                if (row.classList.contains('exam-divider-row')) {
                    if (currentDivider) currentDivider.style.display = dividerHasVisible ? '' : 'none';
                    currentDivider = row;
                    dividerHasVisible = false;
                } else if (row.classList.contains('exam-view-row')) {
                    if (row.style.display !== 'none') dividerHasVisible = true;
                }
            });
            if (currentDivider) currentDivider.style.display = dividerHasVisible ? '' : 'none';
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('examSearch')?.addEventListener('input',  filterExamView);
            document.getElementById('examProgram')?.addEventListener('change', filterExamView);
            document.getElementById('examResult')?.addEventListener('change',  filterExamView);
        });
    </script>
</body>
</html>