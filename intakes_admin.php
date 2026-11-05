<?php
require_once 'scripts/connector.php';

$intakes_sql = "
    SELECT i.id, i.name, i.type, i.date_launching, i.date_ending,
           i.branch_id,
           b.name     AS branch_name,
           b.location AS branch_location
    FROM intakes i
    LEFT JOIN branches b ON b.id = i.branch_id
    ORDER BY i.date_launching DESC
";
$intakes_result = $conn->query($intakes_sql);
$intake_count   = $intakes_result ? $intakes_result->num_rows : 0;

// Course categories for Add modal
$categories_result = $conn->query("SELECT id, name FROM course_category ORDER BY name ASC");

// Branches for both modals
$branches_result = $conn->query("SELECT id, name FROM branches ORDER BY name ASC");
$branches = [];
if ($branches_result) {
    while ($b = $branches_result->fetch_assoc()) $branches[] = $b;
}
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


<div class="page-heading">
    <div>
        <h2><?php echo htmlspecialchars($systemname ?? 'System'); ?> <em>Intakes</em></h2>
        <div class="heading-meta">
            <span class="meta-pill">
                <i class="fa fa-house"></i>
                <a href="./" style="text-decoration:none;color:inherit;">Home</a>
            </span>
            <span class="meta-pill"><i class="fa fa-book"></i> Courses</span>
            <span class="meta-pill" style="background:var(--maroon-dim);color:var(--maroon);border-color:rgba(122,25,25,.18);">
                <i class="fa fa-layer-group"></i> Intakes
            </span>
        </div>
    </div>
    <button class="btn-export" onclick="openAddModal()">
        <i class="fa fa-plus"></i> Add Intake
    </button>
</div>


<div class="tbl-card">

    <div class="tbl-toolbar">
        <span class="tbl-title">
            <i class="fa fa-layer-group tbl-title-icon"></i> All Intakes
        </span>
        <div style="display:flex;align-items:center;gap:12px;">
            <span class="count-pill"><?php echo number_format($intake_count); ?> intake<?php echo $intake_count !== 1 ? 's' : ''; ?></span>
            <span style="font-size:.72rem;color:var(--muted);display:flex;align-items:center;gap:4px;">
                <i class="fa fa-clock" style="color:var(--gold);font-size:.65rem;"></i>
                <?php echo date('h:i a'); ?>
            </span>
        </div>
    </div>

    <div class="tbl-scroll">
        <table class="app-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th class="th-gold">Duration</th>
                    <th>Type</th>
                    <th>Branch</th>
                    <th>Location</th>
                    <th class="th-gold" style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$intakes_result || $intake_count < 1): ?>
                <tr>
                    <td colspan="7" style="padding:0;">
                        <div class="empty" style="border:none;border-radius:0;">
                            <i class="fa fa-layer-group"></i>
                            <p>No intakes found. Add one to get started.</p>
                        </div>
                    </td>
                </tr>
            <?php else:
                $num = 1;
                while ($d = $intakes_result->fetch_assoc()):
                    $type_label = ($d['type'] == '1') ? 'Certificate' : 'Diploma';
                    $launch     = $d['date_launching'] ? date('d M Y', strtotime($d['date_launching'])) : '-';
                    $ending     = $d['date_ending']    ? date('d M Y', strtotime($d['date_ending']))    : '-';
            ?>
                <tr>
                    <td class="cell-num"><?php echo $num++; ?></td>

                    <td>
                        <span class="fw-600" style="color:var(--text);font-size:.86rem;">
                            <?php echo htmlspecialchars($d['name']); ?>
                        </span>
                    </td>

                    <td style="white-space:nowrap;font-size:.8rem;color:var(--muted);">
                        <i class="fa fa-calendar-day" style="color:var(--gold);font-size:.7rem;"></i>
                        <?php echo $launch; ?>
                        &rarr;
                        <?php echo $ending; ?>
                    </td>

                    <td>
                        <span class="s-badge <?php echo $d['type'] == '1' ? 's-submitted' : 's-class'; ?>">
                            <?php echo $type_label; ?>
                        </span>
                    </td>

                    <td class="cell-sm"><?php echo htmlspecialchars($d['branch_name']    ?? '-'); ?></td>
                    <td class="cell-sm"><?php echo htmlspecialchars($d['branch_location'] ?? '-'); ?></td>

                    <td>
                        <div class="row-actions" style="justify-content:flex-end;">

                            <!-- Edit -->
                            <button class="ia-btn ia-view" title="Edit"
                                onclick="openEditModal(
                                    <?php echo (int)$d['id']; ?>,
                                    '<?php echo addslashes(htmlspecialchars($d['name'])); ?>',
                                    '<?php echo htmlspecialchars($d['date_launching']); ?>',
                                    '<?php echo htmlspecialchars($d['date_ending']); ?>',
                                    '<?php echo (int)$d['branch_id']; ?>'
                                )">
                                <i class="fa fa-pen"></i>
                            </button>

                            <!-- Delete -->
                            <button class="ia-btn ia-del" title="Delete"
                                onclick="openDeleteModal(
                                    <?php echo (int)$d['id']; ?>,
                                    '<?php echo addslashes(htmlspecialchars($d['name'])); ?>'
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
        <div class="legend">
            <span class="legend-item">
                <span class="legend-dot" style="background:var(--gold-lt);"></span> Certificate
            </span>
            <span class="legend-item">
                <span class="legend-dot legend-dot-muted"></span> Diploma
            </span>
        </div>
        <span><?php echo number_format($intake_count); ?> total intake<?php echo $intake_count !== 1 ? 's' : ''; ?></span>
    </div>
</div>



<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3><i class="fa fa-plus-circle"></i> Add New Intake</h3>
            <button class="modal-close" onclick="closeModal('addModal')" aria-label="Close">
                <i class="fa fa-xmark"></i>
            </button>
        </div>
        <form method="post" action="scripts/add_intake.php">
            <div class="modal-body" style="display:flex;flex-direction:column;gap:14px;">

                <div class="ctrl-field">
                    <label class="ctrl-label"><i class="fa fa-calendar"></i> Intake Date <span style="color:var(--red);">*</span></label>
                    <input type="date" name="dob" class="form-input" required>
                    <span class="field-hint"><i class="fa fa-circle-info"></i> This becomes the launch date for the intake.</span>
                </div>

                <div class="ctrl-field">
                    <label class="ctrl-label"><i class="fa fa-book"></i> Course Category <span style="color:var(--red);">*</span></label>
                    <select name="course" class="ctrl-select" style="min-width:100%;" required>
                        <option value="">Select Course Category</option>
                        <?php if ($categories_result): while ($cat = $categories_result->fetch_assoc()): ?>
                            <option value="<?php echo (int)$cat['id']; ?>">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>

                <div class="ctrl-field">
                    <label class="ctrl-label"><i class="fa fa-code-branch"></i> Branch <span style="color:var(--red);">*</span></label>
                    <select name="branch_id" class="ctrl-select" style="min-width:100%;" required>
                        <option value="">Select Branch</option>
                        <?php foreach ($branches as $br): ?>
                            <option value="<?php echo (int)$br['id']; ?>">
                                <?php echo htmlspecialchars($br['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>
            <div class="modal-foot">
                <button type="button" class="btn-modal-cancel" onclick="closeModal('addModal')">
                    <i class="fa fa-xmark"></i> Cancel
                </button>
                <button type="submit" name="submit1" class="btn-export">
                    <i class="fa fa-plus"></i> Add Intake
                </button>
            </div>
        </form>
    </div>
</div>



<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3><i class="fa fa-pen"></i> Edit Intake</h3>
            <button class="modal-close" onclick="closeModal('editModal')" aria-label="Close">
                <i class="fa fa-xmark"></i>
            </button>
        </div>
        <form method="post" action="scripts/editintake.php">
            <input type="hidden" name="intake_id" id="edit_intake_id">
            <div class="modal-body" style="display:flex;flex-direction:column;gap:14px;">

                <div class="ctrl-field">
                    <label class="ctrl-label"><i class="fa fa-tag"></i> Intake Name <span style="color:var(--red);">*</span></label>
                    <input type="text" id="edit_name" name="name" class="form-input" required>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="ctrl-field">
                        <label class="ctrl-label"><i class="fa fa-calendar-day"></i> Launch Date <span style="color:var(--red);">*</span></label>
                        <input type="date" id="edit_date_launching" name="date_launching" class="form-input" required>
                    </div>
                    <div class="ctrl-field">
                        <label class="ctrl-label"><i class="fa fa-calendar-check"></i> End Date <span style="color:var(--red);">*</span></label>
                        <input type="date" id="edit_date_ending" name="date_ending" class="form-input" required>
                    </div>
                </div>

                <div class="ctrl-field">
                    <label class="ctrl-label"><i class="fa fa-code-branch"></i> Branch <span style="color:var(--red);">*</span></label>
                    <select id="edit_branch_id" name="branch_id" class="ctrl-select" style="min-width:100%;" required>
                        <option value="">Select Branch</option>
                        <?php foreach ($branches as $br): ?>
                            <option value="<?php echo (int)$br['id']; ?>">
                                <?php echo htmlspecialchars($br['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>
            <div class="modal-foot">
                <button type="button" class="btn-modal-cancel" onclick="closeModal('editModal')">
                    <i class="fa fa-xmark"></i> Cancel
                </button>
                <button type="submit" name="submit" class="btn-export">
                    <i class="fa fa-floppy-disk"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>



<div class="modal-overlay" id="deleteModal">
    <div class="modal-box modal-box-sm">
        <div class="modal-head">
            <h3><i class="fa fa-trash"></i> Delete Intake</h3>
            <button class="modal-close" onclick="closeModal('deleteModal')" aria-label="Close">
                <i class="fa fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="del-warn">
                <i class="fa fa-triangle-exclamation del-warn-icon"></i>
                <div class="del-warn-text">
                    Are you sure you want to delete
                    <strong id="delete_intake_name">&hellip;</strong>?
                    This action cannot be undone.
                </div>
            </div>
            <p class="del-note">
                <i class="fa fa-circle-info"></i>
                Any students or courses linked to this intake may be affected.
            </p>
        </div>
        <div class="modal-foot">
            <span class="err-msg" id="del_err"></span>
            <button type="button" class="btn-modal-cancel" onclick="closeModal('deleteModal')">
                <i class="fa fa-xmark"></i> Cancel
            </button>
            <a id="delete_confirm_btn" href="#" class="btn-modal-delete">
                <i class="fa fa-trash"></i> Delete
            </a>
        </div>
    </div>
</div>



<script>

function openModal(id)  { document.getElementById(id).classList.add('is-open');    document.body.style.overflow = 'hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('is-open'); document.body.style.overflow = ''; }

// Close on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
        if (e.target === overlay) closeModal(overlay.id);
    });
});

// Close on Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.is-open').forEach(m => closeModal(m.id));
    }
});


function openAddModal() {
    openModal('addModal');
}

function openEditModal(id, name, launch, ending, branchId) {
    document.getElementById('edit_intake_id').value    = id;
    document.getElementById('edit_name').value         = name;
    document.getElementById('edit_date_launching').value = launch;
    document.getElementById('edit_date_ending').value  = ending;

    const sel = document.getElementById('edit_branch_id');
    sel.value = branchId;
    // Fallback in case value didn't match
    if (!sel.value) sel.selectedIndex = 0;

    openModal('editModal');
}


function openDeleteModal(id, name) {
    document.getElementById('delete_intake_name').textContent = name;
    document.getElementById('delete_confirm_btn').href =
        'scripts/removeintake.php?intake=' + encodeURIComponent(id) + '&vf=intakes';
    openModal('deleteModal');
}
</script>