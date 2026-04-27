<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin Delete Handler - Remove Admin
 * admin-delete-admin.php
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/admin-auth-check.php';
require_once CONFIG_PATH . '/admin-permissions.php';
require_admin_role([ADMIN_ROLE_SUPER_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/app/admin/admin-user-management.php');
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    $_SESSION['admin_error'] = 'Invalid request.';
    header('Location: ' . BASE_URL . '/app/admin/admin-user-management.php');
    exit;
}

// Cannot delete self
if ($id == ($_SESSION['admin_id'] ?? 0)) {
    $_SESSION['admin_error'] = 'You cannot delete your own account.';
    header('Location: ' . BASE_URL . '/app/admin/admin-user-management.php');
    exit;
}

// Count admins - must keep at least one
$count = 0;
$res = mysqli_query($conn, 'SELECT COUNT(*) as c FROM admins');
if ($row = mysqli_fetch_assoc($res)) $count = (int)$row['c'];

if ($count <= 1) {
    $_SESSION['admin_error'] = 'Cannot delete the last admin. At least one admin must remain.';
    header('Location: ' . BASE_URL . '/app/admin/admin-user-management.php');
    exit;
}

$stmt = mysqli_prepare($conn, 'DELETE FROM admins WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$affected = mysqli_affected_rows($conn);
mysqli_stmt_close($stmt);

if ($affected > 0) {
    $_SESSION['admin_success'] = 'Admin removed successfully.';
} else {
    $_SESSION['admin_error'] = 'Admin not found or could not be deleted.';
}

mysqli_close($conn);
header('Location: ' . BASE_URL . '/app/admin/admin-user-management.php');
exit;