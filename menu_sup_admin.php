<?php
$view = $_GET['view'] ?? 'home';

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function is_active(string $currentView, array $views): string
{
    return in_array($currentView, $views, true) ? ' class="active"' : '';
}

$fullName = trim(($firstname ?? '') . ' ' . ($lastname ?? ''));
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
                                <strong class="font-bold"><?= h($fullName ?: 'User') ?></strong>
                            </span>
                            <span class="text-muted text-xs block">
                                <?= h($mytype ?? 'Account') ?> <b class="caret"></b>
                            </span>
                        </span>
                    </a>

                    <ul class="dropdown-menu animated fadeInRight m-t-xs">
                        <li><a href="?view=profile"><i class="fa fa-lock"></i> Change Password</a></li>
                        <li><a href="?view=change_pic"><i class="fa fa-image"></i> Change Profile Picture</a></li>
                        <li class="divider"></li>
                        <li><a href="logout.php"><i class="fa fa-sign-out"></i> Logout</a></li>
                    </ul>
                </div>

                <div class="logo-element"></div>
            </li>

            <li<?= is_active($view, ['', 'home']); ?>>
                <a href="index.php">
                    <i class="fa fa-home"></i>
                    <span class="nav-label">Home</span>
                </a>
            </li>

            <li<?= is_active($view, ['view_students', 'view_instructors', 'view_accounts', 'view_admin', 'create']); ?>>
                <a href="#">
                    <i class="fa fa-users"></i>
                    <span class="nav-label">Users</span>
                    <span class="fa arrow"></span>
                </a>
                <ul class="nav nav-second-level collapse">
                    <li<?= is_active($view, ['view_students']); ?>><a href="?view=view_students">Students</a></li>
                    <li<?= is_active($view, ['view_instructors']); ?>><a href="?view=view_instructors">Instructors</a></li>
                    <li<?= is_active($view, ['view_admin']); ?>><a href="?view=view_admin">Admins</a></li>
                    <li<?= is_active($view, ['create']); ?>><a href="?view=create">Create Account</a></li>
                </ul>
            </li>

            <li<?= is_active($view, ['logins']); ?>>
                <a href="?view=logins">
                    <i class="fa fa-user-lock"></i>
                    <span class="nav-label">User Logins</span>
                </a>
            </li>

            <li<?= is_active($view, ['branches', 'add_branch', 'all_branches']); ?>>
                <a href="#">
                    <i class="fa fa-building"></i>
                    <span class="nav-label">Branches</span>
                    <span class="fa arrow"></span>
                </a>
                <ul class="nav nav-second-level collapse">
                    <li<?= is_active($view, ['all_branches', 'branches']); ?>><a href="?view=all_branches">View Branches</a></li>
                    <li<?= is_active($view, ['add_branch']); ?>><a href="?view=add_branch">Add Branch</a></li>
                </ul>
            </li>

            <li<?= is_active($view, ['intakes']); ?>>
                <a href="?view=intakes">
                    <i class="fa fa-calendar"></i>
                    <span class="nav-label">Intakes</span>
                </a>
            </li>

            <li<?= is_active($view, ['all_courses', 'create_course', 'all_assignments']); ?>>
                <a href="#">
                    <i class="fa fa-graduation-cap"></i>
                    <span class="nav-label">Courses</span>
                    <span class="fa arrow"></span>
                </a>
                <ul class="nav nav-second-level collapse">
                    <li<?= is_active($view, ['all_courses']); ?>><a href="?view=all_courses">View Courses</a></li>
                    <li<?= is_active($view, ['create_course']); ?>><a href="?view=create_course">Add Course</a></li>
                    <li<?= is_active($view, ['all_assignments']); ?>><a href="?view=all_assignments">All Assignments</a></li>
                </ul>
            </li>

            <li<?= is_active($view, ['mindsets']); ?>>
                <a href="?view=mindsets">
                    <i class="fa fa-seedling"></i>
                    <span class="nav-label">Mindsets</span>
                </a>
            </li>

            <li<?= is_active($view, ['certificate_templates', 'recommend_certificates']); ?>>
                <a href="#">
                    <i class="fa fa-certificate"></i>
                    <span class="nav-label">Certificates</span>
                    <span class="fa arrow"></span>
                </a>
                <ul class="nav nav-second-level collapse">
                    <li<?= is_active($view, ['certificate_templates']); ?>><a href="?view=certificate_templates">Certificate Templates</a></li>
                    <li<?= is_active($view, ['recommend_certificates']); ?>><a href="?view=recommend_certificates">Recommend Certificates</a></li>
                </ul>
            </li>

            <li<?= is_active($view, ['view_more_resources', 'insert_free_courses', 'online_class']); ?>>
                <a href="#">
                    <i class="fa fa-play-circle"></i>
                    <span class="nav-label">Resources</span>
                    <span class="fa arrow"></span>
                </a>
                <ul class="nav nav-second-level collapse">
                    <li<?= is_active($view, ['online_class']); ?>><a href="?view=online_class">More Resources</a></li>
                    <li<?= is_active($view, ['view_more_resources']); ?>><a href="?view=view_more_resources">View Resources</a></li>
                    <li<?= is_active($view, ['insert_free_courses']); ?>><a href="?view=insert_free_courses">Add Resources</a></li>
                </ul>
            </li>

            <li<?= is_active($view, ['chatroom_ad', 'create_chatroom']); ?>>
                <a href="#">
                    <i class="fa fa-comments"></i>
                    <span class="nav-label">Chatroom</span>
                    <span class="fa arrow"></span>
                </a>
                <ul class="nav nav-second-level collapse">
                    <li<?= is_active($view, ['chatroom_ad']); ?>><a href="?view=chatroom_ad">View Chatrooms</a></li>
                    <li<?= is_active($view, ['create_chatroom']); ?>><a href="?view=create_chatroom">Add Chatroom</a></li>
                </ul>
            </li>

            <li<?= is_active($view, ['todo']); ?>>
                <a href="?view=todo">
                    <i class="fa fa-sticky-note"></i>
                    <span class="nav-label">Sticky Notes</span>
                </a>
            </li>

        </ul>
    </div>
</nav>