<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Applicant TESDA Decision Handler
 * app/handlers/applicant-decision.php
 *
 * Processes student's Accept or Decline of a TESDA fallback program offer.
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/applicant-messages.php';
require_once APP_PATH . '/shared/MailService.php';

if (!isset($_SESSION['user_id'])) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('app/student/dashboard.php');
}

$mailService = new MailService();
$user_id     = (int)$_SESSION['user_id'];
$decision    = trim($_POST['decision'] ?? ''); // 'accept' or 'decline'

if (!in_array($decision, ['accept', 'decline'], true)) {
    $_SESSION['error'] = 'Invalid decision.';
    redirect('app/student/dashboard.php');
}

// Fetch the application — must belong to this user and be awaiting decision
$stmt = mysqli_prepare($conn,
    "SELECT id, third_choice, exam_score, first_name, last_name, reference_number
     FROM applications
     WHERE user_id = ? AND status = 'Awaiting Applicant Decision'
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$app = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$app) {
    $_SESSION['error'] = 'No pending decision found.';
    redirect('app/student/dashboard.php');
}

$app_id       = (int)$app['id'];
$tesda_choice = $app['third_choice'] ?? '';
$score        = (int)($app['exam_score'] ?? 0);
$full_name    = trim(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? ''));
$ref_number   = $app['reference_number'] ?? '';

// ── FETCH MAIL INFO ────────────────────────────────────────
$mail_stmt = mysqli_prepare($conn,
    "SELECT u.email FROM users u
     JOIN applications a ON a.user_id = u.id
     WHERE a.id = ? LIMIT 1"
);
mysqli_stmt_bind_param($mail_stmt, 'i', $app_id);
mysqli_stmt_execute($mail_stmt);
$mail_row = mysqli_fetch_assoc(mysqli_stmt_get_result($mail_stmt));
mysqli_stmt_close($mail_stmt);
$student_email = $mail_row['email'] ?? '';

// ── FETCH ADMIN EMAILS (admission officers + super admins) ─
$admin_res = mysqli_query($conn,
    "SELECT email, name FROM admins
     WHERE role IN ('super_admin','admission_officer') AND is_active = 1"
);
$admin_accounts = [];
while ($row = mysqli_fetch_assoc($admin_res)) {
    $admin_accounts[] = $row;
}

// ══════════════════════════════════════════════════════════
//  ACCEPT
// ══════════════════════════════════════════════════════════
if ($decision === 'accept') {
    if (empty($tesda_choice)) {
        $_SESSION['error'] = 'No TESDA program found to accept.';
        redirect('app/student/dashboard.php');
    }

    // Update application: move to TESDA pipeline
    $upd = mysqli_prepare($conn,
        "UPDATE applications SET
            status           = 'Documents Verified',
            program_category = 'TESDA',
            first_choice     = ?,
            assigned_program = ?,
            updated_at       = NOW()
         WHERE id = ? AND status = 'Awaiting Applicant Decision'"
    );
    mysqli_stmt_bind_param($upd, 'ssi', $tesda_choice, $tesda_choice, $app_id);
    $ok = mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);

    if (!$ok || mysqli_affected_rows($conn) === 0) {
        $_SESSION['error'] = 'Something went wrong. Please try again.';
        redirect('app/student/dashboard.php');
    }

    // Log status change
    $log = mysqli_prepare($conn,
        "INSERT INTO status_history
            (application_id, old_status, new_status, changed_by, notes)
         VALUES (?, 'Awaiting Applicant Decision', 'Documents Verified', ?, ?)"
    );
    $changed_by = 'Applicant';
    $notes      = "Applicant accepted TESDA fallback: {$tesda_choice}.";
    mysqli_stmt_bind_param($log, 'iss', $app_id, $changed_by, $notes);
    mysqli_stmt_execute($log);
    mysqli_stmt_close($log);

    // In-app message to student
    add_applicant_message($conn, $user_id, 'documents_verified',
        'TESDA Offer Accepted',
        "You have accepted the offer for {$tesda_choice}. Your application is now active in the TESDA track. Please wait for interview scheduling.");

    // Email student
    try {
        $mailService->sendTesdaAcceptedEmail($student_email, $full_name, $ref_number, $tesda_choice);
    } catch (Exception $e) {
        error_log('BPC iEnroll mail error: ' . $e->getMessage());
    }

    // Notify admins
    foreach ($admin_accounts as $admin) {
        try {
            $mailService->sendAdminTesdaDecisionEmail(
                $admin['email'], $admin['name'],
                $full_name, $ref_number, $tesda_choice, 'accepted'
            );
        } catch (Exception $e) {
            error_log('BPC iEnroll mail error: ' . $e->getMessage());
        }
    }

    $_SESSION['success'] = 'You have accepted the TESDA program offer. Please wait for your interview schedule.';

// ══════════════════════════════════════════════════════════
//  DECLINE → Application Withdrawn (applicant's own choice)
// ══════════════════════════════════════════════════════════
} elseif ($decision === 'decline') {
    $upd = mysqli_prepare($conn,
        "UPDATE applications SET
            status     = 'Application Withdrawn',
            updated_at = NOW()
         WHERE id = ? AND status = 'Awaiting Applicant Decision'"
    );
    mysqli_stmt_bind_param($upd, 'i', $app_id);
    $ok = mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);

    if (!$ok || mysqli_affected_rows($conn) === 0) {
        $_SESSION['error'] = 'Something went wrong. Please try again.';
        redirect('app/student/dashboard.php');
    }

    // Log
    $log = mysqli_prepare($conn,
        "INSERT INTO status_history
            (application_id, old_status, new_status, changed_by, notes)
         VALUES (?, 'Awaiting Applicant Decision', 'Application Withdrawn', ?, ?)"
    );
    $changed_by = 'Applicant';
    $notes      = "Applicant declined TESDA fallback offer ({$tesda_choice}). Application withdrawn by applicant's choice.";
    mysqli_stmt_bind_param($log, 'iss', $app_id, $changed_by, $notes);
    mysqli_stmt_execute($log);
    mysqli_stmt_close($log);

    // In-app message to student
    add_applicant_message($conn, $user_id, 'withdrawn',
        'Application Withdrawn',
        "You declined the TESDA program offer. Your application has been closed as per your decision. You are welcome to re-apply during the next admission period.");

    // Email student
    try {
        $mailService->sendTesdaDeclinedEmail($student_email, $full_name, $ref_number);
    } catch (Exception $e) {
        error_log('BPC iEnroll mail error: ' . $e->getMessage());
    }

    // Notify admins
    foreach ($admin_accounts as $admin) {
        try {
            $mailService->sendAdminTesdaDecisionEmail(
                $admin['email'], $admin['name'],
                $full_name, $ref_number, $tesda_choice, 'declined'
            );
        } catch (Exception $e) {
            error_log('BPC iEnroll mail error: ' . $e->getMessage());
        }
    }

    $_SESSION['success'] = 'You have declined the offer. Your application has been closed.';
}

mysqli_close($conn);
redirect('app/student/dashboard.php');