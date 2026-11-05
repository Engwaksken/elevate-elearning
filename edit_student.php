<?php
require 'scripts/connector.php';

/* -- Helpers ----------------------------------------------- */
function getStudentDetails($conn, $id) {
    $s = $conn->prepare("SELECT u.*, ct.name AS class_type_name FROM users u LEFT JOIN class_type ct ON ct.id=u.class_type_id WHERE u.unique_id=?");
    $s->bind_param("s", $id); $s->execute();
    return $s->get_result()->fetch_assoc();
}
function getStudentCourse($conn, $id) {
    $s = $conn->prepare("SELECT cr.id AS reg_id, cr.course_id, cr.intake, c.name AS course_name, i.name AS intake_name, i.date_launching AS intake_start_date, b.name AS branch_name FROM course_registration cr JOIN courses c ON c.id=cr.course_id LEFT JOIN intakes i ON i.id=cr.intake LEFT JOIN branches b ON b.id=i.branch_id WHERE cr.user_id=? LIMIT 1");
    $s->bind_param("s", $id); $s->execute();
    return $s->get_result()->fetch_assoc();
}
function getAllCourses($conn) {
    return $conn->query("SELECT id, name FROM courses ORDER BY name ASC");
}
function getAllIntakes($conn) {
    return $conn->query("SELECT i.id, i.name AS intake_name, i.date_launching, b.name AS branch_name FROM intakes i LEFT JOIN branches b ON b.id=i.branch_id ORDER BY i.date_launching DESC");
}
function getAllClassTypes($conn) {
    return $conn->query("SELECT id, name FROM class_type ORDER BY name ASC");
}
function formatIntakeLabel($name, $date, $branch) {
    $label = !empty($date) ? date('d F Y', strtotime($date)) : ($name ?: '');
    if (!empty($branch)) $label .= ' - ' . $branch;
    return $label ?: 'N/A';
}
function updateStudentDetails($conn, $id, $fname, $lname, $oname, $contact, $email, $dob, $country, $class_type_id) {
    $gender = 'female';
    $s = $conn->prepare("UPDATE users SET fname=?,lname=?,other_name=?,contact=?,email=?,dob=?,gender=?,country=?,class_type_id=? WHERE unique_id=?");
    $s->bind_param("ssssssssss", $fname, $lname, $oname, $contact, $email, $dob, $gender, $country, $class_type_id, $id);
    return $s->execute();
}
function updateCourse($conn, $reg_id, $course_id, $intake_id) {
    $s = $conn->prepare("UPDATE course_registration SET course_id=?,intake=? WHERE id=?");
    $s->bind_param("ssi", $course_id, $intake_id, $reg_id);
    return $s->execute();
}

/* -- Init --------------------------------------------------- */
$successMessage = '';
$errorMessage   = '';
$student        = [];
$courseData     = [];
$allCourses     = getAllCourses($conn);
$allIntakes     = getAllIntakes($conn);
$allClassTypes  = getAllClassTypes($conn);
$countries      = ['Uganda','Kenya','Tanzania','DRC','Sudan'];

/* -- Load on GET ------------------------------------------- */
if (isset($_GET['id'])) {
    $student_id = $_GET['id'];
    $student    = getStudentDetails($conn, $student_id) ?: [];
    $courseData = getStudentCourse($conn, $student_id)  ?: [];
    if (empty($student)) $errorMessage = 'Student not found.';
}

/* -- Handle POST ------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id    = $_POST['unique_id']     ?? '';
    $fname         = trim($_POST['fname']         ?? '');
    $lname         = trim($_POST['lname']         ?? '');
    $oname         = trim($_POST['other_name']    ?? '');
    $contact       = trim($_POST['contact']       ?? '');
    $email         = trim($_POST['email']         ?? '');
    $dob           = $_POST['dob']           ?? '';
    $country       = $_POST['country']       ?? '';
    $class_type_id = $_POST['class_type_id'] ?? '';
    $course_id     = $_POST['course_id']     ?? '';
    $intake_id     = $_POST['intake_id']     ?? '';
    $reg_id        = (int)($_POST['reg_id']  ?? 0);

    if (empty($fname) || empty($lname) || empty($contact) || empty($email)) {
        $errorMessage = 'All required fields must be filled.';
    } else {
        $ok1 = updateStudentDetails($conn, $student_id, $fname, $lname, $oname, $contact, $email, $dob, $country, $class_type_id);
        $ok2 = updateCourse($conn, $reg_id, $course_id, $intake_id);
        if ($ok1 && $ok2) {
            $successMessage = 'Student details updated successfully.';
            $student        = getStudentDetails($conn, $student_id) ?: [];
            $courseData     = getStudentCourse($conn, $student_id)  ?: [];
        } else {
            $errorMessage = 'Error updating: ' . $conn->error;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="app.css">



<!-- -- Topbar ----------------------------------------------- -->
<nav class="topbar">
  <div class="topbar-left">
    <h1><i class="fas fa-user-edit"></i> Edit Student</h1>
    <ol class="breadcrumb">
      <li><a href="?"><i class="fas fa-home"></i> Home</a></li>
      <li><a href="?view=view_students">Students</a></li>
      <li class="active">Edit Student</li>
    </ol>
  </div>
  <div class="topbar-right"><i class="fas fa-clock"></i> <?php echo date("h:i a") ?></div>
</nav>

<main class="page" style="max-width:860px;">

  <!-- -- Page heading --------------------------------------- -->
  <div class="page-heading">
    <div>
      <h2><em>Edit</em> Student Details</h2>
      <?php if (!empty($student)): ?>
      <div class="heading-meta">
        <span class="meta-pill">
          <i class="fas fa-user"></i>
          <?php echo htmlspecialchars(trim(($student['fname']??'').' '.($student['lname']??''))) ?>
        </span>
        <?php if (!empty($student['class_type_name'])): ?>
        <span class="meta-pill">
          <i class="fas fa-chalkboard"></i>
          <?php echo htmlspecialchars($student['class_type_name']) ?>
        </span>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <a href="?view=view_students" class="btn-modal-cancel">
      <i class="fas fa-arrow-left"></i> Back to Students
    </a>
  </div>

  <!-- -- Flash messages ------------------------------------- -->
  <?php if (!empty($successMessage)): ?>
  <div class="app-alert app-alert-info flash-msg" id="flashSuccess">
    <i class="fas fa-check-circle"></i>
    <span><?php echo htmlspecialchars($successMessage) ?></span>
    <button onclick="this.parentElement.remove()"
            style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;">
      <i class="fas fa-times"></i>
    </button>
  </div>
  <?php endif; ?>
  <?php if (!empty($errorMessage)): ?>
  <div class="app-alert app-alert-danger flash-msg" id="flashError">
    <i class="fas fa-exclamation-circle"></i>
    <span><?php echo htmlspecialchars($errorMessage) ?></span>
    <button onclick="this.parentElement.remove()"
            style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;">
      <i class="fas fa-times"></i>
    </button>
  </div>
  <?php endif; ?>

  <?php if (empty($student) && empty($errorMessage)): ?>
  <!-- -- No student selected -------------------------------- -->
  <div class="empty">
    <i class="fas fa-user-slash"></i>
    <p>No student selected. Please <a href="?view=view_students" class="link-maroon-bold">return to the student list</a>.</p>
  </div>

  <?php else: ?>

  <!-- -- Current info summary card -------------------------- -->
  <?php if (!empty($student)): ?>
  <div class="tbl-card" style="margin-bottom:20px;">
    <div class="tbl-toolbar">
      <span class="tbl-title"><i class="fas fa-id-card"></i> Current Student Info</span>
      <span class="s-badge s-graded">
        <i class="fas fa-circle" style="font-size:.5rem;"></i> Active
      </span>
    </div>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; padding:16px;">
      <?php
      $summaryFields = [
        ['label'=>'Full Name',  'icon'=>'fa-user',          'value'=> trim(($student['fname']??'').' '.($student['lname']??''))],
        ['label'=>'Class Type', 'icon'=>'fa-chalkboard',    'value'=> $student['class_type_name'] ?? 'Not assigned'],
        ['label'=>'Course',     'icon'=>'fa-graduation-cap','value'=> $courseData['course_name'] ?? 'N/A'],
        ['label'=>'Intake',     'icon'=>'fa-calendar-alt',  'value'=> formatIntakeLabel($courseData['intake_name']??'',$courseData['intake_start_date']??'',$courseData['branch_name']??'')],
      ];
      foreach ($summaryFields as $sf):
      ?>
      <div style="background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius); padding:11px 14px;">
        <div class="fb-meta-label">
          <i class="fas <?php echo $sf['icon'] ?> icon-gold"></i>
          <?php echo $sf['label'] ?>
        </div>
        <div style="font-size:.9rem; font-weight:500; color:var(--text); margin-top:3px;">
          <?php echo htmlspecialchars($sf['value']) ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- -- Edit form ------------------------------------------ -->
  <form method="post" action="">
    <input type="hidden" name="unique_id" value="<?php echo htmlspecialchars($student['unique_id'] ?? '') ?>">
    <input type="hidden" name="reg_id"    value="<?php echo htmlspecialchars($courseData['reg_id'] ?? '') ?>">
    <input type="hidden" name="gender"    value="female">

    <!-- Personal Information -->
    <div class="tbl-card" style="margin-bottom:16px;">
      <div class="tbl-toolbar">
        <span class="tbl-title"><i class="fas fa-user"></i> Personal Information</span>
      </div>
      <div style="padding:20px; display:flex; flex-direction:column; gap:14px;">

        <!-- Row: First + Last name -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
          <div class="ctrl-field">
            <label class="ctrl-label" for="fname">
              <i class="fas fa-user"></i> First Name <span style="color:var(--red)">*</span>
            </label>
            <input type="text" id="fname" name="fname" class="form-input"
                   value="<?php echo htmlspecialchars($student['fname'] ?? '') ?>"
                   placeholder="First name" required>
          </div>
          <div class="ctrl-field">
            <label class="ctrl-label" for="lname">
              <i class="fas fa-user"></i> Last Name <span style="color:var(--red)">*</span>
            </label>
            <input type="text" id="lname" name="lname" class="form-input"
                   value="<?php echo htmlspecialchars($student['lname'] ?? '') ?>"
                   placeholder="Last name" required>
          </div>
        </div>

        <!-- Row: Other name + Phone -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
          <div class="ctrl-field">
            <label class="ctrl-label" for="other_name">
              <i class="fas fa-user-tag"></i> Other Name
            </label>
            <input type="text" id="other_name" name="other_name" class="form-input"
                   value="<?php echo htmlspecialchars($student['other_name'] ?? '') ?>"
                   placeholder="Middle / other name">
          </div>
          <div class="ctrl-field">
            <label class="ctrl-label" for="contact">
              <i class="fas fa-phone"></i> Phone Number <span style="color:var(--red)">*</span>
            </label>
            <input type="tel" id="contact" name="contact" class="form-input"
                   value="<?php echo htmlspecialchars($student['contact'] ?? '') ?>"
                   placeholder="+256 700 000000" required>
          </div>
        </div>

        <!-- Row: Email + DOB -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
          <div class="ctrl-field">
            <label class="ctrl-label" for="email">
              <i class="fas fa-envelope"></i> Email <span style="color:var(--red)">*</span>
            </label>
            <input type="email" id="email" name="email" class="form-input"
                   value="<?php echo htmlspecialchars($student['email'] ?? '') ?>"
                   placeholder="student@example.com" required>
          </div>
          <div class="ctrl-field">
            <label class="ctrl-label" for="dob">
              <i class="fas fa-birthday-cake"></i> Date of Birth <span style="color:var(--red)">*</span>
            </label>
            <input type="date" id="dob" name="dob" class="form-input"
                   value="<?php echo htmlspecialchars($student['dob'] ?? '') ?>" required>
          </div>
        </div>

        <!-- Row: Country + Class type -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
          <div class="ctrl-field">
            <label class="ctrl-label" for="country">
              <i class="fas fa-globe"></i> Country <span style="color:var(--red)">*</span>
            </label>
            <select id="country" name="country" class="ctrl-select" style="min-width:100%;" required>
              <?php foreach ($countries as $c): ?>
                <option value="<?php echo $c ?>"
                        <?php echo ($student['country'] ?? '') === $c ? 'selected' : '' ?>>
                  <?php echo $c ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="ctrl-field">
            <label class="ctrl-label" for="class_type_id">
              <i class="fas fa-chalkboard"></i> Class Type <span style="color:var(--red)">*</span>
            </label>
            <select id="class_type_id" name="class_type_id" class="ctrl-select" style="min-width:100%;" required>
              <option value="">- Select class type -</option>
              <?php
              $allClassTypes->data_seek(0);
              while ($ct = $allClassTypes->fetch_assoc()):
              ?>
                <option value="<?php echo htmlspecialchars($ct['id']) ?>"
                        <?php echo ($student['class_type_id'] ?? '') == $ct['id'] ? 'selected' : '' ?>>
                  <?php echo htmlspecialchars($ct['name']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>

      </div>
    </div><!-- /personal card -->

    <!-- Course Information -->
    <div class="tbl-card" style="margin-bottom:20px;">
      <div class="tbl-toolbar">
        <span class="tbl-title"><i class="fas fa-graduation-cap"></i> Course Information</span>
      </div>
      <div style="padding:20px;">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">

          <div class="ctrl-field">
            <label class="ctrl-label" for="course_id">
              <i class="fas fa-book"></i> Course <span style="color:var(--red)">*</span>
            </label>
            <select id="course_id" name="course_id" class="ctrl-select" style="min-width:100%;" required>
              <option value="">Select course </option>
              <?php
              $allCourses->data_seek(0);
              while ($c = $allCourses->fetch_assoc()):
              ?>
                <option value="<?php echo htmlspecialchars($c['id']) ?>"
                        <?php echo ($courseData['course_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                  <?php echo htmlspecialchars($c['name']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="ctrl-field">
            <label class="ctrl-label" for="intake_id">
              <i class="fas fa-calendar-alt"></i> Intake <span style="color:var(--red)">*</span>
            </label>
            <select id="intake_id" name="intake_id" class="ctrl-select" style="min-width:100%;" required>
              <option value="">Select intake </option>
              <?php
              $allIntakes->data_seek(0);
              while ($in = $allIntakes->fetch_assoc()):
                $intakeLabel = formatIntakeLabel($in['intake_name'], $in['date_launching'], $in['branch_name']);
              ?>
                <option value="<?php echo htmlspecialchars($in['id']) ?>"
                        <?php echo ($courseData['intake'] ?? '') == $in['id'] ? 'selected' : '' ?>>
                  <?php echo htmlspecialchars($intakeLabel) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>

        </div>
      </div>
    </div><!-- /course card -->

    <!-- Action buttons -->
    <div style="display:flex; gap:10px;">
      <button type="submit" class="btn-export" style="flex:1; justify-content:center;">
        <i class="fas fa-save"></i> Update Student
      </button>
      <a href="?view=view_students" class="btn-modal-cancel" style="flex:1; justify-content:center; text-decoration:none; text-align:center;">
        <i class="fas fa-times"></i> Cancel
      </a>
    </div>

  </form>

  <?php endif; ?>

</main>

<script>
/* Auto-dismiss flash messages */
document.querySelectorAll('.flash-msg').forEach(function(el){
  setTimeout(function(){ el.remove(); }, 5000);
});
</script>