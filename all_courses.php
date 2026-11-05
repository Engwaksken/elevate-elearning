<?php

$courses_sql = "
    SELECT c.id, c.name, c.short_code, c.duration, c.category,
           cc.name AS category_name
    FROM courses c
    LEFT JOIN course_category cc ON cc.id = c.category
    ORDER BY cc.name ASC, c.name ASC
";
$courses_result = $conn->query($courses_sql);
$course_count   = $courses_result ? $courses_result->num_rows : 0;
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
<!-- -- Page heading ------------------------------------------------------------- -->
<div class="page-heading">
    <div>
        <h2><?php echo htmlspecialchars($systemname ?? 'System'); ?> <em>Courses</em></h2>
        <div class="heading-meta">
            <span class="meta-pill">
                <i class="fa fa-house"></i>
                <a href="index.php" style="text-decoration:none;color:inherit;">Home</a>
            </span>
            <span class="meta-pill"><i class="fa fa-book"></i> Courses</span>
            <span class="meta-pill" style="background:var(--maroon-dim);color:var(--maroon);border-color:rgba(122,25,25,.18);">
                <i class="fa fa-list"></i> All Courses
            </span>
        </div>
    </div>
    <span class="count-pill"><?php echo number_format($course_count); ?> course<?php echo $course_count !== 1 ? 's' : ''; ?></span>
</div>

<!-- -- Info badge --------------------------------------------------------------- -->
<div class="info-badge" style="margin-bottom:22px;">
    <i class="fa fa-circle-info"></i>
    <span>Select a course to view its <strong>notes</strong> and <strong>assignments</strong> in the panel on the right.</span>
</div>

<!-- -- Two-column layout: table + detail panel --------------------------------- -->
<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;">

    <!-- -- Course table ----------------------------------------------------- -->
    <div class="tbl-card">

        <div class="tbl-toolbar">
            <span class="tbl-title">
                <i class="fa fa-book-open tbl-title-icon"></i> Course List
            </span>
            <div style="display:flex;align-items:center;gap:12px;">
                <span class="count-pill"><?php echo number_format($course_count); ?></span>
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
                        <th>Course Name</th>
                        <th>Category</th>
                        <th class="th-gold">Duration</th>
                        <th class="th-gold" style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$courses_result || $course_count < 1): ?>
                    <tr>
                        <td colspan="5" style="padding:0;">
                            <div class="empty" style="border:none;border-radius:0;">
                                <i class="fa fa-book-open"></i>
                                <p>No courses found.</p>
                            </div>
                        </td>
                    </tr>
                <?php else:
                    $num = 1;
                    while ($d = $courses_result->fetch_assoc()):
                ?>
                    <tr id="course-row-<?php echo (int)$d['id']; ?>"
                        style="cursor:pointer;"
                        onclick="opencoursez(<?php echo (int)$d['id']; ?>, this)">

                        <td class="cell-num"><?php echo $num++; ?></td>

                        <td>
                            <span class="fw-600" style="color:var(--text);font-size:.86rem;">
                                <?php echo htmlspecialchars($d['name']); ?>
                            </span>
                            <?php if (!empty($d['short_code'])): ?>
                                <span class="course-code"><?php echo htmlspecialchars($d['short_code']); ?></span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="cat-tag">
                                <i class="fa fa-tag"></i>
                                <?php echo htmlspecialchars($d['category_name'] ?? '-'); ?>
                            </span>
                        </td>

                        <td class="cell-sm" style="white-space:nowrap;">
                            <i class="fa fa-clock" style="color:var(--gold);font-size:.65rem;margin-right:3px;"></i>
                            <?php echo htmlspecialchars($d['duration'] ?? '-'); ?>
                        </td>

                        <td>
                            <div class="row-actions" style="justify-content:flex-end;">
                                <a href="?view=editcourse&jhsagdjhasgdhasgiu=<?php echo (int)$d['id']; ?>"
                                   class="ia-btn" style="border-color:var(--gold);color:var(--gold);"
                                   title="Edit course"
                                   onclick="event.stopPropagation()">
                                    <i class="fa fa-pen"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>

        <div class="tbl-footer">
            <span><?php echo number_format($course_count); ?> total course<?php echo $course_count !== 1 ? 's' : ''; ?></span>
        </div>
    </div>

    <!-- -- Detail panel ----------------------------------------------------- -->
    <div id="course-detail-panel">
        <!-- Default / empty state -->
        <div class="tbl-card" id="course-detail-empty">
            <div class="tbl-toolbar">
                <span class="tbl-title">
                    <i class="fa fa-circle-info tbl-title-icon"></i> Course Details
                </span>
            </div>
            <div class="empty" style="border:none;">
                <i class="fa fa-hand-pointer"></i>
                <p>Click a course row to view its details, notes and assignments here.</p>
            </div>
        </div>

        <!-- Loaded content lands here -->
        <div id="ggcq"></div>
    </div>

</div>

<!-- -- Responsive: stack on narrow screens ------------------------------------- -->
<style>
@media (max-width: 900px) {
    #course-detail-panel { display: none; }
    div[style*="grid-template-columns:1fr 340px"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<!-- -- JS ---------------------------------------------------------------------- -->
<script>
let activeRow = null;

function opencoursez(courseId, rowEl) {
    if (!courseId) return;

    // Highlight selected row
    if (activeRow) activeRow.style.background = '';
    activeRow = rowEl;
    rowEl.style.background = 'var(--maroon-dim)';

    const panel = document.getElementById('ggcq');
    const empty = document.getElementById('course-detail-empty');

    // Loading state
    empty.style.display = 'none';
    panel.innerHTML = `
        <div class="tbl-card">
            <div class="tbl-toolbar">
                <span class="tbl-title">
                    <i class="fa fa-circle-info tbl-title-icon"></i> Course Details
                </span>
            </div>
            <div class="empty" style="border:none;">
                <i class="fa fa-spinner fa-spin" style="opacity:.4;font-size:1.8rem;"></i>
                <p style="margin-top:10px;">Loading&hellip;</p>
            </div>
        </div>`;

    fetch('scripts/getcoursedetails.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(courseId)
    })
    .then(r => r.text())
    .then(html => { panel.innerHTML = html; })
    .catch(() => {
        panel.innerHTML = `
            <div class="app-alert app-alert-danger">
                <i class="fa fa-triangle-exclamation"></i>
                Failed to load course details. Please try again.
            </div>`;
    });
}
</script>