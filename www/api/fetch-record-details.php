<?php
require_once __DIR__ . '/../config/session.php';
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

$record_id = trim($_GET['record_id'] ?? $_GET['id'] ?? '');
if ($record_id === '') {
    echo json_encode([
        'success' => false,
        'error' => 'Record ID is required.'
    ]);
    exit;
}

$requested_dept = trim($_GET['department'] ?? $_GET['dept'] ?? $_GET['department_select'] ?? '');

$record = null;
$tableName = '';

// Determine table search order based on requested department or user session department
$tables = ['support_details', 'ivr_details']; // default order
if (!empty($requested_dept)) {
    if (strcasecmp($requested_dept, 'IVR') === 0 || strtolower(str_replace(['_', ' '], '', $requested_dept)) === 'ivr') {
        $tables = ['ivr_details', 'support_details'];
    } elseif (strcasecmp($requested_dept, 'Voice Logger') === 0 || strtolower(str_replace(['_', ' '], '', $requested_dept)) === 'voicelogger') {
        $tables = ['support_details', 'ivr_details'];
    }
} elseif (isset($_SESSION['department']) && !empty($_SESSION['department'])) {
    $user_dept = trim($_SESSION['department']);
    if (strcasecmp($user_dept, 'IVR') === 0) {
        $tables = ['ivr_details', 'support_details'];
    } elseif (strcasecmp($user_dept, 'Voice Logger') === 0) {
        $tables = ['support_details', 'ivr_details'];
    }
}

// 1. Try fetching by record_id column in order of target tables
foreach ($tables as $tbl) {
    $stmt = $db->prepare("SELECT * FROM `$tbl` WHERE `record_id` = :record_id LIMIT 1");
    $stmt->execute(['record_id' => $record_id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($res) {
        $record = $res;
        $tableName = $tbl;
        break;
    }
}

// 2. Fallback to auto-increment id column if not found by record_id column
if (!$record && is_numeric($record_id)) {
    foreach ($tables as $tbl) {
        $stmt = $db->prepare("SELECT * FROM `$tbl` WHERE `id` = :id LIMIT 1");
        $stmt->execute(['id' => (int)$record_id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res) {
            $record = $res;
            $tableName = $tbl;
            break;
        }
    }
}

if ($record) {
    // Attach department information based on the table where the record was found
    $record['department'] = ($tableName === 'support_details') ? 'Voice Logger' : 'IVR';

    $case_id = !empty($record['case_id']) ? $record['case_id'] : ($record['record_id'] ?: $record_id);

    // Determine if this record is the latest entry for its case
    $stmtMax = $db->prepare("SELECT MAX(id) as max_id FROM `$tableName` WHERE COALESCE(NULLIF(case_id, ''), record_id) = :case_id");
    $stmtMax->execute(['case_id' => $case_id]);
    $maxRow = $stmtMax->fetch(PDO::FETCH_ASSOC);
    $record['is_latest'] = ($maxRow && !empty($maxRow['max_id'])) ? ((int)$record['id'] === (int)$maxRow['max_id']) : true;

    $target_record_id = !empty($record['record_id']) ? $record['record_id'] : $record_id;

    // Query support_time_history for only the corresponding record_id
    $historyStmt = $db->prepare("SELECT record_id, support_start_time, support_end_time, date FROM `support_time_history` WHERE `record_id` = :record_id ORDER BY id ASC");
    $historyStmt->execute(['record_id' => $target_record_id]);
    $timeHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

    // If time_history is empty but record has support_start_time, fallback to record's times
    if (empty($timeHistory) && !empty($record['support_start_time'])) {
        $timeHistory = [[
            'record_id' => $target_record_id,
            'support_start_time' => $record['support_start_time'],
            'support_end_time' => $record['support_end_time'],
            'date' => $record['date'] ?? date('Y-m-d')
        ]];
    }

    $record['time_history'] = $timeHistory;

    // Fetch support status history
    $statusHistoryStmt = $db->prepare("SELECT agent, support_status, date FROM `support_status_history` WHERE `case_id` = :case_id ORDER BY id ASC");
    $statusHistoryStmt->execute(['case_id' => $case_id]);
    $record['status_history'] = $statusHistoryStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch attached files from file-uploads directory (including date-wise folders) for corresponding case_id
    $attachments = [];
    $uploadDir = __DIR__ . '/../file-uploads/';
    if (is_dir($uploadDir) && !empty($case_id)) {
        $casePrefix = $case_id . '_';
        $recPrefix = $target_record_id . '_';
        $items = scandir($uploadDir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $itemPath = $uploadDir . $item;

            if (is_dir($itemPath)) {
                // Date-wise folder (e.g. YYYY-MM-DD)
                $subFiles = scandir($itemPath);
                foreach ($subFiles as $f) {
                    if ($f === '.' || $f === '..') continue;
                    if (strpos($f, $casePrefix) === 0 || strpos($f, $recPrefix) === 0) {
                        $matchedPrefix = (strpos($f, $casePrefix) === 0) ? $casePrefix : $recPrefix;
                        $rest = substr($f, strlen($matchedPrefix));
                        $origName = preg_replace('/^([0-9]+_)?[a-f0-9]{13}_/', '', $rest);
                        $filePath = $itemPath . '/' . $f;
                        $sizeBytes = file_exists($filePath) ? filesize($filePath) : 0;
                        
                        $units = ['B', 'KB', 'MB', 'GB'];
                        $pow = $sizeBytes ? floor(log($sizeBytes, 1024)) : 0;
                        $pow = min($pow, count($units) - 1);
                        $sizeFormatted = round($sizeBytes / pow(1024, $pow), 2) . ' ' . $units[$pow];

                        $attachments[] = [
                            'filename' => $f,
                            'name' => $origName,
                            'size' => $sizeFormatted,
                            'url' => 'file-uploads/' . rawurlencode($item) . '/' . rawurlencode($f)
                        ];
                    }
                }
            } elseif (is_file($itemPath)) {
                // Root file (legacy uploads)
                $f = $item;
                if (strpos($f, $casePrefix) === 0 || strpos($f, $recPrefix) === 0) {
                    $matchedPrefix = (strpos($f, $casePrefix) === 0) ? $casePrefix : $recPrefix;
                    $rest = substr($f, strlen($matchedPrefix));
                    $origName = preg_replace('/^([0-9]+_)?[a-f0-9]{13}_/', '', $rest);
                    $sizeBytes = filesize($itemPath);
                    
                    $units = ['B', 'KB', 'MB', 'GB'];
                    $pow = $sizeBytes ? floor(log($sizeBytes, 1024)) : 0;
                    $pow = min($pow, count($units) - 1);
                    $sizeFormatted = round($sizeBytes / pow(1024, $pow), 2) . ' ' . $units[$pow];

                    $attachments[] = [
                        'filename' => $f,
                        'name' => $origName,
                        'size' => $sizeFormatted,
                        'url' => 'file-uploads/' . rawurlencode($f)
                    ];
                }
            }
        }
    }
    $record['attachments'] = $attachments;

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
