<?php
session_start();
require 'connector.php';


if ( (!isset($_GET['intake']))||(!isset($_GET['vf']))){
	
	header("Location: ../index.php?view=intakes");
	die();
	
}else{
$intake = trim($_GET['intake']);
$vf = trim($_GET['vf']);

$checksystemxx = "SELECT * FROM `intakes` WHERE `id`='".$intake."'";
 $checkxx = $conn->query($checksystemxx)or die (mysqli_error());
  $dataxx= mysqli_fetch_array($checkxx);
	   $name= $dataxx["name"];
	   $type= $dataxx["type"];
	   
	     if($type=="1"){
			   $thetype="Short Course";
		   }else{
			   $thetype="Diploma";
		   }
 
 
 $sql = "DELETE FROM `intakes` WHERE `id`='".$intake."'";
	
            $result = $conn->query($sql)or die (mysqli_error());     
            
            if($result){
			

			$_SESSION[$systemname."successmsg"] = $name." ".$thetype." removed";
               header("Location: ../index.php?view=".$vf."");   
	die(); 			
			}else{
				$_SESSION[$systemname."errormsg"] = "Unknown error";
               header("Location: ../index.php?view=".$vf."");   
	die(); 
			}

   



}




?>