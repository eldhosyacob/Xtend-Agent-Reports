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
}

if (!$isMatchingProject) {
    echo json_encode([
        'success' => false,
        'message' => 'Attachment does not belong to the specified project'
    ]);
    exit;
}

$projectUploadDir = realpath(__DIR__ . '/../file-uploads/project');
if (!$projectUploadDir || !is_dir($projectUploadDir)) {
    echo json_encode([
        'success' => false,
        'message' => 'Attachments directory not found'
    ]);
    exit;
}

$targetFilePath = $projectUploadDir . DIRECTORY_SEPARATOR . $baseFilename;

if (!file_exists($targetFilePath) || !is_file($targetFilePath)) {
    echo json_encode([
        'success' => false,
        'message' => 'Attachment file not found'
    ]);
    exit;
}

// Security: Verify target file is strictly inside upload directory
$realTargetDir = dirname(realpath($targetFilePath));
if ($realTargetDir !== $projectUploadDir) {
    echo json_encode([
        'success' => false,
        'message' => 'Access denied: Invalid file location'
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

