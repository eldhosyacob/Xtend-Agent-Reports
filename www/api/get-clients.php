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

$clients = [];
try {
    $stmt = $db->prepare("SELECT company_name FROM company_list ORDER BY company_name ASC");
    $stmt->execute();
    $clients = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch company list: ' . $e->getMessage()
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'data' => $clients
]);
?>
