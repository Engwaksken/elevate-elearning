<div class="wrapper wrapper-content">
<div class="col-lg-5">
                    <div class="ibox float-e-margins">
                        <div class="ibox-title">
                            <h5>Add Student Payment</h5>
                            <div class="ibox-tools">
                                <a class="collapse-link">
                                    <i class="fa fa-chevron-up"></i>
                                </a>
                                <a class="dropdown-toggle" data-toggle="dropdown" href="#">
                                    <i class="fa fa-wrench"></i>
                                </a>
                               
                                
                            </div>
                        </div>
                        <div class="ibox-content">
                            <form class="form-horizontal" method="post" action="scripts/addpayment.php">
                                <p></p>
                                <div class="form-group"><label class="col-lg-2 control-label">Student Number (eg. 18/WITI/0000)</label>

                                    <div class="col-lg-10"><input type="text" id="stdnumber" onKeyUp="checkit()" name="stdnumber" placeholder="Student Number" class="form-control" required> 
                                    </div>
                                </div>
                                <span id="report"></span>
                                <div class="form-group"><label class="col-lg-2 control-label">Amount (in $)</label>

                                    <div class="col-lg-10"><input type="number" name="amount" min="0" onkeypress='validate(event)' placeholder="Amount paid" class="form-control" required></div>
                                </div>
                                
                                <div class="form-group"><label class="col-lg-2 control-label">Balance (in $)</label>

                                    <div class="col-lg-10"><input type="number" name="balance" min="0" onkeypress='validate(event)' placeholder="Tuition Balance" class="form-control" required></div>
                                </div>
                                
                                 <div class="form-group"><label class="col-lg-2 control-label">Comment</label>

                                    <div class="col-lg-10"><textarea name="comment" class="form-control" rows="5" required></textarea></div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="col-lg-offset-2 col-lg-10">
                                        <button class="btn btn-sm btn-info" type="submit" name="manzido">Submit Payment</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                
                </div>
                
                <script>
function checkit(){

  

      var numberx = $("#stdnumber").val().trim();

      if(numberx != ''){

         $("#report").show();

         $.ajax({
            url: 'scripts/checkstudentnumber.php',
            type: 'post',
            data: {number:numberx},
            success: function(response){

                if(response != 0){
                    $("#report").html(response);
                }else{
                    $("#report").html("<span class='badge badge-primary'>Student Not Found</span>");
                }

             }
          });
      }else{
         $("#report").hide();
      }



 }
 
 
 
 
 
 function validate(evt) {
  var theEvent = evt || window.event;
  var key = theEvent.keyCode || theEvent.which;
  key = String.fromCharCode( key );
  var regex = /[0-9]|\./;
  if( !regex.test(key) ) {
    theEvent.returnValue = false;
    if(theEvent.preventDefault) theEvent.preventDefault();
  }
}
</script>