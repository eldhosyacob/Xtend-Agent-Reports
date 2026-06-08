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
$tableName = (strcasecmp($user_department, 'Voice Logger') === 0) ? 'support_details' : 'ivr_details';

// Retrieve and check record_id
$record_id = trim($_POST['record_id'] ?? '');
if ($record_id === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Missing record_id'
    ]);
    exit;
}

// Security: Verify the record exists and belongs to the agent
$checkStmt = $db->prepare("SELECT `agent` FROM `$tableName` WHERE `record_id` = :record_id LIMIT 1");
$checkStmt->execute(['record_id' => $record_id]);
$existing = $checkStmt->fetch();

if (!$existing) {
    echo json_encode([
        'success' => false,
        'message' => 'Record not found'
    ]);
    exit;
}

if (strcasecmp(trim($existing['agent']), $_SESSION['username']) !== 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: You do not own this record'
    ]);
    exit;
}

// Retrieve form values with sanitization/trimming
$data = [
    'date' => trim($_POST['date'] ?? ''),
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
    'date', 'company_name', 'location', 'region', 'contact_details',
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

// Update query
$updateQuery = "UPDATE `$tableName` SET 
    `date` = :date,
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
    WHERE `record_id` = :record_id";

$stmt = $db->prepare($updateQuery);
$stmt->execute(array_merge($data, ['record_id' => $record_id]));

echo json_encode([
    'success' => true,
    'message' => 'Record successfully updated!',
    'data' => [
        'record_id' => $record_id,
        'table' => $tableName
    ]
]);
?>
