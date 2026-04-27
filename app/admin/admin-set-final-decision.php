<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin - Set Final Decision Handler
 * admin-set-final-decision.php
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/admin-auth-check.php';
require_once CONFIG_PATH . '/admin-permissions.php';
require_admin_role([ADMIN_ROLE_SUPER_ADMIN, ADMIN_ROLE_ADMISSION_OFFICER, ADMIN_ROLE_REGISTRAR]);
require_once CONFIG_PATH . '/applicant-messages.php';
require_once APP_PATH . '/shared/MailService.php';

$mailService = new MailService();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('app/admin/admin-final-decision.php');
}

$app_id    = (int)($_POST['app_id']   ?? 0);
$decision  = trim($_POST['decision']  ?? '');
$marked_by = $_SESSION['admin_name'];

if ($app_id <= 0 || !in_array($decision, ['admit', 'reject'])) {
    $_SESSION['admin_error'] = 'Invalid request.';
    redirect('app/admin/admin-final-decision.php');
}

$new_status = $decision === 'admit' ? 'Admitted/Enrolled' : 'Rejected';

$upd = mysqli_prepare($conn,
    "UPDATE applications SET
        status     = ?,
        updated_at = NOW()
     WHERE id = ? AND status = 'Interview Completed'"
);
mysqli_stmt_bind_param($upd, 'si', $new_status, $app_id);
mysqli_stmt_execute($upd);
$affected = mysqli_stmt_affected_rows($upd);
mysqli_stmt_close($upd);

if ($affected > 0) {
    $note = $decision === 'admit'
        ? "Final decision: Admitted/Enrolled. Decided by {$marked_by}."
        : "Final decision: Rejected. Decided by {$marked_by}.";

    $log = mysqli_prepare($conn,
        "INSERT INTO status_history
            (application_id, old_status, new_status, changed_by, notes)
         VALUES (?, 'Interview Completed', ?, ?, ?)"
    );
    mysqli_stmt_bind_param($log, 'isss', $app_id, $new_status, $marked_by, $note);
    mysqli_stmt_execute($log);
    mysqli_stmt_close($log);

    $mailFetch = mysqli_prepare($conn,
        "SELECT a.user_id, a.first_name, a.last_name, a.reference_number,
                COALESCE(a.assigned_program, a.first_choice, '') AS program_name,
                u.email
         FROM applications a
         JOIN users u ON a.user_id = u.id
         WHERE a.id = ?
         LIMIT 1"
    );
    mysqli_stmt_bind_param($mailFetch, 'i', $app_id);
    mysqli_stmt_execute($mailFetch);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($mailFetch));
    mysqli_stmt_close($mailFetch);

    $uid = $row ? (int)$row['user_id'] : 0;
    if ($uid) {
        if ($decision === 'admit') {
            add_applicant_message($conn, $uid, 'admitted', 'Congratulations! You have been admitted', 'Welcome to Bulacan Polytechnic College! Please proceed to the Registrar\'s office to complete your enrollment. Bring your original documents and reference number.');
        } else {
            add_applicant_message($conn, $uid, 'rejected', 'Application not approved', 'We regret to inform you that your application was not approved. You may visit the admissions office for further details.');
        }
    }

    if ($row && !empty($row['email'])) {
        $email = trim((string)$row['email']);
        $fullName = trim((string)(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
        $referenceNumber = (string)($row['reference_number'] ?? '');
        $programName = (string)($row['program_name'] ?? '');
        try {
            if ($decision === 'admit') {
                $mailService->sendAdmittedEmail($email, $fullName, $referenceNumber, $programName);
            } else {
                $mailService->sendFinalRejectedEmail($email, $fullName, $referenceNumber);
            }
        } catch (Exception $e) {
            error_log('BPC iEnroll mail error: ' . $e->getMessage());
        }
    }

    $_SESSION['admin_success'] = $decision === 'admit'
        ? '✓ Applicant has been admitted successfully.'
        : '✕ Applicant has been rejected.';
} else {
    $_SESSION['admin_error'] = 'Could not update. The applicant may have already been decided.';
}

mysqli_close($conn);
redirect('app/admin/admin-final-decision.php');
?>