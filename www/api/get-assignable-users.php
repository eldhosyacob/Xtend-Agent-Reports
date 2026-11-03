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

// Query users from the users table
$department = isset($_GET['department']) ? trim($_GET['department']) : '';

$sql = "
    SELECT DISTINCT COALESCE(NULLIF(TRIM(real_name), ''), username) AS user_display 
    FROM users 
    WHERE ((real_name IS NOT NULL AND TRIM(real_name) != '') 
       OR (username IS NOT NULL AND TRIM(username) != ''))
";
$params = [];

if (!empty($department)) {
    $sql .= " AND LOWER(REPLACE(department, '_', ' ')) = LOWER(REPLACE(:dept, '_', ' '))";
    $params['dept'] = $department;
}

$sql .= " ORDER BY user_display ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);

$users = [];
$seen = [];

while ($row = $stmt->fetch()) {
    $displayName = trim($row['user_display']);
    if (!empty($displayName)) {
        $lower = strtolower($displayName);
        if (!isset($seen[$lower])) {
            $seen[$lower] = true;
            $users[] = $displayName;
        }
    }
}

sort($users, SORT_NATURAL | SORT_FLAG_CASE);

echo json_encode([
    'success' => true,
    'users' => $users
]);
