$(document).ready(function () {
  let allRecords = [];
  let displayedRecords = [];
  let currentPage = 1;
  const pageSize = 10;

  const pageStorageKey = 'project_search_results_page_' + (window.location.search || '');

  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function scrollToTableTop() {
    if ($('.report-card-wrapper').length) {
      $('html, body').animate({
        scrollTop: $('.report-card-wrapper').offset().top - 20
      }, 300);
    }
  }

  function goToPage(page) {
    currentPage = page;
    sessionStorage.setItem(pageStorageKey, String(currentPage));
    renderRecords(displayedRecords);
    scrollToTableTop();
  }

  // Load records on ready using URL query parameters
  loadSearchResults();

  // Listen for browser back/forward cache navigation
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) {
      loadSearchResults();
    }
  });

  // Export CSV click trigger
  $('#btn-export').on('click', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const searchVal = $('#search-records').length ? $('#search-records').val().trim() : '';
    if (searchVal) {
      urlParams.set('q', searchVal);
    } else {
      urlParams.delete('q');
    }
    const queryString = urlParams.toString();
    window.location.href = '../api/export-project-reports-search.php' + (queryString ? ('?' + queryString) : '');
  });

  function applyFilters(resetPage = false) {
    const val = ($('#search-records').length) ? $('#search-records').val().toLowerCase().trim() : '';
    if (val === '') {
      displayedRecords = allRecords;
    } else {
      displayedRecords = allRecords.filter(function (r) {
        const id = String(r.id || '').toLowerCase();
        const agent = String(r.agent_name || '').toLowerCase();
        const client = String(r.client_name || '').toLowerCase();
        const prod = String(r.product_name || '').toLowerCase();
        const pid = String(r.product_id || '').toLowerCase();
        const softVer = String(r.software_version || '').toLowerCase();
        const lcd = String(r.lcd_id || '').toLowerCase();
        const hwId = String(r.hardware_id || '').toLowerCase();
        const hasp = String(r.hasp_id || '').toLowerCase();
        const proj = String(r.project_id || '').toLowerCase();
        const projName = String(r.project_name || '').toLowerCase();
        const status = String(r.status || '').toLowerCase();
        const date = String(r.date || '').toLowerCase();

        return id.includes(val) ||
          agent.includes(val) ||
          client.includes(val) ||
          prod.includes(val) ||
          pid.includes(val) ||
          softVer.includes(val) ||
          lcd.includes(val) ||
          hwId.includes(val) ||
          hasp.includes(val) ||
          proj.includes(val) ||
          projName.includes(val) ||
          status.includes(val) ||
          date.includes(val);
      });
    }

    // Update Badge text
    $('#results-badge').html(
      '<i class="fa-solid fa-list-check results-badge-icon"></i> ' +
      '<span class="results-badge-count">' + displayedRecords.length + '</span> ' +
      '<span class="results-badge-text">matching reports</span>'
    );

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

  // Live filter input inside results
  $('#search-records').on('input', function () {
    applyFilters(true);
  });

  function loadSearchResults(callback) {
    showSkeletons();
    const queryParams = window.location.search;

    $.ajax({
      url: '../api/fetch-project-reports-search.php' + queryParams,
      method: 'GET',
      dataType: 'json',
      success: function (response) {
        if (response.success) {
          allRecords = response.data || [];
          applyFilters(false);
        } else {
          showError(response.message || 'Failed to fetch search results.');
        }
        if (typeof callback === 'function') callback();
      },
      error: function () {
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
          <td><div class="skeleton-line w-70"></div></td>
          <td><div class="skeleton-line w-60"></div></td>
          <td><div class="skeleton-line w-50"></div></td>
          <td><div class="skeleton-line w-50"></div></td>
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
        <td colspan="9" class="table-error-cell">
          <i class="fa-solid fa-triangle-exclamation"></i>
          ${escapeHtml(msg)}
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
              <h3>No Search Results</h3>
              <p>Try modifying your search criteria filters in the Search page.</p>
            </div>
          </td>
        </tr>`);
      $('#pagination-wrapper').hide();
      return;
    }

    // Pagination slice
    const startIndex = (currentPage - 1) * pageSize;
    const endIndex = Math.min(startIndex + pageSize, records.length);
    const paginatedRecords = records.slice(startIndex, endIndex);

    paginatedRecords.forEach(function (r, index) {
      const slNo = startIndex + index + 1;

      // Map status CSS classes
      const rawStatus = r.status || 'Pending';
      const statusLower = rawStatus.toLowerCase().trim();
      let statusClass = 'status-pending';

      if (statusLower === 'completed') {
        statusClass = 'status-completed';
      } else if (statusLower === 'in progress') {
        statusClass = 'status-in-progress';
      } else if (statusLower === 'on hold') {
        statusClass = 'status-on-hold';
      }

      const row = `
        <tr>
          <td>${slNo}</td>
          <td>${escapeHtml(r.date || '—')}</td>
          <td>${escapeHtml(r.agent_name || '—')}</td>
          <td>${escapeHtml(r.project_name || '—')}</td>
          <td class="company-name-cell">${escapeHtml(r.client_name || '—')}</td>
          <td>${escapeHtml(r.product_name || '—')}</td>
          <td>${escapeHtml(r.product_id || '—')}</td>
          <td>
            <span class="status-pill ${statusClass}">
              <i class="fa-solid fa-circle"></i> ${escapeHtml(rawStatus)}
            </span>
          </td>
          <td class="actions-cell">
            <a href="view-project-report.php?id=${encodeURIComponent(r.id)}" class="btn-action-view" data-report-id="${escapeHtml(r.id)}" title="View Project Report">
              <i class="fa-solid fa-eye"></i>
            </a>
          </td>
        </tr>`;
      $tbody.append(row);
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
    pages.forEach(function (page) {
      if (lastPage !== 0) {
        if (page - lastPage === 2) {
          // Fill the gap if it is only 1 page
          const fillPage = lastPage + 1;
          const btn = $('<button class="page-num-btn">' + fillPage + '</button>');
          btn.on('click', function () {
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
      btn.on('click', function () {
        if (currentPage !== page) {
          goToPage(page);
        }
      });
      $pagesContainer.append(btn);
      lastPage = page;
    });
  }

  // Setup Prev/Next click events
  $('#btn-prev').on('click', function () {
    if (currentPage > 1) {
      goToPage(currentPage - 1);
    }
  });

  $('#btn-next').on('click', function () {
    const totalPages = Math.ceil(displayedRecords.length / pageSize);
    if (currentPage < totalPages) {
      goToPage(currentPage + 1);
    }
  });
});
