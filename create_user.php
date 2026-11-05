<?php
declare(strict_types=1);

require 'scripts/connector.php';

$systemname = "Elevate Her 360 eLearning";
$nowdate    = date('Y-m-d');

/*
|--------------------------------------------------------------------------
| Restore sticky selections
|--------------------------------------------------------------------------
*/
$selected_branch_id     = (int)($_POST['branch_id'] ?? $_SESSION['selected_branch_id'] ?? 0);
$selected_class_type_id = (int)($_POST['class_type_id'] ?? $_SESSION['selected_class_type_id'] ?? 0);
$selected_role          = strtolower(trim((string)($_POST['type'] ?? 'student')));

/*
|--------------------------------------------------------------------------
| Flash messages
|--------------------------------------------------------------------------
*/
$flash_error   = $_SESSION['error_message'] ?? null;
$flash_success = $_SESSION['success_message'] ?? null;

unset($_SESSION['error_message'], $_SESSION['success_message']);

/*
|--------------------------------------------------------------------------
| Data queries
|--------------------------------------------------------------------------
*/
$intakes = [];
$courses = [];
$branches = [];
$classTypes = [];

/* Intakes */
if ($result = $conn->query("
    SELECT id, name, date_launching
    FROM intakes
    ORDER BY name ASC
")) {
    while ($row = $result->fetch_assoc()) {
        $intakes[] = $row;
    }
    $result->free();
}

/* Courses */
if ($result = $conn->query("
    SELECT name
    FROM courses
    ORDER BY category ASC, name ASC
")) {
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row['name'];
    }
    $result->free();
}

/* Branches */
if ($result = $conn->query("
    SELECT id, name
    FROM branches
    ORDER BY name ASC
")) {
    while ($row = $result->fetch_assoc()) {
        $branches[] = $row;
    }
    $result->free();
}

/* Class types */
if ($result = $conn->query("
    SELECT id, name
    FROM class_type
    WHERE status = 'Active'
    ORDER BY name ASC
")) {
    while ($row = $result->fetch_assoc()) {
        $classTypes[] = $row;
    }
    $result->free();
}

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        Create User · <?php echo htmlspecialchars($systemname, ENT_QUOTES, 'UTF-8'); ?>
    </title>

    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Outfit:wght@300;400;500;600&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

    <link rel="stylesheet" href="app.css">
</head>

<body>

<!-- ============================================================
     Topbar
============================================================ -->
<nav class="topbar">

    <div class="topbar-left">

        <h1>
            <i class="fas fa-user-plus"></i>
            <?php echo htmlspecialchars($systemname, ENT_QUOTES, 'UTF-8'); ?>
        </h1>

        <ol class="breadcrumb">
            <li>
                <a href="?">
                    <i class="fas fa-home"></i>
                    Home
                </a>
            </li>

            <li>
                <a>Users</a>
            </li>

            <li class="active">
                Create User
            </li>
        </ol>

    </div>

    <div class="topbar-right">
        <i class="fas fa-clock"></i>
        <?php echo date("h:i a"); ?>
    </div>

</nav>


<main class="page" style="max-width:760px;">

    <!-- ========================================================
         Page heading
    ========================================================= -->
    <div class="page-heading">

        <div>

            <h2>
                <em>Create</em> New User
            </h2>

            <div class="heading-meta">

                <span class="meta-pill">
                    <i class="fas fa-user-plus"></i>
                    Student, Instructor, or Admin
                </span>

            </div>

        </div>

        <a
            href="?view=view_students"
            class="btn-modal-cancel"
        >
            <i class="fas fa-arrow-left"></i>
            Back
        </a>

    </div>


    <!-- ========================================================
         Flash messages
    ========================================================= -->

    <?php if ($flash_error): ?>

        <div
            class="app-alert app-alert-danger flash-msg"
            id="flashError"
        >

            <i class="fas fa-exclamation-circle"></i>

            <span>
                <?php
                echo htmlspecialchars(
                    (string)$flash_error,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
            </span>

            <button
                type="button"
                onclick="this.parentElement.remove()"
                style="
                    margin-left:auto;
                    background:none;
                    border:none;
                    cursor:pointer;
                    color:inherit;
                "
            >
                <i class="fas fa-times"></i>
            </button>

        </div>

    <?php endif; ?>


    <?php if ($flash_success): ?>

        <div
            class="app-alert app-alert-info flash-msg"
            id="flashSuccess"
        >

            <i class="fas fa-check-circle"></i>

            <span>
                <?php
                echo htmlspecialchars(
                    (string)$flash_success,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
            </span>

            <button
                type="button"
                onclick="this.parentElement.remove()"
                style="
                    margin-left:auto;
                    background:none;
                    border:none;
                    cursor:pointer;
                    color:inherit;
                "
            >
                <i class="fas fa-times"></i>
            </button>

        </div>

    <?php endif; ?>


    <!-- ========================================================
         Create User Form
    ========================================================= -->
    <form
        method="post"
        action="scripts/create_user.php"
        id="createUserForm"
        onsubmit="return docheck();"
    >

        <!-- ====================================================
             Account Role
        ===================================================== -->
        <div
            class="tbl-card"
            style="margin-bottom:16px;"
        >

            <div class="tbl-toolbar">

                <span class="tbl-title">
                    <i class="fas fa-id-badge"></i>
                    Account Role
                </span>

            </div>

            <div style="padding:20px;">

                <div class="ctrl-field">

                    <label
                        class="ctrl-label"
                        for="type"
                    >
                        <i class="fas fa-user-tag"></i>
                        Role

                        <span style="color:var(--red)">
                            *
                        </span>
                    </label>

                    <select
                        id="type"
                        name="type"
                        class="ctrl-select"
                        style="min-width:100%;"
                        required
                    >

                        <option
                            value="student"
                            <?php echo $selected_role === 'student' ? 'selected' : ''; ?>
                        >
                            Student
                        </option>

                        <option
                            value="instructor"
                            <?php echo $selected_role === 'instructor' ? 'selected' : ''; ?>
                        >
                            Instructor
                        </option>

                        <option
                            value="admin"
                            <?php echo $selected_role === 'admin' ? 'selected' : ''; ?>
                        >
                            Admin
                        </option>

                    </select>

                    <div
                        class="field-hint"
                        id="roleHint"
                    >
                        <i class="fas fa-info-circle"></i>

                        Students and instructors require organisation,
                        course and intake information.
                        Administrators do not.
                    </div>

                </div>

            </div>

        </div>


        <!-- ====================================================
             Personal Information
        ===================================================== -->
        <div
            class="tbl-card"
            style="margin-bottom:16px;"
        >

            <div class="tbl-toolbar">

                <span class="tbl-title">
                    <i class="fas fa-user"></i>
                    Personal Information
                </span>

            </div>

            <div
                style="
                    padding:20px;
                    display:flex;
                    flex-direction:column;
                    gap:14px;
                "
            >

                <!-- First Name + Last Name -->
                <div
                    style="
                        display:grid;
                        grid-template-columns:1fr 1fr;
                        gap:14px;
                    "
                >

                    <div class="ctrl-field">

                        <label
                            class="ctrl-label"
                            for="fname"
                        >
                            <i class="fas fa-user"></i>
                            First Name

                            <span style="color:var(--red)">
                                *
                            </span>
                        </label>

                        <input
                            type="text"
                            id="fname"
                            name="fname"
                            class="form-input"
                            placeholder="First name"
                            required
                        >

                    </div>


                    <div class="ctrl-field">

                        <label
                            class="ctrl-label"
                            for="lname"
                        >
                            <i class="fas fa-user"></i>
                            Last Name

                            <span style="color:var(--red)">
                                *
                            </span>
                        </label>

                        <input
                            type="text"
                            id="lname"
                            name="lname"
                            class="form-input"
                            placeholder="Last name"
                            required
                        >

                    </div>

                </div>


                <!-- Other Name + Phone -->
                <div
                    style="
                        display:grid;
                        grid-template-columns:1fr 1fr;
                        gap:14px;
                    "
                >

                    <div class="ctrl-field">

                        <label
                            class="ctrl-label"
                            for="oname"
                        >
                            <i class="fas fa-user-tag"></i>
                            Other Name
                        </label>

                        <input
                            type="text"
                            id="oname"
                            name="oname"
                            class="form-input"
                            placeholder="Middle / other name"
                        >

                    </div>


                    <div class="ctrl-field">

                        <label
                            class="ctrl-label"
                            for="contact"
                        >
                            <i class="fas fa-phone"></i>
                            Phone Number

                            <span style="color:var(--red)">
                                *
                            </span>
                        </label>

                        <input
                            type="tel"
                            id="contact"
                            name="contact"
                            class="form-input"
                            placeholder="+256 700 000000"
                            required
                        >

                    </div>

                </div>


                <!-- Email + DOB -->
                <div
                    style="
                        display:grid;
                        grid-template-columns:1fr 1fr;
                        gap:14px;
                    "
                >

                    <div class="ctrl-field">

                        <label
                            class="ctrl-label"
                            for="email"
                        >
                            <i class="fas fa-envelope"></i>
                            Email Address

                            <span style="color:var(--red)">
                                *
                            </span>
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-input"
                            placeholder="user@example.com"
                            required
                        >

                        <span
                            id="report"
                            style="
                                font-size:.72rem;
                                color:var(--red);
                                margin-top:3px;
                                display:block;
                            "
                        ></span>

                    </div>


                    <div class="ctrl-field">

                        <label
                            class="ctrl-label"
                            for="dob"
                        >
                            <i class="fas fa-birthday-cake"></i>
                            Date of Birth

                            <span style="color:var(--red)">
                                *
                            </span>
                        </label>

                        <input
                            type="date"
                            id="dob"
                            name="dob"
                            class="form-input"
                            required
                        >

                    </div>

                </div>


                <!-- Gender + Country -->
                <div
                    style="
                        display:grid;
                        grid-template-columns:1fr 1fr;
                        gap:14px;
                    "
                >

                    <div class="ctrl-field">

                        <label
                            class="ctrl-label"
                            for="gender"
                        >
                            <i class="fas fa-venus-mars"></i>
                            Gender

                            <span style="color:var(--red)">
                                *
                            </span>
                        </label>

                        <select
                            id="gender"
                            name="gender"
                            class="ctrl-select"
                            style="min-width:100%;"
                            required
                        >

                            <option value="male">
                                Male
                            </option>

                            <option
                                value="female"
                                selected
                            >
                                Female
                            </option>

                        </select>

                    </div>


                    <div class="ctrl-field">

                        <label
                            class="ctrl-label"
                            for="country"
                        >
                            <i class="fas fa-globe"></i>
                            Country

                            <span style="color:var(--red)">
                                *
                            </span>
                        </label>

                        <select
                            id="country"
                            name="country"
                            class="ctrl-select"
                            style="min-width:100%;"
                            required
                        >

                            <option value="Uganda" selected>
                                Uganda
                            </option>

                            <option value="Kenya">
                                Kenya
                            </option>

                            <option value="Tanzania">
                                Tanzania
                            </option>

                            <option value="DRC">
                                DRC
                            </option>

                            <option value="Sudan">
                                Sudan
                            </option>

                        </select>

                    </div>

                </div>

            </div>

        </div>


        <!-- ====================================================
             Organisation

             Hidden completely for Admin.
        ===================================================== -->
        <div
            class="tbl-card"
            style="margin-bottom:16px;"
            id="organisationCard"
        >

            <div class="tbl-toolbar">

                <span class="tbl-title">
                    <i class="fas fa-building"></i>
                    Organisation
                </span>

                <span
                    class="count-pill"
                    style="
                        background:var(--maroon-dim);
                        color:var(--maroon);
                    "
                >
                    Student / Instructor only
                </span>

            </div>


            <div
                style="
                    padding:20px;
                    display:flex;
                    flex-direction:column;
                    gap:14px;
                "
            >

                <div
                    style="
                        display:grid;
                        grid-template-columns:1fr 1fr;
                        gap:14px;
                    "
                >

                    <!-- Branch -->
                    <div
                        class="ctrl-field"
                        id="branch_field"
                    >

                        <label
                            class="ctrl-label"
                            for="branch_id"
                        >
                            <i class="fas fa-code-branch"></i>
                            Branch

                            <span style="color:var(--red)">
                                *
                            </span>
                        </label>

                        <select
                            id="branch_id"
                            name="branch_id"
                            class="ctrl-select"
                            style="min-width:100%;"
                        >

                            <option value="">
                                Select branch
                            </option>

                            <?php foreach ($branches as $branch): ?>

                                <option
                                    value="<?php echo (int)$branch['id']; ?>"
                                    <?php
                                    echo (
                                        (int)$branch['id']
                                        ===
                                        $selected_branch_id
                                    ) ? 'selected' : '';
                                    ?>
                                >
                                    <?php
                                    echo htmlspecialchars(
                                        (string)$branch['name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- Class Type -->
                    <div
                        class="ctrl-field"
                        id="class_type_field"
                    >

                        <label
                            class="ctrl-label"
                            for="class_type_id"
                        >
                            <i class="fas fa-chalkboard"></i>
                            Class Type

                            <span style="color:var(--red)">
                                *
                            </span>
                        </label>

                        <select
                            id="class_type_id"
                            name="class_type_id"
                            class="ctrl-select"
                            style="min-width:100%;"
                        >

                            <option value="">
                                Select class type
                            </option>

                            <?php foreach ($classTypes as $classType): ?>

                                <option
                                    value="<?php echo (int)$classType['id']; ?>"
                                    <?php
                                    echo (
                                        (int)$classType['id']
                                        ===
                                        $selected_class_type_id
                                    ) ? 'selected' : '';
                                    ?>
                                >
                                    <?php
                                    echo htmlspecialchars(
                                        (string)$classType['name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                </div>

            </div>

        </div>


        <!-- ====================================================
             Course & Intake
             Student / Instructor only
        ===================================================== -->
        <div
            class="tbl-card"
            style="margin-bottom:20px;"
            id="courseCard"
        >

            <div class="tbl-toolbar">

                <span class="tbl-title">
                    <i class="fas fa-graduation-cap"></i>
                    Course &amp; Intake
                </span>

                <span
                    class="count-pill"
                    style="
                        background:var(--maroon-dim);
                        color:var(--maroon);
                    "
                >
                    Student / Instructor only
                </span>

            </div>


            <div style="padding:20px;">

                <div
                    style="
                        display:grid;
                        grid-template-columns:1fr 1fr;
                        gap:14px;
                    "
                >

                    <!-- Course -->
                    <div
                        class="ctrl-field"
                        id="course_field"
                    >

                        <label
                            class="ctrl-label"
                            for="course_name"
                        >
                            <i class="fas fa-book"></i>
                            Course Name

                            <span style="color:var(--red)">
                                *
                            </span>
                        </label>

                        <select
                            id="course_name"
                            name="course_name"
                            class="ctrl-select"
                            style="min-width:100%;"
                        >

                            <option
                                value=""
                                selected
                            >
                                Select a course
                            </option>

                            <?php foreach ($courses as $course): ?>

                                <option
                                    value="<?php
                                    echo htmlspecialchars(
                                        (string)$course,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>"
                                >
                                    <?php
                                    echo htmlspecialchars(
                                        (string)$course,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- Intake -->
                    <div
                        class="ctrl-field"
                        id="intake_field"
                    >

                        <label
                            class="ctrl-label"
                            for="intake"
                        >
                            <i class="fas fa-calendar-alt"></i>
                            Intake

                            <span style="color:var(--red)">
                                *
                            </span>
                        </label>

                        <select
                            id="intake"
                            name="intake"
                            class="ctrl-select"
                            style="min-width:100%;"
                        >

                            <option
                                value=""
                                selected
                            >
                                Select intake
                            </option>

                            <?php if (empty($intakes)): ?>

                                <option
                                    value=""
                                    disabled
                                >
                                    No intakes available
                                </option>

                            <?php else: ?>

                                <?php foreach ($intakes as $intake): ?>

                                    <option
                                        value="<?php echo (int)$intake['id']; ?>"
                                    >
                                        <?php
                                        echo htmlspecialchars(
                                            (string)$intake['name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>

                                        <?php if (!empty($intake['date_launching'])): ?>

                                            (
                                            <?php
                                            echo date(
                                                'd M Y',
                                                strtotime(
                                                    (string)$intake['date_launching']
                                                )
                                            );
                                            ?>
                                            )

                                        <?php endif; ?>

                                    </option>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </select>

                    </div>

                </div>

            </div>

        </div>


        <!-- ====================================================
             Submit
        ===================================================== -->
        <div
            style="
                display:flex;
                gap:10px;
            "
        >

            <button
                type="submit"
                name="submit1"
                class="btn-export"
                style="
                    flex:1;
                    justify-content:center;
                "
            >
                <i class="fas fa-user-plus"></i>
                Create User
            </button>


            <a
                href="?view=view_students"
                class="btn-modal-cancel"
                style="
                    flex:1;
                    justify-content:center;
                    text-decoration:none;
                    text-align:center;
                "
            >
                <i class="fas fa-times"></i>
                Cancel
            </a>

        </div>

    </form>

</main>


<script>
'use strict';

/*
|--------------------------------------------------------------------------
| Auto-dismiss flash messages
|--------------------------------------------------------------------------
*/
document.querySelectorAll('.flash-msg').forEach(function (element) {

    setTimeout(function () {

        if (element) {
            element.remove();
        }

    }, 5000);

});


/*
|--------------------------------------------------------------------------
| Role Fields
|--------------------------------------------------------------------------
|
| Student:
|   Branch      required
|   Class       required
|   Course      required
|   Intake      required
|
| Instructor:
|   Branch      required
|   Class       required
|   Course      required
|   Intake      required
|
| Admin:
|   Branch      NOT required
|   Class       NOT required
|   Course      NOT required
|   Intake      NOT required
|
|--------------------------------------------------------------------------
*/
function toggleRoleFields(selectElement) {

    if (!selectElement) {
        return;
    }

    const role = String(selectElement.value || '')
        .trim()
        .toLowerCase();

    const isAdmin = role === 'admin';

    const organisationCard =
        document.getElementById('organisationCard');

    const courseCard =
        document.getElementById('courseCard');

    const branch =
        document.getElementById('branch_id');

    const classType =
        document.getElementById('class_type_id');

    const course =
        document.getElementById('course_name');

    const intake =
        document.getElementById('intake');

    const roleHint =
        document.getElementById('roleHint');


    /*
    |--------------------------------------------------------------------------
    | ADMIN
    |--------------------------------------------------------------------------
    */
    if (isAdmin) {

        /*
         * Hide sections.
         */
        if (organisationCard) {
            organisationCard.style.display = 'none';
        }

        if (courseCard) {
            courseCard.style.display = 'none';
        }


        /*
         * Remove required attributes.
         */
        if (branch) {
            branch.required = false;
            branch.disabled = true;
        }

        if (classType) {
            classType.required = false;
            classType.disabled = true;
        }

        if (course) {
            course.required = false;
            course.disabled = true;
        }

        if (intake) {
            intake.required = false;
            intake.disabled = true;
        }


        /*
         * Update help text.
         */
        if (roleHint) {

            roleHint.innerHTML =
                '<i class="fas fa-info-circle"></i> ' +
                'Admin accounts do not require branch, class, course or intake.';

        }

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | STUDENT / INSTRUCTOR
    |--------------------------------------------------------------------------
    */

    if (organisationCard) {
        organisationCard.style.display = '';
    }

    if (courseCard) {
        courseCard.style.display = '';
    }


    /*
     * Enable fields before making them required.
     */
    if (branch) {
        branch.disabled = false;
        branch.required = true;
    }

    if (classType) {
        classType.disabled = false;
        classType.required = true;
    }

    if (course) {
        course.disabled = false;
        course.required = true;
    }

    if (intake) {
        intake.disabled = false;
        intake.required = true;
    }


    /*
     * Context-specific help text.
     */
    if (roleHint) {

        if (role === 'instructor') {

            roleHint.innerHTML =
                '<i class="fas fa-info-circle"></i> ' +
                'Instructor accounts require branch, class, course and intake.';

        } else {

            roleHint.innerHTML =
                '<i class="fas fa-info-circle"></i> ' +
                'Student accounts require branch, class, course and intake.';

        }

    }

}


/*
|--------------------------------------------------------------------------
| Form Validation
|--------------------------------------------------------------------------
*/
function docheck() {

    const roleElement =
        document.getElementById('type');

    if (!roleElement) {
        return false;
    }

    const role =
        String(roleElement.value || '')
            .trim()
            .toLowerCase();

    /*
     * Admin does not require academic / organisation fields.
     */
    if (role === 'admin') {
        return true;
    }


    const branch =
        document.getElementById('branch_id');

    const classType =
        document.getElementById('class_type_id');

    const course =
        document.getElementById('course_name');

    const intake =
        document.getElementById('intake');


    if (!branch || !branch.value) {

        alert('Please select a branch.');

        if (branch) {
            branch.focus();
        }

        return false;
    }


    if (!classType || !classType.value) {

        alert('Please select a class type.');

        if (classType) {
            classType.focus();
        }

        return false;
    }


    if (!course || !course.value) {

        alert('Please select a course.');

        if (course) {
            course.focus();
        }

        return false;
    }


    if (!intake || !intake.value) {

        alert('Please select an intake.');

        if (intake) {
            intake.focus();
        }

        return false;
    }


    return true;
}


/*
|--------------------------------------------------------------------------
| Event listeners
|--------------------------------------------------------------------------
*/
document.addEventListener('DOMContentLoaded', function () {

    const roleSelect =
        document.getElementById('type');

    if (!roleSelect) {
        return;
    }


    /*
     * Set initial form state.
     */
    toggleRoleFields(roleSelect);


    /*
     * Handle role changes.
     */
    roleSelect.addEventListener('change', function () {

        toggleRoleFields(this);

    });

});

</script>

</body>
</html>