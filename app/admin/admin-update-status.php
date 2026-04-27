<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin Status Update Handler
 * admin-update-status.php
 *
 * Handles ALL application status changes from admin.
 * Only accepts POST. Requires admin session.
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/admin-auth-check.php';
require_once CONFIG_PATH . '/admin-permissions.php';
require_once APP_PATH . '/shared/MailService.php';
require_once CONFIG_PATH . '/applicant-messages.php';
require_admin_role([ADMIN_ROLE_SUPER_ADMIN, ADMIN_ROLE_ADMISSION_OFFICER]);

$mailService = new MailService();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/app/admin/admin-dashboard.php');
    exit;
}

// Get POST data
$application_id = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
$new_status     = trim($_POST['new_status'] ?? '');
$rejection_reason = trim($_POST['rejection_reason'] ?? '');
$return_to      = trim($_POST['return_to'] ?? '');
$admin_name     = $_SESSION['admin_name'];

// Validate application_id
if ($application_id <= 0) {
    $_SESSION['admin_error'] = 'Invalid application.';
    header('Location: ' . BASE_URL . '/app/admin/admin-dashboard.php');
    exit;
}

// Validate new_status is not empty
if (empty($new_status)) {
    $_SESSION['admin_error'] = 'No status provided.';
    header('Location: ' . BASE_URL . '/app/admin/admin-application-detail.php?id=' . $application_id);
    exit;
}

// ============================================
// FETCH CURRENT STATUS FROM DB
// ============================================
$fetch = mysqli_prepare($conn, 'SELECT status FROM applications WHERE id = ?');
mysqli_stmt_bind_param($fetch, 'i', $application_id);
mysqli_stmt_execute($fetch);
$fetch_result = mysqli_stmt_get_result($fetch);
$application  = mysqli_fetch_assoc($fetch_result);
mysqli_stmt_close($fetch);

if (!$application) {
    $_SESSION['admin_error'] = 'Application not found.';
    header('Location: ' . BASE_URL . '/app/admin/admin-dashboard.php');
    exit;
}

$current_status = $application['status'];

// ============================================
// VALIDATE STATUS TRANSITION
// Only allow logical progressions
// ============================================
$allowed_transitions = [
    'Application Submitted'   => ['Documents Under Review'],
    'Documents Under Review'  => ['Documents Verified', 'Documents Rejected'],
    'Documents Re-submitted'  => ['Documents Verified', 'Documents Rejected'],
    // Future phases (placeholders)
    'Documents Verified'      => ['Exam Scheduled'],
    'Exam Scheduled'          => ['Exam Completed'],
    'Exam Completed'          => ['Interview Scheduled', 'Rejected'],
    'Interview Scheduled'     => ['Admitted/Enrolled', 'Rejected'],
];

$allowed = $allowed_transitions[$current_status] ?? [];

if (!in_array($new_status, $allowed)) {
    $_SESSION['admin_error'] = 'Invalid status transition from "' 
        . $current_status . '" to "' . $new_status . '".';
    header('Location: ' . BASE_URL . '/app/admin/admin-application-detail.php?id=' . $application_id);
    exit;
}

// ============================================
// EXTRA VALIDATION FOR REJECTION
// ============================================
if ($new_status === 'Documents Rejected' && empty($rejection_reason)) {
    $_SESSION['admin_error'] = 'Please provide a reason for rejection.';
    header('Location: ' . BASE_URL . '/app/admin/admin-application-detail.php?id=' . $application_id);
    exit;
}

// ============================================
// UPDATE APPLICATION STATUS IN DB
// ============================================
if ($new_status === 'Documents Rejected') {
    // Update with rejection reason and date
    $update = mysqli_prepare($conn,
        'UPDATE applications
         SET status = ?,
             rejection_reason = ?,
             rejection_date = NOW(),
             updated_at = NOW()
         WHERE id = ?'
    );
    mysqli_stmt_bind_param($update, 'ssi',
        $new_status,
        $rejection_reason,
        $application_id
    );
} else {
    // Standard status update
    $update = mysqli_prepare($conn,
        'UPDATE applications
         SET status = ?,
             updated_at = NOW()
         WHERE id = ?'
    );
    mysqli_stmt_bind_param($update, 'si',
        $new_status,
        $application_id
    );
}

$ok = mysqli_stmt_execute($update);
mysqli_stmt_close($update);

if (!$ok) {
    $_SESSION['admin_error'] = 'Failed to update status. Please try again.';
    header('Location: ' . BASE_URL . '/app/admin/admin-application-detail.php?id=' . $application_id);
    exit;
}

// ============================================
// LOG TO STATUS HISTORY
// ============================================
$log = mysqli_prepare($conn,
    'INSERT INTO status_history
        (application_id, old_status, new_status, changed_by, notes)
     VALUES (?, ?, ?, ?, ?)'
);

$notes = ($new_status === 'Documents Rejected')
    ? 'Rejection reason: ' . $rejection_reason
    : '';

mysqli_stmt_bind_param($log, 'issss',
    $application_id,
    $current_status,
    $new_status,
    $admin_name,
    $notes
);
mysqli_stmt_execute($log);
mysqli_stmt_close($log);

// Fetch user_id and applicant info for in-app message
$msgFetch = mysqli_prepare($conn,
    "SELECT a.user_id, a.first_name, a.last_name, a.reference_number
     FROM applications a WHERE a.id = ? LIMIT 1"
);
mysqli_stmt_bind_param($msgFetch, 'i', $application_id);
mysqli_stmt_execute($msgFetch);
$msgRow = mysqli_fetch_assoc(mysqli_stmt_get_result($msgFetch));
mysqli_stmt_close($msgFetch);
$applicant_user_id = (int)($msgRow['user_id'] ?? 0);

if ($applicant_user_id > 0) {
    if ($new_status === 'Documents Verified') {
        add_applicant_message(
            $conn,
            $applicant_user_id,
            'documents_verified',
            'Documents Verified',
            'Your submitted documents have been reviewed and verified by the admissions office. Please wait for further instructions regarding the next steps.'
        );
    } elseif ($new_status === 'Documents Rejected') {
        add_applicant_message(
            $conn,
            $applicant_user_id,
            'documents_rejected',
            'Documents Rejected',
            'Your submitted documents were rejected. Reason: ' . $rejection_reason . '. Please re-upload the correct documents.'
        );
    } elseif ($new_status === 'Documents Under Review') {
        add_applicant_message(
            $conn,
            $applicant_user_id,
            'documents_review',
            'Documents Under Review',
            'Your submitted documents are now being reviewed by the admissions office. You will be notified once the review is complete.'
        );
    }
}

// Mail notifications for document review outcomes (non-blocking)
if ($new_status === 'Documents Verified' || $new_status === 'Documents Rejected') {
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
        $email = trim((string)$mailRow['email']);
        $fullName = trim((string)(($mailRow['first_name'] ?? '') . ' ' . ($mailRow['last_name'] ?? '')));
        $referenceNumber = (string)($mailRow['reference_number'] ?? '');

        try {
            if ($new_status === 'Documents Verified') {
                $mailService->sendDocumentsVerifiedEmail($email, $fullName, $referenceNumber);
            } else {
                $mailService->sendDocumentsRejectedEmail($email, $fullName, $referenceNumber, $rejection_reason);
            }
        } catch (Exception $e) {
            error_log('BPC iEnroll mail error: ' . $e->getMessage());
        }
    }
}

mysqli_close($conn);

// ============================================
// SUCCESS - Redirect
// ============================================
$_SESSION['admin_success'] = 'Application status updated to "' . $new_status . '".';
if ($return_to === 'dashboard') {
    header('Location: ' . BASE_URL . '/app/admin/admin-dashboard.php');
} elseif ($return_to === 'review') {
    header('Location: ' . BASE_URL . '/app/admin/admin-review-documents.php?id=' . $application_id);
} else {
    header('Location: ' . BASE_URL . '/app/admin/admin-application-detail.php?id=' . $application_id);
}
exit;
?>