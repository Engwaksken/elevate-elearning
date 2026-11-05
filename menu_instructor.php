<?php
$view = $_GET['view'] ?? 'home';

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function active_menu(string $currentView, array $views): string
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
            </li>

            <li<?= active_menu($view, ['', 'home']); ?>>
                <a href="./">
                    <i class="fa fa-home"></i>
                    <span class="nav-label">Home</span>
                </a>
            </li>

            <li<?= active_menu($view, ['todo']); ?>>
                <a href="?view=todo">
                    <i class="fa fa-check-square-o"></i>
                    <span class="nav-label">To-do List</span>
                </a>
            </li>

            <li class="nav-divider"></li>

            <li<?= active_menu($view, ['my_courses']); ?>>
                <a href="?view=my_courses">
                    <i class="fa fa-graduation-cap"></i>
                    <span class="nav-label">My Courses</span>
                </a>
            </li>

            <li<?= active_menu($view, ['mindsets']); ?>>
                <a href="?view=mindsets">
                    <i class="fa fa-seedling"></i>
                    <span class="nav-label">Mindsets</span>
                </a>
            </li>

            <li<?= active_menu($view, ['recommend_certificates']); ?>>
                <a href="?view=recommend_certificates">
                    <i class="fa fa-certificate"></i>
                    <span class="nav-label">Recommend Certificates</span>
                </a>
            </li>

            <li class="nav-divider"></li>

            <li<?= active_menu($view, ['other_resources']); ?>>
                <a href="?view=other_resources">
                    <i class="fa fa-play-circle"></i>
                    <span class="nav-label">Manage Resources</span>
                </a>
            </li>

            <li<?= active_menu($view, ['view_students']); ?>>
                <a href="?view=view_students">
                    <i class="fa fa-users"></i>
                    <span class="nav-label">Manage Students</span>
                </a>
            </li>

            <li<?= active_menu($view, ['chatroom', 'create_chatroom', 'chat', 'chatwith']); ?>>
                <a href="#">
                    <i class="fa fa-comments"></i>
                    <span class="nav-label">Chatroom</span>
                    <span class="fa arrow"></span>
                </a>

                <ul class="nav nav-second-level collapse">
                    <li<?= active_menu($view, ['chatroom', 'chat', 'chatwith']); ?>>
                        <a href="?view=chatroom">
                            <i class="fa fa-list"></i> View Chatrooms
                        </a>
                    </li>
                    <li<?= active_menu($view, ['create_chatroom']); ?>>
                        <a href="?view=create_chatroom">
                            <i class="fa fa-plus-circle"></i> Add Chatroom
                        </a>
                    </li>
                </ul>
            </li>

            <li<?= active_menu($view, ['my_announcements']); ?>>
                <a href="?view=my_announcements">
                    <i class="fa fa-bullhorn"></i>
                    <span class="nav-label">Announcements</span>
                </a>
            </li>

        </ul>
    </div>
</nav>