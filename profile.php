<?php
$checksystemq = "SELECT * FROM users WHERE unique_id = '" . $conn->real_escape_string($unik) . "'";
$checkq       = $conn->query($checksystemq) or die(mysqli_error($conn));
$dataz        = mysqli_fetch_array($checkq);

$dpz      = $dataz['dp']           ?? 'default.jpg';
$f_namex  = htmlspecialchars($dataz['fname']       ?? '');
$l_namex  = htmlspecialchars($dataz['lname']       ?? '');
$o_namex  = htmlspecialchars($dataz['other_name']  ?? '');
$dob      = htmlspecialchars($dataz['dob']         ?? '');
$gender   = htmlspecialchars($dataz['gender']      ?? '');
$cont     = htmlspecialchars($dataz['country']     ?? '');
$contact  = htmlspecialchars($dataz['contact']     ?? '');
$datecre  = $dataz['date_created'] ?? '';
$datef    = $datecre ? date('l, d M Y', strtotime($datecre)) : '-';
$fullname = trim($f_namex . ' ' . $l_namex . ' ' . $o_namex);

$dpfz = ($dpz === 'default.jpg' || empty($dpz))
    ? 'images/default_p_pics/user.png'
    : 'images/user_p_pics/' . $dpz;
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
<!-- ── Profile layout ─────────────────────────────────────── -->
<div class="page" style="padding-top:0;">

  <div class="page-heading">
    <div>
      <h2><em>My</em> Profile</h2>
      <div class="heading-meta">
        <span class="meta-pill"><i class="fas fa-user"></i> <?php echo $fullname ?></span>
        <?php if (!empty($mytype)): ?>
        <span class="meta-pill"><i class="fas fa-id-badge"></i> <?php echo htmlspecialchars($mytype) ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div style="display:grid; grid-template-columns:300px 1fr; gap:20px; align-items:start;">

    <!-- ── Left: avatar card ────────────────────────────── -->
    <div class="tbl-card" style="overflow:visible;">

      <!-- Maroon header -->
      <div style="background:var(--maroon-dk); border-bottom:3px solid var(--gold); padding:28px 20px 24px; text-align:center; position:relative;">

        <!-- Avatar -->
        <div style="width:96px; height:96px; border-radius:50%;
                    border:3px solid var(--gold); overflow:hidden;
                    margin:0 auto 14px; background:var(--maroon-dim);
                    box-shadow:0 4px 16px rgba(0,0,0,.25);">
          <img src="<?php echo htmlspecialchars($dpfz) ?>"
               alt="<?php echo $fullname ?>"
               style="width:100%; height:100%; object-fit:cover; display:block;">
        </div>

        <!-- Name -->
        <div style="font-family:var(--font-serif); font-size:1.15rem;
                    color:#fff; font-weight:600; line-height:1.3;
                    letter-spacing:-.01em;">
          <?php echo $fullname ?>
        </div>

        <!-- Role badge -->
        <?php if (!empty($mytype)): ?>
        <div style="margin-top:8px;">
          <span class="s-badge s-class" style="background:rgba(255,255,255,.12);
                color:var(--gold-lt); border:1px solid rgba(255,255,255,.18);">
            <i class="fas fa-id-badge"></i> <?php echo htmlspecialchars($mytype) ?>
          </span>
        </div>
        <?php endif; ?>

        <?php if (!empty($studentnumber)): ?>
        <div style="margin-top:6px; font-size:.72rem; color:rgba(255,255,255,.5);">
          <?php echo htmlspecialchars($studentnumber) ?>
        </div>
        <?php endif; ?>

      </div>

      <!-- Quick stats -->
      <div style="padding:16px; display:flex; flex-direction:column; gap:10px;">

        <?php
        $profile_fields = [
          ['icon' => 'fa-phone',        'label' => 'Phone',   'value' => $contact],
          ['icon' => 'fa-venus-mars',   'label' => 'Gender',  'value' => $gender],
          ['icon' => 'fa-globe',        'label' => 'Country', 'value' => $cont],
          ['icon' => 'fa-birthday-cake','label' => 'DOB',     'value' => $dob],
          ['icon' => 'fa-calendar-alt', 'label' => 'Joined',  'value' => $datef],
        ];
        foreach ($profile_fields as $pf):
          if (empty($pf['value'])) continue;
        ?>
        <div style="display:flex; align-items:center; gap:10px;
                    padding:9px 12px; border-radius:var(--radius);
                    background:var(--surface2); border:1px solid var(--border);">
          <div style="width:30px; height:30px; border-radius:7px;
                      background:var(--maroon-dim); display:grid;
                      place-items:center; flex-shrink:0;
                      color:var(--maroon); font-size:.8rem;">
            <i class="fas <?php echo $pf['icon'] ?>"></i>
          </div>
          <div style="min-width:0; flex:1;">
            <div style="font-size:.65rem; font-weight:700; text-transform:uppercase;
                        letter-spacing:.08em; color:var(--muted); margin-bottom:1px;">
              <?php echo $pf['label'] ?>
            </div>
            <div style="font-size:.84rem; font-weight:500; color:var(--text);
                        white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
              <?php echo htmlspecialchars($pf['value']) ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>

        <!-- Change password button -->
        <button onclick="openPwModal()"
                class="btn-export" style="width:100%; justify-content:center; margin-top:4px;">
          <i class="fas fa-key"></i> Change Password
        </button>

      </div>
    </div><!-- /.left card -->


    <!-- ── Right: detail card ───────────────────────────── -->
    <div class="tbl-card">

      <div class="tbl-toolbar">
        <span class="tbl-title">
          <i class="fas fa-user-circle"></i> Account Details
        </span>
        <span class="count-pill">
          <i class="fas fa-shield-alt"></i> Active
        </span>
      </div>

      <div style="padding:20px;">

        <!-- Detail grid -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:20px;">
          <?php
          $detail_fields = [
            ['icon' => 'fa-user',         'label' => 'First Name',   'value' => $f_namex],
            ['icon' => 'fa-user',         'label' => 'Last Name',    'value' => $l_namex],
            ['icon' => 'fa-user-tag',     'label' => 'Other Name',   'value' => $o_namex],
            ['icon' => 'fa-venus-mars',   'label' => 'Gender',       'value' => $gender],
            ['icon' => 'fa-globe',        'label' => 'Country',      'value' => $cont],
            ['icon' => 'fa-phone',        'label' => 'Contact',      'value' => $contact],
            ['icon' => 'fa-birthday-cake','label' => 'Date of Birth','value' => $dob],
            ['icon' => 'fa-calendar-alt', 'label' => 'Member Since', 'value' => $datef],
          ];
          foreach ($detail_fields as $df):
          ?>
          <div style="background:var(--surface2); border:1px solid var(--border);
                      border-radius:var(--radius); padding:12px 14px;">
            <div style="font-size:.65rem; font-weight:700; text-transform:uppercase;
                        letter-spacing:.08em; color:var(--muted); margin-bottom:4px;
                        display:flex; align-items:center; gap:5px;">
              <i class="fas <?php echo $df['icon'] ?>" style="color:var(--gold); font-size:.62rem;"></i>
              <?php echo $df['label'] ?>
            </div>
            <div style="font-size:.9rem; font-weight:500; color:<?php echo empty($df['value']) ? 'var(--muted)' : 'var(--text)' ?>;
                        <?php echo empty($df['value']) ? 'font-style:italic;' : '' ?>">
              <?php echo !empty($df['value']) ? htmlspecialchars($df['value']) : 'Not set' ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Info badge -->
        <div class="info-badge">
          <i class="fas fa-info-circle"></i>
          To update your profile information, please contact your system administrator.
        </div>

      </div>
    </div><!-- /.right card -->

  </div><!-- /grid -->
</div><!-- /.page -->


<!-- ══════════════════════════════════════════════════════
     CHANGE PASSWORD MODAL
═══════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="pwModal">
  <div class="modal-box modal-box-sm">

    <div class="modal-head">
      <h3><i class="fas fa-key"></i> Change Password</h3>
      <button type="button" class="modal-close" onclick="closePwModal()">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <form action="scripts/changepassword.php" method="post">
      <div class="modal-body" style="display:flex; flex-direction:column; gap:14px;">

        <!-- Old password -->
        <div class="ctrl-field">
          <label class="ctrl-label" for="oldpassword">
            <i class="fas fa-lock"></i> Current Password <span style="color:var(--red)">*</span>
          </label>
          <div class="pass-wrap">
            <input type="password" id="oldpassword" name="oldpassword"
                   class="form-input pass-input" placeholder="Enter current password" required>
            <button type="button" class="pass-toggle" onclick="togglePw('oldpassword',this)" tabindex="-1">
              <i class="fas fa-eye"></i>
            </button>
          </div>
        </div>

        <!-- New passwords side by side -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="ctrl-field">
            <label class="ctrl-label" for="txtNewPassword">
              <i class="fas fa-key"></i> New Password <span style="color:var(--red)">*</span>
            </label>
            <div class="pass-wrap">
              <input type="password" id="txtNewPassword" name="password1"
                     class="form-input pass-input" placeholder="New password" required
                     oninput="checkPassStrength(); checkPasswordMatch();">
              <button type="button" class="pass-toggle" onclick="togglePw('txtNewPassword',this)" tabindex="-1">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
          <div class="ctrl-field">
            <label class="ctrl-label" for="txtConfirmPassword">
              <i class="fas fa-key"></i> Confirm <span style="color:var(--red)">*</span>
            </label>
            <div class="pass-wrap">
              <input type="password" id="txtConfirmPassword" name="password2"
                     class="form-input pass-input" placeholder="Retype password" required
                     oninput="checkPasswordMatch();">
              <button type="button" class="pass-toggle" onclick="togglePw('txtConfirmPassword',this)" tabindex="-1">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Strength indicator -->
        <div id="divStrength" style="min-height:22px;"></div>

        <!-- Match indicator -->
        <div id="divCheckPasswordMatch" style="min-height:22px; margin-top:-8px;"></div>

      </div><!-- /.modal-body -->

      <div class="modal-foot">
        <button type="button" class="btn-modal-cancel" onclick="closePwModal()">
          <i class="fas fa-times"></i> Cancel
        </button>
        <button type="submit" id="pwSubmitBtn" class="btn-export" disabled>
          <i class="fas fa-save"></i> Change Password
        </button>
      </div>
    </form>

  </div>
</div>


<script>
/* ── Modal ────────────────────────────────────────────────── */
function openPwModal() {
  document.getElementById('pwModal').classList.add('is-open');
  document.body.style.overflow = 'hidden';
}
function closePwModal() {
  document.getElementById('pwModal').classList.remove('is-open');
  document.body.style.overflow = '';
  document.getElementById('divStrength').innerHTML            = '';
  document.getElementById('divCheckPasswordMatch').innerHTML  = '';
  document.getElementById('pwSubmitBtn').disabled             = true;
  document.querySelectorAll('#pwModal input[type="password"]').forEach(function(el) { el.value = ''; });
}
document.getElementById('pwModal').addEventListener('click', function(e) {
  if (e.target === this) closePwModal();
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closePwModal();
});

/* ── Show / hide password ─────────────────────────────────── */
function togglePw(inputId, btn) {
  var input = document.getElementById(inputId);
  var icon  = btn.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'fas fa-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'fas fa-eye';
  }
}

/* ── Password match ───────────────────────────────────────── */
function checkPasswordMatch() {
  var pw1 = document.getElementById('txtNewPassword').value;
  var pw2 = document.getElementById('txtConfirmPassword').value;
  var el  = document.getElementById('divCheckPasswordMatch');
  var btn = document.getElementById('pwSubmitBtn');

  if (!pw2) { el.innerHTML = ''; btn.disabled = true; return; }

  if (pw1 !== pw2) {
    el.innerHTML = '<span class="s-badge s-pending"><i class="fas fa-times-circle"></i> Passwords do not match</span>';
    btn.disabled = true;
  } else {
    el.innerHTML = '<span class="s-badge s-graded"><i class="fas fa-check-circle"></i> Passwords match</span>';
    btn.disabled = false;
  }
}

/* ── Password strength ────────────────────────────────────── */
function checkPassStrength() {
  var password = document.getElementById('txtNewPassword').value;
  var score    = scorePassword(password);
  var el       = document.getElementById('divStrength');

  if (!password) { el.innerHTML = ''; return; }

  var label, cls;
  if      (score > 80) { label = 'Super Strong'; cls = 's-graded';    }
  else if (score > 60) { label = 'Strong';        cls = 's-graded';    }
  else if (score > 30) { label = 's-submitted';   cls = 's-submitted'; label = 'Good'; }
  else                 { label = 'Weak';           cls = 's-pending';   }

  /* fix the label/cls swap above cleanly */
  if (score > 80)       { label = 'Super Strong'; cls = 's-graded'; }
  else if (score > 60)  { label = 'Strong';       cls = 's-graded'; }
  else if (score > 30)  { label = 'Good';         cls = 's-submitted'; }
  else                  { label = 'Weak';          cls = 's-pending'; }

  el.innerHTML = '<span class="s-badge ' + cls + '"><i class="fas fa-shield-alt"></i> Strength: ' + label + '</span>';
}

function scorePassword(pass) {
  var score = 0;
  if (!pass) return score;
  var letters = {};
  for (var i = 0; i < pass.length; i++) {
    letters[pass[i]] = (letters[pass[i]] || 0) + 1;
    score += 5.0 / letters[pass[i]];
  }
  var variations = {
    digits:   /\d/.test(pass),
    lower:    /[a-z]/.test(pass),
    upper:    /[A-Z]/.test(pass),
    nonWords: /\W/.test(pass)
  };
  var variationCount = 0;
  for (var check in variations) {
    variationCount += variations[check] ? 1 : 0;
  }
  score += (variationCount - 1) * 10;
  return parseInt(score);
}

/* ── Responsive: collapse detail grid on small screens ───── */
(function(){
  function checkWidth() {
    var grid = document.querySelector('.profile-detail-grid');
    if (!grid) return;
    grid.style.gridTemplateColumns = window.innerWidth < 600 ? '1fr' : '1fr 1fr';
  }
  window.addEventListener('resize', checkWidth);
  checkWidth();
})();
</script>