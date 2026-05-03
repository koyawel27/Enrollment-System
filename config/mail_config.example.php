<?php
/**
 * SMTP / PHPMailer settings — EXAMPLE FOR PUBLIC REPOS
 *
 * Copy this file to `mail_config.php` and fill in your provider details.
 * `mail_config.php` is gitignored and must never be committed.
 *
 * Gmail: use an App Password (Google Account → Security → 2-Step Verification → App passwords),
 *       not your normal Gmail password.
 */
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'your-email@gmail.com');
define('MAIL_PASSWORD', 'your-app-password-here');
define('MAIL_FROM_NAME', 'BPC iEnroll');
