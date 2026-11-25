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

// Check authorization: Admin or User with project_creator permission
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$user_project_creator = isset($_SESSION['project_creator']) ? (int)$_SESSION['project_creator'] : 0;
if ($user_role !== 'admin' && $user_project_creator !== 1) {
    $checkStmt = $db->prepare("SELECT project_creator FROM users WHERE id = :id LIMIT 1");
    $checkStmt->execute(['id' => $_SESSION['id']]);
    $user_project_creator = (int)$checkStmt->fetchColumn();
    $_SESSION['project_creator'] = $user_project_creator;
}
if ($user_role !== 'admin' && $user_project_creator !== 1) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: You do not have permission to create sub projects'
    ]);
    exit;
}

// Support both standard form POST data and JSON payload
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

$project_id = isset($input['project_id']) ? trim($input['project_id']) : '';
$sub_project_name = isset($input['sub_project_name']) ? trim($input['sub_project_name']) : '';
$date = isset($input['project_date']) ? trim($input['project_date']) : (isset($input['date']) ? trim($input['date']) : '');
$category = isset($input['project_category']) ? trim($input['project_category']) : (isset($input['category']) ? trim($input['category']) : '');
$priority = isset($input['project_priority']) ? trim($input['project_priority']) : (isset($input['priority']) ? trim($input['priority']) : '');
$department = isset($input['project_department']) ? trim($input['project_department']) : (isset($input['department']) ? trim($input['department']) : '');
$asignee = isset($input['project_assignee']) ? trim($input['project_assignee']) : (isset($input['asignee']) ? trim($input['asignee']) : '');
$assigned_by = isset($input['project_assigned_by']) ? trim($input['project_assigned_by']) : (isset($input['assigned_by']) ? trim($input['assigned_by']) : '');
$project_status = isset($input['project_status']) ? trim($input['project_status']) : (isset($input['status']) ? trim($input['status']) : 'Assigned');
if (empty($project_status)) {
    $project_status = 'Assigned';
}
$product_name = isset($input['product_name']) ? trim($input['product_name']) : '';
$product_sub_category = isset($input['product_sub_category']) ? trim($input['product_sub_category']) : '';

$client_dealer_specific = isset($input['client_dealer_specific']) ? trim($input['client_dealer_specific']) : 'No';
if ($client_dealer_specific !== 'Yes') {
    $client_dealer_specific = 'No';
    $client_dealer_name = 'NA';
} else {
    $client_dealer_name = isset($input['client_dealer_name']) ? trim($input['client_dealer_name']) : '';
    if (empty($client_dealer_name)) {
        $client_dealer_name = 'NA';
    }
}

$product_serial_no = isset($input['product_serial_no']) ? trim($input['product_serial_no']) : '';
$lcd_id = isset($input['lcd_id']) ? trim($input['lcd_id']) : '';
$hardware_id = isset($input['hardware_id']) ? trim($input['hardware_id']) : '';
$software_version = isset($input['software_version']) ? trim($input['software_version']) : '';
$hasp_id = isset($input['hasp_id']) ? trim($input['hasp_id']) : '';
$project_description = isset($input['project_description']) ? trim($input['project_description']) : '';
$add_to_company_list = !empty($input['add_to_company_list']) ? 1 : 0;

// Validation checks
if (empty($project_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Parent Project is required'
    ]);
    exit;
}

if (empty($sub_project_name)) {
    echo json_encode([
        'success' => false,
        'message' => 'Sub Project Name is required'
    ]);
    exit;
}

if (empty($date)) {
    echo json_encode([
        'success' => false,
        'message' => 'Date is required'
    ]);
    exit;
}

if (empty($department)) {
    echo json_encode([
        'success' => false,
        'message' => 'Department is required'
    ]);
    exit;
}

if (empty($asignee)) {
    echo json_encode([
        'success' => false,
        'message' => 'Assigned To is required'
    ]);
    exit;
}

if (empty($assigned_by)) {
    echo json_encode([
        'success' => false,
        'message' => 'Assigned By is required'
    ]);
    exit;
}

if (empty($category)) {
    echo json_encode([
        'success' => false,
        'message' => 'Category is required'
    ]);
    exit;
}

if (empty($priority)) {
    echo json_encode([
        'success' => false,
        'message' => 'Priority is required'
    ]);
    exit;
}

if (empty($product_name)) {
    echo json_encode([
        'success' => false,
        'message' => 'Product Name is required'
    ]);
    exit;
}

// Verify parent project exists in projects table
$parentStmt = $db->prepare("SELECT id, project_id, project_name FROM `projects` WHERE `project_id` = :pid LIMIT 1");
$parentStmt->execute(['pid' => $project_id]);
$parentRow = $parentStmt->fetch();

if (!$parentRow) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid Parent Project ID'
    ]);
    exit;
}

$parentProjectName = $parentRow['project_name'];
$canonicalProjectId = $parentRow['project_id'];

// Determine created_by from session
$created_by = '';
if (!empty($_SESSION['full_name'])) {
    $created_by = trim($_SESSION['full_name']);
} elseif (!empty($_SESSION['real_name'])) {
    $created_by = trim($_SESSION['real_name']);
} else {
    $created_by = trim($_SESSION['username']);
}

// Generate sequential sub_project_id, e.g. project_id_SP1, project_id_SP2 (XTP-000001_SP1)
$subIdStmt = $db->prepare("
    SELECT sub_project_id 
    FROM `sub_projects` 
    WHERE `project_id` = :pid AND `sub_project_id` LIKE :prefix
    ORDER BY CAST(SUBSTRING_INDEX(sub_project_id, '_SP', -1) AS UNSIGNED) DESC
    LIMIT 1
");
$subIdStmt->execute([
    'pid' => $canonicalProjectId,
    'prefix' => $canonicalProjectId . '_SP%'
]);
$lastRow = $subIdStmt->fetch();
$nextIndex = 1;
if ($lastRow && !empty($lastRow['sub_project_id'])) {
    $parts = explode('_SP', $lastRow['sub_project_id']);
    if (isset($parts[1]) && is_numeric($parts[1])) {
        $nextIndex = ((int)$parts[1]) + 1;
    }
}
$sub_project_id = $canonicalProjectId . '_SP' . $nextIndex;

// Insert into sub_projects table
$stmt = $db->prepare("
    INSERT INTO `sub_projects` (
        `project_id`, `sub_project_id`, `sub_project_name`, `date`, `project_name`,
        `product_name`, `product_sub_category`, `created_by`, `created_at`,
        `category`, `department`, `assigned_by`, `asignee`, `priority`,
        `project_status`, `project_description`, `client_dealer_specific`, `client_dealer_name`,
        `product_serial_no`, `lcd_id`, `hardware_id`, `software_version`, `hasp_id`
    ) VALUES (
        :project_id, :sub_project_id, :sub_project_name, :date, :project_name,
        :product_name, :product_sub_category, :created_by, NOW(),
        :category, :department, :assigned_by, :asignee, :priority,
        :project_status, :project_description, :client_dealer_specific, :client_dealer_name,
        :product_serial_no, :lcd_id, :hardware_id, :software_version, :hasp_id
    )
");

$inserted = $stmt->execute([
    'project_id' => $canonicalProjectId,
    'sub_project_id' => $sub_project_id,
    'sub_project_name' => $sub_project_name,
    'date' => $date,
    'project_name' => $parentProjectName,
    'product_name' => !empty($product_name) ? $product_name : null,
    'product_sub_category' => !empty($product_sub_category) ? $product_sub_category : null,
    'created_by' => $created_by,
    'category' => $category,
    'department' => !empty($department) ? $department : null,
    'assigned_by' => !empty($assigned_by) ? $assigned_by : null,
    'asignee' => !empty($asignee) ? $asignee : null,
    'priority' => $priority,
    'project_status' => $project_status,
    'project_description' => $project_description !== '' ? $project_description : null,
    'client_dealer_specific' => $client_dealer_specific,
    'client_dealer_name' => $client_dealer_name,
    'product_serial_no' => !empty($product_serial_no) ? $product_serial_no : null,
    'lcd_id' => !empty($lcd_id) ? $lcd_id : null,
    'hardware_id' => !empty($hardware_id) ? $hardware_id : null,
    'software_version' => !empty($software_version) ? $software_version : null,
    'hasp_id' => !empty($hasp_id) ? $hasp_id : null
]);

if ($inserted) {
    $insertedId = $db->lastInsertId();

    // If requested, add client/dealer name to company_list
    if ($add_to_company_list && $client_dealer_specific === 'Yes' && !empty($client_dealer_name) && strcasecmp($client_dealer_name, 'NA') !== 0 && strcasecmp($client_dealer_name, 'N/A') !== 0) {
        $compCheck = $db->prepare("SELECT COUNT(*) FROM company_list WHERE LOWER(company_name) = LOWER(:name)");
        $compCheck->execute(['name' => $client_dealer_name]);
        if ($compCheck->fetchColumn() == 0) {
            $insertCompanyStmt = $db->prepare("INSERT INTO company_list (company_name) VALUES (:name)");
            $insertCompanyStmt->execute(['name' => strtoupper($client_dealer_name)]);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Sub project created successfully',
        'id' => $insertedId,
        'project_id' => $canonicalProjectId,
        'sub_project_id' => $sub_project_id,
        'sub_project_name' => $sub_project_name
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create sub project'
    ]);
}

