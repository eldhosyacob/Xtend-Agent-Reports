<?php
require_once __DIR__ . '/../config/auth_check.php';

$user_role = isset($_SESSION['role']) ? trim($_SESSION['role']) : '';
$is_admin = (strtolower($user_role) === 'admin' || strtolower(trim($_SESSION['username'] ?? '')) === 'admin');
?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Project Lists | Xtend Agent Reports</title>
    <link rel="shortcut icon" href="../images/favicon.png">
    <link rel="stylesheet" href="../styles/header-sidebar.css">
    <link rel="stylesheet" href="../styles/common.css">
    <link rel="stylesheet" href="../styles/project/project-lists.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  </head>

  <body>
    <div class="project-lists-container page-containers" data-is-admin="<?php echo $is_admin ? '1' : '0'; ?>">

      <!-- Page Header Card -->
      <div class="page-header-card">
        <div class="page-header-title">
          <!-- <div class="header-icon-box">
            <i class="fa-solid fa-diagram-project"></i>
          </div> -->
          <div class="page-title-text">
            <h1>Project Lists</h1>
            <!-- <p>View, search, and track all projects created across teams</p> -->
          </div>
        </div>
        <!-- <div class="header-actions">
          <a href="create-project.php" class="btn-create-project">
            <i class="fa-solid fa-plus"></i> Create New Project
          </a>
        </div> -->
      </div>

      <!-- Filter Bar Card -->
      <div class="filter-bar-card">
        <div class="filter-left-group">
          <!-- Search Input -->
          <div class="search-wrapper">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="search-input" class="search-input" placeholder="Search by Project Name, ID..." autocomplete="off">
          </div>

          <!-- Category Filter -->
          <select id="category-filter" class="select-filter-control" aria-label="Filter by Category">
            <option value="">All Categories</option>
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

          <!-- Reset Filter Button -->
          <button type="button" id="btnResetFilters" class="btn-reset-filter" title="Reset all filters">
            <i class="fa-solid fa-rotate-left"></i> Reset
          </button>
        </div>

        <div class="filter-right-group">
          <span class="project-count-badge" id="projectCount">Loading...</span>
        </div>
      </div>

      <!-- Projects Table Card -->
      <div class="details-table-card">
        <div class="loading-overlay" id="loadingOverlay">
          <div class="spinner"></div>
        </div>

        <div class="table-responsive">
          <table class="projects-table">
            <thead>
              <tr>
                <th>Project ID</th>
                <th>Date</th>
                <th>Project Name</th>
                <th>Category</th>
                <th>Priority</th>
                <th>Created By</th>
                <th>Created At</th>
                <th>Status</th>
                <?php if ($is_admin): ?>
                <th class="col-action"></th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody id="projectsTableBody">
              <!-- Dynamically populated via jQuery AJAX -->
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- Header & Sidebar Navigation Component -->
    <?php include '../components/header-sidebar.php'; ?>

    <script src="../plugins/jquery-3.7.1.min.js"></script>
    <script src="../js/project/project-lists.js"></script>
  </body>

</html>
