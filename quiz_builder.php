<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$quizId = (int)($_GET['quiz_id'] ?? 0);

if ($quizId <= 0) {
    echo '<div class="app-alert app-alert-danger"><i class="fas fa-exclamation-circle"></i> Invalid quiz.</div>';
    return;
}

$stmt = $conn->prepare("
    SELECT
        q.*,
        c.name AS course_name,
        cu.name AS module_name
    FROM module_quizzes q
    INNER JOIN courses c ON c.id = q.course_id
    INNER JOIN course_unit cu ON cu.id = q.course_unit_id
    WHERE q.id = ?
    LIMIT 1
");

if (!$stmt) {
    echo '<div class="app-alert app-alert-danger">Unable to load quiz: ' . h($conn->error) . '</div>';
    return;
}

$stmt->bind_param('i', $quizId);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quiz) {
    echo '<div class="app-alert app-alert-danger"><i class="fas fa-exclamation-circle"></i> Quiz not found.</div>';
    return;
}

$questions = [];

$stmt = $conn->prepare("
    SELECT *
    FROM quiz_questions
    WHERE quiz_id = ?
    ORDER BY order_number ASC, id ASC
");

if ($stmt) {
    $stmt->bind_param('i', $quizId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($result && ($row = $result->fetch_assoc())) {
        $questions[] = $row;
    }

    $stmt->close();
}
?>

<link rel="stylesheet" href="app.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
.quiz-builder-page{
    font-size:1rem;
}
.quiz-builder-page .page-heading h2{
    font-size:1.55rem;
}
.quiz-builder-page .heading-meta,
.quiz-builder-page .meta-pill{
    font-size:.9rem;
}
.quiz-builder-page h3{
    font-size:1.18rem;
    margin-bottom:14px;
}
.quiz-question-card{
    padding:16px;
    border:1px solid var(--border);
    border-left:4px solid var(--maroon);
    border-radius:10px;
    margin:10px 0;
    background:var(--surface);
}
.quiz-question-card strong{
    display:block;
    font-size:1rem;
    line-height:1.5;
    color:var(--text);
}
.quiz-question-meta{
    font-size:.88rem;
    color:var(--muted);
    margin-top:6px;
}
.quiz-builder-page .ctrl-label,
.quiz-builder-page label{
    font-size:.92rem;
    font-weight:600;
}
.quiz-builder-page .form-input{
    font-size:.95rem;
    min-height:44px;
}
.quiz-builder-page textarea.form-input{
    min-height:110px;
}
.quiz-builder-page .btn-export{
    font-size:.92rem;
    padding:10px 16px;
}
.q-option-row{
    display:flex;
    align-items:center;
    gap:10px;
    margin:8px 0;
}
.q-option-row input[type="radio"]{
    width:18px;
    height:18px;
    flex:0 0 auto;
}

/* Custom popup */
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
    font-size:1.15rem;
    flex:0 0 46px;
}
.custom-popup-icon.success{background:#e9f7ef;color:#198754;}
.custom-popup-icon.error{background:#fdecec;color:#b42318;}
.custom-popup-title{
    font-size:1.08rem;
    font-weight:700;
    color:var(--text);
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

.quiz-back-btn{
    display:inline-flex;
    align-items:center;
    gap:7px;
    padding:9px 14px;
    border:1px solid var(--border);
    border-radius:8px;
    background:var(--surface);
    color:var(--maroon);
    text-decoration:none;
    font-size:.92rem;
    font-weight:700;
    cursor:pointer;
    transition:background .18s, transform .18s;
}
.quiz-back-btn:hover{
    background:var(--surface2);
    transform:translateY(-1px);
}
.quiz-heading-row{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
}

</style>

<main class="page quiz-builder-page">

    <div class="page-heading">
        <div class="quiz-heading-row" style="width:100%;">
            <div>
                <h2><i class="fas fa-list-check"></i> <?php echo h($quiz['title']); ?></h2>

                <div class="heading-meta">
                    <span class="meta-pill">
                        <i class="fas fa-graduation-cap"></i>
                        <?php echo h($quiz['course_name']); ?>
                    </span>

                    <span class="meta-pill">
                        <i class="fas fa-layer-group"></i>
                        <?php echo h($quiz['module_name']); ?>
                    </span>
                </div>
            </div>

            <button
                type="button"
                class="quiz-back-btn"
                onclick="goBackToCourse()"
                title="Back to previous page"
            >
                <i class="fas fa-arrow-left"></i>
                Back
            </button>
        </div>
    </div>

    <div class="dash-content-card" style="padding:20px;">
        <h3>
            <i class="fas fa-question-circle"></i>
            Questions (<?php echo count($questions); ?>)
        </h3>

        <?php if (empty($questions)): ?>
            <div class="empty">
                <i class="fas fa-question-circle"></i>
                <p>No questions have been added yet.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($questions as $i => $question): ?>
            <div class="quiz-question-card">
                <strong>
                    <?php echo $i + 1; ?>.
                    <?php echo h($question['question_text']); ?>
                </strong>

                <div class="quiz-question-meta">
                    <i class="fas fa-tag"></i>
                    <?php echo h(ucwords(str_replace('_', ' ', (string)$question['question_type']))); ?>
                    &nbsp;.&nbsp;
                    <i class="fas fa-star"></i>
                    <?php echo h((string)$question['points']); ?> point(s)
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="dash-content-card" style="padding:20px;margin-top:16px;">
        <h3><i class="fas fa-plus-circle"></i> Add Question</h3>

        <form id="qForm">
            <input type="hidden" name="quiz_id" value="<?php echo $quizId; ?>">

            <div class="ctrl-field">
                <label class="ctrl-label" for="qt">Question Type</label>

                <select
                    class="form-input"
                    name="question_type"
                    id="qt"
                    onchange="toggleQ()"
                >
                    <option value="multiple_choice">Multiple Choice</option>
                    <option value="true_false">True / False</option>
                    <option value="short_answer">Short Answer</option>
                </select>
            </div>

            <div class="ctrl-field">
                <label class="ctrl-label">Question</label>

                <textarea
                    class="form-input"
                    name="question_text"
                    rows="4"
                    required
                ></textarea>
            </div>

            <div class="ctrl-field">
                <label class="ctrl-label">Points</label>

                <input
                    class="form-input"
                    type="number"
                    step="0.01"
                    min="0"
                    name="points"
                    value="1"
                    required
                >
            </div>

            <div id="mc">
                <label>Options</label>

                <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="q-option-row">
                        <input
                            type="radio"
                            name="correct_option"
                            value="<?php echo $i; ?>"
                            <?php echo $i === 0 ? 'checked' : ''; ?>
                        >

                        <input
                            class="form-input"
                            name="options[]"
                            placeholder="Option <?php echo $i + 1; ?>"
                        >
                    </div>
                <?php endfor; ?>
            </div>

            <div id="tf" style="display:none;">
                <label>Correct Answer</label>

                <select class="form-input" name="correct_answer" id="tfAnswer">
                    <option value="True">True</option>
                    <option value="False">False</option>
                </select>
            </div>

            <div id="sa" style="display:none;">
                <label>Expected Answer</label>

                <input
                    class="form-input"
                    name="short_correct_answer"
                    id="saAnswer"
                    placeholder="Enter the expected answer"
                >
            </div>

            <button
                type="submit"
                class="btn-export"
                id="addQuestionBtn"
                style="margin-top:14px;"
            >
                <i class="fas fa-plus"></i>
                Add Question
            </button>
        </form>
    </div>

</main>

<div class="custom-popup-overlay" id="messagePopup" aria-hidden="true">
    <div class="custom-popup" role="dialog" aria-modal="true">
        <div class="custom-popup-head">
            <div class="custom-popup-icon success" id="popupIcon">
                <i class="fas fa-check"></i>
            </div>
            <div class="custom-popup-title" id="popupTitle">Success</div>
        </div>

        <div class="custom-popup-body" id="popupMessage"></div>

        <div class="custom-popup-foot">
            <button type="button" class="custom-popup-btn" id="popupOk">
                OK
            </button>
        </div>
    </div>
</div>

<script>
'use strict';


function goBackToCourse() {
    if (document.referrer && document.referrer !== window.location.href) {
        window.history.back();
        return;
    }

    window.location.href =
        '?view=open_coursex&course=<?php echo (int)$quiz['course_id']; ?>' +
        '<?php echo !empty($quiz['intake_id']) ? '&ausydfgasuidgipppuwqu=' . (int)$quiz['intake_id'] : ''; ?>';
}

function toggleQ() {
    const type = document.getElementById('qt').value;

    document.getElementById('mc').style.display =
        type === 'multiple_choice' ? '' : 'none';

    document.getElementById('tf').style.display =
        type === 'true_false' ? '' : 'none';

    document.getElementById('sa').style.display =
        type === 'short_answer' ? '' : 'none';
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
            document.body.style.overflow = '';
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

document.getElementById('qForm').addEventListener('submit', async function (event) {
    event.preventDefault();

    const form = event.currentTarget;
    const button = document.getElementById('addQuestionBtn');
    const oldHtml = button.innerHTML;

    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    try {
        const formData = new FormData(form);

        if (document.getElementById('qt').value === 'short_answer') {
            formData.set(
                'correct_answer',
                document.getElementById('saAnswer').value
            );
        }

        const response = await fetch('scripts/save_quiz_question.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const result = await readJson(response);

        if (result.success) {
            await showPopup(
                result.message || 'Question added successfully.',
                'success'
            );

            window.location.reload();
        } else {
            await showPopup(
                result.message || 'Unable to add question.',
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

document.getElementById('messagePopup').addEventListener('click', function (event) {
    if (event.target === this) {
        document.getElementById('popupOk').click();
    }
});

document.addEventListener('keydown', function (event) {
    if (
        event.key === 'Escape' &&
        document.getElementById('messagePopup').classList.contains('is-open')
    ) {
        document.getElementById('popupOk').click();
    }
});

toggleQ();
</script>
