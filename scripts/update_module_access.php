<?php
declare(strict_types=1);
require_once __DIR__ . '/course_content_helpers.php';
try{
    $conn=cc_require_db();[$uid,$role]=cc_current_user();cc_require_instructor_role($role);
    $moduleId=(int)($_POST['module_id']??0);$mode=(string)($_POST['access_mode']??'immediate');if(!in_array($mode,['immediate','after_previous'],true))$mode='immediate';
    $stmt=$conn->prepare("SELECT course_id FROM course_unit WHERE id=? LIMIT 1");$stmt->bind_param('i',$moduleId);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();
    if(!$row||!cc_check_instructor_course_access($conn,$uid,$role,(int)$row['course_id']))cc_json(['success'=>false,'message'=>'Access denied.'],403);
    $stmt=$conn->prepare("UPDATE course_unit SET access_mode=? WHERE id=?");$stmt->bind_param('si',$mode,$moduleId);$stmt->execute();$stmt->close();
    cc_json(['success'=>true,'message'=>'Module access updated.']);
}catch(Throwable $e){cc_json(['success'=>false,'message'=>$e->getMessage()],500);}
