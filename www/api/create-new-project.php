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
        'message' => 'Unauthorized: You do not have permission to create projects'
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

$date = isset($input['project_date']) ? trim($input['project_date']) : (isset($input['date']) ? trim($input['date']) : '');
$project_name = isset($input['project_name']) ? trim($input['project_name']) : '';
$category = isset($input['project_category']) ? trim($input['project_category']) : (isset($input['category']) ? trim($input['category']) : '');
$assigned_by = isset($input['project_assigned_by']) ? trim($input['project_assigned_by']) : (isset($input['assigned_by']) ? trim($input['assigned_by']) : '');
$asignee = isset($input['project_assignee']) ? trim($input['project_assignee']) : (isset($input['asignee']) ? trim($input['asignee']) : '');
$department = isset($input['project_department']) ? trim($input['project_department']) : (isset($input['department']) ? trim($input['department']) : '');
$priority = isset($input['project_priority']) ? trim($input['project_priority']) : (isset($input['priority']) ? trim($input['priority']) : '');
$project_status = isset($input['project_status']) ? trim($input['project_status']) : (isset($input['status']) ? trim($input['status']) : 'Assigned');
if (empty($project_status)) {
    $project_status = 'Assigned';
}
$project_description = isset($input['project_description']) ? trim($input['project_description']) : '';
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

$product_name = isset($input['product_name']) ? trim($input['product_name']) : '';
$product_sub_category = isset($input['product_sub_category']) ? trim($input['product_sub_category']) : '';

// Additional Hardware Details (optional)
$product_serial_no = isset($input['product_serial_no']) ? trim($input['product_serial_no']) : '';
$lcd_id = isset($input['lcd_id']) ? trim($input['lcd_id']) : '';
$hardware_id = isset($input['hardware_id']) ? trim($input['hardware_id']) : '';
$software_version = isset($input['software_version']) ? trim($input['software_version']) : '';
$hasp_id = isset($input['hasp_id']) ? trim($input['hasp_id']) : '';

$add_to_company_list = !empty($input['add_to_company_list']) ? 1 : 0;

// Validation checks
if (empty($date)) {
    echo json_encode([
        'success' => false,
        'message' => 'Date is required'
    ]);
    exit;
}

if (empty($project_name)) {
    echo json_encode([
        'success' => false,
        'message' => 'Project Name is required'
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

// Validate uploaded documents if present
$validDocIndices = [];
if (!empty($_FILES['uploaded_documents']) && !empty($_FILES['uploaded_documents']['name'])) {
    $docs = $_FILES['uploaded_documents'];
    $docCount = is_array($docs['name']) ? count($docs['name']) : 0;
    $totalDocSize = 0;

    for ($i = 0; $i < $docCount; $i++) {
        $err = isset($docs['error'][$i]) ? $docs['error'][$i] : UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_OK) {
            $validDocIndices[] = $i;
            $totalDocSize += isset($docs['size'][$i]) ? (int)$docs['size'][$i] : 0;
        } elseif ($err !== UPLOAD_ERR_NO_FILE) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to upload document: Error code ' . $err
            ]);
            exit;
        }
    }

    if (count($validDocIndices) > 2) {
        echo json_encode([
            'success' => false,
            'message' => 'Maximum 2 documents can be uploaded.'
        ]);
        exit;
    }

    if ($totalDocSize > 20 * 1024 * 1024) {
        echo json_encode([
            'success' => false,
            'message' => 'Total size of documents cannot exceed 20MB.'
        ]);
        exit;
    }

    $allowedExtensions = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'txt'];
    foreach ($validDocIndices as $idx) {
        $origName = basename($docs['name'][$idx]);
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid file format for document "' . htmlspecialchars($origName) . '". Allowed formats: ' . implode(', ', $allowedExtensions)
            ]);
            exit;
        }
    }
}

// Determine created_by from session
$created_by = '';
if (!empty($_SESSION['full_name'])) {
    $created_by = trim($_SESSION['full_name']);
} elseif (!empty($_SESSION['real_name'])) {
    $created_by = trim($_SESSION['real_name']);
} else {
    $created_by = trim($_SESSION['username']);
}

// Generate sequential project_id starting from XTP-000001
$idStmt = $db->query("
    SELECT MAX(CAST(SUBSTRING(project_id, 5) AS UNSIGNED)) AS max_num 
    FROM `projects` 
    WHERE `project_id` REGEXP '^XTP-[0-9]+$'
");
$idRow = $idStmt->fetch();
$nextNum = ($idRow && $idRow['max_num'] !== null) ? ((int)$idRow['max_num'] + 1) : 1;
$project_id = sprintf('XTP-%06d', $nextNum);

// Check available columns in projects table
$existingCols = [];
$colsStmt = $db->query("SHOW COLUMNS FROM `projects`");
if ($colsStmt) {
    while ($colRow = $colsStmt->fetch(PDO::FETCH_ASSOC)) {
        $existingCols[] = $colRow['Field'];
    }
}

// Build dynamic insert columns and parameters based on existing schema
$insertCols = ['project_id', 'date', 'project_name', 'created_by', 'created_at', 'category', 'assigned_by', 'asignee', 'priority', 'project_status', 'project_description', 'client_dealer_specific', 'client_dealer_name'];
$insertValues = [':project_id', ':date', ':project_name', ':created_by', 'NOW()', ':category', ':assigned_by', ':asignee', ':priority', ':project_status', ':project_description', ':client_dealer_specific', ':client_dealer_name'];
$executeParams = [
    'project_id' => $project_id,
    'date' => $date,
    'project_name' => $project_name,
    'created_by' => $created_by,
    'category' => $category,
    'assigned_by' => !empty($assigned_by) ? $assigned_by : null,
    'asignee' => !empty($asignee) ? $asignee : null,
    'priority' => $priority,
    'project_status' => $project_status,
    'project_description' => $project_description !== '' ? $project_description : null,
    'client_dealer_specific' => $client_dealer_specific,
    'client_dealer_name' => $client_dealer_name
];

if (in_array('department', $existingCols)) {
    $insertCols[] = 'department';
    $insertValues[] = ':department';
    $executeParams['department'] = !empty($department) ? $department : null;
}
if (in_array('product_name', $existingCols)) {
    $insertCols[] = 'product_name';
    $insertValues[] = ':product_name';
    $executeParams['product_name'] = !empty($product_name) ? $product_name : null;
}
if (in_array('product_sub_category', $existingCols)) {
    $insertCols[] = 'product_sub_category';
    $insertValues[] = ':product_sub_category';
    $executeParams['product_sub_category'] = !empty($product_sub_category) ? $product_sub_category : null;
}
if (in_array('product_serial_no', $existingCols)) {
    $insertCols[] = 'product_serial_no';
    $insertValues[] = ':product_serial_no';
    $executeParams['product_serial_no'] = !empty($product_serial_no) ? $product_serial_no : null;
}
if (in_array('lcd_id', $existingCols)) {
    $insertCols[] = 'lcd_id';
    $insertValues[] = ':lcd_id';
    $executeParams['lcd_id'] = !empty($lcd_id) ? $lcd_id : null;
}
if (in_array('hardware_id', $existingCols)) {
    $insertCols[] = 'hardware_id';
    $insertValues[] = ':hardware_id';
    $executeParams['hardware_id'] = !empty($hardware_id) ? $hardware_id : null;
}
if (in_array('software_version', $existingCols)) {
    $insertCols[] = 'software_version';
    $insertValues[] = ':software_version';
    $executeParams['software_version'] = !empty($software_version) ? $software_version : null;
}
if (in_array('hasp_id', $existingCols)) {
    $insertCols[] = 'hasp_id';
    $insertValues[] = ':hasp_id';
    $executeParams['hasp_id'] = !empty($hasp_id) ? $hasp_id : null;
}

$colsSql = '`' . implode('`, `', $insertCols) . '`';
$valsSql = implode(', ', $insertValues);

$stmt = $db->prepare("INSERT INTO `projects` ({$colsSql}) VALUES ({$valsSql})");
$inserted = $stmt->execute($executeParams);

if ($inserted) {
    // Save uploaded project documents to file-uploads/project/projectRelated/
    $uploadedFiles = [];
    if (!empty($validDocIndices) && !empty($_FILES['uploaded_documents'])) {
        $uploadDir = __DIR__ . '/../file-uploads/project/projectRelated/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $cleanCanonicalPid = preg_replace('/[^a-zA-Z0-9_-]/', '_', $project_id);
        $docs = $_FILES['uploaded_documents'];

        foreach ($validDocIndices as $idx) {
            $tmpName = $docs['tmp_name'][$idx];
            $origName = basename($docs['name'][$idx]);
            $cleanName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $origName);
            // Filename format: {$cleanCanonicalPid}_{uniqid}_{$cleanName}
            $uniqueFilename = $cleanCanonicalPid . '_' . uniqid() . '_' . $cleanName;
            $destPath = $uploadDir . $uniqueFilename;
            if (move_uploaded_file($tmpName, $destPath)) {
                $uploadedFiles[] = $uniqueFilename;
            }
        }
    }

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
        'message' => 'Project created successfully',
        'id' => $db->lastInsertId(),
        'project_id' => $project_id,
        'uploaded_documents' => $uploadedFiles
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create project'
    ]);
}
