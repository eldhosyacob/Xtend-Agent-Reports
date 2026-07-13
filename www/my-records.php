<?php
require_once __DIR__ . '/config/auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>My Records - Report List</title>
    <link rel="shortcut icon" href="images/favicon.png" />
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
            <span class="label" id="stat-total-label">Total Today</span>
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
          <div class="filter-controls">
            <div class="search-input-wrapper">
              <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
              <input type="text" id="search-records" class="search-control" placeholder="Search records..." aria-label="Search records">
            </div>
            <div class="select-filter-wrapper">
              <i class="fa-solid fa-calendar-days select-icon"></i>
              <select id="date-range-filter" class="filter-select-control" aria-label="Filter by date range">
                <option value="today" selected>Today</option>
                <option value="week">This Week</option>
                <option value="month">This Month</option>
              </select>
            </div>
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
                <th scope="col">Sl. No.</th>
                <th scope="col">Record ID</th>
                <th scope="col">Date</th>
                <th scope="col">Company Name</th>
                <th scope="col">Product Category</th>
                <th scope="col">Status</th>
                <th scope="col">Actions</th>
              </tr>
            </thead>
            <tbody id="records-tbody">
              <!-- Loading Skeleton Rows -->
              <tr class="skeleton-row">
                <td><div class="skeleton-line w-30"></div></td>
                <td><div class="skeleton-badge"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-70"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-badge"></div></td>
                <td class="actions-cell"><div class="skeleton-line w-60 center"></div></td>
              </tr>
              <tr class="skeleton-row">
                <td><div class="skeleton-line w-30"></div></td>
                <td><div class="skeleton-badge"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-70"></div></td>
                <td><div class="skeleton-line w-40"></div></td>
                <td><div class="skeleton-badge"></div></td>
                <td class="actions-cell"><div class="skeleton-line w-60 center"></div></td>
              </tr>
              <tr class="skeleton-row">
                <td><div class="skeleton-line w-30"></div></td>
                <td><div class="skeleton-badge"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-65"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-badge"></div></td>
                <td class="actions-cell"><div class="skeleton-line w-60 center"></div></td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Controls -->
        <div class="pagination-container" id="pagination-wrapper">
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

    <?php include 'components/header-sidebar.php'; ?>
    
    <script src="plugins/jquery-3.7.1.min.js"></script>
    <script>
      $(document).ready(function() {
        let allRecords = [];
        let activeDepartment = '';
        let displayedRecords = [];
        let currentPage = 1;
        const pageSize = 10;

        function getRecordIdentifier(r) {
          if (!r) return '';
          return (r.record_id && String(r.record_id) !== 'null' && String(r.record_id) !== 'NULL' && String(r.record_id).trim() !== '') ? r.record_id : r.id;
        }

        // Load records initially
        loadRecords();

        // Listen for record updates from other tabs
        window.addEventListener('storage', function(e) {
          if (e.key === 'record_updated') {
            loadRecords();
          }
        });

        // Refresh action
        $('#btn-refresh').on('click', function() {
          const $icon = $(this).find('i');
          $icon.addClass('fa-spin');
          loadRecords(function() {
            $icon.removeClass('fa-spin');
          });
        });

        // Change date range filter
        $('#date-range-filter').on('change', function() {
          loadRecords();
        });

        // Filter search input
        $('#search-records').on('input', function() {
          const val = $(this).val().toLowerCase().trim();
          currentPage = 1;
          if (val === '') {
            displayedRecords = allRecords;
            renderRecords(displayedRecords);
          } else {
            const filtered = allRecords.filter(function(r) {
              const recId = String(getRecordIdentifier(r)).toLowerCase();
              const comp = (r.company_name || '').toLowerCase();
              const prod = (r.product_category || '').toLowerCase();
              const status = (r.support_status || '').toLowerCase();
              return recId.indexOf(val) > -1 || comp.indexOf(val) > -1 || prod.indexOf(val) > -1 || status.indexOf(val) > -1;
            });
            displayedRecords = filtered;
            renderRecords(displayedRecords);
          }
        });



        // Setup Prev/Next click events
        $('#btn-prev').on('click', function() {
          if (currentPage > 1) {
            currentPage--;
            renderRecords(displayedRecords);
            scrollToTableTop();
          }
        });

        $('#btn-next').on('click', function() {
          const totalPages = Math.ceil(displayedRecords.length / pageSize);
          if (currentPage < totalPages) {
            currentPage++;
            renderRecords(displayedRecords);
            scrollToTableTop();
          }
        });

        function scrollToTableTop() {
          $('html, body').animate({
            scrollTop: $('.report-card-wrapper').offset().top - 20
          }, 300);
        }

        function loadRecords(callback) {
          // Display skeleton loading
          showSkeletons();

          const range = $('#date-range-filter').val() || 'today';

          $.ajax({
            url: 'api/fetch-my-records.php',
            method: 'GET',
            data: { range: range },
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
                currentPage = 1;
                displayedRecords = allRecords;
                renderRecords(displayedRecords);
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
                <td><div class="skeleton-line w-30"></div></td>
                <td><div class="skeleton-badge"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-70"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-badge"></div></td>
                <td class="actions-cell"><div class="skeleton-line w-60 center"></div></td>
              </tr>`;
          }
          $('#records-tbody').html(skeletonHtml);
          $('#pagination-wrapper').hide();
        }

        function showError(msg) {
          $('#records-tbody').html(`
            <tr>
              <td colspan="7" class="table-error-cell">
                <i class="fa-solid fa-triangle-exclamation"></i>
                ${msg}
              </td>
            </tr>`);
          $('#stat-total, #stat-pending, #stat-closed, #stat-others').text('—');
          $('#pagination-wrapper').hide();
        }

        function updateStats(records) {
          const range = $('#date-range-filter').val() || 'today';
          let labelText = 'Total Today';
          if (range === 'week') {
            labelText = 'Total This Week';
          } else if (range === 'month') {
            labelText = 'Total This Month';
          }
          $('#stat-total-label').text(labelText);

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
                <td colspan="7">
                  <div class="table-empty-state">
                    <i class="fa-solid fa-folder-open"></i>
                    <h3>No Records Found</h3>
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
                <td class="company-name-cell">${escapeHtml(r.company_name) || '—'}</td>
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
          $('.btn-action-view').off('click').on('click', function() {
            const recordId = $(this).attr('data-record-id');
            window.location.href = 'view-record.php?record_id=' + encodeURIComponent(recordId);
          });

          // Draw pagination controls
          updatePaginationControls(records.length);
        }

        function updatePaginationControls(totalCount) {
          const totalPages = Math.ceil(totalCount / pageSize);

          if (totalPages <= 1) {
            $('#pagination-wrapper').hide();
            return;
          } else {
            $('#pagination-wrapper').css('display', 'flex');
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
                // Fill the gap if it is only 1 page
                const fillPage = lastPage + 1;
                const btn = $('<button class="page-num-btn">' + fillPage + '</button>');
                btn.on('click', function() {
                  currentPage = fillPage;
                  renderRecords(displayedRecords);
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
                renderRecords(displayedRecords);
                scrollToTableTop();
              }
            });
            $pagesContainer.append(btn);
            lastPage = page;
          });
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