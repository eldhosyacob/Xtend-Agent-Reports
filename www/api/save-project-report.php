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

$reportId = trim($_POST['id'] ?? $_POST['report_id'] ?? '');
if ($reportId === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Report ID is required'
    ]);
    exit;
}

// Verify that report exists
$checkStmt = $db->prepare("SELECT id, project_id, subtask_id, start_time FROM `project_reports` WHERE `id` = :id LIMIT 1");
$checkStmt->execute(['id' => $reportId]);
$existing = $checkStmt->fetch();

if (!$existing) {
    echo json_encode([
        'success' => false,
        'message' => 'Report record not found'
    ]);
    exit;
}

// Extract form data
$agentName = trim($_POST['agent_name'] ?? '');
$department = trim($_POST['department'] ?? '');
$date = trim($_POST['date'] ?? date('Y-m-d'));
$productName = trim($_POST['product_name'] ?? '');
$productSubCategory = trim($_POST['product_sub_category'] ?? '');
$productType = trim($_POST['product_type'] ?? '');
$assignedBy = trim($_POST['assigned_by'] ?? '');
$productId = trim($_POST['product_id'] ?? '');
$softwareVersion = trim($_POST['software_version'] ?? '');
$lcdId = trim($_POST['lcd_id'] ?? '');
$hardwareId = trim($_POST['hardware_id'] ?? '');
$haspId = trim($_POST['hasp_id'] ?? '');
$clientName = trim($_POST['client_name'] ?? '');
$youtrackId = trim($_POST['youtrack_id'] ?? '');
$receivedFrom = !empty($_POST['received_from']) ? trim($_POST['received_from']) : null;
$sentTo = !empty($_POST['sent_to']) ? trim($_POST['sent_to']) : null;
$testingDescription = trim($_POST['testing_description'] ?? '');
$status = trim($_POST['status'] ?? 'Pending');

// End time and total time
$endTime = trim($_POST['end_time'] ?? date('H:i:s'));
$totalTime = trim($_POST['total_time'] ?? '');
if ($totalTime === '' && !empty($existing['start_time'])) {
    $startSec = strtotime($existing['start_time']);
    $endSec = strtotime($endTime);
    if ($startSec !== false && $endSec !== false && $endSec >= $startSec) {
        $diff = $endSec - $startSec;
        $totalTime = sprintf('%02d:%02d:%02d', floor($diff / 3600), floor(($diff % 3600) / 60), $diff % 60);
    }
}

$updateStmt = $db->prepare("
    UPDATE `project_reports` SET
        `agent_name` = :agent_name,
        `department` = :department,
        `date` = :date,
        `product_name` = :product_name,
        `product_sub_category` = :product_sub_category,
        `product_type` = :product_type,
        `assigned_by` = :assigned_by,
        `product_id` = :product_id,
        `software_version` = :software_version,
        `lcd_id` = :lcd_id,
        `hardware_id` = :hardware_id,
        `hasp_id` = :hasp_id,
        `client_name` = :client_name,
        `youtrack_id` = :youtrack_id,
        `received_from` = :received_from,
        `sent_to` = :sent_to,
        `testing_description` = :testing_description,
        `end_time` = :end_time,
        `total_time` = :total_time,
        `status` = :status
    WHERE `id` = :id
");

$updateStmt->execute([
    'agent_name' => $agentName,
    'department' => $department !== '' ? $department : null,
    'date' => $date,
    'product_name' => $productName,
    'product_sub_category' => $productSubCategory !== '' ? $productSubCategory : null,
    'product_type' => $productType,
    'assigned_by' => $assignedBy,
    'product_id' => $productId !== '' ? $productId : null,
    'software_version' => $softwareVersion !== '' ? $softwareVersion : null,
    'lcd_id' => $lcdId !== '' ? $lcdId : null,
    'hardware_id' => $hardwareId !== '' ? $hardwareId : null,
    'hasp_id' => $haspId !== '' ? $haspId : null,
    'client_name' => $clientName,
    'youtrack_id' => $youtrackId,
    'received_from' => $receivedFrom,
    'sent_to' => $sentTo,
    'testing_description' => $testingDescription,
    'end_time' => $endTime,
    'total_time' => $totalTime,
    'status' => $status,
    'id' => $reportId
]);

// If client name is provided and not in company_list, add it
if (!empty($clientName)) {
    $compCheck = $db->prepare("SELECT COUNT(*) FROM company_list WHERE LOWER(company_name) = LOWER(:name)");
    $compCheck->execute(['name' => $clientName]);
    if ($compCheck->fetchColumn() == 0) {
        $compInsert = $db->prepare("INSERT INTO company_list (company_name) VALUES (:name)");
        $compInsert->execute(['name' => strtoupper($clientName)]);
    }
}

// Update corresponding subtask status and all reports with that subtask_id
if (!empty($status) && !empty($existing['subtask_id'])) {
    $completedAt = (strcasecmp($status, 'Completed') === 0) ? date('Y-m-d H:i:s') : null;
    $subtaskNumericId = is_numeric($existing['subtask_id']) ? (int)$existing['subtask_id'] : 0;

    $stStmt = $db->prepare("SELECT id, subtask_id, project_id FROM `project_subtasks` WHERE `subtask_id` = :sid1 OR `id` = :sid2 LIMIT 1");
    $stStmt->execute([
        'sid1' => $existing['subtask_id'],
        'sid2' => $subtaskNumericId
    ]);
    $stRow = $stStmt->fetch(PDO::FETCH_ASSOC);

    $subtaskIdsToMatch = array_unique(array_filter([
        $existing['subtask_id'],
        $stRow['subtask_id'] ?? null,
        isset($stRow['id']) ? (string)$stRow['id'] : null
    ]));

    $subtaskUpdate = $db->prepare("UPDATE `project_subtasks` SET `status` = :status, `completed_at` = :completed_at WHERE `subtask_id` = :sid1 OR `id` = :sid2");
    $subtaskUpdate->execute([
        'status' => $status,
        'completed_at' => $completedAt,
        'sid1' => $existing['subtask_id'],
        'sid2' => $subtaskNumericId
    ]);

    // Update status of all reports with that subtask_id to the latest status
    $inPlaceholders = implode(',', array_fill(0, count($subtaskIdsToMatch), '?'));
    $allReportsParams = array_merge([$status], array_values($subtaskIdsToMatch));

    $projectMatchSql = "";
    $targetProjectId = !empty($existing['project_id']) ? $existing['project_id'] : ($stRow['project_id'] ?? '');
    if (!empty($targetProjectId)) {
        $projectMatchSql = " AND (`project_id` = ? OR `project_id` IS NULL OR `project_id` = '')";
        $allReportsParams[] = $targetProjectId;
    }

    $allReportsStmt = $db->prepare("
        UPDATE `project_reports` 
        SET `status` = ? 
        WHERE `subtask_id` IN ($inPlaceholders) $projectMatchSql
    ");
    $allReportsStmt->execute($allReportsParams);
}

// Handle uploaded files if provided
$uploadedFiles = [];
if (!empty($_FILES['uploaded_files'])) {
    $uploadDir = __DIR__ . '/../file-uploads/project/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $files = $_FILES['uploaded_files'];
    $fileCount = is_array($files['name']) ? count($files['name']) : 0;

    if ($fileCount > 4) {
        echo json_encode([
            'success' => false,
            'message' => 'Maximum 4 files can be uploaded at a time.'
        ]);
        exit;
    }

    $totalSize = is_array($files['size']) ? array_sum($files['size']) : 0;
    if ($totalSize > 10 * 1024 * 1024) {
        echo json_encode([
            'success' => false,
            'message' => 'Total size of attachments cannot exceed 10MB.'
        ]);
        exit;
    }

    $cleanProjectId = preg_replace('/[^a-zA-Z0-9_-]/', '_', !empty($existing['project_id']) ? $existing['project_id'] : ($_POST['project_id'] ?? 'PROJ'));
    $cleanReportId = (int)$reportId;

    for ($i = 0; $i < $fileCount; $i++) {
        if (isset($files['error'][$i]) && $files['error'][$i] === UPLOAD_ERR_OK) {
            $tmpName = $files['tmp_name'][$i];
            $origName = basename($files['name'][$i]);
            $cleanName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $origName);
            // Filename includes project_id: {$cleanProjectId}_{$cleanReportId}_{uniqid}_{$cleanName}
            $uniqueFilename = $cleanProjectId . '_' . $cleanReportId . '_' . uniqid() . '_' . $cleanName;
            $destPath = $uploadDir . $uniqueFilename;
            if (move_uploaded_file($tmpName, $destPath)) {
                $uploadedFiles[] = $uniqueFilename;
            }
        }
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Project report saved successfully!',
    'data' => [
        'report_id' => $reportId,
        'project_id' => $existing['project_id'],
        'subtask_id' => $existing['subtask_id'],
        'uploaded_files' => $uploadedFiles
    ]
]);
?>
