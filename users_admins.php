<?php
// -- Fetch all admins + their latest course in ONE query -----------------------
$admins_sql = "
    SELECT
        u.unique_id, u.fname, u.lname, u.email, u.dp, u.verified,
        (
            SELECT c.name
            FROM course_registration cr
            JOIN courses c ON c.id = cr.course_id
            WHERE cr.user_id = u.unique_id
            ORDER BY cr.id DESC
            LIMIT 1
        ) AS course_name
    FROM users u
    WHERE u.acc_type = 'xybrtw'
    ORDER BY u.id DESC
";
$admins_result = $conn->query($admins_sql);
$admin_count   = $admins_result ? $admins_result->num_rows : 0;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="app.css">
</head>
<body>


<!-- -- Page heading ------------------------------------------------------------- -->
<div class="page-heading">
    <div>
        <h2><?php echo htmlspecialchars($systemname); ?> <em>Admins</em></h2>
        <div class="heading-meta">
            <span class="meta-pill"><i class="fa fa-house"></i>
                <a href="?" style="text-decoration:none;color:inherit;">Home</a>
            </span>
            <span class="meta-pill"><i class="fa fa-users"></i> Users</span>
            <span class="meta-pill" style="background:var(--maroon-dim);color:var(--maroon);border-color:rgba(122,25,25,.18);">
                <i class="fa fa-user-shield"></i> Admins
            </span>
        </div>
    </div>
    <span class="count-pill"><?php echo number_format($admin_count); ?> admin<?php echo $admin_count !== 1 ? 's' : ''; ?></span>
</div>

<!-- -- Table card -------------------------------------------------------------- -->
<div class="tbl-card">

    <div class="tbl-toolbar">
        <span class="tbl-title">
            <i class="fa fa-user-shield tbl-title-icon"></i> Administrator Accounts
        </span>
        <span style="font-size:.72rem;color:var(--muted);display:flex;align-items:center;gap:5px;">
            <i class="fa fa-clock" style="color:var(--gold);font-size:.65rem;"></i>
            <?php echo date('h:i a'); ?>
        </span>
    </div>

    <div class="tbl-scroll">
        <table class="app-table">
            <thead>
                <tr>
                    <th style="width:44px;">&nbsp;</th>
                    <th>Name</th>
                    <th>Course</th>
                    <th class="th-gold">Email</th>
                    <th class="th-gold">Status</th>
                    <th class="th-gold" style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$admins_result || $admin_count < 1): ?>
                <tr>
                    <td colspan="6" style="padding:0;">
                        <div class="empty" style="border:none;border-radius:0;">
                            <i class="fa fa-user-shield"></i>
                            <p>No administrator accounts found.</p>
                        </div>
                    </td>
                </tr>
            <?php else:
                while ($d = $admins_result->fetch_assoc()):
                    $is_self   = ($d['unique_id'] === $unik);
                    $verified  = ($d['verified'] === 'yes');
                    $dp        = $d['dp'];
                    $avatar    = ($dp === 'default.jpg' || empty($dp))
                                    ? 'images/default_p_pics/user.png'
                                    : 'images/user_p_pics/' . $dp;
                    $course    = $d['course_name'] ?? '-';
            ?>
                <tr>
                    <!-- Avatar -->
                    <td style="width:44px;">
                        <div class="user-cell-avatar" style="width:36px;height:36px;cursor:pointer;"
                             onclick="openuser('<?php echo htmlspecialchars($d['unique_id']); ?>')">
                            <img src="<?php echo htmlspecialchars($avatar); ?>"
                                 alt="<?php echo htmlspecialchars($d['fname']); ?>"
                                 style="width:100%;height:100%;object-fit:cover;">
                        </div>
                    </td>

                    <!-- Name -->
                    <td>
                        <span class="fw-600"
                              style="color:var(--text);font-size:.86rem;cursor:pointer;"
                              onclick="openuser('<?php echo htmlspecialchars($d['unique_id']); ?>')">
                            <?php echo htmlspecialchars($d['fname'] . ' ' . $d['lname']); ?>
                        </span>
                    </td>

                    <!-- Course -->
                    <td class="cell-sm"><?php echo htmlspecialchars($course); ?></td>

                    <!-- Email -->
                    <td>
                        <span class="user-email" style="font-size:.78rem;">
                            <i class="fa fa-envelope"></i>
                            <?php echo htmlspecialchars($d['email']); ?>
                        </span>
                    </td>

                    <!-- Verified badge -->
                    <td>
                        <?php if ($verified): ?>
                            <span class="s-badge s-graded">
                                <i class="fa fa-check"></i> Active
                            </span>
                        <?php else: ?>
                            <span class="s-badge s-pending">
                                <i class="fa fa-ban"></i> Inactive
                            </span>
                        <?php endif; ?>
                    </td>

                    <!-- Row actions -->
                    <td>
                        <div class="row-actions" style="justify-content:flex-end;">

                            <!-- Toggle verified -->
                            <?php if ($verified): ?>
                                <a href="deactivate?id=<?php echo urlencode($d['unique_id']); ?>&xts=view_admin"
                                   class="ia-btn ia-del"
                                   title="Deactivate">
                                    <i class="fa fa-ban"></i>
                                </a>
                            <?php else: ?>
                                <a href="activate?id=<?php echo urlencode($d['unique_id']); ?>&xts=view_admin"
                                   class="ia-btn ia-dl"
                                   title="Activate">
                                    <i class="fa fa-check"></i>
                                </a>
                            <?php endif; ?>

                            <!-- Chat (not self) -->
                            <?php if (!$is_self): ?>
                                <a href="?view=chatwith&pr=<?php echo urlencode($d['unique_id']); ?>"
                                   class="ia-btn ia-view"
                                   title="Message">
                                    <i class="fa fa-comment"></i>
                                </a>
                            <?php endif; ?>

                            <!-- Edit -->
                            <a href="?view=edit_admin&id=<?php echo urlencode($d['unique_id']); ?>"
                               class="ia-btn"
                               style="border-color:var(--gold);color:var(--gold);"
                               title="Edit">
                                <i class="fa fa-pen"></i>
                            </a>

                        </div>
                    </td>
                </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="tbl-footer">
        <div class="legend">
            <span class="legend-item">
                <span class="legend-dot legend-dot-green"></span> Active
            </span>
            <span class="legend-item">
                <span class="legend-dot" style="background:var(--red);"></span> Inactive / unverified
            </span>
        </div>
        <span><?php echo number_format($admin_count); ?> total admin<?php echo $admin_count !== 1 ? 's' : ''; ?></span>
    </div>

</div>

<!-- -- Side panel (populated by openuser()) ------------------------------------ -->
<div id="showit" style="margin-top:20px;"></div>