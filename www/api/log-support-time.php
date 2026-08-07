<?php
require_once('../config/session.php');

header('Content-Type: application/json');

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

// Retrieve user's department from DB
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

// Retrieve and check record_id
$record_id = trim($_POST['record_id'] ?? $_POST['id'] ?? '');
if ($record_id === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Missing record_id'
    ]);
    exit;
}

$requested_dept = trim($_POST['department'] ?? $_POST['dept'] ?? $_POST['department_select'] ?? '');

$existing = null;
$tableName = '';

// Determine table search order based on requested department or logged-in user department
$tables = ['support_details', 'ivr_details']; // default order
if (!empty($requested_dept)) {
    if (strcasecmp($requested_dept, 'IVR') === 0 || strtolower(str_replace(['_', ' '], '', $requested_dept)) === 'ivr') {
        $tables = ['ivr_details', 'support_details'];
    } elseif (strcasecmp($requested_dept, 'Voice Logger') === 0 || strtolower(str_replace(['_', ' '], '', $requested_dept)) === 'voicelogger') {
        $tables = ['support_details', 'ivr_details'];
    }
} elseif (!empty($user_department)) {
    if (strcasecmp($user_department, 'IVR') === 0) {
        $tables = ['ivr_details', 'support_details'];
    } elseif (strcasecmp($user_department, 'Voice Logger') === 0) {
        $tables = ['support_details', 'ivr_details'];
    }
}

// 1. Try fetching by record_id column in order of target tables
foreach ($tables as $tbl) {
    $stmt = $db->prepare("SELECT * FROM `$tbl` WHERE `record_id` = :record_id LIMIT 1");
    $stmt->execute(['record_id' => $record_id]);
    $res = $stmt->fetch();
    if ($res) {
        $existing = $res;
        $tableName = $tbl;
        break;
    }
}

// 2. Fallback to auto-increment id column if not found by record_id column
if (!$existing && is_numeric($record_id)) {
    foreach ($tables as $tbl) {
        $stmt = $db->prepare("SELECT * FROM `$tbl` WHERE `id` = :id LIMIT 1");
        $stmt->execute(['id' => (int)$record_id]);
        $res = $stmt->fetch();
        if ($res) {
            $existing = $res;
            $tableName = $tbl;
            break;
        }
    }
}

if (!$existing) {
    echo json_encode([
        'success' => false,
        'message' => 'Record not found'
    ]);
    exit;
}

// Security: Ensure agent's department matches the record's department
$record_department = ($tableName === 'support_details') ? 'Voice Logger' : 'IVR';
if (strcasecmp($user_department, 'Manager') !== 0 && strcasecmp($user_department, $record_department) !== 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: You do not have permission to edit this record'
    ]);
    exit;
}

$current_time = date('H:i:s');
$start_time = !empty($_POST['support_start_time']) ? trim($_POST['support_start_time']) : (!empty($existing['support_start_time']) ? $existing['support_start_time'] : $current_time);
$end_time = !empty($_POST['support_end_time']) ? trim($_POST['support_end_time']) : $current_time;

$actual_record_id = $existing['record_id'] ?: $record_id;
$case_id = !empty($existing['case_id']) ? $existing['case_id'] : $actual_record_id;

// Always INSERT a new entry into support_time_history to preserve complete session history
$timeHistStmt = $db->prepare("INSERT INTO `support_time_history` (`record_id`, `support_start_time`, `support_end_time`) VALUES (:record_id, :support_start_time, :support_end_time)");
$timeHistStmt->execute([
    'record_id' => $actual_record_id,
    'support_start_time' => $start_time,
    'support_end_time' => $end_time
]);

// Recalculate total_time by summing entries in support_time_history for this specific record_id
$allHistoryStmt = $db->prepare("SELECT support_start_time, support_end_time FROM `support_time_history` WHERE `record_id` = :record_id");
$allHistoryStmt->execute(['record_id' => $actual_record_id]);
$allTimeRows = $allHistoryStmt->fetchAll(PDO::FETCH_ASSOC);

$totalSecs = 0;
foreach ($allTimeRows as $row) {
    if (!empty($row['support_start_time']) && !empty($row['support_end_time'])) {
        $sSecs = strtotime("1970-01-01 " . $row['support_start_time']);
        $eSecs = strtotime("1970-01-01 " . $row['support_end_time']);
        $diff = $eSecs - $sSecs;
        if ($diff < 0) $diff += 86400; // handle cross midnight
        $totalSecs += max(0, $diff);
    }
}

$hours = floor($totalSecs / 3600);
$minutes = floor(($totalSecs % 3600) / 60);
$seconds = $totalSecs % 60;
$total_time_str = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

// Update support_end_time and total_time without changing support_status
$updateStmt = $db->prepare("UPDATE `$tableName` SET `support_end_time` = :end_time, `total_time` = :total_time WHERE `id` = :id");
$updateStmt->execute([
    'end_time' => $end_time,
    'total_time' => $total_time_str,
    'id' => $existing['id']
]);

echo json_encode([
    'success' => true,
    'message' => 'Support time logged successfully',
    'total_time' => $total_time_str
]);
?>
