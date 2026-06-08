<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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

// Retrieve form values with sanitization/trimming
$data = [
    'date' => trim($_POST['date'] ?? ''),
    'agent' => trim($_POST['agent'] ?? ''),
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

// Perform backend validation for strictly required fields
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

// Generate the next incrementing numeric prefix for record_id
$next_num = 1000;

// Query max numeric prefix in support_details
$supPrefixQuery = "
    SELECT MAX(CAST(SUBSTRING(record_id, 1, LENGTH(record_id)-2) AS UNSIGNED)) AS max_num 
    FROM support_details 
    WHERE record_id REGEXP '^[0-9]+[a-zA-Z]{2}$'
";
$stmt_sup = $db->query($supPrefixQuery);
$row_sup = $stmt_sup->fetch();
if ($row_sup && $row_sup['max_num'] !== null) {
    $next_num = max($next_num, (int)$row_sup['max_num'] + 1);
}

// Query max numeric prefix in ivr_details
$ivrPrefixQuery = "
    SELECT MAX(CAST(SUBSTRING(record_id, 1, LENGTH(record_id)-2) AS UNSIGNED)) AS max_num 
    FROM ivr_details 
    WHERE record_id REGEXP '^[0-9]+[a-zA-Z]{2}$'
";
$stmt_ivr = $db->query($ivrPrefixQuery);
$row_ivr = $stmt_ivr->fetch();
if ($row_ivr && $row_ivr['max_num'] !== null) {
    $next_num = max($next_num, (int)$row_ivr['max_num'] + 1);
}

// Handle missing AUTO_INCREMENT id behavior for the ivr_details table
if ($tableName === 'ivr_details') {
    $maxIdStmt = $db->query("SELECT MAX(id) as max_id FROM ivr_details");
    $maxIdRow = $maxIdStmt->fetch();
    $data['id'] = ($maxIdRow && $maxIdRow['max_id'] !== null) ? (int)$maxIdRow['max_id'] + 1 : 1;
}

// Add placeholder for record_id
$data['record_id'] = '';

// Dynamically construct prepared insert query
$cols = array_keys($data);
$columns_str = implode(',', array_map(fn($c) => "`$c`", $cols));
$placeholders_str = implode(',', array_map(fn($c) => ":$c", $cols));

$insertStmt = $db->prepare("INSERT INTO $tableName ($columns_str) VALUES ($placeholders_str)");

$success = false;
$attempts = 0;
$max_attempts = 20;
$record_id = '';

// Generate record_id and attempt insertion
while (!$success && $attempts < $max_attempts) {
    $attempts++;

    // Generate two random uppercase letters
    $letters = chr(rand(65, 90)) . chr(rand(65, 90));
    $record_id = $next_num . $letters;
    $data['record_id'] = $record_id;

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

echo json_encode([
    'success' => true,
    'message' => 'Record successfully created!',
    'data' => [
        'record_id' => $record_id,
        'table' => $tableName
    ]
]);
?>
