<?php
// Secure the page - ensure user is authenticated
require_once __DIR__ . '/../config/auth_check.php';

// Include database configuration
require_once __DIR__ . '/../config/database.php';
$db = getDatabaseConnection();

if (!$db) {
    die("Database connection failed.");
}

// Determine authenticated user's department
$user_department = isset($_SESSION['department']) ? trim($_SESSION['department']) : '';
if (empty($user_department) && !empty($_SESSION['id'])) {
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

// Helper function to find matching product in list
if (!function_exists('findMatchingProduct')) {
    function findMatchingProduct($target, $productList) {
        if (empty($target) || empty($productList)) {
            return '';
        }
        $targetTrimmed = trim($target);
        foreach ($productList as $p) {
            if (strcasecmp(trim($p), $targetTrimmed) === 0) {
                return $p;
            }
        }
        $normTarget = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($targetTrimmed));
        if ($normTarget !== '') {
            foreach ($productList as $p) {
                $normP = preg_replace('/[^a-zA-Z0-9]/', '', strtolower(trim($p)));
                if ($normP === $normTarget) {
                    return $p;
                }
            }
        }
        return '';
    }
}

$report_id = trim($_GET['id'] ?? $_GET['report_id'] ?? '');
$project_id_param = trim($_GET['project_id'] ?? '');
$subtask_id_param = trim($_GET['subtask_id'] ?? '');

$report = null;

if ($report_id !== '') {
    $stmt = $db->prepare("SELECT * FROM `project_reports` WHERE `id` = :id LIMIT 1");
    $stmt->execute(['id' => $report_id]);
    $report = $stmt->fetch();
} elseif ($project_id_param !== '' && $subtask_id_param !== '') {
    // If accessed with project_id and subtask_id parameters, check for existing report created on same day or create one
    $stmt = $db->prepare("SELECT * FROM `project_reports` WHERE `project_id` = :pid AND `subtask_id` = :sid AND `date` = CURDATE() ORDER BY `id` DESC LIMIT 1");
    $stmt->execute(['pid' => $project_id_param, 'sid' => $subtask_id_param]);
    $report = $stmt->fetch();

    if (!$report) {
        // Automatically initialize report record
        $agentName = !empty($_SESSION['real_name']) ? trim($_SESSION['real_name']) : (!empty($_SESSION['full_name']) ? trim($_SESSION['full_name']) : trim($_SESSION['username']));
        $projStmt = $db->prepare("SELECT * FROM `projects` WHERE `project_id` = :pid OR `id` = :numeric_id LIMIT 1");
        $projStmt->execute([
            'pid' => $project_id_param,
            'numeric_id' => is_numeric($project_id_param) ? (int)$project_id_param : 0
        ]);
        $projRow = $projStmt->fetch();

        $prodName = !empty($projRow['product_name']) ? trim($projRow['product_name']) : '';
        $prodSubCategory = !empty($projRow['product_sub_category']) ? trim($projRow['product_sub_category']) : null;
        $deptVal = !empty($projRow['department']) ? trim($projRow['department']) : null;
        $prodSerialNo = !empty($projRow['product_serial_no']) ? trim($projRow['product_serial_no']) : null;
        $softVersion = !empty($projRow['software_version']) ? trim($projRow['software_version']) : null;
        $lcdIdVal = !empty($projRow['lcd_id']) ? trim($projRow['lcd_id']) : null;
        $hwIdVal = !empty($projRow['hardware_id']) ? trim($projRow['hardware_id']) : null;
        $haspIdVal = !empty($projRow['hasp_id']) ? trim($projRow['hasp_id']) : null;

        $assignedBy = $projRow['assigned_by'] ?? '';
        $productType = $projRow['category'] ?? '';
        $receivedFrom = !empty($projRow['date']) ? $projRow['date'] : null;
        $clientName = '';
        if (!empty($projRow['client_dealer_name']) && strcasecmp(trim($projRow['client_dealer_name']), 'NA') !== 0 && strcasecmp(trim($projRow['client_dealer_name']), 'N/A') !== 0) {
            $clientName = trim($projRow['client_dealer_name']);
        }

        // Fetch current subtask status if available
        $stQuery = $db->prepare("SELECT status FROM `project_subtasks` WHERE `subtask_id` = :sid1 OR `id` = :sid2 LIMIT 1");
        $stQuery->execute([
            'sid1' => $subtask_id_param,
            'sid2' => is_numeric($subtask_id_param) ? (int)$subtask_id_param : 0
        ]);
        $stRow = $stQuery->fetch();
        $initialStatus = !empty($stRow['status']) ? $stRow['status'] : 'Pending';

        $insertStmt = $db->prepare("
            INSERT INTO `project_reports` 
            (`project_id`, `subtask_id`, `agent_name`, `department`, `date`, `product_name`, `product_sub_category`, `product_type`, `received_from`, `assigned_by`, `product_id`, `software_version`, `lcd_id`, `hardware_id`, `hasp_id`, `client_name`, `start_time`, `status`) 
            VALUES 
            (:project_id, :subtask_id, :agent_name, :department, :date, :product_name, :product_sub_category, :product_type, :received_from, :assigned_by, :product_id, :software_version, :lcd_id, :hardware_id, :hasp_id, :client_name, :start_time, :status)
        ");
        $insertStmt->execute([
            'project_id' => $project_id_param,
            'subtask_id' => $subtask_id_param,
            'agent_name' => $agentName,
            'department' => $deptVal,
            'date' => date('Y-m-d'),
            'product_name' => $prodName,
            'product_sub_category' => $prodSubCategory,
            'product_type' => $productType,
            'received_from' => $receivedFrom,
            'assigned_by' => $assignedBy,
            'product_id' => $prodSerialNo,
            'software_version' => $softVersion,
            'lcd_id' => $lcdIdVal,
            'hardware_id' => $hwIdVal,
            'hasp_id' => $haspIdVal,
            'client_name' => $clientName,
            'start_time' => date('H:i:s'),
            'status' => $initialStatus
        ]);
        $newId = $db->lastInsertId();

        $stmt = $db->prepare("SELECT * FROM `project_reports` WHERE `id` = :id LIMIT 1");
        $stmt->execute(['id' => $newId]);
        $report = $stmt->fetch();
    }
}

if (!$report) {
    die("Error: Project Report not found. Please access this page from the project details.");
}

// Ensure loaded report status matches the subtask's latest status if available
if (!empty($report['subtask_id'])) {
    $stStmt = $db->prepare("SELECT status FROM `project_subtasks` WHERE `subtask_id` = :sid1 OR `id` = :sid2 LIMIT 1");
    $stStmt->execute([
        'sid1' => $report['subtask_id'],
        'sid2' => is_numeric($report['subtask_id']) ? (int)$report['subtask_id'] : 0
    ]);
    $stRow = $stStmt->fetch();
    if ($stRow && !empty($stRow['status'])) {
        $report['status'] = $stRow['status'];
    }
}

// Fetch project details automatically by project_id
$project = null;
$projectId = !empty($report['project_id']) ? $report['project_id'] : $project_id_param;
if (!empty($projectId)) {
    $projStmt = $db->prepare("SELECT * FROM `projects` WHERE `project_id` = :pid OR `id` = :numeric_id LIMIT 1");
    $projStmt->execute([
        'pid' => $projectId,
        'numeric_id' => is_numeric($projectId) ? (int)$projectId : 0
    ]);
    $project = $projStmt->fetch();
}

// Automatically resolve project attributes (reflect from project if report field is empty)
$department_val = !empty($report['department']) ? trim($report['department']) : (!empty($project['department']) ? trim($project['department']) : '');
$product_name_val = !empty($report['product_name']) ? trim($report['product_name']) : (!empty($project['product_name']) ? trim($project['product_name']) : '');
$selected_product = '';
if (!empty($product_name_val)) {
    foreach ($products as $p) {
        if (strcasecmp(trim($p), $product_name_val) === 0) {
            $selected_product = $p;
            break;
        }
    }
    if (empty($selected_product)) {
        $selected_product = $product_name_val;
    }
}
$product_sub_category_val = !empty($report['product_sub_category']) ? trim($report['product_sub_category']) : (!empty($project['product_sub_category']) ? trim($project['product_sub_category']) : '');
$product_type_val = !empty($report['product_type']) ? $report['product_type'] : ($project['category'] ?? '');
$received_from_val = !empty($report['received_from']) ? $report['received_from'] : ($project['date'] ?? '');
$assigned_by_val = !empty($report['assigned_by']) ? $report['assigned_by'] : ($project['assigned_by'] ?? '');
$product_id_val = !empty($report['product_id']) ? trim($report['product_id']) : (!empty($project['product_serial_no']) ? trim($project['product_serial_no']) : '');
$software_version_val = !empty($report['software_version']) ? trim($report['software_version']) : (!empty($project['software_version']) ? trim($project['software_version']) : '');
$lcd_id_val = !empty($report['lcd_id']) ? trim($report['lcd_id']) : (!empty($project['lcd_id']) ? trim($project['lcd_id']) : '');
$hardware_id_val = !empty($report['hardware_id']) ? trim($report['hardware_id']) : (!empty($project['hardware_id']) ? trim($project['hardware_id']) : '');
$hasp_id_val = !empty($report['hasp_id']) ? trim($report['hasp_id']) : (!empty($project['hasp_id']) ? trim($project['hasp_id']) : '');

$client_name_from_proj = '';
if (!empty($project['client_dealer_name']) && strcasecmp(trim($project['client_dealer_name']), 'NA') !== 0 && strcasecmp(trim($project['client_dealer_name']), 'N/A') !== 0) {
    $client_name_from_proj = trim($project['client_dealer_name']);
}
$client_name_val = !empty($report['client_name']) ? $report['client_name'] : $client_name_from_proj;

// Fetch subtask title for header context
$subtaskTitle = '';
if (!empty($report['subtask_id'])) {
    $subtaskStmt = $db->prepare("SELECT title FROM `project_subtasks` WHERE `subtask_id` = :sid1 OR `id` = :sid2 LIMIT 1");
    $subtaskStmt->execute(['sid1' => $report['subtask_id'], 'sid2' => $report['subtask_id']]);
    $subtaskRow = $subtaskStmt->fetch();
    if ($subtaskRow) {
        $subtaskTitle = $subtaskRow['title'];
    }
}

// Calculate elapsed seconds since start_time for stopwatch timer
$offset_seconds = 0;
if (!empty($report['total_time']) && $report['total_time'] !== '00:00:00') {
    $timeParts = explode(':', $report['total_time']);
    $offset_seconds = (int)($timeParts[0] ?? 0) * 3600 + (int)($timeParts[1] ?? 0) * 60 + (int)($timeParts[2] ?? 0);
} elseif (!empty($report['date']) && !empty($report['start_time'])) {
    $start_datetime = $report['date'] . ' ' . $report['start_time'];
    $start_timestamp = strtotime($start_datetime);
    if ($start_timestamp !== false) {
        $offset_seconds = max(0, time() - $start_timestamp);
    }
}

// Helper function to print selected attributes
if (!function_exists('getSelected')) {
    function getSelected($val, $dbVal) {
        return (strcasecmp(trim((string)$val), trim((string)$dbVal)) === 0) ? 'selected' : '';
    }
}

// Fetch clients from company_list for autocomplete
$clients = [];
$clientStmt = $db->query("SELECT company_name FROM company_list ORDER BY company_name ASC");
if ($clientStmt) {
    $clients = $clientStmt->fetchAll(PDO::FETCH_COLUMN);
}

// Fetch existing attachments for this report from file-uploads/project/
$reportAttachments = [];
$projectUploadDir = __DIR__ . '/../file-uploads/project/';
if (is_dir($projectUploadDir) && !empty($report['project_id']) && !empty($report['id'])) {
    $cleanProjId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $report['project_id']);
    $repPrefix = $cleanProjId . '_' . (int)$report['id'] . '_';
    $pFiles = scandir($projectUploadDir);
    foreach ($pFiles as $f) {
        if ($f === '.' || $f === '..') continue;
        if (strpos($f, $repPrefix) === 0) {
            $rest = substr($f, strlen($repPrefix));
            $origName = preg_replace('/^[a-f0-9]{13}_/', '', $rest);
            $filePath = $projectUploadDir . $f;
            $sizeBytes = file_exists($filePath) ? filesize($filePath) : 0;
            $units = ['B', 'KB', 'MB', 'GB'];
            $pow = $sizeBytes ? floor(log($sizeBytes, 1024)) : 0;
            $pow = min($pow, count($units) - 1);
            $sizeFormatted = round($sizeBytes / pow(1024, $pow), 2) . ' ' . $units[$pow];
            $reportAttachments[] = [
                'filename' => $f,
                'name' => $origName,
                'size' => $sizeFormatted,
                'url' => '../file-uploads/project/' . rawurlencode($f)
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>New Project Report (<?php echo htmlspecialchars($report['project_id']); ?> - <?php echo htmlspecialchars($report['subtask_id']); ?>) | Xtend Agent Reports</title>
    <link rel="shortcut icon" href="../images/favicon.png" />
    <link rel="stylesheet" href="../styles/header-sidebar.css">
    <link rel="stylesheet" href="../styles/new_record.css">
    <link rel="stylesheet" href="../styles/common.css">
    <link rel="stylesheet" href="../styles/project/new-project-report.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  </head>

  <body>
    <div class="new-record-page-container page-containers">
      
      <!-- <div class="back-link-wrapper">
        <a href="my-project-details.php?id=<?php echo urlencode($report['project_id']); ?>" class="back-link-btn">
          <i class="fa-solid fa-arrow-left"></i> Back to Project Details
        </a>
      </div> -->

      <div class="form-card-wrapper">
        <div class="form-header">
          <!-- Stopwatch Timer Widget -->
          <div class="timer-widget">
            <div class="timer-label"><i class="fa-regular fa-clock"></i></div>
            <div class="timer-display-wrapper">
              <span class="timer-pulse-dot"></span>
              <div class="timer-display" id="runningTimer">00:00:00</div>
            </div>
          </div>
          <h2>Create Project Report</h2>
          <p>Document testing details and progress of the assigned subtask.</p>

          <div class="report-meta-badges">
            <div class="report-badge report-badge-subtask"><?php echo htmlspecialchars($project['project_name']); ?></div>
            <span class="report-badge report-badge-project">
              <i class="fa-solid fa-diagram-project"></i> Project: <?php echo htmlspecialchars($report['project_id']); ?>
            </span>
            <span class="report-badge report-badge-subtask">
              <i class="fa-solid fa-list-check"></i> Subtask: <?php echo htmlspecialchars($report['subtask_id']); ?>
            </span>
          </div>
        </div>

        <form id="projectReportForm" autocomplete="off" enctype="multipart/form-data">
          <input type="hidden" name="id" value="<?php echo htmlspecialchars($report['id']); ?>">
          <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($report['project_id']); ?>">
          <input type="hidden" name="subtask_id" value="<?php echo htmlspecialchars($report['subtask_id']); ?>">

          <div class="grid-form">

            <!-- 1. DATE -->
            <div class="form-group col-4">
              <label for="date">Date</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-calendar input-icon"></i>
                <input type="date" id="date" name="date" class="form-control" value="<?php echo htmlspecialchars($report['date'] ?? date('Y-m-d')); ?>" readonly required>
              </div>
            </div>

            <!-- 2. AGENT NAME -->
            <div class="form-group col-4">
              <label for="agent_name">Agent Name</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-user input-icon"></i>
                <input type="text" id="agent_name" name="agent_name" class="form-control" value="<?php echo htmlspecialchars($report['agent_name'] ?? ''); ?>" readonly required>
              </div>
            </div>

            <!-- 3. DEPARTMENT -->
            <div class="form-group col-4">
              <label for="department">DEPARTMENT</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-sitemap input-icon"></i>
                <input type="text" id="department" name="department" class="form-control" placeholder="Enter department" value="<?php echo htmlspecialchars($department_val); ?>">
              </div>
            </div>
            
            <!-- 4. PRODUCT NAME -->
            <div class="form-group col-6">
              <label for="product_name">Product Name</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-box input-icon"></i>
                <select id="product_name" name="product_name" class="form-control" required>
                  <option value="" disabled <?php echo empty($selected_product) ? 'selected' : ''; ?>>Select Product Name</option>
                  <?php 
                  $foundSelectedInList = false;
                  foreach ($products as $prod): 
                    $isSelected = (strcasecmp(trim($prod), trim($selected_product)) === 0);
                    if ($isSelected) $foundSelectedInList = true;
                  ?>
                    <option value="<?php echo htmlspecialchars($prod); ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($prod); ?>
                    </option>
                  <?php endforeach; ?>
                  <?php if (!empty($selected_product) && !$foundSelectedInList): ?>
                    <option value="<?php echo htmlspecialchars($selected_product); ?>" selected>
                      <?php echo htmlspecialchars($selected_product); ?>
                    </option>
                  <?php endif; ?>
                </select>
              </div>
            </div>
            
            <!-- 5. PRODUCT SUB CATEGORY -->
            <div class="form-group col-6">
              <label for="product_sub_category">Product Sub Category</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-layer-group input-icon"></i>
                <input type="text" id="product_sub_category" name="product_sub_category" class="form-control" placeholder="Enter product sub category" value="<?php echo htmlspecialchars($product_sub_category_val); ?>">
              </div>
            </div>

            <!-- 6. PRODUCT TYPE -->
            <div class="form-group col-6">
              <label for="product_type">Product Type</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-cubes input-icon"></i>
                <input type="text" id="product_type" name="product_type" class="form-control" placeholder="Enter product type (e.g. Hardware, Software, Driver)" value="<?php echo htmlspecialchars($product_type_val); ?>" readonly>
              </div>
            </div>

            <!-- 7. ASSIGNED BY -->
            <div class="form-group col-6">
              <label for="assigned_by">Assigned By</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-users-gear input-icon"></i>
                <input type="text" id="assigned_by" name="assigned_by" class="form-control" placeholder="Enter assigned by" value="<?php echo htmlspecialchars($assigned_by_val); ?>" readonly>
              </div>
            </div>

            <!-- 8. PRODUCT ID -->
            <div class="form-group col-6">
              <label for="product_id">Product ID</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-barcode input-icon"></i>
                <input type="text" id="product_id" name="product_id" class="form-control" placeholder="Enter product ID / serial number" value="<?php echo htmlspecialchars($product_id_val); ?>">
              </div>
            </div>

            <!-- 9. SOFTWARE VERSION -->
            <div class="form-group col-6">
              <label for="software_version">Software Version</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-code-branch input-icon"></i>
                <input type="text" id="software_version" name="software_version" class="form-control" placeholder="Enter software version" value="<?php echo htmlspecialchars($software_version_val); ?>">
              </div>
            </div>

            <!-- 10. LCD ID -->
            <div class="form-group col-6">
              <label for="lcd_id">LCD ID</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-desktop input-icon"></i>
                <input type="text" id="lcd_id" name="lcd_id" class="form-control" placeholder="Enter LCD ID" value="<?php echo htmlspecialchars($lcd_id_val); ?>">
              </div>
            </div>

            <!-- 11. HARDWARE ID/CARD ID -->
            <div class="form-group col-6">
              <label for="hardware_id">Hardware ID/Card ID</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-microchip input-icon"></i>
                <input type="text" id="hardware_id" name="hardware_id" class="form-control" placeholder="Enter Hardware ID / Card ID" value="<?php echo htmlspecialchars($hardware_id_val); ?>">
              </div>
            </div>

            <!-- 12. HASP ID -->
            <div class="form-group col-6">
              <label for="hasp_id">HASP ID</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-key input-icon"></i>
                <input type="text" id="hasp_id" name="hasp_id" class="form-control" placeholder="Enter HASP ID" value="<?php echo htmlspecialchars($hasp_id_val); ?>">
              </div>
            </div>

            <!-- 13. CLIENT NAME -->
            <div class="form-group col-6">
              <label for="client_name">Client Name</label>
              <div class="form-control-wrapper autocomplete-wrapper">
                <i class="fa-regular fa-building input-icon"></i>
                <input type="text" id="client_name" name="client_name" class="form-control" placeholder="Enter client name" value="<?php echo htmlspecialchars($client_name_val); ?>" autocomplete="off">
                <div class="autocomplete-suggestions" id="clientSuggestions"></div>
              </div>
            </div>
            
            <!-- 11. YOUTRACK ID -->
            <!-- <div class="form-group col-6">
              <label for="youtrack_id">Youtrack ID</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-bug input-icon"></i>
                <input type="text" id="youtrack_id" name="youtrack_id" class="form-control" placeholder="Enter Youtrack ID" value="<?php echo htmlspecialchars($report['youtrack_id'] ?? ''); ?>">
              </div>
            </div> -->
            
            <!-- 12. RECEIVED FROM (DATE) -->
            <div class="form-group col-6">
              <label for="received_from">Received From (Date)</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-calendar-check input-icon"></i>
                <input type="date" id="received_from" name="received_from" class="form-control" value="<?php echo htmlspecialchars($received_from_val); ?>" readonly>
              </div>
            </div>
            
            <!-- 13. SENT TO (DATE) -->
            <div class="form-group col-6">
              <label for="sent_to">Sent To (Date)</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-calendar-plus input-icon"></i>
                <input type="date" id="sent_to" name="sent_to" class="form-control" value="<?php echo htmlspecialchars($report['sent_to'] ?? ''); ?>">
              </div>
            </div>
            
            <!-- 14. TESTING DESCRIPTION -->
            <div class="form-group col-12">
              <label for="testing_description">Testing Description</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-file-lines input-icon textarea-icon"></i>
                <textarea id="testing_description" name="testing_description" class="form-control" placeholder="Enter detailed testing description, observations, test cases, findings..."><?php echo htmlspecialchars($report['testing_description'] ?? ''); ?></textarea>
              </div>
            </div>

            <!-- 15. START TIME -->
            <div class="form-group col-4">
              <label for="start_time">Start Time</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-hourglass-start input-icon"></i>
                <input type="text" id="start_time" name="start_time" class="form-control" value="<?php echo htmlspecialchars($report['start_time'] ?? date('H:i:s')); ?>" readonly required>
              </div>
            </div>
            
            <!-- 16. END TIME -->
            <div class="form-group col-4">
              <label for="end_time">End Time</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-hourglass-end input-icon"></i>
                <input type="text" id="end_time" name="end_time" class="form-control" value="<?php echo htmlspecialchars($report['end_time'] ?? ''); ?>" readonly>
              </div>
            </div>
            
            <!-- 17. TOTAL TIME -->
            <div class="form-group col-4">
              <label for="total_time">Total Time</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-clock input-icon"></i>
                <input type="text" id="total_time" name="total_time" class="form-control" value="<?php echo htmlspecialchars($report['total_time'] ?? '00:00:00'); ?>" readonly required>
              </div>
            </div>
            
            <!-- 3. STATUS -->
            <div class="form-group col-4">
              <label for="status">Status</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-circle-check input-icon"></i>
                <select id="status" name="status" class="form-control" required>
                  <option value="" disabled <?php echo empty($report['status']) ? 'selected' : ''; ?>>Select Status</option>
                  <option value="Pending" <?php echo getSelected('Pending', $report['status'] ?? 'Pending'); ?>>Pending</option>
                  <option value="In Progress" <?php echo getSelected('In Progress', $report['status'] ?? ''); ?>>In Progress</option>
                  <option value="On Hold" <?php echo getSelected('On Hold', $report['status'] ?? ''); ?>>On Hold</option>
                  <option value="Completed" <?php echo getSelected('Completed', $report['status'] ?? ''); ?>>Completed</option>
                </select>
              </div>
            </div>

            <!-- 18. ATTACHMENTS (FILE UPLOAD) -->
            <div class="form-group col-12">
              <label for="uploaded_files">Upload Additional Files (PDF, Images, CSV, etc.)</label>
              <div class="file-upload-wrapper">
                <input type="file" id="uploaded_files" name="uploaded_files[]" class="file-upload-input" multiple accept=".pdf,.png,.jpg,.jpeg,.gif,.csv,.doc,.docx,.xls,.xlsx,.zip,.txt">
                <div class="file-upload-content">
                  <i class="fa-solid fa-cloud-arrow-up"></i>
                  <div class="file-upload-text">Drag & drop files here or <span>browse</span></div>
                  <div class="file-upload-hint">Supports PDF, Images, CSV, Documents (Max: 4 files at a time, 10MB total)</div>
                </div>
              </div>
              <div class="file-list-preview" id="fileListPreview"></div>

              <?php if (!empty($reportAttachments)): ?>
                <div class="existing-attachments-wrapper">
                  <div class="existing-attachments-title">
                    <i class="fa-solid fa-paperclip"></i> Previously Uploaded Files:
                  </div>
                  <div class="attachments-list">
                    <?php foreach ($reportAttachments as $att): 
                      $ext = strtolower(pathinfo($att['name'], PATHINFO_EXTENSION));
                      $iconClass = 'fa-regular fa-file';
                      if (in_array($ext, ['pdf'])) $iconClass = 'fa-regular fa-file-pdf';
                      elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) $iconClass = 'fa-regular fa-file-image';
                      elseif (in_array($ext, ['csv', 'xls', 'xlsx'])) $iconClass = 'fa-regular fa-file-excel';
                      elseif (in_array($ext, ['doc', 'docx'])) $iconClass = 'fa-regular fa-file-word';
                      elseif (in_array($ext, ['zip', 'rar', '7z'])) $iconClass = 'fa-regular fa-file-zipper';
                    ?>
                      <div class="attachment-card">
                        <div class="attachment-info">
                          <div class="attachment-icon">
                            <i class="<?php echo $iconClass; ?>"></i>
                          </div>
                          <div class="attachment-details">
                            <span class="attachment-name"><?php echo htmlspecialchars($att['name']); ?></span>
                            <span class="attachment-meta"><?php echo htmlspecialchars($att['size']); ?></span>
                          </div>
                        </div>
                        <a href="<?php echo htmlspecialchars($att['url']); ?>" download class="btn-download-file">
                          <i class="fa-solid fa-download"></i> Download
                        </a>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>
            </div>

            <!-- Form Action Buttons -->
            <div class="form-actions">
              <!-- <button type="button" id="resetBtn" class="btn btn-secondary">
                <i class="fa-solid fa-rotate-left"></i> Reset
              </button> -->
              <button type="submit" id="submitBtn" class="btn btn-primary">
                <i class="fa-regular fa-paper-plane"></i> Save Report
              </button>
            </div>

          </div>
        </form>

      </div>
    </div>

    <!-- Include Header & Sidebar Component -->
    <?php include __DIR__ . '/../components/header-sidebar.php'; ?>

    <script>
      window.projectReportOffsetSeconds = <?php echo (int)$offset_seconds; ?>;
      window.projectReportClients = <?php echo json_encode($clients); ?>;
    </script>
    <script src="../plugins/jquery-3.7.1.min.js"></script>
    <script src="../js/project/new-project-report.js?v=<?php echo file_exists(__DIR__ . '/../js/project/new-project-report.js') ? filemtime(__DIR__ . '/../js/project/new-project-report.js') : time(); ?>"></script>
  </body>

</html>
