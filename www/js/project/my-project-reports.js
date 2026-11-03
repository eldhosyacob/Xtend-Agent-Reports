$(document).ready(function () {
  let allReports = [];
  let displayedReports = [];
  let currentPage = 1;
  const pageSize = 10;
  let customFromDate = '';
  let customToDate = '';
  let serverToday = new Date().toISOString().split('T')[0];

  function initCustomDates() {
    const todayStr = new Date().toISOString().split('T')[0];
    if (!$('#from-date-filter').val()) {
      $('#from-date-filter').val(todayStr);
    }
    if (!$('#to-date-filter').val()) {
      $('#to-date-filter').val(todayStr);
    }
  }

  function syncDateRangeState() {
    const range = $('#date-range-filter').val() || 'today';
    if (range === 'custom') {
      initCustomDates();
      $('#custom-date-inputs').addClass('active');
      customFromDate = $('#from-date-filter').val();
      customToDate = $('#to-date-filter').val();
    } else {
      $('#custom-date-inputs').removeClass('active');
    }
  }

  // Initialize date filter state
  syncDateRangeState();
  loadReports();

  // Listen for browser back/forward cache navigation
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) {
      syncDateRangeState();
      loadReports();
    }
  });

  // Listen for report updates from other tabs
  window.addEventListener('storage', function (e) {
    if (e.key === 'project_report_updated' || e.key === 'record_updated') {
      loadReports();
    }
  });

  // Refresh action
  $('#btn-refresh').on('click', function () {
    const $icon = $(this).find('i');
    $icon.addClass('fa-spin');
    loadReports(function () {
      $icon.removeClass('fa-spin');
    });
  });

  // Change date range filter
  $('#date-range-filter').on('change', function () {
    sessionStorage.setItem('my_project_reports_page', '1');
    syncDateRangeState();
    loadReports();
  });

  // Custom date search button
  $('#btn-custom-date-search').on('click', function () {
    customFromDate = $('#from-date-filter').val();
    customToDate = $('#to-date-filter').val();
    if (!customFromDate || !customToDate) {
      alert('Please select both From and To dates.');
      return;
    }
    if (customFromDate > customToDate) {
      alert('From date cannot be after To date.');
      return;
    }
    sessionStorage.setItem('my_project_reports_page', '1');
    loadReports();
  });

  $('#from-date-filter, #to-date-filter').on('change', function () {
    const range = $('#date-range-filter').val() || 'today';
    if (range === 'custom') {
      customFromDate = $('#from-date-filter').val();
      customToDate = $('#to-date-filter').val();
      if (customFromDate && customToDate && customFromDate <= customToDate) {
        sessionStorage.setItem('my_project_reports_page', '1');
        loadReports();
      }
    }
  });

  function applyFilters(resetPage = false) {
    const searchVal = ($('#search-records').val() || '').toLowerCase().trim();
    const statusVal = ($('#status-filter').val() || '').toLowerCase().trim();

    displayedReports = allReports.filter(function (r) {
      // Status filter
      if (statusVal !== '') {
        const recStatus = (r.status || 'Pending').toLowerCase().trim();
        if (recStatus !== statusVal) {
          return false;
        }
      }

      // Keyword search
      if (searchVal !== '') {
        const idStr = String(r.id || '').toLowerCase();
        const projId = (r.project_id || '').toLowerCase();
        const subtaskId = (r.subtask_id || '').toLowerCase();
        const subtaskTitle = (r.subtask_title || '').toLowerCase();
        const prodName = (r.product_name || r.project_name || '').toLowerCase();
        const clientName = (r.client_name || '').toLowerCase();
        const status = (r.status || '').toLowerCase();
        const youtrack = (r.youtrack_id || '').toLowerCase();

        const match =
          idStr.indexOf(searchVal) > -1 ||
          projId.indexOf(searchVal) > -1 ||
          subtaskId.indexOf(searchVal) > -1 ||
          subtaskTitle.indexOf(searchVal) > -1 ||
          prodName.indexOf(searchVal) > -1 ||
          clientName.indexOf(searchVal) > -1 ||
          status.indexOf(searchVal) > -1 ||
          youtrack.indexOf(searchVal) > -1;

        if (!match) return false;
      }

      return true;
    });

    $('#records-count-val').text(displayedReports.length);

    const totalPages = Math.max(1, Math.ceil(displayedReports.length / pageSize));
    if (resetPage) {
      currentPage = 1;
      sessionStorage.setItem('my_project_reports_page', '1');
    } else {
      const savedPage = parseInt(sessionStorage.getItem('my_project_reports_page'), 10) || 1;
      currentPage = Math.min(Math.max(1, savedPage), totalPages);
    }

    renderReports(displayedReports);
  }

  // Filter input events
  $('#search-records').on('input', function () {
    applyFilters(true);
  });

  $('#status-filter').on('change', function () {
    applyFilters(true);
  });

  function goToPage(page) {
    currentPage = page;
    sessionStorage.setItem('my_project_reports_page', String(currentPage));
    renderReports(displayedReports);
    scrollToTableTop();
  }

  $('#btn-prev').on('click', function () {
    if (currentPage > 1) {
      goToPage(currentPage - 1);
    }
  });

  $('#btn-next').on('click', function () {
    const totalPages = Math.ceil(displayedReports.length / pageSize);
    if (currentPage < totalPages) {
      goToPage(currentPage + 1);
    }
  });

  function scrollToTableTop() {
    $('html, body').animate({
      scrollTop: $('.report-card-wrapper').offset().top - 20
    }, 300);
  }

  function loadReports(callback) {
    showSkeletons();

    const range = $('#date-range-filter').val() || 'today';
    const reqData = { range: range };
    if (range === 'custom') {
      initCustomDates();
      customFromDate = $('#from-date-filter').val();
      customToDate = $('#to-date-filter').val();
      reqData.from_date = customFromDate;
      reqData.to_date = customToDate;
    }

    $.ajax({
      url: '../api/fetch-myproject-reports.php',
      method: 'GET',
      data: reqData,
      dataType: 'json',
      success: function (response) {
        if (response.success) {
          if (response.server_date) {
            serverToday = response.server_date;
          }
          allReports = response.data || [];
          updateStats(allReports, response.total_time);
          applyFilters();
        } else {
          showError(response.message || 'Failed to fetch project reports.');
        }
        if (typeof callback === 'function') callback();
      },
      error: function () {
        showError('Network error. Unable to load project reports.');
        if (typeof callback === 'function') callback();
      }
    });
  }

  function showSkeletons() {
    $('#records-count-val').text('—');
    let skeletonHtml = '';
    for (let i = 0; i < 3; i++) {
      skeletonHtml += `
        <tr class="skeleton-row">
          <td><div class="skeleton-line w-30"></div></td>
          <td><div class="skeleton-line w-50"></div></td>
          <td><div class="skeleton-line w-60"></div></td>
          <td><div class="skeleton-line w-70"></div></td>
          <td><div class="skeleton-line w-65"></div></td>
          <td><div class="skeleton-line w-50"></div></td>
          <td><div class="skeleton-badge"></div></td>
          <td class="actions-cell"><div class="skeleton-line w-60 center"></div></td>
        </tr>`;
    }
    $('#records-tbody').html(skeletonHtml);
    $('#pagination-wrapper').hide();
  }

  function showError(msg) {
    $('#records-count-val').text('0');
    $('#records-tbody').html(`
      <tr>
        <td colspan="8" class="table-error-cell">
          <i class="fa-solid fa-triangle-exclamation"></i>
          ${escapeHtml(msg)}
        </td>
      </tr>`);
    $('#stat-total, #stat-pending, #stat-in-progress, #stat-on-hold, #stat-completed, #stat-total-time').text('—');
    $('#pagination-wrapper').hide();
  }

  function updateStats(reports, totalTimeStr) {
    const range = $('#date-range-filter').val() || 'today';
    let labelText = 'Total Today';
    if (range === 'week') {
      labelText = 'Total This Week';
    } else if (range === 'month') {
      labelText = 'Total This Month';
    } else if (range === 'year') {
      labelText = 'Total This Year';
    } else if (range === 'custom') {
      labelText = 'Total (Custom)';
    }
    $('#stat-total-label').text(labelText);

    const total = reports.length;
    let pending = 0;
    let inProgress = 0;
    let onHold = 0;
    let completed = 0;

    reports.forEach(function (r) {
      const st = (r.status || '').toLowerCase().trim();
      if (st === 'pending') {
        pending++;
      } else if (st === 'in progress') {
        inProgress++;
      } else if (st === 'on hold') {
        onHold++;
      } else if (st === 'completed') {
        completed++;
      }
    });

    animateCounter($('#stat-total'), total);
    animateCounter($('#stat-pending'), pending);
    animateCounter($('#stat-in-progress'), inProgress);
    animateCounter($('#stat-on-hold'), onHold);
    animateCounter($('#stat-completed'), completed);

    let formattedTime = totalTimeStr;
    if (!formattedTime) {
      formattedTime = calculateTotalTime(reports);
    }
    $('#stat-total-time').text(formattedTime || '00:00:00');
  }

  function calculateTotalTime(reports) {
    let totalSec = 0;
    reports.forEach(function (r) {
      const timeStr = r.total_time || '';
      if (timeStr) {
        const parts = timeStr.split(':').map(p => parseInt(p, 10) || 0);
        if (parts.length === 3) {
          totalSec += (parts[0] * 3600) + (parts[1] * 60) + parts[2];
        } else if (parts.length === 2) {
          totalSec += (parts[0] * 60) + parts[1];
        }
      }
    });
    const hrs = Math.floor(totalSec / 3600);
    const mins = Math.floor((totalSec % 3600) / 60);
    const secs = totalSec % 60;
    return String(hrs).padStart(2, '0') + ':' + String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
  }

  function animateCounter($element, targetValue) {
    $element.prop('Counter', 0).animate({
      Counter: targetValue
    }, {
      duration: 800,
      easing: 'swing',
      step: function (now) {
        $element.text(Math.ceil(now));
      },
      complete: function () {
        $element.text(targetValue);
      }
    });
  }

  function renderReports(reports) {
    const $tbody = $('#records-tbody');
    $tbody.empty();

    if (reports.length === 0) {
      $tbody.html(`
        <tr>
          <td colspan="8">
            <div class="table-empty-state">
              <i class="fa-solid fa-folder-open"></i>
              <h3>No Project Reports Found</h3>
            </div>
          </td>
        </tr>`);
      $('#pagination-wrapper').hide();
      return;
    }

    const startIndex = (currentPage - 1) * pageSize;
    const endIndex = Math.min(startIndex + pageSize, reports.length);
    const paginatedReports = reports.slice(startIndex, endIndex);

    paginatedReports.forEach(function (r, index) {
      const slNo = startIndex + index + 1;
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

      const prodName = r.product_name || r.project_name || '—';
      const subtaskTitle = r.subtask_title || r.subtask_id || '—';
      const subtaskId = r.subtask_id || '';
      const totalTime = r.total_time || '00:00:00';

      const subtaskStatus = (r.subtask_status !== undefined && r.subtask_status !== null && r.subtask_status !== '') ? r.subtask_status : (r.status || '');
      const isSubtaskCompleted = subtaskStatus.toLowerCase().trim() === 'completed' || statusLower === 'completed';

      const isToday = String(r.date || '').trim() === String(serverToday).trim();
      let editBtn = '';
      if (isToday && !isSubtaskCompleted) {
        editBtn = `
          <a href="new-project-report.php?id=${encodeURIComponent(r.id)}" target="_blank" rel="opener" class="btn-action-edit" data-report-id="${r.id}" title="Edit Report">
            <i class="fa-solid fa-pen-to-square"></i>
          </a>`;
      }

      const row = `
        <tr>
          <td>${slNo}</td>
          <td>${escapeHtml(r.date || '—')}</td>
          <td>
            <a href="my-project-details.php?id=${encodeURIComponent(r.project_id || '')}" class="project-id-link" target="_blank" title="View Project Details">
               ${escapeHtml(r.project_id || '—')}
            </a>
          </td>
          <td>
            <div class="subtask-cell-content">
              <span class="subtask-title-text" title="${escapeHtml(subtaskTitle)}">${escapeHtml(subtaskTitle)}</span>
              ${subtaskId ? `<span class="subtask-id-subtext">${escapeHtml(subtaskId)}</span>` : ''}
            </div>
          </td>
          <td class="company-name-cell" title="${escapeHtml(prodName)}">${escapeHtml(prodName)}</td>
          <td>
            <span class="time-badge">
              <i class="fa-regular fa-clock"></i> ${escapeHtml(totalTime)}
            </span>
          </td>
          <td>
            <span class="status-pill ${statusClass}">
              <i class="fa-solid fa-circle"></i> ${escapeHtml(rawStatus)}
            </span>
          </td>
          <td class="actions-cell">
            <a href="view-project-report.php?id=${encodeURIComponent(r.id)}" class="btn-action-view" data-report-id="${r.id}" title="View Details">
              <i class="fa-solid fa-eye"></i>
            </a>
            ${editBtn}
          </td>
        </tr>`;

      $tbody.append(row);
    });

    updatePaginationControls(reports.length);
  }

  function updatePaginationControls(totalCount) {
    const totalPages = Math.ceil(totalCount / pageSize);

    if (totalPages <= 1) {
      $('#pagination-wrapper').hide();
      return;
    } else {
      $('#pagination-wrapper').css('display', 'flex');
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
    pages.forEach(function (page) {
      if (lastPage !== 0) {
        if (page - lastPage === 2) {
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

  function escapeHtml(text) {
    if (!text) return '';
    return text
      .toString()
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  $(document).on('click', '.btn-action-edit', function (e) {
    const url = $(this).attr('href');
    if (url && url.indexOf('new-project-report.php') !== -1) {
      e.preventDefault();
      window.open(url, '_blank');
    }
  });
});
