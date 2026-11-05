<?php
require 'scripts/connector.php';
require 'scripts/EmailService.php';

$emailService = new EmailService();

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION[$systemname . "leido"])) {
    header("Location: login");
    exit();
}

$user_id  = $_SESSION[$systemname . "leido"];
$acc_type = $_SESSION[$systemname . "acc_type"] ?? '';
$is_admin = in_array($acc_type, ['xybrtw', 'xtfbry']);

// Check lecturer has courses
$stmt = $conn->prepare("SELECT * FROM course_registration_lecturers WHERE user_id = ? ORDER BY id DESC");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result      = $stmt->get_result();
$has_courses = ($result->num_rows > 0);
$stmt->close();

/* -- DELETE ------------------------------------------------ */
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    $stmt = $conn->prepare("SELECT post_image, added_by FROM annouments WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row && ($row['added_by'] == $user_id || $is_admin)) {
        if ($row['post_image'] && file_exists($row['post_image'])) unlink($row['post_image']);

        $stmt = $conn->prepare("DELETE FROM annouments WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        $ok = $stmt->execute();
        $stmt->close();
        $_SESSION[$ok ? 'successmsg' : 'errormsg'] = $ok
            ? "Announcement deleted successfully."
            : "Error deleting announcement.";
    } else {
        $_SESSION['errormsg'] = "You don't have permission to delete this announcement.";
    }

    header("Location: ./?view=my_announcements");
    exit();
}

/* -- ADD / EDIT -------------------------------------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $post_name       = $_POST['post_name']       ?? '';
    $description     = $_POST['description']     ?? '';
    $post_link       = $_POST['post_link']        ?? '';
    $announcement_id = $_POST['announcement_id'] ?? null;
    $target_file     = '';

    if (!empty($_FILES['post_image']['name']) && $_FILES['post_image']['error'] === 0) {
        $target_dir = "announcements/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        require_once __DIR__ . '/scripts/upload_compressor.php';
        // Images only, stored under a random name (previously any file type was accepted).
        if (!eh_upload_extension_allowed((string)$_FILES['post_image']['name'], ['jpg', 'jpeg', 'png', 'gif', 'webp'])
            || !@getimagesize($_FILES['post_image']['tmp_name'])) {
            $_SESSION['errormsg'] = "Only image files (JPG, PNG, GIF or WebP) can be uploaded.";
            header("Location: ./?view=my_announcements");
            exit();
        }
        $target_file = $target_dir . eh_random_upload_name((string)$_FILES['post_image']['name'], 'ann_');
        if (!move_uploaded_file($_FILES['post_image']['tmp_name'], $target_file)) {
            $_SESSION['errormsg'] = "Error uploading file.";
            header("Location: ./?view=my_announcements");
            exit();
        }
        eh_compress_upload($target_file);
    }

    if ($announcement_id) {
        // EDIT
        $stmt = $conn->prepare("SELECT added_by, post_image FROM annouments WHERE id = ?");
        $stmt->bind_param("i", $announcement_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing && ($existing['added_by'] == $user_id || $is_admin)) {
            if ($target_file && $existing['post_image'] && file_exists($existing['post_image']))
                unlink($existing['post_image']);

            if ($target_file) {
                $stmt = $conn->prepare("UPDATE annouments SET post_name=?, post_image=?, description=?, post_link=? WHERE id=?");
                $stmt->bind_param("ssssi", $post_name, $target_file, $description, $post_link, $announcement_id);
            } else {
                $stmt = $conn->prepare("UPDATE annouments SET post_name=?, description=?, post_link=? WHERE id=?");
                $stmt->bind_param("sssi", $post_name, $description, $post_link, $announcement_id);
            }
            $ok = $stmt->execute();
            $_SESSION[$ok ? 'successmsg' : 'errormsg'] = $ok
                ? "Announcement updated successfully."
                : "Error updating announcement.";
            $stmt->close();
        } else {
            $_SESSION['errormsg'] = "You don't have permission to edit this announcement.";
        }

    } else {
        // ADD
        $created_at = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("INSERT INTO annouments (post_name, post_image, description, post_link, added_by, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $post_name, $target_file, $description, $post_link, $user_id, $created_at);

        if ($stmt->execute()) {
            $_SESSION['successmsg'] = "New announcement added successfully.";
            $announcementId = $stmt->insert_id;
            $stmt->close();

            // Queue emails for students in current intake
            $currentDate = date('Y-m-d');
            $courseStmt  = $conn->prepare("SELECT course_id FROM course_registration_lecturers WHERE user_id=?");
            $courseStmt->bind_param("s", $user_id);
            $courseStmt->execute();
            $courseResult = $courseStmt->get_result();
            $courseIds = [];
            while ($c = $courseResult->fetch_assoc()) $courseIds[] = $c['course_id'];
            $courseStmt->close();

            if (!empty($courseIds)) {
                $placeholders = implode(',', array_fill(0, count($courseIds), '?'));
                $types        = str_repeat('i', count($courseIds));
                $sql = "SELECT DISTINCT u.unique_id, u.email, u.fname
                        FROM users u
                        INNER JOIN course_registration crs ON crs.user_id = u.unique_id
                        INNER JOIN intakes i ON i.id = crs.intake
                        WHERE crs.course_id IN ($placeholders)
                          AND ? BETWEEN i.date_launching AND i.date_ending";
                $stmtQueue = $conn->prepare($sql);
                $params    = array_merge($courseIds, [$currentDate]);
                $stmtQueue->bind_param($types . 's', ...$params);
                $stmtQueue->execute();
                $students    = $stmtQueue->get_result();
                $insertStmt  = $conn->prepare("INSERT INTO announcement_email_queue (announcement_id, email, fname) VALUES (?, ?, ?)");
                while ($student = $students->fetch_assoc()) {
                    $insertStmt->bind_param("iss", $announcementId, $student['email'], $student['fname']);
                    $insertStmt->execute();
                }
                $insertStmt->close();
                $stmtQueue->close();
            }
        } else {
            $_SESSION['errormsg'] = "Error adding announcement.";
            $stmt->close();
        }
    }

    header("Location: ./?view=my_announcements");
    exit();
}

/* -- FETCH DATA -------------------------------------------- */
$course_names_res = $conn->query("SELECT name FROM courses ORDER BY name ASC");
$post_names = [];
while ($row = $course_names_res->fetch_assoc()) $post_names[] = $row['name'];

if ($is_admin) {
    $announcements = $conn->query(
        "SELECT a.*, u.fname AS added_by_name
         FROM annouments a
         LEFT JOIN users u ON a.added_by = u.unique_id
         ORDER BY a.created_at DESC"
    );
} else {
    $stmt = $conn->prepare(
        "SELECT a.*, u.fname AS added_by_name
         FROM annouments a
         LEFT JOIN users u ON a.added_by = u.unique_id
         WHERE a.added_by = ?
         ORDER BY a.created_at DESC"
    );
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $announcements = $stmt->get_result();
}

$total_announcements = $announcements->num_rows;

/* Flash messages */
$flash_success = $_SESSION['successmsg'] ?? null; unset($_SESSION['successmsg']);
$flash_error   = $_SESSION['errormsg']   ?? null; unset($_SESSION['errormsg']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo htmlspecialchars($systemname) ?> - Manage Announcements</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="app.css">
<style>
  /* Thumbnail in table */
  .thumb-img {
    width: 44px;
    height: 44px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid var(--border);
    display: block;
  }
  .thumb-placeholder {
    width: 44px;
    height: 44px;
    border-radius: 6px;
    background: var(--surface2);
    border: 1px solid var(--border);
    display: grid;
    place-items: center;
    color: var(--muted);
    font-size: .75rem;
  }

  /* Image preview inside modal */
  .img-preview-wrap {
    margin-top: 10px;
    display: none;
  }
  .img-preview-wrap.visible { display: block; }
  .img-preview-wrap label {
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--muted);
    margin-bottom: 5px;
    display: block;
  }
  .img-preview-wrap img {
    max-width: 150px;
    max-height: 150px;
    border-radius: 8px;
    border: 1px solid var(--border);
    display: block;
  }

  /* Auto-dismiss flash */
  .flash-toast {
    animation: fadeUp .3s ease both;
  }
  @keyframes toast-out {
    to { opacity: 0; transform: translateY(8px); max-height: 0; padding: 0; margin: 0; }
  }
  .flash-toast.dismissing {
    animation: toast-out .4s ease forwards;
    overflow: hidden;
  }
</style>
</head>
<body>

<!-- -- Topbar ----------------------------------------------- -->
<nav class="topbar">
  <div class="topbar-left">
    <h1><i class="fas fa-bullhorn"></i> <?php echo htmlspecialchars($systemname) ?> &mdash; Announcements</h1>
    <ol class="breadcrumb">
      <li><a href="./"><i class="fas fa-home"></i> Home</a></li>
      <li class="active">Manage Announcements</li>
    </ol>
  </div>
  <div class="topbar-right"><i class="fas fa-clock"></i> <?php echo date("h:i a") ?></div>
</nav>

<main class="page page-wide">

  <!-- -- Page heading ------------------------------------- -->
  <div class="page-heading">
    <div>
      <h2><em>Manage</em> Announcements</h2>
      <div class="heading-meta">
        <?php if ($is_admin): ?>
          <span class="meta-pill"><i class="fas fa-shield-alt"></i> Admin view - all announcements</span>
        <?php else: ?>
          <span class="meta-pill"><i class="fas fa-chalkboard-teacher"></i> Your announcements only</span>
        <?php endif; ?>
        <span class="meta-pill"><i class="fas fa-bell"></i> <?php echo $total_announcements ?> total</span>
      </div>
    </div>
    <button class="btn-export" onclick="openModal()">
      <i class="fas fa-plus"></i> Add New Announcement
    </button>
  </div>

  <!-- -- Flash alerts ------------------------------------- -->
  <?php if (!$has_courses): ?>
  <div class="app-alert app-alert-danger flash-toast" id="flash-warn">
    <i class="fas fa-exclamation-triangle"></i>
    <span><strong>Warning:</strong> You are not attached to any course yet. Please contact the administrator.</span>
    <button onclick="dismissFlash('flash-warn')" style="margin-left:auto; background:none; border:none; cursor:pointer; color:inherit; font-size:.9rem;"><i class="fas fa-times"></i></button>
  </div>
  <?php endif; ?>

  <?php if ($flash_error): ?>
  <div class="app-alert app-alert-danger flash-toast" id="flash-error">
    <i class="fas fa-times-circle"></i>
    <span><?php echo htmlspecialchars($flash_error) ?></span>
    <button onclick="dismissFlash('flash-error')" style="margin-left:auto; background:none; border:none; cursor:pointer; color:inherit; font-size:.9rem;"><i class="fas fa-times"></i></button>
  </div>
  <?php endif; ?>

  <?php if ($flash_success): ?>
  <div class="app-alert app-alert-info flash-toast" id="flash-success">
    <i class="fas fa-check-circle"></i>
    <span><?php echo htmlspecialchars($flash_success) ?></span>
    <button onclick="dismissFlash('flash-success')" style="margin-left:auto; background:none; border:none; cursor:pointer; color:inherit; font-size:.9rem;"><i class="fas fa-times"></i></button>
  </div>
  <?php endif; ?>

  <!-- -- Stats strip -------------------------------------- -->
  <div class="stats" style="margin-bottom:20px;">
    <div class="stat-card">
      <div class="stat-icon total"><i class="fas fa-bullhorn"></i></div>
      <div>
        <div class="sv"><?php echo $total_announcements ?></div>
        <div class="sl">Total</div>
      </div>
    </div>
    <?php
    /* Quick counts */
    $with_img = $with_link = 0;
    $all_rows = [];
    while ($r = $announcements->fetch_assoc()) {
        $all_rows[] = $r;
        if (!empty($r['post_image']))  $with_img++;
        if (!empty($r['post_link']))   $with_link++;
    }
    ?>
    <div class="stat-card">
      <div class="stat-icon prog"><i class="fas fa-image"></i></div>
      <div>
        <div class="sv gold"><?php echo $with_img ?></div>
        <div class="sl">With Image</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon done"><i class="fas fa-link"></i></div>
      <div>
        <div class="sv green"><?php echo $with_link ?></div>
        <div class="sl">With Link</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon cancel"><i class="fas fa-image" style="opacity:.4"></i></div>
      <div>
        <div class="sv red"><?php echo $total_announcements - $with_img ?></div>
        <div class="sl">No Image</div>
      </div>
    </div>
  </div>

  <!-- -- Table card --------------------------------------- -->
  <div class="tbl-card">

    <div class="tbl-toolbar">
      <span class="tbl-title">
        <i class="fas fa-list tbl-title-icon"></i> Announcements List
      </span>
      <div class="toolbar-actions">
        <div class="search-wrap">
          <i class="fas fa-search"></i>
          <input type="text" id="annSearch" placeholder="Search announcements...">
        </div>
      </div>
    </div>

    <div class="tbl-scroll">
      <table class="app-table" id="annTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Title</th>
            <th>Image</th>
            <th>Description</th>
            <th class="th-gold">Link</th>
            <th>Posted By</th>
            <th>Created</th>
            <th class="th-gold">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($all_rows)): ?>
          <tr>
            <td colspan="8" style="padding:48px 24px; text-align:center;">
              <div>
                <i class="fas fa-bullhorn" style="font-size:2rem; color:var(--maroon); opacity:.18; display:block; margin-bottom:8px;"></i>
                <p style="color:var(--muted); font-size:.85rem;">No announcements found. Click <strong>Add New Announcement</strong> to get started.</p>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($all_rows as $i => $row):
            $can_edit = ($row['added_by'] == $user_id || $is_admin);
            $desc_preview = htmlspecialchars(mb_substr($row['description'] ?? '', 0, 60));
            $desc_preview .= strlen($row['description'] ?? '') > 60 ? '...' : '';
          ?>
          <tr data-search="<?php echo strtolower(htmlspecialchars($row['post_name'] . ' ' . ($row['added_by_name'] ?? ''))) ?>">
            <td class="cell-num"><?php echo $i + 1 ?></td>
            <td>
              <div class="cell-title"><?php echo htmlspecialchars($row['post_name']) ?></div>
            </td>
            <td>
              <?php if (!empty($row['post_image'])): ?>
                <img src="<?php echo htmlspecialchars($row['post_image']) ?>"
                     alt="<?php echo htmlspecialchars($row['post_name']) ?>"
                     class="thumb-img">
              <?php else: ?>
                <div class="thumb-placeholder"><i class="fas fa-image"></i></div>
              <?php endif; ?>
            </td>
            <td class="cell-sm" style="max-width:200px;"><?php echo $desc_preview ?></td>
            <td>
              <?php if (!empty($row['post_link'])): ?>
                <a href="<?php echo htmlspecialchars($row['post_link']) ?>"
                   target="_blank" rel="noopener"
                   class="s-badge s-submitted" style="text-decoration:none;">
                  <i class="fas fa-external-link-alt"></i> View
                </a>
              <?php else: ?>
                <span class="cell-na-i">N/A</span>
              <?php endif; ?>
            </td>
            <td class="cell-sm"><?php echo htmlspecialchars($row['added_by_name'] ?? 'Unknown') ?></td>
            <td class="chat-date"><?php echo date('M d, Y', strtotime($row['created_at'])) ?></td>
            <td>
              <?php if ($can_edit): ?>
              <div class="row-actions">
                <button class="ia-btn ia-view"
                        title="Edit"
                        onclick="editAnnouncement(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)">
                  <i class="fas fa-pen"></i>
                </button>
                <button class="ia-btn ia-del"
                        title="Delete"
                        onclick="showDeleteModal(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
              <?php else: ?>
                <span class="cell-na-i">No access</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="tbl-footer">
      <div class="legend">
        <div class="legend-item"><span class="legend-dot legend-dot-green"></span> Has link</div>
        <div class="legend-item"><span class="legend-dot legend-dot-gold"></span> Has image</div>
        <div class="legend-item"><span class="legend-dot legend-dot-muted"></span> No image</div>
      </div>
      <span><?php echo $total_announcements ?> announcement<?php echo $total_announcements !== 1 ? 's' : '' ?></span>
    </div>

  </div><!-- /.tbl-card -->

</main>


<!-- ----------------------------------------------------------
     ADD / EDIT MODAL
----------------------------------------------------------- -->
<div class="modal-overlay" id="annModal">
  <div class="modal-box">
    <form method="post" action="" enctype="multipart/form-data">

      <div class="modal-head">
        <h3><i class="fas fa-bullhorn"></i> <span id="modalTitle">Add New Announcement</span></h3>
        <button type="button" class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="announcement_id" name="announcement_id">

        <!-- Title select -->
        <div class="ctrl-field" style="margin-bottom:14px;">
          <label class="ctrl-label" for="post_name">
            <i class="fas fa-tag"></i> Announcement Title <span style="color:var(--red)">*</span>
          </label>
          <select class="ctrl-select" id="post_name" name="post_name" required style="min-width:100%;">
            <option value="">- Select a course title -</option>
            <?php foreach ($post_names as $pn): ?>
            <option value="<?php echo htmlspecialchars($pn) ?>"><?php echo htmlspecialchars($pn) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Image upload -->
        <div class="ctrl-field" style="margin-bottom:14px;">
          <label class="ctrl-label" for="post_image">
            <i class="fas fa-image"></i> Post Image
          </label>
          <input type="file" id="post_image" name="post_image" accept="image/*"
                 class="form-input" style="cursor:pointer;"
                 onchange="previewNewImage(this)">
          <div class="field-hint"><i class="fas fa-info-circle"></i> Leave empty to keep the current image when editing.</div>
          <div class="img-preview-wrap" id="currentImageWrap">
            <label>Current Image:</label>
            <img src="" id="currentImagePreview" alt="Current image">
          </div>
          <div class="img-preview-wrap" id="newImageWrap">
            <label>New Image Preview:</label>
            <img src="" id="newImagePreview" alt="New image preview">
          </div>
        </div>

        <!-- Description -->
        <div class="ctrl-field" style="margin-bottom:14px;">
          <label class="ctrl-label" for="description">
            <i class="fas fa-align-left"></i> Brief Description <span style="color:var(--red)">*</span>
          </label>
          <textarea id="description" name="description" rows="4"
                    class="form-input" style="resize:vertical; width:100%;"
                    placeholder="Enter announcement description" required></textarea>
        </div>

        <!-- Link -->
        <div class="ctrl-field">
          <label class="ctrl-label" for="post_link">
            <i class="fas fa-link"></i> Post Link <span style="color:var(--muted); font-weight:400; text-transform:none;">(optional)</span>
          </label>
          <input type="url" id="post_link" name="post_link" class="form-input"
                 placeholder="https://www.example.com/" style="width:100%;">
        </div>
      </div>

      <div class="modal-foot">
        <button type="button" class="btn-modal-cancel" onclick="closeModal()">
          <i class="fas fa-times"></i> Cancel
        </button>
        <button type="submit" class="btn-export">
          <i class="fas fa-save"></i> Save Announcement
        </button>
      </div>

    </form>
  </div>
</div>


<!-- ----------------------------------------------------------
     DELETE CONFIRM MODAL
----------------------------------------------------------- -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal-box modal-box-sm">
    <div class="modal-head">
      <h3><i class="fas fa-trash-alt"></i> Confirm Delete</h3>
      <button type="button" class="modal-close" onclick="closeDeleteModal()"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <div class="del-warn">
        <i class="fas fa-exclamation-triangle del-warn-icon"></i>
        <div class="del-warn-text">
          Are you sure you want to delete <strong id="delAnnouncementName"></strong>?
          <br><br>This action cannot be undone.
        </div>
      </div>
      <p class="del-note"><i class="fas fa-info-circle"></i> Any uploaded image associated with this announcement will also be removed.</p>
    </div>
    <div class="modal-foot">
      <div class="err-msg" id="delErrMsg"><i class="fas fa-times-circle"></i> <span></span></div>
      <button type="button" class="btn-modal-cancel" onclick="closeDeleteModal()">
        <i class="fas fa-times"></i> Cancel
      </button>
      <a href="#" id="confirmDeleteBtn" class="btn-modal-delete">
        <i class="fas fa-trash-alt"></i> Delete
      </a>
    </div>
  </div>
</div>


<script>
/* -- Flash dismiss ------------------------------------------- */
function dismissFlash(id) {
  var el = document.getElementById(id);
  if (!el) return;
  el.classList.add('dismissing');
  setTimeout(function() { el.remove(); }, 420);
}
/* Auto-dismiss after 5s */
['flash-warn','flash-error','flash-success'].forEach(function(id) {
  setTimeout(function() { dismissFlash(id); }, 5000);
});

/* -- Add / Edit modal ---------------------------------------- */
function openModal() {
  resetForm();
  document.getElementById('annModal').classList.add('is-open');
  document.body.style.overflow = 'hidden';
}
function closeModal() {
  document.getElementById('annModal').classList.remove('is-open');
  document.body.style.overflow = '';
}
function resetForm() {
  document.getElementById('modalTitle').textContent        = 'Add New Announcement';
  document.getElementById('announcement_id').value         = '';
  document.getElementById('post_name').value               = '';
  document.getElementById('description').value             = '';
  document.getElementById('post_link').value               = '';
  document.getElementById('post_image').value              = '';
  document.getElementById('currentImageWrap').classList.remove('visible');
  document.getElementById('newImageWrap').classList.remove('visible');
}
function editAnnouncement(ann) {
  document.getElementById('modalTitle').textContent        = 'Edit Announcement';
  document.getElementById('announcement_id').value         = ann.id;
  document.getElementById('post_name').value               = ann.post_name   || '';
  document.getElementById('description').value             = ann.description || '';
  document.getElementById('post_link').value               = ann.post_link   || '';
  document.getElementById('post_image').value              = '';
  document.getElementById('newImageWrap').classList.remove('visible');

  var wrap = document.getElementById('currentImageWrap');
  if (ann.post_image) {
    document.getElementById('currentImagePreview').src = ann.post_image;
    wrap.classList.add('visible');
  } else {
    wrap.classList.remove('visible');
  }

  document.getElementById('annModal').classList.add('is-open');
  document.body.style.overflow = 'hidden';
}
function previewNewImage(input) {
  var wrap = document.getElementById('newImageWrap');
  var img  = document.getElementById('newImagePreview');
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e) { img.src = e.target.result; wrap.classList.add('visible'); };
    reader.readAsDataURL(input.files[0]);
  } else {
    wrap.classList.remove('visible');
  }
}

/* Close on backdrop click / Escape */
document.getElementById('annModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

/* -- Delete modal -------------------------------------------- */
function showDeleteModal(ann) {
  document.getElementById('delAnnouncementName').textContent = '"' + ann.post_name + '"';
  document.getElementById('confirmDeleteBtn').href = '?view=my_announcements&delete_id=' + ann.id;
  document.getElementById('deleteModal').classList.add('is-open');
  document.body.style.overflow = 'hidden';
}
function closeDeleteModal() {
  document.getElementById('deleteModal').classList.remove('is-open');
  document.body.style.overflow = '';
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
  if (e.target === this) closeDeleteModal();
});

/* Global Escape handler */
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') { closeModal(); closeDeleteModal(); }
});

/* -- Live search --------------------------------------------- */
document.getElementById('annSearch')?.addEventListener('input', function() {
  var q = this.value.toLowerCase().trim();
  document.querySelectorAll('#annTable tbody tr[data-search]').forEach(function(r) {
    r.style.display = (!q || r.dataset.search.includes(q)) ? '' : 'none';
  });
});
</script>

<?php $conn->close(); ?>
</body>
</html>