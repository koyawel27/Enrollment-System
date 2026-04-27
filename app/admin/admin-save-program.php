<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin Save Program Handler
 * app/admin/admin-save-program.php
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/admin-auth-check.php';
require_once CONFIG_PATH . '/admin-permissions.php';
require_admin_role([ADMIN_ROLE_SUPER_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/app/admin/admin-manage-programs.php');
    exit;
}

$action = trim($_POST['action'] ?? '');

// ============================================================
// TOGGLE ACTIVE / INACTIVE
// ============================================================
if ($action === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        $_SESSION['admin_error'] = 'Invalid program.';
        header('Location: ' . BASE_URL . '/app/admin/admin-manage-programs.php');
        exit;
    }
    $stmt = mysqli_prepare($conn,
        'UPDATE programs SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?'
    );
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $_SESSION['admin_success'] = 'Program status updated.';
    header('Location: ' . BASE_URL . '/app/admin/admin-manage-programs.php');
    exit;
}

// ============================================================
// ADD or EDIT
// ============================================================
if (!in_array($action, ['add', 'edit'])) {
    header('Location: ' . BASE_URL . '/app/admin/admin-manage-programs.php');
    exit;
}

// -- Collect & validate fields --
$id            = (int)($_POST['id'] ?? 0);
$code          = strtoupper(trim($_POST['code']     ?? ''));
$name          = trim($_POST['name']                ?? '');
$category      = trim($_POST['category']            ?? '');
$department    = trim($_POST['department']          ?? '') ?: null;
$description   = trim($_POST['description']         ?? '') ?: null;
$display_order = max(0, (int)($_POST['display_order'] ?? 0));

if (empty($code) || empty($name) || !in_array($category, ['CHED', 'TESDA'])) {
    $_SESSION['admin_error'] = 'Code, name, and category are required.';
    header('Location: ' . BASE_URL . '/app/admin/admin-manage-programs.php');
    exit;
}

// -- Convert line-separated textarea values to JSON arrays --
function lines_to_json(string $raw): ?string {
    $lines = array_filter(array_map('trim', explode("\n", $raw)));
    return !empty($lines) ? json_encode(array_values($lines)) : null;
}
$careers  = lines_to_json($_POST['careers']  ?? '');
$alt_jobs = lines_to_json($_POST['alt_jobs'] ?? '');

// -- Handle logo upload --
$logo_path = null; // null means "no change" in edit mode

if (!empty($_FILES['department_logo']['name'])) {
    $file      = $_FILES['department_logo'];
    $allowed   = ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'];
    $max_bytes = 2 * 1024 * 1024; // 2 MB

    if (!in_array($file['type'], $allowed)) {
        $_SESSION['admin_error'] = 'Invalid logo file type. Use PNG, JPG, SVG, or WebP.';
        header('Location: ' . BASE_URL . '/app/admin/admin-manage-programs.php');
        exit;
    }
    if ($file['size'] > $max_bytes) {
        $_SESSION['admin_error'] = 'Logo file too large. Maximum 2 MB.';
        header('Location: ' . BASE_URL . '/app/admin/admin-manage-programs.php');
        exit;
    }

    // Ensure upload directory exists
    $upload_dir = BASE_PATH . '/uploads/dept_logos/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $ext       = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename  = strtolower($code) . '_logo_' . time() . '.' . $ext;
    $dest      = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $_SESSION['admin_error'] = 'Failed to upload logo. Check folder permissions.';
        header('Location: ' . BASE_URL . '/app/admin/admin-manage-programs.php');
        exit;
    }
    $logo_path = $filename; // store just the filename; path built at render time
}

// ============================================================
// ADD
// ============================================================
if ($action === 'add') {
    // Check code uniqueness
    $check = mysqli_prepare($conn, 'SELECT id FROM programs WHERE code = ?');
    mysqli_stmt_bind_param($check, 's', $code);
    mysqli_stmt_execute($check);
    mysqli_stmt_store_result($check);
    if (mysqli_stmt_num_rows($check) > 0) {
        mysqli_stmt_close($check);
        $_SESSION['admin_error'] = 'Program code "' . $code . '" already exists.';
        header('Location: ' . BASE_URL . '/app/admin/admin-manage-programs.php');
        exit;
    }
    mysqli_stmt_close($check);

    $stmt = mysqli_prepare($conn,
        'INSERT INTO programs
            (code, name, category, department, department_logo, description, careers, alt_jobs, display_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    mysqli_stmt_bind_param($stmt, 'ssssssssi',
        $code, $name, $category, $department,
        $logo_path, $description, $careers, $alt_jobs, $display_order
    );
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $_SESSION[$ok ? 'admin_success' : 'admin_error'] = $ok
        ? 'Program "' . $name . '" added successfully.'
        : 'Database error. Could not add program.';
}

// ============================================================
// EDIT
// ============================================================
if ($action === 'edit') {
    if ($id <= 0) {
        $_SESSION['admin_error'] = 'Invalid program ID.';
        header('Location: ' . BASE_URL . '/app/admin/admin-manage-programs.php');
        exit;
    }

    if ($logo_path !== null) {
        // New logo uploaded — update including logo column
        $stmt = mysqli_prepare($conn,
            'UPDATE programs
             SET name = ?, category = ?, department = ?, department_logo = ?,
                 description = ?, careers = ?, alt_jobs = ?, display_order = ?
             WHERE id = ?'
        );
        mysqli_stmt_bind_param($stmt, 'sssssssii',
            $name, $category, $department, $logo_path,
            $description, $careers, $alt_jobs, $display_order, $id
        );
    } else {
        // No new logo — leave existing logo untouched
        $stmt = mysqli_prepare($conn,
            'UPDATE programs
             SET name = ?, category = ?, department = ?,
                 description = ?, careers = ?, alt_jobs = ?, display_order = ?
             WHERE id = ?'
        );
        mysqli_stmt_bind_param($stmt, 'ssssssii',
            $name, $category, $department,
            $description, $careers, $alt_jobs, $display_order, $id
        );
    }

    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $_SESSION[$ok ? 'admin_success' : 'admin_error'] = $ok
        ? 'Program updated successfully.'
        : 'Database error. Could not update program.';
}

mysqli_close($conn);
header('Location: ' . BASE_URL . '/app/admin/admin-manage-programs.php');
exit;