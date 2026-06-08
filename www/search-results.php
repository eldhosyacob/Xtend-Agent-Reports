<?php
require_once __DIR__ . '/config/auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Search Results | Xtend Agent Reports</title>
    <link rel="shortcut icon" href="images/favicon.png" />
    <link rel="stylesheet" href="styles/search-results.css">
    <link rel="stylesheet" href="styles/header-sidebar.css">
    <link rel="stylesheet" href="styles/common.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  </head>

  <body>
    <div class="search-results-page-container page-containers">
      
      <!-- Page Header -->
      <header class="page-header-wrapper">
        <div class="page-title-section">
          <h1>Search Results</h1>
          <!-- <p>Showing records matching your search criteria</p> -->
        </div>
        <!-- <div class="header-actions">
          <a href="search.php" class="btn-icon-text" aria-label="Back to Search">
            <i class="fa-solid fa-arrow-left"></i> Back to Search
          </a>
          <button class="btn-icon-text btn-primary" id="btn-refresh" aria-label="Refresh results">
            <i class="fa-solid fa-rotate"></i> Refresh
          </button>
        </div> -->
      </header>

      <!-- Main Report Card -->
      <main class="report-card-wrapper">
        
        <!-- Filter/Search Bar within results -->
        <div class="table-filter-bar">
          <!-- <div class="search-input-wrapper">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="text" id="search-records" class="search-control" placeholder="Filter current results..." aria-label="Filter results">
          </div> -->
          <div class="results-info-badge" id="results-badge">
            Loading results...
          </div>
        </div>

        <!-- Floating-Row Table View -->
        <div class="table-responsive-wrapper">
          <table class="custom-search-table" id="records-table">
            <thead>
              <tr>
                <th scope="col">Record ID</th>
                <th scope="col">Date</th>
                <th scope="col">Agent</th>
                <th scope="col">Company Name</th>
                <th scope="col">Product Category</th>
                <th scope="col">Ticket ID</th>
                <th scope="col">Status</th>
                <th scope="col">Actions</th>
              </tr>
            </thead>
            <tbody id="records-tbody">
              <!-- Loading Skeleton Rows -->
              <tr class="skeleton-row">
                <td><div class="skeleton-badge"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-70"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-badge"></div></td>
                <td class="actions-cell"><div class="skeleton-line w-60 center"></div></td>
              </tr>
              <tr class="skeleton-row">
                <td><div class="skeleton-badge"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-70"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-badge"></div></td>
                <td class="actions-cell"><div class="skeleton-line w-60 center"></div></td>
              </tr>
              <tr class="skeleton-row">
                <td><div class="skeleton-badge"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-70"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-badge"></div></td>
                <td class="actions-cell"><div class="skeleton-line w-60 center"></div></td>
              </tr>
            </tbody>
          </table>
        </div>

      </main>

    </div>

    <!-- Glassmorphism Detailed Record View Modal -->
    <div class="modal-overlay" id="details-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
      <div class="modal-content-card">
        <header class="modal-header">
          <h2 id="modal-title"><i class="fa-solid fa-file-invoice"></i> Record Details</h2>
          <button class="modal-close-btn" id="modal-close" aria-label="Close details dialog">&times;</button>
        </header>
        <div class="modal-body">
          <form class="details-grid" onsubmit="return false;">
            
            <div class="detail-item">
              <label>Record ID</label>
              <div class="value-box" id="detail-record-id"></div>
            </div>
            <div class="detail-item">
              <label>Date</label>
              <div class="value-box" id="detail-date"></div>
            </div>
            
            <div class="detail-item">
              <label>Agent Name</label>
              <div class="value-box" id="detail-agent"></div>
            </div>
            <div class="detail-item">
              <label>Company Name</label>
              <div class="value-box" id="detail-company-name"></div>
            </div>

            <div class="detail-item">
              <label>Location</label>
              <div class="value-box" id="detail-location"></div>
            </div>
            <div class="detail-item">
              <label>Region</label>
              <div class="value-box" id="detail-region"></div>
            </div>

            <div class="detail-item">
              <label>Email</label>
              <div class="value-box" id="detail-email"></div>
            </div>
            <div class="detail-item">
              <label>Phone</label>
              <div class="value-box" id="detail-phone"></div>
            </div>

            <div class="detail-item">
              <label>Contact Details</label>
              <div class="value-box" id="detail-contact-details"></div>
            </div>
            <div class="detail-item">
              <label>Product Category</label>
              <div class="value-box" id="detail-product-category"></div>
            </div>

            <div class="detail-item">
              <label>Issue Category</label>
              <div class="value-box" id="detail-issue-category"></div>
            </div>
            <div class="detail-item">
              <label>Issue Type</label>
              <div class="value-box" id="detail-issue-type"></div>
            </div>

            <div class="detail-item full-width">
              <label>Issue Notes/Details</label>
              <textarea class="value-box" id="detail-issue-details" readonly></textarea>
            </div>

            <div class="detail-item">
              <label>Support Category</label>
              <div class="value-box" id="detail-support-category"></div>
            </div>
            <div class="detail-item">
              <label>Department / Table</label>
              <div class="value-box" id="detail-department"></div>
            </div>
            
            <div class="detail-item full-width">
              <label>Software Details</label>
              <textarea class="value-box" id="detail-software-details" readonly></textarea>
            </div>
            
            <div class="detail-item full-width">
              <label>Hardware Details</label>
              <textarea class="value-box" id="detail-hardware-details" readonly></textarea>
            </div>
            
            <div class="detail-item full-width">
              <label>Solution</label>
              <textarea class="value-box" id="detail-solution" readonly></textarea>
            </div>
            
            <div class="detail-item">
              <label>Support Start Time</label>
              <div class="value-box" id="detail-start-time"></div>
            </div>

            <div class="detail-item">
              <label>Support End Time</label>
              <div class="value-box" id="detail-end-time"></div>
            </div>

            <div class="detail-item">
              <label>Total Time</label>
              <div class="value-box" id="detail-total-time"></div>
            </div>
            
            <div class="detail-item">
              <label>Support Status</label>
              <div class="value-box" id="detail-support-status"></div>
            </div>
            
            <div class="detail-item full-width">
              <label>Ticket ID / Reason</label>
              <div class="value-box" id="detail-ticket-id"></div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <?php include 'components/header-sidebar.php'; ?>
    
    <script src="plugins/jquery-3.7.1.min.js"></script>
    <script>
      $(document).ready(function() {
        let allRecords = [];

        // Load records on page ready using the current URL query parameters
        loadSearchResults();

        // Refresh click trigger
        $('#btn-refresh').on('click', function() {
          const $icon = $(this).find('i');
          $icon.addClass('fa-spin');
          loadSearchResults(function() {
            $icon.removeClass('fa-spin');
          });
        });

        // Filter text input inside results
        $('#search-records').on('input', function() {
          const val = $(this).val().toLowerCase().trim();
          if (val === '') {
            renderRecords(allRecords);
          } else {
            const filtered = allRecords.filter(function(r) {
              const recId = (r.record_id || '').toLowerCase();
              const agent = (r.agent || '').toLowerCase();
              const comp = (r.company_name || '').toLowerCase();
              const prod = (r.product_category || '').toLowerCase();
              const status = (r.support_status || '').toLowerCase();
              const ticket = (r.ticket_id || '').toLowerCase();
              return recId.indexOf(val) > -1 || 
                     agent.indexOf(val) > -1 || 
                     comp.indexOf(val) > -1 || 
                     prod.indexOf(val) > -1 || 
                     status.indexOf(val) > -1 ||
                     ticket.indexOf(val) > -1;
            });
            renderRecords(filtered);
          }
        });

        // Close details modal clicks
        $('#modal-close, #details-modal').on('click', function(e) {
          if (e.target === this) {
            closeModal();
          }
        });

        function loadSearchResults(callback) {
          showSkeletons();
          const queryParams = window.location.search;

          $.ajax({
            url: 'api/fetch-search-results.php' + queryParams,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
              if (response.success) {
                allRecords = response.data || [];
                
                // Update Badge text
                $('#results-badge').html('<i class="fa-solid fa-list-check" style="color: #ecae3b;"></i> <span style="color: #18ad9f; margin:0 4px;">'+allRecords.length+'</span><span style="color: #18678e;"> matching records</span>');

                // Populate rows
                renderRecords(allRecords);
              } else {
                showError(response.message || 'Failed to fetch search results.');
              }
              if (typeof callback === 'function') callback();
            },
            error: function(xhr, status, error) {
              showError('Network error. Unable to load search results.');
              if (typeof callback === 'function') callback();
            }
          });
        }

        function showSkeletons() {
          let skeletonHtml = '';
          for (let i = 0; i < 4; i++) {
            skeletonHtml += `
              <tr class="skeleton-row">
                <td><div class="skeleton-badge"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-70"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-badge"></div></td>
                <td class="actions-cell"><div class="skeleton-line w-60 center"></div></td>
              </tr>`;
          }
          $('#records-tbody').html(skeletonHtml);
        }

        function showError(msg) {
          $('#records-tbody').html(`
            <tr>
              <td colspan="8" class="table-error-cell">
                <i class="fa-solid fa-triangle-exclamation"></i>
                ${msg}
              </td>
            </tr>`);
        }

        function renderRecords(records) {
          const $tbody = $('#records-tbody');
          $tbody.empty();

          if (records.length === 0) {
            $tbody.html(`
              <tr>
                <td colspan="8">
                  <div class="table-empty-state">
                    <i class="fa-solid fa-folder-open"></i>
                    <h3>No Search Results</h3>
                    <p>Try modifying your search criteria filters in the Search page.</p>
                  </div>
                </td>
              </tr>`);
            return;
          }

          records.forEach(function(r) {
            // Map status CSS classes
            let rawStatus = r.support_status || 'Pending';
            let statusLower = rawStatus.toLowerCase();
            let statusClass = 'status-pending';

            if (statusLower.startsWith('closed')) {
              statusClass = 'status-closed';
            } else if (statusLower === 'pending') {
              statusClass = 'status-pending';
            } else if (statusLower === 'under observation') {
              statusClass = 'status-under-observation';
            } else if (statusLower.startsWith('escalated')) {
              statusClass = 'status-escalated';
            }

            // Edit Reopen Button mapping
            let reopenBtn = '';
            if (statusLower !== 'closed' && statusLower !== 'closed-device replaced') {
              reopenBtn = `
                <a href="edit-record.php?record_id=${escapeHtml(r.record_id)}" target="_blank" class="btn-action-reopen" title="Reopen Record">
                  <i class="fa-solid fa-folder-open"></i>
                </a>`;
            }

            const row = `
              <tr>
                <td>
                  <span class="record-id-badge">${escapeHtml(r.record_id)}</span>
                </td>
                <td>${escapeHtml(r.date || '—')}</td>
                <td>${escapeHtml(r.agent || '—')}</td>
                <td class="company-name-cell">${escapeHtml(r.company_name)}</td>
                <td>${escapeHtml(r.product_category || '—')}</td>
                <td><span class="ticket-id-badge">${escapeHtml(r.ticket_id || '—')}</span></td>
                <td>
                  <span class="status-pill ${statusClass}">
                    <i class="fa-solid fa-circle"></i> ${escapeHtml(rawStatus)}
                  </span>
                </td>
                <td class="actions-cell">
                  <button class="btn-action-view" data-record-id="${escapeHtml(r.record_id)}" title="View Details">
                    <i class="fa-solid fa-eye"></i>
                  </button>
                  ${reopenBtn}
                </td>
              </tr>`;
            $tbody.append(row);
          });

          // Bind view details buttons
          $('.btn-action-view').on('click', function() {
            const recordId = $(this).data('record-id');
            showDetails(recordId);
          });
        }

        function showDetails(recordId) {
          const record = allRecords.find(r => r.record_id === recordId);
          if (!record) return;

          function valOrDash(val) {
            return (val && val.trim() !== '') ? val : '—';
          }

          // Assign record values to modal container fields
          $('#detail-record-id').text(valOrDash(record.record_id));
          $('#detail-date').text(valOrDash(record.date));
          $('#detail-agent').text(valOrDash(record.agent));
          $('#detail-company-name').text(valOrDash(record.company_name));
          $('#detail-location').text(valOrDash(record.location));
          $('#detail-region').text(valOrDash(record.region));
          
          $('#detail-email').text(valOrDash(record.email));
          $('#detail-phone').text(valOrDash(record.phone));
          $('#detail-contact-details').text(valOrDash(record.contact_details));
          $('#detail-product-category').text(valOrDash(record.product_category));
          
          $('#detail-issue-category').text(valOrDash(record.issue_category));
          $('#detail-issue-type').text(valOrDash(record.issue_type));
          
          $('#detail-issue-details').val(valOrDash(record.issue_details));
          $('#detail-support-category').text(valOrDash(record.support_category));
          $('#detail-department').text(valOrDash(record.record_department));
          $('#detail-software-details').val(valOrDash(record.software_details));
          $('#detail-hardware-details').val(valOrDash(record.hardware_details));
          $('#detail-solution').val(valOrDash(record.solution));
          $('#detail-start-time').text(valOrDash(record.support_start_time));
          $('#detail-end-time').text(valOrDash(record.support_end_time));
          $('#detail-total-time').text(valOrDash(record.total_time));
          $('#detail-support-status').text(valOrDash(record.support_status));
          $('#detail-ticket-id').text(valOrDash(record.ticket_id));

          // Toggle modal active state
          $('#details-modal').addClass('active');
        }

        function closeModal() {
          $('#details-modal').removeClass('active');
        }

        function escapeHtml(text) {
          if (!text) return '';
          return text
            .toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
        }
      });
    </script>
  </body>

</html>