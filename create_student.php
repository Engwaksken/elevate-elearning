<?php
session_start();
require_once __DIR__ . '/scripts/connector.php';

$systemname = $systemname ?? "Elevate Her 360 eLearning";

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function old_value(string $key, $default = ''): string
{
    if (isset($_SESSION['old_input'][$key])) {
        return (string)$_SESSION['old_input'][$key];
    }

    return (string)$default;
}

$successMessage = $_SESSION['success_message'] ?? '';
$errorMessage = $_SESSION['error_message'] ?? '';
$shouldRedirectLogin = !empty($_SESSION['registration_success']);

unset($_SESSION['success_message'], $_SESSION['error_message'], $_SESSION['registration_success']);

$selected_branch_id     = (int)old_value('branch_id', $_SESSION['selected_branch_id'] ?? 0);
$selected_course_name   = old_value('course_name', $_SESSION['selected_course_name'] ?? '');
$selected_class_type_id = (int)old_value('class_type_id', $_SESSION['selected_class_type_id'] ?? 0);
$selected_country       = old_value('country', $_SESSION['selected_country'] ?? '');
$selected_is_pwd        = old_value('is_pwd', '') === '1';

$old_fname   = old_value('fname');
$old_lname   = old_value('lname');
$old_oname   = old_value('oname');
$old_contact = old_value('contact');
$old_email   = old_value('email');
$old_dob     = old_value('dob');

unset($_SESSION['old_input']);

$course_intake = [];
$intakes_result = $conn->query("SELECT id, name, date_launching FROM intakes ORDER BY name ASC");
if ($intakes_result) {
    while ($row = $intakes_result->fetch_assoc()) {
        $course_intake[] = $row;
    }
}

$course_names = [];
$course_query = "
    SELECT name 
    FROM courses
    WHERE name NOT IN (
        'ENTREPRENEURSHIP IN THE DIGITAL ECONOMY',
        'SOFT SKILLS IN THE DIGITAL AGE'
    )
    ORDER BY category ASC, name ASC
";
$courses_result = $conn->query($course_query);
if ($courses_result) {
    while ($row = $courses_result->fetch_assoc()) {
        $course_names[] = $row['name'];
    }
}

$branches = [];
$branches_result = $conn->query("SELECT id, name FROM branches ORDER BY name ASC");
if ($branches_result) {
    while ($row = $branches_result->fetch_assoc()) {
        $branches[] = $row;
    }
}

$class_types = [];
$class_types_result = $conn->query("SELECT id, name FROM class_type WHERE status='Active' ORDER BY name ASC");
if ($class_types_result) {
    while ($row = $class_types_result->fetch_assoc()) {
        $class_types[] = $row;
    }
}

$predefined_countries = [
    'Uganda',
    'Kenya',
    'Tanzania',
    'Rwanda',
    'Sudan',
    'DRC',
    'Somalia'
];

$is_other_country = $selected_country !== '' && !in_array($selected_country, $predefined_countries, true);

if (empty($_SESSION['student_register_csrf'])) {
    $_SESSION['student_register_csrf'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= e($systemname); ?> | Student Sign Up</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" type="image/png" href="images/favicon.png">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="font-awesome/css/font-awesome.css" rel="stylesheet">
    <link href="css/animate.css" rel="stylesheet">
    <link href="css/plugins/datapicker/datepicker3.css" rel="stylesheet">
    <link href="app.css" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(122,25,25,.10), transparent 30%),
                linear-gradient(135deg, var(--bg), #ffffff);
            padding: 24px 14px;
            font-family: var(--font-sans);
        }

        .signup-shell {
            max-width: 860px;
            margin: 0 auto;
        }

        .signup-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .signup-head {
            background: var(--maroon-dk);
            border-bottom: 4px solid var(--gold);
            padding: 24px 28px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .signup-logo {
            width: 72px;
            height: 72px;
            border-radius: 18px;
            background: rgba(255,255,255,.10);
            border: 1px solid rgba(255,255,255,.14);
            display: grid;
            place-items: center;
            flex-shrink: 0;
        }

        .signup-logo img {
            max-width: 58px;
            max-height: 58px;
            object-fit: contain;
        }

        .signup-head h2 {
            margin: 0;
            color: #fff;
            font-family: var(--font-serif);
            font-size: 1.55rem;
        }

        .signup-head p {
            margin: 5px 0 0;
            color: rgba(255,255,255,.70);
            font-size: .9rem;
        }

        .signup-body {
            padding: 26px 28px 28px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px 18px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-field-full {
            grid-column: 1 / -1;
        }

        .form-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .07em;
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 0;
        }

        .form-label i {
            color: var(--maroon);
        }

        .form-input,
        .form-select {
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            background: var(--surface2);
            color: var(--text);
            font-size: 15px;
            transition: border-color var(--trans), box-shadow var(--trans);
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--maroon);
            box-shadow: 0 0 0 4px var(--maroon-dim);
        }

        .pwd-box {
            min-height: 44px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            background: var(--surface2);
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
        }

        .pwd-box input {
            width: 18px;
            height: 18px;
            margin: 0;
        }

        .pwd-box label {
            margin: 0;
            font-size: .9rem;
            font-weight: 500;
            color: var(--text);
            cursor: pointer;
        }

        .auth-alert {
            margin-bottom: 16px;
            align-items: flex-start;
        }

        .signup-actions {
            margin-top: 22px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .signup-footer {
            text-align: center;
            font-size: .9rem;
            color: var(--muted);
            margin-top: 4px;
        }

        .signup-footer a {
            color: var(--maroon);
            font-weight: 700;
            text-decoration: none;
        }

        .signup-footer a:hover {
            color: var(--gold);
        }

        .field-hint {
            font-size: 12px;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .field-hint i {
            color: var(--gold);
        }

        @media (max-width: 720px) {
            .signup-head {
                flex-direction: column;
                text-align: center;
                padding: 22px 18px;
            }

            .signup-body {
                padding: 20px 16px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <?php if (!empty($shouldRedirectLogin)): ?>
<meta http-equiv="refresh" content="5;url=login.php">
<?php endif; ?>
    
</head>

<body>

<div class="signup-shell animated fadeInDown">
    <div class="signup-card">
        <div class="signup-head">
            <div class="signup-logo">
                <img src="images/syx_images/logo.png" alt="<?= e($systemname); ?>">
            </div>

            <div>
                <h2>Student Registration</h2>
                <p>Create your learner account and access your course dashboard.</p>
            </div>
        </div>

        <div class="signup-body">
            <?php if (!empty($successMessage)): ?>
                <div class="auth-alert success" id="registrationSuccessAlert">
                    <i class="fa fa-check-circle"></i>

                    <div>
                        <div style="font-weight:700;">Registration Successful</div>

                        <div style="margin-top:4px;">
                            <?= e($successMessage); ?>
                        </div>

                        <?php if ($shouldRedirectLogin): ?>
                            <div style="margin-top:8px;font-size:.85rem;opacity:.85;">
                                Redirecting to login page in <span id="redirectCounter">5</span> seconds...
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($errorMessage)): ?>
                <div class="auth-alert error">
                    <i class="fa fa-exclamation-circle"></i>
                    <?= e($errorMessage); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="scripts/create_user.php" id="studentSignupForm" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['student_register_csrf']); ?>">
                <input type="hidden" name="type" value="student">
                <input type="hidden" name="country" id="country_final" value="<?= e($selected_country); ?>">

                <div class="form-grid">
                    <div class="form-field">
                        <label class="form-label" for="fname">
                            <i class="fa fa-user"></i> Surname *
                        </label>
                        <input type="text" class="form-input" id="fname" name="fname"
                               value="<?= e($old_fname); ?>" placeholder="e.g. Namakula"
                               autocomplete="family-name" required>
                    </div>

                    <div class="form-field">
                        <label class="form-label" for="lname">
                            <i class="fa fa-user"></i> Given Name *
                        </label>
                        <input type="text" class="form-input" id="lname" name="lname"
                               value="<?= e($old_lname); ?>" placeholder="e.g. Joan"
                               autocomplete="given-name" required>
                    </div>

                    <div class="form-field">
                        <label class="form-label" for="oname">
                            <i class="fa fa-id-card"></i> Other Name
                        </label>
                        <input type="text" class="form-input" id="oname" name="oname"
                               value="<?= e($old_oname); ?>" placeholder="Optional"
                               autocomplete="additional-name">
                    </div>

                    <div class="form-field">
                        <label class="form-label" for="contact">
                            <i class="fa fa-phone"></i> Phone Number *
                        </label>
                        <input type="tel" class="form-input" id="contact" name="contact"
                               value="<?= e($old_contact); ?>" placeholder="+256 700 000 000"
                               autocomplete="tel" required>
                    </div>

                    <div class="form-field">
                        <label class="form-label" for="email">
                            <i class="fa fa-envelope"></i> Email Address *
                        </label>
                        <input type="email" class="form-input" id="email" name="email"
                               value="<?= e($old_email); ?>" placeholder="example@gmail.com"
                               autocomplete="email" required>
                        <span id="emailHelp" class="field-hint">
                            <i class="fa fa-shield"></i> A verification code may be sent to this email.
                        </span>
                    </div>

                    <div class="form-field">
                        <label class="form-label" for="dob">
                            <i class="fa fa-calendar"></i> Date of Birth *
                        </label>
                        <input type="date" class="form-input" id="dob" name="dob"
                               value="<?= e($old_dob); ?>" required>
                    </div>

                    <div class="form-field">
                        <label class="form-label" for="branch_id">
                            <i class="fa fa-map-marker"></i> Branch *
                        </label>
                        <select id="branch_id" name="branch_id" class="form-select" required>
                            <option value="">Select Branch e.g. Kampala</option>
                            <?php if (!empty($branches)): ?>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?= (int)$branch['id']; ?>" <?= ((int)$branch['id'] === $selected_branch_id) ? 'selected' : ''; ?>>
                                        <?= e($branch['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No branches available</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-field">
                        <label class="form-label" for="course_name">
                            <i class="fa fa-book"></i> Course *
                        </label>
                        <select id="course_name" name="course_name" class="form-select" required>
                            <option value="">Select Course e.g. Basic Computing</option>
                            <?php if (!empty($course_names)): ?>
                                <?php foreach ($course_names as $course_name): ?>
                                    <option value="<?= e($course_name); ?>" <?= ($course_name === $selected_course_name) ? 'selected' : ''; ?>>
                                        <?= e($course_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No courses available</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-field">
                        <label class="form-label" for="class_type_id">
                            <i class="fa fa-desktop"></i> Class Type *
                        </label>
                        <select id="class_type_id" name="class_type_id" class="form-select" required>
                            <option value="">Online or Physical</option>
                            <?php if (!empty($class_types)): ?>
                                <?php foreach ($class_types as $ct): ?>
                                    <option value="<?= (int)$ct['id']; ?>" <?= ((int)$ct['id'] === $selected_class_type_id) ? 'selected' : ''; ?>>
                                        <?= e($ct['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No class types available</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-field">
                        <label class="form-label" for="country_select">
                            <i class="fa fa-globe"></i> Country *
                        </label>
                        <select id="country_select" name="country_select" class="form-select" required>
                            <option value="">Select Country</option>
                            <?php foreach ($predefined_countries as $country): ?>
                                <option value="<?= e($country); ?>" <?= ($selected_country === $country) ? 'selected' : ''; ?>>
                                    <?= e($country); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="Other" <?= $is_other_country ? 'selected' : ''; ?>>Other</option>
                        </select>

                        <div id="country_other_field" style="<?= $is_other_country ? 'display:block;margin-top:10px;' : 'display:none;margin-top:10px;'; ?>">
                            <input type="text" class="form-input" id="country_other" name="country_other"
                                   placeholder="Type your country"
                                   value="<?= e($is_other_country ? $selected_country : ''); ?>"
                                   <?= $is_other_country ? 'required' : ''; ?>>
                        </div>
                    </div>

                    <div class="form-field form-field-full">
                        <label class="form-label" for="is_pwd">
                            <i class="fa fa-wheelchair"></i> PWD Status
                        </label>

                        <div class="pwd-box">
                            <input type="checkbox" id="is_pwd" name="is_pwd" value="1"
                                   <?= $selected_is_pwd ? 'checked' : ''; ?>>
                            <label for="is_pwd">Yes, I am a Person with Disability (PWD)</label>
                        </div>
                    </div>
                </div>

                <div class="signup-actions">
                    <button type="submit" name="submit1" class="btn-auth" <?= empty($course_names) ? 'disabled' : ''; ?>>
                        <i class="fa fa-user-plus"></i> Register Now
                    </button>

                    <div class="signup-footer">
                        Have an account?
                        <a href="login">Login</a>
                        &nbsp;|&nbsp;
                        <a href="../"><i class="fa fa-arrow-left"></i> Back</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="js/jquery-3.1.1.min.js"></script>
<script src="js/bootstrap.min.js"></script>

<script>
$(function () {
    function syncCountry() {
        const selected = $('#country_select').val();

        if (selected === 'Other') {
            $('#country_other_field').slideDown(200);
            $('#country_other').attr('required', true);
            $('#country_final').val($('#country_other').val().trim());
        } else {
            $('#country_other_field').slideUp(200);
            $('#country_other').removeAttr('required').val('');
            $('#country_final').val(selected || '');
        }
    }

    $('#country_select').on('change', syncCountry);

    $('#country_other').on('input', function () {
        $('#country_final').val($(this).val().trim());
    });

    syncCountry();

    $('#studentSignupForm').on('submit', function (e) {
        const email = $('#email').val().trim();
        const contact = $('#contact').val().trim();

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) {
            e.preventDefault();
            alert('Please enter a valid email address.');
            $('#email').focus();
            return false;
        }

        if (contact.length < 7) {
            e.preventDefault();
            alert('Please enter a valid phone number.');
            $('#contact').focus();
            return false;
        }

        syncCountry();
        return true;
    });

    <?php if (!empty($shouldRedirectLogin)): ?>
        let countdown = 5;
        const counterEl = document.getElementById('redirectCounter');

        if (counterEl) {
            counterEl.textContent = countdown;
        }

        const timer = setInterval(function () {
            countdown--;

            if (counterEl) {
                counterEl.textContent = countdown;
            }

            if (countdown <= 0) {
                clearInterval(timer);
                window.location.replace('login');
            }
        }, 1000);

        setTimeout(function () {
            window.location.replace('login');
        }, 5000);
    <?php else: ?>
        $(".auth-alert").delay(5000).fadeTo(800, 0, function () {
            $(this).slideUp(300, function () {
                $(this).remove();
            });
        });
    <?php endif; ?>
});
</script>

</body>
</html>