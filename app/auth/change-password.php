<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Student Change Password Handler
 * auth/change-password.php
 */

session_start();
require_once CONFIG_PATH . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('app/student/dashboard.php');
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'Please login again to change your password.';
    redirect('index.php');
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    $_SESSION['error'] = 'Invalid session. Please login again.';
    redirect('index.php');
}

$current_password = $_POST['current_password'] ?? '';
$new_password     = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if ($current_password === '' || $new_password === '' || $confirm_password === '') {
    $_SESSION['error'] = 'Please fill in all password fields.';
    redirect('app/student/dashboard.php');
}

if (strlen($new_password) < 6) {
    $_SESSION['error'] = 'New password must be at least 6 characters.';
    redirect('app/student/dashboard.php');
}

if ($new_password !== $confirm_password) {
    $_SESSION['error'] = 'New passwords do not match.';
    redirect('app/student/dashboard.php');
}

$stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$row) {
    $_SESSION['error'] = 'Account not found.';
    redirect('app/student/dashboard.php');
}

if (!password_verify($current_password, $row['password'])) {
    $_SESSION['error'] = 'Current password is incorrect.';
    redirect('app/student/dashboard.php');
}

$hash = password_hash($new_password, PASSWORD_DEFAULT);
$upd = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
mysqli_stmt_bind_param($upd, 'si', $hash, $user_id);
mysqli_stmt_execute($upd);
$ok = mysqli_stmt_affected_rows($upd) >= 0;
mysqli_stmt_close($upd);
mysqli_close($conn);

if ($ok) {
    $_SESSION['success'] = 'Password updated successfully.';
} else {
    $_SESSION['error'] = 'Failed to update password. Please try again.';
}

redirect('app/student/dashboard.php');
