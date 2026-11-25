<?php
require_once __DIR__ . '/../config/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$current_date = date('Y-m-d');
$db = getDatabaseConnection();

// Check authorization: Admin or User with project_creator permission
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$user_project_creator = isset($_SESSION['project_creator']) ? (int)$_SESSION['project_creator'] : 0;
if ($user_role !== 'admin' && $user_project_creator !== 1) {
    if (!empty($_SESSION['id']) && $db) {
        $checkStmt = $db->prepare("SELECT project_creator FROM users WHERE id = :id LIMIT 1");
        $checkStmt->execute(['id' => $_SESSION['id']]);
        $user_project_creator = (int)$checkStmt->fetchColumn();
        $_SESSION['project_creator'] = $user_project_creator;
    }
}
if ($user_role !== 'admin' && $user_project_creator !== 1) {
    header('Location: project-dashboard.php');
    exit;
}

// Fetch parent project passed in URL
$target_project_id = isset($_GET['project_id']) ? trim($_GET['project_id']) : '';
$selected_parent = null;
if (!empty($target_project_id) && $db) {
    $pStmt = $db->prepare("
        SELECT id, project_id, project_name, category, priority, product_name, product_sub_category, client_dealer_specific, client_dealer_name, product_serial_no, lcd_id, hardware_id, software_version, hasp_id 
        FROM `projects` 
        WHERE project_id = :project_id OR id = :numeric_id
        LIMIT 1
    ");
    $pStmt->execute([
        'project_id' => $target_project_id,
        'numeric_id' => is_numeric($target_project_id) ? (int)$target_project_id : 0
    ]);
    $selected_parent = $pStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Fetch distinct departments
$departments = [];
if ($db) {
    $deptStmt = $db->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND TRIM(department) != '' ORDER BY department ASC");
    if ($deptStmt) {
        $departments = $deptStmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
if (empty($departments)) {
    $departments = ['IVR', 'Voice Logger', 'Manager'];
}

// Fetch products
$products = [];
if ($db) {
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
?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Create Sub Project | Xtend Agent Reports</title>
    <link rel="shortcut icon" href="../images/favicon.png">
    <link rel="stylesheet" href="../styles/header-sidebar.css">
    <link rel="stylesheet" href="../styles/common.css">
    <link rel="stylesheet" href="../styles/project/create-project.css">
  </head>

  <body>
    <div class="create-project-page-container page-containers">
      <div class="project-card">
        <div class="project-card-header">
          <div class="project-header-text">
            <h1>Create New Sub Project</h1>
            <p>Assign subproject details, department, and team members</p>
          </div>
        </div>

        <div class="project-card-body">
          <form id="createSubProjectForm" method="POST" enctype="multipart/form-data" autocomplete="off">
            <!-- Row 1: Parent Project, Sub Project Name, Date & Priority -->
            <div class="project-form-row project-form-row-4">
              <!-- Parent Project Field -->
              <div class="project-form-group">
                <label for="parent_project_display">Parent Project <span class="required-star">*</span></label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-diagram-project project-input-icon"></i>
                  <input type="text" id="parent_project_display" class="project-form-control" value="<?php echo $selected_parent ? htmlspecialchars($selected_parent['project_id'] . ' - ' . $selected_parent['project_name']) : ''; ?>" placeholder="No parent project selected" readonly disabled>
                  <input type="hidden" id="parent_project_id" name="project_id" value="<?php echo htmlspecialchars($selected_parent['project_id'] ?? ''); ?>" required>
                </div>
              </div>

              <!-- Sub Project Name Field -->
              <div class="project-form-group">
                <label for="sub_project_name">Sub Project Name <span class="required-star">*</span></label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-folder-tree project-input-icon"></i>
                  <input type="text" id="sub_project_name" name="sub_project_name" class="project-form-control" placeholder="Enter sub project name" required autofocus>
                </div>
              </div>

              <!-- Date Field -->
              <div class="project-form-group">
                <label for="project_date">Date <span class="required-star">*</span></label>
                <div class="project-input-wrapper">
                  <i class="fa-regular fa-calendar-days project-input-icon"></i>
                  <input type="date" id="project_date" name="project_date" class="project-form-control" value="<?php echo htmlspecialchars($current_date); ?>" required readonly>
                </div>
              </div>

              <!-- Priority Field -->
              <div class="project-form-group">
                <label for="project_priority">Priority <span class="required-star">*</span></label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-flag project-input-icon"></i>
                  <select id="project_priority" name="project_priority" class="project-form-control" required>
                    <option value="" disabled <?php echo empty($selected_parent['priority']) ? 'selected' : ''; ?>>Select Priority</option>
                    <option value="Low" <?php echo ($selected_parent && $selected_parent['priority'] === 'Low') ? 'selected' : ''; ?>>Low</option>
                    <option value="Medium" <?php echo ($selected_parent && $selected_parent['priority'] === 'Medium') ? 'selected' : (!isset($selected_parent['priority']) ? 'selected' : ''); ?>>Medium</option>
                    <option value="High" <?php echo ($selected_parent && $selected_parent['priority'] === 'High') ? 'selected' : ''; ?>>High</option>
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
                    <option value="Support">Support</option>
                    <option value="Dispatch">Dispatch</option>
                    <option value="Deepak">Deepak</option>
                  </select>
                </div>
              </div>

              <!-- Project Status Field -->
              <div class="project-form-group">
                <label for="project_status">Project Status <span class="required-star">*</span></label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-bars-progress project-input-icon"></i>
                  <select id="project_status" name="project_status" class="project-form-control" required>
                    <option value="Assigned" selected>Assigned</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Pending">Pending</option>
                    <option value="On Hold">On Hold</option>
                    <option value="Completed">Completed</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Row 3: Category, Product Name, Product Sub Category & Client/Dealer Specific -->
            <div class="project-form-row project-form-row-4">
              <!-- Category Field -->
              <div class="project-form-group">
                <label for="project_category">Category <span class="required-star">*</span></label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-layer-group project-input-icon"></i>
                  <select id="project_category" name="project_category" class="project-form-control" required>
                    <option value="" disabled <?php echo empty($selected_parent['category']) ? 'selected' : ''; ?>>Select Category</option>
                    <option value="Driver" <?php echo ($selected_parent && $selected_parent['category'] === 'Driver') ? 'selected' : ''; ?>>Driver</option>
                    <option value="Hardware" <?php echo ($selected_parent && $selected_parent['category'] === 'Hardware') ? 'selected' : ''; ?>>Hardware</option>
                    <option value="Software" <?php echo ($selected_parent && $selected_parent['category'] === 'Software') ? 'selected' : ''; ?>>Software</option>
                    <option value="Integrated" <?php echo ($selected_parent && $selected_parent['category'] === 'Integrated') ? 'selected' : ''; ?>>Integrated</option>
                  </select>
                </div>
              </div>

              <!-- Product Name Field -->
              <div class="project-form-group">
                <label for="product_name">Product Name <span class="required-star">*</span></label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-box project-input-icon"></i>
                  <select id="product_name" name="product_name" class="project-form-control" required readonly>
                    <option value="" disabled <?php echo empty($selected_parent['product_name']) ? 'selected' : ''; ?>>Select Product Name</option>
                    <?php foreach ($products as $prod): ?>
                      <?php $isProdSelected = ($selected_parent && strcasecmp($selected_parent['product_name'] ?? '', $prod) === 0); ?>
                      <option value="<?php echo htmlspecialchars($prod); ?>" <?php echo $isProdSelected ? 'selected' : ''; ?>><?php echo htmlspecialchars($prod); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <!-- Product Sub Category Field -->
              <div class="project-form-group">
                <label for="product_sub_category">Product Sub Category</label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-layer-group project-input-icon"></i>
                  <input type="text" id="product_sub_category" name="product_sub_category" class="project-form-control" value="<?php echo htmlspecialchars($selected_parent['product_sub_category'] ?? ''); ?>" placeholder="Enter product sub category">
                </div>
              </div>

              <!-- Client/Dealer Specific Field -->
              <div class="project-form-group">
                <label for="client_dealer_specific">Client/Dealer Specific <span class="required-star">*</span></label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-handshake project-input-icon"></i>
                  <select id="client_dealer_specific" name="client_dealer_specific" class="project-form-control" required>
                    <option value="No" <?php echo (!$selected_parent || $selected_parent['client_dealer_specific'] !== 'Yes') ? 'selected' : ''; ?>>No</option>
                    <option value="Yes" <?php echo ($selected_parent && $selected_parent['client_dealer_specific'] === 'Yes') ? 'selected' : ''; ?>>Yes</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Row 4: Client Name/Dealer Name -->
            <div class="project-form-row project-form-row-4">
              <div class="project-form-group">
                <label for="client_dealer_name">Client Name/Dealer Name <span class="required-star client-dealer-star" id="clientDealerStar">*</span></label>
                <div class="project-input-wrapper autocomplete-wrapper">
                  <i class="fa-regular fa-building project-input-icon"></i>
                  <input type="text" id="client_dealer_name" name="client_dealer_name" class="project-form-control project-client-dealer-input" value="<?php echo htmlspecialchars($selected_parent['client_dealer_name'] ?? 'NA'); ?>" placeholder="NA" autocomplete="off" <?php echo (!$selected_parent || $selected_parent['client_dealer_specific'] !== 'Yes') ? 'disabled' : ''; ?>>
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
              <span>Additional Hardware Details</span>
            </div>

            <!-- Row 5: Product Serial No, LCD ID, Hardware ID/Card ID & Software Version -->
            <div class="project-form-row project-form-row-4">
              <!-- Product Serial No Field -->
              <div class="project-form-group">
                <label for="product_serial_no">Product Serial No</label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-barcode project-input-icon"></i>
                  <input type="text" id="product_serial_no" name="product_serial_no" class="project-form-control" value="<?php echo htmlspecialchars($selected_parent['product_serial_no'] ?? ''); ?>" placeholder="Enter product serial no">
                </div>
              </div>

              <!-- LCD ID Field -->
              <div class="project-form-group">
                <label for="lcd_id">LCD ID</label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-desktop project-input-icon"></i>
                  <input type="text" id="lcd_id" name="lcd_id" class="project-form-control" value="<?php echo htmlspecialchars($selected_parent['lcd_id'] ?? ''); ?>" placeholder="Enter LCD ID">
                </div>
              </div>

              <!-- Hardware ID/Card ID Field -->
              <div class="project-form-group">
                <label for="hardware_id">Hardware ID/Card ID</label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-microchip project-input-icon"></i>
                  <input type="text" id="hardware_id" name="hardware_id" class="project-form-control" value="<?php echo htmlspecialchars($selected_parent['hardware_id'] ?? ''); ?>" placeholder="Enter Hardware ID / Card ID">
                </div>
              </div>

              <!-- Software Version Field -->
              <div class="project-form-group">
                <label for="software_version">Software Version</label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-code-branch project-input-icon"></i>
                  <input type="text" id="software_version" name="software_version" class="project-form-control" value="<?php echo htmlspecialchars($selected_parent['software_version'] ?? ''); ?>" placeholder="Enter software version">
                </div>
              </div>
            </div>

            <!-- Row 6: HASP ID Field -->
            <div class="project-form-row project-form-row-4">
              <div class="project-form-group">
                <label for="hasp_id">HASP ID</label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-key project-input-icon"></i>
                  <input type="text" id="hasp_id" name="hasp_id" class="project-form-control" value="<?php echo htmlspecialchars($selected_parent['hasp_id'] ?? ''); ?>" placeholder="Enter HASP ID">
                </div>
              </div>
            </div>

            <!-- Row 7: Sub Project Description -->
            <div class="project-form-row">
              <div class="project-form-group">
                <label for="project_description">Sub Project Description</label>
                <div class="project-input-wrapper">
                  <i class="fa-solid fa-align-left project-input-icon project-textarea-icon"></i>
                  <textarea id="project_description" name="project_description" class="project-form-control project-textarea" rows="4" placeholder="Enter sub project description..."></textarea>
                </div>
              </div>
            </div>

            <!-- Form Actions -->
            <div class="project-form-actions">
              <a href="project-lists.php" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left"></i> Cancel
              </a>
              <button type="submit" class="btn btn-primary" id="btnSubmitSubProject">
                <i class="fa-solid fa-plus"></i> Create Sub Project
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <?php include '../components/header-sidebar.php'; ?>

    <script src="../plugins/jquery-3.7.1.min.js"></script>
    <script src="../js/project/create-sub-project.js?v=<?php echo time(); ?>"></script>
  </body>

</html>

