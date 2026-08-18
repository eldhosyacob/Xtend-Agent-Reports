<?php
require_once __DIR__ . '/config/auth_check.php';

$default_dept = isset($_GET['department']) ? trim($_GET['department']) : 'voice_logger';
$default_range = isset($_GET['range']) ? trim($_GET['range']) : 'today';
$default_status = isset($_GET['status']) ? trim($_GET['status']) : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Timeframe Records Details | Xtend Agent Reports</title>
  <link rel="shortcut icon" href="images/favicon.png" />
  <link rel="stylesheet" href="styles/today-records.css">
  <link rel="stylesheet" href="styles/header-sidebar.css">
  <link rel="stylesheet" href="styles/common.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
  <div class="details-page-container page-containers">

    <!-- Top Header Card -->
    <div class="page-header-card">
      <div class="page-header-title">
        <a href="dashboard.php" class="back-btn" title="Back to Dashboard">
          <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div class="page-title-text">
          <h1 id="pageTitleHeading">Timeframe Records Details</h1>
          <p id="pageSubtitle">Detailed record breakdown for the selected timeframe and department.</p>
        </div>
      </div>
    </div>

    <!-- Filter Bar Card -->
    <div class="filter-bar-card">
      <div class="filter-left-group">
        <!-- Search Input -->
        <div class="search-wrapper">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" id="search-input" class="search-input" placeholder="Search by Agent, Company..." autocomplete="off">
        </div>

        <!-- Department Filter -->
        <select id="department-filter" class="select-filter-control">
          <option value="voice_logger" <?php echo $default_dept === 'voice_logger' ? 'selected' : ''; ?>>Voice Logger</option>
          <option value="ivr" <?php echo $default_dept === 'ivr' ? 'selected' : ''; ?>>IVR</option>
        </select>

        <!-- Date Range Filter -->
        <select id="range-filter" class="select-filter-control">
          <option value="today" <?php echo $default_range === 'today' ? 'selected' : ''; ?>>Today</option>
          <option value="week" <?php echo $default_range === 'week' ? 'selected' : ''; ?>>This Week</option>
          <option value="month" <?php echo $default_range === 'month' ? 'selected' : ''; ?>>This Month</option>
          <option value="year" <?php echo $default_range === 'year' ? 'selected' : ''; ?>>This Year</option>
        </select>

        <!-- Status Filter -->
        <select id="status-filter" class="select-filter-control">
          <option value="">All</option>
          <option value="Closed" <?php echo $default_status === 'Closed' ? 'selected' : ''; ?>>Closed</option>
          <option value="Pending" <?php echo $default_status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
          <option value="Under Observation" <?php echo $default_status === 'Under Observation' ? 'selected' : ''; ?>>Under Observation</option>
          <option value="Escalated" <?php echo $default_status === 'Escalated' ? 'selected' : ''; ?>>Escalated</option>
          <option value="Escalated to Presales" <?php echo $default_status === 'Escalated to Presales' ? 'selected' : ''; ?>>Escalated to Presales</option>
          <option value="Closed-Device Replaced" <?php echo $default_status === 'Closed-Device Replaced' ? 'selected' : ''; ?>>Closed-Device Replaced</option>
          <option value="Closed-No Response" <?php echo $default_status === 'Closed-No Response' ? 'selected' : ''; ?>>Closed-No Response</option>
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
              <th>Agent</th>
              <th>Company Name</th>
              <th>Dealer Name</th>
              <th>Location</th>
              <th>Issue Category</th>
              <th>Support Status</th>
            </tr>
          </thead>
          <tbody id="recordsTableBody">
            <!-- Dynamically populated via jQuery AJAX -->
          </tbody>
        </table>
      </div>

      <!-- Pagination Wrapper -->
      <div class="pagination-wrapper">
        <div class="pagination-info" id="paginationInfo">Showing records...</div>
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
  <?php include 'components/header-sidebar.php'; ?>

  <script src="plugins/jquery-3.7.1.min.js"></script>
  <script src="js/today-records.js"></script>
</body>

</html>
