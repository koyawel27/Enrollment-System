<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Student Logout Handler
 * auth/logout.php
 *
 * Only destroys student session variables.
 * Admin session (if any) is left untouched.
 */

session_start();

// Remove ONLY student session variables
unset($_SESSION['user_id']);
unset($_SESSION['user_email']);
unset($_SESSION['success']);
unset($_SESSION['error']);
unset($_SESSION['form_errors']);

// If nothing left in session, destroy completely
if (empty($_SESSION)) {
    session_destroy();
}

header('Location: ' . BASE_URL . '/index.php');
exit;
?>