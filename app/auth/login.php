<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * User Login Handler
 * app/auth/login.php
 */

session_start();
require_once CONFIG_PATH . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$email    = clean_input($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

$errors = [];

if (empty($email))    $errors[] = 'Email is required.';
if (empty($password)) $errors[] = 'Password is required.';

if (!empty($errors)) {
    $_SESSION['error'] = implode('<br>', $errors);
    redirect('index.php?error=' . urlencode($errors[0]));
}

$stmt = mysqli_prepare($conn,
    "SELECT id, email, password, email_verified, otp_expires_at
     FROM users WHERE email = ?"
);
mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$row) {
    $_SESSION['error'] = 'No account found with that email. Please register first.';
    redirect('index.php?error=user_not_found');
}

if (!password_verify($password, $row['password'])) {
    $_SESSION['error'] = 'Wrong password. Please try again.';
    redirect('index.php?error=wrong_password');
}

// ── Block unverified accounts ──────────────────────────────
if ((int)$row['email_verified'] === 0) {
    $_SESSION['otp_user_id'] = $row['id'];
    $_SESSION['otp_email']   = $email;
    $_SESSION['error'] = 'Your email is not verified yet. Please enter the OTP sent to your email.';
    redirect('app/auth/verify-email.php');
}

// ── Login success ──────────────────────────────────────────
$_SESSION['user_id']    = $row['id'];
$_SESSION['user_email'] = $row['email'];
$_SESSION['success']    = 'Login successful! Welcome back.';

$upd = mysqli_prepare($conn, "UPDATE users SET last_login = NOW() WHERE id = ?");
mysqli_stmt_bind_param($upd, 'i', $row['id']);
mysqli_stmt_execute($upd);
mysqli_stmt_close($upd);

mysqli_close($conn);
redirect('app/student/dashboard.php');