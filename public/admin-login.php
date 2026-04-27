<?php
session_start();
$error = '';
if (isset($_SESSION['admin_error'])) {
    $error = $_SESSION['admin_error'];
    unset($_SESSION['admin_error']);
}
if (isset($_SESSION['admin_id'])) {
    header('Location: ../app/admin/admin-dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login – BPC iEnroll</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --green:       #006400;
            --green-dark:  #004d00;
            --green-light: #e8f5ec;
            --gold:        #f5a623;
            --text:        #1c1c1e;
            --text-muted:  #6e6e73;
            --border:      #d1d5db;
            --border-focus:#1a6b2f;
            --bg:          #f2f4f3;
            --white:       #ffffff;
            --error-bg:    #fef2f2;
            --error-text:  #991b1b;
            --error-border:#fca5a5;
            --radius:      10px;
            --shadow:      0 8px 32px rgba(0,0,0,0.10);
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background-color: var(--bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        /* Subtle background pattern */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                radial-gradient(circle at 20% 20%, rgba(26,107,47,0.06) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(245,166,35,0.05) 0%, transparent 50%);
            pointer-events: none;
        }

        .card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 420px;
            position: relative;
            animation: fadeUp 0.4s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }


        /* Header */
        .header {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .header img {
            width: 100px;
            height: 100px;
            object-fit: contain;
        }

        .header h1 {
            font-size: 1.375rem;
            font-weight: 700;
            color: var(--green-dark);
            line-height: 1.2;
        }

        .header .subtitle {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-muted);
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .divider-line {
            width: 40px;
            height: 2px;
            background: var(--gold);
            border-radius: 2px;
            margin: 0 auto;
        }

        /* Error */
        .alert-error {
            background: var(--error-bg);
            color: var(--error-text);
            border: 1px solid var(--error-border);
            border-radius: var(--radius);
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .alert-error::before {
            content: '⚠';
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* Form */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 0.5rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            font-family: inherit;
            font-size: 0.9375rem;
            color: var(--text);
            background: var(--white);
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }

        .form-group input::placeholder {
            color: #b0b0b8;
        }

        .form-group input:focus {
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(26,107,47,0.1);
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            padding-right: 2.75rem;
        }

        .toggle-pw {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            padding: 0.25rem;
            display: flex;
            align-items: center;
            transition: color 0.2s;
        }

        .toggle-pw:hover { color: var(--green); }

        .toggle-pw svg { width: 18px; height: 18px; }

        /* Submit */
        .btn-submit {
            width: 100%;
            padding: 0.8125rem 1rem;
            background: var(--green-dark);
            color: var(--white);
            border: none;
            border-radius: var(--radius);
            font-family: inherit;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 0.5rem;
            transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
            letter-spacing: 0.01em;
        }

        .btn-submit:hover {
            background: var(--green-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(15,74,31,0.25);
        }

        .btn-submit:active {
            transform: translateY(0);
            box-shadow: none;
        }

        /* Footer */
        .card-footer {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.8125rem;
            color: var(--text-muted);
        }

        .card-footer a {
            color: var(--green);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .card-footer a:hover { color: var(--green-dark); text-decoration: underline; }
    </style>
</head>
<body>

<div class="card">
    <div class="header">
        <img src="../assets/cropped-cropped-cropped-cropped-cropped-bpclogo-1-1-1-150x150.png" alt="BPC Logo">
        <div>
            <h1>BPC iEnroll</h1>
            <p class="subtitle">Admission Officer Portal</p>
        </div>
        <div class="divider-line"></div>
    </div>

    <?php if ($error): ?>
        <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form action="../app/auth/admin-login.php" method="POST">
        <div class="form-group">
            <label for="email">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                required
                placeholder="admin@bpc.edu.ph"
                autocomplete="email">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <div class="password-wrapper">
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    placeholder="Enter your password"
                    autocomplete="current-password">
                <button type="button" class="toggle-pw" onclick="togglePassword()" aria-label="Toggle password visibility">
                    <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-submit">Login</button>
    </form>

</div>

<script>
    function togglePassword() {
        const input = document.getElementById('password');
        const icon  = document.getElementById('eye-icon');
        const show  = input.type === 'password';
        input.type  = show ? 'text' : 'password';
        icon.innerHTML = show
            ? `<path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.97 9.97 0 012.01-3.473M6.53 6.53A9.97 9.97 0 0112 5c4.477 0 8.268 2.943 9.542 7a10.05 10.05 0 01-4.132 5.411M3 3l18 18"/>`
            : `<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>`;
    }
</script>

</body>
</html>