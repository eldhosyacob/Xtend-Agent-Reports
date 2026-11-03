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
          <div class="table-filter-actions">
            <button type="button" id="btn-filter-latest-case" class="btn-filter-case-toggle" title="Show latest record only" aria-label="Filter: Show latest record only" aria-pressed="false">
              <i class="fa-solid fa-filter"></i>
            </button>
            <button class="btn-icon-text btn-primary" id="btn-export" disabled aria-label="Export to CSV">
              <i class="fa-solid fa-file-csv"></i> Export
            </button>
          </div>
        </div>

        <!-- Floating-Row Table View -->
        <div class="table-responsive-wrapper">
          <table class="custom-search-table" id="records-table">
            <thead>
              <tr>
                <th scope="col">Sl. No.</th>
                <th scope="col">Record ID</th>
                <th scope="col">Date</th>
                <th scope="col">Agent</th>
                <th scope="col">Company Name</th>
                <th scope="col">Dealer Name</th>
                <th scope="col">Location</th>
                <th scope="col">Product Category</th>
                <th scope="col">Hardware Details</th>
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
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
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
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
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
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
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

    <?php include 'components/header-sidebar.php'; ?>
    
    <script src="plugins/jquery-3.7.1.min.js"></script>
    <script>
      $(document).ready(function() {
        let allRecords = [];
        let displayedRecords = [];
        let filterLatestCaseOnly = false;
        let currentPage = 1;
        const pageSize = 10;

        function getRecordIdentifier(r) {
          if (!r) return '';
          return (r.record_id && String(r.record_id) !== 'null' && String(r.record_id) !== 'NULL' && String(r.record_id).trim() !== '') ? r.record_id : r.id;
        }

        const pageStorageKey = 'search_results_page_' + (window.location.search || '');

        function goToPage(page) {
          currentPage = page;
          sessionStorage.setItem(pageStorageKey, String(currentPage));
          renderRecords(displayedRecords);
          scrollToTableTop();
        }

        // Load records on page ready using the current URL query parameters
        loadSearchResults();

        // Listen for browser back/forward cache navigation
        window.addEventListener('pageshow', function(e) {
          if (e.persisted) {
            loadSearchResults();
          }
        });

        // Listen for record updates from other tabs
        window.addEventListener('storage', function(e) {
          if (e.key === 'record_updated') {
            loadSearchResults();
          }
        });

        // Refresh click trigger
        $('#btn-refresh').on('click', function() {
          const $icon = $(this).find('i');
          $icon.addClass('fa-spin');
          loadSearchResults(function() {
            $icon.removeClass('fa-spin');
          });
        });

        // Export CSV click trigger
        $('#btn-export').on('click', function() {
          const urlParams = new URLSearchParams(window.location.search);
          if (filterLatestCaseOnly) {
            urlParams.set('filter_latest_case', '1');
          } else {
            urlParams.delete('filter_latest_case');
          }
          const searchVal = $('#search-records').length ? $('#search-records').val().trim() : '';
          if (searchVal) {
            urlParams.set('q', searchVal);
          } else {
            urlParams.delete('q');
          }
          const queryString = urlParams.toString();
          window.location.href = 'api/export.php' + (queryString ? ('?' + queryString) : '');
        });

        function filterLatestPerCase(records) {
          const latestMap = new Map();

          records.forEach(function(r) {
            const rawCaseId = (r.case_id !== undefined && r.case_id !== null) ? String(r.case_id).trim() : '';
            // Group by case_id if present; otherwise treat record as distinct by its id
            const groupKey = (rawCaseId !== '') ? ('case_' + rawCaseId) : ('rec_' + (r.id || Math.random()));

            if (!latestMap.has(groupKey)) {
              latestMap.set(groupKey, r);
            } else {
              const existing = latestMap.get(groupKey);
              const dateA = String(r.date || '');
              const dateB = String(existing.date || '');
              const idA = parseInt(r.id, 10) || 0;
              const idB = parseInt(existing.id, 10) || 0;

              let isNewer = false;
              if (dateA > dateB) {
                isNewer = true;
              } else if (dateA === dateB) {
                if (idA > idB) {
                  isNewer = true;
                }
              }

              if (isNewer) {
                latestMap.set(groupKey, r);
              }
            }
          });

          const result = Array.from(latestMap.values());
          result.sort(function(a, b) {
            const dateA = String(a.date || '');
            const dateB = String(b.date || '');
            if (dateA === dateB) {
              return (parseInt(b.id, 10) || 0) - (parseInt(a.id, 10) || 0);
            }
            return dateB.localeCompare(dateA);
          });

          return result;
        }

        function applyFilters(resetPage = false) {
          let sourceRecords = allRecords;
          if (filterLatestCaseOnly) {
            sourceRecords = filterLatestPerCase(allRecords);
          }

          const val = ($('#search-records').length) ? $('#search-records').val().toLowerCase().trim() : '';
          if (val === '') {
            displayedRecords = sourceRecords;
          } else {
            displayedRecords = sourceRecords.filter(function(r) {
              const recId = String(getRecordIdentifier(r)).toLowerCase();
              const agent = (r.agent || '').toLowerCase();
              const comp = (r.company_name || '').toLowerCase();
              const dealer = (r.dealer_name || '').toLowerCase();
              const loc = (r.location || '').toLowerCase();
              const phone = (r.phone || '').toLowerCase();
              const prod = (r.product_category || '').toLowerCase();
              const hw = (r.hardware_details || '').toLowerCase();
              const status = (r.support_status || '').toLowerCase();
              const ticket = (r.ticket_id || '').toLowerCase();
              return recId.indexOf(val) > -1 || 
                     agent.indexOf(val) > -1 || 
                     comp.indexOf(val) > -1 || 
                     dealer.indexOf(val) > -1 || 
                     loc.indexOf(val) > -1 || 
                     phone.indexOf(val) > -1 || 
                     prod.indexOf(val) > -1 || 
                     hw.indexOf(val) > -1 || 
                     status.indexOf(val) > -1 || 
                     ticket.indexOf(val) > -1;
            });
          }

          // Update Badge text
          $('#results-badge').html('<i class="fa-solid fa-list-check" style="color: #ecae3b;"></i> <span style="color: #18ad9f; margin:0 4px;">'+displayedRecords.length+'</span><span style="color: #18678e;"> matching records</span>');

          // Update export button state
          $('#btn-export').prop('disabled', displayedRecords.length === 0);

          if (resetPage) {
            goToPage(1);
          } else {
            const totalPages = Math.max(1, Math.ceil(displayedRecords.length / pageSize));
            const savedPage = parseInt(sessionStorage.getItem(pageStorageKey), 10) || 1;
            currentPage = Math.min(Math.max(1, savedPage), totalPages);
            renderRecords(displayedRecords);
          }
        }

        // Filter text input inside results
        $('#search-records').on('input', function() {
          applyFilters(true);
        });

        // Filter latest record per case button
        $('#btn-filter-latest-case').on('click', function() {
          filterLatestCaseOnly = !filterLatestCaseOnly;
          $(this).toggleClass('active', filterLatestCaseOnly);
          $(this).attr('aria-pressed', filterLatestCaseOnly ? 'true' : 'false');
          if (filterLatestCaseOnly) {
            $(this).attr('title', 'Show all records');
          } else {
            $(this).attr('title', 'Show latest record only');
          }
          applyFilters(true);
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
                applyFilters(false);
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
                <td><div class="skeleton-line w-30"></div></td>
                <td><div class="skeleton-badge"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-70"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-badge"></div></td>
                <td class="actions-cell"><div class="skeleton-line w-60 center"></div></td>
              </tr>`;
          }
          $('#records-tbody').html(skeletonHtml);
          $('#pagination-wrapper').hide();
          $('#btn-export').prop('disabled', true);
        }

        function showError(msg) {
          $('#records-tbody').html(`
            <tr>
              <td colspan="11" class="table-error-cell">
                <i class="fa-solid fa-triangle-exclamation"></i>
                ${msg}
              </td>
            </tr>`);
          $('#pagination-wrapper').hide();
          $('#btn-export').prop('disabled', true);
        }

        function renderRecords(records) {
          const $tbody = $('#records-tbody');
          $tbody.empty();

          if (records.length === 0) {
            $tbody.html(`
              <tr>
                <td colspan="11">
                  <div class="table-empty-state">
                    <i class="fa-solid fa-folder-open"></i>
                    <h3>No Search Results</h3>
                    <p>Try modifying your search criteria filters in the Search page.</p>
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
            const deptVal = r.record_department || r.department || '';
            let reopenBtn = '';
            if (!statusLower.startsWith('closed') && r.is_latest !== false) {
              reopenBtn = `
                <a href="edit-record.php?record_id=${escapeHtml(getRecordIdentifier(r))}&department=${escapeHtml(deptVal)}" target="_blank" class="btn-action-reopen" data-record-id="${escapeHtml(getRecordIdentifier(r))}" data-department="${escapeHtml(deptVal)}" data-company-name="${escapeHtml(r.company_name || '')}" data-dealer-name="${escapeHtml(r.dealer_name || '')}" title="Reopen Record">
                  <i class="fa-solid fa-folder-open"></i>
                </a>`;
            }

            const row = `
              <tr>
                <td>${slNo}</td>
                <td>
                  <span class="record-id-badge">${escapeHtml(getRecordIdentifier(r))}</span>
                </td>
                <td>${escapeHtml(r.date || '—')}</td>
                <td>${escapeHtml(r.agent || '—')}</td>
                <td class="company-name-cell">${escapeHtml(r.company_name)}</td>
                <td>${escapeHtml(r.dealer_name || '—')}</td>
                <td>${escapeHtml(r.location || '—')}</td>
                <td>${escapeHtml(r.product_category || '—')}</td>
                <td>${escapeHtml(r.hardware_details || '—')}</td>
                <td>
                  <span class="status-pill ${statusClass}">
                    <i class="fa-solid fa-circle"></i> ${escapeHtml(rawStatus)}
                  </span>
                </td>
                <td class="actions-cell">
                  <button class="btn-action-view" data-record-id="${escapeHtml(getRecordIdentifier(r))}" data-department="${escapeHtml(deptVal)}" title="View Details">
                    <i class="fa-solid fa-eye"></i>
                  </button>
                  ${reopenBtn}
                </td>
              </tr>`;
            $tbody.append(row);
          });

          // Bind view details buttons
          $('.btn-action-view').off('click').on('click', function() {
            sessionStorage.setItem(pageStorageKey, String(currentPage));
            const recordId = $(this).attr('data-record-id');
            const dept = $(this).attr('data-department') || '';
            let url = 'view-record.php?record_id=' + encodeURIComponent(recordId);
            if (dept) url += '&department=' + encodeURIComponent(dept);
            window.location.href = url;
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
                // Fill the gap if it is only 1 page
                const fillPage = lastPage + 1;
                const btn = $('<button class="page-num-btn">' + fillPage + '</button>');
                btn.on('click', function() {
                  goToPage(fillPage);
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
                goToPage(page);
              }
            });
            $pagesContainer.append(btn);
            lastPage = page;
          });
        }

        // Setup Prev/Next click events
        $('#btn-prev').on('click', function() {
          if (currentPage > 1) {
            goToPage(currentPage - 1);
          }
        });

        $('#btn-next').on('click', function() {
          const totalPages = Math.ceil(displayedRecords.length / pageSize);
          if (currentPage < totalPages) {
            goToPage(currentPage + 1);
          }
        });

        function scrollToTableTop() {
          $('html, body').animate({
            scrollTop: $('.report-card-wrapper').offset().top - 20
          }, 300);
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