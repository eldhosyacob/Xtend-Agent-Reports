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
    <link rel="stylesheet" href="../styles/project/my-projects.css">
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
        <div class="stat-metric-card">
          <div class="stat-metric-icon stat-icon-projects">
            <i class="fa-solid fa-diagram-project"></i>
          </div>
          <div class="stat-metric-content">
            <span class="stat-metric-value" id="statProjectsVal">0</span>
            <span class="stat-metric-label">Assigned Projects</span>
          </div>
        </div>

        <div class="stat-metric-card">
          <div class="stat-metric-icon stat-icon-tasks">
            <i class="fa-solid fa-list-check"></i>
          </div>
          <div class="stat-metric-content">
            <span class="stat-metric-value" id="statTasksVal">0</span>
            <span class="stat-metric-label">Total Tasks</span>
          </div>
        </div>

        <div class="stat-metric-card">
          <div class="stat-metric-icon stat-icon-pending">
            <i class="fa-solid fa-clock"></i>
          </div>
          <div class="stat-metric-content">
            <span class="stat-metric-value" id="statPendingVal">0</span>
            <span class="stat-metric-label">Pending Tasks</span>
          </div>
        </div>

        <div class="stat-metric-card">
          <div class="stat-metric-icon stat-icon-progress">
            <i class="fa-solid fa-spinner"></i>
          </div>
          <div class="stat-metric-content">
            <span class="stat-metric-value" id="statProgressVal">0</span>
            <span class="stat-metric-label">In Progress</span>
          </div>
        </div>

        <div class="stat-metric-card">
          <div class="stat-metric-icon stat-icon-completed">
            <i class="fa-solid fa-circle-check"></i>
          </div>
          <div class="stat-metric-content">
            <span class="stat-metric-value" id="statCompletedVal">0</span>
            <span class="stat-metric-label">Completed Tasks</span>
          </div>
        </div>
      </div>

      <!-- Filter Bar Card -->
      <div class="filter-bar-card">
        <div class="filter-left-group">
          <!-- Search Input -->
          <div class="search-wrapper">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="search-input" class="search-input" placeholder="Search by Project Name, ID, Task..." autocomplete="off">
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
          <select id="status-filter" class="select-filter-control" aria-label="Filter by Project Status">
            <option value="">All Statuses</option>
            <option value="Assigned">Assigned</option>
            <option value="In Progress">In Progress</option>
            <option value="On Hold">On Hold</option>
            <option value="Completed">Completed</option>
          </select>

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

      <!-- Projects Cards & Tasks Container -->
      <div style="position: relative; min-height: 200px;">
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
    <script src="../js/project/my-projects.js"></script>
  </body>

</html>
