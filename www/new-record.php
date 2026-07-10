<?php
// Secure the page - ensure user is authenticated
require_once 'config/auth_check.php';

// Include database configuration
require_once 'config/database.php';
$db = getDatabaseConnection();

if (!$db) {
    die("Database connection failed.");
}

// Retrieve and validate record_id
$record_id = trim($_GET['id'] ?? $_GET['record_id'] ?? '');
if ($record_id === '') {
    die("Error: Record ID is required to access this page.");
}

$record = null;
$tableName = '';

// Check support_details first (Voice Logger)
$stmt = $db->prepare("SELECT * FROM `support_details` WHERE `record_id` = :record_id LIMIT 1");
$stmt->execute(['record_id' => $record_id]);
$record = $stmt->fetch();
if ($record) {
    $tableName = 'support_details';
} else {
    // Check ivr_details (IVR)
    $stmt = $db->prepare("SELECT * FROM `ivr_details` WHERE `record_id` = :record_id LIMIT 1");
    $stmt->execute(['record_id' => $record_id]);
    $record = $stmt->fetch();
    if ($record) {
        $tableName = 'ivr_details';
    }
}

if (!$record) {
    die("Error: Record not found.");
}

// Fetch user's department from session/DB
$user_department = isset($_SESSION['department']) ? $_SESSION['department'] : '';
$record_department = ($tableName === 'support_details') ? 'Voice Logger' : 'IVR';
if (strcasecmp($user_department, $record_department) !== 0) {
    die("Error: You are not authorized to view this record.");
}

$agent_name = htmlspecialchars($record['agent']);
$agent_department = htmlspecialchars($record_department);

// Calculate elapsed seconds since record support_start_time
$offset_seconds = 0;
if (!empty($record['date']) && !empty($record['support_start_time'])) {
    $start_datetime = $record['date'] . ' ' . $record['support_start_time'];
    $start_timestamp = strtotime($start_datetime);
    if ($start_timestamp !== false) {
        $offset_seconds = max(0, time() - $start_timestamp);
    }
}

// Helper function to print selected select values
if (!function_exists('getSelected')) {
    function getSelected($val, $dbVal) {
        return (strcasecmp(trim((string)$val), trim((string)$dbVal)) === 0) ? 'selected' : '';
    }
}

// Fetch clients from company_list table for autocomplete
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
?>

<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Add New Record | Xtend Agent Reports</title>
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
              <div class="timer-display" id="runningTimer">00:00:00</div>
            </div>
          </div>
          <h2>Create New Record</h2>
          <p>Complete the fields below in sequence to document a new support session.</p>
        </div>

        <form id="newRecordForm" autocomplete="off">
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

            <!-- 3. COMPANY NAME -->
            <div class="form-group col-6">
              <label for="company_name">Company Name</label>
              <div class="form-control-wrapper autocomplete-wrapper">
                <i class="fa-regular fa-building input-icon"></i>
                <input type="text" id="company_name" name="company_name" class="form-control" placeholder="Enter company / client name" value="<?php echo htmlspecialchars($record['company_name']); ?>" autocomplete="off" readonly required>
                <div class="autocomplete-suggestions" id="companySuggestions"></div>
              </div>
            </div>

            <!-- 4. LOCATION -->
            <div class="form-group col-6">
              <label for="location">Location</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-location-dot input-icon"></i>
                <input type="text" id="location" name="location" class="form-control" placeholder="City, State or Country" value="<?php echo htmlspecialchars($record['location']); ?>" required>
              </div>
            </div>

            <!-- 5. REGION (XTEND SALES) -->
            <div class="form-group col-6">
              <label for="region">Region (Xtend Sales)</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-earth-americas input-icon"></i>
                <select id="region" name="region" class="form-control" required>
                  <option value="" disabled <?php echo empty($record['region']) ? 'selected' : ''; ?>>Select Region</option>
                  <option value="INDIA" <?php echo getSelected('INDIA', $record['region']); ?>>INDIA</option>
                  <option value="DUBAI" <?php echo getSelected('DUBAI', $record['region']); ?>>DUBAI</option>
                  <option value="SINGAPORE" <?php echo getSelected('SINGAPORE', $record['region']); ?>>SINGAPORE</option>
                </select>
              </div>
            </div>

            <!-- 6. Email -->
            <div class="form-group col-6">
              <label for="email">Email</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-envelope input-icon"></i>
                <input type="email" id="email" name="email" class="form-control" placeholder="Contact person email" value="<?php echo htmlspecialchars($record['email']); ?>" pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[cC][oO][mM]" title="Please enter an email address ending with .com">
              </div>
            </div>

          <!-- 7. PHONE -->
            <div class="form-group col-6">
              <label for="phone">Phone</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-phone input-icon"></i>
                <input type="number" id="phone" name="phone" class="form-control" placeholder="Contact person phone" value="<?php echo htmlspecialchars($record['phone']); ?>" required>
              </div>
            </div>

            <!-- 8. CONTACT DETAILS -->
            <div class="form-group col-6">
              <label for="contact_details">Contact Details</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-address-book input-icon"></i>
                <input type="text" id="contact_details" name="contact_details" class="form-control" placeholder="Contact person name, phone, email" value="<?php echo htmlspecialchars($record['contact_details']); ?>" required>
              </div>
            </div>

            <!-- 9. PRODUCT CATEGORY -->
            <div class="form-group col-6">
              <label for="product_category">Product Category</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-cubes input-icon"></i>
                <select id="product_category" name="product_category" class="form-control" required>
                  <option value="" disabled <?php echo empty($record['product_category']) ? 'selected' : ''; ?>>Select Product Category</option>
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

            <!-- 10. ISSUE CATEGORY -->
            <div class="form-group col-6">
              <label for="issue_category">Issue Category</label>
              <div class="form-control-wrapper autocomplete-wrapper">
                <i class="fa-solid fa-tags input-icon"></i>
                <input type="text" id="issue_category" name="issue_category" class="form-control" placeholder="<?php echo empty($record['product_category']) ? 'Select Product Category first' : 'Search or select issue category...'; ?>" value="<?php echo htmlspecialchars($record['issue_category']); ?>" autocomplete="off" required <?php echo empty($record['product_category']) ? 'disabled' : ''; ?>>
                <div class="autocomplete-suggestions" id="issueCategorySuggestions"></div>
              </div>
            </div>

            <!-- 11. ISSUE TYPE -->
            <div class="form-group col-6">
              <label for="issue_type">Issue Type</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-triangle-exclamation input-icon"></i>
                <select id="issue_type" name="issue_type" class="form-control" required>
                  <option value="" disabled <?php echo empty($record['issue_type']) ? 'selected' : ''; ?>>Select Issue Type</option>
                  <option value="Hardware" <?php echo getSelected('Hardware', $record['issue_type']); ?>>Hardware</option>
                  <option value="Software" <?php echo getSelected('Software', $record['issue_type']); ?>>Software</option>
                  <option value="Driver" <?php echo getSelected('Driver', $record['issue_type']); ?>>Driver</option>
                  <option value="Others" <?php echo getSelected('Others', $record['issue_type']); ?>>Others</option>
                </select>
              </div>
            </div>

            <!-- 12. ISSUE DETAILS/NOTES -->
            <div class="form-group col-12">
              <label for="issue_details">Issue Details/Notes</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-file-lines input-icon" style="top: 18px;"></i>
                <textarea id="issue_details" name="issue_details" class="form-control" placeholder="Provide detailed notes regarding the issue reported..." required><?php echo htmlspecialchars($record['issue_details']); ?></textarea>
              </div>
            </div>

            <!-- 13. SUPPORT CATEGORY -->
            <div class="form-group col-6">
              <label for="support_category">Support Category</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-layer-group input-icon"></i>
                <select id="support_category" name="support_category" class="form-control" required>
                  <option value="" disabled <?php echo empty($record['support_category']) ? 'selected' : ''; ?>>Select Support Category</option>
                  <option value="Mail Support" <?php echo getSelected('Mail Support', $record['support_category']); ?>>Mail Support</option>
                  <option value="Skype Support" <?php echo getSelected('Skype Support', $record['support_category']); ?>>Skype Support</option>
                  <option value="Mobile Support" <?php echo getSelected('Mobile Support', $record['support_category']); ?>>Mobile Support</option>
                  <option value="CC Support" <?php echo getSelected('CC Support', $record['support_category']); ?>>CC Support</option>
                </select>
              </div>
            </div>

            <!-- 14. SOFTWARE DETAILS -->
            <div class="form-group col-6">
              <label for="software_details">Software Details</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-laptop-code input-icon"></i>
                <input type="text" id="software_details" name="software_details" class="form-control" placeholder="Software version and other details" value="<?php echo htmlspecialchars($record['software_details']); ?>">
              </div>
            </div>

            <!-- 15. HARDWARE DETAILS -->
            <div class="form-group col-6">
              <label for="hardware_details">Hardware Details</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-microchip input-icon"></i>
                <input type="text" id="hardware_details" name="hardware_details" class="form-control" placeholder="Specify the hardware details" value="<?php echo htmlspecialchars($record['hardware_details']); ?>">
              </div>
            </div>

            <!-- 16. SOLUTION -->
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
                <input type="text" id="support_end_time" name="support_end_time" class="form-control" value="<?php echo htmlspecialchars($record['support_end_time']); ?>" readonly>
              </div>
            </div>

            <!-- 17. TOTAL TIME -->
            <div class="form-group col-6">
              <label for="total_time">Total Time</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-clock input-icon"></i>
                <input type="text" id="total_time" name="total_time" class="form-control" value="<?php echo htmlspecialchars($record['total_time']); ?>" readonly required>
              </div>
            </div>

            <!-- 18. SUPPORT STATUS -->
            <div class="form-group col-6">
              <label for="support_status">Support Status</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-circle-info input-icon"></i>
                <select id="support_status" name="support_status" class="form-control" required>
                  <option value="" disabled <?php echo empty($record['support_status']) ? 'selected' : ''; ?>>Select Support Status</option>
                  <option value="Closed" <?php echo getSelected('Closed', $record['support_status']); ?>>Closed</option>
                  <option value="Pending" <?php echo getSelected('Pending', $record['support_status']); ?>>Pending</option>
                  <option value="Under Observation" <?php echo getSelected('Under Observation', $record['support_status']); ?>>Under Observation</option>
                  <option value="Escalated" <?php echo getSelected('Escalated', $record['support_status']); ?>>Escalated</option>
                  <option value="Escalated to Presales" <?php echo getSelected('Escalated to Presales', $record['support_status']); ?>>Escalated to Presales</option>
                  <option value="Closed-Device Replaced" <?php echo getSelected('Closed-Device Replaced', $record['support_status']); ?>>Closed-Device Replaced</option>
                </select>
              </div>
            </div>

            <!-- 19. REASON -->
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
                <i class="fa-solid fa-rotate-left"></i> Reset
              </button>
              <button type="submit" id="submitBtn" class="btn btn-primary">
                <i class="fa-regular fa-paper-plane"></i> Save Record
              </button>
            </div>

          </div>
        </form>

      </div>
    </div>

    <!-- Include Sidebar & Header Component -->
    <?php include 'components/header-sidebar.php'; ?>

    <script>
      $(document).ready(function() {
        let formSubmitted = false;
        const recordId = $('input[name="record_id"]').val();

        $(window).on('beforeunload', function(e) {
          if (!formSubmitted) {
            e.preventDefault();
            e.returnValue = 'All the data you have entered will be lost, are you sure?';
            return 'All the data you have entered will be lost, are you sure?';
          }
        });

        $(window).on('unload', function() {
          if (!formSubmitted && recordId) {
            const formData = new FormData();
            formData.append('record_id', recordId);
            navigator.sendBeacon('api/set-pending-status.php', formData);
          }
        });

        const today = new Date().toISOString().split('T')[0];
        // 2. Interactive features: auto-grow textareas as the user types
        $('textarea').on('input', function() {
          this.style.height = 'auto';
          this.style.height = (this.scrollHeight + 2) + 'px';
        });

        // 3. Reset form handler: Reloads page to restore DB state
        $('#resetBtn').on('click', function() {
          if (confirm('Are you sure you want to revert your edits? All unsaved inputs will be lost.')) {
            formSubmitted = true;
            window.location.reload();
          }
        });

        // 4. Toast notification helper
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

        // 4.5. Custom success modal with OK button to close the tab
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
              <div class="success-title">Record Added Successfully</div>
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

        // 5. Form submission handler
        $('#newRecordForm').on('submit', function(e) {
          e.preventDefault();

          // Set support end time
          $('#support_end_time').val(new Date().toTimeString().split(' ')[0]);
          
          // Show interactive submission state feedback
          const $btn = $('#submitBtn');
          const originalText = $btn.html();
          
          $btn.prop('disabled', true).html('<i class="fa-solid fa-circle-notch fa-spin"></i> Saving...');
          
          const formData = $(this).serialize();

          $.ajax({
            url: 'api/edit-record.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
              if (response.success) {
                formSubmitted = true;
                localStorage.setItem('record_updated', JSON.stringify({
                  record_id: response.data.record_id,
                  action: 'new',
                  timestamp: Date.now()
                }));
                showSuccessModal(response.message, response.data.record_id);
                
                // Reset form and UI states as fallback
                $('#newRecordForm')[0].reset();
                // Restore default date and agent name
                $('#date').val(today);
                
                // Reset start time and clear end time
                $('#support_start_time').val(new Date().toTimeString().split(' ')[0]);
                $('#support_end_time').val('');
                
                // Trigger auto-height reset for textareas
                $('textarea').css('height', 'auto');

                // Clear autocomplete suggestions
                $('#companySuggestions').empty().hide();

                // Restart stopwatch timer
                startTimer();
              } else {
                showNotification('Error: ' + response.message, 'error');
              }
            },
            error: function(xhr, status, error) {
              showNotification('An error occurred while saving the record. Please try again.', 'error');
            },
            complete: function() {
              $btn.prop('disabled', false).html(originalText);
            }
          });
        });

        // 5. Autocomplete logic for Company Name
        const clientList = <?php echo json_encode($clients); ?>;
        const $input = $('#company_name');
        const $suggestions = $('#companySuggestions');
        let currentFocus = -1;

        $input.on('input', function() {
          const val = this.value.trim().toLowerCase();
          $suggestions.empty().hide();
          currentFocus = -1;

          if (!val) return;

          // Filter matches (case-insensitive, max 10 results)
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

        // Keyboard navigation helper
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
          
          // Scroll suggestion container into view if needed
          const containerHeight = $suggestions.height();
          const itemHeight = $activeItem.outerHeight();
          const itemTop = $activeItem.position().top;

          if (itemTop + itemHeight > containerHeight) {
            $suggestions.scrollTop($suggestions.scrollTop() + itemTop + itemHeight - containerHeight);
          } else if (itemTop < 0) {
            $suggestions.scrollTop($suggestions.scrollTop() + itemTop);
          }
        }

        // Dismiss when clicking outside
        $(document).on('click', function(e) {
          if (!$(e.target).closest('#company_name').parent().length) {
            $suggestions.empty().hide();
            currentFocus = -1;
          }
          if (!$(e.target).closest('#issue_category').parent().length) {
            $issueSuggestions.empty().hide();
            issueCurrentFocus = -1;
          }
        });

        // 5.5. Dynamic Issue Category Autocomplete based on Product Category
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

        // 6. Live Session Stopwatch Timer
        
        let timerInterval = null;
        let startTime = null;
        const offsetSeconds = <?php echo (int)$offset_seconds; ?>;

        function startTimer() {
            if (timerInterval) clearInterval(timerInterval);

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