<?php
require 'scripts/connector.php';


function getLecturerDetails(mysqli $conn, string $lecturer_id): ?array
{
    $stmt = $conn->prepare("SELECT * FROM users WHERE unique_id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param("s", $lecturer_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $data ?: null;
}

function getClassTypes(mysqli $conn): array
{
    $result = $conn->query(
        "SELECT id, name FROM class_type WHERE status='Active' ORDER BY name ASC"
    );
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function classTypeExists(mysqli $conn, int $class_type_id): bool
{
    $stmt = $conn->prepare(
        "SELECT id FROM class_type WHERE id = ? AND status='Active' LIMIT 1"
    );
    if (!$stmt) return false;
    $stmt->bind_param("i", $class_type_id);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->num_rows;
    $stmt->close();
    return $exists;
}

function updateLecturerDetails(
    mysqli $conn, string $lecturer_id,
    string $fname, string $lname, string $oname,
    string $contact, string $email, string $dob,
    string $gender, string $country, string $verify_user,
    int $class_type_id, string $role
): bool {
    $stmt = $conn->prepare("
        UPDATE users SET
            fname         = ?, lname        = ?, other_name    = ?,
            contact       = ?, email        = ?, dob           = ?,
            gender        = ?, country      = ?, verified      = ?,
            class_type_id = ?, acc_type     = ?
        WHERE unique_id = ?
    ");
    if (!$stmt) return false;
    $stmt->bind_param(
        "sssssssssiss",
        $fname, $lname, $oname, $contact, $email,
        $dob, $gender, $country, $verify_user,
        $class_type_id, $role, $lecturer_id
    );
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}



$successMessage = '';
$errorMessage   = '';
$classTypes     = getClassTypes($conn);
$lecturer       = null;
$lecturer_id    = '';

if (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $successMessage = "Instructor details updated successfully.";
}

if (isset($_GET['id'])) {
    $lecturer_id = trim($_GET['id']);
    $lecturer    = getLecturerDetails($conn, $lecturer_id);
    if (!$lecturer) $errorMessage = "Instructor not found.";
}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $lecturer_id   = trim($_POST['unique_id']     ?? '');
    $fname         = trim($_POST['fname']         ?? '');
    $lname         = trim($_POST['lname']         ?? '');
    $oname         = trim($_POST['other_name']    ?? '');
    $contact       = trim($_POST['contact']       ?? '');
    $email         = trim($_POST['email']         ?? '');
    $dob           = trim($_POST['dob']           ?? '');
    $gender        = trim($_POST['gender']        ?? '');
    $country       = trim($_POST['country']       ?? '');
    $verify_user   = trim($_POST['verify_user']   ?? '');
    $role          = 'xtfbry';
    $class_type_id = !empty($_POST['class_type_id'])
                        ? (int)$_POST['class_type_id'] : null;

    // Validation
    if (!$lecturer_id || !$fname || !$lname || !$contact ||
        !$email || !$dob || !$gender || !$country || !$verify_user) {
        $errorMessage = "All required fields must be filled.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Invalid email address.";
    } elseif ($class_type_id === null) {
        $errorMessage = "Please select a class type.";
    } elseif (!classTypeExists($conn, $class_type_id)) {
        $errorMessage = "Invalid class type selected.";
    } else {
        $updated = updateLecturerDetails(
            $conn, $lecturer_id, $fname, $lname, $oname,
            $contact, $email, $dob, $gender, $country,
            $verify_user, $class_type_id, $role
        );

        if ($updated) {
            header("Location: index.php?view=edit_instructor&id="
                   . urlencode($lecturer_id) . "&updated=1");
            exit;
        }
        $errorMessage = "Error updating instructor details.";
    }

    $lecturer = getLecturerDetails($conn, $lecturer_id);
}


function sel(string $current, string $value): string {
    return $current === $value ? 'selected' : '';
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


<div class="page-heading">
    <div>
        <h2>Edit <em>Instructor</em></h2>
        <div class="heading-meta">
            <span class="meta-pill">
                <i class="fa fa-house"></i>
                <a href="?" style="text-decoration:none;color:inherit;">Home</a>
            </span>
            <span class="meta-pill">
                <i class="fa fa-chalkboard-teacher"></i>
                <a href="?view=view_instructors"
                   style="text-decoration:none;color:inherit;">Instructors</a>
            </span>
            <span class="meta-pill"
                  style="background:var(--maroon-dim);color:var(--maroon);
                         border-color:rgba(122,25,25,.18);">
                <i class="fa fa-pen"></i> Edit
            </span>
        </div>
    </div>
    <?php if ($lecturer): ?>
        <span class="count-pill">
            ID: <?php echo htmlspecialchars($lecturer['unique_id']); ?>
        </span>
    <?php endif; ?>
</div>


<?php if ($errorMessage): ?>
    <div class="app-alert app-alert-danger" id="alertMsg"
         style="margin-bottom:18px;">
        <i class="fa fa-circle-xmark"></i>
        <?php echo htmlspecialchars($errorMessage); ?>
    </div>
<?php elseif ($successMessage): ?>
    <div class="app-alert app-alert-success" id="alertMsg"
         style="margin-bottom:18px;">
        <i class="fa fa-circle-check"></i>
        <?php echo htmlspecialchars($successMessage); ?>
    </div>
<?php endif; ?>

<?php if (!$lecturer): ?>

    <!-- No-data state -->
    <div class="empty">
        <i class="fa fa-user-slash"></i>
        <p>No instructor data found.
           <a href="?view=view_instructors"
              style="color:var(--maroon);font-weight:600;">
               Back to Instructors
           </a>
        </p>
    </div>

<?php else: ?>

<div style="max-width:740px;margin:0 auto;">
    <div class="tbl-card">

        <!-- Card header -->
        <div class="tbl-toolbar">
            <span class="tbl-title">
                <i class="fa fa-user-pen tbl-title-icon"></i>
                <?php echo htmlspecialchars(
                    ($lecturer['fname'] ?? '') . ' ' . ($lecturer['lname'] ?? '')
                ); ?>
            </span>
            <span style="font-size:.72rem;color:var(--muted);
                         display:flex;align-items:center;gap:4px;">
                <i class="fa fa-asterisk"
                   style="color:var(--red);font-size:.55rem;"></i>
                Required fields
            </span>
        </div>

        <form method="POST" id="editInstructorForm"
              style="padding:22px 24px;">

            <input type="hidden" name="unique_id"
                   value="<?php echo htmlspecialchars($lecturer['unique_id']); ?>">

           
            <div style="display:grid;grid-template-columns:1fr 1fr;
                        gap:14px;margin-bottom:16px;">
                <div class="ctrl-field">
                    <label class="ctrl-label" for="fname">
                        <i class="fa fa-user"></i>
                        First Name <span style="color:var(--red);">*</span>
                    </label>
                    <input type="text" id="fname" name="fname"
                           class="form-input"
                           value="<?php echo htmlspecialchars($lecturer['fname'] ?? ''); ?>"
                           required>
                </div>
                <div class="ctrl-field">
                    <label class="ctrl-label" for="lname">
                        <i class="fa fa-user"></i>
                        Last Name <span style="color:var(--red);">*</span>
                    </label>
                    <input type="text" id="lname" name="lname"
                           class="form-input"
                           value="<?php echo htmlspecialchars($lecturer['lname'] ?? ''); ?>"
                           required>
                </div>
            </div>

           
            <div style="display:grid;grid-template-columns:1fr 1fr;
                        gap:14px;margin-bottom:16px;">
                <div class="ctrl-field">
                    <label class="ctrl-label" for="other_name">
                        <i class="fa fa-user-tag"></i> Other Name
                    </label>
                    <input type="text" id="other_name" name="other_name"
                           class="form-input"
                           value="<?php echo htmlspecialchars($lecturer['other_name'] ?? ''); ?>">
                </div>
                <div class="ctrl-field">
                    <label class="ctrl-label" for="contact">
                        <i class="fa fa-phone"></i>
                        Phone <span style="color:var(--red);">*</span>
                    </label>
                    <input type="tel" id="contact" name="contact"
                           class="form-input"
                           value="<?php echo htmlspecialchars($lecturer['contact'] ?? ''); ?>"
                           required>
                </div>
            </div>

           
            <div style="display:grid;grid-template-columns:1fr 1fr;
                        gap:14px;margin-bottom:16px;">
                <div class="ctrl-field">
                    <label class="ctrl-label" for="email">
                        <i class="fa fa-envelope"></i>
                        Email <span style="color:var(--red);">*</span>
                    </label>
                    <input type="email" id="email" name="email"
                           class="form-input"
                           value="<?php echo htmlspecialchars($lecturer['email'] ?? ''); ?>"
                           required>
                </div>
                <div class="ctrl-field">
                    <label class="ctrl-label" for="dob">
                        <i class="fa fa-calendar-day"></i>
                        Date of Birth <span style="color:var(--red);">*</span>
                    </label>
                    <input type="date" id="dob" name="dob"
                           class="form-input"
                           value="<?php echo htmlspecialchars($lecturer['dob'] ?? ''); ?>"
                           required>
                </div>
            </div>

            
            <div style="display:grid;grid-template-columns:1fr 1fr;
                        gap:14px;margin-bottom:16px;">
                <div class="ctrl-field">
                    <label class="ctrl-label" for="gender">
                        <i class="fa fa-venus-mars"></i>
                        Gender <span style="color:var(--red);">*</span>
                    </label>
                    <select id="gender" name="gender"
                            class="ctrl-select" style="min-width:100%;"
                            required>
                        <option value="">Select Gender</option>
                        <option value="male"
                            <?php echo sel($lecturer['gender'] ?? '', 'male'); ?>>
                            Male
                        </option>
                        <option value="female"
                            <?php echo sel($lecturer['gender'] ?? '', 'female'); ?>>
                            Female
                        </option>
                    </select>
                </div>
                <div class="ctrl-field">
                    <label class="ctrl-label" for="country">
                        <i class="fa fa-earth-africa"></i>
                        Country <span style="color:var(--red);">*</span>
                    </label>
                    <select id="country" name="country"
                            class="ctrl-select" style="min-width:100%;"
                            required>
                        <option value="">Select Country</option>
                        <?php foreach (['Uganda','Kenya','Tanzania',
                                        'Rwanda','Burundi','South Sudan',
                                        'DRC','Other'] as $c): ?>
                            <option value="<?php echo $c; ?>"
                                <?php echo sel($lecturer['country'] ?? '', $c); ?>>
                                <?php echo $c; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

           
            <div style="display:grid;grid-template-columns:1fr 1fr;
                        gap:14px;margin-bottom:24px;">
                <div class="ctrl-field">
                    <label class="ctrl-label" for="class_type_id">
                        <i class="fa fa-users"></i>
                        Class Type <span style="color:var(--red);">*</span>
                    </label>
                    <select id="class_type_id" name="class_type_id"
                            class="ctrl-select" style="min-width:100%;"
                            required>
                        <option value="">Select Class Type</option>
                        <?php foreach ($classTypes as $ct): ?>
                            <option value="<?php echo (int)$ct['id']; ?>"
                                <?php echo (int)($lecturer['class_type_id'] ?? 0) === (int)$ct['id']
                                           ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ct['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ctrl-field">
                    <label class="ctrl-label" for="verify_user">
                        <i class="fa fa-circle-check"></i>
                        Verified <span style="color:var(--red);">*</span>
                    </label>
                    <select id="verify_user" name="verify_user"
                            class="ctrl-select" style="min-width:100%;"
                            required>
                        <option value="">Select Option</option>
                        <option value="yes"
                            <?php echo sel($lecturer['verified'] ?? '', 'yes'); ?>>
                            <i class="fa fa-check"></i> Yes - Active
                        </option>
                        <option value="no"
                            <?php echo sel($lecturer['verified'] ?? '', 'no'); ?>>
                            No - Inactive
                        </option>
                    </select>
                </div>
            </div>

          
            <div style="display:flex;align-items:center;
                        justify-content:flex-end;gap:10px;
                        padding-top:16px;
                        border-top:1px solid var(--border);">
                <a href="?view=view_instructors" class="btn-modal-cancel">
                    <i class="fa fa-xmark"></i> Cancel
                </a>
                <button type="submit" class="btn-export" id="submitBtn">
                    <span class="spin" id="submitSpin"></span>
                    <i class="fa fa-floppy-disk" id="submitIcon"></i>
                    Update Instructor
                </button>
            </div>

        </form>
    </div><!-- /tbl-card -->

  
</div><!-- /max-width wrapper -->

<?php endif; ?>


<script>
// Auto-dismiss alerts after 5 s
setTimeout(() => {
    const el = document.getElementById('alertMsg');
    if (el) {
        el.style.transition = 'opacity .4s';
        el.style.opacity    = '0';
        setTimeout(() => el.remove(), 420);
    }
}, 5000);

// Submit spinner
const form = document.getElementById('editInstructorForm');
if (form) {
    form.addEventListener('submit', function () {
        const btn  = document.getElementById('submitBtn');
        const spin = document.getElementById('submitSpin');
        const icon = document.getElementById('submitIcon');
        btn.disabled       = true;
        spin.classList.add('active');
        icon.style.display = 'none';
    });
}
</script>