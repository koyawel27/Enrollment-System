<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/admin-auth-check.php';
require_once CONFIG_PATH . '/admin-permissions.php';
require_admin_role([ADMIN_ROLE_SUPER_ADMIN, ADMIN_ROLE_ADMISSION_OFFICER]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/app/admin/admin-applications.php');
    exit;
}

$ids_raw    = $_POST['ids'] ?? '';
$new_status = trim($_POST['status'] ?? '');
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

if ($ids_raw === '' || $new_status === '') {
    $_SESSION['admin_error'] = 'Please select applications and a status.';
    header('Location: ' . BASE_URL . '/app/admin/admin-applications.php');
    exit;
}

$allowed_statuses = [
    'Documents Under Review',
    'Documents Verified',
    'Exam Scheduled',
    'Interview Scheduled',
];

if (!in_array($new_status, $allowed_statuses, true)) {
    $_SESSION['admin_error'] = 'Invalid status selected.';
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
$escaped_status = mysqli_real_escape_string($conn, $new_status);

// Fetch current statuses for logging
$current = [];
$res = mysqli_query($conn, "SELECT id, status FROM applications WHERE id IN ($ids_list)");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $current[(int)$row['id']] = $row['status'];
    }
}

// Bulk update
$sql = "UPDATE applications
        SET status = '$escaped_status', updated_at = NOW()
        WHERE id IN ($ids_list)";

if (mysqli_query($conn, $sql)) {
    // Log into status_history for each affected application
    if (!empty($current)) {
        $log_stmt = mysqli_prepare($conn,
            "INSERT INTO status_history
                (application_id, old_status, new_status, changed_by, notes)
             VALUES (?, ?, ?, ?, ?)"
        );
        if ($log_stmt) {
            $note = 'Bulk update from applications';
            foreach ($current as $app_id => $old_status) {
                if ($old_status === $new_status) {
                    continue;
                }
                mysqli_stmt_bind_param($log_stmt, 'issss',
                    $app_id,
                    $old_status,
                    $new_status,
                    $admin_name,
                    $note
                );
                mysqli_stmt_execute($log_stmt);
            }
            mysqli_stmt_close($log_stmt);
        }
    }

    $_SESSION['admin_success'] = 'Status updated for ' . count($ids) . ' application(s).';
} else {
    $_SESSION['admin_error'] = 'Failed to update statuses. Please try again.';
}

mysqli_close($conn);
header('Location: ' . BASE_URL . '/app/admin/admin-applications.php');
exit;
