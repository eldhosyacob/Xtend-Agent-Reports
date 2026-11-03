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

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$priority = isset($_GET['priority']) ? trim($_GET['priority']) : '';
$assignee = isset($_GET['assignee']) ? trim($_GET['assignee']) : '';

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(project_name LIKE :search1 OR project_id LIKE :search2 OR asignee LIKE :search3 OR created_by LIKE :search4)";
    $searchWildcard = '%' . $search . '%';
    $params['search1'] = $searchWildcard;
    $params['search2'] = $searchWildcard;
    $params['search3'] = $searchWildcard;
    $params['search4'] = $searchWildcard;
}

if ($category !== '') {
    $where[] = "category = :category";
    $params['category'] = $category;
}

if ($priority !== '') {
    $where[] = "priority = :priority";
    $params['priority'] = $priority;
}

if ($assignee !== '') {
    $where[] = "asignee = :assignee";
    $params['assignee'] = $assignee;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT id, project_id, date, project_name, product_name, product_sub_category, created_by, created_at, edited_by, edited_at, category, department, assigned_by, asignee, priority, project_status, project_description, client_dealer_specific, client_dealer_name, product_serial_no, lcd_id, hardware_id, software_version, hasp_id 
        FROM `projects` 
        {$whereClause} 
        ORDER BY id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$projects = $stmt->fetchAll();

// Resolve photos
$allowed_extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
$usersStmt = $db->query("SELECT id, username, real_name FROM users");
$allUsers = $usersStmt->fetchAll();

$userPhotoMap = [];
foreach ($allUsers as $u) {
    $photo = '../images/default-avatar.png';
    foreach ($allowed_extensions as $ext) {
        $path = __DIR__ . '/../images/profile/' . $u['id'] . '.' . $ext;
        if (file_exists($path)) {
            $photo = '../images/profile/' . $u['id'] . '.' . $ext . '?t=' . filemtime($path);
            break;
        }
    }
    if (!empty($u['username'])) {
        $userPhotoMap[strtolower(trim($u['username']))] = $photo;
    }
    if (!empty($u['real_name'])) {
        $userPhotoMap[strtolower(trim($u['real_name']))] = $photo;
        $nameParts = explode(' ', trim($u['real_name']));
        if (!empty($nameParts[0])) {
            $userPhotoMap[strtolower($nameParts[0])] = $photo;
        }
    }
}

foreach ($projects as &$p) {
    $key = strtolower(trim($p['asignee'] ?? ''));
    $p['asignee_photo'] = isset($userPhotoMap[$key]) ? $userPhotoMap[$key] : '../images/default-avatar.png';
}
unset($p);

$userRole = strtolower(trim($_SESSION['role'] ?? ''));
$isAdmin = ($userRole === 'admin' || strtolower(trim($_SESSION['username'] ?? '')) === 'admin');

echo json_encode([
    'success' => true,
    'data' => $projects,
    'count' => count($projects),
    'is_admin' => $isAdmin
]);
