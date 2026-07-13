<?php
// Secure the page - ensure user is authenticated
require_once 'config/auth_check.php';
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>View Record | Xtend Agent Reports</title>
    <link rel="shortcut icon" href="images/favicon.png" />
    <link rel="stylesheet" href="styles/header-sidebar.css">
    <link rel="stylesheet" href="styles/new_record.css">
    <link rel="stylesheet" href="styles/common.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="styles/view-record.css">
  </head>

  <body>
    <div class="new-record-page-container page-containers">
      <div class="form-card-wrapper">
        <div class="detail-header-info">
          <div class="record-id-title">
            <i class="fa-solid fa-file-invoice"></i>
            Record Details: <span id="title-record-id">—</span>
          </div>
          <div>
            <span class="badge-dept" id="dept-badge">—</span>
          </div>
        </div>

        <form id="viewRecordForm" autocomplete="off" onsubmit="return false;">
          <div class="grid-form">
            
            <!-- 1. DATE -->
            <div class="form-group col-4">
              <label>Date</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-calendar input-icon"></i>
                <input type="text" id="date" class="form-control" readonly>
              </div>
            </div>

            <!-- 2. AGENT -->
            <div class="form-group col-4">
              <label>Agent</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-user input-icon"></i>
                <input type="text" id="agent" class="form-control" readonly>
              </div>
            </div>

            <!-- 3. DEPARTMENT -->
            <div class="form-group col-4">
              <label>Department</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-sitemap input-icon"></i>
                <input type="text" id="department" class="form-control" readonly>
              </div>
            </div>

            <!-- 4. COMPANY NAME -->
            <div class="form-group col-6">
              <label>Company Name</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-building input-icon"></i>
                <input type="text" id="company_name" class="form-control" readonly>
              </div>
            </div>

            <!-- 5. LOCATION -->
            <div class="form-group col-6">
              <label>Location</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-location-dot input-icon"></i>
                <input type="text" id="location" class="form-control" readonly>
              </div>
            </div>

            <!-- 6. REGION -->
            <div class="form-group col-6">
              <label>Region</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-earth-americas input-icon"></i>
                <input type="text" id="region" class="form-control" readonly>
              </div>
            </div>

            <!-- 7. Email -->
            <div class="form-group col-6">
              <label>Email</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-envelope input-icon"></i>
                <input type="text" id="email" class="form-control" readonly>
              </div>
            </div>

            <!-- 8. PHONE -->
            <div class="form-group col-6">
              <label>Phone</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-phone input-icon"></i>
                <input type="text" id="phone" class="form-control" readonly>
              </div>
            </div>

            <!-- 9. CONTACT DETAILS -->
            <div class="form-group col-6">
              <label>Contact Details</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-address-book input-icon"></i>
                <input type="text" id="contact_details" class="form-control" readonly>
              </div>
            </div>

            <!-- 10. PRODUCT CATEGORY -->
            <div class="form-group col-6">
              <label>Product Category</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-cubes input-icon"></i>
                <input type="text" id="product_category" class="form-control" readonly>
              </div>
            </div>

            <!-- 11. ISSUE CATEGORY -->
            <div class="form-group col-6">
              <label>Issue Category</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-tags input-icon"></i>
                <input type="text" id="issue_category" class="form-control" readonly>
              </div>
            </div>

            <!-- 12. ISSUE TYPE -->
            <div class="form-group col-6">
              <label>Issue Type</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-triangle-exclamation input-icon"></i>
                <input type="text" id="issue_type" class="form-control" readonly>
              </div>
            </div>

            <!-- 13. ISSUE DETAILS/NOTES -->
            <div class="form-group col-12">
              <label>Issue Details/Notes</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-file-lines input-icon"></i>
                <textarea id="issue_details" class="form-control" readonly></textarea>
              </div>
            </div>

            <!-- 14. SUPPORT CATEGORY -->
            <div class="form-group col-6">
              <label>Support Category</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-layer-group input-icon"></i>
                <input type="text" id="support_category" class="form-control" readonly>
              </div>
            </div>

            <!-- 15. SOFTWARE DETAILS -->
            <div class="form-group col-6">
              <label>Software Details</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-laptop-code input-icon"></i>
                <input type="text" id="software_details" class="form-control" readonly>
              </div>
            </div>

            <!-- 16. HARDWARE DETAILS -->
            <div class="form-group col-6">
              <label>Hardware Details</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-microchip input-icon"></i>
                <input type="text" id="hardware_details" class="form-control" readonly>
              </div>
            </div>

            <!-- 17. SOLUTION -->
            <div class="form-group col-12">
              <label>Solution</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-check-double input-icon"></i>
                <textarea id="solution" class="form-control" readonly></textarea>
              </div>
            </div>

            <!-- SUPPORT START TIME -->
            <div class="form-group col-4">
              <label>Support Start Time</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-hourglass-start input-icon"></i>
                <input type="text" id="support_start_time" class="form-control" readonly>
              </div>
            </div>

            <!-- SUPPORT END TIME -->
            <div class="form-group col-4">
              <label>Support End Time</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-hourglass-end input-icon"></i>
                <input type="text" id="support_end_time" class="form-control" readonly>
              </div>
            </div>

            <!-- TOTAL TIME -->
            <div class="form-group col-4">
              <label>Total Time</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-clock input-icon"></i>
                <input type="text" id="total_time" class="form-control" readonly>
              </div>
            </div>

            <!-- SUPPORT STATUS -->
            <div class="form-group col-6">
              <label>Support Status</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-circle-info input-icon"></i>
                <input type="text" id="support_status" class="form-control" readonly>
              </div>
            </div>

            <!-- REASON / TICKET ID -->
            <div class="form-group col-6">
              <label>Ticket ID / Reason</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-question input-icon"></i>
                <input type="text" id="ticket_id" class="form-control" readonly>
              </div>
            </div>

          </div>

          <div class="form-actions">
            <button type="button" class="btn btn-secondary" id="btn-back">
              <i class="fa-solid fa-arrow-left"></i> Back
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Include Sidebar & Header Component -->
    <?php include 'components/header-sidebar.php'; ?>

    <script src="plugins/jquery-3.7.1.min.js"></script>
    <script>
      $(document).ready(function() {
        const urlParams = new URLSearchParams(window.location.search);
        const recordId = urlParams.get('record_id') || '';

        if (!recordId) {
          alert('Error: Record ID is required.');
          history.back();
          return;
        }

        // Back button action
        $('#btn-back').on('click', function() {
          history.back();
        });

        // Fetch details from the API
        $.getJSON('api/fetch-record-details.php', { record_id: recordId }, function(res) {
          if (res.success && res.data) {
            const data = res.data;
            
            function valOrDash(val) {
              if (val === null || val === undefined) return '—';
              const str = String(val).trim();
              return str !== '' ? str : '—';
            }

            const actualId = data.record_id || data.id;
            $('#title-record-id').text(actualId);
            $('#date').val(valOrDash(data.date));
            $('#agent').val(valOrDash(data.agent));
            $('#department').val(valOrDash(data.department));
            $('#company_name').val(valOrDash(data.company_name));
            $('#location').val(valOrDash(data.location));
            $('#region').val(valOrDash(data.region));
            $('#email').val(valOrDash(data.email));
            $('#phone').val(valOrDash(data.phone));
            $('#contact_details').val(valOrDash(data.contact_details));
            $('#product_category').val(valOrDash(data.product_category));
            $('#issue_category').val(valOrDash(data.issue_category));
            $('#issue_type').val(valOrDash(data.issue_type));
            $('#issue_details').val(valOrDash(data.issue_details));
            $('#support_category').val(valOrDash(data.support_category));
            $('#software_details').val(valOrDash(data.software_details));
            $('#hardware_details').val(valOrDash(data.hardware_details));
            $('#solution').val(valOrDash(data.solution));
            $('#support_start_time').val(valOrDash(data.support_start_time));
            $('#support_end_time').val(valOrDash(data.support_end_time));
            $('#total_time').val(valOrDash(data.total_time));
            $('#support_status').val(valOrDash(data.support_status));
            $('#ticket_id').val(valOrDash(data.ticket_id));

            // Set badge
            const isIvr = String(data.department).toLowerCase() === 'ivr';
            const badge = $('#dept-badge');
            badge.text(data.department);
            if (isIvr) {
              badge.addClass('ivr');
            }
          } else {
            alert('Error: ' + (res.error || 'Failed to fetch record details.'));
          }
        }).fail(function() {
          alert('Error: Request failed.');
        });
      });
    </script>
  </body>
</html>
