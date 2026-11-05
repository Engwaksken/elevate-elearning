<!DOCTYPE html>
<html>



<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>E-Learning | Online Editor</title>

    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="font-awesome/css/font-awesome.css" rel="stylesheet">
    <link href="css/animate.css" rel="stylesheet">
    <link href="css/plugins/summernote/summernote.css" rel="stylesheet">
    <link href="css/plugins/summernote/summernote-bs3.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">

</head>

<body>

    <div id="wrapper">

    <nav class="navbar-default navbar-static-side" role="navigation">
        <div class="sidebar-collapse">
            <ul class="nav metismenu" id="side-menu">
                <li class="nav-header">
                  <div class="logo-element">
                      IN+
                  </div>
                 
                </li>
                <li>
                    <a href="index.php"><i class="fa fa-th-large"></i> <span class="nav-label">Home</span> </a>
                   
                </li>
               
             <li>
                    <a class="active" href="editor.php"><i class="landing_link"></i> <span class="nav-label">Text Editor</span> </a>
                   
                </li>
          
                  <ul class="nav nav-second-level collapse">
               
                
                <li></li>
                <li class="landing_link"></li>
                <li class="special_link"></li>
                
                
            </ul>

        </div>
    </nav>

        <div id="page-wrapper" class="gray-bg">
        <div class="row border-bottom"></div>
            <div class="row wrapper border-bottom white-bg page-heading">
                <div class="col-lg-10">
                    <h2>Text Editor</h2>
                    <ol class="breadcrumb">
                        <li>
                            <!--<a href="index-2.html">Home</a> -->
                            <a href="index.php">Home</a>
                        </li>
                        <li>
                            <a>Forms</a>
                        </li>
                        <li class="active">
                            <strong>Text Editor</strong>
                        </li>
                    </ol>
              </div>
                <div class="col-lg-2">

                </div>
          </div>
        <div class="wrapper wrapper-content">

            <div class="row">
                <div class="col-lg-12">
                <div class="ibox float-e-margins">
                    <div class="ibox-title">
                        <h5>E-Learning Online Editor</h5>
                        <div class="ibox-tools">
                            <a class="collapse-link">
                                <i class="fa fa-chevron-up"></i>
                            </a>
                            <a class="dropdown-toggle" data-toggle="dropdown" href="#">
                                <i class="fa fa-wrench"></i>
                            </a>
                            <ul class="dropdown-menu dropdown-user">
                                <li><a href="#">Config option 1</a>
                                </li>
                                <li><a href="#">Config option 2</a>
                                </li>
                            </ul>
                            <a class="close-link">
                                <i class="fa fa-times"></i>
                            </a>
                        </div>
                    </div>
                    <div class="ibox-content no-padding">

                        <div class="summernote">
                            <h3>Welcome to E-Learning Online Editor</h3>
                          Just Feel Free to start coding! <strong>Coding is enjoyable with E-Learning Online Editor</strong> E-Learning is the best interactive online Platform to go with.
                            <br/>
                            <br/>
                            <ul>
                                <li>Learn HTML</li>
                                <li>Learn CSS</li>
                                <li>Learn PHP</li>
                                <li>Learn JavaScript</li>
                            </ul>
                        </div>

                    </div>
                </div>
            </div>
            </div>
           
           

          </div>
       <!-- <div class="footer">
           
            <div>
                <strong>Copyright</strong> E-Learning &copy; 2025
            </div>
        </div> -->

        </div>
        </div>



    <!-- Mainly scripts -->
    <script src="js/jquery-2.1.1.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/plugins/metisMenu/jquery.metisMenu.js"></script>
    <script src="js/plugins/slimscroll/jquery.slimscroll.min.js"></script>

    <!-- Custom and plugin javascript -->
    <script src="js/inspinia.js"></script>
    <script src="js/plugins/pace/pace.min.js"></script>

    <!-- SUMMERNOTE -->
    <script src="js/plugins/summernote/summernote.min.js"></script>

    <script>
        $(document).ready(function(){

            $('.summernote').summernote();

       });
        var edit = function() {
            $('.click2edit').summernote({focus: true});
        };
        var save = function() {
            var aHTML = $('.click2edit').code(); //save HTML If you need(aHTML: array).
            $('.click2edit').destroy();
        };
    </script>

</body>



</html>
