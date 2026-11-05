<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$unik    = $_SESSION[$systemname . "leido"] ?? '';
$nowdate = date('Y-m-d H:i:s');

/* ── Chatrooms the user is registered in ───────────────── */
$newquery = "SELECT * FROM chatroom_reg WHERE user_id='" . $conn->real_escape_string($unik) . "' ORDER BY last_msg_date DESC";
$new      = $conn->query($newquery) or die(mysqli_error($conn));

/* ── Active courses (not done) ──────────────────────────── */
$cq  = "SELECT * FROM course_registration WHERE user_id='" . $conn->real_escape_string($_SESSION[$systemname . "leido"]) . "' AND status!='done'";
$cqr = $conn->query($cq) or die(mysqli_error($conn));
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

<!-- ── Topbar ─────────────────────────────────────────────── -->
<nav class="topbar">
  <div class="topbar-left">
    <h1><i class="fas fa-comments"></i> <?php echo htmlspecialchars($systemname) ?> &mdash; Chatrooms</h1>
    <ol class="breadcrumb">
      <li><a href="?"><i class="fas fa-home"></i> Home</a></li>
      <li class="active">Chatrooms</li>
    </ol>
  </div>
  <div class="topbar-right"><i class="fas fa-clock"></i> <?php echo date("h:i a") ?></div>
</nav>

<main class="page">
<div class="chat-layout">

  <!-- ── Sidebar ──────────────────────────────────────────── -->
  <aside class="chat-sidebar">
    <div class="chat-sidebar-head">
      <h3><i class="fas fa-comments"></i> Chatrooms</h3>
    </div>
    <div class="chat-sidebar-body">

      <a href="?do=chat" class="btn-private-chat">
        <i class="fas fa-comment-dots"></i> Private Chat
      </a>

      <span class="chat-nav-label">Options</span>
      <ul class="chat-nav-list">
        <li class="chat-nav-item">
          <a href="?view=chatroom" class="active">
            <i class="fas fa-inbox"></i> Inbox
          </a>
        </li>
        <li class="chat-nav-item">
          <button class="chat-nav-item-btn" onclick="openJoinModal()">
            <i class="fas fa-plus-circle"></i> Join Chatroom
          </button>
        </li>
      </ul>

    </div>
  </aside>

  <!-- ── Main panel ───────────────────────────────────────── -->
  <div class="chat-main">

    <div class="chat-main-toolbar">
      <div class="chat-main-title">
        <i class="fas fa-layer-group"></i> My Chatrooms
      </div>
      <div class="chat-toolbar-actions">
        <div class="search-wrap">
          <i class="fas fa-search"></i>
          <input type="text" id="roomSearch" placeholder="Search rooms...">
        </div>
        <button class="btn-refresh" onclick="window.location.reload()">
          <i class="fas fa-sync-alt"></i> Refresh
        </button>
      </div>
    </div>

    <div class="chat-table-wrap">
      <table class="chat-table" id="chatTable">
        <thead>
          <tr>
            <th>Chatroom</th>
            <th class="th-gold">Last Message</th>
            <th>Last Active</th>
          </tr>
        </thead>
        <tbody>

          <?php
          /* ── Section 1: Course module rooms (auto-created) ── */
          if ($cqr->num_rows > 0):
            while ($dc = mysqli_fetch_array($cqr)):
              $intaken    = $dc['intake'];
              $cx         = $dc['course_id'];

              $courseRes  = $conn->query("SELECT * FROM courses WHERE id='" . (int)$cx . "'");
              $courseData = mysqli_fetch_array($courseRes);
              $courseName = htmlspecialchars($courseData['name'] ?? '');
              $cat        = $courseData['category'] ?? '';

              $intakeRes  = $conn->query("SELECT * FROM intakes WHERE id='" . (int)$intaken . "'");
              $intakeData = mysqli_fetch_array($intakeRes);
              $intakeName = htmlspecialchars($intakeData['name'] ?? '');
              $intakeYear = $intakeData['date_launching'] ? date('Y', strtotime($intakeData['date_launching'])) : '';
          ?>
          <!-- Course group header -->
          <tr class="chat-group-row">
            <td colspan="3">
              <i class="fas fa-graduation-cap"></i>
              <?php echo $courseName ?> <span class="chat-group-sub">(<?php echo $intakeName ?> &mdash; <?php echo $intakeYear ?>)</span>
            </td>
          </tr>

          <?php
              /* Module rooms */
              $moduleRes = $conn->query("SELECT * FROM course_unit WHERE course_id='" . (int)$cx . "' ORDER BY order_number");
              while ($axe = mysqli_fetch_array($moduleRes)):
                $unitName = htmlspecialchars($axe['name']);
                $unitId   = (int)$axe['id'];
                $roomName = $axe['name'] . ' -' . $intakeName . $intakeYear;

                /* Auto-create chatroom if missing */
                $vvRes = $conn->query("SELECT * FROM chatroom WHERE course_id='" . (int)$cx . "' AND course_unit_id='$unitId' AND intake='" . (int)$intaken . "'");
                if ($vvRes->num_rows < 1) {
                    $conn->query("INSERT INTO chatroom (chatroom_name,date_created,created_by,course_unit_id,course_id,intake,code,pass) VALUES ('" . $conn->real_escape_string($roomName) . "','$nowdate','system','$unitId','" . (int)$cx . "','" . (int)$intaken . "','12345','12345')");
                    $vvRes = $conn->query("SELECT * FROM chatroom WHERE course_id='" . (int)$cx . "' AND course_unit_id='$unitId' AND intake='" . (int)$intaken . "'");
                }
                $roomRow = mysqli_fetch_array($vvRes);
                $groupId = $roomRow['id'];
                $storeMe = bin2hex($groupId);
          ?>
          <tr class="chat-row read" data-name="<?php echo strtolower($unitName) ?>">
            <td>
              <a href="?view=open_chatroom&room=<?php echo $storeMe ?>" class="room-link">
                <span class="room-icon"><i class="fas fa-hashtag"></i></span>
                <?php echo $unitName ?>
              </a>
            </td>
            <td><span class="msg-preview no-msg"><i class="fas fa-comment-slash"></i> Module room</span></td>
            <td class="chat-date"><?php echo date('M d, Y') ?></td>
          </tr>
          <?php
              endwhile; // modules
            endwhile; // courses
          endif;
          ?>

          <?php
          /* ── Section 2: Joined chatrooms ────────────────── */
          if ($new->num_rows < 1):
          ?>
          <tr class="chat-empty-row">
            <td colspan="3">
              <div class="chat-empty-inner">
                <i class="fas fa-comment-slash"></i>
                <p>You have not joined any external chatrooms yet.<br>
                  <a href="#" onclick="openJoinModal()" class="link-maroon-bold">Join one now</a>
                </p>
              </div>
            </td>
          </tr>
          <?php
          else:
            while ($newer = mysqli_fetch_array($new)):
              $roomId     = $newer['roomid'];
              $hasRead    = $newer['hasread'];
              $rowClass   = ($hasRead === 'yes') ? 'read' : 'unread';
              $dateTime   = $newer['last_msg_date'];
              $lastMsgId  = $newer['last_msg_id'];
              $storeMe    = bin2hex($roomId);

              $roomInfo   = $conn->query("SELECT * FROM chatroom WHERE id='" . (int)$roomId . "'")->fetch_assoc();
              $roomNameJ  = htmlspecialchars($roomInfo['chatroom_name'] ?? '');

              $msgRes     = $conn->query("SELECT * FROM chatroom_messages WHERE id='" . (int)$lastMsgId . "'")->fetch_assoc();
              $lastMsg    = trim((string)($msgRes['message'] ?? ''));

              $hasMsg     = $lastMsg !== '';
              $msgClass   = $hasMsg ? 'has-msg' : 'no-msg';
              $msgIcon    = $hasMsg ? 'fa-comment-alt' : 'fa-comment-slash';
              $msgDisplay = $hasMsg ? htmlspecialchars(mb_substr($lastMsg, 0, 18)) . '...' : 'No messages yet';
              if (!$hasMsg) $rowClass = 'read';
          ?>
          <tr class="chat-row <?php echo $rowClass ?>" data-name="<?php echo strtolower($roomNameJ) ?>">
            <td>
              <a href="?view=open_chatroom&room=<?php echo $storeMe ?>"
                 class="room-link <?php echo $rowClass === 'unread' ? 'room-link-unread' : '' ?>">
                <span class="room-icon"><i class="fas fa-comments"></i></span>
                <?php echo $roomNameJ ?>
                <?php if ($rowClass === 'unread'): ?>
                  <span class="unread-dot"></span>
                <?php endif; ?>
              </a>
            </td>
            <td>
              <a href="?view=open_chatroom&room=<?php echo $storeMe ?>"
                 class="msg-preview <?php echo $msgClass ?>">
                <i class="fas <?php echo $msgIcon ?>"></i>
                <?php echo $msgDisplay ?>
              </a>
            </td>
            <td class="chat-date">
              <?php echo $dateTime ? date('M d, Y  H:i', strtotime($dateTime)) : '-' ?>
            </td>
          </tr>
          <?php
            endwhile;
          endif;
          ?>

        </tbody>
      </table>
    </div>

  </div><!-- /.chat-main -->

</div><!-- /.chat-layout -->
</main>

<!-- ── Join Chatroom Modal ─────────────────────────────────── -->
<div class="join-modal-overlay" id="joinModal">
  <div class="join-modal-box">
    <div class="join-modal-head">
      <div>
        <h3><i class="fas fa-plus-circle"></i> Join a Chatroom</h3>
        <p>Share knowledge with the wider community</p>
      </div>
      <button class="modal-close" onclick="closeJoinModal()"><i class="fas fa-times"></i></button>
    </div>

    <div class="join-modal-body">
      <div class="mb-14">
        <label class="ctrl-label" for="codeInput">
          <i class="fas fa-key"></i> Chatroom Code
        </label>
        <input type="text" id="codeInput" class="form-control-app" placeholder="Enter chatroom code" required>
      </div>
      <div>
        <label class="ctrl-label" for="passInput">
          <i class="fas fa-lock"></i> Chatroom Password
        </label>
        <input type="text" id="passInput" class="form-control-app" placeholder="Enter chatroom password" required>
      </div>
      <div class="join-response" id="joinResponse"></div>
    </div>

    <div class="join-modal-foot">
      <button class="btn-modal-cancel" onclick="closeJoinModal()">
        <i class="fas fa-times"></i> Cancel
      </button>
      <button class="btn-join-room"
              onclick="joincc('<?php echo htmlspecialchars($unik) ?>')">
        <i class="fas fa-sign-in-alt"></i> Join Room
      </button>
    </div>
  </div>
</div>

<script>
/* ── Modal ──────────────────────────────────────────────── */
function openJoinModal()  {
  document.getElementById('joinModal').classList.add('is-open');
  document.body.style.overflow = 'hidden';
}
function closeJoinModal() {
  document.getElementById('joinModal').classList.remove('is-open');
  document.body.style.overflow = '';
  document.getElementById('joinResponse').textContent = '';
  document.getElementById('codeInput').value = '';
  document.getElementById('passInput').value = '';
}
document.getElementById('joinModal').addEventListener('click', function(e) {
  if (e.target === this) closeJoinModal();
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeJoinModal();
});

/* ── Join chatroom ──────────────────────────────────────── */
function joincc(unik) {
  var code = document.getElementById('codeInput').value.trim();
  var pass = document.getElementById('passInput').value.trim();
  var res  = document.getElementById('joinResponse');

  res.className = 'join-response';
  res.textContent = '';

  if (!code || !pass) {
    res.className = 'join-response join-response-error';
    res.textContent = 'Please enter both the code and password.';
    return;
  }

  $.ajax({
    type: 'POST',
    data: { code: code, pass: pass, unik: unik },
    url: 'scripts/join_chatroom.php',
    success: function(data) {
      if (data === 'success') {
        closeJoinModal();
        location.reload();
      } else {
        res.className = 'join-response join-response-error';
        res.innerHTML = data;
      }
    },
    error: function() {
      res.className = 'join-response join-response-error';
      res.textContent = 'Network error. Please try again.';
    }
  });
}

/* ── Live search ────────────────────────────────────────── */
document.getElementById('roomSearch')?.addEventListener('input', function() {
  var q = this.value.toLowerCase().trim();
  document.querySelectorAll('#chatTable tbody tr.chat-row').forEach(function(r) {
    r.style.display = (!q || (r.dataset.name || '').includes(q)) ? '' : 'none';
  });
});
</script>
</body>
</html>