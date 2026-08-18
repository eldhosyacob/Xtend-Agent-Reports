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

$case_id = !empty($existing['case_id']) ? $existing['case_id'] : (!empty($existing['record_id']) ? $existing['record_id'] : $existing['id']);

$current_agent = isset($_SESSION['real_name']) ? strtoupper($_SESSION['real_name']) : strtoupper($_SESSION['username']);
$current_date = date('Y-m-d');

$is_same_agent = (strcasecmp(trim($existing['agent']), $current_agent) === 0);
$is_same_day = ($existing['date'] === $current_date);

$save_as_new = !($is_same_agent && $is_same_day);

if ($save_as_new) {
    // Get all record IDs for this case to calculate the next suffix
    $suffixStmt = $db->prepare("SELECT record_id FROM `$tableName` WHERE case_id = :case_id");
    $suffixStmt->execute(['case_id' => $case_id]);
    $existing_record_ids = $suffixStmt->fetchAll(PDO::FETCH_COLUMN);

    $suffixes = [];
    foreach ($existing_record_ids as $rid) {
        if (strpos($rid, $case_id . '_') === 0) {
            $suffix = substr($rid, strlen($case_id) + 1);
            if ($suffix !== '') {
                $suffixes[] = $suffix;
            }
        }
    }

    $numeric_suffixes = array_filter($suffixes, 'is_numeric');
    if (empty($numeric_suffixes)) {
        $next_suffix = 1;
    } else {
        $next_suffix = max(array_map('intval', $numeric_suffixes)) + 1;
    }

    $new_record_id = $case_id . '_' . $next_suffix;

    // Build the new data using the existing record as baseline
    $newData = [];
    foreach ($existing as $key => $val) {
        if (!is_numeric($key)) {
            $newData[$key] = $val;
        }
    }

    unset($newData['id']);
    $newData['record_id'] = $new_record_id;
    $newData['case_id'] = $case_id;
    $newData['agent'] = $current_agent;
    $newData['date'] = $current_date;
    $newData['support_status'] = 'Pending';
    $current_time = date('H:i:s');
    $newData['support_start_time'] = !empty($_POST['support_start_time']) ? trim($_POST['support_start_time']) : $current_time;
    $newData['support_end_time'] = $current_time;
    $newData['total_time'] = !empty($_POST['total_time']) ? trim($_POST['total_time']) : '00:00:00';

    $updatableFields = [
        'company_name', 'dealer_name', 'location', 'region', 'email', 'phone',
        'contact_details', 'product_category', 'issue_category', 'issue_type',
        'issue_details', 'support_category', 'software_details', 'hardware_details',
        'product_model', 'solution', 'reason', 'ticket_id'
    ];

    foreach ($updatableFields as $field) {
        if (array_key_exists($field, $_POST)) {
            $val = trim($_POST[$field]);
            $newData[$field] = $val !== '' ? $val : null;
        }
    }

    if ($tableName === 'ivr_details') {
        $maxIdStmt = $db->query("SELECT MAX(id) as max_id FROM ivr_details");
        $maxIdRow = $maxIdStmt->fetch();
        $newData['id'] = ($maxIdRow && $maxIdRow['max_id'] !== null) ? (int)$maxIdRow['max_id'] + 1 : 1;
    }

    $cols = array_keys($newData);
    $columns_str = implode(',', array_map(fn($c) => "`$c`", $cols));
    $placeholders_str = implode(',', array_map(fn($c) => ":$c", $cols));

    $insertStmt = $db->prepare("INSERT INTO `$tableName` ($columns_str) VALUES ($placeholders_str)");
    $insertStmt->execute($newData);

    $timeHistStmt = $db->prepare("INSERT INTO `support_time_history` (`record_id`, `support_start_time`, `support_end_time`, `date`) VALUES (:record_id, :support_start_time, :support_end_time, :date)");
    $timeHistStmt->execute([
        'record_id' => $new_record_id,
        'support_start_time' => $newData['support_start_time'],
        'support_end_time' => $newData['support_end_time'],
        'date' => !empty($newData['date']) ? $newData['date'] : $current_date
    ]);
} else {
    // In-place update query
    $updatableFields = [
        'company_name', 'dealer_name', 'location', 'region', 'email', 'phone',
        'contact_details', 'product_category', 'issue_category', 'issue_type',
        'issue_details', 'support_category', 'software_details', 'hardware_details',
        'product_model', 'solution', 'reason', 'ticket_id'
    ];

    $current_time = date('H:i:s');
    $start_time = !empty($_POST['support_start_time']) ? trim($_POST['support_start_time']) : (!empty($existing['support_start_time']) ? $existing['support_start_time'] : $current_time);
    $end_time = $current_time;

    if (!empty($_POST['total_time'])) {
        $total_time_str = trim($_POST['total_time']);
    } else {
        $start_secs = strtotime("1970-01-01 $start_time");
        $end_secs = strtotime("1970-01-01 $end_time");
        $diff_secs = max(0, $end_secs - $start_secs);
        $hours = floor($diff_secs / 3600);
        $minutes = floor(($diff_secs % 3600) / 60);
        $seconds = $diff_secs % 60;
        $total_time_str = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    $fieldUpdates = [];
    $queryParams = [
        'agent' => $current_agent,
        'date' => $current_date,
        'start_time' => $start_time,
        'end_time' => $end_time,
        'total_time' => $total_time_str,
        'id' => $existing['id']
    ];

    foreach ($updatableFields as $field) {
        if (array_key_exists($field, $_POST)) {
            $val = trim($_POST[$field]);
            $fieldUpdates[] = "`$field` = :$field";
            $queryParams[$field] = $val !== '' ? $val : null;
        }
    }

    $fieldSql = !empty($fieldUpdates) ? implode(', ', $fieldUpdates) . ', ' : '';

    $updateStmt = $db->prepare("UPDATE `$tableName` SET {$fieldSql}`support_status` = 'Pending', `agent` = :agent, `date` = :date, `support_start_time` = :start_time, `support_end_time` = :end_time, `total_time` = :total_time WHERE `id` = :id");
    $updateStmt->execute($queryParams);

    // Upsert into support_time_history for active session
    $hist_date = !empty($existing['date']) ? $existing['date'] : $current_date;
    $checkHist = $db->prepare("SELECT `id` FROM `support_time_history` WHERE `record_id` = :record_id AND `support_start_time` = :start_time AND `date` = :date ORDER BY `id` DESC LIMIT 1");
    $checkHist->execute([
        'record_id' => $existing['record_id'],
        'start_time' => $start_time,
        'date' => $hist_date
    ]);
    $existingHist = $checkHist->fetch();

    if ($existingHist) {
        $updateHist = $db->prepare("UPDATE `support_time_history` SET `support_end_time` = :end_time WHERE `id` = :id");
        $updateHist->execute([
            'end_time' => $end_time,
            'id' => $existingHist['id']
        ]);
    } else {
        $insertTimeHist = $db->prepare("INSERT INTO `support_time_history` (`record_id`, `support_start_time`, `support_end_time`, `date`) VALUES (:record_id, :support_start_time, :support_end_time, :date)");
        $insertTimeHist->execute([
            'record_id' => $existing['record_id'],
            'support_start_time' => $start_time,
            'support_end_time' => $end_time,
            'date' => $hist_date
        ]);
    }
}

// Synchronize status in all records with the same case_id
$statusUpdateStmt = $db->prepare("UPDATE `$tableName` SET `support_status` = 'Pending' WHERE `case_id` = :case_id");
$statusUpdateStmt->execute([
    'case_id' => $case_id
]);

echo json_encode([
    'success' => true,
    'message' => 'Record status updated to Pending'
]);
?>
