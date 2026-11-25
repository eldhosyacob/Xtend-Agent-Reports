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
        'message' => 'Unauthorized: You do not have permission to edit projects'
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

$projectId = isset($input['project_id']) ? trim($input['project_id']) : '';
$numericId = isset($input['id']) ? (int)$input['id'] : 0;

if (empty($projectId) && empty($numericId)) {
    echo json_encode([
        'success' => false,
        'message' => 'Project ID is required'
    ]);
    exit;
}

// Fetch existing project record
$checkProjStmt = $db->prepare("
    SELECT id, project_id, project_status 
    FROM `projects` 
    WHERE project_id = :project_id OR id = :numeric_id 
    LIMIT 1
");
$checkProjStmt->execute([
    'project_id' => $projectId,
    'numeric_id' => $numericId
]);
$existingProject = $checkProjStmt->fetch();

if (!$existingProject) {
    echo json_encode([
        'success' => false,
        'message' => 'Project not found'
    ]);
    exit;
}

$canonicalPid = $existingProject['project_id'];
$numericPid = (int)$existingProject['id'];

// Check project status: only 'Assigned' projects can be edited
if (strcasecmp(trim($existingProject['project_status']), 'Assigned') !== 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Only projects with status "Assigned" can be edited'
    ]);
    exit;
}

$date = isset($input['project_date']) ? trim($input['project_date']) : (isset($input['date']) ? trim($input['date']) : '');
$project_name = isset($input['project_name']) ? trim($input['project_name']) : '';
$category = isset($input['project_category']) ? trim($input['project_category']) : (isset($input['category']) ? trim($input['category']) : '');
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

if (empty($product_name)) {
    echo json_encode([
        'success' => false,
        'message' => 'Product Name is required'
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

    if (!empty($validDocIndices)) {
        // Count existing documents in file-uploads/project/projectRelated/
        $uploadDir = __DIR__ . '/../file-uploads/project/projectRelated/';
        $existingDocCount = 0;
        if (is_dir($uploadDir)) {
            $cleanCanonicalPid = preg_replace('/[^a-zA-Z0-9_-]/', '_', $canonicalPid);
            $cleanNumPid = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)$numericPid);
            $files = scandir($uploadDir);
            if ($files !== false) {
                foreach ($files as $f) {
                    if ($f === '.' || $f === '..') continue;
                    if ((!empty($cleanCanonicalPid) && strpos($f, $cleanCanonicalPid . '_') === 0) ||
                        (!empty($cleanNumPid) && strpos($f, $cleanNumPid . '_') === 0)) {
                        $existingDocCount++;
                    }
                }
            }
        }

        if (($existingDocCount + count($validDocIndices)) > 2) {
            echo json_encode([
                'success' => false,
                'message' => 'Maximum 2 documents allowed per project. Currently has ' . $existingDocCount . ' document(s).'
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
}

// Determine edited_by from session
$edited_by = '';
if (!empty($_SESSION['full_name'])) {
    $edited_by = trim($_SESSION['full_name']);
} elseif (!empty($_SESSION['real_name'])) {
    $edited_by = trim($_SESSION['real_name']);
} else {
    $edited_by = trim($_SESSION['username']);
}

// Execute update query
$updateSql = "
    UPDATE `projects` SET 
        `date` = :date,
        `project_name` = :project_name,
        `category` = :category,
        `priority` = :priority,
        `project_status` = :project_status,
        `client_dealer_specific` = :client_dealer_specific,
        `client_dealer_name` = :client_dealer_name,
        `product_name` = :product_name,
        `product_sub_category` = :product_sub_category,
        `product_serial_no` = :product_serial_no,
        `lcd_id` = :lcd_id,
        `hardware_id` = :hardware_id,
        `software_version` = :software_version,
        `hasp_id` = :hasp_id,
        `project_description` = :project_description,
        `edited_by` = :edited_by,
        `edited_at` = NOW()
    WHERE `id` = :numeric_id OR `project_id` = :project_id
";

$updateStmt = $db->prepare($updateSql);
$updated = $updateStmt->execute([
    'date' => $date,
    'project_name' => $project_name,
    'category' => $category,
    'priority' => $priority,
    'project_status' => $project_status,
    'client_dealer_specific' => $client_dealer_specific,
    'client_dealer_name' => $client_dealer_name,
    'product_name' => $product_name,
    'product_sub_category' => $product_sub_category !== '' ? $product_sub_category : null,
    'product_serial_no' => $product_serial_no !== '' ? $product_serial_no : null,
    'lcd_id' => $lcd_id !== '' ? $lcd_id : null,
    'hardware_id' => $hardware_id !== '' ? $hardware_id : null,
    'software_version' => $software_version !== '' ? $software_version : null,
    'hasp_id' => $hasp_id !== '' ? $hasp_id : null,
    'project_description' => $project_description !== '' ? $project_description : null,
    'edited_by' => $edited_by,
    'numeric_id' => $numericPid,
    'project_id' => $canonicalPid
]);

if ($updated) {
    // Save uploaded project documents to file-uploads/project/projectRelated/
    $uploadedFiles = [];
    if (!empty($validDocIndices) && !empty($_FILES['uploaded_documents'])) {
        $uploadDir = __DIR__ . '/../file-uploads/project/projectRelated/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $cleanCanonicalPid = preg_replace('/[^a-zA-Z0-9_-]/', '_', $canonicalPid);
        $docs = $_FILES['uploaded_documents'];

        foreach ($validDocIndices as $idx) {
            $tmpName = $docs['tmp_name'][$idx];
            $origName = basename($docs['name'][$idx]);
            $cleanName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $origName);
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
        'message' => 'Project details updated successfully',
        'project_id' => $canonicalPid,
        'uploaded_documents' => $uploadedFiles
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update project details'
    ]);
}

