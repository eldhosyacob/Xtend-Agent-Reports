<?php
// Secure the page - ensure user is authenticated
require_once 'config/auth_check.php';

$company_name = trim($_GET['company_name'] ?? '');
$add_to_company_list = intval($_GET['add_to_company_list'] ?? 0);

if ($company_name === '') {
    die("Error: Client name is required to view pending records.");
}
?>

<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Pending Records | Xtend Agent Reports</title>
    <link rel="shortcut icon" href="images/favicon.png" />
    <link rel="stylesheet" href="styles/view-pending-records.css">
    <link rel="stylesheet" href="styles/header-sidebar.css">
    <link rel="stylesheet" href="styles/common.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  </head>

  <body>
    <div class="search-results-page-container page-containers">
      
      <!-- Page Header -->
      <header class="page-header-wrapper">
        <div class="page-title-section pending-records-title-wrapper">
          <div class="pending-records-page-title">Pending Records for <span class="client-name-highlight"><?php echo htmlspecialchars($company_name); ?></span></div>
          <!-- <p>The client has existing pending or active support logs. Reopen one or proceed to create a new session.</p> -->
        </div>
        <div class="header-actions">
          <button class="btn-icon-text btn-create-direct" id="btn-create-record-direct" aria-label="Create new record without confirmation">
            <i class="fa-solid fa-plus"></i> Create New Record
          </button>
        </div>
      </header>

      <!-- Main Report Card -->
      <main class="report-card-wrapper">
        
        <div class="table-filter-bar">
          <div class="results-info-badge" id="results-badge">
            Checking for pending records...
          </div>
        </div>

        <!-- Floating-Row Table View -->
        <div class="table-responsive-wrapper">
          <table class="custom-search-table pending-records-table" id="records-table">
            <thead>
              <tr>
                <th scope="col">Sl. No.</th>
                <th scope="col">Record ID</th>
                <th scope="col">Date</th>
                <th scope="col">Agent</th>
                <th scope="col">Product Category</th>
                <th scope="col">Department</th>
                <th scope="col">Status</th>
                <th scope="col">Actions</th>
              </tr>
            </thead>
            <tbody id="records-tbody">
              <!-- Loading Skeleton Rows -->
              <tr class="skeleton-row">
                <td><div class="skeleton-line w-30"></div></td>
                <td><div class="skeleton-badge"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-70"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-badge"></div></td>
                <td class="actions-cell"><div class="skeleton-line w-60 center"></div></td>
              </tr>
              <tr class="skeleton-row">
                <td><div class="skeleton-line w-30"></div></td>
                <td><div class="skeleton-badge"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-70"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-badge"></div></td>
                <td class="actions-cell"><div class="skeleton-line w-60 center"></div></td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Controls -->
        <div class="pagination-container" id="pagination-wrapper" style="display: none;">
          <button class="btn-pagination" id="btn-prev" disabled>
            <i class="fa-solid fa-chevron-left"></i> Previous
          </button>
          <div class="pagination-pages" id="pagination-pages"></div>
          <button class="btn-pagination" id="btn-next" disabled>
            Next <i class="fa-solid fa-chevron-right"></i>
          </button>
        </div>

      </main>

    </div>

    <!-- Include Sidebar & Header Component -->
    <?php include 'components/header-sidebar.php'; ?>

    <script>
      $(document).ready(function() {
        const companyName = <?php echo json_encode($company_name); ?>;
        const addToCompanyList = <?php echo json_encode($add_to_company_list); ?>;
        let pendingRecords = [];
        let currentPage = 1;
        const pageSize = 10;

        function getRecordIdentifier(r) {
          if (!r) return '';
          return (r.record_id && String(r.record_id) !== 'null' && String(r.record_id) !== 'NULL' && String(r.record_id).trim() !== '') ? r.record_id : r.id;
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

        function loadPendingRecords() {
          $.ajax({
            url: 'api/fetch-pending-records.php',
            type: 'GET',
            data: { company_name: companyName },
            dataType: 'json',
            success: function(response) {
              if (response.success) {
                pendingRecords = response.data;
                $('#results-badge').text('Total Pending Records: ' + pendingRecords.length);
                renderPendingRecords(pendingRecords);
              } else {
                $('#results-badge').text('Error loading pending records');
                alert('Error loading records: ' + response.message);
              }
            },
            error: function() {
              $('#results-badge').text('Error loading pending records');
              alert('An error occurred while fetching pending records.');
            }
          });
        }

        function renderPendingRecords(records) {
          const $tbody = $('#records-tbody');
          $tbody.empty();

          if (records.length === 0) {
            $tbody.html(`
              <tr>
                <td colspan="8">
                  <div class="table-empty-state">
                    <i class="fa-solid fa-folder-open"></i>
                    <h3>No Active Pending Records Found</h3>
                  </div>
                </td>
              </tr>`);
            $('#pagination-wrapper').hide();
            return;
          }

          // Calculate pagination boundaries
          const startIndex = (currentPage - 1) * pageSize;
          const endIndex = Math.min(startIndex + pageSize, records.length);
          const paginatedRecords = records.slice(startIndex, endIndex);

          paginatedRecords.forEach(function(r, index) {
            const slNo = startIndex + index + 1;

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
            if (statusLower !== 'closed' && statusLower !== 'closed-device replaced' && r.is_latest !== false) {
              reopenBtn = `
                <a href="edit-record.php?record_id=${escapeHtml(getRecordIdentifier(r))}" target="_blank" class="btn-action-reopen" data-record-id="${escapeHtml(getRecordIdentifier(r))}" data-company-name="${escapeHtml(r.company_name || '')}" title="Reopen Record">
                  <i class="fa-solid fa-folder-open"></i>
                </a>`;
            }

            const row = `
              <tr>
                <td>${slNo}</td>
                <td><span class="record-id-badge">${escapeHtml(getRecordIdentifier(r))}</span></td>
                <td>${escapeHtml(r.date || '—')}</td>
                <td>${escapeHtml(r.agent || '—')}</td>
                <td>${escapeHtml(r.product_category || '—')}</td>
                <td><span class="dept-badge">${escapeHtml(r.record_department || '—')}</span></td>
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
          $('.btn-action-view').off('click').on('click', function() {
            const recordId = $(this).attr('data-record-id');
            window.location.href = 'view-record.php?record_id=' + encodeURIComponent(recordId);
          });

          // Draw pagination controls
          updatePaginationControls(records.length);
        }



        // Create record directly button
        $('#btn-create-record-direct').on('click', function() {
          const $btn = $(this);
          const originalHtml = $btn.html();
          $btn.prop('disabled', true).html('<i class="fa-solid fa-circle-notch fa-spin"></i> Creating...');

          $.ajax({
            url: 'api/create-pending-record.php',
            type: 'POST',
            data: { 
              company_name: companyName,
              add_to_company_list: addToCompanyList
            },
            dataType: 'json',
            success: function(response) {
              if (response.success) {
                // Open new record page in a new tab as normally
                window.open('new-record.php?id=' + encodeURIComponent(response.record_id), '_blank');
                // Redirect current tab back to dashboard
                window.location.href = 'dashboard.php';
              } else {
                alert('Error creating record: ' + response.message);
                $btn.prop('disabled', false).html(originalHtml);
              }
            },
            error: function() {
              alert('An error occurred while creating the record.');
              $btn.prop('disabled', false).html(originalHtml);
            }
          });
        });

        function updatePaginationControls(totalCount) {
          const totalPages = Math.ceil(totalCount / pageSize);

          if (totalPages <= 1) {
            $('#pagination-wrapper').hide();
            return;
          } else {
            $('#pagination-wrapper').show();
          }

          // Update Prev/Next buttons state
          $('#btn-prev').prop('disabled', currentPage === 1).toggleClass('disabled', currentPage === 1);
          $('#btn-next').prop('disabled', currentPage === totalPages).toggleClass('disabled', currentPage === totalPages);

          const $pagesContainer = $('#pagination-pages');
          $pagesContainer.empty();

          const range = 1;
          const pages = [];
          for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - range && i <= currentPage + range)) {
              pages.push(i);
            }
          }

          let lastPage = 0;
          pages.forEach(function(page) {
            if (lastPage !== 0) {
              if (page - lastPage === 2) {
                const fillPage = lastPage + 1;
                const btn = $('<button class="page-num-btn">' + fillPage + '</button>');
                btn.on('click', function() {
                  currentPage = fillPage;
                  renderPendingRecords(pendingRecords);
                  scrollToTableTop();
                });
                $pagesContainer.append(btn);
              } else if (page - lastPage > 2) {
                $pagesContainer.append('<span class="pagination-ellipsis">...</span>');
              }
            }

            const btn = $('<button class="page-num-btn">' + page + '</button>');
            if (page === currentPage) {
              btn.addClass('active');
            }
            btn.on('click', function() {
              if (currentPage !== page) {
                currentPage = page;
                renderPendingRecords(pendingRecords);
                scrollToTableTop();
              }
            });
            $pagesContainer.append(btn);
            lastPage = page;
          });
        }

        // Setup Prev/Next click events
        $('#btn-prev').on('click', function() {
          if (currentPage > 1) {
            currentPage--;
            renderPendingRecords(pendingRecords);
            scrollToTableTop();
          }
        });

        $('#btn-next').on('click', function() {
          const totalPages = Math.ceil(pendingRecords.length / pageSize);
          if (currentPage < totalPages) {
            currentPage++;
            renderPendingRecords(pendingRecords);
            scrollToTableTop();
          }
        });

        function scrollToTableTop() {
          $('html, body').animate({
            scrollTop: $('.report-card-wrapper').offset().top - 20
          }, 300);
        }

        // Initialize loading
        loadPendingRecords();
      });
    </script>
  </body>

</html>
