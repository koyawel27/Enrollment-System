<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin - Encode Interview Results Handler
 * admin-encode-interview-results.php
 *
 * FIX: Now scoped to a specific interview_date posted from the UI.
 * Only applicants with that interview_date are processed.
 * If no valid date is posted, the handler aborts immediately.
 *
 * - Present + Pass → Interview Completed
 * - Present + Fail → Rejected
 * - Not present    → Interview No Show
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
require_once CONFIG_PATH . '/applicant-messages.php';
require_once APP_PATH . '/shared/MailService.php';

$mailService = new MailService();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('app/admin/admin-interview-results.php');
}

// ── SCOPE GUARD: require a valid interview_date ────────────
// This is the core fix. Without a specific date, we cannot safely
// determine which applicants belong to the current interview batch.
$interview_date = isset($_POST['interview_date']) ? trim($_POST['interview_date']) : '';

if (empty($interview_date)) {
    $_SESSION['admin_error'] = 'No interview date was specified. Please select a specific date before encoding results.';
    redirect('app/admin/admin-interview-results.php');
}

// Basic date format validation (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $interview_date)) {
    $_SESSION['admin_error'] = 'Invalid interview date format. Please select a valid date and try again.';
    redirect('app/admin/admin-interview-results.php');
}

// ── Server-side guard: require at least one attendance mark ─
$present_ids = $_POST['present'] ?? [];
$results     = $_POST['result']  ?? [];

if (empty($present_ids) && empty($results)) {
    $_SESSION['admin_error'] = 'No attendance was recorded. Please mark at least one applicant as present or absent before confirming.';
    redirect('app/admin/admin-interview-results.php?interview_date=' . urlencode($interview_date));
}

[$head_filter, $head_params] = get_head_program_filter($conn);

$marked_by = $_SESSION['admin_name'];

// ── FETCH ONLY applicants scheduled for the specified date ─
// This is the critical scope fix — we only touch this day's batch.
$sql =
    "SELECT a.id, a.user_id FROM applications a
     WHERE a.status = 'Interview Scheduled'
       AND a.interview_date = ?
       {$head_filter}";

$combined_params = array_merge([$interview_date], $head_params);
$pq = mysqli_prepare($conn, $sql);
$types = str_repeat('s', count($combined_params));
mysqli_stmt_bind_param($pq, $types, ...$combined_params);
mysqli_stmt_execute($pq);
$pq_res = mysqli_stmt_get_result($pq);

$to_process = [];
while ($row = mysqli_fetch_assoc($pq_res)) {
    $to_process[(int)$row['id']] = (int)$row['user_id'];
}
mysqli_stmt_close($pq);

if (empty($to_process)) {
    $_SESSION['admin_error'] = 'No applicants found for this date. They may have already been processed. Please refresh and try again.';
    redirect('app/admin/admin-interview-results.php?interview_date=' . urlencode($interview_date));
}

// ── PREPARE STATEMENTS ─────────────────────────────────────
$upd_pass = mysqli_prepare($conn,
    "UPDATE applications SET
        status               = 'Interview Completed',
        interview_marked_by  = ?,
        interview_marked_at  = NOW(),
        updated_at           = NOW(),
        interview_score      = ?,
        interview_remarks    = ?
     WHERE id = ? AND status = 'Interview Scheduled'"
);

$upd_fail = mysqli_prepare($conn,
    "UPDATE applications SET
        status               = 'Rejected',
        interview_marked_by  = ?,
        interview_marked_at  = NOW(),
        updated_at           = NOW(),
        interview_score      = ?,
        interview_remarks    = ?
     WHERE id = ? AND status = 'Interview Scheduled'"
);

$upd_pass_noscore = mysqli_prepare($conn,
    "UPDATE applications SET
        status               = 'Interview Completed',
        interview_marked_by  = ?,
        interview_marked_at  = NOW(),
        updated_at           = NOW(),
        interview_score      = NULL,
        interview_remarks    = ?
     WHERE id = ? AND status = 'Interview Scheduled'"
);

$upd_fail_noscore = mysqli_prepare($conn,
    "UPDATE applications SET
        status               = 'Rejected',
        interview_marked_by  = ?,
        interview_marked_at  = NOW(),
        updated_at           = NOW(),
        interview_score      = NULL,
        interview_remarks    = ?
     WHERE id = ? AND status = 'Interview Scheduled'"
);

$upd_noshow = mysqli_prepare($conn,
    "UPDATE applications SET
        status               = 'Interview No Show',
        interview_marked_by  = ?,
        interview_marked_at  = NOW(),
        updated_at           = NOW(),
        interview_score      = NULL,
        interview_remarks    = ?
     WHERE id = ? AND status = 'Interview Scheduled'"
);

$log = mysqli_prepare($conn,
    "INSERT INTO status_history
        (application_id, old_status, new_status, changed_by, notes)
     VALUES (?, 'Interview Scheduled', ?, ?, ?)"
);

$mailFetch = mysqli_prepare($conn,
    "SELECT u.email, a.first_name, a.last_name, a.reference_number
     FROM applications a
     JOIN users u ON a.user_id = u.id
     WHERE a.id = ?
     LIMIT 1"
);

$cnt_pass = $cnt_fail = $cnt_noshow = 0;

foreach ($to_process as $app_id => $user_id) {
    $raw_score = $_POST['interview_score'][$app_id] ?? '';
    if ($raw_score === '' || $raw_score === null) {
        $score_val = null;
    } else {
        $score_val = max(0, min(100, (int)$raw_score));
    }

    $interview_remarks = trim((string)($_POST['interview_remarks'][$app_id] ?? ''));
    $is_present        = isset($present_ids[$app_id]);

    if ($is_present) {
        $outcome = $results[$app_id] ?? '';

        if ($outcome === 'pass') {
            if ($score_val === null) {
                mysqli_stmt_bind_param($upd_pass_noscore, 'ssi', $marked_by, $interview_remarks, $app_id);
                mysqli_stmt_execute($upd_pass_noscore);
                $pass_stmt = $upd_pass_noscore;
            } else {
                mysqli_stmt_bind_param($upd_pass, 'sisi', $marked_by, $score_val, $interview_remarks, $app_id);
                mysqli_stmt_execute($upd_pass);
                $pass_stmt = $upd_pass;
            }
            if (mysqli_stmt_affected_rows($pass_stmt) > 0) {
                $cnt_pass++;
                $new_status = 'Interview Completed';
                $note = "Interview passed. Encoded by {$marked_by}.";
                mysqli_stmt_bind_param($log, 'isss', $app_id, $new_status, $marked_by, $note);
                mysqli_stmt_execute($log);
                add_applicant_message($conn, $user_id, 'interview_completed', 'Interview completed',
                    'You have completed your interview. Final admission decision will be announced soon.');

                mysqli_stmt_bind_param($mailFetch, 'i', $app_id);
                mysqli_stmt_execute($mailFetch);
                $mailRow = mysqli_fetch_assoc(mysqli_stmt_get_result($mailFetch));
                if ($mailRow && !empty($mailRow['email'])) {
                    $email           = trim((string)$mailRow['email']);
                    $fullName        = trim((string)(($mailRow['first_name'] ?? '') . ' ' . ($mailRow['last_name'] ?? '')));
                    $referenceNumber = (string)($mailRow['reference_number'] ?? '');
                    try {
                        $mailService->sendInterviewPassedEmail($email, $fullName, $referenceNumber);
                    } catch (Exception $e) {
                        error_log('BPC iEnroll mail error: ' . $e->getMessage());
                    }
                }
            }
        } else {
            // fail or unselected result → Rejected
            if ($score_val === null) {
                mysqli_stmt_bind_param($upd_fail_noscore, 'ssi', $marked_by, $interview_remarks, $app_id);
                mysqli_stmt_execute($upd_fail_noscore);
                $fail_stmt = $upd_fail_noscore;
            } else {
                mysqli_stmt_bind_param($upd_fail, 'sisi', $marked_by, $score_val, $interview_remarks, $app_id);
                mysqli_stmt_execute($upd_fail);
                $fail_stmt = $upd_fail;
            }
            if (mysqli_stmt_affected_rows($fail_stmt) > 0) {
                $cnt_fail++;
                $new_status = 'Rejected';
                $note = "Interview failed. Applicant rejected. Encoded by {$marked_by}.";
                mysqli_stmt_bind_param($log, 'isss', $app_id, $new_status, $marked_by, $note);
                mysqli_stmt_execute($log);
                add_applicant_message($conn, $user_id, 'rejected', 'Application not approved',
                    'We regret to inform you that your application was not approved at this time. You may visit the admissions office for further details.');

                mysqli_stmt_bind_param($mailFetch, 'i', $app_id);
                mysqli_stmt_execute($mailFetch);
                $mailRow = mysqli_fetch_assoc(mysqli_stmt_get_result($mailFetch));
                if ($mailRow && !empty($mailRow['email'])) {
                    $email           = trim((string)$mailRow['email']);
                    $fullName        = trim((string)(($mailRow['first_name'] ?? '') . ' ' . ($mailRow['last_name'] ?? '')));
                    $referenceNumber = (string)($mailRow['reference_number'] ?? '');
                    try {
                        $mailService->sendFinalRejectedEmail($email, $fullName, $referenceNumber);
                    } catch (Exception $e) {
                        error_log('BPC iEnroll mail error: ' . $e->getMessage());
                    }
                }
            }
        }
    } else {
        // NOT PRESENT → Interview No Show
        mysqli_stmt_bind_param($upd_noshow, 'ssi', $marked_by, $interview_remarks, $app_id);
        mysqli_stmt_execute($upd_noshow);
        if (mysqli_stmt_affected_rows($upd_noshow) > 0) {
            $cnt_noshow++;
            $new_status = 'Interview No Show';
            $note = "Did not appear for interview on {$interview_date}. Marked Interview No Show by {$marked_by}.";
            mysqli_stmt_bind_param($log, 'isss', $app_id, $new_status, $marked_by, $note);
            mysqli_stmt_execute($log);
            add_applicant_message($conn, $user_id, 'interview_noshow', 'Interview: No show',
                'You were marked as absent for the scheduled interview. Please contact the admissions office.');
        }
    }
}

mysqli_stmt_close($upd_pass);
mysqli_stmt_close($upd_fail);
mysqli_stmt_close($upd_pass_noscore);
mysqli_stmt_close($upd_fail_noscore);
mysqli_stmt_close($upd_noshow);
mysqli_stmt_close($log);
mysqli_stmt_close($mailFetch);
mysqli_close($conn);

$total = $cnt_pass + $cnt_fail + $cnt_noshow;
$_SESSION['admin_success'] =
    "Interview results processed for {$total} applicant(s) on " . date('F d, Y', strtotime($interview_date)) . " — "
  . "✓ Passed: {$cnt_pass}, "
  . "✕ Failed/Rejected: {$cnt_fail}, "
  . "— No Show: {$cnt_noshow}.";

redirect('app/admin/admin-interview-results.php?interview_date=' . urlencode($interview_date));