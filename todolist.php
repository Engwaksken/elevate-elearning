<link href="css/plugins/datapicker/datepicker3.css" rel="stylesheet">
<div class="row">
            <div class="col-lg-12">
                <div class="wrapper wrapper-content animated fadeInUp">
                
                    <ul class="notes">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#myModal5">
                                    Add Note
                                </button>
                    <?php
					$statement="SELECT * FROM `todolist` WHERE `user_id`='".$_SESSION[$systemname."leido"]."' AND `valid`='yes' ORDER BY `id` DESC";
					 $checkz = $conn->query($statement)or die (mysqli_error());
					 if ($checkz->num_rows < 1) {
						 echo'<div class="alert alert-danger">
                                To-do List is empty <a class="alert-link" href="?view=home">Return Home</a>.
                            </div>';
					 }else{
						 while($d= mysqli_fetch_array($checkz)){
							 echo'   <li>
                            <div>
                                <small>'.$d["date_due"].'</small>
                                <h4>'.$d["title"].'</h4>
                                <p>'.$d["details"].'</p>
                                <a href="scripts/deletenote.php?note='.$d["id"].'"><i class="fa fa-trash-o "></i></a>
                            </div>
                        </li>';
							 
						 }
						 
					 }
					?>
                     
                     
                    </ul>
                </div>
            </div>
        </div>
        
        
        <div class="modal inmodal fade" id="myModal5" tabindex="-1" role="dialog" aria-hidden="true" style="display: none;">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span><span class="sr-only">Close</span></button>
                                            <h4 class="modal-title">Add Note</h4>
                                            <small class="font-bold">To-do list allows you to keep track of your progress</small>
                                        </div>
                                        <div class="modal-body">
                                            
                                            <form action="scripts/addnote.php" method="post">
                                            
                                             <div class="form-group">
                 <input type="text" class="form-control" name="title" placeholder="Title" required="required">
                 </div>
                       <div class="form-group">
                 <label>Details *</label>
                
                 <textarea class="form-control" rows="5" name="details" required="required"></textarea>
                               
                                </div>
                                            
                                            <div class="form-group">
                 <label>Date Due *</label>
                
                                   <input type="date" class="form-control" value="" placeholder="Date Due" id="datedue" name="datedue" min="<?php echo $now ?>" required="" >
                               
                                </div>
                                
                                             <button type="submit" class="btn btn-primary">Add Note</button>
                                            </form>
                                        </div>

                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-white" data-dismiss="modal">Close</button>
                                           
                                        </div>
                                    </div>
                                </div>
                            </div>