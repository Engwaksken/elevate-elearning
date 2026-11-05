<?php
session_start();
require 'scripts/connector.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);

function validate_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

// Function to validate numeric ID
function validate_id($id) {
    return filter_var($id, FILTER_VALIDATE_INT, array("options" => array("min_range" => 1)));
}

// Initialize variables
$course = null;
$course_id = null;
$error_message = '';
$success_message = '';

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $course_id = validate_id($_GET['id']);
} elseif (isset($_POST['id']) && !empty($_POST['id'])) {
    $course_id = validate_id($_POST['id']);
}

if (!$course_id) {
    $error_message = "No valid course ID has been specified.";
} else {

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
      
        $course_title = validate_input($_POST['course_title']);
        $course_image = validate_input($_POST['course_image']);
        $course_description = validate_input($_POST['course_description']);
        $course_link = validate_input($_POST['course_link']);

        if (empty($course_title) || empty($course_image) || empty($course_description) || empty($course_link)) {
            $error_message = "All fields are required.";
        } else {
          
            if (!filter_var($course_image, FILTER_VALIDATE_URL) && !preg_match('/^https?:\/\//', $course_image)) {
                $course_image = 'https://' . ltrim($course_image, '/');
            }
            
            if (!filter_var($course_link, FILTER_VALIDATE_URL) && !preg_match('/^https?:\/\//', $course_link)) {
                $course_link = 'https://' . ltrim($course_link, '/');
            }

            // Update the database
            $sql = "UPDATE course_free SET course_title = ?, course_image = ?, course_description = ?, course_link = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            
            if ($stmt === false) {
                $error_message = "Database prepare error: " . $conn->error;
            } else {
                $stmt->bind_param("ssssi", $course_title, $course_image, $course_description, $course_link, $course_id);
                
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $_SESSION['successmsg'] = "Course updated successfully.";
                        $stmt->close();
                        $conn->close();
                        // Redirect to prevent form resubmission
                        header("Location: ./?view=online_class&updated=1");
                        exit;
                    } else {
                        $error_message = "No changes were made or course not found.";
                    }
                } else {
                    $error_message = "Error updating course: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }

    if (empty($error_message)) {
        $sql = "SELECT id, course_title, course_image, course_description, course_link FROM course_free WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
            $error_message = "Database prepare error: " . $conn->error;
        } else {
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $course = $result->fetch_assoc();
            } else {
                $error_message = "No course found with ID: " . $course_id;
            }
            $stmt->close();
        }
    }
}

// Handle session messages
if (isset($_SESSION['errormsg'])) {
    $error_message = $_SESSION['errormsg'];
    unset($_SESSION['errormsg']);
}

if (isset($_SESSION['successmsg'])) {
    $success_message = $_SESSION['successmsg'];
    unset($_SESSION['successmsg']);
}

$conn->close();
?>
<div class="row wrapper border-bottom white-bg page-heading">
    <div class="col-lg-10">
        <h2>Update Other Resources</h2>
        <ol class="breadcrumb">
            <li>
                <a href="./">Home</a>
            </li>
            <li>
                <a href="?view=other_resources">Other Resources</a>
            </li>
            <li class="active">
                <strong>Update Course Material</strong>
            </li>
        </ol>
    </div>
    <div class="col-lg-2"></div>
</div>


           <div class="wrapper wrapper-content">
    <div class="row">
        <div class="middle-box animated fadeInDown" style="margin: 0 auto; max-width: 600px;">


                    
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle"></i>
                                <?php echo $error_message; ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle"></i>
                                <?php echo $success_message; ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        <?php endif; ?>

                      
                        <?php if ($course && !empty($course['id'])): ?>
                            <form action="" method="post" id="updateCourseForm" novalidate>
                                <input type="hidden" name="id" value="<?php echo $course['id']; ?>">

                                <div class="form-group">
                                    <label for="course_title">
                                        Course Title <span class="required">*</span>
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="course_title" 
                                           name="course_title" 
                                           value="<?php echo htmlspecialchars($course['course_title']); ?>" 
                                           required
                                           maxlength="255"
                                           placeholder="Enter course title">
                                    <div class="invalid-feedback">
                                        Please provide a valid course title.
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="course_image">
                                        Course Image URL <span class="required">*</span>
                                    </label>
                                    <input type="url" 
                                           class="form-control" 
                                           id="course_image" 
                                           name="course_image" 
                                           value="<?php echo htmlspecialchars($course['course_image']); ?>" 
                                           required
                                           placeholder="https://example.com/image.jpg">
                                    <div class="invalid-feedback">
                                        Please provide a valid image URL.
                                    </div>
                            
                                    <div id="imagePreview" class="mt-2" style="display: none;">
                                        <img id="previewImg" src="" alt="Image preview" class="preview-image">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="course_description">
                                        Course Description <span class="required">*</span>
                                    </label>
                                    <textarea class="form-control" 
                                              id="course_description" 
                                              name="course_description" 
                                              rows="5" 
                                              required
                                              maxlength="1000"
                                              placeholder="Enter course description"><?php echo htmlspecialchars($course['course_description']); ?></textarea>
                                    <div class="invalid-feedback">
                                        Please provide a course description.
                                    </div>
                                    <small class="form-text text-muted">
                                        <span id="charCount">0</span>/1000 characters
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label for="course_link">
                                        Course Link <span class="required">*</span>
                                    </label>
                                    <input type="url" 
                                           class="form-control" 
                                           id="course_link" 
                                           name="course_link" 
                                           value="<?php echo htmlspecialchars($course['course_link']); ?>" 
                                           required
                                           placeholder="https://example.com/course">
                                    <div class="invalid-feedback">
                                        Please provide a valid course URL.
                                    </div>
                                    <div class="url-preview" id="linkPreview"></div>
                                </div>

                                <div class="form-group pt-3">
                                    <div class="d-flex justify-content-between">
                                        <a href="./?view=online_class" class="btn btn-secondary">
                                            <i class="fas fa-times"></i> Cancel
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Update Resource
                                        </button>
                                    </div>
                                </div>
                            </form>

                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-exclamation-circle fa-3x text-warning mb-3"></i>
                                <h4>Course Not Found</h4>
                                <p class="text-muted">
                                    <?php echo !empty($error_message) ? $error_message : 'The requested Resource could not be found.'; ?>
                                </p>
                                <a href="./?view=online_class" class="btn btn-primary">
                                    <i class="fas fa-arrow-left"></i> Back to Course List
                                </a>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    // Character counter for description
    const descriptionField = $('#course_description');
    const charCount = $('#charCount');
    
    function updateCharCount() {
        const count = descriptionField.val().length;
        charCount.text(count);
        
        if (count > 900) {
            charCount.parent().removeClass('text-muted').addClass('text-warning');
        } else if (count > 950) {
            charCount.parent().removeClass('text-muted text-warning').addClass('text-danger');
        } else {
            charCount.parent().removeClass('text-warning text-danger').addClass('text-muted');
        }
    }
    
    descriptionField.on('input', updateCharCount);
    updateCharCount(); // Initial count
    
    // Image preview functionality
    const imageInput = $('#course_image');
    const imagePreview = $('#imagePreview');
    const previewImg = $('#previewImg');
    
    function updateImagePreview() {
        const url = imageInput.val().trim();
        if (url && (url.startsWith('http://') || url.startsWith('https://'))) {
            previewImg.attr('src', url);
            previewImg.on('load', function() {
                imagePreview.show();
            }).on('error', function() {
                imagePreview.hide();
            });
        } else {
            imagePreview.hide();
        }
    }
    
    imageInput.on('input', updateImagePreview);
    updateImagePreview(); // Initial preview
    
    // Course link preview
    const linkInput = $('#course_link');
    const linkPreview = $('#linkPreview');
    
    function updateLinkPreview() {
        const url = linkInput.val().trim();
        if (url) {
            linkPreview.text('Preview: ' + url).show();
        } else {
            linkPreview.hide();
        }
    }
    
    linkInput.on('input', updateLinkPreview);
    updateLinkPreview(); // Initial preview
    
    // Form validation
    const form = $('#updateCourseForm');
    
    form.on('submit', function(e) {
        let isValid = true;
        
        // Reset validation states
        form.find('.form-control').removeClass('is-invalid is-valid');
        
        // Validate each required field
        form.find('input[required], textarea[required]').each(function() {
            const field = $(this);
            const value = field.val().trim();
            
            if (!value) {
                field.addClass('is-invalid');
                isValid = false;
            } else {
                field.addClass('is-valid');
            }
        });
        
        // Validate URL fields
        const urlFields = ['course_image', 'course_link'];
        urlFields.forEach(function(fieldName) {
            const field = $('#' + fieldName);
            const value = field.val().trim();
            
            if (value && !isValidUrl(value)) {
                field.addClass('is-invalid');
                isValid = false;
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            e.stopPropagation();
            
            // Scroll to first invalid field
            const firstInvalid = form.find('.is-invalid').first();
            if (firstInvalid.length) {
                $('html, body').animate({
                    scrollTop: firstInvalid.offset().top - 100
                }, 500);
                firstInvalid.focus();
            }
        }
    });
    
    // URL validation helper
    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }
    
    // Auto-add https:// to URL fields if missing
    $('input[type="url"]').on('blur', function() {
        const field = $(this);
        let value = field.val().trim();
        
        if (value && !value.match(/^https?:\/\//)) {
            field.val('https://' + value);
        }
    });
});
</script>

</body>
</html>