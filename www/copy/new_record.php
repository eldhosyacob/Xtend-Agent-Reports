<?php
// Secure the page - ensure user is authenticated
require_once 'config/auth_check.php';

// Default agent name from current session (uppercase)
$agent_name = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : '';
// Default department name from current session (uppercase)
$agent_department = isset($_SESSION['department']) ? strtoupper($_SESSION['department']) : '';
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
          <h2>Create New Record</h2>
          <p>Complete the fields below in sequence to document a new support session.</p>
        </div>

        <form id="newRecordForm" autocomplete="off">
          <div class="grid-form">
            
            <!-- 1. DATE -->
            <div class="form-group col-4">
              <label for="date"><i class="fa-regular fa-calendar"></i> Date</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-calendar input-icon"></i>
                <input type="date" id="date" name="date" class="form-control" required>
              </div>
            </div>

            <!-- 2. AGENT -->
            <div class="form-group col-4">
              <label for="agent"><i class="fa-regular fa-user"></i> Agent</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-user input-icon"></i>
                <input type="text" id="agent" name="agent" class="form-control" value="<?php echo htmlspecialchars($agent_name); ?>" readonly required>
              </div>
            </div>

            <!-- 3. DEPARTMENT -->
            <div class="form-group col-4">
              <label for="department"><i class="fa-solid fa-sitemap"></i> Department</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-sitemap input-icon"></i>
                <input type="text" id="department" name="department" class="form-control" value="<?php echo htmlspecialchars($agent_department); ?>" readonly required>
              </div>
            </div>

            <!-- 3. COMPANY NAME -->
            <div class="form-group col-6">
              <label for="company_name"><i class="fa-regular fa-building"></i> Company Name</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-building input-icon"></i>
                <input type="text" id="company_name" name="company_name" class="form-control" placeholder="Enter company / client name" required>
              </div>
            </div>

            <!-- 4. LOCATION -->
            <div class="form-group col-6">
              <label for="location"><i class="fa-solid fa-location-dot"></i> Location</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-location-dot input-icon"></i>
                <input type="text" id="location" name="location" class="form-control" placeholder="City, State or Country" required>
              </div>
            </div>

            <!-- 5. REGION (XTEND SALES) -->
            <div class="form-group col-6">
              <label for="region"><i class="fa-solid fa-earth-americas"></i> Region (Xtend Sales)</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-earth-americas input-icon"></i>
                <select id="region" name="region" class="form-control" required>
                  <option value="" disabled selected>Select Region</option>
                  <option value="Northern Region">Northern Region</option>
                  <option value="Southern Region">Southern Region</option>
                  <option value="Eastern Region">Eastern Region</option>
                  <option value="Western Region">Western Region</option>
                  <option value="Central Region">Central Region</option>
                  <option value="International">International</option>
                </select>
              </div>
            </div>

            <!-- 6. Email -->
            <div class="form-group col-6">
              <label for="email"><i class="fa-regular fa-envelope"></i> Email</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-envelope input-icon"></i>
                <input type="text" id="email" name="email" class="form-control" placeholder="Contact person email" required>
              </div>
            </div>

          <!-- 7. PHONE -->
            <div class="form-group col-6">
              <label for="phone"><i class="fa-regular fa-phone"></i> Phone</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-phone input-icon"></i>
                <input type="text" id="phone" name="phone" class="form-control" placeholder="Contact person phone" required>
              </div>
            </div>

            <!-- 8. CONTACT DETAILS -->
            <div class="form-group col-6">
              <label for="contact_details"><i class="fa-regular fa-address-book"></i> Contact Details</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-address-book input-icon"></i>
                <input type="text" id="contact_details" name="contact_details" class="form-control" placeholder="Contact person name, phone, email" required>
              </div>
            </div>

            <!-- 9. PRODUCT CATEGORY -->
            <div class="form-group col-6">
              <label for="product_category"><i class="fa-solid fa-cubes"></i> Product Category</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-cubes input-icon"></i>
                <select id="product_category" name="product_category" class="form-control" required>
                  <option value="" disabled selected>Select Product Category</option>
                  <option value="Xtend License">Xtend License</option>
                  <option value="Call Recording System">Call Recording System</option>
                  <option value="Voice Logger">Voice Logger</option>
                  <option value="SMS Gateway">SMS Gateway</option>
                  <option value="IVR System">IVR System</option>
                  <option value="Custom Software">Custom Software</option>
                  <option value="Hardware Appliance">Hardware Appliance</option>
                </select>
              </div>
            </div>

            <!-- 10. ISSUE CATEGORY -->
            <div class="form-group col-6">
              <label for="issue_category"><i class="fa-solid fa-tags"></i> Issue Category</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-tags input-icon"></i>
                <select id="issue_category" name="issue_category" class="form-control" required>
                  <option value="" disabled selected>Select Issue Category</option>
                  <option value="Software Bug">Software Bug</option>
                  <option value="License Issue">License Issue</option>
                  <option value="Hardware Malfunction">Hardware Malfunction</option>
                  <option value="Network Connectivity">Network Connectivity</option>
                  <option value="Installation / Setup">Installation / Setup</option>
                  <option value="User Training / How-to">User Training / How-to</option>
                  <option value="Feature Request">Feature Request</option>
                  <option value="Maintenance">Maintenance</option>
                </select>
              </div>
            </div>

            <!-- 11. ISSUE TYPE -->
            <div class="form-group col-6">
              <label for="issue_type"><i class="fa-solid fa-triangle-exclamation"></i> Issue Type</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-triangle-exclamation input-icon"></i>
                <select id="issue_type" name="issue_type" class="form-control" required>
                  <option value="" disabled selected>Select Issue Type</option>
                  <option value="System Crash / Freeze">System Crash / Freeze</option>
                  <option value="Data Loss / Corruption">Data Loss / Corruption</option>
                  <option value="Slow Performance">Slow Performance</option>
                  <option value="Registration / Activation Error">Registration / Activation Error</option>
                  <option value="Port / Connection Failure">Port / Connection Failure</option>
                  <option value="Call Drop Issue">Call Drop Issue</option>
                  <option value="Audio Quality / Noise">Audio Quality / Noise</option>
                  <option value="Invalid License Key">Invalid License Key</option>
                  <option value="Power Failure">Power Failure</option>
                  <option value="Others">Others</option>
                </select>
              </div>
            </div>

            <!-- 12. ISSUE DETAILS/NOTES -->
            <div class="form-group col-12">
              <label for="issue_details"><i class="fa-regular fa-file-lines"></i> Issue Details/Notes</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-file-lines input-icon" style="top: 18px;"></i>
                <textarea id="issue_details" name="issue_details" class="form-control" placeholder="Provide detailed notes regarding the issue reported..." required></textarea>
              </div>
            </div>

            <!-- 13. SUPPORT CATEGORY -->
            <div class="form-group col-6">
              <label for="support_category"><i class="fa-solid fa-layer-group"></i> Support Category</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-layer-group input-icon"></i>
                <select id="support_category" name="support_category" class="form-control" required>
                  <option value="" disabled selected>Select Support Category</option>
                  <option value="L1 Support (Basic)">L1 Support (Basic)</option>
                  <option value="L2 Support (Technical)">L2 Support (Technical)</option>
                  <option value="L3 Support (Developer)">L3 Support (Developer)</option>
                  <option value="On-Site Support">On-Site Support</option>
                  <option value="Remote Session">Remote Session</option>
                </select>
              </div>
            </div>

            <!-- 14. SOFTWARE DETAILS -->
            <div class="form-group col-12">
              <label for="software_details"><i class="fa-solid fa-laptop-code"></i> Software Details</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-laptop-code input-icon" style="top: 18px;"></i>
                <textarea id="software_details" name="software_details" class="form-control" placeholder="Operating system, database types, Xtend software versions, etc."></textarea>
              </div>
            </div>

            <!-- 15. HARDWARE DETAILS -->
            <div class="form-group col-12">
              <label for="hardware_details"><i class="fa-solid fa-microchip"></i> Hardware Details</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-microchip input-icon" style="top: 18px;"></i>
                <textarea id="hardware_details" name="hardware_details" class="form-control" placeholder="Server specs, voice logger cards, network switches, or hardware appliances involved..."></textarea>
              </div>
            </div>

            <!-- 16. SOLUTION -->
            <div class="form-group col-12">
              <label for="solution"><i class="fa-solid fa-check-double"></i> Solution</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-check-double input-icon" style="top: 18px;"></i>
                <textarea id="solution" name="solution" class="form-control" placeholder="Describe the steps and final resolution applied to resolve the issue..."></textarea>
              </div>
            </div>

            <!-- 17. TOTAL TIME -->
            <div class="form-group col-6">
              <label for="total_time"><i class="fa-regular fa-clock"></i> Total Time</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-clock input-icon"></i>
                <input type="text" id="total_time" name="total_time" class="form-control" placeholder="e.g. 45 mins, 2 hours" required>
              </div>
            </div>

            <!-- 18. SUPPORT STATUS -->
            <div class="form-group col-6">
              <label for="support_status"><i class="fa-solid fa-circle-info"></i> Support Status</label>
              <div class="form-control-wrapper select-wrapper">
                <i class="fa-solid fa-circle-info input-icon"></i>
                <select id="support_status" name="support_status" class="form-control" required>
                  <option value="" disabled selected>Select Support Status</option>
                  <option value="Open">Open</option>
                  <option value="In Progress">In Progress</option>
                  <option value="Pending Customer Response">Pending Customer Response</option>
                  <option value="Pending Developer Action">Pending Developer Action</option>
                  <option value="Resolved">Resolved</option>
                  <option value="Closed">Closed</option>
                </select>
              </div>
            </div>

            <!-- 19. REASON -->
            <div class="form-group col-12">
              <label for="reason"><i class="fa-solid fa-question"></i> Reason</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-question input-icon" style="top: 18px;"></i>
                <textarea id="reason" name="reason" class="form-control" placeholder="Specify the reason/justification for this status or closure..."></textarea>
              </div>
            </div>

            <!-- Form Action Buttons -->
            <div class="form-actions">
              <button type="button" id="resetBtn" class="btn btn-secondary">
                <i class="fa-solid fa-rotate-left"></i> Reset Form
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
        // 1. Pre-fill date input with today's local date
        const today = new Date().toISOString().split('T')[0];
        $('#date').val(today);

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
            
            // Trigger auto-height reset for textareas
            $('textarea').css('height', 'auto');
          }
        });

        // 4. Form submission handler (Frontend Design only - backend integration later)
        $('#newRecordForm').on('submit', function(e) {
          e.preventDefault();
          
          // Show interactive submission state feedback
          const $btn = $('#submitBtn');
          const originalText = $btn.html();
          
          $btn.prop('disabled', true).html('<i class="fa-solid fa-circle-notch fa-spin"></i> Saving...');
          
          setTimeout(function() {
            alert('Form frontend validation successful! Backend integration will be set up next.');
            $btn.prop('disabled', false).html(originalText);
          }, 1000);
        });
      });
    </script>
  </body>

</html>