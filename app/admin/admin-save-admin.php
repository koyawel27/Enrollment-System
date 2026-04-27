<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin Save Admin Handler
 * app/admin/admin-save-admin.php
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/admin-auth-check.php';
require_once CONFIG_PATH . '/admin-permissions.php';
require_admin_role([ADMIN_ROLE_SUPER_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/app/admin/admin-user-management.php');
    exit;
}

$id       = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$name     = trim($_POST['name']     ?? '');
$email    = trim($_POST['email']    ?? '');
$role     = trim($_POST['role']     ?? 'admission_officer');
$password = $_POST['password'] ?? '';

// Validate role
$valid_roles = [
    ADMIN_ROLE_SUPER_ADMIN,
    ADMIN_ROLE_ADMISSION_OFFICER,
    ADMIN_ROLE_REGISTRAR,
    ADMIN_ROLE_PROGRAM_HEAD,
];
if (!in_array($role, $valid_roles, true)) {
    $_SESSION['admin_error'] = 'Invalid role selected.';
    header('Location: ' . BASE_URL . '/app/admin/admin-user-management.php');
    exit;
}

if (empty($name) || empty($email)) {
    $_SESSION['admin_error'] = 'Name and email are required.';
    header('Location: ' . BASE_URL . '/app/admin/admin-user-management.php');
    exit;
}

// Collect assigned programs (only relevant for program_head)
$assigned_programs = [];
if ($role === ADMIN_ROLE_PROGRAM_HEAD && !empty($_POST['assigned_programs'])) {
    foreach ((array)$_POST['assigned_programs'] as $code) {
        $code = strtoupper(trim($code));
        if (!empty($code)) {
            $assigned_programs[] = $code;
        }
    }
}

// ── ADD ──────────────────────────────────────────────────────
if ($id === 0) {
    if (strlen($password) < 8) {
        $_SESSION['admin_error'] = 'Password must be at least 8 characters.';
        header('Location: ' . BASE_URL . '/app/admin/admin-user-management.php');
        exit;
    }

    // Check duplicate email
    $chk = mysqli_prepare($conn, 'SELECT id FROM admins WHERE email = ?');
    mysqli_stmt_bind_param($chk, 's', $email);
    mysqli_stmt_execute($chk);
    mysqli_stmt_store_result($chk);
    if (mysqli_stmt_num_rows($chk) > 0) {
        mysqli_stmt_close($chk);
        $_SESSION['admin_error'] = 'An admin with that email already exists.';
        header('Location: ' . BASE_URL . '/app/admin/admin-user-management.php');
        exit;
    }
    mysqli_stmt_close($chk);

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($conn,
        'INSERT INTO admins (name, email, password, role, is_active) VALUES (?, ?, ?, ?, 1)'
    );
    mysqli_stmt_bind_param($stmt, 'ssss', $name, $email, $hash, $role);
    $ok = mysqli_stmt_execute($stmt);
    $new_id = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        $_SESSION['admin_error'] = 'Failed to add admin.';
        header('Location: ' . BASE_URL . '/app/admin/admin-user-management.php');
        exit;
    }

    // Save department assignments for program head
    if ($role === ADMIN_ROLE_PROGRAM_HEAD && $new_id > 0) {
        save_head_assignments($conn, $new_id, $assigned_programs);
    }

    $_SESSION['admin_success'] = 'Admin "' . $name . '" added successfully.';

// ── EDIT ─────────────────────────────────────────────────────
} else {
    if (!empty($password) && strlen($password) < 8) {
        $_SESSION['admin_error'] = 'New password must be at least 8 characters.';
        header('Location: ' . BASE_URL . '/app/admin/admin-user-management.php');
        exit;
    }

    if (!empty($password)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn,
            'UPDATE admins SET name = ?, email = ?, password = ?, role = ? WHERE id = ?'
        );
        mysqli_stmt_bind_param($stmt, 'ssssi', $name, $email, $hash, $role, $id);
    } else {
        $stmt = mysqli_prepare($conn,
            'UPDATE admins SET name = ?, email = ?, role = ? WHERE id = ?'
        );
        mysqli_stmt_bind_param($stmt, 'sssi', $name, $email, $role, $id);
    }

    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        $_SESSION['admin_error'] = 'Failed to update admin.';
        header('Location: ' . BASE_URL . '/app/admin/admin-user-management.php');
        exit;
    }

    // Always re-sync department assignments (clears if role changed away from program_head)
    save_head_assignments($conn, $id, $role === ADMIN_ROLE_PROGRAM_HEAD ? $assigned_programs : []);

    $_SESSION['admin_success'] = 'Admin "' . $name . '" updated successfully.';
}

mysqli_close($conn);
header('Location: ' . BASE_URL . '/app/admin/admin-user-management.php');
exit;

// ── Helper ───────────────────────────────────────────────────
/**
 * Replace all department assignments for a program head.
 * Passing an empty array clears all assignments.
 */
function save_head_assignments($conn, int $head_id, array $codes): void {
    // Delete existing
    $del = mysqli_prepare($conn, 'DELETE FROM program_head_departments WHERE head_id = ?');
    mysqli_stmt_bind_param($del, 'i', $head_id);
    mysqli_stmt_execute($del);
    mysqli_stmt_close($del);

    if (empty($codes)) return;

    // Insert new
    $ins = mysqli_prepare($conn,
        'INSERT IGNORE INTO program_head_departments (head_id, program_code) VALUES (?, ?)'
    );
    foreach ($codes as $code) {
        mysqli_stmt_bind_param($ins, 'is', $head_id, $code);
        mysqli_stmt_execute($ins);
    }
    mysqli_stmt_close($ins);
}