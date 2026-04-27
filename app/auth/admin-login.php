<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin Login Handler
 * auth/admin-login.php
 */

session_start();
require_once CONFIG_PATH . '/db.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../public/admin-login.php');
    exit;
}

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Basic validation
if (empty($email) || empty($password)) {
    $_SESSION['admin_error'] = 'Email and password are required.';
    header('Location: ../../public/admin-login.php');
    exit;
}

// Look up admin by email
$stmt = mysqli_prepare($conn, 'SELECT id, name, email, password, role, is_active FROM admins WHERE email = ?');
mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$admin  = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Verify password
if (!$admin || !password_verify($password, $admin['password'])) {
    $_SESSION['admin_error'] = 'Invalid email or password.';
    header('Location: ../../public/admin-login.php');
    exit;
}

// Reject inactive accounts
if (empty($admin['is_active'])) {
    $_SESSION['admin_error'] = 'This account is inactive. Contact an administrator.';
    header('Location: ../../public/admin-login.php');
    exit;
}

// Update last login
$upd = mysqli_prepare($conn, 'UPDATE admins SET last_login_at = NOW() WHERE id = ?');
mysqli_stmt_bind_param($upd, 'i', $admin['id']);
mysqli_stmt_execute($upd);
mysqli_stmt_close($upd);

// Auth success: set admin session (keep separate from student session)
$_SESSION['admin_id']    = $admin['id'];
$_SESSION['admin_name']  = $admin['name'];
$_SESSION['admin_email'] = $admin['email'];
$_SESSION['admin_role']  = $admin['role'] ?? 'admission_officer';

mysqli_close($conn);

header('Location: ../admin/admin-dashboard.php');
exit;
?>