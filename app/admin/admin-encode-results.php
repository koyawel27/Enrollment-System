<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin - Encode Exam Results Handler
 * admin-encode-results.php
 *
 * FIX: Now scoped to a specific exam_schedule_id posted from the UI.
 * Only applicants in that schedule batch are processed.
 * If no valid schedule_id is posted, the handler aborts immediately.
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('app/admin/admin-exam-results.php');
}

// ── SCOPE GUARD: require a valid schedule_id ───────────────
// This is the core fix. Without a specific schedule, we cannot
// safely determine which applicants belong to "today's" batch.
$schedule_id = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;

if ($schedule_id <= 0) {
    $_SESSION['admin_error'] = 'No exam schedule was specified. Please select a specific schedule before encoding results.';
    redirect('app/admin/admin-exam-results.php');
}

// ── Validate that schedule_id actually exists ──────────────
$sched_check = mysqli_prepare($conn, "SELECT id FROM exam_schedules WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($sched_check, 'i', $schedule_id);
mysqli_stmt_execute($sched_check);
mysqli_stmt_store_result($sched_check);
if (mysqli_stmt_num_rows($sched_check) === 0) {
    mysqli_stmt_close($sched_check);
    $_SESSION['admin_error'] = 'Invalid exam schedule. Please select a valid schedule and try again.';
    redirect('app/admin/admin-exam-results.php');
}
mysqli_stmt_close($sched_check);

// ── bind_param needs variables by reference ────────────────
$marked_by   = $_SESSION['admin_name'];
$present_ids = $_POST['present'] ?? [];
$scores      = $_POST['score']   ?? [];

// ── Server-side guard: require at least one attendance mark ─
if (empty($present_ids) && empty($scores)) {
    $_SESSION['admin_error'] = 'No attendance was recorded. Please mark at least one applicant as present or absent before confirming.';
    redirect('app/admin/admin-exam-results.php?schedule_id=' . $schedule_id);
}

// ── FETCH ONLY applicants in the specified schedule ────────
// This is the critical scope fix — we only touch this batch.
$sql = "SELECT a.id, a.user_id, a.exam_schedule_id,
               a.first_choice, a.second_choice, a.third_choice, a.program_category,
               a.exam_schedule_id
        FROM applications a
        WHERE a.status = 'Exam Scheduled'
          AND (a.program_category = 'CHED' OR a.program_category IS NULL)
          AND a.exam_schedule_id = ?";

$fetch_stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($fetch_stmt, 'i', $schedule_id);
mysqli_stmt_execute($fetch_stmt);
$result = mysqli_stmt_get_result($fetch_stmt);

$to_process = [];
while ($row = mysqli_fetch_assoc($result)) {
    $to_process[(int)$row['id']] = [
        'user_id'       => (int)$row['user_id'],
        'row'           => $row,
    ];
}
mysqli_stmt_close($fetch_stmt);

if (empty($to_process)) {
    $_SESSION['admin_error'] = 'No applicants found for this schedule. They may have already been processed. Please refresh and try again.';
    redirect('app/admin/admin-exam-results.php?schedule_id=' . $schedule_id);
}

// ── PREPARE STATEMENTS ─────────────────────────────────────
$upd_pass = mysqli_prepare($conn,
    "UPDATE applications SET
        status           = ?,
        exam_score       = ?,
        exam_marked_by   = ?,
        exam_marked_at   = NOW(),
        program_category = ?,
        assigned_program = ?,
        updated_at       = NOW()
     WHERE id = ? AND status = 'Exam Scheduled'"
);

$upd_fail = mysqli_prepare($conn,
    "UPDATE applications SET
        status         = 'Exam Failed',
        exam_score     = ?,
        exam_marked_by = ?,
        exam_marked_at = NOW(),
        updated_at     = NOW()
     WHERE id = ? AND status = 'Exam Scheduled'"
);

$upd_noshow = mysqli_prepare($conn,
    "UPDATE applications SET
        status         = 'Exam No Show',
        exam_score     = NULL,
        exam_marked_by = ?,
        exam_marked_at = NOW(),
        updated_at     = NOW()
     WHERE id = ? AND status = 'Exam Scheduled'"
);

$log = mysqli_prepare($conn,
    "INSERT INTO status_history
        (application_id, old_status, new_status, changed_by, notes)
     VALUES (?, 'Exam Scheduled', ?, ?, ?)"
);

$mailFetch = mysqli_prepare($conn,
    "SELECT u.email, a.first_name, a.last_name, a.reference_number
     FROM applications a
     JOIN users u ON a.user_id = u.id
     WHERE a.id = ?
     LIMIT 1"
);

$cnt_pass = $cnt_fail = $cnt_noshow = 0;

foreach ($to_process as $app_id => $data) {
    $user_id    = $data['user_id'];
    $app_row    = $data['row'];
    $is_present = isset($present_ids[$app_id]);

    if ($is_present) {
        $score = (int)($scores[$app_id] ?? 0);
        $score = max(0, min(100, $score));

        $assignment = determine_program_assignment($app_row, $score, $CHED_PROGRAM_CUTOFFS);

        if ($assignment['final_status'] === 'Exam Completed') {
            mysqli_stmt_bind_param(
                $upd_pass,
                'sisssi',
                $assignment['final_status'],
                $score,
                $marked_by,
                $assignment['final_category'],
                $assignment['assigned_program'],
                $app_id
            );
            mysqli_stmt_execute($upd_pass);
            if (mysqli_stmt_affected_rows($upd_pass) > 0) {
                $cnt_pass++;
                $new_status = $assignment['final_status'];
                $note = "Exam passed. Score: {$score}. Assigned program: " . ($assignment['assigned_program'] ?? 'none') . ". Encoded by {$marked_by}.";
                mysqli_stmt_bind_param($log, 'isss', $app_id, $new_status, $marked_by, $note);
                mysqli_stmt_execute($log);

                if ($assignment['assigned_program']) {
                    $prog_label = get_program_label($assignment['assigned_program'], $conn);
                    if ($assignment['assigned_program'] === $app_row['first_choice']) {
                        $msg_body = "Your exam score: {$score}/100. Congratulations, you qualified for your 1st choice program: {$prog_label}. Your application will proceed to the next stage.";
                    } else {
                        $first_label = get_program_label($app_row['first_choice'], $conn);
                        $msg_body = "Your exam score: {$score}/100. You did not meet the cutoff for your 1st choice ({$first_label}). However, you qualified for your 2nd choice: {$prog_label}. Your application will continue with this program.";
                    }
                } else {
                    $msg_body = "Your exam score: {$score}/100.";
                }
                add_applicant_message($conn, $user_id, 'exam_passed', 'Exam result: Passed', $msg_body);

                mysqli_stmt_bind_param($mailFetch, 'i', $app_id);
                mysqli_stmt_execute($mailFetch);
                $mailRow = mysqli_fetch_assoc(mysqli_stmt_get_result($mailFetch));
                if ($mailRow && !empty($mailRow['email'])) {
                    $email           = trim((string)$mailRow['email']);
                    $fullName        = trim((string)(($mailRow['first_name'] ?? '') . ' ' . ($mailRow['last_name'] ?? '')));
                    $referenceNumber = (string)($mailRow['reference_number'] ?? '');
                    try {
                        $mailService->sendExamPassedEmail($email, $fullName, $referenceNumber, $score);
                    } catch (Exception $e) {
                        error_log('BPC iEnroll mail error: ' . $e->getMessage());
                    }
                }
            }

        } elseif ($assignment['final_status'] === 'Awaiting Applicant Decision') {
            $offered      = $assignment['offered_program'] ?? '';
            $await_status = 'Awaiting Applicant Decision';

            $upd_await = mysqli_prepare($conn,
                "UPDATE applications SET
                    status         = 'Awaiting Applicant Decision',
                    exam_score     = ?,
                    exam_marked_by = ?,
                    exam_marked_at = NOW(),
                    updated_at     = NOW()
                 WHERE id = ? AND status = 'Exam Scheduled'"
            );
            mysqli_stmt_bind_param($upd_await, 'isi', $score, $marked_by, $app_id);
            mysqli_stmt_execute($upd_await);

            if (mysqli_stmt_affected_rows($upd_await) > 0) {
                $cnt_fail++;
                $note = "Did not qualify for CHED choices. Score: {$score}. TESDA fallback offered: {$offered}. Awaiting student decision. Encoded by {$marked_by}.";
                mysqli_stmt_bind_param($log, 'isss', $app_id, $await_status, $marked_by, $note);
                mysqli_stmt_execute($log);

                $offered_label = get_program_label($offered, $conn);
                $msg_body = "Your exam score: {$score}/100. Unfortunately, you did not meet the cutoff for your chosen CHED programs. "
                          . "However, you listed \"{$offered_label}\" as your TESDA fallback choice. "
                          . "Please log in to your dashboard to accept or decline this offer.";
                add_applicant_message($conn, $user_id, 'awaiting_decision', 'Action Required: TESDA Program Offer', $msg_body);

                mysqli_stmt_bind_param($mailFetch, 'i', $app_id);
                mysqli_stmt_execute($mailFetch);
                $mailRow = mysqli_fetch_assoc(mysqli_stmt_get_result($mailFetch));
                if ($mailRow && !empty($mailRow['email'])) {
                    $email    = trim((string)$mailRow['email']);
                    $fullName = trim((string)(($mailRow['first_name'] ?? '') . ' ' . ($mailRow['last_name'] ?? '')));
                    $refNum   = (string)($mailRow['reference_number'] ?? '');
                    try {
                        $mailService->sendTesdaOfferEmail($email, $fullName, $refNum, $offered_label, $score);
                    } catch (Exception $e) {
                        error_log('BPC iEnroll mail error: ' . $e->getMessage());
                    }
                }
            }
            mysqli_stmt_close($upd_await);

        } else {
            // Exam Failed
            mysqli_stmt_bind_param($upd_fail, 'isi', $score, $marked_by, $app_id);
            mysqli_stmt_execute($upd_fail);
            if (mysqli_stmt_affected_rows($upd_fail) > 0) {
                $cnt_fail++;
                $new_status = 'Exam Failed';
                $note       = "Exam failed. Score: {$score}. No program qualified (per-program cutoffs). Encoded by {$marked_by}.";
                mysqli_stmt_bind_param($log, 'isss', $app_id, $new_status, $marked_by, $note);
                mysqli_stmt_execute($log);
                add_applicant_message($conn, $user_id, 'exam_failed', 'Exam result: Not passed',
                    "Your score: {$score}/100. You did not meet the requirements for your chosen programs. Please contact the admissions office for your options.");

                mysqli_stmt_bind_param($mailFetch, 'i', $app_id);
                mysqli_stmt_execute($mailFetch);
                $mailRow = mysqli_fetch_assoc(mysqli_stmt_get_result($mailFetch));
                if ($mailRow && !empty($mailRow['email'])) {
                    $email           = trim((string)$mailRow['email']);
                    $fullName        = trim((string)(($mailRow['first_name'] ?? '') . ' ' . ($mailRow['last_name'] ?? '')));
                    $referenceNumber = (string)($mailRow['reference_number'] ?? '');
                    try {
                        $mailService->sendExamFailedEmail($email, $fullName, $referenceNumber, $score);
                    } catch (Exception $e) {
                        error_log('BPC iEnroll mail error: ' . $e->getMessage());
                    }
                }
            }
        }

    } else {
        // NOT PRESENT → Exam No Show
        mysqli_stmt_bind_param($upd_noshow, 'si', $marked_by, $app_id);
        mysqli_stmt_execute($upd_noshow);
        if (mysqli_stmt_affected_rows($upd_noshow) > 0) {
            $cnt_noshow++;
            $new_status = 'Exam No Show';
            $note       = "Did not appear for exam (schedule ID: {$schedule_id}). Marked Exam No Show by {$marked_by}.";
            mysqli_stmt_bind_param($log, 'isss', $app_id, $new_status, $marked_by, $note);
            mysqli_stmt_execute($log);
            add_applicant_message($conn, $user_id, 'exam_noshow', 'Exam: No show',
                'You were marked as absent for the scheduled exam. Please contact the admissions office.');

            mysqli_stmt_bind_param($mailFetch, 'i', $app_id);
            mysqli_stmt_execute($mailFetch);
            $mailRow = mysqli_fetch_assoc(mysqli_stmt_get_result($mailFetch));
            if ($mailRow && !empty($mailRow['email'])) {
                $email           = trim((string)$mailRow['email']);
                $fullName        = trim((string)(($mailRow['first_name'] ?? '') . ' ' . ($mailRow['last_name'] ?? '')));
                $referenceNumber = (string)($mailRow['reference_number'] ?? '');
                try {
                    $mailService->sendExamNoShowEmail($email, $fullName, $referenceNumber);
                } catch (Exception $e) {
                    error_log('BPC iEnroll mail error: ' . $e->getMessage());
                }
            }
        }
    }
}

mysqli_stmt_close($upd_pass);
mysqli_stmt_close($upd_fail);
mysqli_stmt_close($upd_noshow);
mysqli_stmt_close($log);
mysqli_stmt_close($mailFetch);
mysqli_close($conn);

$total = $cnt_pass + $cnt_fail + $cnt_noshow;
$_SESSION['admin_success'] =
    "Results processed for {$total} applicant(s) — "
  . "✓ Passed: {$cnt_pass}, "
  . "✕ Failed: {$cnt_fail}, "
  . "— No Show: {$cnt_noshow}.";

redirect('app/admin/admin-exam-results.php?schedule_id=' . $schedule_id);