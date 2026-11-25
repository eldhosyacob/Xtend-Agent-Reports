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

$projectId = isset($input['project_id']) ? trim($input['project_id']) : '';
$filename = isset($input['filename']) ? trim($input['filename']) : '';

if (empty($projectId) || empty($filename)) {
    echo json_encode([
        'success' => false,
        'message' => 'Project ID and filename are required'
    ]);
    exit;
}

// Ensure filename does not contain directory traversal characters
$baseFilename = basename($filename);
if ($baseFilename !== $filename || strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid attachment filename'
    ]);
    exit;
}

// Fetch project record by project_id or numeric id to ensure validity
$projectStmt = $db->prepare("
    SELECT id, project_id 
    FROM `projects` 
    WHERE project_id = :project_id OR id = :numeric_id
    LIMIT 1
");

$projectStmt->execute([
    'project_id' => $projectId,
    'numeric_id' => is_numeric($projectId) ? (int)$projectId : 0
]);

$project = $projectStmt->fetch();

if (!$project) {
    // Check in sub_projects table if this is a subproject
    $spStmt = $db->prepare("SELECT id, project_id, sub_project_id FROM `sub_projects` WHERE sub_project_id = :spid OR id = :numeric_id LIMIT 1");
    $spStmt->execute([
        'spid' => $projectId,
        'numeric_id' => is_numeric($projectId) ? (int)$projectId : 0
    ]);
    $spRow = $spStmt->fetch();
    if ($spRow) {
        $project = [
            'id' => $spRow['id'],
            'project_id' => $spRow['sub_project_id'],
            'parent_project_id' => $spRow['project_id'],
            'is_sub_project' => true
        ];
    }
}

if (!$project) {
    echo json_encode([
        'success' => false,
        'message' => 'Project not found'
    ]);
    exit;
}

// Verify that the attachment file belongs to this project
$cleanCanonicalPid = preg_replace('/[^a-zA-Z0-9_-]/', '_', $project['project_id'] ?? '');
$cleanNumPid = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($project['id'] ?? ''));

$isMatchingProject = false;
if (!empty($cleanCanonicalPid) && strpos($baseFilename, $cleanCanonicalPid . '_') === 0) {
    $isMatchingProject = true;
} elseif (!empty($cleanNumPid) && strpos($baseFilename, $cleanNumPid . '_') === 0) {
    $isMatchingProject = true;
} elseif (!empty($project['parent_project_id'])) {
    $cleanParentPid = preg_replace('/[^a-zA-Z0-9_-]/', '_', $project['parent_project_id']);
    if (strpos($baseFilename, $cleanParentPid . '_') === 0) {
        $afterParent = substr($baseFilename, strlen($cleanParentPid . '_'));
        if (preg_match('/^(\d+)_/', $afterParent, $rm)) {
            $repId = (int)$rm[1];
            $checkRepStmt = $db->prepare("
                SELECT id FROM `project_reports` 
                WHERE id = :rid AND (
                    project_id = :spid 
                    OR subtask_id LIKE :sp_prefix 
                    OR subtask_id IN (SELECT pst.subtask_id FROM `project_subtasks` pst WHERE pst.sub_project_id = :spid2 OR pst.project_id = :spid3)
                ) LIMIT 1
            ");
            $checkRepStmt->execute([
                'rid' => $repId,
                'spid' => $project['project_id'],
                'sp_prefix' => $project['project_id'] . '_%',
                'spid2' => $project['project_id'],
                'spid3' => $project['project_id']
            ]);
            if ($checkRepStmt->fetch()) {
                $isMatchingProject = true;
            }
        }
    }
}

if (!$isMatchingProject) {
    echo json_encode([
        'success' => false,
        'message' => 'Attachment does not belong to the specified project'
    ]);
    exit;
}

$projectUploadDir = realpath(__DIR__ . '/../file-uploads/project');
$projectRelatedUploadDir = realpath(__DIR__ . '/../file-uploads/project/projectRelated');

$targetFilePath = '';
$matchedDir = '';
if ($projectUploadDir && is_file($projectUploadDir . DIRECTORY_SEPARATOR . $baseFilename)) {
    $targetFilePath = $projectUploadDir . DIRECTORY_SEPARATOR . $baseFilename;
    $matchedDir = $projectUploadDir;
} elseif ($projectRelatedUploadDir && is_file($projectRelatedUploadDir . DIRECTORY_SEPARATOR . $baseFilename)) {
    $targetFilePath = $projectRelatedUploadDir . DIRECTORY_SEPARATOR . $baseFilename;
    $matchedDir = $projectRelatedUploadDir;
}

if (empty($targetFilePath) || !file_exists($targetFilePath) || !is_file($targetFilePath)) {
    echo json_encode([
        'success' => false,
        'message' => 'Attachment file not found'
    ]);
    exit;
}

// Security: Verify target file is strictly inside an allowed upload directory
$realTargetDir = dirname(realpath($targetFilePath));
if ($realTargetDir !== $matchedDir) {
    echo json_encode([
        'success' => false,
        'message' => 'Access denied: Invalid file location'
    ]);
    exit;
}

// For project related documents, only admin users are authorized to delete
$userRole = strtolower(trim($_SESSION['role'] ?? ($_SESSION['user_type'] ?? '')));
$isAdmin = ($userRole === 'admin');
if ($matchedDir === $projectRelatedUploadDir && !$isAdmin) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Only administrators can delete project related documents'
    ]);
    exit;
}

if (!unlink($targetFilePath)) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete attachment from server'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Attachment deleted successfully'
]);

