<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $systemname; ?> | Online Class</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="font-awesome/css/font-awesome.css" rel="stylesheet">
    <link href="css/animate.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
      h2 {
        margin: 15px 0;
        text-align: center;
        color:#ff3b00;
        font-style: bold;
      }
      .container {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 10px;
        padding: 20px;
      }
      .course {
        width: 100%;
        max-width: 320px;
        border: 1px solid #ddd;
        border-radius: 8px;
        overflow: hidden;
        text-align: center;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        padding: 15px;
        background: #fff;
      }
      .course img {
        width: 100%;
        height: auto;
        border-bottom: 1px solid #ddd;
      }
      .course h4 {
        margin: 10px 0;
      }
      .course p {
        color: #555;
      }
      .course a {
        text-decoration: none;
        color: inherit;
        display: block;
      }
      @media (min-width: 600px) {
        .course {
          width: 48%;
        }
      }
      @media (min-width: 900px) {
        .course {
          width: 30%;
        }
      }
      .container::after {
        content: "";
        flex: 1 1 30%;
        visibility: hidden;
        margin-bottom: 30px;
      }
      .pagination {
        display: flex;
        justify-content: center;
        margin-top: 20px;
      }
      .pagination button {
        border: none;
        background-color: #ff3b00;
        color: white;
        padding: 10px 20px;
        margin: 0 5px;
        cursor: pointer;
      }
      .pagination button[disabled] {
        background-color: #ddd;
        cursor: not-allowed;
      }
    </style>
    <script>
      document.addEventListener('DOMContentLoaded', () => {
        const coursesContainer = document.querySelector('.container');
        const nextButton = document.querySelector('.pagination button:last-child');
        const prevButton = document.querySelector('.pagination button:first-child');
        let page = <?php echo $page; ?>;
        const totalPages = <?php echo $totalPages; ?>;
        const perPage = <?php echo $perPage; ?>;

        nextButton.addEventListener('click', () => {
          if (page < totalPages) {
            page++;
            updateCourses();
          }
        });

        prevButton.addEventListener('click', () => {
          if (page > 1) {
            page--;
            updateCourses();
          }
        });

        function updateCourses() {
          fetch(`scripts/get_courses.php?page=${page}&perPage=${perPage}`)
            .then(response => response.json())
            .then(data => {
              coursesContainer.innerHTML = data.map(course => `
                <a href='${course.course_link}' class='course'>
                  <img src='${course.course_image}' alt='${course.course_title}'>
                  <h4>${course.course_title}</h4>
                  <p>${course.course_description}</p>
                </a>
              `).join('');
              updatePaginationButtons();
            });
        }

        function updatePaginationButtons() {
          prevButton.disabled = page <= 1;
          nextButton.disabled = page >= totalPages;
        }

        updatePaginationButtons();
      });
    </script>
  </head>
  <body>
    <h2>Welcome to Online Class!</h2>
    <div class="container">
      <?php 
       require 'scripts/connector.php';

        $sql = "SELECT course_image, course_title, course_description, course_link FROM course_free ORDER BY course_title ASC";
        $result = $conn->query($sql);

        $courses = [];
        if ($result->num_rows > 0) {
          while($row = $result->fetch_assoc()) {
            $courses[] = $row;
          }
        }

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = 6;
        $totalPages = ceil(count($courses) / $perPage);
        $start = ($page - 1) * $perPage;
        $displayCourses = array_slice($courses, $start, $perPage);

        foreach($displayCourses as $index => $course) {
          echo "<a href='{$course['course_link']}' class='course'>
                  <img src='{$course['course_image']}' alt='{$course['course_title']}'>
                  <h4>{$course['course_title']}</h4>
                  <p>{$course['course_description']}</p>
                </a>";
          if (($index + 1) % 3 == 0 && ($index + 1) < count($displayCourses)) {
            echo "<div class='clearfix'></div>";
          }
        }

        $conn->close();
      ?>
      <!-- <div class="pagination">
        <button <?php if($page <= 1){ echo 'disabled'; } ?>>Previous</button>
        <button <?php if($page >= $totalPages){ echo 'disabled'; } ?>>Next</button>
      </div> -->
    </div>
  </body>
</html>
