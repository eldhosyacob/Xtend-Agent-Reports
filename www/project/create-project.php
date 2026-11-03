<?php
require_once __DIR__ . '/../config/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$current_date = date('Y-m-d');

$departments = [];
$db = getDatabaseConnection();
if ($db) {
    $deptStmt = $db->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND TRIM(department) != '' ORDER BY department ASC");
    if ($deptStmt) {
        $departments = $deptStmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
if (empty($departments)) {
    $departments = ['IVR', 'Voice Logger', 'Manager'];
}

// Determine authenticated user's department
$user_department = isset($_SESSION['department']) ? trim($_SESSION['department']) : '';
if (empty($user_department) && !empty($_SESSION['id']) && $db) {
    $deptStmt = $db->prepare("SELECT department FROM users WHERE id = :id LIMIT 1");
    $deptStmt->execute(['id' => $_SESSION['id']]);
    $user_dep = $deptStmt->fetchColumn();
    if ($user_dep) {
        $user_department = trim($user_dep);
        $_SESSION['department'] = $user_dep;
    }
}

// Fetch products based on department (Voice Logger -> products_vl, IVR -> products_ivr)
$products = [];
if ($db) {
    if (strcasecmp($user_department, 'Voice Logger') === 0) {
        $prodStmt = $db->query("SELECT product_name FROM `products_vl` ORDER BY id ASC");
        if ($prodStmt) {
            $products = $prodStmt->fetchAll(PDO::FETCH_COLUMN);
        }
    } elseif (strcasecmp($user_department, 'IVR') === 0) {
        $prodStmt = $db->query("SELECT product_name FROM `products_ivr` ORDER BY id ASC");
        if ($prodStmt) {
            $products = $prodStmt->fetchAll(PDO::FETCH_COLUMN);
        }
    } else {
        // For Managers or other departments, fetch union of both tables
        $prodStmt = $db->query("
            SELECT product_name FROM `products_vl`
            UNION
            SELECT product_name FROM `products_ivr`
            ORDER BY product_name ASC
        ");
        if ($prodStmt) {
            $products = $prodStmt->fetchAll(PDO::FETCH_COLUMN);
        }
    }
}
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
          <!-- <div class="project-header-icon">
            <i class="fa-solid fa-folder-plus"></i>
          </div> -->
          <div class="project-header-text">
            <h1>Create New Project</h1>
            <p>Fill in the details below to initialize a new project</p>
          </div>
        </div>

        <div class="project-card-body">
          <form id="createProjectForm" method="POST" autocomplete="off">
            <!-- Row 1: Date, Project Name, Category & Priority -->
            <div class="project-form-row project-form-row-4">
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

              <!-- Category Field -->
              <div class="project-form-group">
                <label for="project_category">Category <span class="required-star">*</span></label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-layer-group project-input-icon"></i>
                  <select id="project_category" name="project_category" class="project-form-control" required>
                    <option value="" disabled selected>Select Category</option>
                    <option value="Driver">Driver</option>
                    <option value="Hardware">Hardware</option>
                    <option value="Software">Software</option>
                    <option value="Integrated">Integrated</option>
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

            <!-- Row 2: Department, Assigned To, Assigned By & Project Status -->
            <div class="project-form-row project-form-row-4">
              <!-- Department Field -->
              <div class="project-form-group">
                <label for="project_department">Department <span class="required-star">*</span></label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-sitemap project-input-icon"></i>
                  <select id="project_department" name="project_department" class="project-form-control" required>
                    <option value="" disabled selected>Select Department</option>
                    <?php foreach ($departments as $dept): ?>
                      <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <!-- Assigned To Field -->
              <div class="project-form-group">
                <label for="assignedToDisplay">Assigned To <span class="required-star">*</span></label>
                <div class="project-input-wrapper assigned-to-wrapper" id="assignedToWrapper">
                  <i class="fa-solid fa-users project-input-icon"></i>
                  <div class="project-form-control assigned-to-display disabled" id="assignedToDisplay" tabindex="0">
                    <span class="assigned-to-text placeholder" id="assignedToText">Select Department first</span>
                    <i class="fa-solid fa-chevron-down assigned-to-arrow"></i>
                  </div>
                  <input type="hidden" id="project_assignee" name="project_assignee" value="" required>

                  <div class="assigned-to-dropdown" id="assignedToDropdown">
                    <div class="assigned-to-options-list" id="assignedToOptionsList">
                      <!-- Populated dynamically via JS -->
                    </div>
                  </div>
                </div>
              </div>

              <!-- Assigned By Field -->
              <div class="project-form-group">
                <label for="project_assigned_by">Assigned By <span class="required-star">*</span></label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-users-gear project-input-icon"></i>
                  <select id="project_assigned_by" name="project_assigned_by" class="project-form-control" required>
                    <option value="" disabled selected>Select Assigned By</option>
                    <option value="Development">Development</option>
                    <option value="Production">Production</option>
                    <option value="R&D">R&D</option>
                    <option value="Deepak">Deepak</option>
                  </select>
                </div>
              </div>

              <!-- Project Status Field -->
              <div class="project-form-group">
                <label for="project_status">Project Status <span class="required-star">*</span></label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-bars-progress project-input-icon"></i>
                  <select id="project_status" name="project_status" class="project-form-control" required readonly>
                    <option value="Assigned" selected>Assigned</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Row 3: Product Name, Product Sub Category, Client/Dealer Specific & Client Name/Dealer Name -->
            <div class="project-form-row project-form-row-4">
              <!-- Product Name Field -->
              <div class="project-form-group">
                <label for="product_name">Product Name <span class="required-star">*</span></label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-box project-input-icon"></i>
                  <select id="product_name" name="product_name" class="project-form-control" required>
                    <option value="" disabled selected>Select Product Name</option>
                    <?php foreach ($products as $prod): ?>
                      <option value="<?php echo htmlspecialchars($prod); ?>"><?php echo htmlspecialchars($prod); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <!-- Product Sub Category Field -->
              <div class="project-form-group">
                <label for="product_sub_category">Product Sub Category</label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-layer-group project-input-icon"></i>
                  <input type="text" id="product_sub_category" name="product_sub_category" class="project-form-control" placeholder="Enter product sub category">
                </div>
              </div>

              <!-- Client/Dealer Specific Field -->
              <div class="project-form-group">
                <label for="client_dealer_specific">Client/Dealer Specific <span class="required-star">*</span></label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-handshake project-input-icon"></i>
                  <select id="client_dealer_specific" name="client_dealer_specific" class="project-form-control" required>
                    <option value="No" selected>No</option>
                    <option value="Yes">Yes</option>
                  </select>
                </div>
              </div>

              <!-- Client Name/Dealer Name Field -->
              <div class="project-form-group">
                <label for="client_dealer_name">Client Name/Dealer Name <span class="required-star client-dealer-star" id="clientDealerStar">*</span></label>
                <div class="project-input-wrapper autocomplete-wrapper">
                  <i class="fa-regular fa-building project-input-icon"></i>
                  <input type="text" id="client_dealer_name" name="client_dealer_name" class="project-form-control project-client-dealer-input" value="NA" placeholder="NA" autocomplete="off" disabled>
                  <div class="autocomplete-suggestions" id="clientDealerSuggestions"></div>
                </div>
                <div id="addCompanyCheckboxContainer" class="add-client-checkbox-wrapper">
                  <input type="checkbox" id="add_to_company_list" name="add_to_company_list" value="1">
                  <label for="add_to_company_list">Add to Client List</label>
                </div>
              </div>
            </div>

            <!-- Section: Additional Hardware Details -->
            <div class="project-form-section-title">
              <!-- <i class="fa-solid fa-microchip"></i> -->
              <span>Additional Hardware Details</span>
            </div>

            <!-- Row 4: Product Serial No, LCD ID, Hardware ID/Card ID & Software Version -->
            <div class="project-form-row project-form-row-4">
              <!-- Product Serial No Field -->
              <div class="project-form-group">
                <label for="product_serial_no">Product Serial No</label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-barcode project-input-icon"></i>
                  <input type="text" id="product_serial_no" name="product_serial_no" class="project-form-control" placeholder="Enter product serial no">
                </div>
              </div>

              <!-- LCD ID Field -->
              <div class="project-form-group">
                <label for="lcd_id">LCD ID</label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-desktop project-input-icon"></i>
                  <input type="text" id="lcd_id" name="lcd_id" class="project-form-control" placeholder="Enter LCD ID">
                </div>
              </div>

              <!-- Hardware ID/Card ID Field -->
              <div class="project-form-group">
                <label for="hardware_id">Hardware ID/Card ID</label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-microchip project-input-icon"></i>
                  <input type="text" id="hardware_id" name="hardware_id" class="project-form-control" placeholder="Enter Hardware ID / Card ID">
                </div>
              </div>

              <!-- Software Version Field -->
              <div class="project-form-group">
                <label for="software_version">Software Version</label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-code-branch project-input-icon"></i>
                  <input type="text" id="software_version" name="software_version" class="project-form-control" placeholder="Enter software version">
                </div>
              </div>
            </div>

            <!-- Row 5: HASP ID Field -->
            <div class="project-form-row project-form-row-4">
              <div class="project-form-group">
                <label for="hasp_id">HASP ID</label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-key project-input-icon"></i>
                  <input type="text" id="hasp_id" name="hasp_id" class="project-form-control" placeholder="Enter HASP ID">
                </div>
              </div>
            </div>

            <!-- Row 6: Project Description -->
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
    <script src="../js/project/create-project.js"></script>
  </body>

</html>
