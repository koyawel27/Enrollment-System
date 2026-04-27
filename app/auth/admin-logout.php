<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin Logout Handler
 * auth/admin-logout.php
 *
 * Only destroys admin session variables.
 * Student session (if any) is untouched.
 */

session_start();

// Remove only admin session variables
unset($_SESSION['admin_id']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_email']);
unset($_SESSION['admin_role']);

// If no other session data remains, destroy completely
if (empty($_SESSION)) {
    session_destroy();
}

header('Location: ../../public/admin-login.php');
exit;
?>