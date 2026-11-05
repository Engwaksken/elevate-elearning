<?php
declare(strict_types=1);

function course_module_completion(mysqli $conn, string $userId, int $moduleId, int $intakeId): array
{
    $requiredTopics = 0; $doneTopics = 0;
    $stmt = $conn->prepare("SELECT COUNT(*) total, SUM(EXISTS(SELECT 1 FROM topic_completion tc WHERE tc.topic_id=t.id AND tc.user_id=?)) done FROM course_topics t WHERE t.course_unit_id=? AND COALESCE(t.intake_id,0) IN (0,?) AND t.status='active' AND t.is_required=1");
    if ($stmt) {
        $stmt->bind_param('sii', $userId, $moduleId, $intakeId);
        $stmt->execute(); $r=$stmt->get_result()->fetch_assoc(); $stmt->close();
        $requiredTopics=(int)($r['total']??0); $doneTopics=(int)($r['done']??0);
    }

    $requiredAssignments = 0; $doneAssignments = 0;
    $stmt = $conn->prepare("SELECT COUNT(*) total, SUM(EXISTS(SELECT 1 FROM handed_in hi WHERE hi.assignment_id=a.id AND hi.user_id=? AND hi.status IN ('submitted','late','graded','returned'))) done FROM assignments a WHERE a.course_unit=? AND a.intake=? AND COALESCE(a.status,'active')='active' AND COALESCE(a.required_for_completion,0)=1");
    if ($stmt) {
        $stmt->bind_param('sii',$userId,$moduleId,$intakeId);$stmt->execute();$r=$stmt->get_result()->fetch_assoc();$stmt->close();
        $requiredAssignments=(int)($r['total']??0);$doneAssignments=(int)($r['done']??0);
    }

    $requiredQuizzes = 0; $doneQuizzes = 0;
    $stmt = $conn->prepare("SELECT q.id,q.passing_score,COALESCE(MAX(qa.percentage),-1) best FROM module_quizzes q LEFT JOIN quiz_attempts qa ON qa.quiz_id=q.id AND qa.user_id=? WHERE q.course_unit_id=? AND COALESCE(q.intake_id,0) IN (0,?) AND q.status='active' AND q.required_for_completion=1 GROUP BY q.id,q.passing_score");
    if ($stmt) {
        $stmt->bind_param('sii',$userId,$moduleId,$intakeId);$stmt->execute();$res=$stmt->get_result();
        while($r=$res->fetch_assoc()){ $requiredQuizzes++; if((float)$r['best'] >= (float)$r['passing_score']) $doneQuizzes++; }
        $stmt->close();
    }

    $required=$requiredTopics+$requiredAssignments+$requiredQuizzes;
    $done=$doneTopics+$doneAssignments+$doneQuizzes;
    return [
        'required'=>$required,
        'done'=>$done,
        'complete'=>$required===0 || $done>=$required,
        'topics'=>[$doneTopics,$requiredTopics],
        'assignments'=>[$doneAssignments,$requiredAssignments],
        'quizzes'=>[$doneQuizzes,$requiredQuizzes],
    ];
}
