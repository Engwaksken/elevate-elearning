<?php

declare(strict_types=1);

require 'scripts/connector.php';

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function cleanInput(?string $value): string
{
    return trim((string)$value);
}


function getAdminDetails(mysqli $conn, string $adminId): ?array
{
    $query = "
        SELECT
            id,
            unique_id,
            fname,
            lname,
            other_name,
            contact,
            email,
            dob,
            gender,
            country,
            acc_type
        FROM users
        WHERE unique_id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        throw new RuntimeException(
            'Unable to prepare admin lookup: ' . $conn->error
        );
    }

    $stmt->bind_param(
        's',
        $adminId
    );

    if (!$stmt->execute()) {

        $error = $stmt->error;

        $stmt->close();

        throw new RuntimeException(
            'Unable to load admin details: ' . $error
        );
    }

    $result = $stmt->get_result();

    $admin = $result->fetch_assoc();

    $stmt->close();

    return $admin ?: null;
}


function emailExistsForAnotherUser(
    mysqli $conn,
    string $email,
    string $adminId
): bool {

    $query = "
        SELECT id
        FROM users
        WHERE LOWER(email) = LOWER(?)
        AND unique_id <> ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        throw new RuntimeException(
            'Unable to prepare email validation: ' . $conn->error
        );
    }

    $stmt->bind_param(
        'ss',
        $email,
        $adminId
    );

    if (!$stmt->execute()) {

        $error = $stmt->error;

        $stmt->close();

        throw new RuntimeException(
            'Unable to validate email: ' . $error
        );
    }

    $result = $stmt->get_result();

    $exists = $result->num_rows > 0;

    $stmt->close();

    return $exists;
}


function updateAdminDetails(
    mysqli $conn,
    string $adminId,
    string $fname,
    string $lname,
    string $otherName,
    string $contact,
    string $email,
    ?string $dob,
    string $gender,
    string $country
): bool {

    /*
    |--------------------------------------------------------------------------
    | Admin account type
    |--------------------------------------------------------------------------
    |
    | Existing system mapping:
    |
    | admin => xybrtw
    |
    |--------------------------------------------------------------------------
    */

    $adminAccountType = 'xybrtw';

    $query = "
        UPDATE users
        SET
            fname = ?,
            lname = ?,
            other_name = ?,
            contact = ?,
            email = ?,
            dob = ?,
            gender = ?,
            country = ?,
            acc_type = ?
        WHERE unique_id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        throw new RuntimeException(
            'Unable to prepare admin update: ' . $conn->error
        );
    }

    $stmt->bind_param(
        'ssssssssss',
        $fname,
        $lname,
        $otherName,
        $contact,
        $email,
        $dob,
        $gender,
        $country,
        $adminAccountType,
        $adminId
    );

    if (!$stmt->execute()) {

        $error = $stmt->error;

        $stmt->close();

        throw new RuntimeException(
            'Unable to update admin: ' . $error
        );
    }

    $success = $stmt->affected_rows >= 0;

    $stmt->close();

    return $success;
}


/*
|--------------------------------------------------------------------------
| Messages / Default Values
|--------------------------------------------------------------------------
*/

$successMessage = '';
$errorMessage   = '';

$adminId = '';

$admin = [
    'unique_id'   => '',
    'fname'       => '',
    'lname'       => '',
    'other_name'  => '',
    'contact'     => '',
    'email'       => '',
    'dob'         => '',
    'gender'      => '',
    'country'     => 'Uganda',
    'acc_type'    => '',
];


/*
|--------------------------------------------------------------------------
| Load Admin
|--------------------------------------------------------------------------
*/

try {

    /*
     * GET request
     */
    if (
        $_SERVER['REQUEST_METHOD'] === 'GET' &&
        isset($_GET['id'])
    ) {

        $adminId = cleanInput($_GET['id']);

        if ($adminId === '') {
            throw new RuntimeException(
                'Invalid administrator ID.'
            );
        }

        $loadedAdmin = getAdminDetails(
            $conn,
            $adminId
        );

        if (!$loadedAdmin) {
            throw new RuntimeException(
                'Admin not found.'
            );
        }

        $admin = array_merge(
            $admin,
            $loadedAdmin
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Update Admin
    |--------------------------------------------------------------------------
    */

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $adminId = cleanInput(
            $_POST['unique_id'] ?? ''
        );

        $fname = cleanInput(
            $_POST['fname'] ?? ''
        );

        $lname = cleanInput(
            $_POST['lname'] ?? ''
        );

        $otherName = cleanInput(
            $_POST['other_name'] ?? ''
        );

        $contact = cleanInput(
            $_POST['contact'] ?? ''
        );

        $email = strtolower(
            cleanInput(
                $_POST['email'] ?? ''
            )
        );

        $dob = cleanInput(
            $_POST['dob'] ?? ''
        );

        $gender = strtolower(
            cleanInput(
                $_POST['gender'] ?? ''
            )
        );

        $country = cleanInput(
            $_POST['country'] ?? ''
        );


        /*
        |--------------------------------------------------------------------------
        | Basic validation
        |--------------------------------------------------------------------------
        */

        if ($adminId === '') {
            throw new RuntimeException(
                'Invalid administrator ID.'
            );
        }


        if ($fname === '') {
            throw new RuntimeException(
                'First name is required.'
            );
        }


        if ($lname === '') {
            throw new RuntimeException(
                'Last name is required.'
            );
        }


        if ($contact === '') {
            throw new RuntimeException(
                'Phone number is required.'
            );
        }


        if ($email === '') {
            throw new RuntimeException(
                'Email address is required.'
            );
        }


        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(
                'Please enter a valid email address.'
            );
        }


        if (!in_array(
            $gender,
            ['male', 'female'],
            true
        )) {
            throw new RuntimeException(
                'Please select a valid gender.'
            );
        }


        if ($country === '') {
            throw new RuntimeException(
                'Please select a country.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | DOB Validation
        |--------------------------------------------------------------------------
        */

        $validatedDob = null;

        if ($dob !== '') {

            $dateObject = DateTime::createFromFormat(
                'Y-m-d',
                $dob
            );

            if (
                !$dateObject ||
                $dateObject->format('Y-m-d') !== $dob
            ) {
                throw new RuntimeException(
                    'Please enter a valid date of birth.'
                );
            }

            $validatedDob = $dob;
        }


        /*
        |--------------------------------------------------------------------------
        | Ensure Admin Exists
        |--------------------------------------------------------------------------
        */

        $existingAdmin = getAdminDetails(
            $conn,
            $adminId
        );

        if (!$existingAdmin) {
            throw new RuntimeException(
                'Administrator account was not found.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Duplicate Email Check
        |--------------------------------------------------------------------------
        */

        if (
            emailExistsForAnotherUser(
                $conn,
                $email,
                $adminId
            )
        ) {
            throw new RuntimeException(
                'Another user already uses this email address.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Update
        |--------------------------------------------------------------------------
        */

        if (
            updateAdminDetails(
                $conn,
                $adminId,
                $fname,
                $lname,
                $otherName,
                $contact,
                $email,
                $validatedDob,
                $gender,
                $country
            )
        ) {

            $successMessage =
                'Admin details updated successfully.';


            /*
             * Reload updated data.
             */
            $updatedAdmin = getAdminDetails(
                $conn,
                $adminId
            );

            if ($updatedAdmin) {

                $admin = array_merge(
                    $admin,
                    $updatedAdmin
                );
            }

        } else {

            throw new RuntimeException(
                'Admin details could not be updated.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Handle direct visit without ID
    |--------------------------------------------------------------------------
    */

    if (
        $_SERVER['REQUEST_METHOD'] !== 'POST' &&
        $adminId === ''
    ) {
        $errorMessage =
            'No administrator was selected.';
    }


} catch (Throwable $e) {

    $errorMessage = $e->getMessage();

    /*
     * Preserve entered information after validation error.
     */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $admin = array_merge(
            $admin,
            [
                'unique_id'  => $adminId,
                'fname'      => $fname ?? '',
                'lname'      => $lname ?? '',
                'other_name' => $otherName ?? '',
                'contact'    => $contact ?? '',
                'email'      => $email ?? '',
                'dob'        => $dob ?? '',
                'gender'     => $gender ?? '',
                'country'    => $country ?? 'Uganda',
            ]
        );
    }

    error_log(
        'Edit admin error: ' . $e->getMessage()
    );
}

?>

<link
    href="css/plugins/datapicker/datepicker3.css"
    rel="stylesheet"
>


<div class="row wrapper border-bottom white-bg page-heading">

    <div class="col-lg-10">

        <h2>
            Update Admin Details
        </h2>

        <ol class="breadcrumb">

            <li>
                <a href="?">
                    Home
                </a>
            </li>

            <li>
                <a href="?view=view_admin">
                    Admins
                </a>
            </li>

            <li class="active">
                <strong>
                    Admin Details
                </strong>
            </li>

        </ol>

    </div>

</div>


<div class="wrapper wrapper-content">

    <div class="row">

        <div
            class="middle-box animated fadeInDown"
            style="
                margin:0 auto;
                max-width:600px;
            "
        >


            <?php if ($successMessage !== ''): ?>

                <div
                    class="alert alert-success text-center"
                    id="successMessage"
                >
                    <i class="fa fa-check-circle"></i>

                    <?php
                    echo htmlspecialchars(
                        $successMessage,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </div>

            <?php endif; ?>


            <?php if ($errorMessage !== ''): ?>

                <div
                    class="alert alert-danger text-center"
                    id="errorMessage"
                >
                    <i class="fa fa-exclamation-circle"></i>

                    <?php
                    echo htmlspecialchars(
                        $errorMessage,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </div>

            <?php endif; ?>


            <?php if (!empty($admin['unique_id'])): ?>

                <form
                    method="post"
                    action=""
                    autocomplete="off"
                >

                    <input
                        type="hidden"
                        name="unique_id"
                        value="<?php
                        echo htmlspecialchars(
                            (string)$admin['unique_id'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>"
                    >


                    <!-- First Name -->
                    <div class="form-group">

                        <label for="fname">
                            First Name *
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="fname"
                            name="fname"
                            value="<?php
                            echo htmlspecialchars(
                                (string)$admin['fname'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>"
                            required
                        >

                    </div>


                    <!-- Last Name -->
                    <div class="form-group">

                        <label for="lname">
                            Last Name *
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="lname"
                            name="lname"
                            value="<?php
                            echo htmlspecialchars(
                                (string)$admin['lname'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>"
                            required
                        >

                    </div>


                    <!-- Other Name -->
                    <div class="form-group">

                        <label for="other_name">
                            Other Name
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="other_name"
                            name="other_name"
                            value="<?php
                            echo htmlspecialchars(
                                (string)$admin['other_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>"
                        >

                    </div>


                    <!-- Phone -->
                    <div class="form-group">

                        <label for="contact">
                            Phone Number *
                        </label>

                        <input
                            type="tel"
                            class="form-control"
                            id="contact"
                            name="contact"
                            value="<?php
                            echo htmlspecialchars(
                                (string)$admin['contact'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>"
                            required
                        >

                    </div>


                    <!-- Email -->
                    <div class="form-group">

                        <label for="email">
                            Email Address *
                        </label>

                        <input
                            type="email"
                            class="form-control"
                            id="email"
                            name="email"
                            value="<?php
                            echo htmlspecialchars(
                                (string)$admin['email'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>"
                            required
                        >

                    </div>


                    <!-- DOB -->
                    <div class="form-group">

                        <label for="dob">
                            Date of Birth
                        </label>

                        <input
                            type="date"
                            class="form-control"
                            id="dob"
                            name="dob"
                            value="<?php
                            echo htmlspecialchars(
                                (string)($admin['dob'] ?? ''),
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>"
                        >

                    </div>


                    <!-- Gender -->
                    <div class="form-group">

                        <label for="gender">
                            Gender *
                        </label>

                        <select
                            class="form-control"
                            id="gender"
                            name="gender"
                            required
                        >

                            <option value="">
                                Select gender
                            </option>

                            <option
                                value="male"
                                <?php
                                echo strtolower(
                                    (string)$admin['gender']
                                ) === 'male'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                Male
                            </option>

                            <option
                                value="female"
                                <?php
                                echo strtolower(
                                    (string)$admin['gender']
                                ) === 'female'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                Female
                            </option>

                        </select>

                    </div>


                    <!-- Country -->
                    <div class="form-group">

                        <label for="country">
                            Country *
                        </label>

                        <select
                            class="form-control"
                            id="country"
                            name="country"
                            required
                        >

                            <option value="">
                                Select country
                            </option>

                            <option
                                value="Uganda"
                                <?php
                                echo $admin['country'] === 'Uganda'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                Uganda
                            </option>

                            <option
                                value="Kenya"
                                <?php
                                echo $admin['country'] === 'Kenya'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                Kenya
                            </option>

                            <option
                                value="Tanzania"
                                <?php
                                echo $admin['country'] === 'Tanzania'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                Tanzania
                            </option>

                            <option
                                value="DRC"
                                <?php
                                echo $admin['country'] === 'DRC'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                DRC
                            </option>

                            <option
                                value="Sudan"
                                <?php
                                echo $admin['country'] === 'Sudan'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                Sudan
                            </option>

                        </select>

                    </div>


                    <!-- Account Type -->
                    <div class="form-group">

                        <label>
                            Account Role
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            value="Administrator"
                            readonly
                        >

                        <small class="text-muted">
                            This account will remain an administrator.
                        </small>

                    </div>


                    <!-- Buttons -->
                    <button
                        type="submit"
                        class="btn btn-primary btn-block"
                    >
                        <i class="fa fa-save"></i>
                        Update Admin Details
                    </button>


                    <a
                        href="?view=view_admin"
                        class="btn btn-default btn-block"
                    >
                        <i class="fa fa-times"></i>
                        Cancel
                    </a>

                </form>

            <?php endif; ?>

        </div>

    </div>

</div>


<script>
document.addEventListener('DOMContentLoaded', function () {

    /*
    |--------------------------------------------------------------------------
    | Auto-hide messages after 5 seconds
    |--------------------------------------------------------------------------
    */

    const messages = [
        document.getElementById('successMessage'),
        document.getElementById('errorMessage')
    ];

    messages.forEach(function (message) {

        if (!message) {
            return;
        }

        setTimeout(function () {

            message.style.transition =
                'opacity .4s ease';

            message.style.opacity = '0';

            setTimeout(function () {

                if (message.parentNode) {
                    message.remove();
                }

            }, 400);

        }, 5000);

    });

});
</script>