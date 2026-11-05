<?php
declare(strict_types=1);
require_once __DIR__ . '/course_content_helpers.php';

$uploaded='';
try{
    $conn=cc_require_db(); [$uid,$role]=cc_current_user(); if($role!=='afgezt') cc_json(['success'=>false,'message'=>'Student access required.'],403);
    $assignmentId=(int)($_POST['assignment_id']??0); $type=(string)($_POST['submission_type']??'file');
    if(!in_array($type,['file','text','link'],true)) $type='file';
    $stmt=$conn->prepare("SELECT id,course_unit,date_due,allow_late_submission,max_attempts,submission_type,status FROM assignments WHERE id=? LIMIT 1");
    $stmt->bind_param('i',$assignmentId); $stmt->execute(); $a=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if(!$a||($a['status']??'active')!=='active') cc_json(['success'=>false,'message'=>'Assignment is not available.'],404);
    $allowed=(string)($a['submission_type']??'file');
    $validMap=['file'=>['file'],'text'=>['text'],'link'=>['link'],'file_text'=>['file','text'],'any'=>['file','text','link']];
    if(!in_array($type,$validMap[$allowed]??['file'],true)) cc_json(['success'=>false,'message'=>'This submission type is not allowed for the assignment.'],422);
    $due=!empty($a['date_due'])?strtotime((string)$a['date_due']):false; $late=$due!==false&&time()>$due;
    if($late && empty($a['allow_late_submission'])) cc_json(['success'=>false,'message'=>'The submission deadline has passed.'],422);
    $stmt=$conn->prepare("SELECT COUNT(*) c FROM handed_in WHERE user_id=? AND assignment_id=?"); $stmt->bind_param('si',$uid,$assignmentId); $stmt->execute(); $attempts=(int)($stmt->get_result()->fetch_assoc()['c']??0); $stmt->close();
    $max=max(1,(int)($a['max_attempts']??1)); if($attempts>=$max) cc_json(['success'=>false,'message'=>'Maximum submission attempts reached.'],422);

    $file=''; $text=''; $url='';
    if($type==='file'){
        if(!isset($_FILES['submission_file'])) cc_json(['success'=>false,'message'=>'Please choose a file.'],422);
        $up=cc_upload_file($_FILES['submission_file'],'sub_'); $file=$up['stored']; $uploaded=$up['path'];
    } elseif($type==='text'){
        $text=trim((string)($_POST['text_response']??'')); if($text==='') cc_json(['success'=>false,'message'=>'Please enter your response.'],422);
    } else {
        $url=cc_clean_url((string)($_POST['submission_url']??'')); if($url==='') cc_json(['success'=>false,'message'=>'Please enter a valid URL.'],422);
    }
    $now=date('Y-m-d H:i:s'); $status=$late?'late':'submitted'; $attempt=$attempts+1; $moduleId=(int)$a['course_unit'];
    $stmt=$conn->prepare("INSERT INTO handed_in (user_id,assignment_id,file_uploaded,date_handed_in,submission_type,text_response,submission_url,status,attempt_number) VALUES (?,?,?,?,?,?,?,?,?)");
    if(!$stmt) throw new RuntimeException($conn->error);
    $stmt->bind_param('sissssssi',$uid,$assignmentId,$file,$now,$type,$text,$url,$status,$attempt);
    if(!$stmt->execute()) throw new RuntimeException($stmt->error); $stmt->close();
    cc_json(['success'=>true,'message'=>$late?'Assignment submitted late.':'Assignment submitted successfully.']);
}catch(Throwable $e){ if($uploaded!==''&&is_file($uploaded)) @unlink($uploaded); cc_json(['success'=>false,'message'=>$e->getMessage()],500); }
