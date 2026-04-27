<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Forgot Password - Demo
 * auth/forgot-password.php
 *
 * Simple page directing users to contact the registrar.
 * No actual password reset for demo.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | BPC iEnroll</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        .card {
            background: #fff;
            border-radius: 12px;
            padding: 2.5rem;
            max-width: 420px;
            width: 90%;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
        }
        .icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 1.5rem;
            background: #d1ecf1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .icon svg {
            width: 32px;
            height: 32px;
            color: #0c5460;
        }
        h1 {
            font-size: 1.5rem;
            color: #1a1a1a;
            margin-bottom: 1rem;
        }
        p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        .contact-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .contact-box strong {
            display: block;
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 0.5rem;
        }
        .contact-box a {
            color: #006400;
            font-size: 1.1rem;
            text-decoration: none;
            word-break: break-all;
        }
        .contact-box a:hover {
            text-decoration: underline;
        }
        .back-link {
            display: inline-block;
            color: #007bff;
            text-decoration: none;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
            </svg>
        </div>
        <h1>Forgot Password?</h1>
        <p>To reset your password, please contact the Admissions Office. They will assist you with account recovery.</p>
        <div class="contact-box">
            <strong>Contact</strong>
            <a href="mailto:registrar@bpc.edu.ph">registrar@bpc.edu.ph</a>
        </div>
        <a href="<?php echo BASE_URL; ?>/index.php" class="back-link">&larr; Back to Home</a>
    </div>
</body>
</html>