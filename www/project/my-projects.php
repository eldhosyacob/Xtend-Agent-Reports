<?php
require_once __DIR__ . '/../config/auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>My Projects | Xtend Agent Reports</title>
    <link rel="shortcut icon" href="../images/favicon.png">
    <link rel="stylesheet" href="../styles/header-sidebar.css">
    <link rel="stylesheet" href="../styles/common.css">
    <link rel="stylesheet" href="../styles/project/project-details.css">
    <link rel="stylesheet" href="../styles/project/my-projects.css?v=<?php echo file_exists(__DIR__ . '/../styles/project/my-projects.css') ? filemtime(__DIR__ . '/../styles/project/my-projects.css') : time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  </head>

  <body>
    <div class="my-projects-container page-containers">

      <!-- Page Header Card -->
      <div class="page-header-card">
        <div class="page-header-title">
          <!-- <div class="header-icon-box">
            <i class="fa-solid fa-briefcase"></i>
          </div> -->
          <div class="page-title-text">
            <h1>My Projects</h1>
            <!-- <p>View, track, and manage projects and tasks assigned to you</p> -->
          </div>
        </div>
        <!-- <div class="header-actions">
          <a href="project-lists.php" class="btn-header-action">
            <i class="fa-solid fa-list-check"></i> All Projects
          </a>
        </div> -->
      </div>

      <!-- Quick Metrics Bar -->
      <div class="stats-summary-grid">
        <div class="stat-metric-card" data-status="" title="Filter by All Statuses">
          <div class="stat-metric-icon stat-icon-total">
            <i class="fa-solid fa-diagram-project"></i>
          </div>
          <div class="stat-metric-content">
            <span class="stat-metric-value" id="statTotalVal">0</span>
            <span class="stat-metric-label">Total</span>
          </div>
        </div>

        <div class="stat-metric-card" data-status="Assigned" title="Filter by Assigned">
          <div class="stat-metric-icon stat-icon-assigned">
            <i class="fa-solid fa-user-check"></i>
          </div>
          <div class="stat-metric-content">
            <span class="stat-metric-value" id="statAssignedVal">0</span>
            <span class="stat-metric-label">Assigned</span>
          </div>
        </div>

        <div class="stat-metric-card" data-status="In Progress" title="Filter by In Progress">
          <div class="stat-metric-icon stat-icon-progress">
            <i class="fa-solid fa-spinner"></i>
          </div>
          <div class="stat-metric-content">
            <span class="stat-metric-value" id="statProgressVal">0</span>
            <span class="stat-metric-label">In Progress</span>
          </div>
        </div>

        <div class="stat-metric-card" data-status="Pending" title="Filter by Pending">
          <div class="stat-metric-icon stat-icon-pending">
            <i class="fa-solid fa-clock"></i>
          </div>
          <div class="stat-metric-content">
            <span class="stat-metric-value" id="statPendingVal">0</span>
            <span class="stat-metric-label">Pending</span>
          </div>
        </div>

        <div class="stat-metric-card" data-status="On Hold" title="Filter by Hold">
          <div class="stat-metric-icon stat-icon-hold">
            <i class="fa-solid fa-circle-pause"></i>
          </div>
          <div class="stat-metric-content">
            <span class="stat-metric-value" id="statHoldVal">0</span>
            <span class="stat-metric-label">Hold</span>
          </div>
        </div>

        <div class="stat-metric-card" data-status="Completed" title="Filter by Completed">
          <div class="stat-metric-icon stat-icon-completed">
            <i class="fa-solid fa-circle-check"></i>
          </div>
          <div class="stat-metric-content">
            <span class="stat-metric-value" id="statCompletedVal">0</span>
            <span class="stat-metric-label">Completed</span>
          </div>
        </div>
      </div>

      <!-- Filter Bar Card -->
      <div class="filter-bar-card">
        <div class="filter-left-group">
          <!-- Search Input -->
          <div class="search-wrapper">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="search-input" class="search-input" placeholder="Search by Project Name, ID, Sub Project..." autocomplete="off">
          </div>

          <!-- Category Filter -->
          <select id="category-filter" class="select-filter-control" aria-label="Filter by Category">
            <option value="">All Categories</option>
            <option value="Driver">Driver</option>
            <option value="Hardware">Hardware</option>
            <option value="Software">Software</option>
            <option value="Integrated">Integrated</option>
          </select>

          <!-- Priority Filter -->
          <select id="priority-filter" class="select-filter-control" aria-label="Filter by Priority">
            <option value="">All Priorities</option>
            <option value="High">High</option>
            <option value="Medium">Medium</option>
            <option value="Low">Low</option>
          </select>

          <!-- Status Filter -->
          <div class="status-filter-wrapper" id="statusFilterWrapper">
            <div class="status-filter-display" id="statusFilterDisplay" tabindex="0" role="button" aria-haspopup="listbox" aria-expanded="false" title="Assigned, In Progress, Pending, On Hold">
              <span class="status-filter-text" id="statusFilterText">Statuses</span>
              <i class="fa-solid fa-chevron-down status-filter-arrow"></i>
            </div>
            <input type="hidden" id="status-filter" value="Assigned,In Progress,Pending,On Hold">

            <div class="status-filter-dropdown" id="statusFilterDropdown">
              <div class="status-filter-options-list">
                <label class="status-filter-option status-filter-all-option" id="statusAllOption">
                  <input type="checkbox" id="statusFilterAll" class="status-filter-all-checkbox">
                  <span class="status-option-label status-all-label">All Statuses</span>
                </label>
                <div class="status-filter-divider"></div>
                <label class="status-filter-option">
                  <input type="checkbox" class="status-filter-checkbox" value="Assigned" checked>
                  <span class="status-dot dot-assigned"></span>
                  <span class="status-option-label">Assigned</span>
                </label>
                <label class="status-filter-option">
                  <input type="checkbox" class="status-filter-checkbox" value="In Progress" checked>
                  <span class="status-dot dot-progress"></span>
                  <span class="status-option-label">In Progress</span>
                </label>
                <label class="status-filter-option">
                  <input type="checkbox" class="status-filter-checkbox" value="Pending" checked>
                  <span class="status-dot dot-pending"></span>
                  <span class="status-option-label">Pending</span>
                </label>
                <label class="status-filter-option">
                  <input type="checkbox" class="status-filter-checkbox" value="On Hold" checked>
                  <span class="status-dot dot-hold"></span>
                  <span class="status-option-label">On Hold</span>
                </label>
                <label class="status-filter-option">
                  <input type="checkbox" class="status-filter-checkbox" value="Completed">
                  <span class="status-dot dot-completed"></span>
                  <span class="status-option-label">Completed</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Reset Filter Button -->
          <button type="button" id="btnResetFilters" class="btn-reset-filter" title="Reset all filters">
            <i class="fa-solid fa-rotate-left"></i> Reset
          </button>
        </div>

        <div class="filter-right-group">
          <!-- <button type="button" id="btnToggleAll" class="btn-toggle-all" title="Expand or Collapse all project cards">
            <i class="fa-solid fa-expand"></i> Expand All
          </button> -->
          <span class="project-count-badge" id="projectCountBadge">Loading...</span>
        </div>
      </div>

      <!-- Projects Cards & Assigned Subprojects Container -->
      <div class="projects-cards-wrapper">
        <div class="loading-overlay" id="loadingOverlay">
          <div class="spinner"></div>
        </div>

        <div class="projects-cards-container" id="projectsCardsContainer">
          <!-- Dynamically populated via jQuery AJAX -->
        </div>
      </div>

    </div>

    <!-- Header & Sidebar Navigation Component -->
    <?php include '../components/header-sidebar.php'; ?>

    <script src="../plugins/jquery-3.7.1.min.js"></script>
    <script src="../js/project/my-projects.js?v=<?php echo time(); ?>"></script>
  </body>

</html>
