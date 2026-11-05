<?php
declare(strict_types=1);
require_once __DIR__ . '/course_content_helpers.php';
try{
    $conn=cc_require_db(); [$uid,$role]=cc_current_user();
    if($role!=='afgezt') cc_json(['success'=>false,'message'=>'Student access required.'],403);
    $topicId=(int)($_POST['topic_id']??0); $complete=!isset($_POST['complete'])||((string)$_POST['complete']!=='0');
    if($topicId<=0) cc_json(['success'=>false,'message'=>'Invalid topic.'],422);
    if($complete){
        $now=date('Y-m-d H:i:s');
        $stmt=$conn->prepare("INSERT INTO topic_completion (user_id,topic_id,completed_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE completed_at=VALUES(completed_at)");
        $stmt->bind_param('sis',$uid,$topicId,$now); $stmt->execute(); $stmt->close();
    }else{
        $stmt=$conn->prepare("DELETE FROM topic_completion WHERE user_id=? AND topic_id=?"); $stmt->bind_param('si',$uid,$topicId); $stmt->execute(); $stmt->close();
    }
    cc_json(['success'=>true,'message'=>$complete?'Topic marked complete.':'Topic marked incomplete.']);
}catch(Throwable $e){ cc_json(['success'=>false,'message'=>$e->getMessage()],500); }
