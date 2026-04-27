<?php
session_start();
require_once __DIR__ . '/config/paths.php';
require_once CONFIG_PATH . '/db.php';

$authError = $_SESSION['error'] ?? null;
unset($_SESSION['error']);
// Which tab had the error (register redirects with ?tab=register)
$authErrorTab = (isset($_GET['tab']) && $_GET['tab'] === 'register') ? 'register' : 'login';
$isLoggedIn = isset($_SESSION['user_id']);

// Application period — uses date range if set, manual flag as fallback
$applications_open      = is_application_period_open($conn);
$application_start_date = get_setting('application_start_date', '');
$application_end_date   = get_setting('application_end_date', '');
$admission_period = (!empty($application_start_date) && !empty($application_end_date))
    ? date('F j, Y', strtotime($application_start_date)) . ' – ' . date('F j, Y', strtotime($application_end_date))
    : get_setting('admission_period', '');
$application_deadline = !empty($application_end_date)
    ? date('F j, Y', strtotime($application_end_date))
    : get_setting('application_deadline', '');
 
// Load programs from DB
require_once __DIR__ . '/config/programs.php';
$all_programs  = get_all_programs($conn);
$ched_programs  = array_filter($all_programs, fn($p) => $p['category'] === 'CHED');
$tesda_programs = array_filter($all_programs, fn($p) => $p['category'] === 'TESDA');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BPC iEnroll - Bulacan Polytechnic College Admission Portal</title>
    <link rel="stylesheet" href="index.css?v=6.0">
    <link rel="preload" as="image" href="assets/475467753_122106687440730024_5972239098270303029_n.jpg">
    
</head>
<body>

    <!-- ============================================
         NAVBAR (unchanged, but we'll add scroll class)
    ============================================ -->
    <nav class="navbar" id="mainNavbar">
        <div class="container">
            <div class="navbar-content">
                <a href="index.php" class="navbar-brand">
                    <img src="assets/cropped-cropped-cropped-cropped-cropped-bpclogo-1-1-1-150x150.png" alt="BPC Logo" class="logo-img">
                    <div class="brand-text">
                        <h1>Bulacan Polytechnic College</h1>
                        <p>Admission Portal</p>
                    </div>
                </a>
                
                <?php if ($isLoggedIn): ?>
                <a href="app/student/dashboard.php" class="nav-link">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                    </svg>
                    Go to Dashboard
                </a>
                <?php else: ?>
                <button class="nav-link" onclick="openModal()">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                    Login / Register
                </button>
                <?php endif; ?>

                <button class="mobile-menu-toggle" onclick="toggleMobileMenu()" aria-label="Toggle menu">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>
                    </svg>
                </button>
            </div>
        </div>
    </nav>

    <!-- Mobile Menu (unchanged) -->
    <div class="mobile-overlay" onclick="closeMobileMenu()"></div>
    <div class="mobile-menu">
        <div class="mobile-menu-header">
            <h3 style="font-size: 1.1rem; color: var(--bpc-green); margin: 0;">Menu</h3>
            <button class="mobile-menu-close" onclick="closeMobileMenu()" aria-label="Close menu">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                </svg>
            </button>
        </div>
        <div class="mobile-menu-items">
            <a href="#requirements" onclick="closeMobileMenu()">Requirements</a>
            <a href="#how-it-works" onclick="closeMobileMenu()">How It Works</a>
            <a href="#programs" onclick="closeMobileMenu()">Programs</a>
            <a href="#faqs" onclick="closeMobileMenu()">FAQs</a>
            <a href="#" onclick="closeMobileMenu()">Track Application</a>
            <?php if ($isLoggedIn): ?>
            <a href="app/student/dashboard.php" style="background-color: var(--bpc-green); color: white; text-align: center;">Go to Dashboard</a>
            <?php else: ?>
            <a href="#" onclick="closeMobileMenu(); openModal();" style="background-color: var(--bpc-green); color: white; text-align: center;">Login / Register</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Back to Top Button -->
    <button class="back-to-top" onclick="scrollToTop()" aria-label="Back to top">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <path d="M7.41 15.41L12 10.83l4.59 4.58L18 14l-6-6-6 6z"/>
        </svg>
    </button>

    <!-- Loading Spinner (global) -->
    <div class="loading-spinner">
        <div class="spinner"></div>
    </div>
    <!-- Overlay for form submissions -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <!-- ============================================
         HERO SECTION (unchanged)
    ============================================ -->
    <section class="hero-section">
        <div class="hero-background"></div>
        <div class="hero-overlay" aria-hidden="true"></div>
        
        <div class="container">
            <div class="hero-content">
                <h1 class="hero-title">BPC iEnroll</h1>
                <div class="hero-accent"></div>
                <p class="hero-subtitle">
                    Welcome to Bulacan Polytechnic College's Online Admission System.
                    Start your journey to quality education today.
                </p>
                <div class="hero-cta-row">
                    <?php if ($isLoggedIn): ?>
                    <a href="app/student/dashboard.php" class="btn-primary btn-hero-primary">Go to Dashboard</a>
                    <?php else: ?>
                    <?php if ($applications_open): ?>
                        <button class="btn-primary btn-hero-primary" onclick="openModal()">
                            Start My Application
                        </button>
                    <?php else: ?>
                        <p style="margin-top:1rem; color:#ffcdd2; font-weight:600;">
                            Admissions are currently closed. You can still log in to view your application status.
                        </p>
                        <button class="btn-primary btn-hero-primary" onclick="openModal()" style="margin-top:0.75rem;">
                            Login to Check Status
                        </button>
                    <?php endif; ?>
                    <?php endif; ?>
                    <?php if (!$isLoggedIn && $applications_open): ?>
                    <a href="#how-it-works" class="btn-hero-secondary">Learn More &darr;</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Admission Deadline Banner (unchanged) -->
    <section class="deadline-banner" aria-label="Admission period and deadline">
        <div class="container">
            <div class="deadline-banner-inner">
                <div class="deadline-text">
                    <?php if ($admission_period || $application_deadline): ?>
                    <?php if ($admission_period): ?><strong>Admission period:</strong> <?php echo htmlspecialchars($admission_period); ?><?php endif; ?>
                    <?php if ($admission_period && $application_deadline): ?> &nbsp;|&nbsp; <?php endif; ?>
                    <?php if ($application_deadline): ?><strong>Application deadline:</strong> <?php echo htmlspecialchars($application_deadline); ?><?php endif; ?>
                    <?php else: ?>
                    <strong>Admission period and deadline</strong> are set per semester. Contact Admissions at (044) 903-5634 or registrars@bpc.edu.ph for current dates.
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- REQUIREMENTS SECTION (unchanged) -->
    <section class="requirements-section" id="requirements">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Admission Requirements</h2>
                <div class="section-title-accent"></div>
                <p class="section-subtitle">
                    Prepare and upload clear scanned copies of the following documents
                </p>
            </div>

            <div class="requirements-two-col">
                <div class="req-col">
                    <h3 class="req-col-title">Incoming Freshmen</h3>
                    <ul class="req-list">
                        <li>2×2 ID Photo (white background)</li>
                        <li>Grade 12 Report Card (at least 2nd grading)</li>
                        <li>PSA Birth Certificate</li>
                    </ul>
                </div>
                <div class="req-col">
                    <h3 class="req-col-title">Transferees</h3>
                    <ul class="req-list">
                        <li>2×2 ID Photo (white background)</li>
                        <li>Certificate of Grades or TOR</li>
                        <li>Transfer Credential / Honorable Dismissal</li>
                        <li>PSA Birth Certificate</li>
                    </ul>
                </div>
            </div>
            <div class="requirements-note">
                <p><strong>Note:</strong> Upload clear scanned copies. Incomplete or unclear documents may delay processing.</p>
            </div>
        </div>
    </section>

    <!-- HOW IT WORKS SECTION (unchanged) -->
    <section class="how-it-works-section" id="how-it-works">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">How It Works</h2>
                <div class="section-title-accent"></div>
                <p class="section-subtitle">
                    Follow these steps to complete your admission. Choose your track:
                </p>
            </div>

            <div class="track-toggle">
                <button type="button" class="track-btn active" id="track-ched" onclick="switchTrack('ched')">CHED (Degree Programs)</button>
                <button type="button" class="track-btn" id="track-tesda" onclick="switchTrack('tesda')">TESDA (TechVoc)</button>
            </div>

            <!-- CHED timeline -->
            <div class="timeline-grid" id="timeline-ched">
                <div class="timeline-item"><div class="timeline-number">1</div><div class="timeline-content"><h3 class="timeline-title">Submit Application</h3><p class="timeline-description">Form + documents (2x2 ID, grades, PSA birth cert).</p></div></div>
                <div class="timeline-item"><div class="timeline-number">2</div><div class="timeline-content"><h3 class="timeline-title">Document Verification</h3><p class="timeline-description">Admission reviews for completeness and authenticity.</p></div></div>
                <div class="timeline-item"><div class="timeline-number">3</div><div class="timeline-content"><h3 class="timeline-title">Exam Scheduled</h3><p class="timeline-description">Exam date, time, venue via email or SMS.</p></div></div>
                <div class="timeline-item"><div class="timeline-number">4</div><div class="timeline-content"><h3 class="timeline-title">Take Entrance Exam</h3><p class="timeline-description">At BPC; results in 3–5 business days.</p></div></div>
                <div class="timeline-item"><div class="timeline-number">5</div><div class="timeline-content"><h3 class="timeline-title">Interview</h3><p class="timeline-description">If you pass, attend admission interview.</p></div></div>
                <div class="timeline-item"><div class="timeline-number">6</div><div class="timeline-content"><h3 class="timeline-title">Final Decision</h3><p class="timeline-description">Admitted or Rejected; you’ll be notified.</p></div></div>
                <div class="timeline-item"><div class="timeline-number">7</div><div class="timeline-content"><h3 class="timeline-title">Submit Originals & Enroll</h3><p class="timeline-description">Original documents at campus; complete enrollment.</p></div></div>
            </div>

            <!-- TESDA timeline -->
            <div class="timeline-grid timeline-tesda" id="timeline-tesda" style="display:none;">
                <div class="timeline-item"><div class="timeline-number">1</div><div class="timeline-content"><h3 class="timeline-title">Submit Application</h3><p class="timeline-description">Form + documents for your TechVoc program.</p></div></div>
                <div class="timeline-item"><div class="timeline-number">2</div><div class="timeline-content"><h3 class="timeline-title">Document Verification</h3><p class="timeline-description">Admission reviews; no entrance exam for TESDA.</p></div></div>
                <div class="timeline-item"><div class="timeline-number">3</div><div class="timeline-content"><h3 class="timeline-title">Interview Scheduled</h3><p class="timeline-description">Date, time, venue via email or SMS.</p></div></div>
                <div class="timeline-item"><div class="timeline-number">4</div><div class="timeline-content"><h3 class="timeline-title">Attend Interview</h3><p class="timeline-description">Admission interview with the panel.</p></div></div>
                <div class="timeline-item"><div class="timeline-number">5</div><div class="timeline-content"><h3 class="timeline-title">Decision & Enroll</h3><p class="timeline-description">Admitted or Rejected; submit originals and enroll.</p></div></div>
            </div>

            <div class="how-it-works-cta">
            <?php if ($applications_open): ?>
                <button class="btn-primary" onclick="openModal()">Start My Application</button>
            <?php else: ?>
                <button class="btn-primary" onclick="openModal()">Login to Check Status</button>
            <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- PROGRAMS OFFERED SECTION (with search and improved accordion) -->
    <section class="programs-section" id="programs">
        <div class="container-wide">
            <div class="section-header">
                <h2 class="section-title">Programs Offered</h2>
                <div class="section-title-accent"></div>
            </div>

            <!-- Search input for programs -->
            <div class="programs-search-wrapper">
                <input type="text" id="programSearchInput" class="program-search-input" placeholder="Search programs... e.g., BSIS, HRS, Information Technology">
            </div>

            <?php
                // Group programs for accordion rendering
                $grouped_departments = ['Information Technology Education', 'Hospitality Industry'];
                $dept_groups   = []; 
                $standalone    = []; 
                
                foreach ($all_programs as $p) {
                    if (!empty($p['department']) && in_array($p['department'], $grouped_departments)) {
                        $dept_groups[$p['department']][] = $p;
                    } else {
                        $standalone[] = $p;
                    }
                }
                
                function render_program_card(array $p, bool $use_dept_logo = false): void {
                    $logo_file = !empty($p['department_logo']) ? basename($p['department_logo']) : null;
                    ?>
                    <div class="prog-card">
                        <div class="prog-card-top">
                            <?php if ($logo_file): ?>
                            <div class="prog-card-logo">
                                <img src="uploads/dept_logos/<?php echo htmlspecialchars($logo_file); ?>"
                                    alt="<?php echo htmlspecialchars($p['name']); ?> logo">
                            </div>
                            <?php endif; ?>
                            <div class="prog-card-info">
                                <h4 class="prog-card-name"><?php echo htmlspecialchars($p['name']); ?></h4>
                                <?php if (!empty($p['description'])): ?>
                                <p class="prog-card-desc"><?php echo htmlspecialchars($p['description']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($p['careers_arr']) || !empty($p['alt_jobs_arr'])): ?>
                        <div class="prog-card-bottom">
                            <?php if (!empty($p['careers_arr'])): ?>
                            <div class="prog-card-col">
                                <h5>Careers and Opportunities</h5>
                                <ul>
                                    <?php foreach ($p['careers_arr'] as $c): ?>
                                    <li><?php echo htmlspecialchars($c); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($p['alt_jobs_arr'])): ?>
                            <div class="prog-card-col">
                                <h5>Alternative Jobs</h5>
                                <ul>
                                    <?php foreach ($p['alt_jobs_arr'] as $j): ?>
                                    <li><?php echo htmlspecialchars($j); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php
                }
            ?>
                
            <div class="programs-two-col-layout">
                
                <div class="program-track-column">
                    <h3 class="program-track-heading">CHED Programs</h3>
                    <div class="program-accordion-group" id="ched-programs-group">
                        <?php
                        foreach ([$dept_groups, $standalone] as $source_key => $source):
                            if ($source_key === 0):
                                foreach ($dept_groups as $dept_name => $dept_programs):
                                    if ($dept_programs[0]['category'] !== 'CHED') continue;
                        ?>
                        <div class="program-accordion-item" data-program-name="<?php echo htmlspecialchars(strtolower($dept_name)); ?>">
                            <button type="button" class="program-accordion-head program-accordion-head--group"
                                    onclick="toggleProgramAccordion(this)" aria-expanded="false">
                                <span class="program-accordion-title program-accordion-title--group">
                                    <?php echo htmlspecialchars($dept_name); ?>
                                </span>
                                <svg class="program-accordion-chevron" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
                                </svg>
                            </button>
                            <div class="program-accordion-body">
                                <div class="program-accordion-body-inner">
                                    <?php foreach ($dept_programs as $p): render_program_card($p); endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php
                                endforeach;
                            else:
                                foreach ($standalone as $p):
                                    if ($p['category'] !== 'CHED') continue;
                        ?>
                        <div class="program-accordion-item" data-program-name="<?php echo htmlspecialchars(strtolower($p['name'])); ?>">
                            <button type="button" class="program-accordion-head program-accordion-head--single"
                                    onclick="toggleProgramAccordion(this)" aria-expanded="false">
                                <span class="program-accordion-title program-accordion-title--single">
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </span>
                                <svg class="program-accordion-chevron" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
                                </svg>
                            </button>
                            <div class="program-accordion-body">
                                <div class="program-accordion-body-inner">
                                    <?php render_program_card($p); ?>
                                </div>
                            </div>
                        </div>
                        <?php
                                endforeach;
                            endif;
                        endforeach;
                        ?>
                    </div>
                </div>

                <div class="program-track-column">
                    <h3 class="program-track-heading">TECHVOC Programs</h3>
                    <div class="program-accordion-group" id="tesda-programs-group">
                        <?php
                        foreach ([$dept_groups, $standalone] as $source_key => $source):
                            if ($source_key === 0):
                                foreach ($dept_groups as $dept_name => $dept_programs):
                                    if ($dept_programs[0]['category'] !== 'TESDA') continue;
                        ?>
                        <div class="program-accordion-item" data-program-name="<?php echo htmlspecialchars(strtolower($dept_name)); ?>">
                            <button type="button" class="program-accordion-head program-accordion-head--group"
                                    onclick="toggleProgramAccordion(this)" aria-expanded="false">
                                <span class="program-accordion-title program-accordion-title--group">
                                    <?php echo htmlspecialchars($dept_name); ?>
                                </span>
                                <svg class="program-accordion-chevron" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
                                </svg>
                            </button>
                            <div class="program-accordion-body">
                                <div class="program-accordion-body-inner">
                                    <?php foreach ($dept_programs as $p): render_program_card($p); endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php
                                endforeach;
                            else:
                                foreach ($standalone as $p):
                                    if ($p['category'] !== 'TESDA') continue;
                        ?>
                        <div class="program-accordion-item" data-program-name="<?php echo htmlspecialchars(strtolower($p['name'])); ?>">
                            <button type="button" class="program-accordion-head program-accordion-head--single"
                                    onclick="toggleProgramAccordion(this)" aria-expanded="false">
                                <span class="program-accordion-title program-accordion-title--single">
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </span>
                                <svg class="program-accordion-chevron" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
                                </svg>
                            </button>
                            <div class="program-accordion-body">
                                <div class="program-accordion-body-inner">
                                    <?php render_program_card($p); ?>
                                </div>
                            </div>
                        </div>
                        <?php
                                endforeach;
                            endif;
                        endforeach;
                        ?>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- FAQs SECTION (with expand/collapse all) -->
    <section class="faqs-section" id="faqs">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Frequently Asked Questions</h2>
                <div class="section-title-accent"></div>
                <p class="section-subtitle">
                    Find answers to common questions about the admission process
                </p>
            </div>

            <div class="faq-toggle-all">
                <button id="faqExpandAllBtn">Expand All</button> /
                <button id="faqCollapseAllBtn">Collapse All</button>
            </div>

            <div class="faqs-container faqs-two-col">
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFAQ(this)">
                        <span>What if I don't have my PSA birth certificate yet?</span>
                        <svg class="faq-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
                        </svg>
                    </button>
                    <div class="faq-answer"><div><p>You may use a scanned copy of your original birth certificate to apply. Submit the PSA-issued certificate before final enrollment. Contact Admissions for details.</p></div></div>
                </div>
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFAQ(this)">
                        <span>Can I save my application as a draft?</span>
                        <svg class="faq-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
                        </svg>
                    </button>
                    <div class="faq-answer"><div><p>Yes. Progress is saved automatically. Return with the same device and email to continue.</p></div></div>
                </div>
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFAQ(this)">
                        <span>How long does document verification take?</span>
                        <svg class="faq-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
                        </svg>
                    </button>
                    <div class="faq-answer"><div><p>Usually 1–3 business days (FIFO). You’ll get an email when verified or if something needs correction.</p></div></div>
                </div>
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFAQ(this)">
                        <span>What is the application deadline?</span>
                        <svg class="faq-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
                        </svg>
                    </button>
                    <div class="faq-answer"><div><p>Deadlines vary per semester. Check the BPC website or contact (044) 903-5634 / registrars@bpc.edu.ph.</p></div></div>
                </div>
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFAQ(this)">
                        <span>Can I track my application status?</span>
                        <svg class="faq-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
                        </svg>
                    </button>
                    <div class="faq-answer"><div><p>Yes. Use “Track My Application” with your reference number and email to see status (e.g. Verified, Exam Scheduled, Enrolled).</p></div></div>
                </div>
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFAQ(this)">
                        <span>What happens if I don't pass the CHED entrance exam?</span>
                        <svg class="faq-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
                        </svg>
                    </button>
                    <div class="faq-answer"><div><p>We consider your 1st, 2nd, and 3rd program choices (each has a cutoff). You can re-apply next period or ask Admissions about TESDA options.</p></div></div>
                </div>
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFAQ(this)">
                        <span>What are the passing scores for different programs?</span>
                        <svg class="faq-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
                        </svg>
                    </button>
                    <div class="faq-answer"><div><p>Cutoffs vary by program (e.g. 75% or 85%). Your score is checked against each choice in order. For current figures, contact (044) 903-5634 or registrars@bpc.edu.ph.</p></div></div>
                </div>
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFAQ(this)">
                        <span>Can I choose a TESDA program if I don't pass the CHED exam?</span>
                        <svg class="faq-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
                        </svg>
                    </button>
                    <div class="faq-answer"><div><p>TESDA has no entrance exam—only document verification and interview. Contact Admissions to switch or submit a new application.</p></div></div>
                </div>
            </div>

            <div class="faqs-cta">
                <p>Still have questions?</p>
                <a href="#" class="btn-secondary">Contact Admissions Office</a>
            </div>
        </div>
    </section>

    <!-- FINAL CTA SECTION (unchanged) -->
    <section class="final-cta-section">
        <div class="container">
            <h3>Ready to Begin Your Journey?</h3>
            <p>Start your application now and join the BPC community</p>
            <?php if ($isLoggedIn): ?>
            <a href="app/student/dashboard.php" class="btn-primary">Go to Dashboard</a>
            <?php else: ?>
            <?php if ($applications_open): ?>
            <button class="btn-primary" onclick="openModal()">Start My Application</button>
            <?php else: ?>
            <button class="btn-primary" onclick="openModal()">Login to Check Status</button>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- FOOTER (unchanged) -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <div class="footer-logo">
                        <img src="assets/cropped-cropped-cropped-cropped-cropped-bpclogo-1-1-1-150x150.png" alt="BPC Logo">
                        <h3>Bulacan Polytechnic College</h3>
                    </div>
                    <p>Bulihan, City of Malolos, Bulacan, Philippines</p>
                    <p style="margin-top: 1rem;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle; margin-right: 8px;">
                            <path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56-.35-.12-.74-.03-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z"/>
                        </svg>
                        (044) 903-5634 | registrars@bpc.edu.ph
                    </p>
                    <p>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle; margin-right: 8px;">
                            <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                        </svg>
                        communications@bpc.edu.ph
                    </p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul class="footer-links">
                        <li><a href="#">Home</a></li>
                        <li><a href="#">About BPC</a></li>
                        <li><a href="#">Academic Programs</a></li>
                        <li><a href="#">Admissions</a></li>
                        <li><a href="#">News & Announcements</a></li>
                        <li><a href="#">Contact Us</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Student & Public Resources</h4>
                    <ul class="footer-links">
                        <li><a href="#">Student Portal</a></li>
                        <li><a href="#">Faculty Directory</a></li>
                        <li><a href="#">Downloadable Forms</a></li>
                        <li><a href="#">Scholarships & Financial Aid</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-divider"></div>
        </div>
    </footer>

    <!-- ============================================
         LOGIN/REGISTER MODAL (enhanced)
    ============================================ -->
    <div class="modal-overlay" id="authModal">
        <div class="modal-container">
            <div class="modal-header">
                <h2>Welcome to BPC iEnroll</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            
            <div class="modal-body">
                <div class="modal-tabs">
                    <button class="modal-tab <?php echo ($authErrorTab === 'login') ? 'active' : ''; ?>" onclick="switchModalTab('login')">Login</button>
                    <button class="modal-tab <?php echo ($authErrorTab === 'register') ? 'active' : ''; ?>" onclick="switchModalTab('register')">Register</button>
                </div>

                <!-- Login Form -->
                <div class="modal-form-content <?php echo ($authErrorTab === 'login') ? 'active' : ''; ?>" id="loginForm">
                    <form action="app/auth/login.php" method="POST" id="loginFormElement">
                        <?php if (!empty($authError) && $authErrorTab === 'login'): ?>
                            <div class="modal-alert modal-alert-error" style="margin-bottom: 0.75rem;">
                                <?php echo $authError; ?>
                            </div>
                        <?php endif; ?>
                        <div class="modal-form-group">
                            <label for="loginEmail">Email Address</label>
                            <input type="email" id="loginEmail" name="email" required placeholder="your.email@example.com">
                        </div>
                        
                        <div class="modal-form-group">
                            <label for="loginPassword">Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="loginPassword" name="password" required placeholder="Enter your password">
                                <button type="button" class="password-toggle" onclick="togglePassword('loginPassword')">👁</button>
                            </div>
                        </div>
                        
                        <button type="submit" class="modal-submit-btn" id="loginSubmitBtn">Login</button>
                        
                        <p class="modal-helper-text">
                            <a href="app/auth/forgot-password.php" onclick="closeModal()">Forgot Password?</a>
                        </p>
                    </form>
                </div>

                <!-- Register Form (enhanced with strength meter and real-time validation) -->
                <div class="modal-form-content <?php echo ($authErrorTab === 'register') ? 'active' : ''; ?>" id="registerForm">
                    <form action="app/auth/register.php" method="POST" id="registerFormElement">
                        <?php if (!empty($authError) && $authErrorTab === 'register'): ?>
                            <div class="modal-alert modal-alert-error" style="margin-bottom: 0.75rem;">
                                <?php echo $authError; ?>
                            </div>
                        <?php endif; ?>
                        <div class="modal-form-group" id="emailGroup">
                            <label for="registerEmail">Email Address</label>
                            <input type="email" id="registerEmail" name="email" required placeholder="your.email@example.com">
                            <span class="modal-error-message">Please enter a valid email</span>
                        </div>
                        
                        <div class="modal-form-group" id="passwordGroup">
                            <label for="registerPassword">Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="registerPassword" name="password" required placeholder="Minimum 6 characters" minlength="6">
                                <button type="button" class="password-toggle" onclick="togglePassword('registerPassword')">👁</button>
                            </div>
                            <div class="strength-meter" id="strengthMeter">
                                <div class="strength-segment"></div>
                                <div class="strength-segment"></div>
                                <div class="strength-segment"></div>
                            </div>
                            <span class="modal-error-message">Password must be at least 6 characters</span>
                        </div>
                        
                        <div class="modal-form-group" id="confirmGroup">
                            <label for="confirmPassword">Confirm Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="confirmPassword" name="confirm_password" required placeholder="Re-enter password">
                                <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword')">👁</button>
                            </div>
                            <span class="modal-error-message">Passwords do not match</span>
                            <span class="validation-icon" id="confirmValidationIcon"></span>
                        </div>
                        
                        <button type="submit" class="modal-submit-btn" id="registerSubmitBtn" disabled>Create Account</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================
         JAVASCRIPT (original + enhancements)
    ============================================ -->
    <script>
        // ========== ORIGINAL FUNCTIONS (kept) ==========
        // ... (switchTab, switchTrack, toggleProgramAccordion, toggleFAQ, mobile menu, back to top, etc.)
        // I'll reproduce them here with enhancements.

        // ========== PAGE LOAD ANIMATIONS ==========
        document.addEventListener('DOMContentLoaded', function() {
            const sections = document.querySelectorAll('section');
            sections.forEach((section, index) => {
                section.style.animationDelay = `${index * 0.1}s`;
                section.classList.add('fade-in');
            });
            // Sticky navbar effect
            const navbar = document.getElementById('mainNavbar');
            window.addEventListener('scroll', () => {
                if (window.scrollY > 10) {
                    navbar.classList.add('navbar-scrolled');
                } else {
                    navbar.classList.remove('navbar-scrolled');
                }
            });
            // Program search
            const searchInput = document.getElementById('programSearchInput');
            if (searchInput) {
                searchInput.addEventListener('input', filterPrograms);
            }
            // Expand/Collapse All FAQs
            document.getElementById('faqExpandAllBtn')?.addEventListener('click', () => toggleAllFaqs(true));
            document.getElementById('faqCollapseAllBtn')?.addEventListener('click', () => toggleAllFaqs(false));
        });

        // ========== REQUIREMENTS TAB ==========
        function switchTab(tab) {
            const tabs = document.querySelectorAll('.req-tab');
            const contents = document.querySelectorAll('.req-content');
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));
            if (tab === 'freshmen') {
                tabs[0].classList.add('active');
                document.getElementById('freshmen-content').classList.add('active');
            } else {
                tabs[1].classList.add('active');
                document.getElementById('transferees-content').classList.add('active');
            }
        }

        // ========== HOW IT WORKS TRACK TOGGLE ==========
        function switchTrack(track) {
            document.querySelectorAll('.track-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('timeline-ched').style.display = track === 'ched' ? 'grid' : 'none';
            document.getElementById('timeline-tesda').style.display = track === 'tesda' ? 'grid' : 'none';
            document.getElementById('track-' + track).classList.add('active');
        }

        // ========== PROGRAM ACCORDION ==========
        function toggleProgramAccordion(btn) {
            var item = btn.closest('.program-accordion-item');
            var isOpen = item.classList.contains('program-accordion-open');
            item.classList.toggle('program-accordion-open', !isOpen);
            btn.setAttribute('aria-expanded', !isOpen);
        }

        // ========== PROGRAM FILTER (live search) ==========
        function filterPrograms() {
            const query = document.getElementById('programSearchInput').value.toLowerCase().trim();
            const allItems = document.querySelectorAll('.program-accordion-item');
            let anyVisible = false;
            allItems.forEach(item => {
                const name = item.getAttribute('data-program-name') || '';
                const matches = name.includes(query);
                item.style.display = matches ? '' : 'none';
                if (matches) anyVisible = true;
            });
            // Show "no results" message if needed
            let noResultsDiv = document.querySelector('.no-programs-found');
            if (!anyVisible && query !== '') {
                if (!noResultsDiv) {
                    noResultsDiv = document.createElement('div');
                    noResultsDiv.className = 'no-programs-found';
                    noResultsDiv.innerText = 'No programs match your search.';
                    const container = document.querySelector('.programs-two-col-layout');
                    container.parentNode.insertBefore(noResultsDiv, container.nextSibling);
                }
            } else {
                if (noResultsDiv) noResultsDiv.remove();
            }
        }

        // ========== FAQ ACCORDION ==========
        function toggleFAQ(button) {
            const faqItem = button.parentElement;
            const answer = faqItem.querySelector('.faq-answer');
            const isActive = button.classList.contains('active');
            // Close all others? (optional: comment out to keep only one open)
            // Uncomment the following lines to close others:
            // document.querySelectorAll('.faq-question').forEach(q => {
            //     q.classList.remove('active');
            //     q.parentElement.querySelector('.faq-answer').classList.remove('active');
            // });
            if (!isActive) {
                button.classList.add('active');
                answer.classList.add('active');
            } else {
                button.classList.remove('active');
                answer.classList.remove('active');
            }
        }

        function toggleAllFaqs(expand) {
            document.querySelectorAll('.faq-question').forEach(btn => {
                const answer = btn.parentElement.querySelector('.faq-answer');
                if (expand) {
                    btn.classList.add('active');
                    answer.classList.add('active');
                } else {
                    btn.classList.remove('active');
                    answer.classList.remove('active');
                }
            });
        }

        // ========== MOBILE MENU ==========
        function toggleMobileMenu() {
            const menu = document.querySelector('.mobile-menu');
            const overlay = document.querySelector('.mobile-overlay');
            menu.classList.toggle('active');
            overlay.classList.toggle('active');
            document.body.style.overflow = menu.classList.contains('active') ? 'hidden' : '';
        }
        function closeMobileMenu() {
            const menu = document.querySelector('.mobile-menu');
            const overlay = document.querySelector('.mobile-overlay');
            menu.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        // ========== BACK TO TOP ==========
        const backToTopBtn = document.querySelector('.back-to-top');
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTopBtn.classList.add('visible');
            } else {
                backToTopBtn.classList.remove('visible');
            }
        });
        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // ========== SMOOTH SCROLL FOR ANCHORS ==========
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (href !== '#' && href.length > 1) {
                    e.preventDefault();
                    const target = document.querySelector(href);
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            });
        });

        // ========== MODAL FUNCTIONS ==========
        function openModal() {
            document.getElementById('authModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeModal() {
            document.getElementById('authModal').classList.remove('active');
            document.body.style.overflow = '';
        }
        document.getElementById('authModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        function switchModalTab(tab) {
            const tabs = document.querySelectorAll('.modal-tab');
            const forms = document.querySelectorAll('.modal-form-content');
            tabs.forEach(t => t.classList.remove('active'));
            forms.forEach(f => f.classList.remove('active'));
            if (tab === 'login') {
                tabs[0].classList.add('active');
                document.getElementById('loginForm').classList.add('active');
            } else {
                tabs[1].classList.add('active');
                document.getElementById('registerForm').classList.add('active');
            }
        }
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            input.type = input.type === 'password' ? 'text' : 'password';
        }

        // ========== ENHANCEMENTS: Password Strength & Real-time Validation ==========
        const passwordInput = document.getElementById('registerPassword');
        const confirmInput = document.getElementById('confirmPassword');
        const registerBtn = document.getElementById('registerSubmitBtn');
        const strengthSegments = document.querySelectorAll('#strengthMeter .strength-segment');

        function checkPasswordStrength(pw) {
            let strength = 0;
            if (pw.length >= 6) strength++;
            if (pw.length >= 8) strength++;
            if (/[A-Z]/.test(pw)) strength++;
            if (/[0-9]/.test(pw)) strength++;
            if (/[^A-Za-z0-9]/.test(pw)) strength++;
            return Math.min(strength, 3);
        }

        function updateStrengthMeter() {
            const pw = passwordInput.value;
            const strength = checkPasswordStrength(pw);
            strengthSegments.forEach((seg, idx) => {
                seg.classList.remove('weak', 'medium', 'strong');
                if (idx < strength) {
                    if (strength === 1) seg.classList.add('weak');
                    else if (strength === 2) seg.classList.add('medium');
                    else if (strength >= 3) seg.classList.add('strong');
                }
            });
            validateForm();
        }

        function validateForm() {
            const pw = passwordInput.value;
            const confirm = confirmInput.value;
            const pwValid = pw.length >= 6 && checkPasswordStrength(pw) >= 2; // medium or strong
            const matchValid = pw === confirm && pw !== '';
            const emailValid = document.getElementById('registerEmail').checkValidity();
            registerBtn.disabled = !(pwValid && matchValid && emailValid);
            // Visual feedback for confirm field
            const confirmGroup = document.getElementById('confirmGroup');
            if (confirm !== '' && pw !== confirm) {
                confirmGroup.classList.add('error');
            } else {
                confirmGroup.classList.remove('error');
            }
            // Password group error
            const passwordGroup = document.getElementById('passwordGroup');
            if (pw !== '' && (pw.length < 6 || checkPasswordStrength(pw) < 2)) {
                passwordGroup.classList.add('error');
            } else {
                passwordGroup.classList.remove('error');
            }
        }

        if (passwordInput) {
            passwordInput.addEventListener('input', updateStrengthMeter);
            confirmInput.addEventListener('input', validateForm);
            document.getElementById('registerEmail').addEventListener('input', validateForm);
            updateStrengthMeter();
        }

        // ========== DOUBLE-SUBMIT PROTECTION (show loading overlay) ==========
        const loginForm = document.getElementById('loginFormElement');
        const registerFormElem = document.getElementById('registerFormElement');
        const loadingOverlay = document.getElementById('loadingOverlay');

        function showLoadingAndDisable(form, buttonId) {
            const btn = document.getElementById(buttonId);
            if (btn) {
                btn.disabled = true;
                btn.innerText = 'Processing...';
            }
            loadingOverlay.classList.add('active');
            form.submit();
        }

        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();
                showLoadingAndDisable(loginForm, 'loginSubmitBtn');
            });
        }
        if (registerFormElem) {
            registerFormElem.addEventListener('submit', function(e) {
                e.preventDefault();
                // Extra validation before submit
                if (registerBtn.disabled) {
                    alert('Please fix the form errors before submitting.');
                    return;
                }
                showLoadingAndDisable(registerFormElem, 'registerSubmitBtn');
            });
        }

        // ========== PRESERVE MODAL TAB IF ERROR FROM PHP ==========
        <?php if (!empty($authError)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            openModal();
            switchModalTab('<?php echo $authErrorTab; ?>');
        });
        <?php endif; ?>

        // ========== HANDLE URL PARAMETERS (verified email, etc.) ==========
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('tab') === 'register') {
                openModal();
                switchModalTab('register');
            }
            if (urlParams.get('verified') === '1') {
                openModal();
                switchModalTab('login');
                const msg = document.createElement('div');
                msg.className = 'alert alert-success';
                msg.style.cssText = 'position:fixed;top:1rem;left:50%;transform:translateX(-50%);z-index:9999;padding:0.75rem 1.5rem;background:#d4edda;color:#155724;border:1px solid #c3e6cb;border-radius:8px;font-weight:600;';
                msg.textContent = 'Email verified successfully! Please log in to continue.';
                document.body.appendChild(msg);
                setTimeout(() => msg.remove(), 5000);
            }
        });
    </script>
</body>
</html>