<?php
require_once('../config/session.php');

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

// Retrieve form values with sanitization/trimming
$data = [
    'date' => trim($_POST['date'] ?? ''),
    'agent' => strtoupper(trim($_POST['agent'] ?? $_SESSION['real_name'] ?? $_SESSION['username'] ?? '')),
    'company_name' => trim($_POST['company_name'] ?? ''),
    'location' => trim($_POST['location'] ?? ''),
    'region' => trim($_POST['region'] ?? ''),
    'contact_details' => trim($_POST['contact_details'] ?? ''),
    'product_category' => trim($_POST['product_category'] ?? ''),
    'issue_category' => trim($_POST['issue_category'] ?? ''),
    'issue_type' => trim($_POST['issue_type'] ?? ''),
    'issue_details' => trim($_POST['issue_details'] ?? ''),
    'support_category' => trim($_POST['support_category'] ?? ''),
    'software_details' => trim($_POST['software_details'] ?? ''),
    'hardware_details' => trim($_POST['hardware_details'] ?? ''),
    'solution' => trim($_POST['solution'] ?? ''),
    'total_time' => trim($_POST['total_time'] ?? ''),
    'support_status' => trim($_POST['support_status'] ?? ''),
    'ticket_id' => trim($_POST['reason'] ?? ''), // maps reason to ticket_id
    'email' => !empty($_POST['email']) ? trim($_POST['email']) : null,
    'phone' => !empty($_POST['phone']) ? trim($_POST['phone']) : null,
    'support_start_time' => !empty($_POST['support_start_time']) ? trim($_POST['support_start_time']) : null,
    'support_end_time' => !empty($_POST['support_end_time']) ? trim($_POST['support_end_time']) : null,
];

// Perform backend validation for required fields
$requiredFields = [
    'date', 'agent', 'company_name', 'location', 'region', 'contact_details',
    'product_category', 'issue_category', 'issue_type', 'issue_details',
    'support_category', 'total_time', 'support_status'
];

foreach ($requiredFields as $field) {
    if ($data[$field] === '') {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required field: ' . htmlspecialchars($field)
        ]);
        exit;
    }
}

// Validate email format
if (!empty($data['email']) && !preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.com$/i', $data['email'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Email must end with .com'
    ]);
    exit;
}

// Every edit to any record must be saved as a new record with suffix and same case_id,
// EXCEPT when the same agent edits their own record on the same day.
$case_id = !empty($existing['case_id']) ? $existing['case_id'] : (!empty($existing['record_id']) ? $existing['record_id'] : $existing['id']);

$is_same_agent = (strcasecmp(trim($existing['agent']), $_SESSION['real_name'] ?? $_SESSION['username']) === 0);
$is_same_day = ($existing['date'] === date('Y-m-d'));

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

    // Prepare new record data to insert
    $newData = [
        'date' => $data['date'],
        'agent' => $data['agent'],
        'company_name' => $data['company_name'],
        'location' => $data['location'],
        'region' => $data['region'],
        'contact_details' => $data['contact_details'],
        'product_category' => $data['product_category'],
        'issue_category' => $data['issue_category'],
        'issue_type' => $data['issue_type'],
        'issue_details' => $data['issue_details'],
        'support_category' => $data['support_category'],
        'software_details' => $data['software_details'],
        'hardware_details' => $data['hardware_details'],
        'solution' => $data['solution'],
        'total_time' => $data['total_time'],
        'support_status' => $data['support_status'],
        'ticket_id' => $data['ticket_id'],
        'record_id' => $new_record_id,
        'email' => $data['email'],
        'phone' => $data['phone'],
        'support_start_time' => $data['support_start_time'],
        'support_end_time' => $data['support_end_time'],
        'case_id' => $case_id
    ];

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
    $returned_record_id = $new_record_id;
} else {
    // In-place update query
    $updateQuery = "UPDATE `$tableName` SET 
        `date` = :date,
        `agent` = :agent,
        `company_name` = :company_name,
        `location` = :location,
        `region` = :region,
        `contact_details` = :contact_details,
        `product_category` = :product_category,
        `issue_category` = :issue_category,
        `issue_type` = :issue_type,
        `issue_details` = :issue_details,
        `support_category` = :support_category,
        `software_details` = :software_details,
        `hardware_details` = :hardware_details,
        `solution` = :solution,
        `total_time` = :total_time,
        `support_status` = :support_status,
        `ticket_id` = :ticket_id,
        `email` = :email,
        `phone` = :phone,
        `support_start_time` = :support_start_time,
        `support_end_time` = :support_end_time
        WHERE `id` = :id";

    $stmt = $db->prepare($updateQuery);
    $stmt->execute(array_merge($data, ['id' => $existing['id']]));
    $returned_record_id = $existing['record_id'] ?: $record_id;
}

// Synchronize status in all records with the same case_id
$statusUpdateStmt = $db->prepare("UPDATE `$tableName` SET `support_status` = :status WHERE `case_id` = :case_id");
$statusUpdateStmt->execute([
    'status' => $data['support_status'],
    'case_id' => $case_id
]);

echo json_encode([
    'success' => true,
    'message' => $save_as_new ? 'Record successfully saved as a new record!' : 'Record successfully updated!',
    'data' => [
        'record_id' => $returned_record_id,
        'table' => $tableName
    ]
]);
?>
