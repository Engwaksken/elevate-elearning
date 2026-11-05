<?php
$view = isset($_GET['view']) ? $_GET['view'] : '';
?>

   
    <nav class="navbar-default navbar-static-side" role="navigation">
        <div class="sidebar-collapse">
            <ul class="nav metismenu" id="side-menu">
                <li class="nav-header">
                    <div class="dropdown profile-element"> <span>
                    <img alt="image" class="img-circle profile-top" src="<?php echo $dpf ?>" />
                           
                             </span>
                        <a data-toggle="dropdown" class="dropdown-toggle" href="#">
                            <span class="clear"> <span class="block m-t-xs"> <strong class="font-bold"><?php echo $firstname.' '. $lastname ?></strong>
                             </span> <span class="text-muted text-xs block"><?php echo $mytype?> <b class="caret"></b></span> </span> </a>
                        <ul class="dropdown-menu animated fadeInRight m-t-xs">
                            <li><a href="?view=profile">Profile</a></li>
                            
                            <li><a href="?view=edit_profile">Edit Profile</a></li>
                            <li><a href="?view=change_pic">Change D-pic</a></li>
                            <li class="divider"></li>
                            <li><a href="logout.php">Logout</a></li>
                        </ul>
                    </div>
                    <div class="logo-element">
                        WITI
                    </div>
                </li>
                <li <?php if ($_GET['view']=='home' || !isset($_GET['view'])){  echo 'class="active"';} ?>>
                    <a href="?view=home"><i class="fa fa-home"></i> <span class="nav-label">Home</span></a>
                  
                </li>
                <li <?php if ($_GET['view']=='todo'){  echo 'class="active"';} ?>>
                    <a href="?view=todo"><i class="fa fa-th-list"></i> <span class="nav-label">To-do list</span></a>
                   
                </li>
   
             
                  <li <?php if ($_GET['view']=='addrecord'){  echo 'class="active"';} ?>>
                    <a href="?view=addrecord"><i class="fa fa-plus-circle"></i> <span class="nav-label">Add Payment Record</span></a>
                </li>
                 
               
            </ul>

        </div>
    </nav>