<?php

require 'scripts/connector.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $course_title = trim($_POST['course_name']);
    $course_description = trim($_POST['course_description']);
    $resource_type = $_POST['resource_type'];
    $created_at = date('Y-m-d H:i:s');

    // Initialize variables
    $course_image = null;
    $course_link = null;
    $uploadErrors = [];

    // Ensure the directories exist
    $image_dir = "online_class/images/";
    $file_dir = "online_class/files/";
    
    if (!is_dir($image_dir)) {
        mkdir($image_dir, 0755, true);
    }
    if (!is_dir($file_dir)) {
        mkdir($file_dir, 0755, true);
    }

    // Handle image upload
    if (isset($_FILES['course_image']) && $_FILES['course_image']['error'] == UPLOAD_ERR_OK) {
        $image_name = $_FILES['course_image']['name'];
        $image_tmp = $_FILES['course_image']['tmp_name'];
        $image_size = $_FILES['course_image']['size'];
        $image_ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
        
        // Validate image
        $allowed_image_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($image_ext, $allowed_image_ext)) {
            $uploadErrors[] = "Invalid image format. Allowed: JPG, PNG, GIF, WEBP";
        } elseif ($image_size > 5000000) { // 5MB limit
            $uploadErrors[] = "Image size must be less than 5MB";
        } else {
            $new_image_name = uniqid('course_img_', true) . '.' . $image_ext;
            $image_target = $image_dir . $new_image_name;
            
            if (move_uploaded_file($image_tmp, $image_target)) {
                $course_image = $image_target;
                require_once __DIR__ . '/scripts/upload_compressor.php';
                eh_compress_upload($image_target);
            } else {
                $uploadErrors[] = "Failed to upload image";
            }
        }
    }

    // Handle resource based on type (either link or file)
    if ($resource_type === 'link') {
        $course_link = trim($_POST['course_link_input']);
        if (empty($course_link)) {
            $uploadErrors[] = "Please provide a link URL";
        } elseif (!filter_var($course_link, FILTER_VALIDATE_URL)) {
            $uploadErrors[] = "Please provide a valid URL";
        }
    } elseif ($resource_type === 'file') {
        if (isset($_FILES['course_file']) && $_FILES['course_file']['error'] == UPLOAD_ERR_OK) {
            $file_name = $_FILES['course_file']['name'];
            $file_tmp = $_FILES['course_file']['tmp_name'];
            $file_size = $_FILES['course_file']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Validate file
            $allowed_file_ext = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'rar'];
            if (!in_array($file_ext, $allowed_file_ext)) {
                $uploadErrors[] = "Invalid file format. Allowed: PDF, DOC, DOCX, PPT, PPTX, ZIP, RAR";
            } elseif ($file_size > 20000000) { // 20MB limit
                $uploadErrors[] = "File size must be less than 20MB";
            } else {
                $new_file_name = uniqid('course_file_', true) . '.' . $file_ext;
                $file_target = $file_dir . $new_file_name;
                
                if (move_uploaded_file($file_tmp, $file_target)) {
                    $course_link = $file_target; 
                    require_once __DIR__ . '/scripts/upload_compressor.php';
                    eh_compress_upload($file_target);
                } else {
                    $uploadErrors[] = "Failed to upload file";
                }
            }
        } else {
            $uploadErrors[] = "Please upload a file";
        }
    }

    if (empty($uploadErrors)) {
        $stmt = $conn->prepare("INSERT INTO course_free (course_title, course_image, course_description, course_link, created_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $course_title, $course_image, $course_description, $course_link, $created_at);

        // Execute the statement
        if ($stmt->execute()) {
            $_SESSION['successmsg'] = "New course material added successfully!";
            header("Location: " . $_SERVER['PHP_SELF'] . "?view=online_class");
            exit();
        } else {
            $_SESSION['errormsg'] = "Database error: " . $stmt->error;
        }

        $stmt->close();
    } else {
        $_SESSION['errormsg'] = implode("<br>", $uploadErrors);
    }
}

$sql = "SELECT name FROM courses ORDER BY name ASC";
$result = $conn->query($sql);

$course_names = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $course_names[] = $row['name'];
    }
}
$conn->close();
?>
<div class="row wrapper border-bottom white-bg page-heading">
    <div class="col-lg-10">
        <h2>Add Other Resources</h2>
        <ol class="breadcrumb">
            <li>
                <a href="index.php">Home</a>
            </li>
            <li>
                <a href="?view=other_resources">Other Resources</a>
            </li>
            <li class="active">
                <strong>Add Course Material</strong>
            </li>
        </ol>
    </div>
    <div class="col-lg-2"></div>
</div>

<div class="wrapper wrapper-content">
    <div class="row">
        <div class="middle-box animated fadeInDown" style="margin: 0 auto; max-width: 600px;">


    <?php if (isset($_SESSION['errormsg'])) : ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['errormsg']; ?>
        </div>
        <?php unset($_SESSION['errormsg']); ?>
    <?php elseif (isset($_SESSION['successmsg'])) : ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['successmsg']; ?>
        </div>
        <?php unset($_SESSION['successmsg']); ?>
    <?php endif; ?>

    <form method="post" action="" enctype="multipart/form-data" id="courseForm">
        <div class="form-group">
            <label for="course_name">
                Select Course Name <span style="color: #e74c3c;">*</span>
            </label>
            <select class="form-control" id="course_name" name="course_name" required>
                <option value="">Select a course...</option>
                <?php foreach($course_names as $course_name): ?>
                    <option value="<?php echo htmlspecialchars($course_name); ?>">
                        <?php echo htmlspecialchars($course_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="course_image">
              Upload Course Image
            </label>
            <div class="file-input-wrapper">
                <input type="file" id="course_image" name="course_image" accept="image/*" onchange="updateFileName(this, 'image-name')">
                <label for="course_image" class="file-label">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <span>Choose an image file (JPG, PNG, GIF, WEBP - Max 5MB)</span>
                </label>
                <div id="image-name" class="file-name"></div>
            </div>
        </div>

        <div class="form-group">
            <label for="course_description">
                Brief Description <span style="color: #e74c3c;">*</span>
            </label>
            <textarea class="form-control" id="course_description" name="course_description" rows="4" placeholder="Provide a detailed description of the course material..." required></textarea>
            <div class="form-hint">Minimum 20 characters required</div>
        </div>

        <div class="form-group">
            <label>
                Resource Type <span style="color: #e74c3c;">*</span>
            </label>
            <div style="display: flex; gap: 20px; margin-bottom: 15px;">
                <label style="display: flex; align-items: center; cursor: pointer; font-weight: normal;">
                    <input type="radio" name="resource_type" value="link" id="type_link" onchange="toggleResourceType()" checked style="margin-right: 8px;">
                    <i class="fas fa-link" style="margin-right: 5px; color: #667eea;"></i> External Link
                </label>
                <label style="display: flex; align-items: center; cursor: pointer; font-weight: normal;">
                    <input type="radio" name="resource_type" value="file" id="type_file" onchange="toggleResourceType()" style="margin-right: 8px;">
                    <i class="fas fa-file-upload" style="margin-right: 5px; color: #667eea;"></i> Upload File
                </label>
            </div>

            <div id="link_input_container">
                <input type="url" class="form-control" id="course_link_input" name="course_link_input" placeholder="https://www.example.com/">
                <div class="form-hint">Provide a link to external resources</div>
            </div>

            <div id="file_input_container" style="display: none;">
                <div class="file-input-wrapper">
                    <input type="file" id="course_file" name="course_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.rar" onchange="updateFileName(this, 'file-name')">
                    <label for="course_file" class="file-label">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span>Choose a file (PDF, DOC, PPT, ZIP - Max 20MB)</span>
                    </label>
                    <div id="file-name" class="file-name"></div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn-primary">
            <i class="fas fa-plus-circle"></i> Add Course Material
        </button>
    </form>
</div>

<script>
    function toggleResourceType() {
        const linkContainer = document.getElementById('link_input_container');
        const fileContainer = document.getElementById('file_input_container');
        const linkInput = document.getElementById('course_link_input');
        const fileInput = document.getElementById('course_file');
        
        if (document.getElementById('type_link').checked) {
            linkContainer.style.display = 'block';
            fileContainer.style.display = 'none';
            linkInput.required = true;
            fileInput.required = false;
            fileInput.value = '';
            document.getElementById('file-name').innerHTML = '';
        } else {
            linkContainer.style.display = 'none';
            fileContainer.style.display = 'block';
            linkInput.required = false;
            fileInput.required = true;
            linkInput.value = '';
        }
    }

    function updateFileName(input, displayId) {
        const display = document.getElementById(displayId);
        if (input.files && input.files[0]) {
            const fileName = input.files[0].name;
            const fileSize = (input.files[0].size / 1024 / 1024).toFixed(2);
            display.innerHTML = `<i class="fas fa-file"></i> ${fileName} (${fileSize} MB)`;
        } else {
            display.innerHTML = '';
        }
    }

    document.getElementById('courseForm').addEventListener('submit', function(e) {
        const description = document.getElementById('course_description').value.trim();
        
        if (description.length < 20) {
            e.preventDefault();
            alert('Description must be at least 20 characters long.');
            return false;
        }

        const resourceType = document.querySelector('input[name="resource_type"]:checked').value;
        
        if (resourceType === 'link') {
            const linkInput = document.getElementById('course_link_input').value.trim();
            if (!linkInput) {
                e.preventDefault();
                alert('Please provide a link URL.');
                return false;
            }
        } else if (resourceType === 'file') {
            const fileInput = document.getElementById('course_file');
            if (!fileInput.files || fileInput.files.length === 0) {
                e.preventDefault();
                alert('Please upload a file.');
                return false;
            }
        }
        
        return true;
    });

    // Initialize on page load
    window.addEventListener('DOMContentLoaded', function() {
        toggleResourceType();
    });
</script>

</body>
</html>