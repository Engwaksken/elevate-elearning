<?php
if (session_status() === PHP_SESSION_NONE) session_start();

/* -- Fetch all chatrooms ---------------------------------- */
$n   = "SELECT * FROM chatroom ORDER BY id DESC";
$w   = $conn->query($n) or die(mysqli_error($conn));
$count = $w->num_rows;

/* -- Handle create chatroom form -------------------------- */
$createResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['chatroom_name'])) {
    $cName = $conn->real_escape_string(trim($_POST['chatroom_name']));
    $cCode = $conn->real_escape_string(trim($_POST['chatroom_code'] ?? ''));
    $cPass = $conn->real_escape_string(trim($_POST['chatroom_pass'] ?? ''));
    $cDate = date('Y-m-d H:i:s');

    if ($cName === '' || $cCode === '' || $cPass === '') {
        $createResult = ['type' => 'error', 'msg' => 'Please fill in all fields.'];
    } else {
        $ins = $conn->query("INSERT INTO chatroom (chatroom_name, date_created, created_by, code, pass) VALUES ('$cName', '$cDate', 'admin', '$cCode', '$cPass')");
        $createResult = $ins
            ? ['type' => 'success', 'msg' => 'Chatroom "' . htmlspecialchars($cName) . '" created successfully.']
            : ['type' => 'error',   'msg' => 'Failed to create chatroom: ' . $conn->error];

        /* Re-fetch after insert */
        $w     = $conn->query("SELECT * FROM chatroom ORDER BY id DESC") or die(mysqli_error($conn));
        $count = $w->num_rows;
    }
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
    <h1><i class="fas fa-comments"></i> <?php echo htmlspecialchars($systemname) ?> &mdash; Chatrooms</h1>
    <ol class="breadcrumb">
      <li><a href="?"><i class="fas fa-home"></i> Home</a></li>
      <li><a href="?view=chatroom_ad">Chatrooms</a></li>
      <li class="active">Manage Chatrooms</li>
    </ol>
  </div>
  <div class="topbar-right"><i class="fas fa-clock"></i> <?php echo date("h:i a") ?></div>
</nav>

<main class="page">
<div class="chat-layout">

  <!-- -- Sidebar -------------------------------------------- -->
  <aside class="chat-sidebar">
    <div class="chat-sidebar-head">
      <h3><i class="fas fa-cog"></i> Admin Options</h3>
    </div>
    <div class="chat-sidebar-body">

      <a href="?view=chat_ad" class="btn-private-chat">
        <i class="fas fa-comment-dots"></i> Private Chat
      </a>

      <span class="chat-nav-label">Navigation</span>
      <ul class="chat-nav-list">
        <li class="chat-nav-item">
          <a href="?view=chatroom_ad">
            <i class="fas fa-inbox"></i> Inbox
          </a>
        </li>
        <li class="chat-nav-item">
          <a href="?view=create_chatroom" class="nav-create active">
            <i class="fas fa-plus-circle"></i> Create Chatroom
          </a>
        </li>
      </ul>

      <!-- Quick stats -->
      <span class="chat-nav-label chat-nav-label-spaced">Overview</span>
      <div class="dash-stat dash-stat-gold">
        <div class="dash-stat-header">
          <span class="dash-stat-label">Total Rooms</span>
          <span class="dash-stat-tag dst-maroon"><i class="fas fa-comments"></i></span>
        </div>
        <div class="dash-stat-num"><?php echo $count ?></div>
        <div class="dash-stat-sub">All chatrooms</div>
      </div>

    </div>
  </aside>

  <!-- -- Main panel ----------------------------------------- -->
  <div>

    <!-- Create chatroom form -->
    <div class="create-form-card">
      <div class="create-form-head">
        <h3><i class="fas fa-plus-circle"></i> Create a New Chatroom</h3>
      </div>
      <div class="create-form-body">
        <form method="POST" id="createRoomForm">
          <div class="create-form-grid">
            <div class="form-field form-field-full">
              <label for="chatroom_name">
                <i class="fas fa-comments"></i> Chatroom Name <span class="req">*</span>
              </label>
              <input type="text" name="chatroom_name" id="chatroom_name"
                     class="form-input" placeholder="e.g. Web Development — Intake 2024" required>
            </div>
            <div class="form-field">
              <label for="chatroom_code">
                <i class="fas fa-key"></i> Access Code <span class="req">*</span>
              </label>
              <input type="text" name="chatroom_code" id="chatroom_code"
                     class="form-input" placeholder="e.g. WD2024" required>
            </div>
            <div class="form-field">
              <label for="chatroom_pass">
                <i class="fas fa-lock"></i> Password <span class="req">*</span>
              </label>
              <input type="text" name="chatroom_pass" id="chatroom_pass"
                     class="form-input" placeholder="e.g. pass1234" required>
            </div>
          </div>
        </form>
      </div>
      <div class="create-form-foot">
        <button type="button" class="btn-modal-cancel" onclick="document.getElementById('createRoomForm').reset()">
          <i class="fas fa-undo"></i> Reset
        </button>
        <button type="submit" form="createRoomForm" class="btn-create-room">
          <i class="fas fa-plus-circle"></i> Create Chatroom
        </button>
      </div>
    </div>

    <!-- Chatroom list -->
    <div class="tbl-card">
      <div class="tbl-toolbar">
        <div class="tbl-title">
          <i class="fas fa-layer-group"></i> All Chatrooms
          <span class="count-pill"><?php echo $count ?> rooms</span>
        </div>
        <div class="toolbar-actions">
          <div class="search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="roomSearch" placeholder="Search chatrooms…">
          </div>
          <button class="btn-refresh" onclick="window.location.reload()">
            <i class="fas fa-sync-alt"></i> Refresh
          </button>
        </div>
      </div>

      <div class="tbl-scroll">
        <?php if ($count < 1): ?>
          <div class="empty tbl-empty-pad">
            <i class="fas fa-comment-slash"></i>
            <p>No chatrooms yet. Create one above.</p>
          </div>
        <?php else: ?>
        <table class="chatroom-table" id="chatroomTable">
          <thead>
            <tr>
              <th>#</th>
              <th>Chatroom Name</th>
              <th class="th-gold">Last Message</th>
              <th>Last Activity</th>
            </tr>
          </thead>
          <tbody>
          <?php
          $i = 1;
          while ($r = mysqli_fetch_array($w)):
            $chatroomName = htmlspecialchars($r['chatroom_name']);
            $roomId       = $r['id'];
            $storeMe      = bin2hex($roomId);
            $dateCreated  = date('M d, Y', strtotime($r['date_created']));

            /* Last message */
            $mRes = $conn->query("SELECT * FROM chatroom_messages WHERE roomid='" . (int)$roomId . "' ORDER BY id DESC LIMIT 1");
            $hasMsg = $mRes && $mRes->num_rows > 0;
            if ($hasMsg) {
                $mRow       = $mRes->fetch_assoc();
                $lastMsg    = htmlspecialchars(mb_substr($mRow['message'], 0, 20)) . '…';
                $lastActive = date('M d, Y  H:i', strtotime($mRow['datesent']));
                $msgClass   = 'has-msg';
                $msgIcon    = 'fa-comment-alt';
            } else {
                $lastMsg    = 'No messages yet';
                $lastActive = $dateCreated;
                $msgClass   = 'no-msg';
                $msgIcon    = 'fa-comment-slash';
            }
          ?>
          <tr data-name="<?php echo strtolower($chatroomName) ?>">
            <td class="row-num"><?php echo $i++ ?></td>
            <td>
              <a href="?view=open_chatroom&room=<?php echo $storeMe ?>" class="chatroom-name-link">
                <span class="chatroom-name-icon"><i class="fas fa-hashtag"></i></span>
                <?php echo $chatroomName ?>
              </a>
            </td>
            <td>
              <span class="msg-preview <?php echo $msgClass ?>">
                <i class="fas <?php echo $msgIcon ?>"></i>
                <?php echo $lastMsg ?>
              </span>
            </td>
            <td class="chatroom-date"><?php echo $lastActive ?></td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>

      <div class="tbl-footer">
        <span id="rowCountLabel">Showing all <?php echo $count ?> chatrooms</span>
        <span class="legend">
          <span class="legend-item"><span class="legend-dot legend-dot-green"></span> Has messages</span>
          <span class="legend-item"><span class="legend-dot legend-dot-muted"></span> Empty</span>
        </span>
      </div>
    </div>

  </div><!-- /main col -->

</div><!-- /.chat-layout -->
</main>

<!-- -- Toast bar -------------------------------------------- -->
<div class="toast-bar" id="toastBar"></div>

<script>
/* -- Show server result toast ----------------------------- */
<?php if ($createResult): ?>
(function () {
  var type = '<?php echo $createResult['type'] ?>';
  var msg  = <?php echo json_encode($createResult['msg']) ?>;
  showToast(type, msg);
}());
<?php endif; ?>

function showToast(type, msg) {
  var bar  = document.getElementById('toastBar');
  var icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
  var el   = document.createElement('div');
  el.className = 'toast toast-' + type;
  el.innerHTML = '<i class="fas ' + icon + '"></i> ' + msg;
  bar.appendChild(el);
  setTimeout(function () { el.remove(); }, 4000);
}

/* -- Live search ------------------------------------------- */
document.getElementById('roomSearch')?.addEventListener('input', function () {
  var q    = this.value.toLowerCase().trim();
  var rows = document.querySelectorAll('#chatroomTable tbody tr');
  var vis  = 0;
  rows.forEach(function (r) {
    var show = !q || (r.dataset.name || '').includes(q);
    r.style.display = show ? '' : 'none';
    if (show) vis++;
  });
  var lbl = document.getElementById('rowCountLabel');
  if (lbl) lbl.textContent = q
    ? 'Showing ' + vis + ' of <?php echo $count ?> chatrooms'
    : 'Showing all <?php echo $count ?> chatrooms';
});
</script>
</body>
</html>