<?php

$flash_err = $_SESSION[$systemname . 'errormsg']   ?? null;
$flash_ok  = $_SESSION[$systemname . 'successmsg'] ?? null;
unset($_SESSION[$systemname . 'errormsg'], $_SESSION[$systemname . 'successmsg']);


$cats_result = $conn->query("SELECT id, name FROM course_category ORDER BY name ASC");
$categories  = $cats_result ? $cats_result->fetch_all(MYSQLI_ASSOC) : [];
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
        <h2>Add <em>Course</em></h2>
        <div class="heading-meta">
            <span class="meta-pill">
                <i class="fa fa-house"></i>
                <a href="./" style="text-decoration:none;color:inherit;">Home</a>
            </span>
            <span class="meta-pill">
                <i class="fa fa-book"></i>
                <a href="?view=all_courses" style="text-decoration:none;color:inherit;">Courses</a>
            </span>
            <span class="meta-pill"
                  style="background:var(--maroon-dim);color:var(--maroon);
                         border-color:rgba(122,25,25,.18);">
                <i class="fa fa-plus"></i> Add Course
            </span>
        </div>
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
                <i class="fa fa-book-open tbl-title-icon"></i> Course Details
            </span>
            <span style="font-size:.72rem;color:var(--muted);
                         display:flex;align-items:center;gap:4px;">
                <i class="fa fa-asterisk"
                   style="color:var(--red);font-size:.55rem;"></i>
                Required fields
            </span>
        </div>

        <form method="POST" action="scripts/create_course.php"
              id="createCourseForm" style="padding:22px 24px;">

         
            <div class="ctrl-field" style="margin-bottom:16px;">
                <label class="ctrl-label" for="category">
                    <i class="fa fa-tag"></i>
                    Course Category <span style="color:var(--red);">*</span>
                </label>
                <select id="category" name="category"
                        class="ctrl-select" style="min-width:100%;" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo (int)$cat['id']; ?>">
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
                           placeholder="e.g. Web Development" required>
                </div>
                <div class="ctrl-field">
                    <label class="ctrl-label" for="course_code">
                        <i class="fa fa-hashtag"></i>
                        Course Code <span style="color:var(--red);">*</span>
                    </label>
                    <input type="text" id="course_code" name="course_code"
                           class="form-input"
                           placeholder="e.g. WD-101" required>
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
                           placeholder="e.g. 3 Months" required>
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
                           placeholder="e.g. 500000" min="0" required>
                    <span class="field-hint">
                        <i class="fa fa-circle-info"></i>
                        Enter amount in Uganda Shillings
                    </span>
                </div>
            </div>

           
            <div class="ctrl-field" style="margin-bottom:24px;">
                <label class="ctrl-label" for="shortnotes">
                    <i class="fa fa-align-left"></i> Short Notes
                </label>
                <textarea id="shortnotes" name="shortnotes"
                          class="form-input" rows="4"
                          placeholder="Any additional notes about this course..."
                          id="shortnotes_area"></textarea>
                <div class="field-hint" style="justify-content:space-between;">
                    <span><i class="fa fa-circle-info"></i> Optional - shown to students on the course listing.</span>
                    <span class="char-counter" id="notesCounter">0 / 500</span>
                </div>
            </div>

           
            <div style="display:flex;align-items:center;
                        justify-content:flex-end;gap:10px;
                        padding-top:16px;border-top:1px solid var(--border);">
                <a href="?view=all_courses" class="btn-modal-cancel">
                    <i class="fa fa-xmark"></i> Cancel
                </a>
                <button type="submit" name="create" class="btn-export"
                        id="submitBtn">
                    <span class="spin" id="submitSpin"></span>
                    <i class="fa fa-floppy-disk" id="submitIcon"></i>
                    Create Course
                </button>
            </div>

        </form>
    </div>

   
</div>


<script>

(function () {
    const area    = document.getElementById('shortnotes');
    const counter = document.getElementById('notesCounter');
    const MAX     = 500;

    if (!area || !counter) return;

    area.addEventListener('input', function () {
        const len = this.value.length;
        counter.textContent = `${len} / ${MAX}`;
        counter.className = 'char-counter' +
            (len >= MAX     ? ' limit' :
             len >= MAX * .8 ? ' warn'  : '');
        if (len > MAX) this.value = this.value.slice(0, MAX);
    });
})();


document.getElementById('createCourseForm')
        .addEventListener('submit', function () {
    const btn  = document.getElementById('submitBtn');
    const spin = document.getElementById('submitSpin');
    const icon = document.getElementById('submitIcon');

    btn.disabled         = true;
    spin.classList.add('active');
    icon.style.display   = 'none';
});
</script>