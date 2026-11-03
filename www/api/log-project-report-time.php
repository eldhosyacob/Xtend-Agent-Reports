<?php
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

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
        'message' => 'Missing report_id'
    ]);
    exit;
}

// Verify that report exists
$checkStmt = $db->prepare("SELECT id, project_id, subtask_id FROM `project_reports` WHERE `id` = :id LIMIT 1");
$checkStmt->execute(['id' => $reportId]);
$existing = $checkStmt->fetch();

if (!$existing) {
    echo json_encode([
        'success' => false,
        'message' => 'Report not found'
    ]);
    exit;
}

$endTime = trim($_POST['end_time'] ?? date('H:i:s'));
$totalTime = trim($_POST['total_time'] ?? '');

$updatableFields = [
    'agent_name',
    'department',
    'date',
    'product_name',
    'product_sub_category',
    'product_type',
    'assigned_by',
    'product_id',
    'software_version',
    'lcd_id',
    'hardware_id',
    'hasp_id',
    'client_name',
    'youtrack_id',
    'received_from',
    'sent_to',
    'testing_description',
    'status'
];

$fieldUpdates = [];
$queryParams = [
    'end_time' => $endTime,
    'total_time' => $totalTime !== '' ? $totalTime : null,
    'id' => $reportId
];

foreach ($updatableFields as $field) {
    if (array_key_exists($field, $_POST)) {
        $val = trim((string)$_POST[$field]);
        if ($field === 'received_from' || $field === 'sent_to') {
            $queryParams[$field] = !empty($val) ? $val : null;
            $fieldUpdates[] = "`$field` = :$field";
        } elseif ($field === 'status') {
            if (!empty($val)) {
                $queryParams[$field] = $val;
                $fieldUpdates[] = "`$field` = :$field";
            }
        } else {
            $queryParams[$field] = $val !== '' ? $val : null;
            $fieldUpdates[] = "`$field` = :$field";
        }
    }
}

$fieldSql = !empty($fieldUpdates) ? implode(', ', $fieldUpdates) . ', ' : '';

$stmt = $db->prepare("
    UPDATE `project_reports` SET
        {$fieldSql}
        `end_time` = :end_time,
        `total_time` = COALESCE(:total_time, `total_time`)
    WHERE `id` = :id
");

$stmt->execute($queryParams);

// If client name is provided and not in company_list, add it
if (!empty($queryParams['client_name'])) {
    $clientName = $queryParams['client_name'];
    $compCheck = $db->prepare("SELECT COUNT(*) FROM company_list WHERE LOWER(company_name) = LOWER(:name)");
    $compCheck->execute(['name' => $clientName]);
    if ($compCheck->fetchColumn() == 0) {
        $compInsert = $db->prepare("INSERT INTO company_list (company_name) VALUES (:name)");
        $compInsert->execute(['name' => strtoupper($clientName)]);
    }
}

// Update corresponding subtask status and all reports with that subtask_id
if (isset($queryParams['status']) && !empty($queryParams['status']) && !empty($existing['subtask_id'])) {
    $repStatus = $queryParams['status'];
    $completedAt = (strcasecmp($repStatus, 'Completed') === 0) ? date('Y-m-d H:i:s') : null;
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
        'status' => $repStatus,
        'completed_at' => $completedAt,
        'sid1' => $existing['subtask_id'],
        'sid2' => $subtaskNumericId
    ]);

    // Update status of all reports with that subtask_id to the latest status
    $inPlaceholders = implode(',', array_fill(0, count($subtaskIdsToMatch), '?'));
    $allReportsParams = array_merge([$repStatus], array_values($subtaskIdsToMatch));

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

echo json_encode([
    'success' => true,
    'message' => 'Project report time and fields logged successfully',
    'status' => $queryParams['status'] ?? null
]);
