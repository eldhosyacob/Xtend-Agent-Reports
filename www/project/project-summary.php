<?php
require_once __DIR__ . '/../config/auth_check.php';

$project_id = isset($_GET['project_id']) ? htmlspecialchars(trim($_GET['project_id'])) : (isset($_GET['id']) ? htmlspecialchars(trim($_GET['id'])) : '');
?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Project Summary - <?php echo $project_id ? $project_id : 'Project'; ?> | Xtend Agent Reports</title>
    <link rel="shortcut icon" href="../images/favicon.png">
    <link rel="stylesheet" href="../styles/header-sidebar.css">
    <link rel="stylesheet" href="../styles/common.css">
    <link rel="stylesheet" href="../styles/project/project-details.css">
    <link rel="stylesheet" href="../styles/project/project-summary.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  </head>

  <body>
    <div class="project-details-container project-summary-container page-containers" data-project-id="<?php echo $project_id; ?>">

      <!-- Navigation & Top Actions Bar -->
      <!-- <div class="summary-top-nav">
        <div class="summary-breadcrumbs">
          <a href="<?php echo $project_id ? 'project-details.php?project_id=' . urlencode($project_id) : 'project-lists.php'; ?>" class="back-link-btn" id="backToDetailsLink" title="Back to Project Details">
            <i class="fa-solid fa-arrow-left"></i>
          </a>
          <div class="breadcrumb-trail">
            <a href="project-lists.php">Projects</a>
            <i class="fa-solid fa-chevron-right breadcrumb-separator"></i>
            <a href="<?php echo $project_id ? 'project-details.php?project_id=' . urlencode($project_id) : 'project-lists.php'; ?>" id="breadcrumbProjectLink">
              <span id="breadcrumbProjectName">Project Details</span>
            </a>
            <i class="fa-solid fa-chevron-right breadcrumb-separator"></i>
            <span class="breadcrumb-current">Project Summary</span>
          </div>
        </div>

        <div class="summary-nav-actions">
          <a href="<?php echo $project_id ? 'project-details.php?project_id=' . urlencode($project_id) : 'project-lists.php'; ?>" class="btn-secondary-action" id="btnNavProjectDetails">
            <i class="fa-solid fa-diagram-project"></i> Project Details
          </a>
          <button type="button" class="btn-print-action" id="btnPrintSummary">
            <i class="fa-solid fa-print"></i> Print Summary
          </button>
        </div>
      </div> -->

      <!-- Project Overview Card -->
      <div class="project-overview-card">
        <div class="overview-card-header">
          <div class="header-title-box">
            <!-- <div class="project-avatar-icon">
              <i class="fa-solid fa-chart-pie"></i>
            </div> -->
            <div class="header-text-content">
              <h1 id="projectTitle">Loading project summary...</h1>
              <div class="header-meta-badges">
                <span class="header-id-pill" id="projectIdBadge"><?php echo $project_id; ?></span>
                <span class="badge-category" id="projectCategoryBadge">...</span>
                <span class="badge-priority" id="projectPriorityBadge">...</span>
                <span class="badge-status" id="projectStatusBadge">...</span>
              </div>
            </div>
          </div>

          <!-- <div class="header-right-actions">
            <div class="header-assignee-box">
              <div class="user-chip assignee-chip" id="projectAssigneeDisplay">
                <span class="assignee-avatar">?</span>
                <span>Assignee</span>
              </div>
            </div>
          </div> -->
        </div>

        <div class="overview-card-body">
          <div class="metadata-grid">
            <div class="metadata-item">
              <span class="meta-label"><i class="fa-regular fa-calendar-days"></i> Project Date</span>
              <span class="meta-value" id="metaProjectDate">—</span>
            </div>
            <div class="metadata-item">
              <span class="meta-label"><i class="fa-regular fa-building"></i> Client / Dealer Name</span>
              <span class="meta-value" id="metaClientDealerName">—</span>
            </div>
            <div class="metadata-item">
              <span class="meta-label"><i class="fa-solid fa-users-gear"></i> Assigned By</span>
              <span class="meta-value" id="metaAssignedBy">—</span>
            </div>
            <div class="metadata-item">
              <span class="meta-label"><i class="fa-solid fa-user"></i> Created By</span>
              <span class="meta-value" id="metaCreatedBy">—</span>
            </div>
            <div class="metadata-item">
              <span class="meta-label"><i class="fa-regular fa-clock"></i> Total Duration</span>
              <span class="meta-value" id="metaTotalDuration">—</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Subtasks Summary Section Card -->
      <div class="summary-section-card">
        <div class="loading-overlay" id="loadingOverlay">
          <div class="spinner"></div>
        </div>

        <div class="summary-header-row">
          <div class="summary-title-group">
            <h2>Project Summary</h2>
            <span class="summary-counter-badge" id="subtaskCounterBadge">0 Subtasks</span>
          </div>

          <!-- <div class="summary-filter-wrapper">
            <div class="summary-search-box">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" id="summarySearchInput" class="summary-search-input" placeholder="Search subtasks, agent, status...">
            </div>
          </div> -->
        </div>

        <!-- Subtasks Stats Summary Pills -->
        <div class="subtasks-stats-bar">
          <span class="stat-pill" id="statTotal">Total: 0</span>
          <span class="stat-pill stat-pill-pending" id="statPending">Pending: 0</span>
          <span class="stat-pill stat-pill-progress" id="statInProgress">In Progress: 0</span>
          <span class="stat-pill stat-pill-hold" id="statOnHold">On Hold: 0</span>
          <span class="stat-pill stat-pill-completed" id="statCompleted">Completed: 0</span>
          <span class="stat-pill" id="statTotalTime"><i class="fa-regular fa-clock"></i> Duration: 00:00:00</span>
        </div>

        <!-- Subtasks Summary Table -->
        <div class="summary-table-wrapper">
          <table class="summary-table">
            <thead>
              <tr>
                <th class="col-sl">Sl No</th>
                <th class="col-agent">Agent</th>
                <th class="col-subtask">Subtask Name</th>
                <th class="col-date">Start Date</th>
                <th class="col-date">End Date</th>
                <th class="col-duration">Duration</th>
                <th class="col-status">Status</th>
              </tr>
            </thead>
            <tbody id="subtasksSummaryTableBody">
              <!-- Dynamically populated via jQuery AJAX -->
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- Header & Sidebar Component -->
    <?php include __DIR__ . '/../components/header-sidebar.php'; ?>

    <script src="../plugins/jquery-3.7.1.min.js"></script>
    <script src="../js/project/project-summary.js"></script>
  </body>

</html>
