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

// Get department parameter
$dept = isset($_GET['department']) ? trim($_GET['department']) : 'voice_logger';
$tableName = 'support_details';
$deptLabel = 'Voice Logger';

if ($dept === 'ivr' || strcasecmp($dept, 'IVR') === 0) {
    $tableName = 'ivr_details';
    $deptLabel = 'IVR';
}

// Get date range filter
$range = isset($_GET['range']) ? trim($_GET['range']) : 'today';
$fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$toDate = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

if (!in_array($range, ['today', 'week', 'month', 'year', 'custom'])) {
    $range = 'today';
}

$dateSql = "`date` = CURDATE()";
if ($range === 'week') {
    $dateSql = "`date` >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)";
} elseif ($range === 'month') {
    $dateSql = "`date` >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
} elseif ($range === 'year') {
    $dateSql = "`date` >= DATE_FORMAT(CURDATE(), '%Y-01-01')";
} elseif ($range === 'custom') {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
        if ($fromDate === $toDate) {
            $dateSql = "`date` = " . $db->quote($fromDate);
        } else {
            $dateSql = "`date` BETWEEN " . $db->quote($fromDate) . " AND " . $db->quote($toDate);
        }
    } else {
        $range = 'today';
        $dateSql = "`date` = CURDATE()";
    }
}

// Build WHERE conditions
$conditions = [$dateSql];
$params = [];

// Optional status filter
if (isset($_GET['status']) && trim($_GET['status']) !== '') {
    $conditions[] = "`support_status` = :status";
    $params['status'] = trim($_GET['status']);
}

// Optional search query
if (isset($_GET['search']) && trim($_GET['search']) !== '') {
    $searchVal = '%' . trim($_GET['search']) . '%';
    $conditions[] = "(`agent` LIKE :s1 OR `company_name` LIKE :s2 OR `dealer_name` LIKE :s3 OR `ticket_id` LIKE :s4 OR `reason` LIKE :s5 OR `contact_details` LIKE :s6 OR `phone` LIKE :s7 OR `location` LIKE :s8 OR `issue_category` LIKE :s9)";
    $params['s1'] = $searchVal;
    $params['s2'] = $searchVal;
    $params['s3'] = $searchVal;
    $params['s4'] = $searchVal;
    $params['s5'] = $searchVal;
    $params['s6'] = $searchVal;
    $params['s7'] = $searchVal;
    $params['s8'] = $searchVal;
    $params['s9'] = $searchVal;
}

$whereClause = implode(' AND ', $conditions);

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 15;
$offset = ($page - 1) * $limit;

// Count total matching records
$countQuery = "SELECT COUNT(*) as total FROM `$tableName` WHERE $whereClause";
$countStmt = $db->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = (int)($countStmt->fetch()['total'] ?? 0);

$totalPages = max(1, ceil($totalRecords / $limit));

// Fetch paginated records
$recordsQuery = "SELECT * FROM `$tableName` WHERE $whereClause ORDER BY `date` DESC, `id` DESC LIMIT $limit OFFSET $offset";
$recordsStmt = $db->prepare($recordsQuery);
$recordsStmt->execute($params);
$records = $recordsStmt->fetchAll(PDO::FETCH_ASSOC);

// Find the max ID for each case_id in this table to set is_latest
$case_ids = array_filter(array_unique(array_map(function($r) {
    return !empty($r['case_id']) ? $r['case_id'] : ($r['record_id'] ?? null);
}, $records)));

$max_ids = [];
if (!empty($case_ids)) {
    $in_clause = implode(',', array_fill(0, count($case_ids), '?'));
    $stmt_max = $db->prepare("SELECT COALESCE(NULLIF(case_id, ''), record_id) as cid, MAX(id) as max_id FROM `$tableName` WHERE COALESCE(NULLIF(case_id, ''), record_id) IN ($in_clause) GROUP BY cid");
    $stmt_max->execute(array_values($case_ids));
    while ($row = $stmt_max->fetch(PDO::FETCH_ASSOC)) {
        $max_ids[$row['cid']] = $row['max_id'];
    }
}

foreach ($records as &$rec) {
    $cid = !empty($rec['case_id']) ? $rec['case_id'] : ($rec['record_id'] ?? null);
    $rec['is_latest'] = isset($max_ids[$cid]) ? ($rec['id'] == $max_ids[$cid]) : true;
}
unset($rec);

// Fetch overall status breakdown for this date range (unfiltered by status select)
$statusBreakdownConditions = [$dateSql];
$statusParams = [];
if (isset($_GET['search']) && trim($_GET['search']) !== '') {
    $searchVal = '%' . trim($_GET['search']) . '%';
    $statusBreakdownConditions[] = "(`agent` LIKE :sb1 OR `company_name` LIKE :sb2 OR `dealer_name` LIKE :sb3 OR `ticket_id` LIKE :sb4 OR `reason` LIKE :sb5 OR `contact_details` LIKE :sb6 OR `phone` LIKE :sb7 OR `location` LIKE :sb8 OR `issue_category` LIKE :sb9)";
    $statusParams['sb1'] = $searchVal;
    $statusParams['sb2'] = $searchVal;
    $statusParams['sb3'] = $searchVal;
    $statusParams['sb4'] = $searchVal;
    $statusParams['sb5'] = $searchVal;
    $statusParams['sb6'] = $searchVal;
    $statusParams['sb7'] = $searchVal;
    $statusParams['sb8'] = $searchVal;
    $statusParams['sb9'] = $searchVal;
}
$statusWhere = implode(' AND ', $statusBreakdownConditions);

$statusQuery = "SELECT `support_status` as status, COUNT(*) as count FROM `$tableName` WHERE $statusWhere GROUP BY `support_status`";
$statusStmt = $db->prepare($statusQuery);
$statusStmt->execute($statusParams);
$statusData = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'department' => $deptLabel,
    'range' => $range,
    'total' => $totalRecords,
    'page' => $page,
    'limit' => $limit,
    'pages' => $totalPages,
    'records' => $records,
    'status_breakdown' => $statusData
]);
?>
