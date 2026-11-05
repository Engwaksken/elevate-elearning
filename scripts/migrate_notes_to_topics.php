<?php
declare(strict_types=1);
require_once __DIR__ . '/connector.php';
date_default_timezone_set('Africa/Nairobi');
if(!isset($conn)||!($conn instanceof mysqli)||$conn->connect_errno) exit("Database connection failed.\n");

$conn->begin_transaction();
try{
    $res=$conn->query("SELECT n.*,cu.course_id FROM course_unit_notes n JOIN course_unit cu ON cu.id=n.course_unit WHERE (n.dummy IS NULL OR n.dummy<>'deleted') ORDER BY n.course_unit,n.intake,n.id");
    if(!$res) throw new RuntimeException($conn->error);
    $topicCache=[]; $migrated=0;
    while($n=$res->fetch_assoc()){
        $module=(int)$n['course_unit']; $intake=(int)($n['intake']??0); $course=(int)$n['course_id']; $key=$module.':'.$intake;
        if(!isset($topicCache[$key])){
            $title='Module Learning Materials'; $desc='Automatically created from existing course notes.'; $order=1; $req=0; $class=(string)($n['class_mode']??'both'); $status='active'; $uid=(string)($n['uploaded_by']??'system'); $now=date('Y-m-d H:i:s');
            $stmt=$conn->prepare("SELECT id FROM course_topics WHERE course_unit_id=? AND COALESCE(intake_id,0)=? AND title=? LIMIT 1");
            $stmt->bind_param('iis',$module,$intake,$title); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
            if($row) $topicId=(int)$row['id']; else {
                $stmt=$conn->prepare("INSERT INTO course_topics (course_id,course_unit_id,intake_id,title,description,order_number,is_required,class_mode,status,created_by,created_at) VALUES (?,?,NULLIF(?,0),?,?,?,?,?,?,?,?)");
                $stmt->bind_param('iiissiissss',$course,$module,$intake,$title,$desc,$order,$req,$class,$status,$uid,$now); $stmt->execute(); $topicId=$stmt->insert_id; $stmt->close();
            }
            $topicCache[$key]=$topicId;
        }
        $topicId=$topicCache[$key]; $noteId=(int)$n['id'];
        $stmt=$conn->prepare("SELECT id FROM topic_materials WHERE topic_id=? AND description LIKE ? LIMIT 1"); $marker='%legacy-note-id:'.$noteId.'%'; $stmt->bind_param('is',$topicId,$marker); $stmt->execute(); $exists=$stmt->get_result()->fetch_assoc(); $stmt->close(); if($exists) continue;
        $doc=(string)($n['document']??''); $url=(string)($n['notes_url']??''); $ft=(int)($n['file_type']??3);
        $type=$url!==''?'link':($ft===1?'audio':($ft===2?'video':($ft===4?'image':(in_array(strtolower(pathinfo($doc,PATHINFO_EXTENSION)),['ppt','pptx','odp'],true)?'powerpoint':'document'))));
        $title=(string)($n['title']??'Learning Material'); $description=trim((string)($n['short_message']??''))."\nlegacy-note-id:{$noteId}"; $reading=''; $mime=''; $orig=$doc; $size=0; $order=$migrated+1; $status=(string)($n['status']??'active'); $uid=(string)($n['uploaded_by']??'system'); $created=(string)($n['date_uploaded']??date('Y-m-d H:i:s'));
        $stmt=$conn->prepare("INSERT INTO topic_materials (topic_id,title,description,material_type,document,external_url,reading_content,mime_type,original_filename,file_size,order_number,status,uploaded_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('issssssssiisss',$topicId,$title,$description,$type,$doc,$url,$reading,$mime,$orig,$size,$order,$status,$uid,$created); $stmt->execute(); $stmt->close(); $migrated++;
    }
    $conn->commit(); echo "Migrated {$migrated} existing note(s) into topic materials.\n";
}catch(Throwable $e){ $conn->rollback(); http_response_code(500); echo 'Migration failed: '.$e->getMessage()."\n"; }
