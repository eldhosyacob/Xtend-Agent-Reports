<?php
require_once __DIR__ . '/../config/session.php';

// Check session authentication
if (!isset($_SESSION['id']) || !isset($_SESSION['username'])) {
    die("Unauthorized: Please log in first.");
}

require_once __DIR__ . '/../config/database.php';
$db = getDatabaseConnection();

if (!$db) {
    die("Database connection failed.");
}

$conditions = [];
$params = [];

// 1. Agent Name
$agent = trim($_GET['agent_name'] ?? ($_GET['agent'] ?? ''));
if ($agent !== '') {
    $conditions[] = "pr.agent_name LIKE :agent_name";
    $params['agent_name'] = '%' . $agent . '%';
}

// 2. From Date
$fromDate = trim($_GET['from_date'] ?? '');
if ($fromDate !== '') {
    $conditions[] = "pr.date >= :from_date";
    $params['from_date'] = $fromDate;
}

// 3. To Date
$toDate = trim($_GET['to_date'] ?? '');
if ($toDate !== '') {
    $conditions[] = "pr.date <= :to_date";
    $params['to_date'] = $toDate;
}

// 4. Client Name
$clientName = trim($_GET['client_name'] ?? ($_GET['company_name'] ?? ''));
if ($clientName !== '') {
    $conditions[] = "pr.client_name LIKE :client_name";
    $params['client_name'] = '%' . $clientName . '%';
}

// 5. Product Name
$productName = trim($_GET['product_name'] ?? '');
if ($productName !== '') {
    $conditions[] = "pr.product_name LIKE :product_name";
    $params['product_name'] = '%' . $productName . '%';
}

// 6. Product ID
$productId = trim($_GET['product_id'] ?? '');
if ($productId !== '') {
    $conditions[] = "pr.product_id LIKE :product_id";
    $params['product_id'] = '%' . $productId . '%';
}

// 7. Software Version
$softwareVersion = trim($_GET['software_version'] ?? '');
if ($softwareVersion !== '') {
    $conditions[] = "pr.software_version LIKE :software_version";
    $params['software_version'] = '%' . $softwareVersion . '%';
}

// 8. LCD ID
$lcdId = trim($_GET['lcd_id'] ?? '');
if ($lcdId !== '') {
    $conditions[] = "pr.lcd_id LIKE :lcd_id";
    $params['lcd_id'] = '%' . $lcdId . '%';
}

// 9. Hardware ID / Card ID
$hardwareId = trim($_GET['hardware_id'] ?? '');
if ($hardwareId !== '') {
    $conditions[] = "pr.hardware_id LIKE :hardware_id";
    $params['hardware_id'] = '%' . $hardwareId . '%';
}

// 10. HASP ID
$haspId = trim($_GET['hasp_id'] ?? '');
if ($haspId !== '') {
    $conditions[] = "pr.hasp_id LIKE :hasp_id";
    $params['hasp_id'] = '%' . $haspId . '%';
}

// Optional general search query q
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $conditions[] = "(pr.agent_name LIKE :q_agent 
        OR pr.client_name LIKE :q_client 
        OR pr.product_name LIKE :q_prod 
        OR pr.product_id LIKE :q_pid 
        OR pr.software_version LIKE :q_software_ver
        OR pr.lcd_id LIKE :q_lcd
        OR pr.hardware_id LIKE :q_hw
        OR pr.hasp_id LIKE :q_hasp
        OR pr.project_id LIKE :q_proj 
        OR pr.id LIKE :q_id 
        OR pr.status LIKE :q_status)";
    $params['q_agent'] = '%' . $q . '%';
    $params['q_client'] = '%' . $q . '%';
    $params['q_prod'] = '%' . $q . '%';
    $params['q_pid'] = '%' . $q . '%';
    $params['q_software_ver'] = '%' . $q . '%';
    $params['q_lcd'] = '%' . $q . '%';
    $params['q_hw'] = '%' . $q . '%';
    $params['q_hasp'] = '%' . $q . '%';
    $params['q_proj'] = '%' . $q . '%';
    $params['q_id'] = '%' . $q . '%';
    $params['q_status'] = '%' . $q . '%';
}

$whereClause = "";
if (!empty($conditions)) {
    $whereClause = " WHERE " . implode(" AND ", $conditions);
}

$sql = "
    SELECT 
        pr.id,
        pr.project_id,
        pr.subtask_id,
        pr.agent_name,
        pr.date,
        pr.product_name,
        pr.product_sub_category,
        pr.product_type,
        pr.assigned_by,
        pr.product_id,
        pr.software_version,
        pr.lcd_id,
        pr.hardware_id,
        pr.hasp_id,
        pr.client_name,
        pr.youtrack_id,
        pr.received_from,
        pr.sent_to,
        pr.testing_description,
        pr.start_time,
        pr.end_time,
        pr.total_time,
        pr.status,
        p.project_name,
        pst.title AS subtask_title
    FROM `project_reports` pr
    LEFT JOIN `projects` p 
        ON (pr.project_id = p.project_id OR pr.project_id = CAST(p.id AS CHAR))
    LEFT JOIN `project_subtasks` pst 
        ON (pr.subtask_id = pst.subtask_id OR (pr.subtask_id = CAST(pst.id AS CHAR) AND pr.project_id = pst.project_id))
    {$whereClause}
    ORDER BY pr.date DESC, pr.id DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = "project_reports_search_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($output, [
    'Sl. No.',
    'Report ID',
    'Date',
    'Agent Name',
    'Client Name',
    'Product Name',
    'Product ID',
    'Software Version',
    'LCD ID',
    'Hardware ID',
    'HASP ID',
    'Project ID',
    'Project Name',
    'Subtask',
    'Status',
    'Total Time',
    'Testing Description'
]);

$slNo = 1;
foreach ($records as $row) {
    fputcsv($output, [
        $slNo++,
        $row['id'] ?? '',
        $row['date'] ?? '',
        $row['agent_name'] ?? '',
        $row['client_name'] ?? '',
        $row['product_name'] ?? '',
        $row['product_id'] ?? '',
        $row['software_version'] ?? '',
        $row['lcd_id'] ?? '',
        $row['hardware_id'] ?? '',
        $row['hasp_id'] ?? '',
        $row['project_id'] ?? '',
        $row['project_name'] ?? '',
        $row['subtask_title'] ?? ($row['subtask_id'] ?? ''),
        $row['status'] ?? '',
        $row['total_time'] ?? '',
        $row['testing_description'] ?? ''
    ]);
}

fclose($output);
exit;

