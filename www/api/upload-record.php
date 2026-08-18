<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

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

$user_department = trim($user['department'] ?? '');

// Restrict upload access strictly to Manager department
if (strcasecmp($user_department, 'Manager') !== 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Only Managers are permitted to upload records.'
    ]);
    exit;
}

// Determine target database table based on department selection
$selected_department = trim($_POST['department'] ?? '');
$tableName = '';

if (strcasecmp($selected_department, 'Voice Logger') === 0) {
    $tableName = 'support_details';
} elseif (strcasecmp($selected_department, 'IVR') === 0) {
    $tableName = 'ivr_details';
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Please select a valid department (Voice Logger or IVR).'
    ]);
    exit;
}

// Validate uploaded file
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['file']['error'] ?? -1;
    echo json_encode([
        'success' => false,
        'message' => 'File upload failed. Code: ' . $errCode
    ]);
    exit;
}

$fileTmpPath = $_FILES['file']['tmp_name'];
$fileName = $_FILES['file']['name'];
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$allowedExtensions = ['csv', 'ods', 'xlsx', 'xls'];

if (!in_array($fileExtension, $allowedExtensions)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid file format. Only CSV, ODS, XLSX, and XLS files are allowed.'
    ]);
    exit;
}

// Load spreadsheet using PhpSpreadsheet
try {
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fileTmpPath);
} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to read spreadsheet file: ' . $e->getMessage()
    ]);
    exit;
}

$worksheet = $spreadsheet->getActiveSheet();
$rows = $worksheet->toArray(null, true, true, true);

if (empty($rows) || count($rows) < 2) {
    echo json_encode([
        'success' => false,
        'message' => 'The uploaded file is empty or does not contain any data rows.'
    ]);
    exit;
}

// Map spreadsheet header strings to database column names (normalized: lowercased, non-alphanumeric removed)
$headerToDbMap = [
    'date' => 'date',
    'agent' => 'agent',
    'agentname' => 'agent',
    'companyname' => 'company_name',
    'company' => 'company_name',
    'dealername' => 'dealer_name',
    'dealer' => 'dealer_name',
    'location' => 'location',
    'region' => 'region',
    'email' => 'email',
    'phone' => 'phone',
    'contactdetails' => 'contact_details',
    'contact' => 'contact_details',
    'productcategory' => 'product_category',
    'issuecategory' => 'issue_category',
    'issuetype' => 'issue_type',
    'issuenotesdetails' => 'issue_details',
    'issuenotes' => 'issue_details',
    'issuedetails' => 'issue_details',
    'supportcategory' => 'support_category',
    'softwaredetails' => 'software_details',
    'hardwaredetails' => 'hardware_details',
    'productmodel' => 'product_model',
    'solution' => 'solution',
    'supportstarttime' => 'support_start_time',
    'starttime' => 'support_start_time',
    'supportendtime' => 'support_end_time',
    'endtime' => 'support_end_time',
    'totaltime' => 'total_time',
    'supportstatus' => 'support_status',
    'status' => 'support_status',
    'reason' => 'reason',
    'ticketid' => 'ticket_id',
    'ticket' => 'ticket_id',
    'caseid' => 'case_id',
    'recordid' => 'record_id',
];

// Function to normalize header string (remove UTF-8 BOM & non-alphanumeric chars)
function normalizeHeaderKey($str) {
    $str = preg_replace('/[\x{FEFF}\x{FFFE}]/u', '', (string)$str);
    $str = preg_replace('/^\xEF\xBB\xBF/', '', $str);
    $str = strtolower(trim($str));
    return preg_replace('/[^a-z0-9]/', '', $str);
}

// Extract headers from Row 1
$headerRow = array_shift($rows);
$columnMap = []; // Maps excel column key ('A', 'B'...) to database column name

foreach ($headerRow as $colKey => $headerVal) {
    if ($headerVal === null || trim((string)$headerVal) === '') continue;
    $normHeader = normalizeHeaderKey($headerVal);
    if (isset($headerToDbMap[$normHeader])) {
        $columnMap[$colKey] = $headerToDbMap[$normHeader];
    }
}

if (empty($columnMap)) {
    echo json_encode([
        'success' => false,
        'message' => 'No recognizable database columns found in the file headers.'
    ]);
    exit;
}

// Function to fetch next auto XT-100xXX number
function getInitialNextNum($db) {
    $next_num = 1000;
    $supQuery = "
        SELECT MAX(CASE 
            WHEN record_id REGEXP '^XT-[0-9]+[a-zA-Z]{2}$' THEN CAST(SUBSTRING(record_id, 4, LENGTH(record_id)-5) AS UNSIGNED)
            WHEN record_id REGEXP '^[0-9]+[a-zA-Z]{2}$' THEN CAST(SUBSTRING(record_id, 1, LENGTH(record_id)-2) AS UNSIGNED)
            ELSE 0
        END) AS max_num FROM support_details
    ";
    $stmt1 = $db->query($supQuery);
    if ($stmt1 && $r1 = $stmt1->fetch()) {
        if ($r1['max_num'] !== null) $next_num = max($next_num, (int)$r1['max_num'] + 1);
    }
    $ivrQuery = "
        SELECT MAX(CASE 
            WHEN record_id REGEXP '^XT-[0-9]+[a-zA-Z]{2}$' THEN CAST(SUBSTRING(record_id, 4, LENGTH(record_id)-5) AS UNSIGNED)
            WHEN record_id REGEXP '^[0-9]+[a-zA-Z]{2}$' THEN CAST(SUBSTRING(record_id, 1, LENGTH(record_id)-2) AS UNSIGNED)
            ELSE 0
        END) AS max_num FROM ivr_details
    ";
    $stmt2 = $db->query($ivrQuery);
    if ($stmt2 && $r2 = $stmt2->fetch()) {
        if ($r2['max_num'] !== null) $next_num = max($next_num, (int)$r2['max_num'] + 1);
    }
    return $next_num;
}

// Function to check if a case_id exists and generate suffixed record_id (e.g. case_id_1, case_id_2)
function checkCaseIdAndGetRecordId($db, $case_id) {
    $stmtSup = $db->prepare("SELECT record_id FROM support_details WHERE case_id = :cid1 OR record_id = :cid2 OR record_id LIKE :cprefix");
    $stmtSup->execute(['cid1' => $case_id, 'cid2' => $case_id, 'cprefix' => $case_id . '_%']);
    $idsSup = $stmtSup->fetchAll(PDO::FETCH_COLUMN);

    $stmtIvr = $db->prepare("SELECT record_id FROM ivr_details WHERE case_id = :cid1 OR record_id = :cid2 OR record_id LIKE :cprefix");
    $stmtIvr->execute(['cid1' => $case_id, 'cid2' => $case_id, 'cprefix' => $case_id . '_%']);
    $idsIvr = $stmtIvr->fetchAll(PDO::FETCH_COLUMN);

    $all_rids = array_unique(array_merge($idsSup, $idsIvr));

    if (empty($all_rids)) {
        return false; // Case ID does not exist in database
    }

    $suffixes = [];
    foreach ($all_rids as $rid) {
        if (strpos($rid, $case_id . '_') === 0) {
            $suffix = substr($rid, strlen($case_id) + 1);
            if (is_numeric($suffix)) {
                $suffixes[] = (int)$suffix;
            }
        }
    }

    $next_suffix = empty($suffixes) ? 1 : max($suffixes) + 1;
    return $case_id . '_' . $next_suffix;
}

$next_num = getInitialNextNum($db);

// Prepare ivr_details max id if needed
$next_ivr_id = 1;
if ($tableName === 'ivr_details') {
    $maxIdStmt = $db->query("SELECT MAX(id) as max_id FROM ivr_details");
    if ($maxIdRow = $maxIdStmt->fetch()) {
        if ($maxIdRow['max_id'] !== null) {
            $next_ivr_id = (int)$maxIdRow['max_id'] + 1;
        }
    }
}

$importedCount = 0;
$details = [];
$rowIndex = 1; // Row 1 was header

foreach ($rows as $rowCells) {
    $rowIndex++;

    // Check if entire row is empty
    $nonEmptyCells = array_filter($rowCells, fn($v) => trim((string)$v) !== '');
    if (empty($nonEmptyCells)) {
        continue;
    }

    // Build row data dictionary for mapped columns only
    $rowRecord = [];
    foreach ($columnMap as $colKey => $dbCol) {
        $val = isset($rowCells[$colKey]) ? trim((string)$rowCells[$colKey]) : '';
        $rowRecord[$dbCol] = $val;
    }

    // Handle date formatting
    if (isset($rowRecord['date']) && $rowRecord['date'] !== '') {
        $rawDate = $rowRecord['date'];
        if (is_numeric($rawDate)) {
            try {
                $dtObj = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($rawDate);
                $rowRecord['date'] = $dtObj->format('Y-m-d');
            } catch (\Exception $e) {
                $rowRecord['date'] = date('Y-m-d');
            }
        } else {
            $timeParsed = strtotime($rawDate);
            $rowRecord['date'] = $timeParsed ? date('Y-m-d', $timeParsed) : date('Y-m-d');
        }
    } else {
        $rowRecord['date'] = date('Y-m-d');
    }

    // Default support_status if empty
    if (!isset($rowRecord['support_status']) || $rowRecord['support_status'] === '') {
        $rowRecord['support_status'] = 'Pending';
    }

    // Convert empty string optional fields to null for MySQL database compatibility
    $nullableFields = ['email', 'phone', 'support_start_time', 'support_end_time'];
    foreach ($nullableFields as $nField) {
        if (isset($rowRecord[$nField]) && $rowRecord[$nField] === '') {
            $rowRecord[$nField] = null;
        }
    }

    // Case ID and Record ID allocation logic
    $provided_case_id = trim($rowRecord['case_id'] ?? $rowRecord['record_id'] ?? '');
    $final_case_id = '';
    $final_record_id = '';
    $status_type = 'inserted';
    $status_label = 'Inserted New Record';

    $found_existing_case = false;

    if ($provided_case_id !== '') {
        // Check if case_id exists in support_details or ivr_details
        $suffixed_record_id = checkCaseIdAndGetRecordId($db, $provided_case_id);
        if ($suffixed_record_id !== false) {
            // Case ID exists in DB! Allocate record_id under existing case_id (e.g. case_id_1)
            $final_case_id = $provided_case_id;
            $final_record_id = $suffixed_record_id;
            $status_type = 'reopened';
            $status_label = 'Follow-up Record (' . $suffixed_record_id . ')';
            $found_existing_case = true;
        }
    }

    // If no case_id was provided OR if the provided case_id was NOT found in DB, auto-generate a new ID
    if (!$found_existing_case) {
        $letters = chr(rand(65, 90)) . chr(rand(65, 90));
        $generated_id = 'XT-' . $next_num . $letters;
        $next_num++;

        $final_record_id = $generated_id;
        $final_case_id = $generated_id;
        $status_type = 'inserted';
        $status_label = 'Inserted New Record';
    }

    $rowRecord['record_id'] = $final_record_id;
    $rowRecord['case_id'] = $final_case_id;

    // Handle ivr_details manual ID increment if needed
    if ($tableName === 'ivr_details' && !isset($rowRecord['id'])) {
        $rowRecord['id'] = $next_ivr_id++;
    }

    // Dynamically construct prepared insert query
    $cols = array_keys($rowRecord);
    $columns_str = implode(',', array_map(fn($c) => "`$c`", $cols));
    $placeholders_str = implode(',', array_map(fn($c) => ":$c", $cols));

    $insertQuery = "INSERT INTO `$tableName` ($columns_str) VALUES ($placeholders_str)";
    $insertStmt = $db->prepare($insertQuery);

    try {
        $insertStmt->execute($rowRecord);
        $importedCount++;

        // If this record belongs to an existing case in DB, synchronize support_status across all records under this case_id
        if ($found_existing_case && !empty($rowRecord['support_status'])) {
            $newStatus = $rowRecord['support_status'];
            $updateSup = $db->prepare("UPDATE support_details SET support_status = :status WHERE case_id = :cid1 OR record_id = :cid2");
            $updateSup->execute(['status' => $newStatus, 'cid1' => $final_case_id, 'cid2' => $final_case_id]);

            $updateIvr = $db->prepare("UPDATE ivr_details SET support_status = :status WHERE case_id = :cid1 OR record_id = :cid2");
            $updateIvr->execute(['status' => $newStatus, 'cid1' => $final_case_id, 'cid2' => $final_case_id]);
        }

        $details[] = [
            'sl_no' => count($details) + 1,
            'row_num' => count($details) + 1,
            'date' => $rowRecord['date'] ?? '',
            'agent' => $rowRecord['agent'] ?? '',
            'company_name' => $rowRecord['company_name'] ?? '',
            'dealer_name' => $rowRecord['dealer_name'] ?? '',
            'product_category' => $rowRecord['product_category'] ?? '',
            'record_id' => $final_record_id,
            'case_id' => $final_case_id,
            'status_type' => $status_type,
            'status_label' => $status_label
        ];
    } catch (PDOException $e) {
        $details[] = [
            'sl_no' => count($details) + 1,
            'row_num' => count($details) + 1,
            'date' => $rowRecord['date'] ?? '',
            'agent' => $rowRecord['agent'] ?? '',
            'company_name' => $rowRecord['company_name'] ?? '',
            'dealer_name' => $rowRecord['dealer_name'] ?? '',
            'product_category' => $rowRecord['product_category'] ?? '',
            'record_id' => $final_record_id,
            'case_id' => $final_case_id,
            'status_type' => 'failed',
            'status_label' => 'Failed: ' . $e->getMessage()
        ];
    }
}

$deptLabel = ($tableName === 'support_details') ? 'Voice Logger' : 'IVR';

echo json_encode([
    'success' => true,
    'message' => "Successfully uploaded {$importedCount} records into {$deptLabel}.",
    'imported_count' => $importedCount,
    'details' => $details
]);
exit;
