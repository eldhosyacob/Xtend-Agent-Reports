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
$title = isset($input['title']) ? trim($input['title']) : '';
$description = isset($input['description']) ? trim($input['description']) : '';
$assignedTo = isset($input['assigned_to']) ? trim($input['assigned_to']) : '';
$priority = isset($input['priority']) ? trim($input['priority']) : 'Medium';
$dueDate = isset($input['due_date']) && trim($input['due_date']) !== '' ? trim($input['due_date']) : null;

if (empty($projectId)) {
    echo json_encode([
        'success' => false,
        'message' => 'Project ID is required'
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

// Check that project exists and verify permission
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

// Canonical project ID
$projectId = $projectRow['project_id'];

// Authorization: Admins or users assigned to the project can create subtasks
$userRole = strtolower(trim($_SESSION['role'] ?? ($_SESSION['user_type'] ?? '')));
$isAdmin = ($userRole === 'admin');

$canCreate = $isAdmin;
if (!$canCreate) {
    $currentUsername = trim($_SESSION['username'] ?? '');
    $currentRealName = trim($_SESSION['real_name'] ?? ($_SESSION['full_name'] ?? ''));
    $userNamesLower = array_map('strtolower', array_values(array_filter([$currentUsername, $currentRealName])));

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

if (!$canCreate) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Only assigned agents or administrators can create subtasks for this project'
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

// Generate sequential subtask_id related to project_id, e.g. XTP-000005_T1, XTP-000005_T2...
$subIdStmt = $db->prepare("
    SELECT subtask_id 
    FROM `project_subtasks` 
    WHERE `project_id` = :project_id AND `subtask_id` LIKE :prefix
    ORDER BY CAST(SUBSTRING_INDEX(subtask_id, '_T', -1) AS UNSIGNED) DESC
    LIMIT 1
");
$subIdStmt->execute([
    'project_id' => $projectId,
    'prefix' => $projectId . '_T%'
]);
$lastRow = $subIdStmt->fetch();
$nextIndex = 1;
if ($lastRow && !empty($lastRow['subtask_id'])) {
    $parts = explode('_T', $lastRow['subtask_id']);
    if (isset($parts[1]) && is_numeric($parts[1])) {
        $nextIndex = ((int)$parts[1]) + 1;
    }
}
$subtaskId = $projectId . '_T' . $nextIndex;

$stmt = $db->prepare("
    INSERT INTO `project_subtasks` (
        `project_id`, `subtask_id`, `title`, `description`, `assigned_to`, `priority`, `status`, `due_date`, `created_by`, `created_at`
    ) VALUES (
        :project_id, :subtask_id, :title, :description, :assigned_to, :priority, 'Pending', :due_date, :created_by, NOW()
    )
");

$inserted = $stmt->execute([
    'project_id' => $projectId,
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
        'subtask_id' => $subtaskId
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to add subtask'
    ]);
}
