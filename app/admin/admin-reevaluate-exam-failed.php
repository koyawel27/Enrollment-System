<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin - Re-evaluate Exam Failed applicants (one-time fix)
 *
 * Finds CHED applicants who were marked "Exam Failed" before per-program cutoffs
 * were applied. If their score would now qualify them for 1st, 2nd, or TESDA 3rd
 * choice under current cutoffs, updates their status and sends a correction message.
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/admin-auth-check.php';
require_once CONFIG_PATH . '/admin-permissions.php';
require_admin_role([ADMIN_ROLE_SUPER_ADMIN, ADMIN_ROLE_ADMISSION_OFFICER]);
require_once CONFIG_PATH . '/applicant-messages.php';
require_once CONFIG_PATH . '/programs.php';
require_once CONFIG_PATH . '/program-cutoffs.php';
require_once APP_PATH . '/shared/MailService.php';

$mailService = new MailService();

$marked_by = $_SESSION['admin_name'];

// ── FETCH EXAM FAILED WITH A SCORE (CHED only) ─────────────────────────────
$sql = "SELECT a.id, a.user_id, a.first_name, a.last_name, a.reference_number,
               a.first_choice, a.second_choice, a.third_choice, a.program_category,
               a.exam_score
        FROM applications a
        WHERE a.status = 'Exam Failed'
          AND a.exam_score IS NOT NULL
          AND (a.program_category = 'CHED' OR a.program_category IS NULL)";
$res = mysqli_query($conn, $sql);
$candidates = [];
while ($row = mysqli_fetch_assoc($res)) {
    $score = (int)$row['exam_score'];
    $assignment = determine_program_assignment($row, $score, $CHED_PROGRAM_CUTOFFS);
    if ($assignment['final_status'] !== 'Exam Failed') {
        $row['_assignment'] = $assignment;
        $candidates[] = $row;
    }
}

// ── POST: run re-evaluation ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($candidates)) {
    $upd = mysqli_prepare($conn,
        "UPDATE applications SET
            status = ?, program_category = ?, assigned_program = ?, updated_at = NOW()
         WHERE id = ? AND status = 'Exam Failed'"
    );
    $log = mysqli_prepare($conn,
        "INSERT INTO status_history (application_id, old_status, new_status, changed_by, notes)
         VALUES (?, 'Exam Failed', ?, ?, ?)"
    );
    $mailFetch = mysqli_prepare($conn,
        "SELECT u.email, a.first_name, a.last_name, a.reference_number
         FROM applications a
         JOIN users u ON a.user_id = u.id
         WHERE a.id = ?
         LIMIT 1"
    );

    $fixed = 0;
    foreach ($candidates as $row) {
        $a = $row['_assignment'];
        $app_id = (int)$row['id'];
        $user_id = (int)$row['user_id'];
        $score = (int)$row['exam_score'];

        $final_status   = $a['final_status'];
        $final_category = $a['final_category'];
        $assigned       = $a['assigned_program'] ?? '';
        mysqli_stmt_bind_param($upd, 'sssi', $final_status, $final_category, $assigned, $app_id);
        mysqli_stmt_execute($upd);
        if (mysqli_stmt_affected_rows($upd) > 0) {
            $fixed++;
            $note = "Re-evaluated: score {$score} now qualifies for " . ($a['assigned_program'] ?? '') . ". By {$marked_by}.";
            $new_status = $a['final_status'];
            mysqli_stmt_bind_param($log, 'isss', $app_id, $new_status, $marked_by, $note);
            mysqli_stmt_execute($log);

            $prog_label = $a['assigned_program'] ? get_program_label($a['assigned_program'], $conn) : '';
            $msg_body = "Your application has been re-evaluated. Your exam score of {$score}/100 now qualifies you for: {$prog_label}. You may proceed to interview scheduling.";
            add_applicant_message($conn, $user_id, 'exam_passed', 'Re-evaluation: You now qualify', $msg_body);

            mysqli_stmt_bind_param($mailFetch, 'i', $app_id);
            mysqli_stmt_execute($mailFetch);
            $mailRow = mysqli_fetch_assoc(mysqli_stmt_get_result($mailFetch));
            if ($mailRow && !empty($mailRow['email'])) {
                $email = trim((string)$mailRow['email']);
                $fullName = trim((string)(($mailRow['first_name'] ?? '') . ' ' . ($mailRow['last_name'] ?? '')));
                $referenceNumber = (string)($mailRow['reference_number'] ?? '');
                try {
                    $mailService->sendExamPassedEmail($email, $fullName, $referenceNumber, $score);
                } catch (Exception $e) {
                    error_log('BPC iEnroll mail error: ' . $e->getMessage());
                }
            }
        }
    }
    mysqli_stmt_close($mailFetch);
    mysqli_stmt_close($upd);
    mysqli_stmt_close($log);
    mysqli_close($conn);

    $_SESSION['admin_success'] = "Re-evaluation complete. {$fixed} applicant(s) updated and notified.";
    header('Location: admin-exam-results.php');
    exit;
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Re-evaluate Exam Failed - Admin | BPC iEnroll</title>
    <link rel="stylesheet" href="../../assets/admin-styles.css">
    <style>
        .card { background: #fff; border-radius: 10px; padding: 1.75rem; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 1.5rem; }
        .card-title { font-size: 0.95rem; font-weight: 700; color: #004d00; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 2px solid #f4f6f8; }
        table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background: #f4f6f8; color: #666; font-size: 0.72rem; text-transform: uppercase; }
        .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; border: none; border-radius: 6px; font-size: 0.875rem; font-weight: 600; cursor: pointer; background: #006400; color: #fff; }
        .btn:hover { background: #004d00; }
        .btn-outline { background: transparent; border: 2px solid #006400; color: #006400; text-decoration: none; }
        .btn-outline:hover { background: #006400; color: #fff; }
        .empty { color: #666; font-style: italic; padding: 2rem; text-align: center; }
        .alert-success { background: #d4edda; color: #155724; padding: 0.875rem 1.25rem; border-radius: 6px; margin-bottom: 1rem; }
        .alert-info { background: #d1ecf1; color: #0c5460; padding: 0.875rem 1.25rem; border-radius: 6px; margin-bottom: 1rem; }
    </style>
</head>
<body>
<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';

include '../shared/admin-sidebar.php'; ?>
<main class="main">
    <div class="card">
        <div class="card-title">Re-evaluate Exam Failed (per-program cutoffs)</div>
        <p style="color:#666; font-size:0.9rem; margin-bottom:1rem;">
            Applicants below were marked <strong>Exam Failed</strong> before the system used per-program cutoffs.
            Their score would now qualify them for at least one of their choices. Re-evaluating will update their
            status and send them a correction message on their dashboard.
        </p>

        <?php
if (empty($candidates)): ?>
            <p class="empty">No Exam Failed applicants found who would now qualify. Nothing to fix.</p>
            <a href="admin-exam-results.php" class="btn btn-outline">← Back to Exam Results</a>
        <?php
else: ?>
            <p class="alert-info">
                <strong><?php
echo count($candidates); ?> applicant(s)</strong> would now pass under current program cutoffs.
            </p>
            <table>
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Reference</th>
                        <th>Score</th>
                        <th>1st / 2nd / 3rd</th>
                        <th>New status</th>
                        <th>Assigned program</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
foreach ($candidates as $r):
                        $a = $r['_assignment'];
                    ?>
                    <tr>
                        <td><?php
echo htmlspecialchars($r['last_name'] . ', ' . $r['first_name']); ?></td>
                        <td><?php
echo htmlspecialchars($r['reference_number'] ?? '—'); ?></td>
                        <td><?php
echo (int)$r['exam_score']; ?> / 100</td>
                        <td><?php
echo htmlspecialchars(($r['first_choice'] ?? '') . ' / ' . ($r['second_choice'] ?? '') . ' / ' . ($r['third_choice'] ?? '')); ?></td>
                        <td><?php
echo htmlspecialchars($a['final_status']); ?></td>
                        <td><?php
echo $a['assigned_program'] ? get_program_label($a['assigned_program'], $conn) : '—'; ?></td>
                    </tr>
                    <?php
endforeach; ?>
                </tbody>
            </table>
            <form method="post" style="margin-top:1.5rem;">
                <button type="submit" class="btn" onclick="return confirm('Update these <?php
echo count($candidates); ?> applicant(s) and send them a correction message?');">
                    Re-evaluate now and notify applicants
                </button>
                <a href="admin-exam-results.php" class="btn btn-outline" style="margin-left:0.75rem;">Cancel</a>
            </form>
        <?php
endif; ?>
    </div>
</main>
</body>
</html>