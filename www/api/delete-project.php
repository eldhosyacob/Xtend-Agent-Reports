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

// Only admin users are authorized to delete projects
$userRole = strtolower(trim($_SESSION['role'] ?? ''));
$isUserAdmin = ($userRole === 'admin' || strtolower(trim($_SESSION['username'] ?? '')) === 'admin');
if (!$isUserAdmin) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Only administrator users can delete projects'
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
$numericId = isset($input['id']) ? trim($input['id']) : '';

if (empty($projectId) && empty($numericId)) {
    echo json_encode([
        'success' => false,
        'message' => 'Project ID is required'
    ]);
    exit;
}

// Fetch project record
$checkStmt = $db->prepare("
    SELECT id, project_id, project_name, project_status 
    FROM `projects` 
    WHERE project_id = :pid OR id = :numeric_id 
    LIMIT 1
");

$checkStmt->execute([
    'pid' => !empty($projectId) ? $projectId : $numericId,
    'numeric_id' => is_numeric($numericId) ? (int)$numericId : (is_numeric($projectId) ? (int)$projectId : 0)
]);

$project = $checkStmt->fetch();

if (!$project) {
    echo json_encode([
        'success' => false,
        'message' => 'Project not found'
    ]);
    exit;
}

// Restrict deletion to projects where status = 'Assigned'
if (strcasecmp(trim($project['project_status']), 'Assigned') !== 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Only projects with status "Assigned" can be deleted'
    ]);
    exit;
}

// Delete related subtasks if any
$delSubtasksStmt = $db->prepare("DELETE FROM `project_subtasks` WHERE project_id = :canonical_pid OR project_id = :num_id");
$delSubtasksStmt->execute([
    'canonical_pid' => $project['project_id'],
    'num_id' => (string)$project['id']
]);

// Delete related reports if any
$delReportsStmt = $db->prepare("DELETE FROM `project_reports` WHERE project_id = :canonical_pid OR project_id = :num_id");
$delReportsStmt->execute([
    'canonical_pid' => $project['project_id'],
    'num_id' => (string)$project['id']
]);

// Delete associated physical upload files if any
$projectUploadDir = realpath(__DIR__ . '/../file-uploads/project');
if ($projectUploadDir && is_dir($projectUploadDir)) {
    $cleanCanonicalPid = preg_replace('/[^a-zA-Z0-9_-]/', '_', $project['project_id']);
    $cleanNumPid = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)$project['id']);
    $files = scandir($projectUploadDir);
    if ($files !== false) {
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if ((!empty($cleanCanonicalPid) && strpos($file, $cleanCanonicalPid . '_') === 0) ||
                (!empty($cleanNumPid) && strpos($file, $cleanNumPid . '_') === 0)) {
                $filePath = $projectUploadDir . DIRECTORY_SEPARATOR . $file;
                if (is_file($filePath)) {
                    @unlink($filePath);
                }
            }
        }
    }
}

// Delete project
$delProjectStmt = $db->prepare("DELETE FROM `projects` WHERE id = :id");
$deleted = $delProjectStmt->execute(['id' => $project['id']]);

if ($deleted) {
    echo json_encode([
        'success' => true,
        'message' => 'Project deleted successfully',
        'project_id' => $project['project_id'],
        'project_name' => $project['project_name']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete project'
    ]);
}

