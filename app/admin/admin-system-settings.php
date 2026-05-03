<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin System Settings
 * admin-system-settings.php
 *
 * Configure school info, academic year, application status, etc.
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/admin-auth-check.php';
require_once CONFIG_PATH . '/admin-permissions.php';
require_admin_role([ADMIN_ROLE_SUPER_ADMIN]);

// Ensure system_settings table exists
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS system_settings (
      setting_key VARCHAR(100) PRIMARY KEY,
      setting_value TEXT,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Load current settings
$settings = [];
$q = mysqli_query($conn, "SELECT setting_key, setting_value FROM system_settings");
if ($q) {
    while ($row = mysqli_fetch_assoc($q)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Insert defaults if empty
if (empty($settings)) {
    $defaults = [
        'school_name' => 'Bulacan Polytechnic College',
        'school_address' => 'Malolos, Bulacan',
        'school_email' => 'admission@bpc.edu.ph',
        'school_phone' => '',
        'academic_year' => date('Y') . '-' . (date('Y') + 1),
        'semester' => '1st Semester',
        'application_open' => '1',
        'max_upload_size_mb' => '5',
    ];
    foreach ($defaults as $k => $v) {
        mysqli_query($conn, "INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('" . mysqli_real_escape_string($conn, $k) . "', '" . mysqli_real_escape_string($conn, $v) . "')");
    }
    $settings = $defaults;
}

// Defaults if table doesn't exist or is empty
$get = function($key, $default = '') use ($settings) {
    return $settings[$key] ?? $default;
};

$success = $error = '';
if (isset($_SESSION['admin_success'])) { $success = $_SESSION['admin_success']; unset($_SESSION['admin_success']); }
if (isset($_SESSION['admin_error']))   { $error   = $_SESSION['admin_error'];   unset($_SESSION['admin_error']);   }

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/admin-styles.css">
    <title>System Settings - Admin | BPC iEnroll</title>
    <style>
        .page-header h1 { font-size:1.5rem; }
        .page-header p { color:var(--text-gray); }
        .alert { margin-bottom:1.25rem; }
        .card { background:var(--white); border-radius:10px; padding:1.75rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); margin-bottom:1.5rem; }
        .card-title { font-size:0.9rem; font-weight:700; color:var(--bpc-green-dark); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:1.25rem; padding-bottom:0.75rem; border-bottom:2px solid var(--bg-light); }
        .form-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:1.25rem; }
        .form-group { display:flex; flex-direction:column; gap:0.35rem; }
        .form-group.span-2 { grid-column:span 2; }
        .form-group label { font-size:0.82rem; font-weight:600; color:var(--text-dark); }
        .form-group input, .form-group select, .form-group textarea { padding:0.65rem 0.875rem; border:2px solid var(--border-color); border-radius:6px; font-size:0.875rem; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline:none; border-color:var(--bpc-green); }
        .form-group textarea { resize:vertical; min-height:80px; }
        .form-group small { font-size:0.75rem; color:var(--text-gray); }
        .callout { padding:0.75rem 1rem; border-radius:6px; margin-top:0.5rem; font-size:0.85rem; }
        .callout-warning { background:#fff3cd; color:#856404; border:1px solid #ffc107; }
        .btn { display:inline-flex; align-items:center; gap:0.5rem; padding:0.75rem 1.5rem; border:none; border-radius:6px; font-size:0.875rem; font-weight:600; cursor:pointer; transition:all 0.2s; }
        .btn-green { background:var(--bpc-green); color:#fff; }
        .btn-green:hover { background:var(--bpc-green-dark); }
        .submit-row { margin-top:1.5rem; padding-top:1rem; border-top:1px solid var(--border-color); }
        @media(max-width:768px) { .form-group.span-2 { grid-column:span 1; } }
    </style>
</head>
<body>
<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';

include '../shared/admin-sidebar.php'; ?>

<main class="main">
    <div class="page-header">
        <h1>System Settings</h1>
        <p>Configure school information, academic period, and application settings.</p>
    </div>

    <?php
if ($success): ?><div class="alert alert-success"><?php
echo htmlspecialchars($success); ?></div><?php
endif; ?>
    <?php
if ($error):   ?><div class="alert alert-error"><?php
echo htmlspecialchars($error); ?></div><?php
endif; ?>

    <form method="POST" action="admin-save-settings.php">
        <!-- School Info -->
        <div class="card">
            <div class="card-title">School Information</div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="school_name">School Name *</label>
                    <input type="text" id="school_name" name="school_name" required
                           value="<?php
echo htmlspecialchars($get('school_name', 'Bulacan Polytechnic College')); ?>"
                           placeholder="Bulacan Polytechnic College">
                </div>
                <div class="form-group span-2">
                    <label for="school_address">Address</label>
                    <input type="text" id="school_address" name="school_address"
                           value="<?php
echo htmlspecialchars($get('school_address', 'Malolos, Bulacan')); ?>"
                           placeholder="Malolos, Bulacan">
                </div>
                <div class="form-group">
                    <label for="school_email">Contact Email</label>
                    <input type="email" id="school_email" name="school_email"
                           value="<?php
echo htmlspecialchars($get('school_email', 'admission@bpc.edu.ph')); ?>"
                           placeholder="admission@bpc.edu.ph">
                </div>
                <div class="form-group">
                    <label for="school_phone">Contact Phone</label>
                    <input type="tel" id="school_phone" name="school_phone"
                           value="<?php
echo htmlspecialchars($get('school_phone')); ?>"
                           placeholder="e.g. (044) 123-4567">
                </div>
            </div>
        </div>

        <!-- Academic Period -->
        <div class="card">
            <div class="card-title">Academic Period</div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="academic_year">Academic Year *</label>
                    <input type="text" id="academic_year" name="academic_year" required
                           value="<?php
echo htmlspecialchars($get('academic_year', '2025-2026')); ?>"
                           placeholder="e.g. 2025-2026">
                </div>
                <div class="form-group">
                    <label for="semester">Semester</label>
                    <select id="semester" name="semester">
                        <option value="1st Semester" <?php
echo $get('semester') === '1st Semester' ? 'selected' : ''; ?>>1st Semester</option>
                        <option value="2nd Semester" <?php
echo $get('semester') === '2nd Semester' ? 'selected' : ''; ?>>2nd Semester</option>
                        <option value="Summer" <?php
echo $get('semester') === 'Summer' ? 'selected' : ''; ?>>Summer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="admission_period">Admission Period Display Text</label>
                    <input type="text" id="admission_period" name="admission_period"
                           value="<?php echo htmlspecialchars($get('admission_period', '')); ?>"
                           placeholder="e.g. June 1 – July 31, 2025">
                    <small>Shown on the landing page banner if no date range is set.</small>
                </div>
                <div class="form-group">
                    <label for="application_deadline">Application Deadline Display Text</label>
                    <input type="text" id="application_deadline" name="application_deadline"
                           value="<?php echo htmlspecialchars($get('application_deadline', '')); ?>"
                           placeholder="e.g. July 31, 2025">
                    <small>Shown alongside the admission period on the landing page banner.</small>
                </div>
            </div>
        </div>

        <!-- Application & Exam Settings -->
        <div class="card">
            <div class="card-title">Application & Exam Settings</div>
            <div class="form-grid">
                <!-- Application Period Date Range -->
                <div class="form-group">
                    <label for="application_start_date">Application Opens</label>
                    <input type="date" id="application_start_date" name="application_start_date"
                           value="<?php echo htmlspecialchars($get('application_start_date', '')); ?>">
                    <small>First day students can submit applications. Leave blank to use manual toggle only.</small>
                </div>
                <div class="form-group">
                    <label for="application_end_date">Application Closes</label>
                    <input type="date" id="application_end_date" name="application_end_date"
                           value="<?php echo htmlspecialchars($get('application_end_date', '')); ?>">
                    <small>Last day students can submit applications (inclusive).</small>
                </div>

                <!-- Application Status -->
                <div class="form-group span-2">
                    <label for="application_open">Application Status</label>
                    <select id="application_open" name="application_open">
                        <option value="1" <?php echo $get('application_open', '1') === '1' ? 'selected' : ''; ?>>Open — follow the date range above</option>
                        <option value="0" <?php echo $get('application_open') === '0' ? 'selected' : ''; ?>>Closed — stop accepting applications now</option>
                    </select>
                    <small>Set to <strong>Closed</strong> to immediately stop accepting applications, even if today is within the date range.</small>
                    <div id="applicationClosedCallout" class="callout callout-warning" role="alert"
                        style="<?php echo $get('application_open') === '0' ? '' : 'display:none;'; ?>">
                        ⚠️ Applications are currently closed. Students cannot start or submit new applications.
                    </div>
                </div>

                <!-- Upload size -->
                <div class="form-group">
                    <label for="max_upload_size_mb">Max File Upload Size (MB)</label>
                    <input type="number" id="max_upload_size_mb" name="max_upload_size_mb" min="1" max="50"
                           value="<?php echo htmlspecialchars($get('max_upload_size_mb', '5')); ?>">
                    <small>Maximum size per uploaded document (1–50 MB).</small>
                </div>
            </div>
            <div class="submit-row">
                <button type="submit" class="btn btn-green">Save Settings</button>
            </div>
        </div>
    </form>
</main>
<script>
(function(){
    var alertEl = document.querySelector('.alert-success');
    if (alertEl) alertEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    var appOpen = document.getElementById('application_open');
    var callout = document.getElementById('applicationClosedCallout');
    if (appOpen && callout) {
        appOpen.addEventListener('change', function(){
            callout.style.display = this.value === '0' ? '' : 'none';
        });
    }
})();
</script>
</body>
</html>