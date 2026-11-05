<?php

$coursera = (int)($_GET['jhsagdjhasgdhasgiu'] ?? 0);

if ($coursera < 1) {
    echo '<div class="app-alert app-alert-danger">
            <i class="fa fa-circle-xmark"></i>
            Invalid course. <a href="?view=all_courses"
            style="color:inherit;font-weight:600;">Back to Courses</a>
          </div>';
    exit;
}


$flash_err = $_SESSION[$systemname . 'errormsg']   ?? null;
$flash_ok  = $_SESSION[$systemname . 'successmsg'] ?? null;
unset($_SESSION[$systemname . 'errormsg'], $_SESSION[$systemname . 'successmsg']);


$st = $conn->prepare("SELECT * FROM courses WHERE id = ? LIMIT 1");
$st->bind_param('i', $coursera);
$st->execute();
$course = $st->get_result()->fetch_assoc();
$st->close();

if (!$course) {
    echo '<div class="app-alert app-alert-danger">
            <i class="fa fa-circle-xmark"></i>
            Course not found. <a href="?view=all_courses"
            style="color:inherit;font-weight:600;">Back to Courses</a>
          </div>';
    exit;
}

$cats = $conn->query("SELECT id, name FROM course_category ORDER BY name ASC")
             ?->fetch_all(MYSQLI_ASSOC) ?? [];
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
        <h2>Edit <em>Course</em></h2>
        <div class="heading-meta">
            <span class="meta-pill">
                <i class="fa fa-house"></i>
                <a href="?" style="text-decoration:none;color:inherit;">Home</a>
            </span>
            <span class="meta-pill">
                <i class="fa fa-book"></i>
                <a href="?view=all_courses"
                   style="text-decoration:none;color:inherit;">Courses</a>
            </span>
            <span class="meta-pill"
                  style="background:var(--maroon-dim);color:var(--maroon);
                         border-color:rgba(122,25,25,.18);">
                <i class="fa fa-pen"></i> Edit Course
            </span>
        </div>
    </div>
   
    <div style="display:flex;align-items:center;gap:8px;">
        <?php if (!empty($course['short_code'])): ?>
            <span class="s-badge s-class">
                <?php echo htmlspecialchars($course['short_code']); ?>
            </span>
        <?php endif; ?>
        <span class="count-pill">ID #<?php echo $coursera; ?></span>
    </div>
</div>

<?php if ($flash_err): ?>
    <div class="app-alert app-alert-danger" style="margin-bottom:18px;">
        <i class="fa fa-circle-xmark"></i>
        <?php echo htmlspecialchars($flash_err); ?>
    </div>
<?php elseif ($flash_ok): ?>
    <div class="app-alert app-alert-success" style="margin-bottom:18px;">
        <i class="fa fa-circle-check"></i>
        <?php echo htmlspecialchars($flash_ok); ?>
    </div>
<?php endif; ?>


<div style="max-width:660px;margin:0 auto;">
    <div class="tbl-card">

        <div class="tbl-toolbar">
            <span class="tbl-title">
                <i class="fa fa-pen tbl-title-icon"></i>
                <?php echo htmlspecialchars($course['name']); ?>
            </span>
            <span style="font-size:.72rem;color:var(--muted);
                         display:flex;align-items:center;gap:4px;">
                <i class="fa fa-asterisk"
                   style="color:var(--red);font-size:.55rem;"></i>
                Required fields
            </span>
        </div>

        <form method="POST" action="scripts/edit_course.php"
              id="editCourseForm" style="padding:22px 24px;">

            <input type="hidden" name="course_id"
                   value="<?php echo $coursera; ?>">

       
            <div class="ctrl-field" style="margin-bottom:16px;">
                <label class="ctrl-label" for="category">
                    <i class="fa fa-tag"></i>
                    Course Category <span style="color:var(--red);">*</span>
                </label>
                <select id="category" name="category"
                        class="ctrl-select" style="min-width:100%;" required>
                    <option value="">Select Category</option>
                    <?php foreach ($cats as $cat): ?>
                        <option value="<?php echo (int)$cat['id']; ?>"
                            <?php echo (int)$cat['id'] === (int)$course['category']
                                       ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;
                        gap:14px;margin-bottom:16px;">
                <div class="ctrl-field">
                    <label class="ctrl-label" for="course_name">
                        <i class="fa fa-book"></i>
                        Course Name <span style="color:var(--red);">*</span>
                    </label>
                    <input type="text" id="course_name" name="course_name"
                           class="form-input"
                           placeholder="e.g. Web Development"
                           value="<?php echo htmlspecialchars($course['name'] ?? ''); ?>"
                           required>
                </div>
                <div class="ctrl-field">
                    <label class="ctrl-label" for="course_code">
                        <i class="fa fa-hashtag"></i>
                        Course Code <span style="color:var(--red);">*</span>
                    </label>
                    <input type="text" id="course_code" name="course_code"
                           class="form-input"
                           placeholder="e.g. WD-101"
                           value="<?php echo htmlspecialchars($course['short_code'] ?? ''); ?>"
                           required>
                </div>
            </div>

           
            <div style="display:grid;grid-template-columns:1fr 1fr;
                        gap:14px;margin-bottom:16px;">
                <div class="ctrl-field">
                    <label class="ctrl-label" for="duration">
                        <i class="fa fa-clock"></i>
                        Duration <span style="color:var(--red);">*</span>
                    </label>
                    <input type="text" id="duration" name="duration"
                           class="form-input"
                           placeholder="e.g. 3 Months"
                           value="<?php echo htmlspecialchars($course['duration'] ?? ''); ?>"
                           required>
                    <span class="field-hint">
                        <i class="fa fa-circle-info"></i>
                        Include the unit, e.g. "6 Weeks"
                    </span>
                </div>
                <div class="ctrl-field">
                    <label class="ctrl-label" for="fees">
                        <i class="fa fa-coins"></i>
                        Fees (UGX) <span style="color:var(--red);">*</span>
                    </label>
                    <input type="number" id="fees" name="fees"
                           class="form-input"
                           placeholder="e.g. 500000" min="0"
                           value="<?php echo htmlspecialchars($course['fees'] ?? ''); ?>"
                           required>
                    <span class="field-hint">
                        <i class="fa fa-circle-info"></i>
                        Full course cost in Uganda Shillings
                    </span>
                </div>
            </div>

          
            <div class="ctrl-field" style="margin-bottom:24px;">
                <label class="ctrl-label" for="shortnotes">
                    <i class="fa fa-align-left"></i> Short Notes
                </label>
                <textarea id="shortnotes" name="shortnotes"
                          class="form-input" rows="4"
                          placeholder="Any additional notes about this course…"><?php
                    echo htmlspecialchars($course['short_notes'] ?? $course['shortnotes'] ?? '');
                ?></textarea>
                <div class="field-hint"
                     style="justify-content:space-between;">
                    <span>
                        <i class="fa fa-circle-info"></i>
                        Optional - shown to students on the course listing.
                    </span>
                    <span class="char-counter" id="notesCounter">
                        <?php
                        $existing = strlen($course['short_notes']
                                          ?? $course['shortnotes'] ?? '');
                        echo $existing;
                        ?> / 500
                    </span>
                </div>
            </div>

            <div style="display:flex;align-items:center;
                        justify-content:flex-end;gap:10px;
                        padding-top:16px;
                        border-top:1px solid var(--border);">
                <a href="?view=all_courses" class="btn-modal-cancel">
                    <i class="fa fa-xmark"></i> Cancel
                </a>
                <button type="submit" name="update" class="btn-export"
                        id="submitBtn">
                    <span class="spin" id="submitSpin"></span>
                    <i class="fa fa-floppy-disk" id="submitIcon"></i>
                    Update Course
                </button>
            </div>

        </form>
    </div>

    <!-- Danger zone -->
    <div class="tbl-card" style="margin-top:20px;border-color:var(--red);">
        <div class="tbl-toolbar"
             style="background:var(--red-bg);border-bottom-color:rgba(168,21,21,.15);">
            <span class="tbl-title" style="color:var(--red);">
                <i class="fa fa-triangle-exclamation"
                   style="color:var(--red);"></i> Danger Zone
            </span>
        </div>
        <div style="padding:16px 20px;display:flex;
                    align-items:center;justify-content:space-between;
                    flex-wrap:wrap;gap:12px;">
            <div>
                <div class="fw-600"
                     style="font-size:.86rem;color:var(--text);">
                    Delete this course
                </div>
                <div style="font-size:.76rem;color:var(--muted);margin-top:2px;">
                    Permanently removes all units, notes and assignments.
                    This cannot be undone.
                </div>
            </div>
            <button class="btn-modal-delete"
                    onclick="openDeleteModal()">
                <i class="fa fa-trash"></i> Delete Course
            </button>
        </div>
    </div>
</div>


<div class="modal-overlay" id="deleteModal">
    <div class="modal-box modal-box-sm">
        <div class="modal-head">
            <h3><i class="fa fa-trash"></i> Delete Course</h3>
            <button class="modal-close"
                    onclick="closeModal('deleteModal')"
                    aria-label="Close">
                <i class="fa fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="del-warn">
                <i class="fa fa-triangle-exclamation del-warn-icon"></i>
                <div class="del-warn-text">
                    Are you sure you want to delete
                    <strong><?php echo htmlspecialchars($course['name']); ?></strong>?
                    This cannot be undone.
                </div>
            </div>
            <p class="del-note">
                <i class="fa fa-circle-info"></i>
                All units, notes and assignments linked to this course
                will be permanently removed.
            </p>
        </div>
        <div class="modal-foot">
            <span class="err-msg" id="del_err">
                <i class="fa fa-circle-xmark"></i> <span></span>
            </span>
            <button type="button" class="btn-modal-cancel"
                    onclick="closeModal('deleteModal')">
                <i class="fa fa-xmark"></i> Cancel
            </button>
            <button type="button" class="btn-modal-delete"
                    id="del_btn" onclick="deleteCourse()">
                <span class="spin"     id="del_spin"></span>
                <i class="fa fa-trash" id="del_icon"></i> Delete
            </button>
        </div>
    </div>
</div>

<div class="toast-bar" id="toastBar"></div>


<script>

function openModal(id)  {
    document.getElementById(id).classList.add('is-open');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).classList.remove('is-open');
    document.body.style.overflow = '';
}
document.querySelectorAll('.modal-overlay').forEach(o =>
    o.addEventListener('click', e => { if (e.target === o) closeModal(o.id); })
);
document.addEventListener('keydown', e => {
    if (e.key === 'Escape')
        document.querySelectorAll('.modal-overlay.is-open')
                .forEach(m => closeModal(m.id));
});

function openDeleteModal() { openModal('deleteModal'); }


function toast(msg, type = 'success') {
    const bar = document.getElementById('toastBar');
    const el  = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.innerHTML = `<i class="fa fa-${type === 'success'
                    ? 'circle-check' : 'circle-xmark'}"></i> ${msg}`;
    bar.appendChild(el);
    setTimeout(() => el.classList.add('dismissing'), 4200);
    setTimeout(() => el.remove(), 4600);
}


function showErr(id, msg) {
    const el = document.getElementById(id);
    el.querySelector('span').textContent = msg;
    el.classList.add('visible');
}
function clearErr(id) { document.getElementById(id).classList.remove('visible'); }

function deleteCourse() {
    clearErr('del_err');
    const btn  = document.getElementById('del_btn');
    const spin = document.getElementById('del_spin');
    const icon = document.getElementById('del_icon');

    btn.disabled = true;
    spin.classList.add('active');
    icon.style.display = 'none';

    fetch('scripts/delete_course.php', {
        method : 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body   : 'course_id=<?php echo $coursera; ?>'
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        spin.classList.remove('active');
        icon.style.display = '';

        if (data.success) {
            closeModal('deleteModal');
            toast(data.message || 'Course deleted.');
            // Redirect to course list after short delay
            setTimeout(() => {
                window.location.href = '?view=all_courses';
            }, 1400);
        } else {
            showErr('del_err', data.message || 'Could not delete course.');
        }
    })
    .catch(() => {
        btn.disabled = false;
        spin.classList.remove('active');
        icon.style.display = '';
        showErr('del_err', 'Network error. Please try again.');
    });
}

(function () {
    const area    = document.getElementById('shortnotes');
    const counter = document.getElementById('notesCounter');
    const MAX     = 500;
    if (!area || !counter) return;

    area.addEventListener('input', function () {
        const len = this.value.length;
        counter.textContent = `${len} / ${MAX}`;
        counter.className = 'char-counter' +
            (len >= MAX      ? ' limit' :
             len >= MAX * .8 ? ' warn'  : '');
        if (len > MAX) this.value = this.value.slice(0, MAX);
    });
})();


document.getElementById('editCourseForm')
        .addEventListener('submit', function () {
    const btn  = document.getElementById('submitBtn');
    const spin = document.getElementById('submitSpin');
    const icon = document.getElementById('submitIcon');
    btn.disabled       = true;
    spin.classList.add('active');
    icon.style.display = 'none';
});
</script>