<?php
require_once('../config/session.php');

header('Content-Type: application/json');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

require_once '../config/database.php';
$db = getDatabaseConnection();

if (!$db) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

$department = isset($_GET['department']) ? trim($_GET['department']) : '';

if (empty($department)) {
    echo json_encode([
        'success' => true,
        'data' => []
    ]);
    exit;
}

// Normalize department format for comparison (e.g. 'voice_logger' -> 'voice logger')
$normalizedDept = strtolower(str_replace('_', ' ', $department));

$stmt = $db->prepare("SELECT DISTINCT real_name FROM users WHERE (LOWER(REPLACE(department, '_', ' ')) = :dept OR LOWER(REPLACE(department, '_', ' ')) = 'manager') AND real_name IS NOT NULL AND TRIM(real_name) != '' ORDER BY real_name ASC");
$stmt->execute(['dept' => $normalizedDept]);
$agents = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode([
    'success' => true,
    'data' => $agents
]);
