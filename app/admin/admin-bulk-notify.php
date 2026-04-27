<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/admin-auth-check.php';
require_once CONFIG_PATH . '/admin-permissions.php';
require_once APP_PATH . '/shared/MailService.php';
require_admin_role([ADMIN_ROLE_SUPER_ADMIN, ADMIN_ROLE_ADMISSION_OFFICER]);

$mailService = new MailService();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/app/admin/admin-applications.php');
    exit;
}

$ids_raw = $_POST['ids'] ?? '';
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($ids_raw === '' || $subject === '' || $message === '') {
    $_SESSION['admin_error'] = 'Please select applications and provide subject and message.';
    header('Location: ' . BASE_URL . '/app/admin/admin-applications.php');
    exit;
}

$ids = array_filter(array_map('intval', explode(',', $ids_raw)));

if (empty($ids)) {
    $_SESSION['admin_error'] = 'No valid applications selected.';
    header('Location: ' . BASE_URL . '/app/admin/admin-applications.php');
    exit;
}

$ids_list = implode(',', $ids);

// Fetch recipient emails
$sql = "SELECT DISTINCT u.email
        FROM applications a
        JOIN users u ON a.user_id = u.id
        WHERE a.id IN ($ids_list)";

$result = mysqli_query($conn, $sql);
$emails = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        if (!empty($row['email'])) {
            $emails[] = $row['email'];
        }
    }
}

foreach ($emails as $email) {
    try {
        $mailService->sendBulkNotificationEmail($email, $subject, $message);
    } catch (Exception $e) {
        error_log('BPC iEnroll mail error: ' . $e->getMessage());
    }
}

$count = count($emails);

if ($count > 0) {
    $_SESSION['admin_success'] = 'Prepared notification for ' . $count . ' recipient(s). Please hook up your mailer implementation.';
} else {
    $_SESSION['admin_error'] = 'No email addresses found for selected applications.';
}

mysqli_close($conn);
header('Location: ' . BASE_URL . '/app/admin/admin-applications.php');
exit;
