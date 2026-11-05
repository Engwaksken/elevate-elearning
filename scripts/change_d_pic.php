<?php
session_start();

require_once __DIR__ . '/connector.php';

$systemname = $systemname ?? "Elevate Her 360";

define('UPLOAD_DIR', __DIR__ . '/../images/user_p_pics/');
define('DB_UPLOAD_PATH', '');

$user_unique = $_SESSION[$systemname . "leido"] ?? '';

if ($user_unique === '') {
    echo "Session expired. Please login again.";
    exit;
}

if (empty($_POST['image'])) {
    echo "No image data provided.";
    exit;
}

if (!is_dir(UPLOAD_DIR)) {
    if (!mkdir(UPLOAD_DIR, 0775, true)) {
        echo "Upload folder could not be created.";
        exit;
    }
}

$imageData = $_POST['image'];

if (!preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,/', $imageData, $matches)) {
    echo "Invalid cropped image format.";
    exit;
}

$imageData = preg_replace('/^data:image\/(png|jpeg|jpg|webp);base64,/', '', $imageData);
$imageData = str_replace(' ', '+', $imageData);

$decoded = base64_decode($imageData, true);

if ($decoded === false) {
    echo "Image decoding failed.";
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->buffer($decoded);

$allowedTypes = [
    'image/png',
    'image/jpeg',
    'image/webp'
];

if (!in_array($mime, $allowedTypes, true)) {
    echo "Unsupported image type.";
    exit;
}

$tmpFile = UPLOAD_DIR . 'tmp_' . uniqid('', true) . '.img';

if (file_put_contents($tmpFile, $decoded) === false) {
    echo "Failed to save temporary image.";
    exit;
}

$imageInfo = getimagesize($tmpFile);

if ($imageInfo === false) {
    @unlink($tmpFile);
    echo "Invalid image file.";
    exit;
}

switch ($imageInfo['mime']) {
    case 'image/jpeg':
        $sourceImage = imagecreatefromjpeg($tmpFile);
        break;

    case 'image/png':
        $sourceImage = imagecreatefrompng($tmpFile);
        break;

    case 'image/webp':
        if (!function_exists('imagecreatefromwebp')) {
            @unlink($tmpFile);
            echo "WEBP is not supported on this server.";
            exit;
        }
        $sourceImage = imagecreatefromwebp($tmpFile);
        break;

    default:
        @unlink($tmpFile);
        echo "Invalid image type.";
        exit;
}

if (!$sourceImage) {
    @unlink($tmpFile);
    echo "Failed to process image.";
    exit;
}

$firstName = $_SESSION[$systemname . "firstname"] ?? 'user';
$lastName  = $_SESSION[$systemname . "lastname"] ?? '';

$cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '', $firstName . '_' . $lastName);
if ($cleanName === '') {
    $cleanName = 'user';
}

$newName = strtolower($cleanName) . '_' . time() . '_' . uniqid() . '.jpg';
$destination = UPLOAD_DIR . $newName;

$width = imagesx($sourceImage);
$height = imagesy($sourceImage);

$finalImage = imagecreatetruecolor($width, $height);

$white = imagecolorallocate($finalImage, 255, 255, 255);
imagefill($finalImage, 0, 0, $white);

imagecopy($finalImage, $sourceImage, 0, 0, 0, 0, $width, $height);

$saved = imagejpeg($finalImage, $destination, 90);

imagedestroy($sourceImage);
imagedestroy($finalImage);
@unlink($tmpFile);

if (!$saved || !file_exists($destination)) {
    echo "Image saving failed.";
    exit;
}

$stmt = $conn->prepare("SELECT dp FROM users WHERE unique_id = ? LIMIT 1");
$stmt->bind_param("s", $user_unique);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$oldDp = $user['dp'] ?? '';

if (!empty($oldDp)) {
    $oldPath = UPLOAD_DIR . basename($oldDp);

    if (file_exists($oldPath) && is_file($oldPath)) {
        @unlink($oldPath);
    }
}

$update = $conn->prepare("UPDATE users SET dp = ? WHERE unique_id = ?");
$update->bind_param("ss", $newName, $user_unique);

if ($update->execute()) {
    $_SESSION['successmsg'] = "Profile picture updated successfully.";
    echo "done";
} else {
    @unlink($destination);
    echo "Database update failed.";
}