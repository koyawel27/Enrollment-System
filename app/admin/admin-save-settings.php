<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin Save Settings Handler
 * admin-save-settings.php
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/admin-auth-check.php';
require_once CONFIG_PATH . '/admin-permissions.php';
require_admin_role([ADMIN_ROLE_SUPER_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/app/admin/admin-system-settings.php');
    exit;
}

// Ensure system_settings table exists
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS system_settings (
      setting_key VARCHAR(100) PRIMARY KEY,
      setting_value TEXT,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$keys = [
    'school_name', 'school_address', 'school_email', 'school_phone',
    'academic_year', 'semester', 'application_open',
    'application_start_date', 'application_end_date',
    'admission_period', 'application_deadline',
    'max_upload_size_mb'
];

$stmt = mysqli_prepare($conn, "
    INSERT INTO system_settings (setting_key, setting_value)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
");

foreach ($keys as $key) {
    $val = trim($_POST[$key] ?? '');
    if ($key === 'school_name' && empty($val)) $val = 'Bulacan Polytechnic College';
    if ($key === 'academic_year' && empty($val)) $val = date('Y') . '-' . (date('Y') + 1);
    if ($key === 'application_open') $val = ($val === '1' || $val === 'on') ? '1' : '0';
    if ($key === 'max_upload_size_mb') $val = max(1, min(50, (int)$val));
    mysqli_stmt_bind_param($stmt, 'ss', $key, $val);
    mysqli_stmt_execute($stmt);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

$_SESSION['admin_success'] = 'Settings saved successfully.';
header('Location: ' . BASE_URL . '/app/admin/admin-system-settings.php');
exit;