<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * One-time backfill: Create applicant_messages for existing applicants
 * who were already scheduled / had results before the messages feature was added.
 *
 * Run once as admin: visit backfill-applicant-messages.php in browser
 * (or run via CLI: php backfill-applicant-messages.php)
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/applicant-messages.php';

$is_cli = (php_sapi_name() === 'cli');
if (!$is_cli) {
    require_once CONFIG_PATH . '/admin-auth-check.php';
}

// Check table exists
$check = @mysqli_query($conn, "SHOW TABLES LIKE 'applicant_messages'");
if (!$check || mysqli_num_rows($check) === 0) {
    $msg = 'applicant_messages table does not exist. Run database/applicant_messages_migration.sql first.';
    if ($is_cli) {
        echo $msg . "\n";
    } else {
        $_SESSION['admin_error'] = $msg;
        header('Location: ../admin/admin-dashboard.php');
    }
    exit;
}

$inserted = 0;
$skipped  = 0;

// Helper: insert only if no message of this type exists for this user
function backfill_insert($conn, $user_id, $message_type, $title, $body, $created_at) {
    global $inserted, $skipped;
    $user_id = (int) $user_id;
    if ($user_id <= 0) return;
    $esc = mysqli_real_escape_string($conn, $message_type);
    $exists = mysqli_query($conn, "SELECT 1 FROM applicant_messages WHERE user_id = $user_id AND message_type = '$esc' LIMIT 1");
    if ($exists && mysqli_num_rows($exists) > 0) {
        $skipped++;
        return;
    }
    if (add_applicant_message_with_date($conn, $user_id, $message_type, $title, $body, $created_at)) {
        $inserted++;
    }
}

// ── EXAM SCHEDULED ─────────────────────────────────────────
$q = mysqli_query($conn,
    "SELECT a.id, a.user_id, a.updated_at,
            es.exam_date, es.exam_time, es.exam_venue, es.exam_type
     FROM applications a
     LEFT JOIN exam_schedules es ON a.exam_schedule_id = es.id
     WHERE a.status = 'Exam Scheduled'
       AND (a.program_category = 'CHED' OR a.program_category IS NULL)
       AND es.id IS NOT NULL"
);
while ($row = mysqli_fetch_assoc($q)) {
    $date_fmt = $row['exam_date'] ? date('F d, Y', strtotime($row['exam_date'])) : '—';
    $time_fmt = $row['exam_time'] ? date('g:i A', strtotime($row['exam_time'])) : '—';
    $body = "Date: {$date_fmt} at {$time_fmt}. Venue: " . ($row['exam_venue'] ?? '—')
          . ". Type: " . ($row['exam_type'] ?? 'Physical') . ".";
    $created = $row['updated_at'] ?? date('Y-m-d H:i:s');
    backfill_insert($conn, $row['user_id'], 'exam_scheduled', 'Your exam has been scheduled', $body, $created);
}

// ── EXAM COMPLETED (passed) ─────────────────────────────────
$q = mysqli_query($conn,
    "SELECT a.id, a.user_id, a.exam_score, a.exam_marked_at,
            a.exam_schedule_id
     FROM applications a
     WHERE a.status = 'Exam Completed'
       AND (a.program_category = 'CHED' OR a.program_category IS NULL)"
);
while ($row = mysqli_fetch_assoc($q)) {
    $score = (int)($row['exam_score'] ?? 0);
    $body  = "Your score: {$score}/100. You may proceed to interview scheduling.";
    $created = $row['exam_marked_at'] ?? date('Y-m-d H:i:s');
    backfill_insert($conn, $row['user_id'], 'exam_passed', 'Exam result: Passed', $body, $created);
}

// ── EXAM FAILED ─────────────────────────────────────────────
$q = mysqli_query($conn,
    "SELECT a.id, a.user_id, a.exam_score, a.exam_marked_at,
            a.exam_schedule_id
     FROM applications a
     WHERE a.status = 'Exam Failed'
       AND (a.program_category = 'CHED' OR a.program_category IS NULL)"
);
while ($row = mysqli_fetch_assoc($q)) {
    $score = (int)($row['exam_score'] ?? 0);
    $body  = "Your score: {$score}/100. Please contact the admissions office for your options.";
    $created = $row['exam_marked_at'] ?? date('Y-m-d H:i:s');
    backfill_insert($conn, $row['user_id'], 'exam_failed', 'Exam result: Not passed', $body, $created);
}

// ── NO SHOW (exam vs interview) ────────────────────────────
$q = mysqli_query($conn,
    "SELECT a.id, a.user_id, a.exam_schedule_id, a.interview_date,
            a.exam_marked_at, a.interview_marked_at, a.updated_at
     FROM applications a
     WHERE a.status = 'No Show'"
);
while ($row = mysqli_fetch_assoc($q)) {
    $created = $row['exam_marked_at'] ?? $row['interview_marked_at'] ?? $row['updated_at'] ?? date('Y-m-d H:i:s');
    // If they had interview scheduled, No Show = interview; else exam
    if (!empty($row['interview_date'])) {
        backfill_insert($conn, $row['user_id'], 'interview_noshow', 'Interview: No show',
            'You were marked as absent for the scheduled interview. Please contact the admissions office.', $created);
    } else {
        backfill_insert($conn, $row['user_id'], 'exam_noshow', 'Exam: No show',
            'You were marked as absent for the scheduled exam. Please contact the admissions office.', $created);
    }
}

// ── INTERVIEW SCHEDULED ────────────────────────────────────
$q = mysqli_query($conn,
    "SELECT id, user_id, interview_date, interview_time, interview_venue, interview_type, updated_at
     FROM applications
     WHERE status = 'Interview Scheduled'
       AND interview_date IS NOT NULL
       AND interview_time IS NOT NULL
       AND interview_venue IS NOT NULL"
);
while ($row = mysqli_fetch_assoc($q)) {
    $date_fmt = date('F d, Y', strtotime($row['interview_date']));
    $time_fmt = date('g:i A', strtotime($row['interview_time']));
    $type = $row['interview_type'] ?? 'Individual';
    $body = "Date: {$date_fmt} at {$time_fmt}. Venue: " . ($row['interview_venue'] ?? '—') . ". Type: {$type}.";
    $created = $row['updated_at'] ?? date('Y-m-d H:i:s');
    backfill_insert($conn, $row['user_id'], 'interview_scheduled', 'Your interview has been scheduled', $body, $created);
}

// ── INTERVIEW COMPLETED ────────────────────────────────────
$q = mysqli_query($conn,
    "SELECT id, user_id, interview_marked_at, updated_at
     FROM applications
     WHERE status = 'Interview Completed'"
);
while ($row = mysqli_fetch_assoc($q)) {
    $body   = 'You have completed your interview. Final admission decision will be announced soon.';
    $created = $row['interview_marked_at'] ?? $row['updated_at'] ?? date('Y-m-d H:i:s');
    backfill_insert($conn, $row['user_id'], 'interview_completed', 'Interview completed', $body, $created);
}

// ── ADMITTED / ENROLLED ────────────────────────────────────
$q = mysqli_query($conn,
    "SELECT a.id, a.user_id, a.reference_number, a.first_choice, a.updated_at
     FROM applications a
     WHERE a.status IN ('Admitted/Enrolled', 'Enrolled')"
);
while ($row = mysqli_fetch_assoc($q)) {
    $ref = $row['reference_number'] ? "Reference: {$row['reference_number']}. " : '';
    $prog = $row['first_choice'] ?? '—';
    $body = "Welcome to Bulacan Polytechnic College! {$ref}Please proceed to the Registrar's office to complete your enrollment. Bring your original documents and reference number. Program: {$prog}.";
    $created = $row['updated_at'] ?? date('Y-m-d H:i:s');
    backfill_insert($conn, $row['user_id'], 'admitted', 'Congratulations! You have been admitted', $body, $created);
}

// ── REJECTED ───────────────────────────────────────────────
$q = mysqli_query($conn,
    "SELECT id, user_id, interview_marked_at, updated_at
     FROM applications
     WHERE status = 'Rejected'"
);
while ($row = mysqli_fetch_assoc($q)) {
    $body   = 'We regret to inform you that your application was not approved at this time. You may visit the admissions office for further details.';
    $created = $row['interview_marked_at'] ?? $row['updated_at'] ?? date('Y-m-d H:i:s');
    backfill_insert($conn, $row['user_id'], 'rejected', 'Application not approved', $body, $created);
}

mysqli_close($conn);

// ── OUTPUT ─────────────────────────────────────────────────
if ($is_cli) {
    echo "Backfill complete. Inserted: {$inserted}, Skipped (already had message): {$skipped}\n";
} else {
    $_SESSION['admin_success'] = "Backfill complete. Inserted {$inserted} message(s) for existing applicants. Skipped {$skipped} (already had that message type).";
    header('Location: ../admin/admin-dashboard.php');
    exit;
}