<?php
$view = $_GET['view'] ?? 'home';

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('active_menu')) {
    function active_menu(string $currentView, array $views): string
    {
        return in_array($currentView, $views, true) ? ' class="active"' : '';
    }
}

$fullName = trim(($firstname ?? '') . ' ' . ($lastname ?? ''));
$profileImage = $dpf ?? 'images/default_p_pics/user.png';
$userType = $mytype ?? 'Account';
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
                            src="<?= h($profileImage) ?>"
                            onerror="this.src='images/default_p_pics/user.png';"
                        >
                    </span>

                    <a data-toggle="dropdown" class="dropdown-toggle" href="#">
                        <span class="clear">
                            <span class="block m-t-xs">
                                <strong class="font-bold"><?= h($fullName ?: 'Admin') ?></strong>
                            </span>
                            <span class="text-muted text-xs block">
                                <?= h($userType) ?> <b class="caret"></b>
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
                            <a href="logout">
                                <i class="fa fa-sign-out"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="logo-element"></div>
            </li>

            <li<?= active_menu($view, ['', 'home']); ?>>
                <a href="./">
                    <i class="fa fa-home"></i>
                    <span class="nav-label">Home</span>
                </a>
            </li>

            <li<?= active_menu($view, ['view_students', 'view_instructors', 'view_accounts', 'view_admin', 'view_admins', 'create', 'view_create']); ?>>
                <a href="#">
                    <i class="fa fa-users"></i>
                    <span class="nav-label">Users</span>
                    <span class="fa arrow"></span>
                </a>

                <ul class="nav nav-second-level collapse">
                    <li<?= active_menu($view, ['view_students']); ?>>
                        <a href="?view=view_students">Students</a>
                    </li>

                    <li<?= active_menu($view, ['view_instructors']); ?>>
                        <a href="?view=view_instructors">Instructors</a>
                    </li>

               

                    <li<?= active_menu($view, ['create', 'view_create']); ?>>
                        <a href="?view=create">Create Account</a>
                    </li>
                </ul>
            </li>

            <li<?= active_menu($view, ['logins']); ?>>
                <a href="?view=logins">
                    <i class="fa fa-user"></i>
                    <span class="nav-label">User Logins</span>
                </a>
            </li>

            <li<?= active_menu($view, ['intakes', 'add_intake', 'intake_edit', 'editintake']); ?>>
                <a href="?view=intakes">
                    <i class="fa fa-calendar"></i>
                    <span class="nav-label">Intakes</span>
                </a>
            </li>

            <li<?= active_menu($view, ['all_courses', 'create_course', 'editcourse', 'update_course', 'open_course']); ?>>
                <a href="#">
                    <i class="fa fa-graduation-cap"></i>
                    <span class="nav-label">Courses</span>
                    <span class="fa arrow"></span>
                </a>

                <ul class="nav nav-second-level collapse">
                    <li<?= active_menu($view, ['all_courses', 'editcourse', 'update_course', 'open_course']); ?>>
                        <a href="?view=all_courses">View Courses</a>
                    </li>

                    <li<?= active_menu($view, ['create_course']); ?>>
                        <a href="?view=create_course">Add Course</a>
                    </li>
                </ul>
            </li>

            <li<?= active_menu($view, ['mindsets', 'mindset']); ?>>
                <a href="?view=mindsets">
                    <i class="fa fa-leaf"></i>
                    <span class="nav-label">Mindsets</span>
                </a>
            </li>

            <li<?= active_menu($view, ['certificate_templates', 'recommend_certificates']); ?>>
                <a href="#">
                    <i class="fa fa-certificate"></i>
                    <span class="nav-label">Certificates</span>
                    <span class="fa arrow"></span>
                </a>

                <ul class="nav nav-second-level collapse">
                    <li<?= active_menu($view, ['certificate_templates']); ?>>
                        <a href="?view=certificate_templates">Certificate Templates</a>
                    </li>

                    <li<?= active_menu($view, ['recommend_certificates']); ?>>
                        <a href="?view=recommend_certificates">Recommend Certificates</a>
                    </li>
                </ul>
            </li>

            <li<?= active_menu($view, ['other_resources', 'insert_free_courses', 'view_more_resources', 'update_resource', 'more_resources']); ?>>
                <a href="?view=other_resources">
                    <i class="fa fa-play-circle"></i>
                    <span class="nav-label">Resources</span>
                </a>
            </li>

            <li<?= active_menu($view, ['chatroom_ad', 'create_chatroom', 'open_chatroom', 'chat_ad', 'chatwith']); ?>>
                <a href="#">
                    <i class="fa fa-comments"></i>
                    <span class="nav-label">Chatroom</span>
                    <span class="fa arrow"></span>
                </a>

                <ul class="nav nav-second-level collapse">
                    <li<?= active_menu($view, ['chatroom_ad', 'open_chatroom']); ?>>
                        <a href="?view=chatroom_ad">View Chatrooms</a>
                    </li>

                    <li<?= active_menu($view, ['create_chatroom']); ?>>
                        <a href="?view=create_chatroom">Add Chatroom</a>
                    </li>
                </ul>
            </li>

            <li<?= active_menu($view, ['todo']); ?>>
                <a href="?view=todo">
                    <i class="fa fa-sticky-note"></i>
                    <span class="nav-label">Sticky Notes</span>
                </a>
            </li>

        </ul>
    </div>
</nav>