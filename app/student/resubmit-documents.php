<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Document Resubmission Handler
 * resubmit-documents.php
 *
 * Handles Step 6 POST when status is 'Documents Rejected'.
 * Saves new file paths, increments resubmission_count,
 * updates status to 'Documents Re-submitted',
 * logs to status_history, redirects to dashboard.
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once APP_PATH . '/shared/MailService.php';

$mailService = new MailService();

// Must be logged in as student
if (!isset($_SESSION['user_id'])) {
    redirect('index.php');
}

// Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('app/student/dashboard.php');
}

$user_id = (int)$_SESSION['user_id'];

// Block resubmission when application period is closed
$applications_open = get_setting('application_open', '1') === '1';
if (!$applications_open) {
    $_SESSION['error'] = 'The application period is currently closed. You can no longer re-upload documents.';
    redirect('app/student/dashboard.php');
}

// Fetch current application
$stmt = mysqli_prepare($conn,
    "SELECT * FROM applications WHERE user_id = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$app = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Only allow if status is 'Documents Rejected'
if (!$app || $app['status'] !== 'Documents Rejected') {
    redirect('app/student/dashboard.php');
}

$applicant_type = $app['applicant_type'] ?? 'Freshmen';
$errors = [];

// ── FILE UPLOAD HANDLER ────────────────────────────────
$upload_dir = BASE_PATH . '/uploads/';
if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);

$allowed = ['image/jpeg', 'image/png', 'application/pdf'];
$max     = 10 * 1024 * 1024; // 10MB

function handle_reupload($field, $label, $upload_dir, $allowed, $max, &$errors, $required = true) {
    // No file chosen
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            $errors[] = $label . ' is required.';
        }
        return null; // Will keep existing path
    }

    $f = $_FILES[$field];

    if ($f['error'] !== UPLOAD_ERR_OK) {
        $errors[] = $label . ' upload error (code: ' . $f['error'] . ').';
        return null;
    }

    if (!in_array($f['type'], $allowed, true)) {
        $errors[] = $label . ' must be JPG, PNG, or PDF.';
        return null;
    }

    if ($f['size'] > $max) {
        $errors[] = $label . ' must not exceed 10MB.';
        return null;
    }

    $ext  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $safe = $field . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $upload_dir . $safe;

    if (!move_uploaded_file($f['tmp_name'], $dest)) {
        $errors[] = 'Failed to save ' . $label . '. Please try again.';
        return null;
    }

    return 'uploads/' . $safe;
}

// Process uploads (required: id photo, birth cert; grades required for Freshmen)
$id_photo_path    = handle_reupload('idPhoto',   '2x2 ID Photo',         $upload_dir, $allowed, $max, $errors, true);
$grades_required  = ($applicant_type === 'Freshmen');
$grades_path      = handle_reupload('grades',    'Report Card',          $upload_dir, $allowed, $max, $errors, $grades_required);
$birth_cert_path  = handle_reupload('birthCert', 'PSA Birth Certificate', $upload_dir, $allowed, $max, $errors, true);

$transfer_cred_path = null;
$tor_path           = null;

if ($applicant_type === 'Transferee') {
    $transfer_cred_path = handle_reupload('transferCred', 'Transfer Credential', $upload_dir, $allowed, $max, $errors, true);
    $tor_path           = handle_reupload('tor',          'Transcript of Records', $upload_dir, $allowed, $max, $errors, true);
} else {
    // Optional for non-transferees
    $transfer_cred_path = handle_reupload('transferCred', 'Transfer Credential', $upload_dir, $allowed, $max, $errors, false);
    $tor_path           = handle_reupload('tor',          'Transcript of Records', $upload_dir, $allowed, $max, $errors, false);
}

// If errors, redirect back to step 6 reupload
if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    redirect('app/student/application-form.php?step=6&reupload=1');
}

// ── MERGE WITH EXISTING PATHS ──────────────────────────
// If student didn't re-upload a specific file, keep the old one
$id_photo_path      = $id_photo_path      ?? ($app['id_photo_path']      ?? null);
$grades_path        = $grades_path        ?? ($app['grades_path']        ?? null);
$birth_cert_path    = $birth_cert_path    ?? ($app['birth_cert_path']    ?? null);
$transfer_cred_path = $transfer_cred_path ?? ($app['transfer_cred_path'] ?? null);
$tor_path           = $tor_path           ?? ($app['tor_path']           ?? null);

// ── UPDATE APPLICATION IN DB ───────────────────────────
$new_resubmission_count = ((int)$app['resubmission_count'] ?? 0) + 1;

$update = mysqli_prepare($conn,
    "UPDATE applications SET
        id_photo_path       = ?,
        grades_path         = ?,
        birth_cert_path     = ?,
        transfer_cred_path  = ?,
        tor_path            = ?,
        status              = 'Documents Re-submitted',
        resubmission_count  = ?,
        rejection_reason    = NULL,
        rejection_date      = NULL,
        updated_at          = NOW()
     WHERE user_id = ?"
);
mysqli_stmt_bind_param($update, 'sssssii',
    $id_photo_path,
    $grades_path,
    $birth_cert_path,
    $transfer_cred_path,
    $tor_path,
    $new_resubmission_count,
    $user_id
);
mysqli_stmt_execute($update);
mysqli_stmt_close($update);

// ── LOG TO STATUS HISTORY ──────────────────────────────
$student_name = trim(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? ''));
$log = mysqli_prepare($conn,
    "INSERT INTO status_history
        (application_id, old_status, new_status, changed_by, notes)
     VALUES (?, 'Documents Rejected', 'Documents Re-submitted', ?, ?)"
);
$notes = 'Student re-uploaded documents. Attempt #' . $new_resubmission_count;
mysqli_stmt_bind_param($log, 'iss',
    $app['id'],
    $student_name,
    $notes
);
mysqli_stmt_execute($log);
mysqli_stmt_close($log);

// Notify all active admins that documents were re-submitted (non-blocking)
$mailApplicantFetch = mysqli_prepare($conn,
    "SELECT a.reference_number, a.first_name, a.last_name, u.email
     FROM applications a
     JOIN users u ON a.user_id = u.id
     WHERE a.id = ?
     LIMIT 1"
);
$appIdForMail = (int)$app['id'];
mysqli_stmt_bind_param($mailApplicantFetch, 'i', $appIdForMail);
mysqli_stmt_execute($mailApplicantFetch);
$mailApplicant = mysqli_fetch_assoc(mysqli_stmt_get_result($mailApplicantFetch));
mysqli_stmt_close($mailApplicantFetch);

$referenceNumber = (string)($mailApplicant['reference_number'] ?? '');
$studentFullName = trim((string)(($mailApplicant['first_name'] ?? '') . ' ' . ($mailApplicant['last_name'] ?? '')));
$studentEmail = trim((string)($mailApplicant['email'] ?? ''));

if ($studentEmail !== '') {
    try {
        $mailService->sendDocumentsResubmittedStudentEmail($studentEmail, $studentFullName, $referenceNumber);
    } catch (Exception $e) {
        error_log('BPC iEnroll mail error: ' . $e->getMessage());
    }
}

$adminRes = mysqli_query($conn, "SELECT name, email FROM admins WHERE is_active = 1");
if ($adminRes) {
    while ($adminRow = mysqli_fetch_assoc($adminRes)) {
        $adminEmail = trim((string)($adminRow['email'] ?? ''));
        $adminName = trim((string)($adminRow['name'] ?? 'Admin'));
        if ($adminEmail === '') {
            continue;
        }

        try {
            $mailService->sendDocumentsResubmittedAdminEmail($adminEmail, $adminName, $studentFullName, $referenceNumber);
        } catch (Exception $e) {
            error_log('BPC iEnroll mail error: ' . $e->getMessage());
        }
    }
}

mysqli_close($conn);

// ── SUCCESS ────────────────────────────────────────────
$_SESSION['success'] = 'Your documents have been re-submitted successfully! 
    The admission officer will review them shortly.';
redirect('app/student/dashboard.php');
?>