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

$tables_to_query = [];
if (strcasecmp($user_department, 'Voice Logger') === 0) {
    $tables_to_query = ['support_details'];
} elseif (strcasecmp($user_department, 'IVR') === 0) {
    $tables_to_query = ['ivr_details'];
} else {
    $tables_to_query = ['support_details', 'ivr_details'];
}

// Fetch records for the current logged-in agent with optional date range filter
$agentUsername = strtoupper($_SESSION['real_name'] ?? $_SESSION['username']);
$range = isset($_GET['range']) ? trim($_GET['range']) : 'today';
$fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$toDate = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

if (!in_array($range, ['today', 'week', 'month', 'year', 'custom'])) {
    $range = 'today';
}

$dateCondition = "`date` = CURDATE()";
if ($range === 'week') {
    $dateCondition = "`date` >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)";
} elseif ($range === 'month') {
    $dateCondition = "`date` >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
} elseif ($range === 'year') {
    $dateCondition = "`date` >= DATE_FORMAT(CURDATE(), '%Y-01-01')";
} elseif ($range === 'custom') {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
        if ($fromDate === $toDate) {
            $dateCondition = "`date` = " . $db->quote($fromDate);
        } else {
            $dateCondition = "`date` BETWEEN " . $db->quote($fromDate) . " AND " . $db->quote($toDate);
        }
    } else {
        $range = 'today';
        $dateCondition = "`date` = CURDATE()";
    }
}

$records = [];
foreach ($tables_to_query as $tableName) {
    $query = "SELECT * FROM `$tableName` WHERE $dateCondition AND UPPER(`agent`) = :agent ORDER BY `id` DESC";
    $stmt = $db->prepare($query);
    $stmt->execute(['agent' => $agentUsername]);
    $tableRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $case_ids = array_unique(array_filter(array_column($tableRecords, 'case_id')));
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

    foreach ($tableRecords as &$rec) {
        $cid = $rec['case_id'];
        $rec['is_latest'] = isset($max_ids[$cid]) ? ($rec['id'] == $max_ids[$cid]) : true;
        $rec['record_department'] = ($tableName === 'support_details') ? 'Voice Logger' : 'IVR';
        $records[] = $rec;
    }
    unset($rec);
}

// Sort all combined records by date and id DESC
usort($records, function($a, $b) {
    if ($a['date'] === $b['date']) {
        return (int)$b['id'] - (int)$a['id'];
    }
    return strcmp($b['date'], $a['date']);
});

$totalSeconds = 0;
foreach ($records as $rec) {
    if (!empty($rec['total_time'])) {
        $parts = explode(':', $rec['total_time']);
        if (count($parts) === 3) {
            $totalSeconds += ((int)$parts[0] * 3600) + ((int)$parts[1] * 60) + (int)$parts[2];
        } elseif (count($parts) === 2) {
            $totalSeconds += ((int)$parts[0] * 60) + (int)$parts[1];
        }
    }
}
$tH = floor($totalSeconds / 3600);
$tM = floor(($totalSeconds % 3600) / 60);
$tS = $totalSeconds % 60;
$totalSupportTime = sprintf('%02d:%02d:%02d', $tH, $tM, $tS);

// Fetch total miscellaneous time from miscellaneous_details table
$miscStmt = $db->prepare("SELECT `total_time` FROM `miscellaneous_details` WHERE $dateCondition AND UPPER(`agent`) = :agent");
$miscStmt->execute(['agent' => $agentUsername]);
$miscTimes = $miscStmt->fetchAll(PDO::FETCH_COLUMN);

$totalMiscSeconds = 0;
foreach ($miscTimes as $timeStr) {
    if (!empty($timeStr)) {
        $parts = explode(':', $timeStr);
        if (count($parts) === 3) {
            $totalMiscSeconds += ((int)$parts[0] * 3600) + ((int)$parts[1] * 60) + (int)$parts[2];
        } elseif (count($parts) === 2) {
            $totalMiscSeconds += ((int)$parts[0] * 60) + (int)$parts[1];
        }
    }
}
$mH = floor($totalMiscSeconds / 3600);
$mM = floor(($totalMiscSeconds % 3600) / 60);
$mS = $totalMiscSeconds % 60;
$totalMiscTime = sprintf('%02d:%02d:%02d', $mH, $mM, $mS);

echo json_encode([
    'success' => true,
    'count' => count($records),
    'department' => $user_department,
    'range' => $range,
    'total_support_time' => $totalSupportTime,
    'total_miscellaneous_time' => $totalMiscTime,
    'data' => $records
]);
?>
