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

// Retrieve user's department and user_type from DB
$userStmt = $db->prepare("SELECT department, user_type FROM users WHERE id = :id LIMIT 1");
$userStmt->execute(['id' => $_SESSION['id']]);
$user = $userStmt->fetch();

if (!$user) {
    echo json_encode([
        'success' => false,
        'message' => 'User not found'
    ]);
    exit;
}

$user_department = trim($user['department'] ?? '');
$user_role = trim($user['user_type'] ?? '');
$is_manager = (strcasecmp($user_department, 'Manager') === 0 || strcasecmp($user_role, 'manager') === 0 || strcasecmp($user_role, 'admin') === 0);


// Build dynamic WHERE conditions and parameter bindings
$conditions = [];
$params = [];

if (isset($_GET['agent']) && trim($_GET['agent']) !== '') {
    $conditions[] = "`agent` LIKE :agent";
    $params['agent'] = '%' . trim($_GET['agent']) . '%';
}

if (isset($_GET['from_date']) && trim($_GET['from_date']) !== '') {
    $conditions[] = "`date` >= :from_date";
    $params['from_date'] = trim($_GET['from_date']);
}

if (isset($_GET['to_date']) && trim($_GET['to_date']) !== '') {
    $conditions[] = "`date` <= :to_date";
    $params['to_date'] = trim($_GET['to_date']);
}

if (isset($_GET['miscellaneous_category']) && trim($_GET['miscellaneous_category']) !== '') {
    $conditions[] = "`miscellaneous_category` = :miscellaneous_category";
    $params['miscellaneous_category'] = trim($_GET['miscellaneous_category']);
}

// Optional department filter
if (isset($_GET['department']) && trim($_GET['department']) !== '') {
    $conditions[] = "`department` = :department";
    $params['department'] = trim($_GET['department']);
} elseif (!$is_manager && !empty($user_department)) {
    // Non-manager users are scoped to their department unless manager
    $normalized_dept = strtolower(str_replace('_', ' ', $user_department));
    $conditions[] = "LOWER(REPLACE(`department`, '_', ' ')) = :user_dept";
    $params['user_dept'] = $normalized_dept;
}

$where_clause = "";
if (!empty($conditions)) {
    $where_clause = " WHERE " . implode(" AND ", $conditions);
}

$query = "SELECT * FROM `miscellaneous_details`" . $where_clause . " ORDER BY `date` DESC, `id` DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'data' => $records,
    'count' => count($records)
]);
