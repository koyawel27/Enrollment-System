<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin Document Review - Split-screen view
 * admin-review-documents.php
 *
 * Left: Document viewer with tabs (ID photo, Birth Cert, Grades, Transfer Cred, TOR)
 * Right: Applicant details + Accept/Reject form
 * POST to admin-update-status.php
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/admin-auth-check.php';
require_once CONFIG_PATH . '/admin-permissions.php';
require_admin_role([ADMIN_ROLE_SUPER_ADMIN, ADMIN_ROLE_ADMISSION_OFFICER]);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id < 1) {
    header('Location: ' . BASE_URL . '/app/admin/admin-dashboard.php');
    exit;
}

// Fetch application
$stmt = mysqli_prepare($conn,
    "SELECT a.*, u.email AS user_email
     FROM applications a
     JOIN users u ON a.user_id = u.id
     WHERE a.id = ? AND a.submitted = 1 LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$app = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$app) {
    header('Location: ' . BASE_URL . '/app/admin/admin-dashboard.php');
    exit;
}

$status = $app['status'] ?? '';
$allowed_statuses = ['Application Submitted', 'Documents Under Review', 'Documents Re-submitted'];
if (!in_array($status, $allowed_statuses)) {
    $_SESSION['admin_error'] = 'This application is not pending document review.';
    header('Location: ' . BASE_URL . '/app/admin/admin-dashboard.php');
    exit;
}

$apt = $app['applicant_type'] ?? 'Freshmen';
$a = $app;

// Validate file path to prevent directory traversal
function safe_doc_path($path) {
    if (empty($path)) return null;
    $path = trim(str_replace('\\', '/', $path));
    if (strpos($path, '..') !== false) return null;
    if (strpos($path, 'uploads/') !== 0) {
        $path = 'uploads/' . ltrim($path, '/');
    }
    if (strpos($path, 'uploads/') !== 0) return null;
    return $path;
}

$docs = [
    'id_photo' => ['label' => '2x2 ID Photo', 'path' => safe_doc_path($a['id_photo_path'] ?? '')],
    'birth_cert' => ['label' => 'PSA Birth Certificate', 'path' => safe_doc_path($a['birth_cert_path'] ?? '')],
    'grades' => ['label' => 'Report Card / Grades', 'path' => safe_doc_path($a['grades_path'] ?? '')],
];
if ($apt === 'Transferee') {
    $docs['transfer_cred'] = ['label' => 'Transfer Credential', 'path' => safe_doc_path($a['transfer_cred_path'] ?? '')];
    $docs['tor'] = ['label' => 'TOR', 'path' => safe_doc_path($a['tor_path'] ?? '')];
}

$prog_labels = [
    'BSIS'=>'BS Information Systems', 'ACT'=>'Associate in Computer Technology',
    'BSOM'=>'BS Office Management', 'BSAIS'=>'BS Accounting Information System',
    'BSCA'=>'BS Customs Administration', 'BTVTED'=>'Bachelor in Tech-Voc Teacher Education',
    'DHRMT'=>'Diploma in Hotel & Restaurant Mgmt Tech',
    'HRS'=>'Hotel and Restaurant Services', 'CCS'=>'Contact Center Services NCII',
    'BK'=>'Bookkeeping NCIII', 'EIM'=>'Electrical Installation & Maintenance NCII',
    'SMAW'=>'Shield Metal Arc Welding NCI/NCII',
];
$fc_label = $prog_labels[$a['first_choice'] ?? ''] ?? ($a['first_choice'] ?? '—');

$success = $error = '';
if (isset($_SESSION['admin_success'])) { $success = $_SESSION['admin_success']; unset($_SESSION['admin_success']); }
if (isset($_SESSION['admin_error']))   { $error   = $_SESSION['admin_error'];   unset($_SESSION['admin_error']);   }

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/admin-styles.css">
    <title>Review Documents - App #<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';

echo $id; ?> | BPC iEnroll</title>
    <style>
        .main { padding:1.5rem; }
        .page-header { margin-bottom:1rem; }
        .page-header h1 { font-size:1.35rem; }
        .back-link { display:inline-flex; align-items:center; gap:0.4rem; color:var(--bpc-green); text-decoration:none; font-weight:600; font-size:0.9rem; margin-bottom:0.5rem; }
        .back-link:hover { text-decoration:underline; }
        .alert { margin-bottom:1rem; }
        /* Split layout */
        .review-layout { display:flex; gap:1.5rem; min-height:calc(100vh - 12rem); }
        .doc-panel { flex:0 0 60%; background:var(--white); border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.06); overflow:hidden; display:flex; flex-direction:column; }
        .doc-tabs { display:flex; gap:0; border-bottom:2px solid var(--border-color); background:#f8f9fa; padding:0 1rem; flex-wrap:wrap; }
        .doc-tab { padding:0.75rem 1rem; font-size:0.85rem; font-weight:600; color:var(--text-gray); cursor:pointer; border-bottom:3px solid transparent; margin-bottom:-2px; transition:all 0.2s; }
        .doc-tab:hover { color:var(--bpc-green); }
        .doc-tab.active { color:var(--bpc-green); border-bottom-color:var(--bpc-green); background:var(--white); }
        .doc-tab.empty { color:#999; }
        .doc-content { flex:1; padding:1.5rem; overflow:auto; min-height:400px; position:relative; }
        .doc-pane { display:none; align-items:center; justify-content:center; min-height:350px; }
        .doc-pane.active { display:flex; flex-direction:column; }
        .doc-viewer { max-width:100%; max-height:70vh; }
        .doc-viewer img { max-width:100%; max-height:70vh; object-fit:contain; border:1px solid var(--border-color); border-radius:6px; }
        .doc-viewer iframe, .doc-viewer embed { width:100%; min-height:500px; border:1px solid var(--border-color); border-radius:6px; }
        .doc-placeholder { color:var(--text-gray); font-size:0.9rem; text-align:center; padding:3rem; }
        .doc-link { display:inline-block; margin-top:0.75rem; padding:0.5rem 1rem; background:var(--bpc-green); color:white; border-radius:6px; text-decoration:none; font-size:0.85rem; font-weight:600; }
        .doc-link:hover { background:#005000; color:white; }
        .right-panel { flex:0 0 calc(40% - 1.5rem); display:flex; flex-direction:column; gap:1rem; }
        .info-card { background:var(--white); border-radius:10px; padding:1.25rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
        .info-card h3 { font-size:0.9rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:1rem; color:var(--bpc-green-dark); }
        .info-row { font-size:0.875rem; margin-bottom:0.5rem; }
        .info-row strong { display:inline-block; min-width:100px; color:var(--text-gray); }
        .action-card { background:var(--white); border-radius:10px; padding:1.25rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
        .action-card h3 { font-size:0.9rem; font-weight:700; margin-bottom:1rem; color:var(--text-dark); }
        .btn { display:inline-block; padding:0.6rem 1.25rem; border-radius:6px; font-size:0.9rem; font-weight:600; cursor:pointer; border:none; text-decoration:none; text-align:center; transition:all 0.2s; }
        .btn-approve { background:var(--bpc-green); color:white; width:100%; margin-bottom:0.75rem; }
        .btn-approve:hover { background:#005000; color:white; }
        .btn-reject { background:#dc3545; color:white; width:100%; margin-bottom:0.75rem; }
        .btn-reject:hover { background:#c82333; color:white; }
        .btn-start { background:var(--bpc-green); color:white; width:100%; }
        .btn-start:hover { background:#005000; color:white; }
        .rejection-form { display:none; margin-top:1rem; padding-top:1rem; border-top:1px solid var(--border-color); }
        .rejection-form.show { display:block; }
        .action-card label { display:block; font-size:0.85rem; font-weight:600; margin-bottom:0.35rem; margin-top:0.75rem; }
        .action-card select, .action-card textarea { width:100%; padding:0.5rem; border:2px solid var(--border-color); border-radius:6px; font-size:0.875rem; }
        .action-card textarea { min-height:80px; resize:vertical; }
        .btn-secondary { background:#6c757d; color:white; margin-top:0.5rem; }
        .btn-secondary:hover { background:#5a6268; color:white; }
        .alert-warning { background:#fff3cd; color:#856404; border:1px solid #ffeeba; font-size:0.82rem; padding:0.75rem; border-radius:6px; margin-bottom:1rem; }
        @media(max-width:900px) { .review-layout { flex-direction:column; } .doc-panel, .right-panel { flex:1 1 auto; } }
    </style>
</head>
<body>
<?php
include '../shared/admin-sidebar.php'; ?>

<main class="main">
    <a href="admin-applications.php" class="back-link">← Back</a>
    <div class="page-header">
        <h1>Review Documents — <?php
echo htmlspecialchars($a['last_name'].', '.$a['first_name']); ?></h1>
        <p style="font-size:0.9rem;color:var(--text-gray);">Ref: <?php
echo htmlspecialchars($a['reference_number'] ?? '—'); ?> · <?php
echo htmlspecialchars($status); ?></p>
    </div>

    <?php
if($success): ?><div class="alert alert-success"><?php
echo htmlspecialchars($success); ?></div><?php
endif; ?>
    <?php
if($error):   ?><div class="alert alert-error"><?php
echo htmlspecialchars($error); ?></div><?php
endif; ?>

    <div class="review-layout">
        <!-- LEFT: Document viewer with tabs -->
        <div class="doc-panel">
            <div class="doc-tabs">
                <?php
$first = true; foreach ($docs as $key => $d): $has_file = !empty($d['path']); ?>
                <button type="button" class="doc-tab <?php
echo $first ? 'active' : ''; ?> <?php
echo !$has_file ? 'empty' : ''; ?>"
                        data-tab="<?php
echo htmlspecialchars($key); ?>">
                    <?php
echo htmlspecialchars($d['label']); ?>
                    <?php
if(!$has_file): ?><span style="color:#ccc;"> (none)</span><?php
endif; ?>
                </button>
                <?php
$first = false; endforeach; ?>
            </div>
            <div class="doc-content">
                <?php
$first = true; foreach ($docs as $key => $d): ?>
                <div class="doc-pane <?php
echo $first ? 'active' : ''; ?>" data-pane="<?php
echo htmlspecialchars($key); ?>">
                    <?php
if (empty($d['path'])) {
                        echo '<div class="doc-placeholder">No document uploaded.</div>';
                    } else {
                        $ext = strtolower(pathinfo($d['path'], PATHINFO_EXTENSION));
                        $is_img = in_array($ext, ['jpg','jpeg','png','gif','webp']);
                        $filename = basename($d['path']);
                        $url = htmlspecialchars(BASE_URL . '/uploads/' . $filename);
                        if ($is_img) {
                            echo '<div class="doc-viewer"><a href="'.$url.'" target="_blank"><img src="'.$url.'" alt="'.htmlspecialchars($d['label']).'"></a>';
                            echo '<a href="'.$url.'" target="_blank" class="doc-link">Download / Open in new tab</a></div>';
                        } else {
                            echo '<div class="doc-viewer"><iframe src="'.$url.'" title="'.htmlspecialchars($d['label']).'"></iframe>';
                            echo '<a href="'.$url.'" target="_blank" class="doc-link">Download / Open PDF in new tab</a></div>';
                        }
                    }
                    ?>
                </div>
                <?php
$first = false; endforeach; ?>
            </div>
        </div>

        <!-- RIGHT: Applicant info + Accept/Reject -->
        <div class="right-panel">
            <div class="info-card">
                <h3>Applicant</h3>
                <div class="info-row"><strong>Name:</strong> <?php echo htmlspecialchars($a['first_name'].' '.$a['last_name']); ?></div>

				<div class="info-row"><strong>Email:</strong> <?php echo htmlspecialchars($a['user_email'] ?? '—'); ?></div>

				<div class="info-row"><strong>Program:</strong> <?php echo htmlspecialchars($fc_label); ?></div>

				<div class="info-row"><strong>Track:</strong> <?php echo htmlspecialchars($a['program_category'] ?? '—'); ?></div>

				<div class="info-row"><strong>Type:</strong> <?php echo htmlspecialchars($apt); ?> </div>
            </div>

            <div class="action-card">
                <h3>Document Review</h3>

                <?php
				if ($status === 'Application Submitted'): ?>
                    <form method="POST" action="admin-update-status.php">
                        <input type="hidden" name="application_id" value="<?php echo $id; ?>">
                        <input type="hidden" name="new_status" value="Documents Under Review">
                        <input type="hidden" name="return_to" value="review">
                        <button type="submit" class="btn btn-start" onclick="return confirm('Start document review?');">
                            Start Document Review
                        </button>
                    </form>
                    <p style="font-size:0.8rem;color:var(--text-gray);margin-top:0.75rem;">After starting, you can Accept or Reject documents.</p>

                <?php
				elseif ($status === 'Documents Under Review' || $status === 'Documents Re-submitted'): ?>
                    <?php
				if ($status === 'Documents Re-submitted'): ?>
                        <div class="alert-warning">
                            Student has re-uploaded documents.
                            <?php
				if (!empty($a['resubmission_count'])): ?>(Attempt #<?php
				echo (int)$a['resubmission_count']; ?>)<?php
endif; ?>
                        </div>
                    <?php
endif; ?>

                    <form method="POST" action="admin-update-status.php">
                        <input type="hidden" name="application_id" value="<?php
echo $id; ?>">
                        <input type="hidden" name="new_status" value="Documents Verified">
                        <input type="hidden" name="return_to" value="dashboard">
                        <button type="submit" class="btn btn-approve" onclick="return confirm('Approve documents for this application?');">
                            Approve Documents
                        </button>
                    </form>

                    <button type="button" class="btn btn-reject" onclick="toggleRejectForm()">Reject Documents</button>

                    <div class="rejection-form" id="rejectForm">
                        <form method="POST" action="admin-update-status.php">
                            <input type="hidden" name="application_id" value="<?php
echo $id; ?>">
                            <input type="hidden" name="new_status" value="Documents Rejected">
                            <input type="hidden" name="return_to" value="dashboard">
                            <label for="rejection_preset">Select Reason *</label>
                            <select id="rejection_preset" onchange="prefillReason(this.value)">
                                <option value="">-- Select a reason --</option>
                                <option value="ID photo is unclear or low quality. Please re-upload a clearer 2x2 photo.">ID photo is unclear or low quality</option>
                                <option value="Report card / grades document is unreadable. Please re-upload a clearer copy.">Report card is unreadable</option>
                                <option value="PSA Birth Certificate is missing. Please upload your PSA Birth Certificate.">PSA Birth Certificate is missing</option>
                                <option value="PSA Birth Certificate is unreadable. Please re-upload a clearer copy.">PSA Birth Certificate is unreadable</option>
                                <option value="Wrong document was uploaded. Please check and re-upload the correct file.">Wrong document uploaded</option>
                                <option value="The uploaded document appears to be expired. Please upload a valid document.">Document appears expired</option>
                                <?php
if ($apt === 'Transferee'): ?>
                                <option value="Transfer Credential is missing. Please upload your Transfer Credential.">Transfer Credential is missing</option>
                                <option value="Transcript of Records (TOR) is missing. Please upload your TOR.">TOR is missing</option>
                                <?php
endif; ?>
                                <option value="custom">Other (specify below)</option>
                            </select>
                            <label for="rejection_reason">Full Reason / Additional Details *</label>
                            <textarea name="rejection_reason" id="rejection_reason" required placeholder="Select a reason above or type a custom reason..."></textarea>
                            <button type="submit" class="btn btn-reject" onclick="return confirm('Reject documents? The student will be notified and can re-upload.');">
                                Confirm Rejection
                            </button>
                        </form>
                        <button type="button" class="btn btn-secondary" onclick="toggleRejectForm()">Cancel</button>
                    </div>
                <?php
endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
(function() {
    const tabs = document.querySelectorAll('.doc-tab');
    const panes = document.querySelectorAll('.doc-pane');
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            const key = this.getAttribute('data-tab');
            tabs.forEach(function(t) { t.classList.remove('active'); });
            panes.forEach(function(p) {
                p.classList.toggle('active', p.getAttribute('data-pane') === key);
            });
            this.classList.add('active');
        });
    });

    window.toggleRejectForm = function() {
        document.getElementById('rejectForm').classList.toggle('show');
    };
    window.prefillReason = function(val) {
        const ta = document.getElementById('rejection_reason');
        if (val && val !== 'custom') ta.value = val;
        if (val === 'custom') { ta.value = ''; ta.placeholder = 'Type your reason here...'; ta.focus(); }
    };
})();
</script>
</body>
</html>