<?php
include 'connector.php';
//session_start();

$assignment_id = $_GET['id']; 
$query = "SELECT * FROM assignments WHERE id = '$assignment_id'";
$result = $conn->query($query);
$assignment = $result->fetch_assoc();

$fileTypeQuery = "SELECT * FROM filetypes ORDER BY name DESC";
$fileTypes = $conn->query($fileTypeQuery);
?>

<!-- Modal for Editing Assignment -->
<div class="modal" id="editAssignmentModal" tabindex="-1" role="dialog" aria-hidden="true" style="display: block;">
    <div class="modal-dialog">
        <div class="modal-content animated flipInY">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><?php echo htmlspecialchars($assignment["name"]); ?></h4>
                <small class="font-bold">Update Assignment</small>
            </div>
            <div class="modal-body">
                <!-- Form to edit assignment -->
                <form method="POST" action="scripts/editassignment.php" enctype="multipart/form-data">
                    <!-- Assignment Title -->
                    <div class="form-group">
                        <label for="title">Assignment Title</label>
                        <input type="text" name="title" id="title" class="form-control" placeholder="Title" value="<?php echo htmlspecialchars($assignment['title']); ?>" required>
                    </div>

                    <!-- Short Notes -->
                    <div class="form-group">
                        <label for="shortnotes">Short Notes</label>
                        <textarea name="shortnotes" id="shortnotes" class="form-control" required><?php echo htmlspecialchars($assignment['shortnotes']); ?></textarea>
                    </div>

                    <!-- Hidden Fields for Course and Intake -->
                    <input type="hidden" name="course" value="<?php echo htmlspecialchars($assignment['course']); ?>">
                    <input type="hidden" name="intake" value="<?php echo htmlspecialchars($assignment['intake']); ?>">

                    <!-- Date and Time -->
                    <div class="form-inline">
                        <div class="form-group">
                            <label for="dueDate">Date Due</label>
                            <input type="date" name="due_date" id="dueDate" class="form-control" required value="<?php echo htmlspecialchars($assignment['due_date']); ?>">
                        </div>
                        <div class="form-group ml-3">
                            <label for="time">Time</label>
                            <input type="time" name="due_time" id="time" class="form-control" required value="<?php echo htmlspecialchars($assignment['due_time']); ?>">
                        </div>
                    </div>

                    <!-- File Type (Expected and Actual) -->
                    <div class="form-group">
                        <label for="expectedFileType">Expected File Type</label>
                        <select name="expectedfiletype" id="expectedFileType" class="form-control" required>
                            <?php while ($fileType = $fileTypes->fetch_assoc()) { ?>
                                <option value="<?php echo $fileType['id']; ?>" <?php echo ($assignment['expected_filetype'] == $fileType['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($fileType['name']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fileType">File Type</label>
                        <select name="filetype" id="fileType" class="form-control" required>
                            <?php $fileTypes->data_seek(0); // Reset pointer for second dropdown ?>
                            <?php while ($fileType = $fileTypes->fetch_assoc()) { ?>
                                <option value="<?php echo $fileType['id']; ?>" <?php echo ($assignment['filetype'] == $fileType['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($fileType['name']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <!-- File Upload -->
                    <div class="form-group">
                        <label for="notesfile">Upload Assignment File</label>
                        <input type="file" name="notesfile" id="notesfile" class="form-control-file">
                       
                        <?php if (!empty($assignment['file'])) { ?>
                            <small>Current file: <?php echo htmlspecialchars($assignment['file']); ?></small>
                        <?php } ?>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-primary">Update Assignment</button>
                </form>
            </div>

            <div class="modal-footer">
                <!-- Fixed Close Button -->
                <button type="button" class="btn btn-secondary" id="closeModal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script> <!-- Full jQuery -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function () {
        // Close modal using jQuery if Close button is clicked
        $('#closeModal').click(function () {
            $('#editAssignmentModal').modal('hide');
        });
    });
</script>
</body>
</html>
