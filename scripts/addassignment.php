<?php
declare(strict_types=1);
require_once __DIR__ . '/course_content_helpers.php';
require_once __DIR__ . '/EmailService.php';

$uploadedFiles = [];
try {
    $conn = cc_require_db();
    [$uid, $role] = cc_current_user();
    cc_require_instructor_role($role);

    $courseId = (int)($_POST['course_id'] ?? 0);
    $moduleId = (int)($_POST['course_unit_id'] ?? 0);
    $intakeId = (int)($_POST['intake_id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? ''));
    $instructions = trim((string)($_POST['instructions'] ?? $_POST['short_message'] ?? ''));
    $marks = max(0, (float)($_POST['marks'] ?? 0));
    $dueRaw = trim((string)($_POST['date_due'] ?? ''));
    $submissionType = (string)($_POST['submission_type'] ?? 'file');
    $required = !empty($_POST['required_for_completion']) ? 1 : 0;
    $allowLate = !empty($_POST['allow_late_submission']) ? 1 : 0;
    $maxAttempts = max(1, (int)($_POST['max_attempts'] ?? 1));
    $classMode = cc_valid_class_mode((string)($_POST['class_mode'] ?? 'both'));
    $status = cc_valid_status((string)($_POST['status'] ?? 'active'));

    if (!in_array($submissionType, ['file','text','link','file_text','any'], true)) $submissionType = 'file';
    if ($courseId <= 0 || $moduleId <= 0 || $title === '' || $instructions === '' || $dueRaw === '') {
        cc_json(['success'=>false,'message'=>'Module, title, instructions and due date are required.'],422);
    }
    $ts = strtotime($dueRaw);
    if ($ts === false) cc_json(['success'=>false,'message'=>'Invalid due date.'],422);
    $due = date('Y-m-d H:i:s',$ts);

    if (!cc_check_instructor_course_access($conn,$uid,$role,$courseId) || !cc_module_belongs_to_course($conn,$moduleId,$courseId)) {
        cc_json(['success'=>false,'message'=>'Invalid or unauthorised course/module.'],403);
    }

    $now = date('Y-m-d H:i:s');
    $dummy = $intakeId > 0 ? 'no' : 'yes';
    $contentType = 'none';
    $document = '';
    $assignmentUrl = '';
    $fileType = 3;
    $expectedType = 3;

    $conn->begin_transaction();
    $stmt = $conn->prepare("INSERT INTO assignments (document,content_type,assignment_url,title,marks,file_type,course_unit,short_message,uploaded_by,date_uploaded,date_due,expectedtype,dummy,intake,class_mode,status,submission_type,required_for_completion,allow_late_submission,max_attempts,published_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    if(!$stmt) throw new RuntimeException($conn->error);
    $stmt->bind_param('ssssdiissssisisssiiis',$document,$contentType,$assignmentUrl,$title,$marks,$fileType,$moduleId,$instructions,$uid,$now,$due,$expectedType,$dummy,$intakeId,$classMode,$status,$submissionType,$required,$allowLate,$maxAttempts,$now);
    if(!$stmt->execute()) throw new RuntimeException($stmt->error);
    $assignmentId = $stmt->insert_id;
    $stmt->close();

    $resourceTitles = $_POST['resource_title'] ?? [];
    $resourceTypes = $_POST['resource_type'] ?? [];
    $resourceUrls = $_POST['resource_url'] ?? [];
    $resourceTexts = $_POST['resource_text'] ?? [];
    if (!is_array($resourceTitles)) $resourceTitles=[];
    if (!is_array($resourceTypes)) $resourceTypes=[];

    $count = max(count($resourceTitles), count($resourceTypes));
    for($i=0;$i<$count;$i++){
        $rTitle=trim((string)($resourceTitles[$i]??''));
        $rType=(string)($resourceTypes[$i]??'');
        if($rTitle==='' || !in_array($rType,['file','link','text'],true)) continue;
        $rDoc=$rUrl=$rText=$original='';
        if($rType==='link'){
            $rUrl=cc_clean_url((string)($resourceUrls[$i]??''));
            if($rUrl==='') throw new RuntimeException("Invalid URL for resource: {$rTitle}");
        } elseif($rType==='text'){
            $rText=trim((string)($resourceTexts[$i]??''));
            if($rText==='') throw new RuntimeException("Text content is required for resource: {$rTitle}");
        } else {
            if(isset($_FILES['resource_file']) && is_array($_FILES['resource_file']['name']??null) && !empty($_FILES['resource_file']['name'][$i])){
                $single=[
                    'name'=>$_FILES['resource_file']['name'][$i],
                    'type'=>$_FILES['resource_file']['type'][$i]??'',
                    'tmp_name'=>$_FILES['resource_file']['tmp_name'][$i]??'',
                    'error'=>$_FILES['resource_file']['error'][$i]??UPLOAD_ERR_NO_FILE,
                    'size'=>$_FILES['resource_file']['size'][$i]??0,
                ];
                $up=cc_upload_file($single,'asg_res_');
                $rDoc=$up['stored']; $original=$up['original']; $uploadedFiles[]=$up['path'];
            } else throw new RuntimeException("File is required for resource: {$rTitle}");
        }
        $stmt=$conn->prepare("INSERT INTO assignment_resources (assignment_id,title,resource_type,document,resource_url,resource_text,original_filename,uploaded_by,created_at) VALUES (?,?,?,?,?,?,?,?,?)");
        if(!$stmt) throw new RuntimeException($conn->error);
        $stmt->bind_param('issssssss',$assignmentId,$rTitle,$rType,$rDoc,$rUrl,$rText,$original,$uid,$now);
        if(!$stmt->execute()) throw new RuntimeException($stmt->error);
        $stmt->close();
    }
    $conn->commit();

    $message = 'Assignment created successfully.';
    try {
        if($intakeId>0){
            $emailService=new EmailService();
            $result=$emailService->notifyNewAssignment($courseId,$intakeId,$title,$due);
            $sent=(int)($result['sent']??0); $failed=(int)($result['failed']??0);
            if($sent>0) $message.=" Email sent to {$sent} student(s)".($failed>0?", {$failed} failed.":'.');
        }
    } catch(Throwable $mailError){ error_log('Assignment email failed: '.$mailError->getMessage()); }

    cc_json(['success'=>true,'message'=>$message,'assignment_id'=>$assignmentId]);
} catch(Throwable $e){
    if(isset($conn) && $conn instanceof mysqli){ try{$conn->rollback();}catch(Throwable){} }
    foreach($uploadedFiles as $p) if(is_file($p)) @unlink($p);
    cc_json(['success'=>false,'message'=>$e->getMessage()],500);
}
