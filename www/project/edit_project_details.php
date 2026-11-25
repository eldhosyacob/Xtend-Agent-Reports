<?php
require_once __DIR__ . '/../config/auth_check.php';
require_once __DIR__ . '/../config/database.php';

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
    header('Location: project-lists.php');
    exit;
}

$projectIdParam = isset($_GET['project_id']) ? trim($_GET['project_id']) : (isset($_GET['id']) ? trim($_GET['id']) : '');
$project = null;

if (!empty($projectIdParam) && $db) {
    $stmt = $db->prepare("
        SELECT id, project_id, date, project_name, product_name, product_sub_category, created_by, created_at, edited_by, edited_at, category, priority, project_status, project_description, client_dealer_specific, client_dealer_name, product_serial_no, lcd_id, hardware_id, software_version, hasp_id 
        FROM `projects` 
        WHERE project_id = :project_id OR id = :numeric_id
        LIMIT 1
    ");
    $stmt->execute([
        'project_id' => $projectIdParam,
        'numeric_id' => is_numeric($projectIdParam) ? (int)$projectIdParam : 0
    ]);
    $project = $stmt->fetch();
}

// Fetch products for projects (Voice Logger and IVR)
$products = [];
if ($project && $db) {
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

// Fetch existing documents from file-uploads/project/projectRelated/
$existingDocuments = [];
if ($project) {
    $uploadDir = __DIR__ . '/../file-uploads/project/projectRelated/';
    if (is_dir($uploadDir)) {
        $cleanCanonicalPid = preg_replace('/[^a-zA-Z0-9_-]/', '_', $project['project_id'] ?? '');
        $cleanNumPid = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($project['id'] ?? ''));
        $files = scandir($uploadDir);
        if ($files !== false) {
            foreach ($files as $f) {
                if ($f === '.' || $f === '..') continue;
                $filePath = $uploadDir . $f;
                if (!is_file($filePath)) continue;

                $matches = false;
                $matchedPrefix = '';
                if (!empty($cleanCanonicalPid) && strpos($f, $cleanCanonicalPid . '_') === 0) {
                    $matches = true;
                    $matchedPrefix = $cleanCanonicalPid . '_';
                } elseif (!empty($cleanNumPid) && strpos($f, $cleanNumPid . '_') === 0) {
                    $matches = true;
                    $matchedPrefix = $cleanNumPid . '_';
                }

                if ($matches) {
                    $rest = substr($f, strlen($matchedPrefix));
                    $restWithoutReportId = preg_replace('/^\d+_/', '', $rest);
                    $origName = preg_replace('/^[a-f0-9]{13}_/', '', $restWithoutReportId);
                    $sizeBytes = file_exists($filePath) ? filesize($filePath) : 0;
                    $units = ['B', 'KB', 'MB', 'GB'];
                    $pow = $sizeBytes ? floor(log($sizeBytes, 1024)) : 0;
                    $pow = min($pow, count($units) - 1);
                    $sizeFormatted = round($sizeBytes / pow(1024, $pow), 2) . ' ' . $units[$pow];
                    $existingDocuments[] = [
                        'filename' => $f,
                        'name' => $origName,
                        'size' => $sizeFormatted,
                        'url' => '../file-uploads/project/projectRelated/' . rawurlencode($f)
                    ];
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?php echo $project ? 'Edit ' . htmlspecialchars($project['project_name']) : 'Project Not Found'; ?> | Xtend Agent Reports</title>
    <link rel="shortcut icon" href="../images/favicon.png">
    <link rel="stylesheet" href="../styles/header-sidebar.css">
    <link rel="stylesheet" href="../styles/common.css">
    <link rel="stylesheet" href="../styles/project/edit-project-details.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  </head>

  <body>
    <div class="edit-project-page-container page-containers">

      <?php if (!$project): ?>
        <div class="project-not-found-card">
          <i class="fa-regular fa-folder-open"></i>
          <h2>Project Not Found</h2>
          <p>The project you requested does not exist or has been removed.</p>
          <a href="project-lists.php" class="btn btn-primary">
            <i class="fa-solid fa-arrow-left"></i> Back to Project Lists
          </a>
        </div>
      <?php else: ?>

        <div class="project-card">
          <!-- Card Header -->
          <div class="project-card-header edit-project-card-header">
            <div class="edit-project-header-left">
              <div class="project-header-text">
                <h1>Edit Project Details</h1>
                <div class="edit-project-header-badges">
                  <span class="badge-project-id"><?php echo htmlspecialchars($project['project_id']); ?></span>
                  <span class="badge-project-status"><?php echo htmlspecialchars($project['project_status'] ?: 'Assigned'); ?></span>
                </div>
              </div>
            </div>
            <div class="edit-project-header-right">
              <a href="project-lists.php" class="btn-header-back" title="Back to Project Lists">
                <i class="fa-solid fa-arrow-left"></i> Back to Projects
              </a>
            </div>
          </div>

          <!-- Card Body -->
          <div class="project-card-body">
            <?php if (strcasecmp(trim($project['project_status']), 'Assigned') !== 0): ?>
              <div class="edit-alert edit-alert-warning">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><strong>Notice:</strong> This project status is currently "<?php echo htmlspecialchars($project['project_status']); ?>". Editing is primarily intended for projects with "Assigned" status.</span>
              </div>
            <?php endif; ?>

            <form id="editProjectForm" method="POST" enctype="multipart/form-data" autocomplete="off">
              <!-- Hidden Identifier Inputs -->
              <input type="hidden" id="project_id" name="project_id" value="<?php echo htmlspecialchars($project['project_id']); ?>">
              <input type="hidden" id="numeric_id" name="id" value="<?php echo htmlspecialchars($project['id']); ?>">

              <!-- Row 1: Date, Project Name, Category & Priority -->
              <div class="project-form-row project-form-row-4">
                <!-- Date Field -->
                <div class="project-form-group">
                  <label for="project_date">Date <span class="required-star">*</span></label>
                  <div class="project-input-wrapper">
                    <i class="fa-regular fa-calendar-days project-input-icon"></i>
                    <input type="date" id="project_date" name="project_date" class="project-form-control" value="<?php echo htmlspecialchars($project['date']); ?>" required>
                  </div>
                </div>

                <!-- Project Name Field -->
                <div class="project-form-group">
                  <label for="project_name">Project Name <span class="required-star">*</span></label>
                  <div class="project-input-wrapper">
                    <i class="fa-solid fa-diagram-project project-input-icon"></i>
                    <input type="text" id="project_name" name="project_name" class="project-form-control" value="<?php echo htmlspecialchars($project['project_name']); ?>" placeholder="Enter project name" required>
                  </div>
                </div>

                <!-- Category Field -->
                <div class="project-form-group">
                  <label for="project_category">Category <span class="required-star">*</span></label>
                  <div class="project-input-wrapper">
                    <i class="fa-solid fa-layer-group project-input-icon"></i>
                    <select id="project_category" name="project_category" class="project-form-control" required>
                      <option value="" disabled>Select Category</option>
                      <option value="Driver" <?php echo ($project['category'] === 'Driver') ? 'selected' : ''; ?>>Driver</option>
                      <option value="Hardware" <?php echo ($project['category'] === 'Hardware') ? 'selected' : ''; ?>>Hardware</option>
                      <option value="Software" <?php echo ($project['category'] === 'Software') ? 'selected' : ''; ?>>Software</option>
                      <option value="Integrated" <?php echo ($project['category'] === 'Integrated') ? 'selected' : ''; ?>>Integrated</option>
                    </select>
                  </div>
                </div>

                <!-- Priority Field -->
                <div class="project-form-group">
                  <label for="project_priority">Priority <span class="required-star">*</span></label>
                  <div class="project-input-wrapper">
                    <i class="fa-solid fa-flag project-input-icon"></i>
                    <select id="project_priority" name="project_priority" class="project-form-control" required>
                      <option value="" disabled>Select Priority</option>
                      <option value="Low" <?php echo ($project['priority'] === 'Low') ? 'selected' : ''; ?>>Low</option>
                      <option value="Medium" <?php echo ($project['priority'] === 'Medium') ? 'selected' : ''; ?>>Medium</option>
                      <option value="High" <?php echo ($project['priority'] === 'High') ? 'selected' : ''; ?>>High</option>
                    </select>
                  </div>
                </div>
              </div>

              <input type="hidden" id="project_status" name="project_status" value="<?php echo htmlspecialchars($project['project_status'] ?: 'Assigned'); ?>">

              <!-- Row 2: Product Name, Product Sub Category, Client/Dealer Specific & Client Name/Dealer Name -->
              <div class="project-form-row project-form-row-4">
                <!-- Product Name Field -->
                <div class="project-form-group">
                  <label for="product_name">Product Name <span class="required-star">*</span></label>
                  <div class="project-input-wrapper">
                    <i class="fa-solid fa-box project-input-icon"></i>
                    <select id="product_name" name="product_name" class="project-form-control" data-initial-value="<?php echo htmlspecialchars($project['product_name'] ?? ''); ?>" required>
                      <option value="" disabled <?php echo empty($project['product_name']) ? 'selected' : ''; ?>>Select Product Name</option>
                      <?php $productMatched = false; ?>
                      <?php foreach ($products as $prod): ?>
                        <?php $isProdSelected = (strcasecmp($project['product_name'] ?? '', $prod) === 0); ?>
                        <?php if ($isProdSelected) $productMatched = true; ?>
                        <option value="<?php echo htmlspecialchars($prod); ?>" <?php echo $isProdSelected ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars($prod); ?>
                        </option>
                      <?php endforeach; ?>
                      <?php if (!empty($project['product_name']) && !$productMatched): ?>
                        <option value="<?php echo htmlspecialchars($project['product_name']); ?>" selected>
                          <?php echo htmlspecialchars($project['product_name']); ?>
                        </option>
                      <?php endif; ?>
                    </select>
                  </div>
                </div>

                <!-- Product Sub Category Field -->
                <div class="project-form-group">
                  <label for="product_sub_category">Product Sub Category</label>
                  <div class="project-input-wrapper">
                    <i class="fa-solid fa-layer-group project-input-icon"></i>
                    <input type="text" id="product_sub_category" name="product_sub_category" class="project-form-control" value="<?php echo htmlspecialchars($project['product_sub_category'] ?? ''); ?>" placeholder="Enter product sub category">
                  </div>
                </div>

                <!-- Client/Dealer Specific Field -->
                <div class="project-form-group">
                  <label for="client_dealer_specific">Client/Dealer Specific <span class="required-star">*</span></label>
                  <div class="project-input-wrapper">
                    <i class="fa-solid fa-handshake project-input-icon"></i>
                    <select id="client_dealer_specific" name="client_dealer_specific" class="project-form-control" required>
                      <option value="No" <?php echo (strcasecmp($project['client_dealer_specific'] ?? '', 'Yes') !== 0) ? 'selected' : ''; ?>>No</option>
                      <option value="Yes" <?php echo (strcasecmp($project['client_dealer_specific'] ?? '', 'Yes') === 0) ? 'selected' : ''; ?>>Yes</option>
                    </select>
                  </div>
                </div>

                <!-- Client Name/Dealer Name Field -->
                <?php
                $isClientSpecific = (strcasecmp($project['client_dealer_specific'] ?? '', 'Yes') === 0);
                $clientNameVal = $project['client_dealer_name'] ?? 'NA';
                ?>
                <div class="project-form-group">
                  <label for="client_dealer_name">Client Name/Dealer Name <span class="required-star client-dealer-star <?php echo $isClientSpecific ? 'active' : ''; ?>" id="clientDealerStar">*</span></label>
                  <div class="project-input-wrapper autocomplete-wrapper">
                    <i class="fa-regular fa-building project-input-icon"></i>
                    <input type="text" id="client_dealer_name" name="client_dealer_name" class="project-form-control project-client-dealer-input" value="<?php echo htmlspecialchars($clientNameVal); ?>" placeholder="<?php echo $isClientSpecific ? 'Enter or select client/dealer name' : 'NA'; ?>" autocomplete="off" <?php echo $isClientSpecific ? '' : 'disabled'; ?>>
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

              <!-- Row 4: Product Serial No, LCD ID, Hardware ID/Card ID & Software Version -->
              <div class="project-form-row project-form-row-4">
                <!-- Product Serial No Field -->
                <div class="project-form-group">
                  <label for="product_serial_no">Product Serial No</label>
                  <div class="project-input-wrapper">
                    <i class="fa-solid fa-barcode project-input-icon"></i>
                    <input type="text" id="product_serial_no" name="product_serial_no" class="project-form-control" value="<?php echo htmlspecialchars($project['product_serial_no'] ?? ''); ?>" placeholder="Enter product serial no">
                  </div>
                </div>

                <!-- LCD ID Field -->
                <div class="project-form-group">
                  <label for="lcd_id">LCD ID</label>
                  <div class="project-input-wrapper">
                    <i class="fa-solid fa-desktop project-input-icon"></i>
                    <input type="text" id="lcd_id" name="lcd_id" class="project-form-control" value="<?php echo htmlspecialchars($project['lcd_id'] ?? ''); ?>" placeholder="Enter LCD ID">
                  </div>
                </div>

                <!-- Hardware ID/Card ID Field -->
                <div class="project-form-group">
                  <label for="hardware_id">Hardware ID/Card ID</label>
                  <div class="project-input-wrapper">
                    <i class="fa-solid fa-microchip project-input-icon"></i>
                    <input type="text" id="hardware_id" name="hardware_id" class="project-form-control" value="<?php echo htmlspecialchars($project['hardware_id'] ?? ''); ?>" placeholder="Enter Hardware ID / Card ID">
                  </div>
                </div>

                <!-- Software Version Field -->
                <div class="project-form-group">
                  <label for="software_version">Software Version</label>
                  <div class="project-input-wrapper">
                    <i class="fa-solid fa-code-branch project-input-icon"></i>
                    <input type="text" id="software_version" name="software_version" class="project-form-control" value="<?php echo htmlspecialchars($project['software_version'] ?? ''); ?>" placeholder="Enter software version">
                  </div>
                </div>
              </div>

              <!-- Row 5: HASP ID Field -->
              <div class="project-form-row project-form-row-4">
                <div class="project-form-group">
                  <label for="hasp_id">HASP ID</label>
                  <div class="project-input-wrapper">
                    <i class="fa-solid fa-key project-input-icon"></i>
                    <input type="text" id="hasp_id" name="hasp_id" class="project-form-control" value="<?php echo htmlspecialchars($project['hasp_id'] ?? ''); ?>" placeholder="Enter HASP ID">
                  </div>
                </div>
              </div>

              <!-- Row 6: Project Description -->
              <div class="project-form-row">
                <div class="project-form-group">
                  <label for="project_description">Project Description</label>
                  <div class="project-input-wrapper">
                    <i class="fa-solid fa-align-left project-input-icon project-textarea-icon"></i>
                    <textarea id="project_description" name="project_description" class="project-form-control project-textarea" rows="4" placeholder="Enter project description..."><?php echo htmlspecialchars($project['project_description'] ?? ''); ?></textarea>
                  </div>
                </div>
              </div>

              <!-- Row 7: Documents Section (Existing & Upload New) -->
              <div class="project-form-row">
                <div class="project-form-group">
                  <label>Project Documents</label>

                  <!-- Existing Documents -->
                  <?php if (!empty($existingDocuments)): ?>
                    <div class="existing-documents-card" id="existingDocumentsCard">
                      <div class="existing-documents-title">
                        <i class="fa-solid fa-paperclip"></i> Existing Documents (<?php echo count($existingDocuments); ?>)
                      </div>
                      <div class="existing-documents-list" id="existingDocumentsList">
                        <?php foreach ($existingDocuments as $doc): ?>
                          <div class="existing-doc-item">
                            <div class="existing-doc-info">
                              <i class="fa-regular fa-file"></i>
                              <a href="<?php echo htmlspecialchars($doc['url']); ?>" target="_blank" class="existing-doc-name" title="<?php echo htmlspecialchars($doc['name']); ?>">
                                <?php echo htmlspecialchars($doc['name']); ?>
                              </a>
                              <span class="existing-doc-size">(<?php echo htmlspecialchars($doc['size']); ?>)</span>
                            </div>
                            <div class="existing-doc-actions">
                              <a href="<?php echo htmlspecialchars($doc['url']); ?>" target="_blank" class="btn-view-doc" title="View Document">
                                <i class="fa-regular fa-eye"></i>
                              </a>
                              <button type="button" class="btn-delete-existing-doc" data-filename="<?php echo htmlspecialchars($doc['filename']); ?>" data-project-id="<?php echo htmlspecialchars($project['project_id']); ?>" title="Delete Document">
                                <i class="fa-solid fa-trash-can"></i>
                              </button>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php else: ?>
                    <div class="existing-documents-card" id="existingDocumentsCard" style="display:none;">
                      <div class="existing-documents-list" id="existingDocumentsList"></div>
                    </div>
                  <?php endif; ?>

                  <!-- Upload New Documents -->
                  <div class="file-upload-wrapper">
                    <input type="file" id="uploaded_documents" name="uploaded_documents[]" class="file-upload-input" multiple accept=".pdf,.png,.jpg,.jpeg,.gif,.csv,.doc,.docx,.xls,.xlsx,.zip,.txt">
                    <div class="file-upload-content">
                      <i class="fa-solid fa-cloud-arrow-up"></i>
                      <div class="file-upload-text">Drag & drop new documents here or <span>browse</span></div>
                      <div class="file-upload-hint">Supports PDF, Images, Documents, CSV (Maximum 2 documents total)</div>
                    </div>
                  </div>
                  <div class="file-list-preview" id="fileListPreview"></div>
                </div>
              </div>

              <!-- Form Actions -->
              <div class="project-form-actions">
                <a href="project-lists.php" class="btn btn-secondary">
                  <i class="fa-solid fa-xmark"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary" id="btnUpdateProject">
                  <i class="fa-solid fa-floppy-disk"></i> Update Project
                </button>
              </div>
            </form>
          </div>
        </div>

      <?php endif; ?>

    </div>

    <!-- Header & Sidebar Navigation Component -->
    <?php include __DIR__ . '/../components/header-sidebar.php'; ?>

    <script src="../plugins/jquery-3.7.1.min.js"></script>
    <script src="../js/project/edit_project_details.js?v=<?php echo time(); ?>"></script>
  </body>

</html>
