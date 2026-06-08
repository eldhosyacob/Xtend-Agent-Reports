<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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

// Include database configuration
require_once __DIR__ . '/../config/database.php';
$db = getDatabaseConnection();

if (!$db) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

// Retrieve user's department from DB to ensure it's accurate and fresh
$userStmt = $db->prepare("SELECT department FROM users WHERE id = :id LIMIT 1");
$userStmt->execute(['id' => $_SESSION['id']]);
$user = $userStmt->fetch();

if (!$user) {
    echo json_encode([
        'success' => false,
        'message' => 'User not found'
    ]);
    exit;
}

$user_department = trim($user['department']);

// Determine the table based on user's department
$tableName = '';
if (strcasecmp($user_department, 'Voice Logger') === 0) {
    $tableName = 'support_details';
} else {
    $tableName = 'ivr_details';
}

// Fetch today's records for the current logged-in agent
$agentUsername = strtoupper($_SESSION['username']);

$query = "SELECT * FROM `$tableName` WHERE `date` = CURDATE() AND UPPER(`agent`) = :agent ORDER BY `id` DESC";
$stmt = $db->prepare($query);
$stmt->execute(['agent' => $agentUsername]);
$records = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'count' => count($records),
    'department' => $user_department,
    'table' => $tableName,
    'data' => $records
]);
?>
