<?php
require_once __DIR__ . '/config/auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Miscellaneous Search Results | Xtend Agent Reports</title>
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
          <h1>Miscellaneous Search Results</h1>
        </div>
      </header>

      <!-- Main Report Card -->
      <main class="report-card-wrapper">
        
        <!-- Filter/Search Bar within results -->
        <div class="table-filter-bar">
          <div class="results-info-badge" id="results-badge">
            Loading results...
          </div>
          <button class="btn-icon-text btn-primary" id="btn-export" disabled aria-label="Export to CSV">
            <i class="fa-solid fa-file-csv"></i> Export
          </button>
        </div>

        <!-- Table View -->
        <div class="table-responsive-wrapper">
          <table class="custom-search-table" id="records-table">
            <thead>
              <tr>
                <th scope="col">Sl. No.</th>
                <th scope="col">Date</th>
                <th scope="col">Agent</th>
                <th scope="col">Department</th>
                <th scope="col">Category</th>
                <th scope="col">Start Time</th>
                <th scope="col">End Time</th>
                <th scope="col">Total Time</th>
                <th scope="col">Actions</th>
              </tr>
            </thead>
            <tbody id="records-tbody">
              <!-- Loading Skeleton Rows -->
              <tr class="skeleton-row">
                <td><div class="skeleton-line w-30"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-70"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td class="actions-cell"><div class="skeleton-line w-60 center"></div></td>
              </tr>
              <tr class="skeleton-row">
                <td><div class="skeleton-line w-30"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-70"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
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
        let currentPage = 1;
        const pageSize = 10;

        loadSearchResults();

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
          if (!allRecords || allRecords.length === 0) return;
          let csvContent = "data:text/csv;charset=utf-8,";
          csvContent += "Sl No,Date,Agent,Department,Miscellaneous Category,Description,Start Time,End Time,Total Time\n";

          allRecords.forEach(function(r, idx) {
            let row = [
              idx + 1,
              `"${(r.date || '').replace(/"/g, '""')}"`,
              `"${(r.agent || '').replace(/"/g, '""')}"`,
              `"${(r.department || '').replace(/"/g, '""')}"`,
              `"${(r.miscellaneous_category || '').replace(/"/g, '""')}"`,
              `"${(r.description || '').replace(/"/g, '""')}"`,
              `"${(r.start_time || '').replace(/"/g, '""')}"`,
              `"${(r.end_time || '').replace(/"/g, '""')}"`,
              `"${(r.total_time || '').replace(/"/g, '""')}"`
            ];
            csvContent += row.join(",") + "\n";
          });

          const encodedUri = encodeURI(csvContent);
          const link = document.createElement("a");
          link.setAttribute("href", encodedUri);
          link.setAttribute("download", "miscellaneous_search_results.csv");
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
        });

        function loadSearchResults(callback) {
          showSkeletons();
          const queryParams = window.location.search;

          $.ajax({
            url: 'api/fetch-miscellaneous-results.php' + queryParams,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
              if (response.success) {
                allRecords = response.data || [];
                currentPage = 1;
                displayedRecords = allRecords;
                
                // Update Badge text
                $('#results-badge').html('<i class="fa-solid fa-list-check" style="color: #ecae3b;"></i> <span style="color: #18ad9f; margin:0 4px;">'+allRecords.length+'</span><span style="color: #18678e;"> matching miscellaneous records</span>');

                // Update export button state
                $('#btn-export').prop('disabled', allRecords.length === 0);

                // Populate rows
                renderRecords(displayedRecords);
              } else {
                showError(response.message || 'Failed to fetch search results.');
              }
              if (typeof callback === 'function') callback();
            },
            error: function() {
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
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-60"></div></td>
                <td><div class="skeleton-line w-70"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
                <td><div class="skeleton-line w-50"></div></td>
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
              <td colspan="9" class="table-error-cell">
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
                <td colspan="9">
                  <div class="table-empty-state">
                    <i class="fa-solid fa-folder-open"></i>
                    <h3>No Miscellaneous Results</h3>
                    <p>Try modifying your search criteria filters in the Search page.</p>
                  </div>
                </td>
              </tr>`);
            $('#pagination-wrapper').hide();
            return;
          }

          const startIndex = (currentPage - 1) * pageSize;
          const endIndex = Math.min(startIndex + pageSize, records.length);
          const paginatedRecords = records.slice(startIndex, endIndex);

          paginatedRecords.forEach(function(r, index) {
            const slNo = startIndex + index + 1;

            const row = `
              <tr>
                <td>${slNo}</td>
                <td>${escapeHtml(r.date || '—')}</td>
                <td>${escapeHtml(r.agent ? r.agent.toUpperCase() : '—')}</td>
                <td>${escapeHtml(r.department || '—')}</td>
                <td><span class="record-id-badge">${escapeHtml(r.miscellaneous_category || '—')}</span></td>
                <td>${escapeHtml(r.start_time || 'N/A')}</td>
                <td>${escapeHtml(r.end_time || 'N/A')}</td>
                <td><strong>${escapeHtml(r.total_time || '00:00:00')}</strong></td>
                <td class="actions-cell">
                  <button class="btn-action-view" data-id="${escapeHtml(r.id)}" title="View Details">
                    <i class="fa-solid fa-eye"></i>
                  </button>
                </td>
              </tr>`;
            $tbody.append(row);
          });

          $('.btn-action-view').off('click').on('click', function() {
            const id = $(this).attr('data-id');
            window.location.href = 'view-miscellaneous.php?id=' + encodeURIComponent(id);
          });

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
