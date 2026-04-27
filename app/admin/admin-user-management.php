<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
/**
 * Admin User Management
 * admin-user-management.php
 */

session_start();
require_once CONFIG_PATH . '/db.php';
require_once CONFIG_PATH . '/admin-auth-check.php';
require_once CONFIG_PATH . '/admin-permissions.php';
require_once CONFIG_PATH . '/programs.php';

require_admin_role([ADMIN_ROLE_SUPER_ADMIN]);

$admins = [];
$q = mysqli_query($conn, "SELECT id, name, email, role, is_active, last_login_at, created_at FROM admins ORDER BY name ASC");
while ($row = mysqli_fetch_assoc($q)) $admins[] = $row;

$all_programs = get_all_programs($conn);

$head_assignments = [];
$qa = mysqli_query($conn, "SELECT head_id, program_code FROM program_head_departments");
while ($row = mysqli_fetch_assoc($qa)) $head_assignments[$row['head_id']][] = $row['program_code'];

$success = $error = '';
if (isset($_SESSION['admin_success'])) { $success = $_SESSION['admin_success']; unset($_SESSION['admin_success']); }
if (isset($_SESSION['admin_error']))   { $error   = $_SESSION['admin_error'];   unset($_SESSION['admin_error']); }

$edit_id    = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_admin = null;
if ($edit_id > 0) {
    foreach ($admins as $a) {
        if ($a['id'] == $edit_id) { $edit_admin = $a; break; }
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
    <title>User Management - Admin | BPC iEnroll</title>
    <style>
        .page-header h1 { font-size:1.5rem; }
        .page-header p  { color:var(--text-gray); }
        .alert { margin-bottom:1.25rem; }

        .card { background:var(--white); border-radius:10px; padding:1.75rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); margin-bottom:1.5rem; }
        .card-title { font-size:0.9rem; font-weight:700; color:var(--bpc-green-dark); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:1.25rem; padding-bottom:0.75rem; border-bottom:2px solid var(--bg-light); }

        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
        .form-group { display:flex; flex-direction:column; gap:0.35rem; }
        .form-group.span-2 { grid-column:span 2; }
        .form-group label { font-size:0.82rem; font-weight:600; color:var(--text-dark); }
        .form-group input, .form-group select { padding:0.65rem 0.875rem; border:2px solid var(--border-color); border-radius:6px; font-size:0.875rem; }
        .form-group input:focus, .form-group select:focus { outline:none; border-color:var(--bpc-green); }
        .form-group small { font-size:0.75rem; color:var(--text-gray); }

        .btn { display:inline-flex; align-items:center; gap:0.5rem; padding:0.65rem 1.25rem; border:none; border-radius:6px; font-size:0.875rem; font-weight:600; cursor:pointer; text-decoration:none; transition:all 0.2s; }
        .btn-green   { background:var(--bpc-green); color:#fff; }
        .btn-green:hover { background:var(--bpc-green-dark); }
        .btn-outline { background:transparent; border:2px solid var(--bpc-green); color:var(--bpc-green); }
        .btn-outline:hover { background:var(--bpc-green); color:#fff; }
        .btn-sm { padding:0.35rem 0.65rem; font-size:0.75rem; }

        .submit-row { margin-top:1.25rem; padding-top:1rem; border-top:1px solid var(--border-color); display:flex; gap:0.75rem; align-items:center; }

        /* TABLE — compact */
        .table-wrapper { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; font-size:0.875rem; }
        th { background:var(--bg-light); color:var(--text-gray); font-size:0.7rem; text-transform:uppercase; letter-spacing:0.5px; padding:0.6rem 0.75rem; text-align:left; border-bottom:2px solid var(--border-color); white-space:nowrap; }
        td { padding:0.6rem 0.75rem; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
        tr:last-child td { border-bottom:none; }
        tr:hover td { background:#fafafa; }
        tr.hidden-by-search { display:none; }

        /* Name + email stacked */
        .admin-name  { font-weight:700; font-size:0.875rem; display:block; }
        .admin-email { font-size:0.75rem; color:var(--text-gray); }

        /* Role badges */
        .badge-role { font-size:0.7rem; font-weight:700; padding:0.2rem 0.55rem; border-radius:8px; white-space:nowrap; display:inline-block; }
        .badge-super        { background:#1a237e; color:#fff; }
        .badge-officer      { background:#0d47a1; color:#fff; }
        .badge-registrar    { background:#004d40; color:#fff; }
        .badge-program-head { background:#4a148c; color:#fff; }
        .badge-active   { background:#d4edda; color:#155724; font-size:0.7rem; font-weight:700; padding:0.2rem 0.55rem; border-radius:8px; display:inline-block; }
        .badge-inactive { background:#f8d7da; color:#721c24; font-size:0.7rem; font-weight:700; padding:0.2rem 0.55rem; border-radius:8px; display:inline-block; }

        .you-badge { font-size:0.68rem; background:var(--bpc-green); color:white; padding:0.15rem 0.45rem; border-radius:10px; margin-left:0.3rem; vertical-align:middle; }

        /* Assigned programs — chips, max width constrained */
        .program-chips { display:flex; flex-wrap:wrap; gap:0.25rem; max-width:200px; }
        .program-chip  { font-size:0.68rem; background:#ede7f6; color:#4a148c; padding:0.15rem 0.45rem; border-radius:20px; font-weight:600; white-space:nowrap; }

        /* Last login — date only, small */
        .last-login { font-size:0.78rem; color:var(--text-gray); white-space:nowrap; }

        /* Actions — tight */
        .actions { display:flex; gap:0.35rem; flex-wrap:nowrap; align-items:center; }

        /* Search */
        .search-wrap { margin-bottom:1rem; }
        .search-wrap input { width:100%; max-width:300px; padding:0.5rem 0.75rem; border:2px solid var(--border-color); border-radius:6px; font-size:0.875rem; }
        .search-wrap input:focus { outline:none; border-color:var(--bpc-green); }
        .search-wrap label { display:block; font-size:0.72rem; font-weight:700; color:var(--text-gray); text-transform:uppercase; margin-bottom:0.35rem; }

        /* Department assignment */
        #deptAssignGroup { display:none; }
        .dept-checkboxes { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px,1fr)); gap:0.5rem; margin-top:0.5rem; }
        .dept-checkbox-item { display:flex; align-items:center; gap:0.5rem; padding:0.5rem 0.75rem; border:2px solid var(--border-color); border-radius:6px; cursor:pointer; transition:border-color 0.15s, background 0.15s; }
        .dept-checkbox-item:has(input:checked) { border-color:var(--bpc-green); background:#f0f7f0; }
        .dept-checkbox-item input[type="checkbox"] { width:16px; height:16px; accent-color:var(--bpc-green); cursor:pointer; border:none; padding:0; }
        .dept-checkbox-label { font-size:0.85rem; cursor:pointer; }
        .dept-checkbox-code  { font-size:0.72rem; font-family:monospace; color:var(--text-gray); margin-left:auto; }

        @media(max-width:768px) {
            .form-grid { grid-template-columns:1fr; }
            .form-group.span-2 { grid-column:span 1; }
        }
    </style>
</head>
<body>
<?php include '../shared/admin-sidebar.php'; ?>

<main class="main">
    <div class="page-header">
        <h1>User Management</h1>
        <p>Add, edit, or manage admin accounts. Set roles and activate/deactivate staff.</p>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <!-- Add / Edit Form -->
    <div class="card">
        <div class="card-title"><?php echo $edit_admin ? 'Edit Admin' : 'Add New Admin'; ?></div>
        <form method="POST" action="admin-save-admin.php">
            <?php if ($edit_admin): ?>
            <input type="hidden" name="id" value="<?php echo (int)$edit_admin['id']; ?>">
            <?php endif; ?>
            <div class="form-grid">
                <div class="form-group">
                    <label for="name">Full Name *</label>
                    <input type="text" id="name" name="name" required
                           value="<?php echo $edit_admin ? htmlspecialchars($edit_admin['name']) : ''; ?>"
                           placeholder="Juan Dela Cruz">
                </div>
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required
                           value="<?php echo $edit_admin ? htmlspecialchars($edit_admin['email']) : ''; ?>"
                           placeholder="admin@bpc.edu.ph">
                </div>
                <div class="form-group">
                    <label for="roleSelect">Role</label>
                    <select id="roleSelect" name="role" onchange="onRoleChange(this.value)">
                        <option value="super_admin"       <?php echo ($edit_admin['role'] ?? '')        === 'super_admin'       ? 'selected' : ''; ?>>Super Admin</option>
                        <option value="admission_officer" <?php echo (($edit_admin['role'] ?? 'admission_officer') === 'admission_officer') ? 'selected' : ''; ?>>Admission Officer</option>
                        <option value="registrar"         <?php echo ($edit_admin['role'] ?? '')        === 'registrar'         ? 'selected' : ''; ?>>Registrar</option>
                        <option value="program_head"      <?php echo ($edit_admin['role'] ?? '')        === 'program_head'      ? 'selected' : ''; ?>>Program Head</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="password"><?php echo $edit_admin ? 'New Password (leave blank to keep)' : 'Password *'; ?></label>
                    <input type="password" id="password" name="password"
                           <?php echo $edit_admin ? '' : 'required'; ?>
                           placeholder="<?php echo $edit_admin ? 'Leave blank to keep current' : 'Min 8 characters'; ?>">
                </div>
                <div class="form-group span-2" id="deptAssignGroup">
                    <label>Assigned Programs *</label>
                    <small>Select the programs this head is responsible for. Only applicants for these programs will be visible to them.</small>
                    <div class="dept-checkboxes">
                        <?php
                        $edit_assigned = $edit_id > 0 ? ($head_assignments[$edit_id] ?? []) : [];
                        foreach ($all_programs as $p):
                        ?>
                        <label class="dept-checkbox-item">
                            <input type="checkbox" name="assigned_programs[]"
                                   value="<?php echo htmlspecialchars($p['code']); ?>"
                                   <?php echo in_array($p['code'], $edit_assigned) ? 'checked' : ''; ?>>
                            <span class="dept-checkbox-label"><?php echo htmlspecialchars($p['name']); ?></span>
                            <span class="dept-checkbox-code"><?php echo htmlspecialchars($p['code']); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="submit-row">
                <button type="submit" class="btn btn-green"><?php echo $edit_admin ? 'Update Admin' : 'Add Admin'; ?></button>
                <?php if ($edit_admin): ?><a href="admin-user-management.php" class="btn btn-outline">Cancel</a><?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Admin List -->
    <div class="card">
        <div class="card-title">Admin Accounts (<?php echo count($admins); ?>)</div>
        <?php if (empty($admins)): ?>
            <div style="text-align:center;padding:2rem;color:var(--text-gray);">No admin accounts yet. Add one above.</div>
        <?php else: ?>
        <div class="search-wrap">
            <label for="adminSearch">Search by name or email</label>
            <input type="text" id="adminSearch" placeholder="Type to filter...">
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Admin</th>
                        <th>Role</th>
                        <th>Assigned Programs</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($admins as $a):
                    $is_you   = ($a['id'] ?? 0) == ($_SESSION['admin_id'] ?? 0);
                    $active   = !empty($a['is_active']);
                    $role     = $a['role'] ?? 'admission_officer';
                    $role_badge_class = match($role) {
                        'super_admin'  => 'badge-super',
                        'registrar'    => 'badge-registrar',
                        'program_head' => 'badge-program-head',
                        default        => 'badge-officer',
                    };
                    $assigned_codes = $head_assignments[$a['id']] ?? [];
                    $super_active_count = count(array_filter($admins, fn($x) => ($x['role'] ?? '') === 'super_admin' && !empty($x['is_active'])));
                    $can_toggle = !$is_you && ($active ? ($super_active_count > 1 || $role !== 'super_admin') : true);
                ?>
                <tr data-name="<?php echo htmlspecialchars(strtolower($a['name'])); ?>"
                    data-email="<?php echo htmlspecialchars(strtolower($a['email'])); ?>">
                    <td>
                        <span class="admin-name">
                            <?php echo htmlspecialchars($a['name']); ?>
                            <?php if ($is_you): ?><span class="you-badge">You</span><?php endif; ?>
                        </span>
                        <span class="admin-email"><?php echo htmlspecialchars($a['email']); ?></span>
                    </td>
                    <td><span class="badge-role <?php echo $role_badge_class; ?>"><?php echo htmlspecialchars(get_admin_role_label($role)); ?></span></td>
                    <td>
                        <?php if ($role === 'program_head' && !empty($assigned_codes)): ?>
                        <div class="program-chips">
                            <?php foreach ($assigned_codes as $code): ?>
                            <span class="program-chip"><?php echo htmlspecialchars($code); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php elseif ($role === 'program_head'): ?>
                        <span style="color:var(--text-gray);font-style:italic;font-size:0.78rem;">None assigned</span>
                        <?php else: ?>
                        <span style="color:var(--text-gray);font-size:0.82rem;">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="<?php echo $active ? 'badge-active' : 'badge-inactive'; ?>"><?php echo $active ? 'Active' : 'Inactive'; ?></span></td>
                    <td class="last-login">
                        <?php echo !empty($a['last_login_at']) ? date('M d, Y', strtotime($a['last_login_at'])) : '—'; ?>
                    </td>
                    <td>
                        <div class="actions">
                            <a href="admin-user-management.php?edit=<?php echo $a['id']; ?>" class="btn btn-sm btn-outline">Edit</a>
                            <?php if ($can_toggle): ?>
                                <?php if ($active): ?>
                                <form method="POST" action="admin-toggle-admin-status.php" style="display:inline;"
                                      onsubmit="return confirm('Deactivate <?php echo htmlspecialchars(addslashes($a['name'])); ?>?');">
                                    <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                    <input type="hidden" name="set_active" value="0">
                                    <button type="submit" class="btn btn-sm btn-outline">Deactivate</button>
                                </form>
                                <?php else: ?>
                                <form method="POST" action="admin-toggle-admin-status.php" style="display:inline;">
                                    <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                    <input type="hidden" name="set_active" value="1">
                                    <button type="submit" class="btn btn-sm btn-green">Activate</button>
                                </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
function onRoleChange(role) {
    var group = document.getElementById('deptAssignGroup');
    if (!group) return;
    group.style.display = role === 'program_head' ? 'flex' : 'none';
}
document.addEventListener('DOMContentLoaded', function() {
    var sel = document.getElementById('roleSelect');
    if (sel) onRoleChange(sel.value);
});

(function(){
    var search = document.getElementById('adminSearch');
    var rows   = document.querySelectorAll('table tbody tr[data-name]');
    if (!search || !rows.length) return;
    search.addEventListener('input', function(){
        var q = (this.value || '').toLowerCase().trim();
        rows.forEach(function(tr){
            var name  = tr.getAttribute('data-name')  || '';
            var email = tr.getAttribute('data-email') || '';
            tr.classList.toggle('hidden-by-search', !(!q || name.includes(q) || email.includes(q)));
        });
    });
})();
</script>
</body>
</html>