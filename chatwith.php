<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$unik    = $unik    ?? ($_SESSION[$systemname . "leido"] ?? '');
$nowdate = date('Y-m-d H:i:s');

/* -- Validate recipient ----------------------------------- */
if (empty($_GET['pr']) || trim($_GET['pr']) === '') {
    ?>
    <!doctype html><html lang="en"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="app.css">
    </head><body>
    <nav class="topbar">
      <div class="topbar-left"><h1><i class="fas fa-comment-dots"></i> Private Chat</h1></div>
      <div class="topbar-right"><i class="fas fa-clock"></i> <?php echo date("h:i a") ?></div>
    </nav>
    <main class="page">
      <div class="chat-invalid">
        <i class="fas fa-exclamation-triangle"></i>
        <p>Invalid page directive. <a href="?view=chatroom">Return to chatrooms</a></p>
      </div>
    </main></body></html>
    <?php
    exit;
}

$targetUser = strip_tags(trim($_GET['pr']));

/* -- Recipient data --------------------------------------- */
$uRes   = $conn->query("SELECT * FROM users WHERE unique_id='" . $conn->real_escape_string($targetUser) . "' LIMIT 1");
$uData  = mysqli_fetch_array($uRes);
$rName  = htmlspecialchars(trim(($uData['fname'] ?? '') . ' ' . ($uData['lname'] ?? '') . ' ' . ($uData['other_name'] ?? '')));
$rRole  = $uData['acc_type'] ?? '';
$rDp    = $uData['dp'] ?? 'default.jpg';
$rDpSrc = ($rDp === 'default.jpg') ? 'images/default_p_pics/user.png' : 'images/user_p_pics/' . $rDp;

function roleLabel(string $code): array {
    return match ($code) {
        'xybrtw' => ['Admin',      'role-admin'],
        'afgezt' => ['Student',    'role-student'],
        'xtfbry' => ['Instructor', 'role-instructor'],
        'zyffwq' => ['Accounts',   'role-accounts'],
        default  => ['User',       'role-student'],
    };
}
[$rRoleLabel, $rRoleClass] = roleLabel($rRole);

/* -- Message count ---------------------------------------- */
$msgCount = $conn->query("SELECT COUNT(*) AS c FROM chats WHERE (from_id='" . $conn->real_escape_string($targetUser) . "' AND to_id='" . $conn->real_escape_string($unik) . "') OR (from_id='" . $conn->real_escape_string($unik) . "' AND to_id='" . $conn->real_escape_string($targetUser) . "')")->fetch_assoc()['c'] ?? 0;

/* -- Ensure chat_reg entry -------------------------------- */
$regCheck = $conn->query("SELECT id FROM chat_reg WHERE (user1='" . $conn->real_escape_string($targetUser) . "' AND user2='" . $conn->real_escape_string($unik) . "') OR (user1='" . $conn->real_escape_string($unik) . "' AND user2='" . $conn->real_escape_string($targetUser) . "') LIMIT 1");
if ($regCheck->num_rows < 1) {
    $conn->query("INSERT INTO chat_reg (user1, user2, last_msg_date) VALUES ('" . $conn->real_escape_string($targetUser) . "','" . $conn->real_escape_string($unik) . "','$nowdate')");
}

/* -- Contacts list (all chats) ---------------------------- */
$contacts = [];
$cRes = $conn->query("SELECT * FROM chat_reg WHERE user1='" . $conn->real_escape_string($unik) . "' OR user2='" . $conn->real_escape_string($unik) . "' ORDER BY last_msg_date DESC");
while ($cr = mysqli_fetch_array($cRes)) {
    $otherId   = ($unik === $cr['user1']) ? $cr['user2'] : $cr['user1'];
    $oRes      = $conn->query("SELECT * FROM users WHERE unique_id='" . $conn->real_escape_string($otherId) . "' LIMIT 1");
    $oData     = mysqli_fetch_array($oRes);
    [$oLabel, $oClass] = roleLabel($oData['acc_type'] ?? '');
    $oDp       = $oData['dp'] ?? 'default.jpg';
    $contacts[] = [
        'id'       => $otherId,
        'name'     => htmlspecialchars(trim(($oData['fname'] ?? '') . ' ' . ($oData['lname'] ?? ''))),
        'role'     => $oLabel,
        'rclass'   => $oClass,
        'dp'       => ($oDp === 'default.jpg') ? 'images/default_p_pics/user.png' : 'images/user_p_pics/' . $oDp,
        'active'   => ($otherId === $targetUser),
    ];
}

/* -- Messages --------------------------------------------- */
$messages = [];
$mRes = $conn->query("SELECT * FROM chats WHERE (from_id='" . $conn->real_escape_string($targetUser) . "' AND to_id='" . $conn->real_escape_string($unik) . "') OR (from_id='" . $conn->real_escape_string($unik) . "' AND to_id='" . $conn->real_escape_string($targetUser) . "') ORDER BY id DESC");
while ($msg = mysqli_fetch_array($mRes)) {
    $senderId = $msg['from_id'];
    $sRes     = $conn->query("SELECT * FROM users WHERE unique_id='" . $conn->real_escape_string($senderId) . "' LIMIT 1");
    $sData    = mysqli_fetch_array($sRes);
    [$sLabel, $sClass] = roleLabel($sData['acc_type'] ?? '');
    $sDp      = $sData['dp'] ?? 'default.jpg';
    $messages[] = [
        'text'      => htmlspecialchars($msg['message']),
        'sender_id' => $senderId,
        'name'      => htmlspecialchars(trim(($sData['fname'] ?? '') . ' ' . ($sData['lname'] ?? ''))),
        'role'      => $sLabel,
        'rclass'    => $sClass,
        'dp'        => ($sDp === 'default.jpg') ? 'images/default_p_pics/user.png' : 'images/user_p_pics/' . $sDp,
        'date'      => date('d M Y, h:i a', strtotime($msg['datesent'])),
        'outgoing'  => ($senderId === $unik),
    ];
}

/* Mark incoming as seen */
$conn->query("UPDATE chats SET seen='yes' WHERE from_id='" . $conn->real_escape_string($targetUser) . "' AND to_id='" . $conn->real_escape_string($unik) . "'");
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
      <li><a href="?view=chatroom">Chatrooms</a></li>
      <li class="active"><?php echo $rName ?></li>
    </ol>
  </div>
  <div class="topbar-right"><i class="fas fa-clock"></i> <?php echo date("h:i a") ?></div>
</nav>

<main class="page">
<div class="chat-page-layout">

  <!-- -- Contacts sidebar ----------------------------------- -->
  <aside class="contacts-panel">
    <div class="contacts-panel-head">
      <h3><i class="fas fa-users"></i> Conversations</h3>
    </div>
    <div class="contacts-search">
      <div class="search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" id="contactSearch" placeholder="Search contacts...">
      </div>
    </div>
    <div class="contacts-list" id="contactsList">
      <?php if (empty($contacts)): ?>
        <div class="todo-empty"><i class="fas fa-user-slash"></i>No conversations yet.</div>
      <?php else: ?>
        <?php foreach ($contacts as $c): ?>
        <a href="?view=chatwith&pr=<?php echo htmlspecialchars($c['id']) ?>"
           class="contact-item <?php echo $c['active'] ? 'active' : '' ?>"
           data-name="<?php echo strtolower($c['name']) ?>">
          <div class="contact-avatar">
            <img src="<?php echo htmlspecialchars($c['dp']) ?>" alt="<?php echo $c['name'] ?>">
          </div>
          <div>
            <div class="contact-name"><?php echo $c['name'] ?></div>
            <span class="contact-role-tag <?php echo $c['rclass'] ?>"><?php echo $c['role'] ?></span>
          </div>
        </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </aside>

  <!-- -- Chat window ---------------------------------------- -->
  <div class="chat-window">

    <!-- Header -->
    <div class="chat-window-head">
      <div class="chat-window-head-info">
        <div class="chat-window-avatar">
          <img src="<?php echo htmlspecialchars($rDpSrc) ?>" alt="<?php echo $rName ?>">
        </div>
        <div>
          <div class="chat-window-name"><?php echo $rName ?></div>
          <div class="chat-window-meta">
            <i class="fas fa-circle presence-dot"></i>
            <span class="contact-role-tag <?php echo $rRoleClass ?>"><?php echo $rRoleLabel ?></span>
          </div>
        </div>
      </div>
      <span class="chat-msg-count">
        <i class="fas fa-comment-alt"></i> <?php echo $msgCount ?> message<?php echo $msgCount !== 1 ? 's' : '' ?>
      </span>
    </div>

    <!-- Messages -->
    <div class="chat-messages" id="chatMessages">
      <?php if (empty($messages)): ?>
        <div class="chat-no-messages">
          <i class="fas fa-comment-dots"></i>
          <p>No messages yet. Say hello!</p>
        </div>
      <?php else: ?>
        <?php foreach ($messages as $msg): ?>
        <div class="chat-bubble-wrap <?php echo $msg['outgoing'] ? 'outgoing' : '' ?>">

          <div class="bubble-avatar">
            <img src="<?php echo htmlspecialchars($msg['dp']) ?>" alt="<?php echo $msg['name'] ?>">
          </div>

          <div class="bubble-content">
            <div class="bubble-sender">
              <?php if (!$msg['outgoing']): ?>
                <?php echo $msg['name'] ?>
                <span class="contact-role-tag <?php echo $msg['rclass'] ?>"><?php echo $msg['role'] ?></span>
              <?php else: ?>
                <span class="contact-role-tag <?php echo $msg['rclass'] ?>"><?php echo $msg['role'] ?></span>
                You
              <?php endif; ?>
            </div>
            <div class="bubble <?php echo $msg['outgoing'] ? 'bubble-outgoing' : 'bubble-incoming' ?>">
              <?php echo $msg['text'] ?>
            </div>
            <span class="bubble-time"><?php echo $msg['date'] ?></span>
          </div>

        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Compose -->
    <div class="chat-compose">
      <textarea id="messageInput" placeholder="Type a message..." rows="1"
        onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg()}"></textarea>
      <input type="hidden" id="recipientId" value="<?php echo htmlspecialchars($targetUser) ?>">
      <button class="btn-send" id="sendBtn" onclick="sendMsg()">
        <span class="spin" id="sendSpin"></span>
        <i class="fas fa-paper-plane" id="sendIcon"></i> Send
      </button>
    </div>

  </div><!-- /.chat-window -->

</div><!-- /.chat-page-layout -->
</main>

<script>
/* -- Auto-grow textarea ----------------------------------- */
const ta = document.getElementById('messageInput');
ta.addEventListener('input', function () {
  this.style.height = 'auto';
  this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

/* -- Send message ----------------------------------------- */
function sendMsg() {
  var msg  = document.getElementById('messageInput').value.trim();
  var f    = document.getElementById('recipientId').value;
  var btn  = document.getElementById('sendBtn');
  var spin = document.getElementById('sendSpin');
  var icon = document.getElementById('sendIcon');

  if (!msg || !f) return;

  btn.disabled = true;
  spin.classList.add('active');
  icon.style.display = 'none';

  $.ajax({
    type: 'POST',
    data: { message: msg, f: f, unik: '<?php echo htmlspecialchars($unik) ?>' },
    url: 'scripts/send_chat.php',
    success: function () { location.reload(); },
    error:   function () {
      btn.disabled = false;
      spin.classList.remove('active');
      icon.style.display = '';
    }
  });
}

/* -- Contact search --------------------------------------- */
document.getElementById('contactSearch')?.addEventListener('input', function () {
  var q = this.value.toLowerCase().trim();
  document.querySelectorAll('#contactsList .contact-item').forEach(function (el) {
    el.style.display = (!q || (el.dataset.name || '').includes(q)) ? '' : 'none';
  });
});
</script>
</body>
</html>