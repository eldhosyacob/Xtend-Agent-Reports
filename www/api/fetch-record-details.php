<?php
require_once('../config/session.php');
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
    exit;
}

// Check session authentication
if (!isset($_SESSION['id']) || !isset($_SESSION['username'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized: Please log in first'
    ]);
    exit;
}

$db = getDatabaseConnection();
if (!$db) {
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed.'
    ]);
    exit;
}

$record_id = trim($_GET['record_id'] ?? '');
if ($record_id === '') {
    echo json_encode([
        'success' => false,
        'error' => 'Record ID is required.'
    ]);
    exit;
}

$record = null;
$tableName = '';

// Check support_details first (Voice Logger)
$stmt = $db->prepare("SELECT * FROM `support_details` WHERE `record_id` = :record_id LIMIT 1");
$stmt->execute(['record_id' => $record_id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if ($record) {
    $tableName = 'support_details';
} else {
    // Check ivr_details (IVR)
    $stmt = $db->prepare("SELECT * FROM `ivr_details` WHERE `record_id` = :record_id LIMIT 1");
    $stmt->execute(['record_id' => $record_id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($record) {
        $tableName = 'ivr_details';
    }
}

// Fallback to auto-increment id if not found by record_id
if (!$record && is_numeric($record_id)) {
    $stmt = $db->prepare("SELECT * FROM `support_details` WHERE `id` = :id LIMIT 1");
    $stmt->execute(['id' => (int)$record_id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($record) {
        $tableName = 'support_details';
    } else {
        $stmt = $db->prepare("SELECT * FROM `ivr_details` WHERE `id` = :id LIMIT 1");
        $stmt->execute(['id' => (int)$record_id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($record) {
            $tableName = 'ivr_details';
        }
    }
}

if ($record) {
    // Attach department information based on the table where the record was found
    $record['department'] = ($tableName === 'support_details') ? 'Voice Logger' : 'IVR';
    echo json_encode([
        'success' => true,
        'data' => $record
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Record not found.'
    ]);
}
?>
