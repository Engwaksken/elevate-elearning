<?php
require 'scripts/connector.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function validate_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}
function validate_id($id) {
    return filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}

$flash_error   = null;
$flash_success = null;

/* -- DELETE ---------------------------------------------- */
if (isset($_GET['delete_id'])) {
    $del_id = validate_id($_GET['delete_id']);
    if ($del_id) {
        $s = $conn->prepare("SELECT course_image FROM course_free WHERE id=?");
        $s->bind_param("i", $del_id);
        $s->execute();
        $del_row = $s->get_result()->fetch_assoc();
        $s->close();
        if ($del_row) {
            if (!empty($del_row['course_image']) && file_exists($del_row['course_image']))
                unlink($del_row['course_image']);
            $s = $conn->prepare("DELETE FROM course_free WHERE id=?");
            $s->bind_param("i", $del_id);
            $ok = $s->execute();
            $s->close();
            $_SESSION['successmsg'] = $ok ? "Resource deleted successfully." : "Error deleting resource.";
        }
    }
    header("Location: ./?view=online_class");
    exit();
}

/* -- EDIT (POST) ----------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['edit_id'])) {
    $edit_id     = validate_id($_POST['edit_id']);
    $course_title       = validate_input($_POST['course_title']       ?? '');
    $course_image_url   = validate_input($_POST['course_image_url']   ?? '');
    $course_description = validate_input($_POST['course_description'] ?? '');
    $course_link        = validate_input($_POST['course_link']        ?? '');
    $errors = [];

    if (!$edit_id)          $errors[] = "Invalid resource ID.";
    if (!$course_title)     $errors[] = "Title is required.";
    if (!$course_description || mb_strlen($course_description) < 20)
                            $errors[] = "Description must be at least 20 characters.";

    // Normalise URLs
    foreach (['course_image_url', 'course_link'] as $var) {
        if (!empty($$var) && !preg_match('/^https?:\/\//', $$var))
            $$var = 'https://' . ltrim($$var, '/');
    }

    if (empty($errors)) {
        // Use new image URL if provided, else keep old
        $s = $conn->prepare("SELECT course_image FROM course_free WHERE id=?");
        $s->bind_param("i", $edit_id);
        $s->execute();
        $old = $s->get_result()->fetch_assoc();
        $s->close();

        $final_image = !empty($course_image_url) ? $course_image_url : ($old['course_image'] ?? '');

        $s = $conn->prepare("UPDATE course_free SET course_title=?, course_image=?, course_description=?, course_link=? WHERE id=?");
        $s->bind_param("ssssi", $course_title, $final_image, $course_description, $course_link, $edit_id);
        $ok = $s->execute();
        $s->close();
        $_SESSION['successmsg'] = $ok ? "Resource updated successfully." : "Error updating resource.";
    } else {
        $_SESSION['errormsg'] = implode('<br>', $errors);
    }
    header("Location: ./?view=online_class");
    exit();
}

/* -- ADD (POST) ------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['edit_id'])) {
    $course_title       = trim($_POST['course_name']        ?? '');
    $course_description = trim($_POST['course_description'] ?? '');
    $resource_type      = $_POST['resource_type']           ?? 'link';
    $created_at         = date('Y-m-d H:i:s');
    $course_image       = null;
    $course_link        = null;
    $errors             = [];

    if (!$course_title)                            $errors[] = "Course name is required.";
    if (mb_strlen($course_description) < 20)       $errors[] = "Description must be at least 20 characters.";

    // Image upload
    $image_dir = "online_class/images/";
    $file_dir  = "online_class/files/";
    if (!is_dir($image_dir)) mkdir($image_dir, 0755, true);
    if (!is_dir($file_dir))  mkdir($file_dir,  0755, true);

    if (!empty($_FILES['course_image']['name']) && $_FILES['course_image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['course_image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp']))
            $errors[] = "Invalid image format. Allowed: JPG, PNG, GIF, WEBP.";
        elseif ($_FILES['course_image']['size'] > 5000000)
            $errors[] = "Image must be under 5 MB.";
        else {
            $fname = uniqid('course_img_', true) . '.' . $ext;
            if (move_uploaded_file($_FILES['course_image']['tmp_name'], $image_dir . $fname)) {
                $course_image = $image_dir . $fname;
                require_once __DIR__ . '/scripts/upload_compressor.php';
                eh_compress_upload($course_image);
            }
            else
                $errors[] = "Failed to upload image.";
        }
    }

    // Link or file
    if ($resource_type === 'link') {
        $course_link = trim($_POST['course_link_input'] ?? '');
        if (empty($course_link))                    $errors[] = "Please provide a link URL.";
        elseif (!filter_var($course_link, FILTER_VALIDATE_URL)) $errors[] = "Please provide a valid URL.";
    } elseif ($resource_type === 'file') {
        if (!empty($_FILES['course_file']['name']) && $_FILES['course_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['course_file']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf','doc','docx','ppt','pptx','zip','rar']))
                $errors[] = "Invalid file format.";
            elseif ($_FILES['course_file']['size'] > 20000000)
                $errors[] = "File must be under 20 MB.";
            else {
                $fname = uniqid('course_file_', true) . '.' . $ext;
                if (move_uploaded_file($_FILES['course_file']['tmp_name'], $file_dir . $fname)) {
                    $course_link = $file_dir . $fname;
                    require_once __DIR__ . '/scripts/upload_compressor.php';
                    eh_compress_upload($course_link);
                }
                else
                    $errors[] = "Failed to upload file.";
            }
        } else {
            $errors[] = "Please upload a file.";
        }
    }

    if (empty($errors)) {
        $s = $conn->prepare("INSERT INTO course_free (course_title, course_image, course_description, course_link, created_at) VALUES (?,?,?,?,?)");
        $s->bind_param("sssss", $course_title, $course_image, $course_description, $course_link, $created_at);
        $ok = $s->execute();
        $s->close();
        $_SESSION[$ok ? 'successmsg' : 'errormsg'] = $ok
            ? "Resource added successfully."
            : "Database error. Please try again.";
    } else {
        $_SESSION['errormsg'] = implode('<br>', $errors);
    }
    header("Location: ./?view=online_class");
    exit();
}

/* -- FETCH flash messages -------------------------------- */
$flash_success = $_SESSION['successmsg'] ?? null; unset($_SESSION['successmsg']);
$flash_error   = $_SESSION['errormsg']   ?? null; unset($_SESSION['errormsg']);

/* -- FETCH course names for dropdown --------------------- */
$course_names = [];
$r = $conn->query("SELECT name FROM courses ORDER BY name ASC");
while ($row = $r->fetch_assoc()) $course_names[] = $row['name'];

/* -- PAGINATION ------------------------------------------ */
$per_page     = 12;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($current_page - 1) * $per_page;

$total_res    = (int)($conn->query("SELECT COUNT(*) AS c FROM course_free")->fetch_assoc()['c'] ?? 0);
$total_pages  = (int)ceil($total_res / $per_page) ?: 1;

$courses = [];
$s = $conn->prepare("SELECT * FROM course_free ORDER BY course_title ASC LIMIT ? OFFSET ?");
$s->bind_param("ii", $per_page, $offset);
$s->execute();
$res = $s->get_result();
while ($row = $res->fetch_assoc()) $courses[] = $row;
$s->close();

function pgUrl(int $p): string {
    $params = array_merge($_GET, ['page' => $p]);
    return '?' . http_build_query($params);
}

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo htmlspecialchars($systemname ?? 'LMS') ?> - Other Resources</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="app.css">
<style>
  /* -- Resource card thumb -------------------------------- */
  .res-thumb {
    width: 100%;
    height: 160px;
    object-fit: cover;
    display: block;
  }
  .res-thumb-placeholder {
    width: 100%;
    height: 160px;
    background: linear-gradient(135deg, var(--maroon-dim), var(--gold-dim));
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gold);
    font-size: 2.2rem;
    opacity: .5;
  }

  /* -- Resource type toggle ------------------------------- */
  .type-toggle {
    display: flex;
    gap: 8px;
    margin-bottom: 14px;
  }
  .type-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 9px 14px;
    border-radius: 7px;
    border: 1.5px solid var(--border);
    background: var(--surface2);
    color: var(--muted);
    font-family: var(--font-sans);
    font-size: .82rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .17s;
  }
  .type-btn.active {
    background: var(--maroon);
    border-color: var(--maroon);
    color: #fff;
    box-shadow: 0 2px 8px rgba(122,25,25,.25);
  }
  .type-btn i { font-size: .78rem; }

  /* -- Image URL preview ---------------------------------- */
  .url-img-preview {
    margin-top: 8px;
    display: none;
  }
  .url-img-preview.visible { display: block; }
  .url-img-preview img {
    max-width: 140px;
    max-height: 100px;
    border-radius: 7px;
    border: 1px solid var(--border);
    object-fit: cover;
  }

  /* -- Char counter --------------------------------------- */
  .char-counter {
    font-size: .7rem;
    color: var(--muted);
    text-align: right;
    margin-top: 3px;
  }
  .char-counter.warn  { color: var(--gold); }
  .char-counter.limit { color: var(--red);  }

  /* -- Pagination ----------------------------------------- */
  .pag-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 28px;
  }
  .pag-info { font-size:.76rem; color:var(--muted); display:flex; align-items:center; gap:5px; }
  .pag-info i { color:var(--maroon); font-size:.7rem; }
  .pag-buttons { display:flex; align-items:center; gap:6px; }
  .pg-num {
    display:inline-flex; align-items:center; justify-content:center;
    width:32px; height:32px; border-radius:6px;
    font-size:.78rem; font-weight:600; text-decoration:none;
    border:1.5px solid var(--border); color:var(--muted); background:var(--surface);
    transition:border-color .15s,color .15s,background .15s;
  }
  .pg-num:hover { border-color:var(--maroon); color:var(--maroon); }
  .pg-num.active { background:var(--maroon); border-color:var(--maroon); color:#fff; box-shadow:0 2px 6px rgba(122,25,25,.25); }
  .pg-ellipsis  { font-size:.78rem; color:var(--muted); padding:0 2px; user-select:none; }
  .pg-arrow {
    display:inline-flex; align-items:center; gap:5px;
    padding:6px 13px; border-radius:6px; font-size:.78rem; font-weight:600;
    text-decoration:none; border:1.5px solid var(--maroon);
    color:var(--maroon); background:transparent; transition:background .15s,color .15s;
  }
  .pg-arrow:hover { background:var(--maroon); color:#fff; }
  .pg-arrow.disabled { border-color:var(--border); color:var(--muted); pointer-events:none; }
  .pg-arrow i { font-size:.72rem; }

  /* Flash auto-dismiss */
  @keyframes toast-out {
    to { opacity:0; transform:translateY(6px); max-height:0; padding:0; margin:0; overflow:hidden; }
  }
  .flash-toast.dismissing { animation:toast-out .4s ease forwards; }

  /* File input styled */
  .file-input-wrap { position:relative; }
  .file-input-wrap input[type="file"] {
    opacity:0; position:absolute; inset:0; cursor:pointer; z-index:2;
  }
  .file-input-label {
    display:flex; align-items:center; gap:9px;
    padding:9px 14px; border-radius:7px;
    border:1.5px dashed var(--border);
    background:var(--surface2); color:var(--muted);
    font-size:.82rem; cursor:pointer; transition:border-color .18s,color .18s;
  }
  .file-input-label:hover { border-color:var(--maroon); color:var(--maroon); }
  .file-input-label i { font-size:.9rem; }
  .file-name-display {
    margin-top:5px; font-size:.73rem; color:var(--muted);
    display:flex; align-items:center; gap:5px; min-height:18px;
  }
  .file-name-display i { color:var(--gold); }
</style>
</head>
<body>

<!-- -- Topbar ----------------------------------------------- -->
<nav class="topbar">
  <div class="topbar-left">
    <h1><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($systemname ?? 'LMS') ?> &mdash; Other Resources</h1>
    <ol class="breadcrumb">
      <li><a href="./"><i class="fas fa-home"></i> Home</a></li>
      <li class="active">Other Resources</li>
    </ol>
  </div>
  <div class="topbar-right"><i class="fas fa-clock"></i> <?php echo date("h:i a") ?></div>
</nav>

<main class="page">

  <!-- -- Page heading --------------------------------------- -->
  <div class="page-heading">
    <div>
      <h2>Other <em>Resources</em></h2>
      <div class="heading-meta">
        <span class="meta-pill"><i class="fas fa-layer-group"></i> <?php echo $total_res ?> resource<?php echo $total_res !== 1 ? 's' : '' ?></span>
        <span class="meta-pill"><i class="fas fa-file-alt"></i> Free materials</span>
      </div>
    </div>
    <button class="btn-export" onclick="openAddModal()">
      <i class="fas fa-plus"></i> Add New Resource
    </button>
  </div>

  <!-- -- Flash alerts --------------------------------------- -->
  <?php if ($flash_error): ?>
  <div class="app-alert app-alert-danger flash-toast" id="flash-error">
    <i class="fas fa-times-circle"></i>
    <span><?php echo $flash_error ?></span>
    <button onclick="dismissFlash('flash-error')" style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;"><i class="fas fa-times"></i></button>
  </div>
  <?php endif; ?>
  <?php if ($flash_success): ?>
  <div class="app-alert app-alert-info flash-toast" id="flash-success">
    <i class="fas fa-check-circle"></i>
    <span><?php echo htmlspecialchars($flash_success) ?></span>
    <button onclick="dismissFlash('flash-success')" style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;"><i class="fas fa-times"></i></button>
  </div>
  <?php endif; ?>

  <!-- -- Toolbar -------------------------------------------- -->
  <div class="resources-toolbar">
    <div class="resources-count">
      <i class="fas fa-layer-group"></i>
      Showing <strong id="visibleCount"><?php echo count($courses) ?></strong> of <?php echo $total_res ?> resources
    </div>
    <div class="search-wrap">
      <i class="fas fa-search"></i>
      <input type="text" id="resourceSearch" placeholder="Search resources...">
    </div>
  </div>

  <!-- -- Resource grid -------------------------------------- -->
  <?php if (empty($courses)): ?>
    <div class="resources-empty">
      <i class="fas fa-folder-open"></i>
      <p>No resources yet. Click <strong>Add New Resource</strong> to get started.</p>
    </div>
  <?php else: ?>

  <div class="resources-grid" id="resourceGrid">
    <?php foreach ($courses as $idx => $row):
      $title   = htmlspecialchars($row['course_title']       ?? '');
      $desc    = htmlspecialchars($row['course_description'] ?? '');
      $img     = htmlspecialchars($row['course_image']       ?? '');
      $link    = htmlspecialchars($row['course_link']        ?? '#');
      $short   = mb_strlen($desc) > 100 ? mb_substr($desc, 0, 100) . '...' : $desc;
      $created = !empty($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : '';
      $json    = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
    ?>
    <div class="res-card" style="animation-delay:<?php echo $idx * 50 ?>ms"
         data-title="<?php echo strtolower($title) ?>"
         data-desc="<?php echo strtolower($desc) ?>">

      <!-- Image area -->
      <div class="res-card-img-wrap" style="cursor:default;">
        <?php if ($img): ?>
          <img src="<?php echo $img ?>" alt="<?php echo $title ?>" class="res-thumb" loading="lazy">
        <?php else: ?>
          <div class="res-thumb-placeholder"><i class="fas fa-graduation-cap"></i></div>
        <?php endif; ?>
        <span class="res-card-tag"><i class="fas fa-certificate"></i> Free</span>
      </div>

      <!-- Body -->
      <div class="res-card-body">
        <div class="res-card-title"><?php echo $title ?></div>
        <?php if ($short): ?>
          <div class="res-card-desc"><?php echo $short ?></div>
        <?php endif; ?>
        <?php if ($created): ?>
          <div style="font-size:.69rem; color:var(--muted); margin-top:4px; display:flex; align-items:center; gap:4px;">
            <i class="fas fa-calendar-alt" style="color:var(--gold); font-size:.63rem;"></i> <?php echo $created ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Footer -->
      <div class="res-card-footer">
        <?php if ($link && $link !== '#'): ?>
          <a href="<?php echo $link ?>" target="_blank" rel="noopener" class="res-card-link-hint">
            <i class="fas fa-external-link-alt"></i> Open
          </a>
        <?php else: ?>
          <span class="res-card-link-hint" style="color:var(--muted); opacity:.5;">No link</span>
        <?php endif; ?>
        <div style="display:flex; gap:5px;">
          <button class="ia-btn ia-view" title="Edit" onclick="openEditModal(<?php echo $json ?>)">
            <i class="fas fa-pen"></i>
          </button>
          <button class="ia-btn ia-del" title="Delete" onclick="showDeleteModal(<?php echo $json ?>)">
            <i class="fas fa-trash"></i>
          </button>
        </div>
      </div>

    </div>
    <?php endforeach; ?>
  </div>

  <!-- -- Pagination ----------------------------------------- -->
  <?php if ($total_pages > 1): ?>
  <div class="pag-bar">
    <div class="pag-info">
      <i class="fas fa-graduation-cap"></i>
      Showing <?php echo $offset + 1 ?>–<?php echo min($current_page * $per_page, $total_res) ?> of <?php echo $total_res ?> resources
    </div>
    <div class="pag-buttons">
      <a href="<?php echo $current_page > 1 ? pgUrl($current_page - 1) : '#' ?>"
         class="pg-arrow <?php echo $current_page <= 1 ? 'disabled' : '' ?>">
        <i class="fas fa-chevron-left"></i> Prev
      </a>
      <?php
      $range = 2; $prev_p = null;
      for ($p = 1; $p <= $total_pages; $p++):
        if ($p === 1 || $p === $total_pages || abs($p - $current_page) <= $range):
          if ($prev_p !== null && $p - $prev_p > 1): ?>
            <span class="pg-ellipsis">&hellip;</span>
      <?php  endif; ?>
          <a href="<?php echo pgUrl($p) ?>" class="pg-num <?php echo $p === $current_page ? 'active' : '' ?>"><?php echo $p ?></a>
      <?php
          $prev_p = $p;
        endif;
      endfor;
      ?>
      <a href="<?php echo $current_page < $total_pages ? pgUrl($current_page + 1) : '#' ?>"
         class="pg-arrow <?php echo $current_page >= $total_pages ? 'disabled' : '' ?>">
        Next <i class="fas fa-chevron-right"></i>
      </a>
    </div>
  </div>
  <?php endif; ?>

  <?php endif; // end non-empty check ?>

</main>


<!-- ------------------------------------------------------
     ADD RESOURCE MODAL
------------------------------------------------------- -->
<div class="modal-overlay" id="addModal">
  <div class="modal-box">
    <form method="post" action="" enctype="multipart/form-data" id="addForm">

      <div class="modal-head">
        <h3><i class="fas fa-plus-circle"></i> Add New Resource</h3>
        <button type="button" class="modal-close" onclick="closeAddModal()"><i class="fas fa-times"></i></button>
      </div>

      <div class="modal-body">

        <!-- Course name -->
        <div class="ctrl-field" style="margin-bottom:14px;">
          <label class="ctrl-label" for="add_course_name">
            <i class="fas fa-book"></i> Course Name <span style="color:var(--red)">*</span>
          </label>
          <select class="ctrl-select" id="add_course_name" name="course_name" required style="min-width:100%;">
            <option value="">- Select a course -</option>
            <?php foreach ($course_names as $cn): ?>
            <option value="<?php echo htmlspecialchars($cn) ?>"><?php echo htmlspecialchars($cn) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Image upload -->
        <div class="ctrl-field" style="margin-bottom:14px;">
          <label class="ctrl-label"><i class="fas fa-image"></i> Course Image <span style="color:var(--muted);font-weight:400;text-transform:none;">(optional)</span></label>
          <div class="file-input-wrap">
            <input type="file" id="add_course_image" name="course_image" accept="image/*"
                   onchange="updateFileName(this,'add_img_name')">
            <label class="file-input-label" for="add_course_image">
              <i class="fas fa-cloud-upload-alt"></i> Choose image (JPG, PNG, GIF, WEBP - max 5 MB)
            </label>
          </div>
          <div class="file-name-display" id="add_img_name"></div>
        </div>

        <!-- Description -->
        <div class="ctrl-field" style="margin-bottom:14px;">
          <label class="ctrl-label" for="add_description">
            <i class="fas fa-align-left"></i> Description <span style="color:var(--red)">*</span>
          </label>
          <textarea id="add_description" name="course_description" rows="4"
                    class="form-input" style="resize:vertical;width:100%;"
                    placeholder="Minimum 20 characters..." required
                    oninput="updateCharCount(this,'add_char_count',1000)"></textarea>
          <div class="char-counter" id="add_char_count">0 / 1000</div>
        </div>

        <!-- Resource type toggle -->
        <div class="ctrl-field" style="margin-bottom:14px;">
          <label class="ctrl-label"><i class="fas fa-link"></i> Resource Type <span style="color:var(--red)">*</span></label>
          <div class="type-toggle">
            <button type="button" class="type-btn active" id="add_btn_link" onclick="setAddType('link')">
              <i class="fas fa-link"></i> External Link
            </button>
            <button type="button" class="type-btn" id="add_btn_file" onclick="setAddType('file')">
              <i class="fas fa-file-upload"></i> Upload File
            </button>
          </div>
          <input type="hidden" id="add_resource_type" name="resource_type" value="link">

          <div id="add_link_wrap">
            <input type="url" id="add_course_link" name="course_link_input"
                   class="form-input" style="width:100%;" placeholder="https://www.example.com/">
            <div class="field-hint"><i class="fas fa-info-circle"></i> Paste a valid URL.</div>
          </div>

          <div id="add_file_wrap" style="display:none;">
            <div class="file-input-wrap">
              <input type="file" id="add_course_file" name="course_file"
                     accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.rar"
                     onchange="updateFileName(this,'add_file_name')">
              <label class="file-input-label" for="add_course_file">
                <i class="fas fa-cloud-upload-alt"></i> PDF, DOC, PPT, ZIP - max 20 MB
              </label>
            </div>
            <div class="file-name-display" id="add_file_name"></div>
          </div>
        </div>

      </div><!-- /.modal-body -->

      <div class="modal-foot">
        <button type="button" class="btn-modal-cancel" onclick="closeAddModal()">
          <i class="fas fa-times"></i> Cancel
        </button>
        <button type="submit" class="btn-export">
          <i class="fas fa-plus-circle"></i> Add Resource
        </button>
      </div>

    </form>
  </div>
</div>


<!-- ------------------------------------------------------
     EDIT RESOURCE MODAL
------------------------------------------------------- -->
<div class="modal-overlay" id="editModal">
  <div class="modal-box">
    <form method="post" action="" id="editForm">

      <div class="modal-head">
        <h3><i class="fas fa-pen"></i> Edit Resource</h3>
        <button type="button" class="modal-close" onclick="closeEditModal()"><i class="fas fa-times"></i></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="edit_id" id="edit_id">

        <!-- Title -->
        <div class="ctrl-field" style="margin-bottom:14px;">
          <label class="ctrl-label" for="edit_title">
            <i class="fas fa-tag"></i> Title <span style="color:var(--red)">*</span>
          </label>
          <input type="text" id="edit_title" name="course_title"
                 class="form-input" style="width:100%;" required maxlength="255">
        </div>

        <!-- Image URL -->
        <div class="ctrl-field" style="margin-bottom:14px;">
          <label class="ctrl-label" for="edit_image_url">
            <i class="fas fa-image"></i> Image URL <span style="color:var(--muted);font-weight:400;text-transform:none;">(leave blank to keep current)</span>
          </label>
          <input type="url" id="edit_image_url" name="course_image_url"
                 class="form-input" style="width:100%;" placeholder="https://example.com/image.jpg"
                 oninput="previewEditImage(this)">
          <div class="url-img-preview" id="edit_img_preview">
            <img src="" id="edit_img_preview_img" alt="Preview">
          </div>
          <div id="edit_current_img_wrap" class="url-img-preview" style="margin-top:6px;">
            <label style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);display:block;margin-bottom:4px;">Current image</label>
            <img src="" id="edit_current_img" alt="Current image">
          </div>
        </div>

        <!-- Description -->
        <div class="ctrl-field" style="margin-bottom:14px;">
          <label class="ctrl-label" for="edit_description">
            <i class="fas fa-align-left"></i> Description <span style="color:var(--red)">*</span>
          </label>
          <textarea id="edit_description" name="course_description" rows="4"
                    class="form-input" style="resize:vertical;width:100%;" required
                    oninput="updateCharCount(this,'edit_char_count',1000)"></textarea>
          <div class="char-counter" id="edit_char_count">0 / 1000</div>
        </div>

        <!-- Link -->
        <div class="ctrl-field">
          <label class="ctrl-label" for="edit_link">
            <i class="fas fa-link"></i> Resource Link
          </label>
          <input type="url" id="edit_link" name="course_link"
                 class="form-input" style="width:100%;" placeholder="https://example.com/course">
        </div>

      </div><!-- /.modal-body -->

      <div class="modal-foot">
        <button type="button" class="btn-modal-cancel" onclick="closeEditModal()">
          <i class="fas fa-times"></i> Cancel
        </button>
        <button type="submit" class="btn-export">
          <i class="fas fa-save"></i> Save Changes
        </button>
      </div>

    </form>
  </div>
</div>


<!-- ------------------------------------------------------
     DELETE CONFIRM MODAL
------------------------------------------------------- -->
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
          Are you sure you want to delete <strong id="del_title_display"></strong>?<br>
          This action cannot be undone.
        </div>
      </div>
      <p class="del-note"><i class="fas fa-info-circle"></i> Any uploaded image file will also be removed from the server.</p>
    </div>
    <div class="modal-foot">
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
/* -- Flash dismiss --------------------------------------- */
function dismissFlash(id) {
  var el = document.getElementById(id);
  if (!el) return;
  el.classList.add('dismissing');
  setTimeout(function(){ el.remove(); }, 420);
}
['flash-error','flash-success'].forEach(function(id){
  setTimeout(function(){ dismissFlash(id); }, 5000);
});

/* -- Modal helpers --------------------------------------- */
function openOverlay(id)  { document.getElementById(id).classList.add('is-open');    document.body.style.overflow='hidden'; }
function closeOverlay(id) { document.getElementById(id).classList.remove('is-open'); document.body.style.overflow=''; }

function openAddModal()    { resetAddForm(); openOverlay('addModal'); }
function closeAddModal()   { closeOverlay('addModal'); }
function closeEditModal()  { closeOverlay('editModal'); }
function closeDeleteModal(){ closeOverlay('deleteModal'); }

/* Backdrop clicks */
['addModal','editModal','deleteModal'].forEach(function(id){
  document.getElementById(id).addEventListener('click', function(e){
    if (e.target === this) closeOverlay(id);
  });
});
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape'){ closeOverlay('addModal'); closeOverlay('editModal'); closeOverlay('deleteModal'); }
});

/* -- ADD modal ------------------------------------------- */
function resetAddForm() {
  document.getElementById('addForm').reset();
  document.getElementById('add_img_name').innerHTML  = '';
  document.getElementById('add_file_name').innerHTML = '';
  setAddType('link');
}
function setAddType(type) {
  document.getElementById('add_resource_type').value = type;
  var isLink = (type === 'link');
  document.getElementById('add_btn_link').classList.toggle('active',  isLink);
  document.getElementById('add_btn_file').classList.toggle('active', !isLink);
  document.getElementById('add_link_wrap').style.display = isLink  ? '' : 'none';
  document.getElementById('add_file_wrap').style.display = isLink  ? 'none' : '';
  document.getElementById('add_course_link').required = isLink;
  document.getElementById('add_course_file').required = !isLink;
  if (isLink)  { document.getElementById('add_course_file').value = ''; document.getElementById('add_file_name').innerHTML=''; }
  else         { document.getElementById('add_course_link').value = ''; }
}
document.getElementById('addForm').addEventListener('submit', function(e) {
  var desc = document.getElementById('add_description').value.trim();
  if (desc.length < 20) { e.preventDefault(); alert('Description must be at least 20 characters.'); return; }
  var type = document.getElementById('add_resource_type').value;
  if (type === 'link' && !document.getElementById('add_course_link').value.trim()) {
    e.preventDefault(); alert('Please provide a link URL.'); return;
  }
  if (type === 'file' && !document.getElementById('add_course_file').files.length) {
    e.preventDefault(); alert('Please upload a file.'); return;
  }
});

/* -- EDIT modal ------------------------------------------ */
function openEditModal(row) {
  document.getElementById('edit_id').value          = row.id || '';
  document.getElementById('edit_title').value       = row.course_title       || '';
  document.getElementById('edit_description').value = row.course_description || '';
  document.getElementById('edit_link').value        = row.course_link        || '';
  document.getElementById('edit_image_url').value   = '';
  document.getElementById('edit_img_preview').classList.remove('visible');

  var curWrap = document.getElementById('edit_current_img_wrap');
  var curImg  = document.getElementById('edit_current_img');
  if (row.course_image) {
    curImg.src = row.course_image;
    curWrap.classList.add('visible');
  } else {
    curWrap.classList.remove('visible');
  }

  updateCharCount(document.getElementById('edit_description'), 'edit_char_count', 1000);
  openOverlay('editModal');
}
function previewEditImage(input) {
  var url  = input.value.trim();
  var wrap = document.getElementById('edit_img_preview');
  var img  = document.getElementById('edit_img_preview_img');
  if (url.startsWith('http')) {
    img.src = url;
    img.onload  = function(){ wrap.classList.add('visible'); };
    img.onerror = function(){ wrap.classList.remove('visible'); };
  } else {
    wrap.classList.remove('visible');
  }
}

/* -- DELETE modal ---------------------------------------- */
function showDeleteModal(row) {
  document.getElementById('del_title_display').textContent = '"' + (row.course_title || 'this resource') + '"';
  document.getElementById('confirmDeleteBtn').href = './?view=online_class&delete_id=' + row.id;
  openOverlay('deleteModal');
}

/* -- Shared helpers -------------------------------------- */
function updateFileName(input, displayId) {
  var el = document.getElementById(displayId);
  if (input.files && input.files[0]) {
    var f = input.files[0];
    var mb = (f.size / 1024 / 1024).toFixed(2);
    el.innerHTML = '<i class="fas fa-file"></i> ' + f.name + ' (' + mb + ' MB)';
  } else {
    el.innerHTML = '';
  }
}
function updateCharCount(el, displayId, max) {
  var len = el.value.length;
  var disp = document.getElementById(displayId);
  disp.textContent = len + ' / ' + max;
  disp.className = 'char-counter' + (len > max * 0.95 ? ' limit' : len > max * 0.9 ? ' warn' : '');
}

/* -- Live search ----------------------------------------- */
document.getElementById('resourceSearch')?.addEventListener('input', function() {
  var q   = this.value.toLowerCase().trim();
  var vis = 0;
  document.querySelectorAll('#resourceGrid .res-card').forEach(function(c){
    var match = !q || (c.dataset.title||'').includes(q) || (c.dataset.desc||'').includes(q);
    c.style.display = match ? '' : 'none';
    if (match) vis++;
  });
  var cnt = document.getElementById('visibleCount');
  if (cnt) cnt.textContent = vis;
});

/* -- Stagger animation ----------------------------------- */
document.querySelectorAll('.res-card').forEach(function(el){
  el.style.animationDelay = (el.style.animationDelay || '0ms');
});
</script>
</body>
</html>