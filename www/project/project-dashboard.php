<?php
// Secure the page - ensure user is authenticated
require_once __DIR__ . '/../config/auth_check.php';

// Resolve user details and profile photo path
$user_id = isset($_SESSION['id']) ? $_SESSION['id'] : '';
$user_full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Admin';
$user_department = isset($_SESSION['department']) ? $_SESSION['department'] : '';

// Determine default selected tab ('voice_logger' or 'ivr') based on user's department
$default_dept = 'voice_logger';
if (strcasecmp($user_department, 'ivr') === 0) {
    $default_dept = 'ivr';
}

// Extract first name for the greeting (e.g. "Hello, Eldhos!")
$user_first_name = 'Admin';
if (!empty($user_full_name)) {
    $name_parts = explode(' ', trim($user_full_name));
    $user_first_name = $name_parts[0];
}

$profile_photo_url = '../images/default-avatar.png'; // default fallback
$allowed_extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
if (!empty($user_id)) {
    foreach ($allowed_extensions as $ext) {
        $photo_path = __DIR__ . '/../images/profile/' . $user_id . '.' . $ext;
        if (file_exists($photo_path)) {
            $profile_photo_url = '../images/profile/' . $user_id . '.' . $ext . '?t=' . filemtime($photo_path);
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Project Dashboard | Xtend Agent Reports</title>
    <link rel="shortcut icon" href="../images/favicon.png" />
    <link rel="stylesheet" href="../styles/dashboard.css">
    <link rel="stylesheet" href="../styles/header-sidebar.css">
    <link rel="stylesheet" href="../styles/common.css">
    <link rel="stylesheet" href="../styles/project/project-dashboard.css">
  </head>

  <body>
    <div class="dashboard-page-container page-containers" data-default-dept="<?php echo htmlspecialchars($default_dept); ?>">
      
      <!-- Top Header Title Section -->
      <div class="dashboard-header">
        <div class="dashboard-title-section">
          <div class="dashboard-profile-avatar-container">
            <?php if ($profile_photo_url !== '../images/default-avatar.png'): ?>
              <img src="<?php echo $profile_photo_url; ?>" alt="User Avatar" class="dashboard-profile-avatar-img">
            <?php else: ?>
              <div class="dashboard-profile-avatar-initials">
                <?php 
                  $initials = '';
                  if (!empty($user_full_name)) {
                    $words = explode(' ', $user_full_name);
                    foreach ($words as $word) {
                      $initials .= strtoupper(substr($word, 0, 1));
                    }
                    $initials = substr($initials, 0, 2);
                  }
                  echo htmlspecialchars($initials ?: '?');
                ?>
              </div>
            <?php endif; ?>
          </div>
          <div class="dashboard-title-text-wrapper">
            <div class="dashboard-title">
              Hello, <?php echo htmlspecialchars($user_first_name); ?>!
            </div>
            <div class="dashboard-subtitle">Track project progress and team performance here.</div>
          </div>
        </div>

        <div class="dashboard-filter-container">
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
        </div>
      </div>

      <!-- Department Tabs (Pill Buttons) -->
      <div class="tabs-wrapper">
        <div class="tabs-container">
          <button class="tab-btn<?php echo $default_dept === 'voice_logger' ? ' active' : ''; ?>" data-dept="voice_logger">
            <i class="fa-solid fa-microphone-lines"></i> Voice Logger
          </button>
          <button class="tab-btn<?php echo $default_dept === 'ivr' ? ' active' : ''; ?>" data-dept="ivr">
            <i class="fa-solid fa-phone-volume"></i> IVR
          </button>
        </div>
      </div>

      <!-- Stats Summary Cards Row -->
      <div class="stats-grid">

        <!-- Card 1: Today's Reports -->
        <div class="stat-card today-card">
          <div class="stat-icon">
            <i class="fa-solid fa-calendar-day"></i>
          </div>
          <div class="stat-details">
            <span class="stat-value" id="valTodayReports">-</span>
            <span class="stat-label" id="lblTodayReports">Today's Reports</span>
          </div>
        </div>

        <!-- Card 2: Registered/Active Agents -->
        <div class="stat-card agents-card">
          <div class="stat-icon">
            <i class="fa-solid fa-users"></i>
          </div>
          <div class="stat-details">
            <span class="stat-value" id="valActiveAgents">-</span>
            <span class="stat-label">Total Agents</span>
          </div>
        </div>

        <!-- Card 3: Agents Active Today -->
        <div class="stat-card active-card">
          <div class="stat-icon">
            <i class="fa-solid fa-user-check"></i>
          </div>
          <div class="stat-details">
            <span class="stat-value" id="valAgentsActiveToday">-</span>
            <span class="stat-label" id="lblAgentsActiveToday">Agents Active Today</span>
          </div>
        </div>

        <!-- Card 4: Total Time -->
        <div class="stat-card support-hours-card total-time-card">
          <div class="stat-icon">
            <i class="fa-solid fa-clock"></i>
          </div>
          <div class="stat-details">
            <span class="stat-value" id="valTotalTime">-</span>
            <span class="stat-label" id="lblTotalTime">Total Time</span>
          </div>
        </div>
      </div>

      <!-- Two-Column Grid (Breakdown Details) -->
      <div class="dashboard-details-grid" style="position: relative;">
        <!-- Loading Overlay for AJAX switching -->
        <div class="loading-overlay" id="dashboardLoading">
          <div class="spinner"></div>
        </div>

        <!-- Column 1: Status Breakdown -->
        <div class="details-card">
          <div class="details-card-header">
            <h2>Status Breakdown</h2>
          </div>
          <div class="status-list" id="statusListContainer">
            <!-- Dynamically populated status items -->
          </div>
        </div>

        <!-- Column 2: Agent Performance Table -->
        <div class="details-card">
          <div class="details-card-header">
            <h2>Agent Performance Details</h2>
          </div>
          <div class="agent-table-wrapper">
            <table class="agent-table">
              <thead>
                <tr>
                  <th>Agent Name</th>
                  <th>Username</th>
                  <th>Role</th>
                  <th id="thAgentTimeframeReports">Today's Reports</th>
                  <th>Total Time</th>
                  <th>Last Report</th>
                </tr>
              </thead>
              <tbody id="agentTableBody">
                <!-- Dynamically populated agent rows -->
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>

    <!-- Include Sidebar & Header Component -->
    <?php include __DIR__ . '/../components/header-sidebar.php'; ?>

    <script src="../plugins/jquery-3.7.1.min.js"></script>
    <script src="../js/project/project-dashboard.js"></script>
  </body>

</html>
