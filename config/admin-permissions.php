<?php
/**
 * Admin Role-Based Access Control
 * config/admin-permissions.php
 */

// Role constants
define('ADMIN_ROLE_SUPER_ADMIN',       'super_admin');
define('ADMIN_ROLE_ADMISSION_OFFICER', 'admission_officer');
define('ADMIN_ROLE_REGISTRAR',         'registrar');
define('ADMIN_ROLE_PROGRAM_HEAD',      'program_head');

/** Human labels for roles */
function get_admin_role_label(string $role): string {
    $labels = [
        ADMIN_ROLE_SUPER_ADMIN       => 'Super Admin',
        ADMIN_ROLE_ADMISSION_OFFICER => 'Admission Officer',
        ADMIN_ROLE_REGISTRAR         => 'Registrar',
        ADMIN_ROLE_PROGRAM_HEAD      => 'Program Head',
    ];
    return $labels[$role] ?? $role;
}

/**
 * Restrict access to given roles.
 */
function require_admin_role(array $allowed_roles): void {
    $role = $_SESSION['admin_role'] ?? '';
    if (!in_array($role, $allowed_roles, true)) {
        require_once __DIR__ . '/paths.php';
        $_SESSION['admin_error'] = 'You do not have permission to access this page.';
        header('Location: ' . BASE_URL . '/app/admin/admin-dashboard.php');
        exit;
    }
}

/** Super Admin only */
function admin_can_manage_users(): bool {
    return ($_SESSION['admin_role'] ?? '') === ADMIN_ROLE_SUPER_ADMIN;
}

/** Can schedule and record interview results */
function admin_can_schedule_interviews(): bool {
    return in_array($_SESSION['admin_role'] ?? '', [
        ADMIN_ROLE_SUPER_ADMIN,
        ADMIN_ROLE_ADMISSION_OFFICER,
        ADMIN_ROLE_PROGRAM_HEAD,
    ], true);
}

/** Can make final admit/reject decisions */
function admin_can_make_decisions(): bool {
    return in_array($_SESSION['admin_role'] ?? '', [
        ADMIN_ROLE_SUPER_ADMIN,
        ADMIN_ROLE_ADMISSION_OFFICER,
        ADMIN_ROLE_PROGRAM_HEAD,
    ], true);
}

/** Can view exam scores */
function admin_can_view_exams(): bool {
    return in_array($_SESSION['admin_role'] ?? '', [
        ADMIN_ROLE_SUPER_ADMIN,
        ADMIN_ROLE_ADMISSION_OFFICER,
        ADMIN_ROLE_PROGRAM_HEAD,
    ], true);
}

/** Can encode exam results — not program heads */
function admin_can_encode_exams(): bool {
    return in_array($_SESSION['admin_role'] ?? '', [
        ADMIN_ROLE_SUPER_ADMIN,
        ADMIN_ROLE_ADMISSION_OFFICER,
    ], true);
}

/**
 * Get program codes assigned to the current program head.
 * Returns empty array for non-program-head roles (no filter = see all).
 */
function get_head_program_codes($conn): array {
    $role = $_SESSION['admin_role'] ?? '';
    if ($role !== ADMIN_ROLE_PROGRAM_HEAD) {
        return [];
    }
    $head_id = (int)($_SESSION['admin_id'] ?? 0);
    if ($head_id <= 0) return [];

    static $cache = null;
    if ($cache !== null) return $cache;

    $codes = [];
    $stmt = mysqli_prepare($conn,
        'SELECT program_code FROM program_head_departments WHERE head_id = ?'
    );
    mysqli_stmt_bind_param($stmt, 'i', $head_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $codes[] = $row['program_code'];
    }
    mysqli_stmt_close($stmt);
    $cache = $codes;
    return $cache;
}

/**
 * Build a WHERE clause fragment filtering applications by program head's
 * assigned programs using ONLY first_choice.
 *
 * WHY first_choice only:
 * Filtering on second/third choice caused cross-department leakage.
 * e.g. a TESDA applicant whose second_choice happened to be BSIS
 * would incorrectly appear under the ITE program head.
 * first_choice is the applicant's primary intended program and the
 * correct basis for department ownership.
 *
 * Returns ['', []] for non-program-head roles (no filter = see all).
 *
 * @param  mysqli  $conn
 * @param  string  $alias  Table alias for applications (default 'a')
 * @return array{0: string, 1: string[]}
 */
function get_head_program_filter($conn, string $alias = 'a'): array {
    $codes = get_head_program_codes($conn);
    if (empty($codes)) {
        return ['', []];
    }
    $placeholders = implode(',', array_fill(0, count($codes), '?'));
    $clause = " AND {$alias}.first_choice IN ({$placeholders})";
    return [$clause, $codes];
}