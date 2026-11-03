$(document).ready(function () {
  const urlParams = new URLSearchParams(window.location.search);
  const subtaskIdParam = urlParams.get('subtask_id') || urlParams.get('id') || '';
  const projectIdParam = urlParams.get('project_id') || '';

  if (!subtaskIdParam) {
    alert('No Subtask ID specified. Returning to previous page.');
    window.location.href = projectIdParam ? `my-project-details.php?id=${encodeURIComponent(projectIdParam)}` : 'my-projects.php';
    return;
  }

  const $loadingOverlay = $('#loadingOverlay');
  const $reportsTableBody = $('#reportsTableBody');
  let currentSubtask = null;
  let serverDateToday = new Date().toISOString().split('T')[0];

  function showLoading() {
    $loadingOverlay.css('display', 'flex');
  }

  function hideLoading() {
    $loadingOverlay.hide();
  }

  function escapeHtml(str) {
    if (!str) return '';
    return $('<div>').text(str).html();
  }

  function getPriorityBadgeClass(priority) {
    const p = (priority || '').toLowerCase();
    if (p === 'high') return 'badge-priority-high';
    if (p === 'medium') return 'badge-priority-medium';
    return 'badge-priority-low';
  }

  function getPriorityIcon(priority) {
    const p = (priority || '').toLowerCase();
    if (p === 'high') return 'fa-solid fa-angles-up';
    if (p === 'medium') return 'fa-solid fa-bars';
    return 'fa-solid fa-angles-down';
  }

  function getStatusBadgeClass(status) {
    const s = (status || '').toLowerCase();
    if (s === 'in progress') return 'badge-status-progress';
    if (s === 'completed') return 'badge-status-completed';
    if (s === 'on hold' || s === 'hold') return 'badge-status-hold';
    return 'badge-status-pending';
  }

  function getStatusIcon(status) {
    const s = (status || '').toLowerCase();
    if (s === 'in progress') return 'fa-solid fa-spinner';
    if (s === 'completed') return 'fa-solid fa-circle-check';
    if (s === 'pending') return 'fa-solid fa-clock';
    if (s === 'on hold' || s === 'hold') return 'fa-solid fa-circle-pause';
    return 'fa-solid fa-bars-progress';
  }

  function isSubtaskCompleted(report) {
    if (report && (report.subtask_status || '').toLowerCase().trim() === 'completed') {
      return true;
    }
    if (!currentSubtask) return false;
    const s = (currentSubtask.status || '').toLowerCase().trim();
    const ls = (currentSubtask.latest_status || '').toLowerCase().trim();
    const ts = (currentSubtask.subtask_table_status || '').toLowerCase().trim();
    return s === 'completed' || ls === 'completed' || ts === 'completed';
  }

  function loadSubtaskReports(silent = false) {
    if (!silent) {
      showLoading();
    }

    $.ajax({
      url: '../api/fetch-subtask-reports.php',
      type: 'GET',
      data: {
        subtask_id: subtaskIdParam,
        project_id: projectIdParam
      },
      dataType: 'json',
      success: function (response) {
        if (!silent) {
          hideLoading();
        }

        if (response.success) {
          if (response.server_date) {
            serverDateToday = response.server_date;
          }
          currentSubtask = response.subtask;
          renderSubtaskOverview(response.subtask, response.project_id);
          renderStats(response.stats);
          renderReportsTable(response.reports || []);
        } else {
          if (!silent) {
            alert(response.message || 'Failed to load related reports.');
          }
        }
      },
      error: function () {
        if (!silent) {
          hideLoading();
          alert('An error occurred while loading related reports.');
        }
      }
    });
  }

  function renderSubtaskOverview(subtask, fallbackProjectId) {
    const sid = subtask ? (subtask.subtask_id || subtask.id) : subtaskIdParam;
    const title = subtask ? (subtask.title || 'Subtask') : `Subtask ${sid}`;
    const pid = subtask ? (subtask.project_id || fallbackProjectId) : fallbackProjectId;
    const pName = subtask && subtask.project_name ? subtask.project_name : (pid || 'Project');

    // Breadcrumbs
    $('#breadcrumbSubtaskName').text(sid);
    if (pid) {
      $('#backToProjectLink').attr('href', `my-project-details.php?id=${encodeURIComponent(pid)}`);
      $('#breadcrumbProjectLink').attr('href', `my-project-details.php?id=${encodeURIComponent(pid)}`).text(pName);
    } else {
      $('#backToProjectLink').attr('href', 'my-projects.php');
      $('#breadcrumbProjectLink').attr('href', 'my-projects.php').text('My Projects');
    }

    // Title & Pill
    $('#subtaskTitle').text(title);
    $('#subtaskIdPill').text(sid);

    if (subtask) {
      // Priority badge
      const prio = subtask.priority || 'Medium';
      const prioClass = getPriorityBadgeClass(prio);
      const prioIcon = getPriorityIcon(prio);
      $('#subtaskPriorityBadge')
        .attr('class', `badge-priority ${prioClass}`)
        .html(`<i class="${prioIcon}"></i> ${escapeHtml(prio)}`);

      // Status badge
      const statusVal = subtask.latest_status || subtask.status || 'Pending';
      const statusClass = getStatusBadgeClass(statusVal);
      const statusIcon = getStatusIcon(statusVal);
      $('#subtaskStatusBadge')
        .attr('class', `badge-status ${statusClass}`)
        .html(`<i class="${statusIcon}"></i> ${escapeHtml(statusVal)}`);

      // Assignee chip in overview header
      if (subtask.assigned_to && subtask.assigned_to.trim() !== '') {
        $('#subtaskAssigneeDisplay').show();
        $('#assigneeName').text(subtask.assigned_to);
      } else {
        $('#subtaskAssigneeDisplay').hide();
      }

      // Metadata items
      $('#metaProject').html(`
        <a href="my-project-details.php?id=${encodeURIComponent(pid)}" class="project-id-link" title="View Project Details">
          <i class="fa-solid fa-diagram-project"></i> ${escapeHtml(pName)} (${escapeHtml(pid)})
        </a>
      `);
      $('#metaAssignedTo').text(subtask.assigned_to || '—');
      $('#metaDueDate').text(subtask.due_date || '—');
      $('#metaCreatedBy').text(subtask.created_by ? `${subtask.created_by} (${(subtask.created_at || '').substring(0, 10)})` : '—');

      if (subtask.description && subtask.description.trim() !== '') {
        $('#subtaskDescSection').show();
        $('#subtaskDescBox').removeClass('desc-empty').text(subtask.description);
      } else {
        $('#subtaskDescSection').show();
        $('#subtaskDescBox').addClass('desc-empty').text('No description provided for this subtask.');
      }
    } else {
      $('#metaProject').text(pid || '—');
      $('#subtaskAssigneeDisplay').hide();
      $('#subtaskDescSection').hide();
    }
  }

  function renderStats(stats) {
    if (!stats) return;
    $('#statTotal').text(`Total: ${stats.total || 0}`);
    $('#statPending').text(`Pending: ${stats.pending || 0}`);
    $('#statInProgress').text(`In Progress: ${stats.in_progress || 0}`);
    $('#statOnHold').text(`On Hold: ${stats.on_hold || 0}`);
    $('#statCompleted').text(`Completed: ${stats.completed || 0}`);
    $('#statTotalTime').html(`<i class="fa-regular fa-clock"></i> Time: ${escapeHtml(stats.total_time || '00:00:00')}`);
    $('#metaTotalTime').text(stats.total_time || '00:00:00');
  }

  function renderReportsTable(reports) {
    const count = reports.length;
    $('#reportsCounterBadge').text(`${count} Report${count === 1 ? '' : 's'}`);
    const subtaskCompleted = isSubtaskCompleted();

    if (count === 0) {
      $reportsTableBody.html(`
        <tr>
          <td colspan="7">
            <div class="empty-reports-state">
              <div class="empty-reports-icon">
                <i class="fa-solid fa-folder-open"></i>
              </div>
              <h3 class="empty-reports-title">No reports created yet for this subtask</h3>
              <p class="empty-reports-desc">Reports submitted for this subtask will appear here chronologically.</p>
              ${!subtaskCompleted ? `
              <button type="button" class="btn-primary-action" id="btnCreateFirstReport">
                <i class="fa-solid fa-file-circle-plus"></i> Create Report
              </button>
              ` : ''}
            </div>
          </td>
        </tr>
      `);
      return;
    }

    // Check if there is already a report created today to adjust top action button
    const todayReport = reports.find(function (r) {
      return String(r.date || '').trim() === String(serverDateToday).trim();
    });

    const $topActionBtn = $('#btnTopReportAction');
    if (subtaskCompleted) {
      $topActionBtn.hide();
    } else if (todayReport) {
      $topActionBtn
        .show()
        .attr('href', `new-project-report.php?id=${encodeURIComponent(todayReport.id)}`)
        .attr('target', '_blank')
        .attr('rel', 'opener')
        .html('<i class="fa-solid fa-pen-to-square"></i> Edit Today\'s Report');
    } else {
      $topActionBtn
        .show()
        .attr('href', '#')
        .removeAttr('target')
        .html('<i class="fa-solid fa-file-circle-plus"></i> Create Report');
    }

    let html = '';
    reports.forEach(function (r, index) {
      const slNo = index + 1;
      const rawStatus = r.status || 'Pending';
      const statusClass = getStatusBadgeClass(rawStatus);
      const statusIcon = getStatusIcon(rawStatus);
      const isToday = String(r.date || '').trim() === String(serverDateToday).trim();

      let editBtn = '';
      if (isToday && !isSubtaskCompleted(r)) {
        editBtn = `
          <a href="new-project-report.php?id=${encodeURIComponent(r.id)}" target="_blank" rel="opener" class="btn-subtask-action btn-action-edit" data-report-id="${r.id}" title="Edit Report" aria-label="Edit Report">
            <i class="fa-solid fa-pen-to-square"></i>
          </a>
        `;
      }

      const clientOrProd = r.client_name || r.product_name || '—';
      const totalTime = r.total_time || '00:00:00';

      html += `
        <tr class="clickable-report-row" data-report-id="${r.id}" title="Click to view Report">
          <td>${slNo}</td>
          <td>
            <span><i class="fa-regular fa-calendar" style="color: var(--text-muted); margin-right: 4px;"></i>${escapeHtml(r.date || '—')}</span>
          </td>
          <td>
            <span style="font-weight: 500;">${escapeHtml(r.agent_name || '—')}</span>
          </td>
          <td>
            <span class="report-time-badge">
              <i class="fa-regular fa-clock" style="color: var(--text-muted);"></i> ${escapeHtml(totalTime)}
            </span>
          </td>
          <td>
            <span class="badge-status ${statusClass}">
              <i class="${statusIcon}"></i> ${escapeHtml(rawStatus)}
            </span>
          </td>
          <td class="actions-cell">
            <div class="subtask-actions-group">
              <a href="view-project-report.php?id=${encodeURIComponent(r.id)}" class="btn-subtask-action btn-action-view" data-report-id="${r.id}" title="View Details" aria-label="View Details">
                <i class="fa-solid fa-eye"></i>
              </a>
              ${editBtn}
            </div>
          </td>
        </tr>
      `;
    });

    $reportsTableBody.html(html);
  }

  // Clicking anywhere on a report row navigates to view-project-report.php
  $reportsTableBody.on('click', '.clickable-report-row', function (e) {
    if ($(e.target).closest('a, button').length > 0) {
      return;
    }
    const reportId = $(this).attr('data-report-id');
    if (reportId) {
      window.location.href = 'view-project-report.php?id=' + encodeURIComponent(reportId);
    }
  });

  // Top Action: Create Report
  function handleCreateReportAction() {
    if (isSubtaskCompleted()) {
      alert('Cannot create reports for a completed subtask.');
      return;
    }

    const pid = currentSubtask ? currentSubtask.project_id : projectIdParam;
    const sid = currentSubtask ? (currentSubtask.subtask_id || currentSubtask.id) : subtaskIdParam;

    if (!pid || !sid) {
      alert('Unable to identify project or subtask for report creation.');
      return;
    }

    showLoading();
    $.ajax({
      url: '../api/create-project-report.php',
      type: 'POST',
      data: {
        project_id: pid,
        subtask_id: sid
      },
      dataType: 'json',
      success: function (res) {
        hideLoading();
        if (res.success && res.report_id) {
          const reportUrl = 'new-project-report.php?id=' + encodeURIComponent(res.report_id);
          const newTab = window.open(reportUrl, '_blank');
          if (!newTab) {
            window.location.href = reportUrl;
          }
          setTimeout(function () {
            loadSubtaskReports(true);
          }, 1000);
        } else {
          alert('Error: ' + (res.message || 'Failed to create report record.'));
        }
      },
      error: function (xhr) {
        hideLoading();
        let msg = 'An error occurred while creating project report.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        }
        alert(msg);
      }
    });
  }

  $('#btnTopReportAction').on('click', function (e) {
    if (isSubtaskCompleted()) {
      e.preventDefault();
      alert('Cannot create or edit reports for a completed subtask.');
      return;
    }
    const href = $(this).attr('href');
    if (href && href !== '#') {
      e.preventDefault();
      window.open(href, '_blank');
      return;
    }
    e.preventDefault();
    handleCreateReportAction();
  });

  $reportsTableBody.on('click', '.btn-action-edit', function (e) {
    if (isSubtaskCompleted()) {
      e.preventDefault();
      alert('Cannot edit reports for a completed subtask.');
      return;
    }
    const url = $(this).attr('href');
    if (url) {
      e.preventDefault();
      window.open(url, '_blank');
    }
  });

  $reportsTableBody.on('click', '#btnCreateFirstReport', function (e) {
    e.preventDefault();
    handleCreateReportAction();
  });

  // Window focus listener to refresh reports when returning from view or edit
  window.addEventListener('focus', function () {
    loadSubtaskReports(true);
  });

  // Initial load
  loadSubtaskReports();
});
