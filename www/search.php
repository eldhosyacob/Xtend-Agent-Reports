<?php
// Secure the page - ensure user is authenticated
require_once 'config/auth_check.php';
require_once 'config/database.php';

$db = getDatabaseConnection();
if (!$db) {
    die("Database connection failed.");
}

// Determine if the user is a manager to display the department field
$user_department = isset($_SESSION['department']) ? strtolower($_SESSION['department']) : '';

if (empty($user_department)) {
    // Fallback: Query from db if not in session
    if (isset($_SESSION['id'])) {
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

// Fetch list of agents from users table (filtered by department for non-managers)
$agents = [];
if (!$is_manager && !empty($user_department)) {
    try {
        $normalized_user_dept = strtolower(str_replace('_', ' ', $user_department));
        $stmt = $db->prepare("SELECT DISTINCT real_name FROM users WHERE (LOWER(REPLACE(department, '_', ' ')) = :dept OR LOWER(REPLACE(department, '_', ' ')) = 'manager') AND real_name IS NOT NULL AND TRIM(real_name) != '' ORDER BY real_name ASC");
        $stmt->execute(['dept' => $normalized_user_dept]);
        $agents = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Failed to fetch agents list: " . $e->getMessage());
    }
}


// Fetch clients from company_list table in the database
$clients = [];
if ($db) {
    try {
        $stmt = $db->prepare("SELECT company_name FROM company_list ORDER BY company_name ASC");
        $stmt->execute();
        $clients = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Failed to fetch clients list from DB: " . $e->getMessage());
    }
}

// Fetch IVR Product Categories if user belongs to IVR department
$ivrProducts = [];
if (isset($user_department) && strtolower($user_department) === 'ivr') {
    $prodCacheFile = dirname(__DIR__, 1) . '/cache/product_categories_ivr.json';
    $prodOdsFile = dirname(__DIR__, 1) . '/uploads/IssueCategory/ProductCategoryIVR.ods';
    
    if (file_exists($prodCacheFile) && file_exists($prodOdsFile) && filemtime($prodOdsFile) === filemtime($prodCacheFile)) {
        $ivrProducts = json_decode(file_get_contents($prodCacheFile), true);
    }
    
    if (empty($ivrProducts) && file_exists($prodOdsFile)) {
        try {
            require_once __DIR__ . '/vendor/autoload.php';
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($prodOdsFile);
            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = $worksheet->getHighestRow();
            for ($row = 1; $row <= $highestRow; $row++) {
                $val = $worksheet->getCell([1, $row])->getValue();
                if ($val !== null && trim((string)$val) !== '') {
                    $ivrProducts[] = trim((string)$val);
                }
            }
            $ivrProducts = array_values(array_unique($ivrProducts));
            
            // Cache it
            $cacheDir = dirname($prodCacheFile);
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }
            file_put_contents($prodCacheFile, json_encode($ivrProducts, JSON_UNESCAPED_UNICODE));
            touch($prodCacheFile, filemtime($prodOdsFile));
        } catch (Exception $e) {
            // Fail silently
        }
    }
}

// Fetch IVR Issue Categories if user belongs to IVR department
$ivrIssues = [];
if (isset($user_department) && strtolower($user_department) === 'ivr') {
    $ivrCacheFile = dirname(__DIR__, 1) . '/cache/issue_categories_ivr.json';
    if (file_exists($ivrCacheFile)) {
        $ivrCacheData = json_decode(file_get_contents($ivrCacheFile), true);
        if (is_array($ivrCacheData) && isset($ivrCacheData['categories'])) {
            foreach ($ivrCacheData['categories'] as $cat) {
                if (isset($cat['issues']) && is_array($cat['issues'])) {
                    $ivrIssues = array_merge($ivrIssues, $cat['issues']);
                }
            }
            $ivrIssues = array_values(array_unique($ivrIssues));
        }
    }
    
    if (empty($ivrIssues)) {
        $ivrOdsFile = dirname(__DIR__, 1) . '/uploads/IssueCategory/IssueCategoryIVR.ods';
        if (file_exists($ivrOdsFile)) {
            try {
                require_once __DIR__ . '/vendor/autoload.php';
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($ivrOdsFile);
                $worksheet = $spreadsheet->getActiveSheet();
                $highestRow = $worksheet->getHighestRow();
                $highestColumn = $worksheet->getHighestColumn();
                $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
                
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    for ($row = 2; $row <= $highestRow; $row++) {
                        $val = $worksheet->getCell([$col, $row])->getValue();
                        if ($val !== null && trim((string)$val) !== '') {
                            $ivrIssues[] = trim((string)$val);
                        }
                    }
                }
                $ivrIssues = array_values(array_unique($ivrIssues));
            } catch (Exception $e) {
                // Fail silently
            }
        }
    }
}

// Fetch Miscellaneous Categories from DB
$miscCategories = [];
if ($db) {
    try {
        $stmt = $db->query("SELECT categories FROM miscellaneous_categories ORDER BY categories ASC");
        $miscCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Failed to fetch miscellaneous categories: " . $e->getMessage());
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
              <div class="header-menu-container">
                <div class="header-spacer"></div>
                <h2>Search Records</h2>
                <div class="form-control-wrapper select-wrapper header-select-wrapper">
                  <i class="fa-solid fa-layer-group input-icon"></i>
                  <select id="search_category_menu" class="form-control header-category-select">
                    <option value="support_details" selected>Support Details</option>
                    <option value="miscellaneous">Miscellaneous</option>
                  </select>
                </div>
              </div>
              <p id="searchSubheading">Use the search criteria below to filter the records list.</p>
            </div>

            <form method="get" action="search-results.php" autocomplete="off" id="searchForm">
                <!-- Support Details Search Criteria Grid -->
                <div id="supportDetailsGrid" class="grid-form">
                    <!-- 1. DEPARTMENT SELECT (If Manager) -->
                    <?php if ($is_manager): ?>
                      <div class="form-group col-3">
                        <label for="department_select">Department</label>
                        <div class="form-control-wrapper select-wrapper">
                            <i class="fa-solid fa-sitemap input-icon"></i>
                          <select id="department_select" name="department_select" class="form-control">
                          <option value="" selected>-- Select Department --</option>
                          <option value="ivr">IVR</option>
                          <option value="voice_logger">Voice Logger</option>
                          <option value="manager">Manager</option>
                          </select>
                        </div>
                      </div>
                    <?php endif; ?>

                    <!-- 2. AGENT NAME -->
                    <div class="form-group col-3">
                        <label for="agent">Agent Name</label>
                        <div class="form-control-wrapper select-wrapper">
                            <i class="fa-regular fa-user input-icon"></i>
                            <select id="agent" name="agent" class="form-control">
                                <option value="" selected>-- Select Agent --</option>
                                <?php foreach ($agents as $agentName): ?>
                                    <option value="<?php echo htmlspecialchars($agentName); ?>">
                                        <?php echo htmlspecialchars(strtoupper($agentName)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
        
                    <!-- 3. FROM DATE -->
                    <div class="form-group col-3">
                        <label for="from_date">From Date</label>
                        <div class="form-control-wrapper">
                            <i class="fa-regular fa-calendar input-icon"></i>
                            <input type="date" id="from_date" name="from_date" class="form-control">
                        </div>
                    </div>

                    <!-- 4. TO DATE -->
                    <div class="form-group col-3">
                        <label for="to_date">To Date</label>
                        <div class="form-control-wrapper">
                            <i class="fa-regular fa-calendar input-icon"></i>
                            <input type="date" id="to_date" name="to_date" class="form-control">
                        </div>
                    </div>

                    <!-- 5. COMPANY NAME -->
                    <div class="form-group col-3">
                        <label for="company_name">Company Name</label>
                        <div class="form-control-wrapper autocomplete-wrapper">
                            <i class="fa-regular fa-building input-icon"></i>
                            <input type="text" id="company_name" name="company_name" class="form-control" placeholder="Enter company / client name" autocomplete="off">
                            <div class="autocomplete-suggestions" id="companySuggestions"></div>
                        </div>
                    </div>

                    <!-- 6. DEALER NAME -->
                    <div class="form-group col-3">
                        <label for="dealer_name">Dealer Name</label>
                        <div class="form-control-wrapper autocomplete-wrapper">
                            <i class="fa-solid fa-user-tie input-icon"></i>
                            <input type="text" id="dealer_name" name="dealer_name" class="form-control" placeholder="Enter dealer name" autocomplete="off">
                            <div class="autocomplete-suggestions" id="dealerSuggestions"></div>
                        </div>
                    </div>

                    <!-- 7. LOCATION -->
                    <div class="form-group col-3">
                        <label for="location">Location</label>
                        <div class="form-control-wrapper">
                            <i class="fa-solid fa-location-dot input-icon"></i>
                            <input type="text" id="location" name="location" class="form-control" placeholder="City, State or Country">
                        </div>
                    </div>

                    <!-- 8. PHONE -->
                    <div class="form-group col-3">
                        <label for="phone">Phone</label>
                        <div class="form-control-wrapper">
                            <i class="fa-solid fa-phone input-icon"></i>
                            <input type="text" id="phone" name="phone" class="form-control" placeholder="Enter Phone Number">
                        </div>
                    </div>

                    <!-- 9. HARDWARE DETAILS -->
                    <div class="form-group col-3">
                        <label for="hardware_details">Hardware Details</label>
                        <div class="form-control-wrapper">
                            <i class="fa-solid fa-microchip input-icon"></i>
                            <input type="text" id="hardware_details" name="hardware_details" class="form-control" placeholder="Device Id">
                        </div>
                    </div>

                    <!-- 9. PRODUCT CATEGORY -->
                    <div class="form-group col-3">
                        <label for="product_category">Product Category</label>
                        <div class="form-control-wrapper select-wrapper">
                            <i class="fa-solid fa-cubes input-icon"></i>
                            <select id="product_category" name="product_category" class="form-control">
                                <option value="" selected>-- Select Product Category --</option>
                                <?php if (isset($user_department) && strtolower($user_department) === 'ivr'): ?>
                                    <?php foreach ($ivrProducts as $prod): ?>
                                        <option value="<?php echo htmlspecialchars($prod); ?>"><?php echo htmlspecialchars($prod); ?></option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="ANALOG_LOGGER">ANALOG_LOGGER</option>
                                    <option value="PRI_LOGGER">PRI_LOGGER</option>
                                    <option value="DIGITAL_EXTENSION">DIGITAL_EXTENSION</option>
                                    <option value="IP_LOGGER">IP_LOGGER</option>
                                    <option value="XTEND_SMARTLOG">XTEND_SMARTLOG</option>
                                    <option value="CALLBILLING">CALLBILLING</option>
                                    <option value="CMS_HO">CMS_HO</option>
                                    <option value="IVR_GATEWAY">IVR_GATEWAY</option>
                                    <option value="STANDALONE_LOGGER">STANDALONE_LOGGER</option>
                                    <option value="ACTIVE_LOGGER">ACTIVE_LOGGER</option>
                                    <option value="XTEND_ONCALL">XTEND_ONCALL</option>
                                    <option value="XTEND_VX">XTEND_VX</option>
                                    <option value="XTEND_SX2">XTEND_SX2</option>
                                    <option value="XTEND_SX">XTEND_SX</option>
                                    <option value="LINUX_STANDALONE">LINUX_STANDALONE</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <!-- 10. ISSUE CATEGORY -->
                    <div class="form-group col-3">
                        <label for="issue_category">Issue Category</label>
                        <?php if (isset($user_department) && strtolower($user_department) === 'ivr'): ?>
                            <div class="form-control-wrapper select-wrapper">
                                <i class="fa-solid fa-tags input-icon"></i>
                                <select id="issue_category" name="issue_category" class="form-control">
                                    <option value="" selected>-- Select Issue Category --</option>
                                    <?php foreach ($ivrIssues as $issue): ?>
                                        <option value="<?php echo htmlspecialchars($issue); ?>"><?php echo htmlspecialchars($issue); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <div class="form-control-wrapper autocomplete-wrapper">
                                <i class="fa-solid fa-tags input-icon"></i>
                                <input type="text" id="issue_category" name="issue_category" class="form-control" placeholder="Select Product Category first" autocomplete="off" disabled>
                                <div class="autocomplete-suggestions" id="issueCategorySuggestions"></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 11. STATUS -->
                    <div class="form-group col-3">
                        <label for="support_status">Status</label>
                        <div class="form-control-wrapper select-wrapper">
                            <i class="fa-solid fa-circle-info input-icon"></i>
                            <select id="support_status" name="support_status" class="form-control">
                                <option value="" selected>-- Select Status --</option>
                                <option value="Closed">Closed</option>
                                <option value="Pending">Pending</option>
                                <option value="Under Observation">Under Observation</option>
                                <option value="Escalated">Escalated</option>
                                <option value="Escalated to Presales">Escalated to Presales</option>
                                <option value="Closed-Device Replaced">Closed-Device Replaced</option>
                                <option value="Closed-No Response">Closed-No Response</option>
                            </select>
                        </div>
                    </div>

                    <!-- 12. TICKET ID -->
                    <div class="form-group col-3">
                        <label for="ticket_id">Ticket ID</label>
                        <div class="form-control-wrapper">
                            <i class="fa-solid fa-ticket input-icon"></i>
                            <input type="text" id="ticket_id" name="ticket_id" class="form-control" placeholder="Enter Ticket ID">
                        </div>
                    </div>

                    <!-- 13. CASE ID -->
                    <div class="form-group col-3">
                        <label for="case_id">Case ID</label>
                        <div class="form-control-wrapper">
                            <i class="fa-solid fa-folder-open input-icon"></i>
                            <input type="text" id="case_id" name="case_id" class="form-control" placeholder="Enter Case ID">
                        </div>
                    </div>
                </div>

                <!-- Miscellaneous Search Criteria Grid -->
                <div id="miscellaneousGrid" class="grid-form" style="display: none;">
                    <?php if ($is_manager): ?>
                      <div class="form-group col-3">
                        <label for="misc_department_select">Department</label>
                        <div class="form-control-wrapper select-wrapper">
                            <i class="fa-solid fa-sitemap input-icon"></i>
                          <select id="misc_department_select" name="department" class="form-control">
                          <option value="" selected>-- Select Department --</option>
                          <option value="Voice Logger">Voice Logger</option>
                          <option value="IVR">IVR</option>
                          <option value="Manager">Manager</option>
                          </select>
                        </div>
                      </div>
                    <?php endif; ?>

                    <!-- Agent Name -->
                    <div class="form-group col-3">
                        <label for="misc_agent">Agent Name</label>
                        <div class="form-control-wrapper select-wrapper">
                            <i class="fa-regular fa-user input-icon"></i>
                            <select id="misc_agent" name="agent" class="form-control">
                                <option value="" selected>-- Select Agent --</option>
                                <?php foreach ($agents as $agentName): ?>
                                    <option value="<?php echo htmlspecialchars($agentName); ?>">
                                        <?php echo htmlspecialchars(strtoupper($agentName)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- From Date -->
                    <div class="form-group col-3">
                        <label for="misc_from_date">From Date</label>
                        <div class="form-control-wrapper">
                            <i class="fa-regular fa-calendar input-icon"></i>
                            <input type="date" id="misc_from_date" name="from_date" class="form-control">
                        </div>
                    </div>

                    <!-- To Date -->
                    <div class="form-group col-3">
                        <label for="misc_to_date">To Date</label>
                        <div class="form-control-wrapper">
                            <i class="fa-regular fa-calendar input-icon"></i>
                            <input type="date" id="misc_to_date" name="to_date" class="form-control">
                        </div>
                    </div>

                    <!-- Miscellaneous Category -->
                    <div class="form-group col-3">
                        <label for="misc_category">Miscellaneous Category</label>
                        <div class="form-control-wrapper select-wrapper">
                            <i class="fa-solid fa-layer-group input-icon"></i>
                            <select id="misc_category" name="miscellaneous_category" class="form-control">
                                <option value="" selected>-- Select Category --</option>
                                <?php foreach ($miscCategories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>">
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

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
        // Set maximum date for calendars to today (hide future days and year)
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        const todayStr = `${yyyy}-${mm}-${dd}`;

        $('#from_date, #misc_from_date').attr('max', todayStr);
        $('#to_date, #misc_to_date').attr('max', todayStr);

        // Date range validation on change
        $('#from_date, #to_date, #misc_from_date, #misc_to_date').on('change', function() {
          const isMisc = $(this).attr('id').startsWith('misc_');
          const fromDate = isMisc ? $('#misc_from_date').val() : $('#from_date').val();
          const toDate = isMisc ? $('#misc_to_date').val() : $('#to_date').val();

          if (fromDate && toDate) {
            if (new Date(fromDate) > new Date(toDate)) {
              alert('From Date cannot be greater than To Date. Also, To Date should be greater than or equal to From Date.');
              $(this).val('');
            }
          }
        });

        // Toggle search category grid and form action
        $('#search_category_menu').on('change', function() {
          const mode = $(this).val();
          if (mode === 'miscellaneous') {
            $('#supportDetailsGrid').hide().find('input, select').prop('disabled', true);
            $('#miscellaneousGrid').show().find('input, select').prop('disabled', false);
            $('#searchForm').attr('action', 'miscellaneous-search-results.php');
            $('#searchSubheading').text('Use the search criteria below to filter miscellaneous records.');
          } else {
            $('#miscellaneousGrid').hide().find('input, select').prop('disabled', true);
            $('#supportDetailsGrid').show().find('input, select').prop('disabled', false);
            $('#searchForm').attr('action', 'search-results.php');
            $('#searchSubheading').text('Use the search criteria below to filter the records list.');
          }
        }).trigger('change');

        // Autocomplete suggestions loading for Company Name & Dealer Name
        const clientList = <?php echo json_encode($clients); ?> || [];

        function setupAutocomplete($input, $suggestions) {
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
        }

        setupAutocomplete($('#company_name'), $('#companySuggestions'));
        setupAutocomplete($('#dealer_name'), $('#dealerSuggestions'));

        // Close autocomplete when clicking outside
        $(document).on('click', function(e) {
          if (!$(e.target).closest('#company_name').parent().length) {
            $('#companySuggestions').empty().hide();
          }
          if (!$(e.target).closest('#dealer_name').parent().length) {
            $('#dealerSuggestions').empty().hide();
          }
          if (typeof $issueSuggestions !== 'undefined' && !$(e.target).closest('#issue_category').parent().length) {
            $issueSuggestions.empty().hide();
            issueCurrentFocus = -1;
          }
        });

        // Dynamic Issue Category Autocomplete based on Product Category
        <?php if (!isset($user_department) || strtolower($user_department) !== 'ivr'): ?>
        let issueCategories = [];
        const $issueInput = $('#issue_category');
        const $issueSuggestions = $('#issueCategorySuggestions');
        let issueCurrentFocus = -1;

        // Fetch categories when Product Category changes
        $('#product_category').on('change', function() {
          const selectedCategory = $(this).val();
          $issueInput.val('').prop('disabled', true).attr('placeholder', 'Loading issue categories...');
          $issueSuggestions.empty().hide();
          issueCategories = [];

          if (!selectedCategory) {
            $issueInput.attr('placeholder', 'Select Product Category first');
            return;
          }

          $.ajax({
            url: 'api/get-issue-categories.php',
            type: 'GET',
            data: { product_category: selectedCategory },
            dataType: 'json',
            success: function(response) {
              if (response.success && Array.isArray(response.data)) {
                issueCategories = response.data;
                $issueInput.prop('disabled', false).attr('placeholder', 'Search or select issue category...');
              } else {
                showNotification(response.message || 'Failed to load issue categories.', 'error');
                $issueInput.attr('placeholder', 'Error loading categories');
              }
            },
            error: function() {
              showNotification('Failed to load issue categories from server.', 'error');
              $issueInput.attr('placeholder', 'Error loading categories');
            }
          });
        });

        // Trigger focus and input events for autocomplete filtering
        $issueInput.on('focus input', function() {
          const val = this.value.trim().toLowerCase();
          $issueSuggestions.empty().hide();
          issueCurrentFocus = -1;

          if (issueCategories.length === 0) return;

          const matches = val 
            ? issueCategories.filter(cat => cat.toLowerCase().includes(val))
            : issueCategories;

          const limitMatches = matches.slice(0, 30);

          if (limitMatches.length > 0) {
            limitMatches.forEach((match, index) => {
              const highlighted = val ? 
                match.replace(new RegExp('(' + val.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&') + ')', 'gi'), '<strong>$1</strong>') : 
                match;
              
              const $suggestion = $('<div class="autocomplete-suggestion"></div>')
                .html(highlighted)
                .attr('data-index', index)
                .on('click', function() {
                  $issueInput.val(match);
                  $issueSuggestions.empty().hide();
                });
              $issueSuggestions.append($suggestion);
            });
            $issueSuggestions.show();
          }
        });

        // Keyboard navigation for issue categories
        $issueInput.on('keydown', function(e) {
          const $items = $issueSuggestions.find('.autocomplete-suggestion');
          if (!$items.length) return;

          if (e.keyCode === 40) { // Arrow Down
            issueCurrentFocus++;
            setIssueActive($items);
            e.preventDefault();
          } else if (e.keyCode === 38) { // Arrow Up
            issueCurrentFocus--;
            setIssueActive($items);
            e.preventDefault();
          } else if (e.keyCode === 13) { // Enter
            if (issueCurrentFocus > -1) {
              if ($items.eq(issueCurrentFocus).length) {
                $items.eq(issueCurrentFocus).trigger('click');
                e.preventDefault();
              }
            }
          } else if (e.keyCode === 27) { // Escape
            $issueSuggestions.empty().hide();
            issueCurrentFocus = -1;
          }
        });

        function setIssueActive($items) {
          $items.removeClass('active');
          if (issueCurrentFocus >= $items.length) issueCurrentFocus = 0;
          if (issueCurrentFocus < 0) issueCurrentFocus = $items.length - 1;
          const $activeItem = $items.eq(issueCurrentFocus);
          $activeItem.addClass('active');
          
          const containerHeight = $issueSuggestions.height();
          const itemHeight = $activeItem.outerHeight();
          const itemTop = $activeItem.position().top;

          if (itemTop + itemHeight > containerHeight) {
            $issueSuggestions.scrollTop($issueSuggestions.scrollTop() + itemTop + itemHeight - containerHeight);
          } else if (itemTop < 0) {
            $issueSuggestions.scrollTop($issueSuggestions.scrollTop() + itemTop);
          }
        }
        <?php endif; ?>

        // Dynamic Agent Name loading based on Department select for Manager
        $('#department_select, #misc_department_select').on('change', function() {
          const selectedDept = $(this).val();
          const $agentSelect = $('#agent');
          const $miscAgentSelect = $('#misc_agent');

          $agentSelect.html('<option value="" selected>-- Select Agent --</option>');
          $miscAgentSelect.html('<option value="" selected>-- Select Agent --</option>');

          if (!selectedDept) return;

          $.ajax({
            url: 'api/get-agents.php',
            type: 'GET',
            data: { department: selectedDept },
            dataType: 'json',
            success: function(response) {
              if (response.success && Array.isArray(response.data)) {
                response.data.forEach(function(agentName) {
                  const optionHtml = $('<option></option>').val(agentName).text(agentName.toUpperCase());
                  $agentSelect.append(optionHtml.clone());
                  $miscAgentSelect.append(optionHtml.clone());
                });
              } else {
                showNotification(response.message || 'Failed to load agents.', 'error');
              }
            },
            error: function() {
              showNotification('Failed to load agents from server.', 'error');
            }
          });
        });

        // Reset button logic
        $('#resetBtn').on('click', function() {
          if (confirm('Are you sure you want to clear the search criteria?')) {
            $('#searchForm')[0].reset();
            $suggestions.empty().hide();
            if (typeof $issueSuggestions !== 'undefined') $issueSuggestions.empty().hide();
            if (typeof $issueInput !== 'undefined') {
              $issueInput.prop('disabled', true).attr('placeholder', 'Select Product Category first');
            }
            $('#search_category_menu').trigger('change');
          }
        });

        // Toast notification helper
        function showNotification(message, type = 'success') {
          $('.toast-notification').remove();

          const icon = type === 'success' 
            ? '<i class="fa-solid fa-circle-check toast-icon" style="color: #10b981;"></i>' 
            : '<i class="fa-solid fa-circle-exclamation toast-icon" style="color: #ef4444;"></i>';

          const $toast = $('<div class="toast-notification"></div>')
            .addClass('toast-' + type)
            .html(icon + '<span>' + message + '</span>')
            .appendTo('body');

          setTimeout(() => $toast.addClass('show'), 50);

          setTimeout(() => {
            $toast.removeClass('show');
            setTimeout(() => $toast.remove(), 400);
          }, 4000);
        }

        // Form submission validation: Ensure at least one search criterion is entered
        $('#searchForm').on('submit', function(e) {
          const searchMode = $('#search_category_menu').val();

          if (searchMode === 'miscellaneous') {
            const agent = $('#misc_agent').val() ? $('#misc_agent').val().trim() : '';
            const fromDate = $('#misc_from_date').val() ? $('#misc_from_date').val().trim() : '';
            const toDate = $('#misc_to_date').val() ? $('#misc_to_date').val().trim() : '';
            const category = $('#misc_category').val() ? $('#misc_category').val().trim() : '';
            const dept = $('#misc_department_select').length ? $('#misc_department_select').val() : '';

            if (!agent && !fromDate && !toDate && !category && !dept) {
              e.preventDefault();
              showNotification('Please enter at least one search criteria to search.', 'error');
              return;
            }

            if (fromDate && toDate && new Date(fromDate) > new Date(toDate)) {
              e.preventDefault();
              alert('From Date cannot be greater than To Date. Also, To Date should be greater than or equal to From Date.');
              return;
            }
          } else {
            const agent = $('#agent').val() ? $('#agent').val().trim() : '';
            const fromDate = $('#from_date').val() ? $('#from_date').val().trim() : '';
            const toDate = $('#to_date').val() ? $('#to_date').val().trim() : '';
            const companyName = $('#company_name').val() ? $('#company_name').val().trim() : '';
            const dealerName = $('#dealer_name').val() ? $('#dealer_name').val().trim() : '';
            const location = $('#location').val() ? $('#location').val().trim() : '';
            const phone = $('#phone').val() ? $('#phone').val().trim() : '';
            const hardware = $('#hardware_details').val() ? $('#hardware_details').val().trim() : '';
            const ticket = $('#ticket_id').val() ? $('#ticket_id').val().trim() : '';
            const caseId = $('#case_id').val() ? $('#case_id').val().trim() : '';
            const dept = $('#department_select').length ? $('#department_select').val() : '';
            const prodCat = $('#product_category').val();
            const issueCat = $('#issue_category').val() ? $('#issue_category').val().trim() : '';
            const supportStatus = $('#support_status').val();

            if (!agent && !fromDate && !toDate && !companyName && !dealerName && !location && !phone && !hardware && !ticket && !caseId && !dept && !prodCat && !issueCat && !supportStatus) {
              e.preventDefault();
              showNotification('Please enter at least one search criteria to search.', 'error');
              return;
            }

            if (fromDate && toDate && new Date(fromDate) > new Date(toDate)) {
              e.preventDefault();
              alert('From Date cannot be greater than To Date. Also, To Date should be greater than or equal to From Date.');
              return;
            }
          }
        });
      });
    </script>
  </body>

</html>