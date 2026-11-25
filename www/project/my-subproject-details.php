<?php
require_once __DIR__ . '/../config/auth_check.php';

$project_id = isset($_GET['project_id']) ? htmlspecialchars(trim($_GET['project_id'])) : (isset($_GET['sub_project_id']) ? htmlspecialchars(trim($_GET['sub_project_id'])) : (isset($_GET['id']) ? htmlspecialchars(trim($_GET['id'])) : ''));
?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>My Sub Project Details | Xtend Agent Reports</title>
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
          <a href="my-projects.php" class="back-link-btn" id="btnBackToProject" title="Back to Project">
            <i class="fa-solid fa-arrow-left"></i>
          </a>
          <div class="breadcrumb-trail">
            <a href="my-projects.php">My Projects</a>
            <i class="fa-solid fa-chevron-right breadcrumb-separator"></i>
            <a href="#" id="breadcrumbParentProjectLink">Parent Project</a>
            <i class="fa-solid fa-chevron-right breadcrumb-separator"></i>
            <span class="breadcrumb-current" id="breadcrumbSubprojectName">Sub Project Details</span>
          </div>
        </div>
      </div> -->

      <!-- Subproject Overview Card -->
      <div class="project-overview-card">
        <div class="overview-card-header">
          <div class="header-title-box">
            <div class="header-text-content">
              <h1 id="projectTitle">Loading sub project...</h1>
              <div class="header-meta-badges">
                <span class="header-id-pill" id="projectIdBadge"><?php echo $project_id; ?></span>
                <span class="badge-category" id="projectCategoryBadge">...</span>
                <span class="badge-priority" id="projectPriorityBadge">...</span>
              </div>
            </div>
          </div>
        </div>

        <div class="overview-card-body">
          <div class="metadata-grid">
            <div class="metadata-item">
              <span class="meta-label"><i class="fa-regular fa-calendar-days"></i> Project Date</span>
              <span class="meta-value" id="metaProjectDate">—</span>
            </div>
            <div class="metadata-item">
              <span class="meta-label"><i class="fa-solid fa-diagram-project"></i> Parent Project</span>
              <span class="meta-value" id="metaParentProject">—</span>
            </div>
            <div class="metadata-item">
              <span class="meta-label"><i class="fa-solid fa-users-gear"></i> Assigned By</span>
              <span class="meta-value" id="metaAssignedBy">—</span>
            </div>
            <div class="metadata-item">
              <span class="meta-label"><i class="fa-solid fa-sitemap"></i> Department</span>
              <span class="meta-value" id="metaDepartment">—</span>
            </div>
            <div class="metadata-item">
              <span class="meta-label"><i class="fa-solid fa-bars-progress"></i> Status</span>
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
            <div class="metadata-item">
              <span class="meta-label"><i class="fa-regular fa-building"></i> Client / Dealer Name</span>
              <span class="meta-value" id="metaClientDealerName">—</span>
            </div>
            <div class="metadata-item">
              <span class="meta-label"><i class="fa-regular fa-clock"></i> Total Time</span>
              <span class="meta-value" id="metaTotalTime">—</span>
            </div>
          </div>

          <div class="description-section">
            <span class="desc-title"><i class="fa-solid fa-align-left"></i> Description</span>
            <div class="desc-box" id="projectDescBox">Loading description...</div>
          </div>

          <div class="project-attachments-section" id="projectAttachmentsSection">
            <span class="desc-title"><i class="fa-solid fa-paperclip"></i> Test Related Attachments</span>
            <div class="attachments-list" id="projectAttachmentsList"></div>
          </div>
        </div>
      </div>

      <!-- Task Section Card -->
      <div class="subtasks-section-card">
        <div class="loading-overlay" id="loadingOverlay">
          <div class="spinner"></div>
        </div>

        <div class="subtasks-header">
          <div class="subtasks-title-group">
            <h2>Tasks</h2>
            <span class="subtasks-counter-badge" id="subtaskCounterBadge">0 Tasks</span>
          </div>

          <button type="button" class="btn-primary-action" id="btnOpenSubtaskModal">
            <i class="fa-solid fa-plus"></i> Add Task
          </button>
        </div>

        <!-- Subtasks Stats Summary Pills -->
        <div class="subtasks-stats-bar">
          <span class="stat-pill" id="statTotal">Total: 0</span>
          <span class="stat-pill stat-pill-pending" id="statPending">Pending: 0</span>
          <span class="stat-pill stat-pill-progress" id="statInProgress">In Progress: 0</span>
          <span class="stat-pill stat-pill-completed" id="statCompleted">Completed: 0</span>
          <span class="stat-pill stat-pill-hold" id="statOnHold">On Hold: 0</span>
        </div>

        <!-- Subtasks Table -->
        <div class="subtasks-table-wrapper">
          <table class="subtasks-table">
            <thead>
              <tr>
                <th style="width: 150px;">ID</th>
                <th>Task</th>
                <th style="width: 160px;">Assigned To</th>
                <th style="width: 130px;">Total Time</th>
                <th style="width: 130px;">Status</th>
                <th style="width: 110px; text-align: center;">Action</th>
              </tr>
            </thead>
            <tbody id="subtasksTableBody">
              <!-- Dynamically populated via jQuery AJAX -->
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- Add Subtask Modal Dialog -->
    <div class="subtask-modal-overlay" id="subtaskModal" style="display: none;">
      <div class="subtask-modal-dialog">
        <div class="modal-header-section">
          <h3><i class="fa-solid fa-list-check"></i> Add New Task</h3>
          <button type="button" class="modal-close-btn" id="btnCloseSubtaskModal" title="Close">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>

        <form id="addSubtaskForm" autocomplete="off">
          <div class="modal-body-section">
            <div class="modal-form-group">
              <label for="subtask_title">Task Title <span class="required-star">*</span></label>
              <input type="text" id="subtask_title" name="subtask_title" class="modal-form-control" placeholder="e.g. Implement user authentication module" required>
            </div>

            <div class="modal-form-group">
              <label for="subtask_description">Description / Notes</label>
              <textarea id="subtask_description" name="subtask_description" class="modal-form-control" rows="3" placeholder="Optional details or specifications for this task..."></textarea>
            </div>

            <div class="modal-actions">
              <button type="button" class="btn-modal-cancel" id="btnCancelSubtaskModal">Cancel</button>
              <button type="submit" class="btn-modal-submit" id="btnSubmitSubtask">
                <i class="fa-solid fa-check"></i> Add Task
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- View Subtask Modal Dialog -->
    <div class="subtask-modal-overlay" id="viewSubtaskModal" style="display: none;">
      <div class="subtask-modal-dialog">
        <div class="modal-header-section">
          <div class="view-subtask-modal-header">
            <i class="fa-solid fa-list-check"></i>
            <h3>Task Details</h3>
            <span class="subtask-id-pill" id="viewSubtaskIdPill"></span>
          </div>
          <button type="button" class="modal-close-btn" id="btnCloseViewSubtaskModal" title="Close">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>

        <div class="modal-body-section">
          <h2 class="view-subtask-title" id="viewSubtaskTitle"></h2>

          <div class="view-subtask-meta-grid">
            <div class="view-subtask-meta-item">
              <span class="view-subtask-meta-label"><i class="fa-solid fa-bars-progress"></i> Status</span>
              <div class="view-subtask-meta-value" id="viewSubtaskStatus"></div>
            </div>
            <div class="view-subtask-meta-item">
              <span class="view-subtask-meta-label"><i class="fa-solid fa-flag"></i> Priority</span>
              <div class="view-subtask-meta-value" id="viewSubtaskPriority"></div>
            </div>
            <div class="view-subtask-meta-item">
              <span class="view-subtask-meta-label"><i class="fa-regular fa-calendar-days"></i> Due Date</span>
              <div class="view-subtask-meta-value" id="viewSubtaskDueDate">—</div>
            </div>
            <div class="view-subtask-meta-item">
              <span class="view-subtask-meta-label"><i class="fa-regular fa-clock"></i> Total Time</span>
              <div class="view-subtask-meta-value" id="viewSubtaskTotalTime">—</div>
            </div>
            <div class="view-subtask-meta-item">
              <span class="view-subtask-meta-label"><i class="fa-solid fa-user-check"></i> Assigned To</span>
              <div class="view-subtask-meta-value" id="viewSubtaskAssignee">—</div>
            </div>
            <div class="view-subtask-meta-item">
              <span class="view-subtask-meta-label"><i class="fa-solid fa-user-plus"></i> Created By</span>
              <div class="view-subtask-meta-value" id="viewSubtaskCreatedBy">—</div>
            </div>
            <div class="view-subtask-meta-item">
              <span class="view-subtask-meta-label"><i class="fa-solid fa-diagram-project"></i> Sub Project</span>
              <div class="view-subtask-meta-value" id="viewSubtaskProject">—</div>
            </div>
          </div>

          <div class="view-subtask-desc-section">
            <span class="view-subtask-meta-label"><i class="fa-solid fa-align-left"></i> Description / Specifications</span>
            <div class="view-subtask-desc-box" id="viewSubtaskDesc"></div>
          </div>

          <div class="modal-actions">
            <button type="button" class="btn-modal-cancel" id="btnCancelViewSubtaskModal">Close</button>
            <button type="button" class="btn-primary-action" id="btnModalCreateReport">
              <i class="fa-solid fa-file-circle-plus"></i> Create Report
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Header & Sidebar Navigation Component -->
    <?php include '../components/header-sidebar.php'; ?>

    <script src="../plugins/jquery-3.7.1.min.js"></script>
    <script src="../js/project/my-subproject-details.js?v=<?php echo file_exists(__DIR__ . '/../js/project/my-subproject-details.js') ? filemtime(__DIR__ . '/../js/project/my-subproject-details.js') : time(); ?>"></script>
  </body>

</html>

