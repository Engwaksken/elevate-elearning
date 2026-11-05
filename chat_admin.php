<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$unik = $unik ?? ($_SESSION[$systemname . "leido"] ?? '');

/* -- Role config ------------------------------------------ */
$roles = [
    'xybrtw' => ['label' => 'Admin',      'class' => 'role-admin',      'icon' => 'fa-user-shield',   'stat_class' => 'dsi-admin'],
    'xtfbry' => ['label' => 'Instructor',  'class' => 'role-instructor', 'icon' => 'fa-chalkboard-teacher', 'stat_class' => 'dsi-instructor'],
    'afgezt' => ['label' => 'Student',    'class' => 'role-student',    'icon' => 'fa-user-graduate', 'stat_class' => 'dsi-student'],
    'zyffwq' => ['label' => 'Accounts',   'class' => 'role-accounts',   'icon' => 'fa-user-tie',      'stat_class' => 'dsi-accounts'],
];

/* -- Fetch all user groups -------------------------------- */
$groups = [];
foreach ($roles as $code => $meta) {
    $esc  = $conn->real_escape_string($unik);
    $res  = $conn->query("SELECT * FROM users WHERE acc_type='$code' AND unique_id!='$esc' ORDER BY fname ASC");
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $groups[$code] = ['meta' => $meta, 'rows' => $rows, 'count' => count($rows)];
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

<!-- -- Topbar ----------------------------------------------- -->
<nav class="topbar">
  <div class="topbar-left">
    <h1><i class="fas fa-comment-dots"></i> <?php echo htmlspecialchars($systemname) ?> &mdash; Private Chat</h1>
    <ol class="breadcrumb">
      <li><a href="?"><i class="fas fa-home"></i> Home</a></li>
      <li><a href="?view=chatroom_ad">Chatrooms</a></li>
      <li class="active">User Directory</li>
    </ol>
  </div>
  <div class="topbar-right"><i class="fas fa-clock"></i> <?php echo date("h:i a") ?></div>
</nav>

<main class="page">

  <!-- -- Page heading --------------------------------------- -->
  <div class="page-heading">
    <div>
      <h2>User <em>Directory</em></h2>
      <div class="heading-meta">
        <span class="meta-pill"><i class="fas fa-users"></i>
          <?php echo array_sum(array_column($groups, 'count')) ?> users
        </span>
      </div>
    </div>
    <div class="search-wrap">
      <i class="fas fa-search"></i>
      <input type="text" id="globalSearch" placeholder="Search all users…">
    </div>
  </div>

  <!-- -- Stats strip ---------------------------------------- -->
  <div class="dir-stats">
    <?php foreach ($groups as $code => $g): ?>
    <div class="dir-stat">
      <span class="dir-stat-icon <?php echo $g['meta']['stat_class'] ?>">
        <i class="fas <?php echo $g['meta']['icon'] ?>"></i>
      </span>
      <div>
        <div class="dir-stat-num"><?php echo $g['count'] ?></div>
        <div class="dir-stat-lbl"><?php echo $g['meta']['label'] ?>s</div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- -- Tab bar -------------------------------------------- -->
  <div class="tab-bar">
    <?php $first = true; foreach ($groups as $code => $g): ?>
    <button class="tab-btn <?php echo $first ? 'active' : '' ?>"
            onclick="switchTab('tab-<?php echo $code ?>', this)">
      <i class="fas <?php echo $g['meta']['icon'] ?>"></i>
      <?php echo $g['meta']['label'] ?>s
      <span class="pill"><?php echo $g['count'] ?></span>
    </button>
    <?php $first = false; endforeach; ?>
  </div>

  <!-- -- Tab panels ----------------------------------------- -->
  <?php $first = true; foreach ($groups as $code => $g): ?>
  <div id="tab-<?php echo $code ?>" class="tab-panel <?php echo $first ? 'active' : '' ?>">

    <?php if ($g['count'] === 0): ?>
      <div class="dir-empty">
        <i class="fas <?php echo $g['meta']['icon'] ?>"></i>
        <p>No <?php echo strtolower($g['meta']['label']) ?>s to display.</p>
        <a href="?view=create" class="btn-add-user">
          <i class="fas fa-plus-circle"></i> Add <?php echo $g['meta']['label'] ?>
        </a>
      </div>

    <?php else: ?>
      <div class="tbl-card">
        <div class="tbl-toolbar">
          <div class="tbl-title">
            <i class="fas <?php echo $g['meta']['icon'] ?> tbl-title-icon"></i>
            <?php echo $g['meta']['label'] ?>s
            <span class="count-pill"><?php echo $g['count'] ?></span>
          </div>
          <div class="toolbar-actions">
            <div class="search-wrap">
              <i class="fas fa-search"></i>
              <input type="text" class="tab-search" placeholder="Search <?php echo strtolower($g['meta']['label']) ?>s…"
                     data-tab="tab-<?php echo $code ?>">
            </div>
            <a href="?view=create" class="btn-add-user">
              <i class="fas fa-plus-circle"></i> Add
            </a>
          </div>
        </div>

        <div class="tbl-scroll">
          <table class="dir-table" data-tab="<?php echo $code ?>">
            <thead>
              <tr>
                <th>#</th>
                <th>User</th>
                <th>Email</th>
                <th>Role</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
            <?php $i = 1; foreach ($g['rows'] as $u):
              $fullName = htmlspecialchars(trim(($u['fname'] ?? '') . ' ' . ($u['lname'] ?? '') . ' ' . ($u['other_name'] ?? '')));
              $email    = htmlspecialchars($u['email'] ?? '');
              $uid      = htmlspecialchars($u['unique_id'] ?? '');
              $dp       = $u['dp'] ?? 'default.jpg';
              $dpSrc    = ($dp === 'default.jpg') ? 'images/default_p_pics/user.png' : 'images/user_p_pics/' . $dp;
            ?>
            <tr data-name="<?php echo strtolower($fullName) ?>" data-email="<?php echo strtolower($email) ?>">
              <td class="row-num"><?php echo $i++ ?></td>
              <td>
                <div class="user-cell">
                  <div class="user-cell-avatar">
                    <img src="<?php echo htmlspecialchars($dpSrc) ?>" alt="<?php echo $fullName ?>">
                  </div>
                  <div>
                    <div class="user-cell-name"><?php echo $fullName ?></div>
                  </div>
                </div>
              </td>
              <td>
                <span class="user-email">
                  <i class="fas fa-envelope"></i> <?php echo $email ?>
                </span>
              </td>
              <td>
                <span class="contact-role-tag <?php echo $g['meta']['class'] ?>">
                  <?php echo $g['meta']['label'] ?>
                </span>
              </td>
              <td>
                <a href="?view=chatwith&pr=<?php echo $uid ?>" class="btn-chat-user">
                  <i class="fas fa-comment-dots"></i> Chat
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="tbl-footer">
          <span class="row-count" data-tab="<?php echo $code ?>">
            Showing all <?php echo $g['count'] ?> <?php echo strtolower($g['meta']['label']) ?>s
          </span>
        </div>
      </div>
    <?php endif; ?>

  </div>
  <?php $first = false; endforeach; ?>

</main>

<script>
/* -- Tab switching ---------------------------------------- */
function switchTab(id, btn) {
  document.querySelectorAll('.tab-panel').forEach(function(p) { p.classList.remove('active'); });
  document.querySelectorAll('.tab-btn').forEach(function(b)   { b.classList.remove('active'); });
  document.getElementById(id).classList.add('active');
  btn.classList.add('active');
}

/* -- Per-tab search --------------------------------------- */
document.querySelectorAll('.tab-search').forEach(function (inp) {
  inp.addEventListener('input', function () {
    var q    = this.value.toLowerCase().trim();
    var tab  = this.dataset.tab;
    var rows = document.querySelectorAll('[data-tab="' + tab.replace('tab-','') + '"] tbody tr');
    var vis  = 0;
    rows.forEach(function (r) {
      var match = !q || (r.dataset.name || '').includes(q) || (r.dataset.email || '').includes(q);
      r.style.display = match ? '' : 'none';
      if (match) vis++;
    });
    var lbl = document.querySelector('.row-count[data-tab="' + tab.replace('tab-','') + '"]');
    if (lbl) lbl.textContent = q
      ? 'Showing ' + vis + ' results'
      : 'Showing all ' + vis + ' ' + lbl.textContent.split(' ').pop();
  });
});

/* -- Global search (filters across all panels) ------------ */
document.getElementById('globalSearch')?.addEventListener('input', function () {
  var q = this.value.toLowerCase().trim();
  if (!q) {
    /* clear all tab searches */
    document.querySelectorAll('.tab-search').forEach(function(i){ i.value=''; i.dispatchEvent(new Event('input')); });
    return;
  }
  /* Show the first tab with a match */
  var matched = false;
  document.querySelectorAll('.tab-panel').forEach(function (panel) {
    var rows   = panel.querySelectorAll('tbody tr');
    var panelMatch = false;
    rows.forEach(function (r) {
      var match = (r.dataset.name || '').includes(q) || (r.dataset.email || '').includes(q);
      r.style.display = match ? '' : 'none';
      if (match) panelMatch = true;
    });
    if (panelMatch && !matched) {
      /* activate this tab */
      var tabId  = panel.id;
      var tabBtn = document.querySelector('.tab-btn[onclick*="' + tabId + '"]');
      if (tabBtn) { switchTab(tabId, tabBtn); }
      matched = true;
    }
  });
});
</script>
</body>
</html>