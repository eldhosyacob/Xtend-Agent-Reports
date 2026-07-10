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

// Fetch records for the current logged-in agent with optional date range filter
$agentUsername = strtoupper($_SESSION['real_name'] ?? $_SESSION['username']);
$range = isset($_GET['range']) ? trim($_GET['range']) : 'today';

if (!in_array($range, ['today', 'week', 'month'])) {
    $range = 'today';
}

$dateCondition = "`date` = CURDATE()";
if ($range === 'week') {
    $dateCondition = "`date` >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)";
} elseif ($range === 'month') {
    $dateCondition = "`date` >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
}

$query = "SELECT * FROM `$tableName` WHERE $dateCondition AND UPPER(`agent`) = :agent ORDER BY `id` DESC";
$stmt = $db->prepare($query);
$stmt->execute(['agent' => $agentUsername]);
$records = $stmt->fetchAll();

// Find the max ID for each case_id in this table
$case_ids = array_unique(array_filter(array_column($records, 'case_id')));
$max_ids = [];
if (!empty($case_ids)) {
    $placeholders = [];
    $params_max = [];
    foreach ($case_ids as $index => $cid) {
        $key = "cid_" . $index;
        $placeholders[] = ":" . $key;
        $params_max[$key] = $cid;
    }
    $in_clause = implode(',', $placeholders);
    $maxStmt = $db->prepare("SELECT case_id, MAX(id) as max_id FROM `$tableName` WHERE case_id IN ($in_clause) GROUP BY case_id");
    $maxStmt->execute($params_max);
    $max_ids = $maxStmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

foreach ($records as &$record) {
    $cid = $record['case_id'];
    $record['is_latest'] = isset($max_ids[$cid]) ? ($record['id'] == $max_ids[$cid]) : true;
}
unset($record);

echo json_encode([
    'success' => true,
    'count' => count($records),
    'department' => $user_department,
    'table' => $tableName,
    'range' => $range,
    'data' => $records
]);
?>
