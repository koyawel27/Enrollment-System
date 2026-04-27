<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin Application Detail View
 * admin-application-detail.php
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/admin-auth-check.php';
require_once CONFIG_PATH . '/admin-permissions.php';
require_admin_role([ADMIN_ROLE_SUPER_ADMIN, ADMIN_ROLE_ADMISSION_OFFICER, ADMIN_ROLE_PROGRAM_HEAD]);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_print_view = isset($_GET['print']) && $_GET['print'] === '1';
if ($id < 1) {
    redirect('app/admin/admin-dashboard.php');
}

// Fetch application
$stmt = mysqli_prepare($conn,
    "SELECT a.*, u.email AS user_email
     FROM applications a
     JOIN users u ON a.user_id = u.id
     WHERE a.id = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$app = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$app) {
    redirect('app/admin/admin-dashboard.php');
}

// Fetch status history
$hist_stmt = mysqli_prepare($conn,
    "SELECT application_id, old_status, new_status, changed_by, notes, created_at
     FROM status_history
     WHERE application_id = ?
     ORDER BY created_at ASC"
);
mysqli_stmt_bind_param($hist_stmt, 'i', $id);
mysqli_stmt_execute($hist_stmt);
$hist_result = mysqli_stmt_get_result($hist_stmt);
$history = [];
while ($row = mysqli_fetch_assoc($hist_result)) {
    $history[] = $row;
}
mysqli_stmt_close($hist_stmt);

// Fetch internal notes (admin-only)
$notes = [];
if ($stmt_notes = mysqli_prepare($conn,
    "SELECT id, application_id, admin_name, note, created_at
     FROM application_notes
     WHERE application_id = ?
     ORDER BY created_at DESC"
)) {
    mysqli_stmt_bind_param($stmt_notes, 'i', $id);
    mysqli_stmt_execute($stmt_notes);
    $res_notes = mysqli_stmt_get_result($stmt_notes);
    while ($row = mysqli_fetch_assoc($res_notes)) {
        $notes[] = $row;
    }
    mysqli_stmt_close($stmt_notes);
}

$a   = $app;
$apt = $a['applicant_type'] ?? '';
$status = $a['status'] ?? 'Draft';

// Exam schedules (for No Show reschedule dropdown) — only upcoming, not past
$exam_schedules = [];
if ($status === 'Exam No Show' && (($a['program_category'] ?? '') !== 'TESDA')) {
    $es_q = mysqli_query($conn,
        "SELECT id, exam_date, exam_time, exam_venue, exam_type, schedule_type
         FROM exam_schedules
         WHERE (exam_date > CURDATE()) OR (exam_date = CURDATE() AND exam_time >= CURTIME())
         ORDER BY exam_date ASC, exam_time ASC"
    );
    while ($row = mysqli_fetch_assoc($es_q)) {
        $exam_schedules[] = $row;
    }
}

$prog_labels = [
    'BSIS'   => 'Bachelor of Science in Information Systems',
    'ACT'    => 'Associate in Computer Technology',
    'BSOM'   => 'Bachelor of Science in Office Management',
    'BSAIS'  => 'Bachelor of Science in Accounting Information System',
    'BSCA'   => 'Bachelor of Science in Customs Administration',
    'BTVTED' => 'Bachelor in Technical-Vocational Teacher Education',
    'DHRMT'  => 'Diploma in Hotel and Restaurant Management Technology',
    'HRS'    => 'Hotel and Restaurant Services (Bundled)',
    'CCS'    => 'Contact Center Services NCII',
    'BK'     => 'Bookkeeping NCIII',
    'EIM'    => 'Electrical Installation & Maintenance NCII',
    'SMAW'   => 'Shield Metal Arc Welding NCI, NCII',
];

$status_colors = [
    'Application Submitted'   => '#0d6efd',
    'Documents Under Review'  => '#fd7e14',
    'Documents Verified'      => '#198754',
    'Documents Rejected'      => '#dc3545',
    'Documents Re-submitted'  => '#6f42c1',
    'Exam Scheduled'          => '#1565c0',
    'Interview Scheduled'     => '#6f42c1',
    'Interview Completed'     => '#0b7285',
    'Exam No Show'            => '#6c757d',
    'Interview No Show'       => '#6c757d',
    'Admitted/Enrolled'       => '#006400',
    'Rejected'                => '#dc3545',
    'Awaiting Applicant Decision' => '#f9a825',
    'Application Withdrawn'   => '#6c757d',
];

$fc_label = $prog_labels[$a['first_choice'] ?? ''] ?? ($a['first_choice'] ?? '—');
$sc_label = $prog_labels[$a['second_choice'] ?? ''] ?? ($a['second_choice'] ?? '—');
$badge_color = $status_colors[$status] ?? '#6c757d';

// Helper: render a document with viewer (print‑aware)
function render_doc($label, $path, $is_print = false) {
    if (empty($path)) {
        echo '<div class="doc-item doc-missing">';
        echo '<div class="doc-label">' . htmlspecialchars($label) . '</div>';
        echo '<div class="doc-placeholder">Not uploaded</div>';
        echo '</div>';
        return;
    }

    $filename  = basename($path);
    $ext       = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $is_image  = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    $url       = htmlspecialchars(BASE_URL . '/uploads/' . $filename);

    echo '<div class="doc-item">';
    echo '<div class="doc-label">' . htmlspecialchars($label) . '</div>';

    if ($is_print) {
        // Print view: only show file name and a note (no thumbnail)
        echo '<div class="doc-print-link">📄 ' . htmlspecialchars($filename) . '</div>';
        echo '<div class="doc-note-print">(Original document stored in system)</div>';
    } else {
        if ($is_image) {
            // Lightbox trigger
            echo '<a href="javascript:void(0)" onclick="openLightbox(\'' . $url . '\')">';
            echo '<img src="' . $url . '" alt="' . htmlspecialchars($label) . '" class="doc-thumbnail">';
            echo '</a>';
            echo '<a href="javascript:void(0)" onclick="openLightbox(\'' . $url . '\')" class="doc-link">View Full Image ↗</a>';
        } else {
            // PDF or other
            echo '<a href="' . $url . '" target="_blank" class="doc-link doc-pdf">📄 View PDF ↗</a>';
        }
        echo '<div class="doc-filename">' . htmlspecialchars($filename) . '</div>';
    }
    echo '</div>';
}

// Get session messages
$success = '';
$error   = '';
if (isset($_SESSION['admin_success'])) {
    $success = $_SESSION['admin_success'];
    unset($_SESSION['admin_success']);
}
if (isset($_SESSION['admin_error'])) {
    $error = $_SESSION['admin_error'];
    unset($_SESSION['admin_error']);
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/admin-styles.css">
    <title>Application #<?php echo $id; ?> - Admin | BPC iEnroll</title>
    <style>
        .page-wrapper {
            max-width: 1100px;
            margin: 0 auto;
        }

        /* ── TOP NAV ── */
        .top-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--bpc-green);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .back-link:hover { text-decoration: underline; }

        /* Expand/Collapse All button */
        .toggle-sections-btn {
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            padding: 0.3rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .toggle-sections-btn:hover {
            background: var(--border-color);
        }

        /* ── PAGE HEADER ── */
        .page-header {
            background: var(--white);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header-left h1 {
            font-size: 1.5rem;
            color: var(--bpc-green-dark);
            margin-bottom: 0.5rem;
        }

        .meta {
            font-size: 0.875rem;
            color: var(--text-gray);
            line-height: 1.8;
        }

        .meta strong { color: var(--text-dark); }

        /* Email copy button */
        .copy-email-btn {
            background: none;
            border: none;
            font-size: 0.9rem;
            cursor: pointer;
            margin-left: 0.5rem;
            color: var(--bpc-green);
            padding: 0 0.25rem;
        }
        .copy-email-btn:hover {
            text-decoration: underline;
        }

        .status-badge {
            display: inline-block;
            padding: 0.35rem 0.875rem;
            border-radius: 20px;
            font-size: 0.82rem;
            font-weight: 700;
            color: white;
        }

        /* ── ALERTS ── */
        .alert { margin-bottom: 1.25rem; }
        .alert-warning { border-color: #ffeaa7; }

        /* ── LAYOUT: content + action panel ── */
        .detail-layout {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 1.5rem;
            align-items: start;
        }

        /* ── SECTIONS ── */
        .section {
            background: var(--white);
            border-radius: 10px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        .section h2 {
            font-size: 0.95rem;
            color: var(--bpc-green-dark);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--bg-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Collapsible sections */
        details.section {
            padding: 0;
            overflow: hidden;
        }
        details.section > summary {
            list-style: none;
            cursor: pointer;
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        details.section > summary::-webkit-details-marker { display: none; }
        details.section > summary h2 {
            margin: 0;
            padding: 0;
            border: 0;
        }
        details.section > summary .chev {
            color: var(--text-gray);
            font-weight: 900;
            user-select: none;
        }
        details.section[open] > summary .chev { transform: rotate(180deg); }
        details.section .section-body {
            padding: 0 1.5rem 1.25rem 1.5rem;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
            gap: 1rem;
        }

        .detail-label {
            font-size: 0.72rem;
            color: var(--text-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.2rem;
        }

        .detail-value {
            font-size: 0.9rem;
            color: var(--text-dark);
        }

        .detail-value.empty { color: #aaa; font-style: italic; }

        /* ── DOCUMENTS ── */
        .docs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 1rem;
        }

        .doc-item {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.75rem;
            text-align: center;
            background: #fafafa;
        }

        .doc-item.doc-missing {
            border-style: dashed;
            opacity: 0.6;
        }

        .doc-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .doc-thumbnail {
            width: 100%;
            height: 110px;
            object-fit: cover;
            border-radius: 4px;
            display: block;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: opacity 0.2s ease;
        }

        .doc-thumbnail:hover { opacity: 0.85; }

        .doc-link {
            display: inline-block;
            font-size: 0.8rem;
            color: var(--bpc-green);
            text-decoration: none;
            font-weight: 600;
            margin-top: 0.35rem;
        }

        .doc-link:hover { text-decoration: underline; }

        .doc-pdf {
            display: block;
            padding: 1.5rem 0.5rem;
            font-size: 0.9rem;
            background: #f0f0f0;
            border-radius: 6px;
            color: var(--text-dark);
            text-decoration: none;
            margin-bottom: 0.5rem;
        }

        .doc-pdf:hover { background: #e0e0e0; }

        .doc-filename {
            font-size: 0.7rem;
            color: #aaa;
            margin-top: 0.35rem;
            word-break: break-all;
        }

        .doc-placeholder {
            color: #aaa;
            font-style: italic;
            font-size: 0.85rem;
            padding: 1rem 0;
        }

        /* Print-specific document display */
        .doc-print-link {
            font-size: 0.85rem;
            font-weight: 600;
            margin: 0.5rem 0;
        }
        .doc-note-print {
            font-size: 0.7rem;
            color: #6c757d;
        }

        /* ── LIGHTBOX ── */
        .lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            cursor: pointer;
        }
        .lightbox img {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
        }
        .lightbox .close-lightbox {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            font-weight: bold;
        }

        /* ── ACTION PANEL ── */
        .action-panel {
            position: sticky;
            top: 1.5rem;
        }

        .action-card {
            background: var(--white);
            border-radius: 10px;
            padding: 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 1.25rem;
        }

        .action-card h3 {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-gray);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--bg-light);
        }

        .action-status-current {
            text-align: center;
            padding: 0.75rem;
            background: var(--bg-light);
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            color: var(--text-gray);
        }

        .action-status-current strong {
            display: block;
            font-size: 0.95rem;
            color: var(--text-dark);
            margin-top: 0.25rem;
        }

        .btn {
            display: block;
            width: 100%;
            padding: 0.75rem;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s ease;
            margin-bottom: 0.625rem;
        }

        .btn:last-child { margin-bottom: 0; }

        .btn-review {
            background-color: #fd7e14;
            color: white;
        }
        .btn-review:hover { background-color: #e06c0a; }

        .btn-approve {
            background-color: #198754;
            color: white;
        }
        .btn-approve:hover { background-color: #146c43; }

        .btn-reject {
            background-color: #dc3545;
            color: white;
        }
        .btn-reject:hover { background-color: #b02a37; }

        .btn-secondary {
            background-color: var(--bg-light);
            color: var(--text-gray);
            border: 1px solid var(--border-color);
        }
        .btn-secondary:hover { background-color: var(--border-color); }

        /* Rejection form (better UI) */
        .rejection-form {
            display: none;
            margin-top: 1rem;
            border-top: 1px solid #f0f0f0;
            padding-top: 1rem;
        }

        .rejection-form.visible { display: block; }

        .rejection-form label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.4rem;
            margin-top: 0.75rem;
        }

        .rejection-form select,
        .rejection-form textarea {
            width: 100%;
            padding: 0.625rem;
            border: 2px solid #dc3545;
            border-radius: 6px;
            font-size: 0.85rem;
            font-family: inherit;
            background: white;
        }

        .rejection-form select:focus,
        .rejection-form textarea:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(220,53,69,0.15);
        }

        .char-counter {
            font-size: 0.7rem;
            color: var(--text-gray);
            text-align: right;
            margin-top: 0.25rem;
        }

        .rejection-notice {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 0.75rem;
            font-size: 0.82rem;
            color: #856404;
            margin-bottom: 0.75rem;
        }

        .phase-placeholder {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px dashed var(--border-color);
            font-size: 0.82rem;
            color: var(--text-gray);
        }

        /* Status History */
        .history-list {
            list-style: none;
        }

        .history-item {
            display: flex;
            gap: 0.75rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.85rem;
        }

        .history-item:last-child { border-bottom: none; }

        .history-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: var(--bpc-green);
            flex-shrink: 0;
            margin-top: 4px;
        }

        .history-content { flex: 1; }

        .history-transition {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.2rem;
        }

        .history-meta {
            color: var(--text-gray);
            font-size: 0.78rem;
        }

        .history-notes {
            margin-top: 0.3rem;
            font-size: 0.8rem;
            color: #dc3545;
            font-style: italic;
        }

        .no-history {
            text-align: center;
            color: var(--text-gray);
            font-size: 0.85rem;
            padding: 1rem 0;
            font-style: italic;
        }

        /* Internal notes */
        .notes-list {
            list-style: none;
        }
        .note-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.85rem;
        }
        .note-item:last-child { border-bottom: none; }
        .note-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 0.2rem;
            gap: 0.5rem;
        }
        .note-author {
            font-weight: 600;
            color: var(--text-dark);
        }
        .note-date {
            font-size: 0.78rem;
            color: var(--text-gray);
        }
        .note-body {
            font-size: 0.85rem;
            color: var(--text-dark);
            white-space: pre-wrap;
        }
        .note-empty {
            text-align: center;
            color: var(--text-gray);
            font-size: 0.85rem;
            padding: 0.75rem 0;
            font-style: italic;
        }
        .note-form textarea {
            width: 100%;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            padding: 0.6rem 0.7rem;
            font-size: 0.85rem;
            resize: vertical;
            min-height: 70px;
            font-family: inherit;
        }
        .note-form textarea:focus {
            outline: none;
            border-color: var(--bpc-green);
            box-shadow: 0 0 0 2px rgba(0,100,0,0.08);
        }
        .note-form button {
            margin-top: 0.5rem;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.45rem 0.9rem;
            border-radius: 6px;
            border: none;
            background: var(--bpc-green);
            color: #fff;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
        }
        .note-form button:hover {
            background: var(--bpc-green-dark);
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .detail-layout {
                grid-template-columns: 1fr;
            }
            .action-panel { position: static; }
        }

        /* ── PRINT STYLES (IMPROVED) ── */
        @media print {
                /* Hide non‑print elements */
                .action-panel,
                .top-nav,
                .back-link,
                .toggle-sections-btn,
                .btn,
                .rejection-form,
                .note-form,
                .alert,
                .copy-email-btn,
                .lightbox {
                    display: none !important;
            }

            body {
                background: white;
                padding: 0;
                margin: 0;
            }

            /* Center the main content */
            .main,
            .page-wrapper {
                max-width: 100%;
                width: 100%;
                margin: 0 auto;
                padding: 0;
            }

            /* The left column takes full width */
            .detail-layout {
                display: block;
            }

            .detail-content {
                width: 90%;
                max-width: 800px;
                margin: 0 auto;
                text-align: left;   /* keep text left‑aligned inside cards */
            }

            /* Cards and sections */
            .section,
            .page-header {
                box-shadow: none;
                border: 1px solid #ddd;
                page-break-inside: avoid;
                break-inside: avoid;
                margin: 1rem auto;
                width: 100%;
            }

            .page-header {
                text-align: left;
                margin-top: 0;
            }

            /* Document thumbnails hidden, show print‑friendly text */
            .doc-thumbnail {
                display: none;
            }
            .doc-print-link {
                display: block;
            }
            .status-badge {
                border: 1px solid #ddd;
                color: black !important;
                background: white !important;
            }

            /* Footer with admin name and date */
            .footer-print {
                position: fixed;
                bottom: 0;
                left: 0;
                width: 100%;
                font-size: 0.7rem;
                text-align: center;
                color: #aaa;
                border-top: 1px solid #eee;
                padding: 0.5rem;
                background: white;
            }
        }
    </style>
</head>
<body>
<?php if (!$is_print_view): ?>
<?php include '../shared/admin-sidebar.php'; ?>
<?php endif; ?>

<main class="main">
<div class="page-wrapper">

    <!-- Top Nav -->
    <?php if (!$is_print_view): ?>
    <div class="top-nav">
        <div>
            <a href="admin-dashboard.php" class="back-link">← Back to Dashboard</a>
            <button class="toggle-sections-btn" id="toggleSectionsBtn" style="margin-left: 1rem;">Expand All</button>
        </div>
        <span style="font-size:0.82rem; color:var(--text-gray);">
            Logged in as <strong><?php echo htmlspecialchars($_SESSION['admin_name']); ?></strong>
        </span>
    </div>
    <?php else: ?>
    <div style="display:flex; justify-content:space-between; align-items:flex-end; border-bottom:2px solid #ddd; margin-bottom:1rem; padding-bottom:0.75rem;">
        <div>
            <div style="font-size:1rem; font-weight:700; color:#1b5e20;">Bulacan Polytechnic College - iEnroll</div>
            <div style="font-size:0.85rem; color:#6c757d;">Application Record</div>
        </div>
        <div style="font-size:0.78rem; color:#6c757d;">
            Printed: <?php echo date('F d, Y g:i A'); ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Alerts -->
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="header-left">
            <h1>Application #<?php echo $id; ?></h1>
            <div class="meta">
                <strong>Reference:</strong> <?php echo htmlspecialchars($a['reference_number'] ?? '—'); ?><br>
                <strong>Applicant:</strong> <?php echo htmlspecialchars(trim(($a['first_name']??'').' '.($a['last_name']??''))); ?><br>
                <strong>Email:</strong> <?php echo htmlspecialchars($a['user_email'] ?? '');
                if (!$is_print_view): ?>
                    <button class="copy-email-btn" onclick="copyEmail()" title="Copy email address">Copy</button>
                <?php endif; ?><br>
                <strong>Submitted:</strong> <?php echo $a['submitted_at'] ? date('F d, Y g:i A', strtotime($a['submitted_at'])) : '—'; ?>
            </div>
        </div>
        <div>
            <span class="status-badge" style="background-color:<?php echo $badge_color; ?>;">
                <?php echo htmlspecialchars($status); ?>
            </span>
            <?php if (!$is_print_view && !empty($a['program_category'])): ?>
                <span style="background:#e9ecef; color:#495057; padding:0.2rem 0.6rem; border-radius:12px; font-size:0.75rem; margin-left:0.5rem;">
                    <?php echo htmlspecialchars($a['program_category']); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Layout -->
    <div class="detail-layout">

        <!-- LEFT: Application Details -->
        <div class="detail-content">
            <!-- Personal Information -->
            <details class="section" open>
                <summary>
                    <h2>Personal Information</h2>
                    <span class="chev">▾</span>
                </summary>
                <div class="section-body">
                <div class="detail-grid">
                    <div>
                        <div class="detail-label">Full Name</div>
                        <div class="detail-value"><?php echo htmlspecialchars(trim(($a['last_name']??'').', '.($a['first_name']??'').' '.($a['middle_name']??'').' '.($a['suffix']??''))); ?></div>
                    </div>
                    <div><div class="detail-label">Sex</div><div class="detail-value"><?php echo htmlspecialchars($a['sex']??'—'); ?></div></div>
                    <div><div class="detail-label">Date of Birth</div><div class="detail-value"><?php echo $a['date_of_birth'] ? date('F d, Y', strtotime($a['date_of_birth'])) : '—'; ?></div></div>
                    <div><div class="detail-label">Age</div><div class="detail-value"><?php echo htmlspecialchars($a['age']??'—'); ?></div></div>
                    <div><div class="detail-label">Place of Birth</div><div class="detail-value"><?php echo htmlspecialchars(($a['birth_city']??'').', '.($a['birth_province']??'')); ?></div></div>
                    <div><div class="detail-label">Civil Status</div><div class="detail-value"><?php echo htmlspecialchars($a['civil_status']??'—'); ?></div></div>
                    <div><div class="detail-label">Citizenship</div><div class="detail-value"><?php echo htmlspecialchars($a['citizenship']??'—'); ?></div></div>
                    <div><div class="detail-label">Religion</div><div class="detail-value"><?php echo htmlspecialchars($a['religion']??'—'); ?></div></div>
                </div>
                </div>
            </details>

            <!-- Contact & Address -->
            <details class="section" open>
                <summary>
                    <h2>Contact & Address</h2>
                    <span class="chev">▾</span>
                </summary>
                <div class="section-body">
                <div class="detail-grid">
                    <div><div class="detail-label">Mobile</div><div class="detail-value"><?php echo htmlspecialchars($a['mobile_number']??'—'); ?></div></div>
                    <div><div class="detail-label">Email</div><div class="detail-value"><?php echo htmlspecialchars($a['email']??'—'); ?></div></div>
                    <div><div class="detail-label">Facebook</div><div class="detail-value"><?php echo htmlspecialchars($a['facebook']??'—'); ?></div></div>
                    <div style="grid-column: 1 / -1;"><div class="detail-label">Current Address</div><div class="detail-value"><?php echo htmlspecialchars(trim(($a['current_house_street']??'').', '.($a['current_barangay']??'').', '.($a['current_city']??'').', '.($a['current_province']??'').' '.($a['current_zip_code']??''))); ?></div></div>
                    <div style="grid-column: 1 / -1;"><div class="detail-label">Permanent Address</div><div class="detail-value"><?php echo htmlspecialchars(trim(($a['permanent_house_street']??'').', '.($a['permanent_barangay']??'').', '.($a['permanent_city']??'').', '.($a['permanent_province']??'').' '.($a['permanent_zip_code']??''))); ?></div></div>
                </div>
                </div>
            </details>

            <!-- Family Background -->
            <details class="section">
                <summary>
                    <h2>Family Background</h2>
                    <span class="chev">▾</span>
                </summary>
                <div class="section-body">
                <div class="detail-grid">
                    <div><div class="detail-label">Father's Name</div><div class="detail-value"><?php echo htmlspecialchars(trim(($a['father_first_name']??'').' '.($a['father_middle_name']??'').' '.($a['father_last_name']??''))); ?></div></div>
                    <div><div class="detail-label">Father Occupation</div><div class="detail-value"><?php echo htmlspecialchars($a['father_occupation']??'—'); ?></div></div>
                    <div><div class="detail-label">Father Education</div><div class="detail-value"><?php echo htmlspecialchars($a['father_education']??'—'); ?></div></div>
                    <div><div class="detail-label">Mother's Name</div><div class="detail-value"><?php echo htmlspecialchars(trim(($a['mother_first_name']??'').' '.($a['mother_middle_name']??'').' '.($a['mother_last_name']??''))); ?></div></div>
                    <div><div class="detail-label">Mother Occupation</div><div class="detail-value"><?php echo htmlspecialchars($a['mother_occupation']??'—'); ?></div></div>
                    <div><div class="detail-label">Mother Education</div><div class="detail-value"><?php echo htmlspecialchars($a['mother_education']??'—'); ?></div></div>
                    <div><div class="detail-label">Guardian</div><div class="detail-value"><?php echo htmlspecialchars($a['guardian_name']??'—'); ?></div></div>
                    <div><div class="detail-label">Relationship</div><div class="detail-value"><?php echo htmlspecialchars($a['guardian_relationship']??'—'); ?></div></div>
                    <div><div class="detail-label">Guardian Contact</div><div class="detail-value"><?php echo htmlspecialchars($a['guardian_contact']??'—'); ?></div></div>
                </div>
                </div>
            </details>

            <!-- Educational Background -->
            <details class="section" open>
                <summary>
                    <h2>Educational Background</h2>
                    <span class="chev">▾</span>
                </summary>
                <div class="section-body">
                <div class="detail-grid">
                    <div><div class="detail-label">Applicant Type</div><div class="detail-value"><?php echo htmlspecialchars($apt ?: '—'); ?></div></div>
                    <?php if ($apt === 'Freshmen'): ?>
                    <div><div class="detail-label">SHS Name</div><div class="detail-value"><?php echo htmlspecialchars($a['shs_name']??'—'); ?></div></div>
                    <div style="grid-column: 1 / -1;"><div class="detail-label">SHS Address</div><div class="detail-value"><?php echo htmlspecialchars($a['shs_address']??'—'); ?></div></div>
                    <div><div class="detail-label">Year Graduated</div><div class="detail-value"><?php echo htmlspecialchars($a['shs_year_grad']??'—'); ?></div></div>
                    <div><div class="detail-label">SHS Strand</div><div class="detail-value"><?php echo htmlspecialchars($a['shs_strand']??'—'); ?></div></div>
                    <div><div class="detail-label">GWA</div><div class="detail-value"><?php echo htmlspecialchars($a['shs_gwa']??'—'); ?></div></div>
                    <div><div class="detail-label">Awards/Honors</div><div class="detail-value"><?php echo htmlspecialchars($a['awards']??'—'); ?></div></div>
                    <?php else: ?>
                    <div><div class="detail-label">Previous School</div><div class="detail-value"><?php echo htmlspecialchars($a['prev_school_name']??'—'); ?></div></div>
                    <div style="grid-column: 1 / -1;"><div class="detail-label">Previous School Address</div><div class="detail-value"><?php echo htmlspecialchars($a['prev_school_address']??'—'); ?></div></div>
                    <div><div class="detail-label">Previous Course</div><div class="detail-value"><?php echo htmlspecialchars($a['prev_course']??'—'); ?></div></div>
                    <div><div class="detail-label">Year Level</div><div class="detail-value"><?php echo htmlspecialchars($a['prev_year_level']??'—'); ?></div></div>
                    <div style="grid-column: 1 / -1;"><div class="detail-label">Reason for Transfer</div><div class="detail-value"><?php echo htmlspecialchars($a['transfer_reason']??'—'); ?></div></div>
                    <?php endif; ?>
                </div>
                </div>
            </details>

            <!-- Program Selection -->
            <div class="section">
                <h2>Program Selection</h2>
                <div class="detail-grid">
                    <div><div class="detail-label">1st Choice</div><div class="detail-value"><?php echo htmlspecialchars($fc_label); ?></div></div>
                    <div><div class="detail-label">2nd Choice</div><div class="detail-value"><?php echo htmlspecialchars($sc_label); ?></div></div>
                    <div><div class="detail-label">Scholarship</div><div class="detail-value"><?php echo !empty($a['scholarship']) ? 'Yes — '.htmlspecialchars($a['scholarship_type']??'') : 'No'; ?></div></div>
                    <div><div class="detail-label">PWD</div><div class="detail-value"><?php echo !empty($a['pwd']) ? 'Yes — '.htmlspecialchars($a['pwd_specify']??'') : 'No'; ?></div></div>
                    <div><div class="detail-label">Indigenous Member</div><div class="detail-value"><?php echo !empty($a['indigenous']) ? 'Yes' : 'No'; ?></div></div>
                    <div><div class="detail-label">4Ps Beneficiary</div><div class="detail-value"><?php echo !empty($a['four_ps']) ? 'Yes' : 'No'; ?></div></div>
                </div>
            </div>

            <!-- Documents -->
            <div class="section">
                <h2>Uploaded Documents</h2>
                <div class="docs-grid">
                    <?php
                    render_doc('2x2 ID Photo', $a['id_photo_path'] ?? '', $is_print_view);
                    render_doc('Report Card / TOR', $a['grades_path'] ?? '', $is_print_view);
                    render_doc('PSA Birth Cert', $a['birth_cert_path'] ?? '', $is_print_view);
                    if ($apt === 'Transferee') {
                        render_doc('Transfer Credential', $a['transfer_cred_path'] ?? '', $is_print_view);
                        render_doc('TOR', $a['tor_path'] ?? '', $is_print_view);
                    }
                    ?>
                </div>
            </div>

            <!-- Status History -->
            <div class="section">
                <h2>Status History</h2>
                <?php if (empty($history)): ?>
                    <p class="no-history">No status changes recorded yet.</p>
                <?php else: ?>
                    <ul class="history-list">
                        <?php foreach ($history as $h): ?>
                        <li class="history-item">
                            <div class="history-dot"></div>
                            <div class="history-content">
                                <div class="history-transition">
                                    <?php echo htmlspecialchars($h['old_status'] ?: 'Initial'); ?>
                                    → <?php echo htmlspecialchars($h['new_status']); ?>
                                </div>
                                <div class="history-meta">
                                    By <strong><?php echo htmlspecialchars($h['changed_by']); ?></strong>
                                    on <?php echo date('F d, Y g:i A', strtotime($h['created_at'])); ?>
                                </div>
                                <?php if (!empty($h['notes'])): ?>
                                    <div class="history-notes"><?php echo htmlspecialchars($h['notes']); ?></div>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- Internal Notes (Admin-only) -->
            <div class="section">
                <h2>Internal Notes</h2>
                <div class="note-form">
                    <form method="POST" action="admin-add-note.php">
                        <input type="hidden" name="application_id" value="<?php echo $id; ?>">
                        <textarea name="note" placeholder="Add a note for other admins (only visible in the admin panel)..." required></textarea>
                        <button type="submit">Save Note</button>
                    </form>
                </div>
                <?php if (empty($notes)): ?>
                    <p class="note-empty">No internal notes yet.</p>
                <?php else: ?>
                    <ul class="notes-list">
                        <?php foreach ($notes as $n): ?>
                            <li class="note-item">
                                <div class="note-header">
                                    <span class="note-author"><?php echo htmlspecialchars($n['admin_name']); ?></span>
                                    <span class="note-date"><?php echo date('F d, Y g:i A', strtotime($n['created_at'])); ?></span>
                                </div>
                                <div class="note-body"><?php echo nl2br(htmlspecialchars($n['note'])); ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div><!-- end detail-content -->

        <!-- RIGHT: Action Panel -->
        <?php if (!$is_print_view): ?>
        <div class="action-panel">
            <div class="action-card">
                <h3>Actions</h3>

                <div class="action-status-current">
                    Current Status
                    <strong><?php echo htmlspecialchars($status); ?></strong>
                </div>

                <?php if ($status === 'Application Submitted'): ?>
                    <form method="POST" action="admin-update-status.php" class="status-change-form">
                        <input type="hidden" name="application_id" value="<?php echo $id; ?>">
                        <input type="hidden" name="new_status" value="Documents Under Review">
                        <button type="submit" class="btn btn-review"
                            onclick="return confirmStatusChange(event, 'Documents Under Review', 'Move application to document review?');">
                            Start Document Review
                        </button>
                    </form>

                <?php elseif ($status === 'Documents Under Review' || $status === 'Documents Re-submitted'): ?>
                    <?php if ($status === 'Documents Re-submitted'): ?>
                        <div class="alert alert-warning" style="margin-bottom:1rem; font-size:0.82rem;">
                            ⚠️ Student has re-uploaded documents.
                            <?php if (!empty($a['resubmission_count'])): ?>
                                (Attempt #<?php echo (int)$a['resubmission_count']; ?>)
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="admin-update-status.php" class="status-change-form">
                        <input type="hidden" name="application_id" value="<?php echo $id; ?>">
                        <input type="hidden" name="new_status" value="Documents Verified">
                        <button type="submit" class="btn btn-approve"
                            onclick="return confirmStatusChange(event, 'Documents Verified', 'Approve documents for this application?');">
                            Approve Documents
                        </button>
                    </form>

                    <button type="button" class="btn btn-reject" onclick="toggleRejectForm()">
                        Reject Documents
                    </button>

                    <div class="rejection-form" id="rejectForm">
                        <div class="rejection-notice">
                            The student will be notified on their dashboard and can re-upload their documents.
                        </div>
                        <form method="POST" action="admin-update-status.php">
                            <input type="hidden" name="application_id" value="<?php echo $id; ?>">
                            <input type="hidden" name="new_status" value="Documents Rejected">

                            <label for="rejection_preset">Select Reason *</label>
                            <select id="rejection_preset" onchange="prefillReason(this.value)">
                                <option value="">-- Select a reason --</option>
                                <option value="ID photo is unclear or low quality. Please re-upload a clearer 2x2 photo.">ID photo is unclear or low quality</option>
                                <option value="Report card / grades document is unreadable. Please re-upload a clearer copy.">Report card is unreadable</option>
                                <option value="PSA Birth Certificate is missing. Please upload your PSA Birth Certificate.">PSA Birth Certificate is missing</option>
                                <option value="PSA Birth Certificate is unreadable. Please re-upload a clearer copy.">PSA Birth Certificate is unreadable</option>
                                <option value="Wrong document was uploaded. Please check and re-upload the correct file.">Wrong document uploaded</option>
                                <option value="The uploaded document appears to be expired. Please upload a valid document.">Document appears expired</option>
                                <option value="Transfer Credential is missing. Please upload your Transfer Credential.">Transfer Credential is missing (Transferee)</option>
                                <option value="Transcript of Records (TOR) is missing. Please upload your TOR.">TOR is missing (Transferee)</option>
                                <option value="custom">Other (please specify below)</option>
                            </select>

                            <label for="rejection_reason">Full Reason / Additional Details *</label>
                            <textarea name="rejection_reason" id="rejection_reason"
                                      placeholder="Select a reason above or type a custom reason here..."
                                      required maxlength="1000"></textarea>
                            <div class="char-counter">
                                <span id="charCount">0</span> / 1000 characters
                            </div>

                            <button type="submit" class="btn btn-reject" style="margin-top:0.75rem;"
                                    onclick="return confirmStatusChange(event, 'Documents Rejected', 'Reject documents? The applicant will be notified and can re-upload.');">
                                Confirm Rejection
                            </button>
                        </form>
                        <button type="button" class="btn btn-secondary" style="margin-top:0.5rem;" onclick="toggleRejectForm()">
                            Cancel
                        </button>
                    </div>

                <?php elseif ($status === 'Documents Verified'): ?>
                    <?php $is_ched = (($a['program_category'] ?? '') === 'CHED'); ?>
                    <div class="phase-placeholder">
                        Documents verified.<br><br>
                        <?php if ($is_ched): ?>
                        Proceed to <a href="admin-exam-schedule.php">Exam Schedule</a> to assign this CHED applicant to an exam.
                        <?php else: ?>
                        Proceed to <a href="admin-interview-schedule.php">Interview Schedule</a> to assign this TESDA applicant to an interview.
                        <?php endif; ?>
                    </div>

                <?php elseif ($status === 'Documents Rejected'): ?>
                    <div class="alert alert-warning" style="font-size:0.85rem;">
                        <strong>Rejection Reason:</strong><br>
                        <?php echo htmlspecialchars($a['rejection_reason'] ?? '—'); ?>
                        <br><br><em>Waiting for student to re-upload documents.</em>
                    </div>

                <?php elseif ($status === 'Admitted/Enrolled'): ?>
                    <div class="alert alert-success" style="font-size:0.85rem;"> This applicant has been admitted and enrolled.</div>

                <?php elseif ($status === 'Rejected'): ?>
                    <div class="alert alert-error" style="font-size:0.85rem;"> This application has been rejected.</div>

                <?php elseif ($status === 'Application Withdrawn'): ?>
                    <div class="alert" style="background:#f3f4f6; border:1px solid #9ca3af; font-size:0.85rem; color:#374151;">
                         This applicant voluntarily withdrew their application after declining a TESDA program offer. No admin action is required.
                    </div>

                <?php elseif ($status === 'Exam No Show' && (($a['program_category'] ?? '') !== 'TESDA') && !empty($exam_schedules)): ?>
                    <div class="alert alert-warning" style="font-size:0.85rem; margin-bottom:1rem;">
                        Applicant was marked absent for their scheduled exam. If they had a valid reason and you approved a makeup, assign them to an exam below.
                    </div>
                    <form method="POST" action="admin-reschedule-no-show.php">
                        <input type="hidden" name="application_id" value="<?php echo $id; ?>">
                        <label for="exam_schedule_id" style="display:block; font-size:0.82rem; font-weight:600; margin-bottom:0.35rem;">Upcoming exam schedules (choose a makeup date)</label>
                        <select name="exam_schedule_id" id="exam_schedule_id" required
                                style="width:100%; padding:0.6rem; border:2px solid var(--border-color); border-radius:6px; font-size:0.9rem; margin-bottom:0.75rem;">
                            <option value="">— Select schedule —</option>
                            <?php foreach ($exam_schedules as $es): ?>
                            <option value="<?php echo (int)$es['id']; ?>">
                                <?php echo (($es['schedule_type'] ?? 'Regular') === 'Makeup') ? 'Makeup Exam' : 'Regular'; ?>
                                — <?php echo date('M d, Y', strtotime($es['exam_date'])); ?>
                                <?php echo date('g:i A', strtotime($es['exam_time'])); ?>
                                — <?php echo htmlspecialchars($es['exam_venue']); ?>
                                (<?php echo htmlspecialchars($es['exam_type']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-approve"
                                onclick="return confirm('Reschedule this applicant to the selected exam? They will be notified on their dashboard.');">
                             Reschedule to makeup exam
                        </button>
                    </form>

                <?php elseif ($status === 'Exam No Show'): ?>
                    <div class="phase-placeholder">
                        No upcoming exam schedules to assign. Add a new schedule (e.g. for makeup) in <a href="admin-exam-schedule.php">Exam Schedule</a>, then return here to assign this applicant.
                    </div>

                <?php elseif ($status === 'Interview No Show'): ?>
                    <div class="alert alert-warning" style="font-size:0.85rem;">
                        Applicant did not appear for their scheduled interview.
                        Please contact the applicant. If approved, you can reschedule their interview from the
                        <a href="admin-interview-schedule.php">Interview Schedule</a> page.
                    </div>

                <?php else: ?>
                    <div class="phase-placeholder">No actions available for current status.</div>
                <?php endif; ?>
            </div>

            <!-- Print Button -->
            <div class="action-card">
                <h3>Other Actions</h3>
                <button type="button" onclick="openPrintView();" class="btn btn-secondary">
                    Print Application
                </button>
            </div>
        </div><!-- end action-panel -->
        <?php endif; ?>

    </div><!-- end detail-layout -->
</div><!-- end page-wrapper -->
</main>

<!-- Lightbox Modal -->
<div id="lightbox" class="lightbox" onclick="closeLightbox()">
    <span class="close-lightbox">&times;</span>
    <img id="lightboxImg" src="" alt="Document preview">
</div>

<!-- Print footer (only visible in print) -->
<?php if ($is_print_view): ?>
<div class="footer-print">
    Printed by <?php echo htmlspecialchars($_SESSION['admin_name']); ?> on <?php echo date('Y-m-d H:i:s'); ?>
</div>
<?php endif; ?>

<script>
    // ========== LIGHTBOX ==========
    function openLightbox(url) {
        const lightbox = document.getElementById('lightbox');
        const img = document.getElementById('lightboxImg');
        img.src = url;
        lightbox.style.display = 'flex';
    }
    function closeLightbox() {
        document.getElementById('lightbox').style.display = 'none';
    }

    // ========== EXPAND / COLLAPSE ALL SECTIONS ==========
    function toggleAllSections() {
        const details = document.querySelectorAll('details.section');
        const anyOpen = Array.from(details).some(d => d.open);
        details.forEach(d => d.open = !anyOpen);
        const btn = document.getElementById('toggleSectionsBtn');
        if (btn) btn.textContent = anyOpen ? 'Expand All' : 'Collapse All';
    }
    document.getElementById('toggleSectionsBtn')?.addEventListener('click', toggleAllSections);

    // ========== COPY EMAIL ==========
    function copyEmail() {
        const emailSpan = document.querySelector('.meta strong:contains("Email:")')?.nextSibling?.nodeValue?.trim();
        // Better: get email from meta div
        const metaDiv = document.querySelector('.meta');
        let email = '';
        if (metaDiv) {
            const lines = metaDiv.innerHTML.split('<br>');
            for (let line of lines) {
                if (line.includes('Email:')) {
                    email = line.replace(/.*Email:<\/strong>/i, '').trim();
                    break;
                }
            }
        }
        if (email) {
            navigator.clipboard.writeText(email).then(() => {
                const btn = document.querySelector('.copy-email-btn');
                const original = btn.innerHTML;
                btn.innerHTML = '✓ Copied!';
                setTimeout(() => btn.innerHTML = original, 2000);
            }).catch(() => alert('Could not copy. Please select manually.'));
        } else {
            alert('Email address not found.');
        }
    }

    // ========== REJECTION FORM: presets & character counter ==========
    function toggleRejectForm() {
        const form = document.getElementById('rejectForm');
        form.classList.toggle('visible');
        if (form.classList.contains('visible')) {
            document.getElementById('rejection_preset').value = '';
            document.getElementById('rejection_reason').value = '';
            updateCharCount();
        }
    }

    function prefillReason(value) {
        const textarea = document.getElementById('rejection_reason');
        if (value === 'custom' || value === '') {
            textarea.value = '';
            textarea.placeholder = 'Please describe the reason for rejection...';
        } else {
            textarea.value = value;
        }
        updateCharCount();
        textarea.focus();
    }

    function updateCharCount() {
        const textarea = document.getElementById('rejection_reason');
        const countSpan = document.getElementById('charCount');
        if (textarea && countSpan) {
            countSpan.innerText = textarea.value.length;
        }
    }
    document.getElementById('rejection_reason')?.addEventListener('input', updateCharCount);
    // Initial call
    updateCharCount();

    // ========== CONFIRM STATUS CHANGE (with specific message) ==========
    function confirmStatusChange(event, newStatus, customMessage) {
        event.preventDefault();
        const form = event.target.closest('form');
        if (!form) return false;
        const confirmMsg = customMessage || `Are you sure you want to change status to "${newStatus}"?`;
        if (confirm(confirmMsg)) {
            form.submit();
        }
        return false;
    }

    // Attach to any form with class .status-change-form (or any form that triggers status change)
    document.querySelectorAll('.status-change-form button[type="submit"]').forEach(btn => {
        const form = btn.closest('form');
        if (form && !form.hasAttribute('data-confirm-attached')) {
            form.setAttribute('data-confirm-attached', 'true');
            form.addEventListener('submit', (e) => {
                const newStatus = form.querySelector('input[name="new_status"]')?.value;
                if (newStatus) {
                    return confirmStatusChange(e, newStatus, `Change status to "${newStatus}"?`);
                }
                return true;
            });
        }
    });

    // For rejection form's confirm button – already handled inline in onclick.

    // ========== PRINT VIEW: ensure all details open before print ==========
    <?php if ($is_print_view): ?>
    window.addEventListener('load', function () {
        document.querySelectorAll('details.section').forEach(function (el) {
            el.open = true;
        });
        window.print();
    });
    <?php endif; ?>

    function openPrintView() {
        const url = new URL(window.location.href);
        url.searchParams.set('print', '1');
        window.open(url.toString(), '_blank');
    }
</script>
</body>
</html>