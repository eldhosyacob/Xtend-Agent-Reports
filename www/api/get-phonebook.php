<?php
require_once('../config/session.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

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

// Check if the entered Contact Details, Phone, and Email combination exists in phonebook
if (isset($_GET['check']) && $_GET['check'] === '1') {
    $contactDetails = trim($_GET['contact_details'] ?? '');
    $phone = trim($_GET['phone'] ?? '');
    $email = trim($_GET['email'] ?? '');

    if ($contactDetails === '' && $phone === '' && $email === '') {
        echo json_encode([
            'success' => true,
            'exists' => true
        ]);
        exit;
    }

    $conditions = [];
    $params = [];

    if ($contactDetails !== '') {
        $conditions[] = "LOWER(TRIM(COALESCE(`contact_details`, ''))) = LOWER(:contact_details)";
        $params['contact_details'] = $contactDetails;
    }
    if ($phone !== '') {
        $conditions[] = "TRIM(COALESCE(`phone`, '')) = :phone";
        $params['phone'] = $phone;
    }
    if ($email !== '') {
        $conditions[] = "LOWER(TRIM(COALESCE(`email`, ''))) = LOWER(:email)";
        $params['email'] = $email;
    }

    $sql = "SELECT `id` FROM `phonebook` WHERE " . implode(' AND ', $conditions) . " LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'exists' => (bool)$stmt->fetch()
    ]);
    exit;
}

$allowedFields = ['email', 'phone', 'contact_details', 'all'];
$field = trim($_GET['field'] ?? 'all');
if (!in_array($field, $allowedFields, true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid search field'
    ]);
    exit;
}

$query = trim($_GET['q'] ?? '');
if ($query === '') {
    echo json_encode([
        'success' => true,
        'data' => []
    ]);
    exit;
}

$exact = isset($_GET['exact']) && $_GET['exact'] === '1';

if ($exact && $field !== 'all') {
    $sql = "SELECT id, contact_details, email, phone
            FROM phonebook
            WHERE `$field` IS NOT NULL AND LOWER(TRIM(`$field`)) = LOWER(:exact_val)
            LIMIT 2";
    $stmt = $db->prepare($sql);
    $stmt->execute(['exact_val' => $query]);
    $rows = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => $rows
    ]);
    exit;
}

$escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
$likePattern = '%' . $escaped . '%';
$prefixPattern = $escaped . '%';

if ($field === 'all') {
    $sql = "SELECT id, contact_details, email, phone
            FROM phonebook
            WHERE contact_details LIKE :like_contact ESCAPE '\\\\'
               OR email LIKE :like_email ESCAPE '\\\\'
               OR phone LIKE :like_phone ESCAPE '\\\\'
            ORDER BY contact_details ASC
            LIMIT 15";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'like_contact' => $likePattern,
        'like_email' => $likePattern,
        'like_phone' => $likePattern
    ]);
} else {
    $sql = "SELECT id, contact_details, email, phone
            FROM phonebook
            WHERE `$field` IS NOT NULL AND `$field` LIKE :like_val ESCAPE '\\\\'
            ORDER BY (`$field` LIKE :prefix_val ESCAPE '\\\\') DESC, `$field` ASC
            LIMIT 15";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'like_val' => $likePattern,
        'prefix_val' => $prefixPattern
    ]);
}

$rows = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'data' => $rows
]);

