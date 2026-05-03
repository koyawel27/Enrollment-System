<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Forgot Password
 * app/auth/forgot-password.php
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once APP_PATH  . '/shared/MailService.php';

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if user exists
        $stmt = mysqli_prepare($conn, "SELECT id, email_verified FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        // Always show success to prevent email enumeration
        if ($row && (int)$row['email_verified'] === 1) {
            $token = bin2hex(random_bytes(16)); // 32-char hex token (128-bit), compatible with shorter DB columns

            $upd = mysqli_prepare($conn,
                "UPDATE users
                 SET reset_token = ?, reset_token_expires = DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                 WHERE email = ?"
            );
            mysqli_stmt_bind_param($upd, 'ss', $token, $email);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);

            $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
            // Encode spaces in folder names (e.g. "/Enrollment System") for email client compatibility.
            $basePath = str_replace(' ', '%20', rtrim(BASE_URL, '/'));
            $reset_link = $scheme . '://' . $host . $basePath . '/app/auth/reset-password.php?token=' . rawurlencode($token);

            $mail = new MailService();
            $mail->sendPasswordResetEmail($email, 'Applicant', $reset_link);
        }

        // Always show success regardless of whether email exists
        $success = true;
    }

    mysqli_close($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | BPC iEnroll</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            background:linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding:1rem;
        }
        .card {
            background:#fff;
            border-radius:12px;
            padding:2.5rem;
            max-width:420px;
            width:100%;
            box-shadow:0 4px 20px rgba(0,0,0,0.08);
        }
        .icon {
            width:64px; height:64px;
            margin:0 auto 1.5rem;
            background:#d1ecf1;
            border-radius:50%;
            display:flex;
            align-items:center;
            justify-content:center;
        }
        .icon svg { width:32px; height:32px; fill:#0c5460; }
        .icon.success-icon { background:#d4edda; }
        .icon.success-icon svg { fill:#155724; }
        h1 { font-size:1.5rem; color:#1a1a1a; margin-bottom:0.75rem; text-align:center; }
        p { color:#666; line-height:1.6; margin-bottom:1.25rem; text-align:center; font-size:0.95rem; }
        .form-group { margin-bottom:1.25rem; }
        label { display:block; font-size:0.9rem; font-weight:600; color:#1a1a1a; margin-bottom:0.4rem; }
        input[type="email"] {
            width:100%; padding:0.75rem 1rem;
            border:2px solid #e0e0e0; border-radius:6px;
            font-size:0.95rem; font-family:inherit;
            transition:border-color 0.2s;
        }
        input[type="email"]:focus {
            outline:none; border-color:#006400;
            box-shadow:0 0 0 3px rgba(0,100,0,0.1);
        }
        .btn-submit {
            width:100%; padding:0.875rem;
            background:#006400; color:#fff;
            border:none; border-radius:6px;
            font-size:1rem; font-weight:600;
            cursor:pointer; transition:background 0.2s;
        }
        .btn-submit:hover { background:#004d00; }
        .btn-submit:disabled { background:#ccc; cursor:not-allowed; }
        .alert-error {
            background:#f8d7da; border:1px solid #f5c6cb;
            color:#721c24; border-radius:6px;
            padding:0.75rem 1rem; margin-bottom:1rem;
            font-size:0.9rem;
        }
        .back-link {
            display:block; text-align:center;
            color:#006400; text-decoration:none;
            font-size:0.9rem; margin-top:1.25rem;
            font-weight:600;
        }
        .back-link:hover { text-decoration:underline; }
    </style>
</head>
<body>
    <div class="card">

        <?php if ($success): ?>
            <!-- Success state -->
            <div class="icon success-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                </svg>
            </div>
            <h1>Check Your Email</h1>
            <p>
                If that email is registered and verified, you'll receive a password reset link shortly.
                The link expires in <strong>30 minutes</strong>.
            </p>
            <p style="font-size:0.85rem; color:#999;">
                Didn't receive it? Check your spam folder or try again.
            </p>
            <a href="<?php echo BASE_URL; ?>/index.php" class="back-link">&larr; Back to Login</a>

        <?php else: ?>
            <!-- Form state -->
            <div class="icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                </svg>
            </div>
            <h1>Forgot Password?</h1>
            <p>Enter your registered email address and we'll send you a link to reset your password.</p>

            <?php if ($error): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email"
                           placeholder="your.email@example.com"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           required autofocus>
                </div>
                <button type="submit" class="btn-submit" id="submitBtn">
                    Send Reset Link
                </button>
            </form>

            <a href="<?php echo BASE_URL; ?>/index.php" class="back-link">&larr; Back to Login</a>
        <?php endif; ?>

    </div>

    <script>
        // Disable button on submit to prevent double-click
        document.querySelector('form')?.addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Sending...';
            }
        });
    </script>
</body>
</html>