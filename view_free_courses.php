<?php
//session_start();
require 'scripts/connector.php';
$sql = "SELECT id, course_title, course_image, course_description, course_link FROM course_free";
$result = $conn->query($sql);
$courses = [];
if ($result->num_rows > 0) {
    // Output data of each row
    while($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
} else {
    echo "0 results";
}
$conn->close();
?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $systemname; ?> | Update Other Resources</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="font-awesome/css/font-awesome.css" rel="stylesheet">
    <link href="css/animate.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
      .search-container {
        margin: 20px 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
      }
      
      .search-box {
        flex: 1;
        min-width: 250px;
        max-width: 400px;
      }
      
      .pagination-info {
        color: #666;
        font-size: 14px;
      }
      
      .table-container {
        overflow-x: auto;
        overflow-y: visible;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-bottom: 20px;
        width: 100%;
        -webkit-overflow-scrolling: touch; 
      }
      
      .table {
        min-width: 1200px; 
        margin-bottom: 0;
        width: auto;
        table-layout: fixed;
      }
      
      .table td, .table th {
        white-space: nowrap;
        vertical-align: middle;
        padding: 12px 8px;
      }
      
   
      .table th:nth-child(1), .table td:nth-child(1) { 
        width: 120px;
        min-width: 120px;
      }
      
      .table th:nth-child(2), .table td:nth-child(2) {
        width: 250px;
        min-width: 200px;
        white-space: normal;
        word-wrap: break-word;
      }
      
      .table th:nth-child(3), .table td:nth-child(3) { 
        width: 350px;
        min-width: 300px;
        white-space: normal;
        word-wrap: break-word;
        overflow-wrap: break-word;
      }
      
      .table th:nth-child(4), .table td:nth-child(4) { 
        width: 100px;
        min-width: 100px;
      }
      
      .table th:nth-child(5), .table td:nth-child(5) { 
        width: 120px;
        min-width: 120px;
      }
      
      .pagination-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-top: 20px;
      }
      
      .pagination-controls {
        display: flex;
        gap: 5px;
        align-items: center;
      }
      
      .pagination-btn {
        padding: 8px 12px;
        border: 1px solid #ddd;
        background: white;
        cursor: pointer;
        border-radius: 4px;
        transition: all 0.2s;
      }
      
      .pagination-btn:hover:not(:disabled) {
        background: #f5f5f5;
        border-color: #999;
      }
      
      .pagination-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
      }
      
      .pagination-btn.active {
        background: #337ab7;
        color: white;
        border-color: #337ab7;
      }
      
      .entries-select {
        margin-left: 10px;
      }
      
      .no-results {
        text-align: center;
        padding: 40px;
        color: #666;
        font-style: italic;
      }
      
      .loading {
        text-align: center;
        padding: 20px;
        color: #666;
      }
      
      /* Scrollbar styling */
      .table-container::-webkit-scrollbar {
        height: 8px;
      }
      
      .table-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
      }
      
      .table-container::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 4px;
      }
      
      .table-container::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
      }
      
      /* Add scroll indicator shadow */
      .table-container {
        position: relative;
      }
      
      .table-container::after {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        width: 20px;
        background: linear-gradient(to left, rgba(255,255,255,0.8), transparent);
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.3s ease;
      }
      
      .table-container.has-scroll::after {
        opacity: 1;
      }
        .search-container {
          flex-direction: column;
          align-items: stretch;
        }
        
        .search-box {
          min-width: 100%;
          max-width: 100%;
        }
        
        .pagination-container {
          flex-direction: column;
          align-items: center;
        }
        
        .pagination-controls {
          flex-wrap: wrap;
          justify-content: center;
        }
      }
    </style>
  </head>
  <body>
    <div class="container">
      <h2> Update Other Resources</h2>
      

      <div class="search-container">
        <div class="search-box">
          <div class="input-group">
            <input type="text" id="searchInput" class="form-control" placeholder="Search courses by title or description...">
            <span class="input-group-addon">
              <i class="fa fa-search"></i>
            </span>
          </div>
        </div>
        
        <div class="pagination-info">
          <span id="entriesInfo">Showing 0 of 0 entries</span>
          <select id="entriesPerPage" class="form-control entries-select" style="width: auto; display: inline-block;">
            <option value="10">10 per page</option>
            <option value="25">25 per page</option>
            <option value="50">50 per page</option>
            <option value="100">100 per page</option>
          </select>
        </div>
      </div>
      
 
      <div class="table-container">
        <table class="table table-bordered" id="coursesTable">
          <thead>
            <tr>
              <th>Image</th>
              <th>Title</th>
              <th>Description</th>
              <th>Link</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="coursesTableBody">
         
          </tbody>
        </table>
        
        <div id="noResults" class="no-results" style="display: none;">
          <i class="fa fa-search fa-2x"></i>
          <p>No courses found matching your search criteria.</p>
        </div>
        
        <div id="loading" class="loading">
          <i class="fa fa-spinner fa-spin"></i>
          Loading courses...
        </div>
      </div>
      

      <div class="pagination-container">
        <div class="pagination-info">
          <span id="paginationInfo"></span>
        </div>
        
        <div class="pagination-controls">
          <button class="pagination-btn" id="firstBtn" onclick="goToPage(1)">
            <i class="fa fa-angle-double-left"></i>
          </button>
          <button class="pagination-btn" id="prevBtn" onclick="goToPage(currentPage - 1)">
            <i class="fa fa-angle-left"></i>
          </button>
          
          <div id="pageNumbers"></div>
          
          <button class="pagination-btn" id="nextBtn" onclick="goToPage(currentPage + 1)">
            <i class="fa fa-angle-right"></i>
          </button>
          <button class="pagination-btn" id="lastBtn" onclick="goToPage(totalPages)">
            <i class="fa fa-angle-double-right"></i>
          </button>
        </div>
      </div>
    </div>

    <script>
      
      const allCourses = <?php echo json_encode($courses); ?>;
      
   
      let currentPage = 1;
      let entriesPerPage = 10;
      let filteredCourses = [...allCourses];
      let totalPages = 1;
      
     
      document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('loading').style.display = 'none';
        setupEventListeners();
        updateDisplay();
        checkHorizontalScroll();
        
  
        window.addEventListener('resize', checkHorizontalScroll);
      });
      
      function checkHorizontalScroll() {
        const container = document.querySelector('.table-container');
        const table = document.querySelector('.table');
        
        if (container && table) {
          if (table.scrollWidth > container.clientWidth) {
            container.classList.add('has-scroll');
          } else {
            container.classList.remove('has-scroll');
          }
        }
      }
      
      function setupEventListeners() {
  
        const searchInput = document.getElementById('searchInput');
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
          clearTimeout(searchTimeout);
          searchTimeout = setTimeout(() => {
            performSearch();
          }, 300); 
        });
        
  
        document.getElementById('entriesPerPage').addEventListener('change', function() {
          entriesPerPage = parseInt(this.value);
          currentPage = 1;
          updateDisplay();
        });
        
       
        searchInput.addEventListener('keydown', function(e) {
          if (e.key === 'Escape') {
            this.value = '';
            performSearch();
          }
        });
      }
      
      function performSearch() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
        
        if (searchTerm === '') {
          filteredCourses = [...allCourses];
        } else {
          filteredCourses = allCourses.filter(course => 
            course.course_title.toLowerCase().includes(searchTerm) ||
            course.course_description.toLowerCase().includes(searchTerm)
          );
        }
        
        currentPage = 1;
        updateDisplay();
      }
      
      function updateDisplay() {
        updateTableContent();
        updatePagination();
        updateEntriesInfo();
     
        setTimeout(checkHorizontalScroll, 50);
      }
      
      function updateTableContent() {
        const tbody = document.getElementById('coursesTableBody');
        const noResults = document.getElementById('noResults');
        const table = document.getElementById('coursesTable');
        
        if (filteredCourses.length === 0) {
          table.style.display = 'none';
          noResults.style.display = 'block';
          return;
        }
        
        table.style.display = 'table';
        noResults.style.display = 'none';
        
  
        totalPages = Math.ceil(filteredCourses.length / entriesPerPage);
        const startIndex = (currentPage - 1) * entriesPerPage;
        const endIndex = Math.min(startIndex + entriesPerPage, filteredCourses.length);
        const currentCourses = filteredCourses.slice(startIndex, endIndex);
      
        tbody.innerHTML = currentCourses.map(course => `
          <tr>
            <td>
              <img src="${escapeHtml(course.course_image)}" 
                   alt="Course Image" 
                   width="90" 
                   style="border-radius: 4px; object-fit: cover;"
                   onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iOTAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA5MCA2MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjkwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjRjVGNUY1Ii8+CjxwYXRoIGQ9Ik0zNyAyNUw0MyAzMUwzNyAzN0gzM1YyNUgzN1oiIGZpbGw9IiM5OTk5OTkiLz4KPHN2Zz4K';">
            </td>
            <td><strong>${escapeHtml(course.course_title)}</strong></td>
            <td>${escapeHtml(truncateText(course.course_description, 150))}</td>
            <td>
              <a href="${escapeHtml(course.course_link)}" 
                 target="_blank" 
                 title="View Course">
                <span class="label label-info">
                  <i class="fa fa-eye"></i> View
                </span>
              </a>
            </td>
            <td>
              <a href="index.php?view=update_resource&id=${course.id}" 
                 title="Edit Course">
                <span class="label label-warning">
                  <i class="fa fa-edit"></i>
                </span>
              </a>
              <a href="index.php?view=delete_course&id=${course.id}" 
                 title="Delete Course"
                 onclick="return confirm('Are you sure you want to delete this course?')">
                <span class="label label-danger">
                  <i class="fa fa-trash"></i>
                </span>
              </a>
            </td>
          </tr>
        `).join('');
      }
      
      function updatePagination() {
        const pageNumbers = document.getElementById('pageNumbers');
        const firstBtn = document.getElementById('firstBtn');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const lastBtn = document.getElementById('lastBtn');
        
        // Update button states
        firstBtn.disabled = currentPage === 1;
        prevBtn.disabled = currentPage === 1;
        nextBtn.disabled = currentPage === totalPages || totalPages === 0;
        lastBtn.disabled = currentPage === totalPages || totalPages === 0;
        
        // Generate page numbers
        pageNumbers.innerHTML = generatePageNumbers();
      }
      
      function generatePageNumbers() {
        if (totalPages <= 1) return '';
        
        const maxVisiblePages = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
        
        if (endPage - startPage < maxVisiblePages - 1) {
          startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }
        
        let html = '';
        
        for (let i = startPage; i <= endPage; i++) {
          html += `
            <button class="pagination-btn ${i === currentPage ? 'active' : ''}" 
                    onclick="goToPage(${i})">
              ${i}
            </button>
          `;
        }
        
        return html;
      }
      
      function updateEntriesInfo() {
        const entriesInfo = document.getElementById('entriesInfo');
        const paginationInfo = document.getElementById('paginationInfo');
        
        if (filteredCourses.length === 0) {
          entriesInfo.textContent = 'Showing 0 of 0 entries';
          paginationInfo.textContent = '';
          return;
        }
        
        const startIndex = (currentPage - 1) * entriesPerPage + 1;
        const endIndex = Math.min(currentPage * entriesPerPage, filteredCourses.length);
        
        entriesInfo.textContent = `Showing ${startIndex} to ${endIndex} of ${filteredCourses.length} entries`;
        
        if (allCourses.length !== filteredCourses.length) {
          entriesInfo.textContent += ` (filtered from ${allCourses.length} total)`;
        }
        
        if (totalPages > 1) {
          paginationInfo.textContent = `Page ${currentPage} of ${totalPages}`;
        } else {
          paginationInfo.textContent = '';
        }
      }
      
      function goToPage(page) {
        if (page >= 1 && page <= totalPages) {
          currentPage = page;
          updateDisplay();
          
          // Scroll to top of table smoothly
          document.querySelector('.table-container').scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }
      }
      
      function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
      }
      
      function truncateText(text, maxLength) {
        if (text.length <= maxLength) return text;
        return text.substr(0, maxLength) + '...';
      }
      
      // Keyboard navigation
      document.addEventListener('keydown', function(e) {
        if (e.target.tagName === 'INPUT') return;
        
        switch(e.key) {
          case 'ArrowLeft':
            if (currentPage > 1) {
              e.preventDefault();
              goToPage(currentPage - 1);
            }
            break;
          case 'ArrowRight':
            if (currentPage < totalPages) {
              e.preventDefault();
              goToPage(currentPage + 1);
            }
            break;
          case '/':
            e.preventDefault();
            document.getElementById('searchInput').focus();
            break;
        }
      });
    </script>
  </body>
</html>