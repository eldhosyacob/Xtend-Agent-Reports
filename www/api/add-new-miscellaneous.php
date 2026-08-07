<?php
require_once '../config/session.php';

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

require_once '../config/database.php';
$db = getDatabaseConnection();

if (!$db) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

// Support both standard form POST data and JSON payload
$input = $_POST;
if (empty($input)) {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $jsonInput = json_decode($rawInput, true);
        if (is_array($jsonInput)) {
            $input = $jsonInput;
        }
    }
}

$date = isset($input['date']) ? trim($input['date']) : '';
$agent = isset($input['agent']) ? trim($input['agent']) : '';
$miscellaneous_category = isset($input['miscellaneous_category']) ? trim($input['miscellaneous_category']) : '';
$description = isset($input['description']) ? trim($input['description']) : '';
$start_time = isset($input['start_time']) ? trim($input['start_time']) : null;
$end_time = isset($input['end_time']) ? trim($input['end_time']) : null;
$total_time = isset($input['total_time']) ? trim($input['total_time']) : null;

// Validation checks
if (empty($date)) {
    echo json_encode([
        'success' => false,
        'message' => 'Date is required'
    ]);
    exit;
}

if (empty($agent)) {
    echo json_encode([
        'success' => false,
        'message' => 'Agent is required'
    ]);
    exit;
}

if (empty($miscellaneous_category)) {
    echo json_encode([
        'success' => false,
        'message' => 'Miscellaneous Category is required'
    ]);
    exit;
}

// Format time strings (ensure HH:MM:SS format)
if (!empty($start_time) && strlen($start_time) === 5) {
    $start_time .= ':00';
}
if (!empty($end_time) && strlen($end_time) === 5) {
    $end_time .= ':00';
}
if (!empty($total_time) && strlen($total_time) === 5) {
    $total_time .= ':00';
}

// Automatic total_time calculation fallback
if (!empty($start_time) && !empty($end_time) && empty($total_time)) {
    $startTs = strtotime("1970-01-01 $start_time");
    $endTs = strtotime("1970-01-01 $end_time");
    if ($startTs !== false && $endTs !== false) {
        $diff = $endTs - $startTs;
        if ($diff < 0) {
            $diff += 86400; // Account for overnight transition
        }
        $hours = floor($diff / 3600);
        $minutes = floor(($diff % 3600) / 60);
        $seconds = $diff % 60;
        $total_time = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }
}

// Insert into database using prepared statement
$stmt = $db->prepare("INSERT INTO `miscellaneous_details` (`date`, `agent`, `miscellaneous_category`, `description`, `start_time`, `end_time`, `total_time`, `created_at`) VALUES (:date, :agent, :category, :description, :start_time, :end_time, :total_time, NOW())");

$inserted = $stmt->execute([
    'date' => $date,
    'agent' => $agent,
    'category' => $miscellaneous_category,
    'description' => $description !== '' ? $description : null,
    'start_time' => !empty($start_time) ? $start_time : null,
    'end_time' => !empty($end_time) ? $end_time : null,
    'total_time' => !empty($total_time) ? $total_time : null
]);

if ($inserted) {
    echo json_encode([
        'success' => true,
        'message' => 'Miscellaneous details added successfully',
        'id' => $db->lastInsertId()
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save miscellaneous details'
    ]);
}
