$(document).ready(function () {
  const $cardsContainer = $('#projectsCardsContainer');
  const $loadingOverlay = $('#loadingOverlay');
  const $projectCountBadge = $('#projectCountBadge');
  const $searchInput = $('#search-input');
  const $categoryFilter = $('#category-filter');
  const $priorityFilter = $('#priority-filter');
  const $statusFilter = $('#status-filter');
  const $btnResetFilters = $('#btnResetFilters');
  const $btnToggleAll = $('#btnToggleAll');

  // Stats elements
  const $statProjectsVal = $('#statProjectsVal');
  const $statTasksVal = $('#statTasksVal');
  const $statPendingVal = $('#statPendingVal');
  const $statProgressVal = $('#statProgressVal');
  const $statCompletedVal = $('#statCompletedVal');

  let searchTimeout = null;
  let allProjectsData = [];
  let isAllExpanded = false;

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
    // if (p === 'high') return 'fa-solid fa-angles-up';
    // if (p === 'medium') return 'fa-solid fa-bars';
    // return 'fa-solid fa-angles-down';
  }

  function getCategoryIcon(category) {
    const c = (category || '').toLowerCase();
    // if (c === 'hardware') return 'fa-solid fa-server';
    // if (c === 'software') return 'fa-solid fa-code';
    // return 'fa-solid fa-microchip';
  }

  function getStatusBadgeClass(status) {
    const s = (status || '').toLowerCase();
    if (s === 'in progress') return 'badge-status-progress';
    if (s === 'completed') return 'badge-status-completed';
    if (s === 'pending') return 'badge-status-pending';
    if (s === 'on hold' || s === 'hold') return 'badge-status-hold';
    return 'badge-status-assigned';
  }

  function getStatusIcon(status) {
    const s = (status || '').toLowerCase();
    // if (s === 'in progress') return 'fa-solid fa-spinner';
    // if (s === 'completed') return 'fa-solid fa-circle-check';
    // if (s === 'pending') return 'fa-solid fa-clock';
    // if (s === 'on hold' || s === 'hold') return 'fa-solid fa-circle-pause';
    // return 'fa-solid fa-bars-progress';
  }

  function updatePageStats(stats) {
    if (!stats) return;
    $statProjectsVal.text(stats.total_projects || 0);
    $statTasksVal.text(stats.total_tasks || 0);
    $statPendingVal.text(stats.pending_tasks || 0);
    $statProgressVal.text(stats.in_progress_tasks || 0);
    $statCompletedVal.text(stats.completed_tasks || 0);
  }

  function loadMyProjects() {
    showLoading();

    const params = {
      search: $searchInput.val().trim(),
      category: $categoryFilter.val() || '',
      priority: $priorityFilter.val() || '',
      status: $statusFilter.val() || ''
    };

    $.ajax({
      url: '../api/fetch-my-projects.php',
      type: 'GET',
      data: params,
      dataType: 'json',
      success: function (response) {
        hideLoading();
        if (response.success && Array.isArray(response.data)) {
          allProjectsData = response.data;
          renderProjectsCards(response.data);
          updatePageStats(response.stats);
          $projectCountBadge.text(response.data.length + (response.data.length === 1 ? ' Project' : ' Projects'));
        } else {
          renderEmptyState('No assigned projects found.');
          $projectCountBadge.text('0 Projects');
        }
      },
      error: function () {
        hideLoading();
        renderEmptyState('An error occurred while fetching your projects.');
        $projectCountBadge.text('0 Projects');
      }
    });
  }

  function renderProjectsCards(projects) {
    if (!projects || projects.length === 0) {
      renderEmptyState();
      return;
    }

    let html = '';
    projects.forEach(function (proj, projIdx) {
      const pid = escapeHtml(proj.project_id);
      const name = escapeHtml(proj.project_name);
      const date = escapeHtml(proj.date || '—');
      const cat = escapeHtml(proj.category || '—');
      const prio = escapeHtml(proj.priority || 'Medium');
      const status = escapeHtml(proj.project_status || 'Assigned');
      const desc = escapeHtml(proj.project_description || '');
      const assignedBy = escapeHtml(proj.assigned_by || '—');
      const assignee = escapeHtml(proj.asignee || '—');
      const createdBy = escapeHtml(proj.created_by || '—');
      const createdAt = escapeHtml(proj.created_at ? proj.created_at.substring(0, 16) : '—');
      const createdByPhoto = proj.created_by_photo || '../images/default-avatar.png';
      const assigneePhoto = proj.asignee_photo || '../images/default-avatar.png';

      const prioClass = getPriorityBadgeClass(proj.priority);
      const prioIcon = getPriorityIcon(proj.priority);
      const catIcon = getCategoryIcon(proj.category);
      const statusClass = getStatusBadgeClass(status);
      const statusIcon = getStatusIcon(status);

      const subtasks = (proj.subtasks || []).filter(function (st) {
        return st.is_my_task !== false;
      });
      const stats = proj.stats || { total: 0, pending: 0, in_progress: 0, completed: 0, progress_percent: 0 };
      const progressPercent = stats.progress_percent || 0;
      const detailsUrl = `my-project-details.php?project_id=${encodeURIComponent(proj.project_id)}`;

      html += `
        <div class="project-card" data-project-id="${pid}" id="projectCard_${projIdx}">
          
          <!-- Clickable Header -->
          <div class="project-card-header" data-target="#projectBody_${projIdx}">
            <div class="project-header-main">
              <div class="project-id-badge-box">
                <span class="id-label">Project</span>
                <span class="id-value">${pid}</span>
              </div>

              <div class="project-title-meta-wrap">
                <div class="project-title-row">
                  <a href="${detailsUrl}" class="project-title-link" onclick="event.stopPropagation();">${name}</a>
                </div>
                <div class="project-meta-pills">
                  <span class="badge-category"><i class="${catIcon}"></i> ${cat}</span>
                  <span class="badge-priority ${prioClass}"><i class="${prioIcon}"></i> ${prio}</span>
                  <span class="badge-status ${statusClass}"><i class="${statusIcon}"></i> ${status}</span>
                  <span class="project-date-chip"><i class="fa-regular fa-calendar"></i> ${date}</span>
                </div>
              </div>
            </div>

            <div class="project-header-right">

              <div class="task-stats-pills">
                <span class="task-chip total-task-chip" title="Total Tasks">
                  <i class="fa-solid fa-list-check"></i> ${stats.total}
                </span>
                ${stats.pending > 0 ? `
                  <span class="task-chip pending-chip" title="Pending Tasks">
                    <i class="fa-solid fa-clock"></i> ${stats.pending}
                  </span>
                ` : ''}
                ${stats.completed > 0 ? `
                  <span class="task-chip completed-chip" title="Completed Tasks">
                    <i class="fa-solid fa-circle-check"></i> ${stats.completed}
                  </span>
                ` : ''}
              </div>

              <a href="${detailsUrl}" class="btn-open-details" title="Open Full Project Details" onclick="event.stopPropagation();">
                <i class="fa-solid fa-arrow-up-right-from-square"></i>
              </a>

              <div class="accordion-chevron">
                <i class="fa-solid fa-chevron-down"></i>
              </div>
            </div>
          </div>

          <!-- Expandable Body Content -->
          <div class="project-card-body" id="projectBody_${projIdx}">
            
            <!-- Project Meta Details Grid -->
            <div class="project-info-grid">
              <div class="project-info-item">
                <span class="info-label"><i class="fa-solid fa-user-tie"></i> Assigned By</span>
                <span class="info-value">${assignedBy}</span>
              </div>

              <div class="project-info-item">
                <span class="info-label"><i class="fa-solid fa-user"></i> Created By</span>
                <span class="info-value">
                  <span class="user-chip">
                    <span>${createdBy}</span>
                  </span>
                </span>
              </div>
              <div class="project-info-item">
                <span class="info-label"><i class="fa-regular fa-clock"></i> Created At</span>
                <span class="info-value">${createdAt}</span>
              </div>
            </div>

            ${desc ? `
              <div class="project-desc-row">
                <span class="info-label"><i class="fa-solid fa-align-left"></i> Description</span>
                <div class="project-desc-text">${desc}</div>
              </div>
            ` : ''}

            <!-- Subtasks Section -->
            <div class="subtasks-section-wrapper">
              <div class="subtasks-section-header">
                <div class="subtasks-header-title">
                  <i class="fa-solid fa-tasks" style="color: var(--accent-teal);"></i>
                  <h3>Tasks Assigned to You</h3>
                  <span class="subtasks-count-pill">${subtasks.length} Task${subtasks.length === 1 ? '' : 's'}</span>
                </div>
              </div>

              ${renderSubtasksTableHtml(subtasks, pid)}
            </div>

          </div>

        </div>
      `;
    });

    $cardsContainer.html(html);
  }

  function renderSubtasksTableHtml(subtasks, projectId) {
    if (!subtasks || subtasks.length === 0) {
      return `
        <div style="padding: 32px 20px; text-align: center; color: var(--text-secondary);">
          <i class="fa-solid fa-clipboard-list" style="font-size: 32px; color: var(--text-muted); margin-bottom: 8px; display: block;"></i>
          <p style="margin: 0; font-size: 13px;">No tasks assigned to you in this project.</p>
        </div>
      `;
    }

    let rowsHtml = '';
    subtasks.forEach(function (st) {
      const isCompleted = (st.status || '').toLowerCase() === 'completed';
      const stPrioClass = getPriorityBadgeClass(st.priority);
      const stPrioIcon = getPriorityIcon(st.priority);
      const assignedPhoto = st.assigned_to_photo || '../images/default-avatar.png';
      const createdByPhoto = st.created_by_photo || '../images/default-avatar.png';

      const stStatusVal = st.status || 'Pending';
      const stStatusClass = getStatusBadgeClass(stStatusVal);
      const stStatusIcon = getStatusIcon(stStatusVal);

      rowsHtml += `
        <tr class="${isCompleted ? 'subtask-completed-row' : ''} ${st.is_my_task ? 'subtask-highlight-row' : ''}" data-subtask-id="${st.id}" data-project-id="${projectId}">
          <td style="width: 130px;">
            <span class="subtask-id-pill">${escapeHtml(st.subtask_id || '—')}</span>
          </td>
          <td>
            <div class="subtask-title-cell">
              <div>
                <span class="subtask-title-text">${escapeHtml(st.title)}</span>
              </div>
              ${st.description ? `<span class="subtask-desc-text">${escapeHtml(st.description)}</span>` : ''}
            </div>
          </td>
          <td style="width: 110px;">
            <span class="badge-priority ${stPrioClass}"><i class="${stPrioIcon}"></i> ${escapeHtml(st.priority)}</span>
          </td>
          <td style="width: 120px;">
            <span style="font-size: 12px; color: var(--text-secondary);">${st.due_date ? escapeHtml(st.due_date) : '—'}</span>
          </td>
          <td style="width: 140px;">
            <span class="badge-status ${stStatusClass}">
              <i class="${stStatusIcon}"></i> ${escapeHtml(stStatusVal)}
            </span>
          </td>
        </tr>
      `;
    });

    return `
      <div class="subtasks-table-responsive">
        <table class="subtasks-table">
          <thead>
            <tr>
              <th>Task ID</th>
              <th>Task Details</th>
              <th>Priority</th>
              <th>Due Date</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            ${rowsHtml}
          </tbody>
        </table>
      </div>
    `;
  }

  function renderEmptyState(message) {
    $cardsContainer.html(`
      <div class="empty-state-card">
        <div class="empty-icon-box">
          <i class="fa-solid fa-folder-open"></i>
        </div>
        <h2>No Projects Found</h2>
        <p>${escapeHtml(message)}</p>
      </div>
    `);
  }

  // Handle Accordion Header Click
  $cardsContainer.on('click', '.project-card-header', function (e) {
    if ($(e.target).closest('a, button, select, input').length) {
      return;
    }
    const $card = $(this).closest('.project-card');
    const $body = $card.find('.project-card-body');

    $body.slideToggle(250, function () {
      if ($body.is(':visible')) {
        $card.addClass('expanded');
      } else {
        $card.removeClass('expanded');
      }
    });
  });

  // Toggle Expand All / Collapse All
  $btnToggleAll.on('click', function () {
    isAllExpanded = !isAllExpanded;
    if (isAllExpanded) {
      $('.project-card-body').slideDown(250);
      $('.project-card').addClass('expanded');
      $btnToggleAll.html('<i class="fa-solid fa-compress"></i> Collapse All');
    } else {
      $('.project-card-body').slideUp(250);
      $('.project-card').removeClass('expanded');
      $btnToggleAll.html('<i class="fa-solid fa-expand"></i> Expand All');
    }
  });

  // Filter Events
  $searchInput.on('input', function () {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(function () {
      loadMyProjects();
    }, 300);
  });

  $categoryFilter.on('change', loadMyProjects);
  $priorityFilter.on('change', loadMyProjects);
  $statusFilter.on('change', loadMyProjects);

  $btnResetFilters.on('click', function () {
    $searchInput.val('');
    $categoryFilter.val('');
    $priorityFilter.val('');
    $statusFilter.val('');
    loadMyProjects();
  });

  // Initial Load
  loadMyProjects();
});
