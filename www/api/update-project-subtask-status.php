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

$subtaskId = isset($input['subtask_id']) ? (int)$input['subtask_id'] : 0;
$status = isset($input['status']) ? trim($input['status']) : '';

$validStatuses = ['Pending', 'In Progress', 'Completed'];

if ($subtaskId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Valid Subtask ID is required'
    ]);
    exit;
}

if (!in_array($status, $validStatuses, true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status value'
    ]);
    exit;
}

$completedAt = ($status === 'Completed') ? date('Y-m-d H:i:s') : null;

$stmt = $db->prepare("
    UPDATE `project_subtasks`
    SET `status` = :status,
        `completed_at` = :completed_at
    WHERE `id` = :id
");

$updated = $stmt->execute([
    'status' => $status,
    'completed_at' => $completedAt,
    'id' => $subtaskId
]);

if ($updated) {
    echo json_encode([
        'success' => true,
        'message' => 'Subtask status updated successfully',
        'status' => $status,
        'completed_at' => $completedAt
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update subtask status'
    ]);
}
