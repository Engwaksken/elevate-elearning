<?php
declare(strict_types=1);
require_once __DIR__ . '/course_content_helpers.php';
try{
    $conn=cc_require_db(); [$uid,$role]=cc_current_user(); cc_require_instructor_role($role);
    $courseId=(int)($_POST['course_id']??0); $moduleId=(int)($_POST['course_unit_id']??0); $intakeId=(int)($_POST['intake_id']??0);
    $title=trim((string)($_POST['title']??'')); $instructions=trim((string)($_POST['instructions']??''));
    $points=max(0,(float)($_POST['total_points']??0)); $passing=min(100,max(0,(float)($_POST['passing_score']??70)));
    $attempts=max(1,(int)($_POST['max_attempts']??1)); $deadlineRaw=trim((string)($_POST['deadline']??''));
    $deadline=$deadlineRaw!==''&&strtotime($deadlineRaw)!==false?date('Y-m-d H:i:s',strtotime($deadlineRaw)):null;
    $required=!empty($_POST['required_for_completion'])?1:0; $classMode=cc_valid_class_mode((string)($_POST['class_mode']??'both')); $status=cc_valid_status((string)($_POST['status']??'active'));
    if($courseId<=0||$moduleId<=0||$title==='') cc_json(['success'=>false,'message'=>'Course, module and quiz title are required.'],422);
    if(!cc_check_instructor_course_access($conn,$uid,$role,$courseId)||!cc_module_belongs_to_course($conn,$moduleId,$courseId)) cc_json(['success'=>false,'message'=>'Access denied.'],403);
    $now=date('Y-m-d H:i:s');
    $stmt=$conn->prepare("INSERT INTO module_quizzes (course_id,course_unit_id,intake_id,title,instructions,total_points,passing_score,max_attempts,deadline,required_for_completion,class_mode,status,created_by,created_at) VALUES (?,?,NULLIF(?,0),?,?,?,?,?,?,?,?,?,?,?)");
    if(!$stmt) throw new RuntimeException($conn->error);
    $stmt->bind_param('iiissddisissss',$courseId,$moduleId,$intakeId,$title,$instructions,$points,$passing,$attempts,$deadline,$required,$classMode,$status,$uid,$now);
    if(!$stmt->execute()) throw new RuntimeException($stmt->error); $id=$stmt->insert_id; $stmt->close();
    cc_json(['success'=>true,'message'=>'Quiz created. Add questions from the quiz builder.','quiz_id'=>$id]);
}catch(Throwable $e){ cc_json(['success'=>false,'message'=>$e->getMessage()],500); }
