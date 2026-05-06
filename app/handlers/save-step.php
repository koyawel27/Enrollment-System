<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Save Step Handler
 * save-step.php
 *
 * Receives POST from application-form.php steps 1-6.
 * Saves step data to DB, updates current_step, redirects.
 * No AJAX - traditional form POST + redirect.
 */

session_start();
require_once CONFIG_PATH . '/db.php';

if (!isset($_SESSION['user_id'])) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('app/student/application-form.php?step=1');
}

$user_id = (int)$_SESSION['user_id'];
$step = isset($_POST['step']) ? (int)$_POST['step'] : 0;
$action = $_POST['action'] ?? 'next';

if ($step < 1 || $step > 6) {
    redirect('app/student/application-form.php?step=1');
}

// Block edits when application period is closed
$applications_open = get_setting('application_open', '1') === '1';
if (!$applications_open) {
    $_SESSION['error'] = 'The application period is currently closed. You can no longer submit changes.';
    redirect('app/student/dashboard.php');
}

// Check if already submitted
$check = mysqli_query($conn, "SELECT submitted, status FROM applications WHERE user_id = $user_id LIMIT 1");
$row = mysqli_fetch_assoc($check);
if ($row && (!empty($row['submitted']) || in_array($row['status'] ?? '', ['Submitted', 'Application Submitted'], true))) {
    redirect('app/student/application-form.php?step=7');
}

// Ensure application row exists
$exists = mysqli_query($conn, "SELECT id FROM applications WHERE user_id = $user_id LIMIT 1");
if (!mysqli_fetch_assoc($exists)) {
    mysqli_query($conn, "INSERT INTO applications (user_id, current_step, status, created_at, updated_at) VALUES ($user_id, 1, 'Draft', NOW(), NOW())");
}

$errors = [];

function require_field($key, $label, &$errors) {
    $v = trim($_POST[$key] ?? '');
    if ($v === '') $errors[] = $label . ' is required.';
    return $v;
}

function optional($key) {
    return trim($_POST[$key] ?? '');
}

// ── STEP 1: Personal Information ──────────────────────────
if ($step === 1) {
    $last_name      = require_field('lastName',      'Last Name',      $errors);
    $first_name     = require_field('firstName',     'First Name',     $errors);
    $sex            = require_field('sex',           'Sex',            $errors);
    $date_of_birth  = require_field('dateOfBirth',   'Date of Birth',  $errors);
    $birth_city     = require_field('birthCity',     'Place of Birth', $errors);
    $birth_province = require_field('birthProvince', 'Birth Province', $errors);
    $civil_status   = require_field('civilStatus',   'Civil Status',   $errors);
    $citizenship    = require_field('citizenship',   'Citizenship',    $errors);
    $psa_registry_no = require_field('psaRegistryNo', 'PSA Registry Number', $errors);

    if (empty($errors)) {
        $age = null;
    if (!empty($date_of_birth)) {
        $dob = date_create($date_of_birth);
        $today = date_create('today');
        if (!$dob || $dob >= $today) {
            $errors[] = 'Please enter a valid date of birth.';
        } else {
            $age = date_diff($dob, $today)->y;
            if ($age < 16) {
                $errors[] = 'Applicants must be at least 16 years old to apply.';
            }
        }
    }
        $middle_name = optional('middleName');
        $suffix      = optional('suffix');
        $religion    = optional('religion');

        $stmt = mysqli_prepare($conn,
            "UPDATE applications SET
                last_name=?, first_name=?, middle_name=?, suffix=?,
                psa_registry_no=?,
                sex=?, date_of_birth=?, age=?,
                birth_city=?, birth_province=?,
                civil_status=?, citizenship=?, religion=?,
                current_step=1, updated_at=NOW()
             WHERE user_id=?"
        );
        // s  s  s  s  s  s  s  i  s  s  s  s  s  i = 14 params
        mysqli_stmt_bind_param($stmt, 'ssssssssissssi',
            $last_name, $first_name, $middle_name, $suffix,
            $psa_registry_no,
            $sex, $date_of_birth, $age,
            $birth_city, $birth_province,
            $civil_status, $citizenship, $religion,
            $user_id
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// ── STEP 2: Contact & Address ──────────────────────────────
if ($step === 2) {
    $mobile_number        = require_field('mobileNumber',        'Mobile Number',          $errors);
    $email                = require_field('email',               'Email Address',          $errors);
    $current_house_street = require_field('currentHouseStreet',  'Current House/Street',   $errors);
    $current_barangay     = require_field('currentBarangay',     'Current Barangay',       $errors);
    $current_city         = require_field('currentCity',         'Current City/Municipality', $errors);
    $current_province     = require_field('currentProvince',     'Current Province',       $errors);
    $current_zip_code     = require_field('currentZipCode',      'Current ZIP Code',       $errors);

    $same_as = !empty($_POST['sameAsPermanent']);
    if ($same_as) {
        $permanent_house_street = $current_house_street;
        $permanent_barangay     = $current_barangay;
        $permanent_city         = $current_city;
        $permanent_province     = $current_province;
        $permanent_zip_code     = $current_zip_code;
    } else {
        $permanent_house_street = require_field('permanentHouseStreet', 'Permanent House/Street',      $errors);
        $permanent_barangay     = require_field('permanentBarangay',    'Permanent Barangay',          $errors);
        $permanent_city         = require_field('permanentCity',        'Permanent City/Municipality', $errors);
        $permanent_province     = require_field('permanentProvince',    'Permanent Province',          $errors);
        $permanent_zip_code     = require_field('permanentZipCode',     'Permanent ZIP Code',          $errors);
    }

    if (empty($errors)) {
        $facebook = optional('facebook');
        $stmt = mysqli_prepare($conn,
            "UPDATE applications SET
                mobile_number=?, email=?, facebook=?,
                current_house_street=?, current_barangay=?, current_city=?, current_province=?, current_zip_code=?,
                permanent_house_street=?, permanent_barangay=?, permanent_city=?, permanent_province=?, permanent_zip_code=?,
                current_step=2, updated_at=NOW()
             WHERE user_id=?"
        );
        mysqli_stmt_bind_param($stmt, 'sssssssssssssi',
            $mobile_number, $email, $facebook,
            $current_house_street, $current_barangay, $current_city, $current_province, $current_zip_code,
            $permanent_house_street, $permanent_barangay, $permanent_city, $permanent_province, $permanent_zip_code,
            $user_id
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// ── STEP 3: Family Background ──────────────────────────────
if ($step === 3) {
    $father_last_name  = require_field('fatherLastName',  "Father's Last Name",  $errors);
    $father_first_name = require_field('fatherFirstName', "Father's First Name", $errors);
    $father_occupation = require_field('fatherOccupation',"Father's Occupation", $errors);
    $father_education  = require_field('fatherEducation', "Father's Education",  $errors);
    $mother_last_name  = require_field('motherLastName',  "Mother's Last Name",  $errors);
    $mother_first_name = require_field('motherFirstName', "Mother's First Name", $errors);
    $mother_occupation = require_field('motherOccupation',"Mother's Occupation", $errors);
    $mother_education  = require_field('motherEducation', "Mother's Education",  $errors);

    if (empty($errors)) {
        $father_middle_name   = optional('fatherMiddleName');
        $father_contact       = optional('fatherContact');
        $mother_middle_name   = optional('motherMiddleName');
        $mother_contact       = optional('motherContact');
        $guardian_name        = optional('guardianName');
        $guardian_relationship= optional('guardianRelationship');
        $guardian_contact     = optional('guardianContact');

        $stmt = mysqli_prepare($conn,
            "UPDATE applications SET
                father_last_name=?, father_first_name=?, father_middle_name=?,
                father_occupation=?, father_contact=?, father_education=?,
                mother_last_name=?, mother_first_name=?, mother_middle_name=?,
                mother_occupation=?, mother_contact=?, mother_education=?,
                guardian_name=?, guardian_relationship=?, guardian_contact=?,
                current_step=3, updated_at=NOW()
             WHERE user_id=?"
        );
        mysqli_stmt_bind_param($stmt, 'sssssssssssssssi',
            $father_last_name, $father_first_name, $father_middle_name,
            $father_occupation, $father_contact, $father_education,
            $mother_last_name, $mother_first_name, $mother_middle_name,
            $mother_occupation, $mother_contact, $mother_education,
            $guardian_name, $guardian_relationship, $guardian_contact,
            $user_id
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// ── STEP 4: Educational Background ────────────────────────
if ($step === 4) {
    $applicant_type = require_field('applicantType', 'Applicant Type', $errors);

    if (empty($errors)) {
        if ($applicant_type === 'Freshmen') {
            $shs_name      = require_field('shsName',     'Senior High School Name',    $errors);
            $shs_address   = require_field('shsAddress',  'Senior High School Address', $errors);
            $shs_year_grad = require_field('shsYearGrad', 'Year Graduated/Expected',    $errors);
            $shs_strand    = require_field('shsStrand',   'SHS Strand',                 $errors);
            $shs_gwa       = require_field('shsGwa',      'GWA',                        $errors);
            $school_type   = require_field('schoolType',  'Type of School',             $errors);
            $lrn           = require_field('lrn',         "Learner's Reference Number", $errors);
            $awards        = optional('awards');

            $prev_school_name = $prev_school_address = $prev_course = null;
            $prev_year_level  = $transfer_reason = null;

        } elseif ($applicant_type === 'Transferee') {
            $prev_school_name    = require_field('prevSchoolName',    'Previous School Name',    $errors);
            $prev_school_address = require_field('prevSchoolAddress', 'Previous School Address', $errors);
            $prev_course         = require_field('prevCourse',        'Previous Course',         $errors);
            $prev_year_level     = require_field('prevYearLevel',     'Previous Year Level',     $errors);
            $transfer_reason     = require_field('transferReason',    'Reason for Transfer',     $errors);

            $shs_name = $shs_address = $shs_year_grad = $shs_strand = null;
            $shs_gwa  = $awards = null;
            // Transferees don't have LRN/school_type from SHS section
            $school_type = optional('schoolType');
            $lrn         = optional('lrn');

        } else {
            $shs_name = $shs_address = $shs_year_grad = $shs_strand = null;
            $shs_gwa  = $awards = null;
            $prev_school_name = $prev_school_address = $prev_course = null;
            $prev_year_level  = $transfer_reason = null;
            $school_type = null;
            $lrn         = null;
        }
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn,
            "UPDATE applications SET
                applicant_type=?,
                shs_name=?, shs_address=?, shs_year_grad=?, shs_strand=?, shs_gwa=?, awards=?,
                school_type=?, lrn=?,
                prev_school_name=?, prev_school_address=?, prev_course=?, prev_year_level=?, transfer_reason=?,
                current_step=4, updated_at=NOW()
             WHERE user_id=?"
        );
        // s s s s s s s  s  s  s  s  s  s  s  i = 15 params
        mysqli_stmt_bind_param($stmt, 'ssssssssssssssi',
            $applicant_type,
            $shs_name, $shs_address, $shs_year_grad, $shs_strand, $shs_gwa, $awards,
            $school_type, $lrn,
            $prev_school_name, $prev_school_address, $prev_course, $prev_year_level, $transfer_reason,
            $user_id
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// ── STEP 5: Program Selection ──────────────────────────────
if ($step === 5) {
    $first_choice = require_field('firstChoice', '1st Choice Program', $errors);

    if (empty($errors)) {
        $second_choice    = optional('secondChoice');
        $third_choice     = optional('thirdChoice');
        $scholarship      = !empty($_POST['scholarship']) ? 1 : 0;
        $scholarship_type = optional('scholarshipType');
        $pwd              = !empty($_POST['pwd']) ? 1 : 0;
        $pwd_specify      = optional('pwdSpecify');
        $indigenous       = !empty($_POST['indigenous']) ? 1 : 0;
        $four_ps          = !empty($_POST['fourPs']) ? 1 : 0;

        // Use explicit student selection for program_category
        $program_track    = trim($_POST['programTrack'] ?? '');
        $program_category = $program_track === 'TESDA' ? 'TESDA' : 'CHED';

        // TESDA applicants have no 3rd choice
        if ($program_category === 'TESDA') $third_choice = '';

        $stmt = mysqli_prepare($conn,
            "UPDATE applications SET
                first_choice=?, second_choice=?, third_choice=?, program_category=?,
                scholarship=?, scholarship_type=?,
                pwd=?, pwd_specify=?,
                indigenous=?, four_ps=?,
                current_step=5, updated_at=NOW()
             WHERE user_id=?"
        );
        // s  s  s  s  i  s  i  s  i  i  i = 11 params
        mysqli_stmt_bind_param($stmt, 'ssssisisiii',
            $first_choice, $second_choice, $third_choice, $program_category,
            $scholarship, $scholarship_type,
            $pwd, $pwd_specify,
            $indigenous, $four_ps,
            $user_id
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// ── STEP 6: Document Upload ────────────────────────────────
if ($step === 6) {
    $app_row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT applicant_type FROM applications WHERE user_id = $user_id LIMIT 1"
    ));
    $applicant_type = $app_row['applicant_type'] ?? 'Freshmen';

    $upload_dir = BASE_PATH . '/uploads/';
    if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);

    $allowed = ['image/jpeg', 'image/png', 'application/pdf'];
    $max     = 10 * 1024 * 1024;

    function handle_file($field, $label, $upload_dir, $allowed, $max, &$errors, $required = true) {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            if ($required) $errors[] = $label . ' is required.';
            return null;
        }
        $f = $_FILES[$field];
        if ($f['error'] !== UPLOAD_ERR_OK)             { $errors[] = $label . ' upload error.';              return null; }
        if (!in_array($f['type'], $allowed, true))     { $errors[] = $label . ' must be JPG, PNG, or PDF.';  return null; }
        if ($f['size'] > $max)                         { $errors[] = $label . ' must not exceed 10MB.';      return null; }
        $ext  = pathinfo($f['name'], PATHINFO_EXTENSION);
        $safe = $field . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $path = $upload_dir . $safe;
        if (!move_uploaded_file($f['tmp_name'], $path)) { $errors[] = 'Failed to save ' . $label;            return null; }
        return 'uploads/' . $safe;
    }

    $id_photo_path    = handle_file('idPhoto',    '2x2 ID Photo',         $upload_dir, $allowed, $max, $errors, true);
    $grades_required  = ($applicant_type === 'Freshmen');
    $grades_path      = handle_file('grades',     'Report Card',          $upload_dir, $allowed, $max, $errors, $grades_required);
    $birth_cert_path  = handle_file('birthCert',  'PSA Birth Certificate',$upload_dir, $allowed, $max, $errors, true);

    if ($applicant_type === 'Transferee') {
        $transfer_cred_path = handle_file('transferCred', 'Transfer Credential',   $upload_dir, $allowed, $max, $errors, true);
        $tor_path           = handle_file('tor',          'Transcript of Records', $upload_dir, $allowed, $max, $errors, true);
    } else {
        $transfer_cred_path = handle_file('transferCred', 'Transfer Credential',   $upload_dir, $allowed, $max, $errors, false);
        $tor_path           = handle_file('tor',          'Transcript of Records', $upload_dir, $allowed, $max, $errors, false);
    }

    if (empty($errors)) {
        // Keep existing paths if no new file uploaded
        $stmt_cur = mysqli_prepare($conn,
            "SELECT id_photo_path, grades_path, birth_cert_path, transfer_cred_path, tor_path
             FROM applications WHERE user_id = ? LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt_cur, 'i', $user_id);
        mysqli_stmt_execute($stmt_cur);
        $cur = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cur));
        mysqli_stmt_close($stmt_cur);

        $id_photo_path      = $id_photo_path      ?? ($cur['id_photo_path']      ?? null);
        $grades_path        = $grades_path        ?? ($cur['grades_path']        ?? null);
        $birth_cert_path    = $birth_cert_path    ?? ($cur['birth_cert_path']    ?? null);
        $transfer_cred_path = $transfer_cred_path ?? ($cur['transfer_cred_path'] ?? null);
        $tor_path           = $tor_path           ?? ($cur['tor_path']           ?? null);

        $stmt = mysqli_prepare($conn,
            "UPDATE applications SET
                id_photo_path=?, grades_path=?, birth_cert_path=?,
                transfer_cred_path=?, tor_path=?,
                current_step=6, updated_at=NOW()
             WHERE user_id=?"
        );
        mysqli_stmt_bind_param($stmt, 'sssssi',
            $id_photo_path, $grades_path, $birth_cert_path,
            $transfer_cred_path, $tor_path,
            $user_id
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// ── REDIRECT ───────────────────────────────────────────────
if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    redirect("app/student/application-form.php?step=$step");
}

$_SESSION['form_errors'] = [];
$target = ($action === 'next') ? min($step + 1, 7) : $step;
redirect("app/student/application-form.php?step=$target");