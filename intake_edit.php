<?php
require_once 'scripts/connector.php';

$intake_id = isset($_GET['intake']) ? intval($_GET['intake']) : 0;
$intake_name = $date_launching = $date_ending = $selected_branch_id = '';

if ($intake_id > 0) {
    $stmt = $conn->prepare("SELECT name, date_launching, date_ending, branch_id FROM intakes WHERE id = ?");
    $stmt->bind_param("i", $intake_id);
    $stmt->execute();
    $stmt->bind_result($intake_name, $date_launching, $date_ending, $selected_branch_id);
    $stmt->fetch();
    $stmt->close();
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="app.css">
</head>
<body>
<div class="wrapper wrapper-content">
    <div class="row">
        <div class="middle-box animated fadeInDown" style="margin: 0 auto; max-width: 800px;">
<div class="row justify-content-center" id="editintake">
    <div class="col-md-8 col-sm-8"> <!-- controls width of the form -->
        <div class="ibox">
            <div class="ibox-content">
                
                
                
                <form method="post" action="scripts/editintake.php">
                    <input type="hidden" name="intake_id" value="<?= htmlspecialchars($intake_id) ?>">

                    <div class="form-group">
                        <label for="intake_name">Intake Name *</label>
                        <input type="text" id="intake_name" name="name" class="form-control"
                               value="<?= htmlspecialchars($intake_name) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="date_launching">Launch Date *</label>
                        <input type="date" id="date_launching" name="date_launching" class="form-control"
                               value="<?= htmlspecialchars($date_launching) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="date_ending">End Date *</label>
                        <input type="date" id="date_ending" name="date_ending" class="form-control"
                               value="<?= htmlspecialchars($date_ending) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="branch">Branch *</label>
                        <select id="branch" name="branch_id" class="form-control" required>
                            <option value="">Select Branch</option>
                            <?php
                            $branches = $conn->query("SELECT id, name FROM branches ORDER BY name ASC");
                            while ($branch = $branches->fetch_assoc()):
                                $selected = ($branch['id'] == $selected_branch_id) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($branch['id']) ?>" <?= $selected ?>>
                                    <?= htmlspecialchars($branch['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="text-center">
                        <button type="submit" name="submit" class="btn btn-primary">Update Intake</button>
                        <button type="button" class="btn btn-secondary" onclick="history.back()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
