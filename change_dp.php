<?php
//session_start();
require_once 'scripts/connector.php';

$systemname = $systemname ?? "Elevate Her 360";

$user_unique = $_SESSION[$systemname . "leido"] ?? '';
if ($user_unique === '') {
    echo "<div class='app-alert app-alert-danger'>Session expired. Please login again.</div>";
    return;
}

$stmt = $conn->prepare("SELECT dp FROM users WHERE unique_id = ? LIMIT 1");
$stmt->bind_param("s", $user_unique);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$currentDp = !empty($user['dp'])
    ? "images/user_p_pics/" . $user['dp']
    : "images/user_p_pics/default.png";
?>

<link rel="stylesheet" href="css/plugins/cropper/cropper.min.css">
<link rel="stylesheet" href="app.css">

<nav class="topbar">
  <div class="topbar-left">
    <h1><i class="fas fa-camera"></i> <?= htmlspecialchars($systemname) ?> - Profile Picture</h1>
    <ol class="breadcrumb">
      <li><a href="?"><i class="fas fa-home"></i> Home</a></li>
      <li><a href="?view=profile">Edit Profile</a></li>
      <li class="active">Change Profile Pic</li>
    </ol>
  </div>
</nav>

<main class="page">
  <div class="page-heading">
    <div>
      <h2><em>Change</em> Profile Picture</h2>
      <div class="heading-meta">
        <span class="meta-pill"><i class="fas fa-crop-alt"></i> Crop & Upload</span>
        <span class="meta-pill"><i class="fas fa-image"></i> JPG, PNG, WEBP</span>
      </div>
    </div>
  </div>

  <div class="tbl-card">
    <div class="tbl-toolbar">
      <span class="tbl-title">
        <i class="fas fa-crop-alt"></i> Crop Your Photo
      </span>

      <label for="inputImage" class="btn-export" style="cursor:pointer;margin:0;">
        <i class="fas fa-folder-open"></i> Pick New Pic
        <input type="file" id="inputImage" accept="image/jpeg,image/png,image/webp"
               style="position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;">
      </label>
    </div>

    <div style="padding:24px;">
      <div style="display:grid;grid-template-columns:1fr 280px;gap:24px;align-items:start;">

        <div>
          <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:10px;">
            <i class="fas fa-expand-arrows-alt" style="color:var(--gold);"></i> Crop Area
          </div>

          <div class="image-crop" style="background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;max-height:420px;">
            <img id="cropTarget" src="<?= htmlspecialchars($currentDp) ?>" alt="Profile image" style="max-width:100%;display:block;">
          </div>

          <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:14px;">
            <button class="ia-btn ia-view" id="zoomIn" type="button"><i class="fas fa-search-plus"></i></button>
            <button class="ia-btn ia-view" id="zoomOut" type="button"><i class="fas fa-search-minus"></i> </button>
            <button class="ia-btn ia-dl" id="rotateLeft" type="button"><i class="fas fa-undo"></i> </button>
            <button class="ia-btn ia-dl" id="rotateRight" type="button"><i class="fas fa-redo"></i> </button>
            <button class="ia-btn" id="resetCrop" type="button"><i class="fas fa-refresh"></i> Reset</button>
          </div>
        </div>

        <div>
          <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:10px;">
            <i class="fas fa-eye" style="color:var(--gold);"></i> Preview
          </div>

          <div style="display:flex;flex-direction:column;gap:14px;align-items:center;">
            <div style="text-align:center;">
              <div class="img-preview" style="width:130px;height:130px;border-radius:50%;overflow:hidden;border:3px solid var(--gold);background:var(--surface2);"></div>
              <div style="font-size:.7rem;color:var(--muted);margin-top:6px;">Profile preview</div>
            </div>

            <div style="text-align:center;">
              <div class="img-preview" style="width:80px;height:80px;border-radius:50%;overflow:hidden;border:2px solid var(--border);background:var(--surface2);"></div>
              <div style="font-size:.7rem;color:var(--muted);margin-top:6px;">Small preview</div>
            </div>
          </div>

          <button id="uploadBtn" class="btn-export" type="button" style="width:100%;justify-content:center;margin-top:20px;">
            <i class="fas fa-cloud-upload-alt"></i> Upload Profile Pic
            <span class="spin" id="uploadSpin"></span>
          </button>

          <div id="uploadResult" style="margin-top:10px;min-height:20px;"></div>
        </div>

      </div>
    </div>
  </div>
</main>

<script src="css/plugins/cropper/cropper.min.js"></script>

<script>
(function () {
  let cropper = null;
  let hasImageReady = false;
  let imageLoadToken = 0;

  const image = document.getElementById('cropTarget');
  const inputImage = document.getElementById('inputImage');
  const uploadBtn = document.getElementById('uploadBtn');
  const uploadSpin = document.getElementById('uploadSpin');
  const uploadResult = document.getElementById('uploadResult');

  function showResult(type, message) {
    const cls = type === 'success' ? 'app-alert-info' : 'app-alert-danger';
    uploadResult.innerHTML =
      '<div class="app-alert ' + cls + '" style="margin:0;">' + escapeHtml(message) + '</div>';
  }

  function escapeHtml(text) {
    return String(text).replace(/[&<>"']/g, function (m) {
      return ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      })[m];
    });
  }

  function setLoading(status) {
    uploadBtn.disabled = status;
    uploadSpin.classList.toggle('active', status);
  }

  function destroyCropper() {
    if (cropper) {
      cropper.destroy();
      cropper = null;
    }
    hasImageReady = false;
    uploadBtn.disabled = true;
  }

  function createCropper(token) {
    if (token !== imageLoadToken || !image.complete || image.naturalWidth <= 0 || cropper) return;

    let instance = null;
    instance = new Cropper(image, {
      aspectRatio: 1,
      viewMode: 1,
      dragMode: 'move',
      autoCropArea: 0.9,
      responsive: true,
      background: false,
      preview: '.img-preview',
      movable: true,
      zoomable: true,
      rotatable: true,
      scalable: true,
      cropBoxMovable: true,
      cropBoxResizable: true,
      checkCrossOrigin: false,
      ready: function () {
        if (token === imageLoadToken && cropper === instance) {
          hasImageReady = true;
          uploadBtn.disabled = false;
        }
      }
    });
    cropper = instance;
  }

  function loadImage(src) {
    const token = ++imageLoadToken;
    destroyCropper();
    let started = false;

    const startCropper = function () {
      if (started || token !== imageLoadToken || !image.complete || image.naturalWidth <= 0) return;
      started = true;
      createCropper(token);
    };

    image.onload = function () {
      startCropper();
    };

    image.onerror = function () {
      if (token !== imageLoadToken) return;
      destroyCropper();
      showResult('danger', 'Image failed to load. Please choose another image.');
    };

    image.src = src;

    if (image.complete && image.naturalWidth > 0) {
      startCropper();
    }
  }

  loadImage(image.getAttribute('src'));

  inputImage.addEventListener('change', function () {
    const file = inputImage.files && inputImage.files[0];

    if (!file) {
      return;
    }

    const allowed = ['image/jpeg', 'image/png', 'image/webp'];

    if (!allowed.includes(file.type)) {
      inputImage.value = '';
      showResult('danger', 'Please select a JPG, PNG, or WEBP image.');
      return;
    }

    const reader = new FileReader();

    reader.onload = function (event) {
      uploadResult.innerHTML = '';
      loadImage(event.target.result);
    };

    reader.onerror = function () {
      showResult('danger', 'Failed to read image file.');
    };

    reader.readAsDataURL(file);
  });

  document.getElementById('zoomIn').addEventListener('click', function () {
    if (cropper && hasImageReady) cropper.zoom(0.1);
  });

  document.getElementById('zoomOut').addEventListener('click', function () {
    if (cropper && hasImageReady) cropper.zoom(-0.1);
  });

  document.getElementById('rotateLeft').addEventListener('click', function () {
    if (cropper && hasImageReady) cropper.rotate(-45);
  });

  document.getElementById('rotateRight').addEventListener('click', function () {
    if (cropper && hasImageReady) cropper.rotate(45);
  });

  document.getElementById('resetCrop').addEventListener('click', function () {
    if (cropper && hasImageReady) cropper.reset();
  });

  uploadBtn.addEventListener('click', function () {
    if (!cropper || !hasImageReady) {
      showResult('danger', 'Please wait for the image to load, then crop it.');
      return;
    }

    let canvas = null;

    try {
      canvas = cropper.getCroppedCanvas({
        width: 500,
        height: 500,
        fillColor: '#ffffff',
        imageSmoothingEnabled: true,
        imageSmoothingQuality: 'high'
      });
    } catch (e) {
      canvas = null;
    }

    if (!canvas) {
      showResult('danger', 'Could not crop image. Please choose another image.');
      return;
    }

    const imageData = canvas.toDataURL('image/png');

    setLoading(true);

    fetch('scripts/change_d_pic.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
      },
      body: 'image=' + encodeURIComponent(imageData)
    })
    .then(response => response.text())
    .then(data => {
      setLoading(false);
      data = data.trim();

      if (data === 'done') {
        showResult('success', 'Profile picture updated successfully.');
        setTimeout(function () {
          window.location.href = '?view=profile';
        }, 1200);
      } else {
        showResult('danger', data || 'Upload failed.');
      }
    })
    .catch(function () {
      setLoading(false);
      showResult('danger', 'Network error. Please try again.');
    });
  });
})();
</script>
