<?php
$view = $_GET['view'] ?? 'home';

if (!function_exists('sidebar_active')) {
    function sidebar_active(string $currentView, array $views): string
    {
        return in_array($currentView, $views, true) ? ' class="active"' : '';
    }
}

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

$fullName = trim((string)($firstname ?? '') . ' ' . (string)($lastname ?? ''));

$mentorshipUrl = 'https://www.elevateher360.org/mentorship/';
$jobsUrl       = 'https://www.elevateher360.org/jobs_portal/';
$libraryUrl    = 'https://library.elevateher360.org/';
?>

<nav class="navbar-default navbar-static-side" role="navigation">
    <div class="sidebar-collapse">
        <ul class="nav metismenu" id="side-menu">

            <li class="nav-header">
                <div class="dropdown profile-element">
                    <span>
                        <img
                            alt="Profile"
                            class="img-circle profile-top"
                            src="<?= h($dpf ?? 'assets/img/default-user.png') ?>"
                            onerror="this.src='assets/img/default-user.png';"
                        >
                    </span>

                    <a data-toggle="dropdown" class="dropdown-toggle" href="#">
                        <span class="clear">
                            <span class="block m-t-xs">
                                <strong class="font-bold">
                                    <?= h($fullName ?: 'Student') ?>
                                    <?php if (!empty($studentnumber)): ?>
                                        (<?= h($studentnumber) ?>)
                                    <?php endif; ?>
                                </strong>
                            </span>

                            <span class="text-muted text-xs block">
                                <?= h($mytype ?? 'Learner') ?> <b class="caret"></b>
                            </span>
                        </span>
                    </a>

                    <ul class="dropdown-menu animated fadeInRight m-t-xs">
                        <li>
                            <a href="?view=profile">
                                <i class="fa fa-lock"></i> Change Password
                            </a>
                        </li>
                        <li>
                            <a href="?view=change_pic">
                                <i class="fa fa-image"></i> Change Profile Picture
                            </a>
                        </li>
                        <li class="divider"></li>
                        <li>
                            <a href="logout.php">
                                <i class="fa fa-sign-out"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <li<?= sidebar_active($view, ['home']); ?>>
                <a href="index.php">
                    <i class="fa fa-home"></i>
                    <span class="nav-label">Home</span>
                </a>
            </li>

            <li<?= sidebar_active($view, ['my_courses']); ?>>
                <a href="?view=my_courses">
                    <i class="fa fa-graduation-cap"></i>
                    <span class="nav-label">My Courses</span>
                </a>
            </li>

            <li<?= sidebar_active($view, ['mindset']); ?>>
                <a href="?view=mindset">
                    <i class="fa fa-seedling"></i>
                    <span class="nav-label">Mindset Curriculum</span>
                </a>
            </li>

            <li<?= sidebar_active($view, ['my_assignment']); ?>>
                <a href="?view=my_assignment">
                    <i class="fa fa-clipboard"></i>
                    <span class="nav-label">My Assignments</span>
                </a>
            </li>

            <li<?= sidebar_active($view, ['my_certificates']); ?>>
                <a href="?view=my_certificates">
                    <i class="fa fa-certificate"></i>
                    <span class="nav-label">My Certificates</span>
                </a>
            </li>

            <li<?= sidebar_active($view, ['student_more_resources']); ?>>
                <a href="?view=student_more_resources">
                    <i class="fa fa-play-circle"></i>
                    <span class="nav-label">More Resources</span>
                </a>
            </li>

            <li<?= sidebar_active($view, ['todo']); ?>>
                <a href="?view=todo">
                    <i class="fa fa-sticky-note"></i>
                    <span class="nav-label">My Sticky Notes</span>
                </a>
            </li>

            <li<?= sidebar_active($view, ['chatroom']); ?>>
                <a href="?view=chatroom">
                    <i class="fa fa-comments"></i>
                    <span class="nav-label">Chatrooms</span>
                </a>
            </li>

            <li class="nav-divider"></li>

            <li>
                <a href="<?= h($mentorshipUrl) ?>" target="_blank" rel="noopener noreferrer">
                    <i class="fa fa-users"></i>
                    <span class="nav-label">Mentorship</span>
                    <i class="fa fa-external-link pull-right"></i>
                </a>
            </li>

            <li>
                <a href="<?= h($jobsUrl) ?>" target="_blank" rel="noopener noreferrer">
                    <i class="fa fa-briefcase"></i>
                    <span class="nav-label">Jobs Portal</span>
                    <i class="fa fa-external-link pull-right"></i>
                </a>
            </li>

            <li>
                <a href="<?= h($libraryUrl) ?>" target="_blank" rel="noopener noreferrer">
                    <i class="fa fa-book"></i>
                    <span class="nav-label">E-library</span>
                    <i class="fa fa-external-link pull-right"></i>
                </a>
            </li>

        </ul>
    </div>
</nav>