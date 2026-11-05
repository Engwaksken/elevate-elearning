<?php
declare(strict_types=1);
require_once __DIR__ . '/course_content_helpers.php';

$uploadedPath = '';
try {
    $conn = cc_require_db();
    [$uid, $role] = cc_current_user();
    cc_require_instructor_role($role);

    $topicId = (int)($_POST['topic_id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $type = cc_valid_material_type((string)($_POST['material_type'] ?? 'document'));
    $order = max(1, (int)($_POST['order_number'] ?? 1));
    $status = cc_valid_status((string)($_POST['status'] ?? 'active'));

    if ($topicId <= 0 || $title === '') {
        cc_json(['success' => false, 'message' => 'Topic and material title are required.'], 422);
    }

    $stmt = $conn->prepare("SELECT t.course_id FROM course_topics t WHERE t.id = ? LIMIT 1");
    $stmt->bind_param('i', $topicId);
    $stmt->execute();
    $topic = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$topic || !cc_check_instructor_course_access($conn, $uid, $role, (int)$topic['course_id'])) {
        cc_json(['success' => false, 'message' => 'Topic not found or access denied.'], 403);
    }

    $document = $externalUrl = $readingContent = $mime = $original = '';
    $fileSize = 0;

    if ($type === 'link') {
        $externalUrl = cc_clean_url((string)($_POST['external_url'] ?? ''));
        if ($externalUrl === '') cc_json(['success' => false, 'message' => 'Please enter a valid external URL.'], 422);
    } elseif ($type === 'text') {
        $readingContent = trim((string)($_POST['reading_content'] ?? ''));
        if ($readingContent === '') cc_json(['success' => false, 'message' => 'Please enter the reading content.'], 422);
    } else {
        if (!isset($_FILES['document'])) cc_json(['success' => false, 'message' => 'Please choose a material file.'], 422);
        $up = cc_upload_file($_FILES['document']);
        $document = $up['stored'];
        $original = $up['original'];
        $mime = $up['mime'];
        $fileSize = $up['size'];
        $uploadedPath = $up['path'];
    }

    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO topic_materials (topic_id,title,description,material_type,document,external_url,reading_content,mime_type,original_filename,file_size,order_number,status,uploaded_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    if (!$stmt) throw new RuntimeException($conn->error);
    $stmt->bind_param('issssssssiisss', $topicId, $title, $description, $type, $document, $externalUrl, $readingContent, $mime, $original, $fileSize, $order, $status, $uid, $now);
    if (!$stmt->execute()) throw new RuntimeException($stmt->error);
    $id = $stmt->insert_id;
    $stmt->close();

    cc_json(['success' => true, 'message' => 'Material added successfully.', 'material_id' => $id]);
} catch (Throwable $e) {
    if ($uploadedPath !== '' && is_file($uploadedPath)) @unlink($uploadedPath);
    cc_json(['success' => false, 'message' => $e->getMessage()], 500);
}
