<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin - Set Exam Schedule Handler
 * admin-set-exam.php
 *
 * 1. Validates input
 * 2. Inserts new exam_schedules record
 * 3. Updates SELECTED applicants only → 'Exam Scheduled'
 * 4. Links each applicant to exam_schedule_id
 * 5. Logs to status_history
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
    redirect('app/admin/admin-exam-schedule.php');
}

$exam_date     = trim($_POST['exam_date']     ?? '');
$exam_time     = trim($_POST['exam_time']     ?? '');
$exam_venue    = trim($_POST['exam_venue']    ?? '');
$exam_type     = ($_POST['exam_type'] ?? '') === 'Online' ? 'Online' : 'Physical';
$schedule_type = ($_POST['schedule_type'] ?? '') === 'Makeup' ? 'Makeup' : 'Regular';
$instructions  = trim($_POST['instructions']  ?? '');
$applicant_ids = $_POST['applicant_ids']      ?? [];
$admin_name    = $_SESSION['admin_name'];

// ── VALIDATE ───────────────────────────────────────────────
if (empty($exam_date) || empty($exam_time) || empty($exam_venue)) {
    $_SESSION['admin_error'] = 'Please fill in all required fields (date, time, venue).';
    redirect('app/admin/admin-exam-schedule.php');
}

if (empty($applicant_ids)) {
    $_SESSION['admin_error'] = 'Please select at least one applicant to schedule.';
    redirect('app/admin/admin-exam-schedule.php');
}

// Sanitize IDs to integers only
$applicant_ids = array_map('intval', $applicant_ids);
$applicant_ids = array_filter($applicant_ids, fn($id) => $id > 0);

if (empty($applicant_ids)) {
    $_SESSION['admin_error'] = 'Invalid applicant selection. Please try again.';
    redirect('app/admin/admin-exam-schedule.php');
}

// ── INSERT EXAM SCHEDULE ───────────────────────────────────
// Keep DB default for legacy data; qualifying uses per-program cutoffs when encoding results.
$passing_score = 75;
$ins = mysqli_prepare($conn,
    "INSERT INTO exam_schedules
        (exam_date, exam_time, exam_venue, exam_type, schedule_type, passing_score, instructions, created_by)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);
mysqli_stmt_bind_param($ins, 'sssssiss',
    $exam_date, $exam_time, $exam_venue,
    $exam_type, $schedule_type, $passing_score, $instructions, $admin_name
);
mysqli_stmt_execute($ins);
$schedule_id = (int)mysqli_insert_id($conn);
mysqli_stmt_close($ins);

if (!$schedule_id) {
    $_SESSION['admin_error'] = 'Failed to save exam schedule. Please try again.';
    redirect('app/admin/admin-exam-schedule.php');
}

// ── UPDATE SELECTED APPLICANTS ─────────────────────────────
$upd = mysqli_prepare($conn,
    "UPDATE applications SET
        status           = 'Exam Scheduled',
        exam_schedule_id = ?,
        updated_at       = NOW()
     WHERE id = ? AND status = 'Documents Verified'"
);

$log = mysqli_prepare($conn,
    "INSERT INTO status_history
        (application_id, old_status, new_status, changed_by, notes)
     VALUES (?, 'Documents Verified', 'Exam Scheduled', ?, ?)"
);

$note = 'Exam scheduled: '
      . date('F d, Y', strtotime($exam_date))
      . ' at ' . date('g:i A', strtotime($exam_time))
      . ', ' . $exam_venue;

// Resolve application id -> user_id for dashboard messages
$id_list = implode(',', array_map('intval', $applicant_ids));
$user_ids = [];
$res = mysqli_query($conn, "SELECT id, user_id FROM applications WHERE id IN ($id_list)");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $user_ids[(int)$row['id']] = (int)$row['user_id'];
    }
}

$exam_date_fmt = date('F d, Y', strtotime($exam_date));
$exam_time_fmt = date('g:i A', strtotime($exam_time));
$scheduled_count = 0;
$mailApplicant = mysqli_prepare($conn,
    "SELECT u.email, a.first_name, a.last_name, a.reference_number
     FROM applications a
     JOIN users u ON a.user_id = u.id
     WHERE a.id = ?
     LIMIT 1"
);

foreach ($applicant_ids as $app_id) {
    mysqli_stmt_bind_param($upd, 'ii', $schedule_id, $app_id);
    mysqli_stmt_execute($upd);
    if (mysqli_stmt_affected_rows($upd) > 0) {
        $scheduled_count++;
        mysqli_stmt_bind_param($log, 'iss', $app_id, $admin_name, $note);
        mysqli_stmt_execute($log);
        $uid = $user_ids[$app_id] ?? 0;
        if ($uid) {
            add_applicant_message($conn, $uid, 'exam_scheduled',
                'Your exam has been scheduled',
                "Date: {$exam_date_fmt} at {$exam_time_fmt}. Venue: {$exam_venue}. Type: {$exam_type}.");
        }

        mysqli_stmt_bind_param($mailApplicant, 'i', $app_id);
        mysqli_stmt_execute($mailApplicant);
        $mailRow = mysqli_fetch_assoc(mysqli_stmt_get_result($mailApplicant));
        if ($mailRow && !empty($mailRow['email'])) {
            $email = trim((string)$mailRow['email']);
            $fullName = trim((string)(($mailRow['first_name'] ?? '') . ' ' . ($mailRow['last_name'] ?? '')));
            $referenceNumber = (string)($mailRow['reference_number'] ?? '');
            try {
                $mailService->sendExamScheduledEmail(
                    $email,
                    $fullName,
                    $referenceNumber,
                    $exam_date_fmt,
                    $exam_time_fmt,
                    $exam_venue
                );
            } catch (Exception $e) {
                error_log('BPC iEnroll mail error: ' . $e->getMessage());
            }
        }
    }
}

mysqli_stmt_close($mailApplicant);
mysqli_stmt_close($upd);
mysqli_stmt_close($log);
mysqli_close($conn);

// ── REDIRECT ───────────────────────────────────────────────
if ($scheduled_count > 0) {
    $_SESSION['admin_success'] =
        "{$scheduled_count} applicant(s) scheduled for exam on "
      . date('F d, Y', strtotime($exam_date))
      . " at " . date('g:i A', strtotime($exam_time))
      . " — {$exam_venue}.";
} else {
    $_SESSION['admin_error'] =
        'No applicants were updated. They may have already been scheduled '
      . 'or their status changed. Please refresh and try again.';
}

redirect('app/admin/admin-exam-schedule.php');