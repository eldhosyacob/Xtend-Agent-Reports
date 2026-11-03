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

// Only admin users are authorized to update project status
$userRole = strtolower(trim($_SESSION['role'] ?? ''));
if ($userRole !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Only administrator users can update project status'
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
$status = isset($input['status']) ? trim($input['status']) : '';

if (empty($projectId)) {
    echo json_encode([
        'success' => false,
        'message' => 'Project ID is required'
    ]);
    exit;
}

$validStatuses = ['Assigned', 'Pending', 'In Progress', 'On Hold', 'Completed'];
if (!in_array($status, $validStatuses, true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid project status value'
    ]);
    exit;
}

// Verify project exists
$checkStmt = $db->prepare("SELECT id, project_id, project_status FROM `projects` WHERE project_id = :pid OR id = :numeric_id LIMIT 1");
$checkStmt->execute([
    'pid' => $projectId,
    'numeric_id' => is_numeric($projectId) ? (int)$projectId : 0
]);
$existingProject = $checkStmt->fetch();

if (!$existingProject) {
    echo json_encode([
        'success' => false,
        'message' => 'Project not found'
    ]);
    exit;
}

// Disallow setting status back to 'Assigned' once changed from 'Assigned'
$currentDbStatus = trim($existingProject['project_status'] ?? '');
if (strcasecmp($currentDbStatus, 'Assigned') !== 0 && strcasecmp($status, 'Assigned') === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Cannot change project status back to "Assigned" once updated'
    ]);
    exit;
}

// Disallow setting status to 'Completed' unless the project has subtasks and all subtasks are Completed
if (strcasecmp($status, 'Completed') === 0) {
    $subtasksStmt = $db->prepare("
        SELECT pst.id, pst.subtask_id,
               COALESCE(
                   (
                       SELECT pr.status 
                       FROM `project_reports` pr 
                       WHERE (pr.subtask_id = pst.subtask_id OR (pr.subtask_id = CAST(pst.id AS CHAR) AND pr.project_id = pst.project_id))
                         AND pr.status IS NOT NULL 
                         AND TRIM(pr.status) != ''
                       ORDER BY pr.id DESC 
                       LIMIT 1
                   ),
                   pst.status,
                   'Pending'
               ) AS status
        FROM `project_subtasks` pst
        WHERE pst.project_id = :canonical_pid OR pst.project_id = :num_id
    ");
    $subtasksStmt->execute([
        'canonical_pid' => $existingProject['project_id'],
        'num_id' => (string)$existingProject['id']
    ]);
    $subtasks = $subtasksStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($subtasks)) {
        echo json_encode([
            'success' => false,
            'message' => 'Cannot set project status to "Completed": The project has no subtasks.'
        ]);
        exit;
    }

    $incompleteCount = 0;
    foreach ($subtasks as $st) {
        $stStatus = trim($st['status'] ?? '');
        if (strcasecmp($stStatus, 'Completed') !== 0) {
            $incompleteCount++;
        }
    }

    if ($incompleteCount > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Cannot set project status to "Completed": All subtasks related to this project must be "Completed" first (' . $incompleteCount . ' remaining).'
        ]);
        exit;
    }
}

// Resolve user name for edited_by
$editedBy = '';
if (!empty($_SESSION['full_name'])) {
    $editedBy = trim($_SESSION['full_name']);
} elseif (!empty($_SESSION['real_name'])) {
    $editedBy = trim($_SESSION['real_name']);
} else {
    $editedBy = trim($_SESSION['username']);
}

// Update project status and audit fields
$updateStmt = $db->prepare("
    UPDATE `projects`
    SET `project_status` = :status,
        `edited_by` = :edited_by,
        `edited_at` = NOW()
    WHERE `id` = :id
");

$updated = $updateStmt->execute([
    'status' => $status,
    'edited_by' => $editedBy,
    'id' => $existingProject['id']
]);

if ($updated) {
    // Resolve profile photo for edited_by user
    $photoPath = '../images/default-avatar.png';
    $allowed_extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    foreach ($allowed_extensions as $ext) {
        $path = __DIR__ . '/../images/profile/' . $_SESSION['id'] . '.' . $ext;
        if (file_exists($path)) {
            $photoPath = '../images/profile/' . $_SESSION['id'] . '.' . $ext . '?t=' . filemtime($path);
            break;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Project status updated successfully',
        'project_id' => $existingProject['project_id'],
        'project_status' => $status,
        'edited_by' => $editedBy,
        'edited_at' => date('Y-m-d H:i:s'),
        'edited_by_photo' => $photoPath
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update project status'
    ]);
}
