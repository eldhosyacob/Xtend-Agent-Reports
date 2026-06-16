<?php
require_once __DIR__ . '/config/auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>My Records - Report List</title>
    <link rel="stylesheet" href="styles/my-records.css">
    <link rel="stylesheet" href="styles/header-sidebar.css">
    <link rel="stylesheet" href="styles/common.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  </head>

  <body>
    <div class="my-records-page-container page-containers">
      
      <!-- Page Header -->
      <header class="page-header-wrapper">
        <div class="page-title-section">
          <h1>My Records</h1>
          <!-- <p id="current-date-label">Report of records submitted today: <?php echo date('l, M j, Y'); ?></p> -->
        </div>
        <div class="header-actions">
          <button class="btn-icon-text" id="btn-refresh" aria-label="Refresh list">
            <i class="fa-solid fa-rotate"></i> Refresh
          </button>
          <!-- <a href="new-record.php" target="_blank" class="btn-icon-text btn-primary">
            <i class="fa-solid fa-plus"></i> New Record
          </a> -->
        </div>
      </header>

      <!-- Stats Grid -->
      <section class="stats-grid-container" aria-label="Today's stats summary">
        <div class="stat-card-wrapper">
          <div class="stat-icon-box total" aria-hidden="true">
            <i class="fa-solid fa-list-check"></i>
          </div>
          <div class="stat-info-box">
            <span class="value" id="stat-total">—</span>
            <span class="label">Total Today</span>
          </div>
        </div>
        <div class="stat-card-wrapper">
          <div class="stat-icon-box pending" aria-hidden="true">
            <i class="fa-solid fa-hourglass-half"></i>
          </div>
          <div class="stat-info-box">
            <span class="value" id="stat-pending">—</span>
            <span class="label">Pending</span>
          </div>
        </div>
        <div class="stat-card-wrapper">
          <div class="stat-icon-box closed" aria-hidden="true">
            <i class="fa-solid fa-circle-check"></i>
          </div>
          <div class="stat-info-box">
            <span class="value" id="stat-closed">—</span>
            <span class="label">Closed</span>
          </div>
        </div>
        <div class="stat-card-wrapper">
          <div class="stat-icon-box others" aria-hidden="true">
            <i class="fa-solid fa-ellipsis"></i>
          </div>
          <div class="stat-info-box">
            <span class="value" id="stat-others">—</span>
            <span class="label">Others</span>
          </div>
        </div>
      </section>

      <!-- Main Report Card -->
      <main class="report-card-wrapper">
        
        <!-- Filter/Search Bar -->
        <div class="table-filter-bar">
          <div class="search-input-wrapper">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="text" id="search-records" class="search-control" placeholder="Search records..." aria-label="Search records">
          </div>
          <!-- <div class="department-info-badge" id="department-badge">
            Loading Department...
          </div> -->
        </div>

        <!-- Table View -->
        <div class="table-responsive-wrapper">
          <table class="custom-report-table" id="records-table">
            <thead>
              <tr>
                <th scope="col">Record ID</th>
                <th scope="col">Company Name</th>
                <th scope="col">Product Category</th>
                <th scope="col">Status</th>
                <th scope="col">Actions</th>
              </tr>
            </thead>
            <tbody id="records-tbody">
              <!-- Loading Skeleton Rows -->
              <tr class="skeleton-row">
                <td><div class="skeleton-badge"></div></td>
                <td><div class="skeleton-line w-70"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-badge"></div></td>
                <td class="actions-cell"><div class="skeleton-line w-60 center"></div></td>
              </tr>
              <tr class="skeleton-row">
                <td><div class="skeleton-badge"></div></td>
                <td><div class="skeleton-line w-70"></div></td>
                <td><div class="skeleton-line w-40"></div></td>
                <td><div class="skeleton-badge"></div></td>
                <td class="actions-cell"><div class="skeleton-line w-60 center"></div></td>
              </tr>
              <tr class="skeleton-row">
                <td><div class="skeleton-badge"></div></td>
                <td><div class="skeleton-line w-65"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
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
            
            <div class="detail-item">
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
        let activeDepartment = '';

        function getRecordIdentifier(r) {
          if (!r) return '';
          return (r.record_id && String(r.record_id) !== 'null' && String(r.record_id) !== 'NULL' && String(r.record_id).trim() !== '') ? r.record_id : r.id;
        }

        // Load records initially
        loadRecords();

        // Refresh action
        $('#btn-refresh').on('click', function() {
          const $icon = $(this).find('i');
          $icon.addClass('fa-spin');
          loadRecords(function() {
            $icon.removeClass('fa-spin');
          });
        });

        // Filter search input
        $('#search-records').on('input', function() {
          const val = $(this).val().toLowerCase().trim();
          if (val === '') {
            renderRecords(allRecords);
          } else {
            const filtered = allRecords.filter(function(r) {
              const recId = String(getRecordIdentifier(r)).toLowerCase();
              const comp = (r.company_name || '').toLowerCase();
              const prod = (r.product_category || '').toLowerCase();
              const status = (r.support_status || '').toLowerCase();
              return recId.indexOf(val) > -1 || comp.indexOf(val) > -1 || prod.indexOf(val) > -1 || status.indexOf(val) > -1;
            });
            renderRecords(filtered);
          }
        });

        // Close details modal
        $('#modal-close, #details-modal').on('click', function(e) {
          if (e.target === this) {
            closeModal();
          }
        });

        function loadRecords(callback) {
          // Display skeleton loading
          showSkeletons();

          $.ajax({
            url: 'api/fetch-my-records.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
              if (response.success) {
                allRecords = response.data || [];
                activeDepartment = response.department || 'N/A';
                
                // Update department badge
                $('#department-badge').html('<i class="fa-solid fa-sitemap"></i> Department: ' + activeDepartment);

                // Calculate and animate stats
                updateStats(allRecords);

                // Render rows
                renderRecords(allRecords);
              } else {
                showError(response.message || 'Failed to fetch records.');
              }
              if (typeof callback === 'function') callback();
            },
            error: function(xhr, status, error) {
              showError('Network error. Unable to load reports.');
              if (typeof callback === 'function') callback();
            }
          });
        }

        function showSkeletons() {
          let skeletonHtml = '';
          for (let i = 0; i < 3; i++) {
            skeletonHtml += `
              <tr class="skeleton-row">
                <td><div class="skeleton-badge"></div></td>
                <td><div class="skeleton-line w-70"></div></td>
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
              <td colspan="5" class="table-error-cell">
                <i class="fa-solid fa-triangle-exclamation"></i>
                ${msg}
              </td>
            </tr>`);
          $('#stat-total, #stat-pending, #stat-closed, #stat-others').text('—');
        }

        function updateStats(records) {
          const total = records.length;
          const pending = records.filter(r => (r.support_status || '').toLowerCase() === 'pending').length;
          const closed = records.filter(r => (r.support_status || '').toLowerCase().startsWith('closed')).length;
          const others = total - pending - closed;

          animateCounter($('#stat-total'), total);
          animateCounter($('#stat-pending'), pending);
          animateCounter($('#stat-closed'), closed);
          animateCounter($('#stat-others'), others);
        }

        function animateCounter($element, targetValue) {
          $element.prop('Counter', 0).animate({
            Counter: targetValue
          }, {
            duration: 800,
            easing: 'swing',
            step: function(now) {
              $element.text(Math.ceil(now));
            },
            complete: function() {
              $element.text(targetValue);
            }
          });
        }

        function renderRecords(records) {
          const $tbody = $('#records-tbody');
          $tbody.empty();

          if (records.length === 0) {
            $tbody.html(`
              <tr>
                <td colspan="5">
                  <div class="table-empty-state">
                    <i class="fa-solid fa-folder-open"></i>
                    <h3>No Records Found</h3>
                  </div>
                </td>
              </tr>`);
            return;
          }

          records.forEach(function(r) {
            // Map status classes
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

            let reopenBtn = '';
            if (statusLower !== 'closed' && statusLower !== 'closed-device replaced') {
              reopenBtn = `
                <a href="edit-record.php?record_id=${escapeHtml(getRecordIdentifier(r))}" target="_blank" class="btn-action-reopen" title="Reopen Record">
                  <i class="fa-solid fa-folder-open"></i>
                </a>`;
            }

            const row = `
              <tr>
                <td><span class="record-id-badge">${escapeHtml(getRecordIdentifier(r))}</span></td>
                <td class="company-name-cell">${escapeHtml(r.company_name)}</td>
                <td>${escapeHtml(r.product_category || '—')}</td>
                <td>
                  <span class="status-pill ${statusClass}">
                    <i class="fa-solid fa-circle"></i> ${escapeHtml(rawStatus)}
                  </span>
                </td>
                <td class="actions-cell">
                  <button class="btn-action-view" data-record-id="${escapeHtml(getRecordIdentifier(r))}" title="View Details">
                    <i class="fa-solid fa-eye"></i>
                  </button>
                  ${reopenBtn}
                </td>
              </tr>`;
            $tbody.append(row);
          });

          // Bind view buttons
          $('.btn-action-view').on('click', function() {
            const recordId = $(this).attr('data-record-id');
            showDetails(recordId);
          });
        }

        function showDetails(recordId) {
          const record = allRecords.find(r => String(getRecordIdentifier(r)) === String(recordId));
          if (!record) return;

          // Utility to handle empty values cleanly
          function valOrDash(val) {
            if (val === null || val === undefined) return '—';
            const str = String(val).trim();
            return str !== '' ? str : '—';
          }

          // Populate modal fields
          $('#detail-record-id').text(valOrDash(getRecordIdentifier(record)));
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
          $('#detail-software-details').val(valOrDash(record.software_details));
          $('#detail-hardware-details').val(valOrDash(record.hardware_details));
          $('#detail-solution').val(valOrDash(record.solution));
          $('#detail-start-time').text(valOrDash(record.support_start_time));
          $('#detail-end-time').text(valOrDash(record.support_end_time));
          $('#detail-total-time').text(valOrDash(record.total_time));
          $('#detail-support-status').text(valOrDash(record.support_status));
          $('#detail-ticket-id').text(valOrDash(record.ticket_id)); // reason field

          // Open Modal
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