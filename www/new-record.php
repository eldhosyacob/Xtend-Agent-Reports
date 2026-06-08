<?php
// Secure the page - ensure user is authenticated
require_once 'config/auth_check.php';

// Default agent name from current session (uppercase)
$agent_name = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : '';
// Default department name from current session (uppercase)
$agent_department = isset($_SESSION['department']) ? strtoupper($_SESSION['department']) : '';

// Fetch clients from ODS file for autocomplete (cached in session with timestamp check)
// 
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
          <div class="grid-form">
            
            <!-- 1. DATE -->
            <div class="form-group col-4">
              <label for="date">Date</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-calendar input-icon"></i>
                <input type="date" id="date" name="date" class="form-control" required>
              </div>
            </div>

            <!-- 2. AGENT -->
            <div class="form-group col-4">
              <label for="agent">Agent</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-user input-icon"></i>
                <input type="text" id="agent" name="agent" class="form-control" value="<?php echo htmlspecialchars($agent_name); ?>" readonly required>
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
                <input type="text" id="company_name" name="company_name" class="form-control" placeholder="Enter company / client name" autocomplete="off" required>
                <div class="autocomplete-suggestions" id="companySuggestions"></div>
              </div>
            </div>

            <!-- 4. LOCATION -->
            <div class="form-group col-6">
              <label for="location">Location</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-location-dot input-icon"></i>
                <input type="text" id="location" name="location" class="form-control" placeholder="City, State or Country" required>
              </div>
            </div>

            <!-- 5. REGION (XTEND SALES) -->
            <div class="form-group col-6">
              <label for="region">Region (Xtend Sales)</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-earth-americas input-icon"></i>
                <select id="region" name="region" class="form-control" required>
                  <option value="" disabled selected>Select Region</option>
                  <option value="INDIA">INDIA</option>
                  <option value="DUBAI">DUBAI</option>
                  <option value="SINGAPORE">SINGAPORE</option>
                </select>
              </div>
            </div>

            <!-- 6. Email -->
            <div class="form-group col-6">
              <label for="email">Email</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-envelope input-icon"></i>
                <input type="text" id="email" name="email" class="form-control" placeholder="Contact person email" required>
              </div>
            </div>

          <!-- 7. PHONE -->
            <div class="form-group col-6">
              <label for="phone">Phone</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-phone input-icon"></i>
                <input type="text" id="phone" name="phone" class="form-control" placeholder="Contact person phone" required>
              </div>
            </div>

            <!-- 8. CONTACT DETAILS -->
            <div class="form-group col-6">
              <label for="contact_details">Contact Details</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-address-book input-icon"></i>
                <input type="text" id="contact_details" name="contact_details" class="form-control" placeholder="Contact person name, phone, email" required>
              </div>
            </div>

            <!-- 9. PRODUCT CATEGORY -->
            <div class="form-group col-6">
              <label for="product_category">Product Category</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-cubes input-icon"></i>
                <select id="product_category" name="product_category" class="form-control" required>
                  <option value="" disabled selected>Select Product Category</option>
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
                </select>
              </div>
            </div>

            <!-- 10. ISSUE CATEGORY -->
            <div class="form-group col-6">
              <label for="issue_category">Issue Category</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-tags input-icon"></i>
                <select id="issue_category" name="issue_category" class="form-control" required>
                  <option value="" disabled selected>Select Issue Category</option>
                  <option value="Hardware">Hardware</option>
                  <option value="Software">Software</option>
                  <option value="Driver">Driver</option>
                  <option value="Others">Others</option>
                </select>
              </div>
            </div>

            <!-- 11. ISSUE TYPE -->
            <div class="form-group col-6">
              <label for="issue_type">Issue Type</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-triangle-exclamation input-icon"></i>
                <select id="issue_type" name="issue_type" class="form-control" required>
                  <option value="" disabled selected>Select Issue Type</option>
                  <option value="Hardware">Hardware</option>
                  <option value="Software">Software</option>
                  <option value="Driver">Driver</option>
                  <option value="Others">Others</option>
                </select>
              </div>
            </div>

            <!-- 12. ISSUE DETAILS/NOTES -->
            <div class="form-group col-12">
              <label for="issue_details">Issue Details/Notes</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-file-lines input-icon" style="top: 18px;"></i>
                <textarea id="issue_details" name="issue_details" class="form-control" placeholder="Provide detailed notes regarding the issue reported..." required></textarea>
              </div>
            </div>

            <!-- 13. SUPPORT CATEGORY -->
            <div class="form-group col-6">
              <label for="support_category">Support Category</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-layer-group input-icon"></i>
                <select id="support_category" name="support_category" class="form-control" required>
                  <option value="" disabled selected>Select Support Category</option>
                  <option value="Mail Support">Mail Support</option>
                  <option value="Skype Support">Skype Support</option>
                  <option value="Mobile Support">Mobile Support</option>
                  <option value="CC Support">CC Support</option>
                </select>
              </div>
            </div>

            <!-- 14. SOFTWARE DETAILS -->
            <div class="form-group col-6">
              <label for="software_details">Software Details</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-laptop-code input-icon"></i>
                <input type="text" id="software_details" name="software_details" class="form-control" placeholder="Software version and other details">
              </div>
            </div>

            <!-- 15. HARDWARE DETAILS -->
            <div class="form-group col-6">
              <label for="hardware_details">Hardware Details</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-microchip input-icon"></i>
                <input type="text" id="hardware_details" name="hardware_details" class="form-control" placeholder="Specify the hardware details">
              </div>
            </div>

            <!-- 16. SOLUTION -->
            <div class="form-group col-12">
              <label for="solution">Solution</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-check-double input-icon" style="top: 18px;"></i>
                <textarea id="solution" name="solution" class="form-control" placeholder="Describe the solution"></textarea>
              </div>
            </div>

            <!-- SUPPORT START TIME -->
            <div class="form-group col-6">
              <label for="support_start_time">Support Start Time</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-hourglass-start input-icon"></i>
                <input type="text" id="support_start_time" name="support_start_time" class="form-control" readonly required>
              </div>
            </div>

            <!-- SUPPORT END TIME -->
            <div class="form-group col-6">
              <label for="support_end_time">Support End Time</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-hourglass-end input-icon"></i>
                <input type="text" id="support_end_time" name="support_end_time" class="form-control" readonly>
              </div>
            </div>

            <!-- 17. TOTAL TIME -->
            <div class="form-group col-6">
              <label for="total_time">Total Time</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-clock input-icon"></i>
                <input type="text" id="total_time" name="total_time" class="form-control" readonly required>
              </div>
            </div>

            <!-- 18. SUPPORT STATUS -->
            <div class="form-group col-6">
              <label for="support_status">Support Status</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-circle-info input-icon"></i>
                <select id="support_status" name="support_status" class="form-control" required>
                  <option value="" disabled selected>Select Support Status</option>
                  <option value="Closed">Closed</option>
                  <option value="Pending">Pending</option>
                  <option value="Under Observation">Under Observation</option>
                  <option value="Escalated">Escalated</option>
                  <option value="Escalated to Presales">Escalated to Presales</option>
                  <option value="Closed-Device Replaced">Closed-Device Replaced</option>
                </select>
              </div>
            </div>

            <!-- 19. REASON -->
            <div class="form-group col-12">
              <label for="reason">Reason</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-question input-icon" style="top: 18px;"></i>
                <textarea id="reason" name="reason" class="form-control" placeholder="Specify the reason"></textarea>
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
        // 1. Pre-fill date input with today's local date and set start time
        const today = new Date().toISOString().split('T')[0];
        $('#date').val(today);
        $('#support_start_time').val(new Date().toTimeString().split(' ')[0]);

        // 2. Interactive features: auto-grow textareas as the user types
        $('textarea').on('input', function() {
          this.style.height = 'auto';
          this.style.height = (this.scrollHeight + 2) + 'px';
        });

        // 3. Reset form handler
        $('#resetBtn').on('click', function() {
          if (confirm('Are you sure you want to clear the form? All unsaved inputs will be lost.')) {
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
            url: 'api/add-new-record.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
              if (response.success) {
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
          if (!$(e.target).closest('.autocomplete-wrapper').length) {
            $suggestions.empty().hide();
            currentFocus = -1;
          }
        });

        // 6. Live Session Stopwatch Timer
        // let totalSeconds = 0;
        // let timerInterval = null;

        // function startTimer() {
        //   if (timerInterval) clearInterval(timerInterval);
        //   totalSeconds = 0;
        //   updateTimerDisplay();
          
        //   timerInterval = setInterval(function() {
        //     totalSeconds++;
        //     updateTimerDisplay();
        //   }, 1000);
        // }

        // function updateTimerDisplay() {
        //   const hours = Math.floor(totalSeconds / 3600);
        //   const minutes = Math.floor((totalSeconds % 3600) / 60);
        //   const seconds = totalSeconds % 60;

        //   const formattedTime = 
        //     String(hours).padStart(2, '0') + ':' + 
        //     String(minutes).padStart(2, '0') + ':' + 
        //     String(seconds).padStart(2, '0');

        //   $('#runningTimer').text(formattedTime);
        //   $('#total_time').val(formattedTime);
        // }

        // // Start timer immediately on page load
        // startTimer();
        let timerInterval = null;
        let startTime = null;

        function startTimer() {
            if (timerInterval) clearInterval(timerInterval);

            startTime = Date.now();
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