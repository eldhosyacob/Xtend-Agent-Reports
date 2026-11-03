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
if ($dept === 'ivr' || strcasecmp($dept, 'IVR') === 0) {
    $deptLabel = 'IVR';
    $normalizedDept = 'ivr';
} else {
    $deptLabel = 'Voice Logger';
    $normalizedDept = 'voice logger';
}

// Get date range filter
$range = isset($_GET['range']) ? trim($_GET['range']) : 'today';
$fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$toDate = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

if (!in_array($range, ['today', 'week', 'month', 'year', 'custom'], true)) {
    $range = 'today';
}

$dateParams = [];
$dateCondition = "pr.`date` = CURDATE()";

if ($range === 'week') {
    $dateCondition = "pr.`date` >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)";
} elseif ($range === 'month') {
    $dateCondition = "pr.`date` >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
} elseif ($range === 'year') {
    $dateCondition = "pr.`date` >= DATE_FORMAT(CURDATE(), '%Y-01-01')";
} elseif ($range === 'custom') {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
        if ($fromDate === $toDate) {
            $dateCondition = "pr.`date` = :from_date";
            $dateParams['from_date'] = $fromDate;
        } else {
            $dateCondition = "pr.`date` BETWEEN :from_date AND :to_date";
            $dateParams['from_date'] = $fromDate;
            $dateParams['to_date'] = $toDate;
        }
    } else {
        $range = 'today';
        $dateCondition = "pr.`date` = CURDATE()";
    }
}

// Base joins
$joins = "
    LEFT JOIN `users` u ON (LOWER(TRIM(pr.`agent_name`)) = LOWER(TRIM(u.`real_name`)) OR LOWER(TRIM(pr.`agent_name`)) = LOWER(TRIM(u.`username`)))
    LEFT JOIN `projects` p ON (pr.`project_id` = p.`project_id` OR pr.`project_id` = CAST(p.`id` AS CHAR))
    LEFT JOIN `project_subtasks` pst ON (pr.`subtask_id` = pst.`subtask_id` OR (pr.`subtask_id` = CAST(pst.`id` AS CHAR) AND pr.`project_id` = pst.`project_id`))
";

$conditions = [
    $dateCondition,
    "(LOWER(REPLACE(u.`department`, '_', ' ')) = :dept OR LOWER(REPLACE(u.`department`, '_', ' ')) = 'manager')"
];
$params = array_merge($dateParams, ['dept' => $normalizedDept]);

// Optional status filter
if (isset($_GET['status']) && trim($_GET['status']) !== '') {
    $conditions[] = "LOWER(TRIM(pr.`status`)) = :status";
    $params['status'] = strtolower(trim($_GET['status']));
}

// Optional search query
if (isset($_GET['search']) && trim($_GET['search']) !== '') {
    $searchVal = '%' . trim($_GET['search']) . '%';
    $conditions[] = "(pr.`agent_name` LIKE :s1 OR pr.`project_id` LIKE :s2 OR pr.`subtask_id` LIKE :s3 OR pr.`client_name` LIKE :s4 OR pr.`product_name` LIKE :s5 OR p.`project_name` LIKE :s6 OR pst.`title` LIKE :s7 OR pr.`status` LIKE :s8 OR pr.`youtrack_id` LIKE :s9)";
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
$countQuery = "SELECT COUNT(*) as total FROM `project_reports` pr $joins WHERE $whereClause";
$countStmt = $db->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = (int)($countStmt->fetch()['total'] ?? 0);

$totalPages = max(1, ceil($totalRecords / $limit));

// Fetch paginated records
$recordsQuery = "
    SELECT 
        pr.id,
        pr.project_id,
        pr.subtask_id,
        pr.agent_name,
        pr.date,
        COALESCE(NULLIF(TRIM(pr.product_name), ''), p.project_name) AS product_name,
        COALESCE(NULLIF(TRIM(pr.client_name), ''), p.client_dealer_name) AS client_name,
        pr.youtrack_id,
        pr.start_time,
        pr.end_time,
        pr.total_time,
        pr.status,
        p.project_name,
        p.category as project_category,
        pst.title as subtask_title
    FROM `project_reports` pr
    $joins
    WHERE $whereClause
    ORDER BY pr.date DESC, pr.id DESC
    LIMIT $limit OFFSET $offset
";
$recordsStmt = $db->prepare($recordsQuery);
$recordsStmt->execute($params);
$records = $recordsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch overall status breakdown for this date range & department (unfiltered by status select)
$statusBreakdownConditions = [
    $dateCondition,
    "(LOWER(REPLACE(u.`department`, '_', ' ')) = :dept OR LOWER(REPLACE(u.`department`, '_', ' ')) = 'manager')"
];
$statusParams = array_merge($dateParams, ['dept' => $normalizedDept]);

if (isset($_GET['search']) && trim($_GET['search']) !== '') {
    $searchVal = '%' . trim($_GET['search']) . '%';
    $statusBreakdownConditions[] = "(pr.`agent_name` LIKE :s1 OR pr.`project_id` LIKE :s2 OR pr.`subtask_id` LIKE :s3 OR pr.`client_name` LIKE :s4 OR pr.`product_name` LIKE :s5 OR p.`project_name` LIKE :s6 OR pst.`title` LIKE :s7 OR pr.`status` LIKE :s8 OR pr.`youtrack_id` LIKE :s9)";
    $statusParams['s1'] = $searchVal;
    $statusParams['s2'] = $searchVal;
    $statusParams['s3'] = $searchVal;
    $statusParams['s4'] = $searchVal;
    $statusParams['s5'] = $searchVal;
    $statusParams['s6'] = $searchVal;
    $statusParams['s7'] = $searchVal;
    $statusParams['s8'] = $searchVal;
    $statusParams['s9'] = $searchVal;
}
$statusWhere = implode(' AND ', $statusBreakdownConditions);

$statusQuery = "
    SELECT LOWER(TRIM(pr.status)) as st, COUNT(*) as count 
    FROM `project_reports` pr 
    $joins 
    WHERE $statusWhere 
    GROUP BY LOWER(TRIM(pr.status))
";
$statusStmt = $db->prepare($statusQuery);
$statusStmt->execute($statusParams);
$dbStatuses = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

$statusCounts = [
    'Completed' => 0,
    'Pending' => 0,
    'In Progress' => 0,
    'On Hold' => 0
];

foreach ($dbStatuses as $row) {
    $st = strtolower(trim($row['st'] ?? ''));
    if ($st === 'completed') {
        $statusCounts['Completed'] += (int)$row['count'];
    } elseif ($st === 'in progress' || $st === 'inprogress') {
        $statusCounts['In Progress'] += (int)$row['count'];
    } elseif ($st === 'pending') {
        $statusCounts['Pending'] += (int)$row['count'];
    } elseif ($st === 'on hold' || $st === 'onhold' || $st === 'hold') {
        $statusCounts['On Hold'] += (int)$row['count'];
    }
}

echo json_encode([
    'success' => true,
    'department' => $deptLabel,
    'range' => $range,
    'total' => $totalRecords,
    'page' => $page,
    'limit' => $limit,
    'pages' => $totalPages,
    'records' => $records,
    'status_breakdown' => $statusCounts
]);
