<?php
require_once __DIR__ . '/paths.php';
/**
 * Database Configuration
 * config/db.php
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_PORT', 3307);             // Change if your MySQL runs on a different port (check XAMPP Control Panel)
define('DB_USER', 'root');           // Change if needed
define('DB_PASS', '');               // Change if needed
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
        // Check if system_settings table exists
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
 *
 * Logic (in priority order):
 * 1. If application_open is manually set to '0' → CLOSED (admin override wins).
 * 2. If both application_start_date and application_end_date are set →
 *    open only if today falls within that range (inclusive).
 * 3. If only application_open = '1' and no dates set → OPEN (legacy behaviour).
 *
 * @param  mysqli $conn
 * @return bool
 */
function is_application_period_open($conn) {
    $manual_open   = get_setting('application_open', '1');
    $start_date    = get_setting('application_start_date', '');
    $end_date      = get_setting('application_end_date', '');

    // Rule 1: manual close always wins
    if ($manual_open === '0') {
        return false;
    }

    // Rule 2: date range is configured — check today falls within it
    if (!empty($start_date) && !empty($end_date)) {
        $today = date('Y-m-d');
        return ($today >= $start_date && $today <= $end_date);
    }

    // Rule 3: no dates set, fall back to manual flag
    return $manual_open === '1';
}
?>