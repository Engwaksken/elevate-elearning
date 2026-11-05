<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($systemname) || trim((string)$systemname) === '') {
    $systemname = "Elevate Her 360";
}

$user_unique = $_SESSION[$systemname . 'leido']
    ?? $_SESSION['unique_id']
    ?? $_SESSION['user_unique']
    ?? '';

if ($user_unique === '') {
    echo '
    <div class="app-alert app-alert-danger">
        <i class="fa fa-circle-exclamation"></i>
        Session expired. Please login again.
    </div>';
    return;
}

$studentName =
    $_SESSION[$systemname . 'firstname']
    ?? $_SESSION['fname']
    ?? $_SESSION['lname']
    ?? 'Student';


?>

<link rel="stylesheet" href="app.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<div class="ms-page">

    <div id="msDashboard">

        <div class="ms-header">
            <h1><i class="fa-solid fa-seedling"></i> ElevateHer360 Mindset Curriculum</h1>
            <p>
                Welcome back,
                <strong><?= h($studentName); ?></strong>.
                Pick up where you left off.
            </p>
        </div>

        <div class="ms-progress-card">
            <div class="ms-progress-row">
                <div>
                    <span class="ms-progress-label" id="msCompleteText">
                        Loading...
                        <small>Fetching your progress</small>
                    </span>
                </div>

                <div style="text-align:right">
                    <span class="ms-progress-label" id="msPointsText">
                        0 pts
                        <small>Total points earned</small>
                    </span>
                </div>
            </div>

            <div class="ms-progress-bar">
                <div class="ms-progress-fill" id="msProgressFill" style="width:0%"></div>
            </div>
        </div>

        <div id="msLoadingSkeleton" class="ms-modules">
            <?php for ($i = 0; $i < 6; $i++): ?>
                <div class="ms-module-card" style="pointer-events:none">
                    <span class="ms-module-no" style="background:#e5e7eb;color:transparent">00</span>
                    <div class="msa-skeleton" style="height:14px;margin:14px 0 6px;width:85%"></div>
                    <div class="msa-skeleton" style="height:10px;width:55%"></div>
                </div>
            <?php endfor; ?>
        </div>

        <div class="ms-modules" id="msModuleGrid" style="display:none"></div>

        <div id="msLoadError" class="app-alert app-alert-danger d-none" style="flex-direction:column;align-items:flex-start">
            <div style="display:flex;align-items:center;gap:8px">
                <i class="fa fa-circle-exclamation"></i>
                <strong id="msLoadErrorText">Could not load curriculum.</strong>
            </div>

            <div id="msDebugBox" class="fb-content d-none" style="margin-top:10px;width:100%;color:var(--red);border-color:rgba(168,21,21,.2)"></div>

            <button type="button" class="btn-export" style="margin-top:12px" onclick="window.location.reload()">
                <i class="fa fa-rotate"></i> Retry
            </button>
        </div>

        <div class="ms-community" id="msCommunityBox"></div>

    </div>

    <div id="msModuleView" class="ms-module-view">

        <div class="ms-topline">
            <button type="button" class="ms-back-btn" onclick="backToDashboard()">
                <i class="fa fa-arrow-left"></i> Back
            </button>

            <div class="ms-topline-center">
                <div class="ms-theme-label" id="msModuleTheme"></div>
                <div class="ms-step-title" id="msStepTitle"></div>
            </div>

            <span class="ms-badge" id="msModuleDuration">
                <i class="fa fa-clock"></i>
            </span>
        </div>

        <div class="ms-step-dots" id="msStepDots"></div>

        <div class="ms-step-box" id="msStepBox"></div>

        <div class="ms-footer-nav">
            <div>
                <span class="ms-step-counter" id="msStepCounter">
                    <i class="fa fa-circle-dot"></i>
                    <span></span>
                </span>

                <div class="ms-step-error" id="msStepError">
                    <i class="fa fa-circle-exclamation"></i>
                    <span id="msStepErrorText">Please complete this step before continuing.</span>
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:12px">
                <span class="ms-save-indicator" id="msSaveIndicator">
                    <i class="fa fa-check-circle"></i> Saved
                </span>

                <button type="button" class="ms-continue-btn" id="msContinueBtn" onclick="nextStep()">
                    Continue <i class="fa fa-arrow-right"></i>
                </button>
            </div>
        </div>

    </div>

</div>

<div class="ms-saving-toast" id="msSavingToast">
    <i class="fa fa-cloud-arrow-up"></i> Saving...
</div>

<script>
'use strict';

const API = './scripts/process-mindset.php';

let MODULES = [];
let currentModule = 0;
let currentStep = 0;
let responses = {};
let completed = {};
let stepIndex = {};
let saveTimer = null;

document.addEventListener('DOMContentLoaded', init);

function qs(id) {
    return document.getElementById(id);
}

async function init() {
    try {
        const qData = await apiPost({ action: 'load_questions' });
        const pData = await apiPost({ action: 'load_progress' });

        if (!qData.success || !Array.isArray(qData.modules)) {
            throw new Error(qData.error || 'Invalid curriculum response');
        }

        MODULES = qData.modules.filter(m => Array.isArray(m.steps) && m.steps.length > 0);

        if (MODULES.length < 1) {
            throw new Error('No active mindset questions found in the mindset_questions table.');
        }

        if (pData.success && pData.progress) {
            Object.entries(pData.progress).forEach(([mid, prog]) => {
                const id = parseInt(mid, 10);

                stepIndex[id] = parseInt(prog.step_index || 0, 10);

                if (prog.status === 'completed') {
                    completed[id] = parseInt(prog.points || 0, 10);
                }

                if (prog.responses && typeof prog.responses === 'object') {
                    Object.assign(responses, prog.responses);
                }
            });
        }

        qs('msLoadingSkeleton').style.display = 'none';
        qs('msModuleGrid').style.display = '';
        renderDashboard();
        loadCommunity();

    } catch (err) {
        showLoadError(err);
    }
}

async function apiPost(data = {}) {
    const body = new URLSearchParams();

    Object.entries(data).forEach(([k, v]) => {
        body.append(k, v === null || v === undefined ? '' : String(v));
    });

    const res = await fetch(API, {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body
    });

    return parseResponse(res, API + ' action=' + (data.action || ''));
}

async function apiGet(data = {}) {
    const params = new URLSearchParams();

    Object.entries(data).forEach(([k, v]) => {
        params.append(k, v === null || v === undefined ? '' : String(v));
    });

    const url = API + '?' + params.toString();

    const res = await fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    });

    return parseResponse(res, url);
}

async function parseResponse(res, url) {
    const text = await res.text();

    if (!text || !text.trim()) {
        const e = new Error('Empty response from ' + url);
        e.debug = 'Backend returned no output. Check PHP fatal errors, connector.php, or session.';
        throw e;
    }

    let json;

    try {
        json = JSON.parse(text);
    } catch (err) {
        const e = new Error('Invalid JSON returned by backend');
        e.debug = text.substring(0, 5000);
        throw e;
    }

    if (!res.ok || json.success === false) {
        const e = new Error(json.error || ('Server returned HTTP ' + res.status));
        e.debug = JSON.stringify(json, null, 2);
        throw e;
    }

    return json;
}

function showLoadError(err) {
    qs('msLoadingSkeleton').style.display = 'none';
    qs('msModuleGrid').style.display = 'none';
    qs('msLoadErrorText').textContent = 'Could not load curriculum: ' + (err.message || 'Unknown error');

    if (err.debug) {
        const box = qs('msDebugBox');
        box.textContent = err.debug;
        box.classList.remove('d-none');
    }

    const errEl = qs('msLoadError');
    errEl.classList.remove('d-none');
    errEl.style.display = 'flex';
}

function showSaveToast() {
    qs('msSavingToast').classList.add('ms-visible');
}

function hideSaveToast() {
    qs('msSavingToast').classList.remove('ms-visible');
}

function flashSaved() {
    const el = qs('msSaveIndicator');
    el.classList.add('ms-visible');
    setTimeout(() => el.classList.remove('ms-visible'), 1400);
}

async function saveStepToServer(moduleId, stepIdx, resp) {
    showSaveToast();

    try {
        await apiPost({
            action: 'save_step',
            module_id: moduleId,
            step_index: stepIdx,
            responses: JSON.stringify(resp)
        });
        flashSaved();
    } catch (err) {
        console.error('Save failed:', err);
    } finally {
        hideSaveToast();
    }
}

async function completeModuleOnServer(moduleId, points) {
    showSaveToast();

    try {
        await apiPost({
            action: 'complete_module',
            module_id: moduleId,
            points: points,
            responses: JSON.stringify(responses)
        });
        flashSaved();
    } catch (err) {
        alert('Could not complete module: ' + err.message);
    } finally {
        hideSaveToast();
    }
}

function scheduleSave() {
    clearTimeout(saveTimer);

    saveTimer = setTimeout(() => {
        const mod = MODULES[currentModule];
        if (mod) {
            saveStepToServer(mod.id, currentStep, responses);
        }
    }, 700);
}

function setResponse(key, val) {
    responses[key] = val;
    clearStepError();
    scheduleSave();
}

function setObjectResponse(key, field, val) {
    if (!responses[key] || typeof responses[key] !== 'object') {
        responses[key] = {};
    }

    responses[key][field] = val;
    clearStepError();
    scheduleSave();
}

function setQuiz(key, index, val, btn) {
    if (!responses[key] || typeof responses[key] !== 'object') {
        responses[key] = {};
    }

    responses[key][index] = val;

    Array.from(btn.parentElement.children).forEach(b => b.classList.remove('ms-selected'));
    btn.classList.add('ms-selected');

    clearStepError();
    scheduleSave();
}

function clearStepError() {
    qs('msStepError').classList.remove('ms-visible');
}

function showStepError(msg) {
    qs('msStepErrorText').textContent = msg;
    qs('msStepError').classList.add('ms-visible');
}

function totalPoints() {
    return Object.values(completed).reduce((a, b) => a + Number(b || 0), 0);
}

function renderDashboard() {
    const done = Object.keys(completed).length;
    const total = MODULES.length || 1;
    const percent = Math.round((done / total) * 100);

    qs('msCompleteText').innerHTML =
        percent + '% complete<small>' +
        (done === total ? 'All modules done!' : 'Continue your mindset journey') +
        '</small>';

    qs('msPointsText').innerHTML = totalPoints() + ' pts<small>Total points earned</small>';
    qs('msProgressFill').style.width = percent + '%';

    const grid = qs('msModuleGrid');
    grid.innerHTML = '';

    MODULES.forEach((m, i) => {
        const prev = MODULES[i - 1];
        const locked = i > 0 && prev && !completed[prev.id];
        const isDone = !!completed[m.id];
        const modStep = parseInt(stepIndex[m.id] || 0, 10);
        const stepPct = m.steps.length ? Math.min(100, Math.round((modStep / m.steps.length) * 100)) : 0;

        const card = document.createElement('div');

        card.className = 'ms-module-card'
            + (locked ? ' ms-locked' : '')
            + (isDone ? ' ms-completed' : '');

        card.innerHTML = `
            <span class="ms-module-no">${String(i + 1).padStart(2, '0')}</span>
            <h3>${escHtml(m.title)}</h3>
            <p>${escHtml(m.theme)}</p>
            <div class="ms-card-footer">
                <span class="ms-badge"><i class="fa fa-clock"></i> ${escHtml(m.duration || '')}</span>
                ${
                    isDone
                    ? `<span class="ms-badge ms-badge-done"><i class="fa fa-check"></i> +${completed[m.id]} pts</span>`
                    : `<span class="ms-badge"><i class="fa fa-star"></i> ${parseInt(m.points || 0, 10)} pts</span>`
                }
                ${locked ? `<span class="ms-badge"><i class="fa fa-lock"></i> Locked</span>` : ''}
            </div>
            ${
                !isDone && !locked && modStep > 0
                ? `<div class="ms-card-stepbar"><div class="ms-card-stepfill" style="width:${stepPct}%"></div></div>`
                : ''
            }
            ${
                isDone
                ? `<div class="ms-card-stepbar"><div class="ms-card-stepfill ms-done" style="width:100%"></div></div>`
                : ''
            }
        `;

        if (!locked) {
            card.addEventListener('click', () => openModule(i));
        }

        grid.appendChild(card);
    });
}

async function loadCommunity() {
    try {
        const data = await apiGet({
            action: 'load_community',
            limit: 8
        });

        if (data.success) {
            renderCommunityFeed(data.posts || []);
        }
    } catch (err) {
        console.warn('Community feed failed:', err);
    }
}

function renderCommunityFeed(posts) {
    const box = qs('msCommunityBox');

    if (!Array.isArray(posts) || posts.length === 0) {
        box.innerHTML = '';
        return;
    }

    box.innerHTML = `
        <div class="ms-community-head">
            <span class="ms-community-title">
                <i class="fa fa-users"></i> Community Shares
            </span>
        </div>
        <div class="ms-feed">
            ${posts.map(p => `
                <div class="ms-feed-item">
                    <div class="ms-feed-avatar"><i class="fa fa-user"></i></div>
                    <div class="ms-feed-body">
                        <div class="ms-feed-name">${escHtml(p.display_name || 'Student')}</div>
                        <div class="ms-feed-text">${escHtml(p.content || '')}</div>
                        <div class="ms-feed-meta">${formatDate(p.created_at)}</div>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

function openModule(index) {
    const mod = MODULES[index];

    if (!mod || !mod.steps.length) {
        alert('This module has no active steps yet.');
        return;
    }

    currentModule = index;

    const saved = parseInt(stepIndex[mod.id] || 0, 10);
    const lastIndex = Math.max(0, mod.steps.length - 1);
    const beforeComplete = Math.max(0, mod.steps.length - 2);

    currentStep = completed[mod.id] ? lastIndex : Math.min(saved, beforeComplete);

    qs('msDashboard').style.display = 'none';
    qs('msModuleView').classList.add('ms-visible');

    renderStep();
}

function backToDashboard() {
    const mod = MODULES[currentModule];

    if (mod) {
        saveStepToServer(mod.id, currentStep, responses);
    }

    qs('msModuleView').classList.remove('ms-visible');
    qs('msDashboard').style.display = '';

    renderDashboard();
}

function renderStep() {
    const mod = MODULES[currentModule];
    const step = mod.steps[currentStep];

    qs('msModuleTheme').textContent =
        String(currentModule + 1).padStart(2, '0') + ' · ' + (mod.theme || '');

    qs('msStepTitle').textContent = step.title || 'Untitled step';
    qs('msModuleDuration').innerHTML = '<i class="fa fa-clock"></i> ' + escHtml(mod.duration || '');

    qs('msStepCounter').querySelector('span').textContent =
        'Step ' + (currentStep + 1) + ' of ' + mod.steps.length;

    qs('msStepDots').innerHTML = mod.steps.map((_, i) => {
        let cls = 'ms-dot';
        if (i < currentStep) cls += ' ms-dot-done';
        if (i === currentStep) cls += ' ms-dot-active';
        return '<span class="' + cls + '"></span>';
    }).join('');

    qs('msStepBox').innerHTML = buildStepHtml(step, mod);
    clearStepError();

    qs('msContinueBtn').innerHTML = step.type === 'complete'
        ? 'Complete Module <i class="fa fa-check"></i>'
        : 'Continue <i class="fa fa-arrow-right"></i>';
}

function buildStepHtml(step, mod) {
    switch (step.type) {
        case 'intro':
            return `
                ${step.quote ? `<div class="ms-quote">${escHtml(step.quote)}</div>` : ''}
                ${step.body ? `<p class="ms-intro-body">${escHtml(step.body)}</p>` : ''}
            `;

        case 'textarea':
        case 'rewrite':
        case 'earning':
            return `
                ${step.prompt ? `<p class="ms-prompt">${escHtml(step.prompt)}</p>` : ''}
                ${step.hint ? `<p class="ms-hint"><i class="fa fa-lightbulb"></i> ${escHtml(step.hint)}</p>` : ''}
                <textarea class="ms-textarea"
                    oninput="setResponse('${escJs(step.key)}', this.value)"
                    placeholder="Write your response here...">${escHtml(responses[step.key] || '')}</textarea>
                ${step.minLen ? `<p class="ms-char-count">Minimum ${parseInt(step.minLen, 10)} characters</p>` : ''}
            `;

        case 'sentence':
            return `
                <p class="ms-prompt">${escHtml(step.starter || 'Complete the sentence')}</p>
                <input class="ms-input"
                    value="${escAttr(responses[step.key] || '')}"
                    oninput="setResponse('${escJs(step.key)}', this.value)">
                <br><br>
                <button type="button" class="ms-share-btn" onclick="shareIdentity('${escJs(step.key)}')">
                    <i class="fa fa-users"></i> Share with group
                </button>
            `;

        case 'quiz':
            return buildQuizHtml(step);

        case 'goalmap':
            return buildGoalMapHtml(step);

        case 'scenarios':
            return buildScenariosHtml(step);

        case 'list':
            return buildListHtml(step);

        case 'transcription':
            return buildTranscriptionHtml(step);

        case 'earner':
            return buildEarnerHtml(step);

        case 'task':
            return `
                <div class="ms-task-card">
                    <i class="fa fa-headphones"></i>
                    <div>
                        <strong>Practice Task</strong>
                        <span>${escHtml(step.body || 'Complete the task and continue.')}</span>
                    </div>
                </div>
            `;

        case 'review':
            return buildReviewHtml();

        case 'complete':
            return `
                <div class="ms-complete-box">
                    <i class="fa fa-circle-check ms-complete-icon"></i>
                    <h2 class="ms-complete-title">${escHtml(step.title || 'Module complete')}</h2>
                    <p class="ms-complete-msg">${escHtml(step.message || step.body || 'You have completed this module.')}</p>
                    <div class="ms-points-award">
                        <i class="fa fa-star"></i> +${parseInt(mod.points || 0, 10)} points earned
                    </div>
                </div>
            `;

        default:
            return `<p>${escHtml(step.body || 'Step content coming soon.')}</p>`;
    }
}

function buildQuizHtml(step) {
    const questions = [
        'Women in my community earn their own income independently.',
        'I believe I could earn consistent income using digital skills.',
        'Managing money is something I am capable of learning.',
        'I deserve to be financially independent.',
        'Digital work is accessible to someone like me.'
    ];

    const saved = responses[step.key] && typeof responses[step.key] === 'object'
        ? responses[step.key]
        : {};

    return questions.map((q, i) => `
        <div class="ms-quiz-question">
            <p class="ms-quiz-q-text">${escHtml(q)}</p>
            <div class="ms-likert">
                ${[1, 2, 3, 4, 5].map(v => `
                    <button type="button"
                        class="ms-likert-btn ${saved[i] == v ? 'ms-selected' : ''}"
                        onclick="setQuiz('${escJs(step.key)}', ${i}, ${v}, this)">${v}</button>
                `).join('')}
            </div>
            <div class="ms-likert-labels">
                <span>Strongly disagree</span>
                <span>Strongly agree</span>
            </div>
        </div>
    `).join('');
}

function buildGoalMapHtml(step) {
    const keys = ['goal', 'm1', 'm2', 'm3'];
    const labels = ['My 6-month income goal', 'Milestone 1', 'Milestone 2', 'Milestone 3'];

    const saved = responses[step.key] && typeof responses[step.key] === 'object'
        ? responses[step.key]
        : {};

    return keys.map((k, i) => `
        <div class="ms-goal-row">
            <label class="ms-goal-label">
                <i class="fa fa-flag"></i> ${labels[i]}
            </label>
            <input class="ms-input"
                value="${escAttr(saved[k] || '')}"
                oninput="setObjectResponse('${escJs(step.key)}', '${k}', this.value)">
        </div>
    `).join('');
}

function buildScenariosHtml(step) {
    const scenarios = [
        'A client rejects your work',
        'You miss an income target for the month',
        'Someone close to you tells you to stop'
    ];

    const saved = responses[step.key] && typeof responses[step.key] === 'object'
        ? responses[step.key]
        : {};

    return scenarios.map((s, i) => `
        <div class="ms-scenario-card">
            <h4>${escHtml(s)}</h4>
            <textarea class="ms-textarea"
                oninput="setObjectResponse('${escJs(step.key)}', 's${i}', this.value)">${escHtml(saved['s' + i] || '')}</textarea>
        </div>
    `).join('');
}

function buildListHtml(step) {
    const saved = responses[step.key] && typeof responses[step.key] === 'object'
        ? responses[step.key]
        : {};

    return `<div class="ms-list-inputs">` +
        Array.from({ length: 5 }, (_, i) => `
            <input class="ms-input"
                value="${escAttr(saved['i' + i] || '')}"
                oninput="setObjectResponse('${escJs(step.key)}', 'i${i}', this.value)"
                placeholder="Something I have overcome or achieved...">
        `).join('') +
    `</div>`;
}

function buildTranscriptionHtml(step) {
    const saved = responses[step.key] && typeof responses[step.key] === 'object'
        ? responses[step.key]
        : {};

    return `
        <div class="ms-scenario-card">
            <h4>Part A</h4>
            <textarea class="ms-textarea"
                oninput="setObjectResponse('${escJs(step.key)}', 'a', this.value)">${escHtml(saved.a || '')}</textarea>
        </div>
        <div class="ms-scenario-card">
            <h4>Part B</h4>
            <textarea class="ms-textarea"
                oninput="setObjectResponse('${escJs(step.key)}', 'b', this.value)">${escHtml(saved.b || '')}</textarea>
        </div>
    `;
}

function buildEarnerHtml(step) {
    const fields = [
        { k: 'name', label: 'Your name' },
        { k: 'skill', label: 'Your earning skill' },
        { k: 'goal', label: 'Your next income goal' },
        { k: 'date', label: 'Target date' }
    ];

    const saved = responses[step.key] && typeof responses[step.key] === 'object'
        ? responses[step.key]
        : {};

    return `
        <div class="ms-earner-grid">
            ${fields.map(f => `
                <div class="ms-earner-field">
                    <label class="ms-earner-label">${escHtml(f.label)}</label>
                    <input class="ms-input"
                        value="${escAttr(saved[f.k] || '')}"
                        oninput="setObjectResponse('${escJs(step.key)}', '${f.k}', this.value)">
                </div>
            `).join('')}
        </div>
        <button type="button" class="ms-share-btn" onclick="shareEarnerPost('${escJs(step.key)}')">
            <i class="fa fa-users"></i> Post to community
        </button>
    `;
}

function buildReviewHtml() {
    const all = Object.values(responses)
        .filter(v => typeof v === 'string' ? v.trim() : v && typeof v === 'object')
        .slice(0, 8);

    if (!all.length) {
        return '<p class="ms-hint">No responses yet.</p>';
    }

    return `<div class="ms-review-grid">` +
        all.map((v, i) => `
            <div class="ms-review-item">
                <div class="ms-review-item-label">Reflection ${i + 1}</div>
                <div class="ms-review-item-value">${escHtml(
                    typeof v === 'string' ? v : Object.values(v).join(' | ')
                )}</div>
            </div>
        `).join('') +
    `</div>`;
}

function validateStep(step) {
    if (['intro', 'data', 'task', 'complete', 'review', 'story'].includes(step.type) || !step.required) {
        return true;
    }

    const val = responses[step.key];

    if (['textarea', 'rewrite', 'earning', 'sentence'].includes(step.type)) {
        const str = typeof val === 'string' ? val.trim() : '';
        const min = parseInt(step.minLen || 1, 10);

        if (str.length < min) {
            showStepError('Please complete this step before continuing.');
            return false;
        }
    }

    if (step.type === 'quiz') {
        const obj = val && typeof val === 'object' ? val : {};

        if (Object.keys(obj).length < 5) {
            showStepError('Please answer all 5 questions.');
            return false;
        }
    }

    return true;
}

async function nextStep() {
    const mod = MODULES[currentModule];
    const step = mod.steps[currentStep];

    if (step.type === 'complete') {
        completed[mod.id] = parseInt(mod.points || 0, 10);
        await completeModuleOnServer(mod.id, completed[mod.id]);
        backToDashboard();
        return;
    }

    if (!validateStep(step)) {
        return;
    }

    const nextIdx = currentStep + 1;
    stepIndex[mod.id] = Math.max(stepIndex[mod.id] || 0, nextIdx);

    await saveStepToServer(mod.id, nextIdx, responses);

    if (nextIdx >= mod.steps.length) {
        completed[mod.id] = parseInt(mod.points || 0, 10);
        await completeModuleOnServer(mod.id, completed[mod.id]);
        backToDashboard();
        return;
    }

    currentStep = nextIdx;
    renderStep();
}

async function shareIdentity(key) {
    const val = String(responses[key] || '').trim();

    if (!val) {
        alert('Please write your identity statement first.');
        return;
    }

    const mod = MODULES[currentModule];

    await apiPost({
        action: 'share_post',
        content: val,
        module_id: mod.id,
        post_type: 'identity'
    });

    alert('Shared with the group!');
    loadCommunity();
}

async function shareEarnerPost(key) {
    const e = responses[key] && typeof responses[key] === 'object' ? responses[key] : {};

    if (!e.name || !e.skill || !e.goal) {
        alert('Please fill in your Name, Skill, and Goal fields first.');
        return;
    }

    const text = 'I am ' + e.name + ' and I earn by doing ' + e.skill +
        '. My next goal is ' + e.goal + (e.date ? ' by ' + e.date : '') + '.';

    const mod = MODULES[currentModule];

    await apiPost({
        action: 'share_post',
        content: text,
        module_id: mod.id,
        post_type: 'earner'
    });

    alert('Posted to the community!');
    loadCommunity();
}

function escHtml(str) {
    if (str === null || str === undefined) {
        return '';
    }

    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function escAttr(str) {
    return escHtml(str);
}

function escJs(str) {
    if (str === null || str === undefined) {
        return '';
    }

    return String(str)
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/"/g, '&quot;')
        .replace(/\n/g, '\\n')
        .replace(/\r/g, '');
}

function formatDate(dt) {
    if (!dt) {
        return '';
    }

    try {
        return new Date(dt).toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    } catch (err) {
        return dt;
    }
}
</script>