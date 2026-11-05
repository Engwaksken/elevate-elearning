<?php
session_start();
require 'connector.php';

if (!isset($_POST['id'])) {
    echo '<div class="alert alert-danger">
  <strong>Danger!</strong> Hacking Attempt detected.
</div>';
    die();
} else {
    $id = $_POST['id'];
    $a = "SELECT * FROM users WHERE unique_id = '".$id."'";
    $b = $conn->query($a) or die(mysqli_error($conn));

    if ($b->num_rows < 1) {
        echo '<div class="alert alert-danger"><strong>Invalid Selection.</strong></div>';
    } else {
        while ($d = mysqli_fetch_array($b)) {
            $std_id = $d["unique_id"];
            $ver = ($d["verified"] == "yes") ?
                '<a href="deactivate?id=' . $d["unique_id"] . '"><span class="label label-primary">Active</span></a>' :
                '<a href="activate?id=' . $d["unique_id"] . '"><span class="label label-info">Not Active</span></a>';

            $mtype = "Unknown";
            $p = "";

            switch ($d["acc_type"]) {
                case "xybrtw":
                    $mtype = "Admin";
                    break;
                case "afgezt":
                    $mtype = "Student";
                    $p = "SELECT * FROM course_registration WHERE user_id = '" . $std_id . "' ORDER BY id DESC";
                    break;
                case "xtfbry":
                    $mtype = "Lecturer";
                    $p = "SELECT * FROM course_registration_lecturers WHERE user_id = '" . $std_id . "' ORDER BY id DESC";
                    break;
                case "zyffwq":
                    $mtype = "Accounts";
                    break;
            }

            $course = "N/A";
            $button = '<a href="?view=change_course&id='.$d["unique_id"].'" class="label label-primary">Assign Course</a>';
            $added_by_name = "N/A"; // Default fallback

            if (!empty($p)) {
                $z = $conn->query($p) or die(mysqli_error($conn));
                if ($z->num_rows > 0) {
                    $a  = mysqli_fetch_array($z);
                    $fty = $a["added_by"];

                    $az = "SELECT * FROM users WHERE unique_id ='".$fty."'";
                    $bz = $conn->query($az) or die(mysqli_error($conn));
                    if ($bz->num_rows > 0) {
                        $azz = mysqli_fetch_array($bz);
                        $added_by_name = $azz["fname"];
                    }

                    $pp = "SELECT name FROM courses WHERE id = '" . $a["course_id"] . "' ORDER BY id DESC";
                    $ll = $conn->query($pp) or die(mysqli_error($conn));
                    if ($ll->num_rows > 0) {
                        $qw = mysqli_fetch_array($ll);
                        $course = $qw['name'];
                        $button = '<a href="?view=change_course&id='.$d["unique_id"].'" class="label label-warning">Change Course</a>';
                    }
                }
            }

            $dpt = $d["dp"];
            $dpft = ($dpt == "default.jpg") ? 'images/default_p_pics/user.png' : 'images/user_p_pics/' . $dpt;

            echo '
            <div id="contact-1" class="tab-pane active">
                <div class="row m-b-lg">
                    <div class="col-lg-4 text-center">
                        <h2>' . $d["fname"] . ' ' . $d["lname"] . ' ' . $d["other_name"] . '</h2>
                        <div class="m-b-sm"> <img alt="image" class="img-circle" src="' . $dpft . '" style="width: 62px"> </div>
                    </div>
                    <div class="col-lg-8"> <strong> ' . $mtype . ' </strong>
                        <a href="?view=chatwith&pr=' . $std_id . '" type="button" class="btn btn-primary btn-sm btn-block"><i class="fa fa-envelope"></i> Chat </a>
                    </div>
                </div>
                <div class="client-detail">
                    <div class="full-height-scroll"> <strong>Account Details</strong>
                        <ul class="list-group clear-list">
                            <li class="list-group-item fist-item"> <span class="pull-right"> ' . $d["dob"] . '</span> D.O.B </li>
                            <li class="list-group-item"> <span class="pull-right"> ' . $d["date_created"] . ' </span> Date Created</li>
                            <li class="list-group-item"> <span class="pull-right">  ' . $d["email"] . '  </span>Email </li>
                            <li class="list-group-item"> <span class="pull-right"> ' . $d["contact"] . ' </span> Contact </li>
                            <li class="list-group-item"> <span class="pull-right"> ' . $d["gender"] . ' </span> Gender </li>
                            <li class="list-group-item"> <span class="pull-right"> ' . $added_by_name . ' </span> Added By </li>
                        </ul>
                        <strong>Course</strong>
                        <p> ' . $course . '</p>
                        <p> ' . $button . '</p>
                        <hr/>
                    </div>
                </div>
            </div>';
        }
    }
}
?>
