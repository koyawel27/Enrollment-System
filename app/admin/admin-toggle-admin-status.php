<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin Toggle Status (Active/Inactive)
 * admin-toggle-admin-status.php
 *
 * Super Admin only. Sets is_active = 0 or 1. Prevents deactivating the last active Super Admin.
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

$id       = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$set_active = isset($_POST['set_active']) ? (int)$_POST['set_active'] : -1;

if ($id <= 0 || !in_array($set_active, [0, 1], true)) {
    $_SESSION['admin_error'] = 'Invalid request.';
    header('Location: ' . BASE_URL . '/app/admin/admin-user-management.php');
    exit;
}

if ($id == ($_SESSION['admin_id'] ?? 0)) {
    $_SESSION['admin_error'] = 'You cannot deactivate your own account.';
    header('Location: ' . BASE_URL . '/app/admin/admin-user-management.php');
    exit;
}

$stmt_get = mysqli_prepare($conn, 'SELECT id, role, is_active FROM admins WHERE id = ?');
mysqli_stmt_bind_param($stmt_get, 'i', $id);
mysqli_stmt_execute($stmt_get);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get));
mysqli_stmt_close($stmt_get);

if (!$row) {
    $_SESSION['admin_error'] = 'Admin not found.';
    header('Location: ' . BASE_URL . '/app/admin/admin-user-management.php');
    exit;
}

// Deactivating: ensure at least one active Super Admin remains
if ($set_active === 0 && ($row['role'] ?? '') === ADMIN_ROLE_SUPER_ADMIN) {
    $role_super = ADMIN_ROLE_SUPER_ADMIN;
    $cnt_stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS c FROM admins WHERE role = ? AND is_active = 1 AND id != ?');
    mysqli_stmt_bind_param($cnt_stmt, 'si', $role_super, $id);
    mysqli_stmt_execute($cnt_stmt);
    $count = 0;
    $cr = mysqli_fetch_assoc(mysqli_stmt_get_result($cnt_stmt));
    if ($cr) $count = (int)$cr['c'];
    mysqli_stmt_close($cnt_stmt);
    if ($count < 1) {
        $_SESSION['admin_error'] = 'Cannot deactivate: at least one active Super Admin must remain.';
        header('Location: ' . BASE_URL . '/app/admin/admin-user-management.php');
        exit;
    }
}

$stmt = mysqli_prepare($conn, 'UPDATE admins SET is_active = ? WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'ii', $set_active, $id);
mysqli_stmt_execute($stmt);
$affected = mysqli_affected_rows($conn);
mysqli_stmt_close($stmt);

if ($affected > 0) {
    $_SESSION['admin_success'] = $set_active ? 'Admin activated. They can log in again.' : 'Admin deactivated. They can no longer log in until reactivated.';
} else {
    $_SESSION['admin_error'] = 'Update failed or no change.';
}

mysqli_close($conn);
header('Location: ' . BASE_URL . '/app/admin/admin-user-management.php');
exit;