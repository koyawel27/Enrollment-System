<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin - Add Internal Note for Application
 * admin-add-note.php
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/admin-auth-check.php';
require_once CONFIG_PATH . '/admin-permissions.php';
require_admin_role([ADMIN_ROLE_SUPER_ADMIN, ADMIN_ROLE_ADMISSION_OFFICER]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/app/admin/admin-dashboard.php');
    exit;
}

$application_id = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
$note           = trim($_POST['note'] ?? '');
$admin_name     = $_SESSION['admin_name'] ?? 'Admin';

if ($application_id <= 0 || $note === '') {
    $_SESSION['admin_error'] = 'Please provide a note before saving.';
    header('Location: ' . BASE_URL . '/app/admin/admin-dashboard.php');
    exit;
}

// Insert note
if ($stmt = mysqli_prepare($conn,
    "INSERT INTO application_notes (application_id, admin_name, note, created_at)
     VALUES (?, ?, ?, NOW())"
)) {
    mysqli_stmt_bind_param($stmt, 'iss', $application_id, $admin_name, $note);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $_SESSION['admin_success'] = 'Note saved.';
} else {
    $_SESSION['admin_error'] = 'Failed to save note. Please try again.';
}

mysqli_close($conn);
header('Location: ' . BASE_URL . '/app/admin/admin-application-detail.php?id=' . $application_id);
exit;
