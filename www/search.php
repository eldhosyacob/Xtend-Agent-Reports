<?php
// Secure the page - ensure user is authenticated
require_once 'config/auth_check.php';

// Determine if the user is a manager to display the department field
$user_department = isset($_SESSION['department']) ? strtolower($_SESSION['department']) : '';

if (empty($user_department)) {
    // Fallback: Query from db if not in session
    require_once 'config/database.php';
    $db = getDatabaseConnection();
    if ($db && isset($_SESSION['id'])) {
        try {
            $stmt = $db->prepare('SELECT department FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['id']]);
            $user_dep = $stmt->fetchColumn();
            if ($user_dep) {
                $user_department = strtolower($user_dep);
                $_SESSION['department'] = $user_dep;
            }
        } catch (PDOException $e) {
            error_log("Search page DB error: " . $e->getMessage());
        }
    }
}
$is_manager = ($user_department === 'manager');

// Fetch clients from ODS file for autocomplete (cached in session with timestamp check)
$clients = [];
$odsFile = dirname(__DIR__) . '/uploads/ClientList/ClientList.ods';
$cacheFile = dirname(__DIR__) . '/cache/clients.json';

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
?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Advanced Search Reports | Xtend Agent Reports</title>
    <link rel="shortcut icon" href="images/favicon.png" />
    <link rel="stylesheet" href="styles/search.css">
    <link rel="stylesheet" href="styles/header-sidebar.css">
    <link rel="stylesheet" href="styles/common.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  </head>

  <body>
    <div class="search-page-container page-containers">
      <div class="form-card-wrapper">
            <div class="form-header">
            <h2> Search Records</h2>
            <p>Complete the search criteria below to filter the records list.</p>
            </div>

            <form method="get" action="search-results.php" autocomplete="off" id="searchForm">
                <div class="grid-form">
                <!-- 1. AGENT NAME -->
                <div class="form-group col-6">
                        <label for="agent">Agent Name</label>
                        <div class="form-control-wrapper">
                            <i class="fa-regular fa-user input-icon"></i>
                            <input type="text" id="agent" name="agent" class="form-control" placeholder="Enter agent username">
                        </div>
                    </div>
        
                    <!-- 2. FROM DATE -->
                    <div class="form-group col-6">
                        <label for="from_date">From Date</label>
                        <div class="form-control-wrapper">
                            <i class="fa-regular fa-calendar input-icon"></i>
                            <input type="date" id="from_date" name="from_date" class="form-control">
                        </div>
                    </div>

                    <!-- 3. TO DATE -->
                    <div class="form-group col-6">
                        <label for="to_date">To Date</label>
                        <div class="form-control-wrapper">
                            <i class="fa-regular fa-calendar input-icon"></i>
                            <input type="date" id="to_date" name="to_date" class="form-control">
                        </div>
                    </div>

                    <!-- 4. COMPANY NAME -->
                    <div class="form-group col-6">
                        <label for="company_name">Company Name</label>
                        <div class="form-control-wrapper autocomplete-wrapper">
                            <i class="fa-regular fa-building input-icon"></i>
                            <input type="text" id="company_name" name="company_name" class="form-control" placeholder="Enter company / client name" autocomplete="off">
                            <div class="autocomplete-suggestions" id="companySuggestions"></div>
                        </div>
                    </div>


                    <!-- 5. LOCATION -->
                    <div class="form-group col-6">
                        <label for="location">Location</label>
                        <div class="form-control-wrapper">
                            <i class="fa-solid fa-location-dot input-icon"></i>
                            <input type="text" id="location" name="location" class="form-control" placeholder="City, State or Country">
                        </div>
                    </div>

                    <!-- 6. HARDWARE DETAILS -->
                    <div class="form-group col-6">
                        <label for="hardware_details">Hardware Details</label>
                        <div class="form-control-wrapper">
                            <i class="fa-solid fa-microchip input-icon"></i>
                            <input type="text" id="hardware_details" name="hardware_details" class="form-control" placeholder="Device Id">
                        </div>
                    </div>

                    <!-- 7. TICKET ID -->
                    <div class="form-group col-6">
                        <label for="ticket_id">Ticket ID</label>
                        <div class="form-control-wrapper">
                            <i class="fa-solid fa-ticket input-icon"></i>
                            <input type="text" id="ticket_id" name="ticket_id" class="form-control" placeholder="Enter Ticket ID or Reason">
                        </div>
                    </div>

                    <!-- 8. DEPARTMENT SELECT (If Manager) -->
                    <?php if ($is_manager): ?>
                        <div class="form-group col-6">
                            <label for="department_select">Department</label>
                            <div class="form-control-wrapper select-wrapper">
                                <i class="fa-solid fa-sitemap input-icon"></i>
                                <select id="department_select" name="department_select" class="form-control">
                                <option value="" selected>-- Select Department --</option>
                                <option value="ivr">IVR</option>
                                <option value="voice_logger">Voice Logger</option>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Form Action Buttons -->
                    <div class="form-actions">
                        <button type="button" id="resetBtn" class="btn btn-secondary">
                            <i class="fa-solid fa-rotate-left"></i> Reset
                        </button>
                        <button type="submit" id="searchBtn" class="btn btn-primary">
                            <i class="fa-solid fa-magnifying-glass"></i> Search
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php include 'components/header-sidebar.php'; ?>
    
    <script src="plugins/jquery-3.7.1.min.js"></script>
    <script>
      $(document).ready(function() {
        // Autocomplete suggestions loading
        const clientList = <?php echo json_encode($clients); ?> || [];
        const $input = $('#company_name');
        const $suggestions = $('#companySuggestions');
        let currentFocus = -1;

        $input.on('input', function() {
          const val = this.value.trim().toLowerCase();
          $suggestions.empty().hide();
          currentFocus = -1;

          if (!val) return;

          // Filter client list matches (case-insensitive, max 10 matches)
          const matches = clientList.filter(client => client.toLowerCase().includes(val)).slice(0, 10);

          if (matches.length > 0) {
            matches.forEach((match, index) => {
              const $suggestion = $('<div class="autocomplete-suggestion"></div>')
                .text(match)
                .attr('data-index', index)
                .on('click', function() {
                  $input.val(match);
                  $suggestions.empty().hide();
                });
              $suggestions.append($suggestion);
            });
            $suggestions.show();
          }
        });

        // Autocomplete Keyboard navigation
        $input.on('keydown', function(e) {
          const $items = $suggestions.find('.autocomplete-suggestion');
          if (!$items.length) return;

          if (e.keyCode === 40) { // Arrow Down
            currentFocus++;
            setActive($items);
            e.preventDefault();
          } else if (e.keyCode === 38) { // Arrow Up
            currentFocus--;
            setActive($items);
            e.preventDefault();
          } else if (e.keyCode === 13) { // Enter
            if (currentFocus > -1) {
              if ($items.eq(currentFocus).length) {
                $items.eq(currentFocus).trigger('click');
                e.preventDefault();
              }
            }
          } else if (e.keyCode === 27) { // Escape
            $suggestions.empty().hide();
            currentFocus = -1;
          }
        });

        function setActive($items) {
          $items.removeClass('active');
          if (currentFocus >= $items.length) currentFocus = 0;
          if (currentFocus < 0) currentFocus = $items.length - 1;
          const $activeItem = $items.eq(currentFocus);
          $activeItem.addClass('active');

          // Scroll helper
          const containerHeight = $suggestions.height();
          const itemHeight = $activeItem.outerHeight();
          const itemTop = $activeItem.position().top;

          if (itemTop + itemHeight > containerHeight) {
            $suggestions.scrollTop($suggestions.scrollTop() + itemTop + itemHeight - containerHeight);
          } else if (itemTop < 0) {
            $suggestions.scrollTop($suggestions.scrollTop() + itemTop);
          }
        }

        // Close autocomplete when clicking outside
        $(document).on('click', function(e) {
          if (!$(e.target).closest('.autocomplete-wrapper').length) {
            $suggestions.empty().hide();
          }
        });

        // Reset button logic
        $('#resetBtn').on('click', function() {
          if (confirm('Are you sure you want to clear the search criteria?')) {
            $('#searchForm')[0].reset();
            $suggestions.empty().hide();
          }
        });
      });
    </script>
  </body>

</html>