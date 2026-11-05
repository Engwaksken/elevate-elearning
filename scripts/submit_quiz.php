<?php
declare(strict_types=1);
require_once __DIR__ . '/course_content_helpers.php';
try{
    $conn=cc_require_db(); [$uid,$role]=cc_current_user(); if($role!=='afgezt') cc_json(['success'=>false,'message'=>'Student access required.'],403);
    $quizId=(int)($_POST['quiz_id']??0); if($quizId<=0) cc_json(['success'=>false,'message'=>'Invalid quiz.'],422);
    $stmt=$conn->prepare("SELECT * FROM module_quizzes WHERE id=? AND status='active' LIMIT 1");$stmt->bind_param('i',$quizId);$stmt->execute();$quiz=$stmt->get_result()->fetch_assoc();$stmt->close();if(!$quiz)cc_json(['success'=>false,'message'=>'Quiz not available.'],404);
    $stmt=$conn->prepare("SELECT COUNT(*) c FROM quiz_attempts WHERE quiz_id=? AND user_id=?");$stmt->bind_param('is',$quizId,$uid);$stmt->execute();$attemptNo=(int)($stmt->get_result()->fetch_assoc()['c']??0)+1;$stmt->close();if($attemptNo>(int)$quiz['max_attempts'])cc_json(['success'=>false,'message'=>'Maximum attempts reached.'],422);
    $qs=[];$stmt=$conn->prepare("SELECT * FROM quiz_questions WHERE quiz_id=? ORDER BY order_number,id");$stmt->bind_param('i',$quizId);$stmt->execute();$r=$stmt->get_result();while($q=$r->fetch_assoc())$qs[]=$q;$stmt->close();
    $now=date('Y-m-d H:i:s');$score=0;$total=0;$conn->begin_transaction();
    $stmt=$conn->prepare("INSERT INTO quiz_attempts (quiz_id,user_id,attempt_number,score,percentage,status,started_at,submitted_at) VALUES (?,?,?,0,0,'submitted',?,?)");$stmt->bind_param('isiss',$quizId,$uid,$attemptNo,$now,$now);$stmt->execute();$attemptId=$stmt->insert_id;$stmt->close();
    foreach($qs as $q){$qid=(int)$q['id'];$pts=(float)$q['points'];$total+=$pts;$ansText='';$optionId=null;$aw=0;$correct=null;
        if(in_array($q['question_type'],['multiple_choice','true_false'],true)){$optionId=(int)($_POST['q_'.$qid]??0);$s=$conn->prepare("SELECT is_correct FROM quiz_options WHERE id=? AND question_id=? LIMIT 1");$s->bind_param('ii',$optionId,$qid);$s->execute();$o=$s->get_result()->fetch_assoc();$s->close();$correct=$o?(int)$o['is_correct']:0;if($correct){$aw=$pts;$score+=$pts;}}
        else{$ansText=trim((string)($_POST['q_'.$qid]??''));$expected=trim((string)($q['correct_answer']??''));$correct=$expected!==''&&strcasecmp($ansText,$expected)===0?1:0;if($correct){$aw=$pts;$score+=$pts;}}
        $stmt=$conn->prepare("INSERT INTO quiz_answers (attempt_id,question_id,option_id,answer_text,awarded_points,is_correct) VALUES (?,?,NULLIF(?,0),?,?,?)");$stmt->bind_param('iiisdi',$attemptId,$qid,$optionId,$ansText,$aw,$correct);$stmt->execute();$stmt->close();
    }
    $pct=$total>0?round($score/$total*100,2):0;$stmt=$conn->prepare("UPDATE quiz_attempts SET score=?,percentage=?,status='graded' WHERE id=?");$stmt->bind_param('ddi',$score,$pct,$attemptId);$stmt->execute();$stmt->close();$conn->commit();
    cc_json(['success'=>true,'message'=>'Quiz submitted.','score'=>$score,'total'=>$total,'percentage'=>$pct,'passed'=>$pct>=(float)$quiz['passing_score']]);
}catch(Throwable $e){if(isset($conn)&&$conn instanceof mysqli){try{$conn->rollback();}catch(Throwable){}}cc_json(['success'=>false,'message'=>$e->getMessage()],500);}
