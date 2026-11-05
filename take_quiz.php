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
    SELECT *
    FROM module_quizzes
    WHERE id = ?
      AND status = 'active'
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
    echo '<div class="app-alert app-alert-danger"><i class="fas fa-exclamation-circle"></i> Quiz not available.</div>';
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

    while ($question = $result->fetch_assoc()) {
        $options = [];

        if (in_array(
            $question['question_type'],
            ['multiple_choice', 'true_false'],
            true
        )) {
            $optionStmt = $conn->prepare("
                SELECT id, option_text
                FROM quiz_options
                WHERE question_id = ?
                ORDER BY order_number ASC, id ASC
            ");

            if ($optionStmt) {
                $qid = (int)$question['id'];
                $optionStmt->bind_param('i', $qid);
                $optionStmt->execute();

                $optionResult = $optionStmt->get_result();

                while ($optionResult && ($option = $optionResult->fetch_assoc())) {
                    $options[] = $option;
                }

                $optionStmt->close();
            }
        }

        $question['options'] = $options;
        $questions[] = $question;
    }

    $stmt->close();
}
?>

<link rel="stylesheet" href="app.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
.take-quiz-page{
    font-size:1rem;
}
.take-quiz-page .page-heading h2{
    font-size:1.55rem;
}
.quiz-summary{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-top:8px;
}
.quiz-summary span{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:6px 10px;
    border-radius:16px;
    background:var(--surface2);
    border:1px solid var(--border);
    color:var(--muted);
    font-size:.9rem;
}
.quiz-card{
    padding:18px;
    margin-bottom:14px;
    border-left:4px solid var(--maroon);
}
.quiz-question{
    font-size:1.02rem;
    line-height:1.55;
    font-weight:700;
    color:var(--text);
}
.quiz-points{
    font-size:.88rem;
    color:var(--muted);
    margin:6px 0 12px;
}
.quiz-option{
    display:flex;
    align-items:flex-start;
    gap:9px;
    margin:9px 0;
    padding:9px 10px;
    border:1px solid var(--border);
    border-radius:8px;
    cursor:pointer;
    font-size:.96rem;
    line-height:1.45;
    background:var(--surface);
}
.quiz-option:hover{
    background:var(--surface2);
}
.quiz-option input{
    width:18px;
    height:18px;
    margin-top:2px;
    flex:0 0 auto;
}
.take-quiz-page textarea.form-input{
    font-size:.96rem;
    min-height:95px;
}
.submit-quiz-btn{
    font-size:.96rem;
    padding:11px 18px;
}

/* Custom result popup */
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
    width:min(470px,100%);
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
    width:48px;
    height:48px;
    border-radius:50%;
    display:grid;
    place-items:center;
    flex:0 0 48px;
    font-size:1.2rem;
}
.custom-popup-icon.success{background:#e9f7ef;color:#198754;}
.custom-popup-icon.error{background:#fdecec;color:#b42318;}
.custom-popup-icon.warning{background:#fff4d6;color:#b7791f;}
.custom-popup-title{
    font-size:1.1rem;
    font-weight:700;
}
.custom-popup-body{
    padding:4px 20px 18px;
    font-size:.98rem;
    line-height:1.6;
    color:var(--muted);
}
.quiz-result-box{
    margin-top:12px;
    padding:12px 14px;
    border:1px solid var(--border);
    border-radius:9px;
    background:var(--surface2);
}
.quiz-result-score{
    font-size:1.25rem;
    font-weight:800;
    color:var(--maroon);
    margin-bottom:4px;
}
.quiz-result-status{
    font-size:.95rem;
    font-weight:700;
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

<main class="page take-quiz-page">

    <div class="page-heading">
        <div>
            <h2>
                <i class="fas fa-circle-question"></i>
                <?php echo h($quiz['title']); ?>
            </h2>

            <div class="quiz-summary">
                <span>
                    <i class="fas fa-list-ol"></i>
                    <?php echo count($questions); ?> question(s)
                </span>

                <span>
                    <i class="fas fa-star"></i>
                    <?php echo h((string)($quiz['total_points'] ?? 0)); ?> total points
                </span>

                <span>
                    <i class="fas fa-percent"></i>
                    Pass: <?php echo h((string)($quiz['passing_score'] ?? 0)); ?>%
                </span>
            </div>
        </div>
    </div>

    <?php if (!empty($quiz['instructions'])): ?>
        <div class="dash-content-card" style="padding:16px;margin-bottom:14px;">
            <strong style="font-size:.98rem;">
                <i class="fas fa-circle-info"></i>
                Instructions
            </strong>

            <div style="font-size:.94rem;line-height:1.6;margin-top:7px;color:var(--muted);">
                <?php echo nl2br(h($quiz['instructions'])); ?>
            </div>
        </div>
    <?php endif; ?>

    <form id="quizForm">
        <input
            type="hidden"
            name="quiz_id"
            value="<?php echo $quizId; ?>"
        >

        <?php foreach ($questions as $i => $question): ?>
            <div class="dash-content-card quiz-card">

                <div class="quiz-question">
                    <?php echo $i + 1; ?>.
                    <?php echo h($question['question_text']); ?>
                </div>

                <div class="quiz-points">
                    <i class="fas fa-star"></i>
                    <?php echo h((string)$question['points']); ?> point(s)
                </div>

                <?php if ($question['question_type'] === 'short_answer'): ?>

                    <textarea
                        class="form-input"
                        name="q_<?php echo (int)$question['id']; ?>"
                        rows="3"
                        placeholder="Enter your answer..."
                    ></textarea>

                <?php else: ?>

                    <?php foreach ($question['options'] as $option): ?>
                        <label class="quiz-option">
                            <input
                                type="radio"
                                name="q_<?php echo (int)$question['id']; ?>"
                                value="<?php echo (int)$option['id']; ?>"
                            >

                            <span>
                                <?php echo h($option['option_text']); ?>
                            </span>
                        </label>
                    <?php endforeach; ?>

                <?php endif; ?>

            </div>
        <?php endforeach; ?>

        <button
            type="submit"
            class="btn-export submit-quiz-btn"
            id="submitQuizBtn"
        >
            <i class="fas fa-paper-plane"></i>
            Submit Quiz
        </button>
    </form>

</main>

<div class="custom-popup-overlay" id="resultPopup" aria-hidden="true">
    <div class="custom-popup">
        <div class="custom-popup-head">
            <div class="custom-popup-icon success" id="resultIcon">
                <i class="fas fa-check"></i>
            </div>

            <div class="custom-popup-title" id="resultTitle">
                Quiz Result
            </div>
        </div>

        <div class="custom-popup-body">
            <div id="resultMessage"></div>

            <div class="quiz-result-box" id="scoreBox" style="display:none;">
                <div class="quiz-result-score" id="resultScore"></div>
                <div class="quiz-result-status" id="resultStatus"></div>
            </div>
        </div>

        <div class="custom-popup-foot">
            <button
                type="button"
                class="custom-popup-btn"
                id="resultOk"
            >
                OK
            </button>
        </div>
    </div>
</div>

<script>
'use strict';

function showResultPopup(data, type = 'success') {
    return new Promise(resolve => {
        const overlay = document.getElementById('resultPopup');
        const icon = document.getElementById('resultIcon');
        const title = document.getElementById('resultTitle');
        const message = document.getElementById('resultMessage');
        const scoreBox = document.getElementById('scoreBox');
        const score = document.getElementById('resultScore');
        const status = document.getElementById('resultStatus');
        const ok = document.getElementById('resultOk');

        const success = type === 'success';

        icon.className =
            'custom-popup-icon ' + (success ? 'success' : 'error');

        icon.innerHTML =
            '<i class="fas ' + (success ? 'fa-check' : 'fa-xmark') + '"></i>';

        title.textContent =
            success ? 'Quiz Submitted' : 'Unable to Submit Quiz';

        message.textContent =
            data.message || (success ? 'Your quiz has been submitted.' : 'Quiz submission failed.');

        if (success && data.score !== undefined) {
            scoreBox.style.display = '';

            score.textContent =
                data.score + '/' + data.total +
                ' (' + data.percentage + '%)';

            status.textContent =
                data.passed ? 'Passed' : 'Not passed';

            status.style.color =
                data.passed ? '#198754' : '#b42318';
        } else {
            scoreBox.style.display = 'none';
        }

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

document.getElementById('quizForm').addEventListener('submit', async function (event) {
    event.preventDefault();

    const form = event.currentTarget;
    const button = document.getElementById('submitQuizBtn');
    const oldHtml = button.innerHTML;

    button.disabled = true;
    button.innerHTML =
        '<i class="fas fa-spinner fa-spin"></i> Submitting...';

    try {
        const response = await fetch('scripts/submit_quiz.php', {
            method: 'POST',
            body: new FormData(form),
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const result = await readJson(response);

        if (result.success) {
            await showResultPopup(result, 'success');

            window.location.reload();
        } else {
            await showResultPopup(result, 'error');
        }
    } catch (error) {
        await showResultPopup(
            {
                message: 'Request failed: ' + error.message
            },
            'error'
        );
    } finally {
        button.disabled = false;
        button.innerHTML = oldHtml;
    }
});

document.getElementById('resultPopup').addEventListener('click', function (event) {
    if (event.target === this) {
        document.getElementById('resultOk').click();
    }
});

document.addEventListener('keydown', function (event) {
    if (
        event.key === 'Escape'
        && document.getElementById('resultPopup').classList.contains('is-open')
    ) {
        document.getElementById('resultOk').click();
    }
});
</script>
