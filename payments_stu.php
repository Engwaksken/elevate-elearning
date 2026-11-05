<div class="wrapper wrapper-content">
<div class="alert alert-success alert-dismissable">
                                <button aria-hidden="true" data-dismiss="alert" class="close" type="button">×</button>
                                Having any communication about accounts/payments? <br /> Please send an email to the accounts department. <a class="alert-link" href="mailto:<?php echo $accountsemail ?>"><?php echo $accountsemail ?></a>.
                            </div>

<?php

//$stdid=strip_tags(trim($_GET["fsajdgafsduygoilgy"]));

if($unik==""){
echo '<div class="alert alert-danger">
                                Invalid Student<a class="alert-link" href="?view=home">Return Home</a>.
                            </div>';	
}else{
	
	$a = "SELECT * FROM users WHERE `unique_id` = '".$unik."' ORDER BY id DESC";
 $b = $conn->query($a)or die (mysqli_error());
 $d= mysqli_fetch_array($b);
 $fname=$d['fname'];
  $lname=$d['lname'];
	
echo '<h4 style="font-size: 35px;
    font-weight: 700;
    color: #690303;"><span  style="color: #fd9017;
    font-weight: 400;
    font-style: italic;">'.$fname.' '.$lname.'\'s</span> account records</h4>'
	

?>
<table class="table">
  <thead>
    <tr>
      <th scope="col">#</th>
      
      <th scope="col">Amount Paid</th>
      <th scope="col">Balance</th>
      <th scope="col">Date Paid</th>
    </tr>
  </thead>
  <tbody>
<?php 


 $pagenum=$_GET['pagenum'];
 
 //This checks to see if there is a page number. If not, it will set it to page 1 

 if (!(isset($pagenum))) 
 { 
 $pagenum = 1; 
 } 

 //Here we count the number of results 

 //Edit $data to be your query 
 $data = "SELECT * FROM `accounts` WHERE `user_id`='".$unik."'"; 
 $data = $conn->query($data)or die (mysqli_error());
 $rows=$data->num_rows;
 
 if ($rows>0){
 //$rows = mysqli_num_rows($data); 
 

 //This is the number of results displayed per page 
 $page_rows = 50; 

 //This tells us the page number of our last page 
 $last = ceil($rows/$page_rows); 

 //this makes sure the page number isn't below one, or more than our maximum pages 
 if ($pagenum < 1) 
 { 
 $pagenum = 1; 
 } 
 elseif ($pagenum > $last) 
 { 
 $pagenum = $last; 
 } 

 //This sets the range to display in our query 
 $pagenum-1;
 $max = 'limit ' .($pagenum - 1) * $page_rows .',' .$page_rows; 
 
 //This is your query again, the same one... the only difference is we add $max into it

 //$data_p = mysqli_query("SELECT * FROM `accounts` WHERE `user_id`='".$unik."' $max") or die(mysql_error()); 
 $da = "SELECT * FROM `accounts` WHERE `user_id`='".$unik."' ORDER BY `id` DESC ".$max.""; 
 $data_p = $conn->query($da)or die (mysqli_error());


 //This is where you display your query results
$cou=1;
 while($info = mysqli_fetch_array( $data_p )) 

 { 
 $userid=$info["user_id"];
 $amount=$info["amount_received"];
 $balance=$info["balance"];
  $date=$info["date_received"];
 $checksystem = "SELECT * FROM `users` WHERE `unique_id`='".$userid."'";
 $check = $conn->query($checksystem)or die (mysqli_error());
 $ccc = mysqli_fetch_array( $check );
 $name=$ccc['fname'].' '.$ccc['lname'];
 
 echo'<tr>
      <th scope="row">'.$cou.'</th>
      <td>$'.$amount.'</td>
      <td>$'.$balance.'</td>
	  <td>'.date('l d-m-Y h:m a', strtotime($date)).'</td>
    </tr>';

 //Print $info['heading']; 

$cou++;

 } 

 echo " </tbody>
</table><p>";

 
 // This shows the user what page they are on, and the total number of pages

 echo '<span class="badge badge-info">--Page '.$pagenum.' of '.$last.'--</span>  <p>';

 
 // First we check if we are on page one. If we are then we don't need a link to the previous page or the first page so we do nothing. If we aren't then we generate links to the first page, and to the previous page.

 if ($pagenum == 1) 

 {

 } 

 else 

 {

 echo " <a class='btn btn-primary btn-xs' href='{$_SERVER['PHP_SELF']}?pagenum=1'> <<-First</a> ";

 echo " ";

 $previous = $pagenum-1;

 echo " <a class='btn btn-primary btn-xs' href='{$_SERVER['PHP_SELF']}?pagenum=$previous'> <-Previous</a> ";

 } 



 //This does the same as above, only checking if we are on the last page, and then generating the Next and Last links

 if ($pagenum == $last) 

 {

 } 

 else {

 $next = $pagenum+1;

 echo " <a class='btn btn-primary btn-xs' href='{$_SERVER['PHP_SELF']}?pagenum=$next'>Next -></a> ";

 echo " ";

 echo " <a  href='{$_SERVER['PHP_SELF']}?pagenum=$last' class='btn btn-primary btn-xs'>Last ->></a> ";

 } 
 
 //if rows greater than 0
 } else{
	echo '<div class="alert alert-warning">
                                No payments made yet <a class="alert-link" href="?view=home">Return Home</a>.
                            </div>'; 
 }

}
 ?> 
 </div>