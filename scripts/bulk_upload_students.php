<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Bulk student upload (admins only)
|--------------------------------------------------------------------------
| GET  ?action=template   -> downloads the CSV template
| GET  ?action=reference  -> downloads course codes and intake IDs to use in the CSV
| POST (multipart)        -> imports students from an uploaded CSV
|      fields: csv_file, course_id, intake_id (defaults, optional),
|              class_type_id, send_emails (0/1), csrf_token
|
| Each row may set its own `course` (ID, short code or exact name) and
| `intake` (ID, or an exact name that is unique). Blank cells fall back to
| the course/intake chosen on the upload form.
|
| Every row ends with the student registered in course_registration:
|   - New email: created the same way as scripts/create_user.php (users row,
|     inactive until the student verifies their email; unified-auth publish;
|     account-creation email). The account is rolled back unless every course
|     registration succeeds.
|   - Existing student email: registered in the row's course + intake if not
|     already (so one student can appear on several rows, one per course).
|   - Existing staff email: skipped.
|   In every case the default courses 4 and 7 are added for the same intake.
*/

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/connector.php';
require_once __DIR__ . '/EmailService.php';
require_once __DIR__ . '/auth_publish.php';

date_default_timezone_set('Africa/Nairobi');

$systemname = $systemname ?? 'Elevate Her 360';
$adminId    = (string)($_SESSION[$systemname . 'leido'] ?? '');
$role       = $adminId !== '' ? (string)($_SESSION[$systemname . 'letype'] ?? '') : '';

const BU_MAX_ROWS      = 1000;
const BU_MAX_BYTES     = 2 * 1024 * 1024; // 2 MB
const BU_DEFAULT_COURSES = [4, 7];

/** Template columns, in order. */
const BU_COLUMNS = ['first_name', 'other_name', 'last_name', 'email', 'phone', 'gender', 'country', 'dob', 'is_pwd', 'course', 'intake'];

/** Accepted header spellings for each column. */
const BU_ALIASES = [
    'first_name' => ['first_name', 'firstname', 'first name', 'fname'],
    'other_name' => ['other_name', 'othername', 'other name', 'other_names', 'middle_name', 'middle name', 'oname'],
    'last_name'  => ['last_name', 'lastname', 'last name', 'surname', 'lname'],
    'email'      => ['email', 'email address', 'e-mail'],
    'phone'      => ['phone', 'contact', 'phone number', 'mobile', 'telephone'],
    'gender'     => ['gender', 'sex'],
    'country'    => ['country', 'nationality'],
    'dob'        => ['dob', 'date_of_birth', 'date of birth', 'birth date'],
    'is_pwd'     => ['is_pwd', 'pwd', 'disability', 'person with disability'],
    'course'     => ['course', 'course_code', 'course code', 'course_name', 'course name', 'course_id'],
    'intake'     => ['intake', 'intake_id', 'intake id', 'intake_name', 'intake name'],
];

/** @return array<int, array{id:int,name:string,short_code:string}> keyed by id */
function bu_load_courses(mysqli $conn): array
{
    $out = [];
    $res = $conn->query("SELECT id, name, short_code FROM courses ORDER BY name ASC");
    if ($res) while ($r = $res->fetch_assoc()) {
        $out[(int)$r['id']] = ['id' => (int)$r['id'], 'name' => (string)$r['name'], 'short_code' => (string)($r['short_code'] ?? '')];
    }
    return $out;
}

/** @return array<int, array{id:int,name:string,branch:string,starts:string,ends:string}> keyed by id */
function bu_load_intakes(mysqli $conn): array
{
    $out = [];
    $res = $conn->query("
        SELECT i.id, i.name, i.date_launching, i.date_ending, b.name AS branch_name
        FROM intakes i
        LEFT JOIN branches b ON b.id = i.branch_id
        ORDER BY i.date_launching DESC
    ");
    if ($res) while ($r = $res->fetch_assoc()) {
        $out[(int)$r['id']] = [
            'id'     => (int)$r['id'],
            'name'   => (string)$r['name'],
            'branch' => (string)($r['branch_name'] ?? ''),
            'starts' => (string)($r['date_launching'] ?? ''),
            'ends'   => (string)($r['date_ending'] ?? ''),
        ];
    }
    return $out;
}

/**
 * Match a course by ID, short code or exact name (case-insensitive).
 * @return array{0:?array,1:string} [course, error]
 */
function bu_match_course(string $value, array $courses): array
{
    if (ctype_digit($value)) {
        return isset($courses[(int)$value]) ? [$courses[(int)$value], ''] : [null, "Course ID {$value} was not found."];
    }
    $v = mb_strtolower($value);
    foreach ($courses as $c) {
        if ($c['short_code'] !== '' && mb_strtolower($c['short_code']) === $v) return [$c, ''];
    }
    foreach ($courses as $c) {
        if (mb_strtolower($c['name']) === $v) return [$c, ''];
    }
    return [null, "Course '{$value}' was not found. Use the course ID or short code from the reference file."];
}

/**
 * Match an intake by ID, or by exact name when only one intake has that name.
 * @return array{0:?array,1:string} [intake, error]
 */
function bu_match_intake(string $value, array $intakes): array
{
    if (ctype_digit($value)) {
        return isset($intakes[(int)$value]) ? [$intakes[(int)$value], ''] : [null, "Intake ID {$value} was not found."];
    }
    $v = mb_strtolower($value);
    $hits = array_values(array_filter($intakes, static fn($i) => mb_strtolower($i['name']) === $v));
    if (count($hits) === 1) return [$hits[0], ''];
    if (count($hits) > 1) {
        return [null, "More than one intake is named '{$value}'. Use the intake ID (" . implode(', ', array_column($hits, 'id')) . ').'];
    }
    return [null, "Intake '{$value}' was not found. Use the intake ID from the reference file."];
}

function bu_json(int $code, array $payload): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function bu_random(int $length): string
{
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $out = '';
    for ($i = 0; $i < $length; $i++) $out .= $chars[random_int(0, strlen($chars) - 1)];
    return $out;
}

function bu_clean($v): string
{
    $v = trim(strip_tags((string)($v ?? '')));
    return trim((string)preg_replace('/\s+/u', ' ', $v));
}

function bu_to_utf8(string $v): string
{
    if ($v === '' || mb_check_encoding($v, 'UTF-8')) return $v;
    return mb_convert_encoding($v, 'UTF-8', 'Windows-1252');
}

/** Accepts YYYY-MM-DD, DD/MM/YYYY and DD-MM-YYYY. Returns Y-m-d or null; false when invalid. */
function bu_parse_date(string $v): string|false|null
{
    if ($v === '') return null;
    foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $fmt) {
        $d = DateTime::createFromFormat('!' . $fmt, $v);
        if ($d && $d->format($fmt) === $v) {
            $year = (int)$d->format('Y');
            if ($year < 1900 || $d > new DateTime()) return false;
            return $d->format('Y-m-d');
        }
    }
    return false;
}

function bu_gender(string $v): string
{
    $k = strtolower($v);
    return match (true) {
        in_array($k, ['m', 'male'], true)   => 'Male',
        in_array($k, ['f', 'female'], true) => 'Female',
        default                             => $v,
    };
}

function bu_truthy(string $v): int
{
    return in_array(strtolower($v), ['1', 'yes', 'y', 'true'], true) ? 1 : 0;
}

/* ======================================================================
   Access control
   ====================================================================== */
if (!in_array($role, ['sabrtw', 'xybrtw'], true)) {
    if (in_array($_GET['action'] ?? '', ['template', 'reference'], true)) {
        http_response_code(403);
        exit('Only administrators can download student upload files.');
    }
    bu_json(403, ['status' => 'error', 'message' => 'Only administrators can upload students.']);
}

/* ======================================================================
   Template download
   ====================================================================== */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && ($_GET['action'] ?? '') === 'template') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="student_upload_template.csv"');
    header('Cache-Control: no-store');

    // Use a real course code and intake ID in the example row so it is valid as-is.
    $exampleCourse = '';
    $exampleIntake = '';
    if (isset($conn) && $conn instanceof mysqli) {
        foreach (bu_load_courses($conn) as $c) {
            if (in_array($c['id'], BU_DEFAULT_COURSES, true)) continue;
            $exampleCourse = $c['short_code'] !== '' ? $c['short_code'] : (string)$c['id'];
            break;
        }
        $firstIntake   = array_key_first(bu_load_intakes($conn));
        $exampleIntake = $firstIntake !== null ? (string)$firstIntake : '';
    }

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel opens it as UTF-8
    fputcsv($out, BU_COLUMNS);
    fputcsv($out, ['Mary', 'Wanjiru', 'Kamau', 'mary.kamau@example.com', '+254712345678', 'Female', 'Kenya', '1998-04-21', 'no', $exampleCourse, $exampleIntake]);
    fclose($out);
    exit;
}

/* ======================================================================
   Reference download: course codes and intake IDs for the CSV
   ====================================================================== */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && ($_GET['action'] ?? '') === 'reference') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="courses_and_intakes_reference.csv"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, ['COURSES - put the ID or short code in the "course" column']);
    fputcsv($out, ['course ID', 'short code', 'course name']);
    foreach (bu_load_courses($conn) as $c) {
        fputcsv($out, [$c['id'], $c['short_code'], $c['name']]);
    }

    fputcsv($out, []);
    fputcsv($out, ['INTAKES - put the ID in the "intake" column']);
    fputcsv($out, ['intake ID', 'intake name', 'branch', 'starts', 'ends']);
    foreach (bu_load_intakes($conn) as $i) {
        fputcsv($out, [
            $i['id'], $i['name'], $i['branch'],
            $i['starts'] !== '' ? date('Y-m-d', strtotime($i['starts'])) : '',
            $i['ends']   !== '' ? date('Y-m-d', strtotime($i['ends']))   : '',
        ]);
    }
    fclose($out);
    exit;
}

/* ======================================================================
   Upload
   ====================================================================== */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    bu_json(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

$csrf = (string)($_POST['csrf_token'] ?? '');
if ($csrf === '' || empty($_SESSION['bulk_upload_csrf']) || !hash_equals((string)$_SESSION['bulk_upload_csrf'], $csrf)) {
    bu_json(419, ['status' => 'error', 'message' => 'Your session has expired. Refresh the page and try again.']);
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    bu_json(500, ['status' => 'error', 'message' => 'Database connection not found.']);
}
$conn->set_charset('utf8mb4');

$courseId    = (int)($_POST['course_id'] ?? 0);
$intakeId    = (int)($_POST['intake_id'] ?? 0);
$classTypeId = (int)($_POST['class_type_id'] ?? 0);
$sendEmails  = ($_POST['send_emails'] ?? '1') === '1';

/* -- Default course / intake (used when a row leaves them blank) ------ */
$allCourses = bu_load_courses($conn);
$allIntakes = bu_load_intakes($conn);

$defaultCourse = null;
if ($courseId > 0) {
    $defaultCourse = $allCourses[$courseId] ?? null;
    if (!$defaultCourse) bu_json(422, ['status' => 'error', 'message' => 'The selected default course was not found.']);
}

$defaultIntake = null;
if ($intakeId > 0) {
    $defaultIntake = $allIntakes[$intakeId] ?? null;
    if (!$defaultIntake) bu_json(422, ['status' => 'error', 'message' => 'The selected default intake was not found.']);
}

/* -- Class type (applies to every row) ------------------------------- */
$stmt = $conn->prepare("SELECT id FROM class_type WHERE id = ? AND status = 'Active' LIMIT 1");
$stmt->bind_param('i', $classTypeId);
$stmt->execute();
$classOk = (bool)$stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$classOk) bu_json(422, ['status' => 'error', 'message' => 'Please select an active class type.']);

/* -- Validate the file ------------------------------------------------ */
$file = $_FILES['csv_file'] ?? null;
if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
    bu_json(422, ['status' => 'error', 'message' => 'Please choose a CSV file to upload.']);
}
if ((int)$file['size'] > BU_MAX_BYTES) {
    bu_json(422, ['status' => 'error', 'message' => 'The file is too large (maximum 2 MB).']);
}
if (strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION)) !== 'csv') {
    bu_json(422, ['status' => 'error', 'message' => 'Only .csv files are accepted. In Excel use File > Save As > CSV.']);
}

$fh = fopen($file['tmp_name'], 'r');
if (!$fh) bu_json(500, ['status' => 'error', 'message' => 'Could not read the uploaded file.']);

// Detect delimiter (Excel in some locales saves with semicolons).
$firstLine = (string)fgets($fh);
rewind($fh);
$delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

$header = fgetcsv($fh, 0, $delimiter);
if (!$header) bu_json(422, ['status' => 'error', 'message' => 'The file is empty.']);

$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header[0]);

$colIndex = [];
foreach ($header as $i => $h) {
    $h = strtolower(trim((string)$h));
    foreach (BU_ALIASES as $key => $aliases) {
        if (!isset($colIndex[$key]) && in_array($h, $aliases, true)) $colIndex[$key] = $i;
    }
}

$missing = array_diff(['first_name', 'last_name', 'email', 'phone'], array_keys($colIndex));
if ($missing) {
    bu_json(422, [
        'status'  => 'error',
        'message' => 'Missing required column(s): ' . implode(', ', $missing) . '. Download the template to see the expected columns.',
    ]);
}

$rows = [];
$line = 1;
while (($data = fgetcsv($fh, 0, $delimiter)) !== false) {
    $line++;
    if (count(array_filter($data, static fn($c) => trim((string)$c) !== '')) === 0) continue; // blank line
    $row = ['_line' => $line];
    foreach (BU_COLUMNS as $key) {
        $row[$key] = isset($colIndex[$key]) ? bu_clean(bu_to_utf8((string)($data[$colIndex[$key]] ?? ''))) : '';
    }
    $rows[] = $row;
    if (count($rows) > BU_MAX_ROWS) {
        fclose($fh);
        bu_json(422, ['status' => 'error', 'message' => 'Too many rows. Upload at most ' . BU_MAX_ROWS . ' students at a time.']);
    }
}
fclose($fh);

if (!$rows) bu_json(422, ['status' => 'error', 'message' => 'The file has no student rows.']);

/* ======================================================================
   Import
   ====================================================================== */
@set_time_limit(0);

$emailService = null;
if ($sendEmails) {
    try {
        $emailService = new EmailService();
    } catch (Throwable $e) {
        error_log('[bulk-upload] EmailService init failed: ' . $e->getMessage());
    }
}

$createdBy = $adminId !== '' ? $adminId : '0o5ppyn7mb';
$now       = date('Y-m-d H:i:s');
$dp        = 'default.jpg';
$actype    = 'afgezt';

$findUser    = $conn->prepare("
    SELECT unique_id, acc_type, fname, other_name, lname, student_no
    FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1
");
$stdnoCheck  = $conn->prepare("SELECT id FROM users WHERE student_no = ? LIMIT 1");
$uniqueCheck = $conn->prepare("SELECT id FROM users WHERE unique_id = ? LIMIT 1");
$insertUser  = $conn->prepare("
    INSERT INTO users
        (fname, lname, other_name, email, salt, password, dp, verified, unique_id, acc_type,
         created_by, date_created, country, is_pwd, dob, student_no, gender, contact, class_type_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$regExists   = $conn->prepare("
    SELECT id FROM course_registration
    WHERE user_id = ? AND course_id = ? AND intake = ?
    LIMIT 1
");
$insertReg   = $conn->prepare("
    INSERT INTO course_registration (course_id, user_id, date_added, added_by, intake)
    VALUES (?, ?, ?, ?, ?)
");

if (!$findUser || !$stdnoCheck || !$uniqueCheck || !$insertUser || !$regExists || !$insertReg) {
    bu_json(500, ['status' => 'error', 'message' => 'Could not prepare the import: ' . $conn->error]);
}

$exists = static function (mysqli_stmt $stmt, string $value): bool {
    $stmt->bind_param('s', $value);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
};

/**
 * Register a student in a course + intake in course_registration.
 * Returns true when a row was inserted, false when it already existed.
 * Throws if the insert does not produce exactly one row, so the caller's
 * transaction is rolled back and the row is reported as failed.
 */
$register = static function (int $courseId, string $userId, int $intakeId) use ($regExists, $insertReg, $now, $createdBy): bool {
    $regExists->bind_param('sii', $userId, $courseId, $intakeId);
    if (!$regExists->execute()) throw new RuntimeException($regExists->error);
    if ($regExists->get_result()->fetch_assoc()) return false;

    $insertReg->bind_param('isssi', $courseId, $userId, $now, $createdBy, $intakeId);
    if (!$insertReg->execute() || $insertReg->affected_rows !== 1) {
        throw new RuntimeException("Course registration (course {$courseId}) failed: " . $insertReg->error);
    }
    return true;
};

$results    = [];
$created    = 0;
$registered = 0; // existing students newly registered in a course
$skipped    = 0;
$emailed    = 0;

foreach ($rows as $r) {
    $line   = $r['_line'];
    $email  = strtolower($r['email']);
    $name   = trim($r['first_name'] . ' ' . $r['other_name'] . ' ' . $r['last_name']);
    $result = [
        'line' => $line, 'name' => $name, 'email' => $email, 'student_no' => '',
        'course' => '', 'intake' => '', 'status' => 'skipped', 'message' => '', 'password' => '',
    ];

    /* -- Resolve this row's course and intake (blank = form default) ---- */
    $rowCourse = $defaultCourse;
    $rowIntake = $defaultIntake;
    $courseError = '';
    $intakeError = '';

    if ($r['course'] !== '') {
        [$rowCourse, $courseError] = bu_match_course($r['course'], $allCourses);
    } elseif (!$rowCourse) {
        $courseError = 'No course given. Fill the course column or choose a default course on the form.';
    }

    if ($r['intake'] !== '') {
        [$rowIntake, $intakeError] = bu_match_intake($r['intake'], $allIntakes);
    } elseif (!$rowIntake) {
        $intakeError = 'No intake given. Fill the intake column or choose a default intake on the form.';
    }

    if ($rowCourse) $result['course'] = $rowCourse['name'];
    if ($rowIntake) $result['intake'] = $rowIntake['name'] . ($rowIntake['branch'] !== '' ? ' (' . $rowIntake['branch'] . ')' : '');

    /* -- Email + course/intake are needed for every row ---------------- */
    $error = '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Invalid email address.';
    elseif ($courseError !== '')                     $error = $courseError;
    elseif ($intakeError !== '')                     $error = $intakeError;

    if ($error !== '') {
        $result['message'] = $error;
        $results[] = $result;
        $skipped++;
        continue;
    }

    $rowCourseId = (int)$rowCourse['id'];
    $rowIntakeId = (int)$rowIntake['id'];

    /* -- Existing account: register it in this row's course ------------
       Covers students who already exist and the same student appearing on
       several rows (one per course). */
    $findUser->bind_param('s', $email);
    $findUser->execute();
    $existing = $findUser->get_result()->fetch_assoc();

    if ($existing) {
        $result['student_no'] = (string)($existing['student_no'] ?? '');
        $existingName = trim(implode(' ', array_filter([
            trim((string)$existing['fname']), trim((string)($existing['other_name'] ?? '')), trim((string)$existing['lname']),
        ])));
        if ($existingName !== '') $result['name'] = $existingName;

        if ($existing['acc_type'] !== 'afgezt') {
            $result['message'] = 'This email belongs to a staff account, not a student. Not registered.';
            $results[] = $result;
            $skipped++;
            continue;
        }

        $existingId = (string)$existing['unique_id'];
        try {
            $conn->begin_transaction();
            $added = $register($rowCourseId, $existingId, $rowIntakeId);
            foreach (BU_DEFAULT_COURSES as $defaultCourseId) {
                if ($defaultCourseId !== $rowCourseId) $register($defaultCourseId, $existingId, $rowIntakeId);
            }
            $conn->commit();
        } catch (Throwable $e) {
            try { $conn->rollback(); } catch (Throwable $ignored) {}
            error_log("[bulk-upload] line {$line} ({$email}): " . $e->getMessage());
            $result['message'] = 'Existing student could not be registered. ' . $e->getMessage();
            $results[] = $result;
            $skipped++;
            continue;
        }

        if ($added) {
            $registered++;
            $result['status']  = 'registered';
            $result['message'] = 'Existing student registered in this course and intake.';
        } else {
            $result['status']  = 'already';
            $result['message'] = 'Already registered in this course and intake. Nothing changed.';
        }
        $results[] = $result;
        continue;
    }

    /* -- New student: full validation ------------------------------------ */
    $dob = bu_parse_date($r['dob']);

    if ($r['first_name'] === '')     $error = 'First name is required.';
    elseif ($r['last_name'] === '')  $error = 'Last name is required.';
    elseif ($r['phone'] === '')      $error = 'Phone number is required.';
    elseif ($dob === false)          $error = 'Invalid date of birth (use YYYY-MM-DD).';

    if ($error !== '') {
        $result['message'] = $error;
        $results[] = $result;
        $skipped++;
        continue;
    }

    /* -- Generate identifiers & credentials ---------------------------- */
    do {
        $stdnumber = date('y') . '/DE/' . random_int(10000, 99999);
    } while ($exists($stdnoCheck, $stdnumber));

    do {
        $unique = strtolower(bu_random(10));
    } while ($exists($uniqueCheck, $unique));

    $password    = bu_random(8);
    $salt        = bu_random(5);
    $verify      = bu_random(5); // account stays inactive until the student verifies
    $hashed      = md5($salt . $password); // matches the existing login scheme
    $fname       = $r['first_name'];
    $lname       = $r['last_name'];
    $oname       = $r['other_name'];
    $country     = $r['country'];
    $gender      = bu_gender($r['gender']);
    $contact     = $r['phone'];
    $isPwd       = bu_truthy($r['is_pwd']);
    $dobValue    = $dob ?: null;

    /* -- Insert (one transaction per student) -------------------------- */
    try {
        $conn->begin_transaction();

        $insertUser->bind_param(
            'sssssssssssssissssi',
            $fname, $lname, $oname, $email, $salt, $hashed, $dp, $verify, $unique, $actype,
            $createdBy, $now, $country, $isPwd, $dobValue, $stdnumber, $gender, $contact, $classTypeId
        );
        if (!$insertUser->execute()) throw new RuntimeException($insertUser->error);

        // The student account is only kept if every course registration succeeds.
        $register($rowCourseId, $unique, $rowIntakeId);
        foreach (BU_DEFAULT_COURSES as $defaultCourseId) {
            if ($defaultCourseId !== $rowCourseId) $register($defaultCourseId, $unique, $rowIntakeId);
        }

        $conn->commit();
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        error_log("[bulk-upload] line {$line} ({$email}): " . $e->getMessage());
        $result['message'] = 'Could not save this student. ' . $e->getMessage();
        $results[] = $result;
        $skipped++;
        continue;
    }

    $created++;
    $result['status']     = 'created';
    $result['student_no'] = $stdnumber;

    /* -- Unified auth (failure does not undo the local account) -------- */
    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash !== false) {
            eh_auth_publish_user([
                'email' => $email, 'fname' => $fname, 'lname' => $lname,
                'password_hash' => $hash, 'source' => 'elearning',
            ], $conn);
        }
    } catch (Throwable $e) {
        error_log('[bulk-upload][auth-publish] ' . $e->getMessage());
    }

    /* -- Welcome email --------------------------------------------------- */
    $sent = false;
    if ($emailService !== null) {
        try {
            $sent = (bool)$emailService->sendAccountCreationEmail(
                ['email' => $email, 'fname' => $fname, 'lname' => $lname, 'role' => 'student', 'student_no' => $stdnumber],
                $password,
                $verify,
                ['course_name' => $rowCourse['name'], 'intake_name' => $rowIntake['name']]
            );
        } catch (Throwable $e) {
            error_log("[bulk-upload] email failed for {$email}: " . $e->getMessage());
        }
    }

    if ($sent) {
        $emailed++;
        $result['message'] = 'Created. Login details emailed.';
    } else {
        // Only reveal the temporary password when the student did not receive it by email.
        $result['password'] = $password;
        $result['message']  = $sendEmails
            ? 'Created, but the email could not be sent. Share the temporary password manually.'
            : 'Created. Emails were turned off; share the temporary password manually.';
    }

    $results[] = $result;
}

$already = count(array_filter($results, static fn($x) => $x['status'] === 'already'));

$summary = "{$created} new student(s) created and registered, {$registered} existing student(s) registered";
if ($already) $summary .= ", {$already} already registered";
$summary .= ", {$skipped} skipped.";
if ($sendEmails) $summary .= " Login emails sent to {$emailed}.";

bu_json(200, [
    'status'     => 'success',
    'created'    => $created,
    'registered' => $registered,
    'skipped' => $skipped,
    'emailed' => $emailed,
    'default_course' => $defaultCourse['name'] ?? '',
    'default_intake' => $defaultIntake['name'] ?? '',
    'message' => $summary,
    'results' => $results,
]);
