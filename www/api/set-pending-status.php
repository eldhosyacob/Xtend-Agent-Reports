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
$record_id = trim($_POST['record_id'] ?? '');
if ($record_id === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Missing record_id'
    ]);
    exit;
}

// Find record in either support_details or ivr_details
$existing = null;
$tableName = '';
$stmt = $db->prepare("SELECT * FROM `support_details` WHERE `record_id` = :record_id LIMIT 1");
$stmt->execute(['record_id' => $record_id]);
$existing = $stmt->fetch();
if ($existing) {
    $tableName = 'support_details';
} else {
    $stmt = $db->prepare("SELECT * FROM `ivr_details` WHERE `record_id` = :record_id LIMIT 1");
    $stmt->execute(['record_id' => $record_id]);
    $existing = $stmt->fetch();
    if ($existing) {
        $tableName = 'ivr_details';
    }
}

// Fallback to auto-increment id if not found by record_id
if (!$existing && is_numeric($record_id)) {
    $stmt = $db->prepare("SELECT * FROM `support_details` WHERE `id` = :id LIMIT 1");
    $stmt->execute(['id' => (int)$record_id]);
    $existing = $stmt->fetch();
    if ($existing) {
        $tableName = 'support_details';
    } else {
        $stmt = $db->prepare("SELECT * FROM `ivr_details` WHERE `id` = :id LIMIT 1");
        $stmt->execute(['id' => (int)$record_id]);
        $existing = $stmt->fetch();
        if ($existing) {
            $tableName = 'ivr_details';
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
if (strcasecmp($user_department, $record_department) !== 0) {
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
    $newData['support_start_time'] = date('H:i:s');
    $newData['support_end_time'] = null;
    $newData['total_time'] = '00:00:00';

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
} else {
    // In-place update query
    $updateStmt = $db->prepare("UPDATE `$tableName` SET `support_status` = 'Pending', `agent` = :agent, `date` = :date WHERE `id` = :id");
    $updateStmt->execute([
        'agent' => $current_agent,
        'date' => $current_date,
        'id' => $existing['id']
    ]);
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
