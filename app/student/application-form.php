<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admission Application Form
 * application-form.php
 *
 * PHP-driven multi-step form. Step controlled by ?step=X.
 * Each step is a separate <form method="POST">. No AJAX.
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/programs.php';
$all_programs   = get_all_programs($conn);
$ched_opts      = array_filter($all_programs, fn($p) => $p['category'] === 'CHED');
$tesda_opts     = array_filter($all_programs, fn($p) => $p['category'] === 'TESDA');

if (!isset($_SESSION['user_id'])) {
    redirect('index.php');
}

$user_id = (int)$_SESSION['user_id'];

// Load full application row for pre-fill and review
$query = "SELECT * FROM applications WHERE user_id = ? LIMIT 1";
$stmt  = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$app = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
mysqli_stmt_close($stmt);

// Check application period from system settings
$applications_open = get_setting('application_open', '1') === '1';

mysqli_close($conn);

$status           = $app['status'] ?? 'Draft';
$is_submitted     = !empty($app['submitted']) || in_array($status, ['Submitted', 'Application Submitted'], true);
$reference_number = $app['reference_number'] ?? '';
$form_errors      = $_SESSION['form_errors'] ?? [];
$_SESSION['form_errors'] = [];

// When applications are closed, block editing (but still allow viewing submitted applications)
if (!$applications_open && !$is_submitted) {
    $_SESSION['error'] = 'The application period is currently closed. You can no longer edit your application.';
    redirect('app/student/dashboard.php');
}

// ── REUPLOAD MODE ──────────────────────────────────────────
$is_reupload_mode = (
    $status === 'Documents Rejected' &&
    isset($_GET['reupload']) &&
    (int)$_GET['reupload'] === 1
);

// ── STEP RESOLUTION ────────────────────────────────────────
$step = isset($_GET['step']) ? (int)$_GET['step'] : 0;

if ($is_reupload_mode) {
    $step = 6;
} elseif ($is_submitted) {
    $step = 7;
} else {
    if ($step < 1 || $step > 7) {
        $step = max(1, (int)($app['current_step'] ?? 1));
    }
}

// ── STEP NAMES ─────────────────────────────────────────────
$step_names = [
    1 => 'Personal Information',
    2 => 'Contact & Address',
    3 => 'Family Background',
    4 => 'Educational Background',
    5 => 'Program Selection',
    6 => 'Document Upload',
    7 => 'Review & Submit',
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../forms.css?v=2.2">
    <title>BPC Admission Application Form - Bulacan Polytechnic College</title>
    <style>
        @media print {
            .breadcrumb-nav, .progress-container, .no-print { display: none !important; }
        }
    </style>
</head>
<body class="<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';

echo $is_submitted ? 'is-submitted' : ''; ?>">

    <!-- HEADER -->
    <header class="form-header">
        <div class="header-content">
            <img src="../../assets/cropped-cropped-cropped-cropped-cropped-bpclogo-1-1-1-150x150.png" alt="BPC Logo" class="header-logo">
            <div class="header-text">
                <h1>Bulacan Polytechnic College</h1>
                <p>Online Admission Application Form</p>
            </div>
        </div>
    </header>

    <!-- BREADCRUMB -->
    <nav class="breadcrumb-nav">
        <div class="breadcrumb-content">
            <a href="dashboard.php" class="breadcrumb-link">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
                </svg>
                <span>Back to Dashboard</span>
            </a>
            <?php
if ($is_submitted): ?>
            <button type="button" class="breadcrumb-link no-print" onclick="window.print();" style="margin-left: 1rem; background: none; border: none; cursor: pointer; font: inherit; color: inherit; display: inline-flex; align-items: center; gap: 0.5rem;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                    <path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/>
                </svg>
                <span>Print</span>
            </button>
            <?php
endif; ?>
        </div>
    </nav>

    <!-- PROGRESS BAR -->
    <div class="progress-container">
        <div class="progress-content">
            <div class="progress-bar-wrapper">
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" id="progressBar" style="width: <?php
echo round(($step / 7) * 100); ?>%;"></div>
                </div>
            </div>
            <div class="progress-steps">
                <?php
for ($i = 1; $i <= 7; $i++):
                    $classes = ['progress-step'];
                    if ($i === $step) $classes[] = 'active';
                    elseif ($i < $step) $classes[] = 'completed';
                ?>
                <div class="<?php
echo implode(' ', $classes); ?>">
                    <div class="step-number"><?php
echo $i; ?></div>
                    <div class="step-label"><?php
echo $i === 1 ? 'Personal Info' : ($i === 2 ? 'Address' : ($i === 3 ? 'Family' : ($i === 4 ? 'Education' : ($i === 5 ? 'Program' : ($i === 6 ? 'Documents' : 'Review'))))); ?></div>
                </div>
                <?php
endfor; ?>
            </div>
            <p class="step-title">Step <strong id="currentStepNum"><?php
echo $step; ?></strong> of 7: <strong id="currentStepName"><?php
echo htmlspecialchars($step_names[$step]); ?></strong></p>
            <?php
if (!empty($form_errors)): ?>
            <div class="form-errors-list" style="background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;border-radius:8px;padding:1rem;margin-top:0.5rem;">
                <strong>Please correct the following:</strong>
                <ul style="margin:0.5rem 0 0 1.5rem;">
                    <?php
foreach ($form_errors as $e): ?>
                    <li><?php
echo htmlspecialchars($e); ?></li>
                    <?php
endforeach; ?>
                </ul>
            </div>
            <?php
endif; ?>
        </div>
    </div>

    <!-- FORM CONTAINER -->
    <div class="form-container">
        <?php
if ($is_submitted): ?>
            <div style="background-color: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1.25rem;">
                <strong>Submitted:</strong> Your application has already been submitted<?php
echo $reference_number ? ' (Ref: ' . htmlspecialchars($reference_number) . ')' : ''; ?>.
                You can review your answers below, but editing is disabled.
            </div>
        <?php
endif; ?>

        <?php
if ($step === 1): ?>
        <!-- ===== STEP 1: PERSONAL INFORMATION ===== -->
        <form method="POST" action="../handlers/save-step.php" id="stepForm1" class="form-step-wrapper">
            <input type="hidden" name="step" value="1">
            <div class="form-step active" id="step-1">
                <div class="step-header">
                    <h2>Personal Information</h2>
                    <p>Please provide your basic personal details</p>
                </div>

                <!-- Name Row -->
                <div class="form-row three-column">
                    <div class="form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" id="lastName" name="lastName" value="<?php
echo htmlspecialchars($app['last_name'] ?? ''); ?>" required>
                        <span class="error-message">Last name is required</span>
                    </div>
                    <div class="form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" id="firstName" name="firstName" value="<?php
echo htmlspecialchars($app['first_name'] ?? ''); ?>" required>
                        <span class="error-message">First name is required</span>
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" id="middleName" name="middleName" value="<?php
echo htmlspecialchars($app['middle_name'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Suffix + PSA Row -->
                <div class="form-row two-column">
                    <div class="form-group">
                        <label>Suffix (if applicable)</label>
                        <select id="suffix" name="suffix">
                            <option value="">None</option>
                            <?php
$sfx = $app['suffix'] ?? ''; ?>
                            <option value="Jr." <?php
echo $sfx === 'Jr.' ? 'selected' : ''; ?>>Jr.</option>
                            <option value="Sr." <?php
echo $sfx === 'Sr.' ? 'selected' : ''; ?>>Sr.</option>
                            <option value="II"  <?php
echo $sfx === 'II'  ? 'selected' : ''; ?>>II</option>
                            <option value="III" <?php
echo $sfx === 'III' ? 'selected' : ''; ?>>III</option>
                            <option value="IV"  <?php
echo $sfx === 'IV'  ? 'selected' : ''; ?>>IV</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>PSA Registry Number <span class="required">*</span></label>
                        <input type="text" id="psaRegistryNo" name="psaRegistryNo"
                               value="<?php
echo htmlspecialchars($app['psa_registry_no'] ?? ''); ?>"
                               placeholder="e.g. 2003-12345678" required>
                        <span class="form-helper">Found on your PSA Birth Certificate</span>
                        <span class="error-message">PSA Registry Number is required</span>
                    </div>
                </div>

                <!-- Sex + DOB Row -->
                <div class="form-row two-column">
                    <div class="form-group">
                        <label>Sex <span class="required">*</span></label>
                        <select id="sex" name="sex" required>
                            <option value="">Select</option>
                            <?php
$sex = $app['sex'] ?? ''; ?>
                            <option value="Male"   <?php
echo $sex === 'Male'   ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php
echo $sex === 'Female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                        <span class="error-message">Please select your sex</span>
                    </div>
                    <div class="form-group">
                        <label>Date of Birth <span class="required">*</span></label>
                        <input type="date" id="dateOfBirth" name="dateOfBirth" value="<?php
echo htmlspecialchars($app['date_of_birth'] ?? ''); ?>" required>
                        <span class="error-message">Date of birth is required</span>
                    </div>
                </div>

                <!-- Age + Birth Place Row -->
                <div class="form-row two-column">
                    <div class="form-group">
                        <label>Age</label>
                        <input type="number" id="age" name="age" value="<?php
echo htmlspecialchars($app['age'] ?? ''); ?>" readonly>
                        <span class="form-helper">Auto-calculated from date of birth</span>
                    </div>
                    <div class="form-group">
                        <label>Place of Birth (City/Municipality) <span class="required">*</span></label>
                        <input type="text" id="birthCity" name="birthCity" value="<?php
echo htmlspecialchars($app['birth_city'] ?? ''); ?>" required>
                        <span class="error-message">Place of birth is required</span>
                    </div>
                </div>

                <!-- Province + Civil Status Row -->
                <div class="form-row two-column">
                    <div class="form-group">
                        <label>Province <span class="required">*</span></label>
                        <input type="text" id="birthProvince" name="birthProvince" value="<?php
echo htmlspecialchars($app['birth_province'] ?? ''); ?>" required>
                        <span class="error-message">Province is required</span>
                    </div>
                    <div class="form-group">
                        <label>Civil Status <span class="required">*</span></label>
                        <select id="civilStatus" name="civilStatus" required>
                            <option value="">Select</option>
                            <?php
$cs = $app['civil_status'] ?? ''; ?>
                            <option value="Single"    <?php
echo $cs === 'Single'    ? 'selected' : ''; ?>>Single</option>
                            <option value="Married"   <?php
echo $cs === 'Married'   ? 'selected' : ''; ?>>Married</option>
                            <option value="Widowed"   <?php
echo $cs === 'Widowed'   ? 'selected' : ''; ?>>Widowed</option>
                            <option value="Separated" <?php
echo $cs === 'Separated' ? 'selected' : ''; ?>>Separated</option>
                        </select>
                        <span class="error-message">Please select your civil status</span>
                    </div>
                </div>

                <!-- Citizenship + Religion Row -->
                <div class="form-row two-column">
                    <div class="form-group">
                        <label>Citizenship <span class="required">*</span></label>
                        <input type="text" id="citizenship" name="citizenship" value="<?php
echo htmlspecialchars($app['citizenship'] ?? 'Filipino'); ?>" required>
                        <span class="error-message">Citizenship is required</span>
                    </div>
                    <div class="form-group">
                        <label>Religion</label>
                        <input type="text" id="religion" name="religion" value="<?php
echo htmlspecialchars($app['religion'] ?? ''); ?>" placeholder="e.g., Roman Catholic, Islam, etc.">
                    </div>
                </div>

                <div class="form-navigation">
                    <span class="btn btn-secondary" style="cursor:default;opacity:0.6;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
                        Previous
                    </span>
                    <button type="submit" name="action" value="save" class="btn btn-save">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
                        Save Draft
                    </button>
                    <button type="submit" name="action" value="next" class="btn btn-primary">
                        Next
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                    </button>
                </div>
            </div>
        </form>
        <?php
endif; ?>

        <?php
if ($step === 2): ?>
        <!-- ===== STEP 2: CONTACT & ADDRESS ===== -->
        <form method="POST" action="../handlers/save-step.php" id="stepForm2" class="form-step-wrapper">
            <input type="hidden" name="step" value="2">
            <div class="form-step active" id="step-2">
                <div class="step-header">
                    <h2>Contact & Address Information</h2>
                    <p>How can we reach you?</p>
                </div>

                <div class="form-row two-column">
                    <div class="form-group">
                        <label>Mobile Number <span class="required">*</span></label>
                        <input type="tel" id="mobileNumber" name="mobileNumber" value="<?php
echo htmlspecialchars($app['mobile_number'] ?? ''); ?>" placeholder="09XX-XXX-XXXX" required>
                        <span class="error-message">Valid mobile number is required</span>
                    </div>
                    <div class="form-group">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" value="<?php
echo htmlspecialchars($app['email'] ?? ''); ?>" placeholder="your.email@example.com" required>
                        <span class="error-message">Valid email address is required</span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Facebook Account</label>
                        <input type="text" id="facebook" name="facebook" value="<?php
echo htmlspecialchars($app['facebook'] ?? ''); ?>" placeholder="Your Facebook name or profile URL">
                        <span class="form-helper">For easier communication (optional)</span>
                    </div>
                </div>

                <div class="section-divider">
                    <h3>Current Address</h3>
                    <p>Where do you currently live?</p>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>House No. / Street <span class="required">*</span></label>
                        <input type="text" id="currentHouseStreet" name="currentHouseStreet" value="<?php
echo htmlspecialchars($app['current_house_street'] ?? ''); ?>" required>
                        <span class="error-message">House number/street is required</span>
                    </div>
                </div>

                <div class="form-row two-column">
                    <div class="form-group">
                        <label>Barangay <span class="required">*</span></label>
                        <input type="text" id="currentBarangay" name="currentBarangay" value="<?php
echo htmlspecialchars($app['current_barangay'] ?? ''); ?>" required>
                        <span class="error-message">Barangay is required</span>
                    </div>
                    <div class="form-group">
                        <label>City/Municipality <span class="required">*</span></label>
                        <input type="text" id="currentCity" name="currentCity" value="<?php
echo htmlspecialchars($app['current_city'] ?? ''); ?>" required>
                        <span class="error-message">City/Municipality is required</span>
                    </div>
                </div>

                <div class="form-row two-column">
                    <div class="form-group">
                        <label>Province <span class="required">*</span></label>
                        <input type="text" id="currentProvince" name="currentProvince" value="<?php
echo htmlspecialchars($app['current_province'] ?? ''); ?>" required>
                        <span class="error-message">Province is required</span>
                    </div>
                    <div class="form-group">
                        <label>ZIP Code <span class="required">*</span></label>
                        <input type="text" id="currentZipCode" name="currentZipCode" value="<?php
echo htmlspecialchars($app['current_zip_code'] ?? ''); ?>" maxlength="4" required>
                        <span class="error-message">ZIP code is required</span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="sameAsPermanent" name="sameAsPermanent">
                            <label for="sameAsPermanent">Same as Permanent Address</label>
                        </div>
                    </div>
                </div>

                <div class="section-divider" id="permanentAddressSection">
                    <h3>Permanent Address</h3>
                    <p>Your official/permanent residence</p>
                </div>

                <div id="permanentAddressFields">
                    <div class="form-row">
                        <div class="form-group">
                            <label>House No. / Street <span class="required">*</span></label>
                            <input type="text" id="permanentHouseStreet" name="permanentHouseStreet" value="<?php
echo htmlspecialchars($app['permanent_house_street'] ?? ''); ?>" required>
                            <span class="error-message">House number/street is required</span>
                        </div>
                    </div>

                    <div class="form-row two-column">
                        <div class="form-group">
                            <label>Barangay <span class="required">*</span></label>
                            <input type="text" id="permanentBarangay" name="permanentBarangay" value="<?php
echo htmlspecialchars($app['permanent_barangay'] ?? ''); ?>" required>
                            <span class="error-message">Barangay is required</span>
                        </div>
                        <div class="form-group">
                            <label>City/Municipality <span class="required">*</span></label>
                            <input type="text" id="permanentCity" name="permanentCity" value="<?php
echo htmlspecialchars($app['permanent_city'] ?? ''); ?>" required>
                            <span class="error-message">City/Municipality is required</span>
                        </div>
                    </div>

                    <div class="form-row two-column">
                        <div class="form-group">
                            <label>Province <span class="required">*</span></label>
                            <input type="text" id="permanentProvince" name="permanentProvince" value="<?php
echo htmlspecialchars($app['permanent_province'] ?? ''); ?>" required>
                            <span class="error-message">Province is required</span>
                        </div>
                        <div class="form-group">
                            <label>ZIP Code <span class="required">*</span></label>
                            <input type="text" id="permanentZipCode" name="permanentZipCode" value="<?php
echo htmlspecialchars($app['permanent_zip_code'] ?? ''); ?>" maxlength="4" required>
                            <span class="error-message">ZIP code is required</span>
                        </div>
                    </div>
                </div>

                <div class="form-navigation">
                    <a href="application-form.php?step=1" class="btn btn-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
                        Previous
                    </a>
                    <button type="submit" name="action" value="save" class="btn btn-save">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
                        Save Draft
                    </button>
                    <button type="submit" name="action" value="next" class="btn btn-primary">
                        Next
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                    </button>
                </div>
            </div>
        </form>
        <?php
endif; ?>

        <?php
if ($step === 3): ?>
        <!-- ===== STEP 3: FAMILY BACKGROUND ===== -->
        <form method="POST" action="../handlers/save-step.php" id="stepForm3" class="form-step-wrapper">
            <input type="hidden" name="step" value="3">
            <div class="form-step active" id="step-3">
                <div class="step-header">
                    <h2>Family Background</h2>
                    <p>Information about your parents/guardians</p>
                </div>

                <div class="section-divider"><h3>Father's Information</h3></div>

                <div class="form-row three-column">
                    <div class="form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" id="fatherLastName" name="fatherLastName" value="<?php
echo htmlspecialchars($app['father_last_name'] ?? ''); ?>" required>
                        <span class="error-message">Father's last name is required</span>
                    </div>
                    <div class="form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" id="fatherFirstName" name="fatherFirstName" value="<?php
echo htmlspecialchars($app['father_first_name'] ?? ''); ?>" required>
                        <span class="error-message">Father's first name is required</span>
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" id="fatherMiddleName" name="fatherMiddleName" value="<?php
echo htmlspecialchars($app['father_middle_name'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-row two-column">
                    <div class="form-group">
                        <label>Occupation <span class="required">*</span></label>
                        <input type="text" id="fatherOccupation" name="fatherOccupation" value="<?php
echo htmlspecialchars($app['father_occupation'] ?? ''); ?>" required>
                        <span class="error-message">Father's occupation is required</span>
                    </div>
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="tel" id="fatherContact" name="fatherContact" value="<?php
echo htmlspecialchars($app['father_contact'] ?? ''); ?>" placeholder="09XX-XXX-XXXX">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Highest Educational Attainment <span class="required">*</span></label>
                        <select id="fatherEducation" name="fatherEducation" required>
                            <option value="">Select</option>
                            <?php
$fe = $app['father_education'] ?? ''; ?>
                            <option value="Elementary"        <?php
echo $fe === 'Elementary'        ? 'selected' : ''; ?>>Elementary</option>
                            <option value="High School"       <?php
echo $fe === 'High School'       ? 'selected' : ''; ?>>High School</option>
                            <option value="Senior High School"<?php
echo $fe === 'Senior High School'? 'selected' : ''; ?>>Senior High School</option>
                            <option value="Vocational"        <?php
echo $fe === 'Vocational'        ? 'selected' : ''; ?>>Vocational/Technical</option>
                            <option value="College"           <?php
echo $fe === 'College'           ? 'selected' : ''; ?>>College Graduate</option>
                            <option value="Masteral"          <?php
echo $fe === 'Masteral'          ? 'selected' : ''; ?>>Masteral Degree</option>
                            <option value="Doctoral"          <?php
echo $fe === 'Doctoral'          ? 'selected' : ''; ?>>Doctoral Degree</option>
                        </select>
                        <span class="error-message">Please select father's educational attainment</span>
                    </div>
                </div>

                <div class="section-divider"><h3>Mother's Information</h3></div>

                <div class="form-row three-column">
                    <div class="form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" id="motherLastName" name="motherLastName" value="<?php
echo htmlspecialchars($app['mother_last_name'] ?? ''); ?>" required>
                        <span class="error-message">Mother's last name is required</span>
                    </div>
                    <div class="form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" id="motherFirstName" name="motherFirstName" value="<?php
echo htmlspecialchars($app['mother_first_name'] ?? ''); ?>" required>
                        <span class="error-message">Mother's first name is required</span>
                    </div>
                    <div class="form-group">
                        <label>Middle Name (Maiden)</label>
                        <input type="text" id="motherMiddleName" name="motherMiddleName" value="<?php
echo htmlspecialchars($app['mother_middle_name'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-row two-column">
                    <div class="form-group">
                        <label>Occupation <span class="required">*</span></label>
                        <input type="text" id="motherOccupation" name="motherOccupation" value="<?php
echo htmlspecialchars($app['mother_occupation'] ?? ''); ?>" required>
                        <span class="error-message">Mother's occupation is required</span>
                    </div>
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="tel" id="motherContact" name="motherContact" value="<?php
echo htmlspecialchars($app['mother_contact'] ?? ''); ?>" placeholder="09XX-XXX-XXXX">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Highest Educational Attainment <span class="required">*</span></label>
                        <select id="motherEducation" name="motherEducation" required>
                            <option value="">Select</option>
                            <?php
$me = $app['mother_education'] ?? ''; ?>
                            <option value="Elementary"        <?php
echo $me === 'Elementary'        ? 'selected' : ''; ?>>Elementary</option>
                            <option value="High School"       <?php
echo $me === 'High School'       ? 'selected' : ''; ?>>High School</option>
                            <option value="Senior High School"<?php
echo $me === 'Senior High School'? 'selected' : ''; ?>>Senior High School</option>
                            <option value="Vocational"        <?php
echo $me === 'Vocational'        ? 'selected' : ''; ?>>Vocational/Technical</option>
                            <option value="College"           <?php
echo $me === 'College'           ? 'selected' : ''; ?>>College Graduate</option>
                            <option value="Masteral"          <?php
echo $me === 'Masteral'          ? 'selected' : ''; ?>>Masteral Degree</option>
                            <option value="Doctoral"          <?php
echo $me === 'Doctoral'          ? 'selected' : ''; ?>>Doctoral Degree</option>
                        </select>
                        <span class="error-message">Please select mother's educational attainment</span>
                    </div>
                </div>

                <div class="section-divider">
                    <h3>Guardian Information (If applicable)</h3>
                    <p>Fill this out only if you have a legal guardian</p>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Guardian's Full Name</label>
                        <input type="text" id="guardianName" name="guardianName" value="<?php
echo htmlspecialchars($app['guardian_name'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-row two-column">
                    <div class="form-group">
                        <label>Relationship</label>
                        <input type="text" id="guardianRelationship" name="guardianRelationship" value="<?php
echo htmlspecialchars($app['guardian_relationship'] ?? ''); ?>" placeholder="e.g., Aunt, Uncle, Grandparent">
                    </div>
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="tel" id="guardianContact" name="guardianContact" value="<?php
echo htmlspecialchars($app['guardian_contact'] ?? ''); ?>" placeholder="09XX-XXX-XXXX">
                    </div>
                </div>

                <div class="form-navigation">
                    <a href="application-form.php?step=2" class="btn btn-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
                        Previous
                    </a>
                    <button type="submit" name="action" value="save" class="btn btn-save">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
                        Save Draft
                    </button>
                    <button type="submit" name="action" value="next" class="btn btn-primary">
                        Next
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                    </button>
                </div>
            </div>
        </form>
        <?php
endif; ?>

        <?php
if ($step === 4): ?>
        <!-- ===== STEP 4: EDUCATIONAL BACKGROUND ===== -->
        <form method="POST" action="../handlers/save-step.php" id="stepForm4" class="form-step-wrapper">
            <input type="hidden" name="step" value="4">
            <div class="form-step active" id="step-4">
                <div class="step-header">
                    <h2>Educational Background</h2>
                    <p>Tell us about your academic history</p>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Applicant Type <span class="required">*</span></label>
                        <div class="radio-group">
                            <?php
$at = $app['applicant_type'] ?? 'Freshmen'; ?>
                            <div class="radio-item">
                                <input type="radio" id="freshmen" name="applicantType" value="Freshmen" required <?php
echo $at === 'Freshmen' ? 'checked' : ''; ?>>
                                <label for="freshmen">Incoming Freshmen (Senior High School Graduate)</label>
                            </div>
                            <div class="radio-item">
                                <input type="radio" id="transferee" name="applicantType" value="Transferee" required <?php
echo $at === 'Transferee' ? 'checked' : ''; ?>>
                                <label for="transferee">Transferee (From another college/university)</label>
                            </div>
                        </div>
                        <span class="error-message">Please select applicant type</span>
                    </div>
                </div>

                <!-- FRESHMEN FIELDS -->
                <div id="freshmenFields" style="<?php
echo $at === 'Transferee' ? 'display:none;' : ''; ?>">
                    <div class="section-divider"><h3>Senior High School Information</h3></div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Name of School <span class="required">*</span></label>
                            <input type="text" id="shsName" name="shsName" value="<?php
echo htmlspecialchars($app['shs_name'] ?? ''); ?>" required>
                            <span class="error-message">School name is required</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>School Address <span class="required">*</span></label>
                            <input type="text" id="shsAddress" name="shsAddress" value="<?php
echo htmlspecialchars($app['shs_address'] ?? ''); ?>" required>
                            <span class="error-message">School address is required</span>
                        </div>
                    </div>

                    <!-- NEW: Type of School + LRN -->
                    <div class="form-row two-column">
                        <div class="form-group">
                            <label>Type of School <span class="required">*</span></label>
                            <select id="schoolType" name="schoolType" required>
                                <option value="">Select Type</option>
                                <?php
$stype = $app['school_type'] ?? ''; ?>
                                <option value="Public"                 <?php
echo $stype === 'Public'                 ? 'selected' : ''; ?>>Public</option>
                                <option value="Private"                <?php
echo $stype === 'Private'                ? 'selected' : ''; ?>>Private</option>
                                <option value="State University/College" <?php
echo $stype === 'State University/College' ? 'selected' : ''; ?>>State University / College</option>
                            </select>
                            <span class="error-message">Please select type of school</span>
                        </div>
                        <div class="form-group">
                            <label>Learner's Reference Number (LRN) <span class="required">*</span></label>
                            <input type="text" id="lrn" name="lrn"
                                   value="<?php
echo htmlspecialchars($app['lrn'] ?? ''); ?>"
                                   placeholder="12-digit LRN e.g. 104777508002"
                                   maxlength="12" required>
                            <span class="form-helper">Found on your school ID or report card</span>
                            <span class="error-message">LRN is required</span>
                        </div>
                    </div>

                    <div class="form-row two-column">
                        <div class="form-group">
                            <label>Year Graduated/Expected Graduation <span class="required">*</span></label>
                            <input type="text" id="shsYearGrad" name="shsYearGrad" value="<?php
echo htmlspecialchars($app['shs_year_grad'] ?? ''); ?>" placeholder="e.g., 2024" required>
                            <span class="error-message">Year is required</span>
                        </div>
                        <div class="form-group">
                            <label>SHS Strand <span class="required">*</span></label>
                            <select id="shsStrand" name="shsStrand" required>
                                <option value="">Select Strand</option>
                                <optgroup label="Academic Track">
                                    <option value="STEM"  <?php
echo ($app['shs_strand']??'')==='STEM'  ? 'selected':''?>>STEM (Science, Technology, Engineering, and Mathematics)</option>
                                    <option value="ABM"   <?php
echo ($app['shs_strand']??'')==='ABM'   ? 'selected':''?>>ABM (Accountancy, Business, and Management)</option>
                                    <option value="HUMSS" <?php
echo ($app['shs_strand']??'')==='HUMSS' ? 'selected':''?>>HUMSS (Humanities and Social Sciences)</option>
                                    <option value="GAS"   <?php
echo ($app['shs_strand']??'')==='GAS'   ? 'selected':''?>>GAS (General Academic Strand)</option>
                                </optgroup>
                                <optgroup label="Arts & Design Track">
                                    <option value="Arts-Music"    <?php
echo ($app['shs_strand']??'')==='Arts-Music'    ? 'selected':''?>>Music and Arts</option>
                                    <option value="Arts-Creative" <?php
echo ($app['shs_strand']??'')==='Arts-Creative' ? 'selected':''?>>Creative and Design</option>
                                    <option value="Arts-Media"    <?php
echo ($app['shs_strand']??'')==='Arts-Media'    ? 'selected':''?>>Media Arts</option>
                                    <option value="Arts-Literary" <?php
echo ($app['shs_strand']??'')==='Arts-Literary' ? 'selected':''?>>Literary Arts</option>
                                </optgroup>
                                <optgroup label="Sports Track">
                                    <option value="Sports" <?php
echo ($app['shs_strand']??'')==='Sports' ? 'selected':''?>>Sports and Recreation</option>
                                </optgroup>
                                <optgroup label="TVL - Home Economics">
                                    <option value="TVL-HE-Bread"      <?php
echo ($app['shs_strand']??'')==='TVL-HE-Bread'      ? 'selected':''?>>Bread and Pastry Production</option>
                                    <option value="TVL-HE-Cookery"    <?php
echo ($app['shs_strand']??'')==='TVL-HE-Cookery'    ? 'selected':''?>>Cookery</option>
                                    <option value="TVL-HE-Food"       <?php
echo ($app['shs_strand']??'')==='TVL-HE-Food'       ? 'selected':''?>>Food and Beverage Services</option>
                                    <option value="TVL-HE-Housekeeping" <?php
echo ($app['shs_strand']??'')==='TVL-HE-Housekeeping' ? 'selected':''?>>Housekeeping</option>
                                    <option value="TVL-HE-Tourism"    <?php
echo ($app['shs_strand']??'')==='TVL-HE-Tourism'    ? 'selected':''?>>Tourism Promotion Services</option>
                                    <option value="TVL-HE-Beauty"     <?php
echo ($app['shs_strand']??'')==='TVL-HE-Beauty'     ? 'selected':''?>>Beauty Care/Wellness Massage</option>
                                    <option value="TVL-HE-Caregiving" <?php
echo ($app['shs_strand']??'')==='TVL-HE-Caregiving' ? 'selected':''?>>Caregiving</option>
                                    <option value="TVL-HE-Dressmaking"<?php
echo ($app['shs_strand']??'')==='TVL-HE-Dressmaking'? 'selected':''?>>Dressmaking/Tailoring</option>
                                    <option value="TVL-HE-Events"     <?php
echo ($app['shs_strand']??'')==='TVL-HE-Events'     ? 'selected':''?>>Events Management</option>
                                </optgroup>
                                <optgroup label="TVL - Information & Communications Technology">
                                    <option value="TVL-ICT-Animation"   <?php
echo ($app['shs_strand']??'')==='TVL-ICT-Animation'   ? 'selected':''?>>Animation</option>
                                    <option value="TVL-ICT-CSS"         <?php
echo ($app['shs_strand']??'')==='TVL-ICT-CSS'         ? 'selected':''?>>Computer Systems Servicing</option>
                                    <option value="TVL-ICT-Programming" <?php
echo ($app['shs_strand']??'')==='TVL-ICT-Programming' ? 'selected':''?>>Computer Programming (Java, .NET)</option>
                                    <option value="TVL-ICT-Illustration"<?php
echo ($app['shs_strand']??'')==='TVL-ICT-Illustration'? 'selected':''?>>Illustration</option>
                                    <option value="TVL-ICT-Contact"     <?php
echo ($app['shs_strand']??'')==='TVL-ICT-Contact'     ? 'selected':''?>>Contact Center Services</option>
                                    <option value="TVL-ICT-Medical"     <?php
echo ($app['shs_strand']??'')==='TVL-ICT-Medical'     ? 'selected':''?>>Medical Transcription</option>
                                </optgroup>
                                <optgroup label="TVL - Industrial Arts">
                                    <option value="TVL-IA-Automotive"  <?php
echo ($app['shs_strand']??'')==='TVL-IA-Automotive'  ? 'selected':''?>>Automotive Servicing</option>
                                    <option value="TVL-IA-Carpentry"   <?php
echo ($app['shs_strand']??'')==='TVL-IA-Carpentry'   ? 'selected':''?>>Carpentry</option>
                                    <option value="TVL-IA-Construction"<?php
echo ($app['shs_strand']??'')==='TVL-IA-Construction'? 'selected':''?>>Construction Painting</option>
                                    <option value="TVL-IA-Drafting"    <?php
echo ($app['shs_strand']??'')==='TVL-IA-Drafting'    ? 'selected':''?>>Drafting</option>
                                    <option value="TVL-IA-Driving"     <?php
echo ($app['shs_strand']??'')==='TVL-IA-Driving'     ? 'selected':''?>>Driving</option>
                                    <option value="TVL-IA-Electrical"  <?php
echo ($app['shs_strand']??'')==='TVL-IA-Electrical'  ? 'selected':''?>>Electrical Installation and Maintenance</option>
                                    <option value="TVL-IA-Electronics" <?php
echo ($app['shs_strand']??'')==='TVL-IA-Electronics' ? 'selected':''?>>Electronics Products Assembly and Servicing</option>
                                    <option value="TVL-IA-Masonry"     <?php
echo ($app['shs_strand']??'')==='TVL-IA-Masonry'     ? 'selected':''?>>Masonry</option>
                                    <option value="TVL-IA-Plumbing"    <?php
echo ($app['shs_strand']??'')==='TVL-IA-Plumbing'    ? 'selected':''?>>Plumbing</option>
                                    <option value="TVL-IA-Refrigeration"<?php
echo ($app['shs_strand']??'')==='TVL-IA-Refrigeration'? 'selected':''?>>Refrigeration and Air Conditioning Servicing</option>
                                    <option value="TVL-IA-Welding"     <?php
echo ($app['shs_strand']??'')==='TVL-IA-Welding'     ? 'selected':''?>>Welding and Fabrication</option>
                                    <option value="TVL-IA-Tile"        <?php
echo ($app['shs_strand']??'')==='TVL-IA-Tile'        ? 'selected':''?>>Tile Setting</option>
                                </optgroup>
                                <optgroup label="TVL - Agri-Fishery Arts">
                                    <option value="TVL-AFA-Agriculture"<?php
echo ($app['shs_strand']??'')==='TVL-AFA-Agriculture'? 'selected':''?>>Agricultural Crops Production</option>
                                    <option value="TVL-AFA-Animal"     <?php
echo ($app['shs_strand']??'')==='TVL-AFA-Animal'     ? 'selected':''?>>Animal Production</option>
                                    <option value="TVL-AFA-Aquaculture"<?php
echo ($app['shs_strand']??'')==='TVL-AFA-Aquaculture'? 'selected':''?>>Aquaculture (Fish Production)</option>
                                    <option value="TVL-AFA-Horticulture"<?php
echo ($app['shs_strand']??'')==='TVL-AFA-Horticulture'? 'selected':''?>>Horticulture</option>
                                    <option value="TVL-AFA-Landscape"  <?php
echo ($app['shs_strand']??'')==='TVL-AFA-Landscape'  ? 'selected':''?>>Landscape Installation and Maintenance</option>
                                    <option value="TVL-AFA-Organic"    <?php
echo ($app['shs_strand']??'')==='TVL-AFA-Organic'    ? 'selected':''?>>Organic Agriculture</option>
                                    <option value="TVL-AFA-Pest"       <?php
echo ($app['shs_strand']??'')==='TVL-AFA-Pest'       ? 'selected':''?>>Pest Management</option>
                                    <option value="TVL-AFA-Slaughtering"<?php
echo ($app['shs_strand']??'')==='TVL-AFA-Slaughtering'? 'selected':''?>>Slaughtering Operations</option>
                                    <option value="TVL-AFA-Processing" <?php
echo ($app['shs_strand']??'')==='TVL-AFA-Processing' ? 'selected':''?>>Agricultural and Fishery Arts Strand</option>
                                </optgroup>
                                <optgroup label="TVL - Maritime">
                                    <option value="TVL-Maritime-EIM"   <?php
echo ($app['shs_strand']??'')==='TVL-Maritime-EIM'   ? 'selected':''?>>Electrical Installation and Maintenance (Marine)</option>
                                    <option value="TVL-Maritime-Marine"<?php
echo ($app['shs_strand']??'')==='TVL-Maritime-Marine'? 'selected':''?>>Marine Fishing</option>
                                    <option value="TVL-Maritime-Ship"  <?php
echo ($app['shs_strand']??'')==='TVL-Maritime-Ship'  ? 'selected':''?>>Shielded Metal Arc Welding (SMAW) for Shipbuilding</option>
                                </optgroup>
                            </select>
                            <span class="error-message">Please select your strand</span>
                        </div>
                    </div>

                    <div class="form-row two-column">
                        <div class="form-group">
                            <label>General Average (GWA) <span class="required">*</span></label>
                            <input type="number" id="shsGwa" name="shsGwa" value="<?php
echo htmlspecialchars($app['shs_gwa'] ?? ''); ?>" step="0.01" min="75" max="100" placeholder="e.g., 92.50" required>
                            <span class="error-message">Please enter your GWA</span>
                        </div>
                        <div class="form-group">
                            <label>Awards/Honors Received</label>
                            <input type="text" id="awards" name="awards" value="<?php
echo htmlspecialchars($app['awards'] ?? ''); ?>" placeholder="e.g., With Honors, Academic Excellence">
                            <span class="form-helper">Leave blank if none</span>
                        </div>
                    </div>
                </div>

                <!-- TRANSFEREE FIELDS -->
                <div id="transfereeFields" style="<?php
echo $at === 'Freshmen' ? 'display:none;' : ''; ?>">
                    <div class="section-divider"><h3>Previous College/University Information</h3></div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Previous School Name <span class="required">*</span></label>
                            <input type="text" id="prevSchoolName" name="prevSchoolName" value="<?php
echo htmlspecialchars($app['prev_school_name'] ?? ''); ?>" required>
                            <span class="error-message">Previous school name is required</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>School Address <span class="required">*</span></label>
                            <input type="text" id="prevSchoolAddress" name="prevSchoolAddress" value="<?php
echo htmlspecialchars($app['prev_school_address'] ?? ''); ?>" required>
                            <span class="error-message">School address is required</span>
                        </div>
                    </div>

                    <div class="form-row two-column">
                        <div class="form-group">
                            <label>Course/Program Taken <span class="required">*</span></label>
                            <input type="text" id="prevCourse" name="prevCourse" value="<?php
echo htmlspecialchars($app['prev_course'] ?? ''); ?>" required>
                            <span class="error-message">Course is required</span>
                        </div>
                        <div class="form-group">
                            <label>Year Level Completed <span class="required">*</span></label>
                            <select id="prevYearLevel" name="prevYearLevel" required>
                                <option value="">Select</option>
                                <?php
$pyl = $app['prev_year_level'] ?? ''; ?>
                                <option value="1st Year" <?php
echo $pyl === '1st Year' ? 'selected' : ''; ?>>1st Year</option>
                                <option value="2nd Year" <?php
echo $pyl === '2nd Year' ? 'selected' : ''; ?>>2nd Year</option>
                                <option value="3rd Year" <?php
echo $pyl === '3rd Year' ? 'selected' : ''; ?>>3rd Year</option>
                                <option value="4th Year" <?php
echo $pyl === '4th Year' ? 'selected' : ''; ?>>4th Year</option>
                            </select>
                            <span class="error-message">Please select year level</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Reason for Transfer <span class="required">*</span></label>
                            <textarea id="transferReason" name="transferReason" rows="3" placeholder="Please briefly explain your reason for transferring" required><?php
echo htmlspecialchars($app['transfer_reason'] ?? ''); ?></textarea>
                            <span class="error-message">Please provide a reason</span>
                        </div>
                    </div>
                </div>

                <div class="form-navigation">
                    <a href="application-form.php?step=3" class="btn btn-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
                        Previous
                    </a>
                    <button type="submit" name="action" value="save" class="btn btn-save">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
                        Save Draft
                    </button>
                    <button type="submit" name="action" value="next" class="btn btn-primary">
                        Next
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                    </button>
                </div>
            </div>
        </form>
        <?php
endif; ?>

        <?php
if ($step === 5): ?>
        <!-- ===== STEP 5: PROGRAM SELECTION ===== -->
        <form method="POST" action="../handlers/save-step.php" id="stepForm5" class="form-step-wrapper">
            <input type="hidden" name="step" value="5">
            <div class="form-step active" id="step-5">
                <div class="step-header">
                    <h2>Program Selection</h2>
                    <p>Choose your desired program(s)</p>
                </div>

                <?php
$fc  = $app['first_choice']      ?? '';
                $sc  = $app['second_choice']     ?? '';
                $tc  = $app['third_choice']      ?? '';
                $pc  = $app['program_category']  ?? '';

                // Determine saved track for pre-selection
                $tesda_list = ['DHRMT','HRS','CCS','BK','EIM','SMAW'];
                $ched_list  = ['BSIS','ACT','BSOM','BSAIS','BSCA','BTVTED'];
                // If program_category saved, use it; otherwise infer from first_choice
                if ($pc === 'TESDA') $saved_track = 'TESDA';
                elseif ($pc === 'CHED') $saved_track = 'CHED';
                elseif (in_array($fc, $tesda_list)) $saved_track = 'TESDA';
                elseif (in_array($fc, $ched_list))  $saved_track = 'CHED';
                else $saved_track = '';
                ?>

                <!-- TRACK SELECTOR -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Program Track <span class="required">*</span></label>
                        <div class="radio-group">
                            <div class="radio-item">
                                <input type="radio" id="trackCHED" name="programTrack" value="CHED"
                                       <?php
echo $saved_track === 'CHED' ? 'checked' : ''; ?> required>
                                <label for="trackCHED">
                                    <strong>CHED Programs</strong>
                                    — Bachelor's &amp; Associate Degrees
                                </label>
                            </div>
                            <div class="radio-item">
                                <input type="radio" id="trackTESDA" name="programTrack" value="TESDA"
                                       <?php
echo $saved_track === 'TESDA' ? 'checked' : ''; ?>>
                                <label for="trackTESDA">
                                    <strong>TECHVOC Programs</strong>
                                    — Diplomas &amp; NC Courses (TESDA)
                                </label>
                            </div>
                        </div>
                        <span class="form-helper">
                            This determines which programs are available for your choices.
                        </span>
                        <span class="error-message">Please select a program track</span>
                    </div>
                </div>

                <!-- PROGRAM CHOICES (shown after track selected) -->
                <div id="programChoices" style="<?php
echo $saved_track ? '' : 'display:none;'; ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label>1st Choice Program <span class="required">*</span></label>
                            <select id="firstChoice" name="firstChoice" required>
                                <option value="">Select Program</option>
                                <optgroup label="CHED Programs" class="ched-opts">
                                    <?php foreach ($ched_opts as $p): ?>
                                    <option value="<?php echo htmlspecialchars($p['code']); ?>"
                                        <?php echo $fc === $p['code'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="TECHVOC Programs" class="tesda-opts">
                                    <?php foreach ($tesda_opts as $p): ?>
                                    <option value="<?php echo htmlspecialchars($p['code']); ?>"
                                        <?php echo $fc === $p['code'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                            <span class="error-message">Please select your 1st choice program</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>2nd Choice Program</label>
                            <select id="secondChoice" name="secondChoice" required>
                                <option value="">Select Program</option>
                                <optgroup label="CHED Programs" class="ched-opts">
                                    <?php foreach ($ched_opts as $p): ?>
                                    <option value="<?php echo htmlspecialchars($p['code']); ?>"
                                        <?php echo $sc === $p['code'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="TECHVOC Programs" class="tesda-opts">
                                    <?php foreach ($tesda_opts as $p): ?>
                                    <option value="<?php echo htmlspecialchars($p['code']); ?>"
                                        <?php echo $sc === $p['code'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                            <span class="form-helper">In case your 1st choice is not available</span>
                        </div>
                    </div>

                    <!-- 3rd Choice: CHED → shows TESDA options | TESDA → hidden -->
                    <div class="form-row" id="thirdChoiceRow">
                        <div class="form-group">
                            <label>3rd Choice Program <span id="thirdChoiceOptLabel" style="font-weight:400; color:#666;">(Optional)</span></label>
                            <select id="thirdChoice" name="thirdChoice">
                                <option value="">Select Program (Optional)</option>
                                <optgroup label="TECHVOC Programs" class="third-tesda-opts">
                                    <?php foreach ($tesda_opts as $p): ?>
                                    <option value="<?php echo htmlspecialchars($p['code']); ?>"
                                        <?php echo $tc === $p['code'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                            <span class="form-helper" id="thirdChoiceHelper">
                                CHED applicants may choose a TECHVOC program as last fallback
                            </span>
                        </div>
                    </div>

                </div><!-- end #programChoices -->

                <div class="section-divider">
                    <h3>Special Categories (If applicable)</h3>
                    <p>Check all that apply to you</p>
                </div>

                <div class="checkbox-group">
                    <div class="checkbox-item">
                        <input type="checkbox" id="scholarship" name="scholarship" value="yes" <?php
echo !empty($app['scholarship']) ? 'checked' : ''; ?>>
                        <label for="scholarship">I am applying for a scholarship</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="pwd" name="pwd" value="yes" <?php
echo !empty($app['pwd']) ? 'checked' : ''; ?>>
                        <label for="pwd">I am a Person with Disability (PWD)</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="indigenous" name="indigenous" value="yes" <?php
echo !empty($app['indigenous']) ? 'checked' : ''; ?>>
                        <label for="indigenous">I am a member of an Indigenous Community</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="fourPs" name="fourPs" value="yes" <?php
echo !empty($app['four_ps']) ? 'checked' : ''; ?>>
                        <label for="fourPs">I am a 4Ps (Pantawid Pamilyang Pilipino Program) Beneficiary</label>
                    </div>
                </div>

                <div id="scholarshipDetails" style="<?php
echo !empty($app['scholarship']) ? '' : 'display: none;'; ?> margin-top: 1.5rem;">
                    <div class="form-group">
                        <label>Type of Scholarship</label>
                        <select id="scholarshipType" name="scholarshipType">
                            <option value="">Select</option>
                            <?php
$scht = $app['scholarship_type'] ?? ''; ?>
                            <option value="Government" <?php
echo $scht === 'Government' ? 'selected' : ''; ?>>Government Scholarship</option>
                            <option value="Academic"   <?php
echo $scht === 'Academic'   ? 'selected' : ''; ?>>Academic Scholarship</option>
                            <option value="Athletic"   <?php
echo $scht === 'Athletic'   ? 'selected' : ''; ?>>Athletic Scholarship</option>
                            <option value="Other"      <?php
echo $scht === 'Other'      ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div id="pwdDetails" style="<?php
echo !empty($app['pwd']) ? '' : 'display: none;'; ?> margin-top: 1.5rem;">
                    <div class="form-group">
                        <label>Please specify your disability</label>
                        <textarea id="pwdSpecify" name="pwdSpecify" rows="2" placeholder="Brief description of disability"><?php
echo htmlspecialchars($app['pwd_specify'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="form-navigation">
                    <a href="application-form.php?step=4" class="btn btn-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
                        Previous
                    </a>
                    <button type="submit" name="action" value="save" class="btn btn-save">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
                        Save Draft
                    </button>
                    <button type="submit" name="action" value="next" class="btn btn-primary">
                        Next
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                    </button>
                </div>
            </div>
        </form>
        <?php
endif; ?>

        <?php
if ($step === 6): ?>
        <!-- ===== STEP 6: DOCUMENT UPLOAD ===== -->
        <?php
$at6 = $app['applicant_type'] ?? 'Freshmen'; ?>
        <form method="POST" action="<?php
echo $is_reupload_mode ? 'resubmit-documents.php' : '../handlers/save-step.php'; ?>" id="stepForm6" class="form-step-wrapper" enctype="multipart/form-data">
            <input type="hidden" name="step" value="6">
            <div class="form-step active" id="step-6">
                <div class="step-header">
                    <h2><?php
echo $is_reupload_mode ? 'Re-upload Documents' : 'Document Upload'; ?></h2>
                    <p><?php
echo $is_reupload_mode
                        ? 'Upload corrected documents. You may re-upload only the files that need fixing — existing uploads will be kept.'
                        : 'Upload clear scanned copies (JPG, PNG, or PDF)'; ?></p>
                </div>

                <?php
if ($is_reupload_mode && !empty($app['rejection_reason'])): ?>
                <div style="background:#fff0f0; border:2px solid #dc3545; border-radius:8px; padding:1.25rem; margin-bottom:1.5rem;">
                    <strong style="color:#dc3545;">⚠ Rejection Reason:</strong>
                    <p style="margin-top:0.5rem; color:#721c24; font-size:0.9rem;"><?php
echo htmlspecialchars($app['rejection_reason']); ?></p>
                </div>
                <?php
endif; ?>

                <div class="form-group">
                    <label>2x2 ID Photo (White Background) <span class="required">*</span></label>
                    <div class="file-upload-wrapper">
                        <input type="file" id="idPhoto" name="idPhoto" class="file-upload-input" accept="image/*" <?php
echo !$is_reupload_mode ? 'required' : ''; ?>>
                        <label for="idPhoto" class="file-upload-label">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                            <div class="file-upload-text"><p>Click to upload 2x2 ID Photo</p><span>JPG, PNG (Max 5MB)</span></div>
                        </label>
                        <div class="file-preview" id="idPhotoPreview">
                            <div class="file-info">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
                                <div class="file-details"><p id="idPhotoName"></p><span id="idPhotoSize"></span></div>
                            </div>
                            <button type="button" class="file-remove" onclick="removeFile('idPhoto')">✕</button>
                        </div>
                    </div>
                    <?php
if (!empty($app['id_photo_path'])): ?>
                    <p class="file-already-uploaded">Already uploaded: <strong><?php
echo htmlspecialchars(basename($app['id_photo_path'])); ?></strong></p>
                    <?php
endif; ?>
                    <span class="error-message">2x2 ID photo is required</span>
                </div>

                <div class="form-group">
                    <label id="gradesLabel"><?php
echo $at6 === 'Transferee' ? 'Latest Grade Report (optional, if available)' : 'Form 138 / Report Card (Grade 12) <span class="required">*</span>'; ?></label>
                    <div class="file-upload-wrapper">
                        <input type="file" id="grades" name="grades" class="file-upload-input" accept=".pdf,image/*" <?php
echo $at6 === 'Freshmen' ? 'required' : ''; ?>>
                        <label for="grades" class="file-upload-label">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                            <div class="file-upload-text"><p>Click to upload Report Card</p><span>PDF, JPG, PNG (Max 10MB)</span></div>
                        </label>
                        <div class="file-preview" id="gradesPreview">
                            <div class="file-info">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
                                <div class="file-details"><p id="gradesName"></p><span id="gradesSize"></span></div>
                            </div>
                            <button type="button" class="file-remove" onclick="removeFile('grades')">✕</button>
                        </div>
                    </div>
                    <?php
if (!empty($app['grades_path'])): ?>
                    <p class="file-already-uploaded">Already uploaded: <strong><?php
echo htmlspecialchars(basename($app['grades_path'])); ?></strong></p>
                    <?php
endif; ?>
                    <span class="error-message">Report card is required</span>
                </div>

                <div class="form-group">
                    <label>PSA Certificate of Live Birth <span class="required">*</span></label>
                    <div class="file-upload-wrapper">
                        <input type="file" id="birthCert" name="birthCert" class="file-upload-input" accept=".pdf,image/*" <?php
echo !$is_reupload_mode ? 'required' : ''; ?>>
                        <label for="birthCert" class="file-upload-label">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                            <div class="file-upload-text"><p>Click to upload PSA Birth Certificate</p><span>PDF, JPG, PNG (Max 10MB)</span></div>
                        </label>
                        <div class="file-preview" id="birthCertPreview">
                            <div class="file-info">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
                                <div class="file-details"><p id="birthCertName"></p><span id="birthCertSize"></span></div>
                            </div>
                            <button type="button" class="file-remove" onclick="removeFile('birthCert')">✕</button>
                        </div>
                    </div>
                    <?php
if (!empty($app['birth_cert_path'])): ?>
                    <p class="file-already-uploaded">Already uploaded: <strong><?php
echo htmlspecialchars(basename($app['birth_cert_path'])); ?></strong></p>
                    <?php
endif; ?>
                    <span class="error-message">PSA birth certificate is required</span>
                </div>

                <div id="transfereeDocuments" style="<?php
echo $at6 === 'Transferee' ? '' : 'display: none;'; ?>">
                    <div class="section-divider"><h3>Additional Documents for Transferees</h3></div>

                    <div class="form-group">
                        <label>Transfer Credential / Honorable Dismissal <span class="required">*</span></label>
                        <div class="file-upload-wrapper">
                            <input type="file" id="transferCred" name="transferCred" class="file-upload-input" accept=".pdf,image/*" <?php
echo $at6 === 'Transferee' ? 'required' : ''; ?>>
                            <label for="transferCred" class="file-upload-label">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                                <div class="file-upload-text"><p>Click to upload Transfer Credential</p><span>PDF, JPG, PNG (Max 10MB)</span></div>
                            </label>
                            <div class="file-preview" id="transferCredPreview">
                                <div class="file-info">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
                                    <div class="file-details"><p id="transferCredName"></p><span id="transferCredSize"></span></div>
                                </div>
                                <button type="button" class="file-remove" onclick="removeFile('transferCred')">✕</button>
                            </div>
                        </div>
                        <?php
if (!empty($app['transfer_cred_path'])): ?>
                        <p class="file-already-uploaded">Already uploaded: <strong><?php
echo htmlspecialchars(basename($app['transfer_cred_path'])); ?></strong></p>
                        <?php
endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Transcript of Records <span class="required">*</span></label>
                        <div class="file-upload-wrapper">
                            <input type="file" id="tor" name="tor" class="file-upload-input" accept=".pdf,image/*" <?php
echo $at6 === 'Transferee' ? 'required' : ''; ?>>
                            <label for="tor" class="file-upload-label">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                                <div class="file-upload-text"><p>Click to upload Transcript of Records</p><span>PDF, JPG, PNG (Max 10MB)</span></div>
                            </label>
                            <div class="file-preview" id="torPreview">
                                <div class="file-info">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
                                    <div class="file-details"><p id="torName"></p><span id="torSize"></span></div>
                                </div>
                                <button type="button" class="file-remove" onclick="removeFile('tor')">✕</button>
                            </div>
                        </div>
                        <?php
if (!empty($app['tor_path'])): ?>
                        <p class="file-already-uploaded">Already uploaded: <strong><?php
echo htmlspecialchars(basename($app['tor_path'])); ?></strong></p>
                        <?php
endif; ?>
                    </div>
                </div>

                <div style="background-color: #fff9e6; border: 2px solid var(--bpc-gold); border-radius: 8px; padding: 1.5rem; margin-top: 2rem;">
                    <p style="margin: 0; font-size: 0.9rem; color: var(--text-dark);">
                        <strong>⚠ Important:</strong> Ensure all documents are clear and readable. File sizes should not exceed 10MB each. Accepted formats: JPG, PNG, PDF.
                    </p>
                </div>

                <div class="form-navigation">
                    <?php
if ($is_reupload_mode): ?>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
                            Back to Dashboard
                        </a>
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Re-submit your documents for review?');">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                            Re-submit Documents
                        </button>
                    <?php
else: ?>
                        <a href="application-form.php?step=5" class="btn btn-secondary">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
                            Previous
                        </a>
                        <button type="submit" name="action" value="save" class="btn btn-save">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
                            Save Draft
                        </button>
                        <button type="submit" name="action" value="next" class="btn btn-primary">
                            Next
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                        </button>
                    <?php
endif; ?>
                </div>
            </div>
        </form>
        <?php
endif; ?>

        <?php
if ($step === 7): ?>
        <!-- ===== STEP 7: REVIEW & SUBMIT ===== -->
        <form method="POST" action="../handlers/submit-application.php" id="stepForm7" class="form-step-wrapper">
            <div class="form-step active" id="step-7">
                <div class="step-header">
                    <h2>Review & Submit</h2>
                    <p>Please review all information before submitting</p>
                </div>

                <div id="reviewSummary" class="review-summary">
                    <?php
$a = $app;
                    $apt = $a['applicant_type'] ?? '';
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
                    $fc_label = $prog_labels[$a['first_choice']  ?? ''] ?? ($a['first_choice']  ?? '');
                    $sc_label = $prog_labels[$a['second_choice'] ?? ''] ?? ($a['second_choice'] ?? '') ?: 'None';
                    $tc_label = $prog_labels[$a['third_choice']  ?? ''] ?? ($a['third_choice']  ?? '') ?: 'None';
                    ?>

                    <!-- Personal Information -->
                    <div class="review-section">
                        <div class="review-header">
                            <h3>Personal Information</h3>
                            <?php
if (!$is_submitted): ?><a href="application-form.php?step=1" class="btn-edit">Edit</a><?php
endif; ?>
                        </div>
                        <div class="review-grid">
                            <div class="review-item"><div class="review-label">Full Name</div><div class="review-value"><?php
echo htmlspecialchars(trim(($a['first_name']??'').' '.($a['middle_name']??'').' '.($a['last_name']??'').' '.($a['suffix']??''))); ?></div></div>
                            <div class="review-item"><div class="review-label">PSA Registry No.</div><div class="review-value"><?php
echo htmlspecialchars($a['psa_registry_no']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">Sex</div><div class="review-value"><?php
echo htmlspecialchars($a['sex']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">Date of Birth</div><div class="review-value"><?php
echo htmlspecialchars($a['date_of_birth']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">Age</div><div class="review-value"><?php
echo htmlspecialchars($a['age']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">Place of Birth</div><div class="review-value"><?php
echo htmlspecialchars(($a['birth_city']??'').', '.($a['birth_province']??'')); ?></div></div>
                            <div class="review-item"><div class="review-label">Civil Status</div><div class="review-value"><?php
echo htmlspecialchars($a['civil_status']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">Citizenship</div><div class="review-value"><?php
echo htmlspecialchars($a['citizenship']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">Religion</div><div class="review-value"><?php
echo htmlspecialchars($a['religion']??''); ?></div></div>
                        </div>
                    </div>

                    <!-- Contact & Address -->
                    <div class="review-section">
                        <div class="review-header">
                            <h3>Contact & Address</h3>
                            <?php
if (!$is_submitted): ?><a href="application-form.php?step=2" class="btn-edit">Edit</a><?php
endif; ?>
                        </div>
                        <div class="review-grid">
                            <div class="review-item"><div class="review-label">Mobile</div><div class="review-value"><?php
echo htmlspecialchars($a['mobile_number']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">Email</div><div class="review-value"><?php
echo htmlspecialchars($a['email']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">Facebook</div><div class="review-value"><?php
echo htmlspecialchars($a['facebook']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">Current Address</div><div class="review-value"><?php
echo htmlspecialchars(trim(($a['current_house_street']??'').', '.($a['current_barangay']??'').', '.($a['current_city']??'').', '.($a['current_province']??'').' '.($a['current_zip_code']??''))); ?></div></div>
                            <div class="review-item"><div class="review-label">Permanent Address</div><div class="review-value"><?php
echo htmlspecialchars(trim(($a['permanent_house_street']??'').', '.($a['permanent_barangay']??'').', '.($a['permanent_city']??'').', '.($a['permanent_province']??'').' '.($a['permanent_zip_code']??''))); ?></div></div>
                        </div>
                    </div>

                    <!-- Family Background -->
                    <div class="review-section">
                        <div class="review-header">
                            <h3>Family Background</h3>
                            <?php
if (!$is_submitted): ?><a href="application-form.php?step=3" class="btn-edit">Edit</a><?php
endif; ?>
                        </div>
                        <div class="review-grid">
                            <div class="review-item"><div class="review-label">Father</div><div class="review-value"><?php
echo htmlspecialchars(trim(($a['father_first_name']??'').' '.($a['father_middle_name']??'').' '.($a['father_last_name']??''))); ?></div></div>
                            <div class="review-item"><div class="review-label">Father Occupation</div><div class="review-value"><?php
echo htmlspecialchars($a['father_occupation']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">Father Education</div><div class="review-value"><?php
echo htmlspecialchars($a['father_education']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">Mother</div><div class="review-value"><?php
echo htmlspecialchars(trim(($a['mother_first_name']??'').' '.($a['mother_middle_name']??'').' '.($a['mother_last_name']??''))); ?></div></div>
                            <div class="review-item"><div class="review-label">Mother Occupation</div><div class="review-value"><?php
echo htmlspecialchars($a['mother_occupation']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">Mother Education</div><div class="review-value"><?php
echo htmlspecialchars($a['mother_education']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">Guardian</div><div class="review-value"><?php
echo htmlspecialchars($a['guardian_name']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">Guardian Relationship</div><div class="review-value"><?php
echo htmlspecialchars($a['guardian_relationship']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">Guardian Contact</div><div class="review-value"><?php
echo htmlspecialchars($a['guardian_contact']??''); ?></div></div>
                        </div>
                    </div>

                    <!-- Educational Background -->
                    <div class="review-section">
                        <div class="review-header">
                            <h3>Educational Background</h3>
                            <?php
if (!$is_submitted): ?><a href="application-form.php?step=4" class="btn-edit">Edit</a><?php
endif; ?>
                        </div>
                        <div class="review-grid">
                            <div class="review-item"><div class="review-label">Applicant Type</div><div class="review-value"><?php
echo htmlspecialchars($apt); ?></div></div>
                            <?php
if ($apt === 'Freshmen'): ?>
                            <div class="review-item"><div class="review-label">SHS Name</div><div class="review-value"><?php
echo htmlspecialchars($a['shs_name']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">SHS Address</div><div class="review-value"><?php
echo htmlspecialchars($a['shs_address']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">Type of School</div><div class="review-value"><?php
echo htmlspecialchars($a['school_type']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">LRN</div><div class="review-value"><?php
echo htmlspecialchars($a['lrn']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">Year Graduated / Expected</div><div class="review-value"><?php
echo htmlspecialchars($a['shs_year_grad']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">SHS Strand</div><div class="review-value"><?php
echo htmlspecialchars($a['shs_strand']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">GWA</div><div class="review-value"><?php
echo htmlspecialchars($a['shs_gwa']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">Awards</div><div class="review-value"><?php
echo htmlspecialchars($a['awards']??''); ?></div></div>
                            <?php
else: ?>
                            <div class="review-item"><div class="review-label">Previous School</div><div class="review-value"><?php
echo htmlspecialchars($a['prev_school_name']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">Previous School Address</div><div class="review-value"><?php
echo htmlspecialchars($a['prev_school_address']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">Previous Course</div><div class="review-value"><?php
echo htmlspecialchars($a['prev_course']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">Previous Year Level</div><div class="review-value"><?php
echo htmlspecialchars($a['prev_year_level']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">Reason for Transfer</div><div class="review-value"><?php
echo htmlspecialchars($a['transfer_reason']??''); ?></div></div>
                            <?php
endif; ?>
                        </div>
                    </div>

                    <!-- Program Selection -->
                    <div class="review-section">
                        <div class="review-header">
                            <h3>Program Selection</h3>
                            <?php
if (!$is_submitted): ?><a href="application-form.php?step=5" class="btn-edit">Edit</a><?php
endif; ?>
                        </div>
                        <div class="review-grid">
                            <div class="review-item"><div class="review-label">1st Choice</div><div class="review-value"><?php
echo htmlspecialchars($fc_label); ?></div></div>
                            <div class="review-item"><div class="review-label">2nd Choice</div><div class="review-value"><?php
echo htmlspecialchars($sc_label); ?></div></div>
                            <div class="review-item"><div class="review-label">3rd Choice</div><div class="review-value"><?php
echo htmlspecialchars($tc_label); ?></div></div>
                            <div class="review-item"><div class="review-label">Program Category</div><div class="review-value"><?php
echo htmlspecialchars($a['program_category']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">Scholarship Applicant</div><div class="review-value"><?php
echo !empty($a['scholarship']) ? 'Yes' : 'No'; ?></div></div>
                            <div class="review-item"><div class="review-label">Scholarship Type</div><div class="review-value"><?php
echo htmlspecialchars($a['scholarship_type']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">PWD</div><div class="review-value"><?php
echo !empty($a['pwd']) ? 'Yes' : 'No'; ?></div></div>
                            <div class="review-item"><div class="review-label">PWD Details</div><div class="review-value"><?php
echo htmlspecialchars($a['pwd_specify']??''); ?></div></div>
                            <div class="review-item"><div class="review-label">Indigenous Member</div><div class="review-value"><?php
echo !empty($a['indigenous']) ? 'Yes' : 'No'; ?></div></div>
                            <div class="review-item"><div class="review-label">4Ps Beneficiary</div><div class="review-value"><?php
echo !empty($a['four_ps']) ? 'Yes' : 'No'; ?></div></div>
                        </div>
                    </div>

                    <!-- Documents -->
                    <div class="review-section">
                        <div class="review-header">
                            <h3>Documents</h3>
                            <?php
if (!$is_submitted): ?><a href="application-form.php?step=6" class="btn-edit">Edit</a><?php
endif; ?>
                        </div>
                        <div class="review-grid">
                            <div class="review-item"><div class="review-label">2x2 ID Photo</div><div class="review-value"><?php
echo !empty($a['id_photo_path']) ? basename($a['id_photo_path']) : 'Not uploaded'; ?></div></div>
                            <div class="review-item"><div class="review-label">Report Card</div><div class="review-value"><?php
echo !empty($a['grades_path']) ? basename($a['grades_path']) : 'Not uploaded'; ?></div></div>
                            <div class="review-item"><div class="review-label">PSA Birth Cert</div><div class="review-value"><?php
echo !empty($a['birth_cert_path']) ? basename($a['birth_cert_path']) : 'Not uploaded'; ?></div></div>
                            <?php
if ($apt === 'Transferee'): ?>
                            <div class="review-item"><div class="review-label">Transfer Credential</div><div class="review-value"><?php
echo !empty($a['transfer_cred_path']) ? basename($a['transfer_cred_path']) : 'Not uploaded'; ?></div></div>
                            <div class="review-item"><div class="review-label">TOR</div><div class="review-value"><?php
echo !empty($a['tor_path']) ? basename($a['tor_path']) : 'Not uploaded'; ?></div></div>
                            <?php
endif; ?>
                        </div>
                    </div>
                </div>

                <div class="consent-section">
                    <h3>Data Privacy & Consent</h3>
                    <div class="consent-item">
                        <div class="checkbox-item">
                            <input type="checkbox" id="certify" name="certify" required>
                            <label for="certify">I certify that all information provided in this application is true, correct, and complete to the best of my knowledge. I understand that any false statement may result in the denial or cancellation of my admission.</label>
                        </div>
                    </div>
                    <div class="consent-item">
                        <div class="checkbox-item">
                            <input type="checkbox" id="dataPrivacy" name="dataPrivacy" required>
                            <label for="dataPrivacy">I consent to the collection, use, and processing of my personal data by Bulacan Polytechnic College in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173) for admission and enrollment purposes.</label>
                        </div>
                    </div>
                </div>

                <div class="form-navigation">
                    <?php
if (!$is_submitted): ?>
                    <a href="application-form.php?step=6" class="btn btn-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
                        Previous
                    </a>
                    <button type="submit" class="btn btn-primary" id="submitBtn" onclick="return confirm('Are you sure you want to submit your application? This cannot be undone.');">
                        Submit Application
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
                    </button>
                    <?php
endif; ?>
                </div>
            </div>
        </form>
        <?php
endif; ?>

        <div class="success-message" id="successMessage" style="display:none;">
            <div class="success-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
            </div>
            <h2>Application Submitted Successfully!</h2>
            <p>Thank you for applying to Bulacan Polytechnic College.</p>
            <p>Your application reference number is:</p>
            <div class="reference-number" id="referenceNumber">BPC-2024-XXXXX</div>
            <button type="button" class="btn btn-primary" onclick="window.location.href='dashboard.php'" style="margin-top: 2rem;">
                Return to Dashboard
            </button>
        </div>

    </div>

    <script>
        // ── STEP 5: Program Track Filter ───────────────────────────
        (function () {
            const trackRadios   = document.querySelectorAll('input[name="programTrack"]');
            if (!trackRadios.length) return; // not on step 5

            const choicesDiv    = document.getElementById('programChoices');
            const firstChoice   = document.getElementById('firstChoice');
            const secondChoice  = document.getElementById('secondChoice');
            const thirdChoice   = document.getElementById('thirdChoice');
            const thirdRow      = document.getElementById('thirdChoiceRow');
            const thirdHelper   = document.getElementById('thirdChoiceHelper');
            const choiceSelects = [firstChoice, secondChoice, thirdChoice].filter(Boolean);

            function normalizeChoiceConflicts() {
                if (!firstChoice || !secondChoice || !thirdChoice) return;
                // Keep higher-priority choices and clear duplicates in lower-priority fields.
                if (firstChoice.value && secondChoice.value === firstChoice.value) {
                    secondChoice.value = '';
                }
                if (firstChoice.value && thirdChoice.value === firstChoice.value) {
                    thirdChoice.value = '';
                }
                if (secondChoice.value && thirdChoice.value === secondChoice.value) {
                    thirdChoice.value = '';
                }
            }

            function updateChoiceAvailability() {
                const selectedById = new Map();
                choiceSelects.forEach(function(sel) {
                    selectedById.set(sel.id, sel.value);
                });

                choiceSelects.forEach(function(sel) {
                    const selectedElsewhere = new Set(
                        choiceSelects
                            .filter(other => other.id !== sel.id)
                            .map(other => selectedById.get(other.id))
                            .filter(Boolean)
                    );

                    sel.querySelectorAll('option').forEach(function(opt) {
                        if (!opt.value) {
                            opt.disabled = false;
                            return;
                        }
                        // Keep currently selected value enabled in its own select.
                        opt.disabled = selectedElsewhere.has(opt.value) && opt.value !== sel.value;
                    });
                });
            }

            function filterByTrack(track) {
                if (!choicesDiv) return;
                choicesDiv.style.display = 'block';

                // Show/hide optgroups in 1st and 2nd choice
                [firstChoice, secondChoice].forEach(function(sel) {
                    if (!sel) return;
                    sel.querySelectorAll('.ched-opts').forEach(function(og) {
                        og.style.display = track === 'CHED' ? '' : 'none';
                    });
                    sel.querySelectorAll('.tesda-opts').forEach(function(og) {
                        og.style.display = track === 'TESDA' ? '' : 'none';
                    });
                    // Reset if currently selected value is from the wrong track
                    const val = sel.value;
                    const chedVals  = ['BSIS','ACT','BSOM','BSAIS','BSCA','BTVTED'];
                    const tesdaVals = ['DHRMT','HRS','CCS','BK','EIM','SMAW'];
                    if (track === 'CHED'  && tesdaVals.includes(val)) sel.value = '';
                    if (track === 'TESDA' && chedVals.includes(val))  sel.value = '';
                });

                // 3rd choice logic
                if (track === 'CHED') {
                    // CHED applicants: 3rd choice = TESDA fallback (show it)
                    thirdRow.style.display = '';
                    thirdHelper.textContent = 'CHED applicants may choose a TECHVOC program as last fallback';
                } else {
                    // TESDA applicants: no 3rd choice needed
                    thirdRow.style.display = 'none';
                    if (thirdChoice) thirdChoice.value = '';
                }

                normalizeChoiceConflicts();
                updateChoiceAvailability();
            }

            // Attach change listener
            trackRadios.forEach(function(radio) {
                radio.addEventListener('change', function() {
                    filterByTrack(this.value);
                });
            });

            choiceSelects.forEach(function(sel) {
                sel.addEventListener('change', function() {
                    normalizeChoiceConflicts();
                    updateChoiceAvailability();
                });
            });

            // Apply on page load if a track was already saved
            const checkedRadio = document.querySelector('input[name="programTrack"]:checked');
            if (checkedRadio) {
                filterByTrack(checkedRadio.value);
            } else {
                // No track selected yet: hide program choices
                if (choicesDiv) choicesDiv.style.display = 'none';
            }
            normalizeChoiceConflicts();
            updateChoiceAvailability();
        })();

        // ── APP CONTEXT ────────────────────────────────────────────
        window.APP_CONTEXT = {
            isSubmitted: <?php
echo $is_submitted ? 'true' : 'false'; ?>,
            isReuploadMode: <?php
echo $is_reupload_mode ? 'true' : 'false'; ?>,
            status: <?php
echo json_encode($status); ?>,
            referenceNumber: <?php
echo json_encode($reference_number); ?>
        };
    </script>
    <script src="../../form-script.js"></script>
</body>
</html>