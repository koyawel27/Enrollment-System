<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin - Reschedule Exam No Show to makeup exam
 * admin-reschedule-no-show.php
 *
 * POST only. For applications with status "Exam No Show", assigns them to a
 * selected exam schedule (makeup), updates status to Exam Scheduled, logs
 * history, and notifies the applicant.
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/admin-auth-check.php';
require_once CONFIG_PATH . '/admin-permissions.php';
require_admin_role([ADMIN_ROLE_SUPER_ADMIN, ADMIN_ROLE_ADMISSION_OFFICER]);
require_once CONFIG_PATH . '/applicant-messages.php';
require_once APP_PATH . '/shared/MailService.php';

$mailService = new MailService();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('app/admin/admin-dashboard.php');
}

$application_id   = isset($_POST['application_id'])   ? (int)$_POST['application_id']   : 0;
$exam_schedule_id = isset($_POST['exam_schedule_id']) ? (int)$_POST['exam_schedule_id'] : 0;
$admin_name       = $_SESSION['admin_name'] ?? 'Admin';

if ($application_id <= 0 || $exam_schedule_id <= 0) {
    $_SESSION['admin_error'] = 'Invalid application or exam schedule.';
    redirect('app/admin/admin-application-detail.php?id=' . $application_id);
}

// ── Fetch application — must be Exam No Show ───────────────
$stmt = mysqli_prepare($conn,
    "SELECT id, user_id, status, program_category
     FROM applications
     WHERE id = ? AND status = 'Exam No Show'"
);
mysqli_stmt_bind_param($stmt, 'i', $application_id);
mysqli_stmt_execute($stmt);
$app = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$app) {
    $_SESSION['admin_error'] = 'Application not found or not in Exam No Show status.';
    redirect('app/admin/admin-application-detail.php?id=' . $application_id);
}

if (($app['program_category'] ?? '') === 'TESDA') {
    $_SESSION['admin_error'] = 'TESDA applicants do not take exams; reschedule is not applicable.';
    redirect('app/admin/admin-application-detail.php?id=' . $application_id);
}

// ── Fetch exam schedule ───────────────────────────────────
$stmt = mysqli_prepare($conn,
    "SELECT id, exam_date, exam_time, exam_venue, exam_type FROM exam_schedules WHERE id = ?"
);
mysqli_stmt_bind_param($stmt, 'i', $exam_schedule_id);
mysqli_stmt_execute($stmt);
$schedule = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$schedule) {
    $_SESSION['admin_error'] = 'Selected exam schedule not found.';
    redirect('app/admin/admin-application-detail.php?id=' . $application_id);
}

// ── Update application ─────────────────────────────────────
$upd = mysqli_prepare($conn,
    "UPDATE applications
     SET status = 'Exam Scheduled', exam_schedule_id = ?, updated_at = NOW()
     WHERE id = ? AND status = 'Exam No Show'"
);
mysqli_stmt_bind_param($upd, 'ii', $exam_schedule_id, $application_id);
mysqli_stmt_execute($upd);
$affected = mysqli_stmt_affected_rows($upd);
mysqli_stmt_close($upd);

if ($affected === 0) {
    $_SESSION['admin_error'] = 'Could not update application. It may have been changed. Please refresh and try again.';
    redirect('app/admin/admin-application-detail.php?id=' . $application_id);
}

// ── Log status history ─────────────────────────────────────
$note = 'Rescheduled for makeup exam by ' . $admin_name . ': '
      . date('F d, Y', strtotime($schedule['exam_date']))
      . ' at ' . date('g:i A', strtotime($schedule['exam_time']))
      . ', ' . $schedule['exam_venue'];

$log         = mysqli_prepare($conn,
    "INSERT INTO status_history (application_id, old_status, new_status, changed_by, notes)
     VALUES (?, 'Exam No Show', 'Exam Scheduled', ?, ?)"
);
$log_app_id  = $application_id;
$log_admin   = $admin_name;
$log_note    = $note;
mysqli_stmt_bind_param($log, 'iss', $log_app_id, $log_admin, $log_note);
mysqli_stmt_execute($log);
mysqli_stmt_close($log);

// ── Dashboard message for applicant ────────────────────────
$user_id  = (int)$app['user_id'];
$date_fmt = date('F d, Y', strtotime($schedule['exam_date']));
$time_fmt = date('g:i A', strtotime($schedule['exam_time']));
$body     = "Date: {$date_fmt} at {$time_fmt}. Venue: {$schedule['exam_venue']}. Type: {$schedule['exam_type']}.";
add_applicant_message($conn, $user_id, 'exam_scheduled', 'Rescheduled for makeup exam', $body);

// ── Email applicant ─────────────────────────────────────────
$mailFetch = mysqli_prepare($conn,
    "SELECT u.email, a.first_name, a.last_name, a.reference_number
     FROM applications a
     JOIN users u ON a.user_id = u.id
     WHERE a.id = ?
     LIMIT 1"
);
mysqli_stmt_bind_param($mailFetch, 'i', $application_id);
mysqli_stmt_execute($mailFetch);
$mailRow = mysqli_fetch_assoc(mysqli_stmt_get_result($mailFetch));
mysqli_stmt_close($mailFetch);

if ($mailRow && !empty($mailRow['email'])) {
    $email           = trim((string)$mailRow['email']);
    $fullName        = trim((string)(($mailRow['first_name'] ?? '') . ' ' . ($mailRow['last_name'] ?? '')));
    $referenceNumber = (string)($mailRow['reference_number'] ?? '');
    $mailPassingScore = 75;

    try {
        $mailService->sendExamScheduledEmail(
            $email,
            $fullName,
            $referenceNumber,
            $date_fmt,
            $time_fmt,
            $schedule['exam_venue'],
            $mailPassingScore
        );
    } catch (Exception $e) {
        error_log('BPC iEnroll mail error: ' . $e->getMessage());
    }
}

mysqli_close($conn);

$_SESSION['admin_success'] = 'Applicant rescheduled to makeup exam. They have been notified on their dashboard.';
redirect('app/admin/admin-application-detail.php?id=' . $application_id);