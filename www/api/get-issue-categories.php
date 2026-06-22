<?php
require_once('../config/session.php');

header('Content-Type: application/json');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$odsFile = dirname(__DIR__, 2) . '/uploads/IssueCategory/IssueCategory.ods';
$cacheFile = dirname(__DIR__, 2) . '/cache/issue_categories.json';

$categoryMap = [];
$cacheValid = false;

if (file_exists($odsFile)) {
    $odsModifiedTime = filemtime($odsFile);
    if (file_exists($cacheFile)) {
        $cacheData = json_decode(file_get_contents($cacheFile), true);
        if (
            is_array($cacheData) &&
            isset($cacheData['filemtime']) &&
            $cacheData['filemtime'] === $odsModifiedTime &&
            isset($cacheData['categories'])
        ) {
            $categoryMap = $cacheData['categories'];
            $cacheValid = true;
        }
    }
    
    if (!$cacheValid) {
        try {
            $spreadsheet = IOFactory::load($odsFile);
            $worksheet = $spreadsheet->getActiveSheet();
            $highestColumn = $worksheet->getHighestColumn();
            $highestRow = $worksheet->getHighestRow();
            $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
            
            $headers = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $val = $worksheet->getCell([$col, 1])->getValue();
                if ($val !== null && trim((string)$val) !== '') {
                    $headers[$col] = trim((string)$val);
                }
            }
            
            foreach ($headers as $colIndex => $originalHeader) {
                // Normalize header name (remove space, underscore, lowercase)
                $normalizedHeader = strtolower(str_replace([' ', '_'], '', $originalHeader));
                
                $issues = [];
                for ($row = 2; $row <= $highestRow; $row++) {
                    $val = $worksheet->getCell([$colIndex, $row])->getValue();
                    if ($val !== null && trim((string)$val) !== '') {
                        $issues[] = trim((string)$val);
                    }
                }
                
                $categoryMap[$normalizedHeader] = [
                    'original_name' => $originalHeader,
                    'issues' => array_values(array_unique($issues))
                ];
            }
            
            // Cache the parsed mapping
            $cacheDir = dirname($cacheFile);
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }
            file_put_contents($cacheFile, json_encode([
                'filemtime' => $odsModifiedTime,
                'categories' => $categoryMap
            ], JSON_UNESCAPED_UNICODE));
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error reading ODS file: ' . $e->getMessage()
            ]);
            exit;
        }
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Source spreadsheet not found.'
    ]);
    exit;
}

// Check if a specific product category is requested
$productCategory = isset($_GET['product_category']) ? trim($_GET['product_category']) : '';

if ($productCategory !== '') {
    $normalizedSearch = strtolower(str_replace([' ', '_'], '', $productCategory));
    if (isset($categoryMap[$normalizedSearch])) {
        echo json_encode([
            'success' => true,
            'original_name' => $categoryMap[$normalizedSearch]['original_name'],
            'data' => $categoryMap[$normalizedSearch]['issues']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Product Category not found in the spreadsheet mappings.'
        ]);
    }
} else {
    // Return all categories
    echo json_encode([
        'success' => true,
        'data' => $categoryMap
    ]);
}
?>
