<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Resend OTP Handler
 * app/auth/resend-otp.php
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once APP_PATH . '/shared/MailService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

if (empty($_SESSION['otp_user_id']) || empty($_SESSION['otp_email'])) {
    redirect('index.php');
}

$user_id = (int)$_SESSION['otp_user_id'];
$email   = $_SESSION['otp_email'];

// ── Fetch current OTP state ────────────────────────────────
$stmt = mysqli_prepare($conn,
    "SELECT otp_expires_at,
            TIMESTAMPDIFF(
                SECOND,
                DATE_SUB(otp_expires_at, INTERVAL 10 MINUTE),
                NOW()
            ) AS seconds_since_generated
     FROM users
     WHERE id = ? AND email_verified = 0"
);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$row) {
    $_SESSION['error'] = 'Account not found or already verified.';
    redirect('app/auth/verify-email.php');
}

// ── 60-second cooldown check (DB clock) ────────────────────
$seconds_since = (int)($row['seconds_since_generated'] ?? 99999);

if ($seconds_since < 60) {
    $wait = 60 - $seconds_since;
    $_SESSION['error'] = "Please wait {$wait} second(s) before requesting a new code.";
    redirect('app/auth/verify-email.php');
}

// ── Generate fresh OTP ─────────────────────────────────────
$otp        = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

$upd = mysqli_prepare($conn,
    "UPDATE users
     SET otp_code = ?, otp_expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE)
     WHERE id = ?"
);
mysqli_stmt_bind_param($upd, 'si', $otp, $user_id);
mysqli_stmt_execute($upd);
mysqli_stmt_close($upd);
mysqli_close($conn);

// ── Send email ─────────────────────────────────────────────
try {
    $mail = new MailService();
    $mail->sendOtpEmail($email, $otp);
    $_SESSION['error'] = ''; // clear any previous error
} catch (Exception $e) {
    error_log('BPC iEnroll OTP resend error: ' . $e->getMessage());
    $_SESSION['error'] = 'Failed to send email. Please try again in a moment.';
    redirect('app/auth/verify-email.php');
}

redirect('app/auth/verify-email.php?resent=1');