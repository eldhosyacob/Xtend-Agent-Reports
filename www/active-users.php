<?php
require_once __DIR__ . '/config/auth_check.php';

$default_dept = isset($_GET['department']) ? trim($_GET['department']) : 'voice_logger';
$default_range = isset($_GET['range']) ? trim($_GET['range']) : 'today';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Active Users Details | Xtend Agent Reports</title>
  <link rel="shortcut icon" href="images/favicon.png" />
  <link rel="stylesheet" href="styles/active-users.css">
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
          <h1>Active Users Details</h1>
          <p id="pageSubtitle">Roster of currently active online users and their timeframe contributions.</p>
        </div>
      </div>
    </div>

    <!-- Filter Bar Card -->
    <div class="filter-bar-card">
      <div class="filter-left-group">
        <div class="search-wrapper">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" id="search-input" class="search-input" placeholder="Search user name, username, role..." autocomplete="off">
        </div>

        <select id="department-filter" class="select-filter-control">
          <option value="voice_logger" <?php echo $default_dept === 'voice_logger' ? 'selected' : ''; ?>>Voice Logger</option>
          <option value="ivr" <?php echo $default_dept === 'ivr' ? 'selected' : ''; ?>>IVR</option>
        </select>

        <select id="range-filter" class="select-filter-control">
          <option value="today" <?php echo $default_range === 'today' ? 'selected' : ''; ?>>Today</option>
          <option value="week" <?php echo $default_range === 'week' ? 'selected' : ''; ?>>This Week</option>
          <option value="month" <?php echo $default_range === 'month' ? 'selected' : ''; ?>>This Month</option>
          <option value="year" <?php echo $default_range === 'year' ? 'selected' : ''; ?>>This Year</option>
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
              <th>User Details</th>
              <th>Role</th>
              <th>Department</th>
              <th>Timeframe Records</th>
              <th>Total Records</th>
              <th>Last Active Timestamp</th>
            </tr>
          </thead>
          <tbody id="activeUsersTableBody">
            <!-- Dynamically populated via jQuery AJAX -->
          </tbody>
        </table>
      </div>

      <!-- Pagination Wrapper -->
      <div class="pagination-wrapper">
        <div class="pagination-info" id="paginationInfo">Showing active users...</div>
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
  <script src="js/active-users.js"></script>
</body>

</html>
