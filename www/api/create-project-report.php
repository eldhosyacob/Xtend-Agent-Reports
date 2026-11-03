<?php
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Check session authentication
if (!isset($_SESSION['id']) || !isset($_SESSION['username'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Please log in first'
    ]);
    exit;
}

require_once __DIR__ . '/../config/database.php';
$db = getDatabaseConnection();

if (!$db) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

$input = $_POST;
if (empty($input)) {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $jsonInput = json_decode($rawInput, true);
        if (is_array($jsonInput)) {
            $input = $jsonInput;
        }
    }
}

$projectId = trim($input['project_id'] ?? '');
$subtaskId = trim($input['subtask_id'] ?? '');

if ($projectId === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Project ID is required'
    ]);
    exit;
}

if ($subtaskId === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Subtask ID is required'
    ]);
    exit;
}

// Determine agent name from session (realname by default)
$agentName = !empty($_SESSION['real_name']) ? trim($_SESSION['real_name']) : (!empty($_SESSION['full_name']) ? trim($_SESSION['full_name']) : trim($_SESSION['username']));

// Fetch project attributes from projects table
$productName = '';
$productSubCategory = null;
$department = null;
$productIdField = null;
$softwareVersion = null;
$lcdId = null;
$hardwareId = null;
$haspId = null;
$assignedBy = '';
$productType = '';
$receivedFrom = null;

$projectStmt = $db->prepare("SELECT * FROM `projects` WHERE `project_id` = :pid OR `id` = :numeric_id LIMIT 1");
$projectStmt->execute([
    'pid' => $projectId,
    'numeric_id' => is_numeric($projectId) ? (int)$projectId : 0
]);
$project = $projectStmt->fetch();
$canonicalProjectId = $projectId;
$numericProjectId = is_numeric($projectId) ? (int)$projectId : 0;
$clientName = '';
if ($project) {
    if (!empty($project['project_id'])) {
        $canonicalProjectId = $project['project_id'];
    }
    if (!empty($project['id'])) {
        $numericProjectId = (int)$project['id'];
    }
    $productName = !empty($project['product_name']) ? trim($project['product_name']) : '';
    $productSubCategory = !empty($project['product_sub_category']) ? trim($project['product_sub_category']) : null;
    $department = !empty($project['department']) ? trim($project['department']) : null;
    $productIdField = !empty($project['product_serial_no']) ? trim($project['product_serial_no']) : null;
    $softwareVersion = !empty($project['software_version']) ? trim($project['software_version']) : null;
    $lcdId = !empty($project['lcd_id']) ? trim($project['lcd_id']) : null;
    $hardwareId = !empty($project['hardware_id']) ? trim($project['hardware_id']) : null;
    $haspId = !empty($project['hasp_id']) ? trim($project['hasp_id']) : null;

    $assignedBy = $project['assigned_by'] ?? '';
    $productType = $project['category'] ?? '';
    $receivedFrom = !empty($project['date']) ? $project['date'] : null;

    if (!empty($project['client_dealer_name']) && strcasecmp(trim($project['client_dealer_name']), 'NA') !== 0 && strcasecmp(trim($project['client_dealer_name']), 'N/A') !== 0) {
        $clientName = trim($project['client_dealer_name']);
    }
}

// Check if subtask exists and resolve subtask_id string
$subtaskNumericId = is_numeric($subtaskId) ? (int)$subtaskId : 0;
$subtaskStmt = $db->prepare("SELECT id, subtask_id, status FROM `project_subtasks` WHERE `subtask_id` = :sid1 OR `id` = :sid2 LIMIT 1");
$subtaskStmt->execute(['sid1' => $subtaskId, 'sid2' => $subtaskId]);
$subtaskRow = $subtaskStmt->fetch();
$initialStatus = 'Pending';
if ($subtaskRow) {
    if (!empty($subtaskRow['subtask_id'])) {
        $subtaskId = $subtaskRow['subtask_id'];
    }
    $subtaskNumericId = (int)$subtaskRow['id'];
    if (!empty($subtaskRow['status'])) {
        $initialStatus = $subtaskRow['status'];
    }
}

$currentDate = date('Y-m-d');
$currentTime = date('H:i:s');

// If a report was already created for this subtask id on the same day, do not create a new report; return existing record
$checkStmt = $db->prepare("
    SELECT id FROM `project_reports` 
    WHERE (subtask_id = :sid1 OR subtask_id = :sid2)
      AND (project_id = :pid1 OR project_id = :pid2 OR project_id = :pid3)
      AND `date` = :cur_date
    ORDER BY id DESC 
    LIMIT 1
");
$checkStmt->execute([
    'sid1' => $subtaskId,
    'sid2' => (string)$subtaskNumericId,
    'pid1' => $projectId,
    'pid2' => $canonicalProjectId,
    'pid3' => (string)$numericProjectId,
    'cur_date' => $currentDate
]);
$existingReport = $checkStmt->fetch();

if ($existingReport && !empty($existingReport['id'])) {
    echo json_encode([
        'success' => true,
        'report_id' => (int)$existingReport['id'],
        'project_id' => $canonicalProjectId,
        'subtask_id' => $subtaskId,
        'already_exists' => true,
        'message' => 'Report already created for this subtask today. Opening existing report.'
    ]);
    exit;
}

// Insert new report record with project_id and subtask_id
$insertStmt = $db->prepare("
    INSERT INTO `project_reports` 
    (`project_id`, `subtask_id`, `agent_name`, `department`, `date`, `product_name`, `product_sub_category`, `product_type`, `received_from`, `assigned_by`, `product_id`, `software_version`, `lcd_id`, `hardware_id`, `hasp_id`, `client_name`, `start_time`, `status`) 
    VALUES 
    (:project_id, :subtask_id, :agent_name, :department, :date, :product_name, :product_sub_category, :product_type, :received_from, :assigned_by, :product_id, :software_version, :lcd_id, :hardware_id, :hasp_id, :client_name, :start_time, :status)
");

$insertStmt->execute([
    'project_id' => $canonicalProjectId,
    'subtask_id' => $subtaskId,
    'agent_name' => $agentName,
    'department' => $department,
    'date' => $currentDate,
    'product_name' => $productName,
    'product_sub_category' => $productSubCategory,
    'product_type' => $productType,
    'received_from' => $receivedFrom,
    'assigned_by' => $assignedBy,
    'product_id' => $productIdField,
    'software_version' => $softwareVersion,
    'lcd_id' => $lcdId,
    'hardware_id' => $hardwareId,
    'hasp_id' => $haspId,
    'client_name' => $clientName,
    'start_time' => $currentTime,
    'status' => $initialStatus
]);

$reportId = $db->lastInsertId();

echo json_encode([
    'success' => true,
    'report_id' => (int)$reportId,
    'project_id' => $canonicalProjectId,
    'subtask_id' => $subtaskId,
    'already_exists' => false,
    'message' => 'Project report initialized successfully'
]);
