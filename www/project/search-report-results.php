<?php
require_once __DIR__ . '/../config/auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Search Results | Xtend Agent Reports</title>
    <link rel="shortcut icon" href="../images/favicon.png" />
    <link rel="stylesheet" href="../styles/search-results.css">
    <link rel="stylesheet" href="../styles/project/my-project-reports.css">
    <link rel="stylesheet" href="../styles/project/search-project-reports.css">
    <link rel="stylesheet" href="../styles/header-sidebar.css">
    <link rel="stylesheet" href="../styles/common.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  </head>

  <body>
    <div class="search-results-page-container page-containers">
      
      <!-- Page Header -->
      <header class="page-header-wrapper">
        <div class="page-title-section">
          <h1>Search Results</h1>
        </div>
      </header>

      <!-- Main Report Card -->
      <main class="report-card-wrapper">
        
        <!-- Filter/Search Bar within results -->
        <div class="table-filter-bar">
          <div class="results-info-badge" id="results-badge">
            Loading results...
          </div>
          <div class="table-filter-actions">
            <button class="btn-icon-text btn-primary" id="btn-export" disabled aria-label="Export to CSV">
              <i class="fa-solid fa-file-csv"></i> Export
            </button>
          </div>
        </div>

        <!-- Floating-Row Table View -->
        <div class="table-responsive-wrapper">
          <table class="custom-search-table" id="records-table">
            <thead>
              <tr>
                <th scope="col">Sl. No.</th>
                <!-- <th scope="col">Report ID</th> -->
                <th scope="col">Date</th>
                <th scope="col">Agent</th>
                <th scope="col">Project Name</th>
                <th scope="col">Client Name</th>
                <th scope="col">Product Name</th>
                <th scope="col">Product ID</th>
                <!-- <th scope="col">Project ID</th> -->
                <th scope="col">Status</th>
                <th scope="col">Action</th>
              </tr>
            </thead>
            <tbody id="records-tbody">
              <!-- Loading Skeleton Rows (9 columns) -->
              <tr class="skeleton-row">
                <td><div class="skeleton-line w-30"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-70"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-badge"></div></td>
                <td class="actions-cell"><div class="skeleton-line w-60 center"></div></td>
              </tr>
              <tr class="skeleton-row">
                <td><div class="skeleton-line w-30"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-70"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-badge"></div></td>
                <td class="actions-cell"><div class="skeleton-line w-60 center"></div></td>
              </tr>
              <tr class="skeleton-row">
                <td><div class="skeleton-line w-30"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-70"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-badge"></div></td>
                <td class="actions-cell"><div class="skeleton-line w-60 center"></div></td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Controls -->
        <div class="pagination-container" id="pagination-wrapper" style="display: none;">
          <button class="btn-pagination" id="btn-prev" disabled>
            <i class="fa-solid fa-chevron-left"></i> Previous
          </button>
          <div class="pagination-pages" id="pagination-pages"></div>
          <button class="btn-pagination" id="btn-next" disabled>
            Next <i class="fa-solid fa-chevron-right"></i>
          </button>
        </div>

      </main>

    </div>

    <?php include __DIR__ . '/../components/header-sidebar.php'; ?>

    <script src="../plugins/jquery-3.7.1.min.js"></script>
    <script src="../js/project/search-report-results.js"></script>
  </body>

</html>
