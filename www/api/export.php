<?php
require_once __DIR__ . '/../config/auth_check.php';

// $db is initialized by config/auth_check.php
if (!$db) {
    die("Database connection failed.");
}

// Retrieve user's department from DB to ensure it's accurate and fresh
$userStmt = $db->prepare("SELECT department FROM users WHERE id = :id LIMIT 1");
$userStmt->execute(['id' => $_SESSION['id']]);
$user = $userStmt->fetch();

if (!$user) {
    die("User not found.");
}

$user_department = trim($user['department']);

// Determine the table(s) to query based on user's department
$tables_to_query = [];
if (strcasecmp($user_department, 'Voice Logger') === 0) {
    $tables_to_query = ['support_details'];
} elseif (strcasecmp($user_department, 'IVR') === 0) {
    $tables_to_query = ['ivr_details'];
} else {
    // If the user belongs to another department (e.g. Manager), check department_select parameter
    $dept_select = isset($_GET['department_select']) ? trim($_GET['department_select']) : '';
    if (strcasecmp($dept_select, 'voice_logger') === 0) {
        $tables_to_query = ['support_details'];
    } elseif (strcasecmp($dept_select, 'ivr') === 0) {
        $tables_to_query = ['ivr_details'];
    } else {
        // Query both tables if no department is specified
        $tables_to_query = ['support_details', 'ivr_details'];
    }
}

// Build dynamic WHERE conditions and parameter bindings
$conditions = [];
$params = [];

if (isset($_GET['agent']) && trim($_GET['agent']) !== '') {
    $conditions[] = "`agent` LIKE :agent";
    $params['agent'] = '%' . trim($_GET['agent']) . '%';
}
if (isset($_GET['from_date']) && trim($_GET['from_date']) !== '') {
    $conditions[] = "`date` >= :from_date";
    $params['from_date'] = trim($_GET['from_date']);
}
if (isset($_GET['to_date']) && trim($_GET['to_date']) !== '') {
    $conditions[] = "`date` <= :to_date";
    $params['to_date'] = trim($_GET['to_date']);
}
if (isset($_GET['company_name']) && trim($_GET['company_name']) !== '') {
    $conditions[] = "`company_name` LIKE :company_name";
    $params['company_name'] = '%' . trim($_GET['company_name']) . '%';
}
if (isset($_GET['location']) && trim($_GET['location']) !== '') {
    $conditions[] = "`location` LIKE :location";
    $params['location'] = '%' . trim($_GET['location']) . '%';
}
if (isset($_GET['hardware_details']) && trim($_GET['hardware_details']) !== '') {
    $conditions[] = "`hardware_details` LIKE :hardware_details";
    $params['hardware_details'] = '%' . trim($_GET['hardware_details']) . '%';
}
if (isset($_GET['ticket_id']) && trim($_GET['ticket_id']) !== '') {
    $conditions[] = "`ticket_id` LIKE :ticket_id";
    $params['ticket_id'] = '%' . trim($_GET['ticket_id']) . '%';
}
if (isset($_GET['case_id']) && trim($_GET['case_id']) !== '') {
    $conditions[] = "`case_id` LIKE :case_id";
    $params['case_id'] = '%' . trim($_GET['case_id']) . '%';
}
if (isset($_GET['product_category']) && trim($_GET['product_category']) !== '') {
    $conditions[] = "`product_category` LIKE :product_category";
    $params['product_category'] = '%' . trim($_GET['product_category']) . '%';
}
if (isset($_GET['issue_category']) && trim($_GET['issue_category']) !== '') {
    $conditions[] = "`issue_category` LIKE :issue_category";
    $params['issue_category'] = '%' . trim($_GET['issue_category']) . '%';
}
if (isset($_GET['support_status']) && trim($_GET['support_status']) !== '') {
    $conditions[] = "`support_status` = :support_status";
    $params['support_status'] = trim($_GET['support_status']);
}

$all_records = [];

foreach ($tables_to_query as $table) {
    $where_clause = "";
    if (!empty($conditions)) {
        $where_clause = " WHERE " . implode(" AND ", $conditions);
    }

    $query = "SELECT * FROM `$table`" . $where_clause;
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $dept_label = ($table === 'support_details') ? 'Voice Logger' : 'IVR';
    foreach ($records as &$record) {
        $record['record_department'] = $dept_label;
    }
    unset($record); // Break reference

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

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="search_results_' . date('Ymd_His') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel support
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Output CSV headers
fputcsv($output, [
    'Record ID',
    'Department',
    'Date',
    'Agent Name',
    'Company Name',
    'Location',
    'Region',
    'Email',
    'Phone',
    'Contact Details',
    'Product Category',
    'Issue Category',
    'Issue Type',
    'Issue Notes/Details',
    'Support Category',
    'Software Details',
    'Hardware Details',
    'Solution',
    'Support Start Time',
    'Support End Time',
    'Total Time',
    'Support Status',
    'Ticket ID / Reason'
]);

// Loop through records and output each row
foreach ($all_records as $r) {
    $rec_id = (!empty($r['record_id']) && $r['record_id'] !== 'null' && $r['record_id'] !== 'NULL') ? $r['record_id'] : ($r['id'] ?? '');
    fputcsv($output, [
        $rec_id,
        $r['record_department'] ?? '',
        $r['date'] ?? '',
        $r['agent'] ?? '',
        $r['company_name'] ?? '',
        $r['location'] ?? '',
        $r['region'] ?? '',
        $r['email'] ?? '',
        $r['phone'] ?? '',
        $r['contact_details'] ?? '',
        $r['product_category'] ?? '',
        $r['issue_category'] ?? '',
        $r['issue_type'] ?? '',
        $r['issue_details'] ?? '',
        $r['support_category'] ?? '',
        $r['software_details'] ?? '',
        $r['hardware_details'] ?? '',
        $r['solution'] ?? '',
        $r['support_start_time'] ?? '',
        $r['support_end_time'] ?? '',
        $r['total_time'] ?? '',
        $r['support_status'] ?? '',
        $r['ticket_id'] ?? ''
    ]);
}

fclose($output);
exit;
