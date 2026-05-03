<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Student Dashboard
 * dashboard.php
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/programs.php';

if (!isset($_SESSION['user_id'])) {
    redirect('index.php');
}

$user_id    = $_SESSION['user_id'];
$user_email = $_SESSION['user_email'];

// Application period flag (from system settings)
$applications_open = get_setting('application_open', '1') === '1';

$query = "SELECT a.*, es.exam_date, es.exam_time, es.exam_venue, es.exam_type, es.instructions
          FROM applications a
          LEFT JOIN exam_schedules es ON a.exam_schedule_id = es.id
          WHERE a.user_id = ? LIMIT 1";
$stmt  = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$application = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$application) {
    $create_stmt = mysqli_prepare($conn,
        "INSERT INTO applications (user_id, current_step, status) VALUES (?, 1, 'Draft')"
    );
    mysqli_stmt_bind_param($create_stmt, "i", $user_id);
    mysqli_stmt_execute($create_stmt);
    mysqli_stmt_close($create_stmt);

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $application = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

$status           = $application['status'];
$current_step     = $application['current_step'];
$reference_number = $application['reference_number'];
$is_submitted     = $application['submitted'];
$rejection_reason = $application['rejection_reason'] ?? '';

// Program fields
$first_choice     = $application['first_choice'] ?? null;
$assigned_program = $application['assigned_program'] ?? null;

// Program track
$is_tesda       = ($application['program_category'] ?? '') === 'TESDA';

// Special state flags
$is_rejected      = ($status === 'Documents Rejected');
$is_resubmitted   = ($status === 'Documents Re-submitted');
$is_exam_failed   = ($status === 'Exam Failed');
$is_no_show       = ($status === 'Exam No Show');
$is_interview_noshow = ($status === 'Interview No Show');
$is_exam_sched    = ($status === 'Exam Scheduled');
$is_interview_sch = ($status === 'Interview Scheduled');
$is_admitted      = ($status === 'Admitted/Enrolled' || $status === 'Enrolled');
$is_app_rejected  = ($status === 'Rejected');
$is_withdrawn     = ($status === 'Application Withdrawn');
$is_awaiting_decision = ($status === 'Awaiting Applicant Decision');
$offered_tesda_program = $is_awaiting_decision ? ($application['third_choice'] ?? '') : '';


// Status → timeline step mapping (CHED)
$status_steps = [
    'Draft'                  => 0,
    'Application Submitted'  => 1,
    'Documents Under Review' => 2,
    'Documents Rejected'     => 2,
    'Documents Re-submitted' => 2,
    'Awaiting Applicant Decision' => 2,
    'Documents Verified'     => 3,
    'Exam Scheduled'         => 4,
    'Exam Completed'         => 5,
    'Exam Failed'            => 5,
    'Exam No Show'           => 5,
    'Interview Scheduled'    => 6,
    'Interview Completed'    => 6,
    'Interview No Show'      => 6,
    'Admitted/Enrolled'      => 7,
    'Enrolled'               => 7,
    'Rejected'               => 7,
    'Application Withdrawn'  => 7,
];

$current_status_step = $status_steps[$status] ?? 1;

// Status labels
$status_labels = [
    'Draft'                  => 'Application In Progress',
    'Application Submitted'  => 'Application Submitted',
    'Awaiting Applicant Decision' => 'Action Required',
    'Documents Under Review' => 'Documents Under Review',
    'Documents Rejected'     => 'Documents Rejected',
    'Documents Re-submitted' => 'Documents Re-submitted',
    'Documents Verified'     => 'Documents Verified',
    'Exam Scheduled'         => 'Exam Scheduled',
    'Exam Completed'         => 'Exam Passed',
    'Exam Failed'            => 'Exam Failed',
    'Exam No Show'           => 'Exam: No Show',
    'Interview No Show'      => 'Interview: No Show',
    'Interview Scheduled'    => 'Interview Scheduled',
    'Interview Completed'    => 'Interview Completed',
    'Admitted/Enrolled'      => 'Admitted / Enrolled',
    'Enrolled'               => 'Admitted / Enrolled',
    'Rejected'               => 'Application Rejected',
    'Application Withdrawn'  => 'Application Withdrawn',
];

// Dashboard messages (exam/interview schedule, results, admit/reject — in lieu of email)
$dashboard_messages = [];
$msg_table = @mysqli_query($conn, "SHOW TABLES LIKE 'applicant_messages'");
if ($msg_table && mysqli_num_rows($msg_table) > 0) {
    $mq = mysqli_prepare($conn, "SELECT id, message_type, title, body, created_at, read_at FROM applicant_messages WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
    if ($mq) {
        mysqli_stmt_bind_param($mq, 'i', $user_id);
        mysqli_stmt_execute($mq);
        $mr = mysqli_stmt_get_result($mq);
        while ($row = mysqli_fetch_assoc($mr)) {
            $dashboard_messages[] = $row;
        }
        mysqli_stmt_close($mq);
    }
    $dashboard_message_count = count($dashboard_messages);
    // Mark as read when they view the dashboard
    $mark_read = mysqli_prepare($conn, "UPDATE applicant_messages SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL");
    if ($mark_read) {
        mysqli_stmt_bind_param($mark_read, 'i', $user_id);
        mysqli_stmt_execute($mark_read);
        mysqli_stmt_close($mark_read);
    }
}
// Pre-fetch offered program label for decision banner
$offered_tesda_label = $is_awaiting_decision && $offered_tesda_program
    ? get_program_label($offered_tesda_program, $conn)
    : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - BPC iEnroll</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bpc-green: #006400; --bpc-green-light: #28a745; --bpc-green-dark: #004d00;
            --bpc-gold: #FDB714; --bpc-gold-dark: #e6a510;
            --text-dark: #1a1a1a; --text-gray: #666666;
            --bg-light: #f8f9fa; --border-color: #e0e0e0;
        }
        body { 
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light); 
            color: var(--text-dark); 
            -webkit-font-smoothing: antialiased;
        }

        /* ---------- SIDEBAR (DESKTOP) ---------- */
        .sidebar {
            width: 260px;
            background-color: #003300;
            color: white;
            padding: 1.25rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .sidebar::-webkit-scrollbar { display: none; }
        .sidebar-header {
            padding: 0 1.5rem 1rem;
            margin-bottom: 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-logo { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem; }
        .sidebar-logo img { width: 56px; height: 56px; object-fit: contain; }
        .sidebar-logo h2 { font-size: 1rem; }
        .sidebar-user { font-size: 0.85rem; color: rgba(255,255,255,0.6); word-break: break-all; }
        .sidebar-menu { margin-top: 1rem; list-style: none; }
        .sidebar-menu li { margin-bottom: 0.1rem; }
        .sidebar-menu a,
        .sidebar-menu button.sidebar-link-btn {
            width: 100%;
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.65rem 1.5rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.2s ease;
            background: transparent;
            border: none;
            cursor: pointer;
            font: inherit;
            font-size: 0.875rem;
            text-align: left;
        }
        .sidebar-menu a:hover,
        .sidebar-menu a.active,
        .sidebar-menu button.sidebar-link-btn:hover {
            background-color: rgba(255,255,255,0.1);
            color: white;
        }
        .sidebar-menu svg { width: 20px; height: 20px; }
        .sidebar-badge {
            margin-left: auto;
            min-width: 18px;
            padding: 0 6px;
            height: 18px;
            border-radius: 999px;
            background: #dc3545;
            color: #fff;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        .sidebar-section-title {
            padding: 0.5rem 1.5rem 0.25rem;
            font-size: 0.7rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.4);
        }
        
        .sidebar-logout {
            position: absolute;
            bottom: 1rem;
            width: 100%;
            padding: 0 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 1rem;
        }

        .btn-logout {
            width: 100%; padding: 0.625rem; font-size: 0.875rem;
            background-color: #dc3545; color: white; border: none;
            border-radius: 6px; cursor: pointer; font-weight: 600;
            transition: background-color 0.3s ease;
        }
        .btn-logout:hover { background-color: #c82333; }

        /* ---------- MAIN CONTENT ---------- */
        .main-content { margin-left: 260px; flex: 1; padding: 1.25rem 1.5rem; }
        .dashboard-header { margin-bottom: 1.25rem; }
        .dashboard-header h1 { font-size: 1.6rem; color: var(--bpc-green-dark); margin-bottom: 0.5rem; }
        .dashboard-header p { color: var(--text-gray); }

        /* ---------- ALERTS ---------- */
        .alert { padding: 1rem 1.25rem; border-radius: 6px; margin-bottom: 1.5rem; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }

        /* ---------- INFO BANNERS ---------- */
        .info-banner {
            border-radius: 12px; padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: flex-start;
        }
        .banner-icon { font-size: 2rem; flex-shrink: 0; }
        .banner-body h3 { font-size: 1.05rem; font-weight: 700; margin-bottom: 0.375rem; }
        .banner-body p { font-size: 0.875rem; line-height: 1.6; margin-bottom: 0.5rem; }
        .banner-detail {
            display: inline-flex; align-items: center; gap: 0.4rem;
            font-size: 0.82rem; font-weight: 600;
            background: rgba(255,255,255,0.6); border-radius: 6px;
            padding: 0.3rem 0.75rem; margin: 0.2rem 0.2rem 0 0;
        }
        .banner-exam { background: #e3f2fd; border: 2px solid #90caf9; }
        .banner-exam h3 { color: #1565c0; }
        .banner-exam p { color: #1a3a5c; }
        .banner-interview { background: #f3e5f5; border: 2px solid #ce93d8; }
        .banner-interview h3 { color: #6a1b9a; }
        .banner-interview p { color: #3a1060; }
        .banner-admitted { background: #e8f5e9; border: 2px solid #a5d6a7; }
        .banner-admitted h3 { color: #1b5e20; }
        .banner-admitted p { color: #2e4d2e; }
        .banner-rejected { background: #fce4ec; border: 2px solid #f48fb1; }
        .banner-rejected h3 { color: #880e4f; }
        .banner-rejected p { color: #4a0020; }
        .banner-failed { background: #fff3e0; border: 2px solid #ffcc80; }
        .banner-failed h3 { color: #e65100; }
        .banner-failed p { color: #5d2500; }

        /* Rejection banner */
        .rejection-banner {
            background: #fff0f0; border: 2px solid #dc3545; border-radius: 10px;
            padding: 1.25rem 1.5rem; margin-bottom: 1.5rem;
            display: flex; gap: 1rem; align-items: flex-start;
        }
        .rejection-text h3 { color: #dc3545; font-size: 1.1rem; margin-bottom: 0.375rem; }
        .rejection-text p { color: #721c24; font-size: 0.9rem; margin-bottom: 0.75rem; line-height: 1.5; }
        .rejection-reason-box {
            background: white; border: 1px solid #f5c6cb; border-radius: 6px;
            padding: 0.75rem 1rem; font-size: 0.9rem; color: #721c24;
            margin-bottom: 1rem; font-style: italic;
        }
        .btn-reupload {
            display: inline-block; padding: 0.75rem 1.5rem;
            background: #dc3545; color: white; border: none; border-radius: 6px;
            font-size: 0.95rem; font-weight: 600; text-decoration: none;
            transition: background 0.3s ease;
        }
        .btn-reupload:hover { background: #b02a37; }

        /* Resubmitted banner */
        .resubmit-banner {
            background: #f3f0ff; border: 2px solid #6f42c1; border-radius: 10px;
            padding: 1.25rem 1.5rem; margin-bottom: 1.5rem;
            display: flex; gap: 1rem; align-items: center;
        }
        .resubmit-banner h3 { color: #6f42c1; font-size: 1rem; margin-bottom: 0.25rem; }
        .resubmit-banner p { color: #4a2a8a; font-size: 0.875rem; }

        /* ---------- STATUS CARD ---------- */
        .status-card {
            background: white; border-radius: 12px; padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 2rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .status-card:hover {
            transform: none !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08) !important;
        }
        .status-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--bg-light);
            flex-wrap: wrap;
        }
        .status-card-header h2 { font-size: 1.5rem; color: var(--bpc-green-dark); }
        .status-badge {
            padding: 0.5rem 1rem; border-radius: 20px;
            font-size: 0.9rem; font-weight: 600;
        }
        .badge-draft        { background: #6c757d; color: #fff; }
        .badge-submitted    { background: #0d6efd; color: #fff; }
        .badge-awaiting     { background: #f9a825; color: #fff; }
        .badge-review       { background: #fd7e14; color: #fff; }
        .badge-rejected     { background: #dc3545; color: #fff; }
        .badge-resubmitted  { background: #6f42c1; color: #fff; }
        .badge-verified     { background: #198754; color: #fff; }
        .badge-exam         { background: #1565c0; color: #fff; }
        .badge-exam-fail    { background: #dc3545; color: #fff; }
        .badge-interview    { background: #6f42c1; color: #fff; }
        .badge-enrolled     { background: #198754; color: #fff; }
        .badge-final-rejected { background: #dc3545; color: #fff; }
        .badge-app-rejected { background: #6c757d; color: #fff; }

        /* Reference display (sticky on desktop) */
        .reference-display {
            background: linear-gradient(135deg, #f9f6e8, #fffdf0);
            border: 2px solid var(--bpc-gold); border-radius: 10px;
            padding: 1rem 1.5rem; display: flex; align-items: center;
            justify-content: space-between; margin-bottom: 1.5rem;
            flex-wrap: wrap; gap: 0.5rem;
            position: relative !important;
            top: auto !important;
            z-index: 90;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .reference-label {
            font-size: 0.8rem; color: var(--text-gray);
            text-transform: uppercase; letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }
        .reference-code {
            font-size: 1.5rem; font-weight: 700;
            color: var(--bpc-green); letter-spacing: 3px;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
        }
        .copy-ref-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            transition: all 0.2s;
            color: var(--bpc-green-dark);
        }
        .copy-ref-btn:hover {
            background: rgba(0,100,0,0.1);
        }
        .reference-hint { font-size: 0.78rem; color: var(--text-gray); }
        .reference-submitted-note {
            font-size: 0.78rem; color: #856404;
            margin-top: 0.4rem; font-weight: 500;
        }

        /* Timeline */
        .status-timeline {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin: 2rem 0;
            padding: 0 1rem;
        }
        .status-timeline::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 2rem;
            right: 2rem;
            height: 3px;
            background: var(--border-color);
            z-index: 1;
        }
        .timeline-item {
            position: relative;
            z-index: 2;
            flex: 1;
            text-align: center;
        }
        .timeline-dot {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            color: var(--text-gray);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.75rem;
            font-weight: 700;
            transition: all 0.3s ease;
        }
        .timeline-item.completed .timeline-dot {
            background: var(--bpc-gold);
            color: var(--text-dark);
        }
        .timeline-item.active .timeline-dot {
            background: var(--bpc-green);
            color: white;
            box-shadow: 0 0 0 4px rgba(0,100,0,0.2);
        }
        .timeline-item.active .timeline-label {
            color: var(--bpc-green);
            font-weight: 600;
        }
        .timeline-item.rejected .timeline-dot {
            background: #dc3545;
            color: white;
            box-shadow: 0 0 0 4px rgba(220,53,69,0.2);
        }
        .timeline-item.rejected .timeline-label {
            color: #dc3545;
            font-weight: 600;
        }
        .timeline-item.resubmitted .timeline-dot {
            background: #6f42c1;
            color: white;
            box-shadow: 0 0 0 4px rgba(111,66,193,0.2);
        }
        .timeline-item.resubmitted .timeline-label {
            color: #6f42c1;
            font-weight: 600;
        }
        .timeline-label {
            font-size: 0.75rem;
            color: var(--text-gray);
            line-height: 1.3;
        }

        /* Action buttons */
        .action-section {
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .action-section .btn-primary,
        .action-section .btn-secondary { min-width: 200px; }
        .btn-primary {
            display: inline-block; padding: 1rem 2.5rem;
            background: var(--bpc-green); color: white; border: none;
            border-radius: 6px; font-size: 1rem; font-weight: 600;
            cursor: pointer; text-decoration: none;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: var(--bpc-green-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,100,0,0.3);
        }
        .btn-secondary {
            display: inline-block; padding: 1rem 2.5rem;
            background: white; color: var(--bpc-green);
            border: 2px solid var(--bpc-green); border-radius: 6px;
            font-size: 1rem; font-weight: 600; cursor: pointer;
            text-decoration: none; transition: all 0.3s ease;
            margin-left: 1rem;
        }
        .btn-secondary:hover {
            background: var(--bpc-green); color: white;
        }

        /* Dashboard messages */
        .messages-card .status-card-header { margin-bottom: 0.75rem; }
        .messages-intro {
            font-size: 0.9rem; color: var(--text-gray);
            margin-bottom: 1.25rem; line-height: 1.5;
        }
        .messages-count {
            font-size: 0.85rem; color: var(--text-gray); font-weight: 400;
        }
        .messages-list { list-style: none; }
        .message-item {
            padding: 1rem 1.25rem; border-radius: 8px; margin-bottom: 0.75rem;
            border-left: 4px solid var(--border-color);
            background: var(--bg-light);
            transition: background 0.2s ease, box-shadow 0.2s ease;
        }
        .message-item:hover {
            background: #ffffff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }
        .message-item.message-exam_scheduled { border-left-color: #1565c0; background: #e3f2fd; }
        .message-item.message-exam_passed { border-left-color: var(--bpc-green); background: #e8f5e9; }
        .message-item.message-application_submitted { border-left-color: #0d6efd; background: #e8f0fe; }
        .message-item.message-awaiting_decision { border-left-color: #f9a825; background: #fff8e1; }
        .message-item.message-documents_review { border-left-color: #fd7e14; background: #fff3e0; }
        .message-item.message-documents_verified { border-left-color: #006400; background: #e8f5e9; }
        .message-item.message-documents_rejected { border-left-color: #dc3545; background: #fce4ec; }
        .message-item.message-exam_failed,
        .message-item.message-exam_noshow { border-left-color: #e65100; background: #fff3e0; }
        .message-item.message-interview_scheduled { border-left-color: #6a1b9a; background: #f3e5f5; }
        .message-item.message-interview_completed { border-left-color: #17a2b8; background: #e0f7fa; }
        .message-item.message-admitted { border-left-color: var(--bpc-green); background: #e8f5e9; }
        .message-item.message-rejected,
        .message-item.message-interview_rejected,
        .message-item.message-interview_noshow { border-left-color: #dc3545; background: #fce4ec; }
        .message-item.message-withdrawn { border-left-color: #9ca3af; background: #f9fafb; }

        .messages-collapsed .messages-list,
        .messages-collapsed .messages-intro { display: none; }
        .messages-summary {
            font-size: 0.9rem; color: var(--text-dark); font-weight: 600;
            margin-bottom: 0.5rem; padding: 0.75rem 1rem;
            background: var(--bg-light); border-radius: 8px;
            border-left: 4px solid var(--bpc-green);
        }
        .messages-actions {
            display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;
        }
        .messages-toggle-btn {
            padding: 0.45rem 0.9rem; border-radius: 8px;
            border: 1px solid var(--border-color); background: #fff;
            cursor: pointer; font-weight: 600; font-size: 0.85rem;
            color: var(--bpc-green-dark);
        }
        .messages-toggle-btn:hover {
            border-color: var(--bpc-green); color: var(--bpc-green);
        }
        .message-header-btn {
            width: 100%; display: flex; justify-content: space-between;
            align-items: flex-start; gap: 1rem; flex-wrap: wrap;
            background: transparent; border: none; padding: 0;
            cursor: pointer; text-align: left;
        }
        .message-header-btn strong { font-size: 0.95rem; color: var(--text-dark); }
        .message-date {
            font-size: 0.78rem; color: var(--text-gray); white-space: nowrap;
        }
        .message-body {
            display: none; margin-top: 0.5rem;
            font-size: 0.875rem; color: var(--text-gray); line-height: 1.5;
        }
        .message-item.expanded .message-body { display: block; }

        /* Exam instructions expander */
        .exam-instructions {
            margin-top: 0.75rem;
            border-top: 1px dashed rgba(0,0,0,0.1);
            padding-top: 0.75rem;
        }
        .exam-instructions-toggle {
            background: none;
            border: none;
            color: #1565c0;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        .exam-instructions-toggle:hover {
            text-decoration: underline;
        }
        .exam-instructions-content {
            display: none;
            margin-top: 0.5rem;
            font-size: 0.82rem;
            background: rgba(255,255,255,0.5);
            padding: 0.5rem;
            border-radius: 6px;
        }
        .exam-instructions-content.expanded {
            display: block;
        }

        /* Last updated */
        .last-updated {
            font-size: 0.75rem;
            color: var(--text-gray);
            margin-top: 0.5rem;
        }

        /* Empty updates message */
        .empty-messages {
            padding: 2rem;
            text-align: center;
            background: var(--bg-light);
            border-radius: 12px;
            color: var(--text-gray);
        }

        /* ---------- LOGOUT MODAL (ENHANCED) ---------- */
        .logout-modal-overlay {
            display: none; position: fixed; top: 0; left: 0;
            width: 100%; height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(3px); z-index: 1100;
            align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.2s ease;
        }
        .logout-modal-overlay.active {
            display: flex; opacity: 1;
        }
        .logout-modal {
            background: white; border-radius: 20px;
            max-width: 420px; width: 90%; padding: 1.8rem;
            box-shadow: 0 20px 35px -10px rgba(0,0,0,0.2);
            transform: scale(0.96); transition: transform 0.2s ease;
            animation: modalPop 0.2s ease forwards;
            position: relative;
        }
        @keyframes modalPop {
            from { transform: scale(0.96); opacity: 0; }
            to   { transform: scale(1); opacity: 1; }
        }
        .logout-modal h3 {
            color: var(--bpc-green-dark); margin-bottom: 0.75rem;
            font-size: 1.5rem; display: flex; align-items: center; gap: 0.5rem;
        }
        .logout-modal p {
            color: var(--text-gray); margin-bottom: 1.8rem; line-height: 1.5;
        }
        .logout-modal-actions {
            display: flex; gap: 1rem; justify-content: flex-end;
        }
        .btn-cancel, .btn-confirm-logout {
            padding: 0.7rem 1.5rem; border-radius: 40px;
            font-weight: 600; cursor: pointer; border: none;
            transition: all 0.2s;
        }
        .btn-cancel {
            background: #f1f3f4; color: #1f2937;
        }
        .btn-cancel:hover { background: #e5e7eb; }
        .btn-confirm-logout {
            background: #dc3545; color: white;
        }
        .btn-confirm-logout:hover {
            background: #b91c2c; transform: translateY(-1px);
        }
        .btn-confirm-logout:disabled {
            opacity: 0.6; cursor: not-allowed; transform: none;
        }
        .modal-close {
            background: transparent; border: none;
            font-size: 1.6rem; line-height: 1; cursor: pointer;
            color: #9ca3af; position: absolute; top: 1rem; right: 1rem;
        }
        .modal-close:hover { color: #4b5563; }

        /* ---------- CHANGE PASSWORD MODAL LIVE VALIDATION ---------- */
        .cp-field-group {
            position: relative;
        }
        .cp-field-group .validation-icon {
            position: absolute;
            right: 12px;
            top: 42px;
            font-size: 1rem;
        }
        .cp-field-group .cp-error-message {
            font-size: 0.75rem;
            color: #dc3545;
            margin-top: 0.25rem;
            display: none;
        }
        .cp-field-group.error input {
            border-color: #dc3545;
        }
        .cp-field-group.error .cp-error-message {
            display: block;
        }

        /* ---------- MOBILE ELEMENTS ---------- */
        .mobile-header { display: none; }
        .sidebar-overlay { display: none; }

        /* ========== RESPONSIVE (max-width: 768px) ========== */
        @media (max-width: 768px) {
            /* Mobile header */
            .mobile-header {
                display: flex; justify-content: space-between; align-items: center;
                padding: 0.75rem 1.5rem;
                background-color: var(--bpc-green-dark); color: white;
                position: fixed; top: 0; left: 0; width: 100%;
                z-index: 999; box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            }
            .mobile-logo {
                display: flex; align-items: center; gap: 0.75rem;
                font-weight: 600; font-size: 1rem;
            }
            .mobile-logo img { width: 36px; height: 36px; object-fit: contain; }
            .hamburger-btn {
                background: transparent; border: none; color: white;
                cursor: pointer; display: flex; align-items: center;
                justify-content: center; padding: 0.25rem;
            }
            .hamburger-btn svg { width: 28px; height: 28px; fill: white; }

            /* Off-canvas sidebar */
            .sidebar {
                position: fixed; top: 0; left: -300px;
                width: 260px; height: 100vh;
                z-index: 1001; transition: left 0.3s ease;
                background-color: #003300;
            }
            .sidebar.active { left: 0; }
            .sidebar-overlay.active {
                display: block; position: fixed; top: 0; left: 0;
                width: 100%; height: 100vh;
                background-color: rgba(0, 0, 0, 0.6);
                backdrop-filter: blur(2px); z-index: 1000;
                animation: fadeIn 0.3s ease;
            }
            @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

            /* Main content */
            .main-content {
                margin-left: 0; padding: 85px 1rem 1rem 1rem;
            }

            /* Sticky reference on mobile - adjust top offset */
            .reference-display {
                top: 70px;
            }

            /* Status card header */
            .status-card-header {
                flex-direction: column;
                align-items: center;
                gap: 0.75rem;
                text-align: center;
            }
            .status-card-header h2 { text-align: center; }
            .status-badge { display: inline-block; margin: 0 auto; }

            /* Reference display stack */
            .reference-display {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
            .reference-code {
                font-size: 1.2rem;
                letter-spacing: 2px;
                word-break: break-all;
            }

            /* Action buttons – full width, stacked */
            .action-section {
                flex-direction: column;
                gap: 0.75rem;
            }
            .action-section .btn-primary,
            .action-section .btn-secondary {
                width: 100%;
                text-align: center;
                padding: 0.8rem 1rem;
                margin-left: 0;
                min-width: auto;
            }
            .btn-secondary { margin-top: 0; }

            /* Timeline – vertical layout with connecting lines */
            .status-timeline {
                flex-direction: column;
                padding: 0;
                margin: 1.5rem 0 0.5rem;
            }
            .status-timeline::before { display: none; }
            .timeline-item {
                display: flex;
                align-items: center;
                text-align: left;
                margin-bottom: 1.25rem;
                position: relative;
            }
            .timeline-dot {
                margin: 0 1rem 0 0;
                flex-shrink: 0;
                width: 36px;
                height: 36px;
                font-size: 0.9rem;
            }
            .timeline-label {
                font-size: 0.85rem;
                line-height: 1.4;
                text-align: left;
                flex: 1;
            }
            /* Vertical line connecting dots */
            .timeline-item:not(:last-child)::after {
                content: '';
                position: absolute;
                left: 18px;
                top: 36px;
                width: 2px;
                height: calc(100% - 10px);
                background: var(--border-color);
                z-index: 0;
            }
            .timeline-item.completed:not(:last-child)::after,
            .timeline-item.active:not(:last-child)::after {
                background: var(--bpc-green);
            }

            /* Banners – column direction */
            .rejection-banner, .info-banner, .resubmit-banner {
                flex-direction: column;
                padding: 1rem;
            }
            .banner-body h3 { font-size: 1rem; }
            .banner-detail { font-size: 0.75rem; }

            /* Move logout button higher on mobile */
            .sidebar-logout {
                position: relative;
                bottom: auto;
                margin-top: 2rem;
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
<div class="dashboard-container">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <img src="../../assets/cropped-cropped-cropped-cropped-cropped-bpclogo-1-1-1-150x150.png" alt="BPC Logo">
                <h2>BPC iEnroll</h2>
            </div>
            <p class="sidebar-user"><?php echo htmlspecialchars($user_email); ?></p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php" class="active"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg> Dashboard</a></li>
            <li><a href="application-form.php"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 18H8v-2h8v2zm0-4H8v-2h8v2zm-3-7V3.5L18.5 9H13z"/></svg> My Application</a></li>
            <li><a href="profile.php"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg> My Profile</a></li>
            <li><a href="documents.php"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm0 7V3.5L18.5 9H14z"/></svg> My Documents</a></li>
            <li><a href="#updates"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M4 6h16v12H5.17L4 19.17V6zm2 2v8h12V8H6zm1 1h10v2H7V9zm0 3h7v2H7v-2z"/></svg> Updates
            <?php if (!empty($dashboard_message_count ?? 0)): ?><span class="sidebar-badge"><?php echo $dashboard_message_count; ?></span><?php endif; ?></a></li>
            <li class="sidebar-section-title">Account</li>
            <li><button type="button" class="sidebar-link-btn" onclick="showChangePasswordModal()"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 17a2 2 0 0 0 2-2v-2a2 2 0 0 0-4 0v2a2 2 0 0 0 2 2zm6-7h-1V8a5 5 0 0 0-10 0v2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2zm-3 0H9V8a3 3 0 0 1 6 0v2z"/></svg> Change Password</button></li>
        </ul>
        <div class="sidebar-logout">
            <button type="button" class="btn-logout" onclick="showLogoutModal()">Logout</button>
        </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="mobile-header">
        <div class="mobile-logo">
            <img src="../../assets/cropped-cropped-cropped-cropped-cropped-bpclogo-1-1-1-150x150.png" alt="BPC Logo">
            <span>BPC iEnroll</span>
        </div>
        <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle Menu">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M3 4H21V6H3V4ZM3 11H21V13H3V11ZM3 18H21V20H3V18Z"/></svg>
        </button>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <div class="dashboard-header">
            <h1>Welcome, <?php echo htmlspecialchars($application['first_name'] ?? 'Applicant'); ?>!</h1>
            <p>Here you can view the status and details of your admission application.</p>
            <div class="last-updated" id="lastUpdated"></div>
        </div>

        <?php if (!$applications_open): ?>
            <div class="alert alert-warning">
                The application period is currently <strong>closed</strong>. You can still view your application and status but cannot make changes.
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-warning"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <!-- BANNERS (unchanged logic) -->
        <?php if ($is_rejected): ?>
        <div class="rejection-banner">
            <div class="rejection-text">
                <h3>Document Status: Requires Your Attention</h3>
                <p>The admission officer noted an issue with one or more of your submitted documents. Please review the reason below. When you're ready, you may re-upload the corrected files. We're here to help if you have any questions.</p>
                <?php if (!empty($rejection_reason)): ?>
                    <div class="rejection-reason-box"><strong>Reason:</strong> <?php echo htmlspecialchars($rejection_reason); ?></div>
                <?php endif; ?>
                <a href="application-form.php?step=6&reupload=1" class="btn-reupload">Re-upload Documents</a>
            </div>
        </div>
        <?php elseif ($is_resubmitted): ?>
        <div class="resubmit-banner"><div><h3>Documents Re-submitted Successfully</h3><p>Your documents are waiting for the admission officer to review them again.</p></div></div>
        <?php elseif ($is_exam_sched && !$is_tesda): ?>
        <div class="info-banner banner-exam">
            <div class="banner-body">
                <h3>Your Exam Has Been Scheduled</h3>
                <p>Please be on time and bring the required documents. Good luck!</p>
                <?php if (!empty($application['exam_date'])): ?>
                    <span class="banner-detail"><?php echo date('F d, Y', strtotime($application['exam_date'])); ?></span>
                    <span class="banner-detail"><?php echo date('g:i A', strtotime($application['exam_time'])); ?></span>
                    <span class="banner-detail"><?php echo htmlspecialchars($application['exam_venue']); ?></span>
                    <span class="banner-detail"><?php echo htmlspecialchars($application['exam_type']); ?></span>
                <?php endif; ?>
                <?php if (!empty($application['instructions'])): ?>
                    <div class="exam-instructions">
                        <button type="button" class="exam-instructions-toggle" onclick="toggleExamInstructions(this)">Show exam instructions</button>
                        <div class="exam-instructions-content">
                            <?php echo nl2br(htmlspecialchars($application['instructions'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
                <p style="margin-top:0.75rem; font-size:0.82rem; color:#1a3a5c;">Your exam score will be used to determine eligibility for your chosen CHED programs. Higher scores improve your chances of getting your higher-priority choices.</p>
            </div>
        </div>
        <?php elseif ($is_awaiting_decision): ?>
<div class="info-banner" style="background:#fff8e1; border:2px solid #f9a825;">
    <div class="banner-icon"></div>
    <div class="banner-body" style="flex:1;">
        <h3 style="color:#e65100; margin-bottom:0.5rem;">Action Required: TESDA Program Offer</h3>
        <p style="color:#5d4037; margin-bottom:0.75rem;">Your exam score did not meet the cutoff for your chosen CHED programs. However, you listed <strong><?php echo htmlspecialchars($offered_tesda_label); ?></strong> as your TESDA fallback choice.</p>
        <p style="color:#5d4037; font-size:0.875rem; margin-bottom:1.25rem;">Would you like to accept this TESDA program and continue your application, or decline and close your application?</p>
        <?php if (!empty($application['exam_score'])): ?>
        <p style="font-size:0.82rem; color:#795548; margin-bottom:1rem;">Your exam score: <strong><?php echo (int)$application['exam_score']; ?>/100</strong></p>
        <?php endif; ?>
        <div style="display:flex; gap:1rem; flex-wrap:wrap;">
            <form method="POST" action="<?php echo BASE_URL; ?>/app/handlers/applicant-decision.php" onsubmit="return confirm('Accept the TESDA program offer for <?php echo htmlspecialchars(addslashes($offered_tesda_label)); ?>? This will move your application to the TESDA track.');">
                <input type="hidden" name="decision" value="accept">
                <button type="submit" style="padding:0.75rem 1.75rem; background:#006400; color:white; border:none; border-radius:6px; font-size:0.95rem; font-weight:600; cursor:pointer;">Accept TESDA Program Offer</button>
            </form>
            <form method="POST" action="<?php echo BASE_URL; ?>/app/handlers/applicant-decision.php" onsubmit="return confirm('Decline the offer? This will permanently close your application. You may re-apply next admission period.');">
                <input type="hidden" name="decision" value="decline">
                <button type="submit" style="padding:0.75rem 1.75rem; background:#dc3545; color:white; border:none; border-radius:6px; font-size:0.95rem; font-weight:600; cursor:pointer;">Decline TESDA Program Offer</button>
            </form>
        </div>
    </div>
</div>
        <?php elseif ($is_exam_failed || $is_no_show): ?>
            <div class="info-banner banner-failed"><div class="banner-body"><h3><?php echo $is_no_show ? 'You Missed Your Exam' : 'Exam Result — Not Passed'; ?></h3><p><?php echo $is_no_show ? 'You were marked as absent for your scheduled exam. If you had a valid reason (e.g. medical or family emergency), please contact the admissions office. If approved, you may be rescheduled for a makeup exam.' : 'You did not meet the requirements for your chosen programs based on your exam score. Please contact the admissions office for your options.'; ?></p></div></div>
            <?php if ($status === 'Exam Completed' && isset($application['exam_score']) && $application['exam_score'] !== null): ?>
            <div class="info-banner banner-exam" style="margin-top:0;"><div class="banner-body"><h3>Your Exam Result</h3><p>Your exam score: <strong><?php echo (int)$application['exam_score']; ?>/100</strong>.</p><p style="font-size:0.82rem; color:#1a3a5c; margin-top:0.4rem;">This score is one of the factors considered when reviewing your eligibility for the CHED programs you selected.</p></div></div>
            <?php endif; ?>
        <?php elseif ($is_interview_noshow): ?>
        <div class="info-banner banner-failed"><div class="banner-body"><h3>Interview Attendance Recorded</h3><p>Our records indicate that you were not able to attend your scheduled interview. If there was a valid reason, such as a medical or family emergency, you are welcome to contact the admissions office. Depending on your situation, a rescheduling may be possible. Please reach out to discuss your circumstances, we are here to help.</p></div></div>
        <?php elseif ($is_interview_sch): ?>
        <div class="info-banner banner-interview"><div class="banner-body"><h3>Your Interview Has Been Scheduled</h3><p>Your interview has been scheduled. We recommend that you come prepared and dressed neatly. If possible, please plan to arrive at least 15 minutes before your scheduled time to settle in comfortably. Thank you, and best of luck.</p>
                <?php if (!empty($application['interview_date'])): ?>
                    <span class="banner-detail"><?php echo date('F d, Y', strtotime($application['interview_date'])); ?></span>
                    <span class="banner-detail"><?php echo date('g:i A', strtotime($application['interview_time'])); ?></span>
                    <span class="banner-detail"><?php echo htmlspecialchars($application['interview_venue']); ?></span>
                    <span class="banner-detail"><?php echo htmlspecialchars($application['interview_type'] ?? 'Individual'); ?></span>
                <?php endif; ?>
                <?php if (!empty($application['interview_notes'])): ?>
                    <p style="margin-top:0.75rem; font-size:0.82rem; color:#3a1060;"><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($application['interview_notes'])); ?></p>
                <?php endif; ?>
            </div></div>
        <?php elseif ($is_admitted): ?>
        <div class="info-banner banner-admitted"><div class="banner-body"><h3>Congratulations! You Have Been Admitted!</h3><p>Welcome to Bulacan Polytechnic College! Please proceed to the Registrar's office to complete your enrollment. Bring your original documents and reference number.</p>
                <?php if ($reference_number): ?>
                    <span class="banner-detail">Reference: <?php echo htmlspecialchars($reference_number); ?></span>
                <?php endif; ?>
                <?php $display_program = $assigned_program ?: $first_choice; $display_program_label = $display_program ? get_program_label($display_program, $conn) : '—'; ?>
                <span class="banner-detail">Program: <?php echo htmlspecialchars($display_program_label); ?></span>
                <?php if ($assigned_program && $assigned_program !== $first_choice): ?>
                    <p style="margin-top:0.75rem; font-size:0.85rem; color:#2e4d2e;">Based on your exam score and program choices, you have been assigned to <strong><?php echo htmlspecialchars($display_program_label); ?></strong>.</p>
                <?php endif; ?>
            </div></div>
        <?php elseif ($is_app_rejected): ?>
        <div class="info-banner banner-rejected"><div class="banner-body"><h3>Application Not Approved</h3><p>Thank you for your interest in Bulacan Polytechnic College. After careful review, we are unable to approve your application at this time. You are welcome to visit the admissions office if you would like more information or guidance on next steps. Please know that this decision does not prevent you from reapplying during a future admission period.</p></div></div>
        <?php elseif ($is_withdrawn): ?>
        <div class="info-banner" style="background:#f3f4f6; border:2px solid #9ca3af;"><div class="banner-body"><h3 style="color:#374151;">You've Withdrawn Your Application</h3><p style="color:#4b5563;">You have withdrawn your application and declined the TESDA program offer. As a result, your application has been closed according to your decision. Please be assured that this status reflects your choice and is not a rejection from the college.</p><p style="color:#4b5563; font-size:0.875rem;">You are welcome to re-apply during the next admission period. We encourage you to review the program offerings before reapplying. If you have questions, please visit the admissions office.</p></div></div>
        <?php endif; ?>

        <!-- Status Card -->
        <div class="status-card">
            <div class="status-card-header">
                <h2>Application Status</h2>
                <?php
                $badge_map = [
                    'Draft' => 'badge-draft', 'Application Submitted' => 'badge-submitted', 'Awaiting Applicant Decision' => 'badge-awaiting',
                    'Documents Under Review' => 'badge-review', 'Documents Rejected' => 'badge-rejected', 'Documents Re-submitted' => 'badge-resubmitted',
                    'Documents Verified' => 'badge-verified', 'Exam Scheduled' => 'badge-exam', 'Exam Completed' => 'badge-exam',
                    'Exam Failed' => 'badge-exam-fail', 'Exam No Show' => 'badge-exam-fail', 'Interview No Show' => 'badge-exam-fail',
                    'Interview Scheduled' => 'badge-interview', 'Interview Completed' => 'badge-interview', 'Admitted/Enrolled' => 'badge-enrolled',
                    'Enrolled' => 'badge-enrolled', 'Rejected' => 'badge-final-rejected', 'Application Withdrawn' => 'badge-app-rejected',
                ];
                $badge_class = $badge_map[$status] ?? 'badge-draft';
                ?>
                <span class="status-badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($status_labels[$status] ?? $status); ?></span>
            </div>

            <?php if ($is_submitted && $reference_number): ?>
            <div class="reference-display" id="refDisplay">
                <div>
                    <div class="reference-label">Your Reference Number</div>
                    <div class="reference-code">
                        <span id="refCode"><?php echo htmlspecialchars($reference_number); ?></span>
                        <button type="button" class="copy-ref-btn" onclick="copyReferenceNumber()" title="Copy to clipboard">
                        <i class="fas fa-copy"></i>
                        </button>   
                    </div>
                    <div class="reference-submitted-note">Your application has been submitted. Editing is now locked.</div>
                </div>
                <div class="reference-hint">Save this for tracking your application</div>
            </div>
            <?php endif; ?>

            <!-- Action Section -->
            <div class="action-section">
                <?php if (!$applications_open): ?>
                    <?php if ($is_submitted): ?>
                        <a href="application-form.php" class="btn-secondary">View Application</a>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if ($status === 'Draft' && !$is_submitted): ?>
                        <a href="application-form.php" class="btn-primary"><?php echo $current_step > 1 ? 'Resume Application (Step '.$current_step.')' : 'Start Application'; ?></a>
                    <?php elseif ($is_rejected): ?>
                        <!-- handled by banner -->
                    <?php elseif ($is_submitted): ?>
                        <a href="application-form.php" class="btn-primary">View Application</a>
                    <?php else: ?>
                        <p style="color:var(--text-gray); margin-bottom:1rem;">Your application is being processed. Check back here for updates on your next steps.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Timeline -->
            <div class="status-timeline">
                <?php
                if ($is_tesda) {
                    $timeline_steps = [1 => 'Application Submitted', 2 => 'Documents Under Review', 3 => 'Documents Verified', 4 => 'Interview Scheduled', 5 => 'Admitted/Enrolled'];
                    $tesda_step_map = ['Draft'=>0,'Application Submitted'=>1,'Awaiting Applicant Decision'=>2,'Documents Under Review'=>2,'Documents Rejected'=>2,'Documents Re-submitted'=>2,'Documents Verified'=>3,'Interview Scheduled'=>4,'Interview Completed'=>4,'Admitted/Enrolled'=>5,'Enrolled'=>5,'Rejected'=>5];
                    $current_status_step = $tesda_step_map[$status] ?? 1;
                } else {
                    $timeline_steps = [1 => 'Application Submitted', 2 => 'Documents Under Review', 3 => 'Documents Verified', 4 => 'Exam Scheduled', 5 => 'Exam Completed', 6 => 'Interview Scheduled', 7 => 'Admitted/Enrolled'];
                }

                $display_step = 1;
                foreach ($timeline_steps as $step_num => $step_label):
                    if ($display_step < $current_status_step) { $dot_class = 'completed'; $dot_content = '✓'; }
                    elseif ($display_step === $current_status_step) {
                        if ($is_rejected) { $dot_class = 'rejected'; $dot_content = '✕'; }
                        elseif ($is_resubmitted) { $dot_class = 'resubmitted'; $dot_content = $display_step; }
                        elseif ($is_exam_failed || $is_no_show || $is_app_rejected || $is_withdrawn) { $dot_class = 'rejected'; $dot_content = '✕'; }
                        elseif ($is_admitted) { $dot_class = 'completed'; $dot_content = '✓'; }
                        else { $dot_class = 'active'; $dot_content = $display_step; }
                    } else { $dot_class = ''; $dot_content = $display_step; }

                    $display_label = $step_label;
                    if ($display_step === 2 && $is_rejected) $display_label = 'Documents Rejected';
                    if ($display_step === 2 && $is_resubmitted) $display_label = 'Documents Re-submitted';
                    if (!$is_tesda && $display_step === 5 && $is_exam_failed) $display_label = 'Exam Failed';
                    if (!$is_tesda && $display_step === 5 && $is_no_show) $display_label = 'Exam: No Show';
                    if ($display_step === 6 && $status === 'Interview No Show') $display_label = 'Interview: No Show';
                    $last_step = count($timeline_steps);
                    if ($display_step === $last_step && $is_app_rejected) $display_label = 'Rejected';
                    if ($display_step === $last_step && $is_withdrawn) $display_label = 'Withdrawn';
                    $display_step++;
                ?>
                <div class="timeline-item <?php echo $dot_class; ?>">
                    <div class="timeline-dot"><?php echo $dot_content; ?></div>
                    <div class="timeline-label"><?php echo $display_label; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Dashboard Messages (with empty state) -->
        <div class="status-card messages-card messages-collapsed" id="updates">
            <div class="status-card-header">
                <h2>Your Updates
                    <?php if (!empty($dashboard_message_count)): ?>
                        <span style="display:inline-block; background:#dc3545; color:white; font-size:0.72rem; font-weight:700; padding:0.15rem 0.5rem; border-radius:999px; margin-left:0.5rem; vertical-align:middle;"><?php echo $dashboard_message_count; ?> new</span>
                    <?php endif; ?>
                </h2>
                <div class="messages-actions">
                    <span class="messages-count"><?php echo count($dashboard_messages); ?> message(s)</span>
                    <button type="button" class="messages-toggle-btn" id="messagesToggleBtn" onclick="toggleMessagesCard()">Show</button>
                </div>
            </div>
            <?php if (!empty($dashboard_messages)): ?>
                <p class="messages-summary">Latest: <strong><?php echo htmlspecialchars($dashboard_messages[0]['title']); ?></strong> — <?php echo date('M j, Y g:i A', strtotime($dashboard_messages[0]['created_at'])); ?></p>
                <p class="messages-intro">When your exam or interview is scheduled, results are released, or a decision is made, you'll see it here.</p>
                <ul class="messages-list">
                    <?php foreach ($dashboard_messages as $msg): ?>
                    <li class="message-item message-<?php echo htmlspecialchars($msg['message_type']); ?>">
                        <button type="button" class="message-header-btn" onclick="toggleMessageItem(this)" aria-expanded="false">
                            <strong><?php echo htmlspecialchars($msg['title']); ?></strong>
                            <span class="message-date"><?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?></span>
                        </button>
                        <?php if (!empty($msg['body'])): ?>
                        <div class="message-body"><?php echo nl2br(htmlspecialchars($msg['body'])); ?></div>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="empty-messages">
                    <p>You have no new updates. Check back later for exam schedules or results.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Logout Modal -->
<div class="logout-modal-overlay" id="logoutModal" role="dialog" aria-modal="true" aria-labelledby="logoutModalTitle">
    <div class="logout-modal">
        <button type="button" class="modal-close" onclick="hideLogoutModal()" aria-label="Close">×</button>
        <h3 id="logoutModalTitle">⚠️ Confirm Logout</h3>
        <p>Are you sure you want to logout? You'll need to login again to access your dashboard.</p>
        <div class="logout-modal-actions">
            <button type="button" class="btn-cancel" id="logoutCancelBtn">Cancel</button>
            <form action="../auth/logout.php" method="POST" id="logoutForm">
                <button type="submit" class="btn-confirm-logout" id="logoutConfirmBtn">Logout</button>
            </form>
        </div>
    </div>
</div>

<!-- Change Password Modal (with live validation) -->
<div class="logout-modal-overlay" id="changePasswordModal" onclick="if(event.target===this) hideChangePasswordModal()">
    <div class="logout-modal">
        <h3>Change Password</h3>
        <p>For your security, enter your current password and choose a new one.</p>
        <form action="../auth/change-password.php" method="POST" id="changePasswordForm">
            <div style="display:flex; flex-direction:column; gap:0.75rem; margin-bottom:1.25rem;">
                <div class="cp-field-group" id="cpCurrentGroup">
                    <label for="cp_current" style="font-size:0.9rem; font-weight:600;">Current Password</label>
                    <input id="cp_current" name="current_password" type="password" required style="padding:0.7rem 0.9rem; border:1px solid var(--border-color); border-radius:8px; width:100%;">
                </div>
                <div class="cp-field-group" id="cpNewGroup">
                    <label for="cp_new" style="font-size:0.9rem; font-weight:600;">New Password</label>
                    <input id="cp_new" name="new_password" type="password" minlength="6" required style="padding:0.7rem 0.9rem; border:1px solid var(--border-color); border-radius:8px; width:100%;">
                    <div class="strength-meter" id="cpStrengthMeter" style="display:flex; gap:6px; margin-top:6px;">
                        <div class="strength-segment" style="flex:1; height:4px; background:#e0e0e0; border-radius:2px;"></div>
                        <div class="strength-segment" style="flex:1; height:4px; background:#e0e0e0; border-radius:2px;"></div>
                        <div class="strength-segment" style="flex:1; height:4px; background:#e0e0e0; border-radius:2px;"></div>
                    </div>
                    <div class="cp-error-message">Password must be at least 6 characters.</div>
                </div>
                <div class="cp-field-group" id="cpConfirmGroup">
                    <label for="cp_confirm" style="font-size:0.9rem; font-weight:600;">Confirm New Password</label>
                    <input id="cp_confirm" name="confirm_password" type="password" minlength="6" required style="padding:0.7rem 0.9rem; border:1px solid var(--border-color); border-radius:8px; width:100%;">
                    <div class="cp-error-message">Passwords do not match</div>
                </div>
            </div>
            <div class="logout-modal-actions">
                <button type="button" class="btn-cancel" onclick="hideChangePasswordModal()">Cancel</button>
                <button type="submit" class="btn-confirm-logout" id="cpSubmitBtn" style="background: var(--bpc-green);" disabled>Save</button>
            </div>
        </form>
    </div>
</div>

<script>
    // ========== GLOBAL FUNCTIONS ==========
    let logoutSubmitting = false;

    function showLogoutModal() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            if (overlay) overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        const modal = document.getElementById('logoutModal');
        if (!modal) return;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        const cancelBtn = document.getElementById('logoutCancelBtn');
        if (cancelBtn) cancelBtn.focus();
        logoutSubmitting = false;
        const confirmBtn = document.getElementById('logoutConfirmBtn');
        if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.textContent = 'Logout'; }
    }

    function hideLogoutModal() {
        const modal = document.getElementById('logoutModal');
        if (modal) modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    const logoutForm = document.getElementById('logoutForm');
    if (logoutForm) {
        logoutForm.addEventListener('submit', function(e) {
            if (logoutSubmitting) { e.preventDefault(); return; }
            logoutSubmitting = true;
            const confirmBtn = document.getElementById('logoutConfirmBtn');
            if (confirmBtn) { confirmBtn.disabled = true; confirmBtn.textContent = 'Logging out ...'; }
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { hideLogoutModal(); hideChangePasswordModal(); }
    });
    document.getElementById('logoutCancelBtn')?.addEventListener('click', hideLogoutModal);

    function showChangePasswordModal() { document.getElementById('changePasswordModal').classList.add('active'); document.body.style.overflow='hidden'; }
    function hideChangePasswordModal() { document.getElementById('changePasswordModal').classList.remove('active'); document.body.style.overflow=''; }

    function toggleMessagesCard() {
        const card = document.getElementById('updates');
        if (!card) return;
        const btn = document.getElementById('messagesToggleBtn');
        const isCollapsed = card.classList.toggle('messages-collapsed');
        if (btn) btn.textContent = isCollapsed ? 'Show' : 'Hide';
    }
    function toggleMessageItem(btn) {
        const item = btn.closest('.message-item');
        if (!item) return;
        const expanded = item.classList.toggle('expanded');
        btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }

    // Copy reference number
    function copyReferenceNumber() {
        const refSpan = document.getElementById('refCode');
        if (!refSpan) return;
        const text = refSpan.innerText;
        navigator.clipboard.writeText(text).then(() => {
            const btn = document.querySelector('.copy-ref-btn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '✓ Copied!';
            setTimeout(() => { btn.innerHTML = originalText; }, 2000);
        }).catch(() => alert('Could not copy. Please select manually.'));
    }

    // Toggle exam instructions expander
    function toggleExamInstructions(btn) {
        const parent = btn.closest('.exam-instructions');
        const content = parent.querySelector('.exam-instructions-content');
        if (content) {
            content.classList.toggle('expanded');
            btn.textContent = content.classList.contains('expanded') ? 'Hide exam instructions' : 'Show exam instructions';
        }
    }

    // Last updated timestamp
    document.addEventListener('DOMContentLoaded', function() {
        const lastUpdatedSpan = document.getElementById('lastUpdated');
        if (lastUpdatedSpan) {
            const now = new Date();
            lastUpdatedSpan.innerText = `Last updated: ${now.toLocaleString()}`;
        }
        // Mobile sidebar close on link click
        const sidebarLinks = document.querySelectorAll('.sidebar a, .sidebar .sidebar-link-btn');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                const sidebar = document.querySelector('.sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                if (sidebar && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    if (overlay) overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });
        // Hamburger menu toggle
        const hamburger = document.getElementById('hamburgerBtn');
        const sidebar = document.querySelector('.sidebar');
        const overlaySide = document.getElementById('sidebarOverlay');
        function toggleMobileMenu() {
            if (!sidebar || !overlaySide) return;
            sidebar.classList.toggle('active');
            overlaySide.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        }
        if (hamburger) hamburger.addEventListener('click', toggleMobileMenu);
        if (overlaySide) overlaySide.addEventListener('click', toggleMobileMenu);
    });

    // ========== CHANGE PASSWORD LIVE VALIDATION ==========
    const cpNew = document.getElementById('cp_new');
    const cpConfirm = document.getElementById('cp_confirm');
    const cpSubmitBtn = document.getElementById('cpSubmitBtn');
    const cpNewGroup = document.getElementById('cpNewGroup');
    const cpConfirmGroup = document.getElementById('cpConfirmGroup');
    const cpStrengthSegments = document.querySelectorAll('#cpStrengthMeter .strength-segment');

    function checkPasswordStrength(pw) {
        let strength = 0;
        if (pw.length >= 6) strength++;
        if (pw.length >= 8) strength++;
        if (/[A-Z]/.test(pw)) strength++;
        if (/[0-9]/.test(pw)) strength++;
        if (/[^A-Za-z0-9]/.test(pw)) strength++;
        return Math.min(strength, 3);
    }
    function updateCpStrengthMeter() {
        const pw = cpNew.value;
        const strength = checkPasswordStrength(pw);
        cpStrengthSegments.forEach((seg, idx) => {
            seg.classList.remove('weak', 'medium', 'strong');
            if (idx < strength) {
                if (strength === 1) seg.classList.add('weak');
                else if (strength === 2) seg.classList.add('medium');
                else if (strength >= 3) seg.classList.add('strong');
                seg.style.background = '#28a745';
            } else {
                seg.style.background = '#e0e0e0';
            }
        });
        validateCpForm();
    }
    function validateCpForm() {
        const pw = cpNew.value;
        const confirm = cpConfirm.value;
        const pwValid = pw.length >= 6 && checkPasswordStrength(pw) >= 2;
        const matchValid = pw === confirm && pw !== '';
        cpNewGroup.classList.toggle('error', pw !== '' && (!pwValid));
        cpConfirmGroup.classList.toggle('error', confirm !== '' && !matchValid);
        cpSubmitBtn.disabled = !(pwValid && matchValid);
    }
    if (cpNew) {
        cpNew.addEventListener('input', updateCpStrengthMeter);
        cpConfirm.addEventListener('input', validateCpForm);
        updateCpStrengthMeter();
    }
</script>
</body>
</html>