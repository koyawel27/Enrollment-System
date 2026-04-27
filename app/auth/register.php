<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * User Registration Handler
 * app/auth/register.php
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once APP_PATH . '/shared/MailService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$email            = clean_input($_POST['email'] ?? '');
$password         = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

$errors = [];

if (empty($email)) {
    $errors[] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format.';
}

if (empty($password)) {
    $errors[] = 'Password is required.';
} elseif (strlen($password) < 6) {
    $errors[] = 'Password must be at least 6 characters.';
}

if ($password !== $confirm_password) {
    $errors[] = 'Passwords do not match.';
}

if (empty($errors)) {
    $stmt = mysqli_prepare($conn, "SELECT id, email_verified FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($existing) {
        if ((int)$existing['email_verified'] === 0) {
            // Account exists but unverified — resend OTP and redirect to verify
            $otp        = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            $upd = mysqli_prepare($conn,
                "UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?"
            );
            mysqli_stmt_bind_param($upd, 'ssi', $otp, $expires_at, $existing['id']);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);

            try {
                $mail = new MailService();
                $mail->sendOtpEmail($email, $otp);
            } catch (Exception $e) {
                error_log('BPC iEnroll OTP mail error: ' . $e->getMessage());
            }

            $_SESSION['otp_user_id'] = $existing['id'];
            $_SESSION['otp_email']   = $email;
            redirect('app/auth/verify-email.php');
        } else {
            $errors[] = 'Email already registered. Please login instead.';
        }
    }
}

if (!empty($errors)) {
    $_SESSION['error'] = implode('<br>', $errors);
    redirect('index.php?tab=register');
}

// ── Create new unverified account ─────────────────────────
$hashed  = password_hash($password, PASSWORD_DEFAULT);
$otp     = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

$stmt = mysqli_prepare($conn,
    "INSERT INTO users (email, password, email_verified, otp_code, otp_expires_at)
     VALUES (?, ?, 0, ?, ?)"
);
mysqli_stmt_bind_param($stmt, 'ssss', $email, $hashed, $otp, $expires);

if (!mysqli_stmt_execute($stmt)) {
    $_SESSION['error'] = 'Registration failed. Please try again.';
    redirect('index.php?tab=register');
}

$user_id = mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

// Create draft application row
$app_stmt = mysqli_prepare($conn,
    "INSERT INTO applications (user_id, current_step, status) VALUES (?, 1, 'Draft')"
);
mysqli_stmt_bind_param($app_stmt, 'i', $user_id);
mysqli_stmt_execute($app_stmt);
mysqli_stmt_close($app_stmt);

// Send OTP email
try {
    $mail = new MailService();
    $mail->sendOtpEmail($email, $otp);
} catch (Exception $e) {
    error_log('BPC iEnroll OTP mail error: ' . $e->getMessage());
}

mysqli_close($conn);

$_SESSION['otp_user_id'] = $user_id;
$_SESSION['otp_email']   = $email;
redirect('app/auth/verify-email.php');