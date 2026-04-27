<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/admin-auth-check.php';
require_once CONFIG_PATH . '/admin-permissions.php';
require_admin_role([ADMIN_ROLE_SUPER_ADMIN, ADMIN_ROLE_ADMISSION_OFFICER, ADMIN_ROLE_REGISTRAR]);

// Mirror filters from admin-dashboard
$allowed_filters = [
    'all', 'Application Submitted', 'Documents Under Review',
    'Documents Verified', 'Documents Rejected', 'Documents Re-submitted',
    'Exam Scheduled', 'Exam Completed', 'Exam Failed', 'No Show',
    'Interview Scheduled', 'Interview Completed',
    'Admitted/Enrolled', 'Rejected'
];

$filter = trim($_GET['status'] ?? 'all');
if (!in_array($filter, $allowed_filters, true)) {
    $filter = 'all';
}

$q = trim($_GET['q'] ?? '');
$track_filter = trim($_GET['track'] ?? 'all');
if (!in_array($track_filter, ['all', 'CHED', 'TESDA'], true)) {
    $track_filter = 'all';
}

$sql = "SELECT a.id, a.reference_number, a.first_name, a.last_name,
               a.first_choice, a.status, a.program_category,
               a.submitted_at, a.updated_at, u.email
        FROM applications a
        JOIN users u ON a.user_id = u.id
        WHERE a.submitted = 1 AND a.status != 'Draft'";

if ($filter !== 'all') {
    $filter_escaped = mysqli_real_escape_string($conn, $filter);
    $sql .= " AND a.status = '$filter_escaped'";
}

if ($track_filter !== 'all') {
    $track_escaped = mysqli_real_escape_string($conn, $track_filter);
    $sql .= " AND a.program_category = '$track_escaped'";
}

if ($q !== '') {
    $q_escaped = mysqli_real_escape_string($conn, $q);
    $like = '%' . $q_escaped . '%';
    $sql .= " AND (
                a.reference_number LIKE '$like'
                OR a.first_name LIKE '$like'
                OR a.last_name LIKE '$like'
                OR u.email LIKE '$like'
            )";
}

$sql .= " ORDER BY a.submitted_at ASC";

$result = mysqli_query($conn, $sql);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="applications_export_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

// CSV header
fputcsv($output, [
    'Reference #',
    'Last Name',
    'First Name',
    'Email',
    'Program',
    'Track',
    'Status',
    'Submitted At',
    'Updated At',
]);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, [
            $row['reference_number'],
            $row['last_name'],
            $row['first_name'],
            $row['email'],
            $row['first_choice'],
            $row['program_category'],
            $row['status'],
            $row['submitted_at'],
            $row['updated_at'],
        ]);
    }
}

fclose($output);
mysqli_close($conn);
exit;
