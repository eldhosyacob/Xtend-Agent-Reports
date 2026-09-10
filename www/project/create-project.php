<?php
require_once __DIR__ . '/../config/auth_check.php';

$current_date = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Create Project</title>
    <link rel="shortcut icon" href="../images/favicon.png">
    <link rel="stylesheet" href="../styles/header-sidebar.css">
    <link rel="stylesheet" href="../styles/common.css">
    <link rel="stylesheet" href="../styles/project/create-project.css">
  </head>

  <body>
    <div class="create-project-page-container page-containers">
      <div class="project-card">
        <div class="project-card-header">
          <div class="project-header-icon">
            <i class="fa-solid fa-folder-plus"></i>
          </div>
          <div class="project-header-text">
            <h1>Create New Project</h1>
            <p>Fill in the details below to initialize a new project</p>
          </div>
        </div>

        <div class="project-card-body">
          <form id="createProjectForm" method="POST" autocomplete="off">
            <!-- Row 1: Date & Project Name -->
            <div class="project-form-row project-form-row-2">
              <!-- Date Field -->
              <div class="project-form-group">
                <label for="project_date">Date <span class="required-star">*</span></label>
                <div class="project-input-wrapper">
                  <i class="fa-regular fa-calendar-days project-input-icon"></i>
                  <input type="date" id="project_date" name="project_date" class="project-form-control" value="<?php echo htmlspecialchars($current_date); ?>" required>
                </div>
              </div>

              <!-- Project Name Field -->
              <div class="project-form-group">
                <label for="project_name">Project Name <span class="required-star">*</span></label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-diagram-project project-input-icon"></i>
                  <input type="text" id="project_name" name="project_name" class="project-form-control" placeholder="Enter project name" required>
                </div>
              </div>
            </div>

            <!-- Row 2: Category, Assignee & Priority -->
            <div class="project-form-row project-form-row-3">
              <!-- Category Field -->
              <div class="project-form-group">
                <label for="project_category">Category <span class="required-star">*</span></label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-layer-group project-input-icon"></i>
                  <select id="project_category" name="project_category" class="project-form-control" required>
                    <option value="" disabled selected>Select Category</option>
                    <option value="Hardware">Hardware</option>
                    <option value="Software">Software</option>
                    <option value="Integrated">Integrated</option>
                  </select>
                </div>
              </div>

              <!-- Assignee Field -->
              <div class="project-form-group">
                <label for="project_assignee">Assignee <span class="required-star">*</span></label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-user-check project-input-icon"></i>
                  <select id="project_assignee" name="project_assignee" class="project-form-control" required>
                    <option value="" disabled selected>Select Assignee</option>
                    <option value="All team">All team</option>
                    <option value="Sachin">Sachin</option>
                    <option value="Abin">Abin</option>
                    <option value="Jibin">Jibin</option>
                    <option value="Reni">Reni</option>
                    <option value="Aiswarya">Aiswarya</option>
                    <option value="Reshma">Reshma</option>
                  </select>
                </div>
              </div>

              <!-- Priority Field -->
              <div class="project-form-group">
                <label for="project_priority">Priority <span class="required-star">*</span></label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-flag project-input-icon"></i>
                  <select id="project_priority" name="project_priority" class="project-form-control" required>
                    <option value="" disabled selected>Select Priority</option>
                    <option value="Low">Low</option>
                    <option value="Medium">Medium</option>
                    <option value="High">High</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Row 3: Project Description -->
            <div class="project-form-row">
              <div class="project-form-group">
                <label for="project_description">Project Description</label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-align-left project-input-icon project-textarea-icon"></i>
                  <textarea id="project_description" name="project_description" class="project-form-control project-textarea" rows="4" placeholder="Enter project description..."></textarea>
                </div>
              </div>
            </div>

            <!-- Form Actions -->
            <div class="project-form-actions">
              <button type="reset" class="btn btn-secondary">
                <i class="fa-solid fa-rotate-left"></i> Reset
              </button>
              <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> Create Project
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <?php include '../components/header-sidebar.php'; ?>

    <script src="../plugins/jquery-3.7.1.min.js"></script>
  </body>

</html>