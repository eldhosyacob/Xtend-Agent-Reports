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
$subProjectId = isset($input['sub_project_id']) ? trim($input['sub_project_id']) : '';
$title = isset($input['title']) ? trim($input['title']) : '';
$description = isset($input['description']) ? trim($input['description']) : '';
$assignedTo = isset($input['assigned_to']) ? trim($input['assigned_to']) : '';
$priority = isset($input['priority']) ? trim($input['priority']) : 'Medium';
$dueDate = isset($input['due_date']) && trim($input['due_date']) !== '' ? trim($input['due_date']) : null;

if (empty($projectId) && empty($subProjectId)) {
    echo json_encode([
        'success' => false,
        'message' => 'Project ID or Sub Project ID is required'
    ]);
    exit;
}

if (empty($title)) {
    echo json_encode([
        'success' => false,
        'message' => 'Subtask title is required'
    ]);
    exit;
}

// Detect if this is for a subproject
if (empty($subProjectId) && strpos($projectId, '_SP') !== false) {
    $subProjectId = $projectId;
}

$userRole = strtolower(trim($_SESSION['role'] ?? ($_SESSION['user_type'] ?? '')));
$isAdmin = ($userRole === 'admin');
$currentUsername = trim($_SESSION['username'] ?? '');
$currentRealName = trim($_SESSION['real_name'] ?? ($_SESSION['full_name'] ?? ''));
$userNamesLower = array_map('strtolower', array_values(array_filter([$currentUsername, $currentRealName])));

$parentProjectId = '';
$canonicalSubProjectId = null;
$canCreate = $isAdmin;

if (!empty($subProjectId)) {
    // Check in sub_projects table
    $spStmt = $db->prepare("SELECT id, project_id, sub_project_id, asignee, created_by, department FROM `sub_projects` WHERE sub_project_id = :spid OR id = :num_id LIMIT 1");
    $spStmt->execute([
        'spid' => $subProjectId,
        'num_id' => is_numeric($subProjectId) ? (int)$subProjectId : 0
    ]);
    $subProjectRow = $spStmt->fetch();

    if (!$subProjectRow) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid Sub Project ID: ' . htmlspecialchars($subProjectId)
        ]);
        exit;
    }

    $canonicalSubProjectId = $subProjectRow['sub_project_id'];
    $parentProjectId = $subProjectRow['project_id'];

    if (!$canCreate) {
        $spAssignee = trim($subProjectRow['asignee'] ?? '');
        $spAssigneeLower = strtolower($spAssignee);

        if ($spAssigneeLower === 'all') {
            $canCreate = true;
        } else {
            $assignees = array_map('trim', explode(',', $spAssigneeLower));
            foreach ($userNamesLower as $uname) {
                if (in_array($uname, $assignees, true)) {
                    $canCreate = true;
                    break;
                }
            }
        }

        if (!$canCreate && !empty($subProjectRow['created_by'])) {
            $creatorLower = strtolower(trim($subProjectRow['created_by']));
            if (in_array($creatorLower, $userNamesLower, true)) {
                $canCreate = true;
            }
        }
    }
} else {
    // Legacy direct project task
    $checkStmt = $db->prepare("SELECT id, project_id, asignee, created_by, department FROM `projects` WHERE project_id = :pid OR id = :num_id LIMIT 1");
    $checkStmt->execute([
        'pid' => $projectId,
        'num_id' => is_numeric($projectId) ? (int)$projectId : 0
    ]);
    $projectRow = $checkStmt->fetch();

    if (!$projectRow) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid Project ID'
        ]);
        exit;
    }

    $parentProjectId = $projectRow['project_id'];

    if (!$canCreate) {
        $projectAssignee = trim($projectRow['asignee'] ?? '');
        $projectAssigneeLower = strtolower($projectAssignee);

        if ($projectAssigneeLower === 'all') {
            $canCreate = true;
        } else {
            $assignees = array_map('trim', explode(',', $projectAssigneeLower));
            foreach ($userNamesLower as $uname) {
                if (in_array($uname, $assignees, true)) {
                    $canCreate = true;
                    break;
                }
            }
        }

        if (!$canCreate && !empty($projectRow['created_by'])) {
            $creatorLower = strtolower(trim($projectRow['created_by']));
            if (in_array($creatorLower, $userNamesLower, true)) {
                $canCreate = true;
            }
        }
    }
}

if (!$canCreate) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Only assigned agents or administrators can create tasks for this project'
    ]);
    exit;
}

if (empty($assignedTo)) {
    $userId = (int)($_SESSION['id'] ?? 0);
    if ($userId > 0) {
        $uStmt = $db->prepare("SELECT real_name, username FROM users WHERE id = :id LIMIT 1");
        $uStmt->execute(['id' => $userId]);
        $uRow = $uStmt->fetch();
        if ($uRow) {
            $assignedTo = !empty($uRow['real_name']) ? trim($uRow['real_name']) : trim($uRow['username']);
        }
    }
    if (empty($assignedTo)) {
        $assignedTo = !empty($_SESSION['real_name']) ? trim($_SESSION['real_name']) : (!empty($_SESSION['full_name']) ? trim($_SESSION['full_name']) : trim($_SESSION['username']));
    }
}

// Determine created_by from session
$createdBy = '';
if (!empty($_SESSION['full_name'])) {
    $createdBy = trim($_SESSION['full_name']);
} elseif (!empty($_SESSION['real_name'])) {
    $createdBy = trim($_SESSION['real_name']);
} else {
    $createdBy = trim($_SESSION['username']);
}

// Generate sequential subtask_id:
// For subprojects: project_id_SP1_T1, project_id_SP1_T2, etc. (XTP-000001_SP1_T1)
// For legacy projects: project_id_T1, project_id_T2...
$targetPrefix = !empty($canonicalSubProjectId) ? ($canonicalSubProjectId . '_T') : ($parentProjectId . '_T');

$subIdStmt = $db->prepare("
    SELECT subtask_id 
    FROM `project_subtasks` 
    WHERE `subtask_id` LIKE :prefix
    ORDER BY CAST(SUBSTRING_INDEX(subtask_id, '_T', -1) AS UNSIGNED) DESC
    LIMIT 1
");
$subIdStmt->execute([
    'prefix' => $targetPrefix . '%'
]);
$lastRow = $subIdStmt->fetch();
$nextIndex = 1;
if ($lastRow && !empty($lastRow['subtask_id'])) {
    $parts = explode('_T', $lastRow['subtask_id']);
    if (isset($parts[1]) && is_numeric($parts[1])) {
        $nextIndex = ((int)$parts[1]) + 1;
    }
}
$subtaskId = $targetPrefix . $nextIndex;

$stmt = $db->prepare("
    INSERT INTO `project_subtasks` (
        `project_id`, `sub_project_id`, `subtask_id`, `title`, `description`, `assigned_to`, `priority`, `status`, `due_date`, `created_by`, `created_at`
    ) VALUES (
        :project_id, :sub_project_id, :subtask_id, :title, :description, :assigned_to, :priority, 'Pending', :due_date, :created_by, NOW()
    )
");

$inserted = $stmt->execute([
    'project_id' => $parentProjectId,
    'sub_project_id' => $canonicalSubProjectId,
    'subtask_id' => $subtaskId,
    'title' => $title,
    'description' => $description !== '' ? $description : null,
    'assigned_to' => $assignedTo,
    'priority' => $priority,
    'due_date' => $dueDate,
    'created_by' => $createdBy
]);

if ($inserted) {
    echo json_encode([
        'success' => true,
        'message' => 'Subtask added successfully',
        'id' => $db->lastInsertId(),
        'subtask_id' => $subtaskId,
        'sub_project_id' => $canonicalSubProjectId,
        'project_id' => $parentProjectId
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to add subtask'
    ]);
}
