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

// Route insertion to the correct table based on user's department
$tableName = '';
if (strcasecmp($user_department, 'Voice Logger') === 0) {
    $tableName = 'support_details';
} elseif (strcasecmp($user_department, 'IVR') === 0) {
    $tableName = 'ivr_details';
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized department to create records: ' . $user_department
    ]);
    exit;
}

// Generate the next incrementing numeric prefix for record_id
$next_num = 1000;

// Query max numeric prefix in support_details matching both old and XT- prefixed record IDs
$supPrefixQuery = "
    SELECT MAX(CASE 
        WHEN record_id REGEXP '^XT-[0-9]+[a-zA-Z]{2}$' THEN CAST(SUBSTRING(record_id, 4, LENGTH(record_id)-5) AS UNSIGNED)
        WHEN record_id REGEXP '^[0-9]+[a-zA-Z]{2}$' THEN CAST(SUBSTRING(record_id, 1, LENGTH(record_id)-2) AS UNSIGNED)
        ELSE 0
    END) AS max_num 
    FROM support_details
";
$stmt_sup = $db->query($supPrefixQuery);
$row_sup = $stmt_sup->fetch();
if ($row_sup && $row_sup['max_num'] !== null) {
    $next_num = max($next_num, (int)$row_sup['max_num'] + 1);
}

// Query max numeric prefix in ivr_details matching both old and XT- prefixed record IDs
$ivrPrefixQuery = "
    SELECT MAX(CASE 
        WHEN record_id REGEXP '^XT-[0-9]+[a-zA-Z]{2}$' THEN CAST(SUBSTRING(record_id, 4, LENGTH(record_id)-5) AS UNSIGNED)
        WHEN record_id REGEXP '^[0-9]+[a-zA-Z]{2}$' THEN CAST(SUBSTRING(record_id, 1, LENGTH(record_id)-2) AS UNSIGNED)
        ELSE 0
    END) AS max_num 
    FROM ivr_details
";
$stmt_ivr = $db->query($ivrPrefixQuery);
$row_ivr = $stmt_ivr->fetch();
if ($row_ivr && $row_ivr['max_num'] !== null) {
    $next_num = max($next_num, (int)$row_ivr['max_num'] + 1);
}

// Prepare baseline data
$agent_name = isset($_SESSION['real_name']) ? strtoupper($_SESSION['real_name']) : strtoupper($_SESSION['username']);
$company_name = isset($_POST['company_name']) ? trim($_POST['company_name']) : '';

$data = [
    'date' => date('Y-m-d'),
    'agent' => $agent_name,
    'company_name' => $company_name,
    'location' => '',
    'region' => '',
    'contact_details' => '',
    'product_category' => '',
    'issue_category' => '',
    'issue_type' => '',
    'issue_details' => '',
    'support_category' => '',
    'software_details' => '',
    'hardware_details' => '',
    'solution' => '',
    'total_time' => '00:00:00',
    'support_status' => 'Pending',
    'ticket_id' => '',
    'email' => null,
    'phone' => null,
    'support_start_time' => date('H:i:s'),
    'support_end_time' => null,
    'record_id' => '',
    'case_id' => ''
];

// Handle missing AUTO_INCREMENT id behavior for the ivr_details table
if ($tableName === 'ivr_details') {
    $maxIdStmt = $db->query("SELECT MAX(id) as max_id FROM ivr_details");
    $maxIdRow = $maxIdStmt->fetch();
    $data['id'] = ($maxIdRow && $maxIdRow['max_id'] !== null) ? (int)$maxIdRow['max_id'] + 1 : 1;
}

// Dynamically construct prepared insert query
$cols = array_keys($data);
$columns_str = implode(',', array_map(fn($c) => "`$c`", $cols));
$placeholders_str = implode(',', array_map(fn($c) => ":$c", $cols));

$insertStmt = $db->prepare("INSERT INTO `$tableName` ($columns_str) VALUES ($placeholders_str)");

$success = false;
$attempts = 0;
$max_attempts = 20;
$record_id = '';

// Generate record_id and attempt insertion
while (!$success && $attempts < $max_attempts) {
    $attempts++;

    // Generate two random uppercase letters
    $letters = chr(rand(65, 90)) . chr(rand(65, 90));
    $record_id = 'XT-' . $next_num . $letters;
    $data['record_id'] = $record_id;
    $data['case_id'] = $record_id;

    try {
        $insertStmt->execute($data);
        $success = true;
    } catch (PDOException $e) {
        // Retry on unique constraint violation (duplicate key 1062)
        if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
            continue;
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Database execution failed: ' . $e->getMessage()
            ]);
            exit;
        }
    }
}

if (!$success) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to generate a unique record ID after multiple attempts.'
    ]);
    exit;
}

// Add new company to company_list table if requested
$add_to_company_list = isset($_POST['add_to_company_list']) ? (int)$_POST['add_to_company_list'] : 0;
if ($add_to_company_list && !empty($company_name)) {
    $checkStmt = $db->prepare("SELECT COUNT(*) FROM company_list WHERE LOWER(company_name) = LOWER(:company_name)");
    $checkStmt->execute(['company_name' => $company_name]);
    if ($checkStmt->fetchColumn() == 0) {
        $insertCompanyStmt = $db->prepare("INSERT INTO company_list (company_name) VALUES (:company_name)");
        $insertCompanyStmt->execute(['company_name' => strtoupper($company_name)]);
    }
}

echo json_encode([
    'success' => true,
    'record_id' => $record_id,
    'message' => 'Pending record successfully created!'
]);
?>
