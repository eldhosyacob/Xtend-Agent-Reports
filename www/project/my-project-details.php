<?php
require_once __DIR__ . '/../config/auth_check.php';

$project_id = isset($_GET['project_id']) ? htmlspecialchars(trim($_GET['project_id'])) : (isset($_GET['id']) ? htmlspecialchars(trim($_GET['id'])) : '');
?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>My Project Details | Xtend Agent Reports</title>
    <link rel="shortcut icon" href="../images/favicon.png">
    <link rel="stylesheet" href="../styles/header-sidebar.css">
    <link rel="stylesheet" href="../styles/common.css">
    <link rel="stylesheet" href="../styles/project/project-details.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  </head>

  <body>
    <div class="project-details-container page-containers">

      <!-- Navigation & Top Actions Bar -->
      <!-- <div class="page-top-nav">
        <div class="nav-breadcrumbs">
          <a href="my-projects.php" class="back-link-btn" title="Back to My Projects">
            <i class="fa-solid fa-arrow-left"></i>
          </a>
          <div class="breadcrumb-trail">
            <a href="my-projects.php">My Projects</a>
            <i class="fa-solid fa-chevron-right breadcrumb-separator"></i>
            <span class="breadcrumb-current" id="breadcrumbProjectName">Project Details</span>
          </div>
        </div>
      </div> -->

      <!-- Project Overview Card -->
      <div class="project-overview-card">
        <div class="overview-card-header">
          <div class="header-title-box">
            <!-- <div class="project-avatar-icon">
              <i class="fa-solid fa-diagram-project"></i>
            </div> -->
            <div class="header-text-content">
              <h1 id="projectTitle">Loading project...</h1>
              <div class="header-meta-badges">
                <span class="header-id-pill" id="projectIdBadge"><?php echo $project_id; ?></span>
                <span class="badge-category" id="projectCategoryBadge">...</span>
                <span class="badge-priority" id="projectPriorityBadge">...</span>
                <!-- <span class="badge-status" id="projectStatusBadge">...</span> -->
              </div>
            </div>
          </div>

          <!-- <div class="header-assignee-box">
            <div class="assignee-chip" id="projectAssigneeDisplay" style="background: rgba(255, 255, 255, 0.2); padding: 6px 14px; border-radius: 20px; color: #ffffff;">
              <span class="assignee-avatar">?</span>
              <span>Assignee</span>
            </div>
          </div> -->
        </div>

        <div class="overview-card-body">
          <div class="metadata-grid">
            <div class="metadata-item">
              <span class="meta-label"><i class="fa-regular fa-calendar-days"></i> Project Date</span>
              <span class="meta-value" id="metaProjectDate">—</span>
            </div>
            <!-- <div class="metadata-item">
              <span class="meta-label"><i class="fa-solid fa-users-gear"></i> Assigned By</span>
              <span class="meta-value" id="metaAssignedBy">—</span>
            </div> -->
            <div class="metadata-item">
              <span class="meta-label"><i class="fa-solid fa-bars-progress"></i> Project Status</span>
              <span class="meta-value" id="metaProjectStatus">—</span>
            </div>
            <div class="metadata-item">
              <span class="meta-label"><i class="fa-solid fa-user"></i> Created By</span>
              <span class="meta-value" id="metaCreatedBy">—</span>
            </div>
            <div class="metadata-item">
              <span class="meta-label"><i class="fa-regular fa-clock"></i> Created At</span>
              <span class="meta-value" id="metaCreatedAt">—</span>
            </div>
            <!-- <div class="metadata-item">
              <span class="meta-label"><i class="fa-solid fa-handshake"></i> Client/Dealer Specific</span>
              <span class="meta-value" id="metaClientDealerSpecific">—</span>
            </div> -->
            <div class="metadata-item">
              <span class="meta-label"><i class="fa-regular fa-building"></i> Client / Dealer Name</span>
              <span class="meta-value" id="metaClientDealerName">—</span>
            </div>
            <div class="metadata-item">
              <span class="meta-label"><i class="fa-regular fa-clock"></i> Total Time</span>
              <span class="meta-value" id="metaTotalTime">—</span>
            </div>
            <!-- <div class="metadata-item" id="metaEditedGroup" style="display: none;">
              <span class="meta-label"><i class="fa-solid fa-pen-to-square"></i> Last Edited</span>
              <span class="meta-value" id="metaEditedBy">—</span>
            </div> -->

          </div>

          <div class="description-section">
            <span class="desc-title"><i class="fa-solid fa-align-left"></i> Description</span>
            <div class="desc-box" id="projectDescBox">Loading description...</div>
          </div>

          <div class="project-documents-section" id="projectDocumentsSection">
            <span class="desc-title"><i class="fa-solid fa-folder-open"></i> Project Related Documents</span>
            <div class="attachments-list" id="projectDocumentsList"></div>
          </div>
        </div>
      </div>

      <!-- Subprojects Section Card -->
      <div class="subprojects-section-card">
        <div class="loading-overlay" id="loadingOverlay">
          <div class="spinner"></div>
        </div>

        <div class="subprojects-header">
          <div class="subprojects-title-group">
            <h2>Sub Projects</h2>
            <span class="subprojects-counter-badge" id="subprojectCounterBadge">0 Sub Projects</span>
          </div>
        </div>

        <!-- Subprojects Stats Summary Pills -->
        <div class="subprojects-stats-bar">
          <span class="stat-pill" id="statSubTotal">Total: 0</span>
          <span class="stat-pill stat-pill-assigned" id="statSubAssigned">Assigned: 0</span>
          <span class="stat-pill stat-pill-pending" id="statSubPending">Pending: 0</span>
          <span class="stat-pill stat-pill-progress" id="statSubInProgress">In Progress: 0</span>
          <span class="stat-pill stat-pill-completed" id="statSubCompleted">Completed: 0</span>
          <span class="stat-pill stat-pill-hold" id="statSubOnHold">On Hold: 0</span>
        </div>

        <!-- Subprojects Table -->
        <div class="subprojects-table-wrapper">
          <table class="subprojects-table">
            <thead>
              <tr>
                <th style="width: 150px;">Sub Project ID</th>
                <th>Sub Project Name</th>
                <th style="width: 140px;">Department</th>
                <th style="width: 180px;">Assigned To</th>
                <th style="width: 110px;">Priority</th>
                <th style="width: 120px;">Total Time</th>
                <th style="width: 130px;">Status</th>
              </tr>
            </thead>
            <tbody id="subprojectsTableBody">
              <!-- Dynamically populated via jQuery AJAX -->
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- Header & Sidebar Navigation Component -->
    <?php include '../components/header-sidebar.php'; ?>

    <script src="../plugins/jquery-3.7.1.min.js"></script>
    <script src="../js/project/my-project-details.js?v=<?php echo file_exists(__DIR__ . '/../js/project/my-project-details.js') ? filemtime(__DIR__ . '/../js/project/my-project-details.js') : time(); ?>"></script>
  </body>

</html>
