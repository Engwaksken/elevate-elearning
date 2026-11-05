<?php
declare(strict_types=1);
require_once __DIR__ . '/course_content_helpers.php';
try{
    $conn=cc_require_db(); [$uid,$role]=cc_current_user(); cc_require_instructor_role($role);
    $assignmentId=(int)($_GET['assignment_id']??0); if($assignmentId<=0) exit('Invalid assignment.');
    $stmt=$conn->prepare("SELECT a.title,cu.course_id FROM assignments a JOIN course_unit cu ON cu.id=a.course_unit WHERE a.id=? LIMIT 1");
    $stmt->bind_param('i',$assignmentId); $stmt->execute(); $a=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if(!$a||!cc_check_instructor_course_access($conn,$uid,$role,(int)$a['course_id'])){ http_response_code(403); exit('Access denied.'); }
    if(!class_exists('ZipArchive')){ http_response_code(500); exit('ZipArchive PHP extension is required.'); }
    $stmt=$conn->prepare("SELECT hi.*,u.fname,u.lname FROM handed_in hi JOIN users u ON u.unique_id=hi.user_id WHERE hi.assignment_id=? ORDER BY u.fname,u.lname,hi.attempt_number");
    $stmt->bind_param('i',$assignmentId); $stmt->execute(); $res=$stmt->get_result();
    $tmp=tempnam(sys_get_temp_dir(),'asg_zip_'); $zip=new ZipArchive(); if($zip->open($tmp,ZipArchive::OVERWRITE)!==true) throw new RuntimeException('Could not create ZIP file.');
    $baseDir=dirname(__DIR__).'/course_materials/';
    while($r=$res->fetch_assoc()){
        $student=cc_safe_filename(trim(($r['fname']??'').' '.($r['lname']??'')) ?: ('student-'.$r['user_id']));
        $attempt='attempt-'.(int)($r['attempt_number']??1);
        $file=(string)($r['file_uploaded']??'');
        if($file!=='' && is_file($baseDir.$file)) $zip->addFile($baseDir.$file,$student.'/'.$attempt.'/'.basename($file));
        if(!empty($r['text_response'])) $zip->addFromString($student.'/'.$attempt.'/text-response.txt',(string)$r['text_response']);
        if(!empty($r['submission_url'])) $zip->addFromString($student.'/'.$attempt.'/submission-link.txt',(string)$r['submission_url']);
    }
    $stmt->close(); $zip->close();
    $name=cc_safe_filename((string)$a['title']).'-Submissions.zip';
    header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="'.$name.'"'); header('Content-Length: '.filesize($tmp));
    readfile($tmp); @unlink($tmp); exit;
}catch(Throwable $e){ http_response_code(500); echo cc_h($e->getMessage()); }
