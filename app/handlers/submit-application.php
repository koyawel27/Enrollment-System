<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Final Submit Application Handler
 * submit-application.php
 *
 * Receives POST from Step 7 form (certify, dataPrivacy only).
 * All other data already in DB from steps 1-6.
 * No AJAX - traditional form POST + redirect.
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/applicant-messages.php';
require_once APP_PATH . '/shared/MailService.php';

$mailService = new MailService();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/app/student/application-form.php?step=7');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Step 7 form only sends certify and dataPrivacy
$certify = !empty($_POST['certify']);
$data_privacy = !empty($_POST['dataPrivacy']);

if (!$certify || !$data_privacy) {
    $_SESSION['form_errors'] = ['You must certify and agree to the Data Privacy consent before submitting.'];
    header('Location: ' . BASE_URL . '/app/student/application-form.php?step=7');
    exit;
}

// Load full application row (data from steps 1-6)
$stmt = mysqli_prepare($conn, "SELECT * FROM applications WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$app = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$app) {
    $_SESSION['form_errors'] = ['Application not found. Please start from Step 1.'];
    header('Location: ' . BASE_URL . '/app/student/application-form.php?step=1');
    exit;
}

$status = $app['status'] ?? 'Draft';
$already_submitted = !empty($app['submitted']) || in_array($status, ['Submitted', 'Application Submitted'], true);
if ($already_submitted) {
    header('Location: ' . BASE_URL . '/app/student/dashboard.php');
    exit;
}

// Basic server-side check: required fields from steps 1-6
$required = [
    'last_name', 'first_name', 'sex', 'date_of_birth', 'birth_city', 'birth_province',
    'civil_status', 'citizenship', 'mobile_number', 'email', 'current_house_street',
    'current_barangay', 'current_city', 'current_province', 'current_zip_code',
    'father_last_name', 'father_first_name', 'father_occupation', 'father_education',
    'mother_last_name', 'mother_first_name', 'mother_occupation', 'mother_education',
    'applicant_type', 'first_choice', 'id_photo_path', 'birth_cert_path'
];
$missing = [];
foreach ($required as $k) {
    $v = $app[$k] ?? '';
    if ($v === null || $v === '') $missing[] = str_replace('_', ' ', ucfirst($k));
}
if ($app['applicant_type'] === 'Freshmen') {
    if (empty($app['shs_name']) || empty($app['shs_address']) || empty($app['shs_strand']) || empty($app['shs_gwa'])) $missing[] = 'SHS information';
    if (empty($app['grades_path'])) $missing[] = 'Report Card';
} elseif ($app['applicant_type'] === 'Transferee') {
    if (empty($app['prev_school_name']) || empty($app['transfer_reason'])) $missing[] = 'Transferee information';
    if (empty($app['transfer_cred_path']) || empty($app['tor_path'])) $missing[] = 'Transfer Credential and TOR';
}
if (!empty($missing)) {
    $_SESSION['form_errors'] = ['Some required information is missing: ' . implode(', ', $missing) . '. Please go back and complete all steps.'];
    header('Location: ' . BASE_URL . '/app/student/application-form.php?step=7');
    exit;
}

// Generate reference number
$reference_number = 'BPC-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));

$stmt = mysqli_prepare($conn, "
    UPDATE applications
    SET reference_number = ?, status = 'Application Submitted', submitted = 1,
        submitted_at = NOW(), current_step = 7, updated_at = NOW()
    WHERE user_id = ?
");
mysqli_stmt_bind_param($stmt, "si", $reference_number, $user_id);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if ($ok) {
    add_applicant_message(
        $conn,
        $user_id,
        'application_submitted',
        'Application Submitted',
        'Your application has been successfully submitted. Reference number: ' . $reference_number . '. The admissions office will review your documents shortly.'
    );
}

mysqli_close($conn);

if (!$ok) {
    $_SESSION['form_errors'] = ['Failed to submit. Please try again.'];
    header('Location: ' . BASE_URL . '/app/student/application-form.php?step=7');
    exit;
}

// Send applicant confirmation email (non-blocking)
$email = trim((string)($app['email'] ?? ''));
$fullName = trim((string)(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? '')));
if ($email !== '') {
    try {
        $mailService->sendApplicationSubmittedEmail($email, $fullName, $reference_number);
    } catch (Exception $e) {
        error_log('BPC iEnroll mail error: ' . $e->getMessage());
    }
}

$_SESSION['form_errors'] = [];
header('Location: ' . BASE_URL . '/app/student/dashboard.php');
exit;