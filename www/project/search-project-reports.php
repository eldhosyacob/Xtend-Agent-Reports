<?php
require_once __DIR__ . '/../config/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$db = getDatabaseConnection();
if (!$db) {
    die("Database connection failed.");
}

// 1. Fetch agents list
$agents = [];
try {
    $stmtUsers = $db->query("SELECT DISTINCT real_name FROM users WHERE real_name IS NOT NULL AND TRIM(real_name) != ''");
    $userAgents = $stmtUsers ? $stmtUsers->fetchAll(PDO::FETCH_COLUMN) : [];

    $stmtReports = $db->query("SELECT DISTINCT agent_name FROM project_reports WHERE agent_name IS NOT NULL AND TRIM(agent_name) != ''");
    $reportAgents = $stmtReports ? $stmtReports->fetchAll(PDO::FETCH_COLUMN) : [];

    $mergedAgents = array_unique(array_filter(array_merge($userAgents, $reportAgents)));
    natcasesort($mergedAgents);
    $agents = array_values($mergedAgents);
} catch (PDOException $e) {
    error_log("Failed to fetch agents list for project reports search: " . $e->getMessage());
}

// 2. Fetch clients list (from company_list and project_reports)
$clients = [];
try {
    $stmtCompany = $db->query("SELECT company_name FROM company_list WHERE company_name IS NOT NULL AND TRIM(company_name) != ''");
    $companyClients = $stmtCompany ? $stmtCompany->fetchAll(PDO::FETCH_COLUMN) : [];

    $stmtProjClients = $db->query("SELECT DISTINCT client_name FROM project_reports WHERE client_name IS NOT NULL AND TRIM(client_name) != '' AND LOWER(client_name) NOT IN ('na', 'n/a')");
    $projClients = $stmtProjClients ? $stmtProjClients->fetchAll(PDO::FETCH_COLUMN) : [];

    $mergedClients = array_unique(array_filter(array_merge($companyClients, $projClients)));
    natcasesort($mergedClients);
    $clients = array_values($mergedClients);
} catch (PDOException $e) {
    error_log("Failed to fetch clients list for project reports search: " . $e->getMessage());
}

// 3. Fetch products list (from products_vl, products_ivr, and project_reports)
$products = [];
try {
    $stmtVl = $db->query("SELECT product_name FROM products_vl WHERE product_name IS NOT NULL AND TRIM(product_name) != ''");
    $vlProducts = $stmtVl ? $stmtVl->fetchAll(PDO::FETCH_COLUMN) : [];

    $stmtIvr = $db->query("SELECT product_name FROM products_ivr WHERE product_name IS NOT NULL AND TRIM(product_name) != ''");
    $ivrProducts = $stmtIvr ? $stmtIvr->fetchAll(PDO::FETCH_COLUMN) : [];

    $stmtRep = $db->query("SELECT DISTINCT product_name FROM project_reports WHERE product_name IS NOT NULL AND TRIM(product_name) != ''");
    $repProducts = $stmtRep ? $stmtRep->fetchAll(PDO::FETCH_COLUMN) : [];

    $mergedProducts = array_unique(array_filter(array_merge($vlProducts, $ivrProducts, $repProducts)));
    natcasesort($mergedProducts);
    $products = array_values($mergedProducts);
} catch (PDOException $e) {
    error_log("Failed to fetch products list for project reports search: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Search Project Reports | Xtend Agent Reports</title>
    <link rel="shortcut icon" href="../images/favicon.png" />
    <link rel="stylesheet" href="../styles/search.css">
    <link rel="stylesheet" href="../styles/header-sidebar.css">
    <link rel="stylesheet" href="../styles/common.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  </head>

  <body>
    <div class="search-page-container page-containers">
      <div class="form-card-wrapper">
        <div class="form-header">
          <div class="header-menu-container">
            <div class="header-spacer"></div>
            <h2>Search Project Reports</h2>
          </div>
          <p id="searchSubheading">Use the search criteria below to filter project reports.</p>
        </div>

        <form method="get" action="search-report-results.php" autocomplete="off" id="searchProjectReportsForm">
          <div class="grid-form">
            <!-- 1. AGENT NAME -->
            <div class="form-group col-4">
              <label for="agent_name">Agent Name</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-regular fa-user input-icon"></i>
                <select id="agent_name" name="agent_name" class="form-control">
                  <option value="" selected>-- Select Agent --</option>
                  <?php foreach ($agents as $agentName): ?>
                    <option value="<?php echo htmlspecialchars($agentName); ?>">
                      <?php echo htmlspecialchars(strtoupper($agentName)); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <!-- 2. FROM DATE -->
            <div class="form-group col-4">
              <label for="from_date">From Date</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-calendar input-icon"></i>
                <input type="date" id="from_date" name="from_date" class="form-control">
              </div>
            </div>

            <!-- 3. TO DATE -->
            <div class="form-group col-4">
              <label for="to_date">To Date</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-calendar input-icon"></i>
                <input type="date" id="to_date" name="to_date" class="form-control">
              </div>
            </div>

            <!-- 4. CLIENT NAME -->
            <div class="form-group col-4">
              <label for="client_name">Client Name</label>
              <div class="form-control-wrapper autocomplete-wrapper">
                <i class="fa-regular fa-building input-icon"></i>
                <input type="text" id="client_name" name="client_name" class="form-control" placeholder="Enter company / client name" autocomplete="off">
                <div class="autocomplete-suggestions" id="clientSuggestions"></div>
              </div>
            </div>

            <!-- 5. PRODUCT NAME -->
            <div class="form-group col-4">
              <label for="product_name">Product Name</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-box input-icon"></i>
                <select id="product_name" name="product_name" class="form-control">
                  <option value="" selected>-- Select Product Name --</option>
                  <?php foreach ($products as $prodName): ?>
                    <option value="<?php echo htmlspecialchars($prodName); ?>">
                      <?php echo htmlspecialchars($prodName); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <!-- 6. PRODUCT ID -->
            <div class="form-group col-4">
              <label for="product_id">Product ID</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-barcode input-icon"></i>
                <input type="text" id="product_id" name="product_id" class="form-control" placeholder="Enter Product ID">
              </div>
            </div>

            <!-- 7. SOFTWARE VERSION -->
            <div class="form-group col-4">
              <label for="software_version">Software Version</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-code-branch input-icon"></i>
                <input type="text" id="software_version" name="software_version" class="form-control" placeholder="Enter software version">
              </div>
            </div>

            <!-- 8. LCD ID -->
            <div class="form-group col-4">
              <label for="lcd_id">LCD ID</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-desktop input-icon"></i>
                <input type="text" id="lcd_id" name="lcd_id" class="form-control" placeholder="Enter LCD ID">
              </div>
            </div>

            <!-- 9. HARDWARE ID/CARD ID -->
            <div class="form-group col-4">
              <label for="hardware_id">Hardware ID/Card ID</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-microchip input-icon"></i>
                <input type="text" id="hardware_id" name="hardware_id" class="form-control" placeholder="Enter Hardware ID / Card ID">
              </div>
            </div>

            <!-- 10. HASP ID -->
            <div class="form-group col-4">
              <label for="hasp_id">HASP ID</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-key input-icon"></i>
                <input type="text" id="hasp_id" name="hasp_id" class="form-control" placeholder="Enter HASP ID">
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

    <?php include __DIR__ . '/../components/header-sidebar.php'; ?>

    <script>
      window.searchProjectClients = <?php echo json_encode($clients, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?> || [];
    </script>
    <script src="../plugins/jquery-3.7.1.min.js"></script>
    <script src="../js/project/search-project-reports.js"></script>
  </body>

</html>

