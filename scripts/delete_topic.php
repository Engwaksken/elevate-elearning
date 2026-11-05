<?php
declare(strict_types=1);
require_once __DIR__ . '/course_content_helpers.php';
try {
    $conn = cc_require_db();
    [$uid, $role] = cc_current_user();
    cc_require_instructor_role($role);
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) cc_json(['success'=>false,'message'=>'Invalid topic.'],422);

    $stmt = $conn->prepare("SELECT course_id FROM course_topics WHERE id=? LIMIT 1");
    $stmt->bind_param('i',$id); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$row || !cc_check_instructor_course_access($conn,$uid,$role,(int)$row['course_id'])) cc_json(['success'=>false,'message'=>'Access denied.'],403);

    $files=[];
    $stmt=$conn->prepare("SELECT document FROM topic_materials WHERE topic_id=? AND document IS NOT NULL AND document<>''");
    $stmt->bind_param('i',$id); $stmt->execute(); $res=$stmt->get_result(); while($r=$res->fetch_assoc()) $files[]=(string)$r['document']; $stmt->close();
    $stmt=$conn->prepare("DELETE FROM course_topics WHERE id=?"); $stmt->bind_param('i',$id); $stmt->execute(); $stmt->close();
    foreach($files as $f){ $p=dirname(__DIR__).'/course_materials/'.$f; if(is_file($p)) @unlink($p); }
    cc_json(['success'=>true,'message'=>'Topic deleted.']);
} catch(Throwable $e){ cc_json(['success'=>false,'message'=>$e->getMessage()],500); }
