$(document).ready(function () {
  const urlParams = new URLSearchParams(window.location.search);
  const projectId = urlParams.get('project_id') || urlParams.get('id') || $('.project-summary-container').data('project-id') || '';

  if (!projectId) {
    alert('No Project ID specified. Returning to Project Lists.');
    window.location.href = 'project-lists.php';
    return;
  }

  const $loadingOverlay = $('#loadingOverlay');
  const $subtasksTableBody = $('#subtasksSummaryTableBody');
  const $searchInput = $('#summarySearchInput');
  let allSubtasks = [];

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
    // if (s === 'in progress') return 'fa-solid fa-spinner';
    // if (s === 'completed') return 'fa-solid fa-circle-check';
    // if (s === 'pending') return 'fa-solid fa-clock';
    // if (s === 'on hold' || s === 'hold') return 'fa-solid fa-circle-pause';
    // return 'fa-solid fa-bars-progress';
  }

  function getCategoryIcon(cat) {
    const c = (cat || '').toLowerCase();
    // if (c === 'hardware') return 'fa-solid fa-microchip';
    // if (c === 'software') return 'fa-solid fa-code';
    // if (c === 'network') return 'fa-solid fa-network-wired';
    // if (c === 'testing') return 'fa-solid fa-vial-circle-check';
    // return 'fa-solid fa-folder';
  }

  function renderProjectOverview(project) {
    $('#projectTitle').text(project.project_name || 'Project Summary');
    $('#projectIdBadge').text(project.project_id || projectId);
    $('#breadcrumbProjectName').text(project.project_name || 'Project Details');

    const detailsUrl = `project-details.php?project_id=${encodeURIComponent(project.project_id || projectId)}`;
    $('#backToDetailsLink, #breadcrumbProjectLink, #btnNavProjectDetails').attr('href', detailsUrl);

    if (project.category) {
      const catIcon = getCategoryIcon(project.category);
      $('#projectCategoryBadge').html(`<i class="${catIcon}"></i> ${escapeHtml(project.category)}`).show();
    } else {
      $('#projectCategoryBadge').hide();
    }

    if (project.priority) {
      const prioClass = getPriorityBadgeClass(project.priority);
      const prioIcon = getPriorityIcon(project.priority);
      $('#projectPriorityBadge').attr('class', `badge-priority ${prioClass}`).html(`<i class="${prioIcon}"></i> ${escapeHtml(project.priority)}`).show();
    } else {
      $('#projectPriorityBadge').hide();
    }

    const statusVal = project.project_status || 'Assigned';
    const statusClass = getStatusBadgeClass(statusVal);
    const statusIcon = getStatusIcon(statusVal);
    $('#projectStatusBadge').attr('class', `badge-status ${statusClass}`).html(`<i class="${statusIcon}"></i> ${escapeHtml(statusVal)}`).show();

    if (project.asignee && project.asignee.trim() !== '') {
      const asigneePhoto = project.asignee_photo || '../images/default-avatar.png';
      $('#projectAssigneeDisplay').show().html(`
        <img src="${asigneePhoto}" class="user-avatar-img" alt="${escapeHtml(project.asignee)}" onerror="this.src='../images/default-avatar.png'">
        <span>${escapeHtml(project.asignee)}</span>
      `);
    } else {
      $('#projectAssigneeDisplay').hide();
    }

    $('#metaProjectDate').text(project.date || '—');
    $('#metaAssignedBy').text(project.assigned_by || '—');
    $('#metaCreatedBy').text(project.created_by || '—');
    $('#metaClientDealerName').text(project.client_dealer_name || '—');
    $('#metaTotalDuration').text(project.total_duration || '00:00:00');
  }

  function renderStats(stats) {
    $('#statTotal').text(`Total: ${stats.total_subtasks || 0}`);
    $('#statPending').text(`Pending: ${stats.pending || 0}`);
    $('#statInProgress').text(`In Progress: ${stats.in_progress || 0}`);
    $('#statOnHold').text(`On Hold: ${stats.on_hold || 0}`);
    $('#statCompleted').text(`Completed: ${stats.completed || 0}`);
    $('#statTotalTime').html(`<i class="fa-regular fa-clock"></i> Duration: ${stats.total_duration || '00:00:00'}`);
    $('#subtaskCounterBadge').text(`${stats.total_subtasks || 0} Subtasks`);
  }

  function renderSubtasksTable(subtasks) {
    if (!subtasks || subtasks.length === 0) {
      $subtasksTableBody.html(`
        <tr>
          <td colspan="7">
            <div class="summary-empty-state">
              <div class="summary-empty-icon">
                <i class="fa-solid fa-list-check"></i>
              </div>
              <h3 class="summary-empty-title">No subtasks found</h3>
            </div>
          </td>
        </tr>
      `);
      return;
    }

    let html = '';
    subtasks.forEach(function (st, index) {
      const slNo = st.sl_no || (index + 1);
      const agentName = st.assigned_to || '—';
      const subtaskTitle = st.title || 'Untitled Subtask';
      const subtaskId = st.subtask_id || `Task #${st.id}`;
      const startDate = st.start_date ? escapeHtml(st.start_date) : '';
      const endDate = st.end_date ? escapeHtml(st.end_date) : '';
      const duration = st.duration || '00:00:00';
      const statusVal = st.status || 'Pending';
      const statusClass = getStatusBadgeClass(statusVal);
      const statusIcon = getStatusIcon(statusVal);

      html += `
        <tr>
          <td class="col-sl">${slNo}</td>
          <td class="col-agent">
            <span class="agent-name">${escapeHtml(agentName)}</span>
          </td>
          <td class="col-subtask">
            <div class="subtask-name-cell">
              <span class="subtask-title-text">${escapeHtml(subtaskTitle)}</span>
              <div class="subtask-meta-pills">
                <span class="subtask-id-tag">${escapeHtml(subtaskId)}</span>
              </div>
            </div>
          </td>
          <td class="col-date">
            ${startDate ? `<span class="date-badge"><i class="fa-regular fa-calendar"></i> ${startDate}</span>` : '<span class="date-empty">—</span>'}
          </td>
          <td class="col-date">
            ${endDate ? `<span class="date-badge"><i class="fa-regular fa-calendar-check"></i> ${endDate}</span>` : '<span class="date-empty">—</span>'}
          </td>
          <td class="col-duration">
            <span class="duration-badge">
              <i class="fa-regular fa-clock"></i> ${escapeHtml(duration)}
            </span>
          </td>
          <td class="col-status">
            <span class="badge-status ${statusClass}">
              <i class="${statusIcon}"></i> ${escapeHtml(statusVal)}
            </span>
          </td>
        </tr>
      `;
    });

    $subtasksTableBody.html(html);
  }

  function filterSubtasks() {
    const query = $searchInput.val().trim().toLowerCase();
    if (!query) {
      renderSubtasksTable(allSubtasks);
      return;
    }

    const filtered = allSubtasks.filter(function (st) {
      const title = (st.title || '').toLowerCase();
      const subtaskId = (st.subtask_id || '').toLowerCase();
      const agent = (st.assigned_to || '').toLowerCase();
      const status = (st.status || '').toLowerCase();
      const startDate = (st.start_date || '').toLowerCase();
      const endDate = (st.end_date || '').toLowerCase();

      return title.includes(query) ||
        subtaskId.includes(query) ||
        agent.includes(query) ||
        status.includes(query) ||
        startDate.includes(query) ||
        endDate.includes(query);
    });

    renderSubtasksTable(filtered);
  }

  function loadProjectSummary() {
    showLoading();

    $.ajax({
      url: '../api/fetch-project-summary.php',
      type: 'GET',
      data: {
        project_id: projectId
      },
      dataType: 'json',
      success: function (response) {
        hideLoading();
        if (response.success) {
          renderProjectOverview(response.project);
          renderStats(response.stats);
          allSubtasks = response.subtasks || [];
          renderSubtasksTable(allSubtasks);
        } else {
          alert(response.message || 'Failed to load project summary.');
        }
      },
      error: function () {
        hideLoading();
        alert('An error occurred while loading project summary.');
      }
    });
  }

  // Live search input event
  $searchInput.on('input', filterSubtasks);

  // Print summary button
  $('#btnPrintSummary').on('click', function () {
    window.print();
  });

  // Initial load
  loadProjectSummary();
});
