<?php
require_once __DIR__ . '/../config/session.php';

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

require_once __DIR__ . '/../config/database.php';
$db = getDatabaseConnection();

if (!$db) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

// Determine user department
$userDepartment = isset($_SESSION['department']) ? trim($_SESSION['department']) : '';
if (empty($userDepartment) && !empty($_SESSION['id'])) {
    $deptStmt = $db->prepare("SELECT department FROM users WHERE id = :id LIMIT 1");
    $deptStmt->execute(['id' => $_SESSION['id']]);
    $depVal = $deptStmt->fetchColumn();
    if ($depVal) {
        $userDepartment = trim($depVal);
        $_SESSION['department'] = $userDepartment;
    }
}

// Allow optional department query parameter
$requestedDept = trim($_GET['department'] ?? '');
$deptToUse = $userDepartment;
if ($requestedDept !== '') {
    $deptToUse = $requestedDept;
}

$products = [];
if (strcasecmp($deptToUse, 'Voice Logger') === 0) {
    $stmt = $db->prepare("SELECT product_name FROM `products_vl` ORDER BY id ASC");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_COLUMN);
} elseif (strcasecmp($deptToUse, 'IVR') === 0) {
    $stmt = $db->prepare("SELECT product_name FROM `products_ivr` ORDER BY id ASC");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    // For Managers or other departments without specific parameter, return combined products
    $stmt = $db->prepare("
        SELECT product_name FROM `products_vl`
        UNION
        SELECT product_name FROM `products_ivr`
        ORDER BY product_name ASC
    ");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

echo json_encode([
    'success' => true,
    'department' => $deptToUse,
    'data' => $products
]);
