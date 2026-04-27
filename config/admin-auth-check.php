<?php
/**
 * Admin Authentication Guard
 * config/admin-auth-check.php
 *
 * Include this at the top of EVERY admin page.
 * Redirects to admin login if not authenticated.
 * Backfills admin_role for sessions created before RBAC migration.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    require_once __DIR__ . '/paths.php';
    header('Location: ' . BASE_URL . '/public/admin-login.php');
    exit;
}

// Backfill admin_role for sessions created before RBAC (so dashboard etc. don't redirect loop)
if (!isset($_SESSION['admin_role'])) {
    require_once __DIR__ . '/db.php';
    $id = (int) $_SESSION['admin_id'];
    $res = @mysqli_query($conn, "SELECT role FROM admins WHERE id = $id LIMIT 1");
    if ($res && $row = mysqli_fetch_assoc($res)) {
        $_SESSION['admin_role'] = $row['role'] ?? 'super_admin';
    } else {
        $_SESSION['admin_role'] = 'super_admin';
    }
}
?>