<?php
require_once __DIR__ . '/../config/auth_check.php';

$default_status = isset($_GET['status']) ? trim($_GET['status']) : '';
?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>My Reports - Project Report List</title>
    <link rel="shortcut icon" href="../images/favicon.png" />
    <link rel="stylesheet" href="../styles/my-records.css">
    <link rel="stylesheet" href="../styles/project/my-project-reports.css">
    <link rel="stylesheet" href="../styles/header-sidebar.css">
    <link rel="stylesheet" href="../styles/common.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  </head>

  <body>
    <div class="my-records-page-container page-containers">

      <!-- Page Header -->
      <header class="page-header-wrapper">
        <div class="page-title-section">
          <h1>My Reports</h1>
        </div>
        <!-- <div class="header-actions">
          <button class="btn-icon-text" id="btn-refresh" aria-label="Refresh list">
            <i class="fa-solid fa-rotate"></i> Refresh
          </button>
          <a href="my-projects.php" class="btn-icon-text btn-primary">
            <i class="fa-solid fa-briefcase"></i> My Projects
          </a>
        </div> -->
      </header>

      <!-- Stats Grid Summary -->
      <section class="stats-grid-container" aria-label="Project reports summary">
        <div class="stat-card-wrapper">
          <div class="stat-icon-box total" aria-hidden="true">
            <i class="fa-solid fa-list-check"></i>
          </div>
          <div class="stat-info-box">
            <span class="value" id="stat-total">—</span>
            <span class="label" id="stat-total-label">Total Today</span>
          </div>
        </div>

        <div class="stat-card-wrapper">
          <div class="stat-icon-box pending" aria-hidden="true">
            <i class="fa-solid fa-hourglass-half"></i>
          </div>
          <div class="stat-info-box">
            <span class="value" id="stat-pending">—</span>
            <span class="label">Pending</span>
          </div>
        </div>

        <div class="stat-card-wrapper">
          <div class="stat-icon-box in-progress" aria-hidden="true">
            <i class="fa-solid fa-spinner"></i>
          </div>
          <div class="stat-info-box">
            <span class="value" id="stat-in-progress">—</span>
            <span class="label">In Progress</span>
          </div>
        </div>

        <div class="stat-card-wrapper">
          <div class="stat-icon-box on-hold" aria-hidden="true">
            <i class="fa-solid fa-pause"></i>
          </div>
          <div class="stat-info-box">
            <span class="value" id="stat-on-hold">—</span>
            <span class="label">On Hold</span>
          </div>
        </div>

        <div class="stat-card-wrapper">
          <div class="stat-icon-box completed" aria-hidden="true">
            <i class="fa-solid fa-circle-check"></i>
          </div>
          <div class="stat-info-box">
            <span class="value" id="stat-completed">—</span>
            <span class="label">Completed</span>
          </div>
        </div>

        <div class="stat-card-wrapper">
          <div class="stat-icon-box support-time" aria-hidden="true">
            <i class="fa-solid fa-clock"></i>
          </div>
          <div class="stat-info-box">
            <span class="value" id="stat-total-time">—</span>
            <span class="label">Total Time</span>
          </div>
        </div>
      </section>

      <!-- Main Report Card -->
      <main class="report-card-wrapper">

        <!-- Filter/Search Bar -->
        <div class="table-filter-bar">
          <div class="filter-controls">
            <div class="search-input-wrapper">
              <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
              <input type="text" id="search-records" class="search-control" placeholder="Search project reports..." aria-label="Search records">
            </div>

            <div class="select-filter-wrapper">
              <i class="fa-solid fa-calendar-days select-icon"></i>
              <select id="date-range-filter" class="filter-select-control" aria-label="Filter by date range">
                <option value="today" selected>Today</option>
                <option value="week">This Week</option>
                <option value="month">This Month</option>
                <option value="year">This Year</option>
                <option value="custom">Custom Range</option>
              </select>
            </div>

            <div class="select-filter-wrapper status-filter-wrapper">
              <i class="fa-solid fa-list-check select-icon"></i>
              <select id="status-filter" class="filter-select-control" aria-label="Filter by status">
                <option value="" <?php echo $default_status === '' ? 'selected' : ''; ?>>All Statuses</option>
                <option value="Pending" <?php echo $default_status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="In Progress" <?php echo $default_status === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                <option value="On Hold" <?php echo $default_status === 'On Hold' ? 'selected' : ''; ?>>On Hold</option>
                <option value="Completed" <?php echo $default_status === 'Completed' ? 'selected' : ''; ?>>Completed</option>
              </select>
            </div>

            <div id="custom-date-inputs" class="custom-date-inputs-wrapper">
              <div class="date-input-group">
                <span class="date-input-label">From:</span>
                <input type="date" id="from-date-filter" class="filter-date-control" aria-label="From date">
              </div>
              <div class="date-input-group">
                <span class="date-input-label">To:</span>
                <input type="date" id="to-date-filter" class="filter-date-control" aria-label="To date">
              </div>
              <button type="button" id="btn-custom-date-search" class="custom-date-search-btn" title="Search Date Range">
                <i class="fa-solid fa-magnifying-glass"></i>
              </button>
            </div>

            <div class="records-count-badge" id="records-count-badge" title="Total project reports">
              <span class="count-label">Total:</span>
              <strong class="count-value" id="records-count-val">0</strong>
            </div>
          </div>
        </div>

        <!-- Table View -->
        <div class="table-responsive-wrapper">
          <table class="custom-report-table" id="records-table">
            <thead>
              <tr>
                <th scope="col">Sl. No.</th>
                <th scope="col">Date</th>
                <th scope="col">Project ID</th>
                <th scope="col">Subtask</th>
                <th scope="col">Project Name</th>
                <th scope="col">Total Time</th>
                <th scope="col">Status</th>
                <th scope="col">Actions</th>
              </tr>
            </thead>
            <tbody id="records-tbody">
              <!-- Loading Skeleton Rows -->
              <tr class="skeleton-row">
                <td><div class="skeleton-line w-30"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-70"></div></td>
                <td><div class="skeleton-line w-65"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-badge"></div></td>
                <td class="actions-cell"><div class="skeleton-line w-60 center"></div></td>
              </tr>
              <tr class="skeleton-row">
                <td><div class="skeleton-line w-30"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-70"></div></td>
                <td><div class="skeleton-line w-65"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-badge"></div></td>
                <td class="actions-cell"><div class="skeleton-line w-60 center"></div></td>
              </tr>
              <tr class="skeleton-row">
                <td><div class="skeleton-line w-30"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-70"></div></td>
                <td><div class="skeleton-line w-65"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-badge"></div></td>
                <td class="actions-cell"><div class="skeleton-line w-60 center"></div></td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Controls -->
        <div class="pagination-container" id="pagination-wrapper">
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
    <script src="../js/project/my-project-reports.js?v=<?php echo file_exists(__DIR__ . '/../js/project/my-project-reports.js') ? filemtime(__DIR__ . '/../js/project/my-project-reports.js') : time(); ?>"></script>
  </body>

</html>
