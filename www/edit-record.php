<?php
// Secure the page - ensure user is authenticated
require_once 'config/auth_check.php';

// Include database configuration
require_once 'config/database.php';
$db = getDatabaseConnection();

if (!$db) {
    die("Database connection failed.");
}

// Fetch user's department from DB
$userStmt = $db->prepare("SELECT department FROM users WHERE id = :id LIMIT 1");
$userStmt->execute(['id' => $_SESSION['id']]);
$user = $userStmt->fetch();
$agent_department = $user ? trim($user['department']) : '';

// Route query to the correct table based on user's department
$tableName = (strcasecmp($agent_department, 'Voice Logger') === 0) ? 'support_details' : 'ivr_details';

// Retrieve and validate record_id
$record_id = trim($_GET['record_id'] ?? '');
if ($record_id === '') {
    die("Error: Record ID is required.");
}

$stmt = $db->prepare("SELECT * FROM `$tableName` WHERE `record_id` = :record_id LIMIT 1");
$stmt->execute(['record_id' => $record_id]);
$record = $stmt->fetch();

if (!$record) {
    die("Error: Record not found.");
}

// Security: Ensure the record belongs to the logged-in agent
if (strcasecmp(trim($record['agent']), $_SESSION['username']) !== 0) {
    die("Error: You are not authorized to edit this record.");
}

// Calculate stopwatch offsetSeconds from the record's saved total_time
$timeParts = explode(':', $record['total_time']);
$offsetSeconds = (int)($timeParts[0] ?? 0) * 3600 + (int)($timeParts[1] ?? 0) * 60 + (int)($timeParts[2] ?? 0);

// Helper function to print selected select values
function getSelected($val, $dbVal) {
    return (strcasecmp(trim((string)$val), trim((string)$dbVal)) === 0) ? 'selected' : '';
}

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
                    $xml->registerXPathNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
                    $xml->registerXPathNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');
                    $tables = $xml->xpath('//table:table');
                    foreach ($tables as $table) {
                        $rows = $table->xpath('./table:table-row');
                        if (empty($rows)) continue;
                        $headerCells = $rows[0]->xpath('./table:table-cell');
                        if (empty($headerCells)) continue;
                        $headerText = trim((string)($headerCells[0]->xpath('.//text:p')[0] ?? ''));
                        if ($headerText !== 'Client List') continue;
                        for ($i = 1; $i < count($rows); $i++) {
                            $cells = $rows[$i]->xpath('./table:table-cell');
                            if (empty($cells)) continue;
                            $clientName = trim((string)($cells[0]->xpath('.//text:p')[0] ?? ''));
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
        file_put_contents($cacheFile, json_encode(['filemtime' => $odsModifiedTime, 'clients' => $clients], JSON_UNESCAPED_UNICODE));
    }
}
?>

<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Edit Record <?php echo htmlspecialchars($record_id); ?> | Xtend Agent Reports</title>
    <link rel="shortcut icon" href="images/favicon.png" />
    <link rel="stylesheet" href="styles/header-sidebar.css">
    <link rel="stylesheet" href="styles/new_record.css">
    <link rel="stylesheet" href="styles/common.css">
  </head>

  <body>
    <div class="new-record-page-container page-containers">
      <div class="form-card-wrapper">
        <div class="form-header">
          <!-- Timer Widget -->
          <div class="timer-widget">
            <div class="timer-label"><i class="fa-regular fa-clock"></i></div>
            <div class="timer-display-wrapper">
              <span class="timer-pulse-dot"></span>
              <div class="timer-display" id="runningTimer"><?php echo htmlspecialchars($record['total_time']); ?></div>
            </div>
          </div>
          <h2>Edit Record (<?php echo htmlspecialchars($record_id); ?>)</h2>
          <p>Modify the fields below to update the logged support session.</p>
        </div>

        <form id="editRecordForm" autocomplete="off">
          <input type="hidden" name="record_id" value="<?php echo htmlspecialchars($record['record_id']); ?>">
          <div class="grid-form">
            
            <!-- 1. DATE -->
            <div class="form-group col-4">
              <label for="date">Date</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-calendar input-icon"></i>
                <input type="date" id="date" name="date" class="form-control" value="<?php echo htmlspecialchars($record['date']); ?>" required>
              </div>
            </div>

            <!-- 2. AGENT -->
            <div class="form-group col-4">
              <label for="agent">Agent</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-user input-icon"></i>
                <input type="text" id="agent" name="agent" class="form-control" value="<?php echo htmlspecialchars($record['agent']); ?>" readonly required>
              </div>
            </div>

            <!-- 3. DEPARTMENT -->
            <div class="form-group col-4">
              <label for="department"> Department</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-sitemap input-icon"></i>
                <input type="text" id="department" name="department" class="form-control" value="<?php echo htmlspecialchars($agent_department); ?>" readonly required>
              </div>
            </div>

            <!-- 4. COMPANY NAME -->
            <div class="form-group col-6">
              <label for="company_name">Company Name</label>
              <div class="form-control-wrapper autocomplete-wrapper">
                <i class="fa-regular fa-building input-icon"></i>
                <input type="text" id="company_name" name="company_name" class="form-control" placeholder="Enter company / client name" value="<?php echo htmlspecialchars($record['company_name']); ?>" autocomplete="off" required>
                <div class="autocomplete-suggestions" id="companySuggestions"></div>
              </div>
            </div>

            <!-- 5. LOCATION -->
            <div class="form-group col-6">
              <label for="location">Location</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-location-dot input-icon"></i>
                <input type="text" id="location" name="location" class="form-control" placeholder="City, State or Country" value="<?php echo htmlspecialchars($record['location']); ?>" required>
              </div>
            </div>

            <!-- 6. REGION (XTEND SALES) -->
            <div class="form-group col-6">
              <label for="region">Region (Xtend Sales)</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-earth-americas input-icon"></i>
                <select id="region" name="region" class="form-control" required>
                  <option value="" disabled>Select Region</option>
                  <option value="INDIA" <?php echo getSelected('INDIA', $record['region']); ?>>INDIA</option>
                  <option value="DUBAI" <?php echo getSelected('DUBAI', $record['region']); ?>>DUBAI</option>
                  <option value="SINGAPORE" <?php echo getSelected('SINGAPORE', $record['region']); ?>>SINGAPORE</option>
                </select>
              </div>
            </div>

            <!-- 7. Email -->
            <div class="form-group col-6">
              <label for="email">Email</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-envelope input-icon"></i>
                <input type="text" id="email" name="email" class="form-control" placeholder="Contact person email" value="<?php echo htmlspecialchars($record['email']); ?>" required>
              </div>
            </div>

            <!-- 8. PHONE -->
            <div class="form-group col-6">
              <label for="phone">Phone</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-phone input-icon"></i>
                <input type="text" id="phone" name="phone" class="form-control" placeholder="Contact person phone" value="<?php echo htmlspecialchars($record['phone']); ?>" required>
              </div>
            </div>

            <!-- 9. CONTACT DETAILS -->
            <div class="form-group col-6">
              <label for="contact_details">Contact Details</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-address-book input-icon"></i>
                <input type="text" id="contact_details" name="contact_details" class="form-control" placeholder="Contact person name, phone, email" value="<?php echo htmlspecialchars($record['contact_details']); ?>" required>
              </div>
            </div>

            <!-- 10. PRODUCT CATEGORY -->
            <div class="form-group col-6">
              <label for="product_category">Product Category</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-cubes input-icon"></i>
                <select id="product_category" name="product_category" class="form-control" required>
                  <option value="" disabled>Select Product Category</option>
                  <?php
                  $products = [
                    "ANALOG_LOGGER", "PRI_LOGGER", "DIGITAL_EXTENSION", "IP_LOGGER",
                    "XTEND_SMARTLOG", "CALLBILLING", "CMS_HO", "IVR_GATEWAY",
                    "STANDALONE_LOGGER", "ACTIVE_LOGGER", "XTEND_ONCALL", "XTEND_VX",
                    "XTEND_SX2", "XTEND_SX", "LINUX_STANDALONE"
                  ];
                  foreach ($products as $p) {
                      echo '<option value="' . $p . '" ' . getSelected($p, $record['product_category']) . '>' . $p . '</option>';
                  }
                  ?>
                </select>
              </div>
            </div>

            <!-- 11. ISSUE CATEGORY -->
            <div class="form-group col-6">
              <label for="issue_category">Issue Category</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-tags input-icon"></i>
                <select id="issue_category" name="issue_category" class="form-control" required>
                  <option value="" disabled>Select Issue Category</option>
                  <option value="Hardware" <?php echo getSelected('Hardware', $record['issue_category']); ?>>Hardware</option>
                  <option value="Software" <?php echo getSelected('Software', $record['issue_category']); ?>>Software</option>
                  <option value="Driver" <?php echo getSelected('Driver', $record['issue_category']); ?>>Driver</option>
                  <option value="Others" <?php echo getSelected('Others', $record['issue_category']); ?>>Others</option>
                </select>
              </div>
            </div>

            <!-- 12. ISSUE TYPE -->
            <div class="form-group col-6">
              <label for="issue_type">Issue Type</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-triangle-exclamation input-icon"></i>
                <select id="issue_type" name="issue_type" class="form-control" required>
                  <option value="" disabled>Select Issue Type</option>
                  <option value="Hardware" <?php echo getSelected('Hardware', $record['issue_type']); ?>>Hardware</option>
                  <option value="Software" <?php echo getSelected('Software', $record['issue_type']); ?>>Software</option>
                  <option value="Driver" <?php echo getSelected('Driver', $record['issue_type']); ?>>Driver</option>
                  <option value="Others" <?php echo getSelected('Others', $record['issue_type']); ?>>Others</option>
                </select>
              </div>
            </div>

            <!-- 13. ISSUE DETAILS/NOTES -->
            <div class="form-group col-12">
              <label for="issue_details">Issue Details/Notes</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-file-lines input-icon" style="top: 18px;"></i>
                <textarea id="issue_details" name="issue_details" class="form-control" placeholder="Provide detailed notes regarding the issue reported..." required><?php echo htmlspecialchars($record['issue_details']); ?></textarea>
              </div>
            </div>

            <!-- 14. SUPPORT CATEGORY -->
            <div class="form-group col-6">
              <label for="support_category">Support Category</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-layer-group input-icon"></i>
                <select id="support_category" name="support_category" class="form-control" required>
                  <option value="" disabled>Select Support Category</option>
                  <option value="Mail Support" <?php echo getSelected('Mail Support', $record['support_category']); ?>>Mail Support</option>
                  <option value="Skype Support" <?php echo getSelected('Skype Support', $record['support_category']); ?>>Skype Support</option>
                  <option value="Mobile Support" <?php echo getSelected('Mobile Support', $record['support_category']); ?>>Mobile Support</option>
                  <option value="CC Support" <?php echo getSelected('CC Support', $record['support_category']); ?>>CC Support</option>
                </select>
              </div>
            </div>

            <!-- 15. SOFTWARE DETAILS -->
            <div class="form-group col-6">
              <label for="software_details">Software Details</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-laptop-code input-icon"></i>
                <input type="text" id="software_details" name="software_details" class="form-control" placeholder="Software version and other details" value="<?php echo htmlspecialchars($record['software_details']); ?>">
              </div>
            </div>

            <!-- 16. HARDWARE DETAILS -->
            <div class="form-group col-6">
              <label for="hardware_details">Hardware Details</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-microchip input-icon"></i>
                <input type="text" id="hardware_details" name="hardware_details" class="form-control" placeholder="Specify the hardware details" value="<?php echo htmlspecialchars($record['hardware_details']); ?>">
              </div>
            </div>

            <!-- 17. SOLUTION -->
            <div class="form-group col-12">
              <label for="solution">Solution</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-check-double input-icon" style="top: 18px;"></i>
                <textarea id="solution" name="solution" class="form-control" placeholder="Describe the solution"><?php echo htmlspecialchars($record['solution']); ?></textarea>
              </div>
            </div>

            <!-- SUPPORT START TIME -->
            <div class="form-group col-6">
              <label for="support_start_time">Support Start Time</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-hourglass-start input-icon"></i>
                <input type="text" id="support_start_time" name="support_start_time" class="form-control" value="<?php echo htmlspecialchars($record['support_start_time']); ?>" readonly required>
              </div>
            </div>

            <!-- SUPPORT END TIME -->
            <div class="form-group col-6">
              <label for="support_end_time">Support End Time</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-hourglass-end input-icon"></i>
                <input type="text" id="support_end_time" name="support_end_time" class="form-control" value="<?php echo htmlspecialchars($record['support_end_time'] ?? ''); ?>" readonly>
              </div>
            </div>

            <!-- 18. TOTAL TIME -->
            <div class="form-group col-6">
              <label for="total_time">Total Time</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-clock input-icon"></i>
                <input type="text" id="total_time" name="total_time" class="form-control" value="<?php echo htmlspecialchars($record['total_time']); ?>" readonly required>
              </div>
            </div>

            <!-- 19. SUPPORT STATUS -->
            <div class="form-group col-6">
              <label for="support_status">Support Status</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-circle-info input-icon"></i>
                <select id="support_status" name="support_status" class="form-control" required>
                  <option value="" disabled>Select Support Status</option>
                  <option value="Closed" <?php echo getSelected('Closed', $record['support_status']); ?>>Closed</option>
                  <option value="Pending" <?php echo getSelected('Pending', $record['support_status']); ?>>Pending</option>
                  <option value="Under Observation" <?php echo getSelected('Under Observation', $record['support_status']); ?>>Under Observation</option>
                  <option value="Escalated" <?php echo getSelected('Escalated', $record['support_status']); ?>>Escalated</option>
                  <option value="Escalated to Presales" <?php echo getSelected('Escalated to Presales', $record['support_status']); ?>>Escalated to Presales</option>
                  <option value="Closed-Device Replaced" <?php echo getSelected('Closed-Device Replaced', $record['support_status']); ?>>Closed-Device Replaced</option>
                </select>
              </div>
            </div>

            <!-- 20. REASON -->
            <div class="form-group col-12">
              <label for="reason">Reason</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-question input-icon" style="top: 18px;"></i>
                <textarea id="reason" name="reason" class="form-control" placeholder="Specify the reason"><?php echo htmlspecialchars($record['ticket_id'] ?? ''); ?></textarea>
              </div>
            </div>

            <!-- Form Action Buttons -->
            <div class="form-actions">
              <button type="button" id="resetBtn" class="btn btn-secondary">
                <i class="fa-solid fa-rotate-left"></i> Revert
              </button>
              <button type="submit" id="submitBtn" class="btn btn-primary">
                <i class="fa-regular fa-paper-plane"></i> Save Changes
              </button>
            </div>

          </div>
        </form>

      </div>
    </div>

    <!-- Include Sidebar & Header Component -->
    <?php include 'components/header-sidebar.php'; ?>

    <script src="plugins/jquery-3.7.1.min.js"></script>
    <script>
      $(document).ready(function() {
        const clientList = <?php echo json_encode($clients, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        
        // Auto-grow textareas initially
        $('textarea').each(function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight + 2) + 'px';
        });

        // Interactive features: auto-grow textareas as the user types
        $('textarea').on('input', function() {
          this.style.height = 'auto';
          this.style.height = (this.scrollHeight + 2) + 'px';
        });

        // Reset form handler: Reloads page to restore DB state
        $('#resetBtn').on('click', function() {
          if (confirm('Are you sure you want to revert your edits? All unsaved inputs will be lost.')) {
            window.location.reload();
          }
        });

        // Toast notification helper
        function showNotification(message, type = 'success') {
          if ($('#toast-style').length === 0) {
            $('<style id="toast-style">')
              .prop('type', 'text/css')
              .html(`
                .toast-notification {
                  position: fixed;
                  top: 30px;
                  right: 30px;
                  z-index: 9999;
                  box-shadow: 0 10px 25px rgba(0,0,0,0.15);
                  min-width: 300px;
                  max-width: 450px;
                  padding: 16px 24px;
                  border-radius: 12px;
                  display: flex;
                  align-items: center;
                  gap: 12px;
                  font-family: 'Plus Jakarta Sans', sans-serif;
                  font-size: 14px;
                  font-weight: 500;
                  transform: translateY(-20px);
                  opacity: 0;
                  transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.4s ease;
                }
                .toast-notification.show {
                  transform: translateY(0);
                  opacity: 1;
                }
                .toast-success {
                  background-color: #ecfdf5;
                  color: #065f46;
                  border: 1px solid #a7f3d0;
                }
                .toast-error {
                  background-color: #fef2f2;
                  color: #991b1b;
                  border: 1px solid #fecaca;
                }
                .toast-icon {
                  font-size: 18px;
                }
              `)
              .appendTo('head');
          }

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

        // Custom success modal with OK button to close the tab
        function showSuccessModal(message, recordId) {
          if ($('#modal-style').length === 0) {
            $('<style id="modal-style">')
              .prop('type', 'text/css')
              .html(`
                .custom-success-overlay {
                  position: fixed;
                  top: 0;
                  left: 0;
                  width: 100%;
                  height: 100%;
                  background: rgba(15, 23, 42, 0.4);
                  backdrop-filter: blur(8px);
                  z-index: 10000;
                  display: flex;
                  align-items: center;
                  justify-content: center;
                  opacity: 0;
                  transition: opacity 0.3s ease;
                }
                .custom-success-modal {
                  background: #ffffff;
                  border: 1px solid rgba(226, 232, 240, 0.8);
                  border-radius: 24px;
                  padding: 40px;
                  width: 90%;
                  max-width: 440px;
                  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
                  text-align: center;
                  transform: scale(0.9) translateY(10px);
                  transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.3s ease;
                  opacity: 0;
                }
                .custom-success-overlay.show {
                  opacity: 1;
                }
                .custom-success-overlay.show .custom-success-modal {
                  transform: scale(1) translateY(0);
                  opacity: 1;
                }
                .success-icon-wrapper {
                  width: 80px;
                  height: 80px;
                  border-radius: 50%;
                  background: #f0fdf4;
                  border: 2px solid #bbf7d0;
                  display: flex;
                  align-items: center;
                  justify-content: center;
                  margin: 0 auto 24px auto;
                  color: #10b981;
                  font-size: 36px;
                  animation: scaleIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
                }
                .success-title {
                  font-family: 'Plus Jakarta Sans', sans-serif;
                  font-size: 16px;
                  font-weight: 700;
                  color: #0f172a;
                  margin-bottom: 12px;
                }
                .success-text {
                  font-family: 'Plus Jakarta Sans', sans-serif;
                  font-size: 15px;
                  color: #64748b;
                  margin-bottom: 28px;
                  line-height: 1.6;
                }
                .success-record-badge {
                  display: inline-block;
                  background: #f1f5f9;
                  color: #475569;
                  font-family: monospace;
                  font-size: 16px;
                  font-weight: 700;
                  padding: 8px 16px;
                  border-radius: 8px;
                  margin-bottom: 24px;
                  border: 1px solid #e2e8f0;
                  letter-spacing: 0.5px;
                }
                .modal-btn-ok {
                  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                  color: #ffffff;
                  border: none;
                  font-family: 'Plus Jakarta Sans', sans-serif;
                  font-size: 15px;
                  font-weight: 600;
                  padding: 14px 0;
                  width: 50%;
                  border-radius: 12px;
                  cursor: pointer;
                  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
                  transition: all 0.3s ease;
                  outline: none;
                }
                .modal-btn-ok:hover {
                  transform: translateY(-1px);
                  box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
                  background: linear-gradient(135deg, #34d399 0%, #059669 100%);
                }
                .modal-btn-ok:active {
                  transform: translateY(1px);
                  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
                }
                @keyframes scaleIn {
                  from { transform: scale(0.5); opacity: 0; }
                  to { transform: scale(1); opacity: 1; }
                }
              `)
              .appendTo('head');
          }

          const $overlay = $('<div class="custom-success-overlay"></div>').appendTo('body');
          const $modal = $(`
            <div class="custom-success-modal">
              <div class="success-icon-wrapper">
                <i class="fa-solid fa-circle-check"></i>
              </div>
              <div class="success-title">Record Updated Successfully</div>
              <div class="success-record-badge">ID: ${recordId}</div><br>
              <button class="modal-btn-ok">OK</button>
            </div>
          `).appendTo($overlay);

          setTimeout(() => $overlay.addClass('show'), 50);

          $modal.find('.modal-btn-ok').on('click', function() {
            $overlay.removeClass('show');
            setTimeout(() => {
              $overlay.remove();
              window.close();
            }, 300);
          });
        }

        // Form submission handler
        $('#editRecordForm').on('submit', function(e) {
          e.preventDefault();

          // Set support end time to current local time
          $('#support_end_time').val(new Date().toTimeString().split(' ')[0]);
          
          const $btn = $('#submitBtn');
          $btn.prop('disabled', true).html('<i class="fa-solid fa-circle-notch fa-spin"></i> Saving...');
          
          const formData = $(this).serialize();

          $.ajax({
            url: 'api/edit-record.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
              if (response.success) {
                showSuccessModal(response.message, response.data.record_id);
              } else {
                showNotification(response.message || 'Error occurred while saving.', 'error');
                $btn.prop('disabled', false).html('<i class="fa-regular fa-paper-plane"></i> Save Changes');
              }
            },
            error: function() {
              showNotification('Network connection error. Please try again.', 'error');
              $btn.prop('disabled', false).html('<i class="fa-regular fa-paper-plane"></i> Save Changes');
            }
          });
        });

        // Autocomplete suggestions search
        const $companyNameInput = $('#company_name');
        const $suggestions = $('#companySuggestions');
        let currentFocus = -1;

        if (clientList && clientList.length > 0) {
          $companyNameInput.on('input', function() {
            const val = this.value;
            $suggestions.empty().hide();
            currentFocus = -1;

            if (!val) return false;

            let count = 0;
            const valUpper = val.toUpperCase();

            for (let i = 0; i < clientList.length; i++) {
              const client = clientList[i];
              const clientUpper = client.toUpperCase();
              const matchIndex = clientUpper.indexOf(valUpper);

              if (matchIndex > -1) {
                if (count >= 50) break;

                const highlighted = client.substring(0, matchIndex) + 
                  '<strong>' + client.substring(matchIndex, matchIndex + val.length) + '</strong>' + 
                  client.substring(matchIndex + val.length);

                const $suggestion = $('<div class="autocomplete-suggestion"></div>')
                  .html(highlighted)
                  .data('val', client)
                  .on('click', function() {
                    $companyNameInput.val($(this).data('val'));
                    $suggestions.empty().hide();
                  });

                $suggestions.append($suggestion);
                count++;
              }
            }

            if (count > 0) {
              $suggestions.show();
            }
          });

          $companyNameInput.on('keydown', function(e) {
            let $items = $suggestions.find('.autocomplete-suggestion');
            if ($items.length === 0) return;

            if (e.keyCode === 40) { // DOWN
              currentFocus++;
              setActive($items);
            } else if (e.keyCode === 38) { // UP
              currentFocus--;
              setActive($items);
            } else if (e.keyCode === 13) { // ENTER
              if (currentFocus > -1) {
                e.preventDefault();
                $items.eq(currentFocus).click();
              }
            }
          });
        }

        function setActive($items) {
          $items.removeClass('active');
          if (currentFocus >= $items.length) currentFocus = 0;
          if (currentFocus < 0) currentFocus = $items.length - 1;
          
          const $activeItem = $items.eq(currentFocus).addClass('active');
          
          // Scroll item into view inside the suggestions dropdown
          const container = $suggestions[0];
          const elem = $activeItem[0];
          
          if (elem.offsetTop < container.scrollTop) {
            container.scrollTop = elem.offsetTop;
          } else if (elem.offsetTop + elem.clientHeight > container.scrollTop + container.clientHeight) {
            container.scrollTop = elem.offsetTop + elem.clientHeight - container.clientHeight;
          }
        }

        $(document).on('click', function(e) {
          if (!$(e.target).closest('.autocomplete-wrapper').length) {
            $suggestions.empty().hide();
            currentFocus = -1;
          }
        });

        // 6. Live Session Stopwatch Timer with PHP Offset Resumption
        let timerInterval = null;
        let startTime = null;
        const offsetSeconds = <?php echo (int)$offsetSeconds; ?>;

        function startTimer() {
            if (timerInterval) clearInterval(timerInterval);

            // Shift startTime backwards in time by offsetSeconds * 1000 to resume counting
            startTime = Date.now() - (offsetSeconds * 1000);
            updateTimerDisplay();

            timerInterval = setInterval(updateTimerDisplay, 1000);
        }

        function updateTimerDisplay() {
            const totalSeconds = Math.floor((Date.now() - startTime) / 1000);

            const hours = Math.floor(totalSeconds / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;

            const formattedTime =
                String(hours).padStart(2, '0') + ':' +
                String(minutes).padStart(2, '0') + ':' +
                String(seconds).padStart(2, '0');

            $('#runningTimer').text(formattedTime);
            $('#total_time').val(formattedTime);
        }

        startTimer();
      });
    </script>
  </body>

</html>
