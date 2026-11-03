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

$userId = (int)$_SESSION['id'];
$username = trim($_SESSION['username'] ?? '');
$realName = trim($_SESSION['real_name'] ?? ($_SESSION['full_name'] ?? ''));

// Fetch user info from database to ensure fresh identifiers
$userStmt = $db->prepare("SELECT id, username, real_name FROM users WHERE id = :id LIMIT 1");
$userStmt->execute(['id' => $userId]);
$userData = $userStmt->fetch();

if ($userData) {
    if (!empty($userData['username'])) {
        $username = trim($userData['username']);
    }
    if (!empty($userData['real_name'])) {
        $realName = trim($userData['real_name']);
    }
}

// Gather candidate name variations for matching agent_name
$candidateNames = array_values(array_unique(array_filter([
    $username,
    $realName
])));

if (!empty($realName)) {
    $parts = explode(' ', $realName);
    if (!empty($parts[0])) {
        $candidateNames[] = trim($parts[0]);
    }
}

$candidateNamesLower = array_values(array_unique(array_map('strtolower', array_filter($candidateNames))));

if (empty($candidateNamesLower)) {
    $candidateNamesLower = [strtolower($username)];
}

// Filters
$range = isset($_GET['range']) ? trim($_GET['range']) : 'today';
$fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$toDate = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

if (!in_array($range, ['today', 'week', 'month', 'year', 'custom'], true)) {
    $range = 'today';
}

$queryParams = [];

// Date condition
$dateCondition = "pr.date = CURDATE()";
if ($range === 'week') {
    $dateCondition = "pr.date >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)";
} elseif ($range === 'month') {
    $dateCondition = "pr.date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
} elseif ($range === 'year') {
    $dateCondition = "pr.date >= DATE_FORMAT(CURDATE(), '%Y-01-01')";
} elseif ($range === 'custom') {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
        if ($fromDate === $toDate) {
            $dateCondition = "pr.date = :from_date";
            $queryParams['from_date'] = $fromDate;
        } else {
            $dateCondition = "pr.date BETWEEN :from_date AND :to_date";
            $queryParams['from_date'] = $fromDate;
            $queryParams['to_date'] = $toDate;
        }
    } else {
        $range = 'today';
        $dateCondition = "pr.date = CURDATE()";
    }
}

// User matching condition using prepared parameter placeholders
$userPlaceholders = [];
foreach ($candidateNamesLower as $idx => $name) {
    $paramKey = 'agent_u_' . $idx;
    $userPlaceholders[] = ':' . $paramKey;
    $queryParams[$paramKey] = $name;
}
$userInClause = implode(', ', $userPlaceholders);

$query = "
    SELECT 
        pr.id,
        pr.project_id,
        pr.subtask_id,
        pr.agent_name,
        pr.department,
        pr.date,
        pr.product_name,
        pr.product_sub_category,
        pr.product_type,
        pr.assigned_by,
        pr.product_id,
        pr.software_version,
        pr.lcd_id,
        pr.hardware_id,
        pr.hasp_id,
        pr.client_name,
        pr.youtrack_id,
        pr.received_from,
        pr.sent_to,
        pr.testing_description,
        pr.start_time,
        pr.end_time,
        pr.total_time,
        pr.status,
        pr.created_at,
        pr.updated_at,
        pst.title AS subtask_title,
        pst.priority AS subtask_priority,
        pst.status AS subtask_status,
        p.project_name,
        p.category AS project_category,
        p.priority AS project_priority
    FROM `project_reports` pr
    LEFT JOIN `project_subtasks` pst 
        ON (pr.subtask_id = pst.subtask_id OR (pr.subtask_id = CAST(pst.id AS CHAR) AND pr.project_id = pst.project_id))
    LEFT JOIN `projects` p 
        ON (pr.project_id = p.project_id OR pr.project_id = CAST(p.id AS CHAR))
    WHERE $dateCondition 
      AND LOWER(TRIM(pr.agent_name)) IN ($userInClause)
    ORDER BY pr.date DESC, pr.id DESC
";

$stmt = $db->prepare($query);
$stmt->execute($queryParams);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total time across records
$totalSeconds = 0;
$pendingCount = 0;
$inProgressCount = 0;
$onHoldCount = 0;
$completedCount = 0;

foreach ($records as &$rec) {
    $timeStr = trim($rec['total_time'] ?? '');
    if (!empty($timeStr)) {
        $parts = explode(':', $timeStr);
        if (count($parts) === 3) {
            $totalSeconds += ((int)$parts[0] * 3600) + ((int)$parts[1] * 60) + (int)$parts[2];
        } elseif (count($parts) === 2) {
            $totalSeconds += ((int)$parts[0] * 60) + (int)$parts[1];
        }
    }

    $st = strtolower(trim($rec['status'] ?? 'pending'));
    if ($st === 'pending') {
        $pendingCount++;
    } elseif ($st === 'in progress') {
        $inProgressCount++;
    } elseif ($st === 'on hold') {
        $onHoldCount++;
    } elseif ($st === 'completed') {
        $completedCount++;
    }
}
unset($rec);

$tH = floor($totalSeconds / 3600);
$tM = floor(($totalSeconds % 3600) / 60);
$tS = $totalSeconds % 60;
$totalTimeFormatted = sprintf('%02d:%02d:%02d', $tH, $tM, $tS);

echo json_encode([
    'success' => true,
    'count' => count($records),
    'range' => $range,
    'server_date' => date('Y-m-d'),
    'total_time' => $totalTimeFormatted,
    'total_support_time' => $totalTimeFormatted, // for compatibility
    'stats' => [
        'total' => count($records),
        'pending' => $pendingCount,
        'in_progress' => $inProgressCount,
        'on_hold' => $onHoldCount,
        'completed' => $completedCount,
        'total_time' => $totalTimeFormatted
    ],
    'data' => $records
]);
?>
