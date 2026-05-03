<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin - Set Interview Schedule Handler
 * admin-set-interview.php
 *
 * Handles both:
 *   mode=batch      → one slot assigned to selected applicant IDs
 *   mode=individual → per-applicant date/time/venue from form arrays
 *
 * Eligible sources:
 *   CHED → status = 'Exam Completed'
 *   TESDA → status = 'Documents Verified' AND program_category = 'TESDA'
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
    redirect('app/admin/admin-interview-schedule.php');
}

$mode       = trim($_POST['mode'] ?? 'batch');
$admin_name = $_SESSION['admin_name'];
$scheduled  = 0;
$skipped    = 0;

// ── ALLOWED STATUSES FOR INTERVIEW ────────────────────────
// CHED must have passed exam; TESDA must be verified
// We enforce this server-side regardless of what was submitted
$allowed_statuses = ['Exam Completed', 'Documents Verified'];

// ══════════════════════════════════════════════════════════
//  BATCH MODE
// ══════════════════════════════════════════════════════════
if ($mode === 'batch') {

    $batch_date  = trim($_POST['batch_date']  ?? '');
    $batch_time  = trim($_POST['batch_time']  ?? '');
    $batch_venue = trim($_POST['batch_venue'] ?? '');
    $batch_type  = 'Batch';
    $batch_notes = trim($_POST['batch_notes'] ?? '');
    $ids         = $_POST['applicant_ids'] ?? [];

    // Validate required fields
    if (empty($batch_date) || empty($batch_time) || empty($batch_venue)) {
        $_SESSION['admin_error'] = 'Please fill in date, time, and venue before scheduling.';
        redirect('app/admin/admin-interview-schedule.php');
    }

    if (empty($ids)) {
        $_SESSION['admin_error'] = 'Please select at least one applicant.';
        redirect('app/admin/admin-interview-schedule.php');
    }

    // Sanitize IDs
    $ids = array_filter(array_map('intval', $ids), fn($id) => $id > 0);

    if (empty($ids)) {
        $_SESSION['admin_error'] = 'Invalid applicant selection.';
        redirect('app/admin/admin-interview-schedule.php');
    }

    $upd = mysqli_prepare($conn,
        "UPDATE applications SET
            status           = 'Interview Scheduled',
            interview_date   = ?,
            interview_time   = ?,
            interview_venue  = ?,
            interview_type   = ?,
            interview_notes  = ?,
            updated_at       = NOW()
         WHERE id = ?
           AND status IN ('Exam Completed', 'Documents Verified')
           AND (
               (program_category = 'CHED' AND status = 'Exam Completed')
               OR
               (program_category = 'TESDA' AND status = 'Documents Verified')
               OR
               (program_category IS NULL AND status = 'Exam Completed')
           )"
    );

    $log = mysqli_prepare($conn,
        "INSERT INTO status_history
            (application_id, old_status, new_status, changed_by, notes)
         VALUES (?, ?, 'Interview Scheduled', ?, ?)"
    );

    $id_list = implode(',', array_map('intval', $ids));
    $user_ids = [];
    $ur = mysqli_query($conn, "SELECT id, user_id FROM applications WHERE id IN ($id_list)");
    if ($ur) {
        while ($row = mysqli_fetch_assoc($ur)) $user_ids[(int)$row['id']] = (int)$row['user_id'];
    }

    $note = "Interview scheduled (Batch): {$batch_date} at {$batch_time}, {$batch_venue}";
    $date_fmt = date('F d, Y', strtotime($batch_date));
    $time_fmt = date('g:i A', strtotime($batch_time));
    $mailFetch = mysqli_prepare($conn,
        "SELECT u.email, a.first_name, a.last_name, a.reference_number
         FROM applications a
         JOIN users u ON a.user_id = u.id
         WHERE a.id = ?
         LIMIT 1"
    );

    foreach ($ids as $app_id) {
        mysqli_stmt_bind_param($upd, 'sssssi',
            $batch_date, $batch_time, $batch_venue,
            $batch_type, $batch_notes, $app_id
        );
        mysqli_stmt_execute($upd);

        if (mysqli_stmt_affected_rows($upd) > 0) {
            $scheduled++;
            $old_status = 'Exam Completed / Documents Verified';
            mysqli_stmt_bind_param($log, 'isss', $app_id, $old_status, $admin_name, $note);
            mysqli_stmt_execute($log);
            $uid = $user_ids[$app_id] ?? 0;
            if ($uid) {
                add_applicant_message($conn, $uid, 'interview_scheduled', 'Your interview has been scheduled', "Date: {$date_fmt} at {$time_fmt}. Venue: {$batch_venue}. Type: {$batch_type}.");
            }

            mysqli_stmt_bind_param($mailFetch, 'i', $app_id);
            mysqli_stmt_execute($mailFetch);
            $mailRow = mysqli_fetch_assoc(mysqli_stmt_get_result($mailFetch));
            if ($mailRow && !empty($mailRow['email'])) {
                $email = trim((string)$mailRow['email']);
                $fullName = trim((string)(($mailRow['first_name'] ?? '') . ' ' . ($mailRow['last_name'] ?? '')));
                $referenceNumber = (string)($mailRow['reference_number'] ?? '');
                try {
                    $mailService->sendInterviewScheduledEmail($email, $fullName, $referenceNumber, $date_fmt, $time_fmt, $batch_venue);
                } catch (Exception $e) {
                    error_log('BPC iEnroll mail error: ' . $e->getMessage());
                }
            }
        } else {
            $skipped++;
        }
    }

    mysqli_stmt_close($mailFetch);
    mysqli_stmt_close($upd);
    mysqli_stmt_close($log);

// ══════════════════════════════════════════════════════════
//  INDIVIDUAL MODE
// ══════════════════════════════════════════════════════════
} elseif ($mode === 'individual') {
    $_SESSION['admin_error'] = 'Individual bulk scheduling is disabled. Use batch scheduling, then use reschedule for emergency one-on-one cases.';
    redirect('app/admin/admin-interview-schedule.php');

} elseif ($mode === 'reschedule') {

    $app_id           = (int)($_POST['applicant_id'] ?? 0);
    $current_status   = trim($_POST['current_status'] ?? '');
    $reschedule_date  = trim($_POST['reschedule_date'] ?? '');
    $reschedule_time  = trim($_POST['reschedule_time'] ?? '');
    $reschedule_venue = trim($_POST['reschedule_venue'] ?? '');
    $reschedule_type  = trim($_POST['reschedule_type'] ?? 'Individual');
    $reschedule_reason = trim($_POST['reschedule_reason'] ?? '');
    $admin_name        = $_SESSION['admin_name'];

    if ($app_id <= 0 || empty($reschedule_date) || empty($reschedule_time) || empty($reschedule_venue)) {
        $_SESSION['admin_error'] = 'All reschedule fields are required.';
        redirect('app/admin/admin-interview-schedule.php');
    }

    // Server-side policy guard: emergency reschedule is only for Interview No Show appeals.
    if ($current_status !== 'Interview No Show') {
        $statusCheck = mysqli_prepare($conn, 'SELECT status FROM applications WHERE id = ? LIMIT 1');
        if ($statusCheck) {
            mysqli_stmt_bind_param($statusCheck, 'i', $app_id);
            mysqli_stmt_execute($statusCheck);
            $statusRow = mysqli_fetch_assoc(mysqli_stmt_get_result($statusCheck));
            mysqli_stmt_close($statusCheck);
            $current_status = (string)($statusRow['status'] ?? '');
        }
    }

    if ($current_status !== 'Interview No Show') {
        $_SESSION['admin_error'] = 'Only applicants marked as Interview No Show can be rescheduled through this appeal flow.';
        redirect('app/admin/admin-interview-schedule.php');
    }

    if (!in_array($reschedule_type, ['Batch', 'Individual'], true)) {
        $_SESSION['admin_error'] = 'Invalid interview type selected.';
        redirect('app/admin/admin-interview-schedule.php');
    }

    if ($reschedule_type === 'Individual' && mb_strlen($reschedule_reason) < 8) {
        $_SESSION['admin_error'] = 'Emergency reason is required for Individual reschedule (at least 8 characters).';
        redirect('app/admin/admin-interview-schedule.php');
    }

    $reschedule_note = ($reschedule_type === 'Individual')
        ? ('Emergency individual reschedule reason: ' . $reschedule_reason)
        : '';

    $stmt = mysqli_prepare($conn,
        'UPDATE applications
         SET interview_date = ?, interview_time = ?,
             interview_venue = ?, interview_type = ?,
             interview_notes = IF(? <> "", ?, interview_notes),
             status = "Interview Scheduled", updated_at = NOW()
         WHERE id = ?'
    );
    mysqli_stmt_bind_param($stmt, 'ssssssi',
        $reschedule_date, $reschedule_time,
        $reschedule_venue, $reschedule_type, $reschedule_note, $reschedule_note, $app_id
    );
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        // Log to status_history
        $log = mysqli_prepare($conn,
            'INSERT INTO status_history
                (application_id, old_status, new_status, changed_by, notes)
             VALUES (?, "Interview Scheduled", "Interview Scheduled", ?, ?)'
        );
        $notes = 'Interview no-show appeal approved. Rescheduled to ' . $reschedule_date . ' ' . $reschedule_time . ' at ' . $reschedule_venue . ' (' . $reschedule_type . ').';
        if ($reschedule_type === 'Individual') {
            $notes .= ' Emergency reason: ' . $reschedule_reason;
        }
        mysqli_stmt_bind_param($log, 'iss', $app_id, $admin_name, $notes);
        mysqli_stmt_execute($log);
        mysqli_stmt_close($log);

        $_SESSION['admin_success'] = 'Interview rescheduled successfully.';
    } else {
        $_SESSION['admin_error'] = 'Failed to reschedule. Please try again.';
    }

    redirect('app/admin/admin-interview-schedule.php');

} else {
    $_SESSION['admin_error'] = 'Invalid mode. Please try again.';
    redirect('app/admin/admin-interview-schedule.php');
}

mysqli_close($conn);

// ── REDIRECT WITH RESULT ───────────────────────────────────
if ($scheduled > 0) {
    $msg = "{$scheduled} applicant(s) successfully scheduled for interview.";
    if ($skipped > 0) $msg .= " ({$skipped} skipped — already scheduled or status mismatch.)";
    $_SESSION['admin_success'] = $msg;
} else {
    $_SESSION['admin_error'] =
        'No applicants were scheduled. They may have already been scheduled, '
      . 'or their status no longer matches the requirements.';
}

redirect('app/admin/admin-interview-schedule.php');
?>