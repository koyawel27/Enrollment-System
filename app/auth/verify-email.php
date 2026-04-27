<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Email OTP Verification Page
 * app/auth/verify-email.php
 */

session_start();
require_once CONFIG_PATH . '/db.php';

// Must have come from registration or login redirect
if (empty($_SESSION['otp_user_id']) || empty($_SESSION['otp_email'])) {
    redirect('index.php');
}

$user_id = (int)$_SESSION['otp_user_id'];
$email   = $_SESSION['otp_email'];

$error   = '';
$success = '';

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// ── HANDLE OTP SUBMISSION ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp'])) {
    $entered_otp = trim($_POST['otp'] ?? '');

    $stmt = mysqli_prepare($conn,
        "SELECT otp_code, otp_expires_at FROM users
         WHERE id = ? AND email_verified = 0"
    );
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        $error = 'Account not found or already verified.';
    } elseif (empty($row['otp_code'])) {
        $error = 'No OTP found. Please request a new one.';
    } elseif (strtotime($row['otp_expires_at']) < time()) {
        $error = 'Your OTP has expired. Please request a new one.';
    } elseif ($entered_otp !== $row['otp_code']) {
        $error = 'Incorrect OTP. Please check the code and try again.';
    } else {
        // ── Verify the account ─────────────────────────────
        $upd = mysqli_prepare($conn,
            "UPDATE users
             SET email_verified = 1, otp_code = NULL, otp_expires_at = NULL
             WHERE id = ?"
        );
        mysqli_stmt_bind_param($upd, 'i', $user_id);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);

        // Clear OTP session vars
        unset($_SESSION['otp_user_id'], $_SESSION['otp_email']);

        mysqli_close($conn);

        // Redirect to landing page — login modal will open with success message
        redirect('index.php?verified=1');
    }
}

// ── FETCH OTP EXPIRY for countdown ────────────────────────
$expires_at = null;
$stmt = mysqli_prepare($conn,
    "SELECT otp_expires_at FROM users WHERE id = ? AND email_verified = 0"
);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
mysqli_close($conn);

if ($row) {
    $expires_at = $row['otp_expires_at'];
}

$seconds_left = $expires_at ? max(0, strtotime($expires_at) - time()) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email — BPC iEnroll</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --green:      #006400;
            --green-dark: #004d00;
            --gold:       #FDB714;
            --red:        #dc3545;
            --gray:       #6c757d;
            --bg:         #f4f7f4;
            --white:      #ffffff;
            --border:     #e0e0e0;
            --text:       #1a1a1a;
            --text-light: #666;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            -webkit-font-smoothing: antialiased;
        }

        .card {
            background: var(--white);
            border-radius: 16px;
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            text-align: center;
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            margin-bottom: 1.75rem;
        }

        .logo-icon {
            width: 44px;
            height: 44px;
            background: var(--green);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
            font-weight: 700;
        }

        .logo-text {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--green-dark);
        }

        .envelope-icon {
            width: 64px;
            height: 64px;
            background: #e8f5e9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
            font-size: 1.75rem;
        }

        h1 {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--green-dark);
            margin-bottom: 0.5rem;
        }

        .subtitle {
            font-size: 0.875rem;
            color: var(--text-light);
            line-height: 1.6;
            margin-bottom: 1.75rem;
        }

        .subtitle strong {
            color: var(--text);
            font-weight: 600;
        }

        /* OTP INPUT */
        .otp-inputs {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin-bottom: 1.5rem;
        }

        .otp-digit {
            width: 52px;
            height: 60px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
            color: var(--text);
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
            -moz-appearance: textfield;
        }

        .otp-digit::-webkit-outer-spin-button,
        .otp-digit::-webkit-inner-spin-button { -webkit-appearance: none; }

        .otp-digit:focus {
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(0,100,0,0.12);
        }

        .otp-digit.filled {
            border-color: var(--green);
            background: #f0fdf4;
        }

        /* Hidden actual input for form submission */
        #otpHidden { display: none; }

        /* ALERTS */
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
            text-align: left;
        }
        .alert-error   { background: #fce4ec; color: #880e4f; border: 1px solid #f48fb1; }
        .alert-success { background: #e8f5e9; color: #1b5e20; border: 1px solid #a5d6a7; }

        /* SUBMIT BUTTON */
        .btn-verify {
            width: 100%;
            padding: 0.875rem;
            background: var(--green);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            margin-bottom: 1.25rem;
        }
        .btn-verify:hover  { background: var(--green-dark); }
        .btn-verify:active { transform: scale(0.98); }
        .btn-verify:disabled { background: #9e9e9e; cursor: not-allowed; }

        /* COUNTDOWN */
        .countdown-wrap {
            font-size: 0.82rem;
            color: var(--text-light);
            margin-bottom: 1rem;
        }

        .countdown-wrap #countdownTimer {
            font-weight: 700;
            color: var(--green);
        }

        .countdown-wrap.expired #countdownTimer {
            color: var(--red);
        }

        /* RESEND */
        .resend-wrap {
            font-size: 0.82rem;
            color: var(--text-light);
        }

        .resend-wrap a,
        .resend-wrap button {
            background: none;
            border: none;
            color: var(--green);
            font-weight: 600;
            cursor: pointer;
            font-size: 0.82rem;
            font-family: inherit;
            text-decoration: underline;
            padding: 0;
        }

        .resend-wrap a:hover,
        .resend-wrap button:hover { color: var(--green-dark); }

        .resend-wrap button:disabled {
            color: var(--gray);
            cursor: not-allowed;
            text-decoration: none;
        }

        .back-link {
            display: block;
            margin-top: 1.5rem;
            font-size: 0.82rem;
            color: var(--text-light);
            text-decoration: none;
        }

        .back-link:hover { color: var(--green); }

        @media (max-width: 480px) {
            .otp-digit { width: 44px; height: 52px; font-size: 1.25rem; }
            .card { padding: 2rem 1.25rem; }
        }
    </style>
</head>
<body>

<div class="card">
    <div class="logo">
        <div class="logo-icon">B</div>
        <span class="logo-text">BPC iEnroll</span>
    </div>

    <div class="envelope-icon">✉</div>

    <h1>Verify Your Email</h1>
    <p class="subtitle">
        We sent a 6-digit code to<br>
        <strong><?php echo htmlspecialchars($email); ?></strong><br>
        Enter it below to activate your account.
    </p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" action="" id="otpForm">
        <div class="otp-inputs" id="otpInputs">
            <input type="number" class="otp-digit" maxlength="1" min="0" max="9" data-index="0" inputmode="numeric">
            <input type="number" class="otp-digit" maxlength="1" min="0" max="9" data-index="1" inputmode="numeric">
            <input type="number" class="otp-digit" maxlength="1" min="0" max="9" data-index="2" inputmode="numeric">
            <input type="number" class="otp-digit" maxlength="1" min="0" max="9" data-index="3" inputmode="numeric">
            <input type="number" class="otp-digit" maxlength="1" min="0" max="9" data-index="4" inputmode="numeric">
            <input type="number" class="otp-digit" maxlength="1" min="0" max="9" data-index="5" inputmode="numeric">
        </div>
        <input type="hidden" name="otp" id="otpHidden">

        <div class="countdown-wrap" id="countdownWrap">
            Code expires in <span id="countdownTimer"><?php echo gmdate('i:s', $seconds_left); ?></span>
        </div>

        <button type="submit" class="btn-verify" id="verifyBtn">Verify Email</button>
    </form>

    <div class="resend-wrap">
        Didn't receive a code?
        <form method="POST" action="resend-otp.php" style="display:inline;">
            <button type="submit" id="resendBtn">Resend OTP</button>
        </form>
    </div>

    <a href="<?php echo BASE_URL; ?>/index.php" class="back-link">← Back to home</a>
</div>

<script>
// ── OTP digit inputs behaviour ─────────────────────────────
const digits  = Array.from(document.querySelectorAll('.otp-digit'));
const hidden  = document.getElementById('otpHidden');
const form    = document.getElementById('otpForm');
const verifyBtn = document.getElementById('verifyBtn');

function syncHidden() {
    hidden.value = digits.map(d => d.value).join('');
    digits.forEach(d => d.classList.toggle('filled', d.value !== ''));
}

digits.forEach((input, idx) => {
    input.addEventListener('input', function () {
        // Keep only last digit if multiple typed
        if (this.value.length > 1) this.value = this.value.slice(-1);
        syncHidden();
        if (this.value && idx < digits.length - 1) digits[idx + 1].focus();
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Backspace' && !this.value && idx > 0) {
            digits[idx - 1].focus();
            digits[idx - 1].value = '';
            syncHidden();
        }
    });

    input.addEventListener('paste', function (e) {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData)
            .getData('text').replace(/\D/g, '').slice(0, 6);
        pasted.split('').forEach((ch, i) => {
            if (digits[i]) digits[i].value = ch;
        });
        syncHidden();
        const next = digits[Math.min(pasted.length, digits.length - 1)];
        if (next) next.focus();
    });
});

// Auto-submit when all 6 digits filled
function checkAutoSubmit() {
    if (digits.every(d => d.value !== '')) {
        syncHidden();
        // Small delay so user sees the last digit filled
        setTimeout(() => form.submit(), 300);
    }
}
digits.forEach(d => d.addEventListener('input', checkAutoSubmit));

// Focus first digit on load
digits[0]?.focus();

// ── Countdown timer ────────────────────────────────────────
let secondsLeft = <?php echo (int)$seconds_left; ?>;
const timerEl   = document.getElementById('countdownTimer');
const wrapEl    = document.getElementById('countdownWrap');
const resendBtn = document.getElementById('resendBtn');

function pad(n) { return String(n).padStart(2, '0'); }

function tick() {
    if (secondsLeft <= 0) {
        timerEl.textContent = '00:00';
        wrapEl.classList.add('expired');
        wrapEl.innerHTML = '<span style="color:#dc3545; font-weight:600;">Code has expired.</span> Please request a new one.';
        verifyBtn.disabled = true;
        return;
    }
    const m = Math.floor(secondsLeft / 60);
    const s = secondsLeft % 60;
    timerEl.textContent = pad(m) + ':' + pad(s);
    secondsLeft--;
    setTimeout(tick, 1000);
}

tick();
</script>
</body>
</html>