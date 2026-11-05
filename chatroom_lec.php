<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$unik = $unik ?? ($_SESSION[$systemname . "leido"] ?? 0);
$unik = (int)$unik;

$nowdate = $nowdate ?? date('Y-m-d H:i:s');

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo '<div class="app-alert app-alert-danger"><i class="fas fa-exclamation-circle"></i> Database connection not available.</div>';
    return;
}

if ($unik <= 0) {
    echo '<div class="app-alert app-alert-warning"><i class="fas fa-exclamation-triangle"></i> User session not found. Please login again.</div>';
    return;
}

/* ── My registered chatrooms ────────────────────────────── */
$regRooms = false;
$regCount = 0;

$stmt = $conn->prepare("
    SELECT *
    FROM chatroom_reg
    WHERE user_id = ?
    ORDER BY last_msg_date DESC
");

if ($stmt) {
    $stmt->bind_param("i", $unik);
    $stmt->execute();
    $regRooms = $stmt->get_result();
    $regCount = $regRooms ? (int)$regRooms->num_rows : 0;
    $stmt->close();
}

/* ── Course assignments ─────────────────────────────────── */
$lecCourses = [];

$stmtL = $conn->prepare("
    SELECT intake, course_id
    FROM course_registration_lecturers
    WHERE user_id = ?
");

if (!$stmtL) {
    echo '<div class="app-alert app-alert-danger"><i class="fas fa-exclamation-circle"></i> Failed to load lecturer courses.</div>';
    return;
}

$stmtL->bind_param("i", $unik);
$stmtL->execute();
$lecRes = $stmtL->get_result();

while ($dc = $lecRes->fetch_assoc()) {
    $intaken = (int)($dc['intake'] ?? 0);      // course_unit.id
    $cx      = (int)($dc['course_id'] ?? 0);   // courses.id

    if ($intaken <= 0 || $cx <= 0) {
        continue;
    }

    /* Course info */
    $cat = '';
    $courseRawName = 'Unknown Course';

    $stmtC = $conn->prepare("
        SELECT category, name
        FROM courses
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmtC) {
        $stmtC->bind_param("i", $cx);
        $stmtC->execute();
        $cData = $stmtC->get_result()->fetch_assoc();
        $stmtC->close();

        if ($cData) {
            $cat = (string)($cData['category'] ?? '');
            $courseRawName = (string)($cData['name'] ?? 'Unknown Course');
        }
    }

    if ($cat === '') {
        continue;
    }

    $course_name = h($courseRawName);

    /* Course unit name */
    $unitRawName = 'Unknown Unit';

    $stmtU = $conn->prepare("
        SELECT name
        FROM course_unit
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmtU) {
        $stmtU->bind_param("i", $intaken);
        $stmtU->execute();
        $uData = $stmtU->get_result()->fetch_assoc();
        $stmtU->close();

        if ($uData) {
            $unitRawName = (string)($uData['name'] ?? 'Unknown Unit');
        }
    }

    $unit_name = h($unitRawName);

    /* Intakes for this category */
    $intakes = [];

    $stmtI = $conn->prepare("
        SELECT id, name, date_launching
        FROM intakes
        WHERE type = ?
        ORDER BY date_launching DESC
    ");

    if (!$stmtI) {
        continue;
    }

    $stmtI->bind_param("s", $cat);
    $stmtI->execute();
    $iRes = $stmtI->get_result();

    while ($dq = $iRes->fetch_assoc()) {
        $intakeid = (int)($dq['id'] ?? 0);

        if ($intakeid <= 0) {
            continue;
        }

        $intakeRawName = (string)($dq['name'] ?? '');
        $intakedate = '';

        if (!empty($dq['date_launching'])) {
            $time = strtotime((string)$dq['date_launching']);
            $intakedate = $time ? date('Y', $time) : '';
        }

        $roomname = trim($unitRawName . '-' . $intakeRawName . $intakedate);

        /* Check if chatroom exists */
        $groupId = 0;

        $stmtChk = $conn->prepare("
            SELECT id
            FROM chatroom
            WHERE course_id = ?
              AND course_unit_id = ?
              AND intake = ?
            LIMIT 1
        ");

        if (!$stmtChk) {
            continue;
        }

        $stmtChk->bind_param("iii", $cx, $intaken, $intakeid);
        $stmtChk->execute();
        $chkRes = $stmtChk->get_result();
        $roomRow = $chkRes ? $chkRes->fetch_assoc() : null;

        if ($roomRow) {
            $groupId = (int)$roomRow['id'];
        }

        $stmtChk->close();

        /* Auto-create chatroom if missing */
        if ($groupId <= 0) {
            $code = '12345';
            $pass = '12345';
            $createdBy = 'system';

            $stmtIns = $conn->prepare("
                INSERT INTO chatroom
                    (chatroom_name, date_created, created_by, course_unit_id, course_id, intake, code, pass)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if ($stmtIns) {
                $stmtIns->bind_param(
                    "sssiiiss",
                    $roomname,
                    $nowdate,
                    $createdBy,
                    $intaken,
                    $cx,
                    $intakeid,
                    $code,
                    $pass
                );

                if ($stmtIns->execute()) {
                    $groupId = (int)$stmtIns->insert_id;
                }

                $stmtIns->close();
            }
        }

        if ($groupId > 0) {
            $intakes[] = [
                'id'    => $groupId,
                'store' => bin2hex((string)$groupId),
                'name'  => h($intakeRawName),
                'year'  => h($intakedate),
            ];
        }
    }

    $stmtI->close();

    $lecCourses[] = [
        'course_name' => $course_name,
        'unit_name'   => $unit_name,
        'intakes'     => $intakes,
    ];
}

$stmtL->close();
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
      <li class="active">My Chatrooms</li>
    </ol>
  </div>
  <div class="topbar-right"><i class="fas fa-clock"></i> <?php echo date("h:i a") ?></div>
</nav>

<main class="page">
<div class="chat-layout">

  <!-- ── Sidebar ──────────────────────────────────────────── -->
  <aside class="chat-sidebar">
    <div class="chat-sidebar-head">
      <h3><i class="fas fa-chalkboard-teacher"></i> Instructor</h3>
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

      <!-- Stats -->
      <span class="chat-nav-label chat-nav-label-spaced">Overview</span>
      <div class="dash-stat dash-stat-gold">
        <div class="dash-stat-header">
          <span class="dash-stat-label">Registered</span>
          <span class="dash-stat-tag dst-maroon"><i class="fas fa-comments"></i></span>
        </div>
        <div class="dash-stat-num"><?php echo $regCount ?></div>
        <div class="dash-stat-sub">Joined chatrooms</div>
      </div>
      <div class="dash-stat dash-stat-mt">
        <div class="dash-stat-header">
          <span class="dash-stat-label">Courses</span>
          <span class="dash-stat-tag dst-gold"><i class="fas fa-chalkboard"></i></span>
        </div>
        <div class="dash-stat-num sv gold"><?php echo count($lecCourses) ?></div>
        <div class="dash-stat-sub">Assigned units</div>
      </div>

    </div>
  </aside>

  <!-- ── Main panel ───────────────────────────────────────── -->
  <div class="chat-main">

    <div class="chat-main-toolbar">
      <div class="chat-main-title">
        <i class="fas fa-layer-group"></i> My Chatrooms
        <span class="count-pill"><?php echo $regCount ?> joined</span>
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
            <th>Chatroom / Module</th>
            <th class="th-gold">Intake Rooms</th>
            <th>Last Active</th>
          </tr>
        </thead>
        <tbody>

          <?php
          /* ── Section 1: Course-unit rooms ───────────────── */
          if (!empty($lecCourses)):
            foreach ($lecCourses as $lc):
          ?>
          <!-- Course group header -->
          <tr class="chat-group-row">
            <td colspan="3">
              <i class="fas fa-graduation-cap"></i>
              <?php echo $lc['course_name'] ?>
              <span class="chat-group-sub">&mdash; <?php echo $lc['unit_name'] ?></span>
            </td>
          </tr>

          <?php if (empty($lc['intakes'])): ?>
          <tr class="chat-row read">
            <td colspan="3">
              <div class="no-intakes-info"><i class="fas fa-info-circle"></i> No intakes found for this unit.</div>
            </td>
          </tr>
          <?php else: ?>
          <tr class="chat-row read" data-name="<?php echo strtolower($lc['unit_name']) ?>">
            <td>
              <div class="room-link">
                <span class="room-icon"><i class="fas fa-cube"></i></span>
                <?php echo $lc['unit_name'] ?>
              </div>
            </td>
            <td>
              <div class="intake-chips">
                <?php foreach ($lc['intakes'] as $intake): ?>
                <a href="?view=open_chatroom&room=<?php echo $intake['store'] ?>"
                   class="intake-chip">
                  <i class="fas fa-hashtag"></i>
                  <?php echo $intake['name'] ?> <?php echo $intake['year'] ?>
                </a>
                <?php endforeach; ?>
              </div>
            </td>
            <td class="chat-date"><?php echo date('M d, Y') ?></td>
          </tr>
          <?php endif; ?>

          <?php
            endforeach;
          endif;
          ?>

          <?php
          /* ── Section 2: Joined chatrooms ────────────────── */
          if ($regCount < 1):
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
            while ($newer = $regRooms->fetch_assoc()):
              $roomId    = (int)($newer['roomid'] ?? 0);
              $hasRead   = $newer['hasread'] ?? 'yes';
              $rowClass  = $hasRead === 'yes' ? 'read' : 'unread';
              $dateTime  = $newer['last_msg_date'] ?? '';
              $lastMsgId = (int)($newer['last_msg_id'] ?? 0);
              $storeMe   = bin2hex((string)$roomId);

              $stmtR = $conn->prepare("SELECT chatroom_name FROM chatroom WHERE id=? LIMIT 1");
              $stmtR->bind_param("i", $roomId); $stmtR->execute();
              $roomInfo  = $stmtR->get_result()->fetch_assoc();
              $roomNameJ = htmlspecialchars($roomInfo['chatroom_name'] ?? 'Chatroom');

              $lastMsg  = ''; $hasMsg = false;
              if ($lastMsgId > 0) {
                  $stmtM = $conn->prepare("SELECT message FROM chatroom_messages WHERE id=? LIMIT 1");
                  $stmtM->bind_param("i", $lastMsgId); $stmtM->execute();
                  $mRow   = $stmtM->get_result()->fetch_assoc();
                  $lastMsg = trim($mRow['message'] ?? '');
                  $hasMsg  = $lastMsg !== '';
              }
              if (!$hasMsg) $rowClass = 'read';
              $msgDisplay = $hasMsg ? htmlspecialchars(mb_substr($lastMsg, 0, 18)) . '...' : 'No messages yet';
              $msgClass   = $hasMsg ? 'has-msg' : 'no-msg';
              $msgIcon    = $hasMsg ? 'fa-comment-alt' : 'fa-comment-slash';
          ?>
          <tr class="chat-row <?php echo $rowClass ?>" data-name="<?php echo strtolower($roomNameJ) ?>">
            <td>
              <a href="?view=open_chatroom&room=<?php echo urlencode($storeMe) ?>"
                 class="room-link <?php echo $rowClass === 'unread' ? 'room-link-unread' : '' ?>">
                <span class="room-icon"><i class="fas fa-comments"></i></span>
                <?php echo $roomNameJ ?>
                <?php if ($rowClass === 'unread'): ?>
                  <span class="unread-dot"></span>
                <?php endif; ?>
              </a>
            </td>
            <td>
              <span class="msg-preview <?php echo $msgClass ?>">
                <i class="fas <?php echo $msgIcon ?>"></i>
                <?php echo $msgDisplay ?>
              </span>
            </td>
            <td class="chat-date">
              <?php echo $dateTime ? date('M d, Y  H:i', strtotime($dateTime)) : '—' ?>
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
        <input type="text" id="codeInput" class="form-control-app" placeholder="Enter chatroom code">
      </div>
      <div>
        <label class="ctrl-label" for="passInput">
          <i class="fas fa-lock"></i> Chatroom Password
        </label>
        <input type="text" id="passInput" class="form-control-app" placeholder="Enter chatroom password">
      </div>
      <div class="join-response" id="joinResponse"></div>
    </div>
    <div class="join-modal-foot">
      <button class="btn-modal-cancel" onclick="closeJoinModal()">
        <i class="fas fa-times"></i> Cancel
      </button>
      <button class="btn-join-room" onclick="joincc('<?php echo htmlspecialchars((string)$unik, ENT_QUOTES) ?>')">
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
    url: 'scripts/join_chatroom.php',
    data: { code: code, pass: pass, unik: unik },
    success: function(data) {
      if (String(data).trim() === 'success') {
        closeJoinModal(); location.reload();
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