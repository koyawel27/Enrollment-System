<?php
require_once __DIR__ . '/paths.php';
/**
 * Database Configuration — EXAMPLE FOR PUBLIC REPOS
 *
 * Setup: copy this file to `db.php` (same folder) and set your real credentials.
 *        `db.php` is gitignored and is not uploaded to GitHub.
 *
 * See also: database/*.sql migrations and `SYSTEM_BEHAVIOR.txt` for schema notes.
 */

// Database credentials (XAMPP defaults shown; adjust for your machine)
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);             // Typical XAMPP custom port — check MySQL panel
define('DB_USER', 'root');
define('DB_PASS', '');               // Empty is common locally; use a strong password in production
define('DB_NAME', 'bpc_ienroll');

// Create connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to utf8mb4
mysqli_set_charset($conn, "utf8mb4");

/**
 * Helper function to sanitize input
 */
function clean_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return mysqli_real_escape_string($conn, $data);
}

/**
 * Helper function to redirect
 */
function redirect($url) {
    if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0 && strpos($url, '/') !== 0) {
        $url = BASE_URL . '/' . ltrim($url, '/');
    }
    header("Location: $url");
    exit();
}

/**
 * Helper: get system setting by key (from system_settings table)
 * Falls back to $default if table/setting does not exist.
 */
function get_setting($key, $default = null) {
    global $conn;
    static $settings_cache = null;

    if ($settings_cache === null) {
        $settings_cache = [];
        $check = @mysqli_query($conn, "SHOW TABLES LIKE 'system_settings'");
        if ($check && mysqli_num_rows($check) > 0) {
            $res = @mysqli_query($conn, "SELECT setting_key, setting_value FROM system_settings");
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $settings_cache[$row['setting_key']] = $row['setting_value'];
                }
            }
        }
    }

    return array_key_exists($key, $settings_cache) ? $settings_cache[$key] : $default;
}

/**
 * Determines whether the application period is currently open.
 */
function is_application_period_open($conn) {
    $manual_open   = get_setting('application_open', '1');
    $start_date    = get_setting('application_start_date', '');
    $end_date      = get_setting('application_end_date', '');

    if ($manual_open === '0') {
        return false;
    }

    if (!empty($start_date) && !empty($end_date)) {
        $today = date('Y-m-d');
        return ($today >= $start_date && $today <= $end_date);
    }

    return $manual_open === '1';
}
