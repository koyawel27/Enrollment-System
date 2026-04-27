<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin Manage Programs
 * app/admin/admin-manage-programs.php
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/admin-auth-check.php';
require_once CONFIG_PATH . '/admin-permissions.php';
require_admin_role([ADMIN_ROLE_SUPER_ADMIN]);

$success = $error = '';
if (isset($_SESSION['admin_success'])) { $success = $_SESSION['admin_success']; unset($_SESSION['admin_success']); }
if (isset($_SESSION['admin_error']))   { $error   = $_SESSION['admin_error'];   unset($_SESSION['admin_error']); }

// Fetch all programs ordered by display_order
$programs = [];
$q = mysqli_query($conn, "SELECT * FROM programs ORDER BY display_order ASC, id ASC");
if ($q) {
    while ($row = mysqli_fetch_assoc($q)) {
        $programs[] = $row;
    }
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/admin-styles.css">
    <title>Manage Programs - Admin | BPC iEnroll</title>
    <style>
        .page-header h1 { font-size: 1.5rem; }
        .page-header p  { color: var(--text-gray); }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .filter-group { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .filter-btn {
            padding: 0.4rem 0.9rem;
            border-radius: 20px;
            border: 2px solid var(--border-color);
            background: white;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
        }
        .filter-btn.active, .filter-btn:hover {
            border-color: var(--bpc-green);
            background: var(--bpc-green);
            color: white;
        }

        .programs-table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        thead th {
            background: var(--bg-light);
            padding: 0.75rem 1rem;
            text-align: left;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-gray);
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }
        tbody td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }
        tbody tr:hover { background: var(--bg-light); }
        tbody tr.inactive { opacity: 0.55; }

        .program-code {
            font-family: monospace;
            font-size: 0.85rem;
            font-weight: 700;
            background: var(--bg-light);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            color: var(--bpc-green-dark);
        }
        .badge-ched  { background: #dbeafe; color: #1e40af; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
        .badge-tesda { background: #dcfce7; color: #166534; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
        .badge-active   { background: #dcfce7; color: #166534; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-inactive { background: #fee2e2; color: #991b1b; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }

        .dept-logo-thumb {
            width: 36px; height: 36px;
            object-fit: contain;
            border-radius: 4px;
            border: 1px solid var(--border-color);
        }
        .dept-logo-placeholder {
            width: 36px; height: 36px;
            background: var(--bg-light);
            border: 1px dashed var(--border-color);
            border-radius: 4px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
            color: var(--text-gray);
        }

        .action-btns { display: flex; gap: 0.4rem; }
        .btn-edit   { padding: 0.35rem 0.75rem; background: var(--bpc-green); color: white; border: none; border-radius: 5px; font-size: 0.78rem; font-weight: 600; cursor: pointer; }
        .btn-edit:hover { background: var(--bpc-green-dark); }
        .btn-toggle { padding: 0.35rem 0.75rem; background: #6c757d; color: white; border: none; border-radius: 5px; font-size: 0.78rem; font-weight: 600; cursor: pointer; }
        .btn-toggle:hover { background: #5a6268; }
        .btn-toggle.activate { background: #198754; }
        .btn-toggle.activate:hover { background: #157347; }

        /* Modal */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.5); z-index: 1000;
            align-items: center; justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: white; border-radius: 12px;
            width: 100%; max-width: 640px;
            max-height: 90vh; overflow-y: auto;
            padding: 2rem; box-shadow: 0 8px 32px rgba(0,0,0,0.18);
            animation: modalIn 0.2s ease;
        }
        @keyframes modalIn { from { opacity:0; transform:translateY(-16px); } to { opacity:1; transform:translateY(0); } }
        .modal-box h2 { font-size: 1.2rem; color: var(--bpc-green-dark); margin-bottom: 1.5rem; }
        .modal-box .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .modal-box .form-group { display: flex; flex-direction: column; gap: 0.3rem; }
        .modal-box .form-group.span-2 { grid-column: span 2; }
        .modal-box label { font-size: 0.82rem; font-weight: 600; color: var(--text-dark); }
        .modal-box input,
        .modal-box select,
        .modal-box textarea {
            padding: 0.6rem 0.875rem;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.875rem;
            font-family: inherit;
        }
        .modal-box input:focus,
        .modal-box select:focus,
        .modal-box textarea:focus { outline: none; border-color: var(--bpc-green); }
        .modal-box textarea { resize: vertical; min-height: 72px; }
        .modal-box small { font-size: 0.75rem; color: var(--text-gray); }
        .modal-actions {
            display: flex; justify-content: flex-end; gap: 0.75rem;
            margin-top: 1.5rem; padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }
        .btn-cancel-modal { padding: 0.6rem 1.25rem; background: #6c757d; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .btn-save-modal   { padding: 0.6rem 1.25rem; background: var(--bpc-green); color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .btn-save-modal:hover { background: var(--bpc-green-dark); }

        .btn-add-program {
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.6rem 1.25rem;
            background: var(--bpc-green); color: white;
            border: none; border-radius: 6px;
            font-size: 0.875rem; font-weight: 600; cursor: pointer;
        }
        .btn-add-program:hover { background: var(--bpc-green-dark); }

        .logo-preview-wrap { display: flex; align-items: center; gap: 0.75rem; margin-top: 0.4rem; }
        .logo-preview-img  { width: 48px; height: 48px; object-fit: contain; border: 1px solid var(--border-color); border-radius: 6px; }

        @media (max-width: 640px) {
            .modal-box .form-grid { grid-template-columns: 1fr; }
            .modal-box .form-group.span-2 { grid-column: span 1; }
        }
    </style>
</head>
<body>
<?php include '../shared/admin-sidebar.php'; ?>

<main class="main">
    <div class="page-header">
        <h1>Manage Programs</h1>
        <p>Add, edit, or deactivate programs. Changes reflect immediately on the landing page and application form.</p>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="card">
        <div class="toolbar">
            <div class="filter-group">
                <button class="filter-btn active" onclick="filterPrograms('all', this)">All (<?php echo count($programs); ?>)</button>
                <button class="filter-btn" onclick="filterPrograms('CHED', this)">CHED</button>
                <button class="filter-btn" onclick="filterPrograms('TESDA', this)">TESDA</button>
                <button class="filter-btn" onclick="filterPrograms('inactive', this)">Inactive</button>
            </div>
            <button class="btn-add-program" onclick="openAddModal()">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                Add Program
            </button>
        </div>

        <div class="programs-table-wrap">
            <table id="programsTable">
                <thead>
                    <tr>
                        <th>Logo</th>
                        <th>Code</th>
                        <th>Program Name</th>
                        <th>Category</th>
                        <th>Department</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($programs as $p): ?>
                <tr class="program-row <?php echo $p['is_active'] ? '' : 'inactive'; ?>"
                    data-category="<?php echo htmlspecialchars($p['category']); ?>"
                    data-active="<?php echo $p['is_active']; ?>">
                    <td>
                        <?php if (!empty($p['department_logo'])): ?>
                            <img src="../../uploads/dept_logos/<?php echo htmlspecialchars(basename($p['department_logo'])); ?>"
                                 alt="Logo" class="dept-logo-thumb">
                        <?php else: ?>
                            <div class="dept-logo-placeholder" title="No logo">🏫</div>
                        <?php endif; ?>
                    </td>
                    <td><span class="program-code"><?php echo htmlspecialchars($p['code']); ?></span></td>
                    <td><?php echo htmlspecialchars($p['name']); ?></td>
                    <td>
                        <span class="<?php echo $p['category'] === 'CHED' ? 'badge-ched' : 'badge-tesda'; ?>">
                            <?php echo htmlspecialchars($p['category']); ?>
                        </span>
                    </td>
                    <td><?php echo $p['department'] ? htmlspecialchars($p['department']) : '<span style="color:var(--text-gray);font-style:italic;">Not set</span>'; ?></td>
                    <td><?php echo (int)$p['display_order']; ?></td>
                    <td>
                        <span class="<?php echo $p['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                            <?php echo $p['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-btns">
                            <button class="btn-edit"
                                onclick="openEditModal(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES); ?>)">
                                Edit
                            </button>
                            <form method="POST" action="admin-save-program.php" style="display:inline;"
                                  onsubmit="return confirm('<?php echo $p['is_active'] ? 'Deactivate' : 'Activate'; ?> this program?');">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                <button type="submit"
                                    class="btn-toggle <?php echo $p['is_active'] ? '' : 'activate'; ?>">
                                    <?php echo $p['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Add / Edit Modal -->
<div class="modal-overlay" id="programModal" onclick="if(event.target===this)closeModal()">
    <div class="modal-box">
        <h2 id="modalTitle">Add Program</h2>
        <form method="POST" action="admin-save-program.php" enctype="multipart/form-data" id="programForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id"     id="formId"     value="">

            <div class="form-grid">
                <div class="form-group">
                    <label for="fCode">Program Code *</label>
                    <input type="text" id="fCode" name="code" required maxlength="20"
                           placeholder="e.g. BSIS" style="text-transform:uppercase;">
                    <small>Short unique code. Cannot be changed after creation.</small>
                </div>
                <div class="form-group">
                    <label for="fCategory">Category *</label>
                    <select id="fCategory" name="category" required>
                        <option value="CHED">CHED</option>
                        <option value="TESDA">TESDA</option>
                    </select>
                </div>
                <div class="form-group span-2">
                    <label for="fName">Program Name *</label>
                    <input type="text" id="fName" name="name" required maxlength="255"
                           placeholder="e.g. Bachelor of Science in Information Systems">
                </div>
                <div class="form-group span-2">
                    <label for="fDept">Department</label>
                    <input type="text" id="fDept" name="department" maxlength="100"
                           placeholder="e.g. Information Technology Education">
                    <small>Leave blank if unknown. Can be updated later.</small>
                </div>
                <div class="form-group span-2">
                    <label for="fDesc">Description</label>
                    <textarea id="fDesc" name="description" rows="3"
                              placeholder="Brief description of the program..."></textarea>
                </div>
                <div class="form-group span-2">
                    <label for="fCareers">Careers & Opportunities</label>
                    <textarea id="fCareers" name="careers" rows="3"
                              placeholder="One career per line. e.g.&#10;Systems Analyst&#10;Software Developer"></textarea>
                    <small>Enter one career per line. Saved automatically as a list.</small>
                </div>
                <div class="form-group span-2">
                    <label for="fAltJobs">Alternative Jobs</label>
                    <textarea id="fAltJobs" name="alt_jobs" rows="3"
                              placeholder="One job per line. e.g.&#10;Business Analyst&#10;UX Designer"></textarea>
                    <small>Enter one job per line.</small>
                </div>
                <div class="form-group">
                    <label for="fOrder">Display Order</label>
                    <input type="number" id="fOrder" name="display_order" min="0" max="999" value="0">
                    <small>Lower number = shown first.</small>
                </div>
                <div class="form-group">
                    <label for="fLogo">Department Logo</label>
                    <input type="file" id="fLogo" name="department_logo"
                           accept="image/png,image/jpeg,image/svg+xml,image/webp">
                    <small>PNG, JPG, SVG, or WebP. Max 2MB. Leave blank to keep existing.</small>
                    <div class="logo-preview-wrap" id="logoPreviewWrap" style="display:none;">
                        <img src="" alt="Preview" class="logo-preview-img" id="logoPreviewImg">
                        <span style="font-size:0.8rem; color:var(--text-gray);" id="logoPreviewName"></span>
                    </div>
                </div>
                <!-- Current logo (shown in edit mode) -->
                <div class="form-group span-2" id="currentLogoGroup" style="display:none;">
                    <label>Current Logo</label>
                    <div class="logo-preview-wrap">
                        <img src="" alt="Current logo" class="logo-preview-img" id="currentLogoImg">
                        <span style="font-size:0.8rem; color:var(--text-gray);" id="currentLogoName"></span>
                    </div>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel-modal" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-save-modal" id="modalSaveBtn">Save Program</button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Filter ──────────────────────────────────────────────────
function filterPrograms(type, btn) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.program-row').forEach(row => {
        const cat    = row.dataset.category;
        const active = row.dataset.active === '1';
        let show = false;
        if (type === 'all')      show = true;
        else if (type === 'inactive') show = !active;
        else show = (cat === type);
        row.style.display = show ? '' : 'none';
    });
}

// ── Modal helpers ────────────────────────────────────────────
function openAddModal() {
    document.getElementById('modalTitle').textContent  = 'Add Program';
    document.getElementById('formAction').value        = 'add';
    document.getElementById('formId').value            = '';
    document.getElementById('programForm').reset();
    document.getElementById('fCode').readOnly          = false;
    document.getElementById('currentLogoGroup').style.display = 'none';
    document.getElementById('logoPreviewWrap').style.display  = 'none';
    document.getElementById('programModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function openEditModal(p) {
    document.getElementById('modalTitle').textContent  = 'Edit Program';
    document.getElementById('formAction').value        = 'edit';
    document.getElementById('formId').value            = p.id;
    document.getElementById('fCode').value             = p.code;
    document.getElementById('fCode').readOnly          = true; // code is immutable
    document.getElementById('fCategory').value         = p.category;
    document.getElementById('fName').value             = p.name;
    document.getElementById('fDept').value             = p.department || '';
    document.getElementById('fDesc').value             = p.description || '';
    document.getElementById('fOrder').value            = p.display_order;

    // Parse JSON arrays back to line-separated text
    try { document.getElementById('fCareers').value  = JSON.parse(p.careers  || '[]').join('\n'); } catch(e) { document.getElementById('fCareers').value  = ''; }
    try { document.getElementById('fAltJobs').value  = JSON.parse(p.alt_jobs || '[]').join('\n'); } catch(e) { document.getElementById('fAltJobs').value  = ''; }

    // Current logo
    const logoGroup = document.getElementById('currentLogoGroup');
    const logoImg   = document.getElementById('currentLogoImg');
    const logoName  = document.getElementById('currentLogoName');
    if (p.department_logo) {
        logoImg.src     = '../../uploads/dept_logos/' + p.department_logo.split('/').pop();
        logoName.textContent = p.department_logo.split('/').pop();
        logoGroup.style.display = '';
    } else {
        logoGroup.style.display = 'none';
    }

    document.getElementById('logoPreviewWrap').style.display = 'none';
    document.getElementById('programModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('programModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Live logo preview
document.getElementById('fLogo').addEventListener('change', function() {
    const file = this.files[0];
    if (!file) { document.getElementById('logoPreviewWrap').style.display = 'none'; return; }
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('logoPreviewImg').src  = e.target.result;
        document.getElementById('logoPreviewName').textContent = file.name;
        document.getElementById('logoPreviewWrap').style.display = '';
    };
    reader.readAsDataURL(file);
});

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>
</body>
</html>