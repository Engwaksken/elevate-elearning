<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Session / Configuration
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('Africa/Kampala');

require 'connector.php';
require 'EmailService.php';
require_once __DIR__ . '/auth_publish.php';

$systemname = 'Elevate Her 360 eLearning';
$nowdate    = date('Y-m-d H:i:s');

$emailService = new EmailService();


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function setErrorAndRedirect(string $message, string $systemname): never
{
    $_SESSION['error_message'] = $message;

    if (!empty($_POST)) {
        $_SESSION['old_input'] = $_POST;
    }

    header('Location: ../create_student');
    exit;
}


function setSuccessAndRedirect(string $message, string $systemname): never
{
    $_SESSION['registration_success'] = true;
    $_SESSION['success_message']      = $message;

    unset($_SESSION['old_input']);

    header('Location: ../create_student');
    exit;
}


function cleanText(?string $value): string
{
    return trim(strip_tags((string)$value));
}


function generateSecureRandomString(int $length = 10): string
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $max         = strlen($characters) - 1;
    $result      = '';

    for ($i = 0; $i < $length; $i++) {
        $result .= $characters[random_int(0, $max)];
    }

    return $result;
}


function generateFormattedUserId(): string
{
    return strtolower(generateSecureRandomString(10));
}


/*
|--------------------------------------------------------------------------
| Validate Request Method
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setErrorAndRedirect(
        'Invalid request method.',
        $systemname
    );
}


/*
|--------------------------------------------------------------------------
| Basic Required Fields
|--------------------------------------------------------------------------
*/

if (
    !isset(
        $_POST['email'],
        $_POST['fname'],
        $_POST['lname'],
        $_POST['type']
    )
) {
    setErrorAndRedirect(
        'Required form data is missing.',
        $systemname
    );
}


/*
|--------------------------------------------------------------------------
| Read Main Form Fields
|--------------------------------------------------------------------------
*/

$email = trim((string)($_POST['email'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    setErrorAndRedirect(
        'Please enter a valid email address.',
        $systemname
    );
}

$email = strtolower($email);

$fname   = cleanText($_POST['fname'] ?? '');
$lname   = cleanText($_POST['lname'] ?? '');
$oname   = cleanText($_POST['oname'] ?? '');
$contact = cleanText($_POST['contact'] ?? '');
$gender  = cleanText($_POST['gender'] ?? '');
$country = cleanText($_POST['country'] ?? '');
$dob     = cleanText($_POST['dob'] ?? '');

$role = strtolower(
    cleanText($_POST['type'] ?? '')
);

$is_pwd = isset($_POST['is_pwd']) ? 1 : 0;


/*
|--------------------------------------------------------------------------
| Role Validation
|--------------------------------------------------------------------------
*/

$validRoles = [
    'student',
    'instructor',
    'accountant',
    'admin'
];

if (!in_array($role, $validRoles, true)) {
    setErrorAndRedirect(
        'Invalid user role selected.',
        $systemname
    );
}


/*
|--------------------------------------------------------------------------
| Required Personal Information
|--------------------------------------------------------------------------
*/

if ($fname === '') {
    setErrorAndRedirect(
        'First name is required.',
        $systemname
    );
}

if ($lname === '') {
    setErrorAndRedirect(
        'Last name is required.',
        $systemname
    );
}

if ($contact === '') {
    setErrorAndRedirect(
        'Phone number is required.',
        $systemname
    );
}

if ($gender === '') {
    setErrorAndRedirect(
        'Gender is required.',
        $systemname
    );
}

if ($country === '') {
    setErrorAndRedirect(
        'Country is required.',
        $systemname
    );
}


/*
|--------------------------------------------------------------------------
| DOB Validation
|--------------------------------------------------------------------------
*/

$newdob = null;

if ($dob !== '') {

    $dateObject = DateTime::createFromFormat(
        'Y-m-d',
        $dob
    );

    if (
        !$dateObject ||
        $dateObject->format('Y-m-d') !== $dob
    ) {
        setErrorAndRedirect(
            'Invalid date of birth format.',
            $systemname
        );
    }

    $newdob = $dob;
}


/*
|--------------------------------------------------------------------------
| Role-Specific Organisation / Academic Fields
|--------------------------------------------------------------------------
|
| Admin:
|   branch       optional
|   class        optional
|   course       optional
|   intake       optional
|
| Student / Instructor:
|   branch       required
|   class        required
|   course       required
|   intake       required
|
|--------------------------------------------------------------------------
*/

$requiresAcademicFields = in_array(
    $role,
    ['student', 'instructor'],
    true
);


/*
|--------------------------------------------------------------------------
| Initialise Optional Fields
|--------------------------------------------------------------------------
*/

$branchId   = null;
$branchName = '';

$classTypeId = null;

$courseName = '';
$courseId   = null;

$intakeId   = null;
$intakeName = '';


/*
|--------------------------------------------------------------------------
| Read Fields Only If Supplied
|--------------------------------------------------------------------------
*/

if (
    isset($_POST['branch_id']) &&
    $_POST['branch_id'] !== '' &&
    is_numeric($_POST['branch_id'])
) {
    $branchId = (int)$_POST['branch_id'];
}


if (
    isset($_POST['class_type_id']) &&
    $_POST['class_type_id'] !== '' &&
    is_numeric($_POST['class_type_id'])
) {
    $classTypeId = (int)$_POST['class_type_id'];
}


$courseName = cleanText(
    $_POST['course_name'] ?? ''
);


/*
|--------------------------------------------------------------------------
| IMPORTANT:
|
| The form field is called "intake".
|
| We also support the old "course_intake" field temporarily so older forms
| do not break.
|--------------------------------------------------------------------------
*/

$postedIntake = $_POST['intake']
    ?? $_POST['course_intake']
    ?? null;

if (
    $postedIntake !== null &&
    $postedIntake !== '' &&
    is_numeric($postedIntake)
) {
    $intakeId = (int)$postedIntake;
}


/*
|--------------------------------------------------------------------------
| Student / Instructor Validation
|--------------------------------------------------------------------------
*/

if ($requiresAcademicFields) {

    if (!$branchId) {
        setErrorAndRedirect(
            'Please select a branch.',
            $systemname
        );
    }

    if (!$classTypeId) {
        setErrorAndRedirect(
            'Please select a class type.',
            $systemname
        );
    }

    if ($courseName === '') {
        setErrorAndRedirect(
            'Please select a course.',
            $systemname
        );
    }

    if (!$intakeId) {
        setErrorAndRedirect(
            'Please select an intake.',
            $systemname
        );
    }
}


/*
|--------------------------------------------------------------------------
| Admin / Accountant
|--------------------------------------------------------------------------
|
| These account types do not need academic details.
|
|--------------------------------------------------------------------------
*/

if (in_array($role, ['admin', 'accountant'], true)) {

    $branchId    = null;
    $branchName  = '';

    $classTypeId = null;

    $courseName  = '';
    $courseId    = null;

    $intakeId    = null;
    $intakeName  = '';
}


/*
|--------------------------------------------------------------------------
| Validate Branch
|--------------------------------------------------------------------------
|
| Do NOT run this for Admin.
|
|--------------------------------------------------------------------------
*/

if ($requiresAcademicFields && $branchId !== null) {

    $branchStmt = $conn->prepare(
        "
        SELECT
            id,
            name
        FROM branches
        WHERE id = ?
        LIMIT 1
        "
    );

    if (!$branchStmt) {
        setErrorAndRedirect(
            'Unable to validate the selected branch.',
            $systemname
        );
    }

    $branchStmt->bind_param(
        'i',
        $branchId
    );

    if (!$branchStmt->execute()) {

        $branchStmt->close();

        setErrorAndRedirect(
            'Unable to validate the selected branch.',
            $systemname
        );
    }

    $branchResult = $branchStmt->get_result();

    if ($branchResult->num_rows === 0) {

        $branchStmt->close();

        setErrorAndRedirect(
            'The selected branch could not be found.',
            $systemname
        );
    }

    $branchData = $branchResult->fetch_assoc();

    $branchName = (string)$branchData['name'];

    $branchStmt->close();
}


/*
|--------------------------------------------------------------------------
| Validate Class Type
|--------------------------------------------------------------------------
*/

if ($requiresAcademicFields && $classTypeId !== null) {

    $classStmt = $conn->prepare(
        "
        SELECT
            id,
            name
        FROM class_type
        WHERE id = ?
        AND status = 'Active'
        LIMIT 1
        "
    );

    if (!$classStmt) {
        setErrorAndRedirect(
            'Unable to validate the selected class type.',
            $systemname
        );
    }

    $classStmt->bind_param(
        'i',
        $classTypeId
    );

    if (!$classStmt->execute()) {

        $classStmt->close();

        setErrorAndRedirect(
            'Unable to validate the selected class type.',
            $systemname
        );
    }

    $classResult = $classStmt->get_result();

    if ($classResult->num_rows === 0) {

        $classStmt->close();

        setErrorAndRedirect(
            'The selected class type was not found or is inactive.',
            $systemname
        );
    }

    $classStmt->close();
}


/*
|--------------------------------------------------------------------------
| Validate Course
|--------------------------------------------------------------------------
*/

if ($requiresAcademicFields && $courseName !== '') {

    $courseStmt = $conn->prepare(
        "
        SELECT
            id,
            name
        FROM courses
        WHERE name = ?
        LIMIT 1
        "
    );

    if (!$courseStmt) {
        setErrorAndRedirect(
            'Unable to validate the selected course.',
            $systemname
        );
    }

    $courseStmt->bind_param(
        's',
        $courseName
    );

    if (!$courseStmt->execute()) {

        $courseStmt->close();

        setErrorAndRedirect(
            'Unable to validate the selected course.',
            $systemname
        );
    }

    $courseResult = $courseStmt->get_result();

    if ($courseResult->num_rows === 0) {

        $courseStmt->close();

        setErrorAndRedirect(
            "Selected course '{$courseName}' was not found.",
            $systemname
        );
    }

    $courseData = $courseResult->fetch_assoc();

    $courseId   = (int)$courseData['id'];
    $courseName = (string)$courseData['name'];

    $courseStmt->close();
}


/*
|--------------------------------------------------------------------------
| Validate Intake
|--------------------------------------------------------------------------
*/

if ($requiresAcademicFields && $intakeId !== null) {

    $intakeStmt = $conn->prepare(
        "
        SELECT
            id,
            name
        FROM intakes
        WHERE id = ?
        LIMIT 1
        "
    );

    if (!$intakeStmt) {
        setErrorAndRedirect(
            'Unable to validate the selected intake.',
            $systemname
        );
    }

    $intakeStmt->bind_param(
        'i',
        $intakeId
    );

    if (!$intakeStmt->execute()) {

        $intakeStmt->close();

        setErrorAndRedirect(
            'Unable to validate the selected intake.',
            $systemname
        );
    }

    $intakeResult = $intakeStmt->get_result();

    if ($intakeResult->num_rows === 0) {

        $intakeStmt->close();

        setErrorAndRedirect(
            'The selected intake could not be found.',
            $systemname
        );
    }

    $intakeData = $intakeResult->fetch_assoc();

    $intakeId   = (int)$intakeData['id'];
    $intakeName = (string)$intakeData['name'];

    $intakeStmt->close();
}


/*
|--------------------------------------------------------------------------
| Account Type
|--------------------------------------------------------------------------
*/

$stdnumber = null;

switch ($role) {

    case 'student':

        $actype = 'afgezt';

        break;


    case 'instructor':

        $actype = 'xtfbry';

        break;


    case 'accountant':

        $actype = 'zyffwq';

        break;


    case 'admin':

        $actype = 'xybrtw';

        break;


    default:

        setErrorAndRedirect(
            'Invalid account type.',
            $systemname
        );
}


/*
|--------------------------------------------------------------------------
| Check Existing Email
|--------------------------------------------------------------------------
*/

$emailCheckStmt = $conn->prepare(
    "
    SELECT id
    FROM users
    WHERE LOWER(email) = LOWER(?)
    LIMIT 1
    "
);

if (!$emailCheckStmt) {
    setErrorAndRedirect(
        'Unable to check whether this email already exists.',
        $systemname
    );
}

$emailCheckStmt->bind_param(
    's',
    $email
);

if (!$emailCheckStmt->execute()) {

    $emailCheckStmt->close();

    setErrorAndRedirect(
        'Unable to check the email address.',
        $systemname
    );
}

$emailCheckResult = $emailCheckStmt->get_result();

if ($emailCheckResult->num_rows > 0) {

    $emailCheckStmt->close();

    setErrorAndRedirect(
        "An account with email '{$email}' already exists.",
        $systemname
    );
}

$emailCheckStmt->close();


/*
|--------------------------------------------------------------------------
| Generate Student Number
|--------------------------------------------------------------------------
*/

if ($role === 'student') {

    $year      = date('y');
    $stdPrefix = 'DE';

    $studentCheckStmt = $conn->prepare(
        "
        SELECT id
        FROM users
        WHERE student_no = ?
        LIMIT 1
        "
    );

    if (!$studentCheckStmt) {
        setErrorAndRedirect(
            'Unable to generate a student number.',
            $systemname
        );
    }

    $attempts = 0;

    do {

        $number = random_int(
            10000,
            99999
        );

        $stdnumber =
            $year .
            '/' .
            $stdPrefix .
            '/' .
            $number;

        $studentCheckStmt->bind_param(
            's',
            $stdnumber
        );

        if (!$studentCheckStmt->execute()) {

            $studentCheckStmt->close();

            setErrorAndRedirect(
                'Unable to generate a student number.',
                $systemname
            );
        }

        $studentCheckResult =
            $studentCheckStmt->get_result();

        $attempts++;

        if ($attempts >= 100) {

            $studentCheckStmt->close();

            setErrorAndRedirect(
                'Unable to generate a unique student number. Please try again.',
                $systemname
            );
        }

    } while ($studentCheckResult->num_rows > 0);

    $studentCheckStmt->close();
}


/*
|--------------------------------------------------------------------------
| Generate Unique User ID
|--------------------------------------------------------------------------
*/

$unique = '';

$uniqueCheckStmt = $conn->prepare(
    "
    SELECT id
    FROM users
    WHERE unique_id = ?
    LIMIT 1
    "
);

if (!$uniqueCheckStmt) {
    setErrorAndRedirect(
        'Unable to generate a unique user ID.',
        $systemname
    );
}

$uniqueAttempts = 0;

do {

    $unique = generateFormattedUserId();

    $uniqueCheckStmt->bind_param(
        's',
        $unique
    );

    if (!$uniqueCheckStmt->execute()) {

        $uniqueCheckStmt->close();

        setErrorAndRedirect(
            'Unable to generate a unique user ID.',
            $systemname
        );
    }

    $uniqueResult =
        $uniqueCheckStmt->get_result();

    $uniqueAttempts++;

    if ($uniqueAttempts >= 100) {

        $uniqueCheckStmt->close();

        setErrorAndRedirect(
            'Unable to generate a unique user ID. Please try again.',
            $systemname
        );
    }

} while ($uniqueResult->num_rows > 0);

$uniqueCheckStmt->close();


/*
|--------------------------------------------------------------------------
| Generate Password
|--------------------------------------------------------------------------
|
| Keep the existing MD5 + salt format because the existing eLearning
| authentication system appears to depend on it.
|
| The unified auth publisher below additionally receives a modern
| password_hash() value.
|
|--------------------------------------------------------------------------
*/

$password = generateSecureRandomString(8);
$salt     = generateSecureRandomString(5);
$verify   = generateSecureRandomString(5);

$newpassword = md5(
    $salt . $password
);


/*
|--------------------------------------------------------------------------
| Created By
|--------------------------------------------------------------------------
*/

$defaultCreatedBy = '0o5ppyn7mb';

$createdBy = $_SESSION[$systemname . 'leido']
    ?? $defaultCreatedBy;

$createdBy = (string)$createdBy;


/*
|--------------------------------------------------------------------------
| Start Transaction
|--------------------------------------------------------------------------
*/

if (!$conn->begin_transaction()) {
    setErrorAndRedirect(
        'Unable to start user registration.',
        $systemname
    );
}


try {

    /*
    |--------------------------------------------------------------------------
    | Insert User
    |--------------------------------------------------------------------------
    */

    $sql = "
        INSERT INTO users
        (
            fname,
            lname,
            other_name,
            email,
            salt,
            password,
            dp,
            verified,
            unique_id,
            acc_type,
            created_by,
            date_created,
            country,
            is_pwd,
            dob,
            student_no,
            gender,
            contact,
            class_type_id
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException(
            'Unable to prepare the user registration.'
        );
    }

    $dp = 'default.jpg';


    /*
    |--------------------------------------------------------------------------
    | Important:
    |
    | $classTypeId will be NULL for Admin.
    |
    |--------------------------------------------------------------------------
    */

    $stmt->bind_param(
        'sssssssssssssissssi',
        $fname,
        $lname,
        $oname,
        $email,
        $salt,
        $newpassword,
        $dp,
        $verify,
        $unique,
        $actype,
        $createdBy,
        $nowdate,
        $country,
        $is_pwd,
        $newdob,
        $stdnumber,
        $gender,
        $contact,
        $classTypeId
    );


    if (!$stmt->execute()) {

        $error = $stmt->error;

        $stmt->close();

        throw new RuntimeException(
            'User registration failed: ' . $error
        );
    }


    $userDatabaseId = (int)$conn->insert_id;

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Student Course Registration
    |--------------------------------------------------------------------------
    |
    | Only STUDENTS are enrolled automatically.
    |
    | Admin does not enter this block.
    | Instructor does not enter this block.
    |
    |--------------------------------------------------------------------------
    */

    $courseRegistrationSuccess = false;


    if (
        $role === 'student' &&
        $courseId !== null
    ) {

        $addedBy   = $createdBy;
        $dateAdded = $nowdate;


        /*
        |--------------------------------------------------------------------------
        | Main Selected Course
        |--------------------------------------------------------------------------
        */

        if ($intakeId !== null) {

            $courseRegistrationStmt = $conn->prepare(
                "
                INSERT INTO course_registration
                (
                    course_id,
                    user_id,
                    date_added,
                    added_by,
                    intake
                )
                VALUES (?, ?, ?, ?, ?)
                "
            );

            if (!$courseRegistrationStmt) {
                throw new RuntimeException(
                    'Unable to prepare course registration.'
                );
            }

            $courseRegistrationStmt->bind_param(
                'isssi',
                $courseId,
                $unique,
                $dateAdded,
                $addedBy,
                $intakeId
            );

        } else {

            $courseRegistrationStmt = $conn->prepare(
                "
                INSERT INTO course_registration
                (
                    course_id,
                    user_id,
                    date_added,
                    added_by
                )
                VALUES (?, ?, ?, ?)
                "
            );

            if (!$courseRegistrationStmt) {
                throw new RuntimeException(
                    'Unable to prepare course registration.'
                );
            }

            $courseRegistrationStmt->bind_param(
                'isss',
                $courseId,
                $unique,
                $dateAdded,
                $addedBy
            );
        }


        if (!$courseRegistrationStmt->execute()) {

            $courseError =
                $courseRegistrationStmt->error;

            $courseRegistrationStmt->close();

            throw new RuntimeException(
                'Course registration failed: ' .
                $courseError
            );
        }

        $courseRegistrationStmt->close();


        /*
        |--------------------------------------------------------------------------
        | Default Courses
        |--------------------------------------------------------------------------
        */

        $defaultCourses = [
            4,
            7
        ];


        if ($intakeId !== null) {

            $defaultCourseStmt = $conn->prepare(
                "
                INSERT IGNORE INTO course_registration
                (
                    course_id,
                    user_id,
                    date_added,
                    added_by,
                    intake
                )
                VALUES (?, ?, ?, ?, ?)
                "
            );

            if (!$defaultCourseStmt) {
                throw new RuntimeException(
                    'Unable to prepare default course registration.'
                );
            }


            foreach ($defaultCourses as $defaultCourseId) {

                /*
                 * Avoid attempting to register the same course twice.
                 */
                if ($defaultCourseId === $courseId) {
                    continue;
                }

                $defaultCourseStmt->bind_param(
                    'isssi',
                    $defaultCourseId,
                    $unique,
                    $dateAdded,
                    $addedBy,
                    $intakeId
                );

                if (!$defaultCourseStmt->execute()) {

                    $defaultError =
                        $defaultCourseStmt->error;

                    $defaultCourseStmt->close();

                    throw new RuntimeException(
                        "Unable to assign default course {$defaultCourseId}: " .
                        $defaultError
                    );
                }
            }


            $defaultCourseStmt->close();

        } else {

            $defaultCourseStmt = $conn->prepare(
                "
                INSERT IGNORE INTO course_registration
                (
                    course_id,
                    user_id,
                    date_added,
                    added_by
                )
                VALUES (?, ?, ?, ?)
                "
            );

            if (!$defaultCourseStmt) {
                throw new RuntimeException(
                    'Unable to prepare default course registration.'
                );
            }


            foreach ($defaultCourses as $defaultCourseId) {

                if ($defaultCourseId === $courseId) {
                    continue;
                }

                $defaultCourseStmt->bind_param(
                    'isss',
                    $defaultCourseId,
                    $unique,
                    $dateAdded,
                    $addedBy
                );

                if (!$defaultCourseStmt->execute()) {

                    $defaultError =
                        $defaultCourseStmt->error;

                    $defaultCourseStmt->close();

                    throw new RuntimeException(
                        "Unable to assign default course {$defaultCourseId}: " .
                        $defaultError
                    );
                }
            }


            $defaultCourseStmt->close();
        }


        $courseRegistrationSuccess = true;
    }


    /*
    |--------------------------------------------------------------------------
    | Commit Main Registration
    |--------------------------------------------------------------------------
    */

    if (!$conn->commit()) {
        throw new RuntimeException(
            'Unable to save the new user.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Publish User To Unified Authentication
    |--------------------------------------------------------------------------
    |
    | Do this after successful local registration.
    |
    |--------------------------------------------------------------------------
    */

    try {

        $publishPasswordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        if ($publishPasswordHash !== false) {

            eh_auth_publish_user(
                [
                    'email'         => $email,
                    'fname'         => $fname,
                    'lname'         => $lname,
                    'password_hash' => $publishPasswordHash,
                    'source'        => 'elearning',
                ],
                $conn
            );

        } else {

            error_log(
                '[elearning-auth-publish] password_hash failed after signup.'
            );
        }

    } catch (Throwable $publishException) {

        /*
         * Do not undo the successfully-created local account because
         * unified-auth publishing failed.
         */

        error_log(
            '[elearning-auth-publish] ' .
            $publishException->getMessage()
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Prepare Email Information
    |--------------------------------------------------------------------------
    */

    $userData = [
        'email'      => $email,
        'fname'      => $fname,
        'lname'      => $lname,
        'role'       => $role,
        'student_no' => $stdnumber,
    ];


    $emailCourseData = null;


    if (
        $role === 'student' &&
        $courseRegistrationSuccess &&
        $courseName !== ''
    ) {

        $emailCourseData = [
            'course_name' => $courseName,
            'intake_name' => $intakeName,
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Send Account Creation Email
    |--------------------------------------------------------------------------
    */

    $emailSent = false;


    try {

        $emailSent =
            $emailService->sendAccountCreationEmail(
                $userData,
                $password,
                $verify,
                $emailCourseData
            );

    } catch (Throwable $emailException) {

        error_log(
            'Account creation email failed for ' .
            $email .
            ': ' .
            $emailException->getMessage()
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Success Message
    |--------------------------------------------------------------------------
    */

    $displayRole = ucfirst($role);

    $successMessage =
        "{$displayRole} account for {$fname} {$lname} has been created successfully.";


    /*
    |--------------------------------------------------------------------------
    | Student-Specific Success Information
    |--------------------------------------------------------------------------
    */

    if (
        $role === 'student' &&
        $stdnumber !== null
    ) {

        $successMessage .=
            " Student number: {$stdnumber}.";


        if (
            $courseRegistrationSuccess &&
            $courseName !== ''
        ) {

            $successMessage .=
                " Enrolled in {$courseName}.";


            if ($intakeName !== '') {

                $successMessage .=
                    " Intake: {$intakeName}.";
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Admin Success
    |--------------------------------------------------------------------------
    */

    if ($role === 'admin') {

        $successMessage .=
            ' No branch, class, course or intake assignment is required.';
    }


    /*
    |--------------------------------------------------------------------------
    | Email Result
    |--------------------------------------------------------------------------
    */

    if ($emailSent) {

        $successMessage .=
            " Login credentials have been sent to {$email}.";

    } else {

        $successMessage .=
            ' The account was created, but the email notification could not be sent. Please provide the login credentials manually.';
    }


    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    setSuccessAndRedirect(
        $successMessage,
        $systemname
    );


} catch (Throwable $exception) {

    /*
    |--------------------------------------------------------------------------
    | Rollback Only If Transaction Is Still Active
    |--------------------------------------------------------------------------
    */

    try {
        $conn->rollback();
    } catch (Throwable $rollbackException) {
        error_log(
            'Registration rollback error: ' .
            $rollbackException->getMessage()
        );
    }


    error_log(
        'User registration error: ' .
        $exception->getMessage()
    );


    setErrorAndRedirect(
        'Registration failed: ' .
        $exception->getMessage(),
        $systemname
    );


} finally {

    if (
        isset($conn) &&
        $conn instanceof mysqli
    ) {
        $conn->close();
    }
}