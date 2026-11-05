

/* ---------- helpers ---------- */
function qs(sel) { return document.querySelector(sel); }

function escapeHtml(str) {
  return String(str ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function getFormParams() {
  const form = document.getElementById('filtersForm');
  return new URLSearchParams(new FormData(form));
}

function getFilterParamsString() {
  return getFormParams().toString();
}

function setSelectOptions(select, items, {
  valueKey = 'id',
  labelKey = 'name',
  defaultValue = '',
  defaultLabel = 'Select',
  keepValue = true,
  mapLabel = null
} = {}) {
  if (!select) return;

  const current = keepValue ? (select.value || defaultValue) : defaultValue;

  select.innerHTML = '';
  const def = document.createElement('option');
  def.value = defaultValue;
  def.textContent = defaultLabel;
  select.appendChild(def);

  (items || []).forEach(it => {
    const opt = document.createElement('option');
    opt.value = String(it[valueKey]);
    opt.textContent = mapLabel ? mapLabel(it) : String(it[labelKey] ?? '');
    select.appendChild(opt);
  });

  if (keepValue) {
    const exists = Array.from(select.options).some(o => o.value === current);
    select.value = exists ? current : defaultValue;
  } else {
    select.value = defaultValue;
  }
}

/* ---------- cards ---------- */
function getCardClass(total, pending) {
  total = parseInt(total || 0, 10);
  pending = parseInt(pending || 0, 10);

  if (total <= 0 || pending <= 0) return 'card-ok';
  const pendingRate = pending / total;
  if (pendingRate <= 0.30) return 'card-warn';
  return 'card-danger';
}

function renderClassTypeCards(data) {
  const container = document.getElementById('classTypeCards');
  if (!container) return;

  container.innerHTML = '';

  if (!Array.isArray(data) || data.length === 0) {
    container.innerHTML = `
      <div class="col-12">
        <div class="analytics-empty">No analytics to display (select an intake).</div>
      </div>`;
    return;
  }

  const totals = data.reduce((acc, it) => {
    acc.total += parseInt(it.total || 0, 10);
    acc.graded += parseInt(it.graded || 0, 10);
    acc.pending += parseInt(it.pending || 0, 10);
    return acc;
  }, { total: 0, graded: 0, pending: 0 });

  const pctAll = totals.total > 0 ? Math.round((totals.graded / totals.total) * 100) : 0;
  const summaryClass = getCardClass(totals.total, totals.pending);

  container.innerHTML += `
    <div class="col-md-6 col-lg-4">
      <div class="analytics-card summary-card ${summaryClass}">
        <div class="title">
          <span>All Class Types</span>
          <span class="pill">${pctAll}% graded</span>
        </div>
        <div class="metric"><span>Total submissions</span><span>${totals.total}</span></div>
        <div class="metric"><span>Graded</span><span>${totals.graded}</span></div>
        <div class="metric"><span>Pending</span><span>${totals.pending}</span></div>
      </div>
    </div>
  `;

  data.forEach(item => {
    const total = parseInt(item.total || 0, 10);
    const graded = parseInt(item.graded || 0, 10);
    const pending = parseInt(item.pending || 0, 10);
    const pct = total > 0 ? Math.round((graded / total) * 100) : 0;
    const cardClass = getCardClass(total, pending);

    container.innerHTML += `
      <div class="col-md-6 col-lg-3">
        <div class="analytics-card ${cardClass}">
          <div class="title">
            <span>${escapeHtml(item.class_type || 'N/A')}</span>
            <span class="pill">${pct}% graded</span>
          </div>
          <div class="metric"><span>Total submissions</span><span>${total}</span></div>
          <div class="metric"><span>Graded</span><span>${graded}</span></div>
          <div class="metric"><span>Pending</span><span>${pending}</span></div>
        </div>
      </div>
    `;
  });
}

function loadClassTypeAnalytics() {
  const params = getFilterParamsString();
  fetch('scripts/class_type_analytics.php?' + params)
    .then(r => r.json())
    .then(resp => {
      if (!resp || resp.success !== true) return renderClassTypeCards([]);
      renderClassTypeCards(resp.data || []);
    })
    .catch(() => renderClassTypeCards([]));
}

/* ---------- table ---------- */
function populateTable(rows) {
  const tbody = qs('#assignmentsTable tbody');
  if (!tbody) return;

  tbody.innerHTML = '';

  if (!Array.isArray(rows) || rows.length === 0) {
    tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted">No records found.</td></tr>`;
    return;
  }

  rows.forEach((r, i) => {
    const marksHtml = (r.finalmarks !== null && r.finalmarks !== undefined && String(r.finalmarks).trim() !== '')
      ? `<span class="badge badge-success">${escapeHtml(r.finalmarks)}</span>`
      : `<span class="badge badge-warning">Pending</span>`;

    tbody.innerHTML += `
      <tr>
        <td>${i + 1}</td>
        <td>${escapeHtml(r.student_name)}</td>
        <td>${escapeHtml(r.class_type_name ?? 'N/A')}</td>
        <td>${escapeHtml(r.course_name ?? '')}</td>
        <td>${escapeHtml(r.unit_name ?? '')}</td>
        <td>${escapeHtml(r.assignment_title ?? '')}</td>
        <td>${escapeHtml(r.date_due ?? '')}</td>
        <td>${escapeHtml(r.date_handed_in ?? '-')}</td>
        <td>${marksHtml}</td>
      </tr>
    `;
  });
}

/* ---------- dropdown population ---------- */
function populateClassTypes(classTypes) {
  const select = qs('[name="class_type_id"]');
  setSelectOptions(select, classTypes || [], {
    valueKey: 'id',
    labelKey: 'name',
    defaultValue: '0',
    defaultLabel: 'All Class Types',
    keepValue: true
  });
}

function populateUnits(units) {
  const select = qs('[name="unit_id"]');
  setSelectOptions(select, units || [], {
    valueKey: 'id',
    labelKey: 'name',
    defaultValue: '0',
    defaultLabel: 'All Units',
    keepValue: true
  });
}

function populateCourses(courses) {
  // ? FIXED selector
  const select = qs('[name="course_id"]');
  setSelectOptions(select, courses || [], {
    valueKey: 'id',
    labelKey: 'name',
    defaultValue: '0',
    defaultLabel: 'All Courses',
    keepValue: true
  });
}

function populateIntakes(intakes) {
  const select = qs('[name="intake_id"]');
  setSelectOptions(select, intakes || [], {
    valueKey: 'intake_id',
    labelKey: 'intake_name',
    defaultValue: '0',
    defaultLabel: 'Choose Intake',
    keepValue: true,
    mapLabel: (it) => `${it.intake_name} - ${it.branch_name}`
  });
}

/* ---------- load meta (intakes/courses/units/class_types) ---------- */
function loadMeta() {
  const params = getFormParams(); 
  fetch('scripts/assignments_meta.php?' + params.toString())
    .then(r => r.json())
    .then(resp => {
      if (!resp || resp.success !== true) return;

      populateIntakes(resp.intakes || []);
      populateCourses(resp.courses || []);
      populateUnits(resp.units || []);
      populateClassTypes(resp.class_types || []);
    })
    .catch(() => {});
}

/* ---------- MAIN LOAD ---------- */
function loadAssignments() {
  loadMeta();

  const params = getFilterParamsString();
  fetch('scripts/assignments_data.php?' + params)
    .then(res => res.json())
    .then(data => {
      if (!data || data.success !== true) {
        populateTable([]);
        renderClassTypeCards([]);
        return;
      }

      if (data.class_types) populateClassTypes(data.class_types);
      populateTable(data.rows || []);

      // ? cards
      loadClassTypeAnalytics();
    })
    .catch(() => {
      populateTable([]);
      renderClassTypeCards([]);
    });
}

document.addEventListener('DOMContentLoaded', function () {
  loadAssignments();
});
