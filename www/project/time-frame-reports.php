<?php
require_once __DIR__ . '/../config/auth_check.php';

$default_dept = isset($_GET['department']) ? trim($_GET['department']) : 'voice_logger';
$default_range = isset($_GET['range']) ? trim($_GET['range']) : 'today';
$default_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$default_from_date = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$default_to_date = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Timeframe Reports Details | Xtend Agent Reports</title>
  <link rel="shortcut icon" href="../images/favicon.png" />
  <link rel="stylesheet" href="../styles/today-records.css">
  <link rel="stylesheet" href="../styles/header-sidebar.css">
  <link rel="stylesheet" href="../styles/common.css">
  <link rel="stylesheet" href="../styles/project/time-frame-reports.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
  <div class="details-page-container page-containers">

    <!-- Top Header Card -->
    <div class="page-header-card">
      <div class="page-header-title">
        <a href="project-dashboard.php" class="back-btn" title="Back to Project Dashboard">
          <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div class="page-title-text">
          <h1 id="pageTitleHeading">Timeframe Reports Details</h1>
          <p id="pageSubtitle">Detailed project report breakdown for the selected timeframe and department.</p>
        </div>
      </div>
    </div>

    <!-- Filter Bar Card -->
    <div class="filter-bar-card">
      <div class="filter-left-group">
        <!-- Search Input -->
        <div class="search-wrapper">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" id="search-input" class="search-input" placeholder="Search by Agent, Project, Subtask, Client..." autocomplete="off">
        </div>

        <!-- Department Filter -->
        <select id="department-filter" class="select-filter-control" aria-label="Department filter">
          <option value="voice_logger" <?php echo $default_dept === 'voice_logger' ? 'selected' : ''; ?>>Voice Logger</option>
          <option value="ivr" <?php echo $default_dept === 'ivr' ? 'selected' : ''; ?>>IVR</option>
        </select>

        <!-- Date Range Filter -->
        <select id="range-filter" class="select-filter-control" aria-label="Date range filter">
          <option value="today" <?php echo $default_range === 'today' ? 'selected' : ''; ?>>Today</option>
          <option value="week" <?php echo $default_range === 'week' ? 'selected' : ''; ?>>This Week</option>
          <option value="month" <?php echo $default_range === 'month' ? 'selected' : ''; ?>>This Month</option>
          <option value="year" <?php echo $default_range === 'year' ? 'selected' : ''; ?>>This Year</option>
          <option value="custom" <?php echo $default_range === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
        </select>

        <!-- Custom Date Range Inputs Wrapper -->
        <div id="custom-date-inputs" class="custom-date-inputs-wrapper<?php echo $default_range === 'custom' ? ' active' : ''; ?>">
          <div class="date-input-group">
            <span class="date-input-label">From:</span>
            <input type="date" id="from-date-filter" class="filter-date-control" value="<?php echo htmlspecialchars($default_from_date); ?>" aria-label="From date">
          </div>
          <div class="date-input-group">
            <span class="date-input-label">To:</span>
            <input type="date" id="to-date-filter" class="filter-date-control" value="<?php echo htmlspecialchars($default_to_date); ?>" aria-label="To date">
          </div>
          <button type="button" id="btn-custom-date-search" class="custom-date-search-btn" title="Search Date Range">
            <i class="fa-solid fa-magnifying-glass"></i>
          </button>
        </div>

        <!-- Status Filter -->
        <select id="status-filter" class="select-filter-control" aria-label="Status filter">
          <option value="">All Statuses</option>
          <option value="Completed" <?php echo strcasecmp($default_status, 'Completed') === 0 ? 'selected' : ''; ?>>Completed</option>
          <option value="In Progress" <?php echo strcasecmp($default_status, 'In Progress') === 0 ? 'selected' : ''; ?>>In Progress</option>
          <option value="Pending" <?php echo strcasecmp($default_status, 'Pending') === 0 ? 'selected' : ''; ?>>Pending</option>
          <option value="On Hold" <?php echo strcasecmp($default_status, 'On Hold') === 0 ? 'selected' : ''; ?>>On Hold</option>
        </select>
      </div>
    </div>

    <!-- Details Table Card -->
    <div class="details-table-card">
      <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
      </div>

      <div class="table-responsive">
        <table class="records-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Project ID</th>
              <th>Subtask</th>
              <th>Agent</th>
              <th>Client Name</th>
              <th>Project Name</th>
              <th>Total Time</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody id="reportsTableBody">
            <!-- Dynamically populated via jQuery AJAX -->
          </tbody>
        </table>
      </div>

      <!-- Pagination Wrapper -->
      <div class="pagination-wrapper">
        <div class="pagination-info" id="paginationInfo">Showing reports...</div>
        <div class="pagination-btns">
          <button type="button" class="page-btn" id="btnPrevPage" disabled>
            <i class="fa-solid fa-chevron-left"></i> Previous
          </button>
          <button type="button" class="page-btn" id="btnNextPage" disabled>
            Next <i class="fa-solid fa-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>

  </div>

  <!-- Header & Sidebar Navigation Component -->
  <?php include __DIR__ . '/../components/header-sidebar.php'; ?>

  <script src="../plugins/jquery-3.7.1.min.js"></script>
  <script src="../js/project/time-frame-reports.js"></script>
</body>

</html>
