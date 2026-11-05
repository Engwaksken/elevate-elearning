<?php
declare(strict_types=1);
require_once __DIR__ . '/course_content_helpers.php';
try{
    $conn=cc_require_db(); [$uid,$role]=cc_current_user(); cc_require_instructor_role($role);
    $id=(int)($_POST['submission_id']??0); $grade=(float)($_POST['grade']??-1); $feedback=trim((string)($_POST['feedback']??''));
    if($id<=0||$grade<0) cc_json(['success'=>false,'message'=>'Submission and grade are required.'],422);
    $stmt=$conn->prepare("SELECT hi.id,a.marks,a.course_unit,cu.course_id FROM handed_in hi JOIN assignments a ON a.id=hi.assignment_id JOIN course_unit cu ON cu.id=a.course_unit WHERE hi.id=? LIMIT 1");
    $stmt->bind_param('i',$id); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if(!$row||!cc_check_instructor_course_access($conn,$uid,$role,(int)$row['course_id'])) cc_json(['success'=>false,'message'=>'Access denied.'],403);
    $max=(float)$row['marks']; if($max>0&&$grade>$max) cc_json(['success'=>false,'message'=>"Grade cannot exceed {$max}."],422);
    $now=date('Y-m-d H:i:s');
    $stmt=$conn->prepare("UPDATE handed_in SET finalmarks=?,feedback=?,status='graded',graded_by=?,graded_at=? WHERE id=?");
    $stmt->bind_param('dsssi',$grade,$feedback,$uid,$now,$id); if(!$stmt->execute()) throw new RuntimeException($stmt->error); $stmt->close();
    cc_json(['success'=>true,'message'=>'Grade and feedback returned to the student.']);
}catch(Throwable $e){ cc_json(['success'=>false,'message'=>$e->getMessage()],500); }
