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

$company_name = trim($_GET['company_name'] ?? $_POST['company_name'] ?? '');
if ($company_name === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Company name is required'
    ]);
    exit;
}

// Retrieve user's department from DB to ensure accuracy
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

// Determine tables to query
$tables_to_query = [];
if (strcasecmp($user_department, 'Voice Logger') === 0) {
    $tables_to_query = ['support_details'];
} elseif (strcasecmp($user_department, 'IVR') === 0) {
    $tables_to_query = ['ivr_details'];
} else {
    $tables_to_query = ['support_details', 'ivr_details'];
}

$all_records = [];

foreach ($tables_to_query as $table) {
    // Query records other than 'Closed' and 'Closed-Device Replaced' matching the company name
    $query = "SELECT * FROM `$table` WHERE LOWER(`company_name`) = LOWER(:company_name) AND LOWER(`support_status`) NOT IN ('closed', 'closed-device replaced')";
    $stmt = $db->prepare($query);
    $stmt->execute(['company_name' => $company_name]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Find the max ID for each case_id in this table to set is_latest
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
        $maxStmt = $db->prepare("SELECT case_id, MAX(id) as max_id FROM `$table` WHERE case_id IN ($in_clause) GROUP BY case_id");
        $maxStmt->execute($params_max);
        $max_ids = $maxStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    $dept_label = ($table === 'support_details') ? 'Voice Logger' : 'IVR';
    foreach ($records as &$record) {
        $record['record_department'] = $dept_label;
        $cid = $record['case_id'];
        $record['is_latest'] = isset($max_ids[$cid]) ? ($record['id'] == $max_ids[$cid]) : true;
    }
    unset($record);

    $all_records = array_merge($all_records, $records);
}

// Sort all records by date (descending) and id (descending)
usort($all_records, function ($a, $b) {
    $dateA = strtotime($a['date']);
    $dateB = strtotime($b['date']);
    if ($dateA !== $dateB) {
        return $dateB - $dateA;
    }
    return $b['id'] - $a['id'];
});

echo json_encode([
    'success' => true,
    'count' => count($all_records),
    'data' => $all_records
]);
?>
