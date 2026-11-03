<?php
require_once __DIR__ . '/../config/auth_check.php';

$subtask_id = isset($_GET['subtask_id']) ? htmlspecialchars(trim($_GET['subtask_id'])) : (isset($_GET['id']) ? htmlspecialchars(trim($_GET['id'])) : '');
$project_id = isset($_GET['project_id']) ? htmlspecialchars(trim($_GET['project_id'])) : '';
?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Related Reports - <?php echo $subtask_id ? $subtask_id : 'Subtask'; ?> | Xtend Agent Reports</title>
    <link rel="shortcut icon" href="../images/favicon.png" />
    <link rel="stylesheet" href="../styles/header-sidebar.css">
    <link rel="stylesheet" href="../styles/common.css">
    <link rel="stylesheet" href="../styles/project/project-details.css">
    <link rel="stylesheet" href="../styles/project/my-project-reports.css">
    <link rel="stylesheet" href="../styles/project/view-related-reports.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  </head>

  <body>
    <div class="project-details-container page-containers">

      <!-- Navigation & Breadcrumb Trail -->
      <!-- <div class="page-top-nav">
        <div class="nav-breadcrumbs">
          <a href="<?php echo $project_id ? 'my-project-details.php?id=' . urlencode($project_id) : 'my-projects.php'; ?>" class="back-link-btn" id="backToProjectLink" title="Back to Project Details">
            <i class="fa-solid fa-arrow-left"></i>
          </a>
          <div class="breadcrumb-trail">
            <a href="my-projects.php">My Projects</a>
            <i class="fa-solid fa-chevron-right breadcrumb-separator"></i>
            <a href="<?php echo $project_id ? 'my-project-details.php?id=' . urlencode($project_id) : 'my-projects.php'; ?>" id="breadcrumbProjectLink">
              <?php echo $project_id ? htmlspecialchars($project_id) : 'Project Details'; ?>
            </a>
            <i class="fa-solid fa-chevron-right breadcrumb-separator"></i>
            <span class="breadcrumb-current" id="breadcrumbSubtaskName"><?php echo $subtask_id ? htmlspecialchars($subtask_id) : 'Subtask Reports'; ?></span>
          </div>
        </div>

        <div class="top-actions-group">
          <a href="#" class="btn-primary-action" id="btnTopReportAction">
            <i class="fa-solid fa-file-circle-plus"></i> Create Report
          </a>
        </div>
      </div> -->

      <!-- Subtask Overview Card -->
      <!-- <div class="project-overview-card">
        <div class="overview-card-header">
          <div class="header-title-box">
            <div class="project-avatar-icon">
              <i class="fa-solid fa-list-check"></i>
            </div>
            <div class="header-text-content">
              <h1 id="subtaskTitle">Loading subtask details...</h1>
              <div class="header-meta-badges">
                <span class="header-id-pill" id="subtaskIdPill"><?php echo $subtask_id; ?></span>
                <span class="badge-priority" id="subtaskPriorityBadge">—</span>
                <span class="badge-status" id="subtaskStatusBadge">—</span>
              </div>
            </div>
          </div>

          <div class="header-assignee-box">
            <div class="assignee-chip" id="subtaskAssigneeDisplay" style="background: rgba(255, 255, 255, 0.2); padding: 6px 14px; border-radius: 20px; color: #ffffff;">
              <span class="assignee-avatar">?</span>
              <span id="assigneeName">Assignee</span>
            </div>
          </div>
        </div>

        <div class="overview-card-body">
          <div class="metadata-grid">
            <div class="metadata-item">
              <span class="meta-label"><i class="fa-solid fa-diagram-project"></i> Project</span>
              <span class="meta-value" id="metaProject"><?php echo $project_id ? htmlspecialchars($project_id) : '—'; ?></span>
            </div>
            <div class="metadata-item">
              <span class="meta-label"><i class="fa-solid fa-user-check"></i> Assigned To</span>
              <span class="meta-value" id="metaAssignedTo">—</span>
            </div>
            <div class="metadata-item">
              <span class="meta-label"><i class="fa-regular fa-calendar-days"></i> Due Date</span>
              <span class="meta-value" id="metaDueDate">—</span>
            </div>
            <div class="metadata-item">
              <span class="meta-label"><i class="fa-solid fa-user-plus"></i> Created By</span>
              <span class="meta-value" id="metaCreatedBy">—</span>
            </div>
            <div class="metadata-item">
              <span class="meta-label"><i class="fa-regular fa-clock"></i> Total Time</span>
              <span class="meta-value" id="metaTotalTime">—</span>
            </div>
          </div>

          <div class="description-section" id="subtaskDescSection">
            <span class="desc-title"><i class="fa-solid fa-align-left"></i> Description</span>
            <div class="desc-box" id="subtaskDescBox">No description provided for this subtask.</div>
          </div>
        </div>
      </div> -->

      <!-- Related Reports Section Card -->
      <div class="subtasks-section-card">
        <div class="loading-overlay" id="loadingOverlay">
          <div class="spinner"></div>
        </div>

        <div class="subtasks-header">
          <div class="subtasks-title-group">
            <h2>Related Reports</h2>
            <span class="subtasks-counter-badge" id="reportsCounterBadge">0 Reports</span>
          </div>
        </div>

        <!-- Subtasks Stats Summary Pills -->
        <div class="subtasks-stats-bar">
          <span class="stat-pill" id="statTotal">Total: 0</span>
          <span class="stat-pill stat-pill-pending" id="statPending">Pending: 0</span>
          <span class="stat-pill stat-pill-progress" id="statInProgress">In Progress: 0</span>
          <span class="stat-pill stat-pill-hold" id="statOnHold">On Hold: 0</span>
          <span class="stat-pill stat-pill-completed" id="statCompleted">Completed: 0</span>
          <span class="stat-pill" id="statTotalTime"><i class="fa-regular fa-clock"></i> Time: 00:00:00</span>
        </div>

        <!-- Reports Table -->
        <div class="subtasks-table-wrapper">
          <table class="subtasks-table reports-table">
            <thead>
              <tr>
                <th style="width: 70px;">Sl.No.</th>
                <th style="width: 130px;">Date</th>
                <th style="width: 160px;">Agent</th>
                <!-- <th class="text-left">Project Name</th> -->
                <th style="width: 130px;">Total Time</th>
                <th style="width: 130px;">Status</th>
                <th style="width: 110px; text-align: center;">Action</th>
              </tr>
            </thead>
            <tbody id="reportsTableBody">
              <!-- Dynamically populated via jQuery AJAX -->
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- Header & Sidebar Component -->
    <?php include __DIR__ . '/../components/header-sidebar.php'; ?>

    <script src="../plugins/jquery-3.7.1.min.js"></script>
    <script src="../js/project/view-related-reports.js?v=<?php echo file_exists(__DIR__ . '/../js/project/view-related-reports.js') ? filemtime(__DIR__ . '/../js/project/view-related-reports.js') : time(); ?>"></script>
  </body>

</html>
