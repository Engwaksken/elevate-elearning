<?php
include 'scripts/connector.php';

$branches_result = $conn->query("SELECT id, name, location FROM branches ORDER BY name ASC");
$branch_count    = $branches_result ? $branches_result->num_rows : 0;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="app.css">
</head>
<body>

<!-- -- Toast bar --------------------------------------------------------------- -->
<div class="toast-bar" id="toastBar"></div>

<!-- -- Page heading ------------------------------------------------------------- -->
<div class="page-heading">
    <div>
        <h2>Branch <em>Management</em></h2>
        <div class="heading-meta">
            <span class="meta-pill">
                <i class="fa fa-house"></i>
                <a href="./" style="text-decoration:none;color:inherit;">Home</a>
            </span>
            <span class="meta-pill" style="background:var(--maroon-dim);color:var(--maroon);border-color:rgba(122,25,25,.18);">
                <i class="fa fa-code-branch"></i> Branches
            </span>
        </div>
    </div>
    <button class="btn-export" onclick="openModal('addModal')">
        <i class="fa fa-plus"></i> Add Branch
    </button>
</div>

<!-- -- Table card -------------------------------------------------------------- -->
<div class="tbl-card">

    <div class="tbl-toolbar">
        <span class="tbl-title">
            <i class="fa fa-code-branch tbl-title-icon"></i> All Branches
        </span>
        <span class="count-pill"><?php echo number_format($branch_count); ?> branch<?php echo $branch_count !== 1 ? 'es' : ''; ?></span>
    </div>

    <div class="tbl-scroll">
        <table class="app-table" id="branchTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Branch Name</th>
                    <th class="th-gold">Location</th>
                    <th class="th-gold" style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$branches_result || $branch_count < 1): ?>
                <tr id="empty-row">
                    <td colspan="4" style="padding:0;">
                        <div class="empty" style="border:none;border-radius:0;">
                            <i class="fa fa-code-branch"></i>
                            <p>No branches found. Add one to get started.</p>
                        </div>
                    </td>
                </tr>
            <?php else:
                $num = 1;
                while ($row = $branches_result->fetch_assoc()):
            ?>
                <tr id="branch-row-<?php echo (int)$row['id']; ?>">
                    <td class="cell-num"><?php echo $num++; ?></td>

                    <td>
                        <span class="fw-600" style="color:var(--text);font-size:.86rem;">
                            <?php echo htmlspecialchars($row['name']); ?>
                        </span>
                    </td>

                    <td class="cell-sm">
                        <i class="fa fa-location-dot" style="color:var(--gold);font-size:.65rem;margin-right:3px;"></i>
                        <?php echo htmlspecialchars($row['location'] ?: '-'); ?>
                    </td>

                    <td>
                        <div class="row-actions" style="justify-content:flex-end;">
                            <button class="ia-btn ia-view" title="Edit"
                                onclick="openEditModal(
                                    <?php echo (int)$row['id']; ?>,
                                    '<?php echo addslashes(htmlspecialchars($row['name'],    ENT_QUOTES)); ?>',
                                    '<?php echo addslashes(htmlspecialchars($row['location'], ENT_QUOTES)); ?>'
                                )">
                                <i class="fa fa-pen"></i>
                            </button>
                            <button class="ia-btn ia-del" title="Delete"
                                onclick="openDeleteModal(
                                    <?php echo (int)$row['id']; ?>,
                                    '<?php echo addslashes(htmlspecialchars($row['name'], ENT_QUOTES)); ?>'
                                )">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="tbl-footer">
        <span id="branch-count-label">
            <?php echo number_format($branch_count); ?> total branch<?php echo $branch_count !== 1 ? 'es' : ''; ?>
        </span>
    </div>
</div>


<!-- ----------------------------------------------------------------
     ADD MODAL
---------------------------------------------------------------- -->
<div class="modal-overlay" id="addModal">
    <div class="modal-box modal-box-sm">
        <div class="modal-head">
            <h3><i class="fa fa-plus-circle"></i> Add New Branch</h3>
            <button class="modal-close" onclick="closeModal('addModal')" aria-label="Close">
                <i class="fa fa-xmark"></i>
            </button>
        </div>
        <form id="addBranchForm">
            <div class="modal-body" style="display:flex;flex-direction:column;gap:14px;">

                <div class="ctrl-field">
                    <label class="ctrl-label">
                        <i class="fa fa-tag"></i> Branch Name <span style="color:var(--red);">*</span>
                    </label>
                    <input type="text" id="add_name" name="name" class="form-input"
                           placeholder="e.g. Kampala Main" required>
                </div>

                <div class="ctrl-field">
                    <label class="ctrl-label">
                        <i class="fa fa-location-dot"></i> Location
                    </label>
                    <input type="text" id="add_location" name="location" class="form-input"
                           placeholder="e.g. Kampala, Uganda">
                </div>

            </div>
            <div class="modal-foot">
                <span class="err-msg" id="add_err"><i class="fa fa-circle-xmark"></i> <span></span></span>
                <button type="button" class="btn-modal-cancel" onclick="closeModal('addModal')">
                    <i class="fa fa-xmark"></i> Cancel
                </button>
                <button type="submit" class="btn-export">
                    <span class="spin" id="add_spin"></span>
                    <i class="fa fa-floppy-disk" id="add_icon"></i> Save Branch
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ----------------------------------------------------------------
     EDIT MODAL
---------------------------------------------------------------- -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box modal-box-sm">
        <div class="modal-head">
            <h3><i class="fa fa-pen"></i> Edit Branch</h3>
            <button class="modal-close" onclick="closeModal('editModal')" aria-label="Close">
                <i class="fa fa-xmark"></i>
            </button>
        </div>
        <form id="editBranchForm">
            <input type="hidden" id="edit_id" name="id">
            <div class="modal-body" style="display:flex;flex-direction:column;gap:14px;">

                <div class="ctrl-field">
                    <label class="ctrl-label">
                        <i class="fa fa-tag"></i> Branch Name <span style="color:var(--red);">*</span>
                    </label>
                    <input type="text" id="edit_name" name="name" class="form-input" required>
                </div>

                <div class="ctrl-field">
                    <label class="ctrl-label">
                        <i class="fa fa-location-dot"></i> Location
                    </label>
                    <input type="text" id="edit_location" name="location" class="form-input">
                </div>

            </div>
            <div class="modal-foot">
                <span class="err-msg" id="edit_err"><i class="fa fa-circle-xmark"></i> <span></span></span>
                <button type="button" class="btn-modal-cancel" onclick="closeModal('editModal')">
                    <i class="fa fa-xmark"></i> Cancel
                </button>
                <button type="submit" class="btn-export">
                    <span class="spin" id="edit_spin"></span>
                    <i class="fa fa-floppy-disk" id="edit_icon"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ----------------------------------------------------------------
     DELETE MODAL
---------------------------------------------------------------- -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box modal-box-sm">
        <div class="modal-head">
            <h3><i class="fa fa-trash"></i> Delete Branch</h3>
            <button class="modal-close" onclick="closeModal('deleteModal')" aria-label="Close">
                <i class="fa fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="del-warn">
                <i class="fa fa-triangle-exclamation del-warn-icon"></i>
                <div class="del-warn-text">
                    Are you sure you want to delete
                    <strong id="delete_branch_name">&hellip;</strong>?
                    This action cannot be undone.
                </div>
            </div>
            <p class="del-note">
                <i class="fa fa-circle-info"></i>
                Any intakes or courses linked to this branch may be affected.
            </p>
        </div>
        <input type="hidden" id="delete_id">
        <div class="modal-foot">
            <span class="err-msg" id="del_err"><i class="fa fa-circle-xmark"></i> <span></span></span>
            <button type="button" class="btn-modal-cancel" onclick="closeModal('deleteModal')">
                <i class="fa fa-xmark"></i> Cancel
            </button>
            <button type="button" class="btn-modal-delete" id="del_btn" onclick="deleteBranch()">
                <span class="spin" id="del_spin"></span>
                <i class="fa fa-trash" id="del_icon"></i> Delete
            </button>
        </div>
    </div>
</div>


<!-- -- JS ---------------------------------------------------------------------- -->
<script>
// -- Modal helpers --------------------------------------------------------------
function openModal(id)  { document.getElementById(id).classList.add('is-open');    document.body.style.overflow = 'hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('is-open'); document.body.style.overflow = ''; }

document.querySelectorAll('.modal-overlay').forEach(o =>
    o.addEventListener('click', e => { if (e.target === o) closeModal(o.id); })
);
document.addEventListener('keydown', e => {
    if (e.key === 'Escape')
        document.querySelectorAll('.modal-overlay.is-open').forEach(m => closeModal(m.id));
});

// -- Toast ----------------------------------------------------------------------
function toast(msg, type = 'success') {
    const bar  = document.getElementById('toastBar');
    const el   = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.innerHTML = `<i class="fa fa-${type === 'success' ? 'circle-check' : 'circle-xmark'}"></i> ${msg}`;
    bar.appendChild(el);
    setTimeout(() => el.classList.add('dismissing'), 4200);
    setTimeout(() => el.remove(), 4600);
}

// -- Spinner helpers ------------------------------------------------------------
function setLoading(spinId, iconId, btnId, loading) {
    document.getElementById(spinId).classList.toggle('active', loading);
    document.getElementById(iconId).style.display = loading ? 'none' : '';
    if (btnId) document.getElementById(btnId).disabled = loading;
}

function showErr(errId, msg) {
    const el = document.getElementById(errId);
    el.querySelector('span').textContent = msg;
    el.classList.add('visible');
}
function clearErr(errId) { document.getElementById(errId).classList.remove('visible'); }

// -- Row counter ----------------------------------------------------------------
let rowCount = <?php echo $branch_count; ?>;

function updateCount() {
    document.getElementById('branch-count-label').textContent =
        `${rowCount} total branch${rowCount !== 1 ? 'es' : ''}`;
}

// -- ADD ------------------------------------------------------------------------
document.getElementById('addBranchForm').addEventListener('submit', function(e) {
    e.preventDefault();
    clearErr('add_err');
    setLoading('add_spin', 'add_icon', null, true);

    fetch('scripts/add_branch.php', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(data => {
            setLoading('add_spin', 'add_icon', null, false);
            if (data.success) {
                closeModal('addModal');
                this.reset();
                toast(data.message || 'Branch added successfully.');

                // Inject new row into table without reload
                const tbody = document.querySelector('#branchTable tbody');
                document.getElementById('empty-row')?.remove();
                rowCount++;
                const tr = document.createElement('tr');
                tr.id = `branch-row-${data.id}`;
                tr.innerHTML = buildRow(data.id, rowCount, data.name, data.location);
                tbody.appendChild(tr);
                updateCount();
            } else {
                showErr('add_err', data.message || 'Could not add branch.');
            }
        })
        .catch(() => {
            setLoading('add_spin', 'add_icon', null, false);
            showErr('add_err', 'Network error. Please try again.');
        });
});

// -- EDIT -----------------------------------------------------------------------
function openEditModal(id, name, location) {
    document.getElementById('edit_id').value       = id;
    document.getElementById('edit_name').value     = name;
    document.getElementById('edit_location').value = location;
    clearErr('edit_err');
    openModal('editModal');
}

document.getElementById('editBranchForm').addEventListener('submit', function(e) {
    e.preventDefault();
    clearErr('edit_err');
    setLoading('edit_spin', 'edit_icon', null, true);

    fetch('scripts/edit_branch.php', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(data => {
            setLoading('edit_spin', 'edit_icon', null, false);
            if (data.success) {
                closeModal('editModal');
                toast(data.message || 'Branch updated.');

                // Update row in place without reload
                const id  = document.getElementById('edit_id').value;
                const row = document.getElementById(`branch-row-${id}`);
                if (row) {
                    const cells = row.querySelectorAll('td');
                    cells[1].innerHTML = `<span class="fw-600" style="color:var(--text);font-size:.86rem;">${escHtml(data.name)}</span>`;
                    cells[2].innerHTML = `<i class="fa fa-location-dot" style="color:var(--gold);font-size:.65rem;margin-right:3px;"></i>${escHtml(data.location || '-')}`;
                }
            } else {
                showErr('edit_err', data.message || 'Could not update branch.');
            }
        })
        .catch(() => {
            setLoading('edit_spin', 'edit_icon', null, false);
            showErr('edit_err', 'Network error. Please try again.');
        });
});

// -- DELETE ---------------------------------------------------------------------
function openDeleteModal(id, name) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_branch_name').textContent = name;
    clearErr('del_err');
    openModal('deleteModal');
}

function deleteBranch() {
    const id = document.getElementById('delete_id').value;
    clearErr('del_err');
    setLoading('del_spin', 'del_icon', 'del_btn', true);

    fetch('scripts/delete_branch.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id)
    })
    .then(r => r.json())
    .then(data => {
        setLoading('del_spin', 'del_icon', 'del_btn', false);
        if (data.success) {
            closeModal('deleteModal');
            toast(data.message || 'Branch deleted.', 'success');

            // Fade & remove row
            const row = document.getElementById(`branch-row-${id}`);
            if (row) {
                row.style.transition = 'opacity .4s';
                row.style.opacity = '0';
                setTimeout(() => {
                    row.remove();
                    rowCount--;
                    updateCount();

                    // Show empty state if table is now empty
                    if (rowCount === 0) {
                        const tbody = document.querySelector('#branchTable tbody');
                        tbody.innerHTML = `
                            <tr id="empty-row">
                                <td colspan="4" style="padding:0;">
                                    <div class="empty" style="border:none;border-radius:0;">
                                        <i class="fa fa-code-branch"></i>
                                        <p>No branches found. Add one to get started.</p>
                                    </div>
                                </td>
                            </tr>`;
                    }
                }, 420);
            }
        } else {
            showErr('del_err', data.message || 'Could not delete branch.');
        }
    })
    .catch(() => {
        setLoading('del_spin', 'del_icon', 'del_btn', false);
        showErr('del_err', 'Network error. Please try again.');
    });
}

// -- Helpers --------------------------------------------------------------------
function escHtml(str) {
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function buildRow(id, num, name, location) {
    const loc = location || '-';
    return `
        <td class="cell-num">${num}</td>
        <td><span class="fw-600" style="color:var(--text);font-size:.86rem;">${escHtml(name)}</span></td>
        <td class="cell-sm">
            <i class="fa fa-location-dot" style="color:var(--gold);font-size:.65rem;margin-right:3px;"></i>
            ${escHtml(loc)}
        </td>
        <td>
            <div class="row-actions" style="justify-content:flex-end;">
                <button class="ia-btn ia-view" title="Edit"
                    onclick="openEditModal(${id},'${escHtml(name).replace(/'/g,"\\'")}','${escHtml(loc).replace(/'/g,"\\'")}')">
                    <i class="fa fa-pen"></i>
                </button>
                <button class="ia-btn ia-del" title="Delete"
                    onclick="openDeleteModal(${id},'${escHtml(name).replace(/'/g,"\\'")}')">
                    <i class="fa fa-trash"></i>
                </button>
            </div>
        </td>`;
}
</script>