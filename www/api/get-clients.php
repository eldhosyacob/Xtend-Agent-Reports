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

$clients = [];

$odsFile = dirname(__DIR__, 2) . '/uploads/ClientList/ClientList.ods';
$cacheFile = dirname(__DIR__, 2) . '/cache/clients.json';

if (file_exists($odsFile)) {
    $odsModifiedTime = filemtime($odsFile);
    $useCache = false;

    if (file_exists($cacheFile)) {
        $cache = json_decode(file_get_contents($cacheFile), true);
        if (
            is_array($cache) &&
            isset($cache['filemtime']) &&
            isset($cache['clients']) &&
            $cache['filemtime'] === $odsModifiedTime
        ) {
            $clients = $cache['clients'];
            $useCache = true;
        }
    }

    if (!$useCache) {
        $clients = [];
        $zip = new ZipArchive();

        if ($zip->open($odsFile) === true) {
            $xmlString = $zip->getFromName('content.xml');
            $zip->close();

            if ($xmlString !== false) {
                $xml = simplexml_load_string($xmlString);
                if ($xml !== false) {
                    $xml->registerXPathNamespace(
                        'table',
                        'urn:oasis:names:tc:opendocument:xmlns:table:1.0'
                    );
                    $xml->registerXPathNamespace(
                        'text',
                        'urn:oasis:names:tc:opendocument:xmlns:text:1.0'
                    );

                    $tables = $xml->xpath('//table:table');
                    foreach ($tables as $table) {
                        $rows = $table->xpath('./table:table-row');
                        if (empty($rows)) {
                            continue;
                        }

                        $headerCells = $rows[0]->xpath('./table:table-cell');
                        if (empty($headerCells)) {
                            continue;
                        }

                        $headerText = trim((string)($headerCells[0]->xpath('.//text:p')[0] ?? ''));
                        if ($headerText !== 'Client List') {
                            continue;
                        }

                        for ($i = 1; $i < count($rows); $i++) {
                            $cells = $rows[$i]->xpath('./table:table-cell');
                            if (empty($cells)) {
                                continue;
                            }

                            $clientName = trim(
                                (string)($cells[0]->xpath('.//text:p')[0] ?? '')
                            );

                            if ($clientName !== '') {
                                $clients[] = $clientName;
                            }
                        }
                        break;
                    }
                }
            }
        }

        $clients = array_values(array_unique($clients));
        sort($clients, SORT_NATURAL | SORT_FLAG_CASE);

        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        file_put_contents(
            $cacheFile,
            json_encode(
                [
                    'filemtime' => $odsModifiedTime,
                    'clients' => $clients
                ],
                JSON_UNESCAPED_UNICODE
            )
        );
    }
}

echo json_encode([
    'success' => true,
    'data' => $clients
]);
?>
