<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Reset Password
 * app/auth/reset-password.php
 */

session_start();
require_once CONFIG_PATH . '/db.php';

$token      = trim($_GET['token'] ?? '');
$error      = '';
$success    = false;
$valid_token = false;
$user_row   = null;

// ── Validate token ─────────────────────────────────────────
if (!empty($token)) {
    $stmt = mysqli_prepare($conn,
        "SELECT id, email FROM users
         WHERE reset_token = ?
           AND reset_token_expires > NOW()
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
    $user_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($user_row) {
        $valid_token = true;
    }
}

// ── Handle form submission ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $new_password     = $_POST['new_password']     ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);

        // Update password and clear reset token
        $upd = mysqli_prepare($conn,
            "UPDATE users
             SET password = ?, reset_token = NULL, reset_token_expires = NULL
             WHERE id = ?"
        );
        mysqli_stmt_bind_param($upd, 'si', $hash, $user_row['id']);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);

        $success = true;
    }
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | BPC iEnroll</title>
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
            border-radius:50%;
            display:flex;
            align-items:center;
            justify-content:center;
        }
        .icon.lock  { background:#d1ecf1; }
        .icon.lock svg { fill:#0c5460; }
        .icon.success { background:#d4edda; }
        .icon.success svg { fill:#155724; }
        .icon.error-icon { background:#f8d7da; }
        .icon.error-icon svg { fill:#721c24; }
        .icon svg { width:32px; height:32px; }
        h1 { font-size:1.5rem; color:#1a1a1a; margin-bottom:0.75rem; text-align:center; }
        p  { color:#666; line-height:1.6; margin-bottom:1.25rem; text-align:center; font-size:0.95rem; }
        .form-group { margin-bottom:1.25rem; }
        label { display:block; font-size:0.9rem; font-weight:600; color:#1a1a1a; margin-bottom:0.4rem; }
        .password-wrapper { position:relative; }
        input[type="password"], input[type="text"] {
            width:100%; padding:0.75rem 2.75rem 0.75rem 1rem;
            border:2px solid #e0e0e0; border-radius:6px;
            font-size:0.95rem; font-family:inherit;
            transition:border-color 0.2s;
        }
        input:focus {
            outline:none; border-color:#006400;
            box-shadow:0 0 0 3px rgba(0,100,0,0.1);
        }
        .toggle-pw {
            position:absolute; right:0.75rem; top:50%;
            transform:translateY(-50%);
            background:none; border:none;
            cursor:pointer; font-size:1.1rem; color:#666;
            padding:0;
        }
        .helper {
            font-size:0.8rem; color:#999; margin-top:0.35rem;
        }
        .alert-error {
            background:#f8d7da; border:1px solid #f5c6cb;
            color:#721c24; border-radius:6px;
            padding:0.75rem 1rem; margin-bottom:1rem;
            font-size:0.9rem;
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

    <?php if (!$valid_token && !$success): ?>
        <!-- Invalid / expired token -->
        <div class="icon error-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
            </svg>
        </div>
        <h1>Link Expired</h1>
        <p>This password reset link is invalid or has expired. Links are valid for <strong>30 minutes</strong>.</p>
        <a href="<?php echo BASE_URL; ?>/app/auth/forgot-password.php" class="back-link">Request a new link</a>

    <?php elseif ($success): ?>
        <!-- Success -->
        <div class="icon success">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/>
            </svg>
        </div>
        <h1>Password Updated!</h1>
        <p>Your password has been reset successfully. You can now log in with your new password.</p>
        <a href="<?php echo BASE_URL; ?>/index.php" class="back-link">Go to Login &rarr;</a>

    <?php else: ?>
        <!-- Reset form -->
        <div class="icon lock">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
            </svg>
        </div>
        <h1>Reset Password</h1>
        <p>Enter your new password below.</p>

        <?php if ($error): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="?token=<?php echo htmlspecialchars($token); ?>" id="resetForm">
            <div class="form-group">
                <label for="new_password">New Password</label>
                <div class="password-wrapper">
                    <input type="password" id="new_password" name="new_password"
                           placeholder="Minimum 6 characters" required minlength="6">
                    <button type="button" class="toggle-pw" onclick="togglePw('new_password')">👁</button>
                </div>
                <div class="helper">At least 6 characters</div>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <div class="password-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password"
                           placeholder="Re-enter new password" required>
                    <button type="button" class="toggle-pw" onclick="togglePw('confirm_password')">👁</button>
                </div>
                <div class="helper" id="matchHelper" style="color:#dc3545; display:none;">
                    Passwords do not match
                </div>
            </div>
            <button type="submit" class="btn-submit" id="submitBtn">
                Reset Password
            </button>
        </form>

        <a href="<?php echo BASE_URL; ?>/index.php" class="back-link">&larr; Back to Login</a>
    <?php endif; ?>

</div>

<script>
    function togglePw(id) {
        const input = document.getElementById(id);
        input.type = input.type === 'password' ? 'text' : 'password';
    }

    const pw      = document.getElementById('new_password');
    const confirm = document.getElementById('confirm_password');
    const helper  = document.getElementById('matchHelper');
    const btn     = document.getElementById('submitBtn');

    function validate() {
        if (!pw || !confirm) return;
        const mismatch = confirm.value !== '' && pw.value !== confirm.value;
        helper.style.display = mismatch ? 'block' : 'none';
        btn.disabled = mismatch || pw.value.length < 6;
    }

    pw?.addEventListener('input', validate);
    confirm?.addEventListener('input', validate);

    document.getElementById('resetForm')?.addEventListener('submit', function() {
        if (btn) { btn.disabled = true; btn.textContent = 'Updating...'; }
    });
</script>
</body>
</html>