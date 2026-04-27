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
    $batch_type  = in_array($_POST['batch_type'] ?? '', ['Batch','Individual'])
                   ? $_POST['batch_type'] : 'Batch';
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

    $ind_dates  = $_POST['ind_date']  ?? [];
    $ind_times  = $_POST['ind_time']  ?? [];
    $ind_venues = $_POST['ind_venue'] ?? [];
    $ind_types  = $_POST['ind_type']  ?? [];

    if (empty($ind_dates)) {
        $_SESSION['admin_error'] = 'No schedule data received. Please try again.';
        redirect('app/admin/admin-interview-schedule.php');
    }

    $upd = mysqli_prepare($conn,
        "UPDATE applications SET
            status           = 'Interview Scheduled',
            interview_date   = ?,
            interview_time   = ?,
            interview_venue  = ?,
            interview_type   = ?,
            updated_at       = NOW()
         WHERE id = ?
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
         VALUES (?, 'Exam Completed / Documents Verified', 'Interview Scheduled', ?, ?)"
    );

    $ind_ids = array_map('intval', array_keys($ind_dates));
    $ind_ids = array_filter($ind_ids, fn($id) => $id > 0);
    $user_ids = [];
    if (!empty($ind_ids)) {
        $id_list = implode(',', $ind_ids);
        $ur = mysqli_query($conn, "SELECT id, user_id FROM applications WHERE id IN ($id_list)");
        if ($ur) {
            while ($row = mysqli_fetch_assoc($ur)) $user_ids[(int)$row['id']] = (int)$row['user_id'];
        }
    }
    $mailFetch = mysqli_prepare($conn,
        "SELECT u.email, a.first_name, a.last_name, a.reference_number
         FROM applications a
         JOIN users u ON a.user_id = u.id
         WHERE a.id = ?
         LIMIT 1"
    );

    foreach ($ind_dates as $app_id => $date) {
        $app_id = (int)$app_id;
        if ($app_id <= 0) continue;

        $date  = trim($date);
        $time  = trim($ind_times[$app_id]  ?? '');
        $venue = trim($ind_venues[$app_id] ?? '');
        $type  = in_array($ind_types[$app_id] ?? '', ['Batch','Individual'])
                 ? $ind_types[$app_id] : 'Individual';

        // Skip rows not fully filled
        if (empty($date) || empty($time) || empty($venue)) {
            $skipped++;
            continue;
        }

        mysqli_stmt_bind_param($upd, 'ssssi',
            $date, $time, $venue, $type, $app_id
        );
        mysqli_stmt_execute($upd);

        if (mysqli_stmt_affected_rows($upd) > 0) {
            $scheduled++;
            $note = "Interview scheduled (Individual): {$date} at {$time}, {$venue}";
            mysqli_stmt_bind_param($log, 'iss', $app_id, $admin_name, $note);
            mysqli_stmt_execute($log);
            $uid = $user_ids[$app_id] ?? 0;
            if ($uid) {
                $date_fmt = date('F d, Y', strtotime($date));
                $time_fmt = date('g:i A', strtotime($time));
                add_applicant_message($conn, $uid, 'interview_scheduled', 'Your interview has been scheduled', "Date: {$date_fmt} at {$time_fmt}. Venue: {$venue}. Type: {$type}.");
            }

            mysqli_stmt_bind_param($mailFetch, 'i', $app_id);
            mysqli_stmt_execute($mailFetch);
            $mailRow = mysqli_fetch_assoc(mysqli_stmt_get_result($mailFetch));
            if ($mailRow && !empty($mailRow['email'])) {
                $email = trim((string)$mailRow['email']);
                $fullName = trim((string)(($mailRow['first_name'] ?? '') . ' ' . ($mailRow['last_name'] ?? '')));
                $referenceNumber = (string)($mailRow['reference_number'] ?? '');
                try {
                    $mailService->sendInterviewScheduledEmail($email, $fullName, $referenceNumber, $date_fmt, $time_fmt, $venue);
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

} elseif ($mode === 'reschedule') {

    $app_id           = (int)($_POST['applicant_id'] ?? 0);
    $reschedule_date  = trim($_POST['reschedule_date'] ?? '');
    $reschedule_time  = trim($_POST['reschedule_time'] ?? '');
    $reschedule_venue = trim($_POST['reschedule_venue'] ?? '');
    $reschedule_type  = trim($_POST['reschedule_type'] ?? 'Individual');
    $admin_name       = $_SESSION['admin_name'];

    if ($app_id <= 0 || empty($reschedule_date) || empty($reschedule_time) || empty($reschedule_venue)) {
        $_SESSION['admin_error'] = 'All reschedule fields are required.';
        redirect('app/admin/admin-interview-schedule.php');
    }

    $stmt = mysqli_prepare($conn,
        'UPDATE applications
         SET interview_date = ?, interview_time = ?,
             interview_venue = ?, interview_type = ?,
             status = "Interview Scheduled", updated_at = NOW()
         WHERE id = ?'
    );
    mysqli_stmt_bind_param($stmt, 'ssssi',
        $reschedule_date, $reschedule_time,
        $reschedule_venue, $reschedule_type, $app_id
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
        $notes = 'Interview rescheduled to ' . $reschedule_date . ' ' . $reschedule_time . ' at ' . $reschedule_venue;
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