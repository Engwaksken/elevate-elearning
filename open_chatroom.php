<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}



function decodeRoomId(string $roomParam): int {
    $roomParam = trim($roomParam);

    if ($roomParam === '') {
        return 0;
    }

    // If room is plain numeric
    if (ctype_digit($roomParam)) {
        return (int)$roomParam;
    }

    // If room is hex encoded, e.g. bin2hex("12")
    if (ctype_xdigit($roomParam) && strlen($roomParam) % 2 === 0) {
        $decoded = hex2bin($roomParam);
        if ($decoded !== false && ctype_digit($decoded)) {
            return (int)$decoded;
        }
    }

    return 0;
}

function renderInvalidPage(string $message, string $systemname = 'System'): void {
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <link rel="stylesheet" href="app.css">
        <title><?= h($systemname) ?> - Chatroom</title>
    </head>
    <body>
        <nav class="topbar">
            <div class="topbar-left">
                <h1><i class="fas fa-comments"></i> Chatroom</h1>
            </div>
            <div class="topbar-right">
                <i class="fas fa-clock"></i> <?= date("h:i a") ?>
            </div>
        </nav>

        <main class="page">
            <div class="chat-invalid">
                <i class="fas fa-exclamation-triangle"></i>
                <p><?= h($message) ?> <a href="?view=chatroom">Return to chatrooms</a></p>
            </div>
        </main>
    </body>
    </html>
    <?php
    exit;
}

$roomParam = $_GET['room'] ?? '';
$roomid = decodeRoomId((string)$roomParam);

if ($roomid <= 0) {
    renderInvalidPage('Invalid page directive.', $systemname ?? 'System');
}

$userId = trim((string)($_SESSION[$systemname . "leido"] ?? ''));

if ($userId === '') {
    renderInvalidPage('Your session has expired. Please login again.', $systemname ?? 'System');
}

/* Fetch room */
$stmt = $conn->prepare("SELECT id, chatroom_name FROM chatroom WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $roomid);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
    renderInvalidPage('Chatroom not found.', $systemname ?? 'System');
}

$chatroom_name = $room['chatroom_name'] ?? 'Chatroom';

/* Message count */
$stmt = $conn->prepare("SELECT COUNT(*) AS total_messages FROM chatroom_messages WHERE roomid = ?");
$stmt->bind_param("i", $roomid);
$stmt->execute();
$countRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

$msgCount = (int)($countRow['total_messages'] ?? 0);

/* Fetch messages with sender info in one query */
$stmt = $conn->prepare("
    SELECT 
        m.id,
        m.from_id,
        m.message,
        m.datesent,
        u.fname,
        u.lname,
        u.acc_type,
        u.dp
    FROM chatroom_messages m
    LEFT JOIN users u ON u.unique_id = m.from_id
    WHERE m.roomid = ?
    ORDER BY m.id ASC
");
$stmt->bind_param("i", $roomid);
$stmt->execute();
$msgRes = $stmt->get_result();

$roleMap = [
    'xybrtw' => ['Admin', 'role-admin'],
    'afgezt' => ['Student', 'role-student'],
    'xtfbry' => ['Instructor', 'role-instructor'],
    'zyffwq' => ['Accounts', 'role-accounts'],
];

$messages = [];

while ($m = $msgRes->fetch_assoc()) {
    $sender = (string)($m['from_id'] ?? '');
    $fullName = trim((string)($m['fname'] ?? '') . ' ' . (string)($m['lname'] ?? ''));

    if ($fullName === '') {
        $fullName = 'Unknown User';
    }

    $dp = $m['dp'] ?: 'default.jpg';
    $dpSrc = ($dp === 'default.jpg')
        ? 'images/default_p_pics/user.png'
        : 'images/user_p_pics/' . $dp;

    [$roleLabel, $roleClass] = $roleMap[$m['acc_type'] ?? ''] ?? ['User', 'role-student'];

    $dateSent = $m['datesent'] ?? date('Y-m-d H:i:s');
    $timestamp = strtotime((string)$dateSent);

    if (!$timestamp) {
        $timestamp = time();
    }

    $messages[] = [
        'text' => (string)($m['message'] ?? ''),
        'sender_id' => $sender,
        'name' => $fullName,
        'role' => $roleLabel,
        'rclass' => $roleClass,
        'dp' => $dpSrc,
        'date' => date('d M Y, h:i a', $timestamp),
        'day' => date('Y-m-d', $timestamp),
        'outgoing' => hash_equals($userId, $sender),
    ];
}

$stmt->close();

/* Mark as read */
$stmt = $conn->prepare("UPDATE chatroom_reg SET hasread = 'yes' WHERE roomid = ? AND user_id = ?");
$stmt->bind_param("is", $roomid, $userId);
$stmt->execute();
$stmt->close();

$encodedUserId = h($userId);
$encodedRoomParam = h((string)$roomParam);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= h($systemname ?? 'System') ?> - <?= h($chatroom_name) ?></title>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="app.css">
</head>
<body>

<nav class="topbar">
    <div class="topbar-left">
        <h1>
            <i class="fas fa-comments"></i>
            <?= h($systemname ?? 'System') ?> &mdash; Chatroom
        </h1>

        <ol class="breadcrumb">
            <li><a href="?"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="?view=chatroom">Chatrooms</a></li>
            <li class="active"><?= h($chatroom_name) ?></li>
        </ol>
    </div>

    <div class="topbar-right">
        <i class="fas fa-clock"></i> <?= date("h:i a") ?>
    </div>
</nav>

<main class="page">
    <div class="chat-window">

        <div class="chat-window-head">
            <div class="chat-window-head-info">
                <div class="room-header-icon">
                    <i class="fas fa-hashtag"></i>
                </div>

                <div>
                    <div class="room-header-name"><?= h($chatroom_name) ?></div>
                    <div class="room-header-sub">
                        <i class="fas fa-comments"></i> Group Chatroom
                        <i class="fas fa-circle presence-dot"></i>
                        <?= date('l, d F Y') ?>
                    </div>
                </div>
            </div>

            <div class="room-header-right">
                <span class="room-msg-count">
                    <i class="fas fa-comment-alt"></i>
                    <?= $msgCount ?> message<?= $msgCount !== 1 ? 's' : '' ?>
                </span>

                <button type="button" class="btn-refresh" onclick="window.location.reload()">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>

        <div class="chat-messages" id="chatMessages">
            <?php if (empty($messages)): ?>
                <div class="chat-no-messages">
                    <i class="fas fa-comment-dots"></i>
                    <p>No messages yet. Be the first to say something!</p>
                </div>
            <?php else: ?>
                <?php
                $lastDay = null;

                foreach ($messages as $msg):
                    if ($msg['day'] !== $lastDay):
                        $lastDay = $msg['day'];

                        if ($msg['day'] === date('Y-m-d')) {
                            $divLabel = 'Today';
                        } elseif ($msg['day'] === date('Y-m-d', strtotime('-1 day'))) {
                            $divLabel = 'Yesterday';
                        } else {
                            $divLabel = date('d M Y', strtotime($msg['day']));
                        }
                ?>
                        <div class="msg-date-divider">
                            <span><?= h($divLabel) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="chat-bubble-wrap <?= $msg['outgoing'] ? 'outgoing' : '' ?>">
                        <div class="bubble-avatar">
                            <img src="<?= h($msg['dp']) ?>" alt="<?= h($msg['name']) ?>">
                        </div>

                        <div class="bubble-content">
                            <div class="bubble-sender">
                                <?php if (!$msg['outgoing']): ?>
                                    <span class="bubble-sender-name"><?= h($msg['name']) ?></span>
                                    <span class="contact-role-tag <?= h($msg['rclass']) ?>"><?= h($msg['role']) ?></span>
                                <?php else: ?>
                                    <span class="contact-role-tag <?= h($msg['rclass']) ?>"><?= h($msg['role']) ?></span>
                                    <span class="bubble-sender-name">You</span>
                                <?php endif; ?>
                            </div>

                            <div class="bubble <?= $msg['outgoing'] ? 'bubble-outgoing' : 'bubble-incoming' ?>">
                                <?= nl2br(h($msg['text'])) ?>
                            </div>

                            <span class="bubble-time"><?= h($msg['date']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="chat-compose">
            <textarea
                id="messageInput"
                placeholder="Type a message to the group..."
                rows="1"
                maxlength="1000"
            ></textarea>

            <input type="hidden" id="roomId" value="<?= $encodedRoomParam ?>">

            <button type="button" class="btn-send" id="sendBtn">
                <span class="spin" id="sendSpin"></span>
                <i class="fas fa-paper-plane" id="sendIcon"></i> Send
            </button>
        </div>

        <div class="compose-footer" id="charCounter">0 / 1000</div>

    </div>
</main>

<script>
const messageInput = document.getElementById('messageInput');
const counter = document.getElementById('charCounter');
const sendBtn = document.getElementById('sendBtn');
const sendSpin = document.getElementById('sendSpin');
const sendIcon = document.getElementById('sendIcon');
const chatMessages = document.getElementById('chatMessages');

function updateCounter() {
    const len = messageInput.value.length;
    counter.textContent = len + ' / 1000';
    counter.className = 'compose-footer' + (len >= 900 ? ' limit' : '');

    messageInput.style.height = 'auto';
    messageInput.style.height = Math.min(messageInput.scrollHeight, 120) + 'px';
}

async function sendMsg() {
    const msg = messageInput.value.trim();
    const room = document.getElementById('roomId').value;

    if (!msg || !room) {
        return;
    }

    sendBtn.disabled = true;
    sendSpin.classList.add('active');
    sendIcon.style.display = 'none';

    const formData = new FormData();
    formData.append('message', msg);
    formData.append('f', room);
    formData.append('unik', '<?= $encodedUserId ?>');

    try {
        const response = await fetch('scripts/send_forum_chat.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error('Message failed');
        }

        window.location.reload();
    } catch (error) {
        sendBtn.disabled = false;
        sendSpin.classList.remove('active');
        sendIcon.style.display = '';
        alert('Message could not be sent. Please try again.');
    }
}

messageInput.addEventListener('input', updateCounter);

messageInput.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMsg();
    }
});

sendBtn.addEventListener('click', sendMsg);

window.addEventListener('load', function () {
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
});
</script>

</body>
</html>