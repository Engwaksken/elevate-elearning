<?php
declare(strict_types=1);
require_once __DIR__ . '/course_content_helpers.php';
try {
    $conn=cc_require_db(); [$uid,$role]=cc_current_user(); cc_require_instructor_role($role);
    $id=(int)($_POST['id']??0); if($id<=0) cc_json(['success'=>false,'message'=>'Invalid material.'],422);
    $stmt=$conn->prepare("SELECT tm.document,t.course_id FROM topic_materials tm JOIN course_topics t ON t.id=tm.topic_id WHERE tm.id=? LIMIT 1");
    $stmt->bind_param('i',$id); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if(!$row || !cc_check_instructor_course_access($conn,$uid,$role,(int)$row['course_id'])) cc_json(['success'=>false,'message'=>'Access denied.'],403);
    $stmt=$conn->prepare("DELETE FROM topic_materials WHERE id=?"); $stmt->bind_param('i',$id); $stmt->execute(); $stmt->close();
    $doc=(string)($row['document']??''); if($doc!==''){ $p=dirname(__DIR__).'/course_materials/'.$doc; if(is_file($p)) @unlink($p); }
    cc_json(['success'=>true,'message'=>'Material deleted.']);
} catch(Throwable $e){ cc_json(['success'=>false,'message'=>$e->getMessage()],500); }
