<?php
declare(strict_types=1);
require_once __DIR__ . '/course_content_helpers.php';
try{
    $conn=cc_require_db(); [$uid,$role]=cc_current_user(); cc_require_instructor_role($role);
    $quizId=(int)($_POST['quiz_id']??0); $type=(string)($_POST['question_type']??'multiple_choice');
    $text=trim((string)($_POST['question_text']??'')); $points=max(0,(float)($_POST['points']??1)); $correct=trim((string)($_POST['correct_answer']??''));
    if(!in_array($type,['multiple_choice','true_false','short_answer'],true)) $type='multiple_choice';
    if($quizId<=0||$text==='') cc_json(['success'=>false,'message'=>'Quiz and question text are required.'],422);
    $stmt=$conn->prepare("SELECT q.course_id FROM module_quizzes q WHERE q.id=? LIMIT 1");$stmt->bind_param('i',$quizId);$stmt->execute();$q=$stmt->get_result()->fetch_assoc();$stmt->close();
    if(!$q||!cc_check_instructor_course_access($conn,$uid,$role,(int)$q['course_id'])) cc_json(['success'=>false,'message'=>'Access denied.'],403);
    $stmt=$conn->prepare("SELECT COALESCE(MAX(order_number),0)+1 n FROM quiz_questions WHERE quiz_id=?");$stmt->bind_param('i',$quizId);$stmt->execute();$order=(int)($stmt->get_result()->fetch_assoc()['n']??1);$stmt->close();
    $now=date('Y-m-d H:i:s');
    $stmt=$conn->prepare("INSERT INTO quiz_questions (quiz_id,question_type,question_text,points,order_number,correct_answer,created_at) VALUES (?,?,?,?,?,?,?)");$stmt->bind_param('issdiss',$quizId,$type,$text,$points,$order,$correct,$now);$stmt->execute();$qid=$stmt->insert_id;$stmt->close();
    if($type==='multiple_choice'){
        $opts=$_POST['options']??[];$correctIndex=(int)($_POST['correct_option']??-1); if(!is_array($opts))$opts=[];
        foreach($opts as $i=>$opt){$opt=trim((string)$opt);if($opt==='')continue;$is=$i===$correctIndex?1:0;$ord=$i+1;$stmt=$conn->prepare("INSERT INTO quiz_options (question_id,option_text,is_correct,order_number) VALUES (?,?,?,?)");$stmt->bind_param('isii',$qid,$opt,$is,$ord);$stmt->execute();$stmt->close();}
    } elseif($type==='true_false'){
        foreach(['True','False'] as $i=>$opt){$is=(strtolower($correct)==strtolower($opt))?1:0;$ord=$i+1;$stmt=$conn->prepare("INSERT INTO quiz_options (question_id,option_text,is_correct,order_number) VALUES (?,?,?,?)");$stmt->bind_param('isii',$qid,$opt,$is,$ord);$stmt->execute();$stmt->close();}
    }
    cc_json(['success'=>true,'message'=>'Question added successfully.']);
}catch(Throwable $e){cc_json(['success'=>false,'message'=>$e->getMessage()],500);}
