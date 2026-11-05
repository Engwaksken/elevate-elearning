
<div class="row wrapper border-bottom white-bg page-heading">
  <div class="col-lg-10">
    <h2><?php echo $systemname?> Students</h2>
    <ol class="breadcrumb">
      <li> <a href="?">Home</a> </li>
      <li> <a>Users</a> </li>
      <li class="active"> <strong>Students</strong> </li>
    </ol>
  </div>
  <div class="col-lg-2"> </div>
</div>
<div class="wrapper wrapper-content  animated fadeInRight">
<div class="row">
  <div class="col-sm-12">
    <div class="ibox">
     <div class="ibox-content"> <?php
$lastEntryQuery = $conn->query("
  SELECT CONCAT(fname, ' ', lname) AS fullname, date_created
  FROM users 
  WHERE acc_type = 'afgezt' 
  ORDER BY date_created DESC 
  LIMIT 1
") or die(mysqli_error($conn));

if ($lastEntryQuery->num_rows > 0) {
  $lastUser = $lastEntryQuery->fetch_assoc();
  $lastSeen = date("d M Y h:i A", strtotime($lastUser['date_created']));
  echo '<span class="text-muted small pull-right">Last Entry: <i class="fa fa-clock-o"></i> '.$lastSeen.'</span>';
} else {
  echo '<span class="text-muted small pull-right">Last entry: <i class="fa fa-clock-o"></i> N/A</span>';
}
?>

        <h2>Students</h2>
     
          
        <div class="input-group">
  <input type="text" id="searchInput" placeholder="Search Student" class="input form-control">
  <span class="input-group-btn">
    <button type="button" class="btn btn-primary" onclick="searchStudent()">
      <i class="fa fa-search"></i> Search
    </button>
  </span>
</div>

<script>
function searchStudent() {
  const input = document.getElementById("searchInput").value.toLowerCase();
  const rows = document.querySelectorAll("#studentList tr");

  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(input) ? "" : "none";
  });
}
</script>

       <div class="clients-list">
  <ul class="nav nav-tabs">
   <?php
            $q = "SELECT COUNT(*) as total FROM users WHERE acc_type = 'afgezt'";
            $qq = $conn->query($q) or die(mysqli_error());
            $result = $qq->fetch_assoc();
            $totalStudents = $result['total'];
            echo '<span class="pull-right small text-muted">' . $totalStudents . ' Students</span>';
            ?>
    <li class="active"><a data-toggle="tab" href="#tab-1"><i class="fa fa-user"></i> Last Joined</a></li>
    <li class=""><a data-toggle="tab" href="#tab-2"><i class="fa fa-briefcase"></i> Alphabetical</a></li>
    <li class=""><a data-toggle="tab" href="#tab-3"><i class="fa fa-briefcase"></i> By Course</a></li>
    <li class=""><a data-toggle="tab" href="#tab-4"><i class="fa fa-briefcase"></i> By INTAKE</a></li>
  </ul>
  
  <div class="tab-content">
    <!-- Tab 1: Last Joined -->
    <div id="tab-1" class="tab-pane active">
      <div class="full-height-scroll">
        <div class="table-responsive">
          <!-- Table Controls -->
          <div class="row mb-3">
            <div class="col-md-6">
              <div class="mb-2">
                <button class="btn btn-sm btn-info" onclick="exportToCSV('last-join')"><i class="fa fa-download"></i> Export CSV</button>
                <button class="btn btn-sm btn-warning" onclick="exportToPDF('last-join')">  <i class="fa fa-download"></i> Export PDF</button>
              </div>
            </div>
            <div class="col-md-6">
              <div class="pull-right">
                <label class="control-label">Show 
                  <select class="form-control input-sm" style="display: inline-block; width: auto;" onchange="changeTableEntries('last-join', this.value)">
                    <option value="15"  selected>15</option>
                    <option value="25">25</option>
                    <option value="100">100</option>
                    <option value="1000">1000</option>
                    <option value="-1">All</option>
                  </select>
                  entries
                </label>
              </div>
            </div>
          </div>

          <table id="last-join" class="table table-striped table-hover">
            <thead>
              <tr>
               <th>#</th>
                <th>Photo</th>
                <th>Name</th>
                <th>Course</th>
                <th>Intake</th>
                <th>Email</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="studentList">
           <?php 
                      // Default limit
                      $limit = 15;
                      $offset = 0;
                      
                      $a = "SELECT * FROM users WHERE acc_type = 'afgezt' ORDER BY id DESC LIMIT $limit OFFSET $offset";
                      $b = $conn->query($a) or die(mysqli_error());
                      
                      if ($b->num_rows < 1) {
                        echo '<tr><td colspan="7"><div class="alert alert-danger"><strong>No Students to display.</strong></div></td></tr>';
                      } else {
                        $counter = 1;
                        while ($d = mysqli_fetch_array($b)) {
                          $std_id = $d["unique_id"];
                          
                     
                          if ($d["verified"] == "yes") {
                            $ver = '<a href="deactivate.php?id=' . $d["unique_id"] . '&xts=view_students"><span class="label label-primary"> <i class="fa fa-check"></i></span></a>';
                          } else {
                            $ver = '<a href="activate.php?id=' . $d["unique_id"] . '&xts=view_students"><span class="label label-info"><i class="fa fa-ban"></i></span></a>';
                          }

                        
                          if ($d["unique_id"] != $unik) {
                            $chatter = '<a href="?view=chatwith&pr=' . $d["unique_id"] . '"><span class="label label-success">Chat</span></a>';
                          } else {
                            $chatter = "";
                          }

                          
                          $p = "SELECT cr.*, c.name as course_name, i.name as intake_name, i.date_launching, b.name as branch_name 
                                FROM course_registration cr 
                                LEFT JOIN courses c ON cr.course_id = c.id 
                                LEFT JOIN intakes i ON cr.intake = i.id 
                                LEFT JOIN branches b ON i.branch_id = b.id 
                                WHERE cr.user_id = '" . $std_id . "' 
                                ORDER BY cr.id DESC LIMIT 1";
                          
                          $z = $conn->query($p) or die(mysqli_error());
                          
                          if ($z->num_rows < 1) {
                            $course = "N/A";
                            $intake = "N/A";
                            $studyline = "";
                          } else {
                            $a_reg = mysqli_fetch_array($z);
                            $course = $a_reg['course_name'] ?? 'N/A';
                            
                            if ($a_reg['intake_name']) {
                              $theyear = date("Y", strtotime($a_reg['date_launching']));
                              $intake = $a_reg['intake_name'] . ' ' . $theyear . ' (' . $a_reg['branch_name'] . ')';
                            } else {
                              $intake = "N/A";
                            }
                            
                            $studyline = '<a href="?view=study_plan&oiuwheilfbwehfjjasdkg=' . $d["unique_id"] . '&xxpoupoquegvbm=' . $a_reg["course_id"] . '"><span class="label">Study Plan</span></a>';
                          }
                 
                         $assignLink = '<a href="?view=change_course&id=' . $d["unique_id"] . '"> <span class="label label-info">  <i class="fa fa-eye"></i></span></a>';
                          $editLink = '<a href="?view=edit_student&id=' . $d["unique_id"] . '"> <span class="label label-warning">  <i class="fa fa-edit"></i></span></a>';
                          $delete_student = '<a href="scripts/delete_student.php?id=' . urlencode($d["unique_id"]) . '" onclick="return confirm(\'Are you sure you want to delete this student?\')"><span class="label label-danger">  <i class="fa fa-trash"></i></span></a>';

                     
                          $dpc = $d["dp"];
                          if ($dpc == "default.jpg") {
                            $dpp = ' <img alt="image" class="img-circle profile-top" src="images/default_p_pics/user.png" />';
                          } else {
                            $dpp = ' <img alt="image" class="img-circle profile-top" src="images/user_p_pics/' . $dpc . '" />';
                          }

                          echo '<tr>
                            <td>' . $counter++ . '</td>
                            <td class="client-avatar">' . $dpp . '</td>
                            <td><a data-toggle="tab" class="client-link" onclick="openuser(\'' . $d["unique_id"] . '\')">' . $d["fname"] . ' ' . $d["lname"] . '</a></td>
                            <td>' . $course . '</td>
                            <td>' . $intake . '</td>
                            <td>' . $d["email"] . '</td>
                            <td class="client-status">' . $ver . ' ' . $assignLink . ' ' . $editLink . ' ' . $delete_student . ' <br><br>' . $studyline . ' ' . $chatter . '</td>
                          </tr>';
                        }
                      }
                      ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Tab 2: Alphabetical -->
    <div id="tab-2" class="tab-pane">
      <div class="full-height-scroll">
        <div class="table-responsive">
          <!-- Table Controls -->
          <div class="row mb-3">
            <div class="col-md-6">
              <div class="mb-2">
                <button class="btn btn-sm btn-info" onclick="exportToCSV('alphabetical')"><i class="fa fa-download"></i> Export CSV</button>
                <button class="btn btn-sm btn-warning" onclick="exportToPDF('alphabetical')"> <i class="fa fa-download"></i>Export PDF</button>
              </div>
            </div>
            <div class="col-md-6">
              <div class="pull-right">
                <label class="control-label">Show 
                  <select class="form-control input-sm" style="display: inline-block; width: auto;" onchange="changeTableEntries('alphabetical', this.value)">
                    <option value="15"  selected>15</option>
                    <option value="25">25</option>
                    <option value="100">100</option>
                    <option value="1000">1000</option>
                    <option value="-1">All</option>
                  </select>
                  entries
                </label>
              </div>
            </div>
          </div>

          <table id="alphabetical" class="table table-striped table-hover">
            <thead>
              <tr>
               <th>#</th>
                <th>Photo</th>
                <th>Name</th>
                <th>Course</th>
                <th>Intake</th>
                <th>Email</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
       <?php  
$a = "SELECT * FROM users WHERE acc_type = 'afgezt' ORDER BY fname ASC";
$b = $conn->query($a) or die(mysqli_error($conn));

if ($b->num_rows < 1) {
    echo '<tr><td colspan="7"><div class="alert alert-danger"><strong>No Students to display.</strong></div></td></tr>';
} else {
    $counter = 1;
    while ($d = mysqli_fetch_array($b)) {
        $std_id = $d["unique_id"] ?? '';
        $ver = ($d["verified"] ?? '') === "yes"
            ? '<a href="deactivate.php?id=' . $std_id . '&xts=view_students"><span class="label label-primary">  <i class="fa fa-check"></i></span></a>'
            : '<a href="activate.php?id=' . $std_id . '&xts=view_students"><span class="label label-info">  <i class="fa fa-ban"></i></span></a>';

        $chatter = ($std_id != $unik)
            ? '<a href="?view=chatwith&pr=' . $std_id . '"><span class="label label-success">Chat</span></a>'
            : '';

        $p = "SELECT * FROM course_registration WHERE user_id = '$std_id' ORDER BY id ASC";
        $z = $conn->query($p) or die(mysqli_error($conn));

        $course = $intake = $studyline = '';
        $assignLink = $editLink = $delete_student = '';

        if ($z->num_rows < 1) {
            $course = $intake = "N/A";
            $assignLink = '<a href="?view=change_course&id=' . $std_id . '"><span class="label label-info">  <i class="fa fa-eye"></i></span></a>';
            $editLink = '<a href="?view=edit_student&id=' . $std_id . '"><span class="label label-warning">  <i class="fa fa-edit"></i></span></a>';
            $delete_student = '<a href="scripts/delete_student.php?id=' . urlencode($std_id) . '" onclick="return confirm(\'Are you sure you want to delete this student?\')"><span class="label label-danger"><i class="fa fa-trash"></i></span></a>';
        } else {
            $a_reg = mysqli_fetch_array($z);
            $course_id = $a_reg["course_id"] ?? '';
            $pp = "SELECT name FROM courses WHERE id = '$course_id' ORDER BY id ASC";
            $ll = $conn->query($pp) or die(mysqli_error($conn));

            $course = "N/A";
            if ($ll->num_rows > 0) {
                $qw = mysqli_fetch_array($ll);
                $course = $qw["name"] ?? "N/A";
            }

            // Now safely get intake information
            $intake_id = $a_reg["intake"] ?? '';
            $p_intake = "
                SELECT intakes.name AS intake_name, intakes.date_launching, branches.name AS branch_name 
                FROM intakes 
                INNER JOIN branches ON intakes.branch_id = branches.id 
                WHERE intakes.id = '$intake_id'
            ";

            $z_intake = $conn->query($p_intake) or die(mysqli_error($conn));
            $intake = "N/A";

            if ($z_intake && $z_intake->num_rows > 0) {
                $dl = mysqli_fetch_array($z_intake);
                $c_name = $dl["intake_name"] ?? "N/A";
                $theyear = isset($dl["date_launching"]) ? date("Y", strtotime($dl["date_launching"])) : "";
                $branch_name = $dl["branch_name"] ?? "";

                $intake = "$c_name $theyear ($branch_name)";
            }

            $studyline = '<a href="?view=study_plan&oiuwheilfbwehfjjasdkg=' . $std_id . '&xxpoupoquegvbm=' . $course_id . '"><span class="label">Study Plan</span></a>';
            $assignLink = '<a href="?view=change_course&id=' . $std_id . '"><span class="label label-info">  <i class="fa fa-eye"></i></span></a>';
            $editLink = '<a href="?view=edit_student&id=' . $std_id . '"><span class="label label-warning">  <i class="fa fa-edit"></i></span></a>';
            $delete_student = '<a href="scripts/delete_student.php?id=' . urlencode($std_id) . '" onclick="return confirm(\'Are you sure you want to delete this student?\')"><span class="label label-danger">  <i class="fa fa-trash"></i></span></a>';
        }

        $dpc = $d["dp"] ?? "default.jpg";
        $dpp = ($dpc === "default.jpg")
            ? '<img alt="image" class="img-circle profile-top" src="images/default_p_pics/user.png" />'
            : '<img alt="image" class="img-circle profile-top" src="images/user_p_pics/' . $dpc . '" />';

        echo '<tr>
            <td>' . $counter++ . '</td>
            <td class="client-avatar">' . $dpp . '</td>
            <td><a data-toggle="tab" class="client-link" onclick="openuser(\'' . $std_id . '\')">' . ($d["fname"] ?? '') . ' ' . ($d["lname"] ?? '') . '</a></td>
            <td>' . $course . '</td>
            <td>' . $intake . '</td>
            <td>' . ($d["email"] ?? '') . '</td>
            <td class="client-status">' . $ver . ' ' . $assignLink . ' ' . $editLink . ' ' . $delete_student . ' <br><br>' . $studyline . ' ' . $chatter . '</td>
        </tr>';
    }
}
?>

            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Tab 3: By Course -->
    <div id="tab-3" class="tab-pane">
      <div class="full-height-scroll">
        <div class="table-responsive">
          <div class="row mb-3">
            <div class="col-md-6">
              <select class="form-control" name="course" onchange="changec(this);">
                <option value="">...Select a Course...</option>
              <?php
$query = "
  SELECT 
    courses.id AS course_id, 
    courses.name AS course_name, 
    courses.short_code, 
    intakes.name AS intake_name, 
    intakes.date_launching,
    branches.name AS branch_name
  FROM courses
  LEFT JOIN intakes ON courses.category = intakes.type
  LEFT JOIN branches ON intakes.branch_id = branches.id
";

$result = $conn->query($query) or die(mysqli_error($conn));

while ($row = mysqli_fetch_array($result)) {
    $courseName = htmlspecialchars($row["course_name"]);
    $shortCode = htmlspecialchars($row["short_code"]);
    $intakeName = htmlspecialchars($row["intake_name"]);
    $launchDate = !empty($row["date_launching"]) ? date("Y", strtotime($row["date_launching"])) : "N/A";
    $branchName = htmlspecialchars($row["branch_name"]);

    echo '<option value="' . $row["course_id"] . '">';
    echo $courseName . ' (' . $shortCode . ') - ' . $intakeName .' ' . $launchDate . ' ( ' . $branchName.')';
    echo '</option>';
}
?>


              </select>
            </div>
            <div class="col-md-6">
              <div class="pull-right">
                <label class="control-label">Show 
                  <select class="form-control input-sm" style="display: inline-block; width: auto;" onchange="changeTableEntries('by-course', this.value)">
                    <option value="15"  selected>15</option>
                    <option value="25">25</option>
                    <option value="100">100</option>
                    <option value="1000">1000</option>
                    <option value="-1">All</option>
                  </select>
                  entries
                </label>
              </div>
            </div>
          </div>
          
          <div class="mb-2">
            <button class="btn btn-sm btn-info" onclick="exportToCSV('by-course')">  <i class="fa fa-download"></i> Export CSV</button>
            <button class="btn btn-sm btn-warning" onclick="exportToPDF('by-course')"> <i class="fa fa-download"></i> Export PDF</button>
          </div>

          <table id="by-course" class="table table-striped table-hover">
            <thead>
              <tr>
            <th>#</th>
                <th>Photo</th>
                <th>Name</th>
                <th>Course</th>
                <th>Intake</th> 
                <th>Email</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="docourse">
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Tab 4: By Intake -->
    <div id="tab-4" class="tab-pane">
      <div class="full-height-scroll">
        <div class="table-responsive">
          <div class="row mb-3">
            <div class="col-md-6">
              <select class="form-control" name="intake" onchange="filterByIntake(this.value);">
                <option value="">...Select Student Intake...</option>
                <?php
                $aa = "
                  SELECT intakes.id, intakes.name AS intake_name, intakes.date_launching, branches.name AS branch_name 
                  FROM intakes 
                  JOIN branches ON intakes.branch_id = branches.id 
                  ORDER BY intake_name DESC, date_launching DESC
                ";

                $bbb = $conn->query($aa) or die(mysqli_error($conn));

                while ($dq = mysqli_fetch_array($bbb)) {
                  $intake_id = $dq["id"];
                  $intake_name = $dq["intake_name"];
                  $date_launching = date("Y-m-d", strtotime($dq["date_launching"]));
                  $branch_name = $dq["branch_name"];

                  echo '<option value="' . $intake_id . '">' . $intake_name . ' (' . $date_launching . ') - ' . $branch_name . '</option>';
                  
                }
                ?>
              </select>
            </div>
            <div class="col-md-6">
              <div class="pull-right">
                <label class="control-label">Show 
                  <select class="form-control input-sm" style="display: inline-block; width: auto;" onchange="changeTableEntries('by-intake', this.value)">
                    <option value="15" selected>15</option>
                    <option value="25">25</option>
                    <option value="100">100</option>
                    <option value="1000">1000</option>
                    <option value="-1">All</option>
                  </select>
                  entries
                </label>
              </div>
            </div>
          </div>
          
          <div class="mb-2">
            <button class="btn btn-sm btn-info" onclick="exportToCSV('by-intake')">   <i class="fa fa-download"></i> Export CSV</button>
            <button class="btn btn-sm btn-warning" onclick="exportToPDF('by-intake')">  <i class="fa fa-download"></i> Export PDF</button>
          </div>

          <table id="by-intake" class="table table-striped table-hover">
            <thead>
              <tr>
                <th>#</th>
                <th>Photo</th>
                <th>Name</th>
                <th>Course</th>
                <th>Intake</th> 
                <th>Email</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="doinkate">
          

            </tbody>
          </table>
        </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
</div>


<div class="col-sm-4">
  <div class="ibox">
    <div class="ibox-content">
      <div class="tab-content" id="showit">
      </div>
    </div>
  </div>
</div>



<style>
.mb-3 {
  margin-bottom: 1rem;
}

.mb-2 {
  margin-bottom: 0.5rem;
}

.control-label {
  font-size: 12px;
  color: #666;
}

.table-responsive {
  padding: 10px;
}

.pull-right {
  float: right;
}

.pagination-controls {
  margin-top: 10px;
}

.pagination-controls button {
  margin: 0 2px;
}

.pagination-controls span {
  margin: 0 10px;
  font-weight: bold;
}

.dataTables_info {
  padding-top: 8px;
  font-size: 12px;
}

.profile-top {
  width: 40px;
  height: 40px;
  object-fit: cover;
}

.loading {
  text-align: center;
  padding: 20px;
  color: #999;
}

</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>

<script>
$(document).ready(function () {
  const dataTableOptions = {
    pageLength: 25,
    lengthMenu: [
      [15, 25, 100, 1000, -1],
      [15, 25, 100, 1000, "All"]
    ],
    responsive: true,
    searching: true,
    ordering: true,
    info: true,
    autoWidth: false
  };

  ['#by-course', '#by-intake'].forEach(id => {
    if ($(id).length) {
      $(id).DataTable(dataTableOptions);
    }
  });

  // Load alphabetical tab content only once
  let alphabeticalLoaded = false;
  $('a[href="#tab-2"]').on('shown.bs.tab', function () {
    if (!alphabeticalLoaded) {
      loadAlphabeticalData(1, currentLimit['alphabetical']);
      alphabeticalLoaded = true;
    }
  });

  // Initial load for last-join table
  loadStudentsData('last-join', currentPage['last-join'], currentLimit['last-join']);
});

// Global pagination state
let currentPage = {
  'last-join': 1,
  'alphabetical': 1,
  'by-intake': 1
};

let currentLimit = {
  'last-join': 15,
  'alphabetical': 15,
  'by-intake': 15
};

let totalEntries = <?php echo json_encode($totalStudents); ?>;

// Manual search for non-DataTables lists
function searchStudent() {
  const input = document.getElementById("searchInput").value.toLowerCase();
  const rows = document.querySelectorAll("#studentList tr");

  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(input) ? "" : "none";
  });
}

// Change entries per page for both DataTables and manual tables
function changeTableEntries(tableId, entries) {
  currentLimit[tableId] = parseInt(entries);
  currentPage[tableId] = 1;

  if (['by-course', 'by-intake'].includes(tableId)) {
    const table = $('#' + tableId).DataTable();
    table.page.len(currentLimit[tableId]).draw();
  } else if (tableId === 'last-join') {
    loadStudentsData(tableId, 1, currentLimit[tableId]);
  } else if (tableId === 'alphabetical') {
    loadAlphabeticalData(1, currentLimit[tableId]);
  }
}

function loadStudentsData(tableId, page, limit) {
  const tbodyId = (tableId === 'alphabetical') ? 'alphabetical-body' : 'studentList';
  const tbody = document.getElementById(tbodyId);
  if (!tbody) return;

  const intake = document.getElementById('intakeFilter')?.value || '';
  tbody.innerHTML = `<tr><td colspan="7" class="loading">Loading...</td></tr>`;

  let url = `fetch_students.php?view=${encodeURIComponent(tableId)}&page=${page}&limit=${limit}`;
  if (intake) url += `&intake=${encodeURIComponent(intake)}`;

  fetch(url)
    .then(res => {
      if (!res.ok) {
        return res.text().then(text => {
          throw new Error(`HTTP ${res.status}: ${text}`);
        });
      }
      return res.text();
    })
    .then(data => {
      if (!data.trim()) {
        throw new Error("Empty response from server.");
      }

      tbody.innerHTML = data;

      // Show pagination block if exists (for alphabetical)
      const paginationBlock = document.getElementById(`${tableId}-pagination`);
      if (paginationBlock) paginationBlock.style.display = 'block';

      updatePaginationInfo(tableId, page, limit);
    })
    .catch(err => {
      console.error('Error loading students:', err.message);
      tbody.innerHTML = `<tr><td colspan="7" class="text-danger text-center">Error loading data: ${err.message}</td></tr>`;
    });
}



// Load alphabetical data
function loadAlphabeticalData(page, limit) {
  const tbody = document.getElementById('alphabetical-body');
  if (!tbody) return;

  tbody.innerHTML = '<tr><td colspan="7" class="loading">Loading...</td></tr>';

  fetch(`fetch_students.php?page=${page}&limit=${limit}&order=alpha`)
    .then(res => res.text())
    .then(data => {
      tbody.innerHTML = data;
      updatePaginationInfo('alphabetical', page, limit);
      document.getElementById('alphabetical-pagination').style.display = 'block';
    })
    .catch(err => {
      console.error('Error loading alphabetical data:', err);
      tbody.innerHTML = '<tr><td colspan="7" class="text-danger text-center">Error loading data.</td></tr>';
    });
}

function updatePaginationInfo(tableId, page, limit) {
  const totalPages = Math.ceil(totalEntries / limit);
  const startEntry = ((page - 1) * limit) + 1;
  const endEntry = Math.min(page * limit, totalEntries);

  const setText = (id, text) => {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
  };

  const infoIdsMap = {
    'last-join': ['entries-start', 'entries-end', 'page-info'],
    'by-course': ['entries-start', 'entries-end', 'page-info'],
    'alphabetical': ['alphabetical-info', 'alphabetical-page-info'],
    'by-intake': ['by-intake-info', 'by-intake-page-info']
  };

  if (!(tableId in infoIdsMap)) return;

  const ids = infoIdsMap[tableId];

  if (tableId === 'last-join' || tableId === 'by-course') {
    setText(ids[0], startEntry);
    setText(ids[1], endEntry);
    setText(ids[2], `Page ${page} of ${totalPages}`);
  } else {
   
    setText(ids[0], `Showing ${startEntry} to ${endEntry} of ${totalEntries} entries`);
    setText(ids[1], `Page ${page} of ${totalPages}`);
  }
}


function loadPage(tableId, page) {
  currentPage[tableId] = page;

  if (tableId === 'last-join') {
    loadStudentsData(tableId, page, currentLimit[tableId]);
  } else if (tableId === 'alphabetical') {
    loadAlphabeticalData(page, currentLimit[tableId]);
  }
}

function loadNext(tableId) {
  const totalPages = Math.ceil(totalEntries / currentLimit[tableId]);
  if (currentPage[tableId] < totalPages) {
    loadPage(tableId, currentPage[tableId] + 1);
  }
}

function loadPrevious(tableId) {
  if (currentPage[tableId] > 1) {
    loadPage(tableId, currentPage[tableId] - 1);
  }
}

function loadLast(tableId) {
  const totalPages = Math.ceil(totalEntries / currentLimit[tableId]);
  loadPage(tableId, totalPages);
}


// Filter by intake
function filterByIntake(intakeId) {
  const tbody = document.getElementById("doinkate");

  if (!intakeId) {
    tbody.innerHTML = "<tr><td colspan='7' class='text-center'>Please select an intake.</td></tr>";
    return;
  }

  tbody.innerHTML = '<tr><td colspan="7" class="loading">Loading...</td></tr>';

  fetch(`fetch_students_by_intake.php?intake=${intakeId}`)
    .then(res => res.text())
    .then(data => tbody.innerHTML = data)
    .catch(err => {
      console.error("Intake filter error:", err);
      tbody.innerHTML = "<tr><td colspan='7' class='text-center text-danger'>Error loading students.</td></tr>";
    });
}

// Export to CSV
function exportToCSV(tableId) {
  const table = document.getElementById(tableId);
  if (!table) return alert("Table not found.");

  const rows = table.querySelectorAll("tbody tr");
  if (!rows.length) return alert("No data available to export.");

  let csv = "#,Name,Course,Intake,Email\n";

  rows.forEach(row => {
    const cells = row.querySelectorAll("td");
    if (cells.length >= 6) {
      const [counter,, name, course, intake, email] = Array.from(cells).map(td => td.textContent.trim());
      csv += `"${counter}","${name}","${course}","${intake}","${email}"\n`;
    }
  });

  const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = `${tableId}.csv`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

// Export to PDF
async function exportToPDF(tableId) {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF();

  const headers = [["#","Name", "Course", "Intake", "Email"]];
  const data = [];

  const table = document.getElementById(tableId);
  if (!table) return alert("Table not found.");

  const rows = table.querySelectorAll("tbody tr");
  if (rows.length === 0) return alert("No data available to export.");

  rows.forEach(row => {
    const cells = row.querySelectorAll("td");
    if (cells.length >= 5) {
      const counter = cells[0].textContent.trim();
      const name = cells[2].textContent.trim();
      const course = cells[3].textContent.trim();
      const intake = cells[4].textContent.trim();
      const email = cells[5].textContent.trim();
      data.push([counter, name,course, intake, email]);
    }
  });

  doc.autoTable({
    head: headers,
    body: data,
    startY: 20,
    styles: { fontSize: 10 },
    headStyles: {
      fillColor: [252, 127, 16] 
    }
  });

  doc.save(`${tableId}.pdf`);
}

</script>
