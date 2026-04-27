<?php
/**
 * Applicant dashboard messages (in lieu of email for now).
 * config/applicant-messages.php
 *
 * Call add_applicant_message() when admin schedules exam, releases results,
 * schedules interview, or sets admit/reject so the student sees an update on their dashboard.
 */

/**
 * Insert a message for the applicant to see on their dashboard.
 *
 * @param mysqli $conn
 * @param int    $user_id
 * @param string $message_type e.g. exam_scheduled, exam_passed, exam_failed, exam_noshow,
 *                             interview_scheduled, interview_completed, interview_rejected, interview_noshow,
 *                             admitted, rejected
 * @param string $title
 * @param string $body optional detail
 * @return bool
 */
function add_applicant_message($conn, $user_id, $message_type, $title, $body = '') {
    $user_id = (int) $user_id;
    if ($user_id <= 0) return false;
    $stmt = mysqli_prepare($conn,
        'INSERT INTO applicant_messages (user_id, message_type, title, body) VALUES (?, ?, ?, ?)'
    );
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 'isss', $user_id, $message_type, $title, $body);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

/**
 * Insert a message with a specific created_at (for backfill of historical data).
 */
function add_applicant_message_with_date($conn, $user_id, $message_type, $title, $body, $created_at) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) return false;
    $stmt = mysqli_prepare($conn,
        'INSERT INTO applicant_messages (user_id, message_type, title, body, created_at) VALUES (?, ?, ?, ?, ?)'
    );
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 'issss', $user_id, $message_type, $title, $body, $created_at);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}
