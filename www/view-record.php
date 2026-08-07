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

            <!-- DEALER NAME -->
            <div class="form-group col-6">
              <label>Dealer Name</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-user-tie input-icon"></i>
                <input type="text" id="dealer_name" class="form-control" readonly>
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

            <!-- PRODUCT MODEL -->
            <div class="form-group col-6">
              <label>Product Model</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-box-open input-icon"></i>
                <input type="text" id="product_model" class="form-control" readonly>
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

            <!-- SUPPORT TIME HISTORY -->
            <div class="form-group col-12 support-time-history-group" id="support-time-history-group">
              <label>Support Time History</label>
              <div class="support-time-history-container">
                <div class="support-time-history-list" id="support-time-history-list">
                  <!-- Dynamically populated -->
                </div>
              </div>
            </div>

            <!-- SUPPORT STATUS -->
            <div class="form-group col-6">
              <label>Support Status</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-circle-info input-icon"></i>
                <input type="text" id="support_status" class="form-control" readonly>
              </div>
              <div class="status-history-btn-container">
                <button type="button" id="btn-view-status-history" class="btn-view-status-history">
                  <i class="fa-solid fa-clock-rotate-left"></i> View History
                </button>
              </div>
            </div>

            <!-- REASON -->
            <div class="form-group col-12">
              <label>Reason</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-question input-icon" style="top: 18px;"></i>
                <textarea id="reason" class="form-control" readonly></textarea>
              </div>
            </div>

            <!-- TICKET ID -->
            <div class="form-group col-12">
              <label>Ticket Id</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-ticket input-icon"></i>
                <input type="text" id="ticket_id" class="form-control" readonly>
              </div>
            </div>

            <!-- ATTACHMENTS (FILE DOWNLOAD) -->
            <div class="form-group col-12 attachments-group">
              <label>Uploaded Attachments</label>
              <div class="attachments-list" id="attachments-list">
                <!-- Dynamically populated -->
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

    <!-- Status History Modal Dialog Overlay -->
    <div id="statusHistoryModal" class="status-history-modal-overlay">
      <div class="status-history-modal-content">
        <div class="status-history-modal-header">
          <h2 class="status-history-modal-title">
            <i class="fa-solid fa-clock-rotate-left"></i> Status Update History
          </h2>
          <button type="button" class="status-history-modal-close" id="btnCloseStatusModalX" aria-label="Close modal">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
        <div class="status-history-modal-body">
          <div class="status-history-timeline" id="status-history-timeline">
            <!-- Dynamically populated -->
          </div>
        </div>
        <div class="status-history-modal-actions">
          <button type="button" class="btn btn-secondary" id="btnCloseStatusModal">Close</button>
        </div>
      </div>
    </div>

    <!-- Include Sidebar & Header Component -->
    <?php include 'components/header-sidebar.php'; ?>

    <script src="plugins/jquery-3.7.1.min.js"></script>
    <script>
      $(document).ready(function() {
        const urlParams = new URLSearchParams(window.location.search);
        const recordId = urlParams.get('record_id') || urlParams.get('id') || '';
        const department = urlParams.get('department') || urlParams.get('dept') || '';
        let statusHistory = [];

        if (!recordId) {
          alert('Error: Record ID is required.');
          history.back();
          return;
        }

        // Back button action
        $('#btn-back').on('click', function() {
          history.back();
        });

        function getStatusBadgeClass(status) {
          const s = String(status).toLowerCase();
          if (s.includes('closed')) return 'badge-closed';
          if (s.includes('pending')) return 'badge-pending';
          if (s.includes('observation')) return 'badge-under-observation';
          if (s.includes('escalated')) return 'badge-escalated';
          return 'badge-default';
        }

        function getTimelineItemClass(status) {
          const s = String(status).toLowerCase();
          if (s.includes('closed')) return 'status-closed';
          if (s.includes('pending')) return 'status-pending';
          if (s.includes('observation')) return 'status-under-observation';
          if (s.includes('escalated')) return 'status-escalated';
          return '';
        }

        function formatTimelineDate(dateStr) {
          if (!dateStr) return '';
          try {
            const d = new Date(dateStr.replace(' ', 'T'));
            if (isNaN(d.getTime())) return dateStr;
            return d.toLocaleString('en-US', { 
              month: 'short', 
              day: 'numeric', 
              year: 'numeric', 
              hour: 'numeric', 
              minute: '2-digit', 
              hour12: true 
            });
          } catch (e) {
            return dateStr;
          }
        }

        // View status history click action
        $('#btn-view-status-history').on('click', function() {
          const timeline = $('#status-history-timeline');
          timeline.empty();
          
          if (statusHistory.length === 0) {
            timeline.append('<div class="timeline-empty">No status updates recorded for this case.</div>');
          } else {
            statusHistory.forEach(function(item, idx) {
              const slNo = idx + 1;
              const badgeClass = getStatusBadgeClass(item.support_status);
              const timelineClass = getTimelineItemClass(item.support_status);
              const formattedDate = formatTimelineDate(item.date);
              const dateHtml = formattedDate ? `
                <div class="timeline-time">
                  <i class="fa-regular fa-calendar"></i> <span>${formattedDate}</span>
                </div>
              ` : '';
              const html = `
                <div class="timeline-item ${timelineClass}">
                  <div class="timeline-marker"></div>
                  <div class="timeline-content">
                    <div class="timeline-header">
                      <span class="timeline-sl-no">Sl No. ${slNo}</span>
                      <span class="timeline-status-badge ${badgeClass}">${item.support_status}</span>
                    </div>
                    <div class="timeline-agent">Updated by: <strong>${item.agent}</strong></div>
                    ${dateHtml}
                  </div>
                </div>
              `;
              timeline.append(html);
            });
          }
          $('#statusHistoryModal').addClass('active');
        });

        // Close modal actions
        $('#btnCloseStatusModal, #btnCloseStatusModalX').on('click', function() {
          $('#statusHistoryModal').removeClass('active');
        });

        $('#statusHistoryModal').on('click', function(e) {
          if ($(e.target).is('#statusHistoryModal')) {
            $(this).removeClass('active');
          }
        });

        // Fetch details from the API
        $.getJSON('api/fetch-record-details.php', { record_id: recordId, department: department }, function(res) {
          if (res.success && res.data) {
            const data = res.data;
            statusHistory = data.status_history || [];
            
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
            $('#dealer_name').val(valOrDash(data.dealer_name));
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
            $('#product_model').val(valOrDash(data.product_model));
            $('#solution').val(valOrDash(data.solution));
            $('#support_start_time').val(valOrDash(data.support_start_time));
            $('#support_end_time').val(valOrDash(data.support_end_time));
            $('#total_time').val(valOrDash(data.total_time));
            $('#support_status').val(valOrDash(data.support_status));
            $('#reason').val(valOrDash(data.reason));
            $('#ticket_id').val(valOrDash(data.ticket_id));

            // Populate Support Time History
            const historyList = $('#support-time-history-list');
            historyList.empty();
            if (data.time_history && data.time_history.length > 0) {
              $('#support-time-history-group').show();
              data.time_history.forEach(function(item, idx) {
                let durationStr = '';
                if (item.support_start_time && item.support_end_time) {
                  const startParts = item.support_start_time.split(':');
                  const endParts = item.support_end_time.split(':');
                  const startSecs = parseInt(startParts[0] || 0)*3600 + parseInt(startParts[1] || 0)*60 + parseInt(startParts[2] || 0);
                  const endSecs = parseInt(endParts[0] || 0)*3600 + parseInt(endParts[1] || 0)*60 + parseInt(endParts[2] || 0);
                  let diffSecs = endSecs - startSecs;
                  if (diffSecs < 0) diffSecs += 24*3600; // handle cross-midnight
                  
                  const hrs = Math.floor(diffSecs / 3600);
                  const mins = Math.floor((diffSecs % 3600) / 60);
                  const secs = diffSecs % 60;
                  
                  const parts = [];
                  if (hrs > 0) parts.push(hrs + ' hr' + (hrs > 1 ? 's' : ''));
                  if (mins > 0) parts.push(mins + ' min' + (mins > 1 ? 's' : ''));
                  if (secs > 0 || parts.length === 0) parts.push(secs + ' sec' + (secs > 1 ? 's' : ''));
                  durationStr = parts.join(' ');
                }
                
                const html = `
                  <div class="history-item">
                    <div class="history-item-times">
                      <i class="fa-regular fa-clock"></i>
                      <span>Session ${idx + 1}: <strong>${item.support_start_time}</strong> &mdash; <strong>${item.support_end_time}</strong></span>
                    </div>
                    ${durationStr ? `<div class="history-item-duration">${durationStr}</div>` : ''}
                  </div>
                `;
                historyList.append(html);
              });
            } else {
              $('#support-time-history-group').hide();
            }

            // Populate Uploaded Attachments
            const attachmentsList = $('#attachments-list');
            attachmentsList.empty();
            if (data.attachments && data.attachments.length > 0) {
              data.attachments.forEach(function(att) {
                const ext = att.name.split('.').pop().toLowerCase();
                let iconClass = 'fa-regular fa-file';
                if (['pdf'].includes(ext)) iconClass = 'fa-regular fa-file-pdf';
                else if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) iconClass = 'fa-regular fa-file-image';
                else if (['csv', 'xls', 'xlsx'].includes(ext)) iconClass = 'fa-regular fa-file-excel';
                else if (['doc', 'docx'].includes(ext)) iconClass = 'fa-regular fa-file-word';
                else if (['zip', 'rar', '7z'].includes(ext)) iconClass = 'fa-regular fa-file-zipper';

                const html = `
                  <div class="attachment-card">
                    <div class="attachment-info">
                      <div class="attachment-icon">
                        <i class="${iconClass}"></i>
                      </div>
                      <div class="attachment-details">
                        <span class="attachment-name">${att.name}</span>
                        <span class="attachment-meta">${att.size}</span>
                      </div>
                    </div>
                    <a href="${att.url}" download class="btn-download-file">
                      <i class="fa-solid fa-download"></i> Download
                    </a>
                  </div>
                `;
                attachmentsList.append(html);
              });
            } else {
              attachmentsList.append('<div class="no-attachments-text">No files uploaded for this record.</div>');
            }

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
