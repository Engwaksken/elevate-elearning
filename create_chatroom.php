<?php
if (session_status() === PHP_SESSION_NONE) session_start();

/* -- Flash messages -------------------------------------- */
$errorMsg   = null;
$successMsg = null;

if (isset($_SESSION[$systemname . "errormsg"])) {
    $errorMsg = htmlspecialchars($_SESSION[$systemname . "errormsg"]);
    unset($_SESSION[$systemname . "errormsg"]);
}
if (isset($_SESSION[$systemname . "successmsg"])) {
    $successMsg = htmlspecialchars($_SESSION[$systemname . "successmsg"]);
    unset($_SESSION[$systemname . "successmsg"]);
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
    <h1><i class="fas fa-plus-circle"></i> <?php echo htmlspecialchars($systemname) ?> &mdash; Create Chatroom</h1>
    <ol class="breadcrumb">
      <li><a href="?"><i class="fas fa-home"></i> Home</a></li>
      <li><a href="?view=chatroom_ad">Chatrooms</a></li>
      <li class="active">Create Chatroom</li>
    </ol>
  </div>
  <div class="topbar-right"><i class="fas fa-clock"></i> <?php echo date("h:i a") ?></div>
</nav>

<main class="page">

  <!-- -- Flash messages ------------------------------------- -->
  <?php if ($errorMsg): ?>
    <div class="app-alert app-alert-danger">
      <i class="fas fa-exclamation-circle"></i> <?php echo $errorMsg ?>
    </div>
  <?php elseif ($successMsg): ?>
    <div class="app-alert app-alert-success">
      <i class="fas fa-check-circle"></i> <?php echo $successMsg ?>
    </div>
  <?php endif; ?>

  <!-- -- Page heading --------------------------------------- -->
  <div class="page-heading">
    <div>
      <h2>Create a <em>Chatroom</em></h2>
      <div class="heading-meta">
        <span class="meta-pill"><i class="fas fa-comments"></i> New group room</span>
      </div>
    </div>
    <a href="?view=chatroom_ad" class="btn-modal-cancel">
      <i class="fas fa-arrow-left"></i> Back to Chatrooms
    </a>
  </div>

  <!-- -- Form card ------------------------------------------ -->
  <div class="create-form-card">
    <div class="create-form-head">
      <h3><i class="fas fa-plus-circle"></i> New Chatroom Details</h3>
    </div>

    <div class="create-form-body">
      <form method="post" action="scripts/create_chatroom.php" id="createForm" onsubmit="return docheck()">

        <div class="create-form-grid">

          <div class="form-field form-field-full">
            <label for="name">
              <i class="fas fa-comments"></i> Chatroom Name <span class="req">*</span>
            </label>
            <input type="text" id="name" name="name" class="form-input"
                   placeholder="e.g. Web Development - Intake 2024" required>
            <span class="field-hint"><i class="fas fa-info-circle"></i> Choose a descriptive name students will recognise.</span>
          </div>

          <div class="form-field">
            <label for="code">
              <i class="fas fa-key"></i> Join Code <span class="req">*</span>
            </label>
            <input type="text" id="code" name="code" class="form-input"
                   placeholder="e.g. WD2024" required>
            <span class="field-hint"><i class="fas fa-info-circle"></i> Students use this to find the room.</span>
          </div>

          <div class="form-field">
            <label for="pass">
              <i class="fas fa-lock"></i> Join Password <span class="req">*</span>
            </label>
            <div class="pass-wrap">
              <input type="password" id="pass" name="pass" class="form-input pass-input"
                     placeholder="Enter join password" required>
              <button type="button" class="pass-toggle" onclick="togglePass()">
                <i class="fas fa-eye" id="passIcon"></i>
              </button>
            </div>
            <span class="field-hint"><i class="fas fa-info-circle"></i> Shared with students to join.</span>
          </div>

        </div><!-- /.create-form-grid -->

        <!-- Validation message -->
        <div class="form-val-msg" id="valMsg"></div>

      </form>
    </div>

    <div class="create-form-foot">
      <a href="?view=chatroom_ad" class="btn-modal-cancel">
        <i class="fas fa-times"></i> Cancel
      </a>
      <button type="submit" form="createForm" name="create" class="btn-create-room">
        <i class="fas fa-plus-circle"></i> Create Chatroom
      </button>
    </div>
  </div>

  <!-- -- Tips card ------------------------------------------ -->
  <div class="tips-card">
    <div class="tips-card-head">
      <i class="fas fa-lightbulb"></i> Tips for a great chatroom
    </div>
    <ul class="tips-list">
      <li><i class="fas fa-check-circle"></i> Use a clear name that includes the course and intake year.</li>
      <li><i class="fas fa-check-circle"></i> Keep the join code short and memorable (e.g. <strong>CS2024</strong>).</li>
      <li><i class="fas fa-check-circle"></i> Share the code and password only with enrolled students.</li>
      <li><i class="fas fa-check-circle"></i> You can manage all rooms from the <a href="?view=chatroom_ad">Chatrooms page</a>.</li>
    </ul>
  </div>

</main>

<script>
/* -- Password toggle -------------------------------------- */
function togglePass() {
  var inp  = document.getElementById('pass');
  var icon = document.getElementById('passIcon');
  if (inp.type === 'password') {
    inp.type  = 'text';
    icon.className = 'fas fa-eye-slash';
  } else {
    inp.type  = 'password';
    icon.className = 'fas fa-eye';
  }
}

/* -- Form validation -------------------------------------- */
function docheck() {
  var name = document.getElementById('name').value.trim();
  var code = document.getElementById('code').value.trim();
  var pass = document.getElementById('pass').value.trim();
  var msg  = document.getElementById('valMsg');

  msg.className = 'form-val-msg';
  msg.textContent = '';

  if (!name || !code || !pass) {
    msg.className = 'form-val-msg form-val-error';
    msg.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please fill in all required fields.';
    return false;
  }
  if (code.length < 3) {
    msg.className = 'form-val-msg form-val-error';
    msg.innerHTML = '<i class="fas fa-exclamation-circle"></i> Join code must be at least 3 characters.';
    return false;
  }
  if (pass.length < 4) {
    msg.className = 'form-val-msg form-val-error';
    msg.innerHTML = '<i class="fas fa-exclamation-circle"></i> Password must be at least 4 characters.';
    return false;
  }
  return true;
}
</script>
</body>
</html>