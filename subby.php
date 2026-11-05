<?php
declare(strict_types=1);

if (!function_exists('e')) {
    function e($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$module = (int)($_GET['hjadvhagsdjhsagsdhg'] ?? 0);
$assignment = (int)($_GET['piouglv'] ?? $_GET['assignment_id'] ?? 0);

if ($module <= 0 || $assignment <= 0) {
    echo '<div class="app-alert app-alert-danger"><i class="fas fa-exclamation-circle"></i> Invalid access.</div>';
    return;
}

$stmt = $conn->prepare("
    SELECT
        a.*,
        cu.name AS module_name,
        cu.course_id,
        c.name AS course_name
    FROM assignments a
    INNER JOIN course_unit cu ON cu.id = a.course_unit
    INNER JOIN courses c ON c.id = cu.course_id
    WHERE a.id = ?
      AND a.course_unit = ?
    LIMIT 1
");

if (!$stmt) {
    echo '<div class="app-alert app-alert-danger">Unable to load assignment: ' . e($conn->error) . '</div>';
    return;
}

$stmt->bind_param('ii', $assignment, $module);
$stmt->execute();
$a = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$a) {
    echo '<div class="app-alert app-alert-danger"><i class="fas fa-exclamation-circle"></i> Assignment not found.</div>';
    return;
}

$stmt = $conn->prepare("
    SELECT
        hi.*,
        u.fname,
        u.lname
    FROM handed_in hi
    INNER JOIN users u ON u.unique_id = hi.user_id
    WHERE hi.assignment_id = ?
    ORDER BY hi.date_handed_in DESC
");

$subs = [];

if ($stmt) {
    $stmt->bind_param('i', $assignment);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($result && ($row = $result->fetch_assoc())) {
        $subs[] = $row;
    }

    $stmt->close();
}

$total = count($subs);

$graded = count(
    array_filter(
        $subs,
        fn($row) =>
            ($row['status'] ?? '') === 'graded'
            || (
                $row['finalmarks'] !== null
                && $row['finalmarks'] !== ''
            )
    )
);

$pending = $total - $graded;
$rate = $total > 0 ? round(($graded / $total) * 100) : 0;
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="app.css">

<style>
.submissions-page{
    font-size:1rem;
}
.submissions-page .page-heading h2{
    font-size:1.5rem;
}
.submissions-page .heading-meta,
.submissions-page .meta-pill{
    font-size:.9rem;
}
.toolbarx{
    display:flex;
    gap:10px;
    align-items:center;
    flex-wrap:wrap;
}
.btnx{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    padding:9px 13px;
    border:0;
    border-radius:7px;
    text-decoration:none;
    cursor:pointer;
    font-size:.9rem;
    font-weight:600;
    font-family:inherit;
}
.green{background:#198754;color:#fff;}
.maroon{background:var(--maroon);color:#fff;}
.gold{background:var(--gold);color:#fff;}
.soft{background:#fff;border:1px solid var(--border);color:var(--text);}

.submissions-page .app-table{
    font-size:.9rem;
}
.submissions-page .app-table th{
    font-size:.86rem;
    padding:11px 10px;
}
.submissions-page .app-table td{
    font-size:.9rem;
    padding:11px 10px;
    line-height:1.45;
}
.submissions-page .tbl-title{
    font-size:1rem;
}
#search{
    min-width:230px;
    padding:9px 11px !important;
    font-size:.9rem;
}

/* Existing content modals */
.modal-overlay{
    position:fixed;
    inset:0;
    background:rgba(15,23,42,.60);
    z-index:99999;
    display:none;
    align-items:center;
    justify-content:center;
    padding:20px;
    overflow-y:auto;
}
.modal-overlay.is-open{display:flex;}
.modal-box{
    background:#fff;
    border-radius:12px;
    width:min(650px,100%);
    max-height:calc(100vh - 40px);
    overflow:hidden;
    display:flex;
    flex-direction:column;
}
.modal-box > form{
    display:flex;
    flex-direction:column;
    min-height:0;
    max-height:calc(100vh - 40px);
}
.modal-head,
.modal-foot{
    padding:15px 18px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    flex:0 0 auto;
}
.modal-head{
    background:var(--maroon);
    color:#fff;
    font-size:1rem;
}
.modal-body{
    padding:18px;
    overflow-y:auto;
    font-size:.94rem;
}
.modal-foot{
    justify-content:flex-end;
    gap:8px;
    border-top:1px solid var(--border);
    background:#fff;
}
.field{margin-bottom:14px;}
.field label{
    display:block;
    font-size:.92rem;
    font-weight:600;
    margin-bottom:6px;
}
.field input,
.field textarea{
    width:100%;
    padding:10px 11px;
    border:1px solid var(--border);
    border-radius:7px;
    font-size:.94rem;
}

/* Custom notification */
.custom-popup-overlay{
    position:fixed;
    inset:0;
    z-index:130000;
    display:none;
    align-items:center;
    justify-content:center;
    padding:20px;
    background:rgba(15,23,42,.60);
    backdrop-filter:blur(2px);
}
.custom-popup-overlay.is-open{display:flex;}
.custom-popup{
    width:min(440px,100%);
    background:#fff;
    border-radius:14px;
    overflow:hidden;
    box-shadow:0 25px 70px rgba(0,0,0,.30);
    animation:popupIn .18s ease-out;
}
.custom-popup-head{
    padding:18px 20px 10px;
    display:flex;
    align-items:center;
    gap:12px;
}
.custom-popup-icon{
    width:46px;
    height:46px;
    border-radius:50%;
    display:grid;
    place-items:center;
    flex:0 0 46px;
    font-size:1.15rem;
}
.custom-popup-icon.success{background:#e9f7ef;color:#198754;}
.custom-popup-icon.error{background:#fdecec;color:#b42318;}
.custom-popup-title{
    font-size:1.08rem;
    font-weight:700;
}
.custom-popup-body{
    padding:4px 20px 18px;
    font-size:.96rem;
    line-height:1.55;
    color:var(--muted);
}
.custom-popup-foot{
    display:flex;
    justify-content:flex-end;
    padding:14px 20px 18px;
    border-top:1px solid var(--border);
    background:var(--surface2);
}
.custom-popup-btn{
    border:0;
    border-radius:8px;
    padding:10px 18px;
    background:var(--maroon);
    color:#fff;
    cursor:pointer;
    font-size:.94rem;
    font-weight:700;
}
@keyframes popupIn{
    from{opacity:0;transform:translateY(8px) scale(.98)}
    to{opacity:1;transform:translateY(0) scale(1)}
}
</style>

<nav class="topbar">
    <div class="topbar-left">
        <h1>
            <i class="fas fa-inbox"></i>
            <?php echo e($a['course_name']); ?> — Submissions
        </h1>
    </div>

    <div class="topbar-right">
        <i class="fas fa-clock"></i>
        <?php echo date('h:i a'); ?>
    </div>
</nav>

<main class="page page-wide submissions-page">

    <div class="page-heading">
        <div>
            <h2><?php echo e($a['title']); ?></h2>

            <div class="heading-meta">
                <span class="meta-pill">
                    <i class="fas fa-layer-group"></i>
                    <?php echo e($a['module_name']); ?>
                </span>

                <span class="meta-pill">
                    <i class="fas fa-star"></i>
                    <?php echo e((string)$a['marks']); ?> points
                </span>
            </div>
        </div>
    </div>

    <div class="stats" style="margin-bottom:18px;">
        <div class="stat-card">
            <div class="stat-icon total"><i class="fas fa-inbox"></i></div>
            <div><div class="sv"><?php echo $total; ?></div><div class="sl">Total</div></div>
        </div>

        <div class="stat-card">
            <div class="stat-icon done"><i class="fas fa-check-circle"></i></div>
            <div><div class="sv green-text"><?php echo $graded; ?></div><div class="sl">Graded</div></div>
        </div>

        <div class="stat-card">
            <div class="stat-icon cancel"><i class="fas fa-hourglass-half"></i></div>
            <div><div class="sv"><?php echo $pending; ?></div><div class="sl">Pending</div></div>
        </div>

        <div class="stat-card">
            <div class="stat-icon prog"><i class="fas fa-percentage"></i></div>
            <div><div class="sv"><?php echo $rate; ?>%</div><div class="sl">Graded Rate</div></div>
        </div>
    </div>

    <div class="tbl-card">
        <div class="tbl-toolbar">
            <span class="tbl-title">
                <i class="fas fa-list-alt"></i>
                Student Submissions
            </span>

            <div class="toolbarx">
                <a
                    class="btnx green"
                    href="scripts/download_all_submissions.php?assignment_id=<?php echo $assignment; ?>"
                >
                    <i class="fas fa-file-zipper"></i>
                    Download All Submissions
                </a>

                <input
                    id="search"
                    placeholder="Search students..."
                >
            </div>
        </div>

        <div class="tbl-scroll">
            <table class="app-table" id="subTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Submission</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Grade</th>
                        <th>Feedback</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($subs as $i => $submission): ?>
                    <?php
                    $sid = (int)$submission['id'];
                    $student = trim(
                        ($submission['fname'] ?? '')
                        . ' '
                        . ($submission['lname'] ?? '')
                    );

                    $type = (string)($submission['submission_type'] ?? 'file');
                    $view = '';

                    if ($type === 'file' && !empty($submission['file_uploaded'])) {
                        $view = 'course_materials/'
                            . rawurlencode((string)$submission['file_uploaded']);
                    } elseif ($type === 'link') {
                        $view = (string)($submission['submission_url'] ?? '');
                    }

                    $gradeExists =
                        $submission['finalmarks'] !== null
                        && $submission['finalmarks'] !== '';
                    ?>

                    <tr
                        data-search="<?php echo e(strtolower(
                            $student . ' ' . $type . ' ' . ($submission['status'] ?? '')
                        )); ?>"
                    >
                        <td><?php echo $i + 1; ?></td>

                        <td><strong><?php echo e($student); ?></strong></td>

                        <td>
                            <?php if ($view): ?>
                                <a href="<?php echo e($view); ?>" target="_blank" rel="noopener">
                                    <i class="fas fa-up-right-from-square"></i>
                                    Open <?php echo e($type); ?>
                                </a>
                            <?php elseif ($type === 'text'): ?>
                                <button
                                    type="button"
                                    class="btnx soft"
                                    onclick="viewText(<?php
                                        echo e(json_encode((string)($submission['text_response'] ?? '')));
                                    ?>)"
                                >
                                    <i class="fas fa-align-left"></i>
                                    View text
                                </button>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php echo !empty($submission['date_handed_in'])
                                ? e(date(
                                    'd M Y, h:i a',
                                    strtotime((string)$submission['date_handed_in'])
                                ))
                                : '-'; ?>
                        </td>

                        <td>
                            <?php echo e(ucfirst((string)($submission['status'] ?? 'submitted'))); ?>
                        </td>

                        <td>
                            <?php echo $gradeExists
                                ? e((string)$submission['finalmarks'])
                                    . '/'
                                    . e((string)$a['marks'])
                                : 'Pending'; ?>
                        </td>

                        <td>
                            <?php echo e((string)($submission['feedback'] ?? '')); ?>
                        </td>

                        <td>
                            <button
                                type="button"
                                class="btnx maroon"
                                onclick="openGrade(
                                    <?php echo $sid; ?>,
                                    <?php echo e(json_encode($student)); ?>,
                                    <?php echo e(json_encode((string)($submission['finalmarks'] ?? ''))); ?>,
                                    <?php echo e(json_encode((string)($submission['feedback'] ?? ''))); ?>
                                )"
                            >
                                <i class="fas fa-star"></i>
                                Grade
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<div class="modal-overlay" id="gradeModal">
    <div class="modal-box">
        <form id="gradeForm">

            <div class="modal-head">
                <strong>
                    Grade Submission —
                    <span id="gradeStudent"></span>
                </strong>

                <button
                    type="button"
                    class="btnx soft"
                    onclick="closeGrade()"
                >
                   X
                </button>
            </div>

            <div class="modal-body">
                <input type="hidden" name="submission_id" id="gradeId">

                <div class="field">
                    <label>
                        Grade (out of <?php echo e((string)$a['marks']); ?>)
                    </label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        max="<?php echo e((string)$a['marks']); ?>"
                        name="grade"
                        id="gradeValue"
                        required
                    >
                </div>

                <div class="field">
                    <label>Feedback</label>

                    <textarea
                        name="feedback"
                        id="gradeFeedback"
                        rows="5"
                        placeholder="Good analysis. Consider adding..."
                    ></textarea>
                </div>
            </div>

            <div class="modal-foot">
                <button
                    type="button"
                    class="btnx soft"
                    onclick="closeGrade()"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="btnx gold"
                    id="gradeSubmitBtn"
                >
                    <i class="fas fa-paper-plane"></i>
                    Return Grade
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="textModal">
    <div class="modal-box">

        <div class="modal-head">
            <strong>Text Submission</strong>

            <button
                type="button"
                class="btnx soft"
                onclick="closeTextModal()"
            >
               X
            </button>
        </div>

        <div class="modal-body">
            <div id="textContent" style="white-space:pre-wrap;font-size:.96rem;line-height:1.6;"></div>
        </div>

        <div class="modal-foot">
            <button
                type="button"
                class="btnx soft"
                onclick="closeTextModal()"
            >
                Close
            </button>
        </div>
    </div>
</div>

<div class="custom-popup-overlay" id="messagePopup" aria-hidden="true">
    <div class="custom-popup">
        <div class="custom-popup-head">
            <div class="custom-popup-icon success" id="popupIcon">
                <i class="fas fa-check"></i>
            </div>

            <div class="custom-popup-title" id="popupTitle">
                Success
            </div>
        </div>

        <div class="custom-popup-body" id="popupMessage"></div>

        <div class="custom-popup-foot">
            <button
                type="button"
                class="custom-popup-btn"
                id="popupOk"
            >
                OK
            </button>
        </div>
    </div>
</div>

<script>
'use strict';

function openGrade(id, student, grade, feedback) {
    document.getElementById('gradeId').value = id;
    document.getElementById('gradeStudent').textContent = student;
    document.getElementById('gradeValue').value = grade;
    document.getElementById('gradeFeedback').value = feedback;

    document.getElementById('gradeModal').classList.add('is-open');
    document.body.style.overflow = 'hidden';
}

function closeGrade() {
    document.getElementById('gradeModal').classList.remove('is-open');
    document.body.style.overflow = '';
}

function viewText(text) {
    document.getElementById('textContent').textContent = text;
    document.getElementById('textModal').classList.add('is-open');
    document.body.style.overflow = 'hidden';
}

function closeTextModal() {
    document.getElementById('textModal').classList.remove('is-open');
    document.body.style.overflow = '';
}

function showPopup(message, type = 'success', title = '') {
    return new Promise(resolve => {
        const overlay = document.getElementById('messagePopup');
        const icon = document.getElementById('popupIcon');
        const titleEl = document.getElementById('popupTitle');
        const messageEl = document.getElementById('popupMessage');
        const ok = document.getElementById('popupOk');

        const success = type === 'success';

        icon.className = 'custom-popup-icon ' + (success ? 'success' : 'error');
        icon.innerHTML = '<i class="fas ' + (success ? 'fa-check' : 'fa-xmark') + '"></i>';

        titleEl.textContent = title || (success ? 'Success' : 'Error');
        messageEl.textContent = message || '';

        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        const close = () => {
            overlay.classList.remove('is-open');
            overlay.setAttribute('aria-hidden', 'true');

            if (
                !document.querySelector(
                    '#gradeModal.is-open, #textModal.is-open'
                )
            ) {
                document.body.style.overflow = '';
            }

            resolve();
        };

        ok.addEventListener('click', close, { once: true });
    });
}

async function readJson(response) {
    const raw = await response.text();

    try {
        return JSON.parse(raw);
    } catch (error) {
        throw new Error(
            raw
                ? 'Server returned an invalid response: ' + raw.substring(0, 180)
                : 'Server returned an empty response.'
        );
    }
}

document.getElementById('gradeForm').addEventListener('submit', async function (event) {
    event.preventDefault();

    const form = event.currentTarget;
    const button = document.getElementById('gradeSubmitBtn');
    const oldHtml = button.innerHTML;

    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    try {
        const response = await fetch('scripts/grade_submission.php', {
            method: 'POST',
            body: new FormData(form),
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const result = await readJson(response);

        if (result.success) {
            closeGrade();

            await showPopup(
                result.message || 'Grade returned successfully.',
                'success'
            );

            window.location.reload();
        } else {
            await showPopup(
                result.message || 'Unable to save the grade.',
                'error'
            );
        }
    } catch (error) {
        await showPopup(
            'Request failed: ' + error.message,
            'error'
        );
    } finally {
        button.disabled = false;
        button.innerHTML = oldHtml;
    }
});

document.getElementById('search').addEventListener('input', function () {
    const query = this.value.toLowerCase().trim();

    document.querySelectorAll('#subTable tbody tr').forEach(row => {
        row.style.display =
            !query || row.dataset.search.includes(query)
                ? ''
                : 'none';
    });
});

document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            if (modal.id === 'gradeModal') {
                closeGrade();
            }

            if (modal.id === 'textModal') {
                closeTextModal();
            }
        }
    });
});

document.getElementById('messagePopup').addEventListener('click', function (event) {
    if (event.target === this) {
        document.getElementById('popupOk').click();
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') {
        return;
    }

    if (document.getElementById('messagePopup').classList.contains('is-open')) {
        document.getElementById('popupOk').click();
        return;
    }

    if (document.getElementById('gradeModal').classList.contains('is-open')) {
        closeGrade();
        return;
    }

    if (document.getElementById('textModal').classList.contains('is-open')) {
        closeTextModal();
    }
});
</script>
